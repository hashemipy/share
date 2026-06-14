<?php

class Inventory_Sync_API {
    
    private $site_url;
    private $consumer_key;
    private $consumer_secret;
    private $timeout = 30;
    private $retry_count = 3;
    
    public function __construct($site_url, $consumer_key, $consumer_secret) {
        $this->site_url = rtrim($site_url, '/');
        $this->consumer_key = $consumer_key;
        $this->consumer_secret = $consumer_secret;
    }
    
    /**
     * تست اتصال API
     */
    public function test_connection() {
        $endpoint = '/wp-json/wc/v3/system_status';
        $response = $this->request('GET', $endpoint, []);
        
        return !is_wp_error($response) && isset($response['status']);
    }
    
    /**
     * دریافت محصولات
     */
    public function get_products($per_page = 100, $page = 1) {
        $endpoint = '/wp-json/wc/v3/products';
        $params = [
            'per_page' => $per_page,
            'page' => $page,
            'orderby' => 'id',
            'order' => 'desc'
        ];
        
        return $this->request('GET', $endpoint, [], $params);
    }
    
    /**
     * دریافت یک محصول
     */
    public function get_product($product_id) {
        $endpoint = '/wp-json/wc/v3/products/' . intval($product_id);
        return $this->request('GET', $endpoint, []);
    }
    
    /**
     * به‌روزرسانی موجودی
     */
    public function update_product_stock($product_id, $stock) {
        $endpoint = '/wp-json/wc/v3/products/' . intval($product_id);
        $data = [
            'stock_quantity' => intval($stock),
            'manage_stock' => true
        ];
        
        return $this->request('PUT', $endpoint, $data);
    }
    
    /**
     * ایجاد محصول
     */
    public function create_product($product_data) {
        $endpoint = '/wp-json/wc/v3/products';
        return $this->request('POST', $endpoint, $product_data);
    }
    
    /**
     * دریافت متغیّرهای یک محصول متغیّر (variations)
     */
    public function get_product_variations($product_id, $per_page = 100, $page = 1) {
        $endpoint = '/wp-json/wc/v3/products/' . intval($product_id) . '/variations';
        $params = [
            'per_page' => $per_page,
            'page' => $page,
        ];
        return $this->request('GET', $endpoint, [], $params);
    }
    
    /**
     * ایجاد گروهی متغیّرها برای یک محصول (batch)
     * 
     * @param int   $product_id شناسه محصول والد در سایت مقصد
     * @param array $variations آرایه‌ای از داده‌های متغیّر برای ساخت
     */
    public function batch_create_variations($product_id, $variations) {
        $endpoint = '/wp-json/wc/v3/products/' . intval($product_id) . '/variations/batch';
        return $this->request('POST', $endpoint, ['create' => $variations]);
    }

    /**
     * دریافت همه ویژگی‌های سراسری (global attributes) سایت
     * 
     * @return array|WP_Error لیست ویژگی‌ها
     */
    public function get_all_attributes() {
        $endpoint = '/wp-json/wc/v3/products/attributes';
        $params = ['per_page' => 100];
        return $this->request('GET', $endpoint, [], $params);
    }

    /**
     * ساخت یک ویژگی سراسری جدید در سایت
     * 
     * @param string $name  نام نمایشی ویژگی
     * @param string $slug  نامک (slug) ویژگی - همان نامک سایت ۱
     * @return array|WP_Error ویژگی ساخته‌شده
     */
    public function create_attribute($name, $slug) {
        $endpoint = '/wp-json/wc/v3/products/attributes';
        $data = [
            'name'         => $name,
            'slug'         => $slug,
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => false,
        ];
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * دریافت همه term های یک ویژگی سراسری
     * 
     * @param int $attribute_id شناسه ویژگی
     * @return array|WP_Error لیست term ها
     */
    public function get_attribute_terms($attribute_id) {
        $endpoint = '/wp-json/wc/v3/products/attributes/' . intval($attribute_id) . '/terms';
        $params = ['per_page' => 100];
        return $this->request('GET', $endpoint, [], $params);
    }

    /**
     * ساخت یک term جدید برای یک ویژگی سراسری
     * 
     * @param int    $attribute_id شناسه ویژگی
     * @param string $name         نام term
     * @param string $slug         نامک term
     * @return array|WP_Error term ساخته‌شده
     */
    public function create_attribute_term($attribute_id, $name, $slug = '') {
        $endpoint = '/wp-json/wc/v3/products/attributes/' . intval($attribute_id) . '/terms';
        $data = [
            'name' => $name,
            'slug' => $slug ?: sanitize_title($name),
        ];
        return $this->request('POST', $endpoint, $data);
    }
    
    /**
     * ارسال درخواست HTTP
     */
    private function request($method, $endpoint, $data = [], $params = []) {
        $url = $this->site_url . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $auth = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
        
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/json',
                'User-Agent' => 'Inventory-Sync/' . INVENTORY_SYNC_VERSION
            ],
            'sslverify' => apply_filters('inventory_sync_verify_ssl', true)
        ];
        
        if (!empty($data)) {
            $args['body'] = wp_json_encode($data);
        }
        
        $attempt = 0;
        while ($attempt < $this->retry_count) {
            $response = wp_remote_request($url, $args);
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                
                if (in_array($status_code, [200, 201, 204])) {
                    $body = wp_remote_retrieve_body($response);
                    return json_decode($body, true);
                } elseif (in_array($status_code, [400, 401, 403, 404])) {
                    // Don't retry on client errors
                    return new WP_Error('api_error', wp_remote_retrieve_body($response), ['status' => $status_code]);
                }
            }
            
            $attempt++;
            if ($attempt < $this->retry_count) {
                sleep(pow(2, $attempt)); // Exponential backoff
            }
        }
        
        return is_wp_error($response) ? $response : new WP_Error('api_timeout', 'درخواست بیش از حد زمان طول کشید');
    }
}
