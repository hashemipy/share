<?php

/**
 * Inventory_Sync_Database_Migration - کمک برای مهاجرت database
 * 
 * وقتی نسخه جدید نصب شود، این کلاس تغییرات database را اعمال می‌کند
 */
class Inventory_Sync_Database_Migration {
    
    /**
     * اجرا کردن تمام migrations
     * این متد در activation hook نصب شود
     */
    public static function run_migrations() {
        self::add_retry_count_column();
    }
    
    /**
     * اضافه کردن ستون retry_count (اگر وجود ندارد)
     * 
     * ⭐ این ستون برای بهبود retry logic ضروری است
     */
    private static function add_retry_count_column() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'inventory_sync_mapping';
        $charset_collate = $wpdb->get_charset_collate();
        
        // بررسی اینکه ستون وجود دارد
        $column_exists = $wpdb->get_results(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = '{$table_name}' 
             AND COLUMN_NAME = 'retry_count'"
        );
        
        if (empty($column_exists)) {
            // اضافه کردن ستون
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN retry_count INT DEFAULT 0 AFTER error_message");
            
            // لاگ کردن
            error_log('[Inventory Sync] Added retry_count column to ' . $table_name);
        }
    }
}
