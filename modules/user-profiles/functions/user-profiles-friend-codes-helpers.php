<?php
// modules/user-profiles/functions/user-profiles-friend-codes-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Purge Nginx Helper cache for friend codes pages
 * This ensures that new/updated codes appear immediately on the front-end
 * Uses the global poke_hub_purge_module_cache() function
 * 
 * @deprecated Use poke_hub_purge_module_cache() directly instead
 */
function poke_hub_purge_friend_codes_cache() {
    // Use global helper function
    if (function_exists('poke_hub_purge_module_cache')) {
        return poke_hub_purge_module_cache(
            ['poke_hub_friend_codes', 'poke_hub_vivillon'],
            null, // No WordPress cache group for friend codes
            null  // No WordPress cache key for friend codes
        );
    }
    
    // Fallback if global function not available
    return false;
}

/**
 * Get client IP address for rate limiting
 *
 * @return string IP address
 */
function poke_hub_get_client_ip() {
    $ip_keys = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    // Fallback to REMOTE_ADDR (might be local IP)
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/**
 * Check if anonymous user can add or update a friend code (1 time every 2 days limit)
 * Uses cookie-based tracking since we don't have IP column
 * This limitation applies to BOTH additions and updates to encourage registration
 *
 * @param string $ip_address IP address (not used for cookie tracking, but kept for future use)
 * @return bool True if allowed, false if rate limited
 */
function poke_hub_can_anonymous_add_friend_code($ip_address = null) {
    // Check cookie first (more reliable than IP)
    $cookie_name = 'poke_hub_friend_code_last_add';
    $last_add_timestamp = isset($_COOKIE[$cookie_name]) ? (int) $_COOKIE[$cookie_name] : 0;
    
    // If last add/update was less than 48 hours (2 days) ago, deny
    $time_since_last = time() - $last_add_timestamp;
    if ($time_since_last < (48 * 60 * 60)) {
        return false;
    }
    
    // Also check database as backup (in case cookie was cleared)
    // This is less reliable but provides a safety net
    global $wpdb;
    $table_name = pokehub_get_table('user_profiles');
    
    if (!empty($table_name)) {
        $two_days_ago = date('Y-m-d H:i:s', strtotime('-48 hours'));
        
        // Check if this specific friend code was added/updated recently
        // This prevents duplicate submissions
        $friend_code_to_check = isset($_POST['friend_code']) ? trim(sanitize_text_field($_POST['friend_code'])) : '';
        if (!empty($friend_code_to_check)) {
            $cleaned = preg_replace('/[^0-9]/', '', $friend_code_to_check);
            if (strlen($cleaned) === 12) {
                // Check both created_at and updated_at to catch updates
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} 
                    WHERE user_id IS NULL 
                    AND friend_code = %s 
                    AND (created_at >= %s OR updated_at >= %s)",
                    $cleaned,
                    $two_days_ago,
                    $two_days_ago
                ));
                
                if ((int) $existing > 0) {
                    return false;
                }
            }
        }
    }
    
    return true;
}

/**
 * Set cookie to track anonymous friend code addition/update
 */
function poke_hub_set_friend_code_add_cookie() {
    $cookie_name = 'poke_hub_friend_code_last_add';
    $cookie_value = time();
    $cookie_expire = time() + (48 * 60 * 60); // 48 hours (2 days)
    
    setcookie($cookie_name, $cookie_value, $cookie_expire, '/', '', is_ssl(), true);
    $_COOKIE[$cookie_name] = $cookie_value; // Set for current request
}

/**
 * Get public friend codes with filters
 *
 * @param array $args {
 *     @type string $country Country filter
 *     @type string $scatterbug_pattern Scatterbug pattern filter
 *     @type int $per_page Items per page
 *     @type int $paged Current page
 *     @type string $orderby Order by field
 *     @type string $order Order direction (ASC/DESC)
 * }
 * @return array {
 *     @type array $items Array of friend codes
 *     @type int $total Total count
 *     @type int $total_pages Total pages
 * }
 */
