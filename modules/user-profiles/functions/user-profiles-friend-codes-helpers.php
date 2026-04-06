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
 * Normalize Pokémon GO trainer name for case-insensitive comparisons (anonymous public forms).
 *
 * @param string $username Raw stored or submitted username
 * @return string Normalized string (empty if only whitespace)
 */
function poke_hub_normalize_public_pogo_username($username) {
    $username = trim(sanitize_text_field((string) $username));
    if ($username === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($username, 'UTF-8');
    }
    return strtolower($username);
}

/**
 * Whether the client IP may modify an anonymous row (legacy rows with no IP are claimable once).
 *
 * @param array  $row Anonymous profile row (expects anonymous_ip key)
 * @param string $ip  Current client IP
 */
function poke_hub_anonymous_ip_allows_profile_update(array $row, $ip) {
    $stored = isset($row['anonymous_ip']) ? trim((string) $row['anonymous_ip']) : '';
    if ($stored === '') {
        return true;
    }
    return $stored === $ip;
}

/**
 * Rules for anonymous update when the row is matched by friend code (same listing).
 * - Same IP as stored (or no IP yet): always allowed.
 * - Different IP but same Pokémon GO username as stored: allowed (e.g. 4G → Wi‑Fi) to update friend code, etc.
 * - Different IP and different username than stored: not allowed (must log in to rename).
 * - Stored username empty, different IP: not allowed until same network or login.
 *
 * @param array  $row               Profile row
 * @param string $ip                Client IP
 * @param string $username_trimmed  Submitted username (trimmed)
 * @return array{ok: bool, message?: string, message_type?: string}
 */
function poke_hub_anonymous_row_by_code_submission_gate(array $row, $ip, $username_trimmed) {
    $stored_norm = poke_hub_normalize_public_pogo_username($row['pokemon_go_username'] ?? '');
    $submit_norm = poke_hub_normalize_public_pogo_username($username_trimmed);
    $ip_ok = poke_hub_anonymous_ip_allows_profile_update($row, $ip);

    if ($stored_norm !== '' && $submit_norm !== $stored_norm) {
        if ($ip_ok) {
            return ['ok' => true];
        }
        return [
            'ok' => false,
            'message' => __('To change your Pokémon GO username, please log in. You can still update your friend code from another network if you keep the same username shown on your listing.', 'poke-hub'),
            'message_type' => 'warning',
        ];
    }

    if (!$ip_ok && $stored_norm !== '' && $submit_norm === $stored_norm) {
        return ['ok' => true];
    }

    if (!$ip_ok) {
        return [
            'ok' => false,
            'message' => __('This friend code was added from a different network. Log in to update it, or use the same connection as when you registered.', 'poke-hub'),
            'message_type' => 'error',
        ];
    }

    return ['ok' => true];
}

/**
 * True if this row was updated less than 48 hours ago (anonymous throttle).
 *
 * @param array $row Profile row with updated_at
 */
function poke_hub_anonymous_friend_code_rate_window_blocked(array $row) {
    if (empty($row['updated_at'])) {
        return false;
    }
    $ts = strtotime($row['updated_at']);
    if ($ts === false) {
        return false;
    }
    return (time() - $ts) < (48 * 60 * 60);
}

/**
 * Check if anonymous user can create a brand-new public profile row (cookie + IP quota).
 * Updates to an already-owned row bypass this via $bypass_cookie_and_ip_quota.
 *
 * @param string $ip_address                    Client IP
 * @param bool   $bypass_cookie_and_ip_quota    When true, allow (verified returning owner path)
 * @return bool True if allowed, false if rate limited
 */
