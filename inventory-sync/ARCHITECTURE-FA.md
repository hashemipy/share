# معماری Inventory Sync - شرح تکنیکی

## نمای کلی سیستم

```
┌─────────────────────────────────────────────────────────────────┐
│                      WordPress Admin Dashboard                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │ Settings     │  │ Mapping      │  │ Transfer / Logs      │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────────────┘  │
└─────────┼──────────────────┼─────────────────┼──────────────────┘
          │                  │                 │
          ▼                  ▼                 ▼
┌─────────────────────────────────────────────────────────────────┐
│          class-admin.php (صفحات مدیریت + AJAX)                 │
│                                                                   │
│  handle_save_settings()                                          │
│  handle_test_connection()                                        │
│  handle_get_products()                                           │
│  handle_save_mapping()                                           │
│  handle_transfer_products()                                      │
└─────────┬───────────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────────────┐
│          class-sync-manager.php (منطق اصلی Sync)               │
│                                                                   │
│  sync_inventory()       - هماهنگ‌سازی موجودی                   │
│  transfer_product()     - انتقال محصول                          │
│  sync_on_order()        - شنیدن فروش‌ها                          │
│  sync_on_stock_change() - شنیدن تغییرات موجودی                 │
└─────────┬───────────────┬───────────────────────────────────────┘
          │               │
    ┌─────▼────┐   ┌──────▼─────┐
    ▼          ▼   ▼            ▼
 ┌──────────────────┐  ┌──────────────────┐
 │ class-api.php    │  │ class-database.php│
 │ API Calls        │  │ Database Ops     │
 │ to WooCommerce   │  │ Logging          │
 └────┬─────┬───────┘  └───────┬──────────┘
      │     │                  │
      ▼     ▼                  ▼
   ┌───────────────┐  ┌──────────────────┐
   │ Site 1 REST   │  │ wp_inventory_sync│
   │ API           │  │ _mapping         │
   └───────────────┘  │ wp_inventory_sync│
                      │ _logs            │
   ┌───────────────┐  └──────────────────┘
   │ Site 2 REST   │
   │ API           │
   └───────────────┘
```

---

## کامپوننت‌های اصلی

### 1. class-admin.php (صفحات مدیریت)

```php
class Inventory_Sync_Admin {
    
    // تب 1: تنظیمات
    render_settings_tab() {
        // فرم‌ها برای API credentials
        // دکمه‌های تست اتصال
    }
    
    // تب 2: مرتبط‌سازی
    render_mapping_tab() {
        // نمایش دو ستون از محصولات
        // کلیک برای مرتبط‌سازی
    }
    
    // تب 3: انتقال
    render_transfer_tab() {
        // لیست محصولات قابل انتقال
        // دکمه‌های انتقال (تک یا دسته‌ای)
    }
    
    // تب 4: لاگ‌ها
    render_logs_tab() {
        // جدول تمام عملیات‌ها
        // فیلتر‌کردن
    }
}
```

### 2. class-sync-manager.php (منطق Sync)

```php
class Inventory_Sync_Manager {
    
    // هماهنگ‌سازی موجودی
    public function sync_inventory($mapping_id) {
        1. برداشت تنظیمات جهت sync
        2. برداشت موجودی از سایت مبدا
        3. اپدیت موجودی در سایت مقصد
        4. لاگ‌کردن نتیجه
        5. Retry logic (اگر ناموفق بود)
    }
    
    // انتقال محصول
    public function transfer_product($site1_id) {
        1. دریافت تمام اطلاعات محصول
        2. آماده‌سازی داده‌ها
        3. ایجاد محصول جدید در سایت 2
        4. ذخیره mapping
        5. لاگ‌کردن
    }
    
    // شنیدن فروش‌ها
    public function sync_on_order($order_id) {
        1. اگر auto_sync فعال باشد
        2. درخواست background sync
    }
}
```

### 3. class-api.php (ارتباط با REST API)

```php
class Inventory_Sync_API {
    
    private $site_url;    // https://example.com
    private $consumer_key; // API Key
    private $consumer_secret; // API Secret
    
    // دریافت محصول
    public function get_product($product_id) {
        GET /wp-json/wc/v3/products/{product_id}
        ↓
        Return: [name, sku, stock, images, ...]
    }
    
    // لیست محصولات
    public function get_products($page = 1) {
        GET /wp-json/wc/v3/products?per_page=100&page={page}
        ↓
        Return: [product1, product2, ...]
    }
    
    // اپدیت موجودی
    public function update_product_stock($id, $quantity) {
        PUT /wp-json/wc/v3/products/{id}
        {stock_quantity: quantity}
        ↓
        Return: true | error
    }
    
    // ایجاد محصول جدید
    public function create_product($data) {
        POST /wp-json/wc/v3/products
        {name, description, images, stock, ...}
        ↓
        Return: {id, ...} | error
    }
}
```