function poke_hub_get_public_friend_codes($args = []) {
    global $wpdb;
    
    $args = wp_parse_args($args, [
        'country' => '',
        'scatterbug_pattern' => '',
        'team' => '',
        'reason' => '',
        'per_page' => 20,
        'paged' => 1,
        'orderby' => 'created_at',
        'order' => 'DESC',
    ]);
    
    $table_name = pokehub_get_table('user_profiles');
    
    if (empty($table_name)) {
        return [
            'items' => [],
            'total' => 0,
            'total_pages' => 0,
        ];
    }
    
    // Debug: log table name to verify it uses global prefix
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKE-HUB] friend-codes: Using table: ' . $table_name);
        $global_prefix = function_exists('poke_hub_global_get_table_prefix') ? poke_hub_global_get_table_prefix() : 'not set';
        error_log('[POKE-HUB] friend-codes: Global prefix: ' . $global_prefix);
        
        // Debug: Check if table exists and count total rows
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        if ($table_exists) {
            $total_codes = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE friend_code != '' AND LENGTH(TRIM(friend_code)) >= 12");
            error_log('[POKE-HUB] friend-codes: Table exists, total codes: ' . $total_codes);
            
            // Check codes with different friend_code_public values
            $codes_public_1 = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE friend_code != '' AND friend_code_public = 1");
            $codes_public_null = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE friend_code != '' AND friend_code_public IS NULL");
            $codes_public_0 = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE friend_code != '' AND friend_code_public = 0");
            $codes_will_show = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE friend_code != '' AND (friend_code_public = 1 OR friend_code_public IS NULL)");
            error_log('[POKE-HUB] friend-codes: Codes with public=1: ' . $codes_public_1 . ', NULL: ' . $codes_public_null . ', private(0): ' . $codes_public_0 . ', will show: ' . $codes_will_show);
        } else {
            error_log('[POKE-HUB] friend-codes: WARNING - Table does not exist: ' . $table_name);
        }
    }
    
    // Build WHERE clause (use 'up.' prefix for table aliases)
    // Include all codes with non-empty friend_code
    // For public display, include codes where friend_code_public = 1 or NULL (legacy codes)
    // Exclude codes where friend_code_public = 0 (explicitly set to private)
    $where = [
        "up.friend_code IS NOT NULL",
        "up.friend_code != ''", 
        "LENGTH(TRIM(up.friend_code)) >= 12", // At least 12 digits (may have spaces in stored format)
        // Include only codes that are public (1) OR have NULL for friend_code_public (legacy codes)
        // Exclude codes where friend_code_public = 0 (explicitly set to private)
        "(up.friend_code_public = 1 OR up.friend_code_public IS NULL)"
    ];
    $where_values = [];
    
    // Country filter (from Ultimate Member usermeta)
    $country_filter = !empty($args['country']);
    
    // Scatterbug pattern filter
    if (!empty($args['scatterbug_pattern'])) {
        $where[] = "up.scatterbug_pattern = %s";
        $where_values[] = sanitize_text_field($args['scatterbug_pattern']);
    }
    
    // Team filter
    if (!empty($args['team'])) {
        $where[] = "up.team = %s";
        $where_values[] = sanitize_text_field($args['team']);
    }
    
    // Reason filter (reasons are stored as JSON array)
    if (!empty($args['reason'])) {
        $reason_value = sanitize_text_field($args['reason']);
        // Search in JSON array using JSON_CONTAINS (MySQL 5.7+) or LIKE fallback
        $where[] = "(JSON_CONTAINS(up.reasons, %s, '$') OR up.reasons LIKE %s)";
        $reason_json = json_encode($reason_value);
        $reason_like = '%"' . $wpdb->esc_like($reason_value) . '"%';
        $where_values[] = $reason_json;
        $where_values[] = $reason_like;
    }
    
    $base_where_sql = implode(' AND ', $where);
    
    // Get total count
    $count_query = "SELECT COUNT(DISTINCT up.id) FROM {$table_name} AS up";
    
    // If country filter, we need to join with usermeta and check both table column and usermeta
    if ($country_filter) {
        $count_query .= " LEFT JOIN {$wpdb->usermeta} AS um ON up.user_id = um.user_id AND um.meta_key = 'country'";
        $count_where = array_merge($where, [
            "(up.country = %s OR um.meta_value = %s)"
        ]);
        $count_where_sql = implode(' AND ', $count_where);
        $country_filter_value = sanitize_text_field($args['country']);
        $where_values_count = array_merge($where_values, [$country_filter_value, $country_filter_value]);
    } else {
        $count_where_sql = $base_where_sql;
        $where_values_count = $where_values;
    }
    
    
    $count_query .= " WHERE {$count_where_sql}";
    
    if (!empty($where_values_count)) {
        $total = $wpdb->get_var($wpdb->prepare($count_query, $where_values_count));
    } else {
        $total = $wpdb->get_var($count_query);
    }
    
    $total = (int) $total;
    $total_pages = (int) ceil($total / $args['per_page']);
    
    // Validate orderby
    $allowed_orderby = ['created_at', 'updated_at', 'friend_code', 'pokemon_go_username'];
    $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
    $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get items
    $offset = ($args['paged'] - 1) * $args['per_page'];
    // Use COALESCE to get country from table (for anonymous) or usermeta (for logged-in users)
    $query = "SELECT up.*, 
                     COALESCE(
                         NULLIF(up.country, ''), 
                         um_country.meta_value, 
                         ''
                     ) AS country 
              FROM {$table_name} AS up
              LEFT JOIN {$wpdb->usermeta} AS um_country ON up.user_id = um_country.user_id AND um_country.meta_key = 'country'";
    
    // Build WHERE clause for items query
    // For country filter, check both table column (anonymous) and usermeta (logged-in)
    if ($country_filter) {
        $items_where = array_merge($where, [
            "(up.country = %s OR um_country.meta_value = %s)"
        ]);
        $items_where_sql = implode(' AND ', $items_where);
        $country_filter_value = sanitize_text_field($args['country']);
        $query_values = array_merge($where_values, [$country_filter_value, $country_filter_value]);
    } else {
        $items_where_sql = $base_where_sql;
        $query_values = $where_values;
    }
    
    $query .= " WHERE {$items_where_sql}";
    $query .= " ORDER BY up.{$orderby} {$order} LIMIT %d OFFSET %d";
    $query_values[] = $args['per_page'];
    $query_values[] = $offset;
    
    // Debug: log the query
    if (defined('WP_DEBUG') && WP_DEBUG) {
        if (!empty($query_values)) {
            error_log('[POKE-HUB] friend-codes query: ' . $wpdb->prepare($query, $query_values));
        } else {
            error_log('[POKE-HUB] friend-codes query: ' . $query);
        }
    }
    
    if (!empty($query_values)) {
        $items = $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);
    } else {
        $items = $wpdb->get_results($query, ARRAY_A);
    }
    
    // Debug: log results count
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKE-HUB] friend-codes: Found ' . count($items) . ' items');
    }
    
    // Format items
    $formatted_items = [];
    foreach ($items as $item) {
        $formatted_items[] = [
            'id' => (int) $item['id'],
            'user_id' => !empty($item['user_id']) ? (int) $item['user_id'] : null,
            'friend_code' => $item['friend_code'],
            'pokemon_go_username' => $item['pokemon_go_username'],
            'country' => !empty($item['country']) ? $item['country'] : '',
            'scatterbug_pattern' => $item['scatterbug_pattern'],
            'team' => $item['team'],
            'xp' => (int) $item['xp'],
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'],
        ];
    }
    
    return [
        'items' => $formatted_items,
        'total' => $total,
        'total_pages' => $total_pages,
    ];
}

