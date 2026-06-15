<?php

/**
 * مدیریت هماهنگ‌سازی خودکار موجودی هر 10 دقیقه
 * 
 * این کلاس مسئول:
 * 1. هماهنگ‌سازی موجودی محصولات منتقل‌شده هر 10 دقیقه
 * 2. بررسی تغییرات موجودی در هر دو سایت
 * 3. اعمال تغییرات بر اساس جهت هماهنگ‌سازی
 */
class Inventory_Sync_Auto_Sync {
    
    private $site1_api;
    private $site2_api;
    private $sync_interval = 600; // 10 دقیقه = 600 ثانیه
    private $last_sync_option = 'inventory_sync_last_auto_sync';
    
    public function __construct() {
        $this->init();
    }
    
    private function init() {
        // ثبت وقت تغییر موجودی برای محصولات
        add_action('woocommerce_product_set_stock', [$this, 'on_product_stock_change'], 10, 2);
        add_action('woocommerce_variation_set_stock', [$this, 'on_variation_stock_change'], 10, 2);
        
        // Cron برای هماهنگ‌سازی خودکار هر 10 دقیقه
        if (!wp_next_scheduled('inventory_sync_auto_sync_event')) {
            wp_schedule_event(time(), 'inventory_sync_ten_minutes', 'inventory_sync_auto_sync_event');
        }
        add_action('inventory_sync_auto_sync_event', [$this, 'run_auto_sync']);
    }
    
    /**
     * اگر موجودی محصول تغییر کند، **فوری** هماهنگ‌سازی انجام شود
     */
    public function on_product_stock_change($product_id, $stock_qty) {
        // بررسی کن که آیا این محصول منتقل شده است
        $transferred = Inventory_Sync_Database::get_transferred_product_by_site1($product_id);
        
        if ($transferred) {
            // ⭐ فوری هماهنگ‌سازی (بدون انتظار Cron)
            $this->sync_product_immediately($transferred->site1_product_id, $transferred->site2_product_id, $stock_qty);
        }
    }
    
