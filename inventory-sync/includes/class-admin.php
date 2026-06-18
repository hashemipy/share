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
        
        // ✨ جستجوی محصولات برای جفت‌سازی
        add_action('wp_ajax_inventory_sync_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_inventory_sync_create_pair', [$this, 'ajax_create_pair']);
        add_action('wp_ajax_inventory_sync_get_pairs', [$this, 'ajax_get_pairs']);
        add_action('wp_ajax_inventory_sync_sync_pair', [$this, 'ajax_sync_pair']);
        add_action('wp_ajax_inventory_sync_delete_pair', [$this, 'ajax_delete_pair']);
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
        
        // ✨ CSS برای جفت‌سازی محصولات
        wp_enqueue_style(
            'inventory-sync-pairing',
            INVENTORY_SYNC_PLUGIN_URL . 'assets/css/pairing.css',
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
        
        // Initialize pairing tab
        wp_add_inline_script('inventory-sync-admin', '
        jQuery(document).ready(function($) {
            if (typeof window.app !== "undefined" && typeof window.app.loadPairs === "function") {
                window.app.loadPairs();
            }
        });
        ');
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
     * ✨ جستجوی محصولات برای جفت‌سازی
     */
    public function ajax_search_products() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $site = sanitize_text_field($_POST['site'] ?? '');
        
        if (empty($search) || empty($site)) {
            wp_send_json_error('پارامترهای مورد نیاز');
        }
        
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
        
        // جستجوی محصول (فقط محصولات ساده)
        $products = $api->get_products_by_search($search);
        
        if (is_wp_error($products)) {
            wp_send_json_error($products->get_error_message());
        }
        
        // فیلتر کردن محصولات (فقط ساده)
        $simple_products = [];
        if (is_array($products)) {
            foreach ($products as $product) {
                if (($product['type'] ?? 'simple') === 'simple') {
                    $simple_products[] = [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'sku' => $product['sku'] ?? '',
                        'stock' => isset($product['stock_quantity']) ? intval($product['stock_quantity']) : 0
                    ];
                }
            }
        }
        
        wp_send_json_success($simple_products);
    }
    
    /**
     * ✨ ایجاد جفت محصول
     */
    public function ajax_create_pair() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $site1_product_id = intval($_POST['site1_product_id'] ?? 0);
        $site1_product_name = sanitize_text_field($_POST['site1_product_name'] ?? '');
        $site1_sku = sanitize_text_field($_POST['site1_sku'] ?? '');
        
        $site2_product_id = intval($_POST['site2_product_id'] ?? 0);
        $site2_product_name = sanitize_text_field($_POST['site2_product_name'] ?? '');
        $site2_sku = sanitize_text_field($_POST['site2_sku'] ?? '');
        
        $sync_direction = sanitize_text_field($_POST['sync_direction'] ?? 'bidirectional');
        
        if (!$site1_product_id || !$site2_product_id) {
            wp_send_json_error('شناسه‌های محصول مورد نیاز است');
        }
        
        $result = Inventory_Sync_Database::create_product_pair(
            $site1_product_id,
            $site2_product_id,
            $site1_product_name,
            $site2_product_name,
            $site1_sku,
            $site2_sku,
            $sync_direction
        );
        
        if ($result) {
            wp_send_json_success('جفت ایجاد شد');
        } else {
            wp_send_json_error('خطا در ایجاد جفت');
        }
    }
    
    /**
     * ✨ دریافت تمام جفت‌های فعال
     */
    public function ajax_get_pairs() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $pairs = Inventory_Sync_Database::get_all_active_pairs();
        
        if (empty($pairs)) {
            wp_send_json_success([]);
            return;
        }
        
        $formatted_pairs = [];
        foreach ($pairs as $pair) {
            // دریافت موجودی محصول‌ها
            $site1_stock = $this->get_product_stock($pair->site1_product_id, 'site1');
            $site2_stock = $this->get_product_stock($pair->site2_product_id, 'site2');
            
            $formatted_pairs[] = [
                'id' => $pair->id,
                'site1_product_id' => $pair->site1_product_id,
                'site1_product_name' => $pair->site1_product_name,
                'site1_stock' => $site1_stock,
                'site2_product_id' => $pair->site2_product_id,
                'site2_product_name' => $pair->site2_product_name,
                'site2_stock' => $site2_stock,
                'sync_direction' => $pair->sync_direction,
                'last_sync' => $pair->last_sync,
                'sync_count' => $pair->sync_count,
                'is_active' => $pair->is_active
            ];
        }
        
        wp_send_json_success($formatted_pairs);
    }
    
    /**
     * ✨ Sync دستی یک جفت
     */
    public function ajax_sync_pair() {
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
            wp_send_json_error('جفت یافت نشد');
        }
        
        // Sync کردن موجودی
        $result = Inventory_Sync_Bidirectional::sync_pair_inventory($pair_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('جفت sync شد');
    }
    
    /**
     * ✨ حذف جفت
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
        
        $result = Inventory_Sync_Database::delete_pair($pair_id);
        
        if ($result) {
            wp_send_json_success('جفت حذف شد');
        } else {
            wp_send_json_error('خطا در حذف');
        }
    }
    
    /**
     * ✨ دریافت موجودی محصول از یک سایت
     */
    private function get_product_stock($product_id, $site) {
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
        
        $product = $api->get_product($product_id);
        
        if (is_wp_error($product)) {
            return 0;
        }
        
        return isset($product['stock_quantity']) ? intval($product['stock_quantity']) : 0;
    }
}
