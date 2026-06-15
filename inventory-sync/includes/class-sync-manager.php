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
    // کش ویژگی‌های سایت ۲ برای جلوگیری از فراخوانی مکرر API
    private $site2_attributes_cache = null;
    
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
        
        // وقتی موجودی دستی تغییر کند (محصول ساده)
        add_action('woocommerce_product_set_stock', [$this, 'sync_on_stock_change'], 10, 1);
        // وقتی موجودی یک واریاسیون تغییر کند
        add_action('woocommerce_variation_set_stock', [$this, 'sync_on_stock_change'], 10, 1);
        // وقتی محصول ذخیره/ویرایش شود (پوشش تغییرات دستی موجودی در ادمین)
        add_action('woocommerce_update_product', [$this, 'sync_on_product_update'], 20, 1);
        
        // handler رویدادهای زمان‌بندی‌شده
        add_action('inventory_sync_mapping', [$this, 'sync_inventory'], 10, 1);
        add_action('inventory_sync_immediate', [$this, 'sync_all_mappings'], 10, 0);
    }
    
    /**
     * هنگام بروزرسانی/ذخیره محصول، اگر در mapping باشد sync کن
     */
    public function sync_on_product_update($product_id) {
        if (!Inventory_Sync_Settings::get_auto_sync_enabled()) {
            return;
        }
        
        global $wpdb;
        $mapping = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE (site1_product_id = %d OR site2_product_id = %d) 
                 AND sync_enabled = 1 LIMIT 1",
                $product_id,
                $product_id
            )
        );
        
        if ($mapping) {
            wp_schedule_single_event(time() + 5, 'inventory_sync_mapping', [$mapping->id]);
        }
    }
    
    /**
     * sync همه‌ی mappingهای فعال
     */
    public function sync_all_mappings() {
        global $wpdb;
        $mappings = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping WHERE sync_enabled = 1"
        );
        
        foreach ($mappings as $m) {
            $this->sync_inventory($m->id);
        }
    }
    
    /**
     * هنگام تکمیل سفارش - موجودی را sync کن
     */
    public function sync_on_order($order_id) {
        if (!Inventory_Sync_Settings::get_auto_sync_enabled()) {
            return;
        }
        
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
        
        if (!is_object($product) || !method_exists($product, 'get_id')) {
            return;
        }
        
        $product_id = $product->get_id();
        if (method_exists($product, 'get_parent_id') && $product->get_parent_id()) {
            $product_id = $product->get_parent_id();
        }
        
        global $wpdb;
        
        $mapping = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE (site1_product_id = %d OR site2_product_id = %d) 
                 AND sync_enabled = 1 LIMIT 1",
                $product_id,
                $product_id
            )
        );
        
        if ($mapping) {
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
     */
    private function sync_site_to_site($from_api, $to_api, $from_id, $to_id, $from_name, $to_name, $mapping) {
        $product = $from_api->get_product($from_id);
        
        if (is_wp_error($product)) {
            Inventory_Sync_Database::insert_log(
                $from_id, '', 'sync_inventory', $from_name, $to_name, '', '', 'failed', $product->get_error_message()
            );
            return;
        }
        
        if (($product['type'] ?? 'simple') === 'variable') {
            $this->sync_variable_stock($from_api, $to_api, $from_id, $to_id, $from_name, $to_name, $product['name'] ?? '');
            return;
        }
        
        $stock = isset($product['stock_quantity']) ? intval($product['stock_quantity']) : 0;
        $update_result = $to_api->update_product_stock($to_id, $stock);
        
        if (is_wp_error($update_result)) {
            $retry_count = intval($mapping->error_message ?? 0) + 1;
            
            if ($retry_count < 3) {
                wp_schedule_single_event(
                    time() + (60 * $retry_count),
                    'inventory_sync_mapping',
                    [$mapping->id]
                );
            }
            
            Inventory_Sync_Database::insert_log(
                $from_id, $product['name'] ?? '', 'sync_inventory', $from_name, $to_name, '', $stock, 'failed',
                $update_result->get_error_message() . " (تلاش $retry_count/3)"
            );
            return;
        }
        
        Inventory_Sync_Database::insert_log(
            $from_id, $product['name'] ?? '', 'sync_inventory', $from_name, $to_name, '', $stock, 'success'
        );
    }
    
    /**
     * هماهنگ‌سازی موجودی محصول متغیّر بر اساس تطابق SKU واریاسیون‌ها
     */
    private function sync_variable_stock($from_api, $to_api, $from_id, $to_id, $from_name, $to_name, $product_name) {
        $from_variations = $this->fetch_all_variations($from_api, $from_id);
        $to_variations   = $this->fetch_all_variations($to_api, $to_id);
        
        if (empty($from_variations) || empty($to_variations)) {
            Inventory_Sync_Database::insert_log(
                $from_id, $product_name, 'sync_variable_stock', $from_name, $to_name, '', $to_id, 'failed',
                'واریاسیونی برای هماهنگ‌سازی پیدا نشد'
            );
            return;
        }
        
        $to_by_sku = [];
        foreach ($to_variations as $v) {
            if (!empty($v['sku'])) {
                $to_by_sku[$v['sku']] = $v['id'];
            }
        }
        
        $synced = 0;
        foreach ($from_variations as $v) {
            $sku = $v['sku'] ?? '';
            if ($sku === '' || !isset($to_by_sku[$sku])) {
                continue;
            }
            
            $stock = isset($v['stock_quantity']) ? intval($v['stock_quantity']) : 0;
            $result = $to_api->update_variation_stock($to_id, $to_by_sku[$sku], $stock);
            
            if (!is_wp_error($result)) {
                $synced++;
            }
        }
        
        Inventory_Sync_Database::insert_log(
            $from_id, $product_name, 'sync_variable_stock', $from_name, $to_name, '', $to_id, 'success',
            "موجودی {$synced} واریاسیون هماهنگ شد"
        );
    }
    
    /**
     * دریافت همه‌ی واریاسیون‌های یک محصول با صفحه‌بندی
     */
    private function fetch_all_variations($api, $product_id) {
        $all = [];
        $page = 1;
        
        do {
            $variations = $api->get_product_variations($product_id, 100, $page);
            
            if (is_wp_error($variations) || empty($variations) || !is_array($variations)) {
                break;
            }
            
            $all = array_merge($all, $variations);
            $page++;
        } while (count($variations) === 100);
        
        return $all;
    }
    
    /**
     * انتقال محصول شامل دسته‌بندی‌ها، ویژگی‌ها و متغیّرها
     * 
     * @param int $site1_product_id شناسه محصول در سایت 1
     * @return array|WP_Error
     */
    public function transfer_product($site1_product_id) {
        $product1 = $this->site1_api->get_product($site1_product_id);
        
        if (is_wp_error($product1)) {
            return $product1;
        }
        
        $product_name = $product1['name'] ?? 'محصول بدون نام';
        $original_sku = $product1['sku'] ?? '';
        
        // ۱. انتقال دسته‌بندی‌ها و ویژگی‌ها پیش از ساخت محصول
        //    تا mapping در DB باشد وقتی prepare_attributes خوانده می‌شود
        $category_attr_sync = new Inventory_Sync_Category_Attribute_Sync(
            $this->site1_api,
            $this->site2_api
        );
        
        $category_map = $category_attr_sync->sync_product_categories(
            $product1['categories'] ?? []
        );
        
        // ذخیره attribute mapping در DB قبل از اینکه resolve_attribute_identity آن را بخواند
        $attribute_map = $category_attr_sync->sync_product_attributes(
            $product1['attributes'] ?? []
        );
        
        // ریست کش تا mapping تازه از DB خوانده شود
        $this->site2_attributes_cache = null;
        
        // ۲. بررسی محصول موجود در سایت 2
        $existing_product = $this->site2_api->product_exists_by_sku($original_sku);
        $site2_product_id = null;
        
        if ($existing_product && !empty($existing_product['id'])) {
            $site2_product_id = $existing_product['id'];
            Inventory_Sync_Database::insert_log(
                $site1_product_id, $product_name, 'product_exists', 'سایت 1', 'سایت 2',
                '', $site2_product_id, 'info',
                "محصول با SKU ({$original_sku}) در سایت 2 موجود است. بروز‌رسانی شد."
            );
        } else {
            if (empty($original_sku)) {
                $product1['sku'] = 'prod-' . time() . '-' . $site1_product_id;
            }
        }
        
        // ۳. آماده‌سازی داده‌های محصول برای سایت 2
        $product_data = $this->prepare_transfer_data($product1);
        
        // دسته‌بندی‌ها را با ID‌های سایت ۲ جایگزین کن
        if (!empty($category_map)) {
            $new_categories = [];
            foreach (($product1['categories'] ?? []) as $cat) {
                $site1_cat_id = $cat['id'] ?? 0;
                if (isset($category_map[$site1_cat_id])) {
                    $new_categories[] = ['id' => $category_map[$site1_cat_id]];
                }
            }
            if (!empty($new_categories)) {
                $product_data['categories'] = $new_categories;
            }
        }
        
        // ۴. ایجاد یا بروز‌رسانی محصول در سایت 2
        if ($site2_product_id !== null) {
            $result = $this->site2_api->update_product($site2_product_id, $product_data);
        } else {
            Inventory_Sync_Database::insert_log(
                $site1_product_id, $product_name, 'create_product_start', 'سایت 1', 'سایت 2',
                json_encode(['sku' => $product_data['sku'], 'type' => $product_data['type']]),
                '', 'info', 'شروع ایجاد محصول جدید'
            );
            $result = $this->site2_api->create_product($product_data);
        }
        
        if (is_wp_error($result)) {
            Inventory_Sync_Database::insert_log(
                $site1_product_id, $product_name, 'transfer_product_error', 'سایت 1', 'سایت 2',
                json_encode($product_data), $site2_product_id ?? '', 'failed', $result->get_error_message()
            );
            Inventory_Sync_Database::add_transferred_product(
                $site1_product_id, $site2_product_id ?? 0, $product_name, 'failed', $result->get_error_message()
            );
            return $result;
        }
        
        $site2_product_id = $result['id'];
        
        // ۵. اگر محصول متغیّر است، واریاسیون‌ها را منتقل کن
        if (($product1['type'] ?? 'simple') === 'variable') {
            $variation_result = $this->transfer_variations($site1_product_id, $site2_product_id, $product_name);
            if (is_wp_error($variation_result)) {
                Inventory_Sync_Database::insert_log(
                    $site1_product_id, $product_name, 'transfer_variations', 'سایت 1', 'سایت 2',
                    '', $site2_product_id, 'failed', $variation_result->get_error_message()
                );
            }
        }
        
        // ۶. ذخیره mapping
        $this->save_mapping(
            $site1_product_id,
            $site2_product_id,
            $product_name,
            $result['name'] ?? $product_name,
            $product1['sku'] ?? '',
            $result['sku'] ?? ''
        );
        
        // ۷. ثبت محصول منتقل‌شده
        Inventory_Sync_Database::add_transferred_product(
            $site1_product_id, $site2_product_id, $product_name, 'success'
        );
        
        Inventory_Sync_Database::insert_log(
            $site1_product_id, $product_name, 'transfer_product', 'سایت 1', 'سایت 2',
            '', $site2_product_id, 'success'
        );
        
        return $result;
    }
    
    /**
     * انتقال متغیّرهای یک محصول متغیّر از سایت ۱ به محصول والد ساخته‌شده در سایت ۲
     * 
     * - واریاسیون‌های موجود (تطابق SKU) آپدیت می‌شوند
     * - واریاسیون‌های جدید ساخته می‌شوند
     */
    private function transfer_variations($site1_product_id, $site2_parent_id, $product_name) {
        $all_variations = [];
        $page = 1;
        
        do {
            $variations = $this->site1_api->get_product_variations($site1_product_id, 100, $page);
            
            if (is_wp_error($variations)) {
                Inventory_Sync_Database::insert_log(
                    $site1_product_id, $product_name, 'get_variations_error', 'سایت 1', 'سایت 2',
                    '', '', 'failed', $variations->get_error_message()
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
                $site1_product_id, $product_name, 'no_variations', 'سایت 1', 'سایت 2',
                '', $site2_parent_id, 'info', 'هیچ متغیّری برای انتقال وجود ندارد'
            );
            return true;
        }
        
        // دریافت واریاسیون‌های موجود در سایت ۲ (برای جلوگیری از duplicate)
        $existing_site2_variations = $this->fetch_all_variations($this->site2_api, $site2_parent_id);
        $site2_sku_to_id = [];
        foreach ($existing_site2_variations as $ev) {
            if (!empty($ev['sku'])) {
                $site2_sku_to_id[$ev['sku']] = $ev['id'];
            }
        }
        
        // جداسازی: کدام باید ساخته شود و کدام باید آپدیت شود
        $to_create = [];
        $to_update = [];
        
        foreach ($all_variations as $idx => $variation) {
            $sku  = $variation['sku'] ?? '';
            $data = $this->prepare_variation_data($variation);
            
            // لاگ دیتیلی برای اولین متغیّر
            if ($idx === 0) {
                Inventory_Sync_Database::insert_log(
                    $site1_product_id, $product_name, 'variation_sample',
                    'سایت 1', 'سایت 2', json_encode($variation), json_encode($data),
                    'info', 'نمونه داده‌ی متغیّر اول'
                );
            }
            
            if ($sku !== '' && isset($site2_sku_to_id[$sku])) {
                $to_update[] = array_merge(['id' => $site2_sku_to_id[$sku]], $data);
            } else {
                $to_create[] = $data;
            }
        }
        
        Inventory_Sync_Database::insert_log(
            $site1_product_id, $product_name, 'variations_transfer_start', 'سایت 1', 'سایت 2',
            json_encode(['create' => count($to_create), 'update' => count($to_update)]),
            $site2_parent_id, 'info',
            'شروع انتقال: ' . count($to_create) . ' جدید، ' . count($to_update) . ' آپدیت'
        );
        
        // ارسال batch به سایت ۲
        $batch_data = [];
        if (!empty($to_create)) {
            $batch_data['create'] = $to_create;
        }
        if (!empty($to_update)) {
            $batch_data['update'] = $to_update;
        }
        
        if (empty($batch_data)) {
            return true;
        }
        
        $result = $this->site2_api->batch_upsert_variations($site2_parent_id, $batch_data);
        
        if (is_wp_error($result)) {
            Inventory_Sync_Database::insert_log(
                $site1_product_id, $product_name, 'batch_variations_error', 'سایت 1', 'سایت 2',
                '', $site2_parent_id, 'failed', $result->get_error_message()
            );
            return $result;
        }
        
        $created_count = count($result['create'] ?? []);
        $updated_count = count($result['update'] ?? []);
        Inventory_Sync_Database::insert_log(
            $site1_product_id, $product_name, 'variations_transferred', 'سایت 1', 'سایت 2',
            '', $site2_parent_id, 'success',
            "{$created_count} واریاسیون ساخته شد، {$updated_count} واریاسیون آپدیت شد"
        );
        
        return $result;
    }
    
    /**
     * آماده‌سازی داده‌ی یک متغیّر برای انتقال به سایت مقصد
     * 
     * مهم: قیمت باید همیشه ارسال شود (حتی اگر ۰ باشد) وگرنه WooCommerce واریاسیون را رد می‌کند
     */
    private function prepare_variation_data($variation) {
        $sku            = $variation['sku'] ?? '';
        $regular_price  = isset($variation['regular_price']) ? strval($variation['regular_price']) : '0';
        $sale_price     = isset($variation['sale_price']) && $variation['sale_price'] !== '' ? strval($variation['sale_price']) : '';
        $manage_stock   = $variation['manage_stock'] ?? false;
        $stock_quantity = isset($variation['stock_quantity']) ? intval($variation['stock_quantity']) : 0;
        $stock_status   = $variation['stock_status'] ?? 'instock';
        
        $data = [
            'sku'           => strval($sku),
            'regular_price' => $regular_price,
            'stock_status'  => $stock_status,
            'attributes'    => $this->prepare_variation_attributes($variation['attributes'] ?? []),
        ];
        
        // قیمت تخفیف فقط اگر معتبر باشد
        if ($sale_price !== '' && floatval($sale_price) > 0 && floatval($sale_price) < floatval($regular_price)) {
            $data['sale_price'] = $sale_price;
        }
        
        $data['manage_stock'] = (bool) $manage_stock;
        if ($manage_stock) {
            $data['stock_quantity'] = $stock_quantity;
        }
        
        if (!empty($variation['weight'])) {
            $data['weight'] = strval($variation['weight']);
        }
        
        if (!empty($variation['description'])) {
            $data['description'] = $variation['description'];
        }
        
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
     */
    private function prepare_variation_attributes($attributes) {
        if (empty($attributes) || !is_array($attributes)) {
            return [];
        }
        
        $clean = [];
        foreach ($attributes as $attr) {
            $site1_attr_id = intval($attr['id'] ?? 0);
            $attr_name = $attr['name'] ?? '';
            $attr_option = $attr['option'] ?? '';
            
            if ($attr_option === '' || (empty($attr_name) && $site1_attr_id <= 0)) {
                continue;
            }
            
            $item = $this->resolve_attribute_identity($site1_attr_id, $attr_name);
            $item['option'] = $attr_option;
            
            $clean[] = $item;
        }
        
        return $clean;
    }
    
    /**
     * آماده‌سازی داده‌های انتقالی محصول والد
     */
    private function prepare_transfer_data($product1) {
        $type = $product1['type'] ?? 'simple';
        
        $data = [
            'name'              => $product1['name'] ?? '',
            'type'              => $type,
            'description'       => $product1['description'] ?? '',
            'short_description' => $product1['short_description'] ?? '',
            'sku'               => $product1['sku'] ?? '',
            'regular_price'     => isset($product1['regular_price']) ? (string) $product1['regular_price'] : '',
            'sale_price'        => isset($product1['sale_price']) ? (string) $product1['sale_price'] : '',
            'images'            => $this->prepare_images($product1['images'] ?? []),
            'categories'        => $this->prepare_terms_as_ids($product1['categories'] ?? []),
            'tags'              => $this->prepare_terms_as_ids($product1['tags'] ?? []),
            'attributes'        => $this->prepare_attributes($product1['attributes'] ?? []),
            'status'            => 'draft'
        ];
        
        if ($type !== 'variable') {
            $data['manage_stock'] = $product1['manage_stock'] ?? false;
            if (!empty($data['manage_stock'])) {
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
     * آماده‌سازی ویژگی‌های محصول والد برای سایت مقصد
     */
    private function prepare_attributes($attributes) {
        if (empty($attributes) || !is_array($attributes)) {
            return [];
        }
        
        $clean = [];
        foreach ($attributes as $attr) {
            $name      = $attr['name'] ?? '';
            $site1_id  = intval($attr['id'] ?? 0);
            
            if (empty($name) && $site1_id <= 0) {
                continue;
            }
            
            $item = $this->resolve_attribute_identity($site1_id, $name);
            
            $item['position']  = isset($attr['position']) ? intval($attr['position']) : 0;
            $item['visible']   = isset($attr['visible']) ? (bool) $attr['visible'] : true;
            $item['variation'] = isset($attr['variation']) ? (bool) $attr['variation'] : false;
            $item['options']   = $attr['options'] ?? [];
            
            $clean[] = $item;
        }
        
        return $clean;
    }
    
    /**
     * تعیین هویت یک ویژگی در سایت مقصد بر اساس ویژگی سایت مبدأ
     * 
     * اولویت: ۱) جدول mapping DB  ۲) جستجو نام در سایت ۲  ۳) ویژگی سفارشی با نام
     */
    private function resolve_attribute_identity($site1_attr_id, $attr_name) {
        $site1_attr_id = intval($site1_attr_id);
        
        if ($site1_attr_id <= 0) {
            return ['name' => $attr_name];
        }
        
        // ۱) جستجو در جدول mapping
        $mapping = Inventory_Sync_Database::get_attribute_mapping($site1_attr_id);
        if ($mapping && !empty($mapping->site2_attribute_id)) {
            return ['id' => intval($mapping->site2_attribute_id)];
        }
        
        // ۲) جستجو بر اساس نام در ویژگی‌های سایت ۲ (با کش)
        if (!empty($attr_name)) {
            if ($this->site2_attributes_cache === null) {
                $attrs = $this->site2_api->get_attributes();
                $this->site2_attributes_cache = (!is_wp_error($attrs) && is_array($attrs)) ? $attrs : [];
            }
            foreach ($this->site2_attributes_cache as $a) {
                if (strtolower($a['name'] ?? '') === strtolower($attr_name)) {
                    return ['id' => intval($a['id'])];
                }
            }
        }
        
        // ۳) ویژگی سفارشی با نام
        return ['name' => $attr_name];
    }
    
    /**
     * پاک‌سازی تصاویر
     */
    private function prepare_images($images) {
        if (empty($images) || !is_array($images)) {
            return [];
        }
        
        $clean = [];
        foreach ($images as $image) {
            if (empty($image['src'])) {
                continue;
            }
            $new_image = ['src' => $image['src']];
            if (!empty($image['name'])) {
                $new_image['name'] = $image['name'];
            }
            if (!empty($image['alt'])) {
                $new_image['alt'] = $image['alt'];
            }
            $clean[] = $new_image;
        }
        
        return $clean;
    }
    
    /**
     * ذخیره نقشه‌برداری با نام محصولات
     */
    private function save_mapping($site1_id, $site2_id, $site1_name, $site2_name, $site1_sku, $site2_sku) {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}inventory_sync_mapping
                 (site1_product_id, site2_product_id, site1_product_name, site2_product_name, site1_sku, site2_sku, sync_enabled, sync_status, last_sync)
                 VALUES (%d, %d, %s, %s, %s, %s, 1, 'synced', NOW())
                 ON DUPLICATE KEY UPDATE
                    site1_product_name = VALUES(site1_product_name),
                    site2_product_name = VALUES(site2_product_name),
                    site1_sku = VALUES(site1_sku),
                    site2_sku = VALUES(site2_sku),
                    sync_status = 'synced',
                    last_sync = NOW()",
                $site1_id,
                $site2_id,
                $site1_name,
                $site2_name,
                $site1_sku,
                $site2_sku
            )
        );
    }
}
