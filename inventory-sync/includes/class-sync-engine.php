<?php

class Inventory_Sync_Engine {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * اعمال تغییر موجودی
     */
    public function apply_stock_change( $site, $product_id, $variant_id, $quantity ) {
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return new WP_Error( 'product_not_found', 'محصول پیدا نشد' );
        }
        
        try {
            // اگر متغیر است
            if ( $product->is_type( 'variable' ) && $variant_id ) {
                $variant = wc_get_product( $variant_id );
                if ( ! $variant ) {
                    return new WP_Error( 'variant_not_found', 'متغیر پیدا نشد' );
                }
                $variant->set_stock_quantity( intval( $quantity ) );
                $variant->save();
            } else {
                // محصول ساده
                $product->set_stock_quantity( intval( $quantity ) );
                $product->save();
            }
            
            return true;
        } catch ( Exception $e ) {
            return new WP_Error( 'stock_update_error', $e->getMessage() );
        }
    }
    
    /**
     * دریافت موجودی فعلی
     */
    public function get_current_inventory( $site, $product_id, $variant_id = null ) {
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return new WP_Error( 'product_not_found', 'محصول پیدا نشد' );
        }
        
        if ( $product->is_type( 'variable' ) && $variant_id ) {
            $variant = wc_get_product( $variant_id );
            if ( ! $variant ) {
                return new WP_Error( 'variant_not_found', 'متغیر پیدا نشد' );
            }
            return intval( $variant->get_stock_quantity() );
        }
        
        return intval( $product->get_stock_quantity() );
    }
    
    /**
     * هماهنگ‌سازی دستی
     */
    public function manual_sync( $mapping_id, $source_site = 1 ) {
        $mapping = Inventory_Sync_Product_Mapper::get_instance()->get_mapping( $mapping_id );
        
        if ( ! $mapping ) {
            return new WP_Error( 'mapping_not_found', 'ارتباط پیدا نشد' );
        }
        
        if ( ! $mapping->sync_enabled ) {
            return new WP_Error( 'sync_disabled', 'هماهنگ‌سازی غیرفعال است' );
        }
        
        // دریافت موجودی سایت منبع
        $source_product_id = ( 1 === intval( $source_site ) ) 
            ? $mapping->site1_product_id 
            : $mapping->site2_product_id;
        
        $source_variant_id = ( 1 === intval( $source_site ) ) 
            ? $mapping->site1_variant_id 
            : $mapping->site2_variant_id;
        
        $current_qty = $this->get_current_inventory( $source_site, $source_product_id, $source_variant_id );
        
        if ( is_wp_error( $current_qty ) ) {
            return $current_qty;
        }
        
        // اضافه کردن به صف
        Inventory_Sync_Queue_Manager::get_instance()->enqueue_action(
            $mapping_id,
            'sync_inventory',
            $source_site,
            $current_qty
        );
        
        return array( 'quantity' => $current_qty, 'message' => 'وظیفه هماهنگ‌سازی افزوده شد' );
    }
    
    /**
     * بررسی وضعیت ارتباط
     */
    public function get_sync_status( $mapping_id ) {
        global $wpdb;
        
        $mapping = Inventory_Sync_Product_Mapper::get_instance()->get_mapping( $mapping_id );
        
        if ( ! $mapping ) {
            return new WP_Error( 'mapping_not_found', 'ارتباط پیدا نشد' );
        }
        
        // تعداد وظایف در حال انتظار
        $pending_tasks = intval( $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}inventory_sync_queue 
                 WHERE mapping_id = %d AND status = %s",
                $mapping_id,
                'pending'
            )
        ) );
        
        $site1_qty = $this->get_current_inventory( 1, $mapping->site1_product_id, $mapping->site1_variant_id );
        $site2_qty = $this->get_current_inventory( 2, $mapping->site2_product_id, $mapping->site2_variant_id );
        
        return array(
            'site1_quantity'   => is_wp_error( $site1_qty ) ? 0 : $site1_qty,
            'site2_quantity'   => is_wp_error( $site2_qty ) ? 0 : $site2_qty,
            'sync_enabled'     => (bool) $mapping->sync_enabled,
            'last_sync_time'   => $mapping->last_sync_time,
            'pending_tasks'    => $pending_tasks,
            'in_sync'          => $pending_tasks > 0
        );
    }
    
    /**
     * دریافت همه ارتباطات محصول
     */
    public function get_product_mappings( $site, $product_id ) {
        return Inventory_Sync_Product_Mapper::get_instance()->get_mappings_by_product( $site, $product_id );
    }
}
