<?php

function get_b2bking_group_id_by_name($name)
{
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'b2bking_group'",
        $name
    ));
}

function sanitize_text($value)
{
    return sanitize_text_field(trim($value));
}

function sanitize_float($value)
{
    $value = str_replace(',', '.', $value);
    return floatval(preg_replace('/[^0-9.\-]/', '', $value));
}

function sanitize_int($value)
{
    return intval(preg_replace('/[^0-9\-]/', '', $value));
}

function is_valid_sku($sku)
{
    return is_string($sku) && preg_match('/^[A-Za-z0-9\-_]+$/', $sku);
}


function safe_group_lookup($name)
{
    $original = $name;
    $group_id = get_b2bking_group_id_by_name($original);

    // If not found, try sanitizing the name
    if (!$group_id) {
        $sanitized = sanitize_text($original);
        if ($sanitized !== $original) {
            $group_id = get_b2bking_group_id_by_name($sanitized);
        }
    }

    return $group_id;
}
