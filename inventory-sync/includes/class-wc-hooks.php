<?php

class Inventory_Sync_WC_Hooks {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Hooks برای تشخیص تغییرات موجودی
        add_action( 'woocommerce_product_set_stock', array( $this, 'handle_simple_product_stock_change' ), 10, 2 );
        add_action( 'woocommerce_variation_set_stock', array( $this, 'handle_variant_stock_change' ), 10, 2 );
        
        // Cron job برای پردازش صف
        add_action( 'inventory_sync_process_queue', array( $this, 'process_queue' ) );
        
        // Cron job برای cleanup
        add_action( 'inventory_sync_cleanup', array( $this, 'cleanup_old_data' ) );
    }
    
    /**
     * تشخیص تغییر موجودی محصول ساده
     */
    public function handle_simple_product_stock_change( $product, $stock_status ) {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }
        
        // بررسی اینکه آیا این محصول مرتبط است
        $mappings = Inventory_Sync_Product_Mapper::get_instance()->get_mappings_by_product(
            1,
            $product->get_id()
        );
        
        if ( empty( $mappings ) ) {
            return;
        }
        
        foreach ( $mappings as $mapping ) {
            // بررسی اینکه آیا هماهنگ‌سازی فعال است
            if ( ! $mapping->sync_enabled ) {
                continue;
            }
            
            $current_qty = intval( $product->get_stock_quantity() );
            
            // ایجاد لاگ
            Inventory_Sync_Log_Manager::get_instance()->create_log(
                $mapping->id,
                array(
                    'product_id'    => $product->get_id(),
                    'product_name'  => $product->get_name(),
                    'variant_id'    => null,
                    'site'          => 1,
                    'old_quantity'  => intval( get_post_meta( $product->get_id(), '_stock', true ) ),
                    'new_quantity'  => $current_qty,
                    'status'        => 'pending',
                    'triggered_by'  => 'auto'
                )
            );
            
            // اضافه کردن به صف
            Inventory_Sync_Queue_Manager::get_instance()->enqueue_action(
                $mapping->id,
                'sync_inventory',
                1,
                $current_qty
            );
        }
    }
    
    /**
     * تشخیص تغییر موجودی متغیر
     */
    public function handle_variant_stock_change( $variation, $stock_status ) {
        if ( ! $variation || ! is_a( $variation, 'WC_Product_Variation' ) ) {
            return;
        }
        
        $variant_id = $variation->get_id();
        $parent_id = $variation->get_parent_id();
        
        // بررسی اینکه آیا این متغیر مرتبط است
        global $wpdb;
        $mapping = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_product_mapping 
                 WHERE site1_product_id = %d AND site1_variant_id = %d",
                $parent_id,
                $variant_id
            )
        );
        
        if ( ! $mapping || ! $mapping->sync_enabled ) {
            return;
        }
        
        $current_qty = intval( $variation->get_stock_quantity() );
        
        // ایجاد لاگ
        Inventory_Sync_Log_Manager::get_instance()->create_log(
            $mapping->id,
            array(
                'product_id'    => $parent_id,
                'product_name'  => get_the_title( $parent_id ),
                'variant_id'    => $variant_id,
                'variant_name'  => $variation->get_name(),
                'site'          => 1,
                'old_quantity'  => intval( get_post_meta( $variant_id, '_stock', true ) ),
                'new_quantity'  => $current_qty,
                'status'        => 'pending',
                'triggered_by'  => 'auto'
            )
        );
        
        // اضافه کردن به صف
        Inventory_Sync_Queue_Manager::get_instance()->enqueue_action(
            $mapping->id,
            'sync_inventory',
            1,
            $current_qty
        );
    }
    
    /**
     * پردازش صف
     */
    public function process_queue() {
        $queue_manager = Inventory_Sync_Queue_Manager::get_instance();
        
        // دریافت وظایف زمان‌بندی‌شده
        $pending_tasks = $queue_manager->get_pending_tasks( 10 );
        
        if ( empty( $pending_tasks ) ) {
            return;
        }
        
        foreach ( $pending_tasks as $task ) {
            $result = $queue_manager->process_queue_item( $task->id );
            
            if ( is_wp_error( $result ) ) {
                error_log( 'Inventory Sync Queue Error: ' . $result->get_error_message() );
            }
        }
        
        // دوباره تلاش برای موارد ناموفق
        $queue_manager->retry_failed_items( 5 );
    }
    
    /**
     * پاکیزه کردن داده‌های قدیمی
     */
    public function cleanup_old_data() {
        // حذف لاگ‌های قدیمی
        Inventory_Sync_Log_Manager::get_instance()->cleanup_old_logs( 90 );
        
        // حذف صف‌های قدیمی
        Inventory_Sync_Queue_Manager::get_instance()->cleanup_old_tasks( 30 );
    }
}

// فعال‌سازی Hooks
new Inventory_Sync_WC_Hooks();
