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
}
