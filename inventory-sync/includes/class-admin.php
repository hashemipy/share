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
        add_action('wp_ajax_inventory_sync_transfer_products', [$this, 'ajax_transfer_products']);
        add_action('wp_ajax_inventory_sync_get_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_inventory_sync_get_transferred_products', [$this, 'ajax_get_transferred_products']);
        
        // AJAX Handlers برای مرتبط‌سازی موجودی
        add_action('wp_ajax_inventory_sync_create_mapping', [$this, 'ajax_create_mapping']);
        add_action('wp_ajax_inventory_sync_get_mappings', [$this, 'ajax_get_mappings']);
        add_action('wp_ajax_inventory_sync_delete_mapping', [$this, 'ajax_delete_mapping']);
        add_action('wp_ajax_inventory_sync_get_inventory_logs', [$this, 'ajax_get_inventory_logs']);
        add_action('wp_ajax_inventory_sync_retry_log', [$this, 'ajax_retry_log']);
        add_action('wp_ajax_inventory_sync_manual_sync', [$this, 'ajax_manual_sync']);
        add_action('wp_ajax_inventory_sync_get_sync_status', [$this, 'ajax_get_sync_status']);
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
        
        wp_enqueue_script(
            'inventory-sync-linking',
            INVENTORY_SYNC_PLUGIN_URL . 'assets/js/inventory-linking.js',
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
    
    // ===== جدید: AJAX Handlers برای مرتبط‌سازی موجودی =====
    
    /**
     * ایجاد ارتباط بین دو محصول
     */
    public function ajax_create_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $site1_product_id = intval($_POST['site1_product_id'] ?? 0);
        $site2_product_id = intval($_POST['site2_product_id'] ?? 0);
        $site1_variant_id = intval($_POST['site1_variant_id'] ?? 0) ?: null;
        $site2_variant_id = intval($_POST['site2_variant_id'] ?? 0) ?: null;
        
        if (!$site1_product_id || !$site2_product_id) {
            wp_send_json_error('محصولات الزامی هستند');
        }
        
        $result = Inventory_Sync_Product_Mapper::get_instance()->create_mapping(
            $site1_product_id,
            $site2_product_id,
            $site1_variant_id,
            $site2_variant_id
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * دریافت تمام ارتباطات
     */
    public function ajax_get_mappings() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $mappings = Inventory_Sync_Product_Mapper::get_instance()->get_all_mappings([
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        // اضافه کردن اطلاعات دقیق برای هر ارتباط
        $formatted_mappings = [];
        foreach ($mappings as $mapping) {
            $status = Inventory_Sync_Engine::get_instance()->get_sync_status($mapping->id);
            
            $site1_product = wc_get_product($mapping->site1_product_id);
            $site2_product = wc_get_product($mapping->site2_product_id);
            
            $formatted_mappings[] = [
                'id' => $mapping->id,
                'site1_product' => [
                    'id' => $mapping->site1_product_id,
                    'name' => $site1_product ? $site1_product->get_name() : 'N/A',
                    'variant_id' => $mapping->site1_variant_id
                ],
                'site2_product' => [
                    'id' => $mapping->site2_product_id,
                    'name' => $site2_product ? $site2_product->get_name() : 'N/A',
                    'variant_id' => $mapping->site2_variant_id
                ],
                'mapping_type' => $mapping->mapping_type,
                'sync_enabled' => (bool) $mapping->sync_enabled,
                'last_sync_time' => $mapping->last_sync_time,
                'status' => is_array($status) ? $status : ['in_sync' => false, 'pending_tasks' => 0]
            ];
        }
        
        $total = Inventory_Sync_Product_Mapper::get_instance()->count_mappings();
        
        wp_send_json_success([
            'mappings' => $formatted_mappings,
            'total' => $total,
            'total_pages' => ceil($total / $limit),
            'current_page' => $page
        ]);
    }
    
    /**
     * حذف ارتباط
     */
    public function ajax_delete_mapping() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $mapping_id = intval($_POST['mapping_id'] ?? 0);
        
        if (!$mapping_id) {
            wp_send_json_error('شناسه ارتباط الزامی است');
        }
        
        $result = Inventory_Sync_Product_Mapper::get_instance()->delete_mapping($mapping_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('ارتباط برای حذف اضافه شد');
    }
    
    /**
     * دریافت لاگ‌های موجودی
     */
    public function ajax_get_inventory_logs() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $status = sanitize_text_field($_POST['status'] ?? null);
        $site = intval($_POST['site'] ?? 0) ?: null;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $args = [
            'limit' => $limit,
            'offset' => $offset,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        if ($status) {
            $args['status'] = $status;
        }
        if ($site) {
            $args['site'] = $site;
        }
        
        $logs = Inventory_Sync_Log_Manager::get_instance()->get_logs($args);
        
        // فرمت کردن لاگ‌ها
        $formatted_logs = [];
        foreach ($logs as $log) {
            $formatted_logs[] = [
                'id' => $log->id,
                'mapping_id' => $log->mapping_id,
                'product_name' => $log->product_name,
                'variant_name' => $log->variant_name,
                'site' => $log->site,
                'old_quantity' => intval($log->old_quantity),
                'new_quantity' => intval($log->new_quantity),
                'status' => $log->status,
                'triggered_by' => $log->triggered_by,
                'created_at' => $log->created_at,
                'error_message' => $log->error_message
            ];
        }
        
        $total = Inventory_Sync_Log_Manager::get_instance()->count_logs($args);
        
        wp_send_json_success([
            'logs' => $formatted_logs,
            'total' => $total,
            'total_pages' => ceil($total / $limit),
            'current_page' => $page
        ]);
    }
    
    /**
     * رفرش دستی یک لاگ
     */
    public function ajax_retry_log() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $log_id = intval($_POST['log_id'] ?? 0);
        
        if (!$log_id) {
            wp_send_json_error('شناسه لاگ الزامی است');
        }
        
        $result = Inventory_Sync_Log_Manager::get_instance()->retry_log_sync($log_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * هماهنگ‌سازی دستی
     */
    public function ajax_manual_sync() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $mapping_id = intval($_POST['mapping_id'] ?? 0);
        $source_site = intval($_POST['source_site'] ?? 1);
        
        if (!$mapping_id) {
            wp_send_json_error('شناسه ارتباط الزامی است');
        }
        
        $result = Inventory_Sync_Engine::get_instance()->manual_sync($mapping_id, $source_site);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * دریافت وضعیت هماهنگ‌سازی
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('inventory_sync_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('عدم دسترسی');
        }
        
        $mapping_id = intval($_POST['mapping_id'] ?? 0);
        
        if (!$mapping_id) {
            wp_send_json_error('شناسه ارتباط الزامی است');
        }
        
        $status = Inventory_Sync_Engine::get_instance()->get_sync_status($mapping_id);
        
        if (is_wp_error($status)) {
            wp_send_json_error($status->get_error_message());
        }
        
        wp_send_json_success($status);
    }
}
