<?php
/**
 * کلاس Admin Master - واسط کاربری
 */

class Inventory_Sync_Master_Admin {
    
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_inventory_sync_master_get_products', [__CLASS__, 'ajax_get_products']);
        add_action('wp_ajax_inventory_sync_master_send_product', [__CLASS__, 'ajax_send_product']);
        add_action('wp_ajax_inventory_sync_master_get_status', [__CLASS__, 'ajax_get_status']);
    }
    
    public static function enqueue_scripts($hook) {
        if (strpos($hook, 'inventory-sync-master') === false) {
            return;
        }
        
        wp_enqueue_script(
            'inventory-sync-master-admin',
            INVENTORY_SYNC_MASTER_URL . 'assets/js/admin-master.js',
            ['jquery'],
            INVENTORY_SYNC_MASTER_VERSION,
            true
        );
        
        wp_enqueue_style(
            'inventory-sync-master-admin',
            INVENTORY_SYNC_MASTER_URL . 'assets/css/admin-master.css',
            [],
            INVENTORY_SYNC_MASTER_VERSION
        );
        
        wp_localize_script('inventory-sync-master-admin', 'inventorySyncMasterData', [
            'nonce' => wp_create_nonce('inventory_sync_master'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'loading' => __('بارگذاری...', 'inventory-sync-master'),
                'sending' => __('ارسال...', 'inventory-sync-master'),
                'success' => __('موفق!', 'inventory-sync-master'),
                'error' => __('خطا!', 'inventory-sync-master'),
            ]
        ]);
    }
    
    /**
     * دریافت محصولات
     */
    public static function ajax_get_products() {
        check_ajax_referer('inventory_sync_master');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $products = wc_get_products([
            'limit' => $per_page,
            'offset' => $offset,
            'status' => 'publish',
            'type' => ['simple', 'variable'],
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        $total = wc_get_products([
            'limit' => -1,
            'status' => 'publish',
            'type' => ['simple', 'variable'],
            'return' => 'ids',
        ]);
        
        $data = [];
        foreach ($products as $product) {
            $data[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'type' => $product->get_type(),
                'price' => $product->get_price(),
                'stock' => $product->get_stock_quantity(),
                'image' => wp_get_attachment_url($product->get_image_id())
            ];
        }
        
        wp_send_json_success([
            'products' => $data,
            'total' => count($total),
            'pages' => ceil(count($total) / $per_page),
            'current_page' => $page
        ]);
    }
    
    /**
     * ارسال محصول
     */
    public static function ajax_send_product() {
        check_ajax_referer('inventory_sync_master');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error('محصول معتبر نیست');
        }
        
        $api = new Inventory_Sync_Master_API();
        $result = $api->send_product($product_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('محصول با موفقیت ارسال شد');
    }
    
    /**
     * دریافت وضعیت
     */
    public static function ajax_get_status() {
        check_ajax_referer('inventory_sync_master');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        global $wpdb;
        $mapping_table = $wpdb->prefix . 'inventory_sync_master_mapping';
        
        $stats = [
            'total_mappings' => $wpdb->get_var("SELECT COUNT(*) FROM $mapping_table"),
            'active_mappings' => $wpdb->get_var("SELECT COUNT(*) FROM $mapping_table WHERE sync_enabled = 1"),
            'recently_synced' => $wpdb->get_results("SELECT * FROM $mapping_table ORDER BY last_sync DESC LIMIT 5")
        ];
        
        wp_send_json_success($stats);
    }
}
