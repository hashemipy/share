<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap inventory-sync-wrap">
    <h1><?php esc_html_e('Inventory Sync - هماهنگ‌سازی موجودی', 'inventory-sync'); ?></h1>
    
    <!-- Quick Help Notice -->
    <div class="inventory-sync-notice notice notice-info" style="margin-bottom: 20px;">
        <p>
            <strong><?php esc_html_e('راهنما:', 'inventory-sync'); ?></strong>
            <?php esc_html_e('علامت سوال‌های کنار هر فیلد را کلیک کنید برای دیدن توضیحات', 'inventory-sync'); ?>
        </p>
    </div>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="#settings" class="nav-tab nav-tab-active" data-tab="settings">
            <?php esc_html_e('⚙️ تنظیمات', 'inventory-sync'); ?>
        </a>
        <a href="#mapping" class="nav-tab" data-tab="mapping">
            <?php esc_html_e('🔗 مرتبط‌سازی محصولات', 'inventory-sync'); ?>
        </a>
        <a href="#transfer" class="nav-tab" data-tab="transfer">
            <?php esc_html_e('📤 انتقال محصولات', 'inventory-sync'); ?>
        </a>
        <a href="#transferred" class="nav-tab" data-tab="transferred">
            <?php esc_html_e('✅ محصولات منتقل‌شده', 'inventory-sync'); ?>
        </a>
        <a href="#logs" class="nav-tab" data-tab="logs">
            <?php esc_html_e('📋 لاگ‌ها', 'inventory-sync'); ?>
        </a>
    </nav>
    
    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Settings Tab -->
        <div id="settings" class="tab-pane active">
            <div class="settings-container">
                <h2><?php esc_html_e('تنظیمات سایت‌ها', 'inventory-sync'); ?></h2>
                
                <div class="settings-grid">
                    <!-- Site 1 -->
                    <div class="site-settings">
                        <h3><?php esc_html_e('سایت شماره 1', 'inventory-sync'); ?></h3>
                        
                        <div class="form-group">
                            <label for="site1_name">
                                <?php esc_html_e('نام سایت', 'inventory-sync'); ?>
                                <button type="button" class="help-btn" data-help-id="help-site1-name">?</button>
                            </label>
                            <input type="text" id="site1_name" class="form-control" 
                                   value="<?php echo esc_attr(Inventory_Sync_Settings::get_site1_name()); ?>"
                                   placeholder="مثال: فروشگاه اصلی">
                        </div>
                        
                        <div class="form-group">
                            <label for="site1_url">
                                <?php esc_html_e('آدرس سایت', 'inventory-sync'); ?>
                                <button type="button" class="help-btn" data-help-id="help-site1-url">?</button>
                            </label>
                            <input type="url" id="site1_url" class="form-control" 
                                   value="<?php echo esc_attr(Inventory_Sync_Settings::get_site1_url()); ?>"
                                   placeholder="https://example.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="site1_key">
                                <?php esc_html_e('API Key', 'inventory-sync'); ?>
                                <button type="button" class="help-btn" data-help-id="help-site1-key">?</button>
                            </label>
                            <input type="password" id="site1_key" class="form-control" 
                                   value="<?php echo esc_attr(Inventory_Sync_Settings::get_site1_key()); ?>"
                                   placeholder="Consumer Key">
                        </div>
                        
                        <div class="form-group">
                            <label for="site1_secret">
                                <?php esc_html_e('API Secret', 'inventory-sync'); ?>
                                <button type="button" class="help-btn" data-help-id="help-site1-secret">?</button>
                            </label>
                            <input type="password" id="site1_secret" class="form-control" 
                                   value="<?php echo esc_attr(Inventory_Sync_Settings::get_site1_secret()); ?>"
                                   placeholder="Consumer Secret">
                        </div>
                        
                        <button class="button button-secondary test-btn" data-site="site1">
                            <?php esc_html_e('🔗 تست اتصال', 'inventory-sync'); ?>
                        </button>
                    </div>
                    
                    <!-- Site 2 -->
                    <div class="site-settings">
                        <h3><?php esc_html_e('سایت شماره 2', 'inventory-sync'); ?></h3>
                        
                        <div class="form-group">
                            <label for="site2_name">
                                <?php esc_html_e('نام سایت', 'inventory-sync'); ?>
                                <button type="button" class="help-btn" data-help-id="help-site1-name">?</button>
                            </label>
                            <input type="text" id="site2_name" class="form-control" 
                                   value="<?php echo esc_attr(Inventory_Sync_Settings::get_site2_name()); ?>"
                                   placeholder="مثال: فروشگاه دوم">
                        </div>
                        
                        <div class="form-group">
                            <label for="site2_url">
                                <?php esc_html_e('آدرس سایت', 'inventory-sync'); ?>
                                <button type="button" class="help-btn" data-help-id="help-site1-url">?</button>
                            </label>
                            <input type="url" id="site2_url" class="form-control" 
                                   value="<?php echo esc_attr(Inventory_Sync_Settings::get_site2_url()); ?>"
                                   placeholder="https://example2.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="site2_key">
                                <?php esc_html_e('API Key', 'inventory-sync'); ?>
                                <button type="button" class="help-btn" data-help-id="help-site1-key">?</button>
                            </label>
                            <input type="password" id="site2_key" class="form-control" 
                                   value="<?php echo esc_attr(Inventory_Sync_Settings::get_site2_key()); ?>"
                                   placeholder="Consumer Key">
                        </div>
                        
                        <div class="form-group">
                            <label for="site2_secret">
                                <?php esc_html_e('API Secret', 'inventory-sync'); ?>
                                <button type="button" class="help-btn" data-help-id="help-site1-secret">?</button>
                            </label>
                            <input type="password" id="site2_secret" class="form-control" 
                                   value="<?php echo esc_attr(Inventory_Sync_Settings::get_site2_secret()); ?>"
                                   placeholder="Consumer Secret">
                        </div>
                        
                        <button class="button button-secondary test-btn" data-site="site2">
                            <?php esc_html_e('🔗 تست اتصال', 'inventory-sync'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Advanced Settings -->
                <div class="advanced-settings">
                    <h3><?php esc_html_e('تنظیمات پیشرفته', 'inventory-sync'); ?></h3>
                    
                    <div class="form-group">
                        <label for="sync_direction">
                            <?php esc_html_e('جهت هماهنگ‌سازی', 'inventory-sync'); ?>
                            <button type="button" class="help-btn" data-help-id="help-sync-direction">?</button>
                        </label>
                        <select id="sync_direction" class="form-control">
                            <option value="site1_to_site2" <?php selected(Inventory_Sync_Settings::get_sync_direction(), 'site1_to_site2'); ?>>
                                <?php esc_html_e('سایت 1 ← سایت 2', 'inventory-sync'); ?>
                            </option>
                            <option value="site2_to_site1" <?php selected(Inventory_Sync_Settings::get_sync_direction(), 'site2_to_site1'); ?>>
                                <?php esc_html_e('سایت 2 ← سایت 1', 'inventory-sync'); ?>
                            </option>
                            <option value="bidirectional" <?php selected(Inventory_Sync_Settings::get_sync_direction(), 'bidirectional'); ?>>
                                <?php esc_html_e('دوطرفه', 'inventory-sync'); ?>
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="auto_sync_enabled" 
                                   <?php checked(Inventory_Sync_Settings::get_auto_sync_enabled()); ?>>
                            <?php esc_html_e('فعال‌سازی هماهنگ‌سازی خودکار', 'inventory-sync'); ?>
                        </label>
                        <button type="button" class="help-btn" data-help-id="help-auto-sync">?</button>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button class="button button-primary save-settings-btn">
                        <?php esc_html_e('💾 ذخیره تنظیمات', 'inventory-sync'); ?>
                    </button>
                    <span class="status-message"></span>
        </div>
    </div>
    
    <!-- Tools Tab -->
    <div id="tools" class="tab-pane">
        <h2><?php esc_html_e('ابزارها', 'inventory-sync'); ?></h2>
        <p class="description">
            <?php esc_html_e('ابزارهایی برای مدیریت سیستم', 'inventory-sync'); ?>
        </p>
        
        <div style="background: #fff; padding: 20px; border-radius: 4px; border: 1px solid #ddd; margin-top: 20px;">
            <h3><?php esc_html_e('🗑 پاک کردن اطلاعات', 'inventory-sync'); ?></h3>
            <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
                <?php esc_html_e('کش و لاگ‌های سیستم را پاک کنید. این عملیات قابل برگشت نیست!', 'inventory-sync'); ?>
            </p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <h4><?php esc_html_e('پاک کردن لاگ‌ها', 'inventory-sync'); ?></h4>
                    <p style="font-size: 12px; color: #999;">
                        <?php esc_html_e('تمام پیغام‌های خطا و موفقیت را حذف کن', 'inventory-sync'); ?>
                    </p>
                    <button class="button button-primary clear-logs-btn" style="background: #dc3545;">
                        <?php esc_html_e('🗑 پاک کردن لاگ‌ها', 'inventory-sync'); ?>
                    </button>
                </div>
                
                <div>
                    <h4><?php esc_html_e('پاک کردن کش', 'inventory-sync'); ?></h4>
                    <p style="font-size: 12px; color: #999;">
                        <?php esc_html_e('کش درخواست‌های API را حذف کن', 'inventory-sync'); ?>
                    </p>
                    <button class="button button-primary clear-cache-btn" style="background: #6c757d;">
                        <?php esc_html_e('🗑 پاک کردن کش', 'inventory-sync'); ?>
                    </button>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 4px; border-left: 4px solid #ffc107;">
                <strong><?php esc_html_e('⚠️ اخطار:', 'inventory-sync'); ?></strong>
                <p style="margin: 5px 0 0 0; font-size: 12px;">
                    <?php esc_html_e('این عملیات‌ها قابل برگشت نیستند. قبل از اجرا مطمئن شوید.', 'inventory-sync'); ?>
                </p>
            </div>
        </div>
        
        <div style="background: #fff; padding: 20px; border-radius: 4px; border: 1px solid #ddd; margin-top: 20px;">
            <h3><?php esc_html_e('🔄 بازنشانی Cron', 'inventory-sync'); ?></h3>
            <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
                <?php esc_html_e('اگر هماهنگ‌سازی خودکار کار نکند، Cron را دوباره ثبت کنید', 'inventory-sync'); ?>
            </p>
            
            <button class="button button-secondary reset-cron-btn">
                <?php esc_html_e('🔄 بازنشانی Cron', 'inventory-sync'); ?>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('help-modal');
    const modalBody = modal.querySelector('.modal-body');
    const closeBtn = modal.querySelector('.modal-close');
    
    // Open modal on help button click
    document.querySelectorAll('.help-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const helpText = this.getAttribute('title');
            const helpId = this.getAttribute('data-help-id');
            
            if (helpId) {
                const helpContent = document.getElementById(helpId);
                if (helpContent) {
                    modalBody.innerHTML = helpContent.innerHTML;
                }
            } else {
                modalBody.innerHTML = '<p>' + helpText + '</p>';
            }
            
            modal.style.display = 'flex';
        });
    });
    
    // Close modal
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Tools handlers
    const nonce = document.querySelector('input[name="_wpnonce"]')?.value || inventorySyncData.nonce;
    
    // پاک کردن لاگ‌ها
    document.querySelector('.clear-logs-btn').addEventListener('click', function(e) {
        e.preventDefault();
        if (!confirm('آیا مطمئن هستید؟ این عملیات قابل برگشت نیست!')) {
            return;
        }
        
        jQuery.ajax({
            url: inventorySyncData.ajaxurl,
            type: 'POST',
            data: {
                action: 'inventory_sync_clear_logs',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('لاگ‌ها پاک شدند!');
                } else {
                    alert('خطا: ' + response.data);
                }
            },
            error: function() {
                alert('خطا در حذف لاگ‌ها');
            }
        });
    });
    
    // پاک کردن کش
    document.querySelector('.clear-cache-btn').addEventListener('click', function(e) {
        e.preventDefault();
        if (!confirm('آیا مطمئن هستید؟ این عملیات قابل برگشت نیست!')) {
            return;
        }
        
        jQuery.ajax({
            url: inventorySyncData.ajaxurl,
            type: 'POST',
            data: {
                action: 'inventory_sync_clear_cache',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('کش پاک شد!');
                } else {
                    alert('خطا: ' + response.data);
                }
            },
            error: function() {
                alert('خطا در حذف کش');
            }
        });
    });
    
    // بازنشانی Cron
    document.querySelector('.reset-cron-btn').addEventListener('click', function(e) {
        e.preventDefault();
        
        jQuery.ajax({
            url: inventorySyncData.ajaxurl,
            type: 'POST',
            data: {
                action: 'inventory_sync_reset_cron',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Cron بازنشانی شد!');
                } else {
                    alert('خطا: ' + response.data);
                }
            },
            error: function() {
                alert('خطا در بازنشانی Cron');
            }
        });
    });
});
</script>
