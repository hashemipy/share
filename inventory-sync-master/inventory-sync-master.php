<?php
/**
 * Plugin Name: Inventory Sync Master
 * Plugin URI: https://example.com/inventory-sync-master
 * Description: مدیریت محصولات و هماهنگ‌سازی انبار - سایت مرکزی (Master)
 * Version: 1.0.0
 * Author: شما
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: inventory-sync-master
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// ثابت‌ها
define('INVENTORY_SYNC_MASTER_VERSION', '1.0.0');
define('INVENTORY_SYNC_MASTER_FILE', __FILE__);
define('INVENTORY_SYNC_MASTER_DIR', plugin_dir_path(__FILE__));
define('INVENTORY_SYNC_MASTER_URL', plugin_dir_url(__FILE__));

// بارگذاری فایل‌های کلاس‌ها
require_once INVENTORY_SYNC_MASTER_DIR . 'includes/class-master-settings.php';
require_once INVENTORY_SYNC_MASTER_DIR . 'includes/class-master-api.php';
require_once INVENTORY_SYNC_MASTER_DIR . 'includes/class-master-admin.php';
require_once INVENTORY_SYNC_MASTER_DIR . 'includes/class-master-sync.php';
require_once INVENTORY_SYNC_MASTER_DIR . 'includes/class-master-webhook.php';

/**
 * کلاس اصلی پلاگین
 */
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
        if (class_exists('Inventory_Sync_Master_Settings')) {
            Inventory_Sync_Master_Settings::init();
        }
        
        // فعالیت‌های مدیریتی
        if (is_admin() && class_exists('Inventory_Sync_Master_Admin')) {
            Inventory_Sync_Master_Admin::init();
        }
        
        // سیستم‌های هماهنگ‌سازی
        if (class_exists('Inventory_Sync_Master_Sync')) {
            Inventory_Sync_Master_Sync::init();
        }
        
        if (class_exists('Inventory_Sync_Master_Webhook')) {
            Inventory_Sync_Master_Webhook::init();
        }
        
        // Hooks
        register_activation_hook(INVENTORY_SYNC_MASTER_FILE, [$this, 'activate']);
        register_deactivation_hook(INVENTORY_SYNC_MASTER_FILE, [$this, 'deactivate']);
    }
    
    /**
     * فعال‌سازی پلاگین
     */
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // جدول Mapping
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
        Inventory_Sync_Master::get_instance();
    }
});
?>
