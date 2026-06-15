<?php

class Inventory_Sync_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Database
        require_once INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-database.php';
        require_once INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-settings.php';
        require_once INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-api.php';
        require_once INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-category-attribute-sync.php';
        require_once INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-sync-manager.php';
        
        // Admin
        if (is_admin()) {
            require_once INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-admin.php';
            Inventory_Sync_Admin::get_instance();
        }
        
        // ساخت Sync Manager در هر درخواست تا hookهای تغییر موجودی و رویدادهای
        // زمان‌بندی‌شده (inventory_sync_mapping / inventory_sync_immediate) ثبت شوند.
        // بدون این، sync خودکار موجودی هرگز اجرا نمی‌شد.
        Inventory_Sync_Manager::get_instance();
        
        // Hooks
        add_action('woocommerce_reduce_order_stock', [$this, 'on_product_sold']);
        add_action('inventory_sync_cron_hook', [$this, 'run_scheduled_sync']);
        
        // Cron
        if (!wp_next_scheduled('inventory_sync_cron_hook')) {
            wp_schedule_event(time(), 'inventory_sync_interval', 'inventory_sync_cron_hook');
        }
        
        // Register cron interval
        add_filter('cron_schedules', [$this, 'add_cron_interval']);
    }
    
    /**
     * وقتی محصول فروخته شود
     */
    public function on_product_sold($order) {
        if (!Inventory_Sync_Settings::get_auto_sync_enabled()) {
            return;
        }
        
        // Schedule background sync
        wp_schedule_single_event(time() + 10, 'inventory_sync_immediate', []);
    }
    
    /**
     * اجرای sync برنامه‌ریزی شده
     */
    public function run_scheduled_sync() {
        global $wpdb;
        
        $mappings = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping WHERE sync_enabled = 1 ORDER BY last_sync ASC LIMIT 10"
        );
        
        $sync_manager = Inventory_Sync_Manager::get_instance();
        
        foreach ($mappings as $mapping) {
            $sync_manager->sync_inventory($mapping->id);
        }
    }
    
    /**
     * اضافه کردن interval برای cron
     */
    public function add_cron_interval($schedules) {
        $interval = Inventory_Sync_Settings::get_sync_interval();
        
        $schedules['inventory_sync_interval'] = [
            'interval' => $interval,
            'display' => sprintf(__('هر %d دقیقه', 'inventory-sync'), intval($interval / 60))
        ];
        
        return $schedules;
    }
}