### 4. class-database.php (عملیات دیتابیس)

```php
class Inventory_Sync_Database {
    
    // جداول
    wp_inventory_sync_mapping {
        id                  - primary key
        site1_product_id   - محصول سایت 1
        site2_product_id   - محصول سایت 2
        site1_sku          - SKU سایت 1
        site2_sku          - SKU سایت 2
        sync_enabled       - فعال/غیرفعال
        last_sync          - آخرین sync
        sync_status        - synced/failed/pending
        error_message      - پیام خطا (اگر موجود)
        created_at         - تاریخ ایجاد
        updated_at         - آخرین اپدیت
    }
    
    wp_inventory_sync_logs {
        id                 - primary key
        product_id         - شناسه محصول
        product_name       - نام محصول
        action             - sync_inventory/transfer
        source_site        - سایت مبدا
        target_site        - سایت مقصد
        old_value          - مقدار قدیم
        new_value          - مقدار جدید
        status             - success/failed/pending
        error_message      - پیام خطا
        created_at         - تاریخ ثبت
    }
}
```

---

## جریان کار: سناریوهای مختلف

### سناریو 1: تنظیمات و تست اتصال

```
مدیر وارد می‌شود (مرحله 1)
        │
        ▼
   Form Submit (AJAX)
        │
        ├─ handle_save_settings()
        │  └─ ذخیره API Keys (encrypted)
        │
        ├─ handle_test_connection()
        │  ├─ initialize API objects
        │  ├─ GET /wp-json/wc/v3/products?per_page=1
        │  └─ Return: Success/Error
        │
        ▼
   Update UI: "✓ اتصال موفق"
```

### سناریو 2: انتقال محصول تکی

```
مدیر محصول را انتخاب می‌کند
        │
        ▼
   "📤 انتقال محصولات انتخاب‌شده" کلیک
        │
        ▼
   handle_transfer_products() (AJAX)
        │
        ├─ For each selected product:
        │  │
        │  ├─ API.get_product(site1_id)
        │  │  └─ Download: name, desc, images, stock
        │  │
        │  ├─ API.create_product(site2, data)
        │  │  └─ POST new product to site2
        │  │
        │  ├─ DB.insert_mapping(site1_id, site2_id)
        │  │  └─ یادداشت‌کردن mapping
        │  │
        │  └─ DB.insert_log(..., success)
        │
        ▼
   Update Progress Bar: 45/100 ✓
        │
        ▼
   "✓ 50 محصول منتقل شد"
```

### سناریو 3: مرتبط‌سازی محصولات

```
مدیر محصول سایت 1 را کلیک می‌کند
        │
        ▼
   مودال نمایش می‌یابد: محصولات سایت 2
        │
        ▼
   مدیر محصول سایت 2 را انتخاب می‌کند
        │
        ▼
   handle_save_mapping() (AJAX)
        │
        ├─ INSERT INTO wp_inventory_sync_mapping
        │  └─ site1_id: 123
        │     site2_id: 456
        │     sync_enabled: 1
        │
        └─ Log: "✓ مرتبط شدند"
```

### سناریو 4: فروش و Sync خودکار

```
↓ Order placed on Site 1 ↓
        │
        ▼
woocommerce_order_status_completed hook
        │
        ├─ if auto_sync_enabled:
        │  │
        │  ├─ wp_schedule_single_event('+5 sec')
        │  │  └─ trigger: inventory_sync_immediate
        │  │
        │  └─ In background:
        │     ├─ Find mapping by product_id
        │     ├─ Call sync_inventory()
        │     └─ Update site2 stock
        │
        ▼
Background Cron Execution
        │
        ├─ GET Site1 Product Stock
        │  └─ "100 → 99"
        │
        ├─ PUT Site2 Product Stock
        │  └─ Update to "99"
        │
        └─ DB.insert_log(success)
           └─ "Product A: 100 → 99 (Site 2)"
```

### سناریو 5: Retry Logic (خرابی API)

```
sync_inventory() attempt
        │
        ├─ GET Site1: Success ✓
        ├─ PUT Site2: TIMEOUT ✗
        │
        ▼
   Update mapping:
   sync_status: failed
   error_message: "Timeout (Retry 1/3)"
        │
        ▼
   wp_schedule_single_event('+1 minute')
        │
        ▼ (1 دقیقه بعد)
   retry sync again
        │
        ├─ Attempt 2: TIMEOUT ✗
        │  └─ Schedule for +2 minutes
        │
        ├─ Attempt 3: SUCCESS ✓
        │  └─ sync_status: synced
        │
        ▼
   Final Log: "✓ Synced after 3 retries"
```

