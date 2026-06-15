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
        
        // ⭐ REST API Endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
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
            'rest_url' => rest_url(),
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
     * ⭐ ثبت REST API endpoints برای کار با mappings
     */
    public function register_rest_routes() {
        // GET: دریافت تمام mappings
        register_rest_route('inventory-sync/v1', '/mappings', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_mappings'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        // POST: ایجاد mapping جدید
        register_rest_route('inventory-sync/v1', '/mappings', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_create_mapping'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        // PUT: اپدیت mapping
        register_rest_route('inventory-sync/v1', '/mappings/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'rest_update_mapping'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        // DELETE: حذف mapping
        register_rest_route('inventory-sync/v1', '/mappings/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'rest_delete_mapping'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        // GET: دریافت محصولات سایت 1
        register_rest_route('inventory-sync/v1', '/products/site1', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_site1_products'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        // GET: دریافت محصولات سایت 2
        register_rest_route('inventory-sync/v1', '/products/site2', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_site2_products'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
    }
    
    /**
     * بررسی اجازه برای REST API
     */
    public function rest_permission_check() {
        return current_user_can('manage_woocommerce');
    }
    
    /**
     * GET: دریافت تمام mappings
     */
    public function rest_get_mappings(WP_REST_Request $request) {
        global $wpdb;
        
        $per_page = intval($request->get_param('per_page') ?? 20);
        $paged = intval($request->get_param('paged') ?? 1);
        $search = sanitize_text_field($request->get_param('search') ?? '');
        
        $offset = ($paged - 1) * $per_page;
        
        // SQL query
        $query = "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping WHERE 1=1";
        $params = [];
        
        if ($search) {
            $query .= " AND (site1_sku LIKE %s OR site2_sku LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        $query .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        if (!empty($params)) {
            $mappings = $wpdb->get_results($wpdb->prepare($query, ...$params));
        } else {
            $mappings = $wpdb->get_results($query);
        }
        
        // دریافت count کل
        $total_query = "SELECT COUNT(*) as total FROM {$wpdb->prefix}inventory_sync_mapping WHERE 1=1";
        if ($search) {
            $total_query .= " AND (site1_sku LIKE %s OR site2_sku LIKE %s)";
            $total = $wpdb->get_var($wpdb->prepare($total_query, $params[0] ?? '', $params[1] ?? ''));
        } else {
            $total = $wpdb->get_var($total_query);
        }
        
        // غنی‌سازی داده‌ها
        foreach ($mappings as $mapping) {
            $site1_product = wc_get_product($mapping->site1_product_id);
            $site2_product = wc_get_product($mapping->site2_product_id);
            
            $mapping->site1_name = $site1_product ? $site1_product->get_name() : 'حذف‌شده';
            $mapping->site2_name = $site2_product ? $site2_product->get_name() : 'حذف‌شده';
            $mapping->site1_stock = $site1_product ? wc_get_product($mapping->site1_product_id)->get_stock_quantity() : 0;
            $mapping->site2_stock = $site2_product ? wc_get_product($mapping->site2_product_id)->get_stock_quantity() : 0;
        }
        
        return new WP_REST_Response([
            'mappings' => $mappings,
            'total' => intval($total),
            'per_page' => $per_page,
            'paged' => $paged
        ], 200);
    }
    
    /**
     * POST: ایجاد mapping جدید
     */
    public function rest_create_mapping(WP_REST_Request $request) {
        global $wpdb;
        
        $site1_product_id = intval($request->get_param('site1_product_id') ?? 0);
        $site2_product_id = intval($request->get_param('site2_product_id') ?? 0);
        
        if (!$site1_product_id || !$site2_product_id) {
            return new WP_REST_Response([
                'message' => 'شناسه محصولات مورد نیاز است'
            ], 400);
        }
        
        // بررسی اینکه mapping موجود نباشد
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping 
             WHERE site1_product_id = %d AND site2_product_id = %d",
            $site1_product_id,
            $site2_product_id
        ));
        
        if ($existing) {
            return new WP_REST_Response([
                'message' => 'این مرتبط‌سازی قبلاً ایجاد شده است'
            ], 409);
        }
        
        $site1_product = wc_get_product($site1_product_id);
        $site2_product = wc_get_product($site2_product_id);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'site1_product_id' => $site1_product_id,
                'site2_product_id' => $site2_product_id,
                'site1_sku' => $site1_product ? $site1_product->get_sku() : '',
                'site2_sku' => $site2_product ? $site2_product->get_sku() : '',
                'sync_enabled' => 1,
                'sync_status' => 'synced'
            ]
        );
        
        if ($result) {
            // sync موجودی فوری
            $sync_manager = Inventory_Sync_Manager::get_instance();
            $sync_manager->sync_inventory($wpdb->insert_id);
            
            return new WP_REST_Response([
                'message' => 'مرتبط‌سازی ایجاد شد',
                'id' => $wpdb->insert_id
            ], 201);
        }
        
        return new WP_REST_Response([
            'message' => 'خطا در ایجاد مرتبط‌سازی'
        ], 500);
    }
    
    /**
     * PUT: اپدیت mapping
     */
    public function rest_update_mapping(WP_REST_Request $request) {
        global $wpdb;
        
        $mapping_id = intval($request->get_param('id'));
        $data = [];
        
        $sync_enabled = $request->get_param('sync_enabled');
        if ($sync_enabled !== null) {
            $data['sync_enabled'] = intval($sync_enabled);
        }
        
        if (empty($data)) {
            return new WP_REST_Response([
                'message' => 'هیچ داده برای اپدیت موجود نیست'
            ], 400);
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'inventory_sync_mapping',
            $data,
            ['id' => $mapping_id]
        );
        
        if ($result !== false) {
            return new WP_REST_Response([
                'message' => 'مرتبط‌سازی اپدیت شد'
            ], 200);
        }
        
        return new WP_REST_Response([
            'message' => 'خطا در اپدیت'
        ], 500);
    }
    
    /**
     * DELETE: حذف mapping
     */
    public function rest_delete_mapping(WP_REST_Request $request) {
        global $wpdb;
        
        $mapping_id = intval($request->get_param('id'));
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'inventory_sync_mapping',
            ['id' => $mapping_id]
        );
        
        if ($result) {
            return new WP_REST_Response([
                'message' => 'مرتبط‌سازی حذف شد'
            ], 200);
        }
        
        return new WP_REST_Response([
            'message' => 'خطا در حذف'
        ], 500);
    }
    
    /**
     * GET: دریافت محصولات سایت 1
     */
    public function rest_get_site1_products(WP_REST_Request $request) {
        $search = sanitize_text_field($request->get_param('search') ?? '');
        $per_page = intval($request->get_param('per_page') ?? 50);
        
        // استفاده از WooCommerce API
        $args = [
            'limit' => $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        if ($search) {
            $args['s'] = $search;
        }
        
        $products = wc_get_products($args);
        $data = [];
        
        foreach ($products as $product) {
            // چک کن این محصول قبلاً mapped شده یا نه
            global $wpdb;
            $mapped = $wpdb->get_row($wpdb->prepare(
                "SELECT site2_product_id FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE site1_product_id = %d",
                $product->get_id()
            ));
            
            $data[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'stock' => wc_get_product($product->get_id())->get_stock_quantity() ?? 0,
                'mapped' => $mapped ? intval($mapped->site2_product_id) : null
            ];
        }
        
        return new WP_REST_Response($data, 200);
    }
    
    /**
     * GET: دریافت محصولات سایت 2
     */
    public function rest_get_site2_products(WP_REST_Request $request) {
        $search = sanitize_text_field($request->get_param('search') ?? '');
        $per_page = intval($request->get_param('per_page') ?? 50);
        
        // دریافت API سایت 2
        $site2_api = new Inventory_Sync_API('site2');
        
        $products = $site2_api->get_products($per_page, 1, $search);
        
        if (is_wp_error($products)) {
            return new WP_REST_Response([
                'message' => $products->get_error_message()
            ], 500);
        }
        
        $data = [];
        foreach ($products as $product) {
            // چک کن این محصول قبلاً mapped شده یا نه
            global $wpdb;
            $mapped = $wpdb->get_row($wpdb->prepare(
                "SELECT site1_product_id FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE site2_product_id = %d",
                $product['id']
            ));
            
            $data[] = [
                'id' => $product['id'],
                'name' => $product['name'] ?? '',
                'sku' => $product['sku'] ?? '',
                'stock' => $product['stock'] ?? 0,
                'mapped' => $mapped ? intval($mapped->site1_product_id) : null
            ];
        }
        
        return new WP_REST_Response($data, 200);
    }
}
