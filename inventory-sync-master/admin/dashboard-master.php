<div class="is-root-container">
    <div class="inventory-sync-master-dashboard">
        <div class="sync-header">
            <h2>مدیریت محصولات</h2>
            <div class="sync-stats">
                <div class="stat-card">
                    <div class="stat-number" id="total-mappings">۰</div>
                    <div class="stat-label">کل Mappings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="active-mappings">۰</div>
                    <div class="stat-label">فعال</div>
                </div>
            </div>
        </div>
        
        <div class="sync-container">
            <div class="products-section">
                <h3>محصولات موجود</h3>
                <div id="products-list" class="products-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                    <!-- محصولات اینجا بارگذاری می‌شوند -->
                </div>
                <div class="pagination" id="pagination-controls"></div>
            </div>
            
            <div class="sync-log-section">
                <h3>آخرین Syncs</h3>
                <div id="sync-log" class="sync-log" style="background: #f5f5f5; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto;">
                    <p style="color: #999;">هنوز sync صورت نگرفته است</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.inventory-sync-master-dashboard {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
}

.sync-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 20px;
}

.sync-stats {
    display: flex;
    gap: 20px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    min-width: 150px;
    text-align: center;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    opacity: 0.9;
}

.sync-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.products-section, .sync-log-section {
    background: #fafafa;
    padding: 20px;
    border-radius: 8px;
}

.products-grid {
    display: grid;
    gap: 15px;
}

.product-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    transition: all 0.3s;
}

.product-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #667eea;
}

.product-card img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 5px;
    margin-bottom: 10px;
}

.product-info {
    font-size: 14px;
}

.product-info strong {
    display: block;
    margin-bottom: 5px;
    color: #333;
}

.product-price {
    color: #667eea;
    font-weight: bold;
    margin: 8px 0;
}

.product-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.btn {
    flex: 1;
    padding: 8px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.3s;
}

.btn-send {
    background: #667eea;
    color: white;
}

.btn-send:hover {
    background: #5568d3;
}

.btn-remove {
    background: #f5f5f5;
    color: #333;
    border: 1px solid #ddd;
}

.btn-remove:hover {
    background: #f0f0f0;
}

.sync-log {
    font-size: 12px;
    line-height: 1.6;
    color: #555;
}

.sync-log-item {
    padding: 8px;
    border-bottom: 1px solid #e0e0e0;
    background: white;
    margin-bottom: 5px;
    border-radius: 3px;
}

.pagination {
    display: flex;
    gap: 5px;
    justify-content: center;
    margin-top: 20px;
}

.page-btn {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    background: white;
    transition: all 0.3s;
}

.page-btn.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.page-btn:hover:not(.active) {
    border-color: #667eea;
}

@media (max-width: 768px) {
    .sync-container {
        grid-template-columns: 1fr;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
}
</style>
