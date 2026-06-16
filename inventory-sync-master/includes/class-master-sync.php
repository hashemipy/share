<?php
/**
 * کلاس Sync Master
 */

class Inventory_Sync_Master_Sync {
    
    public static function init() {
        // Webhook دریافتی
        add_action('rest_api_init', [__CLASS__, 'register_webhook_endpoint']);
    }
    
    public static function register_webhook_endpoint() {
        register_rest_route('inventory-sync-master/v1', '/webhook/stock', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_webhook'],
            'permission_callback' => [__CLASS__, 'verify_webhook']
        ]);
    }
    
    /**
     * بررسی webhook
     */
    public static function verify_webhook($request) {
        $site2_key = Inventory_Sync_Master_Settings::get_site2_key();
        $site2_secret = Inventory_Sync_Master_Settings::get_site2_secret();
        
        $header_key = $request->get_header('X-API-Key');
        $header_secret = $request->get_header('X-API-Secret');
        
        return $header_key === $site2_key && $header_secret === $site2_secret;
    }
    
    /**
     * دریافت webhook
     */
    public static function handle_webhook($request) {
        $data = $request->get_json_params();
        
        if (empty($data['remote_id']) || empty($data['quantity'])) {
            return new WP_Error('invalid_data', 'داده‌های نامعتبر');
        }
        
        global $wpdb;
        $mapping_table = $wpdb->prefix . 'inventory_sync_master_mapping';
        
        // یافتن mapping
        $mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $mapping_table WHERE remote_id = %s",
            $data['remote_id']
        ));
        
        if (!$mapping) {
            return new WP_Error('mapping_not_found', 'Mapping یافت نشد');
        }
        
        // بروزرسانی موجودی
        $product = wc_get_product($mapping->site1_product_id);
        if ($product) {
            $product->set_stock_quantity($data['quantity']);
            $product->save();
            
            // ثبت زمان آخرین همگام‌سازی
            $wpdb->update(
                $mapping_table,
                ['last_sync' => current_time('mysql')],
                ['id' => $mapping->id]
            );
        }
        
        return rest_ensure_response(['success' => true]);
    }
}
