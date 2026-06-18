<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_woocommerce')) {
    wp_die('عدم دسترسی');
}

?>

<div class="wrap">
    <h1>جفت‌سازی محصولات</h1>
    <p class="description">محصول‌های سایت 1 و سایت 2 را با هم جفت کنید و موجودی‌های آن‌ها را خودکار هماهنگ کنید.</p>
    
    <!-- تب‌های تصفیه‌گر -->
    <div class="inventory-sync-tabs">
        <button class="tab-button active" data-tab="create-pair">ایجاد جفت جدید</button>
        <button class="tab-button" data-tab="manage-pairs">مدیریت جفت‌ها</button>
        <button class="tab-button" data-tab="pair-logs">لاگ‌های هماهنگ‌سازی</button>
    </div>
    
    <!-- تب 1: ایجاد جفت جدید -->
    <div id="create-pair" class="tab-content active">
        <div class="card" style="max-width: 100%; margin: 20px 0; padding: 20px;">
            <h2>ایجاد جفت محصولات جدید</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="site1_product">محصول سایت 1:</label></th>
                    <td>
                        <input 
                            type="text" 
                            id="site1_product" 
                            class="product-search" 
                            placeholder="جستجو برای محصول..."
                            data-site="site1"
                        >
                        <div id="site1_results" class="product-results"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="site2_product">محصول سایت 2:</label></th>
                    <td>
                        <input 
                            type="text" 
                            id="site2_product" 
                            class="product-search" 
                            placeholder="جستجو برای محصول..."
                            data-site="site2"
                        >
                        <div id="site2_results" class="product-results"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="sync_direction">جهت هماهنگ‌سازی:</label></th>
                    <td>
                        <select id="sync_direction">
                            <option value="bidirectional">دوطرفه (بهترین انتخاب)</option>
                            <option value="site1_to_site2">سایت 1 → سایت 2</option>
                            <option value="site2_to_site1">سایت 2 → سایت 1</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <button id="create-pair-btn" class="button button-primary">ایجاد جفت</button>
            <span id="create-pair-status"></span>
        </div>
    </div>
    
    <!-- تب 2: مدیریت جفت‌ها -->
    <div id="manage-pairs" class="tab-content">
        <div class="card" style="max-width: 100%; margin: 20px 0; padding: 20px;">
            <h2>جفت‌های فعلی</h2>
            
            <button id="refresh-pairs-btn" class="button">تازه‌کردن</button>
            
            <table class="wp-list-table widefat striped" id="pairs-table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>محصول سایت 1</th>
                        <th>موجودی 1</th>
                        <th>محصول سایت 2</th>
                        <th>موجودی 2</th>
                        <th>آخرین هماهنگ‌سازی</th>
                        <th>جهت</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody id="pairs-tbody">
                    <tr><td colspan="9" style="text-align: center;">بارگذاری...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- تب 3: لاگ‌های هماهنگ‌سازی -->
    <div id="pair-logs" class="tab-content">
        <div class="card" style="max-width: 100%; margin: 20px 0; padding: 20px;">
            <h2>لاگ‌های هماهنگ‌سازی</h2>
            
            <table class="wp-list-table widefat striped" id="logs-table">
                <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>محصول</th>
                        <th>عملیات</th>
                        <th>منبع → مقصد</th>
                        <th>موجودی جدید</th>
                        <th>وضعیت</th>
                        <th>پیام</th>
                    </tr>
                </thead>
                <tbody id="logs-tbody">
                    <tr><td colspan="7" style="text-align: center;">بارگذاری...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.inventory-sync-tabs {
    display: flex;
    gap: 10px;
    margin: 20px 0;
    border-bottom: 2px solid #ccc;
}

.tab-button {
    padding: 10px 20px;
    background: #f0f0f0;
    border: none;
    cursor: pointer;
    font-size: 14px;
    border-bottom: 3px solid transparent;
}

