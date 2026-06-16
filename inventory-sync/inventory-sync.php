<?php
/**
 * Plugin Name: Inventory Sync Pro
 * Plugin URI: https://example.com/inventory-sync
 * Description: هماهنگ‌سازی موجودی محصولات بین دو سایت WooCommerce
 * Version: 1.0.0
 * Author: شما
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: inventory-sync
 * Domain Path: /languages
 * WC tested up to: 8.0
 * Requires: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('INVENTORY_SYNC_VERSION', '1.0.0');
define('INVENTORY_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INVENTORY_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INVENTORY_SYNC_BASENAME', plugin_basename(__FILE__));

// Autoloader
require_once INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-loader.php';
new Inventory_Sync_Loader();

// Init Plugin
add_action('plugins_loaded', function () {
    do_action('inventory_sync_loaded');
    
    if (class_exists('WooCommerce')) {
        require_once INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-plugin.php';
        Inventory_Sync_Plugin::get_instance();
    } else {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            esc_html_e('Inventory Sync نیاز به WooCommerce دارد!', 'inventory-sync');
            echo '</p></div>';
        });
    }
});

// Load Text Domain
add_action('init', function () {
    load_plugin_textdomain('inventory-sync', false, dirname(INVENTORY_SYNC_BASENAME) . '/languages');
});

// Activation Hook
register_activation_hook(__FILE__, function () {
    require_once INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-database.php';
    require_once INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-database-migration.php';
    
    Inventory_Sync_Database::create_tables();
    Inventory_Sync_Database_Migration::run_migrations();
    
    flush_rewrite_rules();
});

// Deactivation Hook
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('inventory_sync_process_queue');
    wp_clear_scheduled_hook('inventory_sync_cleanup');
    flush_rewrite_rules();
});

// ثبت Cron Jobs
add_action('wp_loaded', function () {
    // پردازش صف هر 5 دقیقه
    if (!wp_next_scheduled('inventory_sync_process_queue')) {
        wp_schedule_event(time(), 'five_minutes', 'inventory_sync_process_queue');
    }
    
    // پاکیزه کردن داده‌های قدیمی روزانه
    if (!wp_next_scheduled('inventory_sync_cleanup')) {
        wp_schedule_event(time(), 'daily', 'inventory_sync_cleanup');
    }
});

// اضافه کردن بازه زمانی 5 دقیقه
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['five_minutes'])) {
        $schedules['five_minutes'] = array(
            'interval' => 5 * 60, // 5 دقیقه
            'display'  => esc_html__('هر 5 دقیقه', 'inventory-sync')
        );
    }
    return $schedules;
});
