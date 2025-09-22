<?php
/**
 * Usage Examples for Internal Function Calls
 * B2BKing ERP Sync v3.0
 */

// ==============================================
// OPTION 1: Using WordPress Actions/Hooks
// ==============================================

// From another plugin or theme functions.php:
function my_erp_sync_integration() {
    
    // Single rule
    $rule_data = [
        'RuleType' => 'Fixed Price',
        'ApliesTo' => 'SKU123',
        'ForWho' => [
            'no' => '1234',
            'nome' => 'Test User',
            'email' => 'test@example.com',
            'inativo' => false,
            'tipodesc' => 'Customer',
            'tabelaPrecos' => 'A'
        ],
        'HowMuch' => '10.50',
        'Priority' => '2'
    ];
    
    // Call via action hook
    $result = apply_filters('b2bking_erp_sync_data', $rule_data);
    
    // Or call direct function
    $result = b2bking_erp_create_rule($rule_data);
    
    return $result;
}

// ==============================================
// OPTION 2: Using Static Class Methods
// ==============================================

function advanced_erp_integration() {
    
    // Create single rule
    $rule_result = B2BKing_ERP_Sync::create_rule([
        'RuleType' => 'Discount (Percentage)',
        'ApliesTo' => 'SKU456',
        'ForWho' => 'user123',
        'HowMuch' => '15',
        'Priority' => '1'
    ]);
    
    // Create multiple rules in batch
    $batch_rules = [
        [
            'RuleType' => 'GroupPrice',
            'SKU' => 'SKU789',
            'ForWho' => 'Wholesale',
            'HowMuch' => '25.00',
            'Priority' => '3'
        ],
        [
            'RuleType' => 'Fixed Price',
            'ApliesTo' => 'SKU101',
            'ForWho' => 'premium_user',
            'HowMuch' => '12.99',
            'Priority' => '2'
        ]
    ];
    
    $batch_result = B2BKing_ERP_Sync::create_rules_batch($batch_rules);
    
    // Create user separately
    $user_id = B2BKing_ERP_Sync::create_user([
        'no' => '5678',
        'nome' => 'New Customer',
        'email' => 'newcustomer@example.com',
        'inativo' => false,
        'tipodesc' => 'VIP',
        'tabelaPrecos' => 'PREMIUM'
    ]);
    
    // Create group separately
    $group_id = B2BKing_ERP_Sync::create_group('Special Customers');
    
    return [
        'rule_result' => $rule_result,
        'batch_result' => $batch_result,
        'user_id' => $user_id,
        'group_id' => $group_id
    ];
}

// ==============================================
// OPTION 3: WordPress Cron Integration
// ==============================================

// Schedule automatic sync
add_action('wp', 'schedule_erp_sync');
function schedule_erp_sync() {
    if (!wp_next_scheduled('erp_hourly_sync')) {
        wp_schedule_event(time(), 'hourly', 'erp_hourly_sync');
    }
}

// Cron job function
add_action('erp_hourly_sync', 'perform_erp_sync');
function perform_erp_sync() {
    
    // Get data from ERP (database, file, API, etc.)
    $erp_data = get_erp_data_from_source();
    
    if (!empty($erp_data)) {
        // Process in batches
        $batch_size = 50;
        $batches = array_chunk($erp_data, $batch_size);
        
        foreach ($batches as $batch) {
            $result = B2BKing_ERP_Sync::create_rules_batch($batch);
            
            // Log results
            error_log('ERP Sync Batch: ' . json_encode($result));
        }
    }
}

// ==============================================
// OPTION 4: Database Integration
// ==============================================

function sync_from_database() {
    global $wpdb;
    
    // Get data from external database
    $external_db = new wpdb('username', 'password', 'database', 'host');
    
    $rules = $external_db->get_results("
        SELECT 
            product_sku as ApliesTo,
            customer_code as ForWho,
            special_price as HowMuch,
            priority as Priority,
            'Fixed Price' as RuleType
        FROM erp_special_prices 
        WHERE is_active = 1
    ", ARRAY_A);
    
    if (!empty($rules)) {
        $result = B2BKing_ERP_Sync::create_rules_batch($rules);
        return $result;
    }
    
    return ['status' => 'no_data'];
}

// ==============================================
// OPTION 5: CSV/File Integration
// ==============================================

function sync_from_csv_file($file_path) {
    
    if (!file_exists($file_path)) {
        return ['status' => 'error', 'message' => 'File not found'];
    }
    
    $csv_data = [];
    $handle = fopen($file_path, 'r');
    
    // Skip header row
    $header = fgetcsv($handle);
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        $csv_data[] = [
            'RuleType' => $row[0],
            'ApliesTo' => $row[1],
            'ForWho' => $row[2],
            'HowMuch' => $row[3],
            'Priority' => $row[4] ?? '1'
        ];
    }
    
    fclose($handle);
    
    if (!empty($csv_data)) {
        return B2BKing_ERP_Sync::create_rules_batch($csv_data);
    }
    
    return ['status' => 'no_data'];
}

// ==============================================
// OPTION 6: External API Integration
// ==============================================

function sync_from_external_api($api_endpoint, $api_key) {
    
    $response = wp_remote_get($api_endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ]
    ]);
    
    if (is_wp_error($response)) {
        return ['status' => 'error', 'message' => $response->get_error_message()];
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (!empty($data)) {
        return B2BKing_ERP_Sync::create_rules_batch($data);
    }
    
    return ['status' => 'no_data'];
}