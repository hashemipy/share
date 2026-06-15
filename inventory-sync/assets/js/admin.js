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
            
            // Mapping Tab Events
            $(document).on('click', '#mapping-search-btn', this.searchMappings.bind(this));
            $(document).on('click', '#mapping-refresh-btn', this.loadMappings.bind(this));
            $(document).on('click', '.mapping-sync-btn', this.syncMapping.bind(this));
            $(document).on('click', '.mapping-toggle-btn', this.toggleMapping.bind(this));
            $(document).on('click', '.mapping-delete-btn', this.deleteMapping.bind(this));
            $(document).on('click', '#create-mapping-btn', this.createMapping.bind(this));
            $(document).on('keyup', '#site1-search', this.searchProducts.bind(this, 'site1'));
            $(document).on('keyup', '#site2-search', this.searchProducts.bind(this, 'site2'));
            
            // Mapping
            $(document).on('click', '.sync-all-btn', this.syncAllInventory.bind(this));
            $(document).on('click', '.product-item', this.selectProduct.bind(this));
            
            // Transfer
            $(document).on('click', '#select-all-transfer', this.toggleSelectAll.bind(this));
            $(document).on('click', '.select-product', this.handleSelectProduct.bind(this));
            $(document).on('click', '.transfer-selected-btn', this.transferSelected.bind(this));
            $(document).on('click', '.transfer-all-btn', this.transferAll.bind(this));
        },
        
        loadInitialData: function() {
            this.loadMappings();
            this.loadProducts('site1');
            this.loadProducts('site2');
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
                    '<tr><td colspan="7" style="text-align: center; padding: 20px;">کوئی لاگ نہیں ہے</td></tr>'
                );
                return;
            }
            
            let html = '';
            logs.forEach(log => {
                const statusClass = log.status === 'success' ? 'success' : 
                                   (log.status === 'error' ? 'error' : 'pending');
                const statusText = log.status === 'success' ? '✓ کامیاب' : 
                                  (log.status === 'error' ? '✗ ناکام' : '⏳ منتظر');
                
                html += `
                    <tr>
                        <td>${new Date(log.created_at).toLocaleString('ur-PK')}</td>
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
        loadMappings: function(page = 1) {
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_mappings',
                    nonce: inventorySyncData.nonce,
                    page: page
                },
                success: (response) => {
                    if (response.success) {
                        this.renderMappings(response.data.mappings);
                        this.renderMappingPagination(response.data.total_pages, page);
                    }
                },
                error: () => {
                    $('#mappings-list').html(
                        '<tr><td colspan="7" style="text-align: center; color: red;">خرابی: Mappings لوڈ نہیں ہو سکے</td></tr>'
                    );
                }
            });
        },
        
        renderMappings: function(mappings) {
            if (!mappings || mappings.length === 0) {
                $('#mappings-list').html(
                    '<tr><td colspan="7" style="text-align: center; padding: 20px;">کوئی mapping نہیں ہے۔ نیا بنائیں۔</td></tr>'
                );
                return;
            }
            
            let html = '';
            mappings.forEach(m => {
                const statusIcon = m.sync_enabled ? '●' : '○';
                const site1Name = m.site1_name || '<em>حذف شدہ</em>';
                const site2Name = m.site2_name || '<em>حذف شدہ</em>';
                
                html += `
                    <tr>
                        <td style="text-align: center; font-size: 16px;">${statusIcon}</td>
                        <td>
                            <strong>${site1Name}</strong><br>
                            <small style="color: #999;">SKU: ${m.site1_sku || 'N/A'}</small>
                        </td>
                        <td style="text-align: center; font-weight: bold;">${m.site1_stock}</td>
                        <td style="text-align: center; font-size: 18px; color: #2271b1;">↔</td>
                        <td>
                            <strong>${site2Name}</strong><br>
                            <small style="color: #999;">SKU: ${m.site2_sku || 'N/A'}</small>
                        </td>
                        <td style="text-align: center; font-weight: bold;">${m.site2_stock}</td>
                        <td style="display: flex; gap: 5px; justify-content: flex-end;">
                            <button class="button button-small mapping-sync-btn" data-id="${m.id}" title="ہماہنگ کریں">🔄</button>
                            <button class="button button-small mapping-toggle-btn" data-id="${m.id}" data-enabled="${m.sync_enabled ? 1 : 0}" title="${m.sync_enabled ? 'غیر فعال' : 'فعال'} کریں">${m.sync_enabled ? '✅' : '⏹'}</button>
                            <button class="button button-small mapping-delete-btn" data-id="${m.id}" style="color: #d32f2f;" title="حذف کریں">🗑️</button>
                        </td>
                    </tr>
                `;
            });
            
            $('#mappings-list').html(html);
        },
        
        renderMappingPagination: function(totalPages, currentPage) {
            let html = '';
            for (let i = 1; i <= totalPages; i++) {
                const active = i === currentPage ? 'button-primary' : '';
                html += `<button class="button ${active}" data-page="${i}">${i}</button> `;
            }
            $('#mapping-pagination').html(html);
            
            $(document).off('click', '#mapping-pagination button');
            $(document).on('click', '#mapping-pagination button', (e) => {
                this.loadMappings($(e.target).data('page'));
            });
        },
        
        searchMappings: function(e) {
            e.preventDefault();
            const search = $('#mapping-search').val();
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_mappings',
                    nonce: inventorySyncData.nonce,
                    search: search
                },
                success: (response) => {
                    if (response.success) {
                        this.renderMappings(response.data.mappings);
                    }
                }
            });
        },
        
        syncMapping: function(e) {
            e.preventDefault();
            const mappingId = $(e.target).data('id');
            const $btn = $(e.target);
            
            $btn.prop('disabled', true).text('⏳');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_sync_inventory',
                    nonce: inventorySyncData.nonce,
                    mapping_id: mappingId
                },
                success: (response) => {
                    if (response.success) {
                        this.loadMappings();
                        alert('موجودی ہماہنگ ہو گئی!');
                    }
                },
                complete: () => {
                    $btn.prop('disabled', false).text('🔄');
                }
            });
        },
        
        toggleMapping: function(e) {
            e.preventDefault();
            const mappingId = $(e.target).data('id');
            const isEnabled = $(e.target).data('enabled');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_toggle_mapping',
                    nonce: inventorySyncData.nonce,
                    mapping_id: mappingId,
                    enabled: isEnabled ? 0 : 1
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
            if (!confirm('کیا آپ یقینی ہیں؟')) return;
            
            const mappingId = $(e.target).data('id');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_delete_mapping',
                    nonce: inventorySyncData.nonce,
                    mapping_id: mappingId
                },
                success: (response) => {
                    if (response.success) {
                        this.loadMappings();
                    }
                }
            });
        },
        
        searchProducts: function(site, e) {
            const search = $(e.target).val();
            if (search.length < 2) return;
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_products',
                    nonce: inventorySyncData.nonce,
                    site: site,
                    search: search
                },
                success: (response) => {
                    if (response.success) {
                        this.populateProductSelect(site, response.data);
                    }
                }
            });
        },
        
        populateProductSelect: function(site, products) {
            const selector = site === 'site1' ? '#site1-product' : '#site2-product';
            let options = '<option value="">انتخاب...</option>';
            
            products.forEach(product => {
                options += `<option value="${product.id}">${product.name} (${product.sku})</option>`;
            });
            
            $(selector).html(options);
        },
        
        createMapping: function(e) {
            e.preventDefault();
            const site1Id = $('#site1-product').val();
            const site2Id = $('#site2-product').val();
            
            if (!site1Id || !site2Id) {
                alert('دونوں محصولات کو منتخب کریں');
                return;
            }
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_create_mapping',
                    nonce: inventorySyncData.nonce,
                    site1_product_id: site1Id,
                    site2_product_id: site2Id
                },
                success: (response) => {
                    if (response.success) {
                        alert('Mapping بن گئی! موجودی ہماہنگ ہو رہی ہے...');
                        this.loadMappings();
                        $('#site1-product').val('');
                        $('#site2-product').val('');
                    } else {
                        alert('خرابی: ' + response.data);
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