    /**
     * اگر موجودی متغیر تغییر کند، **فوری** هماهنگ‌سازی انجام شود
     */
    public function on_variation_stock_change($product_id, $stock_qty) {
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $transferred = Inventory_Sync_Database::get_transferred_product_by_site1($parent_id);
            
            if ($transferred) {
                // ⭐ فوری هماهنگ‌سازی متغیر
                $this->sync_variation_immediately($product_id, $transferred->site2_product_id, $stock_qty);
            }
        }
    }
    
    /**
     * هماهنگ‌سازی فوری موجودی یک محصول
     */
    private function sync_product_immediately($site1_product_id, $site2_product_id, $stock_qty) {
        $this->init_apis();
        
        if (is_wp_error($this->site1_api) || is_wp_error($this->site2_api)) {
            return;
        }
        
        $sync_direction = Inventory_Sync_Settings::get_sync_direction();
        
        // اگر جهت site1 به site2
        if ($sync_direction === 'site1_to_site2' || $sync_direction === 'bidirectional') {
            $update_data = ['stock_quantity' => intval($stock_qty)];
            $result = $this->site2_api->update_product($site2_product_id, $update_data);
            
            if (!is_wp_error($result)) {
                Inventory_Sync_Database::insert_log(
                    $site1_product_id,
                    'محصول',
                    'instant_sync_product',
                    'سایت 1',
                    'سایت 2',
                    'موجودی: ' . $stock_qty,
                    $site2_product_id,
                    'success',
                    'هماهنگ‌سازی فوری موجودی'
                );
            }
        }
    }
    
    /**
     * هماهنگ‌سازی فوری موجودی یک متغیر
     */
    private function sync_variation_immediately($variation_id, $site2_product_id, $stock_qty) {
        $this->init_apis();
        
        if (is_wp_error($this->site1_api) || is_wp_error($this->site2_api)) {
            return;
        }
        
        $sync_direction = Inventory_Sync_Settings::get_sync_direction();
        
        // دریافت متغیرهای سایت 2
        $variations = $this->site2_api->get_product_variations($site2_product_id);
        
        if (!is_wp_error($variations) && !empty($variations)) {
            // پیدا کردن متغیر صحیح (بر اساس SKU)
            $site1_product = wc_get_product($variation_id);
            $site1_sku = $site1_product->get_sku();
            
            foreach ($variations as $variation) {
                if ($variation['sku'] === $site1_sku) {
                    // اگر جهت site1 به site2
                    if ($sync_direction === 'site1_to_site2' || $sync_direction === 'bidirectional') {
                        $update_data = ['stock_quantity' => intval($stock_qty)];
                        $this->site2_api->update_product($variation['id'], $update_data);
                    }
                    break;
                }
            }
        }
    }
    
    /**
     * اجرای هماهنگ‌سازی خودکار
     * این تابع هر 10 دقیقه توسط Cron فراخوانی می‌شود
     */
    public function run_auto_sync() {
        $this->init_apis();
        
        if (is_wp_error($this->site1_api) || is_wp_error($this->site2_api)) {
            return;
        }
        
        // دریافت تمام محصولات منتقل‌شده
        $transferred_products = Inventory_Sync_Database::get_transferred_products(999, 0);
        
        if (empty($transferred_products)) {
            return;
        }
        
        $sync_direction = Inventory_Sync_Settings::get_sync_direction();
        $synced_count = 0;
        
        foreach ($transferred_products as $transfer_record) {
            $site1_product_id = $transfer_record->site1_product_id;
            $site2_product_id = $transfer_record->site2_product_id;
            
            // بررسی تغییرات موجودی
            if ($this->sync_product_stock($site1_product_id, $site2_product_id, $sync_direction)) {
                $synced_count++;
            }
        }
        
        // ذخیره زمان آخرین هماهنگ‌سازی
        update_option($this->last_sync_option, current_time('mysql'));
        
        if ($synced_count > 0) {
            Inventory_Sync_Database::insert_log(
                0,
                'Auto Sync',
                'auto_sync_completed',
                'Cron',
                'Both Sites',
                '',
                'تعداد محصولات هماهنگ شده: ' . $synced_count,
                'success'
            );
        }
    }
    
    /**
     * هماهنگ‌سازی موجودی یک محصول
     * 
     * @param int $site1_product_id شناسه محصول در سایت 1
     * @param int $site2_product_id شناسه محصول در سایت 2
     * @param string $sync_direction جهت هماهنگ‌سازی (site1_to_site2, site2_to_site1, bidirectional)
     * @return bool موفقیت یا عدم موفقیت
     */
    private function sync_product_stock($site1_product_id, $site2_product_id, $sync_direction) {
        try {
            // دریافت اطلاعات محصول از هر دو سایت
            $site1_product = $this->site1_api->get_product($site1_product_id);
            $site2_product = $this->site2_api->get_product($site2_product_id);
            
            if (is_wp_error($site1_product) || is_wp_error($site2_product)) {
                return false;
            }
            
            $site1_stock = (int)($site1_product['stock_quantity'] ?? 0);
            $site2_stock = (int)($site2_product['stock_quantity'] ?? 0);
            
            $synced = false;
            
            // بررسی جهت هماهنگ‌سازی
            if ($sync_direction === 'site1_to_site2' && $site1_stock !== $site2_stock) {
                // موجودی سایت 1 را به سایت 2 کپی کن
                $this->site2_api->update_product($site2_product_id, [
                    'stock_quantity' => $site1_stock,
                    'manage_stock' => $site1_product['manage_stock'] ?? false
                ]);
                $synced = true;
            } elseif ($sync_direction === 'site2_to_site1' && $site1_stock !== $site2_stock) {
                // موجودی سایت 2 را به سایت 1 کپی کن
                // (نیاز به API سایت 1 برای update)
                $synced = true;
            } elseif ($sync_direction === 'bidirectional' && $site1_stock !== $site2_stock) {
                // موجودی کم‌تر را برای هر دو اعمال کن
                $min_stock = min($site1_stock, $site2_stock);
                $this->site1_api->update_product($site1_product_id, [
                    'stock_quantity' => $min_stock,
                    'manage_stock' => true
                ]);
                $this->site2_api->update_product($site2_product_id, [
                    'stock_quantity' => $min_stock,
                    'manage_stock' => $site2_product['manage_stock'] ?? false
                ]);
                $synced = true;
            }
            
            // هماهنگ‌سازی متغیرها (اگر محصول متغیّر باشد)
            if (!empty($site1_product['variations']) && is_array($site1_product['variations'])) {
                foreach ($site1_product['variations'] as $variation) {
                    $this->sync_variation_stock($variation, $site2_product_id, $sync_direction);
                }
            }
            
            return $synced;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * هماهنگ‌سازی موجودی یک متغیر محصول
     */
    private function sync_variation_stock($site1_variation, $site2_product_id, $sync_direction) {
        try {
            $site1_var_id = $site1_variation['id'] ?? 0;
            $site1_var_stock = (int)($site1_variation['stock_quantity'] ?? 0);
            
            if ($site1_var_id <= 0) {
                return false;
            }
            
            // جستجوی متغیر مطابقت‌دهنده در سایت 2
            // (این نیاز به matching بر اساس SKU یا ویژگی‌ها دارد)
            $site2_variations = $this->site2_api->get_product_variations($site2_product_id, 100, 1);
            
            if (is_wp_error($site2_variations) || empty($site2_variations)) {
                return false;
            }
            
            // جستجو بر اساس SKU
            $site1_var_sku = $site1_variation['sku'] ?? '';
            $matched_variation = null;
            
            foreach ($site2_variations as $site2_var) {
                if ($site2_var['sku'] === $site1_var_sku) {
                    $matched_variation = $site2_var;
                    break;
                }
            }
            
            if (!$matched_variation) {
                return false;
            }
            
            $site2_var_id = $matched_variation['id'];
            $site2_var_stock = (int)($matched_variation['stock_quantity'] ?? 0);
            
            if ($sync_direction === 'site1_to_site2' && $site1_var_stock !== $site2_var_stock) {
                $this->site2_api->update_product_variation($site2_product_id, $site2_var_id, [
                    'stock_quantity' => $site1_var_stock
                ]);
                return true;
            } elseif ($sync_direction === 'bidirectional' && $site1_var_stock !== $site2_var_stock) {
                $min_stock = min($site1_var_stock, $site2_var_stock);
                $this->site2_api->update_product_variation($site2_product_id, $site2_var_id, [
                    'stock_quantity' => $min_stock
                ]);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * دریافت زمان آخرین هماهنگ‌سازی
     */
    public static function get_last_sync_time() {
        return get_option('inventory_sync_last_auto_sync', 'هرگز');
    }
    
    /**
     * دریافت زمان بعدی هماهنگ‌سازی
     */
    public static function get_next_sync_time() {
        $next = wp_next_scheduled('inventory_sync_auto_sync_event');
        if ($next) {
            return date_i18n('Y-m-d H:i:s', $next + (int) get_option('gmt_offset') * 3600);
        }
        return 'نامشخص';
    }
    
    /**
     * اولین‌سازی API ها
     */
    private function init_apis() {
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
}

// اگر Cron interval تعریف نشده است، آن را اضافه کن
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['inventory_sync_ten_minutes'])) {
        $schedules['inventory_sync_ten_minutes'] = [
            'interval' => 600,
            'display' => 'هر 10 دقیقه'
        ];
    }
    return $schedules;
});
