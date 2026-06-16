<?php
/**
 * Plugin Name: Inventory Sync Slave
 * Plugin URI: https://example.com/inventory-sync-slave
 * Description: دریافت محصولات و مدیریت فروش - سایت فرعی (Slave)
 * Version: 1.0.0
 * Author: شما
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: inventory-sync-slave
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// ثابت‌ها
define('INVENTORY_SYNC_SLAVE_VERSION', '1.0.0');
define('INVENTORY_SYNC_SLAVE_FILE', __FILE__);
define('INVENTORY_SYNC_SLAVE_DIR', plugin_dir_path(__FILE__));
define('INVENTORY_SYNC_SLAVE_URL', plugin_dir_url(__FILE__));

// بارگذاری فایل‌های کلاس‌ها
require_once INVENTORY_SYNC_SLAVE_DIR . 'includes/class-slave-settings.php';
require_once INVENTORY_SYNC_SLAVE_DIR . 'includes/class-slave-receiver.php';
require_once INVENTORY_SYNC_SLAVE_DIR . 'includes/class-slave-admin.php';
require_once INVENTORY_SYNC_SLAVE_DIR . 'includes/class-slave-sync.php';

/**
 * کلاس اصلی پلاگین
 */
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
        if (class_exists('Inventory_Sync_Slave_Settings')) {
            Inventory_Sync_Slave_Settings::init();
        }
        
        // دریافت‌کننده محصولات (API Endpoint)
        if (class_exists('Inventory_Sync_Slave_Receiver')) {
            Inventory_Sync_Slave_Receiver::init();
        }
        
        // فعالیت‌های مدیریتی
        if (is_admin() && class_exists('Inventory_Sync_Slave_Admin')) {
            Inventory_Sync_Slave_Admin::init();
        }
        
        // سیستم‌های هماهنگ‌سازی (Webhook Sender)
        if (class_exists('Inventory_Sync_Slave_Sync')) {
            Inventory_Sync_Slave_Sync::init();
        }
        
        // Hooks
        register_activation_hook(INVENTORY_SYNC_SLAVE_FILE, [$this, 'activate']);
        register_deactivation_hook(INVENTORY_SYNC_SLAVE_FILE, [$this, 'deactivate']);
    }
    
    /**
     * فعال‌سازی پلاگین
     */
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // جدول Mapping و محصولات دریافت‌شده
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}inventory_sync_slave_mapping (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            remote_product_id BIGINT(20) UNSIGNED NOT NULL,
            local_product_id BIGINT(20) UNSIGNED,
            remote_sku VARCHAR(255),
            price_markup DECIMAL(5,2) DEFAULT 0,
            is_variable TINYINT(1) DEFAULT 0,
            remote_data LONGTEXT,
            last_sync DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_remote (remote_product_id),
            KEY idx_local (local_product_id),
            KEY idx_sku (remote_sku)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // ذخیره درصد پیش‌فرض
        if (!get_option('inventory_sync_slave_price_markup')) {
            update_option('inventory_sync_slave_price_markup', 0);
        }
    }
    
    /**
     * غیرفعال‌سازی پلاگین
     */
    public function deactivate() {
        // بدون حذف داده‌ها
    }
}

// فعال کردن پلاگین
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        Inventory_Sync_Slave::get_instance();
    }
});
?>
