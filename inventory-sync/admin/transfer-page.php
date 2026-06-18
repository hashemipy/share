<?php

// فقط در Admin WordPress فعال باشد
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_woocommerce')) {
    wp_die(__('دسترسی رد شد', 'inventory-sync'));
}

$transfer_manager = new Inventory_Sync_Transfer_Manager();
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 50;

$products = $transfer_manager->get_simple_products_not_transferred($per_page, $page);

?>
<div class="wrap">
    <h1>✅ انتقال محصولات (Simple Products)</h1>
    
    <p style="color: #666; margin: 20px 0;">
        <strong>نوع:</strong> فقط محصولات ساده می‌توانند منتقل شوند<br>
        <strong>وضعیت:</strong> محصولات منتقل‌نشده از سایت 1 به سایت 2<br>
        <strong>نتیجه:</strong> بعد از انتقال، mapping خودکار ایجاد می‌شود و موجودی هماهنگ می‌گردد
    </p>
    
    <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 20px 0; border-radius: 3px;">
        <p><strong>💡 نکات مهم:</strong></p>
        <ul style="margin: 0; padding-left: 20px;">
            <li>✅ فقط محصولات ساده پشتیبانی می‌شوند</li>
            <li>✅ اگر محصول با همین SKU در سایت 2 موجود باشد، فقط Mapping ایجاد می‌شود</li>
            <li>✅ موجودی از استراتژی Last-Write-Wins پیروی می‌کند</li>
            <li>✅ بعد از انتقال، دو سایت خودکار هماهنگ می‌شوند</li>
        </ul>
    </div>
    
    <?php if (is_wp_error($products)): ?>
        <div class="notice notice-error" style="margin: 20px 0;">
            <p><strong>خطا:</strong> <?php echo esc_html($products->get_error_message()); ?></p>
        </div>
    <?php elseif (empty($products)): ?>
        <div class="notice notice-info" style="margin: 20px 0;">
            <p>تمام محصولات ساده منتقل شده‌اند! 🎉</p>
        </div>
    <?php else: ?>
        <form id="transfer-products-form" method="post" style="margin: 20px 0;">
            <?php wp_nonce_field('transfer_products_nonce', '_wpnonce'); ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;"><input type="checkbox" id="select-all-products"></th>
                        <th>نام محصول</th>
                        <th>SKU</th>
                        <th>قیمت</th>
                        <th>موجودی</th>
                        <th>نوع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="product-checkbox" value="<?php echo esc_attr($product['id']); ?>">
                            </td>
                            <td>
                                <strong><?php echo esc_html($product['name']); ?></strong>
                                <br>
                                <small style="color: #666;">شناسه: <?php echo esc_html($product['id']); ?></small>
                            </td>
                            <td><?php echo esc_html($product['sku'] ?: 'بدون SKU'); ?></td>
                            <td><?php echo esc_html($product['price'] ?? '0'); ?> تومان</td>
                            <td>
                                <span style="<?php echo ($product['stock_quantity'] > 0) ? 'color: green;' : 'color: red;'; ?>">
                                    <?php echo esc_html($product['stock_quantity'] ?? 0); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($product['type'] ?? 'simple'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin: 20px 0;">
                <button type="submit" class="button button-primary" id="transfer-button">
                    📤 انتقال محصولات انتخاب‌شده
                </button>
                <button type="button" class="button" id="cancel-transfer">لغو</button>
            </div>
        </form>
        
        <div id="transfer-status" style="display: none; margin: 20px 0;">
            <div class="notice notice-info" style="margin: 0;">
                <p id="transfer-message"></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    #transfer-products-form table td {
        padding: 12px;
        vertical-align: middle;
    }
    
    .notice {
        border-radius: 3px;
    }
    
    #transfer-status {
        padding: 15px;
        background: #f5f5f5;
        border-radius: 3px;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-products');
    const productCheckboxes = document.querySelectorAll('.product-checkbox');
    const transferButton = document.getElementById('transfer-button');
    const cancelButton = document.getElementById('cancel-transfer');
    const statusDiv = document.getElementById('transfer-status');
    const statusMessage = document.getElementById('transfer-message');
    
    // Select/Deselect All
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            productCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Cancel
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            productCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            if (selectAllCheckbox) selectAllCheckbox.checked = false;
        });
    }
    
    // Transfer
    if (transferButton) {
        transferButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const selected = Array.from(productCheckboxes)
                .filter(checkbox => checkbox.checked)
                .map(checkbox => checkbox.value);
            
            if (selected.length === 0) {
                alert('لطفاً حداقل یک محصول انتخاب کنید');
                return;
            }
            
            const form = document.getElementById('transfer-products-form');
            const nonce = form._wpnonce.value;
            
            transferButton.disabled = true;
            transferButton.textContent = '💫 در حال انتقال...';
            statusDiv.style.display = 'block';
            statusMessage.innerHTML = 'در حال انتقال ' + selected.length + ' محصول...';
            
            const formData = new FormData();
            formData.append('action', 'inventory_sync_transfer_simple_products');
            formData.append('_wpnonce', nonce);
            selected.forEach(id => {
                formData.append('product-checkbox[]', id);
            });
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                transferButton.disabled = false;
                transferButton.textContent = '📤 انتقال محصولات انتخاب‌شده';
                
                if (data.success) {
                    statusMessage.innerHTML = '<strong style="color: green;">✅ انتقال موفق!</strong>';
                    
                    // بارگذاری مجدد صفحه بعد از 2 ثانیه
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    statusMessage.innerHTML = '<strong style="color: red;">❌ خطا: ' + (data.data || 'خطای نامعلوم') + '</strong>';
                }
            })
            .catch(error => {
                transferButton.disabled = false;
                transferButton.textContent = '📤 انتقال محصولات انتخاب‌شده';
                statusMessage.innerHTML = '<strong style="color: red;">❌ خطای شبکه: ' + error.message + '</strong>';
            });
        });
    }
});
</script>