/**
 * Add or update a friend code (public)
 *
 * @param array $data Friend code data
 * @param bool $is_logged_in Whether user is logged in
 * @return array {
 *     @type bool $success Success status
 *     @type string $message Message
 *     @type int|null $profile_id Profile ID if created/updated
 * }
 */
function poke_hub_add_public_friend_code($data, $is_logged_in = false) {
    global $wpdb;
    
    $user_id = $is_logged_in ? get_current_user_id() : null;
    
    // Validate friend code
    // Trim to remove any trailing spaces that might come from formatting
    $friend_code_raw = isset($data['friend_code']) ? trim(sanitize_text_field($data['friend_code'])) : '';
    if (empty($friend_code_raw)) {
        return [
            'success' => false,
            'message' => __('Friend code is required.', 'poke-hub'),
            'profile_id' => null,
        ];
    }
    
    // Clean friend code
    if (function_exists('poke_hub_clean_friend_code')) {
        $friend_code = poke_hub_clean_friend_code($friend_code_raw);
    } else {
        $cleaned = preg_replace('/[^0-9]/', '', $friend_code_raw);
        $friend_code = (strlen($cleaned) === 12) ? $cleaned : '';
    }
    
    if (empty($friend_code)) {
        return [
            'success' => false,
            'message' => __('Friend code must contain exactly 12 digits.', 'poke-hub'),
            'profile_id' => null,
        ];
    }
    
    // Validate country/pattern combination if both are provided (for Vivillon)
    if (!empty($data['country']) && !empty($data['scatterbug_pattern'])) {
        if (function_exists('poke_hub_validate_vivillon_country_pattern')) {
            $is_valid = poke_hub_validate_vivillon_country_pattern($data['country'], $data['scatterbug_pattern']);
            if (!$is_valid) {
                return [
                    'success' => false,
                    'message' => __('The selected country and Vivillon pattern do not match. Please select a valid combination.', 'poke-hub'),
                    'profile_id' => null,
                ];
            }
        }
    }
    
    // Get table name first
    $table_name = pokehub_get_table('user_profiles');
    
    if (empty($table_name)) {
        return [
            'success' => false,
            'message' => __('Technical error. Please try again later.', 'poke-hub'),
            'profile_id' => null,
        ];
    }
    
    // Debug: verify table name uses global prefix
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[POKE-HUB] add-friend-code: Using table: ' . $table_name);
    }
    
    // Check if profile exists or if friend code exists (anywhere)
    $existing_by_user = null;
    $existing_by_code = null;
    $existing_by_code_with_user = null;
    
    if (!$is_logged_in) {
        // For anonymous users, check if this exact friend code already exists (update scenario)
        $existing_by_code = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, created_at, updated_at FROM {$table_name} WHERE friend_code = %s LIMIT 1",
            $friend_code
        ), ARRAY_A);
    }
    
    // Check rate limiting for anonymous users - applies to BOTH additions AND updates
    // This encourages users to register for unlimited updates
    if (!$is_logged_in) {
        $ip_address = poke_hub_get_client_ip();
        
        // Check if this exact friend code was already added/updated in the last 2 days
        $two_days_ago = date('Y-m-d H:i:s', strtotime('-48 hours'));
        $existing_recent = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} 
            WHERE user_id IS NULL 
            AND friend_code = %s 
            AND (created_at >= %s OR updated_at >= %s)
            LIMIT 1",
            $friend_code,
            $two_days_ago,
            $two_days_ago
        ));
        
        if ($existing_recent) {
            return [
                'success' => false,
                'message' => __('You have already added or updated this friend code recently. You can update your friend code once every 2 days. Log in for unlimited updates and more features!', 'poke-hub'),
                'profile_id' => null,
            ];
        }
        
        // Check rate limiting (1 every 2 days per IP) - applies to both additions and updates
        if (!poke_hub_can_anonymous_add_friend_code($ip_address)) {
            return [
                'success' => false,
                'message' => __('You can only add or update your friend code once every 2 days. Log in for unlimited updates and more features!', 'poke-hub'),
                'profile_id' => null,
            ];
        }
    }
    
    // Prepare data
    $profile_data = [
        'friend_code' => $friend_code,
        'friend_code_public' => 1,
        'pokemon_go_username' => isset($data['pokemon_go_username']) ? sanitize_text_field($data['pokemon_go_username']) : '',
        'scatterbug_pattern' => isset($data['scatterbug_pattern']) ? sanitize_text_field($data['scatterbug_pattern']) : '',
        'team' => isset($data['team']) ? sanitize_text_field($data['team']) : '',
    ];
    
    // Determine profile_type based on available identifiers
    // classic: has user_id (WordPress user)
    // discord: has discord_id but no user_id (Discord bot only)
    // anonymous: has neither user_id nor discord_id (front without login)
    if ($user_id) {
        $profile_data['profile_type'] = 'classic';
        $profile_data['user_id'] = $user_id;
    } else {
        // No user_id - check if discord_id exists (from $data)
        $discord_id_from_data = isset($data['discord_id']) && !empty($data['discord_id']) ? sanitize_text_field($data['discord_id']) : null;
        if ($discord_id_from_data) {
            $profile_data['profile_type'] = 'discord';
            $profile_data['discord_id'] = $discord_id_from_data;
        } else {
            // Neither user_id nor discord_id = anonymous
            $profile_data['profile_type'] = 'anonymous';
        }
    }
    
    // Store country: for anonymous users, store in table; for logged-in users, store in usermeta
    if (!$user_id && isset($data['country']) && !empty($data['country'])) {
        // For anonymous users, store country directly in the table
        $profile_data['country'] = sanitize_text_field($data['country']);
    }
    
    if (!$user_id && !isset($profile_data['discord_id'])) {
        // For anonymous users, we track by IP and friend code
        // The rate limiting function checks by IP and date
        $ip_address = poke_hub_get_client_ip();
    }
    
    // Continue checking if profile exists (for logged-in users)
    if ($user_id) {
        // For logged in users, check if they already have a profile
        $existing_by_user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, friend_code FROM {$table_name} WHERE user_id = %d LIMIT 1",
            $user_id
        ), ARRAY_A);
        
        // If user already has a profile with a different friend code
        if ($existing_by_user && !empty($existing_by_user['friend_code']) && $existing_by_user['friend_code'] !== $friend_code) {
            return [
                'success' => false,
                'message' => __('You already have a friend code registered. You can modify it in your profile.', 'poke-hub'),
                'profile_id' => null,
            ];
        }
        
        // Check if this friend code exists with no user_id (anonymous entry) - for linking
        $existing_by_code = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE friend_code = %s AND user_id IS NULL LIMIT 1",
            $friend_code
        ), ARRAY_A);
        
        // Check if this friend code exists with another user_id (duplicate)
        $existing_by_code_with_user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id FROM {$table_name} WHERE friend_code = %s AND user_id IS NOT NULL AND user_id != %d LIMIT 1",
            $friend_code,
            $user_id
        ), ARRAY_A);
        
        // If code exists with another user, prevent duplicate
        if ($existing_by_code_with_user) {
            return [
                'success' => false,
                'message' => __('This friend code is already used by another user.', 'poke-hub'),
                'profile_id' => null,
            ];
        }
        
        // If user wants to link (confirmed via POST parameter)
        if (isset($_POST['link_existing_code']) && $_POST['link_existing_code'] === '1' && $existing_by_code) {
            // Link the existing code to the user's account
            $update_data = [
                'user_id' => $user_id,
            ];
            
            // Merge with profile data
            foreach ($profile_data as $key => $value) {
                if ($key !== 'friend_code' || !empty($value)) {
                    $update_data[$key] = $value;
                }
            }
            
            $format = [];
            foreach ($update_data as $key => $value) {
                if ($key === 'user_id' || $key === 'friend_code_public' || $key === 'xp') {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
            
            $result = $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $existing_by_code['id']],
                $format,
                ['%d']
            );
            
            if ($result !== false) {
                // Update country: for logged-in users, store in usermeta
                if (isset($data['country']) && !empty($data['country'])) {
                    update_user_meta($user_id, 'country', sanitize_text_field($data['country']));
                    // Remove country from table since it's now linked to a user
                    $wpdb->update($table_name, ['country' => null], ['id' => $existing_by_code['id']], ['%s'], ['%d']);
                }
                
                // Purge Nginx Helper cache so the updated code appears immediately
                if (function_exists('poke_hub_purge_module_cache')) {
                    poke_hub_purge_module_cache(['poke_hub_friend_codes', 'poke_hub_vivillon']);
                }
                
                return [
                    'success' => true,
                    'message' => __('Friend code successfully linked to your account.', 'poke-hub'),
                    'profile_id' => $existing_by_code['id'],
                ];
            }
        }
        
        // If code exists without user but user hasn't confirmed linking
        if ($existing_by_code && (!isset($_POST['link_existing_code']) || $_POST['link_existing_code'] !== '1')) {
            return [
                'success' => false,
                'needs_link_confirmation' => true,
                'message' => __('This friend code already exists. Would you like to link it to your account?', 'poke-hub'),
                'profile_id' => $existing_by_code['id'],
            ];
        }
    } else {
        // For anonymous users, we already checked $existing_by_code above
        // If code exists and is linked to a user, prevent duplicate
        if ($existing_by_code && !empty($existing_by_code['user_id'])) {
            return [
                'success' => false,
                'message' => __('This friend code is already used. Log in to link it to your account if it is yours.', 'poke-hub'),
                'profile_id' => null,
            ];
        }
        
        // If this is an update (code exists and is anonymous), set existing_by_user for update logic
        if ($existing_by_code && empty($existing_by_code['user_id'])) {
            $existing_by_user = $existing_by_code;
        }
    }
    
    // For logged-in users, use poke_hub_save_user_profile for consistency with profile tab
    if ($user_id && function_exists('poke_hub_save_user_profile')) {
        // Determine if this is an update or insert (use $existing_by_user checked earlier)
        $was_existing = !empty($existing_by_user);
        
        // Get existing profile data to preserve fields that are not being updated
        $existing_profile_data = [];
        if ($was_existing && function_exists('poke_hub_get_user_profile')) {
            $existing_profile_data = poke_hub_get_user_profile($user_id);
        }
        
        // Prepare profile data in the format expected by poke_hub_save_user_profile
        // IMPORTANT: If a field is explicitly provided (even if empty), use it (allows deletion)
        // Only preserve existing values if fields are NOT provided in the form data
        $profile_for_save = [
            'friend_code' => $friend_code, // Always update friend_code as it's required
            'friend_code_public' => 1, // Always set to public for friend codes page
        ];
        
        // pokemon_go_username: only preserve if NOT provided in form (allows deletion if provided empty)
        $username_provided = array_key_exists('pokemon_go_username', $data);
        if ($username_provided) {
            $profile_for_save['pokemon_go_username'] = sanitize_text_field($data['pokemon_go_username']);
        } elseif ($was_existing && !empty($existing_profile_data['pokemon_go_username'])) {
            // Preserve existing value only if NOT provided in form
            $profile_for_save['pokemon_go_username'] = $existing_profile_data['pokemon_go_username'];
        }
        
        // scatterbug_pattern: only preserve if NOT provided in form (allows deletion if provided empty)
        $pattern_provided = array_key_exists('scatterbug_pattern', $data);
        if ($pattern_provided) {
            $profile_for_save['scatterbug_pattern'] = sanitize_text_field($data['scatterbug_pattern']);
        } elseif ($was_existing && !empty($existing_profile_data['scatterbug_pattern'])) {
            // Preserve existing value only if NOT provided in form
            $profile_for_save['scatterbug_pattern'] = $existing_profile_data['scatterbug_pattern'];
        }
        
        // team: only preserve if NOT provided in form (allows deletion if provided empty)
        $team_provided = array_key_exists('team', $data);
        if ($team_provided) {
            $profile_for_save['team'] = sanitize_text_field($data['team']);
        } elseif ($was_existing && !empty($existing_profile_data['team'])) {
            // Preserve existing value only if NOT provided in form
            $profile_for_save['team'] = $existing_profile_data['team'];
        }
        
        // country: only preserve if NOT provided in form (allows deletion if provided empty)
        $country_provided = array_key_exists('country', $data);
        if ($country_provided) {
            $profile_for_save['country'] = sanitize_text_field($data['country']);
        } elseif ($was_existing && !empty($existing_profile_data['country'])) {
            // Preserve existing value only if NOT provided in form
            $profile_for_save['country'] = $existing_profile_data['country'];
        }
        
        // Preserve XP if it exists and not provided (XP is not in friend codes form, so preserve)
        if ($was_existing && isset($existing_profile_data['xp']) && $existing_profile_data['xp'] > 0) {
            $profile_for_save['xp'] = $existing_profile_data['xp'];
        }
        
        // Use the standard save function (handles country in usermeta automatically)
        $save_result = poke_hub_save_user_profile($user_id, $profile_for_save);
        
        if ($save_result) {
            // Get the profile ID from the updated/created profile
            $existing_profile = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE user_id = %d LIMIT 1",
                $user_id
            ), ARRAY_A);
            
            $profile_id = $existing_profile ? $existing_profile['id'] : null;
            $result = true;
            $existing = $was_existing;
            
            // Purge Nginx Helper cache so the new/updated code appears immediately
            poke_hub_purge_friend_codes_cache();
        } else {
            $result = false;
            $profile_id = null;
            $existing = false;
        }
    } else {
        // For anonymous users, use direct database operations
        $existing = $existing_by_user;
        
        if ($existing) {
            // Get existing profile data to preserve fields that are not being updated
            $existing_row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
                $existing['id']
            ), ARRAY_A);
            
            // Determine profile_type for update based on available identifiers
            $profile_type_for_update = 'anonymous'; // Default for anonymous users
            if ($user_id) {
                $profile_type_for_update = 'classic';
            } elseif (isset($data['discord_id']) && !empty($data['discord_id'])) {
                $profile_type_for_update = 'discord';
            } elseif ($existing_row && !empty($existing_row['user_id'])) {
                $profile_type_for_update = 'classic';
            } elseif ($existing_row && !empty($existing_row['discord_id'])) {
                $profile_type_for_update = 'discord';
            }
            
            // Update existing profile - allow deletion if fields are explicitly provided (even if empty)
            $update_data = [
                'friend_code' => $friend_code, // Always update friend_code as it's required
                'friend_code_public' => 1, // Always set to public for friend codes page
                'profile_type' => $profile_type_for_update, // Always update profile_type to ensure correctness
            ];
            
            // pokemon_go_username: only preserve if NOT provided in form (allows deletion if provided empty)
            $username_provided = array_key_exists('pokemon_go_username', $data);
            if ($username_provided) {
                $update_data['pokemon_go_username'] = sanitize_text_field($data['pokemon_go_username']);
            } elseif ($existing_row && !empty($existing_row['pokemon_go_username'])) {
                // Preserve existing value only if NOT provided in form
                $update_data['pokemon_go_username'] = $existing_row['pokemon_go_username'];
            }
            
            // scatterbug_pattern: only preserve if NOT provided in form (allows deletion if provided empty)
            $pattern_provided = array_key_exists('scatterbug_pattern', $data);
            if ($pattern_provided) {
                $update_data['scatterbug_pattern'] = sanitize_text_field($data['scatterbug_pattern']);
            } elseif ($existing_row && !empty($existing_row['scatterbug_pattern'])) {
                // Preserve existing value only if NOT provided in form
                $update_data['scatterbug_pattern'] = $existing_row['scatterbug_pattern'];
            }
            
            // team: only preserve if NOT provided in form (allows deletion if provided empty)
            $team_provided = array_key_exists('team', $data);
            if ($team_provided) {
                $update_data['team'] = sanitize_text_field($data['team']);
            } elseif ($existing_row && !empty($existing_row['team'])) {
                // Preserve existing value only if NOT provided in form
                $update_data['team'] = $existing_row['team'];
            }
            
            // Preserve XP if it exists (XP is not in friend codes form, so preserve)
            if ($existing_row && isset($existing_row['xp']) && $existing_row['xp'] > 0) {
                $update_data['xp'] = (int) $existing_row['xp'];
            }
            
            // country: only preserve if NOT provided in form (allows deletion if provided empty)
            $country_provided = array_key_exists('country', $data);
            if ($country_provided) {
                $update_data['country'] = sanitize_text_field($data['country']);
            } elseif ($existing_row && !empty($existing_row['country'])) {
                // Preserve existing value only if NOT provided in form
                $update_data['country'] = $existing_row['country'];
            }
            
            if ($user_id) {
                $update_data['user_id'] = $user_id;
            } elseif (isset($data['discord_id']) && !empty($data['discord_id'])) {
                // If updating with discord_id, set it
                $update_data['discord_id'] = sanitize_text_field($data['discord_id']);
            }
            
            $format = [];
            foreach ($update_data as $key => $value) {
                if ($key === 'user_id' || $key === 'friend_code_public' || $key === 'xp') {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
            
            $result = $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $existing['id']],
                $format,
                ['%d']
            );
            
            $profile_id = $existing['id'];
        } else {
            // Insert new
            // Ensure profile_type is set correctly (should already be set above, but double-check)
            if (!isset($profile_data['profile_type'])) {
                if ($user_id) {
                    $profile_data['profile_type'] = 'classic';
                } elseif (isset($profile_data['discord_id']) && !empty($profile_data['discord_id'])) {
                    $profile_data['profile_type'] = 'discord';
                } else {
                    $profile_data['profile_type'] = 'anonymous';
                }
            }
            
            // Prepare format array based on what fields are present
            $format_array = [];
            foreach ($profile_data as $key => $value) {
                if ($key === 'user_id' || $key === 'friend_code_public' || $key === 'xp') {
                    $format_array[] = '%d';
                } else {
                    $format_array[] = '%s';
                }
            }
            
            $result = $wpdb->insert(
                $table_name,
                $profile_data,
                $format_array
            );
            
            if ($result !== false) {
                $profile_id = $wpdb->insert_id;
            } else {
                $profile_id = null;
            }
        }
    }
    
    if ($result !== false && $profile_id) {
        // Set cookie for anonymous users to track rate limiting
        if (!$is_logged_in) {
            poke_hub_set_friend_code_add_cookie();
        }
        
        // Purge Nginx Helper cache so the new/updated code appears immediately
        poke_hub_purge_friend_codes_cache();
        
        return [
            'success' => true,
            'message' => $existing ? __('Friend code updated successfully.', 'poke-hub') : __('Friend code added successfully.', 'poke-hub'),
            'profile_id' => $profile_id,
        ];
    } else {
        return [
            'success' => false,
            'message' => __('Error adding friend code.', 'poke-hub'),
            'profile_id' => null,
        ];
    }
}

/**
 * Generate QR code data URL for friend code
 *
 * @param string $friend_code Friend code (12 digits)
 * @return string QR code data URL or empty string
 */
function poke_hub_generate_friend_code_qr($friend_code) {
    if (empty($friend_code)) {
        return '';
    }
    
    // Format: "pokemongofriend://friendCode?friendCode=123456789012"
    $qr_data = 'pokemongofriend://friendCode?friendCode=' . $friend_code;
    
    // Use a QR code library if available, otherwise return data for external service
    // For now, we'll use an external service or return the data for client-side generation
    // You can use a library like phpqrcode or an API
    
    // Option 1: Use QR Server API (free, no API key needed)
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_data);
    
    return $qr_url;
}

