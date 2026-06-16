# خلاصه و وضعیت نصب

## ✅ چه کاری انجام شد:

### 1. **دو پلاگین جداگانه ایجاد شدند:**

**📦 Inventory Sync Master (سایت 1):**
```
/vercel/share/v0-project/inventory-sync-master/
├── inventory-sync-master.php (فایل اصلی با Plugin Header درست)
├── includes/
│   ├── class-master-settings.php
│   ├── class-master-api.php
│   ├── class-master-admin.php
│   ├── class-master-sync.php
│   └── class-master-webhook.php
├── admin/
│   └── dashboard-master.php
└── assets/
    ├── js/admin-master.js
    └── css/admin-master.css
```

**📦 Inventory Sync Slave (سایت 2):**
```
/vercel/share/v0-project/inventory-sync-slave/
├── inventory-sync-slave.php (فایل اصلی با Plugin Header درست)
├── includes/
│   ├── class-slave-settings.php
│   ├── class-slave-receiver.php
│   ├── class-slave-admin.php
│   └── class-slave-sync.php
├── admin/
│   └── dashboard-slave.php
└── assets/
    ├── js/admin-slave.js
    └── css/admin-slave.css
```

### 2. **اصلاحات انجام شده:**
- ✅ Plugin Header صحیح اضافه شد (WordPress شناسایی می‌کند)
- ✅ Error checking و debug برای دسترسی فایل‌ها
- ✅ Proper constant definitions (INVENTORY_SYNC_MASTER_DIR, etc.)
- ✅ Hooks برای activation/deactivation

### 3. **مستندات:**
- ✅ `QUICK-START.md` - راهنمای فوری نصب
- ✅ `INSTALLATION-GUIDE.md` - دستورالعمل کامل
- ✅ `README.md` - معرفی پلاگین‌ها

---

## 🚀 مراحل نصب:

### **سایت 1:**
```bash
# کپی پوشه Master به Plugins:
cp -r /vercel/share/v0-project/inventory-sync-master /wp-content/plugins/

# یا از طریق SFTP/SSH این مسیر را کپی کنید
```

### **سایت 2:**
```bash
# کپی پوشه Slave به Plugins:
cp -r /vercel/share/v0-project/inventory-sync-slave /wp-content/plugins/

# یا از طریق SFTP/SSH این مسیر را کپی کنید
```

### **در داشبورد WordPress:**
1. Plugins → Installed Plugins
2. "Inventory Sync Master" یا "Inventory Sync Slave" را فعال کنید
3. منو جدید در بخش مدیریت ظاهر می‌شود

---

## 📋 ویژگی‌های سیستم:

### **Master (سایت 1):**
- مدیریت محصولات ساده و متغیر
- ارسال یک‌کلیکی به سایت 2
- دریافت Webhook برای تحدیثات موجودی
- مدیریت تنظیمات API

### **Slave (سایت 2):**
- دریافت خودکار محصولات
- درصد‌گذاری قیمت یکپارچه (۱ درصد برای همه)
- ارسال موجودی به سایت 1
- مدیریت محصولات دریافت‌شده

---

## ⚠️ نکات مهم:

1. **WordPress Version:** 5.0+
2. **WooCommerce:** 5.0+ (باید فعال باشد)
3. **PHP:** 7.4+
4. **Permissions:** پوشه plugins باید قابل نوشتن باشد
5. **API Communication:** دو سایت باید به یکدیگر دسترسی داشته باشند

---

## 🔧 Troubleshooting:

### اگر پلاگین‌ها نمایش داده نشدند:
1. مسیر کپی درست است؟
2. فایل `inventory-sync-master.php` و `inventory-sync-slave.php` موجود هستند؟
3. دسترسی فایل‌ها `755` است؟
4. PHP error log را بررسی کنید

---

## 📞 پشتیبانی:

اگر مشکلی دارید، این فایل‌ها را بررسی کنید:
- `/wp-content/debug.log` - error log WordPress
- Plugin Headers - صحیح است ✅
- Include paths - صحیح هستند ✅
- Database permissions - برای ایجاد جداول

