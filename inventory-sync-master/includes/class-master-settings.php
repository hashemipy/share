<?php
/**
 * کلاس تنظیمات سایت Master
 */

class Inventory_Sync_Master_Settings {
    
    public static function init() {
        if (is_admin()) {
            add_action('admin_menu', [__CLASS__, 'add_menu']);
            add_action('admin_init', [__CLASS__, 'register_settings']);
        }
    }
    
    public static function add_menu() {
        add_menu_page(
            'هماهنگ‌سازی انبار',
            'هماهنگ‌سازی انبار',
            'manage_woocommerce',
            'inventory-sync-master',
            [__CLASS__, 'render_page'],
            'dashicons-sync',
            59
        );
        
        add_submenu_page(
            'inventory-sync-master',
            'تنظیمات',
            'تنظیمات',
            'manage_woocommerce',
            'inventory-sync-master-settings',
            [__CLASS__, 'render_settings']
        );
    }
    
    public static function register_settings() {
        register_setting('inventory-sync-master', 'inventory_sync_master_site2_url');
        register_setting('inventory-sync-master', 'inventory_sync_master_site2_key');
        register_setting('inventory-sync-master', 'inventory_sync_master_site2_secret');
    }
    
    public static function get_site2_url() {
        return get_option('inventory_sync_master_site2_url', '');
    }
    
    public static function get_site2_key() {
        return get_option('inventory_sync_master_site2_key', '');
    }
    
    public static function get_site2_secret() {
        return get_option('inventory_sync_master_site2_secret', '');
    }
    
    public static function render_page() {
        // بررسی وجود فایل dashboard
        $dashboard_file = INVENTORY_SYNC_MASTER_DIR . 'admin/dashboard-master.php';
        if (file_exists($dashboard_file)) {
            ?>
            <div class="wrap">
                <h1>هماهنگ‌سازی انبار - سایت Master</h1>
                <div style="max-width: 1200px; margin-top: 20px;">
                    <?php require $dashboard_file; ?>
                </div>
            </div>
            <?php
        } else {
            ?>
            <div class="wrap">
                <h1>هماهنگ‌سازی انبار - سایت Master</h1>
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
            <h1>تنظیمات هماهنگ‌سازی</h1>
            <form method="post" action="options.php" style="max-width: 600px; margin-top: 20px;">
                <?php settings_fields('inventory-sync-master'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="site2-url">آدرس سایت 2 (Site 2 URL):</label></th>
                        <td>
                            <input type="url" id="site2-url" name="inventory_sync_master_site2_url" 
                                   value="<?php echo esc_attr(self::get_site2_url()); ?>" 
                                   class="regular-text">
                            <p class="description">مثال: https://site2.com</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="site2-key">API Key:</label></th>
                        <td>
                            <input type="text" id="site2-key" name="inventory_sync_master_site2_key" 
                                   value="<?php echo esc_attr(self::get_site2_key()); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="site2-secret">API Secret:</label></th>
                        <td>
                            <input type="password" id="site2-secret" name="inventory_sync_master_site2_secret" 
                                   value="<?php echo esc_attr(self::get_site2_secret()); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }
}
