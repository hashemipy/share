# 📚 تفصیلی شرح - مسائل اور حل

## مسئہ 1️⃣: متغیر محصول ساده ہو رہی تھی

### کیا ہو رہا تھا؟

جب آپ متغیر محصول اپ لوڈ کرتے تھے، تو یہ **ساده** ہو کر محفوظ ہو جاتی تھی!

```php
// ❌ غلط ترتیب
POST ڈیٹا:        type: "variable"
↓
$product->set_sku(...);
$product->save();     // ← یہاں محصول ساده ہو جاتی ہے!
↓
اب product type set کریں  // ← خیر، بہت دیر!
↓
نتیجہ:              type: "simple" ❌
```

### کیوں ہو رہا تھا؟

WooCommerce میں جب آپ نیا product بناتے ہو، تو default **simple** ہوتا ہے۔

```php
$product = wc_get_product($post_id);
// یہاں $product->get_type() = 'simple' ہے
```

جب آپ `save()` کرتے ہو، تو یہ database میں **simple** کے طور پر ثبت ہو جاتا ہے:

```php
$product->save();  // Database: type = 'simple'
```

بعد میں `set_type('variable')` کرنا بہت دیر ہو چکی ہوتی ہے۔

### حل کیا ہے؟

**Type کو فوری set کریں:**

```php
// ✅ صحیح ترتیب
if ($product_type === 'variable') {
    $product->set_type('variable');  // ← پہلے!
}

$product->set_sku(...);
$product->save();     // اب صحیح type کے ساتھ محفوظ ہوگی
```

### کوڈ میں کہاں ہے؟

```
فائل: product-import-export.php
لائنیں: 893-906
```

---

## مسئہ 2️⃣: Attributes نہیں بن رہی تھیں

### کیا ہو رہا تھا؟

جب متغیر محصول اپ لوڈ کرتے تھے، تو attributes (رنگ، سائز وغیرہ) ثبت نہیں ہو رہے تھے۔

```
مثال:
ڈاؤن لوڈ سے:  متغیر محصول ← رنگ، سائز attributes ✓
اپ لوڈ میں:    متغیر محصول ← رنگ، سائز attributes ✗
```

### کیوں ہو رہا تھا؟

دو غلطیاں تھیں:

#### غلطی 1: غلط Taxonomy
```php
// ❌ غلط!
wp_set_object_terms($post_id, ['pa_color', 'pa_size'], 'pa_variable');
// 'pa_variable' ایک valid taxonomy نہیں ہے!
```

#### غلطی 2: غلط وقت پر `update_post_meta`
```php
// ❌ غلط!
$product->set_attributes($wc_attributes);
$product->save();
update_post_meta($post_id, '_product_type', 'variable');
// اب بہت دیر ہو چکی - product پہلے ہی simple ہے
```

### حل کیا ہے؟

صحیح WooCommerce API استعمال کریں:

```php
// ✅ صحیح!
$product->set_attributes($wc_attributes);
$product->save();  // یہ automatic طور پر database میں رکھتا ہے
```

### کوڈ میں کہاں ہے؟

```
فائل: product-import-export.php
لائنیں: 930-962
پہلے: 961 → wp_set_object_terms(...) ہٹایا
پہلے: 967 → update_post_meta(...) ہٹایا
```

---

## مسئہ 3️⃣: والد دسته‌بندی نہیں بن رہی تھی

### کیا ہو رہا تھا؟

دسته‌بندی کی ساخت غلط تھی:

```
ڈاؤن لوڈ سے:
الکترونکس (والد)
└─ موبائل فون (فرزند)

اپ لوڈ میں:
موبائل فون (فرزند بغیر والد) ✗
الکترونکس (والد) ✗
```

صرف فرزند بنتا تھا، والد نہیں۔

### کیوں ہو رہا تھا؟

کیونکہ یہ code تھا:

```php
if (!empty($cat_data['parent_id']) && $cat_data['parent_id'] > 0) {
    // سایت 1 میں parent_id = 5 ہے
    // لیکن سایت 2 میں یہ ID موجود نہیں ہے!
    $parent_term = get_term(5, 'product_cat');  // ❌ نہیں ملے گا
    
    if ($parent_term && !is_wp_error($parent_term)) {
        $parent_id = $parent_term->term_id;
    }
    // ❌ اگر نہیں ملے تو $parent_id = 0 رہتا ہے
}
```

### حل کیا ہے؟

Parent کو **ایجاد** کریں اگر موجود نہیں ہے:

```php
if (!empty($cat_data['parent_id']) && $cat_data['parent_id'] > 0) {
    $parent_slug = 'parent-' . $cat_data['parent_id'];
    $parent_term = term_exists($parent_slug, 'product_cat');
    
    if (!$parent_term) {
        // ✅ Parent کو ایجاد کریں!
        $parent_term = wp_insert_term(
            'Parent Category ' . $cat_data['parent_id'],
            'product_cat',
            ['slug' => $parent_slug]
        );
    }
    
    if (!is_wp_error($parent_term)) {
        $parent_id = is_array($parent_term) ? $parent_term['term_id'] : $parent_term;
    }
}
```

### نتیجہ:

```
اب:
الکترونکس (والد) ✓ ← خودکار ایجاد
└─ موبائل فون (فرزند) ✓
```

### کوڈ میں کہاں ہے؟

```
فائل: product-import-export.php
لائنیں: 1015-1035
```

---

## ٹیسٹ کریں

### Step 1: یہ JSON بنائیں

```json
{
  "type": "variable",
  "name": "ٹیسٹ متغیر",
  "categories": [
    {
      "name": "والد دسته",
      "slug": "parent-category",
      "parent_id": 0
    },
    {
      "name": "فرزند دسته",
      "slug": "child-category",
      "parent_id": 1
    }
  ],
  "attributes": {
    "رنگ": {
      "values": [
        {"name": "سیاہ", "slug": "black"},
        {"name": "سفید", "slug": "white"}
      ]
    }
  },
  "variations": [
    {
      "attributes": {"رنگ": "سیاہ"},
      "price": "100"
    }
  ]
}
```

### Step 2: اپ لوڈ کریں

WordPress → WooCommerce → Import/Export

### Step 3: چیک کریں

```
✅ محصول متغیر ہے؟ (ساده نہیں)
✅ رنگ attribute موجود ہے؟
✅ فرزند category والد کے اندر ہے؟
✅ 1 variation بنی؟
```

اگر سب ✅ ہیں، تو **بہترین! حل ہو گیا!** 🎉

---

## خلاصہ

| مسئہ | وجہ | حل |
|------|-----|-----|
| متغیر → ساده | Type دیر سے set | فوری set کریں |
| Attributes ✗ | غلط API | صحیح API استعمال |
| والد ✗ | ID check غلط | ایجاد کریں |

---

## اہم یاد رکھیں

1. **ترتیب مہم ہے:**
   ```
   set_type() → set_attributes() → save()
   ```

2. **ہمیشہ ایجاد کریں:**
   ```
   get_term() نہ ملے → wp_insert_term()
   ```

3. **صحیح API:**
   ```
   $product->set_...()  ← WooCommerce
   ```

---

## اگر مسئہ ہو؟

**Error log چیک کریں:**
```
/wp-content/debug.log
```

یا WordPress میں:
```
define('WP_DEBUG_LOG', true);
```

---

✨ **اب سب ٹھیک ہونا چاہیے!**
