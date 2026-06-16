<?php
/**
 * پلاگین: Inventory Sync Slave (سایت 2)
 * توضیح: دریافت محصولات از سایت 1 و مدیریت موجودی
 * نسخه: 1.0.0
 * متن دامنه: inventory-sync-slave
 */

if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌ها
define('INVENTORY_SYNC_SLAVE_VERSION', '1.0.0');
define('INVENTORY_SYNC_SLAVE_PATH', plugin_dir_path(__FILE__));
define('INVENTORY_SYNC_SLAVE_URL', plugin_dir_url(__FILE__));

// بارگذاری فایل‌های کلاس‌ها
require_once INVENTORY_SYNC_SLAVE_PATH . 'includes/class-slave-settings.php';
require_once INVENTORY_SYNC_SLAVE_PATH . 'includes/class-slave-receiver.php';
require_once INVENTORY_SYNC_SLAVE_PATH . 'includes/class-slave-admin.php';
require_once INVENTORY_SYNC_SLAVE_PATH . 'includes/class-slave-sync.php';

class Inventory_Sync_Slave {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // تنظیمات
        Inventory_Sync_Slave_Settings::init();
        
        // دریافت‌کننده محصولات
        Inventory_Sync_Slave_Receiver::init();
        
        // فعالیت‌های مدیریتی
        if (is_admin()) {
            Inventory_Sync_Slave_Admin::init();
        }
        
        // سیستم‌های هماهنگ‌سازی
        Inventory_Sync_Slave_Sync::init();
        
        // Hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function activate() {
        // جداول را بسازید
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}inventory_sync_slave_mapping (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            remote_product_id BIGINT(20) UNSIGNED NOT NULL,
            local_product_id BIGINT(20) UNSIGNED,
            remote_sku VARCHAR(255),
            price_markup DECIMAL(5,2) DEFAULT 0,
            last_sync DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_remote (remote_product_id),
            KEY idx_local (local_product_id),
            KEY idx_sku (remote_sku)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    public function deactivate() {
        // پاک‌سازی
    }
}

// فعال کردن پلاگین
Inventory_Sync_Slave::get_instance();
