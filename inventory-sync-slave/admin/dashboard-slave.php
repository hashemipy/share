<div class="is-root-container">
    <div class="inventory-sync-slave-dashboard">
        <div class="slave-header">
            <div>
                <h2>محصولات دریافت‌شده</h2>
                <p class="subtitle">محصولاتی که از سایت Master وارد شده‌اند</p>
            </div>
            <div class="markup-control">
                <label for="global-markup">درصد افزایش قیمت:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="number" id="global-markup" step="0.01" min="0" max="100" value="0" class="markup-input">
                    <button class="btn btn-primary" onclick="App.updateMarkup()">ذخیره</button>
                </div>
            </div>
        </div>
        
        <div id="products-container" class="products-grid">
            <!-- محصولات اینجا بارگذاری می‌شوند -->
        </div>
    </div>
</div>

<style>
.inventory-sync-slave-dashboard {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
}

.slave-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.slave-header h2 {
    margin: 0;
    font-size: 24px;
    color: #1f2937;
}

.subtitle {
    margin: 5px 0 0 0;
    color: #999;
    font-size: 14px;
}

.markup-control {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 8px;
    min-width: 300px;
}

.markup-control label {
    display: block;
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.markup-input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.product-card-slave {
    background: #fafafa;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.product-card-slave:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #10b981;
}

.product-image {
    width: 100%;
    height: 180px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-details {
    padding: 15px;
}

.product-name {
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 8px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.product-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 12px;
    font-size: 13px;
}

.meta-item {
    background: white;
    padding: 8px;
    border-radius: 4px;
    text-align: center;
}

.meta-label {
    display: block;
    color: #999;
    font-size: 11px;
    margin-bottom: 3px;
}

.meta-value {
    display: block;
    color: #10b981;
    font-weight: 600;
}

.product-price {
    background: #10b981;
    color: white;
    padding: 10px;
    border-radius: 4px;
    text-align: center;
    font-weight: 600;
    margin-top: 10px;
}

.btn {
    padding: 10px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.3s;
}

.btn-primary {
    background: #10b981;
    color: white;
}

.btn-primary:hover {
    background: #059669;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

@media (max-width: 768px) {
    .slave-header {
        flex-direction: column;
        gap: 20px;
        align-items: flex-start;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
}
</style>
