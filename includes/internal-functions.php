<?php
/**
 * Internal Function-Based B2BKing ERP Sync
 * Version: 3.0 (Function-based)
 */

// Create internal action hooks for ERP sync
add_action('b2bking_erp_sync_data', 'handle_b2bking_erp_sync', 10, 1);

/**
 * Main sync function - can be called internally
 * 
 * @param array $data - Same JSON structure as before
 * @return array - Results array
 */
function handle_b2bking_erp_sync($data) {
    // Validate data structure
    if (empty($data)) {
        return ['status' => 'error', 'message' => 'No data provided'];
    }
    
    // Handle single entry or array
    $entries = is_array($data) && isset($data[0]) ? $data : [$data];
    
    // Use existing import function
    $results = import_b2bking_entries($entries);
    
    return [
        'status' => 'completed',
        'report' => $results,
        'processed' => count($entries)
    ];
}

/**
 * Simple function for direct calls
 */
function b2bking_erp_create_rule($rule_data) {
    return handle_b2bking_erp_sync($rule_data);
}

/**
 * Batch function for multiple rules
 */
function b2bking_erp_create_rules_batch($rules_array) {
    return handle_b2bking_erp_sync($rules_array);
}