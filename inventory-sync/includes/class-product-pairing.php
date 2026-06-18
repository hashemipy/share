<?php

/**
 * Product_Pairing - مدیریت جفت‌سازی دو طرفه محصولات
 * 
 * وظایف:
 * 1. جفت کردن محصول A از سایت 1 با محصول B از سایت 2
 * 2. ذخیره‌ی جفت‌ها در جدول database
 * 3. مدیریت حالت جفت‌ها (فعال/غیرفعال، حذف)
 * 4. شنیدن تغییرات موجودی و فعال‌سازی sync خودکار
 */
class Inventory_Sync_Product_Pairing {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * شنیدن تغییرات موجودی برای جفت‌های فعال
     */
    private function init_hooks() {
        // وقتی موجودی محصول تغییر کند (ساده یا متغیر)
        add_action('woocommerce_product_set_stock', [$this, 'on_product_stock_changed'], 10, 1);
        
        // وقتی سفارش کامل شود
        add_action('woocommerce_order_status_completed', [$this, 'on_order_completed'], 10, 1);
        
        // AJAX handler برای ایجاد جفت
        add_action('wp_ajax_inventory_sync_create_pair', [$this, 'ajax_create_pair']);
        
        // AJAX handler برای حذف جفت
        add_action('wp_ajax_inventory_sync_delete_pair', [$this, 'ajax_delete_pair']);
        
        // AJAX handler برای دسترسی به محصولات
        add_action('wp_ajax_inventory_sync_get_paired_products', [$this, 'ajax_get_paired_products']);
        
        // AJAX handler برای لاگ‌های جفت
        add_action('wp_ajax_inventory_sync_get_pair_logs', [$this, 'ajax_get_pair_logs']);
        
        // AJAX handler برای دسترسی دستی sync
        add_action('wp_ajax_inventory_sync_manual_sync_pair', [$this, 'ajax_manual_sync_pair']);
    }
    
    /**
     * وقتی موجودی محصول تغییر کند - sync خودکار
     * 
     * @param WC_Product $product
     */
    public function on_product_stock_changed($product) {
        if (!Inventory_Sync_Settings::get_auto_sync_enabled()) {
            return;
        }
        
        if (!is_object($product) || !method_exists($product, 'get_id')) {
            return;
        }
        
        $product_id = $product->get_id();
        
        // اگر واریاسیون است، والد را بگیر (فقط محصولات ساده را پیدا کن)
        if (method_exists($product, 'get_parent_id') && $product->get_parent_id()) {
            return; // تنها محصولات ساده را پشتیبانی می‌کنیم
        }
        
        // پیدا کن این محصول در کدام جفت قرار دارد
        $pair = Inventory_Sync_Database::get_pair_by_site_product($product_id, 'site1');
        
        if (!$pair) {
            // شاید این محصول از سایت 2 است
            $pair = Inventory_Sync_Database::get_pair_by_site_product($product_id, 'site2');
        }
        
        if ($pair) {
            // برنامه‌ریزی sync خودکار
            wp_schedule_single_event(
                time() + 2,
                'inventory_sync_pair_update',
                [$pair->id]
            );
        }
    }
    