.tab-button.active {
    background: #fff;
    border-bottom-color: #0073aa;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.product-search {
    width: 300px;
    padding: 8px;
    font-size: 14px;
}

.product-results {
    border: 1px solid #ddd;
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    display: none;
}

.product-results.active {
    display: block;
}

.product-item {
    padding: 8px 10px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}

.product-item:hover {
    background: #f5f5f5;
}

.product-item-info {
    font-size: 12px;
    color: #666;
}

#create-pair-status {
    margin-left: 20px;
    font-weight: bold;
}

.status-success {
    color: #008a20;
}

.status-error {
    color: #d32f2f;
}

.pair-actions {
    display: flex;
    gap: 5px;
}

.pair-actions button {
    padding: 4px 8px;
    font-size: 12px;
}

.pair-status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
    const data = inventorySyncData;
    let site1_selected = null;
    let site2_selected = null;
    
    // ============ تب تبدیلی ============
    $('.tab-button').click(function() {
        const tab = $(this).data('tab');
        
        $('.tab-button').removeClass('active');
        $('.tab-content').removeClass('active');
        
        $(this).addClass('active');
        $('#' + tab).addClass('active');
        
        if (tab === 'manage-pairs') {
            load_pairs();
        } else if (tab === 'pair-logs') {
            load_logs();
        }
    });
    
    // ============ جستجوی محصول ============
    $('.product-search').on('input', function() {
        const query = $(this).val();
        const site = $(this).data('site');
        const resultsDiv = $('#' + site + '_results');
        
        if (query.length < 2) {
            resultsDiv.removeClass('active');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'inventory_sync_search_products');
        formData.append('_ajax_nonce', data.nonce);
        formData.append('search', query);
        formData.append('site', site);
        
        $.ajax({
            url: data.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(function(product) {
                        html += '<div class="product-item" data-id="' + product.id + '" data-name="' + product.name + '" data-site="' + site + '">';
                        html += '<strong>' + product.name + '</strong>';
                        html += '<div class="product-item-info">#' + product.id + ' - SKU: ' + (product.sku || 'N/A') + '</div>';
                        html += '</div>';
                    });
                    resultsDiv.html(html).addClass('active');
                }
            }
        });
    });
    
    // انتخاب محصول
    $(document).on('click', '.product-item', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const site = $(this).data('site');
        
        $('#' + site + '_product').val(name + ' (#' + id + ')');
        $('#' + site + '_results').removeClass('active').html('');
        
        if (site === 'site1') {
            site1_selected = id;
        } else {
            site2_selected = id;
        }
    });
    
    // ============ ایجاد جفت ============
    $('#create-pair-btn').click(function() {
        if (!site1_selected || !site2_selected) {
            $('#create-pair-status').html('<span class="status-error">لطفاً هر دو محصول را انتخاب کنید</span>');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'inventory_sync_create_pair');
        formData.append('_ajax_nonce', data.nonce);
        formData.append('site1_id', site1_selected);
        formData.append('site2_id', site2_selected);
        formData.append('sync_direction', $('#sync_direction').val());
        
        $.ajax({
            url: data.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#create-pair-status').html('<span class="status-success">✓ ' + response.data + '</span>');
                    setTimeout(function() {
                        $('#site1_product').val('');
                        $('#site2_product').val('');
                        $('#create-pair-status').html('');
                        site1_selected = null;
                        site2_selected = null;
                        load_pairs();
                    }, 1000);
                } else {
                    $('#create-pair-status').html('<span class="status-error">خطا: ' + response.data + '</span>');
                }
            }
        });
    });
    
    // ============ بارگذاری جفت‌ها ============
    function load_pairs() {
        const formData = new FormData();
        formData.append('action', 'inventory_sync_get_paired_products');
        formData.append('_ajax_nonce', data.nonce);
        formData.append('page', 1);
        
        $.ajax({
            url: data.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(function(pair) {
                        const status = pair.is_active ? 'فعال' : 'غیرفعال';
                        const statusClass = pair.is_active ? 'status-active' : 'status-inactive';
                        
                        html += '<tr>';
                        html += '<td>#' + pair.id + '</td>';
                        html += '<td>' + pair.site1_product_name + '<br><small>' + pair.site1_sku + '</small></td>';
                        html += '<td style="text-align: center;">-</td>';
                        html += '<td>' + pair.site2_product_name + '<br><small>' + pair.site2_sku + '</small></td>';
                        html += '<td style="text-align: center;">-</td>';
                        html += '<td>' + (pair.last_sync ? pair.last_sync : 'هنوز نه') + '</td>';
                        html += '<td>' + pair.sync_direction + '</td>';
                        html += '<td><span class="pair-status-badge ' + statusClass + '">' + status + '</span></td>';
                        html += '<td>';
                        html += '<div class="pair-actions">';
                        html += '<button class="button button-small sync-pair-btn" data-id="' + pair.id + '">Sync</button>';
                        html += '<button class="button button-small delete-pair-btn" data-id="' + pair.id + '">حذف</button>';
                        html += '</div>';
                        html += '</td>';
                        html += '</tr>';
                    });
                    
                    if (html === '') {
                        html = '<tr><td colspan="9" style="text-align: center;">هیچ جفتی یافت نشد</td></tr>';
                    }
                    
                    $('#pairs-tbody').html(html);
                }
            }
        });
    }
    
    // sync دستی
    $(document).on('click', '.sync-pair-btn', function() {
        const pairId = $(this).data('id');
        $(this).prop('disabled', true).text('در حال sync...');
        
        const formData = new FormData();
        formData.append('action', 'inventory_sync_manual_sync_pair');
        formData.append('_ajax_nonce', data.nonce);
        formData.append('pair_id', pairId);
        
        $.ajax({
            url: data.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                alert(response.data);
                load_pairs();
            },
            error: function() {
                alert('خطا در sync');
                load_pairs();
            }
        });
    });
    
    // حذف جفت
    $(document).on('click', '.delete-pair-btn', function() {
        if (!confirm('آیا مطمئن هستید؟')) return;
        
        const pairId = $(this).data('id');
        
        const formData = new FormData();
        formData.append('action', 'inventory_sync_delete_pair');
        formData.append('_ajax_nonce', data.nonce);
        formData.append('pair_id', pairId);
        
        $.ajax({
            url: data.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                alert(response.data);
                load_pairs();
            }
        });
    });
    
    // تازه‌کردن
    $('#refresh-pairs-btn').click(function() {
        load_pairs();
    });
    
    // ============ بارگذاری لاگ‌ها ============
    function load_logs() {
        const formData = new FormData();
        formData.append('action', 'inventory_sync_get_logs');
        formData.append('_ajax_nonce', data.nonce);
        formData.append('page', 1);
        
        $.ajax({
            url: data.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(function(log) {
                        const statusClass = log.status === 'success' ? 'status-success' : 'status-error';
                        const icon = log.status === 'success' ? '✓' : '✗';
                        
                        html += '<tr>';
                        html += '<td>' + log.created_at + '</td>';
                        html += '<td>' + log.product_name + '</td>';
                        html += '<td>' + log.action + '</td>';
                        html += '<td>' + log.source_site + ' → ' + log.target_site + '</td>';
                        html += '<td>' + log.new_value + '</td>';
                        html += '<td><span class="' + statusClass + '">' + icon + ' ' + log.status + '</span></td>';
                        html += '<td>' + (log.error_message || '-') + '</td>';
                        html += '</tr>';
                    });
                    
                    if (html === '') {
                        html = '<tr><td colspan="7" style="text-align: center;">لاگی یافت نشد</td></tr>';
                    }
                    
                    $('#logs-tbody').html(html);
                }
            }
        });
    }
});
</script>
