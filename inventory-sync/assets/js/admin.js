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
            $(document).on('click', '.sync-all-btn', this.syncAllInventory.bind(this));
            $(document).on('click', '.product-item', this.selectProduct.bind(this));
            $(document).on('click', '.add-mapping-btn', this.addMapping.bind(this));
            $(document).on('click', '.delete-mapping-btn', this.deleteMapping.bind(this));
            $(document).on('keyup', '.mapping-search', this.searchProducts.bind(this));
            
            // Transfer
            $(document).on('click', '#select-all-transfer', this.toggleSelectAll.bind(this));
            $(document).on('click', '.select-product', this.handleSelectProduct.bind(this));
            $(document).on('click', '.transfer-selected-btn', this.transferSelected.bind(this));
            $(document).on('click', '.transfer-all-btn', this.transferAll.bind(this));
        },
        
        loadInitialData: function() {
            this.loadProducts('site1');
            this.loadProducts('site2');
            this.loadExistingMappings();
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
                    if (response.success && response.data) {
                        this.renderProducts($container, response.data, site);
                    } else {
                        $container.html('<p class="alert alert-error">خطا: هیچ داده دریافت نشد</p>');
                    }
                },
                error: (xhr, status, error) => {
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
            products.forEach((product, index) => {
                // نام محصول را صحیح اخذ کن
                const productName = product.name || product.post_title || 'بدون نام';
                const productSku = product.sku || 'N/A';
                const productStock = product.stock_quantity !== undefined ? product.stock_quantity : 0;
                const productId = product.id;
                
                html += `
                    <div class="product-item" data-site="${site}" data-id="${productId}">
                        <div class="product-header">
                            <div class="product-name">${this.escapeHtml(productName)}</div>
                            <input type="checkbox" class="product-select" value="${productId}">
                        </div>
                        <div class="product-sku">SKU: ${this.escapeHtml(productSku)}</div>
                        <div class="product-stock">📦 موجودی: ${productStock}</div>
                    </div>
                `;
            });
            
            $container.html(html);
        },
        
        escapeHtml: function(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        },
        
        selectProduct: function(e) {
            const $item = $(e.currentTarget).closest('.product-item');
            
            // اگر کلیک روی دکمه حذف باشد، نریز
            if ($(e.target).hasClass('delete-mapping-btn')) {
                return;
            }
            
            $item.toggleClass('selected');
            this.updateMappingButtonState();
        },
        
        updateMappingButtonState: function() {
            const site1Selected = $('.site1-products .product-item.selected').length > 0;
            const site2Selected = $('.site2-products .product-item.selected').length > 0;
            
            if (site1Selected && site2Selected) {
                $('.add-mapping-btn').attr('disabled', false);
                const site1Name = $('.site1-products .product-item.selected .product-name').text();
                const site2Name = $('.site2-products .product-item.selected .product-name').text();
                $('.mapping-status-text').text(`انتخاب شده: "${site1Name}" → "${site2Name}"`);
            } else {
                $('.add-mapping-btn').attr('disabled', true);
                $('.mapping-status-text').text('');
            }
        },
        
        addMapping: function(e) {
            e.preventDefault();
            
            const site1Item = $('.site1-products .product-item.selected');
            const site2Item = $('.site2-products .product-item.selected');
            
            if (!site1Item.length || !site2Item.length) {
                alert(inventorySyncData.i18n.selectProducts);
                return;
            }
            
            const site1Id = site1Item.attr('data-id');
            const site1Sku = site1Item.find('.product-sku').text().replace('SKU: ', '');
            const site2Id = site2Item.attr('data-id');
            const site2Sku = site2Item.find('.product-sku').text().replace('SKU: ', '');
            
            const $btn = $(e.target);
            const originalText = $btn.text();
            $btn.attr('disabled', true).text(inventorySyncData.i18n.saving);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_save_mapping',
                    _ajax_nonce: inventorySyncData.nonce,
                    site1_id: site1Id,
                    site1_sku: site1Sku,
                    site2_id: site2Id,
                    site2_sku: site2Sku
                },
                success: (response) => {
                    if (response.success) {
                        alert('✓ ' + response.data);
                        site1Item.removeClass('selected');
                        site2Item.removeClass('selected');
                        this.loadExistingMappings();
                        this.updateMappingButtonState();
                    } else {
                        alert('✗ ' + response.data);
                    }
                },
                error: () => {
                    alert('✗ ' + inventorySyncData.i18n.error);
                },
                complete: () => {
                    $btn.attr('disabled', false).text(originalText);
                }
            });
        },
        
        deleteMapping: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!confirm('آیا می‌خواهید این مرتبط‌سازی را حذف کنید؟')) {
                return;
            }
            
            const mappingId = $(e.currentTarget).attr('data-mapping-id');
            const $btn = $(e.currentTarget);
            
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
                        $btn.closest('.mapping-item').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('✗ ' + response.data);
                    }
                },
                error: () => {
                    alert('✗ ' + inventorySyncData.i18n.error);
                }
            });
        },
        
        loadExistingMappings: function() {
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_mappings',
                    _ajax_nonce: inventorySyncData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderExistingMappings(response.data);
                    }
                },
                error: () => {
                    $('.mappings-list').html('<p class="alert alert-error">' + inventorySyncData.i18n.error + '</p>');
                }
            });
        },
        
        renderExistingMappings: function(mappings) {
            if (!mappings || mappings.length === 0) {
                $('.mappings-list').html('<p>هیچ مرتبط‌سازی‌ای وجود ندارد</p>');
                return;
            }
            
            let html = '';
            mappings.forEach(mapping => {
                const syncStatus = mapping.sync_status === 'synced' ? '✓' : '⏳';
                const lastSync = mapping.last_sync ? new Date(mapping.last_sync).toLocaleString('fa-IR') : '-';
                
                // نام‌های محصول را بهتر handle کن
                const site1Name = this.escapeHtml(mapping.site1_product_name || 'محصول سایت 1');
                const site2Name = this.escapeHtml(mapping.site2_product_name || 'محصول سایت 2');
                
                html += `
                    <div class="mapping-item" data-mapping-id="${mapping.id}">
                        <div class="mapping-info">
                            <div class="mapping-products">
                                <strong>${site1Name}</strong>
                                <span class="mapping-arrow">←→</span>
                                <strong>${site2Name}</strong>
                            </div>
                            <div class="mapping-meta">
                                <span>${syncStatus} آخرین هماهنگ: ${lastSync}</span>
                            </div>
                        </div>
                        <button class="button button-small delete-mapping-btn" data-mapping-id="${mapping.id}">
                            🗑️
                        </button>
                    </div>
                `;
            });
            
            $('.mappings-list').html(html);
        },
        
        searchProducts: function(e) {
            const $input = $(e.target);
            const searchText = $input.val().toLowerCase();
            const isStie1 = $input.hasClass('site1-search');
            const $container = isStie1 ? $('.site1-products') : $('.site2-products');
            
            $container.find('.product-item').each(function() {
                const text = $(this).text().toLowerCase();
                const matches = text.includes(searchText);
                $(this).toggle(matches);
            });
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
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_sync_all',
                    _ajax_nonce: inventorySyncData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert('✓ ' + response.data);
                        this.loadExistingMappings();
                    } else {
                        alert('✗ ' + response.data);
                    }
                },
                error: () => {
                    alert('✗ ' + inventorySyncData.i18n.error);
                },
                complete: () => {
                    $btn.attr('disabled', false).text(originalText);
                }
            });
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
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        app.init();
    });
    
})(jQuery);
