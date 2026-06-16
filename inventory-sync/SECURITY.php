<?php
/**
 * Security & Error Handling Best Practices
 * 
 * این فایل بهترین روش‌های امنیتی و مدیریت خطا را توضیح می‌دهد
 */

// ===============================
// 1. Input Validation & Sanitization
// ===============================

/**
 * ✓ درست: تمام ورودی‌ها تست و پاک می‌شوند
 */
function example_correct_input($data) {
    // Validate
    if (empty($data['id']) || !is_numeric($data['id'])) {
        return new WP_Error('invalid_id', 'ID نامعتبر است.');
    }
    
    // Sanitize
    $id = intval($data['id']);
    $name = sanitize_text_field($data['name'] ?? '');
    $url = esc_url_raw($data['url'] ?? '');
    
    // Use safely
    return ['id' => $id, 'name' => $name, 'url' => $url];
}

/**
 * ✗ غلط: ورودی بدون بررسی استفاده می‌شود
 */
function example_wrong_input($data) {
    // خطر: SQL Injection
    $id = $_POST['id'];
    // فوراً استفاده بدون Validation و Sanitization
    return $id;
}

// ===============================
// 2. Database Security - Prepared Statements
// ===============================

/**
 * ✓ درست: استفاده از wpdb->prepare
 */
function example_correct_database() {
    global $wpdb;
    
    $site1_id = intval($_POST['site1_id']);
    $site2_id = intval($_POST['site2_id']);
    
    // Prepared Statement - محفوظ
    $query = $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping 
         WHERE site1_product_id = %d AND site2_product_id = %d",
        $site1_id,
        $site2_id
    );
    
    return $wpdb->get_row($query);
}

/**
 * ✗ غلط: Concatenation مستقیم
 */
function example_wrong_database() {
    global $wpdb;
    
    $site1_id = $_POST['site1_id'];
    $site2_id = $_POST['site2_id'];
    
    // خطر: SQL Injection!
    $query = "SELECT * FROM {$wpdb->prefix}inventory_sync_mapping 
              WHERE site1_product_id = {$site1_id} AND site2_product_id = {$site2_id}";
    
    return $wpdb->get_row($query);
}

// ===============================
// 3. Permission Checks
// ===============================

/**
 * ✓ درست: بررسی اجازات
 */
function example_correct_permissions() {
    // بررسی Nonce
    if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'inventory_sync_nonce')) {
        wp_send_json_error('امنیت: Nonce نامعتبر است');
        return;
    }
    
    // بررسی قابلیت دسترسی
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('عدم دسترسی');
        return;
    }
    
    // فقط سایت 1 می‌تواند مرتبط کند
    if (!Inventory_Sync_Settings::is_primary_site()) {
        wp_send_json_error('فقط سایت 1 می‌تواند این عملیات را انجام دهد');
        return;
    }
    
    // ادامه عملیات...
}

/**
 * ✗ غلط: بدون بررسی اجازات
 */
function example_wrong_permissions() {
    // خطر: هر کسی می‌تواند مرتبط کند!
    $site1_id = $_POST['site1_id'];
    $site2_id = $_POST['site2_id'];
    
    $mapper = Inventory_Sync_Mapping_Manager::get_instance();
    $mapper->create_mapping($site1_id, $site2_id);
}

// ===============================
// 4. Error Handling - Try/Catch
// ===============================

/**
 * ✓ درست: مدیریت استثناهای کامل
 */
function example_correct_error_handling() {
    try {
        $mapper = Inventory_Sync_Mapping_Manager::get_instance();
        $products = $mapper->get_products_for_mapping('site1', 20, 1);
        
        if (is_wp_error($products)) {
            throw new Exception($products->get_error_message());
        }
        
        return $products;
        
    } catch (Exception $e) {
        // Log خطا
        error_log('[Inventory Sync] Error: ' . $e->getMessage());
        
        // Return safe error
        return new WP_Error('fetch_error', 'خطا در دریافت محصولات');
    }
}

/**
 * ✗ غلط: بدون مدیریت خطا
 */
function example_wrong_error_handling() {
    // خطر: اگر خطا بیفتد، صفحه خراب می‌شود
    $mapper = Inventory_Sync_Mapping_Manager::get_instance();
    $products = $mapper->get_products_for_mapping('site1', 20, 1);
    
    // خطا: اگر $products error است، کوئی چک نیست
    return array_slice($products, 0, 10);
}

// ===============================
// 5. Rate Limiting & API Protection
// ===============================

/**
 * ✓ درست: حفاظت از API Abuse
 */
