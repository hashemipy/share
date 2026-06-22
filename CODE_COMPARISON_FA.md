# 🔍 مقایسه تفصیلی: inventory-sync vs code1

## 1️⃣ مشکل اول: محصول متغیر → ساده

### 📍 inventory-sync (درست) - class-sync-manager.php
```php
خطوط 438-700:

public function transfer_product($site1_product_id) {
    // ۱. بررسی mapping
    // ۲. ایجاد SKU منحصر به فرد
    // ۳. انتقال دسته‌بندی‌ها
    $category_map = $category_attr_sync->sync_product_categories(
        $product1['categories'] ?? []
    );
    
    // ۴. انتقال ویژگی‌ها (قبل از محصول!)
    $attribute_map = $category_attr_sync->sync_product_attributes(
        $product1['attributes'] ?? []
    );
    
    // ۵. محصول ایجاد
    // ۶. دسته‌بندی متصل
    // ۷. نوع set
    // ... (شامل set_type برای متغیرها)
    
    // ۸. ویژگی‌ها متصل
    // ۹. یک save
    
    // ۱۰. متغیرها
    if ($product_type === 'variable') {
        $this->create_variations(...);
    }
}
```

### 📍 code1 (نادرست) - product-import-export.php
```php
خطوط 894-978:

if ($product_type === 'variable') {
    // ⚠️ ترتیب غلط!
    $product->set_sku($product_data['sku'] ?? '');
    $product->set_price($product_data['price'] ?? 0);
    $product->set_stock_quantity($product_data['stock_quantity'] ?? 0);
    $product->save(); // ⚠️ خطا: type set نشده!
    
} else {
    // سادهٔ محصول
    $product->set_sku($product_data['sku'] ?? '');
    // ...
}

// بعداً:
if ($product_type === 'variable' && !empty($product_data['attributes'])) {
    // ...
    wp_set_object_terms(..., 'pa_variable'); // ⚠️ غلط!
    $product->set_attributes($wc_attributes);
    $product->save();
    update_post_meta($post_id, '_product_type', 'variable'); // ⚠️ دیر!
}
```

### 🎯 دلیل تفاوت:
```
inventory-sync:
- set_type() + ویژگی‌ها + save() = محصول متغیر

code1:
- save() بدون type + سپس تغییر = محصول ساده ثبت‌شده، نمی‌تواند تغییر کند
```

---

## 2️⃣ مشکل دوم: دسته‌بندی والدین

### 📍 inventory-sync (درست) - class-category-attribute-sync.php
```php
خطوط 41-95:

public function sync_product_categories($product_categories) {
    $category_map = [];
    
    foreach ($product_categories as $category) {
        $category_id = $category['id'] ?? 0;
        
        // mapping قبلی
        $existing_mapping = Inventory_Sync_Database::get_category_mapping($category_id);
        if ($existing_mapping && !empty($existing_mapping->site2_category_id)) {
            $category_map[$category_id] = $existing_mapping->site2_category_id;
            continue;
        }
        
        // دریافت کامل
        $full_category = $this->site1_api->get_category($category_id);
        
        // والد را RECURSIVE پردازش کن
        $site2_parent_id = 0;
        if (!empty($full_category['parent'])) {
            $parent_map = $this->sync_product_categories([
                ['id' => $full_category['parent']]
            ]); // ✅ recursive call!
            
            $site2_parent_id = $parent_map[$full_category['parent']] ?? 0;
        }
        
        // دسته‌بندی در سایت ۲
        $existing_category = $this->find_existing_category(
            $full_category['name'],
            $site2_parent_id
        );
        
        if ($existing_category) {
            $site2_id = $existing_category['id'];
        } else {
            // ایجاد با والد درست
            $new_category_data = [
                'name' => $full_category['name'],
                'slug' => sanitize_title($full_category['name']),
            ];
            
            if ($site2_parent_id) {
                $new_category_data['parent'] = $site2_parent_id; // ✅ والد!
            }
            
            $new_category = $this->site2_api->create_category($new_category_data);
            $site2_id = $new_category['id'];
        }
        
        // ✅ mapping ذخیره
        Inventory_Sync_Database::add_category_mapping(
            $category_id,
            $site2_id,
            $full_category['name'],
            $existing_category ? $existing_category['name'] : $new_category['name'],
            $full_category['parent'] ?? 0,
            $site2_parent_id
        );
        
        $category_map[$category_id] = $site2_id;
    }
    
    return $category_map;
}
```

### 📍 code1 (نادرست) - product-import-export.php
```php
خطوط 1011-1056:

private function sync_category($cat_data) {
    try {
        $parent_id = 0;
        
        if (!empty($cat_data['parent_id']) && $cat_data['parent_id'] > 0) {
            $parent_term = get_term($cat_data['parent_id'], 'product_cat');
            if ($parent_term && !is_wp_error($parent_term)) {
                $parent_id = $parent_term->term_id; // ⚠️ مشکل!
            }
        }
        
        $term = term_exists($slug, 'product_cat');
        if ($term) {
            return is_array($term) ? $term['term_id'] : $term;
        }
        
        $term = wp_insert_term(
            $name,
            'product_cat',
            [
                'slug' => $slug ?: sanitize_title($name),
                'parent' => $parent_id, // ⚠️ والد غلط!
            ]
        );
        
        return is_array($term) ? $term['term_id'] : $term;
    }
}
```

### 🎯 دلیل تفاوت:
```
inventory-sync:
1. parent_id = site1 ID
2. recursive: parent را sync کن
3. parent_map میگوید والد در سایت ۲ کجاست
4. والد جدید = site2 ID

code1:
1. parent_id = term_id (هنوز site1)
2. بدون recursive
3. بدون mapping
4. والد نادرست
```

