<?php

/**
 * Inventory_Sync_Bidirectional - هماهنگ‌سازی دو طرفه موجودی
 * 
 * این کلاس مسئول:
 * 1. شنیدن تغییرات موجودی بر روی هر دو سایت
 * 2. تشخیص جهت sync (کدام سایت تغییر داده است)
 * 3. اعمال تغییر به سایت مقابل (با منطق هوشمند)
 * 4. ثبت لاگ تمام تغییرات
 */
class Inventory_Sync_Bidirectional {
    
    private static $instance = null;
    private $site1_api;
    private $site2_api;
    
    // کش اطلاعات جفت برای جلوگیری از فراخوانی مکرر database
    private $pair_cache = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->init_apis();
        $this->init_hooks();
    }
    
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
    
    private function init_hooks() {
        // رویداد custom برای sync جفت‌ها
        add_action('inventory_sync_pair_update', [$this, 'sync_pair_immediately'], 10, 1);
        
        // رویداد cron برای sync دوره‌ای
        add_action('inventory_sync_pair_cron', [$this, 'sync_all_pairs'], 10, 0);
    }
    
    /**
     * sync فوری یک جفت
     * 
     * @param int $pair_id
     * @return bool|WP_Error
     */
    public function sync_pair_immediately($pair_id) {
        $pair = Inventory_Sync_Database::get_product_pair($pair_id);
        
        if (!$pair) {
            return new WP_Error('pair_not_found', 'جفت پیدا نشد');
        }
        
        if (!$pair->is_active) {
            return new WP_Error('pair_inactive', 'جفت غیرفعال است');
        }
        
        // دریافت اطلاعات محصولات
        $product1 = $this->site1_api->get_product($pair->site1_product_id);
        $product2 = $this->site2_api->get_product($pair->site2_product_id);
        
        if (is_wp_error($product1) || is_wp_error($product2)) {
            $error = is_wp_error($product1) ? $product1->get_error_message() : $product2->get_error_message();
            Inventory_Sync_Database::update_pair_error($pair_id, $error);
            return new WP_Error('product_fetch_failed', $error);
        }
        
        // دریافت موجودی
        $stock1 = isset($product1['stock_quantity']) ? intval($product1['stock_quantity']) : 0;
        $stock2 = isset($product2['stock_quantity']) ? intval($product2['stock_quantity']) : 0;
        
        // اگر هر دو موجودی برابر باشند، نیاز به sync نیست
        if ($stock1 === $stock2) {
            return true;
        }
        
        // ⭐ تصمیم‌گیری هوشمند: کدام موجودی اخیر‌تر است؟
        $product1_updated = strtotime($product1['date_modified'] ?? $product1['date_created'] ?? 'now');
        $product2_updated = strtotime($product2['date_modified'] ?? $product2['date_created'] ?? 'now');
        
        // جهت sync را مشخص کن (کدام سایت اخیراً تغییر کرده است)
        if ($product1_updated > $product2_updated) {
            // سایت 1 اخیراً تغییر کرده است → تغییر را به سایت 2 بنداز
            $this->apply_stock_sync($pair, $stock1, 'site1', 'site2');
        } elseif ($product2_updated > $product1_updated) {
            // سایت 2 اخیراً تغییر کرده است → تغییر را به سایت 1 بنداز
            $this->apply_stock_sync($pair, $stock2, 'site2', 'site1');
        }
        
        // اپدیت last_sync
        $direction = $product1_updated > $product2_updated ? 'site1' : 'site2';
        Inventory_Sync_Database::update_pair_last_sync($pair_id, $direction);
        
        return true;
    }
    
    /**
     * اعمال تغییر موجودی
     * 
     * @param object $pair
     * @param int $new_stock - موجودی جدید
     * @param string $from_site - منبع تغییر (site1 یا site2)
     * @param string $to_site - مقصد تغییر
     */
    private function apply_stock_sync($pair, $new_stock, $from_site, $to_site) {
        // بررسی جهت sync (آیا این جهت مجاز است؟)
        if (!$this->is_sync_direction_allowed($pair, $from_site, $to_site)) {
            return;
        }
        
        $from_name = ($from_site === 'site1') ? 'سایت 1' : 'سایت 2';
        $to_name = ($to_site === 'site1') ? 'سایت 1' : 'سایت 2';
        
        // انتخاب API صحیح
        if ($from_site === 'site1' && $to_site === 'site2') {
            $from_product_id = $pair->site1_product_id;
            $to_product_id = $pair->site2_product_id;
            $api = $this->site2_api;
        } else {
            $from_product_id = $pair->site2_product_id;
            $to_product_id = $pair->site1_product_id;
            $api = $this->site1_api;
        }
        
        // اپدیت موجودی در سایت مقصد
        $result = $api->update_product_stock($to_product_id, $new_stock);
        
        if (is_wp_error($result)) {
            // ثبت خطا
            Inventory_Sync_Database::insert_log(
                $from_product_id,
                $pair->site1_product_name,
                'sync_stock',
                $from_name,
                $to_name,
                '',
                $new_stock,
                'failed',
                $result->get_error_message()
            );
            
            Inventory_Sync_Database::update_pair_error($pair->id, $result->get_error_message());
        } else {
            // موفق: ثبت لاگ
            Inventory_Sync_Database::insert_log(
                $from_product_id,
                $pair->site1_product_name,
                'sync_stock',
                $from_name,
                $to_name,
                '',
                $new_stock,
                'success'
            );
            
            // اپدیت sync count
            Inventory_Sync_Database::update_pair_sync_count($pair->id);
        }
    }
    
    /**
     * بررسی اینکه آیا این جهت sync مجاز است
     * 
     * @param object $pair
     * @param string $from_site
     * @param string $to_site
     * @return bool
     */
    private function is_sync_direction_allowed($pair, $from_site, $to_site) {
        $direction = $pair->sync_direction ?? 'bidirectional';
        
        if ($direction === 'bidirectional') {
            return true;
        } elseif ($direction === 'site1_to_site2') {
            return $from_site === 'site1' && $to_site === 'site2';
        } elseif ($direction === 'site2_to_site1') {
            return $from_site === 'site2' && $to_site === 'site1';
        }
        
        return false;
    }
    
    /**
     * sync همه‌ی جفت‌های فعال (برای cron)
     */
    public function sync_all_pairs() {
        $pairs = Inventory_Sync_Database::get_pairs_to_sync();
        
        foreach ($pairs as $pair) {
            $this->sync_pair_immediately($pair->id);
        }
    }
    
    /**
     * دریافت وضعیت جفت (موجودی فعلی هر دو سایت)
     * 
     * @param int $pair_id
     * @return array|WP_Error
     */
    public function get_pair_status($pair_id) {
        $pair = Inventory_Sync_Database::get_product_pair($pair_id);
        
        if (!$pair) {
            return new WP_Error('pair_not_found', 'جفت پیدا نشد');
        }
        
        $product1 = $this->site1_api->get_product($pair->site1_product_id);
        $product2 = $this->site2_api->get_product($pair->site2_product_id);
        
        if (is_wp_error($product1) || is_wp_error($product2)) {
            return new WP_Error('fetch_failed', 'عدم توانایی در دریافت اطلاعات محصول');
        }
        
        return [
            'pair_id' => $pair->id,
            'site1_product' => [
                'id' => $pair->site1_product_id,
                'name' => $pair->site1_product_name,
                'sku' => $pair->site1_sku,
                'stock' => isset($product1['stock_quantity']) ? intval($product1['stock_quantity']) : 0,
                'last_modified' => $product1['date_modified'] ?? $product1['date_created'] ?? 'N/A'
            ],
            'site2_product' => [
                'id' => $pair->site2_product_id,
                'name' => $pair->site2_product_name,
                'sku' => $pair->site2_sku,
                'stock' => isset($product2['stock_quantity']) ? intval($product2['stock_quantity']) : 0,
                'last_modified' => $product2['date_modified'] ?? $product2['date_created'] ?? 'N/A'
            ],
            'pair_info' => [
                'sync_direction' => $pair->sync_direction,
                'last_sync' => $pair->last_sync,
                'sync_count' => $pair->sync_count,
                'is_active' => $pair->is_active
            ]
        ];
    }
}