---

## جریان فنی: هماهنگ‌سازی خودکار

### مرحله 1: تنظیم Hooks

```php
// class-plugin.php
public function __construct() {
    
    // Hooks برای شنیدن تغییرات
    add_action(
        'woocommerce_order_status_completed',
        [Inventory_Sync_Manager::class, 'sync_on_order']
    );
    
    add_action(
        'woocommerce_product_set_stock',
        [Inventory_Sync_Manager::class, 'sync_on_stock_change']
    );
    
    // Cron برای جدول‌بندی‌شده
    if (!wp_next_scheduled('inventory_sync_cron_hook')) {
        wp_schedule_event(
            time(),
            'inventory_sync_interval', // 5 دقیقه
            'inventory_sync_cron_hook'
        );
    }
}
```

### مرحله 2: بررسی Cron

```php
// فعالیت خودکار هر 5 دقیقه
add_action('inventory_sync_cron_hook', function() {
    
    // دریافت تمام mappings
    $mappings = $wpdb->get_results(
        "SELECT * FROM wp_inventory_sync_mapping
         WHERE sync_enabled = 1
         ORDER BY last_sync ASC
         LIMIT 10"  // در هر بار فقط 10 تا
    );
    
    // Sync کردن هر کدام
    foreach ($mappings as $mapping) {
        Inventory_Sync_Manager::sync_inventory($mapping->id);
    }
});
```

### مرحله 3: Batch Processing

```
Total Mappings: 1000

Cron Run 1 (00:00):  1-10   synced ✓
Cron Run 2 (00:05): 11-20   synced ✓
Cron Run 3 (00:10): 21-30   synced ✓
...
Cron Run 100 (08:15): 991-1000 synced ✓

═══════════════════════════════════════
Total Time: ~8 ساعت برای تمام محصولات
CPU Impact: کم (10 API call / 5 دقیقه)
```

---

## امنیت

### 1. API Credentials

```php
// ذخیره‌سازی encrypted
$api_key = 'ck_1234567890abcdef';
$encrypted = wp_hash($api_key); // WordPress encryption

// Use only over HTTPS
// Never log or display in plain text
```

### 2. Nonce Verification

```php
// تمام AJAX calls
check_admin_referer('inventory_sync_nonce');

// موارد غیر مجاز رد می‌شوند
```

### 3. Capability Checking

```php
// فقط مدیران می‌توانند sync کنند
if (!current_user_can('manage_woocommerce')) {
    wp_die('Access Denied');
}
```

### 4. SQL Injection Prevention

```php
// Prepared statements همیشه
$mapping = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM table WHERE id = %d",
        $mapping_id
    )
);
```

---

## Performance Optimization

### 1. Caching

```php
// Transients (کش موقتی)
set_transient('inventory_sync_site1_products', $products, 5 * MINUTE_IN_SECONDS);

// بعد از 5 دقیقه دوباره fetch کن
```

### 2. Batch Operations

```php
// نه API call برای 100 محصول
// بلکه یک API call با pagination

$products = API->get_products([
    'per_page' => 100,
    'page' => 1
]);
```

### 3. Background Processing

```php
// AJAX ، فوری نیست
wp_schedule_single_event(time() + 5, 'sync_event');

// User experience بهتر است
// سریع‌تر بازگردد
```

---

## Database Schema

### Mapping Table

```sql
CREATE TABLE wp_inventory_sync_mapping (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    site1_product_id BIGINT NOT NULL,
    site2_product_id BIGINT NOT NULL,
    site1_sku VARCHAR(255),
    site2_sku VARCHAR(255),
    sync_enabled BOOLEAN DEFAULT 1,
    last_sync DATETIME,
    sync_status ENUM('pending','synced','failed') DEFAULT 'pending',
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_mapping (site1_product_id, site2_product_id),
    INDEX idx_sync_status (sync_status),
    INDEX idx_last_sync (last_sync)
);
```

### Logs Table

```sql
CREATE TABLE wp_inventory_sync_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT,
    product_name VARCHAR(255),
    action VARCHAR(50),
    source_site VARCHAR(50),
    target_site VARCHAR(50),
    old_value TEXT,
    new_value TEXT,
    status ENUM('success','failed','pending') DEFAULT 'pending',
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_id (product_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);
```

---

## نتیجه

سیستم Inventory Sync:
- ✓ ایمن (Nonce، Capability، Encryption)
- ✓ سریع (Batch، Caching، Background)
- ✓ قابل اعتماد (Retry Logic، Logging)
- ✓ آسان (راهنمایی، مودال، UI)

---

**نسخه:** 1.0  
**آخرین بروزرسانی:** 1403/03/24
