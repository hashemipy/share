# معماری سیستم Inventory Sync Pro

## نمای کلی

```
┌─────────────────────────────────────────────────────────────┐
│                      WordPress Admin                        │
│              (Inventory Sync Dashboard)                     │
└────────────────────────────────────────────────────────────┐
                    ↓
    ┌───────────────────────────────────────────────────────┐
    │           Inventory Sync Plugin System                │
    ├───────────────────────────────────────────────────────┤
    │                                                        │
    │  ┌────────────────────────────────────────────────┐  │
    │  │   class-plugin.php (Core)                      │  │
    │  │   ├─ Initialization                            │  │
    │  │   ├─ Hook Registration                         │  │
    │  │   └─ Cron Scheduling                           │  │
    │  └────────────────────────────────────────────────┘  │
    │                    ↓                                  │
    │  ┌────────────────────────────────────────────────┐  │
    │  │   Sync Modules                                 │  │
    │  ├────────────────────────────────────────────────┤  │
    │  │  1. class-sync-manager.php                     │  │
    │  │     - Transfer Products                        │  │
    │  │     - Map Categories & Attributes             │  │
    │  │     - Handle Variations                        │  │
    │  │     - Error Recovery                           │  │
    │  │                                                │  │
    │  │  2. class-auto-sync.php (جدید)                │  │
    │  │     - Automatic Sync Every 10 mins            │  │
    │  │     - Stock Change Detection                  │  │
    │  │     - Bidirectional Sync                       │  │
    │  │     - Webhook Integration                      │  │
    │  │                                                │  │
    │  │  3. class-category-attribute-sync.php          │  │
    │  │     - Sync Product Categories                 │  │
    │  │     - Sync Attributes & Options               │  │
    │  └────────────────────────────────────────────────┘  │
    │                    ↓                                  │
    │  ┌────────────────────────────────────────────────┐  │
    │  │   API Layer                                    │  │
    │  ├────────────────────────────────────────────────┤  │
    │  │  class-api.php                                 │  │
    │  │  ├─ WooCommerce REST API v1/v2/v3            │  │
    │  │  ├─ Product Management                        │  │
    │  │  ├─ Variation Management                      │  │
    │  │  ├─ Category & Attribute Sync                 │  │
    │  │  └─ Stock Update                              │  │
    │  └────────────────────────────────────────────────┘  │
    │                                                        │
    │  ┌────────────────────────────────────────────────┐  │
    │  │   Data Layer                                   │  │
    │  ├────────────────────────────────────────────────┤  │
    │  │  class-database.php                            │  │
    │  │  ├─ inventory_sync_mapping                     │  │
    │  │  ├─ inventory_sync_logs                        │  │
    │  │  ├─ inventory_sync_category_mapping           │  │
    │  │  ├─ inventory_sync_attribute_mapping          │  │
    │  │  └─ inventory_sync_products_transferred       │  │
    │  └────────────────────────────────────────────────┘  │
    │                                                        │
    │  ┌────────────────────────────────────────────────┐  │
    │  │   Admin Interface                              │  │
    │  ├────────────────────────────────────────────────┤  │
    │  │  class-admin.php + dashboard.php              │  │
    │  │  ├─ Settings Tab                              │  │
    │  │  ├─ Transfer Tab                              │  │
    │  │  ├─ Mapping Tab (جدید)                        │  │
    │  │  ├─ Transferred Products Tab                  │  │
    │  │  └─ Logs Tab                                  │  │
    │  │                                                │  │
    │  │  JavaScript (mapping.js)                      │  │
    │  │  ├─ Auto Mapping Display                      │  │
    │  │  ├─ Manual Mapping Interface                  │  │
    │  │  ├─ Stock Sync Controls                       │  │
    │  │  └─ Real-time Updates                         │  │
    │  └────────────────────────────────────────────────┘  │
    │                                                        │
    └───────────────────────────────────────────────────────┘
            ↓                           ↓
    ┌─────────────────┐       ┌─────────────────┐
    │  Site 1 (Source)│       │  Site 2 (Dest)  │
    │  WooCommerce    │       │  WooCommerce    │
    │                 │       │                 │
    │ - Products      │       │ - Products      │
    │ - Variations    │       │ - Variations    │
    │ - Categories    │  ←→   │ - Categories    │
    │ - Attributes    │       │ - Attributes    │
    │ - Stock         │       │ - Stock         │
    └─────────────────┘       └─────────────────┘
```

## جریان داده

### انتقال محصول (Product Transfer)

