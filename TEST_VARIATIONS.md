# راهنمای تست انتقال متغیّرها

## مرحله ۱: بررسی لاگ‌ها

بعد از انتقال محصول متغیّر، این لاگ‌ها را در پایگاه داده بررسی کنید:

### لاگ‌های مهم:
```
- variation_sample_data: داده‌ی متغیّر از سایت 1
- variation_prepared_data: داده‌ی متغیّر آماده‌شده برای ارسال
- create_variations_start: شروع ایجاد متغیّرها
- variations_created: موفقیت ایجاد
```

## مرحله ۲: بررسی داده‌های منتقل‌شده

### در سایت 2 (WooCommerce Admin):

1. **به محصول متغیّر بروید**
   - محصول باید Draft باشد
   - تب "Variations" را باز کنید

2. **هر متغیّر را بررسی کنید:**
   - ✓ SKU (باید منحصر‌به‌فردی باشد)
   - ✓ Regular Price
   - ✓ Sale Price (اگر موجود بود)
   - ✓ Stock / Inventory (اگر manage_stock فعال بود)
   - ✓ Attributes (ویژگی‌های درست)
   - ✓ Image (اگر موجود بود)

## مرحله ۳: نمونه داده‌ای صحیح

### داده‌ی متغیّر صحیح:
```json
{
  "sku": "tshirt-green-s",
  "regular_price": "100000",
  "sale_price": "80000",
  "stock_quantity": 50,
  "manage_stock": true,
  "stock_status": "instock",
  "attributes": [
    {
      "id": 10,
      "option": "سبز"
    },
    {
      "id": 11,
      "option": "سایز کوچک"
    }
  ],
  "image": {
    "src": "https://example.com/image.jpg"
  }
}
```

## مرحله ۴: بررسی Mapping

### جدول `wp_inventory_sync_attribute_mapping`:
```sql
SELECT * FROM wp_inventory_sync_attribute_mapping;
```

باید نشان دهد:
- `site1_attribute_id`: ID ویژگی در سایت 1
- `site2_attribute_id`: ID ویژگی در سایت 2 (mapped)
- `sync_status`: 'success'

## مرحله ۵: Troubleshooting

### مشکل: متغیّرها ایجاد نمی‌شوند

**علت احتمالی:**
1. ویژگی‌ها در سایت 2 ایجاد نشده‌اند
2. ID ویژگی‌های غلط است
3. Option (مقدار ویژگی) تطابق ندارد

**حل:**
- لاگ `variation_sample_data` را بررسی کنید
- لاگ `variation_prepared_data` را بررسی کنید
- اطمینان حاصل کنید ویژگی‌ها موجود هستند

### مشکل: موجودی نقل نمی‌شود

**علت احتمالی:**
1. `manage_stock` false است
2. `stock_quantity` null یا 0 است

**حل:**
```sql
-- بررسی محصول والد در سایت 2
SELECT manage_stock, stock_quantity FROM wp_postmeta 
WHERE post_id = SITE2_PRODUCT_ID;
```

### مشکل: قیمت نقل نمی‌شود

**علت احتمالی:**
1. `regular_price` خالی است
2. Format string نیست

**حل:**
- `regular_price` باید بزرگ‌تر از 0 باشد
- `regular_price` باید string باشد (در JSON)

## مرحله ۶: SQL برای بررسی

### تعداد متغیّرها:
```sql
SELECT COUNT(*) FROM wp_posts 
WHERE post_parent = SITE2_PRODUCT_ID 
AND post_type = 'product_variation';
```

### اطلاعات متغیّرها:
```sql
SELECT p.ID, p.post_title, pm.meta_key, pm.meta_value
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_parent = SITE2_PRODUCT_ID
AND p.post_type = 'product_variation'
ORDER BY p.ID, pm.meta_key;
```

## نکات مهم

1. **Order of Operations:**
   - محصول والد ایجاد شود
   - سپس متغیّرها ایجاد شوند
   - ویژگی‌ها باید قبلاً mapping شده باشند

2. **Data Types:**
   - price: string (نه number)
   - stock_quantity: integer
   - id: integer
   - option: string

3. **Mapping:**
   - هر ویژگی سایت 1 باید به سایت 2 map شود
   - Option (مقدار) بر اساس نام است

4. **Status:**
   - محصول جدید = Draft
   - متغیّرها تلقائی منتشر نمی‌شوند

## مثال تست عملی

تیشرت با:
- رنگ: سبز، قرمز
- سایز: S، M
- 4 متغیّر ایجاد شود

```
Variation 1: سبز + S = tshirt-green-s
Variation 2: سبز + M = tshirt-green-m
Variation 3: قرمز + S = tshirt-red-s
Variation 4: قرمز + M = tshirt-red-m
```

هر متغیّر باید:
- SKU منحصر‌به‌فردی داشته باشد
- قیمت داشته باشد
- موجودی داشته باشد
- ویژگی‌های درست داشته باشد
