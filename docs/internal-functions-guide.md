# B2BKing ERP Sync - Internal Functions Integration Guide

## Overview

Instead of using REST API endpoints, you can call B2BKing ERP Sync functions directly from within WordPress. This approach:

- ✅ **No authentication required** - runs within WordPress context
- ✅ **Better performance** - no HTTP overhead
- ✅ **More portable** - works on any WordPress installation
- ✅ **Easier to integrate** - just function calls
- ✅ **Better error handling** - direct PHP exceptions

## Integration Methods

### Method 1: Simple Function Calls

```php
// Include the plugin if not already loaded
if (function_exists('b2bking_erp_create_rule')) {
    
    // Create a single rule
    $result = b2bking_erp_create_rule([
        'RuleType' => 'Fixed Price',
        'ApliesTo' => 'SKU123',
        'ForWho' => 'username',
        'HowMuch' => '10.50',
        'Priority' => '2'
    ]);
    
    // Create multiple rules
    $batch_result = b2bking_erp_create_rules([
        [
            'RuleType' => 'GroupPrice',
            'SKU' => 'SKU456',
            'ForWho' => 'Wholesale',
            'HowMuch' => '25.00',
            'Priority' => '1'
        ],
        // ... more rules
    ]);
}
```

### Method 2: Static Class Methods

```php
// Check if class exists
if (class_exists('B2BKing_ERP_Sync')) {
    
    // Create rule
    $result = B2BKing_ERP_Sync::create_rule($rule_data);
    
    // Create user
    $user_id = B2BKing_ERP_Sync::create_user($user_data);
    
    // Create group
    $group_id = B2BKing_ERP_Sync::create_group('Group Name');
}
```

## ERP Integration Scenarios

### Scenario 1: Direct Database Integration

```php
function sync_from_erp_database() {
    // Connect to ERP database
    $erp_db = new PDO('mysql:host=erp_host;dbname=erp_db', $user, $pass);
    
    // Get pricing rules
    $stmt = $erp_db->query("
        SELECT 
            product_sku,
            customer_code, 
            special_price,
            priority,
            'Fixed Price' as rule_type
        FROM erp_pricing 
        WHERE active = 1
    ");
    
    $rules = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rules[] = [
            'RuleType' => $row['rule_type'],
            'ApliesTo' => $row['product_sku'],
            'ForWho' => $row['customer_code'],
            'HowMuch' => $row['special_price'],
            'Priority' => $row['priority']
        ];
    }
    
    // Sync to B2BKing
    if (!empty($rules)) {
        return B2BKing_ERP_Sync::create_rules_batch($rules);
    }
    
    return ['status' => 'no_data'];
}
```

### Scenario 2: CSV File Import

```php
function import_csv_pricing($file_path) {
    
    if (!file_exists($file_path)) {
        return ['error' => 'File not found'];
    }
    
    $rules = [];
    $file = fopen($file_path, 'r');
    
    // Skip header
    fgetcsv($file);
    
    while (($data = fgetcsv($file)) !== FALSE) {
        $rules[] = [
            'RuleType' => $data[0],
            'ApliesTo' => $data[1],
            'ForWho' => $data[2],
            'HowMuch' => $data[3],
            'Priority' => $data[4] ?? '1'
        ];
    }
    
    fclose($file);
    
    return B2BKing_ERP_Sync::create_rules_batch($rules);
}
```

### Scenario 3: WordPress Cron Automation

```php
// Add to functions.php or plugin
add_action('wp', 'setup_erp_sync_cron');
function setup_erp_sync_cron() {
    if (!wp_next_scheduled('erp_sync_hourly')) {
        wp_schedule_event(time(), 'hourly', 'erp_sync_hourly');
    }
}

add_action('erp_sync_hourly', 'perform_erp_sync');
function perform_erp_sync() {
    
    // Get data from your ERP source
    $erp_data = get_latest_erp_pricing_data();
    
    if (!empty($erp_data)) {
        
        // Process in smaller batches to avoid memory issues
        $batches = array_chunk($erp_data, 50);
        
        foreach ($batches as $batch) {
            $result = B2BKing_ERP_Sync::create_rules_batch($batch);
            
            // Log results for debugging
            error_log('ERP Sync: ' . json_encode($result));
            
            // Small delay between batches
            sleep(1);
        }
    }
}
```

### Scenario 4: Admin Interface Integration

```php
// Add admin menu
add_action('admin_menu', 'add_erp_sync_menu');
function add_erp_sync_menu() {
    add_submenu_page(
        'tools.php',
        'ERP Sync',
        'ERP Sync',
        'manage_options',
        'erp-sync',
        'erp_sync_admin_page'
    );
}

function erp_sync_admin_page() {
    
    if (isset($_POST['sync_now'])) {
        
        // Manual sync trigger
        $file = $_FILES['erp_file'] ?? null;
        
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            
            $result = import_csv_pricing($file['tmp_name']);
            
            echo '<div class="notice notice-success">';
            echo '<p>Sync completed: ' . json_encode($result) . '</p>';
            echo '</div>';
        }
    }
    
    ?>
    <div class="wrap">
        <h1>ERP Sync</h1>
        
        <form method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th>Upload CSV File</th>
                    <td>
                        <input type="file" name="erp_file" accept=".csv" required>
                        <p class="description">
                            CSV format: RuleType,ApliesTo,ForWho,HowMuch,Priority
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Sync Now', 'primary', 'sync_now'); ?>
        </form>
        
        <h2>Manual Sync</h2>
        <button onclick="manualSync()" class="button">Sync from Database</button>
        
        <script>
        function manualSync() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=manual_erp_sync'
            })
            .then(response => response.json())
            .then(data => {
                alert('Sync result: ' + JSON.stringify(data));
            });
        }
        </script>
    </div>
    <?php
}

// AJAX handler for manual sync
add_action('wp_ajax_manual_erp_sync', 'handle_manual_sync');
function handle_manual_sync() {
    
    $result = sync_from_erp_database();
    
    wp_send_json($result);
}
```

## Comparison: REST API vs Internal Functions

| Feature | REST API | Internal Functions |
|---------|----------|-------------------|
| **Authentication** | Required (tokens) | Not needed |
| **Performance** | HTTP overhead | Direct function calls |
| **Portability** | Needs endpoint setup | Works anywhere |
| **Integration** | External calls | Native WordPress |
| **Error Handling** | HTTP status codes | PHP exceptions |
| **Debugging** | Network logs | WordPress logs |
| **Security** | Token-based | WordPress permissions |

## Recommended Approach

For **maximum portability and ease of use**, I recommend:

1. **Keep both approaches** - REST API for external systems, Internal functions for WordPress integrations
2. **Use static class methods** - `B2BKing_ERP_Sync::create_rule()`
3. **Add WordPress cron** - for automated sync
4. **Include admin interface** - for manual operations

This way, the plugin works for:
- ✅ External ERP systems (REST API)
- ✅ WordPress themes/plugins (Internal functions)
- ✅ Automated sync (Cron jobs)
- ✅ Manual operations (Admin interface)

Would you like me to implement this dual approach in your plugin?