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
        $table6 = $wpdb->prefix . 'inventory_sync_product_pairs';
        
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
        
        $sql6 = "CREATE TABLE IF NOT EXISTS $table6 (
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
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);
        dbDelta($sql6);
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
    
    // ============ روش‌های جفت‌سازی محصولات ============
    
    public static function create_product_pair($site1_id, $site2_id, $site1_name, $site2_name, $site1_sku = '', $site2_sku = '', $sync_direction = 'bidirectional') {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_product_pairs',
            [
                'site1_product_id' => $site1_id,
                'site2_product_id' => $site2_id,
                'site1_product_name' => $site1_name,
                'site2_product_name' => $site2_name,
                'site1_sku' => $site1_sku,
                'site2_sku' => $site2_sku,
                'sync_direction' => $sync_direction,
                'is_active' => 1
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d']
        );
    }
    
    public static function get_product_pair($pair_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_product_pairs WHERE id = %d",
                $pair_id
            )
        );
    }
    
    public static function get_product_pair_by_ids($site1_id, $site2_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_product_pairs WHERE site1_product_id = %d AND site2_product_id = %d",
                $site1_id,
                $site2_id
            )
        );
    }
    
    public static function get_pair_by_site_product($product_id, $site = 'site1') {
        global $wpdb;
        
        if ($site === 'site1') {
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}inventory_sync_product_pairs WHERE site1_product_id = %d AND is_active = 1",
                    $product_id
                )
            );
        } else {
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}inventory_sync_product_pairs WHERE site2_product_id = %d AND is_active = 1",
                    $product_id
                )
            );
        }
    }
    
    public static function get_all_active_pairs($limit = -1, $offset = 0) {
        global $wpdb;
        
        if ($limit === -1) {
            return $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_product_pairs WHERE is_active = 1 ORDER BY created_at DESC"
            );
        }
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_product_pairs WHERE is_active = 1 ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }
    
    public static function deactivate_pair($pair_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'inventory_sync_product_pairs',
            ['is_active' => 0],
            ['id' => $pair_id]
        );
    }
    
    public static function delete_pair($pair_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'inventory_sync_product_pairs',
            ['id' => $pair_id]
        );
    }
    
    public static function update_pair_last_sync($pair_id, $direction = '') {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'inventory_sync_product_pairs',
            [
                'last_sync' => current_time('mysql'),
                'last_sync_direction' => $direction,
                'sync_count' => new \WP_Query_Expression('sync_count + 1'),
                'error_message' => ''
            ],
            ['id' => $pair_id],
            ['%s', '%s', null, '%s']
        );
    }
    
    public static function update_pair_sync_count($pair_id) {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}inventory_sync_product_pairs SET sync_count = sync_count + 1 WHERE id = %d",
                $pair_id
            )
        );
    }
    
    public static function update_pair_error($pair_id, $error_message) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'inventory_sync_product_pairs',
            ['error_message' => $error_message],
            ['id' => $pair_id]
        );
    }
    
    public static function get_pairs_to_sync() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}inventory_sync_product_pairs 
             WHERE is_active = 1 
             ORDER BY last_sync ASC LIMIT 50"
        );
    }
}
