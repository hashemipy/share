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
        <!-- تب مرتبط‌سازی فقط برای سایت 1 -->
        <?php if (Inventory_Sync_Settings::is_site1()): ?>
        <a href="#mapping" class="nav-tab" data-tab="mapping">
            <?php esc_html_e('🔗 مرتبط‌سازی محصولات', 'inventory-sync'); ?>
        </a>
        <?php endif; ?>
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
                
                <!-- Site Role Selection -->
                <div class="site-role-selection" style="background: #f5f5f5; padding: 20px; margin-bottom: 20px; border-radius: 5px; border-left: 4px solid #0073aa;">
                    <h3 style="margin-top: 0; color: #0073aa;">
                        <strong><?php esc_html_e('🎯 تعیین نقش این سایت', 'inventory-sync'); ?></strong>
                    </h3>
                    <p style="margin: 10px 0; color: #666;">
                        <?php esc_html_e('این سایت کدام نقش را دارد؟ تنها سایت شماره 1 دارای قابلیت مرتبط‌سازی محصولات است.', 'inventory-sync'); ?>
                    </p>
                    <div class="form-group" style="display: flex; gap: 20px; margin-top: 15px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 15px; border: 2px solid #ddd; border-radius: 5px; <?php echo Inventory_Sync_Settings::is_site1() ? 'background: #e7f5ff; border-color: #0073aa;' : ''; ?>">
                            <input type="radio" name="current_site_role" value="is_site1" 
                                   <?php checked(Inventory_Sync_Settings::is_site1()); ?> style="cursor: pointer;">
                            <span><?php esc_html_e('سایت شماره 1 (اصلی)', 'inventory-sync'); ?></span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 15px; border: 2px solid #ddd; border-radius: 5px; <?php echo Inventory_Sync_Settings::is_site2() ? 'background: #fff3cd; border-color: #856404;' : ''; ?>">
                            <input type="radio" name="current_site_role" value="is_site2" 
                                   <?php checked(Inventory_Sync_Settings::is_site2()); ?> style="cursor: pointer;">
                            <span><?php esc_html_e('سایت شماره 2 (ثانویه)', 'inventory-sync'); ?></span>
                        </label>
                    </div>
                </div>
                
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
        
        <!-- Mapping Tab -->
        <?php if (Inventory_Sync_Settings::is_site1()): ?>
        <div id="mapping" class="tab-pane">
            <h2><?php esc_html_e('مرتبط‌سازی محصولات', 'inventory-sync'); ?></h2>
            <p class="description">
                <?php esc_html_e('محصولات سایت 1 و 2 را انتخاب کنید و آن‌ها را با هم مرتبط کنید. پس از مرتبط‌سازی، موجودی خودکار هماهنگ می‌شود.', 'inventory-sync'); ?>
            </p>
            
            <div class="inventory-sync-notice notice notice-warning">
                <p>
                    <strong><?php esc_html_e('💡 نکته مهم:', 'inventory-sync'); ?></strong>
                    <?php esc_html_e('محصولات نقشه‌برداری‌شده در زمینه سبز نمایش داده می‌شوند. تنها نقشه‌برداری جدید یا تغییرات را میتوانید انجام دهید.', 'inventory-sync'); ?>
                </p>
            </div>
            
            <div class="mapping-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Site 1 Products -->
                <div class="mapping-column" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h3 style="margin: 0;"><?php esc_html_e('سایت 1 - محصولات', 'inventory-sync'); ?></h3>
                        <span class="count-badge" style="background: #0073aa; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px;">0</span>
                    </div>
                    <input type="text" class="form-control site1-search" placeholder="<?php esc_html_e('جستجو محصول...', 'inventory-sync'); ?>" 
                           style="margin-bottom: 15px; padding: 8px 12px;">
                    <div class="products-list site1-products" style="height: 500px; overflow-y: auto; border: 1px solid #eee; border-radius: 3px;">
                        <p style="text-align: center; padding: 20px; color: #999;"><?php esc_html_e('درحال بارگذاری...', 'inventory-sync'); ?></p>
                    </div>
                </div>
                
                <!-- Site 2 Products -->
                <div class="mapping-column" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h3 style="margin: 0;"><?php esc_html_e('سایت 2 - محصولات', 'inventory-sync'); ?></h3>
                        <span class="count-badge" style="background: #856404; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px;">0</span>
                    </div>
                    <input type="text" class="form-control site2-search" placeholder="<?php esc_html_e('جستجو محصول...', 'inventory-sync'); ?>" 
                           style="margin-bottom: 15px; padding: 8px 12px;">
                    <div class="products-list site2-products" style="height: 500px; overflow-y: auto; border: 1px solid #eee; border-radius: 3px;">
                        <p style="text-align: center; padding: 20px; color: #999;"><?php esc_html_e('درحال بارگذاری...', 'inventory-sync'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Selected Products Display -->
            <div class="selected-products-display" style="margin-top: 30px; display: none; background: #f5f5f5; padding: 15px; border-radius: 5px;">
                <h3><?php esc_html_e('جفت انتخاب شده:', 'inventory-sync'); ?></h3>
                <div class="selected-pair" style="display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-top: 10px;">
                    <div class="site1-selected" style="flex: 1;">
                        <strong><?php esc_html_e('سایت 1:', 'inventory-sync'); ?></strong>
                        <p class="selected-name" style="margin: 5px 0; padding: 8px; background: white; border-radius: 3px;"></p>
                    </div>
                    <div style="text-align: center; font-size: 20px;">↔</div>
                    <div class="site2-selected" style="flex: 1;">
                        <strong><?php esc_html_e('سایت 2:', 'inventory-sync'); ?></strong>
                        <p class="selected-name" style="margin: 5px 0; padding: 8px; background: white; border-radius: 3px;"></p>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button class="button button-primary connect-products-btn">
                        <?php esc_html_e('🔗 ایجاد ارتباط', 'inventory-sync'); ?>
                    </button>
                    <button class="button button-secondary clear-selection-btn">
                        <?php esc_html_e('❌ لغو انتخاب', 'inventory-sync'); ?>
                    </button>
                </div>
            </div>
            
            <div class="mapping-actions" style="margin-top: 20px;">
                <button class="button button-primary sync-all-btn">
                    <?php esc_html_e('⚡ هماهنگ‌سازی همه موجودی‌ها', 'inventory-sync'); ?>
                </button>
            </div>
        </div>
        <?php endif; ?>
        
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
            </div>
        </div>
        
        <!-- Transferred Products Tab -->
        <div id="transferred" class="tab-pane">
            <h2><?php esc_html_e('محصولات منتقل‌شده', 'inventory-sync'); ?></h2>
            <p class="description">
                <?php esc_html_e('محصولاتی که با موفقیت از سایت 1 به سایت 2 منتقل شده‌اند، با علامت ✅ مشخص می‌شوند', 'inventory-sync'); ?>
            </p>
            
            <div class="transferred-container">
                <div class="transferred-filters">
                    <input type="text" id="transferred-search" class="form-control" 
                           placeholder="<?php esc_html_e('جستجو نام محصول...', 'inventory-sync'); ?>" 
                           style="max-width: 300px;">
                </div>
                
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('وضعیت', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('نام محصول', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('شناسه سایت 1', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('شناسه سایت 2', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('دسته‌بندی', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('ویژگی‌ها', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('تاریخ انتقال', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('عملیات', 'inventory-sync'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="transferred-list">
                        <tr>
                            <td colspan="8" class="text-center">
                                <?php esc_html_e('درحال بارگذاری...', 'inventory-sync'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Logs Tab -->
        <div id="logs" class="tab-pane">
            <h2><?php esc_html_e('لاگ‌های هماهنگ‌سازی', 'inventory-sync'); ?></h2>
            
            <div class="logs-container">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('تاریخ', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('محصول', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('عملیات', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('از سایت', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('به سایت', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('وضعیت', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('پیام', 'inventory-sync'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="logs-list">
                        <tr>
                            <td colspan="7" class="text-center">
                                <?php esc_html_e('درحال بارگذاری لاگ‌ها...', 'inventory-sync'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Help Dialogs -->
<div id="help-modal" class="inventory-sync-modal" style="display: none;">
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        <div class="modal-body"></div>
    </div>
</div>

<!-- HELP CONTENT: Hidden content for modals -->
<div class="help-content" style="display: none;">
    
    <!-- Settings Help -->
    <div id="help-site1-name" class="help-item">
        <h3>نام سایت شماره 1</h3>
        <p>یک نام شناسایی برای سایت اول انتخاب کنید تا بتوانید آن را شناخت کنید.</p>
        <p><strong>مثال:</strong> "فروشگاه اصلی" یا "انبار اصلی"</p>
    </div>
    
    <div id="help-site1-url" class="help-item">
        <h3>آدرس سایت شماره 1</h3>
        <p>آدرس کامل و دقیق سایت WooCommerce را وارد کنید.</p>
        <p><strong>مثال:</strong> https://example.com</p>
        <p><strong>نکته:</strong> حتما https:// یا http:// را بگنجانید</p>
    </div>
    
    <div id="help-site1-key" class="help-item">
        <h3>API Key سایت شماره 1</h3>
        <p><strong>چطور به دست بیاورم؟</strong></p>
        <ol>
            <li>وارد داشبورد WooCommerce سایت 1 شوید</li>
            <li>برو به: WooCommerce > Settings</li>
            <li>تب Advanced را کلیک کنید</li>
            <li>API کلیک کنید</li>
            <li>Create an API Key کلیک کنید</li>
            <li>"Consumer Key" را کپی کنید و اینجا بگذارید</li>
        </ol>
        <p><strong>⚠️ هشدار:</strong> این کلید را با کسی به اشتراک نگذارید!</p>
    </div>
    
    <div id="help-site1-secret" class="help-item">
        <h3>API Secret سایت شماره 1</h3>
        <p><strong>چطور به دست بیاورم؟</strong></p>
        <ol>
            <li>همان مراحل بالا را دنبال کنید</li>
            <li>"Consumer Secret" را کپی کنید</li>
            <li>آن را اینجا بگذارید</li>
        </ol>
        <p><strong>💡 نکته:</strong> API Key و Secret باید با هم معتبر باشند</p>
    </div>
    
    <div id="help-sync-direction" class="help-item">
        <h3>جهت هماهنگ‌سازی</h3>
        <p>کدام سایت "مرجع" است؟</p>
        <ul>
            <li><strong>سایت 1 ← سایت 2:</strong> موجودی سایت 1 مرجع است، موجودی سایت 2 به روز می‌شود</li>
            <li><strong>سایت 2 ← سایت 1:</strong> موجودی سایت 2 مرجع است، موجودی سایت 1 به روز می‌شود</li>
            <li><strong>دوطرفه:</strong> موجودی کم‌تر از دو سایت برای هر دو اعمال می‌شود</li>
        </ul>
        <p><strong>مثال:</strong> اگر سایت 1 مرجع است و 10 عدد محصول دارد، سایت 2 هم 10 عدد می‌شود</p>
    </div>
    
    <div id="help-auto-sync" class="help-item">
        <h3>هماهنگ‌سازی خودکار</h3>
        <p>اگر این گزینه <strong>فعال</strong> باشد:</p>
        <ul>
            <li>هر زمان که سفارش ثبت شود، موجودی خودکار به‌روز می‌شود</li>
            <li>سیستم هر 5 دقیقه موجودی‌ها را چک می‌کند</li>
            <li>اگر نقصی پیدا شود، دوباره تلاش می‌کند</li>
        </ul>
        <p><strong>💡 توصیه:</strong> برای سفارش‌های زیاد، این گزینه را فعال کنید</p>
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
