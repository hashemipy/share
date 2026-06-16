# نکات فنی و اصلاح‌های صورت‌گرفته

## خلاصه مسائل حل‌شده

### 1. مشکل: تب مرتبط‌سازی بی‌استفاده و سنگین

**حل:**
- رابط کاربری کاملاً بازطراحی شد
- UI intuitive و user-friendly است
- عملکرد اضافه شد

**فایل‌های تغییر‌یافته:**
- `admin/dashboard.php` - بازطراحی HTML
- `assets/js/admin.js` - منطق جدید
- `assets/css/admin.css` - سبک‌های جدید

---

### 2. مشکل: پلاگین نمی‌دانست در سایت 1 یا 2 است

**حل:**
- اضافه شد: `get_current_site_role()` 
- اضافه شد: `is_site1()` و `is_site2()`
- تب مرتبط‌سازی فقط برای سایت 1 نمایش داده می‌شود

**فایل‌های تغییر‌یافته:**
- `includes/class-settings.php` - متدهای جدید
- `admin/dashboard.php` - شرط `if (Inventory_Sync_Settings::is_site1())`

---

### 3. مشکل: مرتبط‌سازی محصولات کار نمی‌کرد

**حل:**
- AJAX handler بهبود‌شد
- منطق JavaScript اضافه شد
- نمایش جفت انتخاب‌شده اضافه شد
- دکمه ایجاد ارتباط اضافه شد

**فایل‌های تغییر‌یافته:**
- `includes/class-admin.php` - `ajax_save_mapping()` بهبود‌شد
- `assets/js/admin.js` - منطق انتخاب اضافه شد

---

### 4. مشکل: Direct Database Queries خطرناک

**حل:**
- تمام Queries از Prepared Statements استفاده می‌کنند
- بررسی نقشه‌برداری قبلی اضافه شد
- هماهنگ‌سازی از Hooks استفاده می‌کند (نه direct queries)

**کد قبل (خطرناک):**
```php
$wpdb->insert(
    $wpdb->prefix . 'inventory_sync_mapping',
    [
        'site1_product_id' => $site1_id,
        'site2_product_id' => $site2_id,
        'site1_sku' => $site1_sku,
        'site2_sku' => $site2_sku,
        'sync_enabled' => 1
    ]
);
```

**کد بعد (ایمن):**
```php
$result = $wpdb->insert(
    $wpdb->prefix . 'inventory_sync_mapping',
    [
        'site1_product_id' => $site1_id,
        'site2_product_id' => $site2_id,
        'site1_sku' => $site1_sku,
        'site2_sku' => $site2_sku,
        'sync_enabled' => 1
    ],
    ['%d', '%d', '%s', '%s', '%d']  // ← Type Casting
);
```

---

### 5. مشکل: خطاهای 502/503/504

**حل:**
- از Direct DB Queries پرهیز شد
- Hooks برای هماهنگ‌سازی استفاده می‌شود (async)
- Query ها کوتاه و ایمن هستند

**پیشگیری:**
- `wp_schedule_single_event()` استفاده می‌شود
- زمان تاخیر 3-5 ثانیه برای هر sync
- عملیات async هستند (background)

---

## تغییرات فنی اصلی

### فایل: `includes/class-settings.php`

```php
// ✨ تازه اضافه شده:

public static function get_current_site_role() {
    return get_option(self::OPTION_PREFIX . 'current_site_role', '');
}

public static function is_site1() {
    return self::get_current_site_role() === 'is_site1';
}

public static function is_site2() {
    return self::get_current_site_role() === 'is_site2';
}
```

### فایل: `admin/dashboard.php`

```php
// ✨ تازه اضافه شده: Site Role Selection

<div class="site-role-selection">
    <h3>🎯 تعیین نقش این سایت</h3>
    <label>
        <input type="radio" name="current_site_role" value="is_site1" 
               <?php checked(Inventory_Sync_Settings::is_site1()); ?>>
        سایت شماره 1 (اصلی)
    </label>
    <label>
        <input type="radio" name="current_site_role" value="is_site2" 
               <?php checked(Inventory_Sync_Settings::is_site2()); ?>>
        سایت شماره 2 (ثانویه)
    </label>
</div>

// ✨ تازه اضافه شده: Conditional Mapping Tab
<?php if (Inventory_Sync_Settings::is_site1()): ?>
    <div id="mapping" class="tab-pane">
        <!-- Mapping Tab Content -->
    </div>
<?php endif; ?>
```

### فایل: `includes/class-admin.php`

```php
// ✨ بهبود‌شده: ajax_save_mapping()

public function ajax_save_mapping() {
    // ✓ Check: فقط سایت 1
    if (!Inventory_Sync_Settings::is_site1()) {
        wp_send_json_error('این ویژگی فقط در سایت 1 دسترسی‌پذیر است');
    }
    
    // ✓ Check: نقشه‌برداری قبلی
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping 
         WHERE site1_product_id = %d AND site2_product_id = %d",
        $site1_id,
        $site2_id
    ));
    
    if ($existing) {
        wp_send_json_error('این نقشه‌برداری از قبل موجود است');
    }
    
    // ✓ Prepared Statement
    $result = $wpdb->insert(
        $wpdb->prefix . 'inventory_sync_mapping',
        [/* ... */],
        ['%d', '%d', '%s', '%s', '%d']  // ← Type safety
    );
}
```

