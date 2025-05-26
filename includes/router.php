<?php

add_action('init', function () {
    add_action('rest_api_init', function () {
        register_rest_route('custom/v1', '/import-dados-b2bking', [
            'methods' => 'POST',
            'callback' => 'import_b2bking_json_data_direct',
            'permission_callback' => function () {
                $headers = getallheaders();
                return isset($headers['X-Auth-Token']) && hash_equals(B2BKING_API_TOKEN, $headers['X-Auth-Token']);
            },
        ]);
    });
});