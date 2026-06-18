<?php

/**
 * Inventory_Sync_Transfer_Manager - مدیریت انتقال محصولات ساده
 * 
 * این کلاس مسئول:
 * 1. انتقال محصولات ساده از سایت 1 به سایت 2
 * 2. بررسی اینکه محصول قبلاً منتقل شده است
 * 3. ایجاد mapping خودکار بعد از انتقال
 * 4. اعمال استراتژی Last-Write-Wins برای موجودی
 * 
 * ✅ فقط برای محصولات ساده (نه متغیّر)
 * ✅ دوطرفه: میتواند از سایت 1 → سایت 2 منتقل کند
 */
class Inventory_Sync_Transfer_Manager {
    
    private $site1_api;
    private $site2_api;
    
    public function __construct() {
        $this->site1_api = new Inventory_Sync_API(
            Inventory_Sync_Settings::get_site1_url(),
            Inventory_Sync_Settings::get_site1_key(),
            Inventory_Sync_Settings::get_site1_secret()
        );
        
        $this->site2_api = new Inventory_Sync_API(
            Inventory_Sync_Settings::get_site2_url(),
            Inventory_Sync_Settings::get_site2_key(),
            Inventory_Sync_Settings::get_site2_secret()
        );
    }
    
    /**
     * ✅ محصولات ساده‌ای را که منتقل نشده‌اند، دریافت کن
     */
    public function get_simple_products_not_transferred($per_page = 50, $page = 1) {
        // تمام محصولات ساده سایت 1 را دریافت کن
        $all_products = $this->site1_api->get_products($per_page, $page);
        
        if (is_wp_error($all_products)) {
            return $all_products;
        }
        
        // فقط محصولات ساده
        $simple_products = array_filter($all_products, function($p) {
            return ($p['type'] ?? 'simple') === 'simple';
        });
        
        // حذف محصولاتی که قبلاً منتقل شده‌اند
        $not_transferred = [];
        foreach ($simple_products as $product) {
            if (!$this->is_transferred($product['id'])) {
                $not_transferred[] = $product;
            }
        }
        
        return $not_transferred;
    }
    
    /**
     * ✅ بررسی کن که آیا محصول قبلاً منتقل شده است
     */
    public function is_transferred($site1_product_id) {
        global $wpdb;
        
        $mapping = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE site1_product_id = %d",
                $site1_product_id
            )
        );
        
        return !empty($mapping);
    }
    
    /**
     * ✅ بررسی کن که آیا محصول ساده است
     */
    public function is_simple_product($api, $product_id) {
        $product = $api->get_product($product_id);
        
        if (is_wp_error($product)) {
            return false;
        }
        
        return ($product['type'] ?? 'simple') === 'simple';
    }
    
    /**
     * ✅ انتقال محصول ساده از سایت 1 به سایت 2
     * 
     * @param int $site1_product_id شناسه محصول در سایت 1
     * @return array|WP_Error
     */
    public function transfer_simple_product($site1_product_id) {
        // بررسی کن محصول ساده است
        if (!$this->is_simple_product($this->site1_api, $site1_product_id)) {
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                '',
                'transfer_product',
                'سایت 1',
                'سایت 2',
                '',
                '',
                'skipped',
                'فقط محصولات ساده می‌توانند منتقل شوند'
            );
            return new WP_Error('not_simple', 'این محصول ساده نیست');
        }
        
        // بررسی کن قبلاً منتقل نشده است
        if ($this->is_transferred($site1_product_id)) {
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                '',
                'transfer_product',
                'سایت 1',
                'سایت 2',
                '',
                '',
                'skipped',
                'این محصول قبلاً منتقل شده است'
            );
            return new WP_Error('already_transferred', 'این محصول قبلاً منتقل شده است');
        }
        
        // دریافت محصول از سایت 1
        $product1 = $this->site1_api->get_product($site1_product_id);
        
        if (is_wp_error($product1)) {
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                '',
                'transfer_product',
                'سایت 1',
                'سایت 2',
                '',
                '',
                'failed',
                'خطا در دریافت محصول: ' . $product1->get_error_message()
            );
            return $product1;
        }
        
        $product_name = $product1['name'] ?? 'محصول بدون نام';
        $original_sku = $product1['sku'] ?? '';
        
        // بررسی کن آیا محصول با همین SKU در سایت 2 وجود دارد
        $existing_product = $this->site2_api->product_exists_by_sku($original_sku);
        
        if ($existing_product && !empty($existing_product['id'])) {
            // محصول موجود است، فقط mapping ایجاد کن
            $site2_product_id = $existing_product['id'];
            
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'transfer_product',
                'سایت 1',
                'سایت 2',
                '',
                $site2_product_id,
                'info',
                'محصول با این SKU در سایت 2 موجود است. Mapping ایجاد شد.'
            );
        } else {
            // محصول جدید است، ایجاد کن
            $sku_to_use = $original_sku ?: ('site1-' . $site1_product_id . '-' . bin2hex(random_bytes(4)));
            
            $product1['sku'] = $sku_to_use;
            
            $result = $this->site2_api->create_product($product1);
            
            if (is_wp_error($result)) {
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product_name,
                    'transfer_product',
                    'سایت 1',
                    'سایت 2',
                    '',
                    '',
                    'failed',
                    'خطا در ایجاد محصول: ' . $result->get_error_message()
                );
                return $result;
            }
            
            $site2_product_id = $result['id'] ?? 0;
            
            if (empty($site2_product_id)) {
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    $product_name,
                    'transfer_product',
                    'سایت 1',
                    'سایت 2',
                    '',
                    '',
                    'failed',
                    'خطای نامعلوم در ایجاد محصول'
                );
                return new WP_Error('create_failed', 'خطای نامعلوم در ایجاد محصول');
            }
            
            Inventory_Sync_Database::insert_log(
                $site1_product_id,
                $product_name,
                'transfer_product',
                'سایت 1',
                'سایت 2',
                '',
                $site2_product_id,
                'success',
                'محصول جدید ایجاد شد'
            );
        }
        
        // ✅ اپدیت موجودی در سایت 2 برای استراتژی Last-Write-Wins
        $stock = isset($product1['stock_quantity']) ? intval($product1['stock_quantity']) : 0;
        $this->site2_api->update_product_stock($site2_product_id, $stock);
        
        // ✅ ایجاد mapping خودکار
        $this->create_mapping($site1_product_id, $site2_product_id);
        
        return [
            'site1_product_id' => $site1_product_id,
            'site2_product_id' => $site2_product_id,
            'product_name' => $product_name,
            'stock' => $stock
        ];
    }
    
    /**
     * ✅ ایجاد mapping بین دو محصول
     */
    private function create_mapping($site1_product_id, $site2_product_id) {
        global $wpdb;
        
        // بررسی کن mapping قبلاً موجود نیست
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE site1_product_id = %d AND site2_product_id = %d",
                $site1_product_id,
                $site2_product_id
            )
        );
        
        if ($existing) {
            return true; // mapping قبلاً موجود است
        }
        
        $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'site1_product_id' => $site1_product_id,
                'site2_product_id' => $site2_product_id,
                'sync_enabled' => 1,
                'sync_status' => 'synced',
                'created_at' => current_time('mysql')
            ]
        );
        
        return true;
    }
    
    /**
     * ✅ دریافت لیست محصولات منتقل‌شده
     */
    public function get_transferred_products($per_page = 50, $offset = 0) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping 
                 ORDER BY created_at DESC 
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        
        return $results;
    }
}
