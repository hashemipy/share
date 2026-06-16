<?php

class Inventory_Sync_Settings {
    
    const OPTION_PREFIX = 'inventory_sync_';
    
    public static function get_site1_name() {
        return get_option(self::OPTION_PREFIX . 'site1_name', 'سایت 1');
    }
    
    public static function get_site1_url() {
        return get_option(self::OPTION_PREFIX . 'site1_url', '');
    }
    
    public static function get_site1_key() {
        return get_option(self::OPTION_PREFIX . 'site1_key', '');
    }
    
    public static function get_site1_secret() {
        return get_option(self::OPTION_PREFIX . 'site1_secret', '');
    }
    
    public static function get_site2_name() {
        return get_option(self::OPTION_PREFIX . 'site2_name', 'سایت 2');
    }
    
    public static function get_site2_url() {
        return get_option(self::OPTION_PREFIX . 'site2_url', '');
    }
    
    public static function get_site2_key() {
        return get_option(self::OPTION_PREFIX . 'site2_key', '');
    }
    
    public static function get_site2_secret() {
        return get_option(self::OPTION_PREFIX . 'site2_secret', '');
    }
    
    public static function get_sync_direction() {
        return get_option(self::OPTION_PREFIX . 'sync_direction', 'site1_to_site2');
    }
    
    public static function get_auto_sync_enabled() {
        return (bool) get_option(self::OPTION_PREFIX . 'auto_sync_enabled', false);
    }
    
    public static function get_current_site_type() {
        return get_option(self::OPTION_PREFIX . 'current_site_type', '1');
    }
    
    public static function set_current_site_type($type) {
        if (!in_array($type, ['1', '2'])) {
            return false;
        }
        update_option(self::OPTION_PREFIX . 'current_site_type', $type);
        return true;
    }
    
    public static function is_site_2() {
        return self::get_current_site_type() === '2';
    }
    
    public static function should_sync_to_site1() {
        return (bool) get_option(self::OPTION_PREFIX . 'sync_to_site1', false);
    }
    
    public static function save_settings($data) {
        if (empty($data) || !is_array($data)) {
            return false;
        }
        
        update_option(self::OPTION_PREFIX . 'site1_name', sanitize_text_field($data['site1_name'] ?? ''));
        update_option(self::OPTION_PREFIX . 'site1_url', esc_url_raw($data['site1_url'] ?? ''));
        update_option(self::OPTION_PREFIX . 'site1_key', sanitize_text_field($data['site1_key'] ?? ''));
        update_option(self::OPTION_PREFIX . 'site1_secret', sanitize_text_field($data['site1_secret'] ?? ''));
        
        update_option(self::OPTION_PREFIX . 'site2_name', sanitize_text_field($data['site2_name'] ?? ''));
        update_option(self::OPTION_PREFIX . 'site2_url', esc_url_raw($data['site2_url'] ?? ''));
        update_option(self::OPTION_PREFIX . 'site2_key', sanitize_text_field($data['site2_key'] ?? ''));
        update_option(self::OPTION_PREFIX . 'site2_secret', sanitize_text_field($data['site2_secret'] ?? ''));
        
        update_option(self::OPTION_PREFIX . 'sync_direction', sanitize_text_field($data['sync_direction'] ?? 'site1_to_site2'));
        update_option(self::OPTION_PREFIX . 'auto_sync_enabled', !empty($data['auto_sync_enabled']));
        update_option(self::OPTION_PREFIX . 'sync_interval', intval($data['sync_interval'] ?? 300));
        
        return true;
    }
}