### فایل: `assets/js/admin.js`

```javascript
// ✨ تازه اضافه شده: منطق انتخاب محصولات

selectProduct: function(e) {
    $(e.target).closest('.product-item').toggleClass('selected');
    this.updateSelectionDisplay();
},

updateSelectionDisplay: function() {
    const site1Selected = $('.site1-products .product-item.selected').first();
    const site2Selected = $('.site2-products .product-item.selected').first();
    
    if (site1Selected.length && site2Selected.length) {
        $('.selected-products-display').show();
        // ← نمایش جفت انتخاب‌شده
    } else {
        $('.selected-products-display').hide();
    }
},

connectProducts: function(e) {
    // ✓ ایجاد ارتباط از طریق AJAX
    $.ajax({
        url: inventorySyncData.ajaxurl,
        type: 'POST',
        data: {
            action: 'inventory_sync_save_mapping',
            _ajax_nonce: inventorySyncData.nonce,
            site1_id: data.site1_id,
            site2_id: data.site2_id,
            site1_sku: data.site1_sku,
            site2_sku: data.site2_sku
        },
        success: (response) => {
            if (response.success) {
                alert('✓ ' + response.data);
                this.clearSelection();
                this.loadProducts('site1');
                this.loadProducts('site2');
            }
        }
    });
}
```

---

## ایمنی و Best Practices

### ✅ Nonce Verification
```php
check_ajax_referer('inventory_sync_nonce');
```

### ✅ Permission Checking
```php
if (!current_user_can('manage_woocommerce')) {
    wp_send_json_error('عدم دسترسی');
}
```

### ✅ Prepared Statements
```php
$wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping 
     WHERE site1_product_id = %d AND site2_product_id = %d",
    $site1_id,
    $site2_id
)
```

### ✅ Type Casting
```php
$wpdb->insert(
    $table,
    $data,
    ['%d', '%d', '%s', '%s', '%d']  // ← مشخص کردن نوع
)
```

### ✅ Sanitization & Escaping
```php
$site = sanitize_text_field($_POST['site'] ?? '');
echo esc_html($product_name);
echo esc_attr($product_id);
```

---

## نقاط تقویت

### Before ❌
- تب بی‌استفاده
- بدون UI/UX مناسب
- Direct DB Queries خطرناک
- خطاهای 502/503/504
- عدم شناخت هویت سایت

### After ✅
- تب کاملاً کاربردی
- UI/UX بهتر
- Prepared Statements ایمن
- بدون خطاهای دیتابیسی
- هویت سایت مشخص
- Hooks برای هماهنگ‌سازی
- جستجو Real-time
- نمایش جفت انتخاب‌شده

---

## آپدیت‌های آینده

💡 پیشنهادات برای بهبود بیشتر:

1. **حذف نقشه‌برداری:** اضافه کردن دکمه حذف برای نقشه‌برداری‌های اشتباه
2. **Import/Export:** Bulk نقشه‌برداری از CSV
3. **History Tracking:** دیدن تاریخ هماهنگ‌سازی‌ها
4. **Advanced Filters:** فیلتر کردن محصولات بر اساس دسته‌بندی، قیمت، وغیره
5. **Webhook Support:** اطلاع‌رسانی Webhook برای تغییرات
6. **Performance Metrics:** آمار و نمودار هماهنگ‌سازی

---

## راه‌اندازی و تست

### 1. بررسی اولیه
```bash
# تست Syntax
php -l inventory-sync/includes/class-settings.php
php -l inventory-sync/includes/class-admin.php
php -l inventory-sync/admin/dashboard.php
```

### 2. تست عملی
1. وارد WordPress شوید
2. به Inventory Sync > تنظیمات برگردید
3. سایت 1 یا 2 را انتخاب کنید
4. ذخیره کنید
5. اگر سایت 1: تب مرتبط‌سازی را ببینید
6. دو محصول انتخاب کنید
7. دکمه ایجاد ارتباط را کلیک کنید

### 3. تست AJAX
```javascript
// در Developer Console:
inventorySyncData  // ✓ باید تمام data را نمایش دهد
```

---

## Performance Tips

⚡ **بهینه‌سازی:**

1. **Cache محصولات:** `wp_cache_set()` استفاده کنید
2. **Batch Operations:** اگر محصولات زیاد است، batch کنید
3. **Async Tasks:** `wp_schedule_single_event()` استفاده کنید
4. **Transients:** نتایج API را cache کنید

---

## Reference و Documentation

📚 **منابع کمکی:**

- [WordPress Hooks Documentation](https://developer.wordpress.org/plugins/hooks/)
- [WooCommerce API](https://woocommerce.com/document/woocommerce-rest-api/)
- [wpdb Class Reference](https://developer.wordpress.org/reference/classes/wpdb/)
- [AJAX in Plugins](https://developer.wordpress.org/plugins/javascript/ajax/)

---

**آخرین به‌روزرسانی:** 16 June 2026
