#!/bin/bash

# دستورالعمل نصب دستی پلاگین‌ها

echo "============================================"
echo "Inventory Sync - نصب دستی پلاگین‌ها"
echo "============================================"
echo ""

# مسیرهای نصب (تغییر دهید بر اساس نصب WordPress شما)
WP_PATH="/path/to/wordpress"  # مثال: /var/www/html
PLUGINS_DIR="$WP_PATH/wp-content/plugins"

echo "📍 مسیر WordPress: $WP_PATH"
echo "📍 مسیر Plugins: $PLUGINS_DIR"
echo ""

# بررسی وجود پوشه‌ها
if [ ! -d "$PLUGINS_DIR" ]; then
    echo "❌ خطا: پوشه plugins پیدا نشد!"
    echo "لطفاً مسیر WordPress را تصحیح کنید."
    exit 1
fi

echo "✅ پوشه plugins پیدا شد."
echo ""

# نصب Master
echo "🔧 نصب Inventory Sync Master..."
if [ ! -d "$PLUGINS_DIR/inventory-sync-master" ]; then
    mkdir -p "$PLUGINS_DIR/inventory-sync-master"
    echo "✅ پوشه Master ایجاد شد"
fi

# کپی فایل‌ها
cp -r ./inventory-sync-master/* "$PLUGINS_DIR/inventory-sync-master/"
echo "✅ فایل‌های Master کپی شدند"

echo ""

# نصب Slave
echo "🔧 نصب Inventory Sync Slave..."
if [ ! -d "$PLUGINS_DIR/inventory-sync-slave" ]; then
    mkdir -p "$PLUGINS_DIR/inventory-sync-slave"
    echo "✅ پوشه Slave ایجاد شد"
fi

# کپی فایل‌ها
cp -r ./inventory-sync-slave/* "$PLUGINS_DIR/inventory-sync-slave/"
echo "✅ فایل‌های Slave کپی شدند"

echo ""
echo "============================================"
echo "✅ نصب کامل شد!"
echo "============================================"
echo ""
echo "مراحل بعدی:"
echo "1. وارد داشبورد WordPress شوید"
echo "2. به Plugins بروید"
echo "3. 'Inventory Sync Master' و 'Inventory Sync Slave' را فعال کنید"
echo ""
