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

if (!function_exists('wc_get_product_title_by_id')) {
    function wc_get_product_title_by_id($product_id)
    {
        $product = wc_get_product($product_id);
        return $product ? $product->get_name() : '';
    }
}

if (!function_exists('process_multiple_skus')) {
    /**
     * Process SKU(s) - accepts string or array of SKUs
     * Returns array with product_ids and titles, or errors
     */
    function process_multiple_skus($sku_input)
    {
        $skus = is_array($sku_input) ? $sku_input : [$sku_input];
        $product_ids = [];
        $product_titles = [];
        $errors = [];
        
        foreach ($skus as $sku) {
            $sku = sanitize_text($sku);
            $product_id = wc_get_product_id_by_sku($sku);
            
            if (!$product_id) {
                $errors[] = "Product with SKU '$sku' does not exist";
            } else {
                $product_ids[] = $product_id;
                $product_title = wc_get_product_title_by_id($product_id);
                if ($product_title) {
                    $product_titles[] = $product_title . ' (Product)';
                }
            }
        }
        
        return [
            'product_ids' => $product_ids,
            'product_titles' => $product_titles,
            'errors' => $errors,
            'count' => count($product_ids)
        ];
    }
}

if (!function_exists('process_multiple_users')) {
    /**
     * Process user(s) - accepts single user data or array of users
     * Returns array with user_ids or errors
     */
    function process_multiple_users($user_input)
    {
        // If it's a single user object/string, convert to array
        $users = (is_array($user_input) && !isset($user_input['no'])) ? $user_input : [$user_input];
        $user_ids = [];
        $errors = [];
        
        foreach ($users as $user_data) {
            $user_id = create_user_if_not_exists($user_data);
            
            if (!$user_id) {
                $user_display = is_array($user_data) ? ($user_data['no'] ?? 'unknown') : $user_data;
                $errors[] = "Could not create/find user: $user_display (may be inactive)";
            } else {
                $user_ids[] = $user_id;
            }
        }
        
        return [
            'user_ids' => $user_ids,
            'errors' => $errors,
            'count' => count($user_ids)
        ];
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
                $sku_input = $item['SKU'] ?? '';
                $group_name = sanitize_text($item['ForWho'] ?? '');
                $price = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;
                $priority = isset($item['Priority']) ? max(1, min(10, intval($item['Priority']))) : 1;

                // Process SKU(s) - can be string or array
                $product_data = process_multiple_skus($sku_input);
                
                if (!empty($product_data['errors'])) {
                    foreach ($product_data['errors'] as $error) {
                        $results[] = "[$index] ERROR: $error";
                    }
                    if ($product_data['count'] === 0) {
                        continue;
                    }
                }

                $group_id = create_b2bking_group_if_not_exists($group_name);

                if ($product_data['count'] > 0 && $group_id && is_numeric($price)) {
                    // Build comma-separated product list for B2BKing
                    $product_ids_string = implode(',', array_map(function($id) { 
                        return 'product_' . $id; 
                    }, $product_data['product_ids']));
                    
                    // Build title selector string
                    $selector_value = implode(', ', $product_data['product_titles']);
                    
                    // Build array of product IDs for multiple selection
                    $multiple_options_array = array_map(function($id) {
                        return 'product_' . $id;
                    }, $product_data['product_ids']);
                    
                    // Create descriptive title
                    $sku_display = is_array($sku_input) ? implode(', ', $sku_input) : $sku_input;
                    $product_count = $product_data['count'] > 1 ? " ({$product_data['count']} products)" : "";
                    
                    // Create B2BKing dynamic rule for group price
                    $post_id = wp_insert_post([
                        'post_type' => 'b2bking_rule',
                        'post_status' => 'publish',
                        'post_title' => "Group Price - $sku_display for $group_name$product_count"
                    ]);

                    if ($post_id && !is_wp_error($post_id)) {
                        update_post_meta($post_id, 'b2bking_rule_what', 'fixed_price');
                        update_post_meta($post_id, 'b2bking_rule_howmuch', $price);
                        update_post_meta($post_id, 'b2bking_rule_applies', 'multiple_options');
                        update_post_meta($post_id, 'b2bking_rule_who', 'group_' . $group_id);
                        
                        // Store each product separately for B2BKing admin interface FIRST
                        delete_post_meta($post_id, 'b2bking_rule_select_multiple_options');
                        foreach ($multiple_options_array as $prod_id) {
                            add_post_meta($post_id, 'b2bking_rule_select_multiple_options', $prod_id, false);
                            error_log("B2BKing ERP Sync: Added select option for rule $post_id: $prod_id");
                        }
                        
                        // Then set the other meta fields
                        update_post_meta($post_id, 'b2bking_rule_applies_multiple_options', $product_ids_string);
                        update_post_meta($post_id, 'b2bking_rule_select_multiple_product_categories_selector_value', $selector_value);
                        update_post_meta($post_id, 'b2bking_rule_conditions', 'none');
                        // Set priority in multiple possible fields
                        update_post_meta($post_id, 'b2bking_standard_rule_priority', $priority);
                        update_post_meta($post_id, 'b2bking_rule_priority', $priority);
                        update_post_meta($post_id, 'b2bking_priority', $priority);
                        update_post_meta($post_id, 'b2bking_rule_priority_text', $priority);

                        error_log("B2BKing ERP Sync: Setting priority $priority for group rule $post_id using multiple meta keys");

                        $results[] = "[$index] SUCCESS: Group price rule created for $sku_display in group $group_name = $price (Priority: $priority, Rule ID: $post_id, Products: {$product_data['count']})";
                    } else {
                        $results[] = "[$index] ERROR: Failed to create group price rule for $sku_display";
                    }
                } else {
                    $results[] = "[$index] ERROR: Invalid product, group, or price data";
                }
            } elseif ($tipo_normalized === 'Groupdiscount') {
                $sku_input = $item['SKU'] ?? '';
                $group_name = sanitize_text($item['ForWho'] ?? '');
                $discount = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;
                $priority = isset($item['Priority']) ? max(1, min(10, intval($item['Priority']))) : 1;

                // Process SKU(s) - can be string or array
                $product_data = process_multiple_skus($sku_input);
                
                if (!empty($product_data['errors'])) {
                    foreach ($product_data['errors'] as $error) {
                        $results[] = "[$index] ERROR: $error";
                    }
                    if ($product_data['count'] === 0) {
                        continue;
                    }
                }

                $group_id = create_b2bking_group_if_not_exists($group_name);

                if ($product_data['count'] > 0 && $group_id && is_numeric($discount)) {
                    // Build comma-separated product list for B2BKing
                    $product_ids_string = implode(',', array_map(function($id) { 
                        return 'product_' . $id; 
                    }, $product_data['product_ids']));
                    
                    // Build title selector string
                    $selector_value = implode(', ', $product_data['product_titles']);
                    
                    // Build array of product IDs for multiple selection
                    $multiple_options_array = array_map(function($id) {
                        return 'product_' . $id;
                    }, $product_data['product_ids']);
                    
                    // Create descriptive title
                    $sku_display = is_array($sku_input) ? implode(', ', $sku_input) : $sku_input;
                    $product_count = $product_data['count'] > 1 ? " ({$product_data['count']} products)" : "";
                    
                    // Create B2BKing dynamic rule for group discount
                    $post_id = wp_insert_post([
                        'post_type' => 'b2bking_rule',
                        'post_status' => 'publish',
                        'post_title' => "Group Discount {$discount}% - $sku_display for $group_name$product_count"
                    ]);

                    if ($post_id && !is_wp_error($post_id)) {
                        update_post_meta($post_id, 'b2bking_rule_what', 'discount_percentage');
                        update_post_meta($post_id, 'b2bking_rule_howmuch', $discount);
                        update_post_meta($post_id, 'b2bking_rule_applies', 'multiple_options');
                        update_post_meta($post_id, 'b2bking_rule_who', 'group_' . $group_id);
                        
                        // Store each product separately for B2BKing admin interface FIRST
                        delete_post_meta($post_id, 'b2bking_rule_select_multiple_options');
                        foreach ($multiple_options_array as $prod_id) {
                            add_post_meta($post_id, 'b2bking_rule_select_multiple_options', $prod_id, false);
                            error_log("B2BKing ERP Sync: Added select option for rule $post_id: $prod_id");
                        }
                        
                        // Then set the other meta fields
                        update_post_meta($post_id, 'b2bking_rule_applies_multiple_options', $product_ids_string);
                        update_post_meta($post_id, 'b2bking_rule_select_multiple_product_categories_selector_value', $selector_value);
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
                        update_post_meta($post_id, 'b2bking_rule_discountname', '');

                        error_log("B2BKing ERP Sync: Setting priority $priority for group discount rule $post_id using multiple meta keys");

                        $results[] = "[$index] SUCCESS: Group discount rule created for $sku_display in group $group_name = {$discount}% (Priority: $priority, Rule ID: $post_id, Products: {$product_data['count']})";
                    } else {
                        $results[] = "[$index] ERROR: Failed to create group discount rule for $sku_display";
                    }
                } else {
                    $results[] = "[$index] ERROR: Invalid product, group, or discount data";
                }
            } elseif ($tipo_normalized === 'Discount (percentage)') {
                $sku_input = $item['ApliesTo'] ?? '';
                $for_who_data = $item['ForWho'] ?? '';
                $discount = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;
                $priority = isset($item['Priority']) ? max(1, min(10, intval($item['Priority']))) : 1;

                // Process SKU(s) - can be string or array
                $product_data = process_multiple_skus($sku_input);
                
                if (!empty($product_data['errors'])) {
                    foreach ($product_data['errors'] as $error) {
                        $results[] = "[$index] ERROR: $error";
                    }
                    if ($product_data['count'] === 0) {
                        continue;
                    }
                }

                // Process user(s) - can be single user or array of users
                $user_data = process_multiple_users($for_who_data);
                
                if (!empty($user_data['errors'])) {
                    foreach ($user_data['errors'] as $error) {
                        $results[] = "[$index] ERROR: $error";
                    }
                    if ($user_data['count'] === 0) {
                        continue;
                    }
                }

                // Create a separate rule for each user
                foreach ($user_data['user_ids'] as $user_id) {
                    if ($product_data['count'] > 0) {
                        // Build comma-separated product list for B2BKing
                        $product_ids_string = implode(',', array_map(function($id) { 
                            return 'product_' . $id; 
                        }, $product_data['product_ids']));
                        
                        // Build title selector string
                        $selector_value = implode(', ', $product_data['product_titles']);
                        
                        // Build array of product IDs for multiple selection
                        $multiple_options_array = array_map(function($id) {
                            return 'product_' . $id;
                        }, $product_data['product_ids']);
                        
                        // Create descriptive title
                        $sku_display = is_array($sku_input) ? implode(', ', $sku_input) : $sku_input;
                        $product_count = $product_data['count'] > 1 ? " ({$product_data['count']} products)" : "";
                        
                        // Get user display name
                        $user = get_userdata($user_id);
                        $user_display = $user ? $user->user_login : "User ID $user_id";
                        
                        // Create B2BKing discount rule
                        $post_id = wp_insert_post([
                            'post_type' => 'b2bking_rule',
                            'post_status' => 'publish',
                            'post_title' => "Discount {$discount}% for {$user_display} on {$sku_display}$product_count"
                        ]);

                        if ($post_id && !is_wp_error($post_id)) {
                            update_post_meta($post_id, 'b2bking_rule_what', 'discount_percentage');
                            update_post_meta($post_id, 'b2bking_rule_howmuch', $discount);
                            update_post_meta($post_id, 'b2bking_rule_applies', 'multiple_options');
                            update_post_meta($post_id, 'b2bking_rule_who', 'user_' . $user_id);
                            
                            // Store each product separately for B2BKing admin interface FIRST
                            delete_post_meta($post_id, 'b2bking_rule_select_multiple_options');
                            foreach ($multiple_options_array as $prod_id) {
                                add_post_meta($post_id, 'b2bking_rule_select_multiple_options', $prod_id, false);
                                error_log("B2BKing ERP Sync: Added select option for rule $post_id: $prod_id");
                            }
                            
                            // Then set the other meta fields
                            update_post_meta($post_id, 'b2bking_rule_applies_multiple_options', $product_ids_string);
                            update_post_meta($post_id, 'b2bking_rule_select_multiple_product_categories_selector_value', $selector_value);
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
                            update_post_meta($post_id, 'b2bking_rule_discountname', '');

                            error_log("B2BKing ERP Sync: Setting priority $priority for discount rule $post_id using multiple meta keys");

                            $results[] = "[$index] SUCCESS: Discount rule created for user '{$user_display}' on $sku_display ({$discount}%, Priority: {$priority}, Rule ID: {$post_id}, Products: {$product_data['count']})";
                        } else {
                            $results[] = "[$index] ERROR: Failed to create discount rule for user '{$user_display}'";
                        }
                    }
                }
                
                if ($product_data['count'] === 0) {
                    $results[] = "[$index] ERROR: No valid products found";
                }
            } elseif ($tipo_normalized === 'Fixed Price') {
                $sku_input = $item['ApliesTo'] ?? '';
                $for_who_data = $item['ForWho'] ?? '';
                $price = isset($item['HowMuch']) ? floatval($item['HowMuch']) : null;
                $priority = isset($item['Priority']) ? max(1, min(10, intval($item['Priority']))) : 1;

                // Process SKU(s) - can be string or array
                $product_data = process_multiple_skus($sku_input);
                
                if (!empty($product_data['errors'])) {
                    foreach ($product_data['errors'] as $error) {
                        $results[] = "[$index] ERROR: $error";
                    }
                    if ($product_data['count'] === 0) {
                        continue;
                    }
                }

                // Process user(s) - can be single user or array of users
                $user_data = process_multiple_users($for_who_data);
                
                if (!empty($user_data['errors'])) {
                    foreach ($user_data['errors'] as $error) {
                        $results[] = "[$index] ERROR: $error";
                    }
                    if ($user_data['count'] === 0) {
                        continue;
                    }
                }

                // Create a separate rule for each user
                foreach ($user_data['user_ids'] as $user_id) {
                    if ($product_data['count'] > 0 && is_numeric($price)) {
                        // Build comma-separated product list for B2BKing
                        $product_ids_string = implode(',', array_map(function($id) { 
                            return 'product_' . $id; 
                        }, $product_data['product_ids']));
                        
                        // Build title selector string
                        $selector_value = implode(', ', $product_data['product_titles']);
                        
                        // Build array of product IDs for multiple selection
                        $multiple_options_array = array_map(function($id) {
                            return 'product_' . $id;
                        }, $product_data['product_ids']);
                        
                        // Create descriptive title
                        $sku_display = is_array($sku_input) ? implode(', ', $sku_input) : $sku_input;
                        $product_count = $product_data['count'] > 1 ? " ({$product_data['count']} products)" : "";
                        
                        // Get user display name
                        $user = get_userdata($user_id);
                        $user_display = $user ? $user->user_login : "User ID $user_id";
                        
                        // Create B2BKing fixed price rule
                        $post_id = wp_insert_post([
                            'post_type' => 'b2bking_rule',
                            'post_status' => 'publish',
                            'post_title' => "Fixed Price {$price} for {$user_display} on {$sku_display}$product_count"
                        ]);

                        if ($post_id && !is_wp_error($post_id)) {
                            update_post_meta($post_id, 'b2bking_rule_what', 'fixed_price');
                            update_post_meta($post_id, 'b2bking_rule_howmuch', $price);
                            update_post_meta($post_id, 'b2bking_rule_applies', 'multiple_options');
                            update_post_meta($post_id, 'b2bking_rule_who', 'user_' . $user_id);
                            
                            // Store each product separately for B2BKing admin interface FIRST
                            delete_post_meta($post_id, 'b2bking_rule_select_multiple_options');
                            foreach ($multiple_options_array as $prod_id) {
                                add_post_meta($post_id, 'b2bking_rule_select_multiple_options', $prod_id, false);
                                error_log("B2BKing ERP Sync: Added select option for rule $post_id: $prod_id");
                            }
                            
                            // Then set the other meta fields
                            update_post_meta($post_id, 'b2bking_rule_applies_multiple_options', $product_ids_string);
                            update_post_meta($post_id, 'b2bking_rule_select_multiple_product_categories_selector_value', $selector_value);
                            update_post_meta($post_id, 'b2bking_rule_conditions', 'none');
                            // Set priority in multiple possible fields
                            update_post_meta($post_id, 'b2bking_standard_rule_priority', $priority);
                            update_post_meta($post_id, 'b2bking_rule_priority', $priority);
                            update_post_meta($post_id, 'b2bking_priority', $priority);
                            update_post_meta($post_id, 'b2bking_rule_priority_text', $priority);

                            error_log("B2BKing ERP Sync: Setting priority $priority for fixed price rule $post_id using multiple meta keys");

                            $results[] = "[$index] SUCCESS: Fixed price rule created for user '{$user_display}' on $sku_display = {$price} (Priority: {$priority}, Rule ID: {$post_id}, Products: {$product_data['count']})";
                        } else {
                            $results[] = "[$index] ERROR: Failed to create fixed price rule for user '{$user_display}' on $sku_display";
                        }
                    }
                }
                
                if ($product_data['count'] === 0 || !is_numeric($price)) {
                    $sku_display = is_array($sku_input) ? implode(', ', $sku_input) : $sku_input;
                    $results[] = "[$index] ERROR: Invalid product or price data ($sku_display / $price)";
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
