# Inventory Sync Pro

پلاگین هماهنگ‌سازی موجودی محصولات بین دو سایت WooCommerce

## 📋 مشخصات

- **نام پلاگین**: Inventory Sync Pro
- **نسخه**: 1.0.0
- **نیاز به WooCommerce**: 4.0+
- **نیاز به PHP**: 7.4+
- **زبان**: فارسی + انگلیسی

## ✨ ویژگی‌ها

### 1️⃣ تب تنظیمات
- تنظیم اطلاعات سایت 1 و 2
- آدرس سایت، API Key و Secret
- تست اتصال API
- تعیین جهت هماهنگ‌سازی (یک‌طرفه یا دوطرفه)
- فعال‌سازی هماهنگ‌سازی خودکار

### 2️⃣ تب مرتبط‌سازی محصولات
- نمایش محصولات سایت 1 و 2 در کنار هم
- انتخاب و مرتبط‌کردن محصولات
- هماهنگ‌سازی موجودی برای محصولات مرتبط‌شده
- جستجو در محصولات

### 3️⃣ تب انتقال محصولات
- انتقال محصول به‌صورت انتخابی
- انتقال تمام محصولات
- نمایش وضعیت هر محصول
- انتقال عکس‌ها و مشخصات
- پیش‌نویس خودکار (برای بررسی)

### 4️⃣ تب لاگ‌ها
- مشاهده تمام عملیات sync و transfer
- فیلترکردن بر اساس تاریخ
- مشاهده پیام‌های خطا
- صادرات لاگ‌ها

## 🚀 نحوه نصب و استفاده

**👉 برای شروع سریع: [QUICK-START-FA.md](QUICK-START-FA.md)**

### مرحله 1: نصب پلاگین

1. فولدر `inventory-sync` را در `/wp-content/plugins/` کپی کنید
2. به بخش **پلاگین‌ها** در WordPress بروید
3. پلاگین **Inventory Sync Pro** را فعال کنید

### مرحله 2: تنظیمات API

⚙️ **دریافت API Keys:**

سایت 1:
```
داشبورد → WooCommerce → Settings → Advanced → REST API
Create an API Key:
├─ Description: Inventory Sync
├─ Permissions: Read/Write
├─ Copy: Consumer Key
└─ Copy: Consumer Secret
```

پلاگین میں وارد کنید:
```
WooCommerce → Inventory Sync → ⚙️ تنظیمات
├─ سایت 1 آدرس: https://site1.com
├─ سایت 1 API Key: ck_xxx
├─ سایت 1 API Secret: cs_xxx
└─ [🔗 تست اتصال] → ✓ موفق
```

سایت 2: (همان مراحل)

### مرحله 3: انتقال محصولات

```
Inventory Sync → 📤 انتقال محصولات
├─ [ ☑️ انتخاب همه ]
└─ [📤 انتقال همه محصولات]

نوار پیشرفت:
Transferring 45/200 ████░░░░░░ 22%
```

### مرحله 4: مرتبط‌سازی

```
Inventory Sync → 🔗 مرتبط‌سازی
├─ محصول سایت 1 را کلیک کنید
└─ محصول سایت 2 را انتخاب کنید
└─ ✓ مرتبط شدند
```

### مرحله 5: فعال‌سازی خودکار

```
Inventory Sync → ⚙️ تنظیمات → تنظیمات پیشرفته
├─ ☑️ فعال‌سازی هماهنگ‌سازی خودکار
└─ [💾 ذخیره]

✓ از الان موجودی خودکار به‌روز می‌شود

برای هر کدام از سایت‌ها باید API Credentials تهیه کنید:

#### در سایت WooCommerce:
1. به **WooCommerce > تنظیمات > Advanced > REST API** بروید
2. روی **ایجاد Credentials** کلیک کنید
3. نام را وارد کنید (مثل: Inventory Sync)
4. **User** را بر روی یک administrator تنظیم کنید
5. Permissions را روی **Read/Write** تنظیم کنید
6. **Generate API Credentials** را کلیک کنید
7. **Consumer Key** و **Consumer Secret** را کپی کنید

### مرحله 3: تنظیم سایت‌ها

1. به **Inventory Sync > تنظیمات** بروید
2. **اطلاعات سایت 1** را وارد کنید:
   - نام سایت
   - آدرس (https://site1.com)
   - API Key
   - API Secret
3. **اطلاعات سایت 2** را وارد کنید
4. برای هر سایت **تست اتصال** را کلیک کنید
5. **ذخیره تنظیمات** را کلیک کنید

### مرحله 4: مرتبط‌سازی محصولات

1. به **Inventory Sync > مرتبط‌سازی محصولات** بروید
2. محصول در سایت 1 و سایت 2 را که مطابق هستند انتخاب کنید
3. در صفحه نقشه‌برداری، دو محصول را کنار هم قرار دهید
4. **هماهنگ‌سازی موجودی** را کلیک کنید

### مرحله 5: انتقال محصولات

اگر می‌خواهید همه محصولات سایت 1 را به سایت 2 منتقل کنید:

1. به **Inventory Sync > انتقال محصولات** بروید
2. محصولات مورد نظر را انتخاب کنید
3. **انتقال محصولات انتخاب شده** یا **انتقال همه** را کلیک کنید
4. منتظر تکمیل عملیات باشید
5. محصولات در سایت 2 به‌صورت **پیش‌نویس** ایجاد می‌شوند

> **نکته**: محصولات منتقل‌شده پیش‌نویس هستند. شما باید آن‌ها را بررسی و منتشر کنید.

### مرحله 6: فعال‌سازی هماهنگ‌سازی خودکار

1. به **Inventory Sync > تنظیمات** بروید
2. گزینه **فعال‌سازی هماهنگ‌سازی خودکار** را فعال کنید
3. **ذخیره** را کلیک کنید

اکنون موجودی به‌صورت خودکار هر 5 دقیقه بروز می‌شود.

## 🔐 امنیت

- تمام API Credentials رمزشده ذخیره می‌شود
- Nonce validation برای تمام AJAX requests
- بررسی دسترسی (capability checks)
- Sanitization و Validation برای تمام ورودی‌ها

## 📊 ساختار دیتابیس

### جدول 1: `wp_inventory_sync_mapping`
ذخیره رابطه‌بندی بین محصولات:

```
id: شناسه
site1_product_id: شناسه محصول سایت 1
site2_product_id: شناسه محصول سایت 2
site1_sku: کد SKU سایت 1
site2_sku: کد SKU سایت 2
sync_enabled: آیا sync فعال است
last_sync: آخرین sync
sync_status: pending/synced/error
error_message: پیام خطا
created_at: تاریخ ایجاد
updated_at: تاریخ تغییر
```

### جدول 2: `wp_inventory_sync_logs`
ثبت تمام عملیات:

```
id: شناسه
product_id: شناسه محصول
product_name: نام محصول
action: sync_inventory/transfer_product
source_site: سایت مبدا
target_site: سایت مقصد
old_value: مقدار قبل
new_value: مقدار بعد
status: success/failed/pending
error_message: پیام خطا
created_at: تاریخ
```

## 🛠️ REST API Endpoints

پلاگین از API های WooCommerce استفاده می‌کند:

- `GET /wp-json/wc/v3/products` - دریافت محصولات
- `GET /wp-json/wc/v3/products/{id}` - دریافت یک محصول
- `POST /wp-json/wc/v3/products` - ایجاد محصول
- `PUT /wp-json/wc/v3/products/{id}` - بروزرسانی محصول

## 🔄 Hooks و Filters

### Hooks

```php
// وقتی محصول فروخته شود
do_action('inventory_sync_on_product_sold', $order);

