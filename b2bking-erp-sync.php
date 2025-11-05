<?php
/*
Plugin Name: B2BKing ERP Sync
Description: Custom integration via REST API and Internal Functions between ERP (such as PHC) and B2BKing (WooCommerce). Supports both external API calls and direct WordPress function calls for maximum portability.
Version: 3.1
Author: José Luís
Copyright: (c) 2025 José Luís
License: Proprietary – All Rights Reserved
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin version
define('B2BKING_ERP_SYNC_VERSION', '3.1');

// API token is only required for REST API endpoints
// Internal functions work without token
if (!defined('B2BKING_API_TOKEN')) {
    define('B2BKING_API_TOKEN', ''); // Empty token disables REST API
}

// Bootstrap function
function b2bking_erp_sync_bootstrap()
{
    // Core files (required for both REST API and Internal Functions)
    require_once plugin_dir_path(__FILE__) . 'includes/router.php';
    require_once plugin_dir_path(__FILE__) . 'includes/handlers.php';
    require_once plugin_dir_path(__FILE__) . 'includes/utils.php';
    
    // v3.0 Internal Functions (work without API token)
    require_once plugin_dir_path(__FILE__) . 'includes/static-class.php';
    require_once plugin_dir_path(__FILE__) . 'includes/internal-functions.php';
    
    // Logging disabled to prevent log spam
    // To enable logging for debugging, uncomment the lines below:
    // $log_key = 'b2bking_erp_sync_logged_v' . B2BKING_ERP_SYNC_VERSION;
    // if (!get_transient($log_key)) {
    //     error_log('B2BKing ERP Sync v' . B2BKING_ERP_SYNC_VERSION . ' loaded - Both REST API and Internal Functions available');
    //     set_transient($log_key, true, HOUR_IN_SECONDS);
    // }
}

// REST API callback function
function import_b2bking_json_data_direct($request)
{
    // Logging disabled to prevent log spam
    // To enable logging for debugging, uncomment the line below:
    // error_log('B2BKing ERP Sync: Callback function called!');

    $data = $request->get_json_params();
    if (!is_array($data)) {
        return new WP_Error('invalid_json', 'Malformed or empty JSON.', ['status' => 400]);
    }

    // Fix: Check if it's a single object or array of objects
    $entries = $data;

    // If it's a single object (has RuleType key), wrap it in an array
    if (isset($data['RuleType'])) {
        $entries = [$data];
    }

    $results = import_b2bking_entries($entries);

    return rest_ensure_response([
        'status' => 'completed',
        'report' => $results
    ]);
}

// Register hooks
add_action('plugins_loaded', 'b2bking_erp_sync_bootstrap');
// REST API endpoint registration is handled in includes/router.php with proper authentication