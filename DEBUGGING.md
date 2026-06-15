# راهنمای Debug مشکلات انتقال محصول

## مشکل: محصول منتقل نمی‌شود اما دسته‌بندی‌ها منتقل می‌شوند

### وضعیت فعلی
```
✓ دسته‌بندی‌ها منتقل می‌شوند
✓ ویژگی‌ها منتقل می‌شوند
✗ محصول ایجاد نمی‌شود
```

### علت‌های احتمالی

#### 1. **SKU تکراری**
اگر محصول با SKU یکسان در سایت 2 موجود است:
- سیستم اکنون محصول موجود را شناسایی می‌کند
- بروز‌رسانی آن را انجام می‌دهد (نه ایجاد جدید)

**راه‌حل:**
- بررسی کنید که آیا محصول در سایت 2 قبلاً موجود است
- اگر موجود است، SKU را تغییر دهید یا محصول را حذف کنید

#### 2. **دسته‌بندی یا ویژگی نادرست**
اگر دسته‌بندی ID نادرست باشد:
- محصول ایجاد نمی‌شود
- خطای "invalid category" می‌رود

**راه‌حل:**
- لاگ‌های سایت 2 را بررسی کنید
- `{site2}/wp-json/wc/v3/products` را تست کنید

#### 3. **مقادیر ویژگی نادرست**
اگر ویژگی option‌ها نادرست باشند:
- محصول متغیر ایجاد نمی‌شود
- ویژگی‌های انتخاب‌شده موجود نیستند

**راه‌حل:**
- تمام تکیه‌های ویژگی را منتقل کنید
- در سایت 2 تایید کنید که ویژگی‌ها وجود دارند

### لاگ‌های کلیدی برای بررسی

#### جدول: `wp_inventory_sync_logs`

```sql
SELECT * FROM wp_inventory_sync_logs 
WHERE product_name = 'نام محصول' 
ORDER BY created_at DESC LIMIT 10;
```

**پیام‌های مهم:**
- `create_product_start` - شروع ایجاد محصول
- `transfer_product_error` - خطای انتقال
- `sync_category` - انتقال دسته‌بندی
- `sync_attribute` - انتقال ویژگی

#### جدول: `wp_inventory_sync_products_transferred`

```sql
SELECT * FROM wp_inventory_sync_products_transferred 
WHERE product_name = 'نام محصول';
```

**فیلدهای مهم:**
- `transfer_status` - 'success' یا 'failed'
- `error_message` - پیام خطا
- `categories_synced` - 1 یا 0
- `attributes_synced` - 1 یا 0

### API تست

#### بررسی محصول در سایت 2

```bash
# تست ایجاد محصول
curl -X POST "https://site2.com/wp-json/wc/v3/products" \
  -H "Authorization: Bearer token" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "تیشرت تست",
    "type": "simple",
    "sku": "TEST-123",
    "regular_price": "100000",
    "categories": [
      {"id": 10}
    ]
  }'

# بررسی دسته‌بندی
curl "https://site2.com/wp-json/wc/v3/products/categories?per_page=100"

# بررسی ویژگی‌ها
curl "https://site2.com/wp-json/wc/v3/products/attributes"
```

### حل‌های سریع

#### اگر SKU موجود است:
1. بروز رفتن به آخرین نسخه (خودکار بروز می‌شود)
2. یا حذف محصول از سایت 2 و دوباره انتقال

#### اگر دسته‌بندی ایجاد نشد:
1. تب "محصولات منتقل‌شده" را ببینید
2. `categories_synced = 0` را بررسی کنید
3. لاگ‌های sync_category را بررسی کنید

#### اگر ویژگی‌ها ایجاد نشدند:
1. سایت 2 - Products > Attributes را بررسی کنید
2. تمام تکیه‌های ویژگی را ایجاد کنید
3. دوباره محصول را منتقل کنید

### قطع‌نقاط (Breakpoints)

اگر نیاز به debugging عمیق دارید:

#### 1. بررسی API connection
```php
$api = new Inventory_Sync_API('site2_url', 'consumer_key', 'consumer_secret');
$test = $api->get_product(1);
echo json_encode($test);
```

#### 2. بررسی دسته‌بندی
```php
$cats = new Inventory_Sync_Category_Attribute_Sync($api1, $api2);
$map = $cats->sync_product_categories([['id' => 5]]);
echo json_encode($map);
```

#### 3. بررسی prepare_transfer_data
```php
$manager = Inventory_Sync_Manager::get_instance();
$data = $manager->prepare_transfer_data($product);
echo json_encode($data);
```

### خطوط تماس (Contact)

اگر مشکل حل نشد:
1. بررسی کنید که API keys صحیح هستند
2. بررسی کنید که site URLs بدون slash انجام‌شوند
3. بررسی کنید که WooCommerce نسخه جدید است

---

**نکته:** تمام لاگ‌ها در دیتابیس ذخیره می‌شوند و می‌توانید از داشبورد دسترسی داشته باشید.