function poke_hub_can_anonymous_add_friend_code($ip_address = null, $bypass_cookie_and_ip_quota = false) {
    if ($bypass_cookie_and_ip_quota) {
        return true;
    }

    $cookie_name = 'poke_hub_friend_code_last_add';
    $last_add_timestamp = isset($_COOKIE[$cookie_name]) ? (int) $_COOKIE[$cookie_name] : 0;

    $time_since_last = time() - $last_add_timestamp;
    if ($time_since_last < (48 * 60 * 60)) {
        return false;
    }

    global $wpdb;
    $table_name = pokehub_get_table('user_profiles');

    if (!empty($table_name) && !empty($ip_address)) {
        $two_days_ago = date('Y-m-d H:i:s', strtotime('-48 hours'));
        $count_ip = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
            WHERE user_id IS NULL 
            AND profile_type = 'anonymous' 
            AND anonymous_ip IS NOT NULL 
            AND anonymous_ip != '' 
            AND anonymous_ip = %s 
            AND created_at >= %s",
            $ip_address,
            $two_days_ago
        ));

        if ((int) $count_ip >= 1) {
            return false;
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
    
    $count_query = "SELECT COUNT(DISTINCT up.id) FROM {$table_name} AS up";
    
    // If country filter, we need to join with usermeta and check country_custom (priority), table column, and usermeta
    if ($country_filter) {
        $count_query .= " LEFT JOIN {$wpdb->usermeta} AS um ON up.user_id = um.user_id AND um.meta_key = 'country'";
        $count_where = array_merge($where, [
            "(up.country_custom = %s OR up.country = %s OR um.meta_value = %s)"
        ]);
        $count_where_sql = implode(' AND ', $count_where);
        $country_filter_value = sanitize_text_field($args['country']);
        $where_values_count = array_merge($where_values, [$country_filter_value, $country_filter_value, $country_filter_value]);
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
    
    $offset = ($args['paged'] - 1) * $args['per_page'];
    // Use COALESCE to get country: prioritize country_custom, then table column (for anonymous), then usermeta (for logged-in users)
    $query = "SELECT up.*, 
                     COALESCE(
                         NULLIF(up.country_custom, ''), 
                         NULLIF(up.country, ''), 
                         um_country.meta_value, 
                         ''
                     ) AS country 
              FROM {$table_name} AS up
              LEFT JOIN {$wpdb->usermeta} AS um_country ON up.user_id = um_country.user_id AND um_country.meta_key = 'country'";
    
    // Build WHERE clause for items query
    // For country filter, check country_custom (priority), table column (anonymous), and usermeta (logged-in)
    if ($country_filter) {
        $items_where = array_merge($where, [
            "(up.country_custom = %s OR up.country = %s OR um_country.meta_value = %s)"
        ]);
        $items_where_sql = implode(' AND ', $items_where);
        $country_filter_value = sanitize_text_field($args['country']);
        $query_values = array_merge($where_values, [$country_filter_value, $country_filter_value, $country_filter_value]);
    } else {
        $items_where_sql = $base_where_sql;
        $query_values = $where_values;
    }
    
    $query .= " WHERE {$items_where_sql}";
    $query .= " ORDER BY up.{$orderby} {$order} LIMIT %d OFFSET %d";
    $query_values[] = $args['per_page'];
    $query_values[] = $offset;
    
    if (!empty($query_values)) {
        $items = $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);
    } else {
        $items = $wpdb->get_results($query, ARRAY_A);
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
    // IMPORTANT: Validate using the ORIGINAL country (custom country if applicable) BEFORE mapping
    // This ensures "Hawaï" + "ocean" is validated correctly, not "États-Unis d'Amérique" + "ocean"
    if (!empty($data['country']) && !empty($data['scatterbug_pattern'])) {
        if (function_exists('poke_hub_validate_vivillon_country_pattern')) {
            // Use the original country from form (which may be a custom country like "Hawaï")
            // The validation function will handle custom countries directly
            $country_for_validation = $data['country'];
            $is_valid = poke_hub_validate_vivillon_country_pattern($country_for_validation, $data['scatterbug_pattern']);
            if (!$is_valid) {
                return [
                    'success' => false,
                    'message' => __('The selected country and Vivillon pattern do not match. Please select a valid combination.', 'poke-hub'),
                    'profile_id' => null,
                ];
            }
        }
    }
    
    $table_name = pokehub_get_table('user_profiles');
    
    if (empty($table_name)) {
        return [
            'success' => false,
            'message' => __('Technical error. Please try again later.', 'poke-hub'),
            'profile_id' => null,
        ];
    }
    
    $existing_by_user = null;
    $existing_by_code = null;
    $existing_by_code_with_user = null;
    $row_by_code = null;
    $ip_address = null;

    $discord_id_from_data = isset($data['discord_id']) && !empty($data['discord_id'])
        ? sanitize_text_field($data['discord_id'])
        : null;

    $is_anonymous_web_submission = !$is_logged_in && empty($discord_id_from_data);

    $pokemon_go_username_input = isset($data['pokemon_go_username'])
        ? trim(sanitize_text_field($data['pokemon_go_username']))
        : '';

    if ($is_anonymous_web_submission) {
        if ($pokemon_go_username_input === '') {
            return [
                'success' => false,
                'message' => __('Pokémon GO username is required.', 'poke-hub'),
                'profile_id' => null,
            ];
        }
        if (strlen($pokemon_go_username_input) > 191) {
            return [
                'success' => false,
                'message' => __('Pokémon GO username is too long.', 'poke-hub'),
                'profile_id' => null,
            ];
        }
    }

    if ($is_anonymous_web_submission) {
        $row_by_code = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, friend_code, pokemon_go_username, anonymous_ip, discord_id, profile_type, updated_at, created_at
                FROM {$table_name} WHERE friend_code = %s LIMIT 1",
                $friend_code
            ),
            ARRAY_A
        );
        $existing_by_code = $row_by_code;
        $ip_address = poke_hub_get_client_ip();

        if ($row_by_code && !empty($row_by_code['user_id'])) {
            return [
                'success' => false,
                'message' => __('This friend code is already used. Log in to link it to your account if it is yours.', 'poke-hub'),
                'profile_id' => null,
            ];
        }

        $p_username_norm = poke_hub_normalize_public_pogo_username($pokemon_go_username_input);

        $row_by_pseudo = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, friend_code, pokemon_go_username, anonymous_ip, discord_id, profile_type, updated_at, created_at
                FROM {$table_name}
                WHERE user_id IS NULL
                AND (discord_id IS NULL OR discord_id = '')
                AND profile_type = %s
                AND TRIM(pokemon_go_username) != ''
                AND LOWER(TRIM(pokemon_go_username)) = LOWER(%s)
                ORDER BY updated_at DESC
                LIMIT 1",
                'anonymous',
                $pokemon_go_username_input
            ),
            ARRAY_A
        );

        if ($row_by_code && $row_by_pseudo && (int) $row_by_code['id'] !== (int) $row_by_pseudo['id']) {
            return [
                'success' => false,
                'message' => __('This friend code and Pokémon GO username belong to different listings. Please check your details or log in to manage your profile.', 'poke-hub'),
                'profile_id' => null,
            ];
        }

        $target_row = null;

        if ($row_by_code && empty($row_by_code['user_id'])) {
            if (!empty($row_by_code['discord_id'])) {
                return [
                    'success' => false,
                    'message' => __('This friend code is already used.', 'poke-hub'),
                    'profile_id' => null,
                ];
            }
            $gate = poke_hub_anonymous_row_by_code_submission_gate($row_by_code, $ip_address, $pokemon_go_username_input);
            if (empty($gate['ok'])) {
                $out = [
                    'success' => false,
                    'message' => isset($gate['message']) ? $gate['message'] : __('This friend code cannot be updated from this connection.', 'poke-hub'),
                    'profile_id' => null,
                ];
                if (!empty($gate['message_type'])) {
                    $out['message_type'] = $gate['message_type'];
                }
                return $out;
            }
            $target_row = $row_by_code;
        } elseif ($row_by_pseudo) {
            // Same Pokémon GO username as an existing anonymous row: allow friend code update even after IP change (e.g. mobile data ↔ Wi‑Fi).
            $other_fc = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, user_id FROM {$table_name} WHERE friend_code = %s AND id != %d LIMIT 1",
                    $friend_code,
                    (int) $row_by_pseudo['id']
                ),
                ARRAY_A
            );
            if ($other_fc) {
                if (!empty($other_fc['user_id'])) {
                    return [
                        'success' => false,
                        'message' => __('This friend code is already used by another member. Log in to link it if it is yours.', 'poke-hub'),
                        'profile_id' => null,
                    ];
                }
                return [
                    'success' => false,
                    'message' => __('This friend code is already listed under another profile.', 'poke-hub'),
                    'profile_id' => null,
                ];
            }
            $target_row = $row_by_pseudo;
        }

        if ($target_row) {
            if (poke_hub_anonymous_friend_code_rate_window_blocked($target_row)) {
                return [
                    'success' => false,
                    'message' => __('You have already updated this listing recently. You can change it once every 2 days. Log in for unlimited updates and more features!', 'poke-hub'),
                    'profile_id' => null,
                ];
            }
            $existing_by_user = [
                'id' => (int) $target_row['id'],
            ];
        } else {
            if (!poke_hub_can_anonymous_add_friend_code($ip_address, false)) {
                return [
                    'success' => false,
                    'message' => __('You can only add a new friend code once every 2 days from this network. Log in for unlimited updates and more features!', 'poke-hub'),
                    'profile_id' => null,
                ];
            }
        }
    }

    $profile_data = [
        'friend_code' => $friend_code,
        'friend_code_public' => 1,
        'pokemon_go_username' => $is_anonymous_web_submission
            ? $pokemon_go_username_input
            : (isset($data['pokemon_go_username']) ? sanitize_text_field($data['pokemon_go_username']) : ''),
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
        if ($discord_id_from_data) {
            $profile_data['profile_type'] = 'discord';
            $profile_data['discord_id'] = $discord_id_from_data;
        } else {
            $profile_data['profile_type'] = 'anonymous';
        }
    }
    
    // Store country: for anonymous users, store in table; for logged-in users, store in usermeta
    // IMPORTANT: Handle custom countries mapping (like "Hawaï" -> "États-Unis d'Amérique")
    if (!$user_id && isset($data['country']) && !empty($data['country'])) {
        // For anonymous users, store country directly in the table
        $country = sanitize_text_field($data['country']);
        
        // Check if country is a custom country and map it to UM country
        $custom_to_um = function_exists('poke_hub_get_custom_country_to_um_mapping') 
            ? poke_hub_get_custom_country_to_um_mapping() 
            : [];
        
        if (isset($custom_to_um[$country])) {
            // Country is a custom country (e.g., "Hawaï")
            $profile_data['country_custom'] = $country; // Store custom country name
            $profile_data['country'] = $custom_to_um[$country]; // Store UM country
        } else {
            // Country is NOT a custom country
            $profile_data['country'] = $country;
        }
    }

    if ($is_anonymous_web_submission && !empty($ip_address)) {
        $profile_data['anonymous_ip'] = $ip_address;
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
                // IMPORTANT: Handle custom countries mapping (like "Hawaï" -> "États-Unis d'Amérique")
                if (isset($data['country']) && !empty($data['country'])) {
                    $country = sanitize_text_field($data['country']);
                    
                    // Check if country is a custom country and map it to UM country
                    $custom_to_um = function_exists('poke_hub_get_custom_country_to_um_mapping') 
                        ? poke_hub_get_custom_country_to_um_mapping() 
                        : [];
                    $country_for_um = isset($custom_to_um[$country]) ? $custom_to_um[$country] : $country;
                    
                    // Update Ultimate Member's country (primary and only source for logged-in users)
                    update_user_meta($user_id, 'country', $country_for_um);
                    
                    // Update country_custom in table if it's a custom country
                    if (isset($custom_to_um[$country])) {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$table_name} SET country_custom = %s WHERE id = %d",
                            $country,
                            $existing_by_code['id']
                        ));
                    } else {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$table_name} SET country_custom = NULL WHERE id = %d",
                            $existing_by_code['id']
                        ));
                    }
                    
                    // Retirer le country en table: profil lié à un user
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
        if ($row_by_code && !empty($row_by_code['user_id'])) {
            return [
                'success' => false,
                'message' => __('This friend code is already used. Log in to link it to your account if it is yours.', 'poke-hub'),
                'profile_id' => null,
            ];
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

            if ($is_anonymous_web_submission && !empty($ip_address)) {
                $update_data['anonymous_ip'] = $ip_address;
            }
            
            // pokemon_go_username: only preserve if NOT provided in form (allows deletion if provided empty)
            $username_provided = array_key_exists('pokemon_go_username', $data);
            if ($is_anonymous_web_submission) {
                $update_data['pokemon_go_username'] = $pokemon_go_username_input;
            } elseif ($username_provided) {
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
            // IMPORTANT: Handle custom countries mapping (like "Hawaï" -> "États-Unis d'Amérique")
            $country_provided = array_key_exists('country', $data);
            $country_custom_to_set = null;
            if ($country_provided) {
                $country = sanitize_text_field($data['country']);
                
                    if (empty($country)) {
                        // If country is empty, clear country_custom
                        $country_custom_to_set = null;
                        $update_data['country'] = '';
                    } else {
                        // Check if country is a custom country and map it to UM country
                        $custom_to_um = function_exists('poke_hub_get_custom_country_to_um_mapping') 
                            ? poke_hub_get_custom_country_to_um_mapping() 
                            : [];
                        
                        if (isset($custom_to_um[$country])) {
                            // Country is a custom country (e.g., "Hawaï")
                            $country_custom_to_set = $country; // Store custom country name
                            $country = $custom_to_um[$country]; // Replace with UM country for storage
                            $update_data['country'] = $country;
                            // Don't add country_custom to $update_data, we'll handle it separately with a direct SQL query
                        } else {
                            // Country is NOT a custom country, clear country_custom (to remove old custom country)
                            $country_custom_to_set = null;
                            $update_data['country'] = $country;
                            // Don't add country_custom to $update_data, we'll handle it separately with a direct SQL query
                        }
                    }
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
            
            // Handle country_custom separately (always use direct SQL query for consistency)
            // $wpdb->update() doesn't update fields to NULL reliably, so we use a direct query
            if ($country_provided) {
                if ($country_custom_to_set === null) {
                    // Clear country_custom
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table_name} SET country_custom = NULL WHERE id = %d",
                        $existing['id']
                    ));
                } else {
                    // Set country_custom
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table_name} SET country_custom = %s WHERE id = %d",
                        $country_custom_to_set,
                        $existing['id']
                    ));
                }
            }
            
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

