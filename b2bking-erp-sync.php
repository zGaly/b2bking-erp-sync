<?php
/*
Plugin Name: B2BKing ERP Sync
Description: Custom integration via REST API and Internal Functions between ERP (such as PHC) and B2BKing (WooCommerce). Supports both external API calls and direct WordPress function calls for maximum portability.
Version: 3.0
Author: José Luís
Copyright: (c) 2025 José Luís
License: Proprietary – All Rights Reserved
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin version
define('B2BKING_ERP_SYNC_VERSION', '3.0');

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
    
    // Log plugin initialization only once per session
    $log_key = 'b2bking_erp_sync_logged_v' . B2BKING_ERP_SYNC_VERSION;
    if (!get_transient($log_key)) {
        error_log('B2BKing ERP Sync v' . B2BKING_ERP_SYNC_VERSION . ' loaded - Both REST API and Internal Functions available');
        set_transient($log_key, true, HOUR_IN_SECONDS);
    }
}

// REST API callback function
function import_b2bking_json_data_direct($request)
{
    // Log callback only when debug is enabled or first time per session
    $callback_log_key = 'b2bking_callback_logged_v' . B2BKING_ERP_SYNC_VERSION;
    if ((defined('WP_DEBUG') && WP_DEBUG) || !get_transient($callback_log_key)) {
        error_log('B2BKing ERP Sync: Callback function called!');
        if (!get_transient($callback_log_key)) {
            set_transient($callback_log_key, true, HOUR_IN_SECONDS);
        }
    }

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

// Register REST API endpoint
function b2bking_erp_sync_register_route()
{
    register_rest_route('custom/v1', '/import-dados-b2bking', [
        'methods' => 'POST',
        'callback' => 'import_b2bking_json_data_direct',
        'permission_callback' => '__return_true',
    ]);
}

// Register hooks
add_action('plugins_loaded', 'b2bking_erp_sync_bootstrap');
add_action('rest_api_init', 'b2bking_erp_sync_register_route');