<?php
/**
 * پلاگین: Inventory Sync Master (سایت 1)
 * توضیح: مدیریت محصولات و ارسال آن‌ها به سایت 2
 * نسخه: 1.0.0
 * متن دامنه: inventory-sync-master
 */

if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌ها
define('INVENTORY_SYNC_MASTER_VERSION', '1.0.0');
define('INVENTORY_SYNC_MASTER_PATH', plugin_dir_path(__FILE__));
define('INVENTORY_SYNC_MASTER_URL', plugin_dir_url(__FILE__));

// بارگذاری فایل‌های کلاس‌ها
require_once INVENTORY_SYNC_MASTER_PATH . 'includes/class-master-settings.php';
require_once INVENTORY_SYNC_MASTER_PATH . 'includes/class-master-api.php';
require_once INVENTORY_SYNC_MASTER_PATH . 'includes/class-master-admin.php';
require_once INVENTORY_SYNC_MASTER_PATH . 'includes/class-master-sync.php';
require_once INVENTORY_SYNC_MASTER_PATH . 'includes/class-master-webhook.php';

class Inventory_Sync_Master {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // تنظیمات
        Inventory_Sync_Master_Settings::init();
        
        // فعالیت‌های مدیریتی
        if (is_admin()) {
            Inventory_Sync_Master_Admin::init();
        }
        
        // سیستم‌های هماهنگ‌سازی
        Inventory_Sync_Master_Sync::init();
        Inventory_Sync_Master_Webhook::init();
        
        // Hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function activate() {
        // جداول را بسازید
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}inventory_sync_master_mapping (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site1_product_id BIGINT(20) UNSIGNED NOT NULL,
            site2_product_id BIGINT(20) UNSIGNED,
            remote_id VARCHAR(255),
            sync_enabled TINYINT(1) DEFAULT 1,
            last_sync DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_site1 (site1_product_id),
            KEY idx_remote (remote_id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    public function deactivate() {
        // پاک‌سازی
    }
}

// فعال کردن پلاگین
Inventory_Sync_Master::get_instance();
