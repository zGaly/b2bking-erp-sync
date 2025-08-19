<?php

// Add the missing functions that we discussed earlier
if (!function_exists('wc_get_product_id_by_sku')) {
    function wc_get_product_id_by_sku($sku) {
        global $wpdb;
        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value=%s LIMIT 1",
                $sku
            )
        );
        return $product_id ? intval($product_id) : false;
    }
}

function create_woocommerce_product_if_not_exists($sku) {
    $product_id = wc_get_product_id_by_sku($sku);
    if ($product_id) {
        return $product_id;
    }

    $product = new WC_Product();
    $product->set_name($sku);
    $product->set_sku($sku);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_price(0);
    $product->set_regular_price(0);
    
    $new_product_id = $product->save();
    
    if ($new_product_id) {
        error_log("B2BKing ERP Sync: Created new product with SKU: $sku (ID: $new_product_id)");
        return $new_product_id;
    }
    
    return false;
}

function create_b2bking_group_if_not_exists($group_name) {
    $group_id = get_b2bking_group_id_by_name($group_name);
    if ($group_id) {
        return $group_id;
    }

    $new_group = wp_insert_post([
        'post_title' => $group_name,
        'post_type' => 'b2bking_group',
        'post_status' => 'publish',
        'post_content' => '',
    ]);

    if ($new_group && !is_wp_error($new_group)) {
        error_log("B2BKing ERP Sync: Created new B2BKing group: $group_name (ID: $new_group)");
        return $new_group;
    }
    
    return false;
}

function create_user_if_not_exists($username) {
    $user = get_user_by('login', $username);
    if ($user) {
        return $user->ID;
    }

    $user_id = wp_create_user($username, wp_generate_password(), $username . '@generated.local');
    
    if (!is_wp_error($user_id)) {
        error_log("B2BKing ERP Sync: Created new user: $username (ID: $user_id)");
        return $user_id;
    }
    
    return false;
}

