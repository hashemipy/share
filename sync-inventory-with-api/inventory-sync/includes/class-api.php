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
        $last_error = null;
        
        while ($attempt < $this->retry_count) {
            $response = wp_remote_request($url, $args);
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                if (in_array($status_code, [200, 201, 204])) {
                    // Success - return decoded response
                    if (empty($body)) {
                        return []; // Empty response is valid for 204 No Content
                    }
                    $decoded = json_decode($body, true);
                    return $decoded !== null ? $decoded : [];
                } elseif (in_array($status_code, [400, 401, 403, 404])) {
                    // Don't retry on client errors
                    return new WP_Error(
                        'api_error', 
                        'API Error (' . $status_code . '): ' . $body, 
                        ['status' => $status_code]
                    );
                }
                
                // Server error - may retry
                $last_error = new WP_Error('api_error', 'API returned status ' . $status_code);
            } else {
                $last_error = $response;
            }
            
            $attempt++;
            if ($attempt < $this->retry_count) {
                sleep(pow(2, $attempt)); // Exponential backoff: 2s, 4s, 8s
            }
        }
        
        return $last_error ?? new WP_Error('api_timeout', 'درخواست بیش از حد زمان طول کشید');
    }
}