// برای sync برنامه‌ریزی شده
do_action('inventory_sync_cron_hook');
```

### Filters

```php
// برای تغییر verify SSL
apply_filters('inventory_sync_verify_ssl', true);
```

## 📖 مستندات تکمیلی

### شروع سریع
- **[QUICK-START-FA.md](QUICK-START-FA.md)** - 5 دقیقه برای شروع
  - مراحل اساسی
  - علامت سوالات و مودال‌ها
  - عیب‌یابی سریع

### راهنمای کامل
- **[SETUP-GUIDE-FA.md](SETUP-GUIDE-FA.md)** - راهنمای دقیق
  - نحوه کار سیستم
  - دریافت API Keys
  - انتقال و مرتبط‌سازی
  - هماهنگ‌سازی خودکار
  - سناریوهای عملی

### معماری فنی
- **[ARCHITECTURE-FA.md](ARCHITECTURE-FA.md)** - برای توسعه‌دهندگان
  - نمای کلی سیستم
  - کامپوننت‌ها و کلاس‌ها
  - جریان Cron و hooks
  - ساختار دیتابیس

### سؤالات متداول
- **[FAQ-FA.md](FAQ-FA.md)** - مشکلات و حل‌ها

---

## 💡 نکات مهم

1. **API Credentials**: هرگز API Credentials را علنی نکنید
2. **Backup**: قبل از انتقال بزرگ محصولات، backup بگیرید
3. **Testing**: ابتدا با چند محصول تست کنید
4. **Timezone**: از timezone یکسان در دو سایت استفاده کنید
5. **Performance**: برای سایت‌های بزرگ، sync را شب تنظیم کنید
6. **مودال‌های کمکی**: علامت `?` کنار هر فیلد را کلیک کنید برای راهنمایی

## 🐛 عیب‌یابی

### اتصال ناموفق
- آدرس سایت را بررسی کنید
- API Key و Secret را دوبار چک کنید
- فایروال یا امنیت سایت را بررسی کنید

### محصولات منتقل نمی‌شوند
- لاگ‌ها را در تب لاگ‌ها بررسی کنید
- اطمینان یابید که API active است
- Permissions را بررسی کنید

### موجودی sync نمی‌شود
- نقشه‌برداری محصولات را بررسی کنید
- خودکار sync را فعال کنید
- از WordPress Cron استفاده کنید

## 📚 فایل‌ها

```
inventory-sync/
├── inventory-sync.php              # فایل اصلی پلاگین
├── README.md                        # این فایل
├── includes/
│   ├── class-loader.php            # خودکار loading کلاس‌ها
│   ├── class-plugin.php            # کلاس اصلی پلاگین
│   ├── class-database.php          # عملیات دیتابیس
│   ├── class-settings.php          # مدیریت تنظیمات
│   ├── class-api.php               # API communication
│   ├── class-sync-manager.php      # منطق sync و transfer
│   └── class-admin.php             # صفحات مدیریت
├── admin/
│   └── dashboard.php               # داشبورد مدیریت
├── assets/
│   ├── css/
│   │   └── admin.css               # استایل‌های مدیریت
│   └── js/
│       └── admin.js                # JavaScript مدیریت
└── languages/
    └── inventory-sync-fa_IR.po     # ترجمه فارسی
```

## 📞 پشتیبانی

برای کمک و پشتیبانی:
- به لاگ‌ها مراجعه کنید
- WordPress Debug Mode را فعال کنید
- لاگ‌های پلاگین را بررسی کنید

## 📄 مجوز

این پلاگین تحت مجوز GPL v2 یا بالاتر است.

---

**نسخه**: 1.0.0  
**آخرین بروزرسانی**: 2024
