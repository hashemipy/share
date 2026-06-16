<?php
/**
 * کلاس تنظیمات سایت Slave
 */

class Inventory_Sync_Slave_Settings {
    
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }
    
    public static function add_menu() {
        add_menu_page(
            'محصولات دریافت‌شده',
            'محصولات Slave',
            'manage_woocommerce',
            'inventory-sync-slave',
            [__CLASS__, 'render_page'],
            'dashicons-download',
            59
        );
        
        add_submenu_page(
            'inventory-sync-slave',
            'تنظیمات',
            'تنظیمات',
            'manage_woocommerce',
            'inventory-sync-slave-settings',
            [__CLASS__, 'render_settings']
        );
    }
    
    public static function register_settings() {
        register_setting('inventory-sync-slave', 'inventory_sync_slave_api_key');
        register_setting('inventory-sync-slave', 'inventory_sync_slave_api_secret');
        register_setting('inventory-sync-slave', 'inventory_sync_slave_price_markup');
        register_setting('inventory-sync-slave', 'inventory_sync_slave_webhook_url');
    }
    
    public static function get_api_key() {
        return get_option('inventory_sync_slave_api_key', wp_generate_uuid4());
    }
    
    public static function get_api_secret() {
        return get_option('inventory_sync_slave_api_secret', wp_generate_uuid4());
    }
    
    public static function get_price_markup() {
        return floatval(get_option('inventory_sync_slave_price_markup', 0));
    }
    
    public static function get_webhook_url() {
        return rest_url('inventory-sync-slave/v1/products/import');
    }
    
    public static function render_page() {
        // بررسی وجود فایل dashboard
        $dashboard_file = INVENTORY_SYNC_SLAVE_DIR . 'admin/dashboard-slave.php';
        if (file_exists($dashboard_file)) {
            ?>
            <div class="wrap">
                <h1>محصولات دریافت‌شده - سایت Slave</h1>
                <div style="max-width: 1200px; margin-top: 20px;">
                    <?php require $dashboard_file; ?>
                </div>
            </div>
            <?php
        } else {
            ?>
            <div class="wrap">
                <h1>محصولات دریافت‌شده - سایت Slave</h1>
                <div class="notice notice-error">
                    <p>⚠️ فایل dashboard پیدا نشد. لطفاً فایل‌های پلاگین را بررسی کنید.</p>
                </div>
            </div>
            <?php
        }
    }
    
    public static function render_settings() {
        ?>
        <div class="wrap">
            <h1>تنظیمات محصولات Slave</h1>
            <form method="post" action="options.php" style="max-width: 600px; margin-top: 20px;">
                <?php settings_fields('inventory-sync-slave'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label>API Key:</label></th>
                        <td>
                            <input type="text" value="<?php echo esc_attr(self::get_api_key()); ?>" readonly class="regular-text">
                            <p class="description">برای استفاده در سایت Master</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>API Secret:</label></th>
                        <td>
                            <input type="text" value="<?php echo esc_attr(self::get_api_secret()); ?>" readonly class="regular-text">
                            <p class="description">برای استفاده در سایت Master</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="price-markup">درصد افزایش قیمت:</label></th>
                        <td>
                            <input type="number" id="price-markup" name="inventory_sync_slave_price_markup" 
                                   value="<?php echo esc_attr(self::get_price_markup()); ?>" 
                                   step="0.01" min="0" max="100" class="regular-text">
                            <p class="description">مثال: 10 برای ۱۰ درصد افزایش قیمت</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Webhook URL:</label></th>
                        <td>
                            <input type="text" value="<?php echo esc_attr(self::get_webhook_url()); ?>" readonly class="regular-text">
                            <p class="description">برای استفاده در سایت Master</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }
}
