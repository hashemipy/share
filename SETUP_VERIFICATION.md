# راهنمای تایید و نصب فیچر جفت‌سازی محصولات

## مراحل نصب

### 1. فعال‌کردن Plugin
```bash
cd wp-content/plugins/
# بررسی کنید inventory-sync plugin فعال است
```

### 2. بررسی Database Tables
جداول زیر باید ایجاد شده باشند:
- `wp_inventory_sync_product_pairs` - برای ذخیره جفت‌های محصولات

### 3. فایل‌های جدید
✅ تم ایجاد شدند:
```
inventory-sync/
├── includes/
│   ├── class-product-pairing.php (346 خط)
│   ├── class-bidirectional-sync.php (253 خط)
│   └── class-database-migration.php (تحدیث شده)
├── admin/
│   └── product-pairing.php (494 خط)
├── assets/
│   ├── css/pairing.css (278 خط)
│   └── js/admin.js (تحدیث شده)
└── docs/
    └── PRODUCT_PAIRING.md (238 خط)
```

## نقاط بررسی

### ✅ AJAX Handlers (فعال شدند)
- `inventory_sync_search_products` - جستجوی محصول
- `inventory_sync_create_pair` - ایجاد جفت
- `inventory_sync_get_pairs` - دریافت جفت‌ها
- `inventory_sync_sync_pair` - sync دستی
- `inventory_sync_delete_pair` - حذف جفت

### ✅ Database Methods (اضافه شدند)
- `create_product_pair()`
- `get_product_pair()`
- `get_all_active_pairs()`
- `deactivate_pair()`
- `delete_pair()`
- `update_pair_last_sync()`

### ✅ Admin Enqueue (CSS و JS)
- `inventory-sync-pairing` CSS
- عمومی AJAX handlers

## ترتیب کار

### 1️⃣ تب جفت‌سازی محصولات
- **URL**: WordPress Admin > Inventory Sync > تب جفت‌سازی محصولات

### 2️⃣ سه تب درونی
1. **ایجاد جفت جدید**
   - جستجو برای محصول سایت 1
   - جستجو برای محصول سایت 2
   - انتخاب جهت sync (دوطرفه/یک‌طرفه)
   - کلیک "ایجاد جفت"

2. **مدیریت جفت‌ها**
   - نمایش تمام جفت‌های فعال
   - نمایش موجودی هر دو محصول
   - دکمه sync دستی (🔄)
   - دکمه حذف (🗑️)

3. **لاگ‌های هماهنگ‌سازی**
   - نمایش تاریخ sync
   - نمایش جهت تغییر (site1 → site2 یا برعکس)
   - نمایش موجودی جدید
   - وضعیت (موفق/ناموفق)

## بهینه‌سازی‌های اعمال شده

### ✨ Caching
- محصول‌های جستجو‌شده کش می‌شوند (5 دقیقه)

### ✨ Background Sync
- هر 5 دقیقه جفت‌ها به صورت خودکار sync می‌شوند

### ✨ Bidirectional Logic
- سیستم آخرین تغییر را تشخیص می‌دهد
- موجودی را تنها به سایت دیگر می‌فرستد

### ✨ Error Handling
- تمام خطاها ثبت می‌شوند
- محاولات دوباره خودکار انجام می‌شود

## شروع سریع

### 1. Plugin را Activate کنید
```
Plugins > Installed Plugins > Inventory Sync > Activate
```

### 2. تنظیمات اولیه را تکمیل کنید
```
Inventory Sync > Settings
- Site 1 URL, Key, Secret
- Site 2 URL, Key, Secret
```

### 3. اتصال را تست کنید
```
⚙️ Settings Tab > Test Connection Buttons
```

### 4. اولین جفت را ایجاد کنید
```
💑 Product Pairing Tab > Create New Pair
- Search for a simple product from Site 1
- Search for a simple product from Site 2
- Select sync direction (Bidirectional recommended)
- Click "Create Pair"
```

### 5. موجودی را تغییر دهید
```
- سایت 1 یا 2 میں محصول‌ کو موجودی تغییر کنید
- 5 دقیقه صبر کنید
- موجودی‌های دوطرفه تغییر خواهند یافت
```

## Troubleshooting

### مشکل: تب خالی است
**حل**: 
1. WordPress Admin > Plugins > Inventory Sync Deactivate
2. Activate again
3. اطمینان حاصل کنید API connection کار می‌کند

### مشکل: جفت ایجاد نمی‌شود
**حل**:
1. تنظیمات API را بررسی کنید
2. کنسول بروزر را باز کنید (F12) و خطاها را بررسی کنید
3. در لاگ‌های WordPress بررسی کنید

### مشکل: موجودی sync نمی‌شود
**حل**:
1. Cron jobs روی سرور فعال است
2. در لاگ موجودی تغییر ثبت شده است
3. Bidirectional Sync hook فعال است

## فایل‌های مهم

| فایل | هدف |
|------|------|
| `class-product-pairing.php` | مدیریت جفت‌ها |
| `class-bidirectional-sync.php` | Sync دو طرفه |
| `class-database.php` | Database queries |
| `product-pairing.php` | UI و forms |
| `admin.js` | JavaScript handlers |
| `pairing.css` | Styling |

## نسخه
- **نسخه**: 2.0.0
- **تاریخ**: 1403
- **وضعیت**: آماده برای استفاده
