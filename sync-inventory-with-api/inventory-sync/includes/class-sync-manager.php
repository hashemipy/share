<?php

/**
 * Inventory_Sync_Manager - مدیریت هماهنگ‌سازی موجودی
 * 
 * این کلاس مسئول:
 * 1. انتقال محصولات از سایت 1 به سایت 2
 * 2. هماهنگ‌سازی موجودی بین سایت‌ها
 * 3. شنیدن فروش‌ها و به‌روز رسانی خودکار
 * 4. مدیریت Retry Logic برای خرابی‌های API
 */
class Inventory_Sync_Manager {
    
    private static $instance = null;
    private $site1_api;
    private $site2_api;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->init_apis();
        $this->init_hooks();
    }
    
    /**
     * شنیدن تغییرات موجودی و فروش‌ها
     */
    private function init_hooks() {
        // وقتی سفارش کامل شود، موجودی را sync کن
        add_action('woocommerce_order_status_completed', [$this, 'sync_on_order'], 10, 1);
        
        // وقتی موجودی دستی تغییر کند
        add_action('woocommerce_product_set_stock', [$this, 'sync_on_stock_change'], 10, 1);
    }
    
    /**
     * هنگام تکمیل سفارش - موجودی را sync کن
     */
    public function sync_on_order($order_id) {
        if (!Inventory_Sync_Settings::get_auto_sync_enabled()) {
            return;
        }
        
        // برنامه‌ریزی کن که 5 ثانیه بعد sync شود (تا سایت وقت داشته باشد)
        wp_schedule_single_event(
            time() + 5,
            'inventory_sync_immediate'
        );
    }
    
    /**
     * هنگام تغییر موجودی - تمام mapped محصولات را sync کن
     */
    public function sync_on_stock_change($product) {
        if (!Inventory_Sync_Settings::get_auto_sync_enabled()) {
            return;
        }
        
        global $wpdb;
        
        // پیدا کن این محصول در کدام mapping قرار دارد
        $mapping = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE (site1_product_id = %d OR site2_product_id = %d) 
                 AND sync_enabled = 1 LIMIT 1",
                $product->get_id(),
                $product->get_id()
            )
        );
        
        if ($mapping) {
            // برنامه‌ریزی کن برای sync
            wp_schedule_single_event(
                time() + 3,
                'inventory_sync_mapping',
                [$mapping->id]
            );
        }
    }
    
    private function init_apis() {
        $this->site1_api = new Inventory_Sync_API(
            Inventory_Sync_Settings::get_site1_url(),
            Inventory_Sync_Settings::get_site1_key(),
            Inventory_Sync_Settings::get_site1_secret()
        );
        
        $this->site2_api = new Inventory_Sync_API(
            Inventory_Sync_Settings::get_site2_url(),
            Inventory_Sync_Settings::get_site2_key(),
            Inventory_Sync_Settings::get_site2_secret()
        );
    }
    
    /**
     * هماهنگ‌سازی موجودی بین دو سایت
     * 
     * @param int $mapping_id - شناسه mapping
     * @return bool|WP_Error
     */
    public function sync_inventory($mapping_id) {
        global $wpdb;
        
        $mapping = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping WHERE id = %d",
                $mapping_id
            )
        );
        
        if (!$mapping) {
            return new WP_Error('not_found', 'نقشه‌برداری پیدا نشد');
        }
        
        $direction = Inventory_Sync_Settings::get_sync_direction();
        
        // جهت سایت 1 → سایت 2
        if ($direction === 'site1_to_site2' || $direction === 'bidirectional') {
            $this->sync_site_to_site(
                $this->site1_api,
                $this->site2_api,
                $mapping->site1_product_id,
                $mapping->site2_product_id,
                'سایت 1',
                'سایت 2',
                $mapping
            );
        }
        
        // جهت سایت 2 → سایت 1
        if ($direction === 'site2_to_site1' || $direction === 'bidirectional') {
            $this->sync_site_to_site(
                $this->site2_api,
                $this->site1_api,
                $mapping->site2_product_id,
                $mapping->site1_product_id,
                'سایت 2',
                'سایت 1',
                $mapping
            );
        }
        
        // اپدیت موفقیت
        $wpdb->update(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'sync_status' => 'synced',
                'last_sync' => current_time('mysql'),
                'error_message' => ''
            ],
            ['id' => $mapping_id]
        );
        
        return true;
    }
    
    /**
     * موجودی را از یک سایت به سایت دیگر انتقال بده
     * 
     * @param Inventory_Sync_API $from_api - API مبدا
     * @param Inventory_Sync_API $to_api - API مقصد
     * @param int $from_id - شناسه محصول مبدا
     * @param int $to_id - شناسه محصول مقصد
     * @param string $from_name - نام سایت مبدا
     * @param string $to_name - نام سایت مقصد
     * @param object $mapping - اطلاعات mapping
     */
    private function sync_site_to_site($from_api, $to_api, $from_id, $to_id, $from_name, $to_name, $mapping) {
        // دریافت محصول از سایت مبدا
        $product = $from_api->get_product($from_id);
        
        if (is_wp_error($product)) {
            Inventory_Sync_Database::insert_log(
                $from_id,
                '',
                'sync_inventory',
                $from_name,
                $to_name,
                '',
                '',
                'failed',
                $product->get_error_message()
            );
            return;
        }
        
        $stock = isset($product['stock_quantity']) ? intval($product['stock_quantity']) : 0;
        
        // اپدیت موجودی در سایت مقصد
        $update_result = $to_api->update_product_stock($to_id, $stock);
        
        if (is_wp_error($update_result)) {
            // قرار بده برای دوباره تلاش (Retry Logic)
            $retry_count = intval($mapping->error_message ?? 0) + 1;
            
            if ($retry_count < 3) {
                // دوباره تلاش کن
                wp_schedule_single_event(
                    time() + (60 * $retry_count), // بعد از 1، 2، 3 دقیقه تلاش کن
                    'inventory_sync_mapping',
                    [$mapping->id]
                );
            }
            
            Inventory_Sync_Database::insert_log(
                $from_id,
                $product['name'] ?? '',
                'sync_inventory',
                $from_name,
                $to_name,
                '',
                $stock,
                'failed',
                $update_result->get_error_message() . " (تلاش $retry_count/3)"
            );
            return;
        }
        
        // لاگ موفقیت
        Inventory_Sync_Database::insert_log(
            $from_id,
            $product['name'] ?? '',
            'sync_inventory',
            $from_name,
            $to_name,
            '',
            $stock,
            'success'
        );
    }
    
    /**
     * انتقال محصول
     */
    public function transfer_product($site1_product_id, $site2_product_id = null) {
        // Get product from Site 1
        $product1 = $this->site1_api->get_product($site1_product_id);
        
        if (is_wp_error($product1)) {
            return $product1;
        }
        
        // ساخت محصول والد (ساده یا متغیّر)
        $product_data = $this->prepare_transfer_data($product1);
        $result = $this->site2_api->create_product($product_data);
        
        if (is_wp_error($result)) {
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product1['name'] ?? '',
                'transfer_product',
                'سایت 1',
                'سایت 2',
                '',
                '',
                'failed',
                $result->get_error_message()
            );
            return $result;
        }
        
        // اگر محصول متغیّر است، متغیّرها (variations) را هم منتقل کن
        if (($product1['type'] ?? 'simple') === 'variable') {
            $variation_result = $this->transfer_variations($site1_product_id, $result['id'], $product1['name'] ?? '');
            if (is_wp_error($variation_result)) {
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product1['name'] ?? '',
                    'transfer_variations',
                    'سایت 1',
                    'سایت 2',
                    '',
                    $result['id'],
                    'failed',
                    $variation_result->get_error_message()
                );
            }
        }
        
        // Save mapping
        $this->save_mapping(
            $site1_product_id,
            $result['id'],
            $product1['sku'] ?? '',
            $result['sku'] ?? ''
        );
        
        // Log success
        Inventory_Sync_Database::insert_log(
            $site1_product_id,
            $product1['name'] ?? '',
            'transfer_product',
            'سایت 1',
            'سایت 2',
            '',
            $result['id'],
            'success'
        );
        
        return $result;
    }
    
    /**
     * انتقال متغیّرهای یک محصول متغیّر از سایت ۱ به محصول والد ساخته‌شده در سایت ۲
     * 
     * متغیّرها در WooCommerce بخشی از آبجکت محصول نیستند و باید جداگانه
     * از endpoint /products/{id}/variations دریافت و در مقصد ساخته شوند.
     */
    private function transfer_variations($site1_product_id, $site2_parent_id, $product_name) {
        $all_variations = [];
        $page = 1;
        
        // دریافت همه‌ی متغیّرها با صفحه‌بندی
        do {
            $variations = $this->site1_api->get_product_variations($site1_product_id, 100, $page);
            
            if (is_wp_error($variations)) {
                return $variations;
            }
            
            if (empty($variations) || ! is_array($variations)) {
                break;
            }
            
            $all_variations = array_merge($all_variations, $variations);
            $page++;
        } while (count($variations) === 100);
        
        if (empty($all_variations)) {
            return true;
        }
        
        // آماده‌سازی داده‌ی هر متغیّر برای مقصد
        $variations_to_create = [];
        foreach ($all_variations as $variation) {
            $variations_to_create[] = $this->prepare_variation_data($variation);
        }
        
        // ساخت گروهی متغیّرها در سایت مقصد
        return $this->site2_api->batch_create_variations($site2_parent_id, $variations_to_create);
    }
    
    /**
     * آماده‌سازی داده‌ی یک متغیّر برای انتقال به سایت مقصد
     * (حذف id ها و ارجاع‌های مخصوص سایت مبدأ)
     */
    private function prepare_variation_data($variation) {
        $data = [
            'sku' => $variation['sku'] ?? '',
            'regular_price' => isset($variation['regular_price']) ? (string) $variation['regular_price'] : '',
            'sale_price' => isset($variation['sale_price']) ? (string) $variation['sale_price'] : '',
            'description' => $variation['description'] ?? '',
            'manage_stock' => $variation['manage_stock'] ?? false,
            'stock_quantity' => isset($variation['stock_quantity']) ? intval($variation['stock_quantity']) : null,
            'stock_status' => $variation['stock_status'] ?? 'instock',
            // ویژگی‌های متغیّر فقط با name/option (بدون id سایت مبدأ) تا با ویژگی‌های والد مطابقت کند
            'attributes' => $this->prepare_variation_attributes($variation['attributes'] ?? []),
        ];
        
        // اگر مدیریت موجودی فعال نیست، stock_quantity را نفرست
        if (empty($data['manage_stock'])) {
            unset($data['stock_quantity']);
        }
        
        // تصویر متغیّر (فقط src)
        if (! empty($variation['image']['src'])) {
            $data['image'] = ['src' => $variation['image']['src']];
        }
        
        return $data;
    }
    
    /**
     * پاک‌سازی ویژگی‌های یک متغیّر: حذف id و نگه‌داشتن name/option
     */
    private function prepare_variation_attributes($attributes) {
        if (empty($attributes) || ! is_array($attributes)) {
            return [];
        }
        
        $clean = [];
        foreach ($attributes as $attr) {
            if (empty($attr['name'])) {
                continue;
            }
            $clean[] = [
                'name'   => $attr['name'],
                'option' => $attr['option'] ?? '',
            ];
        }
        
        return $clean;
    }
    
    /**
     * آماده‌سازی داده‌های انتقالی
     */
    private function prepare_transfer_data($product1) {
        $type = $product1['type'] ?? 'simple';
        
        $data = [
            'name' => $product1['name'] ?? '',
            // نوع محصول را منتقل کن (simple / variable / ...) وگرنه همه ساده ساخته می‌شوند
            'type' => $type,
            'description' => $product1['description'] ?? '',
            'short_description' => $product1['short_description'] ?? '',
            'sku' => $product1['sku'] ?? '',
            // قیمت‌ها را منتقل کن (برای محصول متغیّر قیمت روی خود متغیّرها تنظیم می‌شود)
            'regular_price' => isset($product1['regular_price']) ? (string) $product1['regular_price'] : '',
            'sale_price' => isset($product1['sale_price']) ? (string) $product1['sale_price'] : '',
            // فقط آدرس (src) تصاویر را می‌فرستیم؛ ارسال id تصاویر سایت ۱ باعث خطای
            // woocommerce_product_invalid_image_id در سایت ۲ می‌شود چون آن id ها در سایت مقصد وجود ندارند.
            'images' => $this->prepare_images($product1['images'] ?? []),
            // دسته‌ها و برچسب‌ها را با name می‌فرستیم نه id سایت ۱ که در سایت ۲ نامعتبر است.
            'categories' => $this->prepare_terms($product1['categories'] ?? []),
            'tags' => $this->prepare_terms($product1['tags'] ?? []),
            // ویژگی‌ها را پاک‌سازی کن: حذف id سایت مبدأ + حفظ options و فلگ variation
            'attributes' => $this->prepare_attributes($product1['attributes'] ?? []),
            'status' => 'draft' // منتشر نشود تا مدیر بررسی کند
        ];
        
        // موجودی فقط برای محصول ساده روی خود محصول تنظیم می‌شود؛
        // در محصول متغیّر موجودی روی هر متغیّر (variation) تنظیم می‌گردد.
        if ($type !== 'variable') {
            $data['manage_stock'] = $product1['manage_stock'] ?? false;
            if (! empty($data['manage_stock'])) {
                $data['stock_quantity'] = isset($product1['stock_quantity']) ? intval($product1['stock_quantity']) : 0;
            }
            $data['stock_status'] = $product1['stock_status'] ?? 'instock';
        }
        
        return $data;
    }
    
    /**
     * پاک‌سازی ویژگی‌های محصول والد:
     * - حذف id ویژگی سایت مبدأ (id=0 یعنی ویژگی سفارشی محصول، در مقصد معتبر است)
     * - حفظ options و فلگ‌های variation/visible
     */
    private function prepare_attributes($attributes) {
        if (empty($attributes) || ! is_array($attributes)) {
            return [];
        }
        
        $clean = [];
        foreach ($attributes as $attr) {
            if (empty($attr['name'])) {
                continue;
            }
            $clean[] = [
                'name'      => $attr['name'],
                'position'  => isset($attr['position']) ? intval($attr['position']) : 0,
                'visible'   => isset($attr['visible']) ? (bool) $attr['visible'] : true,
                'variation' => isset($attr['variation']) ? (bool) $attr['variation'] : false,
                'options'   => $attr['options'] ?? [],
            ];
        }
        
        return $clean;
    }
    
    /**
     * پاک‌سازی تصاویر: حذف id سایت مبدأ و نگه‌داشتن فقط src/name/alt
     * تا سایت مقصد خودش تصویر را از روی آدرس دانلود و آپلود کند.
     */
    private function prepare_images($images) {
        if (empty($images) || ! is_array($images)) {
            return [];
        }
        
        $clean = [];
        foreach ($images as $image) {
            if (empty($image['src'])) {
                continue;
            }
            $new_image = ['src' => $image['src']];
            if (! empty($image['name'])) {
                $new_image['name'] = $image['name'];
            }
            if (! empty($image['alt'])) {
                $new_image['alt'] = $image['alt'];
            }
            $clean[] = $new_image;
        }
        
        return $clean;
    }
    
    /**
     * پاک‌سازی دسته‌ها/برچسب‌ها: حذف id سایت مبدأ و نگه‌داشتن فقط name
     * تا سایت مقصد بر اساس نام، term موجود را پیدا یا ایجاد کند.
     */
    private function prepare_terms($terms) {
        if (empty($terms) || ! is_array($terms)) {
            return [];
        }
        
        $clean = [];
        foreach ($terms as $term) {
            if (! empty($term['name'])) {
                $clean[] = ['name' => $term['name']];
            }
        }
        
        return $clean;
    }
    
    /**
     * ذخیره نقشه‌برداری
     */
    private function save_mapping($site1_id, $site2_id, $site1_sku, $site2_sku) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'site1_product_id' => $site1_id,
                'site2_product_id' => $site2_id,
                'site1_sku' => $site1_sku,
                'site2_sku' => $site2_sku,
                'sync_enabled' => 1,
                'sync_status' => 'synced'
            ]
        );
    }
}
