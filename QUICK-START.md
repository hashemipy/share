# راهنمای فوری نصب

## چرا پلاگین‌ها شناخته نشده‌اند؟

### دلیل احتمالی:
پلاگین‌های شما احتمالاً **در پوشه‌ی صحیح نصب نشده‌اند**.

---

## حل سریع:

### برای سایت 1 (Master):

```bash
# فرض کنید WordPress در این مسیر است:
# /home/user/public_html/
# یا /var/www/html/

# کپی پوشه Master:
cp -r /vercel/share/v0-project/inventory-sync-master /home/user/public_html/wp-content/plugins/

# تغیر دسترسی (اگر لازم باشد):
chmod -R 755 /home/user/public_html/wp-content/plugins/inventory-sync-master/
```

### برای سایت 2 (Slave):

```bash
# فرض کنید WordPress در این مسیر است:
# /home/user/public_html2/

# کپی پوشه Slave:
cp -r /vercel/share/v0-project/inventory-sync-slave /home/user/public_html2/wp-content/plugins/

# تغیر دسترسی:
chmod -R 755 /home/user/public_html2/wp-content/plugins/inventory-sync-slave/
```

---

## تکمیل نصب:

1. **وارد داشبورد WordPress سایت 1 شوید**
2. **Plugins → Installed Plugins میروید**
3. **"Inventory Sync Master" را پیدا کنید**
4. **دکمه "Activate" را کلیک کنید**

همین کار را برای سایت 2 و "Inventory Sync Slave" تکرار کنید.

---

## بررسی نصب موفق:

### سایت 1:
- مینوی جدید "هماهنگ‌سازی انبار" ظاهر شود
- تب "مرتبط‌سازی محصولات" نمایش داده شود

### سایت 2:
- مینوی جدید "محصولات Slave" ظاهر شود
- تب "محصولات دریافت‌شده" نمایش داده شود

---

## اگر مشکل ادامه دارد:

### بررسی دسترسی‌ها:
```bash
ls -la /wp-content/plugins/inventory-sync-master/
ls -la /wp-content/plugins/inventory-sync-slave/
```

### بررسی error log WordPress:
```bash
tail -f /wp-content/debug.log
```

### بررسی PHP syntax:
```bash
php -l /wp-content/plugins/inventory-sync-master/inventory-sync-master.php
php -l /wp-content/plugins/inventory-sync-slave/inventory-sync-slave.php
```

---

## لازمه‌ها:
- ✅ WordPress 5.0+
- ✅ WooCommerce 5.0+
- ✅ PHP 7.4+
- ✅ دسترسی SFTP/SSH