    /**
     * وقتی سفارش کامل شود
     * 
     * @param int $order_id
     */
    public function on_order_completed($order_id) {
        if (!Inventory_Sync_Settings::get_auto_sync_enabled()) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // برای هر محصول در سفارش
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            
            // پیدا کن این محصول در کدام جفت است
            $pair = Inventory_Sync_Database::get_pair_by_site_product($product_id, 'site1');
            
            if (!$pair) {
                $pair = Inventory_Sync_Database::get_pair_by_site_product($product_id, 'site2');
            }
            
            if ($pair) {
                wp_schedule_single_event(
                    time() + 3,
                    'inventory_sync_pair_update',
                    [$pair->id]
                );
            }
        }
    }
    
    /**
     * ایجاد جفت جدید
     * 
     * @param int $site1_id
     * @param int $site2_id
     * @param string $sync_direction
     * @return int|WP_Error - ID جفت یا خطا
     */
    public function create_pair($site1_id, $site2_id, $sync_direction = 'bidirectional') {
        // بررسی که آیا محصول‌ها قبلاً جفت شده‌اند
        $existing = Inventory_Sync_Database::get_product_pair_by_ids($site1_id, $site2_id);
        if ($existing) {
            return new WP_Error('pair_exists', 'این محصول‌ها قبلاً جفت شده‌اند');
        }
        
        // دریافت اطلاعات محصول از هر دو سایت
        $site1_api = new Inventory_Sync_API(
            Inventory_Sync_Settings::get_site1_url(),
            Inventory_Sync_Settings::get_site1_key(),
            Inventory_Sync_Settings::get_site1_secret()
        );
        
        $site2_api = new Inventory_Sync_API(
            Inventory_Sync_Settings::get_site2_url(),
            Inventory_Sync_Settings::get_site2_key(),
            Inventory_Sync_Settings::get_site2_secret()
        );
        
        $product1 = $site1_api->get_product($site1_id);
        $product2 = $site2_api->get_product($site2_id);
        
        if (is_wp_error($product1) || is_wp_error($product2)) {
            return new WP_Error('product_not_found', 'یکی از محصول‌ها پیدا نشد');
        }
        
        // ایجاد جفت
        $result = Inventory_Sync_Database::create_product_pair(
            $site1_id,
            $site2_id,
            $product1['name'] ?? 'Product 1',
            $product2['name'] ?? 'Product 2',
            $product1['sku'] ?? '',
            $product2['sku'] ?? '',
            $sync_direction
        );
        
        if (!$result) {
            return new WP_Error('pair_creation_failed', 'خطا در ایجاد جفت');
        }
        
        // ثبت لاگ
        Inventory_Sync_Database::insert_log(
            $site1_id,
            $product1['name'] ?? 'Product 1',
            'create_pair',
            'سایت 1',
            'سایت 2',
            '',
            sprintf('جفت شد با محصول #%d', $site2_id),
            'success'
        );
        
        return true;
    }
    
    /**
     * AJAX: ایجاد جفت
     */
    public function ajax_create_pair() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $site1_id = intval($_POST['site1_id'] ?? 0);
        $site2_id = intval($_POST['site2_id'] ?? 0);
        $sync_direction = sanitize_text_field($_POST['sync_direction'] ?? 'bidirectional');
        
        if (!$site1_id || !$site2_id) {
            wp_send_json_error('شناسه‌های محصول مورد نیاز است');
        }
        
        $result = $this->create_pair($site1_id, $site2_id, $sync_direction);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('جفت با موفقیت ایجاد شد');
    }
    
    /**
     * AJAX: حذف جفت
     */
    public function ajax_delete_pair() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $pair_id = intval($_POST['pair_id'] ?? 0);
        
        if (!$pair_id) {
            wp_send_json_error('شناسه جفت مورد نیاز است');
        }
        
        $pair = Inventory_Sync_Database::get_product_pair($pair_id);
        if (!$pair) {
            wp_send_json_error('جفت پیدا نشد');
        }
        
        // حذف جفت
        Inventory_Sync_Database::delete_pair($pair_id);
        
        // ثبت لاگ
        Inventory_Sync_Database::insert_log(
            $pair->site1_product_id,
            $pair->site1_product_name,
            'delete_pair',
            'سایت 1',
            'سایت 2',
            sprintf('جفت با #%d', $pair->site2_product_id),
            '',
            'success'
        );
        
        wp_send_json_success('جفت حذف شد');
    }
    
    /**
     * AJAX: دریافت محصولات جفت‌شده
     */
    public function ajax_get_paired_products() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $pairs = Inventory_Sync_Database::get_all_active_pairs($limit, $offset);
        
        wp_send_json_success($pairs);
    }
    
    /**
     * AJAX: دریافت لاگ‌های جفت
     */
    public function ajax_get_pair_logs() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $pair_id = intval($_POST['pair_id'] ?? 0);
        $page = intval($_POST['page'] ?? 1);
        
        if (!$pair_id) {
            wp_send_json_error('شناسه جفت مورد نیاز است');
        }
        
        $pair = Inventory_Sync_Database::get_product_pair($pair_id);
        if (!$pair) {
            wp_send_json_error('جفت پیدا نشد');
        }
        
        global $wpdb;
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_logs 
                 WHERE (product_id = %d OR product_id = %d) 
                 ORDER BY created_at DESC 
                 LIMIT %d OFFSET %d",
                $pair->site1_product_id,
                $pair->site2_product_id,
                $limit,
                $offset
            )
        );
        
        wp_send_json_success($logs);
    }
    
    /**
     * AJAX: sync دستی جفت
     */
    public function ajax_manual_sync_pair() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $pair_id = intval($_POST['pair_id'] ?? 0);
        
        if (!$pair_id) {
            wp_send_json_error('شناسه جفت مورد نیاز است');
        }
        
        $syncer = Inventory_Sync_Bidirectional::get_instance();
        $result = $syncer->sync_pair_immediately($pair_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('جفت با موفقیت هماهنگ‌سازی شد');
    }
}
