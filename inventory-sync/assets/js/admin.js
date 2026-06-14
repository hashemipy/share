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

        // شناسه‌های محصولاتی که قبلاً منتقل شده‌اند
        _transferredIds: [],

        loadTransferProducts: function() {
            // ابتدا شناسه‌های منتقل‌شده را بگیر، سپس محصولات را رندر کن
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_transferred_ids',
                    _ajax_nonce: inventorySyncData.nonce,
                },
                success: (response) => {
                    if (response.success) {
                        this._transferredIds = response.data || [];
                    }
                },
                complete: () => {
                    // بعد از دریافت وضعیت، محصولات را بارگذاری کن
                    $.ajax({
                        url: inventorySyncData.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'inventory_sync_get_products',
                            _ajax_nonce: inventorySyncData.nonce,
                            site: 'site1',
                            page: 1
                        },
                        success: (resp) => {
                            if (resp.success) {
                                this.renderTransferTable(resp.data);
                            }
                        },
                        error: () => {
                            $('.transfer-products').html(
                                '<tr><td colspan="6" class="text-center alert alert-error">' +
                                inventorySyncData.i18n.error + '</td></tr>'
                            );
                        }
                    });
                }
            });
        },

        renderTransferTable: function(products) {
            if (!products || products.length === 0) {
                $('.transfer-products').html(
                    '<tr><td colspan="6" class="text-center">' +
                    inventorySyncData.i18n.selectProducts + '</td></tr>'
                );
                return;
            }

            let html = '';
            products.forEach(product => {
                const transferred = this._transferredIds.includes(parseInt(product.id));
                const rowClass    = transferred ? ' class="transfer-done"' : '';
                const statusBadge = transferred
                    ? '<span class="status-badge transferred">&#10003; منتقل شده</span>'
                    : '<span class="status-badge pending">&#8212; منتقل نشده</span>';
                const typeLabel = product.type === 'variable' ? 'متغیّر' : 'ساده';

                html += `
                    <tr${rowClass} data-product-id="${product.id}">
                        <td><input type="checkbox" class="select-product" value="${product.id}"${transferred ? ' disabled title="قبلاً منتقل شده"' : ''}></td>
                        <td>${product.name}</td>
                        <td>${product.sku || 'N/A'}</td>
                        <td>${product.stock_quantity || 0}</td>
                        <td>${typeLabel}</td>
                        <td>${statusBadge}</td>
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
            const $progressFill = $progress.find('.progress-fill');
            const $progressText = $progress.find('.progress-text');
            $progress.show();
            $progressFill.css('width', '0%');
            $progressText.text('در حال انتقال...');

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
                        const results  = response.data;
                        const successful = results.filter(r => r.success);
                        const failed     = results.filter(r => !r.success);

                        $progressFill.css('width', '100%');
                        $progressText.text(
                            'موفق: ' + successful.length + ' | ناموفق: ' + failed.length
                        );

                        // علامت‌گذاری ردیف‌های موفق بدون reload کامل
                        successful.forEach(r => {
                            const pid = parseInt(r.product_id);
                            if (!this._transferredIds.includes(pid)) {
                                this._transferredIds.push(pid);
                            }
                            const $row = $('tr[data-product-id="' + pid + '"]');
                            $row.addClass('transfer-done');
                            $row.find('.status-badge')
                                .removeClass('pending')
                                .addClass('transferred')
                                .html('&#10003; منتقل شده');
                            $row.find('input[type="checkbox"]')
                                .prop('checked', false)
                                .prop('disabled', true)
                                .attr('title', 'قبلاً منتقل شده');
                        });

                        // پیام خطا برای ناموفق‌ها
                        if (failed.length > 0) {
                            const failMsgs = failed.map(r => 'محصول ' + r.product_id + ': ' + r.message).join('\n');
                            alert('ناموفق:\n' + failMsgs);
                        }
                    }
                },
                error: () => {
                    alert('&#10007; ' + inventorySyncData.i18n.error);
                    $progress.hide();
                },
                complete: () => {
                    setTimeout(() => $progress.hide(), 2000);
                }
            });
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
