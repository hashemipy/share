#!/bin/bash

# اسکریپت بررسی نصب صحیح پلاگین‌ها

echo "════════════════════════════════════════════════"
echo "بررسی نصب Inventory Sync Plugins"
echo "════════════════════════════════════════════════"
echo ""

PLUGINS_DIR="${1:-.}"
ISSUES=0

echo "📍 مسیر Plugin: $PLUGINS_DIR"
echo ""

# تابع بررسی فایل
check_file() {
    if [ -f "$1" ]; then
        echo "✅ $2"
        return 0
    else
        echo "❌ $2 - فایل پیدا نشد!"
        ((ISSUES++))
        return 1
    fi
}

# تابع بررسی پوشه
check_dir() {
    if [ -d "$1" ]; then
        echo "✅ $2"
        return 0
    else
        echo "❌ $2 - پوشه پیدا نشد!"
        ((ISSUES++))
        return 1
    fi
}

echo "🔍 بررسی Master Plugin:"
echo "─────────────────────────────"

check_file "$PLUGINS_DIR/inventory-sync-master/inventory-sync-master.php" "فایل اصلی Master"
check_dir "$PLUGINS_DIR/inventory-sync-master/includes" "پوشه includes"
check_dir "$PLUGINS_DIR/inventory-sync-master/admin" "پوشه admin"
check_dir "$PLUGINS_DIR/inventory-sync-master/assets" "پوشه assets"

check_file "$PLUGINS_DIR/inventory-sync-master/includes/class-master-settings.php" "کلاس Settings"
check_file "$PLUGINS_DIR/inventory-sync-master/includes/class-master-api.php" "کلاس API"
check_file "$PLUGINS_DIR/inventory-sync-master/includes/class-master-admin.php" "کلاس Admin"
check_file "$PLUGINS_DIR/inventory-sync-master/includes/class-master-sync.php" "کلاس Sync"
check_file "$PLUGINS_DIR/inventory-sync-master/includes/class-master-webhook.php" "کلاس Webhook"

echo ""
echo "🔍 بررسی Slave Plugin:"
echo "─────────────────────────────"

check_file "$PLUGINS_DIR/inventory-sync-slave/inventory-sync-slave.php" "فایل اصلی Slave"
check_dir "$PLUGINS_DIR/inventory-sync-slave/includes" "پوشه includes"
check_dir "$PLUGINS_DIR/inventory-sync-slave/admin" "پوشه admin"
check_dir "$PLUGINS_DIR/inventory-sync-slave/assets" "پوشه assets"

check_file "$PLUGINS_DIR/inventory-sync-slave/includes/class-slave-settings.php" "کلاس Settings"
check_file "$PLUGINS_DIR/inventory-sync-slave/includes/class-slave-receiver.php" "کلاس Receiver"
check_file "$PLUGINS_DIR/inventory-sync-slave/includes/class-slave-admin.php" "کلاس Admin"
check_file "$PLUGINS_DIR/inventory-sync-slave/includes/class-slave-sync.php" "کلاس Sync"

echo ""
echo "════════════════════════════════════════════════"

if [ $ISSUES -eq 0 ]; then
    echo "✅ تمام فایل‌ها موجود هستند!"
    echo ""
    echo "مراحل بعدی:"
    echo "1. وارد داشبورد WordPress شوید"
    echo "2. به Plugins > Installed Plugins بروید"
    echo "3. Plugins را فعال کنید"
    exit 0
else
    echo "❌ $ISSUES مورد مشکل پیدا شد!"
    echo ""
    echo "لطفاً تمام فایل‌ها را کپی کنید:"
    echo "cp -r inventory-sync-master/* /wp-content/plugins/inventory-sync-master/"
    echo "cp -r inventory-sync-slave/* /wp-content/plugins/inventory-sync-slave/"
    exit 1
fi
