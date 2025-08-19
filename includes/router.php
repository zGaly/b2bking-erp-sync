<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    error_log('B2BKing ERP Sync: Attempting to register REST route');

    if (!defined('B2BKING_API_TOKEN')) {
        error_log('B2BKing ERP Sync: ERROR - Token not defined in router');
        return;
    }

    if (!function_exists('import_b2bking_json_data_direct')) {
        error_log('B2BKing ERP Sync: ERROR - Callback function not found');
        return;
    }

    $registered = register_rest_route('custom/v1', '/import-dados-b2bking', [
        'methods' => 'POST',
        'callback' => 'import_b2bking_json_data_direct',
        'permission_callback' => function () {
            $headers = getallheaders();
            $token = $headers['X-Auth-Token'] ?? '';
            $valid = hash_equals(B2BKING_API_TOKEN, $token);
            error_log('B2BKing ERP Sync: Auth check - ' . ($valid ? 'VALID' : 'INVALID'));
            return $valid;
        },
    ]);

    if ($registered) {
        error_log('B2BKing ERP Sync: Route registered successfully!');
    } else {
        error_log('B2BKing ERP Sync: FAILED to register route');
    }
});