function example_correct_rate_limiting() {
    $user_id = get_current_user_id();
    $rate_key = "inventory_sync_api_{$user_id}";
    $rate_limit = 100; // 100 درخواست
    $time_window = 3600; // در 1 ساعت
    
    // Get current count
    $current_count = get_transient($rate_key);
    
    if (false === $current_count) {
        set_transient($rate_key, 1, $time_window);
    } elseif ($current_count < $rate_limit) {
        set_transient($rate_key, $current_count + 1, $time_window);
    } else {
        return new WP_Error('rate_limited', 'بیش از حد درخواست کردید. لطفاً بعداً امتحان کنید.');
    }
    
    return true;
}

/**
 * ✗ غلط: بدون محدودیت
 */
function example_wrong_rate_limiting() {
    // خطر: کسی می‌تواند API را بمباران کند
    $mapper = Inventory_Sync_Mapping_Manager::get_instance();
    $products = $mapper->get_products_for_mapping('site1', 10000, 1); // 10000 محصول!
}

// ===============================
// 6. Output Escaping
// ===============================

/**
 * ✓ درست: تمام output escape می‌شود
 */
function example_correct_output() {
    $product_name = get_post_meta($product_id, 'name');
    
    // برای HTML
    echo '<div>' . esc_html($product_name) . '</div>';
    
    // برای Attributes
    echo '<input value="' . esc_attr($product_name) . '">';
    
    // برای URL
    echo '<a href="' . esc_url($product_url) . '">Link</a>';
    
    // برای JavaScript
    echo 'var name = "' . esc_js($product_name) . '";';
}

/**
 * ✗ غلط: بدون Escaping
 */
function example_wrong_output() {
    $product_name = get_post_meta($product_id, 'name');
    
    // خطر: XSS Attack
    echo '<div>' . $product_name . '</div>';
    echo '<input value="' . $product_name . '">';
}

// ===============================
// 7. Logging & Monitoring
// ===============================

/**
 * ✓ درست: تمام عملیات logged می‌شوند
 */
function example_correct_logging() {
    $mapper = Inventory_Sync_Mapping_Manager::get_instance();
    
    try {
        $result = $mapper->create_mapping($site1_id, $site2_id);
        
        if (is_wp_error($result)) {
            // Log خطا
            error_log('[Inventory Sync] Mapping creation failed: ' . $result->get_error_message());
            // همچنین database log
            // (سیستم خودکار این را انجام می‌دهد)
        } else {
            error_log('[Inventory Sync] Mapping created: ' . $result['mapping_id']);
        }
        
    } catch (Exception $e) {
        error_log('[Inventory Sync] Exception: ' . $e->getMessage());
    }
}

/**
 * ✗ غلط: بدون Logging
 */
function example_wrong_logging() {
    $result = $mapper->create_mapping($site1_id, $site2_id);
    // خطر: اگر خطا بیفتد، کسی نمی‌داند چه شد
}

// ===============================
// 8. Caching & Performance
// ===============================

/**
 * ✓ درست: استفاده از Cache
 */
function example_correct_caching() {
    $cache_key = "inventory_sync_products_site1_page1";
    $cached = wp_cache_get($cache_key, 'inventory-sync');
    
    if (false !== $cached) {
        return $cached; // بازگرداندن از cache
    }
    
    // دریافت از API
    $products = $this->site1_api->get_products([...]);
    
    // ذخیره در cache برای 1 ساعت
    wp_cache_set($cache_key, $products, 'inventory-sync', 3600);
    
    return $products;
}

/**
 * ✗ غلط: بدون Cache
 */
function example_wrong_caching() {
    // خطر: هر بار دوباره دریافت از API (کند و مهم نیست)
    $products = $this->site1_api->get_products([...]);
    return $products;
}

// ===============================
// 9. نوع‌های اشتباهات رایج
// ===============================

/**
 * خطاهای رایجی که باید اجتناب شود:
 * 
 * 1. SQL Injection: استفاده از concatenation به جای prepare
 * 2. XSS: echo کردن user input بدون escape
 * 3. CSRF: Nonce check نشدن
 * 4. Authentication: بدون بررسی اجازات
 * 5. Data Loss: transaction نداشتن
 * 6. Performance: بدون cache
 * 7. Error Handling: بدون try-catch
 * 8. Logging: بدون error logging
 * 9. API Abuse: بدون rate limiting
 * 10. Data Exposure: Sensitive data در logs یا response
 */

// ===============================
// خلاصه
// ===============================

/**
 * تمام این نکات در کد Inventory Sync اعمال شده‌اند:
 * 
 * ✓ تمام ورودی‌ها validated و sanitized می‌شوند
 * ✓ تمام کوئری‌های دیتابیسی prepared statements هستند
 * ✓ تمام عملیات مجوز دارند
 * ✓ تمام عملیات logged می‌شوند
 * ✓ تمام خطاها مدیریت می‌شوند (try-catch)
 * ✓ تمام output escaped می‌شود
 * ✓ Cache استفاده می‌شود
 * ✓ Rate limiting اعمال می‌شود
 * 
 * سیستم حاضر برای production است!
 */
?>
