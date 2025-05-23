<?php
/*
Plugin Name: B2BKing ERP Sync
Description: Custom integration via REST API between ERP (such as PHC) and B2BKing (WooCommerce).
Version: 2.1
Author: José Luís
Copyright: (c) 2025 José Luís
License: Proprietary – All Rights Reserved
*/

add_action('init', function () {
    if (!function_exists('wc_get_product_id_by_sku')) {
        include_once ABSPATH . 'wp-content/plugins/woocommerce/includes/wc-product-functions.php';
    }

    if (!function_exists('wc_get_product_id_by_sku')) return;

    add_action('rest_api_init', function () {
        register_rest_route('custom/v1', '/import-dados-b2bking', [
            'methods' => 'POST',
            'callback' => 'import_b2bking_json_data_direct',
            'permission_callback' => '__return_true',
        ]);
    });
});

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

if (!function_exists('import_b2bking_entries')) {
    function import_b2bking_entries($entries) {
        $results = [];

        foreach ($entries as $index => $item) {
            $tipo = $item['RuleType'] ?? '';

            try {
                if ($tipo === 'SkuGeneralTab') {
                    $sku = $item['SKU'];
                    $group_name = $item['ForWho'];
                    $price = floatval($item['HowMuch']);

                    $product_id = wc_get_product_id_by_sku($sku);
                    $group_id = get_b2bking_group_id_by_name($group_name);

                    if ($product_id && $group_id) {
                        update_post_meta($product_id, 'b2bking_pricegroup_' . $group_id, $price);
                        $results[] = "[$index] Group price updated: Product $sku for group $group_name = $price";
                    } else {
                        $results[] = "[$index] ERROR: Product or group not found ($sku / $group_name)";
                    }
                }

                elseif ($tipo === 'Discount (Percentage)') {
                    $sku = $item['ApliesTo'];
                    $user_name = $item['ForWho'];
                    $discount = floatval($item['HowMuch']);
                    $priority = intval($item['Priority']);

                    $product_id = wc_get_product_id_by_sku($sku);
                    $user = get_user_by('login', $user_name);

                    if ($product_id && $user) {
                        $post_id = wp_insert_post([
                            'post_type' => 'b2bking_rule',
                            'post_status' => 'publish',
                            'post_title' => "Discount $discount% $sku"
                        ]);

                        update_post_meta($post_id, 'b2bking_rule_what', 'discount_percentage');
                        update_post_meta($post_id, 'b2bking_rule_howmuch', $discount);
                        update_post_meta($post_id, 'b2bking_rule_priority', $priority);
                        update_post_meta($post_id, 'b2bking_rule_applies', 'product_' . $product_id);
                        update_post_meta($post_id, 'b2bking_rule_who', 'user_' . $user->ID);

                        $results[] = "[$index] Discount rule created for $user_name on product $sku ($discount%)";
                    } else {
                        $results[] = "[$index] ERROR: Product or user not found ($sku / $user_name)";
                    }
                }

                elseif ($tipo === 'Fixed Price') {
                    $sku = $item['ApliesTo'];
                    $user_name = $item['ForWho'];
                    $price = floatval($item['HowMuch']);

                    $product_id = wc_get_product_id_by_sku($sku);
                    $user = get_user_by('login', $user_name);

                    if ($product_id && $user) {
                        update_post_meta($product_id, 'b2bking_fixedprice_user_' . $user->ID, $price);
                        $results[] = "[$index] Fixed price assigned: Product $sku for user $user_name = $price";
                    } else {
                        $results[] = "[$index] ERROR: Product or user not found ($sku / $user_name)";
                    }
                }

                else {
                    $results[] = "[$index] Unknown rule type: $tipo";
                }

            } catch (Exception $e) {
                $results[] = "[$index] Unexpected error: " . $e->getMessage();
            }
        }

        return $results;
    }
}

if (!function_exists('get_b2bking_group_id_by_name')) {
    function get_b2bking_group_id_by_name($name) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'b2bking_group'",
            $name
        ));
    }
}