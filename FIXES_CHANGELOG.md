# تغییرات اصلاحی - نسخه 1.1

## خلاصه مشکلات حل‌شده

### ❌ **مشکل 1: ناموفق بودن انتقال محصول‌های حذف‌شده**
- **علت**: وقتی محصولی را از سایت 2 حذف می‌کردید و دوباره ارسال می‌کردید، سیستم دوباره ایجاد نمی‌کرد
- **حل**: 
  - اضافه کردن logic برای شناسایی محصول‌های حذف‌شده
  - پاک کردن mapping قدیمی و ایجاد محصول جدید
  - Idempotent کردن فرایند انتقال

### ❌ **مشکل 2: Retry Logic شکسته**
- **علت**: مقدار `error_message` برای شمارش تلاش‌ها استفاده می‌شد (یک string، نه عدد)
- **حل**:
  - اضافه کردن ستون `retry_count` به جدول
  - استفاده صحیح از این ستون برای شمارش تلاش‌ها (تا 3 بار)
  - صحیح‌تر ذخیره کردن error messages

### ❌ **مشکل 3: Duplicate Mapping اگر دوباره ارسال شود**
- **علت**: `save_mapping` هر بار insert می‌کرد، و اگر mapping موجود بود error میدهد
- **حل**:
  - Upsert logic (INSERT OR UPDATE)
  - بررسی mapping قبلی قبل از insert
  - پاک کردن error flag وقتی موفق شود

### ❌ **مشکل 4: Cache برای ویژگی‌های سایت 2 نه‌تنها مشکل می‌آفریند**
- **علت**: cache را به صورت دستی پاک می‌کردند ولی guarantee نبود
- **حل**:
  - پاک کردن cache در هر موقعیت (موفقیت، خطا، تکرار)

---

## تغییرات Detailed

### فایل: `includes/class-database.php`
```
✅ اضافه کردن `retry_count INT DEFAULT 0` ستون
```

### فایل: `includes/class-sync-manager.php`

#### 1️⃣ بهبود Retry Logic (خطوط 271-308)
```php
// Before (❌ غلط):
$retry_count = intval($mapping->error_message ?? 0) + 1;

// After (✅ صحیح):
$retry_count = intval($mapping->retry_count ?? 0) + 1;
if ($retry_count < 4) {
    // تلاش مجدد
    wp_schedule_single_event(...);
    $wpdb->update(..., ['retry_count' => $retry_count, ...]);
}
```

#### 2️⃣ Idempotent Transfer Logic (خطوط 410-596)
```php
// ✅ بررسی mapping قبلی
$existing_mapping = $wpdb->get_row("SELECT * FROM ... WHERE site1_product_id = %d");

// ✅ اگر mapping موجود است و محصول حذف‌شده است
if ($existing_mapping && !$this->site2_api->get_product($existing_mapping->site2_product_id)) {
    $wpdb->delete(...);  // پاک کردن mapping قدیمی
}

// ✅ دوباره تلاش برای ایجاد محصول جدید
```

#### 3️⃣ بهبود Sync Success (خطوط 336-347)
```php
// ✅ بروز کردن status به synced + reset retry_count
$wpdb->update(..., [
    'sync_status' => 'synced',
    'retry_count' => 0,
    'error_message' => ''
]);
```

#### 4️⃣ بهبود save_mapping (خطوط 1259-1300)
```php
// ✅ بررسی mapping موجود
$existing = $wpdb->get_row("SELECT id FROM ... WHERE site1_product_id = %d AND site2_product_id = %d");

if ($existing) {
    // اپدیت موجود
    $wpdb->update(...);
} else {
    // ایجاد جدید
    $wpdb->insert(...);
}
```

### فایل: `includes/class-database-migration.php` (NEW)
```php
✅ Migration utility برای اضافه کردن retry_count column
✅ Safe migration که دو بار نمی‌افتد
```

### فایل: `inventory-sync.php`
```php
✅ اجرا کردن migrations در activation hook
```

---

## چگونگی استفاده

### نصب
فقط پلاگین را به‌روز کنید. Activation hook خودکار migrations را اجرا می‌کند.

### تست کردن بهبود‌ها
1. یک محصول از سایت 1 را ارسال کنید
2. محصول را از سایت 2 حذف کنید
3. دوباره همان محصول را ارسال کنید
   - ✅ حالا باید موفق شود
   - ✅ لاگ‌های جدید را بررسی کنید

### Monitoring
- لاگ جدول: `wp_inventory_sync_logs`
- Mapping جدول: `wp_inventory_sync_mapping` - ستون `retry_count` و `sync_status` را بررسی کنید

---

## نسخه Changelog

### v1.1.0 - 2026-06-15
- ✅ اصلاح مسئله انتقال محصول‌های حذف‌شده
- ✅ اصلاح Retry Logic شکسته
- ✅ Idempotent کردن انتقال محصول
- ✅ اضافه کردن `retry_count` ستون
- ✅ بهبود Cache Management
- ✅ اضافه کردن Database Migration System

