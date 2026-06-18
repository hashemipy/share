<?php
// صفحه مدیریت Mapping - نمایش وضعیت همگام‌سازی و دکمه Retry

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_woocommerce')) {
    wp_die('دسترسی رد شد');
}

// دریافت تمام Mappingها
global $wpdb;
$mappings = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping ORDER BY updated_at DESC"
);
?>

<div class="wrap">
    <h1>مدیریت نقشه‌برداری محصولات و وضعیت همگام‌سازی</h1>
    <p>این صفحه وضعیت همگام‌سازی هر جفت محصول را نمایش می‌دهد. اگر خرابی پیش آمد، می‌توانید دوباره تلاش کنید.</p>
    
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>محصول سایت 1</th>
                <th>محصول سایت 2</th>
                <th>وضعیت</th>
                <th>آخرین تغیر</th>
                <th>موجودی فعلی</th>
                <th>پیغام</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mappings as $mapping): ?>
                <?php
                $status_class = 'status-' . $mapping->sync_status;
                $status_label = $mapping->sync_status === 'synced' ? 'موفق' : ($mapping->sync_status === 'error' ? 'خرابی' : 'در انتظار');
                
                $is_locked = Inventory_Sync_Database::is_mapping_locked($mapping->id);
                $lock_status = $is_locked ? '🔒 قفل شده' : '🔓 باز';
                ?>
                <tr>
                    <td>
                        <strong>#<?php echo $mapping->site1_product_id; ?></strong>
                    </td>
                    <td>
                        <strong>#<?php echo $mapping->site2_product_id; ?></strong>
                    </td>
                    <td>
                        <span class="<?php echo $status_class; ?>">
                            <?php echo $status_label; ?> <?php echo $lock_status; ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $last_change = Inventory_Sync_Database::get_last_change_info($mapping->id);
                        echo esc_html($last_change->last_change_site ?? 'نامشخص');
                        echo '<br>';
                        echo esc_html(date('Y-m-d H:i:s', strtotime($last_change->last_change_timestamp ?? 'now')));
                        ?>
                    </td>
                    <td>
                        <?php echo $last_change->last_change_stock ?? '0'; ?>
                    </td>
                    <td>
                        <small><?php echo esc_html($mapping->sync_status_message ?? $mapping->error_message ?? ''); ?></small>
                    </td>
                    <td>
                        <?php if ($mapping->sync_status === 'error'): ?>
                            <button class="button retry-sync" data-mapping-id="<?php echo $mapping->id; ?>">
                                دوباره تلاش
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
    .status-synced { color: green; font-weight: bold; }
    .status-error { color: red; font-weight: bold; }
    .status-pending { color: orange; font-weight: bold; }
    
    .retry-sync {
        background-color: #0073aa;
        color: white;
        padding: 5px 10px;
    }
</style>

<script>
document.querySelectorAll('.retry-sync').forEach(btn => {
    btn.addEventListener('click', function() {
        const mappingId = this.getAttribute('data-mapping-id');
        
        fetch(ajaxurl, {
            method: 'POST',
            body: new URLSearchParams({
                action: 'inventory_sync_manual_retry',
                _wpnonce: '<?php echo wp_create_nonce('inventory_sync_nonce'); ?>',
                mapping_id: mappingId
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('دوباره تلاش شروع شد');
                location.reload();
            } else {
                alert('خرابی: ' + (data.data?.message || 'نامشخص'));
            }
        });
    });
});
</script>
