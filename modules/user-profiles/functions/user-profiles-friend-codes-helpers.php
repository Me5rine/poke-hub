<?php
// modules/user-profiles/functions/user-profiles-friend-codes-helpers.php

if (!defined('ABSPATH')) {
    exit;
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
 * Check if anonymous user can add a friend code (1 time per day limit)
 * Uses cookie-based tracking since we don't have IP column
 *
 * @param string $ip_address IP address (not used for cookie tracking, but kept for future use)
 * @return bool True if allowed, false if rate limited
 */
function poke_hub_can_anonymous_add_friend_code($ip_address = null) {
    // Check cookie first (more reliable than IP)
    $cookie_name = 'poke_hub_friend_code_last_add';
    $last_add_timestamp = isset($_COOKIE[$cookie_name]) ? (int) $_COOKIE[$cookie_name] : 0;
    
    // If last add was less than 24 hours ago, deny
    $time_since_last = time() - $last_add_timestamp;
    if ($time_since_last < (24 * 60 * 60)) {
        return false;
    }
    
    // Also check database as backup (in case cookie was cleared)
    // This is less reliable but provides a safety net
    global $wpdb;
    $table_name = pokehub_get_table('user_profiles');
    
    if (!empty($table_name)) {
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        // Check if this specific friend code was added recently
        // This prevents duplicate submissions
        $friend_code_to_check = isset($_POST['friend_code']) ? sanitize_text_field($_POST['friend_code']) : '';
        if (!empty($friend_code_to_check)) {
            $cleaned = preg_replace('/[^0-9]/', '', $friend_code_to_check);
            if (strlen($cleaned) === 12) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} 
                    WHERE user_id IS NULL 
                    AND friend_code = %s 
                    AND created_at >= %s",
                    $cleaned,
                    $yesterday
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
 * Set cookie to track anonymous friend code addition
 */
function poke_hub_set_friend_code_add_cookie() {
    $cookie_name = 'poke_hub_friend_code_last_add';
    $cookie_value = time();
    $cookie_expire = time() + (24 * 60 * 60); // 24 hours
    
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
            error_log('[POKE-HUB] friend-codes: Codes with public=1: ' . $codes_public_1 . ', NULL: ' . $codes_public_null . ', 0: ' . $codes_public_0);
        } else {
            error_log('[POKE-HUB] friend-codes: WARNING - Table does not exist: ' . $table_name);
        }
    }
    
    // Build WHERE clause (use 'up.' prefix for table aliases)
    // Include all codes with non-empty friend_code
    // For public display, include codes where friend_code_public = 1 or NULL/0 (legacy codes)
    // This ensures existing codes from the main site are included
    $where = [
        "up.friend_code IS NOT NULL",
        "up.friend_code != ''", 
        "LENGTH(TRIM(up.friend_code)) >= 12", // At least 12 digits (may have spaces in stored format)
        // Include all codes that are public OR have NULL/0 for friend_code_public (legacy codes or anonymous)
        "(up.friend_code_public = 1 OR up.friend_code_public IS NULL OR up.friend_code_public = 0)"
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
    
    // Note: team and reason filters are already in $where, so they're automatically included in count_where_sql
    
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
    $friend_code_raw = isset($data['friend_code']) ? sanitize_text_field($data['friend_code']) : '';
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
    
    // Check rate limiting for anonymous users
    if (!$is_logged_in) {
        $ip_address = poke_hub_get_client_ip();
        
        // Check if this exact friend code was already added by this IP today
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $existing_today = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} 
            WHERE user_id IS NULL 
            AND friend_code = %s 
            AND created_at >= %s
            LIMIT 1",
            $friend_code,
            $yesterday
        ));
        
        if ($existing_today) {
            return [
                'success' => false,
                'message' => __('You have already added this friend code recently. You can add one new friend code per day. Log in for more features.', 'poke-hub'),
                'profile_id' => null,
            ];
        }
        
        // Check rate limiting (1 per day per IP)
        if (!poke_hub_can_anonymous_add_friend_code($ip_address)) {
            return [
                'success' => false,
                'message' => __('You can only add one friend code per day. Log in to add more codes.', 'poke-hub'),
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
    
    // Store country: for anonymous users, store in table; for logged-in users, store in usermeta
    if (!$user_id && isset($data['country']) && !empty($data['country'])) {
        // For anonymous users, store country directly in the table
        $profile_data['country'] = sanitize_text_field($data['country']);
    }
    
    if ($user_id) {
        $profile_data['user_id'] = $user_id;
    } else {
        // For anonymous users, we track by IP and friend code
        // The rate limiting function checks by IP and date
        $ip_address = poke_hub_get_client_ip();
    }
    
    // Check if profile exists or if friend code exists (anywhere)
    $existing_by_user = null;
    $existing_by_code = null;
    $existing_by_code_with_user = null;
    
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
        // For anonymous users, check if friend code already exists (anywhere)
        $existing_by_code = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id FROM {$table_name} WHERE friend_code = %s LIMIT 1",
            $friend_code
        ), ARRAY_A);
        
        // If code exists and is linked to a user, prevent duplicate
        if ($existing_by_code && !empty($existing_by_code['user_id'])) {
            return [
                'success' => false,
                'message' => __('This friend code is already used. Log in to link it to your account if it is yours.', 'poke-hub'),
                'profile_id' => null,
            ];
        }
        
        // If code exists but is anonymous (same code added before), prevent duplicate
        // Rate limiting checks same IP, but we also prevent same code from being added twice
        if ($existing_by_code && empty($existing_by_code['user_id'])) {
            // Check if it was added recently (today) - prevent exact duplicates
            $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
            $recent_entry = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name} 
                WHERE id = %d 
                AND created_at >= %s
                LIMIT 1",
                $existing_by_code['id'],
                $yesterday
            ));
            
            if ($recent_entry) {
                return [
                    'success' => false,
                    'message' => __('This friend code has already been added recently. Log in to link it to your account if it is yours.', 'poke-hub'),
                    'profile_id' => null,
                ];
            }
        }
    }
    
    // For logged-in users, use poke_hub_save_user_profile for consistency with profile tab
    if ($user_id && function_exists('poke_hub_save_user_profile')) {
        // Determine if this is an update or insert (use $existing_by_user checked earlier)
        $was_existing = !empty($existing_by_user);
        
        // Prepare profile data in the format expected by poke_hub_save_user_profile
        $profile_for_save = [
            'friend_code' => $friend_code,
            'friend_code_public' => 1,
            'pokemon_go_username' => isset($data['pokemon_go_username']) ? sanitize_text_field($data['pokemon_go_username']) : '',
            'scatterbug_pattern' => isset($data['scatterbug_pattern']) ? sanitize_text_field($data['scatterbug_pattern']) : '',
            'team' => isset($data['team']) ? sanitize_text_field($data['team']) : '',
        ];
        
        // Add country if provided
        if (isset($data['country']) && !empty($data['country'])) {
            $profile_for_save['country'] = sanitize_text_field($data['country']);
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
        } else {
            $result = false;
            $profile_id = null;
            $existing = false;
        }
    } else {
        // For anonymous users, use direct database operations
        $existing = $existing_by_user;
        
        if ($existing) {
            // Update existing profile
            $update_data = $profile_data;
            if ($user_id) {
                $update_data['user_id'] = $user_id;
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

