    <?php
    /*
    Plugin Name: B2BKing ERP Sync
    Description: Custom integration via REST API between ERP (such as PHC) and B2BKing (WooCommerce).
    Version: 2.2
    Author: José Luís
    Copyright: (c) 2025 José Luís
    License: Proprietary – All Rights Reserved
    */

    if (!defined('B2BKING_API_TOKEN')) {
        return;
    }

    function b2bking_erp_sync_bootstrap()
    {
        require_once plugin_dir_path(__FILE__) . 'includes/router.php';
        require_once plugin_dir_path(__FILE__) . 'includes/handlers.php';
        require_once plugin_dir_path(__FILE__) . 'includes/utils.php';
    }
    add_action('plugins_loaded', 'b2bking_erp_sync_bootstrap');

    function import_b2bking_json_data_direct($request)
    {
        error_log('B2BKing ERP Sync: Callback function called!');

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

    function b2bking_erp_sync_register_route()
    {
        register_rest_route('custom/v1', '/import-dados-b2bking', [
            'methods' => 'POST',
            'callback' => 'import_b2bking_json_data_direct',
            'permission_callback' => '__return_true',
        ]);
    }
    add_action('rest_api_init', 'b2bking_erp_sync_register_route');
