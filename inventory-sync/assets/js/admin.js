/**
 * Inventory Sync Admin JavaScript
 */

(function($) {
    'use strict';
    
    // ذخیره لیست محصولات برای استفاده در dropdown ها
    let site1Products = [];
    let site2Products = [];
    
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
            $(document).on('click', '#save-mapping-btn', this.saveMapping.bind(this));
            $(document).on('click', '.sync-all-btn', this.syncAllInventory.bind(this));
            $(document).on('click', '.delete-mapping-btn', this.deleteMapping.bind(this));
            $(document).on('click', '.sync-single-btn', this.syncSingleMapping.bind(this));
            $(document).on('change', '#map-site1-product', this.updateProductPreview.bind(this, 'site1'));
            $(document).on('change', '#map-site2-product', this.updateProductPreview.bind(this, 'site2'));
            
            // Transfer
            $(document).on('click', '#select-all-transfer', this.toggleSelectAll.bind(this));
            $(document).on('click', '.select-product', this.handleSelectProduct.bind(this));
            $(document).on('click', '.transfer-selected-btn', this.transferSelected.bind(this));
            $(document).on('click', '.transfer-all-btn', this.transferAll.bind(this));
        },
        
        loadInitialData: function() {
            this.loadProductsForMapping('site1');
            this.loadProductsForMapping('site2');
            this.loadMappings();
            this.loadTransferProducts();
            this.loadTransferredProducts();
            this.loadLogs();
        },
        
        // === Tab Management ===
        switchTab: function(e) {
            e.preventDefault();
            const $tab = $(e.target);
            const tabName = $tab.attr('data-tab');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            $('.tab-pane').removeClass('active');
            $('#' + tabName).addClass('active');
            
            if (tabName === 'logs') {
                this.loadLogs();
            } else if (tabName === 'mapping') {
                this.loadMappings();
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
                            .removeClass('error').addClass('success')
                            .text('+ ' + response.data);
                    } else {
                        $btn.parent().find('.status-message')
                            .removeClass('success').addClass('error')
                            .text('- ' + response.data);
                    }
                },
                error: () => {
                    $btn.parent().find('.status-message')
                        .removeClass('success').addClass('error')
                        .text('- ' + inventorySyncData.i18n.error);
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
                            .removeClass('error').addClass('success')
                            .text('+ ' + response.data);
                    }
                },
                error: () => {
                    $('.form-actions .status-message')
                        .removeClass('success').addClass('error')
                        .text('- ' + inventorySyncData.i18n.error);
                },
                complete: () => {
                    $btn.attr('disabled', false).text(originalText);
                }
            });
        },
        
        // === بارگذاری محصولات برای dropdown های mapping ===
        loadProductsForMapping: function(site) {
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
                    if (response.success && response.data) {
                        if (site === 'site1') {
                            site1Products = response.data;
                            this.populateMappingSelect('#map-site1-product', response.data, site);
                        } else {
                            site2Products = response.data;
                            this.populateMappingSelect('#map-site2-product', response.data, site);
                        }
                    }
                }
            });
        },
        
        populateMappingSelect: function(selector, products, site) {
            const $sel = $(selector);
            $sel.find('option:not(:first)').remove();
            
            products.forEach(p => {
                const label = `[#${p.id}] ${p.name}` + (p.sku ? ` — SKU: ${p.sku}` : '');
                $sel.append($('<option>', { value: p.id, text: label, 'data-sku': p.sku || '', 'data-name': p.name }));
            });
        },
        
        updateProductPreview: function(site) {
            const $sel = site === 'site1' ? $('#map-site1-product') : $('#map-site2-product');
            const $preview = site === 'site1' ? $('#map-site1-preview') : $('#map-site2-preview');
            const selectedOpt = $sel.find('option:selected');
            const id = $sel.val();
            
            if (!id) {
                $preview.text('');
                return;
            }
            
            const name = selectedOpt.data('name') || '';
            const sku  = selectedOpt.data('sku') || 'N/A';
            $preview.html(`<strong>نام:</strong> ${name} &nbsp; <strong>SKU:</strong> ${sku}`);
        },
        
        // === مرتبط‌سازی ===
        saveMapping: function(e) {
            e.preventDefault();
            
            const site1Id  = $('#map-site1-product').val();
            const site2Id  = $('#map-site2-product').val();
            const site1Sku = $('#map-site1-product option:selected').data('sku') || '';
            const site2Sku = $('#map-site2-product option:selected').data('sku') || '';
            
            if (!site1Id || !site2Id) {
                $('#mapping-form-status')
                    .removeClass('success').addClass('error')
                    .text('لطفا هر دو محصول را انتخاب کنید');
                return;
            }
            
            if (site1Id === site2Id) {
                $('#mapping-form-status')
                    .removeClass('success').addClass('error')
                    .text('محصولات یکسان انتخاب شده‌اند');
                return;
            }
            
            const $btn = $('#save-mapping-btn');
            $btn.attr('disabled', true).text(inventorySyncData.i18n.saving);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_save_mapping',
                    _ajax_nonce: inventorySyncData.nonce,
                    site1_id: site1Id,
                    site2_id: site2Id,
                    site1_sku: site1Sku,
                    site2_sku: site2Sku
                },
                success: (response) => {
                    if (response.success) {
                        $('#mapping-form-status')
                            .removeClass('error').addClass('success')
                            .text('مرتبط‌سازی ذخیره شد');
                        // ریست dropdown ها
                        $('#map-site1-product').val('');
                        $('#map-site2-product').val('');
                        $('#map-site1-preview').text('');
                        $('#map-site2-preview').text('');
                        // رفرش جدول
                        this.loadMappings();
                    } else {
                        $('#mapping-form-status')
                            .removeClass('success').addClass('error')
                            .text(response.data || inventorySyncData.i18n.error);
                    }
                },
                error: () => {
                    $('#mapping-form-status')
                        .removeClass('success').addClass('error')
                        .text(inventorySyncData.i18n.error);
                },
                complete: () => {
                    $btn.attr('disabled', false).text('ذخیره مرتبط‌سازی');
                }
            });
        },
        
        // بارگذاری جدول مرتبط‌سازی‌ها
        loadMappings: function() {
            $('#existing-mappings-list').html(
                '<tr><td colspan="8" class="text-center">' + inventorySyncData.i18n.loading + '</td></tr>'
            );
            
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
                },
                error: () => {
                    $('#existing-mappings-list').html(
                        '<tr><td colspan="8" class="text-center alert alert-error">' + inventorySyncData.i18n.error + '</td></tr>'
                    );
                }
            });
        },
        
        renderMappings: function(mappings) {
            if (!mappings || mappings.length === 0) {
                $('#existing-mappings-list').html(
                    '<tr><td colspan="8" class="text-center">هیچ مرتبط‌سازی‌ای ثبت نشده است</td></tr>'
                );
                return;
            }
            
            let html = '';
            mappings.forEach((m, idx) => {
                const statusClass = m.sync_status === 'synced' ? 'success' :
                                   (m.sync_status === 'error' ? 'error' : 'pending');
                const statusText  = m.sync_status === 'synced' ? 'همگام‌شده' :
                                   (m.sync_status === 'error' ? 'خطا' : 'در انتظار');
                const lastSync = m.last_sync
                    ? new Date(m.last_sync).toLocaleString('fa-IR')
                    : '---';
                
                // نام محصول: اگر ستون جدید موجود نیست به ID بازگشت
                const s1Name = m.site1_product_name || `محصول #${m.site1_product_id}`;
                const s2Name = m.site2_product_name || `محصول #${m.site2_product_id}`;
                
                html += `
                    <tr>
                        <td><span class="mapping-number">${idx + 1}</span></td>
                        <td>
                            <strong>${s1Name}</strong>
                            <br><small class="text-muted">ID: ${m.site1_product_id}</small>
                        </td>
                        <td><code>${m.site1_sku || '---'}</code></td>
                        <td>
                            <strong>${s2Name}</strong>
                            <br><small class="text-muted">ID: ${m.site2_product_id}</small>
                        </td>
                        <td><code>${m.site2_sku || '---'}</code></td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td><small>${lastSync}</small></td>
                        <td class="mapping-actions-cell">
                            <button class="button button-small sync-single-btn" data-id="${m.id}" title="همگام‌سازی این مرتبط‌سازی">
                                همگام‌سازی
                            </button>
                            <button class="button button-small button-link-delete delete-mapping-btn" data-id="${m.id}" title="حذف">
                                حذف
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            $('#existing-mappings-list').html(html);
        },
        
        // حذف مرتبط‌سازی
        deleteMapping: function(e) {
            e.preventDefault();
            const $btn = $(e.target).closest('.delete-mapping-btn');
            const mappingId = $btn.data('id');
            
            if (!confirm('آیا از حذف این مرتبط‌سازی مطمئن هستید؟')) {
                return;
            }
            
            $btn.attr('disabled', true);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_delete_mapping',
                    _ajax_nonce: inventorySyncData.nonce,
                    mapping_id: mappingId
                },
                success: (response) => {
                    if (response.success) {
                        this.loadMappings();
                    } else {
                        alert(response.data || inventorySyncData.i18n.error);
                    }
                },
                error: () => {
                    alert(inventorySyncData.i18n.error);
                },
                complete: () => {
                    $btn.attr('disabled', false);
                }
            });
        },
        
        // همگام‌سازی یک مرتبط‌سازی خاص
        syncSingleMapping: function(e) {
            e.preventDefault();
            const $btn = $(e.target).closest('.sync-single-btn');
            const mappingId = $btn.data('id');
            const originalText = $btn.text();
            
            $btn.attr('disabled', true).text('...');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_sync_inventory',
                    _ajax_nonce: inventorySyncData.nonce,
                    mapping_id: mappingId
                },
                success: (response) => {
                    if (response.success) {
                        this.loadMappings();
                    } else {
                        alert('خطا: ' + (response.data || inventorySyncData.i18n.error));
                    }
                },
                error: () => {
                    alert(inventorySyncData.i18n.error);
                },
                complete: () => {
                    $btn.attr('disabled', false).text(originalText);
                }
            });
        },
        
        // === همگام‌سازی همه موجودی‌ها ===
        syncAllInventory: function(e) {
            e.preventDefault();
            
            if (!confirm('آیا می‌خواهید تمام موجودی‌های مرتبط‌سازی‌شده را همگام کنید؟')) {
                return;
            }
            
            const $btn = $(e.target);
            const originalText = $btn.text();
            
            $btn.attr('disabled', true).text(inventorySyncData.i18n.syncing);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_sync_all',
                    _ajax_nonce: inventorySyncData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert('همگام‌سازی کامل شد!\n' + response.data.message);
                        this.loadMappings();
                    } else {
                        alert('خطا: ' + (response.data || inventorySyncData.i18n.error));
                    }
                },
                error: () => {
                    alert(inventorySyncData.i18n.error);
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
                        <div class="product-stock">موجودی: ${product.stock_quantity || 0}</div>
                    </div>
                `;
            });
            
            $container.html(html);
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
                        <td><span class="status-badge pending">منتظر</span></td>
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
            const totalCheckboxes   = $('.transfer-products input[type="checkbox"]').length;
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
                        const results   = response.data;
                        const successful = results.filter(r => r.success).length;
                        const failed    = results.filter(r => !r.success).length;
                        
                        const message = 'انتقال کامل شد!\nموفق: ' + successful + '\nناموفق: ' + failed;
                        alert(message);
                        
                        this.loadTransferProducts();
                    }
                },
                error: () => {
                    alert(inventorySyncData.i18n.error);
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
                    '<tr><td colspan="8" class="text-center">هیچ محصولی منتقل نشده است</td></tr>'
                );
                return;
            }
            
            let html = '';
            products.forEach(product => {
                const categoryStatus = product.categories_synced ? 'بله' : 'خیر';
                const attributeStatus = product.attributes_synced ? 'بله' : 'خیر';
                const transferDate = new Date(product.transferred_at).toLocaleString('fa-IR');
                const statusBadge = product.transfer_status === 'success' ?
                    '<span class="status-badge success">موفق</span>' :
                    '<span class="status-badge error">ناموفق</span>';
                
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
                    '<tr><td colspan="7" class="text-center">هیچ لاگی موجود نیست</td></tr>'
                );
                return;
            }
            
            let html = '';
            logs.forEach(log => {
                const statusClass = log.status === 'success' ? 'success' :
                                   (log.status === 'failed' ? 'error' :
                                   (log.status === 'info' ? 'info' : 'pending'));
                const statusText = log.status === 'success' ? 'موفق' :
                                  (log.status === 'failed' ? 'ناموفق' :
                                  (log.status === 'info' ? 'اطلاعات' : 'منتظر'));
                
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
        }
    };
    
    $(document).ready(function() {
        app.init();
    });
    
})(jQuery);