function import_b2bking_entries($entries) {
    $results = [];

    foreach ($entries as $index => $item) {
        $tipo = $item['RuleType'] ?? '';

        try {
            if (in_array($tipo, ['GroupPrice', 'SkuGeneralTab'])) {
                $sku = sanitize_text($item['SKU'] ?? '');
                $group_name = sanitize_text($item['ForWho'] ?? '');
                $price = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;

                $product_id = create_woocommerce_product_if_not_exists($sku);
                $group_id = create_b2bking_group_if_not_exists($group_name);

                if ($product_id && $group_id && is_numeric($price)) {
                    // Create B2BKing dynamic rule for group price
                    $post_id = wp_insert_post([
                        'post_type' => 'b2bking_rule',
                        'post_status' => 'publish',
                        'post_title' => "Group Price - $sku for $group_name"
                    ]);

                    if ($post_id && !is_wp_error($post_id)) {
                        update_post_meta($post_id, 'b2bking_rule_what', 'fixed_price');
                        update_post_meta($post_id, 'b2bking_rule_howmuch', $price);
                        update_post_meta($post_id, 'b2bking_rule_applies', 'product_' . $product_id);
                        update_post_meta($post_id, 'b2bking_rule_who', 'group_' . $group_id);
                        update_post_meta($post_id, 'b2bking_rule_applies_multiple_options', 'product_' . $product_id);
                        update_post_meta($post_id, 'b2bking_rule_conditions', 'none');
                        update_post_meta($post_id, 'b2bking_rule_priority', '1');

                        $results[] = "[$index] SUCCESS: Group price rule created for product $sku in group $group_name = $price (Rule ID: $post_id)";
                    } else {
                        $results[] = "[$index] ERROR: Failed to create group price rule for $sku";
                    }
                } else {
                    $results[] = "[$index] ERROR: Invalid product, group, or price data ($sku / $group_name / $price)";
                }
            }

            elseif ($tipo === 'Discount (Percentage)') {
                $sku = sanitize_text($item['ApliesTo'] ?? '');
                $for_who = sanitize_text($item['ForWho'] ?? '');
                $discount = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;
                $priority = isset($item['Priority']) ? intval($item['Priority']) : 1;

                // Create product if it doesn't exist
                $product_id = create_woocommerce_product_if_not_exists($sku);
                if (!$product_id) {
                    $results[] = "[$index] ERROR: Could not create/find product with SKU: $sku";
                    continue;
                }

                // Create user if it doesn't exist
                $user_id = create_user_if_not_exists($for_who);
                if (!$user_id) {
                    $results[] = "[$index] ERROR: Could not create/find user: $for_who";
                    continue;
                }

                // Create B2BKing discount rule
                $post_id = wp_insert_post([
                    'post_type' => 'b2bking_rule',
                    'post_status' => 'publish',
                    'post_title' => "Discount {$discount}% for {$for_who} on {$sku}"
                ]);

                if ($post_id && !is_wp_error($post_id)) {
                    update_post_meta($post_id, 'b2bking_rule_what', 'discount_percentage');
                    update_post_meta($post_id, 'b2bking_rule_howmuch', $discount);
                    update_post_meta($post_id, 'b2bking_rule_applies', 'product_' . $product_id);
                    update_post_meta($post_id, 'b2bking_rule_who', 'user_' . $user_id);
                    update_post_meta($post_id, 'b2bking_rule_applies_multiple_options', 'product_' . $product_id);
                    update_post_meta($post_id, 'b2bking_rule_priority', $priority);
                    update_post_meta($post_id, 'b2bking_rule_conditions', 'none');

                    $results[] = "[$index] SUCCESS: Discount rule created for user '{$for_who}' on product {$sku} ({$discount}%, Priority: {$priority}, Rule ID: {$post_id})";
                } else {
                    $results[] = "[$index] ERROR: Failed to create discount rule for {$sku}";
                }
            }

            elseif ($tipo === 'Fixed Price') {
                $sku = sanitize_text($item['ApliesTo'] ?? '');
                $user_name = sanitize_text($item['ForWho'] ?? '');
                $price = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;

                $product_id = create_woocommerce_product_if_not_exists($sku);
                $user_id = create_user_if_not_exists($user_name);

                if ($product_id && $user_id && is_numeric($price)) {
                    // Create B2BKing fixed price rule
                    $post_id = wp_insert_post([
                        'post_type' => 'b2bking_rule',
                        'post_status' => 'publish',
                        'post_title' => "Fixed Price {$price} for {$user_name} on {$sku}"
                    ]);

                    if ($post_id && !is_wp_error($post_id)) {
                        update_post_meta($post_id, 'b2bking_rule_what', 'fixed_price');
                        update_post_meta($post_id, 'b2bking_rule_howmuch', $price);
                        update_post_meta($post_id, 'b2bking_rule_applies', 'product_' . $product_id);
                        update_post_meta($post_id, 'b2bking_rule_who', 'user_' . $user_id);
                        update_post_meta($post_id, 'b2bking_rule_applies_multiple_options', 'product_' . $product_id);
                        update_post_meta($post_id, 'b2bking_rule_conditions', 'none');
                        update_post_meta($post_id, 'b2bking_rule_priority', '1');

                        $results[] = "[$index] SUCCESS: Fixed price rule created for user '{$user_name}' on product {$sku} = {$price} (Rule ID: {$post_id})";
                    } else {
                        $results[] = "[$index] ERROR: Failed to create fixed price rule for {$sku}";
                    }
                } else {
                    $results[] = "[$index] ERROR: Product, user, or price data invalid ($sku / $user_name / $price)";
                }
            }

            else {
                $results[] = "[$index] WARNING: Unknown rule type: $tipo";
            }

        } catch (Exception $e) {
            $results[] = "[$index] ERROR: Exception - " . $e->getMessage();
            error_log("B2BKing ERP Sync: Exception in entry $index: " . $e->getMessage());
        }
    }

    if (function_exists('b2bking')) {
        b2bking()->clear_caches_transients();
        if (method_exists(b2bking(), 'b2bking_clear_rules_caches')) {
            b2bking()->b2bking_clear_rules_caches();
        }
    }
    
    return $results;
}
