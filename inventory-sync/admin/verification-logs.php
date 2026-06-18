<?php
/**
 * صفحه بررسی دقیق Logs برای تشخیص مشکلات Sync
 * اینجا می‌تونی ببینی کدام موجودی انتخاب شده و چرا
 */

if (!defined('ABSPATH')) {
    exit;
}

$page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;
?>

<div class="wrap">
    <h1>بررسی دقیق Logs (Verification)</h1>
    <p style="color: #666; font-size: 14px;">
        اینجا می‌تونی ببینی کدام موجودی از کدام سایت انتخاب شده و چرا.
        اگر مشکلی وجود داره، علت اینجا مشخص می‌شه.
    </p>

    <table class="widefat">
        <thead>
            <tr>
                <th style="width: 100px;">ID محصول</th>
                <th>نام محصول</th>
                <th style="width: 150px;">سایت</th>
                <th style="width: 100px;">موجودی قبل</th>
                <th style="width: 100px;">موجودی بعد</th>
                <th>دلیل انتخاب</th>
                <th style="width: 150px;">زمان</th>
                <th style="width: 80px;">وضعیت</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // دریافت logs از database
            global $wpdb;
            
            $logs = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}inventory_sync_logs 
                 WHERE sync_type = 'sync_inventory' 
                 ORDER BY created_at DESC 
                 LIMIT $limit OFFSET $offset"
            );
            
            if (empty($logs)) {
                echo '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #999;">هیچ log موجود نیست</td></tr>';
            } else {
                foreach ($logs as $log) {
                    $status_color = $log->sync_status === 'success' ? '#28a745' : ($log->sync_status === 'failed' ? '#dc3545' : '#ffc107');
                    echo '<tr>';
                    echo '<td>' . esc_html($log->product_id) . '</td>';
                    echo '<td>' . esc_html($log->product_name) . '</td>';
                    echo '<td>' . esc_html($log->from_site . ' → ' . $log->to_site) . '</td>';
                    echo '<td style="background: #f0f0f0;">' . intval($log->stock_before) . '</td>';
                    echo '<td style="background: #e8f5e9; font-weight: bold;">' . intval($log->stock_after) . '</td>';
                    echo '<td style="font-size: 12px; color: #555;">' . esc_html($log->error_message ?? 'بدون توضیح') . '</td>';
                    echo '<td style="font-size: 12px;">' . esc_html(isset($log->created_at) ? wp_date('Y-m-d H:i:s', strtotime($log->created_at)) : '') . '</td>';
                    echo '<td style="color: ' . $status_color . '; font-weight: bold;">' . esc_html($log->sync_status) . '</td>';
                    echo '</tr>';
                }
            }
            ?>
        </tbody>
    </table>

    <!-- صفحه‌بندی -->
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            // تعداد کل logs
            $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}inventory_sync_logs WHERE sync_type = 'sync_inventory'");
            $total_pages = ceil($total / $limit);
            
            if ($total_pages > 1) {
                echo '<span class="displaying-num">' . esc_html("$total آیتم") . '</span>';
                echo '<span class="pagination-links">';
                
                // صفحه قبل
                if ($page > 1) {
                    echo '<a class="button" href="?page=inventory-sync-verification&paged=1">«</a>';
                    echo '<a class="button" href="?page=inventory-sync-verification&paged=' . ($page - 1) . '">‹</a>';
                }
                
                echo '<span style="margin: 0 10px;"> صفحه ' . intval($page) . ' از ' . intval($total_pages) . ' </span>';
                
                // صفحه بعد
                if ($page < $total_pages) {
                    echo '<a class="button" href="?page=inventory-sync-verification&paged=' . ($page + 1) . '">›</a>';
                    echo '<a class="button" href="?page=inventory-sync-verification&paged=' . intval($total_pages) . '">»</a>';
                }
                
                echo '</span>';
            }
            ?>
        </div>
    </div>
</div>

<style>
    table.widefat th {
        background: #f5f5f5;
        font-weight: bold;
        padding: 12px;
        text-align: right;
    }
    table.widefat td {
        padding: 10px 12px;
    }
    table.widefat tr:hover {
        background: #f9f9f9;
    }
    .pagination-links a.button {
        margin: 0 2px;
        padding: 5px 10px;
    }
</style>