---

## 3️⃣ مشکل سوم: ویژگی‌ها

### 📍 inventory-sync (درست) - class-category-attribute-sync.php
```php
خطوط 160-350:

public function sync_product_attributes($product_attributes) {
    $attribute_map = [];
    
    foreach ($product_attributes as $attr) {
        $attribute_id = $attr['id'] ?? 0;
        $attribute_name = $attr['name'] ?? '';
        
        // ✅ ویژگی موجود؟
        $existing_attribute = $this->find_existing_attribute($attribute_name);
        
        if ($existing_attribute) {
            $site2_attribute_id = $existing_attribute['id'];
        } else {
            // ✅ ایجاد ویژگی جدید
            $new_attribute_data = [
                'name' => $full_attribute['name'],
                'slug' => $full_attribute['slug'],
                'type' => $full_attribute['type'] ?? 'select',
                'order_by' => $full_attribute['order_by'] ?? 'menu_order',
                'has_archives' => $full_attribute['has_archives'] ?? false
            ];
            
            $new_attribute = $this->site2_api->create_attribute($new_attribute_data);
            $site2_attribute_id = $new_attribute['id'];
        }
        
        // ✅ Terms (values) را sync کن
        $this->sync_attribute_terms(
            $attribute_id,
            $site2_attribute_id,
            $full_attribute['name']
        );
        
        // ✅ mapping ذخیره
        Inventory_Sync_Database::add_attribute_mapping(
            $attribute_id,
            $site2_attribute_id,
            ...
        );
        
        $attribute_map[$attribute_id] = $site2_attribute_id;
    }
    
    return $attribute_map;
}

private function sync_attribute_terms(...) {
    $terms = $this->site1_api->get_attribute_terms(...);
    
    foreach ($terms as $term) {
        $existing_term = $this->find_existing_attribute_term(...);
        
        if (!$existing_term) {
            $new_term = $this->site2_api->create_attribute_term(...);
        }
    }
}
```

### 📍 code1 (نادرست) - product-import-export.php
```php
خطوط 930-968:

// ⚠️ مشکل ۱: ویژگی‌ها قبل از set_type
if ($product_type === 'variable' && !empty($product_data['attributes'])) {
    foreach ($product_data['attributes'] as $attr_name => $attr_data) {
        $attr_id = $this->sync_attribute($attr_name, $attr_data['values'] ?? []);
        if ($attr_id) {
            $attr_ids[$attr_name] = $attr_id;
            
            // ⚠️ مشکل ۲: wp_set_object_terms غلط
            $clean_attr_name = sanitize_title($attr_name);
            $wc_attributes['pa_' . $clean_attr_name] = [
                'name' => 'pa_' . $clean_attr_name,
                'value' => implode(' | ', ...),
                'position' => 0,
                'visible' => 1,
                'variation' => 1
            ];
        }
    }
    
    // ⚠️ مشکل ۳: wrong taxonomy
    wp_set_object_terms($post_id, array_keys($wc_attributes), 'pa_variable');
    
    $product->set_attributes($wc_attributes);
    $product->save();
    
    // ⚠️ مشکل ۴: بسیار دیر
    update_post_meta($post_id, '_product_type', 'variable');
}
```

### 🎯 تفاوت‌های کلیدی:

| موضوع | inventory-sync | code1 |
|------|---|---|
| زمان sync | قبل از محصول | بعد از محصول |
| wp_set_object_terms | ❌ استفاده نمی‌کند | ❌ با 'pa_variable' |
| set_type | قبل | بعد (دیر) |
| database mapping | ✅ دارد | ❌ ندارد |
| recursive categories | ✅ بله | ❌ خیر |
| save تعداد | ۱ بار | چند بار |

---

## 📋 خلاصه تفاوت‌ها

### Inventory-Sync (✅ درست):
```
۱. دسته‌بندی‌ها (recursive + mapping)
۲. ویژگی‌ها (قبل، terms sync)
۳. محصول (post insert)
۴. set_type('variable')
۵. set attributes
۶. save() - یک بار
۷. متغیرات
```

### Code1 (❌ نادرست):
```
۱. محصول (post insert)
۲. save() - بدون type ⚠️
۳. دسته‌بندی (بدون recursive) ⚠️
۴. sync_attribute (دیر)
۵. wp_set_object_terms('pa_variable') ⚠️
۶. set_attributes
۷. save() (دوباره) ⚠️
۸. update_post_meta (خیلی دیر) ⚠️
۹. متغیرات
```

---

## 🔧 اصلاحات لازم برای Code1

### ۱. Rename methods + اضافه mapping:
```php
private $category_map = []; // جدید

private function sync_category_with_parent($cat_data) {
    // recursive + mapping
}
```

### ۲. Reorder import_products:
```php
// ترتیب جدید:
$categories = sync_categories(); // قبل
$attributes = sync_attributes(); // قبل
$post_id = wp_insert_post();     // محصول
$product->set_type($type);       // type بلافاصله
if ($type === 'variable') {
    $product->set_attributes(); // بعد
}
$product->save();                // یک بار
create_variations();             // آخر
```

### ۳. Remove wp_set_object_terms('pa_variable'):
```php
// ❌ حذف کنید:
wp_set_object_terms($post_id, array_keys($wc_attributes), 'pa_variable');

// ✅ WooCommerce خود مدیریت می‌کند
```

### ۴. Remove update_post_meta('_product_type'):
```php
// ❌ حذف کنید:
update_post_meta($post_id, '_product_type', 'variable');

// ✅ set_type() + save() کافی است
```