```
User selects products to transfer
        ↓
admin/dashboard.php → AJAX Request
        ↓
class-admin.php (ajax_transfer_products)
        ↓
class-sync-manager.php (transfer_product)
        ↓
Parallel Operations:
├─ Sync Categories (class-category-attribute-sync.php)
├─ Sync Attributes
├─ Prepare Product Data
├─ Handle Variations
├─ Upload Images
└─ Create on Site2 via REST API
        ↓
Store in inventory_sync_products_transferred
        ↓
Display Success Message
```

### هماهنگ‌سازی خودکار موجودی (Auto Stock Sync)

```
WordPress Cron (Every 10 minutes)
        ↓
class-auto-sync.php (run_auto_sync)
        ↓
For Each Transferred Product:
├─ Get Site1 Stock
├─ Get Site2 Stock
├─ Apply Sync Direction:
│  ├─ site1_to_site2: Copy Site1 → Site2
│  ├─ site2_to_site1: Copy Site2 → Site1
│  └─ bidirectional: Use minimum of both
└─ Update via REST API
        ↓
Log the changes
        ↓
Also sync variations using SKU matching
```

### تغییر موجودی (Stock Change Detection)

```
WooCommerce Stock Change
(woocommerce_product_set_stock hook)
        ↓
class-auto-sync.php (on_product_stock_change)
        ↓
Is Product Transferred?
├─ Yes: Record change in wp_options
└─ No: Ignore
        ↓
At next Cron execution:
├─ Check recorded changes
├─ Apply sync direction
└─ Update both sites
```

## فایل‌های اصلی

### Core Files
- `inventory-sync.php` - نقطه ورود اصلی
- `includes/class-plugin.php` - مدیریت اصلی
- `includes/class-loader.php` - Autoloader

### Sync Modules
- `includes/class-sync-manager.php` - مدیریت انتقال
- `includes/class-auto-sync.php` - هماهنگ‌سازی خودکار (جدید)
- `includes/class-category-attribute-sync.php` - دسته و ویژگی

### API & Data
- `includes/class-api.php` - REST API Interface
- `includes/class-database.php` - Database Operations
- `includes/class-settings.php` - تنظیمات

### Admin Interface
- `includes/class-admin.php` - Admin Backend
- `admin/dashboard.php` - Dashboard HTML
- `admin/js/mapping.js` - Mapping Interface (جدید)

## Database Schema

### جداول اصلی
```
wp_inventory_sync_mapping
├─ site1_product_id
├─ site2_product_id
├─ site1_sku
├─ site2_sku
├─ sync_enabled
└─ last_sync

wp_inventory_sync_products_transferred
├─ site1_product_id
├─ site2_product_id
├─ product_name
├─ transferred_at
├─ transfer_status
└─ error_message

wp_inventory_sync_logs
├─ product_id
├─ action
├─ source_site
├─ target_site
├─ status
└─ error_message

wp_inventory_sync_category_mapping
├─ site1_category_id
├─ site2_category_id
└─ sync_status

wp_inventory_sync_attribute_mapping
├─ site1_attribute_id
├─ site2_attribute_id
└─ sync_status
```

## Hook & Actions

### WordPress Hooks
```
admin_menu - Dashboard menu registration
admin_enqueue_scripts - Load CSS/JS
wp_ajax_* - AJAX handlers
plugins_loaded - Plugin initialization
woocommerce_product_set_stock - Stock change detection
woocommerce_reduce_order_stock - Order processing
```

### Custom Cron
```
inventory_sync_auto_sync_event - Every 10 minutes
inventory_sync_ten_minutes - Custom interval
```

## جریان AJAX

### تب Mapping (جدید)
```
Mapping Tab Load
    ├─ ajax_get_auto_mapped_products
    │  └─ Display transferred products with stock comparison
    │
    ├─ ajax_get_unmapped_products
    │  ├─ Get Site1 unmapped products
    │  └─ Get Site2 unmapped products
    │
    ├─ ajax_get_next_sync_time
    │  └─ Display next auto-sync time
    │
    └─ User Actions:
       ├─ Select for manual mapping
       │  └─ ajax_create_manual_mapping
       │
       ├─ Sync inventory
       │  ├─ ajax_manual_sync_all (all products)
       │  └─ ajax_sync_product_inventory (single product)
       │
       └─ Remove mapping
          └─ ajax_remove_mapping
```

## بهتری‌های امنیتی

- ✅ Nonce verification for all AJAX requests
- ✅ Capability checks (manage_woocommerce)
- ✅ Sanitization of user inputs
- ✅ Prepared SQL queries (no SQL injection)
- ✅ REST API authentication
- ✅ Error message sanitization

## Performance Optimizations

- ✅ Batch operations for multiple products
- ✅ Database query optimization with indexes
- ✅ Caching for API responses
- ✅ Pagination for large datasets
- ✅ Asynchronous processing for heavy tasks

---

**نسخه**: 1.0.0
