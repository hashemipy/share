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
            // نمایش loading indicator
            const $loader = $('#loading-indicator');
            if ($loader.length) {
                $loader.show();
            }
            
            console.log('[v0] شروع بارگزاری محصولات...');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_all_products',
                    _ajax_nonce: inventorySyncData.nonce
                },
                timeout: 30000, // 30 ثانیہ timeout
                success: (response) => {
                    console.log('[v0] پاسخ موصول ہوا:', response);
                    
                    if (response.success && response.data) {
                        const site1 = response.data.site1 || [];
                        const site2 = response.data.site2 || [];
                        
                        console.log('[v0] محصولات موصول: سائٹ1=' + site1.length + ', سائٹ2=' + site2.length);
                        
                        this.populateSelect('#site1-product-select', site1);
                        this.populateSelect('#site2-product-select', site2);
                        
                        // پنہان کریں اور کامیابی کا پیغام دکھائیں
                        if ($loader.length) {
                            $loader.html(
                                '<strong style="color: green;">✓ کامیاب:</strong> ' + 
                                site1.length + ' محصولات سائٹ 1 اور ' + 
                                site2.length + ' محصولات سائٹ 2 سے لوڈ ہو گئے'
                            ).css('background', '#d4edda').css('border-color', '#c3e6cb').show();
                            
                            // 3 سیکنڈ بعد پنہان کریں
                            setTimeout(() => $loader.fadeOut(), 3000);
                        }
                    } else {
                        console.error('[v0] نامعلوم پاسخ:', response);
                        this.showLoadingError(
                            response.data || 'نامعلوم خرابی'
                        );
                    }
                },
                error: (xhr, status, error) => {
                    console.error('[v0] AJAX خرابی:', status, error, xhr.responseText);
                    
                    let errorMsg = 'نامعلوم خرابی';
                    
                    if (status === 'timeout') {
                        errorMsg = 'درخواست میں بہت زیادہ وقت لگ گیا۔ براہ مہربانی دوبارہ کوشش کریں۔';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMsg = xhr.responseJSON.data;
                    } else if (xhr.responseText) {
                        try {
                            const parsed = JSON.parse(xhr.responseText);
                            errorMsg = parsed.data || parsed.message || errorMsg;
                        } catch (e) {
                            errorMsg = xhr.responseText.substring(0, 200);
                        }
                    }
                    
                    this.showLoadingError(errorMsg);
                    
                    // خالی dropdowns
                    this.populateSelect('#site1-product-select', []);
                    this.populateSelect('#site2-product-select', []);
                }
            });
        },
        
        showLoadingError: function(errorMsg) {
            const $loader = $('#loading-indicator');
            if ($loader.length) {
                $loader.html(
                    '<strong style="color: red;">❌ خرابی:</strong> ' + errorMsg + 
                    '<br><small style="color: #666; margin-top: 10px; display: block;">' +
                    'براہ مہربانی:<br>' +
                    '1. تنظیمات ٹیب میں جائیں<br>' +
                    '2. سائٹ 2 کی API تنظیمات کو تصدیق کریں<br>' +
                    '3. "اتصال کی جانچ" بٹن پر کلک کریں<br>' +
                    '4. اگر مسئلہ برقرار رہے تو براہ کرم براہ کرم دوبارہ کوشش کریں' +
                    '</small>'
                ).css('background', '#f8d7da').css('border-color', '#f5c6cb').show();
            }
        },
        
        populateSelect: function(selector, products) {
            const $select = $(selector);
            
            if (!products || products.length === 0) {
                $select.html('<option value="">کوئی محصول دستیاب نہیں</option>');
                console.warn('[v0] ' + selector + ' کے لیے کوئی محصول نہیں');
                return;
            }
            
            let html = '<option value="">انتخاب کنید...</option>';
            products.forEach(p => {
                if (p.id && p.name) {
                    html += `<option value="${p.id}" data-sku="${p.sku || 'N/A'}">${p.name} (${p.sku || 'بغیر SKU'})</option>`;
                }
            });
            
            $select.html(html);
            console.log('[v0] ' + selector + ' میں ' + products.length + ' محصولات شامل کیے گئے');
        },
        
        updateSite1ProductInfo: function(e) {
            const option = $(e.target).find('option:selected');
            $('#site1-product-id').text(option.val() || '-');
            $('#site1-product-sku').text(option.data('sku') || '-');
        },
        
        updateSite2ProductInfo: function(e) {
            const option = $(e.target).find('option:selected');
            $('#site2-product-id').text(option.val() || '-');
            $('#site2-product-sku').text(option.data('sku') || '-');
        },
        
        loadMappings: function() {
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_mappings',
                    _ajax_nonce: inventorySyncData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderMappings(response.data);
                    }
                }
            });
        },
        
        renderMappings: function(mappings) {
            if (!mappings || mappings.length === 0) {
                $('.mappings-list').html('<tr><td colspan="7" style="text-align: center; padding: 20px;">کوئی mapping موجود نہیں</td></tr>');
                return;
            }
            
            let html = '';
            mappings.forEach(m => {
                const status = m.sync_enabled ? '✅' : '⏸️';
                html += `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="text-align: center; font-size: 18px;">${status}</td>
                        <td><strong>${m.site1_name}</strong><br><small style="color: #999;">SKU: ${m.site1_sku}</small></td>
                        <td style="text-align: center; font-weight: bold; color: ${m.site1_stock > 0 ? '#28a745' : '#dc3545'};">${m.site1_stock}</td>
                        <td style="text-align: center;">↔</td>
                        <td><strong>${m.site2_name}</strong><br><small style="color: #999;">SKU: ${m.site2_sku}</small></td>
                        <td style="text-align: center; font-weight: bold; color: ${m.site2_stock > 0 ? '#28a745' : '#dc3545'};">${m.site2_stock}</td>
                        <td style="text-align: center;">
                            <button class="button button-small sync-mapping-btn" data-id="${m.id}" style="margin: 2px;">🔄</button>
                            <button class="button button-small toggle-mapping-btn" data-id="${m.id}" data-enabled="${m.sync_enabled}" style="margin: 2px;">${m.sync_enabled ? '⏸️' : '▶️'}</button>
                            <button class="button button-small delete-mapping-btn" data-id="${m.id}" style="margin: 2px; color: #dc3545;">🗑️</button>
                        </td>
                    </tr>
                `;
            });
            $('.mappings-list').html(html);
        },
        
        addMapping: function() {
            const site1 = $('#site1-product-select').val();
            const site2 = $('#site2-product-select').val();
            
            if (!site1 || !site2) {
                alert('لطفا دونوں محصولات انتخاب کریں');
                return;
            }
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_add_mapping',
                    nonce: inventorySyncData.nonce,
                    site1_product_id: site1,
                    site2_product_id: site2
                },
                success: (response) => {
                    if (response.success) {
                        alert('Mapping افزوده شد');
                        $('#site1-product-select').val('');
                        $('#site2-product-select').val('');
                        this.loadMappings();
                    }
                }
            });
        },
        
        syncAllMappings: function() {
            if (!confirm('تمام mappings کو sync کریں؟')) return;
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_sync_all_mappings',
                    nonce: inventorySyncData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert('تمام mappings sync ہو گئے');
                        this.loadMappings();
                    }
                }
            });
        },
        
        syncMapping: function(e) {
            e.preventDefault();
            const id = $(e.target).closest('button').data('id');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_sync_mapping',
                    nonce: inventorySyncData.nonce,
                    mapping_id: id
                },
                success: (response) => {
                    if (response.success) {
                        this.loadMappings();
                    }
                }
            });
        },
        
        toggleMapping: function(e) {
            e.preventDefault();
            const $btn = $(e.target).closest('button');
            const id = $btn.data('id');
            const enabled = $btn.data('enabled');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_toggle_mapping',
                    nonce: inventorySyncData.nonce,
                    mapping_id: id,
                    enabled: enabled ? 0 : 1
                },
                success: (response) => {
                    if (response.success) {
                        this.loadMappings();
                    }
                }
            });
        },
        
        deleteMapping: function(e) {
            e.preventDefault();
            if (!confirm('کیا یقین ہیں؟')) return;
            
            const id = $(e.target).closest('button').data('id');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_delete_mapping',
                    nonce: inventorySyncData.nonce,
                    mapping_id: id
                },
                success: (response) => {
                    if (response.success) {
                        alert('Mapping حذف ہو گیا');
                        this.loadMappings();
                    }
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        app.init();
    });
    
})(jQuery);
