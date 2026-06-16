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
     * پاک کردن تمام کش‌ها
     * ⭐ بسیار مهم: این متد را هر بار بعد از transfer محصول فراخوانی کنید
     * تا اطلاعات درست برای محصول بعدی استفاده شود
     */
    private function clear_cache() {
        $this->site2_attributes_cache = null;
        // اگر cache‌های دیگری هم دارید، آن‌ها را نیز اضافه کنید
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
        
        // *** بسیار مهم: handler رویدادهای زمان‌بندی‌شده ***
        // بدون این‌ها، sync خودکار هرگز اجرا نمی‌شد
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
     * sync همه‌ی mappingهای فعال (handler رویداد فوری)
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
        
        // برنامه‌ریزی کن که 5 ثانیه بعد sync شود (تا سایت وقت داشته باشد)
        wp_schedule_single_event(
            time() + 5,
            'inventory_sync_immediate'
        );
    }
    
    /**
     * هنگام تغییر موجودی - تمام mapped محصولات را sync کن
     * این متد هم برای محصول ساده و هم برای واریاسیون فراخوانی می‌شود
     */
    public function sync_on_stock_change($product) {
        if (!Inventory_Sync_Settings::get_auto_sync_enabled()) {
            return;
        }
        
        if (!is_object($product) || !method_exists($product, 'get_id')) {
            return;
        }
        
        // اگر واریاسیون است، شناسه محصول والد را بگیر چون mapping بر اساس والد است
        $product_id = $product->get_id();
        if (method_exists($product, 'get_parent_id') && $product->get_parent_id()) {
            $product_id = $product->get_parent_id();
        }
        
        global $wpdb;
        
        // پیدا کن این محصول در کدام mapping قرار دارد
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
        
        // دریافت mapping
        $mapping = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping WHERE id = %d",
                $mapping_id
            )
        );
        
        if (!$mapping) {
            return new WP_Error('mapping_not_found', 'نقشه‌برداری پیدا نشد');
        }
        
        if (!$mapping->sync_enabled) {
            return new WP_Error('sync_disabled', 'هماهنگ‌سازی غیرفعال است');
        }
        
        // دریافت محصولات
        $product1 = $this->site1_api->get_product($mapping->site1_product_id);
        $product2 = $this->site2_api->get_product($mapping->site2_product_id);
        
        if (is_wp_error($product1) || is_wp_error($product2)) {
            $error_msg = '';
            if (is_wp_error($product1)) $error_msg .= 'سایت 1: ' . $product1->get_error_message();
            if (is_wp_error($product2)) $error_msg .= 'سایت 2: ' . $product2->get_error_message();
            
            Inventory_Sync_Database::insert_log(
                $mapping->site1_product_id,
                'محصول نامشخص',
                'product_fetch_failed',
                'سیستم',
                'سیستم',
                0,
                $mapping->site2_product_id,
                'failed',
                $error_msg
            );
            
            return new WP_Error('product_not_found', 'یکی از محصولات پیدا نشد: ' . $error_msg);
        }
        
        // دریافت نام‌های محصول
        $product1_name = $product1['name'] ?? 'محصول سایت 1';
        $product2_name = $product2['name'] ?? 'محصول سایت 2';
        
        // هماهنگ‌سازی موجودی
        $site1_stock = intval($product1['stock_quantity'] ?? 0);
        $site2_stock = intval($product2['stock_quantity'] ?? 0);
        
        if ($site1_stock === $site2_stock) {
            // موجودی‌ها برابرند - هنوز log کن
            Inventory_Sync_Database::insert_log(
                $mapping->site1_product_id,
                $product1_name,
                'inventory_already_synced',
                'سایت 1',
                'سایت 2',
                $site1_stock,
                $mapping->site2_product_id,
                'info',
                "موجودی‌ها برابرند: {$site1_stock} واحد"
            );
            return ['status' => 'equal'];
        }
        
        $sync_direction = Inventory_Sync_Settings::get_sync_direction();
        
        if ($sync_direction === 'site1_to_site2') {
            // نوشتن موجودی سایت 1 به سایت 2
            Inventory_Sync_Database::insert_log(
                $mapping->site1_product_id,
                $product1_name,
                'inventory_sync_started',
                'سایت 1',
                'سایت 2',
                $site1_stock,
                $mapping->site2_product_id,
                'info',
                "شروع انتقال موجودی: سایت 1 ({$product1_name}) = {$site1_stock} واحد → سایت 2 ({$product2_name})"
            );
            
            $result = $this->site2_api->update_product_stock(
                $mapping->site2_product_id,
                $site1_stock
            );
            
            if (is_wp_error($result)) {
                Inventory_Sync_Database::insert_log(
                    $mapping->site1_product_id,
                    $product1_name,
                    'inventory_sync_failed',
                    'سایت 1',
                    'سایت 2',
                    $site1_stock,
                    $mapping->site2_product_id,
                    'failed',
                    "ناموفق: " . $result->get_error_message()
                );
                return $result;
            }
            
            // ثبت log موفق
            Inventory_Sync_Database::insert_log(
                $mapping->site1_product_id,
                $product1_name,
                'inventory_synced',
                'سایت 1',
                'سایت 2',
                $site1_stock,
                $mapping->site2_product_id,
                'success',
                "موجودی سایت 2 ({$product2_name}) از {$site2_stock} به {$site1_stock} تغییر کرد"
            );
            
        } else {
            // نوشتن موجودی سایت 2 به سایت 1
            Inventory_Sync_Database::insert_log(
                $mapping->site2_product_id,
                $product2_name,
                'inventory_sync_started',
                'سایت 2',
                'سایت 1',
                $site2_stock,
                $mapping->site1_product_id,
                'info',
                "شروع انتقال موجودی: سایت 2 ({$product2_name}) = {$site2_stock} واحد → سایت 1 ({$product1_name})"
            );
            
            $result = $this->site1_api->update_product_stock(
                $mapping->site1_product_id,
                $site2_stock
            );
            
            if (is_wp_error($result)) {
                Inventory_Sync_Database::insert_log(
                    $mapping->site2_product_id,
                    $product2_name,
                    'inventory_sync_failed',
                    'سایت 2',
                    'سایت 1',
                    $site2_stock,
                    $mapping->site1_product_id,
                    'failed',
                    "ناموفق: " . $result->get_error_message()
                );
                return $result;
            }
            
            // ثبت log موفق
            Inventory_Sync_Database::insert_log(
                $mapping->site2_product_id,
                $product2_name,
                'inventory_synced',
                'سایت 2',
                'سایت 1',
                $site2_stock,
                $mapping->site1_product_id,
                'success',
                "موجودی سایت 1 ({$product1_name}) از {$site1_stock} به {$site2_stock} تغییر کرد"
            );
        }
        
        // بروزرسانی sync_status در mapping
        $wpdb->update(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'sync_status' => 'synced',
                'last_sync' => current_time('mysql')
            ],
            ['id' => $mapping_id],
            ['%s', '%s'],
            ['id' => '%d']
        );
        
        return ['status' => 'synced'];
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
        
        // محصول متغیّر: موجودی روی واریاسیون‌هاست، نه والد
        if (($product['type'] ?? 'simple') === 'variable') {
            $this->sync_variable_stock($from_api, $to_api, $from_id, $to_id, $from_name, $to_name, $product['name'] ?? '');
            return;
        }
        
        $stock = isset($product['stock_quantity']) ? intval($product['stock_quantity']) : 0;
        
        // اپدیت موجودی در سایت مقصد
        $update_result = $to_api->update_product_stock($to_id, $stock);
        
        if (is_wp_error($update_result)) {
            // قرار بده برای دوباره تلاش (Retry Logic)
            // ⭐ بهبود: حالا retry_count در جدول ذخیره می‌شود
            $retry_count = intval($mapping->retry_count ?? 0) + 1;
            
            if ($retry_count < 4) {
                // دوباره تلاش کن (تا 3 بار)
                wp_schedule_single_event(
                    time() + (60 * $retry_count), // بعد از 1، 2، 3 دقیقه تلاش کن
                    'inventory_sync_mapping',
                    [$mapping->id]
                );
                
                // به‌روزرسانی retry_count در database
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'inventory_sync_mapping',
                    [
                        'retry_count' => $retry_count,
                        'sync_status' => 'error',
                        'error_message' => $update_result->get_error_message()
                    ],
                    ['id' => $mapping->id]
                );
            } else {
                // 3 بار تلاش شده است، دیگر تلاش نکن
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'inventory_sync_mapping',
                    [
                        'sync_status' => 'error',
                        'error_message' => $update_result->get_error_message() . " (3 تلاش ناموفق)"
                    ],
                    ['id' => $mapping->id]
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
        
        // ⭐ بسیار مهم: بروز کن status به synced
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'sync_status' => 'synced',
                'retry_count' => 0,
                'error_message' => ''
            ],
            ['site1_product_id' => $from_id, 'site2_product_id' => $to_id]
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
                $from_id,
                $product_name,
                'sync_variable_stock',
                $from_name,
                $to_name,
                '',
                $to_id,
                'failed',
                'واریاسیونی برای هماهنگ‌سازی پیدا نشد'
            );
            return;
        }
        
        // ایندکس واریاسیون‌های مقصد بر اساس SKU
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
            $from_id,
            $product_name,
            'sync_variable_stock',
            $from_name,
            $to_name,
            '',
            $to_id,
            'success',
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
     * انتقال محصول شامل دسته‌بندی‌ها، ویژگی‌ه�� و متغیّرها
     * 
     * ⭐ بسیار مهم: این متد حالا Idempotent است
     * یعنی اگر محصول قبلاً منتقل شده بود و پاک‌شد و دوباره منتقل شود، کار می‌کند
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
        
        // ۱. بررسی اینکه mapping قبلی برای این محصول وجود دارد
        global $wpdb;
        $existing_mapping = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE site1_product_id = %d",
                $site1_product_id
            )
        );
        
        $site2_product_id = null;
        
        if ($existing_mapping && $existing_mapping->site2_product_id > 0) {
            // mapping قبلی وجود دارد، بررسی کن که آیا محصول در سایت 2 هنوز موجود است
            $existing_product = $this->site2_api->get_product($existing_mapping->site2_product_id);
            
            if (!is_wp_error($existing_product)) {
                // محصول هنوز موجود است
                $site2_product_id = $existing_mapping->site2_product_id;
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product_name,
                    'product_exists_with_mapping',
                    'سایت 1',
                    'سایت 2',
                    '',
                    $site2_product_id,
                    'info',
                    "محصول با mapping قبلی در سایت 2 موجود است. بروز‌رسانی شد."
                );
            } else {
                // محصول قبلاً حذف شده است، بریز mapping قبلی را و دوباره سعی کن
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product_name,
                    'previous_product_deleted',
                    'سایت 1',
                    'سایت 2',
                    '',
                    $existing_mapping->site2_product_id,
                    'info',
                    "محصول قبلی در سایت 2 حذف‌شده است. در حال ایجاد محصول جدید..."
                );
                
                // پاک کن mapping قبلی
                $wpdb->delete(
                    $wpdb->prefix . 'inventory_sync_mapping',
                    ['id' => $existing_mapping->id]
                );
                
                $site2_product_id = null;
            }
        }
        
        // ۲. اگر mapping نیست، بررسی کن که محصول با همین SKU وجود دارد یا نه
        if ($site2_product_id === null) {
            $existing_product = $this->site2_api->product_exists_by_sku($original_sku);
            
            if ($existing_product && !empty($existing_product['id'])) {
                // محصول با همین SKU موجود است
                $site2_product_id = $existing_product['id'];
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product_name,
                    'product_exists_by_sku',
                    'سایت 1',
                    'سایت 2',
                    '',
                    $site2_product_id,
                    'info',
                    "محصول با SKU ({$original_sku}) در سایت 2 موجود است. بروز‌رسانی شد."
                );
            }
        }
        
        // ۳. اگر محصول نیست، SKU منحصر‌به‌فردی تولید کن
        $sku_to_use = $original_sku;
        if ($site2_product_id === null) {
            if (empty($sku_to_use)) {
                // ⭐ راه بهتر برای generate SKU:
                // site1_product_id منحصر به فرد است و هرگز تکرار نمی‌شود
                // مقدار random نیز برای اطمینان اضافه می‌شود
                $sku_to_use = 'site1-' . $site1_product_id . '-' . bin2hex(random_bytes(4));
                
                // بررسی مجدد اینکه این SKU generate شده نیز در سایت 2 وجود ندارد
                $attempt = 0;
                while ($this->site2_api->product_exists_by_sku($sku_to_use) && $attempt < 5) {
                    $sku_to_use = 'site1-' . $site1_product_id . '-' . bin2hex(random_bytes(4));
                    $attempt++;
                }
                
                if ($attempt >= 5) {
                    Inventory_Sync_Database::insert_log(
                        $site1_product_id,
                        $product_name,
                        'sku_generation_failed',
                        'سایت 1',
                        'سایت 2',
                        'SKU اصلی خالی است و نتوانستیم SKU منحصر به فرد تولید کنیم',
                        '',
                        'failed',
                        'بعد از 5 تلاش، SKU منحصر به فردی تولید نشد'
                    );
                    return new WP_Error('sku_generation_failed', 'نتوانستیم SKU منحصر به فردی برای محصول تولید کنیم');
                }
            } else {
                // اگر SKU اصلی وجود دارد ولی محصول نیست، حتی اگر محصولی با این SKU وجود داشت و پاک‌شد
                // دوباره استفاده می‌کنیم
                $sku_to_use = $original_sku;
            }
            
            $product1['sku'] = $sku_to_use;
        }
        
        // ۴. انتقال دسته‌بندی‌ها و ویژگی‌ها
        // ⭐ بسیار مهم: این مرحله قبل از ایجاد محصول اتفاق می‌افتد
        // تا متغیرها بتوانند به ویژگی‌های sync‌شده اشاره کنند
        $category_attr_sync = new Inventory_Sync_Category_Attribute_Sync(
            $this->site1_api,
            $this->site2_api
        );
        
        // انتقال دسته‌بندی‌ها
        $category_map = $category_attr_sync->sync_product_categories(
            $product1['categories'] ?? []
        );
        
        // انتقال ویژگی‌ها (برای محصولات متغیّر، این ضروری است!)
        // ⭐ این ویژگی‌ها در database mapping ذخیره می‌شوند و متغیرها از آن استفاده می‌کنند
        $attribute_map = $category_attr_sync->sync_product_attributes(
            $product1['attributes'] ?? []
        );
        
        // ۵. آماده‌سازی داده‌های محصول برای سایت 2
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
        
        // ۶. ایجاد یا بروز‌رسانی محصول در سایت 2
        if ($site2_product_id !== null) {
            // محصول موجود - بروز کن
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'update_product_start',
                'سایت 1',
                'سایت 2',
                json_encode(['sku' => $product_data['sku']]),
                $site2_product_id,
                'info',
                'شروع بروزرسانی محصول موجود'
            );
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
            $error_msg = $result->get_error_message();
            
            // ⭐ اگر خطا به خاطر SKU تکراری است و محصول موجود است
            // این می‌تواند بدل اینکه خطا باشد، درواقع موفقیت‌آمیز است
            if ((stripos($error_msg, 'already present') !== false || stripos($error_msg, 'duplicate') !== false) && $site2_product_id !== null) {
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product_name,
                    'skipping_duplicate_sku',
                    'سایت 1',
                    'سایت 2',
                    json_encode(['sku' => $product_data['sku']]),
                    $site2_product_id,
                    'warning',
                    'محصول با این SKU قبلاً در سایت 2 موجود است'
                );
                
                // محصول موجود است، ادامه بده
                // این اتفاق نمی‌افتد اما اگر بیفتد، موفقیت‌آمیز محسوب کن
                $this->clear_cache();
                return ['id' => $site2_product_id];
            }
            
            // خطای واقعی - لاگ کن و برگردان
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'transfer_product_error',
                'سایت 1',
                'سایت 2',
                json_encode($product_data),
                $site2_product_id ?? '',
                'failed',
                $error_msg
            );
            
            Inventory_Sync_Database::add_transferred_product(
                $site1_product_id,
                $site2_product_id ?? 0,
                $product_name,
                'failed',
                $error_msg
            );
            
            // ⭐ پاک کردن کش حتی در صورت خطا
            $this->clear_cache();
            
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
        
        // ⭐ بسیار مهم: پاک کردن کش برای محصول بعدی
        $this->clear_cache();
        
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
     * 
     * ⚠️ بسیار مهم: پیش از این تابع، ویژگی‌های محصول (attributes) باید در سایت 2
     * sync شده باشند تا متغیرها بتوانند به آن‌ها اشاره کنند.
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
        
        // اگر محصول والد از قبل ویژگی‌ها دارد، آن‌ها را دریافت کن
        // تا ویژگی‌های متغیرها بتوانند با صحیح reference کنند
        $parent_product = $this->site2_api->get_product($site2_parent_id);
        if (is_wp_error($parent_product)) {
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'get_parent_product_error',
                'سایت 1',
                'سایت 2',
                '',
                $site2_parent_id,
                'failed',
                $parent_product->get_error_message()
            );
            return $parent_product;
        }
        
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
        
        // آماده‌سازی ویژگی‌های والد برای استفاده در متغیرها
        $parent_attrs_for_variations = [];
        if (!empty($parent_product['attributes']) && is_array($parent_product['attributes'])) {
            $parent_attrs_for_variations = $parent_product['attributes'];
        }
        
        // آماده‌سازی داده‌ی هر متغیّر برای مقصد
        $variations_to_create = [];
        foreach ($all_variations as $idx => $variation) {
            $variation_data = $this->prepare_variation_data(
                $variation,
                $parent_attrs_for_variations,
                $idx + 1,
                $site2_parent_id
            );
            $variations_to_create[] = $variation_data;
            
            // لاگ دیتیلی برای اولین متغیّر
            if ($idx === 0) {
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product_name,
                    'variation_sample_data',
                    'سایت 1',
                    'سایت 2',
                    json_encode($variation),
                    $site2_parent_id,
                    'info',
                    'نمونه داده‌ی متغیّر اول از سایت 1'
                );
                
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product_name,
                    'variation_prepared_data',
                    'سایت 1',
                    'سایت 2',
                    json_encode($variation_data),
                    $site2_parent_id,
                    'info',
                    'نمونه داده‌ی متغیّر آماده‌شده برای ارسال'
                );
            }
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
     * 
     * @param array $variation داده‌ی متغیر از سایت 1
     * @param array $parent_attributes ویژگی‌های والد محصول (برای matching)
     * @param int $variation_index شماره متغیر (برای generate SKU منحصر به فرد)
     * @param int $parent_product_id شناسه محصول والد
     */
    private function prepare_variation_data($variation, $parent_attributes = [], $variation_index = 0, $parent_product_id = 0) {
        // گرفتن داده‌های اساسی
        $sku = $variation['sku'] ?? '';
        $regular_price = isset($variation['regular_price']) ? (float) $variation['regular_price'] : 0;
        $sale_price = isset($variation['sale_price']) ? (float) $variation['sale_price'] : 0;
        $manage_stock = $variation['manage_stock'] ?? false;
        $stock_quantity = isset($variation['stock_quantity']) ? intval($variation['stock_quantity']) : 0;
        $stock_status = $variation['stock_status'] ?? 'instock';
        
        // ⭐ اگر SKU خالی است، یک SKU منحصر به فرد تولید کن
        if (empty($sku)) {
            $sku = 'var-' . $parent_product_id . '-' . $variation_index . '-' . bin2hex(random_bytes(3));
        }
        
        // ⭐ اگر SKU موجود است، بررسی کن که منحصر به فرد است
        // (برای اطمینان اینکه هنگام batch variations، SKU‌ها تکراری نباشند)
        $attempt = 0;
        $original_sku = $sku;
        while ($this->site2_api->product_exists_by_sku($sku) && $attempt < 3) {
            $sku = $original_sku . '-' . bin2hex(random_bytes(2));
            $attempt++;
        }
        
        // ساخت داده‌های متغیّر
        $data = [
            'sku' => strval($sku),
            'attributes' => $this->prepare_variation_attributes($variation['attributes'] ?? [], $parent_attributes),
        ];
        
        // قیمت (ضروری)
        if ($regular_price > 0) {
            $data['regular_price'] = strval($regular_price);
        }
        
        // قیمت تخفیف (اختیاری)
        if ($sale_price > 0 && $sale_price < $regular_price) {
            $data['sale_price'] = strval($sale_price);
        }
        
        // موجودی و وضعیت
        $data['stock_status'] = $stock_status;
        
        // اگر مدیریت موجودی فعال است
        if ($manage_stock) {
            $data['manage_stock'] = true;
            $data['stock_quantity'] = $stock_quantity;
        } else {
            $data['manage_stock'] = false;
        }
        
        // توضیح (اختیاری)
        if (!empty($variation['description'])) {
            $data['description'] = $variation['description'];
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
     * - 'id': شناسه ویژگی عمومی در سایت مقصد (mapping شده)
     * - 'option': نام مقدار ویژگی (مثلاً 'سبز', 'لارج')
     * 
     * ⚠️ بسیار مهم: ویژگی‌های متغیر باید دقیقاً همانطور resolve شوند که
     * در لیست ویژگی والد محصول resolve شده‌اند. در غیر این صورت
     * WooCommerce واریاسیون را به محصول متصل نمی‌کند.
     * 
     * @param array $attributes آرایه ویژگی‌های متغیر از سایت 1
     * @param array $parent_attributes (اختیاری) ویژگی‌های والد که قبلاً resolve شده‌اند
     * @return array ویژگی‌های آماده‌شده برای API
     */
    private function prepare_variation_attributes($attributes, $parent_attributes = []) {
        if (empty($attributes) || !is_array($attributes)) {
            return [];
        }
        
        // اگر ویژگی‌های والد ارائه شده‌اند، آن‌ها را کش کن
        $parent_attr_map = [];
        if (!empty($parent_attributes)) {
            foreach ($parent_attributes as $pattr) {
                if (isset($pattr['id']) && !empty($pattr['id'])) {
                    // key: site1_attr_id, value: site2_attr_id (ID شامل است)
                    // یا name اگر ویژگی سفارشی است
                    $site1_id = intval($pattr['_site1_id'] ?? 0);
                    if ($site1_id > 0) {
                        $parent_attr_map[$site1_id] = $pattr;
                    }
                }
            }
        }
        
        $clean = [];
        foreach ($attributes as $attr) {
            $site1_attr_id = intval($attr['id'] ?? 0);
            $attr_name = $attr['name'] ?? '';
            $attr_option = $attr['option'] ?? '';
            
            // مقدار (option) برای واریاسیون ضروری است
            if ($attr_option === '' || (empty($attr_name) && $site1_attr_id <= 0)) {
                continue;
            }
            
            // اگر ویژگی والد دارای mapping است، از آن استفاده کن
            $item = null;
            if (isset($parent_attr_map[$site1_attr_id])) {
                // از ویژگی والد کپی کن (معمولاً دارای 'id' است)
                $item = [
                    'id' => $parent_attr_map[$site1_attr_id]['id'] ?? null,
                ];
                if (!empty($parent_attr_map[$site1_attr_id]['name'])) {
                    $item['name'] = $parent_attr_map[$site1_attr_id]['name'];
                }
            } else {
                // در غیر این صورت resolve کن
                $item = $this->resolve_attribute_identity($site1_attr_id, $attr_name);
            }
            
            // اضافه کردن option
            if ($item !== null) {
                $item['option'] = $attr_option;
                $clean[] = $item;
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
     * آماده‌سازی ویژگی‌های محصول والد برای سایت مقصد.
     * 
     * بسیار مهم: هویت ویژگی والد باید دقیقاً با هویت ویژگی واریاسیون یکی باشد
     * تا ووکامرس بتواند واریاسیون‌ها را به ویژگی والد متصل کند.
     * - ویژگی گلوبال: با id (مپ‌شده به سایت ۲) ارسال می‌شود
     * - ویژگی سفارشی: با name ارسال می‌شود
     * 
     * ⚠️ اضافه شد: marker _site1_id تا متغیرها بتوانند ویژگی والد را صحیح match کنند
     */
    private function prepare_attributes($attributes) {
        if (empty($attributes) || ! is_array($attributes)) {
            return [];
        }
        
        $clean = [];
        foreach ($attributes as $attr) {
            $name = $attr['name'] ?? '';
            $site1_id = intval($attr['id'] ?? 0);
            
            if (empty($name) && $site1_id <= 0) {
                continue;
            }
            
            // هویت ویژگی در سایت ۲ (id برای گلوبال، name برای سفارشی)
            $item = $this->resolve_attribute_identity($site1_id, $name);
            
            $item['position']  = isset($attr['position']) ? intval($attr['position']) : 0;
            $item['visible']   = isset($attr['visible']) ? (bool) $attr['visible'] : true;
            $item['variation'] = isset($attr['variation']) ? (bool) $attr['variation'] : false;
            $item['options']   = $attr['options'] ?? [];
            
            // ⭐ علامت خصوصی برای matching متغیرها (API WooCommerce این را نادیده می‌گیرد)
            if ($site1_id > 0) {
                $item['_site1_id'] = $site1_id;
            }
            
            $clean[] = $item;
        }
        
        return $clean;
    }
    
    /**
     * تعیین هویت یک ویژگی در سایت مقصد بر اساس ویژگی سایت مبدأ.
     * 
     * ترتیب اولویت:
     * 1. جدول mapping (اگر ویژگی قبلاً انتقال یافته)
     * 2. جستجو با نام در سایت 2
     * 3. ویژگی سفارشی با نام (اگر گلوبال نباشد)
     * 
     * @param int    $site1_attr_id شناسه ویژگی در سایت ۱ (۰ یعنی سفارشی)
     * @param string $attr_name      نام ویژگی
     * @return array  ['id' => int]  برای ویژگی گلوبال، یا ['name' => string] برای سفارشی
     */
    private function resolve_attribute_identity($site1_attr_id, $attr_name) {
        $site1_attr_id = intval($site1_attr_id);
        
        // ویژگی سفارشی (بدون taxonomy گلوبال)
        if ($site1_attr_id <= 0) {
            return ['name' => $attr_name];
        }
        
        // ۱) جستجو در جدول mapping (سریع‌ترین راه)
        // ⭐ این اولویت اول است چون قبلاً انتقال یافته‌است
        $mapping = Inventory_Sync_Database::get_attribute_mapping($site1_attr_id);
        if ($mapping && !empty($mapping->site2_attribute_id)) {
            return ['id' => intval($mapping->site2_attribute_id)];
        }
        
        // ۲) جستجو بر اساس نام در ویژگی‌های سایت ۲ (با کش)
        // ⭐ اگر ویژگی هنوز sync نشده، اما به نام موجود است
        if (!empty($attr_name)) {
            if ($this->site2_attributes_cache === null) {
                $attrs = $this->site2_api->get_attributes();
                $this->site2_attributes_cache = (!is_wp_error($attrs) && is_array($attrs)) ? $attrs : [];
                
                // لاگ اگر cache خالی است
                if (empty($this->site2_attributes_cache)) {
                    Inventory_Sync_Database::insert_log(
                        0,
                        'Attributes Cache',
                        'attributes_cache_empty',
                        'سایت 2',
                        'سایت 2',
                        'site1_attr_id: ' . $site1_attr_id,
                        'attr_name: ' . $attr_name,
                        'warning',
                        'کش ویژگی‌های سایت 2 خالی است'
                    );
                }
            }
            
            foreach ($this->site2_attributes_cache as $a) {
                if (strtolower($a['name'] ?? '') === strtolower($attr_name)) {
                    Inventory_Sync_Database::insert_log(
                        0,
                        $attr_name,
                        'attribute_resolved_by_name',
                        'سایت 1 (ID: ' . $site1_attr_id . ')',
                        'سایت 2',
                        'Mapping جدول پیدا نشد',
                        'جستجو بر اساس نام: ' . $a['id'],
                        'info'
                    );
                    return ['id' => intval($a['id'])];
                }
            }
        }
        
        // ۳) در نهایت به‌صورت ویژگی سفارشی با نام
        Inventory_Sync_Database::insert_log(
            0,
            $attr_name,
            'attribute_resolved_as_custom',
            'سایت 1 (ID: ' . $site1_attr_id . ')',
            'سایت 2',
            'نه mapping و نه نام موجود',
            'ساختن ویژگی سفارشی: ' . $attr_name,
            'warning'
        );
        return ['name' => $attr_name];
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
        
        // ⭐ بهبود: بررسی اینکه mapping قبلاً وجود دارد
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE site1_product_id = %d AND site2_product_id = %d",
                $site1_id,
                $site2_id
            )
        );
        
        if ($existing) {
            // بروز کن mapping موجود (برای تضمین کردن که status صحیح است)
            $wpdb->update(
                $wpdb->prefix . 'inventory_sync_mapping',
                [
                    'site1_sku' => $site1_sku,
                    'site2_sku' => $site2_sku,
                    'sync_enabled' => 1,
                    'sync_status' => 'synced',
                    'retry_count' => 0,
                    'error_message' => ''
                ],
                ['id' => $existing->id]
            );
        } else {
            // ایجاد mapping جدید
            $wpdb->insert(
                $wpdb->prefix . 'inventory_sync_mapping',
                [
                    'site1_product_id' => $site1_id,
                    'site2_product_id' => $site2_id,
                    'site1_sku' => $site1_sku,
                    'site2_sku' => $site2_sku,
                    'sync_enabled' => 1,
                    'sync_status' => 'synced',
                    'retry_count' => 0
                ]
            );
        }
    }
}
