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
        
        // تعیین نوع mapping
        $site1_product = wc_get_product( $site1_product_id );
        $site2_product = wc_get_product( $site2_product_id );
        
        if ( ! $site1_product || ! $site2_product ) {
            return new WP_Error( 'invalid_product', 'محصول در دسترس نیست' );
        }
        
        $mapping_type = ( $site1_product->is_type( 'variable' ) && $site2_product->is_type( 'variable' ) ) 
            ? 'variable' 
            : 'simple';
        
        // اگر متغیر انتخاب شده است، نوع را متغیر قرار بده
        if ( $site1_variant_id || $site2_variant_id ) {
            $mapping_type = 'variable';
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_product_mapping',
            array(
                'site1_product_id'  => $site1_product_id,
                'site2_product_id'  => $site2_product_id,
                'site1_variant_id'  => $site1_variant_id,
                'site2_variant_id'  => $site2_variant_id,
                'mapping_type'      => $mapping_type,
                'sync_enabled'      => 1,
                'last_sync_time'    => current_time( 'mysql' ),
                'created_at'        => current_time( 'mysql' ),
                'updated_at'        => current_time( 'mysql' )
            ),
            array( '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
        );
        
        if ( ! $result ) {
            return new WP_Error( 'db_insert_error', 'خطا در ذخیره ارتباط' );
        }
        
        $mapping_id = $wpdb->insert_id;
        
        // لاگ ایجاد ارتباط
        Inventory_Sync_Log_Manager::get_instance()->create_log(
            $mapping_id,
            array(
                'product_id'    => $site1_product_id,
                'product_name'  => $site1_product->get_name(),
                'variant_id'    => $site1_variant_id,
                'site'          => 1,
                'old_quantity'  => 0,
                'new_quantity'  => 0,
                'status'        => 'success',
                'triggered_by'  => 'manual'
            )
        );
        
        return array( 'id' => $mapping_id, 'mapping_type' => $mapping_type );
    }
    
    /**
     * گرفتن یک ارتباط
     */
    public function get_mapping( $mapping_id ) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_product_mapping WHERE id = %d",
                $mapping_id
            )
        );
    }
    
    /**
     * گرفتن ارتباط بر اساس محصولات
     */
    public function get_mapping_by_products( $site1_product_id, $site2_product_id, $site1_variant_id = null, $site2_variant_id = null ) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}inventory_sync_product_mapping WHERE site1_product_id = %d AND site2_product_id = %d";
        $params = array( $site1_product_id, $site2_product_id );
        
        if ( $site1_variant_id && $site2_variant_id ) {
            $query .= " AND site1_variant_id = %d AND site2_variant_id = %d";
            $params[] = $site1_variant_id;
            $params[] = $site2_variant_id;
        }
        
        return $wpdb->get_row( $wpdb->prepare( $query, ...$params ) );
    }
    
    /**
     * گرفتن تمام ارتباطات محصول
     */
    public function get_mappings_by_product( $site, $product_id ) {
        global $wpdb;
        
        if ( 1 === intval( $site ) ) {
            $query = "SELECT * FROM {$wpdb->prefix}inventory_sync_product_mapping WHERE site1_product_id = %d";
        } else {
            $query = "SELECT * FROM {$wpdb->prefix}inventory_sync_product_mapping WHERE site2_product_id = %d";
        }
        
        return $wpdb->get_results(
            $wpdb->prepare( $query, $product_id )
        );
    }
    
    /**
     * گرفتن تمام ارتباطات
     */
    public function get_all_mappings( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'limit'         => 100,
            'offset'        => 0,
            'status'        => null,
            'search'        => null,
            'orderby'       => 'created_at',
            'order'         => 'DESC'
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        $query = "SELECT * FROM {$wpdb->prefix}inventory_sync_product_mapping WHERE 1=1";
        $params = array();
        
        if ( $args['status'] !== null ) {
            $query .= " AND sync_enabled = %d";
            $params[] = $args['status'];
        }
        
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        return $wpdb->get_results(
            $wpdb->prepare( $query, ...$params )
        );
    }
    
    /**
     * بروز رسانی ارتباط
     */
    public function update_mapping( $mapping_id, $data ) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if ( isset( $data['sync_enabled'] ) ) {
            $update_data['sync_enabled'] = intval( $data['sync_enabled'] );
            $format[] = '%d';
        }
        
        if ( isset( $data['last_sync_time'] ) ) {
            $update_data['last_sync_time'] = $data['last_sync_time'];
            $format[] = '%s';
        }
        
        $update_data['updated_at'] = current_time( 'mysql' );
        $format[] = '%s';
        
        $result = $wpdb->update(
            $wpdb->prefix . 'inventory_sync_product_mapping',
            $update_data,
            array( 'id' => $mapping_id ),
            $format,
            array( '%d' )
        );
        
        return $result !== false;
    }
    
    /**
     * حذف ارتباط
     */
    public function delete_mapping( $mapping_id ) {
        global $wpdb;
        
        $mapping = $this->get_mapping( $mapping_id );
        
        if ( ! $mapping ) {
            return new WP_Error( 'mapping_not_found', 'ارتباط پیدا نشد' );
        }
        
        // اضافه کردن به صف برای حذف
        Inventory_Sync_Queue_Manager::get_instance()->enqueue_action(
            $mapping_id,
            'delete_mapping',
            null,
            null
        );
        
        // لاگ حذف
        Inventory_Sync_Log_Manager::get_instance()->create_log(
            $mapping_id,
            array(
                'product_id'    => $mapping->site1_product_id,
                'product_name'  => get_the_title( $mapping->site1_product_id ),
                'site'          => 1,
                'old_quantity'  => 0,
                'new_quantity'  => 0,
                'status'        => 'pending',
                'triggered_by'  => 'manual'
            )
        );
        
        return true;
    }
    
    /**
     * شمارش کل ارتباطات
     */
    public function count_mappings( $status = null ) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}inventory_sync_product_mapping WHERE 1=1";
        
        if ( $status !== null ) {
            $query .= " AND sync_enabled = %d";
            return $wpdb->get_var( $wpdb->prepare( $query, $status ) );
        }
        
        return $wpdb->get_var( $query );
    }
}
