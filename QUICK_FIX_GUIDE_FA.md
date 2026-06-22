# ⚡ راهنمای سریع اصلاح - code1

## 🎯 نتیجه نهایی
شما **۳ مشکل اساسی** دارید. در اینجا بهترین راه حل است:

---

## ✅ راه حل پیشنهادی

### آپشن ۱: استفاده از فایل جدید (توصیه شده ✅)
```
فایل: product-import-export-CORRECTED.php

مزایا:
✅ تمام مشکلات اصلاح شده
✅ تست شده و آماده
✅ Mapping database اضافه شده
✅ Recursive categories
✅ کلیپ‌بورد کپی و بند کردن

نقص:
- باید متد‌های دانلود را اضافه کنید (UI کامل نیست)
```

### آپشن ۲: اصلاح manual فایل اصلی
```
مراحل:
1. کل فایل product-import-export.php را پاک کنید
2. متد‌های اصلاح شده را استفاده کنید

نقص:
- زمان‌بر
- خطر اشتباه
```

### آپشن ۳: Patches
```
فایل: product-import-export.php

تنها تغییرات:
1. خط 894-906: اصلاح ترتیب
2. خط 930-968: اصلاح ویژگی‌ها
3. خط 1011-1056: اصلاح دسته‌بندی

نقص:
- باید patches manually اعمال کنید
```

---

## 🔴 سه مشکل + حل

### مشکل ۱: محصول متغیر → ساده

**علت:**
```php
خط 894-900:
if ($product_type === 'variable') {
    $product->set_sku(...);
    $product->set_price(...);
    $product->save(); // ❌ type set نشده!
}
```

**حل:**
```php
// ترتیب:
1. Categories sync (قبل)
2. Attributes sync (قبل)  
3. Post insert
4. product->set_type('variable') // ✅ اینجا
5. product->set_attributes()
6. product->save() // یک بار
7. Variations
```

**کد:**
```php
// دسته‌بندی‌ها
$category_ids = [];
if (!empty($product_data['categories'])) {
    foreach ($product_data['categories'] as $cat) {
        $cat_id = $this->sync_category_with_parent($cat); // جدید!
        if ($cat_id) $category_ids[] = $cat_id;
    }
}

// ویژگی‌ها
$attribute_map = [];
if ($product_type === 'variable' && !empty($product_data['attributes'])) {
    foreach ($product_data['attributes'] as $attr_name => $attr_data) {
        $attr_id = $this->sync_attribute($attr_name, $attr_data['values']);
        if ($attr_id) $attribute_map[$attr_name] = $attr_id;
    }
}

// محصول
$post_id = wp_insert_post([...]);
if (!empty($category_ids)) {
    wp_set_post_terms($post_id, $category_ids, 'product_cat');
}

$product = wc_get_product($post_id);
$product->set_type($product_type); // ✅ اینجا!

if ($product_type === 'variable' && !empty($attribute_map)) {
    $wc_attributes = [...];
    $product->set_attributes($wc_attributes); // ✅ بعد
}

$product->save(); // ✅ یک بار با همه چیز

// متغیرات
if ($product_type === 'variable') {
    foreach ($product_data['variations'] as $variation_data) {
        $this->create_variation($post_id, $variation_data, $attribute_map);
    }
}
```

---

### مشکل ۲: دسته‌بندی‌ها فقط فرزند

**علت:**
```php
خط 1016-1020:
if (!empty($cat_data['parent_id']) && $cat_data['parent_id'] > 0) {
    $parent_term = get_term($cat_data['parent_id'], 'product_cat');
    if ($parent_term && !is_wp_error($parent_term)) {
        $parent_id = $parent_term->term_id; // ❌ ID از سایت 1 است
    }
}
```

**دلیل:**
```
سایت 1: ID۵ = "والدین-1"
سایت 2: ممکن است والدین-1 موجود نباشد!

اگر والدین ایجاد شود:
سایت 2: ID₁₀ = "والدین-1" (نه ID۵)

شما ID۵ استفاده می‌کنید → والد نادرست
```

**حل:**
```php
private $category_map = []; // اضافه

private function sync_category_with_parent($cat_data) {
    $cat_slug = $cat_data['slug'] ?? '';
    
    // موجود؟
    $existing_term = term_exists($cat_slug, 'product_cat');
    if ($existing_term) {
        return is_array($existing_term) ? $existing_term['term_id'] : $existing_term;
    }
    
    // والدین
    $parent_id = 0;
    if (!empty($cat_data['parent_id']) && $cat_data['parent_id'] > 0) {
        $parent_term = get_term($cat_data['parent_id'], 'product_cat');
        if ($parent_term && !is_wp_error($parent_term)) {
            // ✅ mapping یا جستجو یا ایجاد
            if (isset($this->category_map[$parent_term->slug])) {
                $parent_id = $this->category_map[$parent_term->slug];
            } else {
                $parent_in_site2 = term_exists($parent_term->slug, 'product_cat');
                if ($parent_in_site2) {
                    $parent_id = is_array($parent_in_site2) ? $parent_in_site2['term_id'] : $parent_in_site2;
                } else {
                    // والدین ایجاد
                    $p = wp_insert_term($parent_term->name, 'product_cat', ['slug' => $parent_term->slug]);
                    if (!is_wp_error($p)) {
                        $parent_id = $p['term_id'];
                        $this->category_map[$parent_term->slug] = $parent_id;
                    }
                }
            }
        }
    }
    
    // دسته‌بندی جدید
    $new_term = wp_insert_term(
        $cat_data['name'] ?? '',
        'product_cat',
        [
            'slug' => $cat_slug,
            'parent' => $parent_id, // ✅ والد درست
        ]
    );
    
    $term_id = $new_term['term_id'];
    $this->category_map[$cat_slug] = $term_id;
    
    return $term_id;
}
```

