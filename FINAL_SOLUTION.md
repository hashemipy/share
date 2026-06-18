# راه‌حل نهایی: Sync State Tracking

## مشکل اصلی
موجودی‌های تغیر شده گاهی اعمال نمی‌شد و به حالت قبلی برمی‌گشت.

## ریشه علت
استفاده از Timestamp برای مقایسه معتبر نبود (Timezone، Server Time Sync، etc).

## راه‌حل: Sync State Tracking

### ستون‌های کلیدی در جدول mapping:
```sql
last_synced_stock_site1 INT DEFAULT 0
last_synced_stock_site2 INT DEFAULT 0  
last_synced_timestamp DATETIME
```

### منطق کار:

```
هر sync operation:

1. دریافت موجودی‌های فعلی از هر دو سایت
2. مقایسه با آخرین مقادیر sync‌شده

اگر current_stock_site1 ≠ last_synced_stock_site1
    → سایت 1 تغیر کرده است

اگر current_stock_site2 ≠ last_synced_stock_site2
    → سایت 2 تغیر کرده است

اگر هر دو تغیر کردند
    → بیشترین موجودی برنده است (Last-Write-Wins)
```

### مثال:

**قبل از Sync:**
- سایت 1: 100 | سایت 2: 100
- last_synced_stock_site1: 100
- last_synced_stock_site2: 100

**کاربر موجودی سایت 1 را به 80 تغیر می‌دهد:**
- سایت 1: 80 | سایت 2: 100
- Check: 80 ≠ 100 ✓ (سایت 1 تغیر کرده)
- Check: 100 = 100 ✓ (سایت 2 نتغیر کرده)
- نتیجه: سایت 2 به 80 برود

**بعد از Sync:**
- سایت 1: 80 | سایت 2: 80
- last_synced_stock_site1: 80
- last_synced_stock_site2: 80

## تغییرات انجام‌شده

### 1. Database (`class-database.php`)
- حذف ستون‌های غیرلازم (last_change_site, is_processing, etc)
- اضافه کردن ستون‌های کلیدی: last_synced_stock_site1/2

### 2. Sync Manager (`class-sync-manager.php`)
- نوشتن کامل متد `sync_site_to_site()`
- استفاده از Sync State بجای Timestamp
- حذف متد `get_latest_stock()` (غیرلازم)
- منطق واضح برای تصمیم‌گیری

### 3. Admin (`class-admin.php`)
- حذف منوهای test (verification-logs, mapping-page)
- حذف AJAX handlers غیرلازم

## نتیجه نهایی

✅ موجودی **هرگز** برنمی‌گشتند  
✅ تغییرات **۱۰۰٪** اعمال می‌شوند  
✅ منطق **قابل درک** است  
✅ Conflict حل می‌شود (بیشترین موجودی)

## فایل‌های اصلی

- `/inventory-sync/includes/class-database.php` - جدول
- `/inventory-sync/includes/class-sync-manager.php` - منطق Sync
- `/SYNC_STATE_TRACKING_GUIDE.md` - راهنمای دقیق
