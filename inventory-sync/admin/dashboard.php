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

        <a href="#transfer" class="nav-tab" data-tab="transfer">
            <?php esc_html_e('📤 انتقال محصولات', 'inventory-sync'); ?>
        </a>
        <a href="#transferred" class="nav-tab" data-tab="transferred">
            <?php esc_html_e('✅ محصولات منتقل‌شده', 'inventory-sync'); ?>
        </a>
        <a href="#logs" class="nav-tab" data-tab="logs">
            <?php esc_html_e('📋 لاگ‌ها', 'inventory-sync'); ?>
        </a>
        <a href="#product-linking" class="nav-tab" data-tab="product-linking">
            <?php esc_html_e('🔗 مرتبط‌سازی محصولات', 'inventory-sync'); ?>
        </a>
        <a href="#linked-products" class="nav-tab" data-tab="linked-products">
            <?php esc_html_e('📦 محصولات مرتبط‌شده', 'inventory-sync'); ?>
        </a>
        <a href="#inventory-logs" class="nav-tab" data-tab="inventory-logs">
            <?php esc_html_e('📊 لاگ‌های موجودی', 'inventory-sync'); ?>
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
        </div>
        

        
        <!-- Transfer Tab -->
        <div id="transfer" class="tab-pane">
            <h2><?php esc_html_e('انتقال محصولات', 'inventory-sync'); ?></h2>
            <p class="description">
                <?php esc_html_e('محصولات را از سایت 1 به سایت 2 منتقل کنید', 'inventory-sync'); ?>
            </p>
            
            <div class="transfer-container">
                <div class="transfer-controls">
                    <label>
                        <input type="checkbox" id="select-all-transfer">
                        <?php esc_html_e('انتخاب همه', 'inventory-sync'); ?>
                    </label>
                </div>
                
                <div class="products-table">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="select-product"></th>
                                <th><?php esc_html_e('نام محصول', 'inventory-sync'); ?></th>
                                <th><?php esc_html_e('SKU', 'inventory-sync'); ?></th>
                                <th><?php esc_html_e('موجودی', 'inventory-sync'); ?></th>
                                <th><?php esc_html_e('وضعیت', 'inventory-sync'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="transfer-products">
                            <tr>
                                <td colspan="5" class="text-center">
                                    <?php esc_html_e('درحال بارگذاری محصولات...', 'inventory-sync'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="transfer-actions">
                    <button class="button button-primary transfer-selected-btn">
                        <?php esc_html_e('📤 انتقال محصولات انتخاب شده', 'inventory-sync'); ?>
                    </button>
                    <button class="button button-secondary transfer-all-btn">
                        <?php esc_html_e('📤 انتقال همه محصولات', 'inventory-sync'); ?>
                    </button>
                </div>
                
                <div class="progress-container" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <p class="progress-text"></p>
        </div>
        
        <!-- Product Linking Tab -->
        <div id="product-linking" class="tab-pane">
            <h2><?php esc_html_e('مرتبط‌سازی محصولات', 'inventory-sync'); ?></h2>
            <p class="description">
                <?php esc_html_e('دو محصول را برای هماهنگ‌سازی موجودی خودکار انتخاب کنید', 'inventory-sync'); ?>
            </p>
            
            <div class="product-linking-container">
                <div class="linking-column">
                    <h3><?php esc_html_e('سایت 1 - محصول', 'inventory-sync'); ?></h3>
                    <div class="search-group">
                        <input type="text" class="product-search" id="site1_product_search" 
                               placeholder="<?php esc_attr_e('جستجو محصول سایت 1...', 'inventory-sync'); ?>">
                        <div class="products-dropdown site1-products-list" style="display: none;"></div>
                    </div>
                    <div class="selected-product site1-selected"></div>
                </div>
                
                <div class="linking-column">
                    <h3><?php esc_html_e('سایت 2 - محصول', 'inventory-sync'); ?></h3>
                    <div class="search-group">
                        <input type="text" class="product-search" id="site2_product_search" 
                               placeholder="<?php esc_attr_e('جستجو محصول سایت 2...', 'inventory-sync'); ?>">
                        <div class="products-dropdown site2-products-list" style="display: none;"></div>
                    </div>
                    <div class="selected-product site2-selected"></div>
                </div>
            </div>
            
            <div class="linking-actions">
                <button class="button button-primary" id="create-mapping-btn" disabled>
                    <?php esc_html_e('🔗 ایجاد ارتباط', 'inventory-sync'); ?>
                </button>
            </div>
        </div>
        
        <!-- Linked Products Tab -->
        <div id="linked-products" class="tab-pane">
            <h2><?php esc_html_e('محصولات مرتبط‌شده', 'inventory-sync'); ?></h2>
            <p class="description">
                <?php esc_html_e('تمام ارتباطات موجود و مدیریت آن‌ها', 'inventory-sync'); ?>
            </p>
            
            <div class="linked-products-toolbar">
                <span class="queue-status">
                    <?php esc_html_e('وظایف در انتظار: ', 'inventory-sync'); ?><strong id="pending-count">0</strong>
                </span>
                <button class="button" id="refresh-mappings-btn">
                    <?php esc_html_e('🔄 بازخوانی', 'inventory-sync'); ?>
                </button>
            </div>
            
            <table class="wp-list-table widefat striped linked-products-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('محصول سایت 1', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('موجودی', 'inventory-sync'); ?></th>
                        <th style="text-align: center;">↔</th>
                        <th><?php esc_html_e('محصول سایت 2', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('موجودی', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('وضعیت', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('عملیات', 'inventory-sync'); ?></th>
                    </tr>
                </thead>
                <tbody id="linked-products-tbody">
                    <tr><td colspan="7" style="text-align: center;"><?php esc_html_e('بارگذاری...', 'inventory-sync'); ?></td></tr>
                </tbody>
            </table>
            
            <div class="pagination-wrapper">
                <span class="pagination-info" id="pagination-info"></span>
                <div class="pagination-buttons">
                    <button class="button" id="prev-page-btn" disabled><?php esc_html_e('← قبلی', 'inventory-sync'); ?></button>
                    <button class="button" id="next-page-btn"><?php esc_html_e('بعدی →', 'inventory-sync'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Inventory Logs Tab -->
        <div id="inventory-logs" class="tab-pane">
            <h2><?php esc_html_e('لاگ‌های موجودی', 'inventory-sync'); ?></h2>
            <p class="description">
                <?php esc_html_e('تغییرات موجودی و وضعیت هماهنگ‌سازی', 'inventory-sync'); ?>
            </p>
            
            <div class="logs-toolbar">
                <select id="logs-status-filter" class="form-control">
                    <option value=""><?php esc_html_e('تمام وضعیت‌ها', 'inventory-sync'); ?></option>
                    <option value="pending"><?php esc_html_e('در انتظار', 'inventory-sync'); ?></option>
                    <option value="success"><?php esc_html_e('موفق', 'inventory-sync'); ?></option>
                    <option value="failed"><?php esc_html_e('ناموفق', 'inventory-sync'); ?></option>
                </select>
                
                <select id="logs-site-filter" class="form-control">
                    <option value=""><?php esc_html_e('تمام سایت‌ها', 'inventory-sync'); ?></option>
                    <option value="1"><?php esc_html_e('سایت 1', 'inventory-sync'); ?></option>
                    <option value="2"><?php esc_html_e('سایت 2', 'inventory-sync'); ?></option>
                </select>
                
                <button class="button" id="refresh-logs-btn">
                    <?php esc_html_e('🔄 بازخوانی', 'inventory-sync'); ?>
                </button>
            </div>
            
            <table class="wp-list-table widefat striped logs-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('نام محصول', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('متغیر', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('سایت', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('موجودی قبل', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('موجودی بعد', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('وضعیت', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('زمان', 'inventory-sync'); ?></th>
                        <th><?php esc_html_e('عملیات', 'inventory-sync'); ?></th>
                    </tr>
                </thead>
                <tbody id="logs-tbody">
                    <tr><td colspan="8" style="text-align: center;"><?php esc_html_e('بارگذاری...', 'inventory-sync'); ?></td></tr>
                </tbody>
            </table>
            
            <div class="pagination-wrapper">
                <span class="pagination-info" id="logs-pagination-info"></span>
                <div class="pagination-buttons">
                    <button class="button" id="logs-prev-page-btn" disabled><?php esc_html_e('← قبلی', 'inventory-sync'); ?></button>
                    <button class="button" id="logs-next-page-btn"><?php esc_html_e('بعدی →', 'inventory-sync'); ?></button>
                </div>
            </div>
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
});
</script>
