(function($) {
    'use strict';
    
    const MappingManager = {
        // متغیرهای انتخاب‌شده برای مرتبط‌سازی دستی
        selectedSite1: null,
        selectedSite2: null,
        
        init: function() {
            this.bindEvents();
            this.loadAutoMappedProducts();
            this.loadUnmappedProducts();
            this.updateNextSyncTime();
            
            // بازخوانی خودکار هر 60 ثانیه
            setInterval(() => {
                this.updateNextSyncTime();
            }, 60000);
        },
        
        /**
         * بارگذاری محصولات مرتبط‌شده خودکار
         */
        loadAutoMappedProducts: function() {
            const self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_auto_mapped_products',
                    nonce: inventorySyncNonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.displayAutoMappedProducts(response.data);
                    }
                }
            });
        },
        
        /**
         * نمایش محصولات مرتبط‌شده خودکار
         */
        displayAutoMappedProducts: function(products) {
            const tbody = $('.auto-mapped-products');
            const countBadge = $('.auto-mapped-count');
            
            if (products.length === 0) {
                tbody.html(`<tr><td colspan="7" class="text-center">محصول مرتبط‌شده‌ای وجود ندارد</td></tr>`);
                countBadge.text('0');
                return;
            }
            
            countBadge.text(products.length);
            let html = '';
            
            products.forEach((product, index) => {
                const stockMatch = product.site1_stock === product.site2_stock ? 
                    '<span style="color: green;">✓</span>' : 
                    '<span style="color: orange;">!⚠</span>';
                
                html += `
                    <tr>
                        <td><input type="checkbox" class="select-product" value="${product.site1_product_id}"></td>
                        <td>${product.product_name}</td>
                        <td>${product.sku || '-'}</td>
                        <td>${product.site1_stock || 0}</td>
                        <td>${product.site2_stock || 0}</td>
                        <td>${stockMatch}</td>
                        <td>
                            <button class="button button-small sync-product-btn" data-site1="${product.site1_product_id}" data-site2="${product.site2_product_id}">
                                ⚡ هماهنگ‌سازی
                            </button>
                            <button class="button button-small button-secondary remove-mapping-btn" data-site1="${product.site1_product_id}">
                                🗑 حذف
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.html(html);
        },
        
        /**
         * بارگذاری محصولات مرتبط نشده
         */
        loadUnmappedProducts: function() {
            const self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_unmapped_products',
                    nonce: inventorySyncNonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.displayUnmappedProducts(response.data);
                    }
                }
            });
        },
        
        /**
         * نمایش محصولات مرتبط نشده
         */
        displayUnmappedProducts: function(data) {
            const site1Container = $('.site1-unmapped-products');
            const site2Container = $('.site2-unmapped-products');
            
            // سایت 1
            if (data.site1 && data.site1.length > 0) {
                let html = '<div class="unmapped-list">';
                data.site1.forEach((product) => {
                    html += `
                        <div class="product-item" data-product-id="${product.id}" data-site="site1">
                            <strong>${product.name}</strong>
                            <small>${product.sku || '-'}</small>
                        </div>
                    `;
                });
                html += '</div>';
                site1Container.html(html);
            } else {
                site1Container.html('<p style="text-align: center; color: #999;">تمام محصولات مرتبط شده‌اند</p>');
            }
            
            // سایت 2
            if (data.site2 && data.site2.length > 0) {
                let html = '<div class="unmapped-list">';
                data.site2.forEach((product) => {
                    html += `
                        <div class="product-item" data-product-id="${product.id}" data-site="site2">
                            <strong>${product.name}</strong>
                            <small>${product.sku || '-'}</small>
                        </div>
                    `;
                });
                html += '</div>';
                site2Container.html(html);
            } else {
                site2Container.html('<p style="text-align: center; color: #999;">تمام محصولات مرتبط شده‌اند</p>');
            }
        },
        
        /**
         * تحدیث زمان بعدی هماهنگ‌سازی
         */
        updateNextSyncTime: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_get_next_sync_time',
                    nonce: inventorySyncNonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#next-sync-time').text('بعدی هماهنگ‌سازی: ' + response.data);
                    }
                }
            });
        },
        
        bindEvents: function() {
            const self = this;
            
            // انتخاب محصول برای مرتبط‌سازی دستی
            $(document).on('click', '.product-item', function() {
                const productId = $(this).attr('data-product-id');
                const site = $(this).attr('data-site');
                
                if (site === 'site1') {
                    self.selectedSite1 = productId;
                    $('.site1-unmapped-products .product-item').removeClass('selected');
                    $(this).addClass('selected');
                } else {
                    self.selectedSite2 = productId;
                    $('.site2-unmapped-products .product-item').removeClass('selected');
                    $(this).addClass('selected');
                }
                
                // فعال کردن دکمه اگر هر دو انتخاب شده‌اند
                if (self.selectedSite1 && self.selectedSite2) {
                    $('.create-manual-mapping-btn').prop('disabled', false);
                }
            });
            
            // مرتبط کردن دستی
            $(document).on('click', '.create-manual-mapping-btn', function() {
                if (!self.selectedSite1 || !self.selectedSite2) {
                    alert('لطفا یک محصول از هر سایت را انتخاب کنید');
                    return;
                }
                
                self.createManualMapping(self.selectedSite1, self.selectedSite2);
            });
            
            // هماهنگ‌سازی دستی موجودی
            $(document).on('click', '.manual-sync-inventory-btn', function() {
                self.manualSyncInventory();
            });
            
            // هماهنگ‌سازی فوری یک محصول
            $(document).on('click', '.sync-product-btn', function() {
                const site1Id = $(this).attr('data-site1');
                const site2Id = $(this).attr('data-site2');
                self.syncProductInventory(site1Id, site2Id);
            });
            
            // حذف mapping
            $(document).on('click', '.remove-mapping-btn', function() {
                if (confirm('آیا مطمئن هستید؟')) {
                    const site1Id = $(this).attr('data-site1');
                    self.removeMapping(site1Id);
                }
            });
            
            // جستجو در لیست محصولات
            $(document).on('keyup', '.site1-search', function() {
                const query = $(this).val().toLowerCase();
                $('.site1-unmapped-products .product-item').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(query) > -1);
                });
            });
            
            $(document).on('keyup', '.site2-search', function() {
                const query = $(this).val().toLowerCase();
                $('.site2-unmapped-products .product-item').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(query) > -1);
                });
            });
            
            // بازخوانی لیست
            $(document).on('click', '.refresh-mapping-btn', function() {
                self.loadAutoMappedProducts();
                self.loadUnmappedProducts();
            });
        },
        
        /**
         * مرتبط کردن دستی
         */
        createManualMapping: function(site1Id, site2Id) {
            const self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_create_manual_mapping',
                    site1_id: site1Id,
                    site2_id: site2Id,
                    nonce: inventorySyncNonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('محصولات مرتبط شدند!');
                        self.selectedSite1 = null;
                        self.selectedSite2 = null;
                        self.loadAutoMappedProducts();
                        self.loadUnmappedProducts();
                    } else {
                        alert('خطا: ' + response.data);
                    }
                }
            });
        },
        
        /**
         * هماهنگ‌سازی دستی موجودی
         */
        manualSyncInventory: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_manual_sync_all',
                    nonce: inventorySyncNonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('موجودی‌ها هماهنگ شدند! ' + response.data.synced + ' محصول');
                        this.loadAutoMappedProducts();
                    }
                }
            });
        },
        
        /**
         * هماهنگ‌سازی موجودی یک محصول
         */
        syncProductInventory: function(site1Id, site2Id) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_sync_product_inventory',
                    site1_id: site1Id,
                    site2_id: site2Id,
                    nonce: inventorySyncNonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('هماهنگ‌سازی انجام شد!');
                        this.loadAutoMappedProducts();
                    }
                }
            });
        },
        
        /**
         * حذف mapping
         */
        removeMapping: function(site1Id) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_remove_mapping',
                    site1_id: site1Id,
                    nonce: inventorySyncNonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Mapping حذف شد!');
                        this.loadAutoMappedProducts();
                        this.loadUnmappedProducts();
                    }
                }
            });
        }
    };
    
    // شروع هنگام بارگذاری صفحه
    $(function() {
        MappingManager.init();
    });
})(jQuery);
