<?php

class Inventory_Sync_Log_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * ایجاد لاگ
     */
    public function create_log( $mapping_id, $data ) {
        global $wpdb;
        
        $insert_data = array(
            'mapping_id'    => $mapping_id,
            'product_id'    => isset( $data['product_id'] ) ? intval( $data['product_id'] ) : 0,
            'product_name'  => isset( $data['product_name'] ) ? sanitize_text_field( $data['product_name'] ) : '',
            'variant_id'    => isset( $data['variant_id'] ) ? intval( $data['variant_id'] ) : null,
            'variant_name'  => isset( $data['variant_name'] ) ? sanitize_text_field( $data['variant_name'] ) : null,
            'site'          => isset( $data['site'] ) ? intval( $data['site'] ) : 0,
            'old_quantity'  => isset( $data['old_quantity'] ) ? intval( $data['old_quantity'] ) : 0,
            'new_quantity'  => isset( $data['new_quantity'] ) ? intval( $data['new_quantity'] ) : 0,
            'status'        => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'pending',
            'error_message' => isset( $data['error_message'] ) ? sanitize_text_field( $data['error_message'] ) : null,
            'triggered_by'  => isset( $data['triggered_by'] ) ? sanitize_text_field( $data['triggered_by'] ) : 'manual',
            'created_at'    => current_time( 'mysql' )
        );
        
        $format = array( '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_logs',
            $insert_data,
            $format
        );
        
        if ( ! $result ) {
            error_log( 'Inventory Sync Log Insert Error: ' . $wpdb->last_error );
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * گرفتن لاگ‌ها
     */
    public function get_logs( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'limit'         => 100,
            'offset'        => 0,
            'status'        => null,
            'site'          => null,
            'product_id'    => null,
            'mapping_id'    => null,
            'date_from'     => null,
            'date_to'       => null,
            'orderby'       => 'created_at',
            'order'         => 'DESC'
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        $query = "SELECT * FROM {$wpdb->prefix}inventory_sync_logs WHERE 1=1";
        $params = array();
        
        if ( $args['status'] !== null ) {
            $query .= " AND status = %s";
            $params[] = $args['status'];
        }
        
        if ( $args['site'] !== null ) {
            $query .= " AND site = %d";
            $params[] = intval( $args['site'] );
        }
        
        if ( $args['product_id'] !== null ) {
            $query .= " AND product_id = %d";
            $params[] = intval( $args['product_id'] );
        }
        
        if ( $args['mapping_id'] !== null ) {
            $query .= " AND mapping_id = %d";
            $params[] = intval( $args['mapping_id'] );
        }
        
        if ( $args['date_from'] !== null ) {
            $query .= " AND created_at >= %s";
            $params[] = $args['date_from'];
        }
        
        if ( $args['date_to'] !== null ) {
            $query .= " AND created_at <= %s";
            $params[] = $args['date_to'];
        }
        
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        $query .= " LIMIT %d OFFSET %d";
        $params[] = intval( $args['limit'] );
        $params[] = intval( $args['offset'] );
        
        if ( empty( $params ) ) {
            return $wpdb->get_results( $query );
        }
        
        return $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );
    }
    
    /**
     * گرفتن یک لاگ
     */
    public function get_log( $log_id ) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_logs WHERE id = %d",
                intval( $log_id )
            )
        );
    }
    
    /**
     * بروز رسانی وضعیت لاگ
     */
    public function update_log_status( $log_id, $status, $error_message = null ) {
        global $wpdb;
        
        $data = array( 'status' => sanitize_text_field( $status ) );
        
        if ( $error_message !== null ) {
            $data['error_message'] = sanitize_text_field( $error_message );
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'inventory_sync_logs',
            $data,
            array( 'id' => intval( $log_id ) ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }
    
    /**
     * بروز رسانی لاگ‌ها بر اساس صف
     */
    public function update_sync_logs_from_queue( $queue_item ) {
        global $wpdb;
        
        // پیدا کردن آخرین لاگ مرتبط
        $log = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_logs 
                 WHERE mapping_id = %d AND status = %s 
                 ORDER BY created_at DESC LIMIT 1",
                intval( $queue_item->mapping_id ),
                'pending'
            )
        );
        
        if ( $log ) {
            $this->update_log_status( $log->id, 'success' );
        }
    }
    
    /**
     * رفرش دستی لاگ
     */
    public function retry_log_sync( $log_id ) {
        $log = $this->get_log( $log_id );
        
        if ( ! $log ) {
            return new WP_Error( 'log_not_found', 'لاگ پیدا نشد' );
        }
        
        // اضافه کردن دوباره به صف
        Inventory_Sync_Queue_Manager::get_instance()->enqueue_action(
            $log->mapping_id,
            'sync_inventory',
            ( 1 === intval( $log->site ) ? 1 : 2 ),
            $log->new_quantity
        );
        
        // بروز رسانی وضعیت لاگ
        $this->update_log_status( $log_id, 'pending' );
        
        return array( 'message' => 'دوباره پردازش افزوده شد' );
    }
    
    /**
     * شمارش لاگ‌ها
     */
    public function count_logs( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'status'     => null,
            'site'       => null,
            'product_id' => null,
            'mapping_id' => null
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}inventory_sync_logs WHERE 1=1";
        $params = array();
        
        if ( $args['status'] !== null ) {
            $query .= " AND status = %s";
            $params[] = $args['status'];
        }
        
        if ( $args['site'] !== null ) {
            $query .= " AND site = %d";
            $params[] = intval( $args['site'] );
        }
        
        if ( $args['product_id'] !== null ) {
            $query .= " AND product_id = %d";
            $params[] = intval( $args['product_id'] );
        }
        
        if ( $args['mapping_id'] !== null ) {
            $query .= " AND mapping_id = %d";
            $params[] = intval( $args['mapping_id'] );
        }
        
        if ( empty( $params ) ) {
            return intval( $wpdb->get_var( $query ) );
        }
        
        return intval( $wpdb->get_var( $wpdb->prepare( $query, ...$params ) ) );
    }
    
    /**
     * حذف لاگ‌های قدیمی
     */
    public function cleanup_old_logs( $days = 90 ) {
        global $wpdb;
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}inventory_sync_logs 
                 WHERE status = %s AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                'success',
                intval( $days )
            )
        );
    }
    
    /**
     * دریافت آمار لاگ‌های ناموفق
     */
    public function get_failed_logs_stats() {
        global $wpdb;
        
        return $wpdb->get_row(
            "SELECT COUNT(*) as failed_count, MAX(created_at) as last_failed 
             FROM {$wpdb->prefix}inventory_sync_logs 
             WHERE status = 'failed'"
        );
    }
}
