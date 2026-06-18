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
        self::migration_01_create_product_pairs_table(); // ✨
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
    
    /**
     * ✨ میگریشن 1: جدول جفت‌های محصولات
     */
    private static function migration_01_create_product_pairs_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inventory_sync_product_pairs';
        $charset_collate = $wpdb->get_charset_collate();
        
        // بررسی کن جدول قبلاً ایجاد شده یا نه
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            return; // جدول قبلاً وجود دارد
        }
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            site1_product_id BIGINT(20) UNSIGNED NOT NULL,
            site2_product_id BIGINT(20) UNSIGNED NOT NULL,
            site1_product_name VARCHAR(255),
            site2_product_name VARCHAR(255),
            site1_sku VARCHAR(255),
            site2_sku VARCHAR(255),
            sync_direction ENUM('bidirectional', 'site1_to_site2', 'site2_to_site1') DEFAULT 'bidirectional',
            is_active BOOLEAN DEFAULT 1,
            last_sync DATETIME,
            last_sync_direction VARCHAR(20) COMMENT 'آخرین جهت sync (site1, site2)',
            sync_count INT DEFAULT 0,
            error_message LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_pair (site1_product_id, site2_product_id),
            INDEX idx_active (is_active),
            INDEX idx_created (created_at),
            INDEX idx_last_sync (last_sync)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        error_log('[Inventory Sync] Created product_pairs table');
    }
}
