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
        add_action('wp_ajax_inventory_sync_get_all_mappings', [$this, 'ajax_get_all_mappings']);
        add_action('wp_ajax_inventory_sync_create_product_mapping', [$this, 'ajax_create_product_mapping']);
        add_action('wp_ajax_inventory_sync_delete_mapping', [$this, 'ajax_delete_mapping']);
        add_action('wp_ajax_inventory_sync_manual_sync_all', [$this, 'ajax_manual_sync_all']);
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
            wp_send_json_success([
                'message' => 'تنظیمات ذخیره شد',
                'current_site_role' => sanitize_text_field($data['current_site_role'] ?? 'site1')
            ]);
        } else {
            wp_send_json_error('خطا در ذخیره');
        }
    }
    
    public function ajax_get_products() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $site = sanitize_text_field($_POST['site'] ?? 'site1');
        $page = intval($_POST['page'] ?? 1);
        
        error_log('[v0] ajax_get_products - site: ' . $site . ', page: ' . $page);
        
        // بررسی اتصالات برای سایت درخواست‌شده
        if ($site === 'site1') {
            $url = Inventory_Sync_Settings::get_site1_url();
            $key = Inventory_Sync_Settings::get_site1_key();
            $secret = Inventory_Sync_Settings::get_site1_secret();
        } elseif ($site === 'site2') {
            $url = Inventory_Sync_Settings::get_site2_url();
            $key = Inventory_Sync_Settings::get_site2_key();
            $secret = Inventory_Sync_Settings::get_site2_secret();
        } else {
            $url = $key = $secret = '';
        }
        
        error_log('[v0] ajax_get_products - url: ' . (!empty($url) ? 'set' : 'empty'));
        
        // اگر اتصالات کامل و معتبر نبود، محصولات محلی را برگردان
        if (empty($url) || empty($key) || empty($secret)) {
            error_log('[v0] ajax_get_products - using local products');
            $products = $this->get_local_products($page);
            if (empty($products)) {
                error_log('[v0] ajax_get_products - no local products found');
                wp_send_json_error('محصولی پیدا نشد و اتصالات سایت ' . ($site === 'site2' ? '۲' : '۱') . ' تنظیم نشده است.');
            }
            error_log('[v0] ajax_get_products - returning ' . count($products) . ' local products');
            wp_send_json_success($products);
            return;
        }
        
        // تلاش برای اتصال به سایت دور
        error_log('[v0] ajax_get_products - trying remote connection');
        $api = new Inventory_Sync_API($url, $key, $secret);
        $products = $api->get_products(50, $page);
        
        if (is_wp_error($products)) {
            error_log('[v0] ajax_get_products - remote error: ' . $products->get_error_message());
            // اگر اتصال ناموفق بود، محصولات محلی را برگردان
            $local_products = $this->get_local_products($page);
            if (!empty($local_products)) {
                error_log('[v0] ajax_get_products - returning local products as fallback');
                wp_send_json_success($local_products);
            }
            wp_send_json_error('خطا در اتصال به سایت ' . ($site === 'site2' ? '۲' : '۱') . ': ' . $products->get_error_message());
        }
        
        error_log('[v0] ajax_get_products - returning ' . count($products) . ' remote products');
        wp_send_json_success($products);
    }
    
    /**
     * دریافت محصولات محلی (از سایت جاری)
     */
    private function get_local_products($page = 1) {
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        error_log('[v0] get_local_products - page: ' . $page . ', per_page: ' . $per_page);
        
        $args = [
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $products_query = new WP_Query($args);
        $products = [];
        
        error_log('[v0] get_local_products - found posts: ' . $products_query->post_count);
        
        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product) {
                    $products[] = [
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'type' => $product->get_type(),
                        'stock_quantity' => $product->get_stock_quantity(),
                        'status' => $product->get_status()
                    ];
                }
            }
        }
        
        wp_reset_postdata();
        
        error_log('[v0] get_local_products - returning ' . count($products) . ' products');
        
        return $products;
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
    
    public function ajax_get_all_mappings() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $limit = 100;
        $offset = ($page - 1) * $limit;
        
        $mappings = Inventory_Sync_Database::get_all_mappings($limit, $offset);
        
        wp_send_json_success($mappings);
    }
    
    public function ajax_create_product_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        if (!Inventory_Sync_Settings::is_site1()) {
            wp_send_json_error('فقط سایت 1 می‌تواند مرتبط‌سازی‌ها را مدیریت کند');
        }
        
        $site1_product_id = intval($_POST['site1_product_id'] ?? 0);
        $site2_product_id = intval($_POST['site2_product_id'] ?? 0);
        $site1_sku = sanitize_text_field($_POST['site1_sku'] ?? '');
        $site2_sku = sanitize_text_field($_POST['site2_sku'] ?? '');
        
        if ($site1_product_id <= 0 || $site2_product_id <= 0) {
            wp_send_json_error('محصولات معتبر نیستند');
        }
        
        $existing = Inventory_Sync_Database::get_mapping_by_site1_id($site1_product_id);
        if (!empty($existing)) {
            wp_send_json_error('این محصول از قبل مرتبط شده است');
        }
        
        $mapping_id = Inventory_Sync_Database::create_product_mapping(
            $site1_product_id,
            $site2_product_id,
            $site1_sku,
            $site2_sku
        );
        
        if ($mapping_id) {
            wp_send_json_success([
                'id' => $mapping_id,
                'message' => 'مرتبط‌سازی با موفقیت انجام شد'
            ]);
        } else {
            wp_send_json_error('خطا در ایجاد مرتبط‌سازی');
        }
    }
    
    public function ajax_delete_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        if (!Inventory_Sync_Settings::is_site1()) {
            wp_send_json_error('فقط سایت 1 می‌تواند مرتبط‌سازی‌ها را مدیریت کند');
        }
        
        $mapping_id = intval($_POST['mapping_id'] ?? 0);
        
        if ($mapping_id <= 0) {
            wp_send_json_error('شناسه معتبر نیست');
        }
        
        if (Inventory_Sync_Database::delete_mapping($mapping_id)) {
            wp_send_json_success('مرتبط‌سازی حذف شد');
        } else {
            wp_send_json_error('خطا در حذف مرتبط‌سازی');
        }
    }
    
    public function ajax_manual_sync_all() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        if (!Inventory_Sync_Settings::is_site1()) {
            wp_send_json_error('فقط سایت 1 می‌تواند هماهنگ‌سازی را انجام دهد');
        }
        
        global $wpdb;
        
        // دریافت تمام mappingهای فعال
        $mappings = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping WHERE sync_enabled = 1"
        );
        
        if (empty($mappings)) {
            wp_send_json_error('هیچ مرتبط‌سازی برای هماهنگ‌سازی وجود ندارد');
        }
        
        $sync_manager = Inventory_Sync_Manager::get_instance();
        $synced = 0;
        $failed = 0;
        
        foreach ($mappings as $mapping) {
            $result = $sync_manager->sync_inventory($mapping->id);
            
            if (is_wp_error($result)) {
                $failed++;
            } else {
                $synced++;
            }
        }
        
        wp_send_json_success([
            'synced' => $synced,
            'failed' => $failed,
            'total' => count($mappings)
        ]);
    }
}
