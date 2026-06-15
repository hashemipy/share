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
     * انتقال محصول شامل دسته‌بندی‌ها، ویژگی‌ها و متغیّرها
     * 
     * @param int $site1_product_id شناسه محصول در سایت 1
     * @return array|WP_Error
     */
    public function transfer_product($site1_product_id) {
        // دریافت محصول از سایت 1
        $product1 = $this->site1_api->get_product($site1_product_id);
        
        if (is_wp_error($product1)) {
            return $product1;
        }
        
        $product_name = $product1['name'] ?? 'محصول بدون نام';
        $original_sku = $product1['sku'] ?? '';
        
        // ۱. بررسی اینکه محصول در سایت 2 قبلاً وجود ندارد
        $existing_product = $this->site2_api->product_exists_by_sku($original_sku);
        $site2_product_id = null;
        
        if ($existing_product && !empty($existing_product['id'])) {
            // محصول موجود است - بروز کن
            $site2_product_id = $existing_product['id'];
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'product_exists',
                'سایت 1',
                'سایت 2',
                '',
                $site2_product_id,
                'info',
                "محصول با SKU ({$original_sku}) در سایت 2 موجود است. بروز‌رسانی شد."
            );
        } else {
            // محصول جدید - بررسی SKU منحصر‌به‌فردی
            $sku_to_use = $original_sku;
            
            // اگر SKU خالی است، یک SKU منحصر‌به‌فردی تولید کن
            if (empty($sku_to_use)) {
                $sku_to_use = 'prod-' . time() . '-' . $site1_product_id;
            }
            
            $product1['sku'] = $sku_to_use;
        }
        
        // ۲. انتقال دسته‌بندی‌ها و ویژگی‌ها
        $category_attr_sync = new Inventory_Sync_Category_Attribute_Sync(
            $this->site1_api,
            $this->site2_api
        );
        
        // انتقال دسته‌بندی‌ها
        $category_map = $category_attr_sync->sync_product_categories(
            $product1['categories'] ?? []
        );
        
        // انتقال ویژگی‌ها
        $attribute_map = $category_attr_sync->sync_product_attributes(
            $product1['attributes'] ?? []
        );
        
        // ۳. آماده‌سازی داده‌های محصول برای سایت 2
        $product_data = $this->prepare_transfer_data($product1);
        
        // اپدیت دسته‌بندی‌های محصول با mapping جدید
        if (!empty($category_map)) {
            $new_categories = [];
            foreach (($product1['categories'] ?? []) as $cat) {
                $site1_cat_id = $cat['id'] ?? 0;
                // بررسی اینکه آیا برای این دسته‌بندی mapping داریم
                if (isset($category_map[$site1_cat_id])) {
                    $site2_cat_id = $category_map[$site1_cat_id];
                    $new_categories[] = ['id' => $site2_cat_id];
                }
            }
            if (!empty($new_categories)) {
                $product_data['categories'] = $new_categories;
            }
        }
        
        // ۴. ایجاد یا بروز‌رسانی محصول در سایت 2
        if ($site2_product_id !== null) {
            // محصول موجود - بروز کن
            $result = $this->site2_api->update_product($site2_product_id, $product_data);
        } else {
            // محصول جدید - ایجاد کن
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'create_product_start',
                'سایت 1',
                'سایت 2',
                json_encode(['sku' => $product_data['sku'], 'categories_count' => count($product_data['categories'] ?? [])]),
                '',
                'info',
                'شروع ایجاد محصول جدید'
            );
            
            $result = $this->site2_api->create_product($product_data);
        }
        
        if (is_wp_error($result)) {
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'transfer_product_error',
                'سایت 1',
                'سایت 2',
                json_encode($product_data),
                $site2_product_id ?? '',
                'failed',
                $result->get_error_message()
            );
            
            Inventory_Sync_Database::add_transferred_product(
                $site1_product_id,
                $site2_product_id ?? 0,
                $product_name,
                'failed',
                $result->get_error_message()
            );
            
            return $result;
        }
        
        $site2_product_id = $result['id'];
        
        // ۵. اگر محصول متغیّر است، متغیّرها را منتقل کن
        if (($product1['type'] ?? 'simple') === 'variable') {
            $variation_result = $this->transfer_variations($site1_product_id, $site2_product_id, $product_name);
            if (is_wp_error($variation_result)) {
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product_name,
                    'transfer_variations',
                    'سایت 1',
                    'سایت 2',
                    '',
                    $site2_product_id,
                    'failed',
                    $variation_result->get_error_message()
                );
            }
        }
        
        // ۶. ذخیره mapping
        $this->save_mapping(
            $site1_product_id,
            $site2_product_id,
            $product1['sku'] ?? '',
            $result['sku'] ?? ''
        );
        
        // ۷. ثبت محصول منتقل‌شده
        Inventory_Sync_Database::add_transferred_product(
            $site1_product_id,
            $site2_product_id,
            $product_name,
            'success'
        );
        
        // لاگ موفقیت
        Inventory_Sync_Database::insert_log(
            $site1_product_id,
            $product_name,
            'transfer_product',
            'سایت 1',
            'سایت 2',
            '',
            $site2_product_id,
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
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product_name,
                    'get_variations_error',
                    'سایت 1',
                    'سایت 2',
                    '',
                    '',
                    'failed',
                    $variations->get_error_message()
                );
                return $variations;
            }
            
            if (empty($variations) || !is_array($variations)) {
                break;
            }
            
            $all_variations = array_merge($all_variations, $variations);
            $page++;
        } while (count($variations) === 100);
        
        if (empty($all_variations)) {
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'no_variations',
                'سایت 1',
                'سایت 2',
                '',
                $site2_parent_id,
                'info',
                'هیچ متغیّری برای انتقال وجود ندارد'
            );
            return true;
        }
        
        // آماده‌سازی داده‌ی هر متغیّر برای مقصد
        $variations_to_create = [];
        foreach ($all_variations as $variation) {
            $variation_data = $this->prepare_variation_data($variation);
            $variations_to_create[] = $variation_data;
        }
        
        Inventory_Sync_Database::insert_log(
            $site1_product_id,
            $product_name,
            'create_variations_start',
            'سایت 1',
            'سایت 2',
            json_encode(['count' => count($all_variations), 'parent_id' => $site2_parent_id]),
            $site2_parent_id,
            'info',
            'شروع ایجاد ' . count($all_variations) . ' متغیّر'
        );
        
        // ساخت گروهی متغیّرها در سایت مقصد
        $result = $this->site2_api->batch_create_variations($site2_parent_id, $variations_to_create);
        
        if (is_wp_error($result)) {
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'batch_create_variations_error',
                'سایت 1',
                'سایت 2',
                json_encode($variations_to_create),
                $site2_parent_id,
                'failed',
                $result->get_error_message()
            );
        } else {
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'variations_created',
                'سایت 1',
                'سایت 2',
                '',
                $site2_parent_id,
                'success',
                count($all_variations) . ' متغیّر با موفقیت ایجاد شد'
            );
        }
        
        return $result;
    }
    
    /**
     * آماده‌سازی داده‌ی یک متغیّر برای انتقال به سایت مقصد
     * شامل: قیمت، موجودی، ویژگی‌ها و تصویر
     */
    private function prepare_variation_data($variation) {
        // پایه‌ی داده‌های متغیّر
        $data = [
            'sku' => $variation['sku'] ?? '',
            'regular_price' => isset($variation['regular_price']) ? (string) $variation['regular_price'] : '',
            'sale_price' => isset($variation['sale_price']) ? (string) $variation['sale_price'] : '',
            'description' => $variation['description'] ?? '',
            'manage_stock' => $variation['manage_stock'] ?? false,
            'stock_status' => $variation['stock_status'] ?? 'instock',
            // ویژگی‌های متغیّر - بسیار مهم!
            'attributes' => $this->prepare_variation_attributes($variation['attributes'] ?? []),
        ];
        
        // موجودی
        if (!empty($data['manage_stock'])) {
            $data['stock_quantity'] = isset($variation['stock_quantity']) ? intval($variation['stock_quantity']) : 0;
        }
        
        // تصویر متغیّر (اگر موجود بود)
        if (!empty($variation['image'])) {
            if (is_array($variation['image']) && !empty($variation['image']['src'])) {
                $data['image'] = ['src' => $variation['image']['src']];
            } elseif (is_string($variation['image'])) {
                $data['image'] = ['src' => $variation['image']];
            }
        }
        
        return $data;
    }
    
    /**
     * آماده‌سازی ویژگی‌های یک متغیّر برای انتقال
     * 
     * WooCommerce API نیاز دارد:
     * - برای ویژگی عمومی: {'id': X} یا {'name': 'رنگ'}
     * - برای ویژگی متغیّر: {'option': 'سبز'} (نام تکیه)
     */
    private function prepare_variation_attributes($attributes) {
        if (empty($attributes) || !is_array($attributes)) {
            return [];
        }
        
        $clean = [];
        foreach ($attributes as $attr) {
            if (empty($attr['name']) && empty($attr['id'])) {
                continue;
            }
            
            $attr_item = [];
            
            // اگر ID موجود است (ویژگی عمومی)
            if (!empty($attr['id']) && $attr['id'] !== 0) {
                $attr_item['id'] = intval($attr['id']);
            } elseif (!empty($attr['name'])) {
                // اگر ID نیست، از name استفاده کن
                $attr_item['name'] = $attr['name'];
            }
            
            // تکیه (option) - نام مقدار ویژگی
            if (!empty($attr['option'])) {
                $attr_item['option'] = $attr['option'];
            }
            
            if (!empty($attr_item)) {
                $clean[] = $attr_item;
            }
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
            'type' => $type,
            'description' => $product1['description'] ?? '',
            'short_description' => $product1['short_description'] ?? '',
            'sku' => $product1['sku'] ?? '',
            'regular_price' => isset($product1['regular_price']) ? (string) $product1['regular_price'] : '',
            'sale_price' => isset($product1['sale_price']) ? (string) $product1['sale_price'] : '',
            'images' => $this->prepare_images($product1['images'] ?? []),
            // دسته‌ها و برچسب‌ها را به‌صورت ID فرستاده‌اند (نه name)
            'categories' => $this->prepare_terms_as_ids($product1['categories'] ?? []),
            'tags' => $this->prepare_terms_as_ids($product1['tags'] ?? []),
            'attributes' => $this->prepare_attributes($product1['attributes'] ?? []),
            'status' => 'draft'
        ];
        
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
     * تبدیل دسته‌ها/برچسب‌ها به آرایه‌ای از ID‌ها
     */
    private function prepare_terms_as_ids($terms) {
        if (empty($terms) || !is_array($terms)) {
            return [];
        }
        
        $clean = [];
        foreach ($terms as $term) {
            if (isset($term['id']) && !empty($term['id'])) {
                $clean[] = ['id' => intval($term['id'])];
            }
        }
        
        return $clean;
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
