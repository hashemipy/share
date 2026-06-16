/**
 * Inventory Linking Manager
 * مدیریت مرتبط‌سازی و لاگ‌های موجودی
 */

class InventoryLinkingManager {
    constructor() {
        this.currentMappingPage = 1;
        this.currentLogsPage = 1;
        this.selectedSite1Product = null;
        this.selectedSite2Product = null;
        
        this.init();
    }
    
    init() {
        this.bindProductLinking();
        this.bindLinkedProducts();
        this.bindInventoryLogs();
        this.loadMappings();
        this.loadLogs();
    }
    
    // ===== Product Linking =====
    
    bindProductLinking() {
        const site1Search = document.getElementById('site1_product_search');
        const site2Search = document.getElementById('site2_product_search');
        const createBtn = document.getElementById('create-mapping-btn');
        
        if (site1Search) {
            site1Search.addEventListener('input', (e) => this.searchProducts('site1', e.target.value));
        }
        
        if (site2Search) {
            site2Search.addEventListener('input', (e) => this.searchProducts('site2', e.target.value));
        }
        
        if (createBtn) {
            createBtn.addEventListener('click', () => this.createMapping());
        }
    }
    
    searchProducts(site, query) {
        if (query.length < 2) return;
        
        const products = this.getMockProducts(site, query);
        this.displayProductDropdown(site, products);
    }
    
    getMockProducts(site, query) {
        // نمایش محصولات آزمایشی - در واقع باید از WooCommerce API بگیری
        return [
            { id: 1, name: 'محصول تستی ' + site + ' - 1', sku: 'TEST-1' },
            { id: 2, name: 'محصول تستی ' + site + ' - 2', sku: 'TEST-2' }
        ].filter(p => p.name.includes(query));
    }
    
    displayProductDropdown(site, products) {
        const container = document.querySelector('.' + site + '-products-list');
        if (!container) return;
        
        if (products.length === 0) {
            container.innerHTML = '<p style="padding: 10px;">محصولی پیدا نشد</p>';
            container.style.display = 'block';
            return;
        }
        
        let html = '';
        products.forEach(product => {
            html += `<div class="product-option" data-site="${site}" data-id="${product.id}" data-name="${product.name}">
                <strong>${product.name}</strong><br>
                <small>SKU: ${product.sku}</small>
            </div>`;
        });
        
        container.innerHTML = html;
        container.style.display = 'block';
        
        // رویدادهای انتخاب
        container.querySelectorAll('.product-option').forEach(el => {
            el.addEventListener('click', (e) => this.selectProduct(site, el));
        });
    }
    
    selectProduct(site, element) {
        const id = element.dataset.id;
        const name = element.dataset.name;
        const sku = element.querySelector('small').textContent.replace('SKU: ', '');
        
        const selectedContainer = document.querySelector('.' + site + '-selected');
        selectedContainer.innerHTML = `
            <div class="selected-product-info">
                <p><strong>${name}</strong></p>
                <p><small>${sku}</small></p>
            </div>
        `;
        
        if (site === 'site1') {
            this.selectedSite1Product = { id, name };
        } else {
            this.selectedSite2Product = { id, name };
        }
        
        this.updateCreateButtonState();
        
        // بستن dropdown
        document.querySelector('.' + site + '-products-list').style.display = 'none';
    }
    
    updateCreateButtonState() {
        const btn = document.getElementById('create-mapping-btn');
        if (this.selectedSite1Product && this.selectedSite2Product) {
            btn.disabled = false;
        } else {
            btn.disabled = true;
        }
    }
    
