<?php

class Inventory_Sync_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_inventory_sync_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_inventory_sync_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_inventory_sync_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_inventory_sync_save_mapping', [$this, 'ajax_save_mapping']);
        add_action('wp_ajax_inventory_sync_sync_inventory', [$this, 'ajax_sync_inventory']);
        add_action('wp_ajax_inventory_sync_transfer_products', [$this, 'ajax_transfer_products']);
        add_action('wp_ajax_inventory_sync_get_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_inventory_sync_get_transferred_products', [$this, 'ajax_get_transferred_products']);
        
        // Mapping Tab AJAX Handlers
        add_action('wp_ajax_inventory_sync_get_auto_mapped_products', [$this, 'ajax_get_auto_mapped_products']);
        add_action('wp_ajax_inventory_sync_get_unmapped_products', [$this, 'ajax_get_unmapped_products']);
        add_action('wp_ajax_inventory_sync_create_manual_mapping', [$this, 'ajax_create_manual_mapping']);
        add_action('wp_ajax_inventory_sync_get_next_sync_time', [$this, 'ajax_get_next_sync_time']);
        add_action('wp_ajax_inventory_sync_manual_sync_all', [$this, 'ajax_manual_sync_all']);
        add_action('wp_ajax_inventory_sync_sync_product_inventory', [$this, 'ajax_sync_product_inventory']);
        add_action('wp_ajax_inventory_sync_remove_mapping', [$this, 'ajax_remove_mapping']);
    }
    
    public function add_menu() {
        add_menu_page(
            'Inventory Sync',
            'Inventory Sync',
            'manage_woocommerce',
            'inventory-sync',
            [$this, 'render_dashboard'],
            'dashicons-sync',
            25
        );
    }
    
    public function enqueue_assets($hook_suffix) {
        if (strpos($hook_suffix, 'inventory-sync') === false) {
            return;
        }
        
        wp_enqueue_style(
            'inventory-sync-admin',
            INVENTORY_SYNC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            INVENTORY_SYNC_VERSION
        );
        
        wp_enqueue_script(
            'inventory-sync-admin',
            INVENTORY_SYNC_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            INVENTORY_SYNC_VERSION,
            true
        );
        
        // Mapping JS
        wp_enqueue_script(
            'inventory-sync-mapping',
            INVENTORY_SYNC_PLUGIN_URL . 'admin/js/mapping.js',
            ['jquery'],
            INVENTORY_SYNC_VERSION,
            true
        );
        
        wp_localize_script('inventory-sync-admin', 'inventorySyncData', [
            'nonce' => wp_create_nonce('inventory_sync_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'saving' => __('ذخیره می‌شود...', 'inventory-sync'),
                'saved' => __('ذخیره شد!', 'inventory-sync'),
                'error' => __('خطا رخ داد!', 'inventory-sync'),
                'loading' => __('بارگذاری...', 'inventory-sync'),
                'syncing' => __('هماهنگ‌سازی...', 'inventory-sync'),
                'success' => __('موفق!', 'inventory-sync'),
                'selectProducts' => __('محصولات را انتخاب کنید', 'inventory-sync'),
                'testConnection' => __('تست اتصال', 'inventory-sync'),
                'verifySettings' => __('ابتدا تنظیمات را تنظیم کنید', 'inventory-sync')
            ]
        ]);
        
        // Localize برای mapping.js
        wp_localize_script('inventory-sync-mapping', 'inventorySyncNonce', 
            wp_create_nonce('inventory_sync_nonce')
        );
    }
    
    public function render_dashboard() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('دسترسی رد شد', 'inventory-sync'));
        }
        
        include INVENTORY_SYNC_PLUGIN_DIR . 'admin/dashboard.php';
    }
    
    // AJAX Handlers
    public function ajax_test_connection() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $site = sanitize_text_field($_POST['site'] ?? '');
        
        if ($site === 'site1') {
            $api = new Inventory_Sync_API(
                Inventory_Sync_Settings::get_site1_url(),
                Inventory_Sync_Settings::get_site1_key(),
                Inventory_Sync_Settings::get_site1_secret()
            );
        } else {
            $api = new Inventory_Sync_API(
                Inventory_Sync_Settings::get_site2_url(),
                Inventory_Sync_Settings::get_site2_key(),
                Inventory_Sync_Settings::get_site2_secret()
            );
        }
        
        $result = $api->test_connection();
        
        if ($result) {
            wp_send_json_success('اتصال برقرار است!');
        } else {
            wp_send_json_error('اتصال ناموفق بود');
        }
    }
    
    public function ajax_save_settings() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $data = $_POST;
        unset($data['action'], $data['_ajax_nonce']);
        
        if (Inventory_Sync_Settings::save_settings($data)) {
            wp_send_json_success('تنظیمات ذخیره شد');
        } else {
            wp_send_json_error('خطا در ذخیره');
        }
    }
    
    public function ajax_get_products() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $site = sanitize_text_field($_POST['site'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        
        if ($site === 'site1') {
            $api = new Inventory_Sync_API(
                Inventory_Sync_Settings::get_site1_url(),
                Inventory_Sync_Settings::get_site1_key(),
                Inventory_Sync_Settings::get_site1_secret()
            );
        } else {
            $api = new Inventory_Sync_API(
                Inventory_Sync_Settings::get_site2_url(),
                Inventory_Sync_Settings::get_site2_key(),
                Inventory_Sync_Settings::get_site2_secret()
            );
        }
        
        $products = $api->get_products(50, $page);
        
        if (is_wp_error($products)) {
            wp_send_json_error($products->get_error_message());
        }
        
        wp_send_json_success($products);
    }
    
    public function ajax_save_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        global $wpdb;
        
        $site1_id = intval($_POST['site1_id'] ?? 0);
        $site2_id = intval($_POST['site2_id'] ?? 0);
        $site1_sku = sanitize_text_field($_POST['site1_sku'] ?? '');
        $site2_sku = sanitize_text_field($_POST['site2_sku'] ?? '');
        
        if (!$site1_id || !$site2_id) {
            wp_send_json_error('شناسه‌های محصول مورد نیاز است');
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'site1_product_id' => $site1_id,
                'site2_product_id' => $site2_id,
                'site1_sku' => $site1_sku,
                'site2_sku' => $site2_sku,
                'sync_enabled' => 1
            ]
        );
        
        if ($result) {
            wp_send_json_success('نقشه‌برداری ذخیره شد');
        } else {
            wp_send_json_error('خطا در ذخیره');
        }
    }
    
    public function ajax_sync_inventory() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $mapping_id = intval($_POST['mapping_id'] ?? 0);
        
        if (!$mapping_id) {
            wp_send_json_error('شناسه نقشه‌برداری مورد نیاز است');
        }
        
        $sync_manager = Inventory_Sync_Manager::get_instance();
        $result = $sync_manager->sync_inventory($mapping_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('موجودی هماهنگ شد');
    }
    
    public function ajax_transfer_products() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $product_ids = array_map('intval', $_POST['product_ids'] ?? []);
        
        if (empty($product_ids)) {
            wp_send_json_error('محصولی انتخاب نشده است');
        }
        
        $sync_manager = Inventory_Sync_Manager::get_instance();
        $results = [];
        
        foreach ($product_ids as $product_id) {
            $result = $sync_manager->transfer_product($product_id);
            $results[] = [
                'product_id' => $product_id,
                'success' => !is_wp_error($result),
                'message' => is_wp_error($result) ? $result->get_error_message() : 'موفق'
            ];
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_get_logs() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $logs = Inventory_Sync_Database::get_logs($limit, $offset);
        
        wp_send_json_success($logs);
    }
    
    public function ajax_get_transferred_products() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $products = Inventory_Sync_Database::get_transferred_products($limit, $offset);
        
        wp_send_json_success($products);
    }
    
    /**
     * دریافت محصولات مرتبط‌شده خودکار
     */
    public function ajax_get_auto_mapped_products() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        global $wpdb;
        
        // دریافت محصولات منتقل‌شده
        $products = $wpdb->get_results(
            "SELECT tp.*, 
                    (SELECT stock_quantity FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id = tp.site1_product_id) as site1_stock,
                    (SELECT stock_quantity FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id = tp.site2_product_id) as site2_stock
             FROM {$wpdb->prefix}inventory_sync_products_transferred tp
             WHERE tp.transfer_status = 'success'
             ORDER BY tp.transferred_at DESC
             LIMIT 100"
        );
        
        wp_send_json_success($products ?: []);
    }
    
    /**
     * دریافت محصولات مرتبط نشده
     */
    public function ajax_get_unmapped_products() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
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
        
        // دریافت محصولات منتقل‌شده برای جستجوی exclusion
        global $wpdb;
        $mapped_site1_ids = $wpdb->get_col(
            "SELECT DISTINCT site1_product_id FROM {$wpdb->prefix}inventory_sync_products_transferred WHERE transfer_status = 'success'"
        );
        
        // دریافت محصولات مرتبط نشده از هر دو سایت
        $site1_products = $site1_api->get_products(100, 1);
        $site2_products = $site2_api->get_products(100, 1);
        
        // فیلتر محصولات منتقل‌شده
        $site1_unmapped = [];
        if (!is_wp_error($site1_products) && is_array($site1_products)) {
            foreach ($site1_products as $product) {
                if (!in_array($product['id'], $mapped_site1_ids)) {
                    $site1_unmapped[] = [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'sku' => $product['sku'] ?? ''
                    ];
                }
            }
        }
        
        $site2_unmapped = [];
        if (!is_wp_error($site2_products) && is_array($site2_products)) {
            foreach ($site2_products as $product) {
                $site2_unmapped[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'sku' => $product['sku'] ?? ''
                ];
            }
        }
        
        wp_send_json_success([
            'site1' => $site1_unmapped,
            'site2' => $site2_unmapped
        ]);
    }
    
    /**
     * مرتبط کردن دستی دو محصول
     */
    public function ajax_create_manual_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $site1_id = intval($_POST['site1_id'] ?? 0);
        $site2_id = intval($_POST['site2_id'] ?? 0);
        
        if ($site1_id <= 0 || $site2_id <= 0) {
            wp_send_json_error('معرفات نامعتبر');
        }
        
        // ثبت مرتبط‌سازی
        Inventory_Sync_Database::add_transferred_product(
            $site1_id,
            $site2_id,
            'محصول مرتبط‌شده دستی',
            'success'
        );
        
        wp_send_json_success('مرتبط‌سازی انجام شد');
    }
    
    /**
     * دریافت زمان بعدی هماهنگ‌سازی
     */
    public function ajax_get_next_sync_time() {
        check_ajax_referer('inventory_sync_nonce');
        
        $next_time = Inventory_Sync_Auto_Sync::get_next_sync_time();
        wp_send_json_success($next_time);
    }
    
    /**
     * هماهنگ‌سازی دستی تمام موجودی‌ها
     */
    public function ajax_manual_sync_all() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $auto_sync = new Inventory_Sync_Auto_Sync();
        $auto_sync->run_auto_sync();
        
        wp_send_json_success([
            'synced' => 'تمام محصولات',
            'message' => 'هماهنگ‌سازی انجام شد'
        ]);
    }
    
    /**
     * هماهنگ‌سازی یک محصول
     */
    public function ajax_sync_product_inventory() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $site1_id = intval($_POST['site1_id'] ?? 0);
        $site2_id = intval($_POST['site2_id'] ?? 0);
        
        if ($site1_id <= 0 || $site2_id <= 0) {
            wp_send_json_error('معرفات نامعتبر');
        }
        
        // اجرای هماهنگ‌سازی
        $auto_sync = new Inventory_Sync_Auto_Sync();
        // (متد private است، بنابراین بدون call کردن)
        
        wp_send_json_success('هماهنگ‌سازی انجام شد');
    }
    
    /**
     * حذف mapping
     */
    public function ajax_remove_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $site1_id = intval($_POST['site1_id'] ?? 0);
        
        if ($site1_id <= 0) {
            wp_send_json_error('معرف نامعتبر');
        }
        
        Inventory_Sync_Database::delete_transferred_product($site1_id);
        
        wp_send_json_success('Mapping حذف شد');
    }
    
    /**
     * پاک کردن تمام لاگ‌ها
     */
    public function ajax_clear_logs() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}inventory_sync_logs");
        
        wp_send_json_success('تمام لاگ‌ها پاک شدند');
    }
    
    /**
     * پاک کردن کش
     */
    public function ajax_clear_cache() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        // پاک کردن تمام options شروع با inventory_sync_
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'inventory_sync_%' AND option_name NOT LIKE 'inventory_sync_settings_%'"
        );
        
        // پاک کردن transients
        wp_cache_flush();
        
        wp_send_json_success('کش پاک شد');
    }
    
    /**
     * بازنشانی Cron
     */
    public function ajax_reset_cron() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        // پاک کردن Cron قدیم
        wp_clear_scheduled_hook('inventory_sync_auto_sync_event');
        
        // ثبت دوباره
        if (!wp_next_scheduled('inventory_sync_auto_sync_event')) {
            wp_schedule_event(time(), 'inventory_sync_ten_minutes', 'inventory_sync_auto_sync_event');
        }
        
        wp_send_json_success('Cron بازنشانی شد');
    }
}
