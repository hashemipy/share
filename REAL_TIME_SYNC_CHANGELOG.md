# تغییرات اصلی - حل مشکل Sync خودکار موجودی

## مشکل اصلی
- موجودی محصولات بین سایت‌ها هماهنگ نمی‌شد
- حتی اگر موجودی تغییر می‌کرد، سایت دوم به‌روز نمی‌شد
- تب "مرتبط‌سازی محصولات" فقط دستی کار می‌کرد

## ریشه‌ی مشکل
1. **وابستگی به WordPress Cron** - wp_schedule_single_event
2. **WordPress Cron معطل است** در اکثریت سرورها (خصوصاً shared hosting)
3. **Hooks ناکافی** برای تمام سناریوهای تغییر موجودی

## راه‌حل اعمال‌شده

### 1. حذف وابستگی به Cron
- تمام `wp_schedule_single_event` حذف شده
- تمام `wp_schedule_event` حذف شده
- سیستم حالا **فوری و همزمان** (Synchronous) است

### 2. اضافه کردن Hooks جدید
```php
// وقتی موجودی محصول ساده تغییر کند
add_action('woocommerce_product_set_stock', [$this, 'sync_on_stock_change'], 10, 1);

// وقتی موجودی واریاسیون تغییر کند
add_action('woocommerce_variation_set_stock', [$this, 'sync_on_stock_change'], 10, 1);

// وقتی محصول ذخیره/ویرایش شود (تغییر دستی موجودی در ادمین)
add_action('woocommerce_update_product', [$this, 'sync_on_product_update'], 20, 1);

// وقتی محصول فروخته شود (خرید و stock reduction)
add_action('woocommerce_reduce_order_stock', [$this, 'on_product_sold'], 10, 1);

// وقتی محصول برگردانده شود (restore stock)
add_action('woocommerce_restore_order_stock', [$this, 'on_product_sold'], 10, 1);

// وقتی status سفارش تغییر کند
add_action('woocommerce_order_status_changed', [$this, 'on_order_status_change'], 10, 3);
```

### 3. متدهای جدید
- `sync_on_stock_change()` - برای محصولات و واریاسیون‌ها
- `sync_on_product_update()` - برای تغییرات دستی
- `on_order_item_quantity_change()` - وقتی quantity سفارش تغییر کند
- `on_product_sold()` - وقتی محصول فروخته شود
- `on_order_status_change()` - وقتی status سفارش تغییر کند

### 4. عملکرد Bidirectional
- دعم کامل برای sync دوطرفه
- محصولات هر دو سایت حالا می‌تواند موجودی را تغییر دهد
- تغییر در هر کدام از سایت‌ها **فوری** به سایت دیگر منتقل می‌شود

## تست کردن

### سناریو 1: تغییر دستی موجودی
1. محصول را در سایت 1 باز کنید
2. موجودی را تغییر دهید → **باید فوری در سایت 2 به‌روز شود**

### سناریو 2: خرید محصول
1. سفارشی در سایت 1 ایجاد کنید
2. محصول در سفارش موجودی داشته باشد
3. سفارش را کامل کنید → **موجودی باید فوری کاهش یابد و در سایت 2 sync شود**

### سناریو 3: تغییر واریاسیون
1. محصول متغیّر را باز کنید
2. یک واریاسیون را انتخاب کنید
3. موجودی آن را تغییر دهید → **باید فوری sync شود**

### سناریو 4: برگرداندن محصول
1. سفارشی را باز کنید
2. Status را به "refunded" یا "cancelled" تغییر دهید
3. موجودی باید restore شود و **فوری sync شود**

## فیلز مهم
- ⭐ حالا **sync خودکار کامل‌اً بدون Cron** کار می‌کند
- ⭐ **تمام تغییرات فوری** هستند
- ⭐ **دوطرفه** کار می‌کند
- ⭐ **محصولات ساده و متغیّر** پشتیبانی می‌شوند

## نسخه مورد بروزرسانی
- Version: 1.2.0
- تاریخ: 15/6/2026

## فایل‌های تغییریافته
- `includes/class-sync-manager.php` - تمام hooks فوری
- `includes/class-plugin.php` - حذف Cron و سادگی کردن

---

**حالا سیستم آماده است برای استفاده در production! 🚀**
