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


function b2bking_erp_sync_bootstrap() {
    require_once plugin_dir_path(__FILE__) . 'includes/router.php';
    require_once plugin_dir_path(__FILE__) . 'includes/handlers.php';
    require_once plugin_dir_path(__FILE__) . 'includes/utils.php';
}
add_action('plugins_loaded', 'b2bking_erp_sync_bootstrap');

function import_b2bking_json_data_direct($request) {
    $data = $request->get_json_params();
    if (!is_array($data)) {
        return new WP_Error('invalid_json', 'Malformed or empty JSON.', ['status' => 400]);
    }

    $results = import_b2bking_entries($data);
    return rest_ensure_response([
        'status' => 'completed',
        'report' => $results
    ]);
}