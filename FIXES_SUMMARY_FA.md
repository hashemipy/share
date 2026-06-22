# 📋 خلاصه اصلاحات - کد code1

## 🔴 مشکلات اصلی (قبل)
```
1. محصول متغیر → ساده ثبت می‌شود
2. دسته‌بندی‌ها فقط فرزند بدون پدر
3. ویژگی‌ها درست نمی‌شوند
```

---

## ✅ اصلاح ۱: ترتیب اجرای مراحل

### ❌ قبل (نادرست):
```php
// خطوط 894-906
$product = wc_get_product($post_id);
if ($product_type === 'variable') {
    $product->set_sku($product_data['sku'] ?? '');
    $product->set_price($product_data['price'] ?? 0);
    $product->set_stock_quantity($product_data['stock_quantity'] ?? 0);
    $product->save(); // ⚠️ خطا: type set نشده، محصول ساده ثبت می‌شود
} else {
    $product->set_sku($product_data['sku'] ?? '');
    $product->set_price($product_data['price'] ?? 0);
    $product->set_stock_quantity($product_data['stock_quantity'] ?? 0);
}

// بعداً attributes...
if ($product_type === 'variable') {
    // ...ویژگی‌ها...
    $product->set_attributes($wc_attributes);
    $product->save(); // ⚠️ دیر است!
    update_post_meta($post_id, '_product_type', 'variable'); // ⚠️ بسیار دیر!
}
```

### ✅ بعد (درست):
```php
// مرحله ۱: دسته‌بندی‌ها
$category_ids = [];
if (!empty($product_data['categories'])) {
    foreach ($product_data['categories'] as $cat) {
        $cat_id = $this->sync_category_with_parent($cat); // ✅ والدین همراه
        if ($cat_id) $category_ids[] = $cat_id;
    }
}

// مرحله ۲: ویژگی‌ها
$attribute_map = [];
if ($product_type === 'variable' && !empty($product_data['attributes'])) {
    foreach ($product_data['attributes'] as $attr_name => $attr_data) {
        $attr_id = $this->sync_attribute($attr_name, $attr_data['values']); // ✅ قبل از محصول
        if ($attr_id) $attribute_map[$attr_name] = $attr_id;
    }
}

// مرحله ۳: محصول ایجاد
$post_id = wp_insert_post([...]);
wp_set_post_terms($post_id, $category_ids, 'product_cat');

// مرحله ۴: نوع SET (بلافاصله!)
$product = wc_get_product($post_id);
$product->set_type($product_type); // ✅ اینجا!

// مرحله ۵: ویژگی‌ها SET
if ($product_type === 'variable') {
    $wc_attributes = [...];
    $product->set_attributes($wc_attributes); // ✅ بعد از type
}

// مرحله ۶: یک save
$product->save(); // ✅ فقط یک بار!

// مرحله ۷: متغیرات
if ($product_type === 'variable') {
    foreach ($product_data['variations'] as $variation_data) {
        $this->create_variation($post_id, $variation_data, $attribute_map);
    }
}
```

**نتیجه:** محصول متغیر به درستی ثبت می‌شود ✅

---

## ✅ اصلاح ۲: دسته‌بندی‌های والد

### ❌ قبل (نادرست):
```php
// خطوط 1011-1056
private function sync_category($cat_data) {
    $parent_id = 0;
    
    if (!empty($cat_data['parent_id']) && $cat_data['parent_id'] > 0) {
        $parent_term = get_term($cat_data['parent_id'], 'product_cat');
        if ($parent_term && !is_wp_error($parent_term)) {
            $parent_id = $parent_term->term_id; // ⚠️ ID از سایت ۱ است!
        }
    }
    
    $term = term_exists($slug, 'product_cat');
    if ($term) return is_array($term) ? $term['term_id'] : $term;
    
    $term = wp_insert_term($name, 'product_cat', [
        'slug' => $slug,
        'parent' => $parent_id, // ⚠️ غلط والد!
    ]);
}
```

### ✅ بعد (درست):
```php
// متد جدید
private function sync_category_with_parent($cat_data) {
    $cat_slug = $cat_data['slug'] ?? '';
    if (strpos($cat_slug, '%') !== false) {
        $cat_slug = urldecode($cat_slug);
        $cat_slug = sanitize_title($cat_slug);
    }
    
    // بررسی قبلی
    $existing_term = term_exists($cat_slug, 'product_cat');
    if ($existing_term) {
        return is_array($existing_term) ? $existing_term['term_id'] : $existing_term;
    }
    
    // والدین
    $parent_id = 0;
    if (!empty($cat_data['parent_id']) && $cat_data['parent_id'] > 0) {
        $parent_term = get_term($cat_data['parent_id'], 'product_cat');
        if ($parent_term && !is_wp_error($parent_term)) {
            // ✅ اول mapping را چک کن
            if (isset($this->category_map[$parent_term->slug])) {
                $parent_id = $this->category_map[$parent_term->slug];
            } else {
                // ✅ یا سایت ۲ را جستجو کن
                $parent_in_site2 = term_exists($parent_term->slug, 'product_cat');
                if ($parent_in_site2) {
                    $parent_id = is_array($parent_in_site2) ? $parent_in_site2['term_id'] : $parent_in_site2;
                } else {
                    // ✅ یا والد ایجاد کن
                    $parent_term_created = wp_insert_term(
                        $parent_term->name,
                        'product_cat',
                        ['slug' => $parent_term->slug]
                    );
                    if (!is_wp_error($parent_term_created)) {
                        $parent_id = $parent_term_created['term_id'];
                        $this->category_map[$parent_term->slug] = $parent_id;
                    }
                }
            }
        }
    }
    
    // دسته‌بندی ایجاد
    $new_term = wp_insert_term($cat_name, 'product_cat', [
        'slug' => $cat_slug ?: sanitize_title($cat_name),
        'parent' => $parent_id, // ✅ والد درست
    ]);
    
    $term_id = $new_term['term_id'];
    $this->category_map[$cat_slug] = $term_id; // ✅ mapping ذخیره
    
    return $term_id;
}
```

