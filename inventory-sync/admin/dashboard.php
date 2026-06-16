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
                
                <!-- Site Role Selection -->
                <div class="site-role-selector" style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2271b1;">
                    <h3><?php esc_html_e('🎯 نقش این سایت', 'inventory-sync'); ?></h3>
                    <p style="margin-bottom: 15px; color: #555;">
                        <?php esc_html_e('مشخص کنید که این سایت نقش «سایت 1» (مبدا) دارد یا «سایت 2» (مقصد):', 'inventory-sync'); ?>
                    </p>
                    <div class="form-group" style="display: flex; gap: 30px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="radio" id="current_site_role_site1" name="current_site_role" value="site1" 
                                   <?php checked(Inventory_Sync_Settings::get_current_site_role(), 'site1'); ?>>
                            <span style="font-weight: bold; color: #d63638;">
                                <?php esc_html_e('📤 سایت 1 (مبدا - منتقل‌کننده)', 'inventory-sync'); ?>
                            </span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="radio" id="current_site_role_site2" name="current_site_role" value="site2" 
                                   <?php checked(Inventory_Sync_Settings::get_current_site_role(), 'site2'); ?>>
                            <span style="font-weight: bold; color: #00a32a;">
                                <?php esc_html_e('📥 سایت 2 (مقصد - دریافت‌کننده)', 'inventory-sync'); ?>
                            </span>
                        </label>
                    </div>
                    <p style="margin-top: 10px; font-size: 12px; color: #666;">
                        <?php esc_html_e('⚠️ تنها سایت 1 دارای دسترسی به مرتبط‌سازی و هماهنگ‌سازی است', 'inventory-sync'); ?>
                    </p>
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
        <div id="mapping" class="tab-pane">
            <h2><?php esc_html_e('مرتبط‌سازی محصولات', 'inventory-sync'); ?></h2>
            <p class="description">
                <?php esc_html_e('محصولات سایت 1 و 2 را انتخاب کنید و با هم مرتبط کنید تا موجودی‌های آن‌ها هماهنگ‌سازی شوند', 'inventory-sync'); ?>
            </p>
            
            <div class="mapping-container" style="display: flex; gap: 20px;">
                <!-- Site 1 Products Column -->
                <div class="mapping-column site1-column" style="flex: 1; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h3><?php esc_html_e('📤 سایت 1 - محصولات (انتخاب کنید)', 'inventory-sync'); ?></h3>
                    <input type="text" id="search-site1" class="form-control" placeholder="<?php esc_html_e('جستجو در محصولات سایت 1...', 'inventory-sync'); ?>" style="margin-bottom: 10px;">
                    <div class="products-list site1-products" style="max-height: 500px; overflow-y: auto; border: 1px solid #eee; border-radius: 3px; padding: 10px;">
                        <p><?php esc_html_e('درحال بارگذاری محصولات...', 'inventory-sync'); ?></p>
                    </div>
                </div>
                
                <!-- Connector -->
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="writing-mode: vertical-rl; transform: rotate(180deg); font-weight: bold; color: #2271b1;">↔</div>
                </div>
                
                <!-- Site 2 Products Column -->
                <div class="mapping-column site2-column" style="flex: 1; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h3><?php esc_html_e('📥 سایت 2 - محصولات (انتخاب کنید)', 'inventory-sync'); ?></h3>
                    <input type="text" id="search-site2" class="form-control" placeholder="<?php esc_html_e('جستجو در محصولات سایت 2...', 'inventory-sync'); ?>" style="margin-bottom: 10px;">
                    <div class="products-list site2-products" style="max-height: 500px; overflow-y: auto; border: 1px solid #eee; border-radius: 3px; padding: 10px;">
                        <p><?php esc_html_e('درحال بارگذاری محصولات...', 'inventory-sync'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Mapping Controls -->
            <div class="mapping-actions" style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
                <div id="selected-mapping" style="margin-bottom: 15px; display: none;">
                    <p style="margin: 0 0 10px 0;">
                        <strong><?php esc_html_e('انتخاب شده:', 'inventory-sync'); ?></strong>
                    </p>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div style="flex: 1;">
                            <strong id="selected-site1-name">-</strong><br>
                            <small id="selected-site1-sku">SKU: -</small><br>
                            <small id="selected-site1-stock">موجودی: -</small>
                        </div>
                        <span style="font-size: 24px; color: #2271b1;">⟷</span>
                        <div style="flex: 1;">
                            <strong id="selected-site2-name">-</strong><br>
                            <small id="selected-site2-sku">SKU: -</small><br>
                            <small id="selected-site2-stock">موجودی: -</small>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="button button-primary" id="create-mapping-btn" disabled>
                        <?php esc_html_e('🔗 ایجاد ارتباط', 'inventory-sync'); ?>
                    </button>
                    <button class="button button-secondary" id="clear-selection-btn">
                        <?php esc_html_e('❌ حذف انتخاب', 'inventory-sync'); ?>
                    </button>
                    <button class="button button-success" id="manual-sync-btn" style="margin-left: auto;">
                        <?php esc_html_e('⚡ هماهنگ‌سازی همه مرتبط‌سازی‌ها', 'inventory-sync'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Existing Mappings List -->
            <div style="margin-top: 30px;">
                <h3><?php esc_html_e('📋 محصولات مرتبط شده', 'inventory-sync'); ?></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('محصول سایت 1', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('SKU', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('محصول سایت 2', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('SKU', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('وضعیت', 'inventory-sync'); ?></th>
                            <th><?php esc_html_e('عملیات', 'inventory-sync'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="existing-mappings">
                        <tr>
                            <td colspan="6" class="text-center">
                                <?php esc_html_e('درحال بارگذاری...', 'inventory-sync'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
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
