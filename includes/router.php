<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    // Use transient to avoid duplicate logs per session
    $log_key = 'b2bking_route_registration_logged_' . B2BKING_ERP_SYNC_VERSION;
    $already_logged = get_transient($log_key);
    
    if (!$already_logged) {
        error_log('B2BKing ERP Sync: Attempting to register REST route');
    }

    if (!defined('B2BKING_API_TOKEN')) {
        if (!$already_logged) {
            error_log('B2BKing ERP Sync: ERROR - Token not defined in router');
        }
        return;
    }

    if (!function_exists('import_b2bking_json_data_direct')) {
        if (!$already_logged) {
            error_log('B2BKing ERP Sync: ERROR - Callback function not found');
        }
        return;
    }

    $registered = register_rest_route('custom/v1', '/import-dados-b2bking', [
        'methods' => 'POST',
        'callback' => 'import_b2bking_json_data_direct',
        'permission_callback' => function () {
            $headers = getallheaders();
            $token = $headers['X-Auth-Token'] ?? '';
            $valid = hash_equals(B2BKING_API_TOKEN, $token);
            // Only log auth checks if debug is enabled and not already logged
            if (defined('WP_DEBUG') && WP_DEBUG && !get_transient('b2bking_auth_logged')) {
                error_log('B2BKing ERP Sync: Auth check - ' . ($valid ? 'VALID' : 'INVALID'));
                set_transient('b2bking_auth_logged', true, MINUTE_IN_SECONDS);
            }
            return $valid;
        },
    ]);

    if (!$already_logged) {
        if ($registered) {
            error_log('B2BKing ERP Sync: Route registered successfully!');
        } else {
            error_log('B2BKing ERP Sync: FAILED to register route');
        }
        
        // Set transient to prevent duplicate logs for the next hour
        set_transient($log_key, true, HOUR_IN_SECONDS);
    }
});
