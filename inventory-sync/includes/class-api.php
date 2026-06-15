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
     * دریافت دسته‌بندی‌ها
     */
    public function get_categories($per_page = 100, $page = 1) {
        $endpoint = '/wp-json/wc/v3/products/categories';
        $params = [
            'per_page' => $per_page,
            'page' => $page,
            'hide_empty' => false,
            'orderby' => 'id',
            'order' => 'asc'
        ];
        
        return $this->request('GET', $endpoint, [], $params);
    }
    
    /**
     * دریافت یک دسته‌بندی
     */
    public function get_category($category_id) {
        $endpoint = '/wp-json/wc/v3/products/categories/' . intval($category_id);
        return $this->request('GET', $endpoint, []);
    }
    
    /**
     * ایجاد دسته‌بندی
     */
    public function create_category($category_data) {
        $endpoint = '/wp-json/wc/v3/products/categories';
        return $this->request('POST', $endpoint, $category_data);
    }
    
    /**
     * دریافت ویژگی‌ها (Attributes)
     */
    public function get_attributes() {
        $endpoint = '/wp-json/wc/v3/products/attributes';
        return $this->request('GET', $endpoint, []);
    }
    
    /**
     * دریافت یک ویژگی
     */
    public function get_attribute($attribute_id) {
        $endpoint = '/wp-json/wc/v3/products/attributes/' . intval($attribute_id);
        return $this->request('GET', $endpoint, []);
    }
    
    /**
     * ایجاد ویژگی
     */
    public function create_attribute($attribute_data) {
        $endpoint = '/wp-json/wc/v3/products/attributes';
        return $this->request('POST', $endpoint, $attribute_data);
    }
    
    /**
     * دریافت تکیه‌های یک ویژگی (Attribute Terms)
     */
    public function get_attribute_terms($attribute_id, $per_page = 100, $page = 1) {
        $endpoint = '/wp-json/wc/v3/products/attributes/' . intval($attribute_id) . '/terms';
        $params = [
            'per_page' => $per_page,
            'page' => $page,
            'orderby' => 'id',
            'order' => 'asc'
        ];
        
        return $this->request('GET', $endpoint, [], $params);
    }
    
    /**
     * ایجاد تکیه برای ویژگی (Attribute Term)
     */
    public function create_attribute_term($attribute_id, $term_data) {
        $endpoint = '/wp-json/wc/v3/products/attributes/' . intval($attribute_id) . '/terms';
        return $this->request('POST', $endpoint, $term_data);
    }
    
    /**
     * بررسی اینکه آیا یک محصول با این SKU قبلاً موجود است
     */
    public function product_exists_by_sku($sku) {
        if (empty($sku)) {
            return false;
        }
        
        $endpoint = '/wp-json/wc/v3/products';
        $params = [
            'sku' => $sku,
            'per_page' => 1
        ];
        
        $response = $this->request('GET', $endpoint, [], $params);
        
        if (is_wp_error($response) || empty($response)) {
            return false;
        }
        
        return is_array($response) && count($response) > 0 ? $response[0] : false;
    }
    
    /**
     * پیدا کردن محصول برای بروزرسانی
     */
    public function find_product_by_name($product_name) {
        $endpoint = '/wp-json/wc/v3/products';
        $params = [
            'search' => $product_name,
            'per_page' => 1
        ];
        
        $response = $this->request('GET', $endpoint, [], $params);
        
        if (is_wp_error($response) || empty($response)) {
            return false;
        }
        
        return is_array($response) && count($response) > 0 ? $response[0] : false;
    }
    
    /**
     * بروزرسانی محصول
     */
    public function update_product($product_id, $product_data) {
        $endpoint = '/wp-json/wc/v3/products/' . intval($product_id);
        return $this->request('PUT', $endpoint, $product_data);
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