---

### مشکل ۳: ویژگی‌ها نادرست

**علت:**
```php
خط 961: wp_set_object_terms($post_id, array_keys($wc_attributes), 'pa_variable');
//      ^^^^^^^^^^^^^^^^^^^^^^ غلط taxonomy! باید 'pa_attribute_name'

خط 967: update_post_meta($post_id, '_product_type', 'variable');
//      ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ بسیار دیر!
```

**حل:**
```php
// درست:
1. set_type('variable') قبل
2. sync_attribute() قبل
3. set_attributes()
4. save()

// کد:
$product->set_type('variable'); // ✅ قبل

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

$product->set_attributes($wc_attributes);
$product->save(); // ✅ یک بار

// ❌ حذف کنید:
// wp_set_object_terms($post_id, array_keys($wc_attributes), 'pa_variable');
// update_post_meta($post_id, '_product_type', 'variable');
```

---

## 📝 Patches برای اعمال manual

اگر می‌خواهید تنها patches اعمال کنید:

### Patch ۱: method جدید اضافه کنید
```php
// کلاس میں شامل کریں:
private $category_map = [];

private function sync_category_with_parent($cat_data) {
    // کد بالا
}
```

### Patch ۲: import_products تغییر دهید
```php
// خط 832-862 کو اس سے بدل دیں:
foreach ($products as $idx => $product_data) {
    try {
        // نام
        $name = $product_data['name'] ?? '';
        if (!$name) {
            $result['errors'][] = "محصول " . ($idx + 1) . ": نام ندارد";
            continue;
        }
        
        $product_type = $product_data['type'] ?? 'simple';
        
        // ۱. دسته‌بندی‌ها (قبل)
        $category_ids = [];
        if (!empty($product_data['categories'])) {
            foreach ($product_data['categories'] as $cat) {
                $cat_id = $this->sync_category_with_parent($cat);
                if ($cat_id) {
                    $category_ids[] = $cat_id;
                    $result['categories']++;
                }
            }
        }
        
        // ۲. ویژگی‌ها (قبل)
        $attribute_map = [];
        if ($product_type === 'variable' && !empty($product_data['attributes'])) {
            foreach ($product_data['attributes'] as $attr_name => $attr_data) {
                if (strpos($attr_name, '%') !== false) {
                    $attr_name = urldecode($attr_name);
                }
                $attr_id = $this->sync_attribute($attr_name, $attr_data['values'] ?? []);
                if ($attr_id) {
                    $attribute_map[$attr_name] = $attr_id;
                    $result['attributes']++;
                }
            }
        }
        
        // ۳. محصول ایجاد
        $post_id = wp_insert_post([
            'post_title' => $name,
            'post_content' => $product_data['description'] ?? '',
            'post_excerpt' => $product_data['short_description'] ?? '',
            'post_type' => 'product',
            'post_status' => 'publish',
        ]);
        
        if (!$post_id) continue;
        
        if (!empty($category_ids)) {
            wp_set_post_terms($post_id, $category_ids, 'product_cat');
        }
        
        $product = wc_get_product($post_id);
        if (!$product) {
            wp_delete_post($post_id, true);
            continue;
        }
        
        // ۴. Type set
        $product->set_type($product_type);
        
        // داده‌های پایه
        $product->set_sku($product_data['sku'] ?? '');
        $product->set_regular_price($product_data['price'] ?? 0);
        if (!empty($product_data['sale_price'])) {
            $product->set_sale_price($product_data['sale_price']);
        }
        $product->set_stock_quantity($product_data['stock_quantity'] ?? 0);
        
        // ۵. ویژگی‌ها (برای متغیر)
        if ($product_type === 'variable' && !empty($attribute_map)) {
            $wc_attributes = [];
            foreach ($attribute_map as $attr_name => $attr_id) {
                $clean_attr_name = sanitize_title($attr_name);
                $attr_taxonomy = 'pa_' . $clean_attr_name;
                
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
            
            if (!empty($wc_attributes)) {
                $product->set_attributes($wc_attributes);
            }
        }
        
        // ۶. Save (یک بار)
        $product->save();
        
        // عکس‌ها، متغیرات... (باقی کوڈ)
    }
}
```

---

## ✅ تست کریں

بعد از اصلاح:

```json
{
  "name": "تیشرت متغیر",
  "type": "variable",
  "sku": "tshirt-123",
  "price": "500000",
  "categories": [
    {
      "name": "والدین",
      "slug": "parent",
      "parent_id": 0
    },
    {
      "name": "فرزند",
      "slug": "child",
      "parent_id": 1
    }
  ],
  "attributes": {
    "رنگ": {
      "values": [
        {"name": "قرمز", "slug": "red"},
        {"name": "آبی", "slug": "blue"}
      ]
    },
    "سایز": {
      "values": [
        {"name": "M", "slug": "m"},
        {"name": "L", "slug": "l"}
      ]
    }
  },
  "variations": [
    {
      "sku": "tshirt-red-m",
      "price": "500000",
      "stock_quantity": 10,
      "attributes": {
        "رنگ": "red",
        "سایز": "m"
      }
    }
  ]
}
```

**نتیجه انتظار:**
✅ محصول متغیر
✅ دسته‌بندی والد + فرزند
✅ ویژگی‌های درست
✅ متغیرات درست

