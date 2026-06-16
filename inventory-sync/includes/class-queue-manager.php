<?php

class Inventory_Sync_Queue_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * اضافه کردن وظیفه به صف
     */
    public function enqueue_action( $mapping_id, $action, $source_site = null, $new_quantity = null ) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'inventory_sync_queue',
            array(
                'mapping_id'    => $mapping_id,
                'action'        => $action,
                'source_site'   => $source_site,
                'new_quantity'  => $new_quantity,
                'status'        => 'pending',
                'retry_count'   => 0,
                'created_at'    => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
        );
        
        if ( ! $result ) {
            error_log( 'Inventory Sync Queue Insert Error: ' . $wpdb->last_error );
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * گرفتن وظایف زمان‌بندی‌شده
     */
    public function get_pending_tasks( $limit = 10 ) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_queue 
                 WHERE status = %s 
                 ORDER BY created_at ASC 
                 LIMIT %d",
                'pending',
                $limit
            )
        );
    }
    
    /**
     * پردازش یک وظیفه
     */
    public function process_queue_item( $queue_id ) {
        $queue_item = $this->get_queue_item( $queue_id );
        
        if ( ! $queue_item ) {
            return new WP_Error( 'queue_item_not_found', 'وظیفه صف پیدا نشد' );
        }
        
        // تغییر وضعیت به در حال پردازش
        $this->update_queue_status( $queue_id, 'processing' );
        
        try {
            if ( 'sync_inventory' === $queue_item->action ) {
                return $this->process_sync_inventory( $queue_item );
            } elseif ( 'delete_mapping' === $queue_item->action ) {
                return $this->process_delete_mapping( $queue_item );
            }
        } catch ( Exception $e ) {
            $this->mark_as_failed( $queue_id, $e->getMessage() );
            return new WP_Error( 'processing_error', $e->getMessage() );
        }
    }
    
    /**
     * پردازش هماهنگ‌سازی موجودی
     */
    private function process_sync_inventory( $queue_item ) {
        $mapping = Inventory_Sync_Product_Mapper::get_instance()->get_mapping( $queue_item->mapping_id );
        
        if ( ! $mapping ) {
            return new WP_Error( 'mapping_not_found', 'ارتباط پیدا نشد' );
        }
        
        // تعیین محصول مقصد
        $target_product_id = ( 1 === intval( $queue_item->source_site ) ) 
            ? $mapping->site2_product_id 
            : $mapping->site1_product_id;
        
        $target_variant_id = ( 1 === intval( $queue_item->source_site ) ) 
            ? $mapping->site2_variant_id 
            : $mapping->site1_variant_id;
        
        $target_site = ( 1 === intval( $queue_item->source_site ) ) ? 2 : 1;
        
        // اعمال تغییر موجودی
        $sync_engine = Inventory_Sync_Engine::get_instance();
        $result = $sync_engine->apply_stock_change(
            $target_site,
            $target_product_id,
            $target_variant_id,
            $queue_item->new_quantity
        );
        
        if ( is_wp_error( $result ) ) {
            throw new Exception( $result->get_error_message() );
        }
        
        // تحدیث لاگ
        $log_manager = Inventory_Sync_Log_Manager::get_instance();
        $log_manager->update_sync_logs_from_queue( $queue_item );
        
        // علامت‌گذاری به عنوان موفق
        $this->mark_as_completed( $queue_item->id );
        
        return true;
    }
    
    /**
     * پردازش حذف ارتباط
     */
    private function process_delete_mapping( $queue_item ) {
        global $wpdb;
        
        // حذف ارتباط
        $result = $wpdb->delete(
            $wpdb->prefix . 'inventory_sync_product_mapping',
            array( 'id' => $queue_item->mapping_id ),
            array( '%d' )
        );
        
        if ( ! $result ) {
            throw new Exception( 'خطا در حذف ارتباط' );
        }
        
        // علامت‌گذاری به عنوان موفق
        $this->mark_as_completed( $queue_item->id );
        
        return true;
    }
    
    /**
     * گرفتن یک وظیفه
     */
    public function get_queue_item( $queue_id ) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_queue WHERE id = %d",
                $queue_id
            )
        );
    }
    
    /**
     * بروز رسانی وضعیت صف
     */
    public function update_queue_status( $queue_id, $status, $error_message = null ) {
        global $wpdb;
        
        $data = array(
            'status'     => $status,
            'updated_at' => current_time( 'mysql' )
        );
        
        if ( $error_message ) {
            $data['error_message'] = $error_message;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'inventory_sync_queue',
            $data,
            array( 'id' => $queue_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }
    
    /**
     * علامت‌گذاری به عنوان موفق
     */
    public function mark_as_completed( $queue_id ) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'inventory_sync_queue',
            array(
                'status'     => 'completed',
                'updated_at' => current_time( 'mysql' )
            ),
            array( 'id' => $queue_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }
    
    /**
     * علامت‌گذاری به عنوان ناموفق
     */
    public function mark_as_failed( $queue_id, $error_message = null ) {
        global $wpdb;
        
        $queue_item = $this->get_queue_item( $queue_id );
        
        $data = array(
            'status'      => 'failed',
            'retry_count' => intval( $queue_item->retry_count ) + 1,
            'updated_at'  => current_time( 'mysql' )
        );
        
        if ( $error_message ) {
            $data['error_message'] = $error_message;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'inventory_sync_queue',
            $data,
            array( 'id' => $queue_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );
    }
    
    /**
     * بازپردازش موارد ناموفق
     */
    public function retry_failed_items( $limit = 5 ) {
        global $wpdb;
        
        $failed_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_queue 
                 WHERE status = %s 
                 AND retry_count < 5 
                 ORDER BY updated_at ASC 
                 LIMIT %d",
                'failed',
                $limit
            )
        );
        
        foreach ( $failed_items as $item ) {
            // تغییر وضعیت به pending برای دوباره تلاش
            $wpdb->update(
                $wpdb->prefix . 'inventory_sync_queue',
                array( 'status' => 'pending' ),
                array( 'id' => $item->id ),
                array( '%s' ),
                array( '%d' )
            );
        }
        
        return count( $failed_items );
    }
    
    /**
     * شمارش وظایف زمان‌بندی‌شده
     */
    public function count_pending_tasks() {
        global $wpdb;
        
        return intval( $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}inventory_sync_queue WHERE status = %s",
                'pending'
            )
        ) );
    }
    
    /**
     * حذف وظایف انجام‌شده قدیمی
     */
    public function cleanup_old_tasks( $days = 30 ) {
        global $wpdb;
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}inventory_sync_queue 
                 WHERE status = %s 
                 AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                'completed',
                $days
            )
        );
    }
}
