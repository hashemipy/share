# خلاصه حل مشکلات Ping-Pong و Cron

## مشکلات حل‌شده

### 1. Ping-Pong Bug (بازگشت موجودی)
**مشکل:** موجودی سایت 1 تغییر می‌یافت، اما بعد از 2 دقیقه دوباره به مقدار سایت 2 برمی‌گشت.

**علت:** سیستم یک‌طرفه بود و هنگام sync دوطرفه، موجودی هر دو سایت با یکدیگر conflict داشتند.

**حل:**
- اضافه شدن ستون `last_change_site` برای ردیابی منبع تغیر
- اضافه شدن ستون `last_change_timestamp` برای بررسی زمان تغیر
- منطق جدید: تنها سایتی که تغیر را شروع کرد می‌تواند آپدیت‌های خود را بفرستد
- اگر تغیر اخیر از سایت دیگر بود، صبر می‌کنید تا lock آزاد شود

### 2. Cron چندبار اجرا شدن (Lock System)
**مشکل:** گاهی cron job چندین بار برای یک mapping اجرا می‌شد و sync تکرار می‌شد.

**حل:**
- اضافه شدن ستون `is_processing` و `lock_until`
- هنگام شروع sync: `lock_mapping()` فراخوانی می‌شود (30 ثانیه قفل)
- اگر جفت محصول قفل باشد: درخواست نادیده گرفته می‌شود
- بعد از تکمیل: `unlock_mapping()` فراخوانی می‌شود

### 3. نبود اعلان و ردیابی
**مشکل:** کاربر نمی‌دانست چه تغییری شده و چرا موجودی دوباره برگشت.

**حل:**
- اضافه شدن ستون `sync_status_message` برای پیغام‌های واضح
- ایجاد جدول `wp_inventory_sync_attempts` برای ردیابی تمام تلاش‌های sync
- صفحه جدید "وضعیت نقشه‌برداری" در admin برای نمایش:
  - وضعیت هر جفت محصول (موفق/خرابی/در انتظار)
  - آخرین تغیر (کدام سایت و چند دقیقه پیش)
  - موجودی فعلی
  - دکمه "دوباره تلاش" برای مورد‌های خرابی

## تغییرات کد

### 1. `class-database.php`
- **ستون‌های جدید:**
  - `last_change_site` - کدام سایت تغیر را شروع کرد
  - `last_change_timestamp` - زمان تغیر
  - `last_change_stock` - موجودی جدید
  - `is_processing` - آیا در حال sync است؟
  - `lock_until` - تا کی قفل است؟
  - `sync_status_message` - پیغام وضعیت

- **متد‌های جدید:**
  - `lock_mapping()` - قفل کردن یک mapping برای 30 ثانیه
  - `unlock_mapping()` - آزاد کردن یک mapping
  - `is_mapping_locked()` - بررسی اینکه آیا قفل است
  - `get_last_change_info()` - دریافت معلومات آخرین تغیر
  - `add_sync_attempt_log()` - ثبت تلاش‌های sync
  - `get_recent_failed_attempts()` - شمارش تلاش‌های ناموفق

### 2. `class-sync-manager.php`
- **متد `sync_inventory()`:**
  - بررسی lock قبل از sync
  - اگر قفل بود: skip کن
  - اگر 5+ تلاش ناموفق: متوقف کن
  - Lock کردن mapping در شروع sync
  - Unlock کردن mapping بعد از تکمیل

- **متد `sync_site_to_site()`:**
  - بررسی `last_change_site` - اگر تغیر اخیر از سایت دیگر بود: skip
  - فقط یک‌سمت را sync کن (نه هر دو سمت)
  - Update کردن `last_change_site` و `last_change_timestamp`
  - Exception handling برای بهتر بودن error messages

### 3. `class-admin.php`
- **منوی جدید:** "وضعیت نقشه‌برداری"
- **AJAX handler:** `ajax_manual_retry()`
  - کاربر می‌تواند دستی برای یک mapping retry کند
  - Unlock کردن mapping و دوباره قرار دادن در queue

### 4. `mapping-page.php` (فایل جدید)
- جدول تمام mappingها
- نمایش وضعیت هر جفت
- نمایش آخرین تغیر (کدام سایت و چه زمانی)
- نمایش موجودی فعلی
- دکمه "دوباره تلاش" برای موارد خرابی

## نحوه کار سیستم جدید

### سناریو 1: تغیر موجودی در سایت 1
```
1. موجودی سایت 1: 10 → 5
2. Hook: `woocommerce_product_set_stock` فعال می‌شود
3. Cron job: `inventory_sync_mapping` قرار می‌دهد
4. Sync شروع: 
   - Lock: is_processing=1, lock_until=+30s, last_change_site='site1'
   - Sync: موجودی سایت 2 = 5
5. Sync تمام:
   - Unlock: is_processing=0, sync_status='synced'
6. اگر سایت 2 بخواهد sync کند: تغیر اخیر از site1 است → SKIP
```

### سناریو 2: Ping-Pong Prevention
```
1. موجودی سایت 1: 10 → 5 (تغیر از سایت 1)
2. Lock: last_change_site='site1', lock_until=+30s
3. Sync: سایت 2 موجودی = 5
4. اگر Cron برای سایت 2 → سایت 1 اجرا شود:
   - Check: last_change_site='site1' و تاریخ < 30s
   - Result: SKIP (منتظر 30 ثانیه)
```

### سناریو 3: خرابی و Retry
```
1. Sync شروع: lock
2. خطا: API سایت 2 پاسخ نمی‌دهد
3. Unlock: sync_status='error', sync_status_message='خطا در سایت 2'
4. Log: add_sync_attempt_log() - فشار ناموفق
5. کاربر: صفحه "وضعیت نقشه‌برداری" → دکمه "دوباره تلاش"
6. دوباره تلاش: unlock + re-queue
```

## نتایج بهبود

| مشکل | قبل | بعد |
|------|------|------|
| Ping-Pong | می‌شد | نمی‌شود |
| Cron تکرار | می‌تواند تکرار شود | فقط 1 بار با lock |
| اعلان کاربر | ندارد | صفحه جزئیات + retry |
| تلاش‌های ناموفق | ۳ بار retry | ۵ دقیقه، max 5 بار |
| Time to Resolve | - | 30 ثانیه معمولی |

## توصیه‌های استفاده

1. **تنظیم Cron:** بهتر است cron هر ۵ دقیقه اجرا شود (نه هر دقیقه)
2. **بررسی منظم:** به صفحه "وضعیت نقشه‌برداری" مراجعه کنید
3. **Retry دستی:** اگر 5 تلاش ناموفق شد، retry دستی را امتحان کنید
4. **Debug:** لاگها را از صفحه "لاگ‌ها" چک کنید برای اطلاعات دقیق‌تر
