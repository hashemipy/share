<?php
/**
 * کلاس دریافت کننده محصولات Slave
 */

class Inventory_Sync_Slave_Receiver {
    
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_import_endpoint']);
    }
    
    public static function register_import_endpoint() {
        register_rest_route('inventory-sync-slave/v1', '/products/import', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'import_product'],
            'permission_callback' => [__CLASS__, 'verify_request']
        ]);
    }
    
    /**
     * بررسی درخواست
     */
    public static function verify_request($request) {
        $header_key = $request->get_header('X-API-Key');
        $header_secret = $request->get_header('X-API-Secret');
        
        $api_key = Inventory_Sync_Slave_Settings::get_api_key();
        $api_secret = Inventory_Sync_Slave_Settings::get_api_secret();
        
        return $header_key === $api_key && $header_secret === $api_secret;
    }
    
    /**
     * import محصول
     */
    public static function import_product($request) {
        $data = $request->get_json_params();
        
        if (empty($data['product_id']) || empty($data['name'])) {
            return new WP_Error('invalid_data', 'داده‌های نامعتبر');
        }
        
        $price_markup = Inventory_Sync_Slave_Settings::get_price_markup() / 100;
        
        // بررسی اگر محصول قبلاً import شده است
        $existing = self::find_existing_product($data['product_id']);
        
        if ($existing) {
            // بروزرسانی محصول موجود
            $product_id = self::update_product($existing, $data, $price_markup);
        } else {
            // ایجاد محصول جدید
            $product_id = self::create_product($data, $price_markup);
        }
        
        if (is_wp_error($product_id)) {
            return $product_id;
        }
        
        // ثبت mapping
        self::save_mapping($data['product_id'], $product_id, $data['sku'] ?? '');
        
        return rest_ensure_response([
            'success' => true,
            'local_product_id' => $product_id,
            'remote_product_id' => $data['product_id']
        ]);
    }
    
    /**
     * یافتن محصول موجود
     */
    private static function find_existing_product($remote_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'inventory_sync_slave_mapping';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT local_product_id FROM $table WHERE remote_product_id = %d LIMIT 1",
            $remote_id
        ));
    }
    
    /**
     * ایجاد محصول جدید
     */
    private static function create_product($data, $price_markup) {
        $product_type = $data['type'] === 'variable' ? 'variable' : 'simple';
        
        $product = new WC_Product($product_type);
        $product->set_name($data['name']);
        $product->set_description($data['description'] ?? '');
        $product->set_short_description($data['short_description'] ?? '');
        $product->set_sku($data['sku'] ?? '');
        $product->set_stock_quantity($data['stock'] ?? 0);
        $product->set_manage_stock(true);
        
        // تنظیم قیمت با markup
        if ($product_type === 'simple') {
            $price = floatval($data['regular_price'] ?? 0);
            $markup_price = $price * (1 + $price_markup);
            $product->set_regular_price($markup_price);
            if (!empty($data['sale_price'])) {
                $sale_price = floatval($data['sale_price']);
                $product->set_sale_price($sale_price * (1 + $price_markup));
            }
        }
        
        // تنظیم status همیشه منتشر شده
        $product->set_status('publish');
        
        // بارگذاری تصاویر
        if (!empty($data['images'])) {
            foreach ($data['images'] as $image) {
                if (!empty($image['src'])) {
                    $attachment_id = self::import_image($image['src']);
                    if ($attachment_id) {
                        $product->set_image_id($attachment_id);
                        break; // تنها تصویر اول
                    }
                }
            }
        }
        
        $product_id = $product->save();
        
        // اگر محصول متغیر است، variations را اضافه کنید
        if ($product_type === 'variable' && !empty($data['variations'])) {
            self::import_variations($product_id, $data['variations'], $price_markup);
        }
        
        return $product_id;
    }
    
    /**
     * بروزرسانی محصول
     */
    private static function update_product($product_id, $data, $price_markup) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('product_not_found', 'محصول یافت نشد');
        }
        
        $product->set_stock_quantity($data['stock'] ?? 0);
        
        if ($product->is_type('simple')) {
            $price = floatval($data['regular_price'] ?? 0);
            $product->set_regular_price($price * (1 + $price_markup));
        }
        
        $product->save();
        return $product_id;
    }
    
    /**
     * import تصویر
     */
    private static function import_image($image_url) {
        // دانلود تصویر از URL
        $response = wp_remote_get($image_url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        $filename = basename($image_url);
        
        // ایجاد attachment
        $upload = wp_upload_bits($filename, null, $image_data);
        
        if (!empty($upload['error'])) {
            return null;
        }
        
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => wp_check_filetype($upload['file'])['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'guid' => $upload['url']
        ], $upload['file']);
        
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
        
        return $attachment_id;
    }
    
    /**
     * import variations
     */
    private static function import_variations($product_id, $variations, $price_markup) {
        $product = wc_get_product($product_id);
        if (!$product) return;
        
        foreach ($variations as $var_data) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_attributes($var_data['attributes'] ?? []);
            
            $price = floatval($var_data['regular_price'] ?? 0);
            $variation->set_regular_price($price * (1 + $price_markup));
            
            $variation->set_stock_quantity($var_data['stock'] ?? 0);
            $variation->set_manage_stock(true);
            $variation->set_status('publish');
            
            $variation->save();
        }
    }
    
    /**
     * ثبت mapping
     */
    private static function save_mapping($remote_id, $local_id, $sku) {
        global $wpdb;
        $table = $wpdb->prefix . 'inventory_sync_slave_mapping';
        
        $wpdb->insert($table, [
            'remote_product_id' => $remote_id,
            'local_product_id' => $local_id,
            'remote_sku' => $sku,
            'created_at' => current_time('mysql')
        ], ['%d', '%d', '%s', '%s']);
    }
}
