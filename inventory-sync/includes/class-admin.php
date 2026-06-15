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
        
        // Mapping handlers
        add_action('wp_ajax_inventory_sync_get_mappings', [$this, 'ajax_get_mappings']);
        add_action('wp_ajax_inventory_sync_create_mapping', [$this, 'ajax_create_mapping']);
        add_action('wp_ajax_inventory_sync_toggle_mapping', [$this, 'ajax_toggle_mapping']);
        add_action('wp_ajax_inventory_sync_delete_mapping', [$this, 'ajax_delete_mapping']);
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
        $search = sanitize_text_field($_POST['search'] ?? '');
        
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
        
        // صرف WooCommerce سایت (site1) کے لیے
        if ($site === 'site1') {
            $products = wc_get_products([
                'limit' => 50,
                'offset' => ($page - 1) * 50,
                's' => $search ?: ''
            ]);
            
            $data = [];
            foreach ($products as $product) {
                $data[] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku() ?: 'N/A'
                ];
            }
        } else {
            // Remote سائٹ کے لیے
            $products = $api->get_products(50, $page, $search);
            
            if (is_wp_error($products)) {
                wp_send_json_error($products->get_error_message());
            }
            
            $data = $products;
        }
        
        wp_send_json_success($data);
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
    
    // === Mapping AJAX Handlers ===
    
    /**
     * تمام mappings حاصل کریں
     */
    public function ajax_get_mappings() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        global $wpdb;
        $page = intval($_POST['page'] ?? 1);
        $search = sanitize_text_field($_POST['search'] ?? '');
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping WHERE 1=1";
        if ($search) {
            $query .= $wpdb->prepare(" AND (site1_sku LIKE %s OR site2_sku LIKE %s)", 
                '%' . $search . '%', '%' . $search . '%');
        }
        $query .= " ORDER BY created_at DESC";
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}inventory_sync_mapping" . 
            ($search ? $wpdb->prepare(" WHERE site1_sku LIKE %s OR site2_sku LIKE %s", 
                '%' . $search . '%', '%' . $search . '%') : ''));
        
        $mappings = $wpdb->get_results($query . $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset));
        
        // معلومات کے ساتھ غنی بنائیں
        foreach ($mappings as $m) {
            $site1 = wc_get_product($m->site1_product_id);
            $site2 = wc_get_product($m->site2_product_id);
            
            $m->site1_name = $site1 ? $site1->get_name() : 'حذف شدہ';
            $m->site2_name = $site2 ? $site2->get_name() : 'حذف شدہ';
            $m->site1_stock = $site1 ? $site1->get_stock_quantity() : 0;
            $m->site2_stock = $site2 ? $site2->get_stock_quantity() : 0;
        }
        
        wp_send_json_success([
            'mappings' => $mappings,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ]);
    }
    
    /**
     * نیا mapping بنائیں
     */
    public function ajax_create_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        $site1_id = intval($_POST['site1_product_id'] ?? 0);
        $site2_id = intval($_POST['site2_product_id'] ?? 0);
        
        if (!$site1_id || !$site2_id) {
            wp_send_json_error('دونوں محصول درکار ہیں');
        }
        
        global $wpdb;
        
        // چیک کریں پہلے سے موجود ہے
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping 
             WHERE site1_product_id = %d AND site2_product_id = %d",
            $site1_id, $site2_id
        ));
        
        if ($existing) {
            wp_send_json_error('یہ mapping پہلے سے موجود ہے');
        }
        
        $site1 = wc_get_product($site1_id);
        $site2 = wc_get_product($site2_id);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'site1_product_id' => $site1_id,
                'site2_product_id' => $site2_id,
                'site1_sku' => $site1 ? $site1->get_sku() : '',
                'site2_sku' => $site2 ? $site2->get_sku() : '',
                'sync_enabled' => 1,
                'sync_status' => 'synced'
            ]
        );
        
        if ($result) {
            // فوری sync کریں
            $sync_manager = Inventory_Sync_Manager::get_instance();
            $sync_manager->sync_inventory($wpdb->insert_id);
            
            wp_send_json_success('Mapping بن گیا اور موجودی ہماہنگ ہو رہی ہے');
        } else {
            wp_send_json_error('خرابی: mapping نہیں بن سکا');
        }
    }
    
    /**
     * Mapping کو toggle کریں (فعال/غیر فعال)
     */
    public function ajax_toggle_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        global $wpdb;
        $mapping_id = intval($_POST['mapping_id'] ?? 0);
        $enabled = intval($_POST['enabled'] ?? 0);
        
        $result = $wpdb->update(
            $wpdb->prefix . 'inventory_sync_mapping',
            ['sync_enabled' => $enabled],
            ['id' => $mapping_id]
        );
        
        if ($result !== false) {
            wp_send_json_success('بہتر بنایا گیا');
        } else {
            wp_send_json_error('خرابی');
        }
    }
    
    /**
     * Mapping کو حذف کریں
     */
    public function ajax_delete_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        global $wpdb;
        $mapping_id = intval($_POST['mapping_id'] ?? 0);
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'inventory_sync_mapping',
            ['id' => $mapping_id]
        );
        
        if ($result) {
            wp_send_json_success('حذف ہو گیا');
        } else {
            wp_send_json_error('خرابی');
        }
    }
}
