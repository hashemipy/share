<?php

class Inventory_Sync_Product_Mapper {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * ایجاد ارتباط بین دو محصول
     */
    public function create_mapping( $site1_product_id, $site2_product_id, $site1_variant_id = null, $site2_variant_id = null ) {
        global $wpdb;
        
        if ( ! $site1_product_id || ! $site2_product_id ) {
            return new WP_Error( 'invalid_products', 'شناسه‌های محصول الزامی است' );
        }
        
        // بررسی اینکه آیا قبلا ایجاد شده است
        $existing = $this->get_mapping_by_products( $site1_product_id, $site2_product_id, $site1_variant_id, $site2_variant_id );
        if ( $existing ) {
            return new WP_Error( 'mapping_exists', 'این ارتباط قبلا ایجاد شده است' );
        }
        
        $table = $wpdb->prefix . 'inventory_sync_product_mapping';
        
        $result = $wpdb->insert(
            $table,
            array(
                'site1_product_id'  => intval( $site1_product_id ),
                'site2_product_id'  => intval( $site2_product_id ),
                'site1_variant_id'  => $site1_variant_id ? intval( $site1_variant_id ) : null,
                'site2_variant_id'  => $site2_variant_id ? intval( $site2_variant_id ) : null,
                'mapping_type'      => 'simple',
                'sync_enabled'      => 1,
                'created_at'        => current_time( 'mysql' )
            )
        );
        
        if ( ! $result ) {
            return new WP_Error( 'db_error', 'خطا در ذخیره‌سازی ارتباط' );
        }
        
        return array( 'id' => $wpdb->insert_id );
    }
    
    /**
     * دریافت ارتباط بر اساس محصولات
     */
    public function get_mapping_by_products( $site1_product_id, $site2_product_id, $site1_variant_id = null, $site2_variant_id = null ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'inventory_sync_product_mapping';
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table WHERE site1_product_id = %d AND site2_product_id = %d",
            intval( $site1_product_id ),
            intval( $site2_product_id )
        );
        
        if ( $site1_variant_id ) {
            $query .= $wpdb->prepare( " AND site1_variant_id = %d", intval( $site1_variant_id ) );
        }
        if ( $site2_variant_id ) {
            $query .= $wpdb->prepare( " AND site2_variant_id = %d", intval( $site2_variant_id ) );
        }
        
        return $wpdb->get_row( $query );
    }
    
    /**
     * دریافت تمام ارتباطات
     */
    public function get_all_mappings( $args = array() ) {
        global $wpdb;
        
        $limit = intval( $args['limit'] ?? 20 );
        $offset = intval( $args['offset'] ?? 0 );
        
        $table = $wpdb->prefix . 'inventory_sync_product_mapping';
        
        $query = "SELECT * FROM $table WHERE 1=1 ORDER BY created_at DESC LIMIT %d OFFSET %d";
        
        return $wpdb->get_results( $wpdb->prepare( $query, $limit, $offset ) );
    }
    
    /**
     * تعداد کل ارتباطات
     */
    public function count_mappings() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'inventory_sync_product_mapping';
        return intval( $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) );
    }
    
    /**
     * حذف ارتباط
     */
    public function delete_mapping( $mapping_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'inventory_sync_product_mapping';
        
        $result = $wpdb->delete( $table, array( 'id' => intval( $mapping_id ) ) );
        
        if ( ! $result ) {
            return new WP_Error( 'db_error', 'خطا در حذف ارتباط' );
        }
        
        return true;
    }
    
    /**
     * دریافت ارتباط بر اساس ID
     */
    public function get_mapping( $mapping_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'inventory_sync_product_mapping';
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            intval( $mapping_id )
        ) );
    }
    
    /**
     * به‌روز کردن زمان آخرین sync
     */
    public function update_last_sync( $mapping_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'inventory_sync_product_mapping';
        
        $wpdb->update(
            $table,
            array( 'last_sync_time' => current_time( 'mysql' ) ),
            array( 'id' => intval( $mapping_id ) )
        );
    }
    
    /**
     * دریافت ارتباطات بر اساس محصول
     */
    public function get_mappings_by_product( $site, $product_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'inventory_sync_product_mapping';
        
        if ( 1 === intval( $site ) ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table WHERE site1_product_id = %d",
                intval( $product_id )
            ) );
        } else {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table WHERE site2_product_id = %d",
                intval( $product_id )
            ) );
        }
    }
}
