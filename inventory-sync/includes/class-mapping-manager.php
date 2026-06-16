<?php

/**
 * Inventory_Sync_Mapping_Manager - مدیریت ارتباط محصولات
 * 
 * این کلاس مسئول:
 * 1. دریافت محصولات از هر دو سایت
 * 2. ایجاد و مدیریت ارتباط‌های محصول
 * 3. نمایش محصولات برای تعیین ارتباط
 * 4. ذخیره mapping در دیتابیس
 */
class Inventory_Sync_Mapping_Manager {
    
    private static $instance = null;
    private $site1_api = null;
    private $site2_api = null;
    private $products_cache = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->init_apis();
    }
    
    /**
     * شروع کردن API های دو سایت
     */
    private function init_apis() {
        try {
            // مقدار دهی اولیه API سایت 1
            $site1_url = Inventory_Sync_Settings::get_site1_url();
            $site1_key = Inventory_Sync_Settings::get_site1_key();
            $site1_secret = Inventory_Sync_Settings::get_site1_secret();
            
            if ($site1_url && $site1_key && $site1_secret) {
                $this->site1_api = new Inventory_Sync_API($site1_url, $site1_key, $site1_secret);
            }
            
            // مقدار دهی اولیه API سایت 2
            $site2_url = Inventory_Sync_Settings::get_site2_url();
            $site2_key = Inventory_Sync_Settings::get_site2_key();
            $site2_secret = Inventory_Sync_Settings::get_site2_secret();
            
            if ($site2_url && $site2_key && $site2_secret) {
                $this->site2_api = new Inventory_Sync_API($site2_url, $site2_key, $site2_secret);
            }
        } catch (Exception $e) {
            error_log('[Inventory Sync] خطا در مقدار دهی اولیه API: ' . $e->getMessage());
        }
    }
    
    /**
     * دریافت محصولات برای نمایش در UI مرتبط‌سازی
     * 
     * @param string $site - 'site1' یا 'site2'
     * @param int $per_page - تعداد محصولات در هر صفحه
     * @param int $page - شماره صفحه
     * 
     * @return array|WP_Error - آرایه محصولات یا خطا
     */
    public function get_products_for_mapping($site = 'site1', $per_page = 20, $page = 1) {
        $cache_key = "inventory_sync_products_{$site}_{$page}_{$per_page}";
        
        // بررسی کش
        $cached = wp_cache_get($cache_key, 'inventory-sync');
        if (false !== $cached) {
            return $cached;
        }
        
        try {
            $api = $site === 'site1' ? $this->site1_api : $this->site2_api;
            
            if (!$api) {
                return new WP_Error('api_not_configured', 
                    sprintf(__('API سایت %s پیکربندی نشده است.', 'inventory-sync'), $site));
            }
            
            // دریافت محصولات از API
            $products = $api->get_products([
                'per_page' => $per_page,
                'page' => $page,
                'status' => 'publish'
            ]);
            
            if (is_wp_error($products)) {
                return $products;
            }
            
            // پردازش محصولات
            $processed = $this->process_products_for_display($products, $site);
            
            // ذخیره در کش برای 1 ساعت
            wp_cache_set($cache_key, $processed, 'inventory-sync', 3600);
            
            return $processed;
            
        } catch (Exception $e) {
            $error_msg = sprintf(
                __('خطا در دریافت محصولات از %s: %s', 'inventory-sync'),
                $site,
                $e->getMessage()
            );
            error_log('[Inventory Sync] ' . $error_msg);
            return new WP_Error('fetch_error', $error_msg);
        }
    }
    
    /**
     * پردازش محصولات برای نمایش
     */
    private function process_products_for_display($products, $site) {
        $processed = [];
        
        if (!is_array($products)) {
            return $processed;
        }
        
        foreach ($products as $product) {
            $product_data = [
                'id' => $product['id'] ?? null,
                'name' => $product['name'] ?? 'بدون نام',
                'sku' => $product['sku'] ?? '--',
                'stock_quantity' => $product['stock_quantity'] ?? ($product['stock'] ?? 0),
                'image' => $product['images'][0]['src'] ?? '',
                'type' => $product['type'] ?? 'simple',
                'variations_count' => count($product['variations'] ?? [])
            ];
            
            if ($product_data['id']) {
                $processed[] = $product_data;
            }
        }
        
        return $processed;
    }
    
    /**
     * ایجاد ارتباط بین دو محصول
     * فقط سایت 1 می‌تواند ارتباط ایجاد کند
     * 
     * @param int $site1_product_id - ID محصول سایت 1
     * @param int $site2_product_id - ID محصول سایت 2
     * 
     * @return array|WP_Error - نتیجه ارتباط یا خطا
     */
    public function create_mapping($site1_product_id, $site2_product_id) {
        try {
            // بررسی اینکه سایت فعلی سایت 1 است
            if (!Inventory_Sync_Settings::is_primary_site()) {
                return new WP_Error('forbidden', 
                    __('فقط سایت 1 می‌تواند محصولات را مرتبط کند.', 'inventory-sync'));
            }
            
            // Validate IDs
            if (!$site1_product_id || !$site2_product_id) {
                return new WP_Error('invalid_ids', 
                    __('ID های محصول نامعتبر هستند.', 'inventory-sync'));
            }
            
            global $wpdb;
            
            // بررسی اینکه این ارتباط قبل‌تر وجود ندارد
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping 
                     WHERE site1_product_id = %d AND site2_product_id = %d",
                    $site1_product_id,
                    $site2_product_id
                )
            );
            
            if ($existing) {
                return new WP_Error('already_mapped', 
                    __('این محصولات قبل‌تر مرتبط شده‌اند.', 'inventory-sync'));
            }
            
            // ایجاد ارتباط جدید
            $result = $wpdb->insert(
                "{$wpdb->prefix}inventory_sync_mapping",
                [
                    'site1_product_id' => (int)$site1_product_id,
                    'site2_product_id' => (int)$site2_product_id,
                    'sync_enabled' => 1,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%s']
            );
            
            if (false === $result) {
                return new WP_Error('db_error', 
                    __('خطا در ذخیره ارتباط:', 'inventory-sync') . $wpdb->last_error);
            }
            
            // Log کردن عملیات
            $this->log_mapping_action('created', $site1_product_id, $site2_product_id);
            
            return [
                'success' => true,
                'mapping_id' => $wpdb->insert_id,
                'message' => __('محصولات با موفقیت مرتبط شدند.', 'inventory-sync')
            ];
            
        } catch (Exception $e) {
            $error_msg = sprintf(
                __('خطا در ایجاد ارتباط: %s', 'inventory-sync'),
                $e->getMessage()
            );
            error_log('[Inventory Sync] ' . $error_msg);
            return new WP_Error('exception', $error_msg);
        }
    }
    
    /**
     * حذف ارتباط بین دو محصول
     * فقط سایت 1 می‌تواند ارتباط حذف کند
     * 
     * @param int $mapping_id - ID ارتباط
     * 
     * @return array|WP_Error
     */
    public function remove_mapping($mapping_id) {
        try {
            if (!Inventory_Sync_Settings::is_primary_site()) {
                return new WP_Error('forbidden', 
                    __('فقط سایت 1 می‌تواند ارتباط‌ها را حذف کند.', 'inventory-sync'));
            }
            
            global $wpdb;
            
            // دریافت اطلاعات ارتباط
            $mapping = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping WHERE id = %d",
                    $mapping_id
                )
            );
            
            if (!$mapping) {
                return new WP_Error('not_found', 
                    __('ارتباط یافت نشد.', 'inventory-sync'));
            }
            
            // حذف ارتباط
            $result = $wpdb->delete(
                "{$wpdb->prefix}inventory_sync_mapping",
                ['id' => $mapping_id],
                ['%d']
            );
            
            if (false === $result) {
                return new WP_Error('db_error', 
                    __('خطا در حذف ارتباط:', 'inventory-sync') . $wpdb->last_error);
            }
            
            $this->log_mapping_action('deleted', $mapping->site1_product_id, $mapping->site2_product_id);
            
            return [
                'success' => true,
                'message' => __('ارتباط با موفقیت حذف شد.', 'inventory-sync')
            ];
            
        } catch (Exception $e) {
            $error_msg = sprintf(
                __('خطا در حذف ارتباط: %s', 'inventory-sync'),
                $e->getMessage()
            );
            error_log('[Inventory Sync] ' . $error_msg);
            return new WP_Error('exception', $error_msg);
        }
    }
    
    /**
     * دریافت تمام ارتباط‌های فعال
     */
    public function get_all_mappings() {
        try {
            global $wpdb;
            
            $mappings = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping WHERE sync_enabled = 1"
            );
            
            return $mappings ?: [];
            
        } catch (Exception $e) {
            error_log('[Inventory Sync] خطا در دریافت ارتباط‌ها: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت یک ارتباط خاص
     */
    public function get_mapping($mapping_id) {
        try {
            global $wpdb;
            
            $mapping = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping WHERE id = %d",
                    $mapping_id
                )
            );
            
            return $mapping;
            
        } catch (Exception $e) {
            error_log('[Inventory Sync] خطا در دریافت ارتباط: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log کردن فعالیت‌های مرتبط‌سازی
     */
    private function log_mapping_action($action, $site1_id, $site2_id) {
        try {
            global $wpdb;
            
            $message = sprintf(
                '%s - محصول سایت 1 (%d) و محصول سایت 2 (%d) %s شدند',
                current_time('Y-m-d H:i:s'),
                $site1_id,
                $site2_id,
                $action === 'created' ? 'مرتبط' : 'جدا'
            );
            
            $wpdb->insert(
                "{$wpdb->prefix}inventory_sync_logs",
                [
                    'action' => "mapping_{$action}",
                    'message' => $message,
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s']
            );
        } catch (Exception $e) {
            error_log('[Inventory Sync] خطا در logging: ' . $e->getMessage());
        }
    }
    
    /**
     * پاک کردن کش
     */
    public function clear_cache() {
        wp_cache_flush();
        $this->products_cache = [];
    }
}
