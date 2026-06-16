jQuery(document).ready(function($) {
    const App = {
        init() {
            this.loadProducts();
            setInterval(() => this.loadProducts(), 30000); // هر 30 ثانیه
        },
        
        loadProducts() {
            $.ajax({
                url: inventorySyncSlaveData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_slave_get_products',
                    _ajax_nonce: inventorySyncSlaveData.nonce
                },
                success: (response) => {
                    if (response.success && response.data.length > 0) {
                        this.renderProducts(response.data);
                    } else {
                        this.renderEmpty();
                    }
                },
                error: () => {
                    console.error('[v0] Error loading products');
                }
            });
        },
        
        renderProducts(products) {
            let html = '';
            products.forEach(product => {
                const image = product.image || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22%3E%3C/svg%3E';
                html += `
                    <div class="product-card-slave" data-id="${product.id}">
                        <div class="product-image">
                            <img src="${image}" alt="${product.name}" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22%3E%3C/svg%3E'">
                        </div>
                        <div class="product-details">
                            <div class="product-name">${product.name}</div>
                            <div class="product-meta">
                                <div class="meta-item">
                                    <span class="meta-label">موجودی</span>
                                    <span class="meta-value">${product.stock}</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Markup</span>
                                    <span class="meta-value">${product.markup}%</span>
                                </div>
                            </div>
                            <div class="product-price">${product.price} تومان</div>
                        </div>
                    </div>
                `;
            });
            $('#products-container').html(html);
        },
        
        renderEmpty() {
            $('#products-container').html(`
                <div style="grid-column: 1 / -1;">
                    <div class="empty-state">
                        <p>📦 هنوز محصولی دریافت نشده است</p>
                        <p style="font-size: 12px; margin-top: 10px;">منتظر بمانید تا محصولات از سایت Master ارسال شوند</p>
                    </div>
                </div>
            `);
        },
        
        updateMarkup() {
            const markup = $('#global-markup').val();
            $.ajax({
                url: inventorySyncSlaveData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'inventory_sync_slave_update_markup',
                    markup: markup,
                    _ajax_nonce: inventorySyncSlaveData.nonce
                },
                success: (response) => {
                    alert(response.data);
                    this.loadProducts();
                }
            });
        }
    };
    
    App.init();
    
    // Expose to global scope
    window.App = App;
});
