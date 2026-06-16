<?php
/**
 * کلاس Sync Slave - ارسال webhook برای تغییرات موجودی
 */

class Inventory_Sync_Slave_Sync {
    
    public static function init() {
        // Webhook Sender برای تغییرات موجودی
        add_action('woocommerce_product_set_stock', [__CLASS__, 'on_stock_change']);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'on_order_completed']);
    }
    
    /**
     * فعل شدن هنگام تغییر موجودی
     */
    public static function on_stock_change($product) {
        if (!is_a($product, 'WC_Product')) {
            return;
        }
        
        global $wpdb;
        $mapping_table = $wpdb->prefix . 'inventory_sync_slave_mapping';
        
        // یافتن mapping برای این محصول
        $mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $mapping_table WHERE local_product_id = %d",
            $product->get_id()
        ));
        
        if ($mapping) {
            self::send_webhook($mapping, $product->get_stock_quantity());
        }
    }
    
    /**
     * فعل شدن هنگام تکمیل سفارش
     */
    public static function on_order_completed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if ($product) {
                self::on_stock_change($product);
            }
        }
    }
    
    /**
     * ارسال webhook به سایت Master
     */
    private static function send_webhook($mapping, $quantity) {
        // این webhook بعداً برای اطلاع رسانی به سایت Master استفاده خواهد شد
        // برای الآن فقط logged می‌شود
        error_log('[Inventory Sync Slave] Stock changed - Remote ID: ' . $mapping->remote_product_id . ', Quantity: ' . $quantity);
    }
}
