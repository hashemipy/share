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
        add_action('wp_ajax_inventory_sync_get_all_products', [$this, 'ajax_get_all_products']);
        add_action('wp_ajax_inventory_sync_get_mappings', [$this, 'ajax_get_mappings']);
        add_action('wp_ajax_inventory_sync_add_mapping', [$this, 'ajax_add_mapping']);
        add_action('wp_ajax_inventory_sync_sync_all_mappings', [$this, 'ajax_sync_all_mappings']);
        add_action('wp_ajax_inventory_sync_sync_mapping', [$this, 'ajax_sync_mapping']);
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
     * تمام محصولات (دونوں سائٹ)
     */
    public function ajax_get_all_products() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        // سائٹ 1: WooCommerce محصولات
        $site1_products = wc_get_products(['limit' => 500, 'status' => 'publish']);
        $site1_data = [];
        
        if (is_array($site1_products) && !empty($site1_products)) {
            $site1_data = array_map(function($p) {
                return [
                    'id' => $p->get_id(),
                    'name' => $p->get_name(),
                    'sku' => $p->get_sku() ?? ''
                ];
            }, $site1_products);
        }
        
        // سائٹ 2: Remote API محصولات
        $site2_data = [];
        try {
            $api = new Inventory_Sync_API(
                Inventory_Sync_Settings::get_site2_url(),
                Inventory_Sync_Settings::get_site2_key(),
                Inventory_Sync_Settings::get_site2_secret()
            );
            
            $response = $api->get_products(500);
            
            // اگر response ایک آرایہ ہے اور خالی نہیں ہے
            if (is_array($response) && !isset($response['code'])) {
                $site2_data = array_map(function($p) {
                    return [
                        'id' => isset($p['id']) ? intval($p['id']) : null,
                        'name' => isset($p['name']) ? sanitize_text_field($p['name']) : 'نامشخص',
                        'sku' => isset($p['sku']) ? sanitize_text_field($p['sku']) : ''
                    ];
                }, $response);
            }
        } catch (Exception $e) {
            // API خرابی - خالی رہے گا
        }
        
        $data = [
            'site1' => $site1_data,
            'site2' => $site2_data
        ];
        
        wp_send_json_success($data);
    }
    
    /**
     * تمام mappings
     */
    public function ajax_get_mappings() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        global $wpdb;
        $mappings = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping ORDER BY created_at DESC LIMIT 100"
        );
        
        // ہر mapping کے لیے محصول کی معلومات شامل کریں
        foreach ($mappings as $m) {
            // سائٹ 1: Local WooCommerce
            $p1 = wc_get_product($m->site1_product_id);
            $m->site1_name = $p1 ? $p1->get_name() : 'حذف شدہ';
            $m->site1_sku = $p1 ? $p1->get_sku() : $m->site1_sku;
            $m->site1_stock = $p1 ? $p1->get_stock_quantity() : 0;
            
            // سائٹ 2: Remote API (اگر دستیاب ہو)
            $m->site2_name = 'نامشخص';
            $m->site2_stock = 0;
            
            try {
                $api = new Inventory_Sync_API(
                    Inventory_Sync_Settings::get_site2_url(),
                    Inventory_Sync_Settings::get_site2_key(),
                    Inventory_Sync_Settings::get_site2_secret()
                );
                
                $response = $api->get_product($m->site2_product_id);
                if (is_array($response) && !isset($response['code'])) {
                    $m->site2_name = isset($response['name']) ? sanitize_text_field($response['name']) : 'نامشخص';
                    $m->site2_stock = isset($response['stock_quantity']) ? intval($response['stock_quantity']) : 0;
                    $m->site2_sku = isset($response['sku']) ? sanitize_text_field($response['sku']) : $m->site2_sku;
                }
            } catch (Exception $e) {
                // سائٹ 2 دستیاب نہیں
            }
        }
        
        wp_send_json_success($mappings);
    }
    
    /**
     * نیا mapping اضافہ کریں
     */
    public function ajax_add_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        global $wpdb;
        $site1_id = intval($_POST['site1_product_id'] ?? 0);
        $site2_id = intval($_POST['site2_product_id'] ?? 0);
        
        if (!$site1_id || !$site2_id) {
            wp_send_json_error('محصول شناخت غلط ہے');
        }
        
        // صرف سائٹ 1 کو چیک کریں (یہ local ہے)
        $p1 = wc_get_product($site1_id);
        if (!$p1) {
            wp_send_json_error('سائٹ 1 میں محصول موجود نہیں');
        }
        
        // سائٹ 2 کی معلومات حاصل کریں (ممکن ہے remote ہو)
        $site2_sku = '';
        $site2_name = '';
        
        try {
            $api = new Inventory_Sync_API(
                Inventory_Sync_Settings::get_site2_url(),
                Inventory_Sync_Settings::get_site2_key(),
                Inventory_Sync_Settings::get_site2_secret()
            );
            
            $products = $api->get_products(1, $site2_id);
            if (is_array($products) && !empty($products)) {
                $p2 = $products[0];
                $site2_sku = isset($p2['sku']) ? sanitize_text_field($p2['sku']) : '';
                $site2_name = isset($p2['name']) ? sanitize_text_field($p2['name']) : '';
            } else {
                wp_send_json_error('سائٹ 2 میں محصول موجود نہیں');
            }
        } catch (Exception $e) {
            wp_send_json_error('سائٹ 2 سے رابطہ نہیں ہو سکا');
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'site1_product_id' => $site1_id,
                'site2_product_id' => $site2_id,
                'site1_sku' => $p1->get_sku() ?? '',
                'site2_sku' => $site2_sku,
                'sync_enabled' => 1,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s']
        );
        
        if ($result) {
            wp_send_json_success('Mapping اضافہ ہو گیا');
        } else {
            wp_send_json_error('ڈیٹا بیس میں خرابی: ' . $wpdb->last_error);
        }
    }
    
    /**
     * تمام mappings کو sync کریں
     */
    public function ajax_sync_all_mappings() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        $manager = Inventory_Sync_Manager::get_instance();
        $manager->sync_all_mappings();
        
        wp_send_json_success('تمام sync ہو گئے');
    }
    
    /**
     * ایک mapping کو sync کریں
     */
    public function ajax_sync_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        $mapping_id = intval($_POST['mapping_id'] ?? 0);
        $manager = Inventory_Sync_Manager::get_instance();
        $manager->sync_inventory($mapping_id);
        
        wp_send_json_success('Sync ہو گیا');
    }
    
    /**
     * Mapping کو toggle کریں
     */
    public function ajax_toggle_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        global $wpdb;
        $mapping_id = intval($_POST['mapping_id'] ?? 0);
        
        if (!$mapping_id) {
            wp_send_json_error('Mapping ID غلط ہے');
        }
        
        // موجودہ state حاصل کریں
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT sync_enabled FROM {$wpdb->prefix}inventory_sync_mapping WHERE id = %d",
                $mapping_id
            )
        );
        
        if (!$current) {
            wp_send_json_error('Mapping موجود نہیں');
        }
        
        // toggle کریں
        $new_state = $current->sync_enabled ? 0 : 1;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'inventory_sync_mapping',
            ['sync_enabled' => $new_state],
            ['id' => $mapping_id],
            ['%d'],
            ['%d']
        );
        
        if ($result !== false) {
            wp_send_json_success([
                'enabled' => $new_state,
                'message' => $new_state ? 'فعال ہو گیا' : 'غیر فعال ہو گیا'
            ]);
        } else {
            wp_send_json_error('اپڈیٹ میں خرابی');
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
        
        if (!$mapping_id) {
            wp_send_json_error('Mapping ID غلط ہے');
        }
        
        // پہلے چیک کریں کہ موجود ہے
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping WHERE id = %d",
                $mapping_id
            )
        );
        
        if (!$exists) {
            wp_send_json_error('Mapping موجود نہیں');
        }
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'inventory_sync_mapping',
            ['id' => $mapping_id],
            ['%d']
        );
        
        if ($result) {
            wp_send_json_success('حذف ہو گیا');
        } else {
            wp_send_json_error('ڈیٹا بیس خرابی: ' . $wpdb->last_error);
        }
    }
}
