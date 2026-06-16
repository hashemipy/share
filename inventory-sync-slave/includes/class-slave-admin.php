<?php
/**
 * کلاس Admin Slave
 */

class Inventory_Sync_Slave_Admin {
    
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_inventory_sync_slave_get_products', [__CLASS__, 'ajax_get_products']);
        add_action('wp_ajax_inventory_sync_slave_update_markup', [__CLASS__, 'ajax_update_markup']);
    }
    
    public static function enqueue_scripts($hook) {
        if (strpos($hook, 'inventory-sync-slave') === false) {
            return;
        }
        
        wp_enqueue_script(
            'inventory-sync-slave-admin',
            INVENTORY_SYNC_SLAVE_URL . 'assets/js/admin-slave.js',
            ['jquery'],
            INVENTORY_SYNC_SLAVE_VERSION,
            true
        );
        
        wp_enqueue_style(
            'inventory-sync-slave-admin',
            INVENTORY_SYNC_SLAVE_URL . 'assets/css/admin-slave.css',
            [],
            INVENTORY_SYNC_SLAVE_VERSION
        );
        
        wp_localize_script('inventory-sync-slave-admin', 'inventorySyncSlaveData', [
            'nonce' => wp_create_nonce('inventory_sync_slave'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'loading' => __('بارگذاری...', 'inventory-sync-slave'),
                'updating' => __('بروزرسانی...', 'inventory-sync-slave'),
                'success' => __('موفق!', 'inventory-sync-slave'),
                'error' => __('خطا!', 'inventory-sync-slave'),
            ]
        ]);
    }
    
    /**
     * دریافت محصولات import شده
     */
    public static function ajax_get_products() {
        check_ajax_referer('inventory_sync_slave');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        global $wpdb;
        $mapping_table = $wpdb->prefix . 'inventory_sync_slave_mapping';
        
        $mappings = $wpdb->get_results("
            SELECT m.*, p.post_title as product_name 
            FROM $mapping_table m
            LEFT JOIN {$wpdb->posts} p ON m.local_product_id = p.ID
            ORDER BY m.created_at DESC
        ");
        
        $data = [];
        foreach ($mappings as $mapping) {
            $product = wc_get_product($mapping->local_product_id);
            if ($product) {
                $data[] = [
                    'id' => $mapping->id,
                    'remote_id' => $mapping->remote_product_id,
                    'local_id' => $mapping->local_product_id,
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'stock' => $product->get_stock_quantity(),
                    'markup' => $mapping->price_markup,
                    'last_sync' => $mapping->last_sync,
                    'image' => wp_get_attachment_url($product->get_image_id())
                ];
            }
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * بروزرسانی markup
     */
    public static function ajax_update_markup() {
        check_ajax_referer('inventory_sync_slave');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('رسائی نہیں');
        }
        
        $markup = floatval($_POST['markup'] ?? 0);
        update_option('inventory_sync_slave_price_markup', $markup);
        
        wp_send_json_success('درصد بروزرسانی شد');
    }
}
