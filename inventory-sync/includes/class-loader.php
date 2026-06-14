<?php

class Inventory_Sync_Loader {
    
    public function __construct() {
        $this->autoload();
    }
    
    private function autoload() {
        spl_autoload_register([$this, 'load_class']);
    }
    
    public function load_class($class) {
        if (strpos($class, 'Inventory_Sync_') === false) {
            return;
        }
        
        $class_name = str_replace('Inventory_Sync_', '', $class);
        $class_file = str_replace('_', '-', strtolower($class_name));
        $file_path = INVENTORY_SYNC_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
        
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}