    createMapping() {
        if (!this.selectedSite1Product || !this.selectedSite2Product) {
            alert('لطفا محصولات را انتخاب کنید');
            return;
        }
        
        const btn = document.getElementById('create-mapping-btn');
        btn.disabled = true;
        btn.textContent = 'درحال ایجاد...';
        
        fetch(inventorySyncData.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'inventory_sync_create_mapping',
                _ajax_nonce: inventorySyncData.nonce,
                site1_product_id: this.selectedSite1Product.id,
                site2_product_id: this.selectedSite2Product.id
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('ارتباط با موفقیت ایجاد شد');
                this.resetLinking();
                this.loadMappings();
            } else {
                alert('خطا: ' + (data.data || 'خطای نامشخص'));
            }
        })
        .catch(err => {
            alert('خطا: ' + err.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'ایجاد ارتباط';
        });
    }
    
    resetLinking() {
        this.selectedSite1Product = null;
        this.selectedSite2Product = null;
        document.querySelector('.site1-selected').innerHTML = '';
        document.querySelector('.site2-selected').innerHTML = '';
        document.getElementById('site1_product_search').value = '';
        document.getElementById('site2_product_search').value = '';
        this.updateCreateButtonState();
    }
    
    // ===== Linked Products =====
    
    bindLinkedProducts() {
        const refreshBtn = document.getElementById('refresh-mappings-btn');
        const prevBtn = document.getElementById('prev-page-btn');
        const nextBtn = document.getElementById('next-page-btn');
        
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadMappings());
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.previousMappingsPage());
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.nextMappingsPage());
        }
    }
    
    loadMappings() {
        fetch(inventorySyncData.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'inventory_sync_get_mappings',
                _ajax_nonce: inventorySyncData.nonce,
                page: this.currentMappingPage
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.renderMappings(data.data);
            }
        })
        .catch(err => console.error(err));
    }
    
    renderMappings(data) {
        const tbody = document.getElementById('linked-products-tbody');
        const paginationInfo = document.getElementById('pagination-info');
        const prevBtn = document.getElementById('prev-page-btn');
        const nextBtn = document.getElementById('next-page-btn');
        
        if (data.mappings.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">ارتباطی پیدا نشد</td></tr>';
            return;
        }
        
        let html = '';
        data.mappings.forEach(mapping => {
            const status = mapping.status.in_sync ? '✓ در حال هماهنگ' : '⊘ منتظر';
            html += `
                <tr>
                    <td>${mapping.site1_product.name}</td>
                    <td><strong>${mapping.status.site1_quantity}</strong></td>
                    <td style="text-align: center;">↔</td>
                    <td>${mapping.site2_product.name}</td>
                    <td><strong>${mapping.status.site2_quantity}</strong></td>
                    <td><span class="status-badge ${mapping.status.in_sync ? 'syncing' : 'pending'}">${status}</span></td>
                    <td>
                        <button class="button button-small sync-btn" data-mapping-id="${mapping.id}">هماهنگ دستی</button>
                        <button class="button button-small delete-btn" data-mapping-id="${mapping.id}">حذف</button>
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
        
        // رویدادها
        tbody.querySelectorAll('.sync-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.manualSync(e.target.dataset.mappingId));
        });
        tbody.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.deleteMapping(e.target.dataset.mappingId));
        });
        
        // Pagination
        paginationInfo.textContent = `صفحه ${data.current_page} از ${data.total_pages}`;
        prevBtn.disabled = data.current_page === 1;
        nextBtn.disabled = data.current_page === data.total_pages;
        
        // تعداد وظایف
        const pendingCount = data.mappings.reduce((sum, m) => sum + (m.status.pending_tasks || 0), 0);
        document.getElementById('pending-count').textContent = pendingCount;
    }
    
    previousMappingsPage() {
        if (this.currentMappingPage > 1) {
            this.currentMappingPage--;
            this.loadMappings();
        }
    }
    
    nextMappingsPage() {
        this.currentMappingPage++;
        this.loadMappings();
    }
    
    manualSync(mappingId) {
        if (!confirm('آیا می‌خواهید اکنون هماهنگ کنید؟')) return;
        
        fetch(inventorySyncData.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'inventory_sync_manual_sync',
                _ajax_nonce: inventorySyncData.nonce,
                mapping_id: mappingId,
                source_site: 1
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('درخواست هماهنگ افزوده شد');
                this.loadMappings();
            } else {
                alert('خطا: ' + data.data);
            }
        })
        .catch(err => console.error(err));
    }
    
    deleteMapping(mappingId) {
        if (!confirm('آیا مطمئنید؟ این ارتباط حذف خواهد شد')) return;
        
        fetch(inventorySyncData.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'inventory_sync_delete_mapping',
                _ajax_nonce: inventorySyncData.nonce,
                mapping_id: mappingId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('ارتباط برای حذف درخواست شد');
                this.loadMappings();
            } else {
                alert('خطا: ' + data.data);
            }
        })
        .catch(err => console.error(err));
    }
    
    // ===== Inventory Logs =====
    
    bindInventoryLogs() {
        const refreshBtn = document.getElementById('refresh-logs-btn');
        const statusFilter = document.getElementById('logs-status-filter');
        const siteFilter = document.getElementById('logs-site-filter');
        const prevBtn = document.getElementById('logs-prev-page-btn');
        const nextBtn = document.getElementById('logs-next-page-btn');
        
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadLogs());
        }
        if (statusFilter) {
            statusFilter.addEventListener('change', () => { this.currentLogsPage = 1; this.loadLogs(); });
        }
        if (siteFilter) {
            siteFilter.addEventListener('change', () => { this.currentLogsPage = 1; this.loadLogs(); });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.previousLogsPage());
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.nextLogsPage());
        }
    }
    
    loadLogs() {
        const statusFilter = document.getElementById('logs-status-filter');
        const siteFilter = document.getElementById('logs-site-filter');
        
        fetch(inventorySyncData.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'inventory_sync_get_inventory_logs',
                _ajax_nonce: inventorySyncData.nonce,
                page: this.currentLogsPage,
                status: statusFilter?.value || '',
                site: siteFilter?.value || ''
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.renderLogs(data.data);
            }
        })
        .catch(err => console.error(err));
    }
    
    renderLogs(data) {
        const tbody = document.getElementById('logs-tbody');
        const paginationInfo = document.getElementById('logs-pagination-info');
        const prevBtn = document.getElementById('logs-prev-page-btn');
        const nextBtn = document.getElementById('logs-next-page-btn');
        
        if (data.logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">لاگی پیدا نشد</td></tr>';
            return;
        }
        
        let html = '';
        data.logs.forEach(log => {
            const statusBadge = this.getStatusBadge(log.status);
            const retryBtn = log.status === 'failed' ? 
                `<button class="button button-small retry-btn" data-log-id="${log.id}">دوباره</button>` : '';
            
            html += `
                <tr>
                    <td>${log.product_name}</td>
                    <td>${log.variant_name || '-'}</td>
                    <td>سایت ${log.site}</td>
                    <td>${log.old_quantity}</td>
                    <td><strong>${log.new_quantity}</strong></td>
                    <td>${statusBadge}</td>
                    <td>${this.formatDate(log.created_at)}</td>
                    <td>${retryBtn}</td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
        
        // Retry buttons
        tbody.querySelectorAll('.retry-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.retryLog(e.target.dataset.logId));
        });
        
        // Pagination
        paginationInfo.textContent = `صفحه ${data.current_page} از ${data.total_pages}`;
        prevBtn.disabled = data.current_page === 1;
        nextBtn.disabled = data.current_page === data.total_pages;
    }
    
    previousLogsPage() {
        if (this.currentLogsPage > 1) {
            this.currentLogsPage--;
            this.loadLogs();
        }
    }
    
    nextLogsPage() {
        this.currentLogsPage++;
        this.loadLogs();
    }
    
    retryLog(logId) {
        if (!confirm('آیا می‌خواهید دوباره تلاش کنید؟')) return;
        
        fetch(inventorySyncData.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'inventory_sync_retry_log',
                _ajax_nonce: inventorySyncData.nonce,
                log_id: logId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('درخواست دوباره افزوده شد');
                this.loadLogs();
            } else {
                alert('خطا: ' + data.data);
            }
        })
        .catch(err => console.error(err));
    }
    
    // ===== Utilities =====
    
    getStatusBadge(status) {
        const badges = {
            'pending': '<span class="status-badge pending">⏳ درانتظار</span>',
            'success': '<span class="status-badge success">✓ موفق</span>',
            'failed': '<span class="status-badge failed">✗ ناموفق</span>'
        };
        return badges[status] || badges.pending;
    }
    
    formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('fa-IR') + ' ' + date.toLocaleTimeString('fa-IR');
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    new InventoryLinkingManager();
});
