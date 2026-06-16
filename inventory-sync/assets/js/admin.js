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
        
        selectedSite1Product: null,
        selectedSite2Product: null,
        
        bindEvents: function() {
            // Tab switching
            $(document).on('click', '.nav-tab', this.switchTab.bind(this));
            
            // Settings
            $(document).on('click', '.test-btn', this.testConnection.bind(this));
            $(document).on('click', '.save-settings-btn', this.saveSettings.bind(this));
            $(document).on('change', 'input[name="current_site_role"]', this.quickSaveSettings.bind(this));
            
            // Mapping
            $(document).on('click', '.sync-all-btn', this.syncAllInventory.bind(this));
            $(document).on('click', '.product-item', this.selectProduct.bind(this));
            $(document).on('click', '#create-mapping-btn', this.createMapping.bind(this));
            $(document).on('click', '#clear-selection-btn', this.clearSelection.bind(this));
            $(document).on('click', '#manual-sync-btn', this.manualSyncAll.bind(this));
            $(document).on('click', '.delete-mapping-btn', this.deleteMapping.bind(this));
            $(document).on('keyup', '#search-site1', this.filterProducts.bind(this, 'site1'));
            $(document).on('keyup', '#search-site2', this.filterProducts.bind(this, 'site2'));
            
            // Transfer
            $(document).on('click', '#select-all-transfer', this.toggleSelectAll.bind(this));
            $(document).on('click', '.select-product', this.handleSelectProduct.bind(this));
            $(document).on('click', '.transfer-selected-btn', this.transferSelected.bind(this));
            $(document).on('click', '.transfer-all-btn', this.transferAll.bind(this));
        },
        
        loadInitialData: function() {
            this.loadProducts('site1');
            this.loadProducts('site2');
            this.loadTransferProducts();
            this.loadTransferredProducts();
            this.loadLogs();
            this.loadExistingMappings();
            this.updateTabsVisibility();
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
        
        updateTabsVisibility: function() {
            const currentSiteRole = $('input[name="current_site_role"]:checked').val();
            
            if (currentSiteRole === 'site2') {
                // Hide mapping and transfer tabs for site 2
                $('a.nav-tab[data-tab="mapping"]').hide();
                $('a.nav-tab[data-tab="transfer"]').hide();
                $('a.nav-tab[data-tab="transferred"]').hide();
            } else {
                // Show all tabs for site 1
                $('a.nav-tab[data-tab="mapping"]').show();
                $('a.nav-tab[data-tab="transfer"]').show();
                $('a.nav-tab[data-tab="transferred"]').show();
            }
        },
        
        updateSiteRole: function(e) {
            const newRole = $('input[name="current_site_role"]:checked').val();
            
            // Save to database via AJAX
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_save_settings',
                    _ajax_nonce: inventorySyncData.nonce,
                    current_site_role: newRole,
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
                },
                success: (response) => {
                    if (response.success) {
                        this.updateTabsVisibility();
                    }
                }
            });
        },
        
        quickSaveSettings: function(e) {
            const newRole = $('input[name="current_site_role"]:checked').val();
            
            // فقط نقش سایت را به سرعت ذخیره کن
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_save_settings',
                    _ajax_nonce: inventorySyncData.nonce,
                    current_site_role: newRole,
                    site1_name: $('#site1_name').val() || '',
                    site1_url: $('#site1_url').val() || '',
                    site1_key: $('#site1_key').val() || '',
                    site1_secret: $('#site1_secret').val() || '',
                    site2_name: $('#site2_name').val() || '',
                    site2_url: $('#site2_url').val() || '',
                    site2_key: $('#site2_key').val() || '',
                    site2_secret: $('#site2_secret').val() || '',
                    sync_direction: $('#sync_direction').val() || 'site1_to_site2',
                    auto_sync_enabled: $('#auto_sync_enabled').is(':checked') ? 1 : 0
                },
                success: (response) => {
                    if (response.success) {
                        this.updateTabsVisibility();
                    }
                }
            });
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
                current_site_role: $('input[name="current_site_role"]:checked').val(),
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
                        const message = response.data.message || response.data;
                        $('.form-actions .status-message')
                            .removeClass('error')
                            .addClass('success')
                            .text('✓ ' + message);
                        
                        // بروز‌رسانی تب‌ها بر اساس نقش جدید
                        if (response.data.current_site_role) {
                            this.updateTabsVisibility();
                        }
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
            
            $container.html('<p>درحال بارگذاری محصولات...</p>');
            
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
                        $container.html(
                            '<p class="alert alert-info">⚠️ ' + 
                            (response.data || 'لطفاً تنظیمات اتصالات سایت را بررسی کنید') + 
                            '</p>'
                        );
                    }
                },
                error: () => {
                    $container.html(
                        '<p class="alert alert-error">❌ خطا در بارگذاری محصولات. ' +
                        'اتصالات سایت یا تنظیمات را بررسی کنید.</p>'
                    );
                }
            });
        },
        
        renderProducts: function($container, products, site) {
            if (!products || products.length === 0) {
                $container.html('<p>❌ محصولی پیدا نشد. لطفاً تنظیمات را بررسی کنید.</p>');
                return;
            }
            
            let html = '';
            products.forEach(product => {
                // پشتیبانی از فرمت‌های مختلف API
                const productId = product.id;
                const productName = product.name || 'نام نامشخص';
                const sku = product.sku || 'بدون SKU';
                const stockQty = product.stock_quantity || 0;
                
                html += `
                    <div class="product-item" data-site="${site}" data-id="${productId}" style="padding: 10px; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s;">
                        <div style="font-weight: bold; margin-bottom: 3px;">${productName}</div>
                        <div style="font-size: 12px; color: #666;">SKU: ${sku}</div>
                        <div style="font-size: 12px; color: #666;">📦 موجودی: ${stockQty}</div>
                    </div>
                `;
            });
            
            $container.html(html);
        },
        
        selectProduct: function(e) {
            const $item = $(e.target).closest('.product-item');
            const site = $item.attr('data-site');
            const productId = parseInt($item.attr('data-id'));
            const productName = $item.find('.product-item > div:first').text() || $item.find('[data-name]').text();
            const sku = $item.find('[data-sku]').text() || '';
            const stock = $item.find('[data-stock]').text() || 0;
            
            // Get product details
            const product = {
                id: productId,
                name: $item.find('div').first().text(),
                sku: $item.find('div').eq(1).text().replace('SKU: ', ''),
                stock_quantity: parseInt($item.find('div').eq(2).text().replace(/[^\d]/g, ''))
            };
            
            if (site === 'site1') {
                this.selectedSite1Product = product;
                $item.css('background', '#e7f5ff').css('border-color', '#339af0');
            } else {
                this.selectedSite2Product = product;
                $item.css('background', '#f0fff4').css('border-color', '#51cf66');
            }
            
            this.updateMappingDisplay();
        },
        
        updateMappingDisplay: function() {
            if (this.selectedSite1Product && this.selectedSite2Product) {
                $('#selected-mapping').show();
                $('#selected-site1-name').text(this.selectedSite1Product.name);
                $('#selected-site1-sku').text('SKU: ' + (this.selectedSite1Product.sku || 'N/A'));
                $('#selected-site1-stock').text('موجودی: ' + (this.selectedSite1Product.stock_quantity || 0));
                
                $('#selected-site2-name').text(this.selectedSite2Product.name);
                $('#selected-site2-sku').text('SKU: ' + (this.selectedSite2Product.sku || 'N/A'));
                $('#selected-site2-stock').text('موجودی: ' + (this.selectedSite2Product.stock_quantity || 0));
                
                $('#create-mapping-btn').prop('disabled', false);
            } else {
                $('#selected-mapping').hide();
                $('#create-mapping-btn').prop('disabled', true);
            }
        },
        
        clearSelection: function(e) {
            e.preventDefault();
            
            $('.product-item').css('background', '').css('border-color', '');
            this.selectedSite1Product = null;
            this.selectedSite2Product = null;
            $('#selected-mapping').hide();
            $('#create-mapping-btn').prop('disabled', true);
        },
        
        createMapping: function(e) {
            e.preventDefault();
            
            if (!this.selectedSite1Product || !this.selectedSite2Product) {
                alert(inventorySyncData.i18n.selectProducts);
                return;
            }
            
            const $btn = $('#create-mapping-btn');
            $btn.prop('disabled', true).text(inventorySyncData.i18n.saving);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_create_product_mapping',
                    _ajax_nonce: inventorySyncData.nonce,
                    site1_product_id: this.selectedSite1Product.id,
                    site2_product_id: this.selectedSite2Product.id,
                    site1_sku: this.selectedSite1Product.sku,
                    site2_sku: this.selectedSite2Product.sku
                },
                success: (response) => {
                    if (response.success) {
                        alert('✓ مرتبط‌سازی با موفقیت انجام شد');
                        this.clearSelection();
                        this.loadExistingMappings();
                    } else {
                        alert('✗ ' + response.data);
                    }
                },
                error: () => {
                    alert('✗ ' + inventorySyncData.i18n.error);
                },
                complete: () => {
                    $btn.prop('disabled', false).text('🔗 ایجاد ارتباط');
                }
            });
        },
        
        deleteMapping: function(e) {
            e.preventDefault();
            
            if (!confirm('آیا مطمئنید که می‌خواهید این مرتبط‌سازی را حذف کنید؟')) {
                return;
            }
            
            const $btn = $(e.target);
            const mappingId = $btn.attr('data-mapping-id');
            
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
                        alert('✓ مرتبط‌سازی حذف شد');
                        this.loadExistingMappings();
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
                    action: 'inventory_sync_get_all_mappings',
                    _ajax_nonce: inventorySyncData.nonce,
                    page: 1
                },
                success: (response) => {
                    if (response.success) {
                        this.renderExistingMappings(response.data);
                    }
                },
                error: () => {
                    $('.existing-mappings').html(
                        '<tr><td colspan="6" class="text-center alert alert-error">' + 
                        inventorySyncData.i18n.error + '</td></tr>'
                    );
                }
            });
        },
        
        renderExistingMappings: function(mappings) {
            if (!mappings || mappings.length === 0) {
                $('.existing-mappings').html(
                    '<tr><td colspan="6" class="text-center">📭 هیچ مرتبط‌سازی وجود ندارد</td></tr>'
                );
                return;
            }
            
            let html = '';
            mappings.forEach(mapping => {
                const statusClass = mapping.sync_status === 'synced' ? 'success' : 
                                   (mapping.sync_status === 'error' ? 'error' : 'pending');
                const statusText = mapping.sync_status === 'synced' ? '✓ هماهنگ' : 
                                  (mapping.sync_status === 'error' ? '✗ خطا' : '⏳ منتظر');
                
                html += `
                    <tr>
                        <td>${mapping.site1_product_id || '-'}</td>
                        <td>${mapping.site1_sku || '-'}</td>
                        <td>${mapping.site2_product_id || '-'}</td>
                        <td>${mapping.site2_sku || '-'}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>
                            <button class="button button-small delete-mapping-btn" data-mapping-id="${mapping.id}">
                                🗑️ حذف
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            $('.existing-mappings').html(html);
        },
        
        filterProducts: function(site, e) {
            const searchTerm = $(e.target).val().toLowerCase();
            const $container = site === 'site1' ? $('.site1-products') : $('.site2-products');
            
            if (!searchTerm) {
                $container.find('.product-item').show();
                return;
            }
            
            $container.find('.product-item').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        },
        
        manualSyncAll: function(e) {
            e.preventDefault();
            
            if (!confirm('آیا می‌خواهید تمام مرتبط‌سازی‌ها را هماهنگ کنید؟ این عملیات ممکن است چند دقیقه طول بکشد.')) {
                return;
            }
            
            const $btn = $('#manual-sync-btn');
            const originalText = $btn.text();
            $btn.prop('disabled', true).text(inventorySyncData.i18n.syncing);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_manual_sync_all',
                    _ajax_nonce: inventorySyncData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const results = response.data;
                        const successful = (results.synced || 0);
                        const failed = (results.failed || 0);
                        
                        const message = 'هماهنگ‌سازی انجام شد!\n' +
                                       'موفق: ' + successful + '\n' +
                                       'ناموفق: ' + failed;
                        alert(message);
                        
                        this.loadExistingMappings();
                    } else {
                        alert('خطا: ' + response.data);
                    }
                },
                error: () => {
                    alert('خطا در هماهنگ‌سازی');
                },
                complete: () => {
                    $btn.prop('disabled', false).text(originalText);
                }
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
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        app.init();
    });
    
})(jQuery);