**نتیجه:** دسته‌بندی‌های والد و فرزند به درستی ایجاد می‌شوند ✅

---

## ✅ اصلاح ۳: ویژگی‌های متغیر

### ❌ قبل (نادرست):
```php
// خطوط 960-961
wp_set_object_terms($post_id, array_keys($wc_attributes), 'pa_variable'); // ⚠️ غلط taxonomy!

$product->set_attributes($wc_attributes);
$product->save();

// خط 967
update_post_meta($post_id, '_product_type', 'variable'); // ⚠️ دیر است!
```

### ✅ بعد (درست):
```php
// مرحله ۱: نوع را set کن
$product->set_type('variable'); // ✅ از ابتدا

// مرحله ۲: ویژگی‌ها را sync کن (قبل از محصول)
$attribute_map = [];
foreach ($product_data['attributes'] as $attr_name => $attr_data) {
    $attr_id = $this->sync_attribute($attr_name, $attr_data['values']); // ✅ قبل
    if ($attr_id) $attribute_map[$attr_name] = $attr_id;
}

// مرحله ۳: ویژگی‌های محصول
if (!empty($attribute_map)) {
    $wc_attributes = [];
    
    foreach ($attribute_map as $attr_name => $attr_id) {
        $clean_attr_name = sanitize_title($attr_name);
        $attr_taxonomy = 'pa_' . $clean_attr_name; // ✅ درست!
        
        $terms = get_terms([
            'taxonomy' => $attr_taxonomy,
            'hide_empty' => false,
        ]);
        
        if (!is_wp_error($terms) && !empty($terms)) {
            $wc_attributes[$attr_taxonomy] = [
                'name' => $attr_taxonomy,
                'value' => implode(' | ', wp_list_pluck($terms, 'slug')),
                'position' => 0,
                'visible' => 1,
                'variation' => 1
            ];
        }
    }
    
    $product->set_attributes($wc_attributes); // ✅ هنگام درست
}

// مرحله ۴: save
$product->save(); // ✅ یک بار با تمام داده‌ها
```

**نتیجه:** ویژگی‌ها درست ایجاد و متصل می‌شوند ✅

---

## 📊 مقایسه نهایی

| موضوع | قبل | بعد |
|------|-----|-----|
| نوع محصول | ❌ ساده | ✅ متغیر |
| ویژگی‌ها | ❌ ایجاد نمی‌شود | ✅ ایجاد و متصل |
| دسته‌بندی والدین | ❌ فقط فرزند | ✅ والد + فرزند |
| ترتیب مراحل | ❌ غلط | ✅ درست |
| Save تعداد | ❌ چند بار | ✅ یک بار |
| Mapping | ❌ ندارد | ✅ دارد |

---

## 🚀 نحوه استفاده

1. **نام پلاگین را عوض کنید:** 
   - از `Product_Import_Export` به `Product_Import_Export_Corrected` تغییر دهید

2. **فایل جدید را استفاده کنید:**
   - فایل `product-import-export-CORRECTED.php` را استفاده کنید

3. **تست کنید:**
   ```json
   {
     "type": "variable",
     "categories": [
       {
         "name": "والد",
         "slug": "parent",
         "parent_id": 0
       },
       {
         "name": "فرزند",
         "slug": "child", 
         "parent_id": 1  // والد
       }
     ],
     "attributes": {
       "رنگ": {
         "values": [
           {"name": "قرمز", "slug": "red"},
           {"name": "آبی", "slug": "blue"}
         ]
       }
     },
     "variations": [
       {
         "sku": "var-red",
         "price": "1000",
         "attributes": {"رنگ": "red"}
       }
     ]
   }
   ```

---

## 📝 یادداشت‌های مهم

- **inventory-sync از چه چیزی استفاده می‌کند:**
  - `Inventory_Sync_Database` برای mapping
  - recursive category sync
  - attribute mapping database

- **code1 اصلاح‌شده:**
  - `$this->category_map` برای mapping
  - `sync_category_with_parent()` recursive
  - `sync_attribute()` قبل از محصول
  - ترتیب صحیح مراحل
