# راهنمای نصب سیستم هماهنگ‌سازی انبار

## نیازمندی‌ها
- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- دسترسی SFTP/FTP یا SSH

## مرحله ۱: نصب پلاگین Master (سایت 1)

### مسیر نصب:
```
wp-content/plugins/inventory-sync-master/
```

### فایل‌های مورد نیاز:
```
inventory-sync-master/
├── inventory-sync-master.php (فایل اصلی)
├── includes/
│   ├── class-master-settings.php
│   ├── class-master-api.php
│   ├── class-master-admin.php
│   ├── class-master-sync.php
│   └── class-master-webhook.php
├── admin/
│   └── dashboard-master.php
└── assets/
    ├── js/
    │   └── admin-master.js
    └── css/
        └── admin-master.css
```

### مراحل نصب:
1. فایل `inventory-sync-master.php` را در `/wp-content/plugins/inventory-sync-master/` کپی کنید
2. تمام فایل‌های `includes/`, `admin/`, `assets/` را به همان پوشه منتقل کنید
3. از داشبورد WordPress وارد شوید
4. به "پلاگین‌ها" بروید و "Inventory Sync Master" را فعال کنید

## مرحله ۲: نصب پلاگین Slave (سایت 2)

### مسیر نصب:
```
wp-content/plugins/inventory-sync-slave/
```

### فایل‌های مورد نیاز:
```
inventory-sync-slave/
├── inventory-sync-slave.php (فایل اصلی)
├── includes/
│   ├── class-slave-settings.php
│   ├── class-slave-receiver.php
│   ├── class-slave-admin.php
│   └── class-slave-sync.php
├── admin/
│   └── dashboard-slave.php
└── assets/
    ├── js/
    │   └── admin-slave.js
    └── css/
        └── admin-slave.css
```

### مراحل نصب:
1. فایل `inventory-sync-slave.php` را در `/wp-content/plugins/inventory-sync-slave/` کپی کنید
2. تمام فایل‌های `includes/`, `admin/`, `assets/` را به همان پوشه منتقل کنید
3. از داشبورد WordPress سایت 2 وارد شوید
4. به "پلاگین‌ها" بروید و "Inventory Sync Slave" را فعال کنید

## مرحله ۳: تنظیمات اولیه

### در سایت 1:
1. به "هماهنگ‌سازی انبار" → "تنظیمات" بروید
2. اطلاعات سایت 2 را وارد کنید:
   - **آدرس سایت 2:** https://site2.com
   - **API Key:** (کلیدی که از سایت 2 توليد می‌شود)
   - **API Secret:** (رازی که از سایت 2 توليد می‌شود)
3. تست کنید: دکمه "تست اتصال" را بزنید

### در سایت 2:
1. به "محصولات" → "تنظیمات هماهنگ‌سازی" بروید
2. درصد تغییر قیمت را تعیین کنید (مثال: 10%)
3. API Key و Secret را اینجا نیز جهت احراز هویت تنظیم کنید

## مرحله ۴: استفاده

### ارسال محصولات از سایت 1:
1. به "هماهنگ‌سازی انبار" → "مرتبط‌سازی محصولات" بروید
2. محصولات خود را انتخاب کنید
3. دکمه "ارسال به سایت 2" را کلیک کنید
4. محصولات در سایت 2 ظاهر می‌شوند

### مراقبت موجودی:
- وقتی محصولی در سایت 2 فروخته شود، موجودی خودکار بروز می‌شود
- سایت 1 از طریق Webhook اطلاع پیدا می‌کند
- موجودی در سایت 1 کاهش می‌یابد

## حل مشکلات

### پلاگین‌ها تشخیص داده نشدند:
- مسیر فایل‌ها را بررسی کنید
- `inventory-sync-master.php` و `inventory-sync-slave.php` باید در root پوشه پلاگین باشند
- دکمه "بارگذاری مجدد پلاگین‌ها" را بزنید

### خطا در اتصال:
- API Key و Secret را دوباره بررسی کنید
- آدرس URL صحیح باشد
- Firewall یا محدودیت‌های دسترسی را بررسی کنید

### محصولات منتقل نشده‌اند:
- WooCommerce فعال است؟
- محصولات "منتشر شده" هستند؟
- اطلاعات سایت 2 تنظیم شدند؟
