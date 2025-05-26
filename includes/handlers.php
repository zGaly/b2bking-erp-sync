<?php

function import_b2bking_entries($entries) {
    $results = [];

    foreach ($entries as $index => $item) {
        $tipo = $item['RuleType'] ?? '';

        try {
            if (in_array($tipo, ['GroupPrice', 'SkuGeneralTab'])) {
                $sku = $item['SKU'] ?? '';
                $group_name = $item['ForWho'] ?? '';
                $price = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;

                $product_id = wc_get_product_id_by_sku($sku);
                $group_id = safe_group_lookup($group_name);

                if ($product_id && $group_id && is_numeric($price)) {
                    update_post_meta($product_id, 'b2bking_pricegroup_' . $group_id, $price);
                    $results[] = "[$index] Group price updated: Product $sku for group $group_name = $price";
                } else {
                    $results[] = "[$index] ERROR: Invalid product, group, or price data ($sku / $group_name / $price)";
                }
            }

            elseif ($tipo === 'Discount (Percentage)') {
                $sku = $item['ApliesTo'];
                $user_name = $item['ForWho'];
                $discount = floatval($item['HowMuch']);
                $priority = isset($item['Priority']) ? intval($item['Priority']) : '';

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
                    update_post_meta($post_id, 'b2bking_standard_rule_priority', intval($priority));
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

    if (function_exists('b2bking')) {
        b2bking()->clear_caches_transients();
        b2bking()->b2bking_clear_rules_caches();
    }
    return $results;
}
