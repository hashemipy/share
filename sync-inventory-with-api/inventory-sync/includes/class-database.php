<?php

class Inventory_Sync_Database {
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table1 = $wpdb->prefix . 'inventory_sync_mapping';
        $table2 = $wpdb->prefix . 'inventory_sync_logs';
        
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
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
    }
    
    public static function insert_log($product_id, $product_name, $action, $source_site, $target_site, $old_value, $new_value, $status, $error = '') {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_logs',
            [
                'product_id' => (int) $product_id,
                'product_name' => (string) $product_name,
                'action' => (string) $action,
                'source_site' => (string) $source_site,
                'target_site' => (string) $target_site,
                'old_value' => (string) $old_value,
                'new_value' => (string) $new_value,
                'status' => (string) $status,
                'error_message' => (string) $error
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            error_log('Inventory Sync Log Error: ' . $wpdb->last_error);
        }
        
        return $result;
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
}
