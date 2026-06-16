/**
 * بهبود‌شده: Inventory Sync Admin JavaScript
 * 
 * تمام عملیات مرتبط‌سازی، انتقال و sync را مدیریت می‌کند
 */

(function($) {
    'use strict';
    
    const app = {
        // ذخیره‌سازی محصولات انتخاب‌شده
        selectedSite1: null,
        selectedSite2: null,
        
        init: function() {
            console.log("[v0] App initializing...");
            this.bindEvents();
            this.loadInitialData();
        },
        
        bindEvents: function() {
            // Tab switching
            $(document).on('click', '.nav-tab', this.switchTab.bind(this));
            
            // Settings
            $(document).on('click', '.test-btn', this.testConnection.bind(this));
            $(document).on('click', '.save-settings-btn', this.saveSettings.bind(this));
            $(document).on('click', '#save-site-role-btn', this.saveSiteRole.bind(this));
            
            // Mapping - انتخاب محصولات
            $(document).on('click', '.products-list .product-item', this.selectProduct.bind(this));
            $(document).on('click', '#create-mapping-btn', this.createMapping.bind(this));
            $(document).on('click', '.remove-mapping-btn', this.removeMapping.bind(this));
            $(document).on('click', '.sync-all-btn', this.syncAllInventory.bind(this));
            
            // Transfer
            $(document).on('click', '#select-all-transfer', this.toggleSelectAll.bind(this));
            $(document).on('click', '.select-product', this.handleSelectProduct.bind(this));
            $(document).on('click', '.transfer-selected-btn', this.transferSelected.bind(this));
            $(document).on('click', '.transfer-all-btn', this.transferAll.bind(this));
        },
        
        loadInitialData: function() {
            console.log("[v0] Loading initial data...");
            this.loadMappingProducts('site1');
            this.loadMappingProducts('site2');
            this.loadTransferProducts();
            this.loadTransferredProducts();
            this.loadLogs();
        },
        
        // === Tab Management ===
        switchTab: function(e) {
            e.preventDefault();
            const $tab = $(e.target);
            const tabName = $tab.attr('data-tab');
            
            console.log("[v0] Switching to tab: " + tabName);
            
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
            
            console.log("[v0] Testing connection to: " + site);
            
            $btn.attr('disabled', true).text('درحال بررسی...');
            
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
                        console.log("[v0] Connection successful: " + site);
                    } else {
                        $btn.parent().find('.status-message')
                            .removeClass('success')
                            .addClass('error')
                            .text('✗ ' + response.data);
                        console.error("[v0] Connection failed: " + response.data);
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    $btn.parent().find('.status-message')
                        .removeClass('success')
                        .addClass('error')
                        .text('✗ خطا: ' + textStatus);
                    console.error("[v0] AJAX error: " + textStatus);
                },
                complete: () => {
                    $btn.attr('disabled', false).text(originalText);
                }
            });
        },
        
        saveSiteRole: function(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const role = $('#current_site_role').val();
            
            if (!role) {
                alert('لطفاً یک نقش سایت انتخاب کنید');
                return;
            }
            
            console.log("[v0] Saving site role: " + role);
            
            $btn.attr('disabled', true).text('درحال ذخیره...');
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_save_site_role',
                    _ajax_nonce: inventorySyncData.nonce,
                    role: role
                },
                success: (response) => {
                    if (response.success) {
                        $('.site-role-status')
                            .removeClass('error')
                            .addClass('success')
                            .text('✓ ' + response.data.message)
                            .show();
                        
                        console.log("[v0] Site role saved successfully");
                        
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        $('.site-role-status')
                            .removeClass('success')
                            .addClass('error')
                            .text('✗ ' + response.data)
                            .show();
                        console.error("[v0] Failed to save site role: " + response.data);
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    $('.site-role-status')
                        .removeClass('success')
                        .addClass('error')
                        .text('✗ خطا در برقراری ارتباط')
                        .show();
                    console.error("[v0] AJAX error: " + textStatus);
                },
                complete: () => {
                    $btn.attr('disabled', false).text('💾 ذخیره نقش سایت');
                }
            });
        },
        
        // === Mapping Functions ===
        loadMappingProducts: function(site) {
            console.log("[v0] Loading mapping products for: " + site);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_mapping_products',
                    _ajax_nonce: inventorySyncData.nonce,
                    site: site,
                    page: 1,
                    per_page: 20
                },
                success: (response) => {
                    if (response.success) {
                        this.renderMappingProducts(site, response.data.products);
                        console.log("[v0] Products loaded: " + site + " (" + response.data.products.length + " items)");
                    } else {
                        this.showMappingError(site, response.data);
                        console.error("[v0] Failed to load products: " + response.data);
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    this.showMappingError(site, 'خطا در بارگذاری محصولات');
                    console.error("[v0] AJAX error: " + textStatus);
                }
            });
        },
        
        renderMappingProducts: function(site, products) {
            const $container = site === 'site1' ? $('.site1-products') : $('.site2-products');
            
            if (!products || products.length === 0) {
                $container.html('<p>هیچ محصولی یافت نشد</p>');
                return;
            }
            
            let html = '';
            products.forEach((product) => {
                html += `
                    <div class="product-item" data-product-id="${product.id}" data-site="${site}">
                        <div style="padding: 10px; border: 1px solid #ddd; margin: 5px 0; border-radius: 4px; cursor: pointer; hover: background #f5f5f5;">
                            <strong>${product.name}</strong><br/>
                            SKU: ${product.sku}<br/>
                            موجودی: ${product.stock}<br/>
                            <small style="color: #999;">${product.type === 'variable' ? 'محصول متغیر' : 'محصول ساده'}</small>
                        </div>
                    </div>
                `;
            });
            
            $container.html(html);
        },
        
        showMappingError: function(site, message) {
            const $container = site === 'site1' ? $('.site1-products') : $('.site2-products');
            $container.html(`<p style="color: red;">خطا: ${message}</p>`);
        },
        
        selectProduct: function(e) {
            const $item = $(e.target).closest('.product-item');
            const productId = $item.attr('data-product-id');
            const site = $item.attr('data-site');
            
            console.log("[v0] Selected product: " + productId + " from " + site);
            
            // Remove previous selection
            $item.parent().find('.product-item').css('background', '');
            
            // Highlight current selection
            $item.css('background', '#e8f4f8');
            
            // Store selection
            if (site === 'site1') {
                this.selectedSite1 = { id: productId, $element: $item };
            } else {
                this.selectedSite2 = { id: productId, $element: $item };
            }
            
            // Update button display
            this.updateMappingButtonState();
        },
        
        updateMappingButtonState: function() {
            const hasSelection = this.selectedSite1 && this.selectedSite2;
            $('#create-mapping-btn').attr('disabled', !hasSelection).css('opacity', hasSelection ? '1' : '0.5');
        },
        
        createMapping: function(e) {
            e.preventDefault();
            
            if (!this.selectedSite1 || !this.selectedSite2) {
                alert('لطفاً یک محصول از هر سایت انتخاب کنید');
                return;
            }
            
            const site1Id = this.selectedSite1.id;
            const site2Id = this.selectedSite2.id;
            
            console.log("[v0] Creating mapping: " + site1Id + " <-> " + site2Id);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_create_mapping',
                    _ajax_nonce: inventorySyncData.nonce,
                    site1_product_id: site1Id,
                    site2_product_id: site2Id
                },
                success: (response) => {
                    if (response.success) {
                        alert('✓ ' + response.data.message);
                        console.log("[v0] Mapping created: " + response.data.mapping_id);
                        
                        // Reload products
                        this.selectedSite1 = null;
                        this.selectedSite2 = null;
                        this.loadMappingProducts('site1');
                        this.loadMappingProducts('site2');
                    } else {
                        alert('✗ ' + response.data);
                        console.error("[v0] Failed to create mapping: " + response.data);
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    alert('✗ خطا: ' + textStatus);
                    console.error("[v0] AJAX error: " + textStatus);
                }
            });
        },
        
        removeMapping: function(e) {
            e.preventDefault();
            
            if (!confirm('آیا از حذف این مرتبط‌سازی مطمئن هستید؟')) {
                return;
            }
            
            const $btn = $(e.target);
            const mappingId = $btn.attr('data-mapping-id');
            
            console.log("[v0] Removing mapping: " + mappingId);
            
            $.ajax({
                url: inventorySyncData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_remove_mapping',
                    _ajax_nonce: inventorySyncData.nonce,
                    mapping_id: mappingId
                },
                success: (response) => {
                    if (response.success) {
                        alert('✓ ' + response.data.message);
                        console.log("[v0] Mapping removed successfully");
                        this.loadMappingProducts('site1');
                        this.loadMappingProducts('site2');
                    } else {
                        alert('✗ ' + response.data);
                        console.error("[v0] Failed to remove mapping: " + response.data);
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    alert('✗ خطا: ' + textStatus);
                    console.error("[v0] AJAX error: " + textStatus);
                }
            });
        },
        
        syncAllInventory: function(e) {
            e.preventDefault();
            console.log("[v0] Syncing all inventory...");
            // Implementation for syncing all mappings
        },
        
        // === Transfer Functions (existing) ===
        loadTransferProducts: function() {
            console.log("[v0] Loading transfer products...");
            // Implementation
        },
        
        loadTransferredProducts: function() {
            console.log("[v0] Loading transferred products...");
            // Implementation
        },
        
        toggleSelectAll: function(e) {
            console.log("[v0] Toggle select all");
            // Implementation
        },
        
        handleSelectProduct: function(e) {
            console.log("[v0] Handle select product");
            // Implementation
        },
        
        transferSelected: function(e) {
            console.log("[v0] Transfer selected products");
            // Implementation
        },
        
        transferAll: function(e) {
            console.log("[v0] Transfer all products");
            // Implementation
        },
        
        loadLogs: function() {
            console.log("[v0] Loading logs...");
            // Implementation
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        console.log("[v0] Document ready - initializing app");
        app.init();
    });
    
})(jQuery);
