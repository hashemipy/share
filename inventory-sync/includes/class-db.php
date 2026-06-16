<?php

class Inventory_Sync_DB {
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Mapping table
        $sql_mapping = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}inventory_sync_mapping (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site1_product_id bigint(20) NOT NULL,
            site2_product_id bigint(20) NOT NULL,
            site1_sku varchar(100),
            site2_sku varchar(100),
            sync_enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_site1_product (site1_product_id),
            KEY idx_site2_product (site2_product_id),
            KEY idx_sync_enabled (sync_enabled),
            UNIQUE KEY unique_products (site1_product_id, site2_product_id)
        ) $charset_collate;";
        
        // Log table
        $sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}inventory_sync_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            mapping_id bigint(20),
            type varchar(50),
            message longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_mapping (mapping_id),
            KEY idx_type (type),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_mapping);
        dbDelta($sql_logs);
    }
    
    /**
     * محصولات را بدون N+1 queries بگیرید
     */
    public static function get_mapped_products_optimized($limit = 100) {
        global $wpdb;
        
        // یک query - نه 100
        $results = $wpdb->get_results("
            SELECT 
                m.id,
                m.site1_product_id,
                m.site2_product_id,
                m.site1_sku,
                m.site2_sku,
                m.sync_enabled,
                p.post_title as product_name
            FROM {$wpdb->prefix}inventory_sync_mapping m
            LEFT JOIN {$wpdb->posts} p ON (m.site1_product_id = p.ID AND p.post_type = 'product')
            ORDER BY m.id DESC
            LIMIT $limit
        ");
        
        return $results ?: [];
    }
    
    /**
     * موجودی را sync کنید - با Batch updates
     */
    public static function update_inventory_batch($updates) {
        global $wpdb;
        
        if (empty($updates)) {
            return 0;
        }
        
        $updated = 0;
        
        // Batch کنید - چند transaction
        foreach (array_chunk($updates, 50) as $batch) {
            foreach ($batch as $update) {
                $updated += $wpdb->update(
                    "{$wpdb->postmeta}",
                    ['meta_value' => $update['stock']],
                    [
                        'post_id' => $update['product_id'],
                        'meta_key' => '_stock'
                    ],
                    ['%d'],
                    ['%d', '%s']
                );
            }
        }
        
        return $updated;
    }
    
    /**
     * Log کو صاف کنید - قدیم log حذف کنید
     */
    public static function cleanup_old_logs($days = 30) {
        global $wpdb;
        
        $date_threshold = gmdate('Y-m-d H:i:s', strtotime("-$days days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}inventory_sync_logs WHERE created_at < %s",
            $date_threshold
        ));
    }
    
    /**
     * Cache کو صاف کنید
     */
    public static function clear_caches() {
        wp_cache_delete('inventory_sync_products_1');
        wp_cache_delete('inventory_sync_products_2');
        wp_cache_flush();
    }
}
