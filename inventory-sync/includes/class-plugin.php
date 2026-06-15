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
        
        // ⭐ مهم: Sync Manager را در هر درخواست بارگذاری کن
        // تمام hooks در Sync Manager درون __construct ثبت می‌شوند
        // هیچ وابستگی به WordPress Cron نیست!
        Inventory_Sync_Manager::get_instance();
    }
}
