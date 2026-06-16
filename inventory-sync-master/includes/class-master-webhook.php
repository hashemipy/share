<?php
/**
 * کلاس Webhook Master
 */

class Inventory_Sync_Master_Webhook {
    
    public static function init() {
        // هنگام تغییر قیمت یا موجودی
        add_action('woocommerce_product_set_stock', [__CLASS__, 'on_product_stock_change'], 10, 1);
        add_action('woocommerce_product_set_stock_status', [__CLASS__, 'on_product_stock_change'], 10, 1);
    }
    
    /**
     * فعل شدن هنگام تغییر موجودی
     */
    public static function on_product_stock_change($product) {
        if (!is_a($product, 'WC_Product')) {
            return;
        }
        
        // این جا می‌تواند برای event‌های دیگر استفاده شود
        // فعلاً فقط برای logged شود
        error_log('[Inventory Sync Master] Product stock changed: ' . $product->get_id());
    }
}
