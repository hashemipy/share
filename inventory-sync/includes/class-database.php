<?php

class Inventory_Sync_Database {
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table1 = $wpdb->prefix . 'inventory_sync_mapping';
        $table2 = $wpdb->prefix . 'inventory_sync_logs';
        $table3 = $wpdb->prefix . 'inventory_sync_category_mapping';
        $table4 = $wpdb->prefix . 'inventory_sync_attribute_mapping';
        $table5 = $wpdb->prefix . 'inventory_sync_products_transferred';
        
        $sql1 = "CREATE TABLE IF NOT EXISTS $table1 (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            site1_product_id BIGINT(20) UNSIGNED NOT NULL,
            site2_product_id BIGINT(20) UNSIGNED NOT NULL,
            site1_sku VARCHAR(255),
            site2_sku VARCHAR(255),
            sync_enabled BOOLEAN DEFAULT 1,
            last_sync DATETIME,
            sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending',
            error_message LONGTEXT,
            retry_count INT DEFAULT 0,
            
            -- ✅ ستون‌های کلیدی: آخرین موجودی که sync شد
            last_synced_stock_site1 INT DEFAULT 0,
            last_synced_stock_site2 INT DEFAULT 0,
            last_synced_timestamp DATETIME,
            
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_mapping (site1_product_id, site2_product_id),
            INDEX idx_status (sync_status),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        $sql2 = "CREATE TABLE IF NOT EXISTS $table2 (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT(20) UNSIGNED,
            product_name VARCHAR(255),
            action VARCHAR(50),
            source_site VARCHAR(100),
            target_site VARCHAR(100),
            old_value LONGTEXT,
            new_value LONGTEXT,
            status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
            error_message LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_product (product_id),
            INDEX idx_status (status)
        ) $charset_collate;";
        
        $sql3 = "CREATE TABLE IF NOT EXISTS $table3 (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            site1_category_id BIGINT(20) UNSIGNED NOT NULL,
            site2_category_id BIGINT(20) UNSIGNED NOT NULL,
            site1_category_name VARCHAR(255),
            site2_category_name VARCHAR(255),
            site1_parent_id BIGINT(20) UNSIGNED,
            site2_parent_id BIGINT(20) UNSIGNED,
            sync_status ENUM('success', 'failed', 'pending') DEFAULT 'success',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_category_mapping (site1_category_id, site2_category_id),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        $sql4 = "CREATE TABLE IF NOT EXISTS $table4 (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            site1_attribute_id BIGINT(20) UNSIGNED NOT NULL,
            site2_attribute_id BIGINT(20) UNSIGNED NOT NULL,
            site1_attribute_name VARCHAR(255),
            site2_attribute_name VARCHAR(255),
            attribute_type VARCHAR(50),
            sync_status ENUM('success', 'failed', 'pending') DEFAULT 'success',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_attribute_mapping (site1_attribute_id, site2_attribute_id),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        $sql5 = "CREATE TABLE IF NOT EXISTS $table5 (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            site1_product_id BIGINT(20) UNSIGNED NOT NULL,
            site2_product_id BIGINT(20) UNSIGNED NOT NULL,
            product_name VARCHAR(255),
            transferred_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            transfer_status ENUM('success', 'failed') DEFAULT 'success',
            categories_synced BOOLEAN DEFAULT 1,
            attributes_synced BOOLEAN DEFAULT 1,
            error_message LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_transferred (site1_product_id, site2_product_id),
            INDEX idx_transferred (transferred_at),
            INDEX idx_status (transfer_status)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);
    }
    
    public static function insert_log($product_id, $product_name, $action, $source_site, $target_site, $old_value, $new_value, $status, $error = '') {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_logs',
            [
                'product_id' => $product_id,
                'product_name' => $product_name,
                'action' => $action,
                'source_site' => $source_site,
                'target_site' => $target_site,
                'old_value' => $old_value,
                'new_value' => $new_value,
                'status' => $status,
                'error_message' => $error
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
    
    public static function get_mapping($site1_id = null) {
        global $wpdb;
        
        if ($site1_id) {
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping WHERE site1_product_id = %d",
                    $site1_id
                )
            );
        }
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping ORDER BY created_at DESC"
        );
    }
    
    public static function get_logs($limit = 50, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_logs ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }
    
    // دسته‌بندی
    public static function add_category_mapping($site1_id, $site2_id, $site1_name, $site2_name, $site1_parent = 0, $site2_parent = 0) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_category_mapping',
            [
                'site1_category_id' => $site1_id,
                'site2_category_id' => $site2_id,
                'site1_category_name' => $site1_name,
                'site2_category_name' => $site2_name,
                'site1_parent_id' => $site1_parent,
                'site2_parent_id' => $site2_parent,
                'sync_status' => 'success'
            ],
            ['%d', '%d', '%s', '%s', '%d', '%d', '%s']
        );
    }
    
    public static function get_category_mapping($site1_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_category_mapping WHERE site1_category_id = %d",
                $site1_id
            )
        );
    }
    
    // ویژگی‌ها (Attributes)
    public static function add_attribute_mapping($site1_id, $site2_id, $site1_name, $site2_name, $type = 'pa') {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_attribute_mapping',
            [
                'site1_attribute_id' => $site1_id,
                'site2_attribute_id' => $site2_id,
                'site1_attribute_name' => $site1_name,
                'site2_attribute_name' => $site2_name,
                'attribute_type' => $type,
                'sync_status' => 'success'
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
    }
    
    public static function get_attribute_mapping($site1_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_attribute_mapping WHERE site1_attribute_id = %d",
                $site1_id
            )
        );
    }
    
    // محصولات منتقل‌شده
    public static function add_transferred_product($site1_id, $site2_id, $product_name, $status = 'success', $error = '') {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_products_transferred',
            [
                'site1_product_id' => $site1_id,
                'site2_product_id' => $site2_id,
                'product_name' => $product_name,
                'transferred_at' => current_time('mysql'),
                'transfer_status' => $status,
                'categories_synced' => 1,
                'attributes_synced' => 1,
                'error_message' => $error
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s']
        );
    }
    
    public static function get_transferred_products($limit = 100, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_products_transferred WHERE transfer_status = 'success' ORDER BY transferred_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }
    
    public static function is_product_transferred($site1_id, $site2_id) {
        global $wpdb;
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}inventory_sync_products_transferred WHERE site1_product_id = %d AND site2_product_id = %d AND transfer_status = 'success'",
                $site1_id,
                $site2_id
            )
        );
        
        return !empty($result);
    }
    
    /**
     * ✅ Lock یک mapping برای جلوگیری از Ping-Pong Bug
     * Lock برای ۳۰ ثانیه قفل می‌شود
     */
    public static function lock_mapping($mapping_id, $change_site = 'unknown', $new_stock = 0) {
        global $wpdb;
        
        $lock_until = date('Y-m-d H:i:s', time() + 30); // قفل برای 30 ثانیه
        
        return $wpdb->update(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'is_processing' => 1,
                'lock_until' => $lock_until,
                'last_change_site' => $change_site,
                'last_change_timestamp' => current_time('mysql'),
                'last_change_stock' => $new_stock,
                'sync_status_message' => 'در حال همگام‌سازی...'
            ],
            ['id' => $mapping_id],
            ['%d', '%s', '%s', '%s', '%d', '%s']
        );
    }
    
    /**
     * ✅ بررسی اینکه آیا یک mapping قفل است
     */
    public static function is_mapping_locked($mapping_id) {
        global $wpdb;
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE id = %d AND is_processing = 1 AND lock_until > NOW()",
                $mapping_id
            )
        );
        
        return !empty($result);
    }
    
    /**
     * ✅ آزاد کردن قفل یک mapping
     */
    public static function unlock_mapping($mapping_id, $new_status = 'synced', $message = '') {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'inventory_sync_mapping',
            [
                'is_processing' => 0,
                'lock_until' => null,
                'sync_status' => $new_status,
                'sync_status_message' => $message,
                'last_sync' => current_time('mysql')
            ],
            ['id' => $mapping_id],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * ✅ دریافت اطلاعات تغیر آخر (Last Change Source)
     */
    public static function get_last_change_info($mapping_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, last_change_site, last_change_timestamp, last_change_stock, sync_status_message 
                 FROM {$wpdb->prefix}inventory_sync_mapping 
                 WHERE id = %d",
                $mapping_id
            )
        );
    }
    
    /**
     * ✅ آپدیت Cron لاگ - برای بررسی تعداد اجراها
     */
    public static function add_sync_attempt_log($mapping_id, $attempt_type = 'auto', $result = 'pending', $message = '') {
        global $wpdb;
        
        // جدول جدید برای Cron Attempts
        $table = $wpdb->prefix . 'inventory_sync_attempts';
        
        // ایجاد جدول اگر وجود نداشتند
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            mapping_id BIGINT(20) UNSIGNED NOT NULL,
            attempt_type VARCHAR(50),
            attempt_result ENUM('success', 'failed', 'pending', 'skipped') DEFAULT 'pending',
            message LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mapping (mapping_id),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        return $wpdb->insert(
            $table,
            [
                'mapping_id' => $mapping_id,
                'attempt_type' => $attempt_type,
                'attempt_result' => $result,
                'message' => $message
            ],
            ['%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * ✅ بررسی تعداد تلاش‌های اخیر برای یک mapping
     * اگر ۳+ تلاش در ۵ دقیقه اخیر موفق نبود = پرتکرار است
     */
    public static function get_recent_failed_attempts($mapping_id, $minutes = 5) {
        global $wpdb;
        
        $attempts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COUNT(*) as count FROM {$wpdb->prefix}inventory_sync_attempts 
                 WHERE mapping_id = %d 
                 AND attempt_result IN ('failed', 'pending') 
                 AND created_at > DATE_SUB(NOW(), INTERVAL %d MINUTE)",
                $mapping_id,
                $minutes
            )
        );
        
        return isset($attempts[0]->count) ? $attempts[0]->count : 0;
    }
}
