<?php
/**
 * کلاس API Master - ارتباط با سایت 2
 */

class Inventory_Sync_Master_API {
    
    private $site2_url;
    private $site2_key;
    private $site2_secret;
    
    public function __construct() {
        $this->site2_url = Inventory_Sync_Master_Settings::get_site2_url();
        $this->site2_key = Inventory_Sync_Master_Settings::get_site2_key();
        $this->site2_secret = Inventory_Sync_Master_Settings::get_site2_secret();
    }
    
    /**
     * ارسال محصول به سایت 2
     */
    public function send_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('product_not_found', 'محصول یافت نشد');
        }
        
        $product_data = $this->prepare_product_data($product);
        
        return $this->make_request('POST', '/wp-json/inventory-sync/v1/products/import', $product_data);
    }
    
    /**
     * آماده‌سازی داده‌های محصول
     */
    private function prepare_product_data($product) {
        $data = [
            'product_id' => $product->get_id(),
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'sku' => $product->get_sku(),
            'stock' => $product->get_stock_quantity(),
            'type' => $product->get_type(),
            'status' => 'publish',
        ];
        
        // تصاویر
        $data['images'] = [];
        $image_id = $product->get_image_id();
        if ($image_id) {
            $data['images'][] = [
                'id' => $image_id,
                'src' => wp_get_attachment_url($image_id)
            ];
        }
        
        // محصولات متغیر
        if ($product->is_type('variable')) {
            $data['variations'] = $this->prepare_variations($product);
        }
        
        return $data;
    }
    
    /**
     * آماده‌سازی variations
     */
    private function prepare_variations($product) {
        $variations = [];
        foreach ($product->get_available_variations() as $variation_data) {
            $variation = wc_get_product($variation_data['variation_id']);
            if ($variation) {
                $variations[] = [
                    'variation_id' => $variation->get_id(),
                    'attributes' => $variation->get_attributes(),
                    'regular_price' => $variation->get_regular_price(),
                    'sale_price' => $variation->get_sale_price(),
                    'sku' => $variation->get_sku(),
                    'stock' => $variation->get_stock_quantity(),
                    'image_id' => $variation->get_image_id(),
                    'image_src' => wp_get_attachment_url($variation->get_image_id())
                ];
            }
        }
        return $variations;
    }
    
    /**
     * درخواست HTTP
     */
    private function make_request($method, $endpoint, $body = []) {
        if (empty($this->site2_url)) {
            return new WP_Error('no_url', 'آدرس سایت 2 تنظیم نشده است');
        }
        
        $url = rtrim($this->site2_url, '/') . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->site2_key,
                'X-API-Secret' => $this->site2_secret,
            ],
            'body' => wp_json_encode($body)
        ];
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('[Inventory Sync Master] Request Error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }
    
    /**
     * دریافت محصولات دریافت‌شده
     */
    public function get_imported_products() {
        return $this->make_request('GET', '/wp-json/inventory-sync/v1/products/imported');
    }
}
