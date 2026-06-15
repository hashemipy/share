<?php

/**
 * Category Helper - کمک به انتقال صحیح دسته‌بندی‌های سلسله‌مراتبی
 */
class Inventory_Sync_Category_Helper {
    
    private $site1_api;
    private $site2_api;
    private $category_map = [];
    
    public function __construct($site1_api, $site2_api) {
        $this->site1_api = $site1_api;
        $this->site2_api = $site2_api;
    }
    
    /**
     * دریافت دسته‌بندی‌های کامل با سلسله‌مراتب
     */
    public function get_category_hierarchy($site_api, $category_id = 0) {
        $category = null;
        $all_categories = [];
        $page = 1;
        
        // دریافت تمام دسته‌بندی‌ها
        do {
            $categories = $site_api->get_categories(100, $page);
            
            if (is_wp_error($categories)) {
                return null;
            }
            
            if (empty($categories) || !is_array($categories)) {
                break;
            }
            
            $all_categories = array_merge($all_categories, $categories);
            $page++;
        } while (count($categories) === 100);
        
        if (empty($all_categories)) {
            return null;
        }
        
        // پیدا کردن دسته‌بندی درخواست‌شده
        foreach ($all_categories as $cat) {
            if ($cat['id'] === $category_id) {
                $category = $cat;
                break;
            }
        }
        
        if (!$category) {
            return null;
        }
        
        // ساخت سلسله‌مراتب (از والد تا فرزند)
        $hierarchy = [];
        $current_id = $category['id'];
        
        while ($current_id !== 0) {
            foreach ($all_categories as $cat) {
                if ($cat['id'] === $current_id) {
                    array_unshift($hierarchy, $cat);
                    $current_id = $cat['parent'] ?? 0;
                    break;
                }
            }
            
            // جلوگیری از حلقه‌ی بی‌پایان
            if ($current_id === 0) {
                break;
            }
        }
        
        return $hierarchy;
    }
    
    /**
     * انتقال دسته‌بندی با سلسله‌مراتب
     */
    public function sync_category_with_hierarchy($category_id) {
        // دریافت سلسله‌مراتب دسته‌بندی
        $hierarchy = $this->get_category_hierarchy($this->site1_api, $category_id);
        
        if (!$hierarchy) {
            return new WP_Error('category_not_found', 'دسته‌بندی پیدا نشد');
        }
        
        $parent_map = [];
        
        foreach ($hierarchy as $category) {
            $cat_name = $category['name'] ?? '';
            if (empty($cat_name)) {
                continue;
            }
            
            // چک کن که دسته‌بندی در سایت 2 موجود است
            $existing_cat = $this->site2_api->get_category_by_name($cat_name);
            
            if (!$existing_cat) {
                // تعیین parent_id برای دسته‌بندی جدید
                $parent_id = 0;
                if (!empty($category['parent'])) {
                    $parent_id = $parent_map[$category['parent']] ?? 0;
                }
                
                // ایجاد دسته‌بندی
                $created = $this->site2_api->create_category($cat_name, $parent_id);
                
                if (is_wp_error($created)) {
                    return $created;
                }
                
                $parent_map[$category['id']] = $created['id'];
            } else {
                $parent_map[$category['id']] = $existing_cat['id'];
            }
        }
        
        return true;
    }
}
