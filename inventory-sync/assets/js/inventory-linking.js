/**
 * Inventory Linking Manager
 * مدیریت مرتبط‌سازی و لاگ‌های موجودی
 */

(function($) {
    'use strict';
    
    const manager = {
        currentMappingPage: 1,
        currentLogsPage: 1,
        selectedSite1Product: null,
        selectedSite2Product: null,
        
        init: function() {
            this.bindProductLinking();
            this.bindLinkedProducts();
            this.bindInventoryLogs();
            
            // Load data when tabs are switched
            $(document).on('click', '.nav-tab[data-tab="linked-products"]', () => this.loadMappings());
            $(document).on('click', '.nav-tab[data-tab="inventory-logs"]', () => this.loadLogs());
        },
        
        // ===== Product Linking Tab =====
        bindProductLinking: function() {
            const self = this;
            
            // Site 1 search
            $(document).on('input', '#site1_product_search', function() {
                const query = $(this).val();
                if (query.length >= 2) {
                    self.searchProducts('site1', query);
                }
            });
            
            // Site 2 search
            $(document).on('input', '#site2_product_search', function() {
                const query = $(this).val();
                if (query.length >= 2) {
                    self.searchProducts('site2', query);
                }
            });
            
            // Create mapping button
            $(document).on('click', '#create-mapping-btn', function() {
                self.createMapping();
            });
            
            // Product selection
            $(document).on('click', '.product-option', function() {
                const site = $(this).data('site');
                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');
                const variantId = $(this).data('variant-id');
                const variantName = $(this).data('variant-name');
                
                self.selectProduct(site, productId, productName, variantId, variantName);
            });
        },
        
        searchProducts: function(site, query) {
            const self = this;
            const dropdownClass = site === 'site1' ? '.site1-products-list' : '.site2-products-list';
            const $dropdown = $(dropdownClass);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_search_products',
                    _ajax_nonce: inventorySyncData.nonce,
                    site: site,
                    query: query
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        self.displayProductDropdown(site, response.data);
                    } else {
                        $dropdown.hide();
                    }
                }
            });
        },
        
        displayProductDropdown: function(site, products) {
            const dropdownClass = site === 'site1' ? '.site1-products-list' : '.site2-products-list';
            const $dropdown = $(dropdownClass);
            
            let html = '';
            products.forEach(product => {
                html += `
                    <div class="product-option" data-site="${site}" data-product-id="${product.id}" 
                         data-product-name="${product.name}" data-variant-id="${product.variant_id || ''}" 
                         data-variant-name="${product.variant_name || ''}">
                        <strong>${product.name}</strong>
                        ${product.variant_name ? `<br><small>${product.variant_name}</small>` : ''}
                        <br><small>موجودی: ${product.stock}</small>
                    </div>
                `;
            });
            
            $dropdown.html(html).show();
        },
        
        selectProduct: function(site, productId, productName, variantId, variantName) {
            const containerClass = site === 'site1' ? '.site1-selected' : '.site2-selected';
            const $container = $(containerClass);
            
            let html = `
                <div class="selected-product-info">
                    <p><strong>${productName}</strong></p>
                    ${variantName ? `<p><small>${variantName}</small></p>` : ''}
                </div>
            `;
            
            $container.html(html);
            
            // Hide dropdown and clear search
            const inputId = site === 'site1' ? '#site1_product_search' : '#site2_product_search';
            $(inputId).val('');
            const dropdownClass = site === 'site1' ? '.site1-products-list' : '.site2-products-list';
            $(dropdownClass).hide();
            
            // Update internal state
            if (site === 'site1') {
                this.selectedSite1Product = { id: productId, variant_id: variantId };
            } else {
                this.selectedSite2Product = { id: productId, variant_id: variantId };
            }
            
            // Enable create button if both products are selected
            this.checkCreateMappingEnabled();
        },
        
        checkCreateMappingEnabled: function() {
            const enabled = this.selectedSite1Product && this.selectedSite2Product;
            $('#create-mapping-btn').prop('disabled', !enabled);
        },
        
        createMapping: function() {
            const self = this;
            
            if (!this.selectedSite1Product || !this.selectedSite2Product) {
                alert('لطفا هر دو محصول را انتخاب کنید');
                return;
            }
            
            const btn = $('#create-mapping-btn');
            const originalText = btn.text();
            btn.prop('disabled', true).text('درحال ایجاد...');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_create_mapping',
                    _ajax_nonce: inventorySyncData.nonce,
                    site1_product_id: this.selectedSite1Product.id,
                    site2_product_id: this.selectedSite2Product.id,
                    site1_variant_id: this.selectedSite1Product.variant_id || 0,
                    site2_variant_id: this.selectedSite2Product.variant_id || 0
                },
                success: function(response) {
                    if (response.success) {
                        alert('ارتباط با موفقیت ایجاد شد');
                        $('.site1-selected').html('');
                        $('.site2-selected').html('');
                        self.selectedSite1Product = null;
                        self.selectedSite2Product = null;
                        self.checkCreateMappingEnabled();
                        self.loadMappings();
                    } else {
                        alert('خطا: ' + response.data);
                    }
                },
                error: function() {
                    alert('خطای سرور');
                },
                complete: function() {
                    btn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        // ===== Linked Products Tab =====
        bindLinkedProducts: function() {
            const self = this;
            
            $(document).on('click', '#refresh-mappings-btn', function() {
                self.loadMappings();
            });
            
            $(document).on('click', '.delete-mapping-btn', function() {
                const mappingId = $(this).data('mapping-id');
                if (confirm('آیا می‌خواهید این ارتباط را حذف کنید؟')) {
                    self.deleteMapping(mappingId);
                }
            });
            
            $(document).on('click', '.sync-mapping-btn', function() {
                const mappingId = $(this).data('mapping-id');
                const sourceSite = $(this).data('source-site');
                self.manualSync(mappingId, sourceSite);
            });
            
            $(document).on('click', '#prev-page-btn', function() {
                if (self.currentMappingPage > 1) {
                    self.currentMappingPage--;
                    self.loadMappings();
                }
            });
            
            $(document).on('click', '#next-page-btn', function() {
                self.currentMappingPage++;
                self.loadMappings();
            });
        },
        
        loadMappings: function() {
            const self = this;
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_mappings',
                    _ajax_nonce: inventorySyncData.nonce,
                    page: this.currentMappingPage
                },
                success: function(response) {
                    if (response.success) {
                        self.displayMappings(response.data.mappings);
                        self.updateMappingsPagination(response.data);
                        $('#pending-count').text(response.data.pending_tasks || 0);
                    }
                }
            });
        },
        
        displayMappings: function(mappings) {
            const $tbody = $('#linked-products-tbody');
            
            if (!mappings || mappings.length === 0) {
                $tbody.html('<tr><td colspan="7" style="text-align: center;">ارتباطی ثبت نشده است</td></tr>');
                return;
            }
            
            let html = '';
            mappings.forEach(mapping => {
                const statusClass = mapping.status.in_sync ? 'success' : (mapping.status.pending_tasks > 0 ? 'pending' : 'syncing');
                const statusText = mapping.status.in_sync ? 'هماهنگ' : `${mapping.status.pending_tasks} وظیفه`;
                
                html += `
                    <tr>
                        <td><strong>${mapping.site1_product.name}</strong></td>
                        <td>${mapping.status.site1_stock || 0}</td>
                        <td style="text-align: center;">↔</td>
                        <td><strong>${mapping.site2_product.name}</strong></td>
                        <td>${mapping.status.site2_stock || 0}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>
                            <button class="button sync-mapping-btn" data-mapping-id="${mapping.id}" data-source-site="1">
                                از سایت 1
                            </button>
                            <button class="button sync-mapping-btn" data-mapping-id="${mapping.id}" data-source-site="2">
                                از سایت 2
                            </button>
                            <button class="button delete-mapping-btn" data-mapping-id="${mapping.id}">
                                حذف
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            $tbody.html(html);
        },
        
        updateMappingsPagination: function(data) {
            const info = `صفحه ${data.current_page} از ${data.total_pages} (کل: ${data.total})`;
            $('#pagination-info').text(info);
            
            $('#prev-page-btn').prop('disabled', data.current_page <= 1);
            $('#next-page-btn').prop('disabled', data.current_page >= data.total_pages);
        },
        
        deleteMapping: function(mappingId) {
            const self = this;
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_delete_mapping',
                    _ajax_nonce: inventorySyncData.nonce,
                    mapping_id: mappingId
                },
                success: function(response) {
                    if (response.success) {
                        alert('ارتباط حذف شد');
                        self.loadMappings();
                    } else {
                        alert('خطا: ' + response.data);
                    }
                }
            });
        },
        
        manualSync: function(mappingId, sourceSite) {
            const self = this;
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_manual_sync',
                    _ajax_nonce: inventorySyncData.nonce,
                    mapping_id: mappingId,
                    source_site: sourceSite
                },
                success: function(response) {
                    if (response.success) {
                        alert('هماهنگ‌سازی دستی شروع شد');
                        self.loadMappings();
                    } else {
                        alert('خطا: ' + response.data);
                    }
                }
            });
        },
        
        // ===== Inventory Logs Tab =====
        bindInventoryLogs: function() {
            const self = this;
            
            $(document).on('change', '#logs-status-filter', function() {
                self.currentLogsPage = 1;
                self.loadLogs();
            });
            
            $(document).on('change', '#logs-site-filter', function() {
                self.currentLogsPage = 1;
                self.loadLogs();
            });
            
            $(document).on('click', '#refresh-logs-btn', function() {
                self.loadLogs();
            });
            
            $(document).on('click', '.retry-log-btn', function() {
                const logId = $(this).data('log-id');
                self.retryLog(logId);
            });
            
            $(document).on('click', '#logs-prev-page-btn', function() {
                if (self.currentLogsPage > 1) {
                    self.currentLogsPage--;
                    self.loadLogs();
                }
            });
            
            $(document).on('click', '#logs-next-page-btn', function() {
                self.currentLogsPage++;
                self.loadLogs();
            });
        },
        
        loadLogs: function() {
            const self = this;
            const status = $('#logs-status-filter').val();
            const site = $('#logs-site-filter').val();
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_inventory_logs',
                    _ajax_nonce: inventorySyncData.nonce,
                    page: this.currentLogsPage,
                    status: status || undefined,
                    site: site ? parseInt(site) : undefined
                },
                success: function(response) {
                    if (response.success) {
                        self.displayLogs(response.data.logs);
                        self.updateLogsPagination(response.data);
                    }
                }
            });
        },
        
        displayLogs: function(logs) {
            const $tbody = $('#logs-tbody');
            
            if (!logs || logs.length === 0) {
                $tbody.html('<tr><td colspan="8" style="text-align: center;">لاگی ثبت نشده است</td></tr>');
                return;
            }
            
            let html = '';
            logs.forEach(log => {
                const statusClass = log.status === 'completed' ? 'success' : (log.status === 'pending' ? 'pending' : 'failed');
                const statusText = log.status === 'completed' ? 'موفق' : (log.status === 'pending' ? 'درانتظار' : 'ناموفق');
                
                html += `
                    <tr>
                        <td>${log.product_name}</td>
                        <td>${log.variant_name || '-'}</td>
                        <td>سایت ${log.site}</td>
                        <td>${log.old_quantity}</td>
                        <td>${log.new_quantity}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${this.formatDate(log.created_at)}</td>
                        <td>
                            ${log.status === 'failed' ? `
                                <button class="button retry-log-btn" data-log-id="${log.id}">
                                    رفرش
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `;
            });
            
            $tbody.html(html);
        },
        
        updateLogsPagination: function(data) {
            const info = `صفحه ${data.current_page} از ${data.total_pages} (کل: ${data.total})`;
            $('#logs-pagination-info').text(info);
            
            $('#logs-prev-page-btn').prop('disabled', data.current_page <= 1);
            $('#logs-next-page-btn').prop('disabled', data.current_page >= data.total_pages);
        },
        
        retryLog: function(logId) {
            const self = this;
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_retry_log',
                    _ajax_nonce: inventorySyncData.nonce,
                    log_id: logId
                },
                success: function(response) {
                    if (response.success) {
                        alert('درخواست رفرش فرستاده شد');
                        self.loadLogs();
                    } else {
                        alert('خطا: ' + response.data);
                    }
                }
            });
        },
        
        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fa-IR') + ' ' + date.toLocaleTimeString('fa-IR');
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        manager.init();
    });
    
})(jQuery);
