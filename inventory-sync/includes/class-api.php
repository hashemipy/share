<?php

class Inventory_Sync_API {
    
    private $site_url;
    private $consumer_key;
    private $consumer_secret;
    private $timeout = 60; // افزایش timeout برای عملیات‌های بزرگ
    private $retry_count = 3;
    private static $cache = []; // کش برای دسته‌بندی‌ها و ویژگی‌ها
    
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
     * دریافت محصول بر اساس SKU
     */
    public function get_product_by_sku($sku) {
        $endpoint = '/wp-json/wc/v3/products';
        $params = [
            'sku' => $sku,
            'per_page' => 1
        ];
        
        $products = $this->request('GET', $endpoint, [], $params);
        
        if (is_wp_error($products) || empty($products) || !is_array($products)) {
            return null;
        }
        
        return isset($products[0]) ? $products[0] : null;
    }
    
    /**
     * اپدیت موجودی
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
     * اپدیت متا‌داده محصول برای علامت‌گذاری انتقال
     */
    public function update_product_meta($product_id, $meta_key, $meta_value) {
        $endpoint = '/wp-json/wc/v3/products/' . intval($product_id);
        $data = [
            'meta_data' => [
                [
                    'key' => $meta_key,
                    'value' => is_array($meta_value) ? wp_json_encode($meta_value) : $meta_value
                ]
            ]
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
     * اپدیت محصول
     */
    public function update_product($product_id, $product_data) {
        $endpoint = '/wp-json/wc/v3/products/' . intval($product_id);
        return $this->request('PUT', $endpoint, $product_data);
    }
    
    /**
     * دریافت تمام دسته‌بندی‌ها
     */
    public function get_categories($per_page = 100, $page = 1) {
        $endpoint = '/wp-json/wc/v3/products/categories';
        $params = [
            'per_page' => $per_page,
            'page' => $page,
            'orderby' => 'id',
            'order' => 'asc',
            'hide_empty' => false
        ];
        return $this->request('GET', $endpoint, [], $params);
    }
    
    /**
     * دریافت دسته‌بندی بر اساس نام
     */
    public function get_category_by_name($name) {
        $endpoint = '/wp-json/wc/v3/products/categories';
        $params = [
            'search' => $name,
            'per_page' => 100
        ];
        $categories = $this->request('GET', $endpoint, [], $params);
        
        if (is_wp_error($categories) || empty($categories)) {
            return null;
        }
        
        // جستجو برای تطابق دقیق
        foreach ($categories as $category) {
            if (strtolower($category['name']) === strtolower($name)) {
                return $category;
            }
        }
        
        return null;
    }
    
    /**
     * ایجاد دسته‌بندی
     */
    public function create_category($name, $parent_id = 0) {
        $endpoint = '/wp-json/wc/v3/products/categories';
        $data = [
            'name' => $name,
            'parent' => intval($parent_id)
        ];
        return $this->request('POST', $endpoint, $data);
    }
    
    /**
     * دریافت تمام ویژگی‌ها (attributes)
     */
    public function get_attributes($per_page = 100, $page = 1) {
        $endpoint = '/wp-json/wc/v3/products/attributes';
        $params = [
            'per_page' => $per_page,
            'page' => $page
        ];
        return $this->request('GET', $endpoint, [], $params);
    }
    
    /**
     * دریافت ویژگی بر اساس نام
     */
    public function get_attribute_by_name($name) {
        $endpoint = '/wp-json/wc/v3/products/attributes';
        $params = [
            'search' => $name,
            'per_page' => 100
        ];
        $attributes = $this->request('GET', $endpoint, [], $params);
        
        if (is_wp_error($attributes) || empty($attributes)) {
            return null;
        }
        
        // جستجو برای تطابق دقیق
        foreach ($attributes as $attribute) {
            if (strtolower($attribute['name']) === strtolower($name)) {
                return $attribute;
            }
        }
        
        return null;
    }
    
    /**
     * ایجاد ویژگی (attribute)
     */
    public function create_attribute($name) {
        $endpoint = '/wp-json/wc/v3/products/attributes';
        $data = [
            'name' => $name,
            'slug' => sanitize_title($name),
            'type' => 'select',
            'has_archives' => true
        ];
        return $this->request('POST', $endpoint, $data);
    }
    
    /**
     * دریافت مقادیر ویژگی (attribute terms)
     */
    public function get_attribute_terms($attribute_id) {
        $endpoint = '/wp-json/wc/v3/products/attributes/' . intval($attribute_id) . '/terms';
        $params = [
            'per_page' => 100,
            'hide_empty' => false
        ];
        return $this->request('GET', $endpoint, [], $params);
    }
    
    /**
     * دریافت مقدار ویژگی بر اساس نام
     */
    public function get_attribute_term_by_name($attribute_id, $name) {
        $endpoint = '/wp-json/wc/v3/products/attributes/' . intval($attribute_id) . '/terms';
        $params = [
            'search' => $name,
            'per_page' => 100
        ];
        $terms = $this->request('GET', $endpoint, [], $params);
        
        if (is_wp_error($terms) || empty($terms)) {
            return null;
        }
        
        foreach ($terms as $term) {
            if (strtolower($term['name']) === strtolower($name)) {
                return $term;
            }
        }
        
        return null;
    }
    
    /**
     * ایجاد مقدار ویژگی (attribute term)
     */
    public function create_attribute_term($attribute_id, $name) {
        $endpoint = '/wp-json/wc/v3/products/attributes/' . intval($attribute_id) . '/terms';
        $data = [
            'name' => $name
        ];
        return $this->request('POST', $endpoint, $data);
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
