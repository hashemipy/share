/**
 * Inventory Sync Admin JavaScript
 */

(function($) {
    'use strict';
    
    const app = {
        init: function() {
            this.bindEvents();
            this.loadInitialData();
        },
        
        bindEvents: function() {
            // Tab switching
            $(document).on('click', '.nav-tab', this.switchTab.bind(this));
            
            // Settings
            $(document).on('click', '.test-btn', this.testConnection.bind(this));
            $(document).on('click', '.save-settings-btn', this.saveSettings.bind(this));
            
            // Mapping
            $(document).on('click', '#add-mapping-btn', this.addMapping.bind(this));
            $(document).on('click', '#sync-all-mappings-btn', this.syncAllMappings.bind(this));
            $(document).on('click', '#refresh-mappings-btn', this.loadMappings.bind(this));
            $(document).on('click', '.sync-mapping-btn', this.syncMapping.bind(this));
            $(document).on('click', '.delete-mapping-btn', this.deleteMapping.bind(this));
            $(document).on('click', '.toggle-mapping-btn', this.toggleMapping.bind(this));
            $(document).on('change', '#site1-product-select', this.updateSite1ProductInfo.bind(this));
            $(document).on('change', '#site2-product-select', this.updateSite2ProductInfo.bind(this));
            
            // Transfer
            $(document).on('click', '#select-all-transfer', this.toggleSelectAll.bind(this));
            $(document).on('click', '.select-product', this.handleSelectProduct.bind(this));
            $(document).on('click', '.transfer-selected-btn', this.transferSelected.bind(this));
            $(document).on('click', '.transfer-all-btn', this.transferAll.bind(this));
        },
        
        loadInitialData: function() {
            this.loadMappings();
            this.loadProductSelects();
            this.loadTransferProducts();
            this.loadTransferredProducts();
            this.loadLogs();
        },
        
        // === Tab Management ===
        switchTab: function(e) {
            e.preventDefault();
            const $tab = $(e.target);
            const tabName = $tab.attr('data-tab');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Update active pane
            $('.tab-pane').removeClass('active');
            $('#' + tabName).addClass('active');
            
            // Load data if needed
            if (tabName === 'logs') {
                this.loadLogs();
            }
        },
        
        // === Settings Management ===
        testConnection: function(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const site = $btn.attr('data-site');
            const originalText = $btn.text();
            
            $btn.attr('disabled', true).text(inventorySyncData.i18n.loading);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_test_connection',
                    _ajax_nonce: inventorySyncData.nonce,
                    site: site
                },
                success: (response) => {
                    if (response.success) {
                        $btn.parent().find('.status-message')
                            .removeClass('error')
                            .addClass('success')
                            .text('✓ ' + response.data);
                    } else {
                        $btn.parent().find('.status-message')
                            .removeClass('success')
                            .addClass('error')
                            .text('✗ ' + response.data);
                    }
                },
                error: () => {
                    $btn.parent().find('.status-message')
                        .removeClass('success')
                        .addClass('error')
                        .text('✗ ' + inventorySyncData.i18n.error);
                },
                complete: () => {
                    $btn.attr('disabled', false).text(originalText);
                }
            });
        },
        
        saveSettings: function(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const originalText = $btn.text();
            
            $btn.attr('disabled', true).text(inventorySyncData.i18n.saving);
            
            const data = {
                action: 'inventory_sync_save_settings',
                _ajax_nonce: inventorySyncData.nonce,
                site1_name: $('#site1_name').val(),
                site1_url: $('#site1_url').val(),
                site1_key: $('#site1_key').val(),
                site1_secret: $('#site1_secret').val(),
                site2_name: $('#site2_name').val(),
                site2_url: $('#site2_url').val(),
                site2_key: $('#site2_key').val(),
                site2_secret: $('#site2_secret').val(),
                sync_direction: $('#sync_direction').val(),
                auto_sync_enabled: $('#auto_sync_enabled').is(':checked') ? 1 : 0
            };
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        $('.form-actions .status-message')
                            .removeClass('error')
                            .addClass('success')
                            .text('✓ ' + response.data);
                    }
                },
                error: () => {
                    $('.form-actions .status-message')
                        .removeClass('success')
                        .addClass('error')
                        .text('✗ ' + inventorySyncData.i18n.error);
                },
                complete: () => {
                    $btn.attr('disabled', false).text(originalText);
                }
            });
        },
        
        // === Product Management ===
        loadProducts: function(site) {
            const $container = site === 'site1' ? 
                $('.site1-products') : $('.site2-products');
            
            $container.html('<p>' + inventorySyncData.i18n.loading + '</p>');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_products',
                    _ajax_nonce: inventorySyncData.nonce,
                    site: site,
                    page: 1
                },
                success: (response) => {
                    if (response.success) {
                        this.renderProducts($container, response.data, site);
                    }
                },
                error: () => {
                    $container.html('<p class="alert alert-error">' + inventorySyncData.i18n.error + '</p>');
                }
            });
        },
        
        renderProducts: function($container, products, site) {
            if (!products || products.length === 0) {
                $container.html('<p>' + inventorySyncData.i18n.selectProducts + '</p>');
                return;
            }
            
            let html = '';
            products.forEach(product => {
                html += `
                    <div class="product-item" data-site="${site}" data-id="${product.id}">
                        <div class="product-name">${product.name}</div>
                        <div class="product-sku">SKU: ${product.sku || 'N/A'}</div>
                        <div class="product-stock">📦 موجودی: ${product.stock_quantity || 0}</div>
                    </div>
                `;
            });
            
            $container.html(html);
        },
        
        selectProduct: function(e) {
            $(e.target).closest('.product-item').toggleClass('selected');
        },
        
        // === Inventory Sync ===
        syncAllInventory: function(e) {
            e.preventDefault();
            
            if (!confirm('آیا می‌خواهید تمام موجودی‌ها را هماهنگ کنید؟')) {
                return;
            }
            
            const $btn = $(e.target);
            const originalText = $btn.text();
            
            $btn.attr('disabled', true).text(inventorySyncData.i18n.syncing);
            
            // Here you would fetch all mappings and sync them
            // For now, just show a message
            
            setTimeout(() => {
                alert('✓ هماهنگ‌سازی کامل شد!');
                $btn.attr('disabled', false).text(originalText);
            }, 1000);
        },
        
        // === Transfer Management ===
        loadTransferProducts: function() {
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_products',
                    _ajax_nonce: inventorySyncData.nonce,
                    site: 'site1',
                    page: 1
                },
                success: (response) => {
                    if (response.success) {
                        this.renderTransferTable(response.data);
                    }
                },
                error: () => {
                    $('.transfer-products').html(
                        '<tr><td colspan="5" class="text-center alert alert-error">' + 
                        inventorySyncData.i18n.error + '</td></tr>'
                    );
                }
            });
        },
        
        renderTransferTable: function(products) {
            if (!products || products.length === 0) {
                $('.transfer-products').html(
                    '<tr><td colspan="5" class="text-center">' + 
                    inventorySyncData.i18n.selectProducts + '</td></tr>'
                );
                return;
            }
            
            let html = '';
            products.forEach(product => {
                html += `
                    <tr>
                        <td><input type="checkbox" class="select-product" value="${product.id}"></td>
                        <td>${product.name}</td>
                        <td>${product.sku || 'N/A'}</td>
                        <td>${product.stock_quantity || 0}</td>
                        <td><span class="status-badge pending">📋 منتظر</span></td>
                    </tr>
                `;
            });
            
            $('.transfer-products').html(html);
        },
        
        toggleSelectAll: function(e) {
            const isChecked = $(e.target).is(':checked');
            $('.transfer-products input[type="checkbox"]').prop('checked', isChecked);
        },
        
        handleSelectProduct: function(e) {
            const totalCheckboxes = $('.transfer-products input[type="checkbox"]').length;
            const checkedCheckboxes = $('.transfer-products input[type="checkbox"]:checked').length;
            
            $('#select-all-transfer').prop('checked', totalCheckboxes === checkedCheckboxes);
        },
        
        transferSelected: function(e) {
            e.preventDefault();
            const productIds = [];
            
            $('.transfer-products input[type="checkbox"]:checked').each(function() {
                productIds.push($(this).val());
            });
            
            if (productIds.length === 0) {
                alert(inventorySyncData.i18n.selectProducts);
                return;
            }
            
            this.performTransfer(productIds);
        },
        
        transferAll: function(e) {
            e.preventDefault();
            const productIds = [];
            
            $('.transfer-products input[type="checkbox"]').each(function() {
                productIds.push($(this).val());
            });
            
            if (productIds.length === 0) {
                alert(inventorySyncData.i18n.selectProducts);
                return;
            }
            
            this.performTransfer(productIds);
        },
        
        performTransfer: function(productIds) {
            const $progress = $('.progress-container');
            $progress.show();
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_transfer_products',
                    _ajax_nonce: inventorySyncData.nonce,
                    product_ids: productIds
                },
                success: (response) => {
                    if (response.success) {
                        const results = response.data;
                        const successful = results.filter(r => r.success).length;
                        const failed = results.filter(r => !r.success).length;
                        
                        const message = `✓ انتقال کامل شد!\nموفق: ${successful}\nناموفق: ${failed}`;
                        alert(message);
                        
                        this.loadTransferProducts();
                    }
                },
                error: () => {
                    alert('✗ ' + inventorySyncData.i18n.error);
                },
                complete: () => {
                    $progress.hide();
                }
            });
        },
        
        // === Transferred Products Management ===
        loadTransferredProducts: function() {
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_transferred_products',
                    _ajax_nonce: inventorySyncData.nonce,
                    page: 1
                },
                success: (response) => {
                    if (response.success) {
                        this.renderTransferredProducts(response.data);
                    }
                },
                error: () => {
                    $('.transferred-list').html(
                        '<tr><td colspan="8" class="text-center alert alert-error">' + 
                        inventorySyncData.i18n.error + '</td></tr>'
                    );
                }
            });
        },
        
        renderTransferredProducts: function(products) {
            if (!products || products.length === 0) {
                $('.transferred-list').html(
                    '<tr><td colspan="8" class="text-center">📭 هیچ محصولی منتقل نشده است</td></tr>'
                );
                return;
            }
            
            let html = '';
            products.forEach(product => {
                const categoryStatus = product.categories_synced ? '✓' : '✗';
                const attributeStatus = product.attributes_synced ? '✓' : '✗';
                const transferDate = new Date(product.transferred_at).toLocaleString('fa-IR');
                const statusBadge = product.transfer_status === 'success' ? 
                    '<span class="status-badge success">✓ موفق</span>' : 
                    '<span class="status-badge error">✗ ناموفق</span>';
                
                html += `
                    <tr>
                        <td>${statusBadge}</td>
                        <td><strong>${product.product_name}</strong></td>
                        <td>${product.site1_product_id}</td>
                        <td>${product.site2_product_id}</td>
                        <td>${categoryStatus}</td>
                        <td>${attributeStatus}</td>
                        <td>${transferDate}</td>
                        <td>
                            <a href="javascript:void(0)" class="view-product" data-product-id="${product.site2_product_id}">
                                مشاهده
                            </a>
                        </td>
                    </tr>
                `;
            });
            
            $('.transferred-list').html(html);
        },
        
        // === Logs Management ===
        loadLogs: function() {
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_logs',
                    _ajax_nonce: inventorySyncData.nonce,
                    page: 1
                },
                success: (response) => {
                    if (response.success) {
                        this.renderLogs(response.data);
                    }
                },
                error: () => {
                    $('.logs-list').html(
                        '<tr><td colspan="7" class="text-center alert alert-error">' + 
                        inventorySyncData.i18n.error + '</td></tr>'
                    );
                }
            });
        },
        
        renderLogs: function(logs) {
            if (!logs || logs.length === 0) {
                $('.logs-list').html(
                    '<tr><td colspan="7" class="text-center">📭 هیچ لاگی موجود نیست</td></tr>'
                );
                return;
            }
            
            let html = '';
            logs.forEach(log => {
                const statusClass = log.status === 'success' ? 'success' : 
                                   (log.status === 'error' ? 'error' : 'pending');
                const statusText = log.status === 'success' ? '✓ موفق' : 
                                  (log.status === 'error' ? '✗ ناموفق' : '⏳ منتظر');
                
                html += `
                    <tr>
                        <td>${new Date(log.created_at).toLocaleString('fa-IR')}</td>
                        <td>${log.product_name || 'N/A'}</td>
                        <td>${log.action}</td>
                        <td>${log.source_site}</td>
                        <td>${log.target_site}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${log.error_message || log.new_value || '-'}</td>
                    </tr>
                `;
            });
            
            $('.logs-list').html(html);
        },
        
        // === Mapping Management ===
        loadProductSelects: function() {
            console.log("[v0] Starting to load products from both sites...");
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_all_products',
                    nonce: inventorySyncData.nonce
                },
                success: (response) => {
                    console.log("[v0] Products loaded:", response);
                    
                    if (response.success && response.data) {
                        const site1 = response.data.site1 || [];
                        const site2 = response.data.site2 || [];
                        
                        console.log("[v0] Site1 products count:", site1.length);
                        console.log("[v0] Site2 products count:", site2.length);
                        
                        // پر کردن دروپ‌داون‌ها
                        this.populateSelect('#site1-product-select', site1, 'سایت 1');
                        this.populateSelect('#site2-product-select', site2, 'سایت 2');
                        
                        // اگر سایت 2 مشکل داشته باشد
                        if (site2.length === 0) {
                            console.warn("[v0] Site2 products not available or error occurred");
                            const errorDiv = $('#site2-product-select').next('.mapping-error-msg');
                            if (errorDiv.length === 0) {
                                $('#site2-product-select').after(
                                    '<div class="mapping-error-msg" style="color: #d32f2f; font-size: 12px; margin-top: 5px;">⚠️ محصولات سایت 2 دریافت نشد. اتصال API را بررسی کنید.</div>'
                                );
                            }
                        } else {
                            // حذف پیام خطا اگر موجود باشد
                            $('#site2-product-select').next('.mapping-error-msg').remove();
                        }
                    } else {
                        console.error("[v0] API Response failed");
                        alert('خطا در دریافت محصولات');
                    }
                },
                error: (xhr, status, error) => {
                    console.error("[v0] AJAX Error:", error, xhr.responseText);
                    alert('خطا در ارتباط با سرور');
                }
            });
        },
        
        populateSelect: function(selector, products, siteName = '') {
            console.log(`[v0] Populating ${selector} with ${products.length} products`);
            
            const $select = $(selector);
            const defaultOption = '<option value="">-- انتخاب کنید --</option>';
            
            // ریست کردن گزینه‌های قدیمی
            $select.html(defaultOption);
            
            if (!products || products.length === 0) {
                console.warn(`[v0] No products found for ${siteName}`);
                return;
            }
            
            // افزودن محصولات
            products.forEach(product => {
                const skuText = product.sku ? `, SKU: ${product.sku}` : '';
                const optionText = `${product.name} (ID: ${product.id}${skuText})`;
                $select.append(`<option value="${product.id}" data-sku="${product.sku || ''}">${optionText}</option>`);
            });
        },
        
        updateSite1ProductInfo: function(e) {
            const $select = $(e.target);
            const option = $select.find('option:selected');
            const infoDiv = $('#site1-product-info');
            
            if (option.val()) {
                $('#site1-product-id').text(option.val());
                $('#site1-product-sku').text(option.data('sku') || '-');
                infoDiv.show();
            } else {
                infoDiv.hide();
            }
        },
        
        updateSite2ProductInfo: function(e) {
            const $select = $(e.target);
            const option = $select.find('option:selected');
            const infoDiv = $('#site2-product-info');
            
            if (option.val()) {
                $('#site2-product-id').text(option.val());
                $('#site2-product-sku').text(option.data('sku') || '-');
                infoDiv.show();
            } else {
                infoDiv.hide();
            }
        },
        
        addMapping: function(e) {
            e.preventDefault();
            
            const site1ProductId = $('#site1-product-select').val();
            const site2ProductId = $('#site2-product-select').val();
            const errorDiv = $('#mapping-form-errors');
            
            // خطاگیری
            if (!site1ProductId || !site2ProductId) {
                errorDiv.html('⚠️ لطفاً هر دو محصول را انتخاب کنید').show();
                return;
            }
            
            if (site1ProductId === site2ProductId) {
                errorDiv.html('⚠️ نمی‌توانید یک محصول را با خود متصل کنید').show();
                return;
            }
            
            errorDiv.hide();
            
            console.log(`[v0] Adding mapping: ${site1ProductId} <-> ${site2ProductId}`);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_add_mapping',
                    nonce: inventorySyncData.nonce,
                    site1_product_id: site1ProductId,
                    site2_product_id: site2ProductId
                },
                success: (response) => {
                    if (response.success) {
                        console.log("[v0] Mapping added successfully");
                        // پاک کردن فرم
                        $('#site1-product-select').val('');
                        $('#site2-product-select').val('');
                        $('#site1-product-info').hide();
                        $('#site2-product-info').hide();
                        
                        // بارگذاری دوباره لیست
                        this.loadMappings();
                        alert('✓ اتصال با موفقیت افزوده شد');
                    } else {
                        errorDiv.html('✗ خطا: ' + response.data).show();
                    }
                },
                error: (xhr, status, error) => {
                    console.error("[v0] Error adding mapping:", error);
                    errorDiv.html('✗ خطا در ارتباط با سرور').show();
                }
            });
        },
        
        loadMappings: function() {
            console.log("[v0] Loading mappings...");
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_mappings',
                    nonce: inventorySyncData.nonce
                },
                success: (response) => {
                    console.log("[v0] Mappings loaded:", response);
                    
                    if (response.success) {
                        this.renderMappings(response.data);
                    } else {
                        $('.mappings-list').html(
                            '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #d32f2f;">✗ خطا در دریافت اتصالات</td></tr>'
                        );
                    }
                },
                error: (xhr, status, error) => {
                    console.error("[v0] Error loading mappings:", error);
                    $('.mappings-list').html(
                        '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #d32f2f;">✗ خطا در ارتباط با سرور</td></tr>'
                    );
                }
            });
        },
        
        renderMappings: function(mappings) {
            console.log("[v0] Rendering mappings, count:", mappings ? mappings.length : 0);
            
            if (!mappings || mappings.length === 0) {
                $('.mappings-list').html(
                    '<tr><td colspan="7" style="text-align: center; padding: 30px; color: #999;">📭 هیچ اتصالی موجود نیست</td></tr>'
                );
                return;
            }
            
            let html = '';
            mappings.forEach((mapping, index) => {
                const statusIcon = mapping.is_active ? '✓ فعال' : '✗ غیر فعال';
                const statusColor = mapping.is_active ? '#4caf50' : '#999';
                const statusBg = mapping.is_active ? '#f1f8f4' : '#f5f5f5';
                
                const lastSyncDate = mapping.last_sync ? new Date(mapping.last_sync * 1000).toLocaleString('fa-IR') : '-';
                
                html += `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; text-align: center; color: ${statusColor}; font-weight: 600;">${statusIcon}</td>
                        <td style="padding: 12px;">
                            <strong>${mapping.site1_name || 'نامشخص'}</strong>
                            <br><small style="color: #999;">ID: ${mapping.site1_product_id} | SKU: ${mapping.site1_sku || '-'}</small>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="background: #e3f2fd; padding: 4px 8px; border-radius: 3px; font-size: 13px;">${mapping.site1_stock || 0}</span>
                        </td>
                        <td style="padding: 12px; text-align: center; color: #666; font-size: 16px;">↔</td>
                        <td style="padding: 12px;">
                            <strong>${mapping.site2_name || 'نامشخص'}</strong>
                            <br><small style="color: #999;">ID: ${mapping.site2_product_id} | SKU: ${mapping.site2_sku || '-'}</small>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="background: #f3e5f5; padding: 4px 8px; border-radius: 3px; font-size: 13px;">${mapping.site2_stock || 0}</span>
                        </td>
                        <td style="padding: 12px; text-align: center; white-space: nowrap;">
                            <button class="button sync-mapping-btn" data-id="${mapping.id}" style="padding: 4px 12px; font-size: 12px; margin: 2px;">⚡ هماهنگ</button>
                            <button class="button toggle-mapping-btn" data-id="${mapping.id}" style="padding: 4px 12px; font-size: 12px; margin: 2px;">${mapping.is_active ? '🔒 غیرفعال' : '🔓 فعال'}</button>
                            <button class="button delete-mapping-btn" data-id="${mapping.id}" style="padding: 4px 12px; font-size: 12px; margin: 2px; color: #d32f2f;">🗑️ حذف</button>
                        </td>
                    </tr>
                `;
            });
            
            $('.mappings-list').html(html);
        },
        
        syncAllMappings: function() {
            console.log("[v0] Starting sync all mappings...");
            
            if (!confirm('آیا می‌خواهید تمام اتصالات را هماهنگ کنید؟')) {
                return;
            }
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_sync_all_mappings',
                    nonce: inventorySyncData.nonce
                },
                success: (response) => {
                    console.log("[v0] Sync all completed:", response);
                    
                    if (response.success) {
                        alert('✓ تمام اتصالات با موفقیت هماهنگ شدند');
                        this.loadMappings();
                    } else {
                        alert('✗ خطا: ' + response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error("[v0] Error syncing all:", error);
                    alert('✗ خطا در ارتباط با سرور');
                }
            });
        },
        
        syncMapping: function(e) {
            e.preventDefault();
            const $btn = $(e.target).closest('button');
            const id = $btn.data('id');
            const originalText = $btn.text();
            
            console.log("[v0] Syncing mapping:", id);
            
            $btn.attr('disabled', true).text('⏳ درحال همزمان‌سازی...');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_sync_mapping',
                    nonce: inventorySyncData.nonce,
                    mapping_id: id
                },
                success: (response) => {
                    console.log("[v0] Sync mapping completed:", response);
                    
                    if (response.success) {
                        alert('✓ هماهنگ‌سازی موفق');
                        this.loadMappings();
                    } else {
                        alert('✗ خطا: ' + response.data);
                        $btn.attr('disabled', false).text(originalText);
                    }
                },
                error: (xhr, status, error) => {
                    console.error("[v0] Error syncing mapping:", error);
                    alert('✗ خطا در ارتباط با سرور');
                    $btn.attr('disabled', false).text(originalText);
                }
            });
        },
        
        toggleMapping: function(e) {
            e.preventDefault();
            const $btn = $(e.target).closest('button');
            const id = $btn.data('id');
            const originalText = $btn.text();
            
            console.log("[v0] Toggling mapping:", id);
            
            $btn.attr('disabled', true).text('⏳ درحال تغییر...');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_toggle_mapping',
                    nonce: inventorySyncData.nonce,
                    mapping_id: id
                },
                success: (response) => {
                    console.log("[v0] Toggle completed:", response);
                    
                    if (response.success) {
                        this.loadMappings();
                    } else {
                        alert('✗ خطا: ' + response.data);
                        $btn.attr('disabled', false).text(originalText);
                    }
                },
                error: (xhr, status, error) => {
                    console.error("[v0] Error toggling mapping:", error);
                    alert('✗ خطا در ارتباط با سرور');
                    $btn.attr('disabled', false).text(originalText);
                }
            });
        },
        
        deleteMapping: function(e) {
            e.preventDefault();
            const $btn = $(e.target).closest('button');
            const id = $btn.data('id');
            
            console.log("[v0] Deleting mapping:", id);
            
            if (!confirm('آیا از حذف این اتصال مطمئن هستید؟')) {
                return;
            }
            
            $btn.attr('disabled', true).text('⏳ درحال حذف...');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_delete_mapping',
                    nonce: inventorySyncData.nonce,
                    mapping_id: id
                },
                success: (response) => {
                    console.log("[v0] Delete completed:", response);
                    
                    if (response.success) {
                        alert('✓ اتصال با موفقیت حذف شد');
                        this.loadMappings();
                    } else {
                        alert('✗ خطا: ' + response.data);
                        $btn.attr('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error("[v0] Error deleting mapping:", error);
                    alert('✗ خطا در ارتباط با سرور');
                    $btn.attr('disabled', false);
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        app.init();
    });
    
})(jQuery);
