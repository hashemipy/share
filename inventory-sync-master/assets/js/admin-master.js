jQuery(document).ready(function($) {
    const App = {
        currentPage: 1,
        
        init() {
            this.loadStats();
            this.loadProducts();
            this.bindEvents();
            setInterval(() => this.loadStats(), 10000); // هر 10 ثانیه
        },
        
        loadStats() {
            $.ajax({
                url: inventorySyncMasterData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_master_get_status',
                    _ajax_nonce: inventorySyncMasterData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#total-mappings').text(response.data.total_mappings);
                        $('#active-mappings').text(response.data.active_mappings);
                        this.updateSyncLog(response.data.recently_synced);
                    }
                }
            });
        },
        
        loadProducts(page = 1) {
            $('.loading').show();
            $.ajax({
                url: inventorySyncMasterData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_master_get_products',
                    page: page,
                    _ajax_nonce: inventorySyncMasterData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderProducts(response.data.products);
                        this.renderPagination(response.data.pages, page);
                        this.currentPage = page;
                    }
                    $('.loading').hide();
                }
            });
        },
        
        renderProducts(products) {
            let html = '';
            products.forEach(product => {
                const image = product.image || 'data:image/svg+xml,<svg></svg>';
                html += `
                    <div class="product-card" data-id="${product.id}">
                        <img src="${image}" alt="${product.name}" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22%3E%3C/svg%3E'">
                        <div class="product-info">
                            <strong>${product.name}</strong>
                            <small>${product.type === 'variable' ? 'محصول متغیر' : 'محصول ساده'}</small>
                            <div class="product-price">${product.price} تومان</div>
                            <small style="color: #999;">موجودی: ${product.stock}</small>
                        </div>
                        <div class="product-actions">
                            <button class="btn btn-send" onclick="App.sendProduct(${product.id})">ارسال</button>
                            <button class="btn btn-remove">حذف</button>
                        </div>
                    </div>
                `;
            });
            $('#products-list').html(html);
        },
        
        renderPagination(pages, currentPage) {
            let html = '';
            for (let i = 1; i <= pages; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                html += `<button class="page-btn ${activeClass}" onclick="App.loadProducts(${i})">${i}</button>`;
            }
            $('#pagination-controls').html(html);
        },
        
        sendProduct(productId) {
            if (!confirm('آیا اطمینان دارید؟')) return;
            
            $.ajax({
                url: inventorySyncMasterData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_master_send_product',
                    product_id: productId,
                    _ajax_nonce: inventorySyncMasterData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert(response.data);
                        this.loadStats();
                    } else {
                        alert('خطا: ' + response.data);
                    }
                }
            });
        },
        
        updateSyncLog(syncs) {
            let html = '';
            if (!syncs || syncs.length === 0) {
                html = '<p style="color: #999; text-align: center; padding: 20px;">هنوز sync صورت نگرفته است</p>';
            } else {
                syncs.forEach(sync => {
                    const date = new Date(sync.last_sync).toLocaleString('fa-IR');
                    html += `
                        <div class="sync-log-item">
                            <strong>محصول #${sync.site1_product_id}</strong><br>
                            <small>${date}</small>
                        </div>
                    `;
                });
            }
            $('#sync-log').html(html);
        },
        
        bindEvents() {
            // Event listeners
        }
    };
    
    App.init();
});
