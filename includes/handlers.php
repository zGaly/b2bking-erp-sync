<?php

// Add the missing functions that we discussed earlier
if (!function_exists('wc_get_product_id_by_sku')) {
    function wc_get_product_id_by_sku($sku)
    {
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

function create_b2bking_group_if_not_exists($group_name)
{
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

function create_user_if_not_exists($user_data)
{
    // Handle both old format (string) and new format (array)
    if (is_string($user_data)) {
        $username = $user_data;
        $user_info = [];
    } else {
        $username = $user_data['no'] ?? $user_data['username'] ?? '';
        $user_info = $user_data;
    }

    // Debug: Log user data received
    error_log("B2BKing ERP Sync: Processing user data for username: $username");
    error_log("B2BKing ERP Sync: User info: " . print_r($user_info, true));

    // Skip inactive users
    if (!empty($user_info['inativo']) && $user_info['inativo'] === true) {
        error_log("B2BKing ERP Sync: Skipping inactive user: $username (inativo = true)");
        return false;
    }

    // Check if user already exists (by username)
    $user = get_user_by('login', $username);

    // If not found by username, try to find by ERP customer ID
    if (!$user && !empty($user_info['no'])) {
        $users = get_users([
            'meta_key' => 'erp_customer_id',
            'meta_value' => $user_info['no'],
            'number' => 1
        ]);
        if (!empty($users)) {
            $user = $users[0];
            error_log("B2BKing ERP Sync: Found existing user by ERP ID: {$user_info['no']} -> WordPress ID: {$user->ID}");
        }
    }

    // Also try to find by email if provided
    if (!$user && !empty($user_info['email'])) {
        $user = get_user_by('email', $user_info['email']);
        if ($user) {
            error_log("B2BKing ERP Sync: Found existing user by email: {$user_info['email']} -> WordPress ID: {$user->ID}");
        }
    }
    if ($user) {
        // Update existing user with new information if provided
        if (!empty($user_info)) {
            update_user_data($user->ID, $user_info);
        }
        return $user->ID;
    }

    // Validate required data
    if (empty($username)) {
        error_log("B2BKing ERP Sync: Cannot create user - username is empty");
        return false;
    }

    // Ensure username is string and sanitized
    $username = sanitize_user($username, true);
    if (empty($username)) {
        error_log("B2BKing ERP Sync: Cannot create user - username is invalid after sanitization");
        return false;
    }

    // Create new user
    $email = !empty($user_info['email']) ? sanitize_email($user_info['email']) : $username . '@generated.local';
    $display_name = !empty($user_info['nome']) ? sanitize_text_field($user_info['nome']) : $username;

    $user_id = wp_create_user($username, wp_generate_password(), $email);

    if (is_wp_error($user_id)) {
        error_log("B2BKing ERP Sync: Failed to create user: $username - " . $user_id->get_error_message());
        return false;
    }

    // Update user display name
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $display_name,
        'first_name' => $display_name
    ]);

    // Update user data (meta + B2BKing group)
    update_user_data($user_id, $user_info);

    error_log("B2BKing ERP Sync: Created new user: $username ($display_name) (ID: $user_id)");
    return $user_id;
}

function update_user_data($user_id, $user_info)
{
    // Store ERP customer data as user meta
    if (!empty($user_info['no'])) {
        update_user_meta($user_id, 'erp_customer_id', sanitize_text_field($user_info['no']));
    }

    if (!empty($user_info['tipodesc'])) {
        update_user_meta($user_id, 'customer_type', sanitize_text_field($user_info['tipodesc']));
    }

    if (!empty($user_info['tabelaPrecos'])) {
        $tabela_precos = sanitize_text_field($user_info['tabelaPrecos']);
        update_user_meta($user_id, 'price_table', $tabela_precos);

        // Map price table to B2BKing group
        $group_name = "Tabela " . $tabela_precos;
        $group_id = create_b2bking_group_if_not_exists($group_name);

        if ($group_id) {
            // Set user's B2BKing group
            update_user_meta($user_id, 'b2bking_customergroup', $group_id);
            error_log("B2BKing ERP Sync: Assigned user $user_id to B2BKing group: $group_name (ID: $group_id)");
        }
    }
}

function import_b2bking_entries($entries)
{
    $results = [];

    foreach ($entries as $index => $item) {
        $tipo = $item['RuleType'] ?? '';
        
        // Normalize rule type to handle case variations
        $tipo_normalized = ucwords(strtolower($tipo));
        
        // Normalize HowMuch value: convert comma to dot for decimal numbers
        // Supports both European (25,6) and American (25.6) formats
        if (isset($item['HowMuch'])) {
            $item['HowMuch'] = str_replace(',', '.', (string)$item['HowMuch']);
        }

        try {
            if (in_array($tipo_normalized, ['Groupprice', 'Skugeneraltab'])) {
                $sku = sanitize_text($item['SKU'] ?? '');
                $group_name = sanitize_text($item['ForWho'] ?? '');
                $price = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;
                $priority = isset($item['Priority']) ? max(1, min(10, intval($item['Priority']))) : 1;

                // Check if product exists (don't create automatically)
                $product_id = wc_get_product_id_by_sku($sku);
                if (!$product_id) {
                    $results[] = "[$index] ERROR: Product with SKU '$sku' does not exist. Please create the product first.";
                    continue;
                }

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
                        // Set priority in multiple possible fields
                        update_post_meta($post_id, 'b2bking_standard_rule_priority', $priority);
                        update_post_meta($post_id, 'b2bking_rule_priority', $priority);
                        update_post_meta($post_id, 'b2bking_priority', $priority);
                        update_post_meta($post_id, 'b2bking_rule_priority_text', $priority);

                        error_log("B2BKing ERP Sync: Setting priority $priority for group rule $post_id using multiple meta keys");

                        $results[] = "[$index] SUCCESS: Group price rule created for product $sku in group $group_name = $price (Priority: $priority, Rule ID: $post_id)";
                    } else {
                        $results[] = "[$index] ERROR: Failed to create group price rule for $sku";
                    }
                } else {
                    $results[] = "[$index] ERROR: Invalid product, group, or price data ($sku / $group_name / $price)";
                }
            } elseif ($tipo_normalized === 'Discount (percentage)') {
                $sku = sanitize_text($item['ApliesTo'] ?? '');
                $for_who_data = $item['ForWho'] ?? '';
                $discount = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;
                $priority = isset($item['Priority']) ? max(1, min(10, intval($item['Priority']))) : 1;

                // Check if product exists (don't create automatically)
                $product_id = wc_get_product_id_by_sku($sku);
                if (!$product_id) {
                    $results[] = "[$index] ERROR: Product with SKU '$sku' does not exist. Please create the product first.";
                    continue;
                }

                // Create user with full data if it doesn't exist
                $user_id = create_user_if_not_exists($for_who_data);
                if (!$user_id) {
                    $for_who_display = is_array($for_who_data) ? ($for_who_data['no'] ?? 'unknown') : $for_who_data;
                    $results[] = "[$index] ERROR: Could not create/find user: $for_who_display (may be inactive)";
                    continue;
                }

                // Create B2BKing discount rule
                $for_who_display = is_array($for_who_data) ? ($for_who_data['no'] ?? 'unknown') : $for_who_data;
                $post_id = wp_insert_post([
                    'post_type' => 'b2bking_rule',
                    'post_status' => 'publish',
                    'post_title' => "Discount {$discount}% for {$for_who_display} on {$sku}"
                ]);

                if ($post_id && !is_wp_error($post_id)) {
                    update_post_meta($post_id, 'b2bking_rule_what', 'discount_percentage');
                    update_post_meta($post_id, 'b2bking_rule_howmuch', $discount);
                    update_post_meta($post_id, 'b2bking_rule_applies', 'product_' . $product_id);
                    update_post_meta($post_id, 'b2bking_rule_who', 'user_' . $user_id);
                    update_post_meta($post_id, 'b2bking_rule_applies_multiple_options', 'product_' . $product_id);
                    update_post_meta($post_id, 'b2bking_rule_conditions', 'none');
                    // Set priority in multiple possible fields
                    update_post_meta($post_id, 'b2bking_standard_rule_priority', $priority);
                    update_post_meta($post_id, 'b2bking_rule_priority', $priority);
                    update_post_meta($post_id, 'b2bking_priority', $priority);
                    update_post_meta($post_id, 'b2bking_rule_priority_text', $priority);
                    // Enable "Show discount everywhere" checkbox
                    update_post_meta($post_id, 'b2bking_rule_discount_show_everywhere', 1);
                    update_post_meta($post_id, 'b2bking_rule_discount_show_everywhere_checkbox', 1);
                    // Enable "Apply Discount as Sale Price" checkbox
                    update_post_meta($post_id, 'b2bking_rule_discountname_checkbox', 1);
                    update_post_meta($post_id, 'b2bking_rule_discountname', 'sale');


                    error_log("B2BKing ERP Sync: Setting priority $priority for discount rule $post_id using multiple meta keys");

                    $results[] = "[$index] SUCCESS: Discount rule created for user '{$for_who_display}' on product {$sku} ({$discount}%, Priority: {$priority}, Rule ID: {$post_id})";
                } else {
                    $results[] = "[$index] ERROR: Failed to create discount rule for {$sku}";
                }
            } elseif ($tipo_normalized === 'Fixed Price') {
                $sku = sanitize_text($item['ApliesTo'] ?? '');
                $user_data = $item['ForWho'] ?? '';
                $price = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;
                $priority = isset($item['Priority']) ? max(1, min(10, intval($item['Priority']))) : 1;

                // Check if product exists (don't create automatically)
                $product_id = wc_get_product_id_by_sku($sku);
                if (!$product_id) {
                    $results[] = "[$index] ERROR: Product with SKU '$sku' does not exist. Please create the product first.";
                    continue;
                }

                $user_id = create_user_if_not_exists($user_data);

                if ($product_id && $user_id && is_numeric($price)) {
                    // Create B2BKing fixed price rule
                    $user_display = is_array($user_data) ? ($user_data['no'] ?? 'unknown') : $user_data;
                    $post_id = wp_insert_post([
                        'post_type' => 'b2bking_rule',
                        'post_status' => 'publish',
                        'post_title' => "Fixed Price {$price} for {$user_display} on {$sku}"
                    ]);

                    if ($post_id && !is_wp_error($post_id)) {
                        update_post_meta($post_id, 'b2bking_rule_what', 'fixed_price');
                        update_post_meta($post_id, 'b2bking_rule_howmuch', $price);
                        update_post_meta($post_id, 'b2bking_rule_applies', 'product_' . $product_id);
                        update_post_meta($post_id, 'b2bking_rule_who', 'user_' . $user_id);
                        update_post_meta($post_id, 'b2bking_rule_applies_multiple_options', 'product_' . $product_id);
                        update_post_meta($post_id, 'b2bking_rule_conditions', 'none');
                        // Set priority in multiple possible fields
                        update_post_meta($post_id, 'b2bking_standard_rule_priority', $priority);
                        update_post_meta($post_id, 'b2bking_rule_priority', $priority);
                        update_post_meta($post_id, 'b2bking_priority', $priority);
                        update_post_meta($post_id, 'b2bking_rule_priority_text', $priority);

                        error_log("B2BKing ERP Sync: Setting priority $priority for fixed price rule $post_id using multiple meta keys");

                        $results[] = "[$index] SUCCESS: Fixed price rule created for user '{$user_display}' on product {$sku} = {$price} (Priority: {$priority}, Rule ID: {$post_id})";
                    } else {
                        $results[] = "[$index] ERROR: Failed to create fixed price rule for {$sku}";
                    }
                } else {
                    $user_display = is_array($user_data) ? ($user_data['no'] ?? 'unknown') : $user_data;
                    $results[] = "[$index] ERROR: Product, user, or price data invalid ($sku / $user_display / $price)";
                }
            } else {
                $results[] = "[$index] WARNING: Unknown rule type: $tipo (normalized: $tipo_normalized)";
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
