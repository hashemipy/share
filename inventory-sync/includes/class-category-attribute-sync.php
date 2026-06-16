<?php

/**
 * Inventory_Sync_Category_Attribute_Sync - انتقال دسته‌بندی و ویژگی‌ها
 * 
 * این کلاس مسئول:
 * 1. انتقال خودکار دسته‌بندی‌ها و والدین آن
 * 2. انتقال خودکار ویژگی‌ها و تکیه‌های آن
 * 3. تطابق دسته‌بندی و ویژگی موجود
 */
class Inventory_Sync_Category_Attribute_Sync {
    
    private $site1_api;
    private $site2_api;
    
    public function __construct($site1_api, $site2_api) {
        $this->site1_api = $site1_api;
        $this->site2_api = $site2_api;
    }
    
    /**
     * انتقال دسته‌بندی‌های محصول
     * شامل والدین و زیردسته‌بندی‌ها
     * 
     * @param array $product_categories لیست دسته‌بندی‌های محصول از سایت 1
     * @return array نقشه‌برداری: site1_id => site2_id
     */
    public function sync_product_categories($product_categories) {
        $category_map = [];
        
        if (empty($product_categories) || !is_array($product_categories)) {
            return $category_map;
        }
        
        foreach ($product_categories as $category) {
            $category_id = $category['id'] ?? 0;
            
            if (!$category_id) {
                continue;
            }
            
            // بررسی اینکه آیا قبلاً mapping داریم
            $existing_mapping = Inventory_Sync_Database::get_category_mapping($category_id);
            if ($existing_mapping && !empty($existing_mapping->site2_category_id)) {
                $category_map[$category_id] = $existing_mapping->site2_category_id;
                continue;
            }
            
            // دریافت اطلاعات کامل دسته‌بندی
            $full_category = $this->site1_api->get_category($category_id);
            
            if (is_wp_error($full_category)) {
                continue;
            }
            
            // انتقال دسته‌بندی والد اگر موجود باشد
            $site2_parent_id = 0;
            if (!empty($full_category['parent'])) {
                $parent_map = $this->sync_product_categories([
                    ['id' => $full_category['parent']]
                ]);
                $site2_parent_id = $parent_map[$full_category['parent']] ?? 0;
            }
            
            // بررسی دسته‌بندی موجود در سایت 2
            $existing_category = $this->find_existing_category(
                $full_category['name'],
                $site2_parent_id
            );
            
            if ($existing_category) {
                $site2_id = $existing_category['id'];
            } else {
                // ایجاد دسته‌بندی جدید در سایت 2
                $new_category_data = [
                    'name' => $full_category['name'],
                    'slug' => sanitize_title($full_category['name']),
                ];
                
                if ($site2_parent_id) {
                    $new_category_data['parent'] = $site2_parent_id;
                }
                
                $new_category = $this->site2_api->create_category($new_category_data);
                
                if (is_wp_error($new_category)) {
                    Inventory_Sync_Database::insert_log(
                        0,
                        $full_category['name'],
                        'sync_category',
                        'سایت 1',
                        'سایت 2',
                        '',
                        '',
                        'failed',
                        $new_category->get_error_message()
                    );
                    continue;
                }
                
                $site2_id = $new_category['id'];
                
                Inventory_Sync_Database::insert_log(
                    0,
                    $full_category['name'],
                    'sync_category',
                    'سایت 1',
                    'سایت 2',
                    '',
                    $new_category['name'],
                    'success'
                );
            }
            
            // ذخیره نقشه‌برداری
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
    
    /**
     * جستجو برای دسته‌بندی موجود در سایت 2
     */
    private function find_existing_category($category_name, $parent_id = 0) {
        $categories = $this->site2_api->get_categories(100, 1);
        
        if (is_wp_error($categories) || empty($categories)) {
            return null;
        }
        
        foreach ($categories as $category) {
            if ($category['name'] === $category_name) {
                if ($parent_id === 0 && $category['parent'] === 0) {
                    return $category;
                } elseif ($category['parent'] === $parent_id) {
                    return $category;
                }
            }
        }
        
        return null;
    }
    
    /**
     * انتقال ویژگی‌های محصول (Attributes)
     * شامل تمام تکیه‌های ویژگی
     * 
     * @param array $product_attributes ویژگی‌های محصول از سایت 1
     * @return array نقشه‌برداری: site1_id => site2_id
     */
    public function sync_product_attributes($product_attributes) {
        $attribute_map = [];
        
        if (empty($product_attributes)) {
            return $attribute_map;
        }
        
        foreach ($product_attributes as $attr) {
            $attribute_id = $attr['id'] ?? 0;
            $attribute_name = $attr['name'] ?? '';
            
            if (!$attribute_id || !$attribute_name) {
                continue;
            }
            
            // دریافت اطلاعات کامل ویژگی
            $full_attribute = $this->site1_api->get_attribute($attribute_id);
            
            if (is_wp_error($full_attribute)) {
                continue;
            }
            
            // بررسی ویژگی موجود در سایت 2
            $existing_attribute = $this->find_existing_attribute($attribute_name);
            
            if ($existing_attribute) {
                $site2_attribute_id = $existing_attribute['id'];
            } else {
                // ایجاد ویژگی جدید در سایت 2
                $new_attribute_data = [
                    'name' => $full_attribute['name'],
                    'slug' => $full_attribute['slug'],
                    'type' => $full_attribute['type'] ?? 'select',
                    'order_by' => $full_attribute['order_by'] ?? 'menu_order',
                    'has_archives' => $full_attribute['has_archives'] ?? false
                ];
                
                $new_attribute = $this->site2_api->create_attribute($new_attribute_data);
                
                if (is_wp_error($new_attribute)) {
                    Inventory_Sync_Database::insert_log(
                        0,
                        $full_attribute['name'],
                        'sync_attribute',
                        'سایت 1',
                        'سایت 2',
                        '',
                        '',
                        'failed',
                        $new_attribute->get_error_message()
                    );
                    continue;
                }
                
                $site2_attribute_id = $new_attribute['id'];
                
                Inventory_Sync_Database::insert_log(
                    0,
                    $full_attribute['name'],
                    'sync_attribute',
                    'سایت 1',
                    'سایت 2',
                    '',
                    $new_attribute['name'],
                    'success'
                );
            }
            
            // انتقال تکیه‌های ویژگی (Attribute Terms)
            $this->sync_attribute_terms(
                $attribute_id,
                $site2_attribute_id,
                $full_attribute['name']
            );
            
            // ذخیره نقشه‌برداری
            Inventory_Sync_Database::add_attribute_mapping(
                $attribute_id,
                $site2_attribute_id,
                $full_attribute['name'],
                $existing_attribute ? $existing_attribute['name'] : $new_attribute['name'],
                $full_attribute['type'] ?? 'select'
            );
            
            $attribute_map[$attribute_id] = $site2_attribute_id;
        }
        
        return $attribute_map;
    }
    
    /**
     * انتقال تکیه‌های ویژگی (مثل رنگ: قرمز، سبز، آبی و غیره)
     */
    private function sync_attribute_terms($site1_attribute_id, $site2_attribute_id, $attribute_name) {
        $terms = $this->site1_api->get_attribute_terms($site1_attribute_id, 100, 1);
        
        if (is_wp_error($terms) || empty($terms)) {
            return;
        }
        
        foreach ($terms as $term) {
            $term_name = $term['name'] ?? '';
            
            if (!$term_name) {
                continue;
            }
            
            // بررسی تکیه موجود در سایت 2
            $existing_term = $this->find_existing_attribute_term(
                $site2_attribute_id,
                $term_name
            );
            
            if (!$existing_term) {
                // ایجاد تکیه جدید
                $new_term_data = [
                    'name' => $term_name,
                    'slug' => sanitize_title($term_name)
                ];
                
                $new_term = $this->site2_api->create_attribute_term(
                    $site2_attribute_id,
                    $new_term_data
                );
                
                if (is_wp_error($new_term)) {
                    Inventory_Sync_Database::insert_log(
                        0,
                        $attribute_name . ': ' . $term_name,
                        'sync_attribute_term',
                        'سایت 1',
                        'سایت 2',
                        '',
                        '',
                        'failed',
                        $new_term->get_error_message()
                    );
                }
            }
        }
    }
    
    /**
     * جستجو برای ویژگی موجود در سایت 2
     */
    private function find_existing_attribute($attribute_name) {
        $attributes = $this->site2_api->get_attributes();
        
        if (is_wp_error($attributes) || empty($attributes)) {
            return null;
        }
        
        foreach ($attributes as $attribute) {
            if ($attribute['name'] === $attribute_name) {
                return $attribute;
            }
        }
        
        return null;
    }
    
    /**
     * جستجو برای تکیه موجود
     */
    private function find_existing_attribute_term($site2_attribute_id, $term_name) {
        $terms = $this->site2_api->get_attribute_terms($site2_attribute_id, 100, 1);
        
        if (is_wp_error($terms) || empty($terms)) {
            return null;
        }
        
        foreach ($terms as $term) {
            if ($term['name'] === $term_name) {
                return $term;
            }
        }
        
        return null;
    }
}
