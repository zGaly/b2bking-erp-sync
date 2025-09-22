<?php
/**
 * B2BKing ERP Sync - Static Class Implementation
 * Version: 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class B2BKing_ERP_Sync {
    
    /**
     * Create a single B2BKing rule
     * 
     * @param array $rule_data Rule data in standard format
     * @return array Result with status and message
     */
    public static function create_rule($rule_data) {
        if (!self::check_dependencies()) {
            return ['status' => 'error', 'message' => 'Missing dependencies (WooCommerce or B2BKing)'];
        }
        
        $results = self::import_entries([$rule_data]);
        
        return [
            'status' => 'completed',
            'rule_id' => $results[0]['rule_id'] ?? null,
            'message' => $results[0] ?? 'Unknown error'
        ];
    }
    
    /**
     * Create multiple B2BKing rules
     * 
     * @param array $rules_array Array of rule data
     * @return array Results array
     */
    public static function create_rules_batch($rules_array) {
        if (!self::check_dependencies()) {
            return ['status' => 'error', 'message' => 'Missing dependencies'];
        }
        
        $results = self::import_entries($rules_array);
        
        return [
            'status' => 'completed',
            'processed' => count($rules_array),
            'results' => $results
        ];
    }
    
    /**
     * Create or update user with ERP data
     * 
     * @param array $user_data User data from ERP
     * @return int|false User ID or false on error
     */
    public static function create_user($user_data) {
        return self::create_user_if_not_exists($user_data);
    }
    
    /**
     * Create B2BKing group
     * 
     * @param string $group_name Group name
     * @return int|false Group ID or false on error
     */
    public static function create_group($group_name) {
        return self::create_b2bking_group_if_not_exists($group_name);
    }
    
    /**
     * Check if required plugins are active
     */
    private static function check_dependencies() {
        return (
            function_exists('wc_get_product_id_by_sku') && 
            function_exists('wp_insert_post') &&
            class_exists('WooCommerce')
        );
    }
    
    /**
     * Import entries using existing logic
     */
    private static function import_entries($entries) {
        // Include the existing handlers.php functions
        require_once plugin_dir_path(__FILE__) . 'handlers.php';
        
        return import_b2bking_entries($entries);
    }
    
    // Import existing helper functions as static methods
    private static function create_user_if_not_exists($user_data) {
        require_once plugin_dir_path(__FILE__) . 'handlers.php';
        return create_user_if_not_exists($user_data);
    }
    
    private static function create_b2bking_group_if_not_exists($group_name) {
        require_once plugin_dir_path(__FILE__) . 'handlers.php';
        return create_b2bking_group_if_not_exists($group_name);
    }
}

// Global helper functions for easier access
function b2bking_erp_create_rule($rule_data) {
    return B2BKing_ERP_Sync::create_rule($rule_data);
}

function b2bking_erp_create_rules($rules_array) {
    return B2BKing_ERP_Sync::create_rules_batch($rules_array);
}

function b2bking_erp_create_user($user_data) {
    return B2BKing_ERP_Sync::create_user($user_data);
}

function b2bking_erp_create_group($group_name) {
    return B2BKing_ERP_Sync::create_group($group_name);
}