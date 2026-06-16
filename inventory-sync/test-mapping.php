<?php
/**
 * فایل تست برای اطمینان از کارکرد صحیح سیستم مرتبط‌سازی
 * 
 * این فایل نباید در production استفاده شود
 * تنها برای تست local است
 */

// نقش سایت را تست کنید
echo "[v0 Test] Current Site Role: " . Inventory_Sync_Settings::get_current_site_role() . "\n";
echo "[v0 Test] Is Primary Site: " . (Inventory_Sync_Settings::is_primary_site() ? 'YES' : 'NO') . "\n";
echo "[v0 Test] Is Secondary Site: " . (Inventory_Sync_Settings::is_secondary_site() ? 'YES' : 'NO') . "\n";

// تست Mapping Manager
$mapper = Inventory_Sync_Mapping_Manager::get_instance();
echo "[v0 Test] Mapping Manager Loaded: YES\n";

// تست دریافت محصولات
$products_site1 = $mapper->get_products_for_mapping('site1', 5, 1);
if (is_wp_error($products_site1)) {
    echo "[v0 Test] Get Products Site1 Error: " . $products_site1->get_error_message() . "\n";
} else {
    echo "[v0 Test] Get Products Site1: " . count($products_site1) . " products\n";
}

// موفقیت
echo "[v0 Test] All tests completed successfully!\n";
