<?php
// modules/user-profiles/functions/user-profiles-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get subscription_accounts table name (uses global prefix).
 *
 * @return string Table name or empty string if prefix not available
 */
function poke_hub_get_subscription_accounts_table() {
    if (function_exists('poke_hub_global_get_table_prefix')) {
        $prefix = poke_hub_global_get_table_prefix();
        if (!empty($prefix)) {
            return $prefix . 'subscription_accounts';
        }
    }
    return '';
}

/**
 * Get Discord ID from WordPress user ID using subscription_accounts table.
 *
 * @param int $user_id WordPress user ID
 * @return string|null Discord ID or null if not found
 */
function poke_hub_get_discord_id_from_user_id($user_id) {
    global $wpdb;
    
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }
    
    $table_name = poke_hub_get_subscription_accounts_table();
    if (empty($table_name)) {
        return null;
    }
    
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT external_user_id FROM {$table_name} WHERE user_id = %d AND provider_slug = 'discord' AND is_active = 1 LIMIT 1",
        $user_id
    ));
    
    return $result ? (string) $result : null;
}

/**
 * Get WordPress user ID from Discord ID using subscription_accounts table.
 *
 * @param string $discord_id Discord ID
 * @return int|null WordPress user ID or null if not found
 */
function poke_hub_get_user_id_from_discord_id($discord_id) {
    global $wpdb;
    
    $discord_id = sanitize_text_field((string) $discord_id);
    if (empty($discord_id)) {
        return null;
    }
    
    $table_name = poke_hub_get_subscription_accounts_table();
    if (empty($table_name)) {
        return null;
    }
    
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$table_name} WHERE external_user_id = %s AND provider_slug = 'discord' AND is_active = 1 LIMIT 1",
        $discord_id
    ));
    
    return $result ? (int) $result : null;
}

/**
 * Get Pokémon GO user profile by profile ID.
 *
 * @param int $profile_id Profile ID
 * @return array User profile or empty array if not found
 */
function poke_hub_get_user_profile_by_id($profile_id) {
    global $wpdb;
    
    $profile_id = (int) $profile_id;
    if ($profile_id <= 0) {
        return [];
    }
    
    // Get table name
    $table_name = pokehub_get_table('user_profiles');
    if (empty($table_name)) {
        return [];
    }
    
    // Get profile by ID
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
        $profile_id
    ), ARRAY_A);
    
    if (!$row) {
        return [];
    }
    
    // Extract user_id and discord_id from row
    $wp_user_id = !empty($row['user_id']) ? (int) $row['user_id'] : null;
    $discord_id_value = !empty($row['discord_id']) ? $row['discord_id'] : null;
    
    // Get country from Ultimate Member (single source of truth) for logged-in users
    $country = '';
    if ($wp_user_id !== null && $wp_user_id > 0) {
        $um_country = get_user_meta($wp_user_id, 'country', true);
        if (!empty($um_country)) {
            $country = $um_country;
        }
    } elseif (isset($row['country'])) {
        // For anonymous profiles, country is stored in table
        $country = $row['country'] ?: '';
    }
    
    // Normalize reasons from database (stored as JSON)
    $reasons = [];
    if (!empty($row['reasons'])) {
        $raw = $row['reasons'];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $reasons = $decoded;
            }
        } elseif (is_array($raw)) {
            $reasons = $raw;
        }
    }
    
    // Clean reasons array
    $reasons = array_values(array_filter(array_map('strval', $reasons), function($v) {
        return $v !== '';
    }));
    
    $profile = [
        'id'                 => (int) $row['id'],
        'profile_type'       => isset($row['profile_type']) ? $row['profile_type'] : 'classic',
        'team'               => $row['team'] ?: '',
        'friend_code'        => $row['friend_code'] ?: '',
        'friend_code_public' => !empty($row['friend_code_public']),
        'xp'                 => (int) $row['xp'],
        'country'            => $country,
        'pokemon_go_username' => $row['pokemon_go_username'] ?: '',
        'scatterbug_pattern' => $row['scatterbug_pattern'] ?: '',
        'reasons'            => $reasons,
        'user_id'            => $wp_user_id,
        'discord_id'         => $discord_id_value,
    ];
    
    return $profile;
}

/**
 * Get Pokémon GO user profile.
 *
 * @param int|null $user_id WordPress User ID (optional)
 * @param string|null $discord_id Optional Discord ID
 * @return array User profile
 */
function poke_hub_get_user_profile($user_id = null, $discord_id = null) {
    global $wpdb;

    // Normalize inputs
    $wp_user_id = null;
    $discord_id_value = null;

    // user_id parameter: always treated as WordPress user ID (int)
    if ($user_id !== null) {
        $wp_user_id = (int) $user_id;
        if ($wp_user_id <= 0) {
            $wp_user_id = null;
        }
    }

    // discord_id parameter: Discord ID (string)
    if ($discord_id !== null) {
        $discord_id_value = sanitize_text_field((string) $discord_id);
        if ($discord_id_value === '') {
            $discord_id_value = null;
        }
    }

    // If only user_id provided, try to get discord_id from subscription_accounts
    if ($wp_user_id !== null && $discord_id_value === null) {
        $discord_id_value = poke_hub_get_discord_id_from_user_id($wp_user_id);
    }

    // If only discord_id provided, try to get user_id from subscription_accounts
    if ($discord_id_value !== null && $wp_user_id === null) {
        $wp_user_id = poke_hub_get_user_id_from_discord_id($discord_id_value);
    }

    // If no identifier provided, return empty
    if ($wp_user_id === null && $discord_id_value === null) {
        return [];
    }

    // Get table name
    $table_name = pokehub_get_table('user_profiles');
    if (empty($table_name)) {
        return [];
    }

    // Build query to search by user_id or discord_id
    if ($wp_user_id !== null && $discord_id_value !== null) {
        // Search by both (OR condition)
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d OR discord_id = %s LIMIT 1",
            $wp_user_id,
            $discord_id_value
        );
    } elseif ($wp_user_id !== null) {
        // Search by user_id only
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d LIMIT 1",
            $wp_user_id
        );
    } elseif ($discord_id_value !== null) {
        // Search by discord_id only
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE discord_id = %s LIMIT 1",
            $discord_id_value
        );
    } else {
        return [];
    }

    $row = $wpdb->get_row($query, ARRAY_A);

    // Get country from Ultimate Member (single source of truth)
    // Always check Ultimate Member first, regardless of whether row exists
    $country = '';
    if ($wp_user_id !== null && $wp_user_id > 0) {
        // Always get country from Ultimate Member usermeta (single source of truth)
        $um_country = get_user_meta($wp_user_id, 'country', true);
        if (!empty($um_country)) {
            $country = $um_country;
        }
    }
    
    // If no row exists, return empty profile with country from Ultimate Member
    if (!$row) {
        return [
            'team'                => '',
            'friend_code'         => '',
            'friend_code_public'  => false,
            'xp'                  => 0,
            'country'             => $country,
            'pokemon_go_username' => '',
            'scatterbug_pattern'  => '',
            'reasons'             => [],
            'user_id'             => $wp_user_id,
            'discord_id'          => $discord_id_value,
        ];
    }

    // Normalize reasons from database (stored as JSON)
    $reasons = [];
    if (!empty($row['reasons'])) {
        $raw = $row['reasons'];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $reasons = $decoded;
            }
        } elseif (is_array($raw)) {
            $reasons = $raw;
        }
    }

    // Clean reasons array
    $reasons = array_values(array_filter(array_map('strval', $reasons), function($v) {
        return $v !== '';
    }));

    // Check if country_custom is stored (priority: use saved custom country)
    $country_custom = isset($row['country_custom']) ? $row['country_custom'] : '';
    
    if (!empty($country_custom)) {
        // Use saved custom country (e.g., "Hawaï")
        $country = $country_custom;
    } else {
        // Fallback: Map UM country back to custom country for display based on pattern
        // If we have a pattern and the country is mapped to a custom country, use the custom one
        $scatterbug_pattern = $row['scatterbug_pattern'] ?? '';
        if (!empty($country) && !empty($scatterbug_pattern)) {
            $um_to_custom = poke_hub_get_um_to_custom_country_mapping($scatterbug_pattern);
            if (isset($um_to_custom[$country])) {
                $country = $um_to_custom[$country];
            }
        }
    }

    $profile = [
        'team'                => $row['team'] ?: '',
        'friend_code'         => $row['friend_code'] ?: '',
        'friend_code_public'  => !empty($row['friend_code_public']),
        'xp'                  => (int) $row['xp'],
        'country'             => $country,
        'pokemon_go_username' => $row['pokemon_go_username'] ?: '',
        'scatterbug_pattern'  => $scatterbug_pattern,
        'reasons'             => $reasons,
        'user_id'             => !empty($row['user_id']) ? (int) $row['user_id'] : null,
        'discord_id'          => !empty($row['discord_id']) ? $row['discord_id'] : null,
        'profile_type'        => isset($row['profile_type']) ? $row['profile_type'] : 'classic',
    ];

    return $profile;
}

/**
 * Save Pokémon GO user profile.
 *
 * @param int|null   $user_id WordPress User ID (optional)
 * @param array      $profile Profile data (may include 'discord_id' key)
 * @param string|null $discord_id Optional Discord ID (can also be in $profile['discord_id'])
 * @return bool Success or failure
 */
function poke_hub_save_user_profile($user_id = null, $profile = [], $discord_id = null) {
    global $wpdb;

    // Normalize user_id
    $wp_user_id = null;
    if ($user_id !== null) {
        $wp_user_id = (int) $user_id;
        if ($wp_user_id <= 0) {
            $wp_user_id = null;
        }
    }

    // Get discord_id from profile or parameter
    $discord_id_value = null;
    if (isset($profile['discord_id'])) {
        $discord_id_value = sanitize_text_field((string) $profile['discord_id']);
        if ($discord_id_value === '') {
            $discord_id_value = null;
        }
    }
    if ($discord_id_value === null && $discord_id !== null) {
        $discord_id_value = sanitize_text_field((string) $discord_id);
        if ($discord_id_value === '') {
            $discord_id_value = null;
        }
    }

    // If only user_id provided, try to get discord_id from subscription_accounts
    if ($wp_user_id !== null && $discord_id_value === null) {
        $discord_id_value = poke_hub_get_discord_id_from_user_id($wp_user_id);
    }

    // If only discord_id provided, try to get user_id from subscription_accounts
    if ($discord_id_value !== null && $wp_user_id === null) {
        $wp_user_id = poke_hub_get_user_id_from_discord_id($discord_id_value);
    }

    // At least one identifier must be provided (unless we're updating by profile ID via save_by_profile_id)
    // Check if profile_id is provided in the profile array (for admin edits by profile_id)
    $profile_id_from_array = isset($profile['id']) ? (int) $profile['id'] : null;
    $update_by_profile_id = ($profile_id_from_array !== null && $profile_id_from_array > 0);
    
    if (!$update_by_profile_id && $wp_user_id === null && $discord_id_value === null) {
        return false;
    }

    // Sanitize data
    $team = isset($profile['team']) ? sanitize_text_field($profile['team']) : '';
    $friend_code = isset($profile['friend_code']) ? trim(sanitize_text_field($profile['friend_code'])) : '';
    // Clean and validate friend code (must be exactly 12 digits)
    if (!empty($friend_code)) {
        if (function_exists('poke_hub_clean_friend_code')) {
            $friend_code = poke_hub_clean_friend_code($friend_code);
        } else {
            $cleaned = preg_replace('/[^0-9]/', '', $friend_code);
            // Must be exactly 12 digits
            $friend_code = (strlen($cleaned) === 12) ? $cleaned : '';
        }
    }
    $friend_code_public = isset($profile['friend_code_public']) ? (bool) $profile['friend_code_public'] : true; // Default to public
    // Clean XP (remove spaces) before saving
    $xp = isset($profile['xp']) ? (function_exists('poke_hub_clean_xp') ? poke_hub_clean_xp($profile['xp']) : absint(preg_replace('/[^0-9]/', '', (string) $profile['xp']))) : 0;
    
    // Get country: prioritize form value, then Ultimate Member, then empty
    // IMPORTANT: If country is explicitly provided in profile (even if empty), use it (allows deletion)
    // Only fetch from Ultimate Member if country is NOT provided in profile array
    $country_provided = array_key_exists('country', $profile);
    if ($country_provided) {
        // Country was explicitly provided in form, use it (even if empty to allow deletion)
        $country = isset($profile['country']) ? sanitize_text_field($profile['country']) : '';
    } else {
        // Country was NOT provided in form, fetch from Ultimate Member if available
        $country = '';
        if ($wp_user_id !== null && $wp_user_id > 0) {
            $um_country = get_user_meta($wp_user_id, 'country', true);
            if (!empty($um_country)) {
                $country = $um_country;
            }
        }
    }
    
    // Validate that the country label exists in Ultimate Member's countries list
    if (!empty($country)) {
        $countries = poke_hub_get_countries(); // Returns array: CODE => LABEL
        if (is_array($countries) && !in_array($country, $countries, true)) {
            // Invalid country label, reset to empty
            $country = '';
        }
    }
    
    $pokemon_go_username = isset($profile['pokemon_go_username']) ? sanitize_text_field($profile['pokemon_go_username']) : '';
    $scatterbug_pattern = isset($profile['scatterbug_pattern']) ? sanitize_text_field($profile['scatterbug_pattern']) : '';
    $reasons = isset($profile['reasons']) && is_array($profile['reasons']) ? array_map('sanitize_text_field', $profile['reasons']) : [];

    // Validate team: Use the centralized teams list
    $allowed_teams = array_keys(poke_hub_get_teams());
    if (!empty($team) && !in_array($team, $allowed_teams, true)) {
        $team = '';
    }

    // Get table name
    $table_name = pokehub_get_table('user_profiles');
    if (empty($table_name)) {
        return false;
    }

    // Store reasons as JSON
    $reasons_json = !empty($reasons) ? json_encode($reasons) : null;

    // Determine profile_type based on available identifiers
    // classic: has user_id (WordPress user)
    // discord: has discord_id but no user_id (Discord bot only)
    // anonymous: has neither user_id nor discord_id (front without login)
    $profile_type = 'classic';
    if ($wp_user_id === null && $discord_id_value !== null) {
        $profile_type = 'discord';
    } elseif ($wp_user_id === null && $discord_id_value === null) {
        $profile_type = 'anonymous';
    }

    // Check if row exists (by profile_id, user_id, or discord_id)
    $existing_row = null;
    
    // If updating by profile_id, use that first
    if ($update_by_profile_id) {
        $existing_row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, user_id, discord_id, profile_type FROM {$table_name} WHERE id = %d LIMIT 1", $profile_id_from_array),
            ARRAY_A
        );
    }
    
    // Otherwise, check by user_id or discord_id
    if (!$existing_row && $wp_user_id !== null) {
        $existing_row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, user_id, discord_id, profile_type FROM {$table_name} WHERE user_id = %d LIMIT 1", $wp_user_id),
            ARRAY_A
        );
    }
    if (!$existing_row && $discord_id_value !== null) {
        $existing_row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, user_id, discord_id, profile_type FROM {$table_name} WHERE discord_id = %s LIMIT 1", $discord_id_value),
            ARRAY_A
        );
    }

    // Prepare data for insert/update
    // For anonymous users, country is stored in the table
    $data = [
        'team'                => $team,
        'friend_code'         => $friend_code,
        'friend_code_public'  => $friend_code_public ? 1 : 0,
        'xp'                  => $xp,
        'pokemon_go_username' => $pokemon_go_username,
        'scatterbug_pattern'  => $scatterbug_pattern,
        'reasons'             => $reasons_json,
        'profile_type'        => $profile_type,
    ];
    
    // Store country in table only for anonymous profiles (profile_type = 'anonymous')
    // If country is explicitly provided in profile (even if empty), store it (allowing deletion)
    if ($profile_type === 'anonymous') {
        $country_provided = array_key_exists('country', $profile);
        if ($country_provided) {
            // Store country in table (even if empty, to allow deletion)
            $data['country'] = $country;
        }
    }
    
    // Store country_custom if country is a custom country (like "Hawaï", "Galápagos", etc.)
    // This allows us to display the custom country name even though UM stores the mapped country
    $country_provided = array_key_exists('country', $profile);
    if ($country_provided && !empty($country)) {
        $custom_to_um = poke_hub_get_custom_country_to_um_mapping();
        // If the country is a custom country, save it in country_custom
        if (isset($custom_to_um[$country])) {
            $data['country_custom'] = $country;
        } elseif (empty($country)) {
            // If country is empty, also clear country_custom
            $data['country_custom'] = null;
        }
    }

    // Only set user_id and discord_id if they are not null
    if ($wp_user_id !== null) {
        $data['user_id'] = $wp_user_id;
    }
    if ($discord_id_value !== null) {
        $data['discord_id'] = $discord_id_value;
    }

    if ($existing_row) {
        // Get full existing row data to preserve fields that are not being updated
        $existing_full_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
            $existing_row['id']
        ), ARRAY_A);
        
        // Update existing row - merge with existing data to preserve fields not provided
        // Merge user_id and discord_id if we have both
        if ($wp_user_id !== null && empty($existing_row['user_id'])) {
            $data['user_id'] = $wp_user_id;
        }
        if ($discord_id_value !== null && empty($existing_row['discord_id'])) {
            $data['discord_id'] = $discord_id_value;
        }
        
        // Preserve existing values for fields that are not provided in the update
        // If a field is explicitly provided (even if empty), we update it (allowing deletion)
        // If a field is not provided at all, we preserve the existing value
        if ($existing_full_row) {
            // Preserve pokemon_go_username only if not provided in profile array
            // If provided but empty, allow deletion by not preserving
            $username_provided = array_key_exists('pokemon_go_username', $profile);
            if (!$username_provided && !empty($existing_full_row['pokemon_go_username'])) {
                $data['pokemon_go_username'] = $existing_full_row['pokemon_go_username'];
            }
            
            // Preserve scatterbug_pattern only if not provided in profile array
            // If provided but empty, allow deletion by not preserving
            $pattern_provided = array_key_exists('scatterbug_pattern', $profile);
            if (!$pattern_provided && !empty($existing_full_row['scatterbug_pattern'])) {
                $data['scatterbug_pattern'] = $existing_full_row['scatterbug_pattern'];
            }
            
            // Preserve team only if not provided in profile array
            // If provided but empty, allow deletion by not preserving
            $team_provided = array_key_exists('team', $profile);
            if (!$team_provided && !empty($existing_full_row['team'])) {
                $data['team'] = $existing_full_row['team'];
            }
            
            // Preserve reasons only if not provided in profile array
            // If provided but empty, allow deletion by not preserving
            $reasons_provided = array_key_exists('reasons', $profile);
            if (!$reasons_provided && !empty($existing_full_row['reasons'])) {
                $data['reasons'] = $existing_full_row['reasons'];
            }
            
            // Preserve XP only if not provided in profile array
            // If provided but 0, allow setting to 0 (don't preserve)
            $xp_provided = array_key_exists('xp', $profile);
            if (!$xp_provided && isset($existing_full_row['xp']) && $existing_full_row['xp'] > 0) {
                $data['xp'] = (int) $existing_full_row['xp'];
            }
            
            // Preserve friend_code_public if friend_code is not being updated
            $friend_code_provided = array_key_exists('friend_code', $profile);
            if (!$friend_code_provided && isset($existing_full_row['friend_code_public'])) {
                $data['friend_code_public'] = (int) $existing_full_row['friend_code_public'];
            }
        }

        // Build format array dynamically based on $data keys
        $format = [];
        foreach ($data as $key => $value) {
            if ($key === 'user_id' || $key === 'xp' || $key === 'friend_code_public') {
                $format[] = '%d';
            } elseif ($key === 'reasons') {
                $format[] = '%s'; // JSON string
            } else {
                $format[] = '%s';
            }
        }

        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => $existing_row['id']],
            $format,
            ['%d']
        );
    } else {
        // Insert new row
        // Build format array dynamically based on $data keys
        $format = [];
        foreach ($data as $key => $value) {
            if ($key === 'user_id' || $key === 'xp' || $key === 'friend_code_public') {
                $format[] = '%d';
            } elseif ($key === 'reasons') {
                $format[] = '%s'; // JSON string
            } else {
                $format[] = '%s';
            }
        }

        $result = $wpdb->insert(
            $table_name,
            $data,
            $format
        );
    }

    if ($result === false) {
        return false;
    }

    // If friend_code was updated, purge Nginx Helper cache so the change appears immediately
    if (isset($profile['friend_code'])) {
        if (function_exists('poke_hub_purge_module_cache')) {
            poke_hub_purge_module_cache(['poke_hub_friend_codes', 'poke_hub_vivillon']);
        }
    }

    // Sync country with Ultimate Member (country is the single source of truth)
    // Country is ONLY stored in Ultimate Member usermeta, NOT in our table
    // If country is explicitly provided in profile (even if empty), update it (allowing deletion)
    if ($wp_user_id !== null && $wp_user_id > 0) {
        $country_provided = array_key_exists('country', $profile);
        if ($country_provided) {
            if (empty($country)) {
                // Delete country usermeta to remove it (Ultimate Member uses delete to clear)
                delete_user_meta($wp_user_id, 'country');
            } else {
                // Map custom countries (like "Hawaï") to UM countries before saving
                $custom_to_um = poke_hub_get_custom_country_to_um_mapping();
                $country_for_um = isset($custom_to_um[$country]) ? $custom_to_um[$country] : $country;
                
                // Update Ultimate Member's country (primary and only source)
                update_user_meta($wp_user_id, 'country', $country_for_um);
            }
            
            // IMPORTANT: purge all caches to see change immediately in UM tab
            // This deletes UM cache (wp_options), WordPress cache, and refreshes UM context
            if (function_exists('poke_hub_purge_um_user_cache')) {
                poke_hub_purge_um_user_cache($wp_user_id);
            }
        }
    }

    /**
     * Action after profile save
     *
     * @param int|null   $user_id User ID (may be null if only discord_id)
     * @param array      $profile Saved profile
     * @param string|null $discord_id Discord ID (may be null if only user_id)
     */
    do_action('poke_hub_user_profile_saved', $wp_user_id, $profile, $discord_id_value);

    return true;
}

/**
 * Synchronize user_id and discord_id in user_profiles from subscription_accounts.
 * This function should be called when subscription_accounts is updated.
 *
 * @param int $user_id WordPress user ID
 * @return bool Success or failure
 */
function poke_hub_sync_user_profile_ids_from_subscription($user_id) {
    global $wpdb;
    
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    
    // Get table name
    $table_name = pokehub_get_table('user_profiles');
    if (empty($table_name)) {
        return false;
    }
    
    // Check if table exists
    if (!pokehub_table_exists($table_name)) {
        return false;
    }
    
    // Get Discord ID from subscription_accounts
    $discord_id = poke_hub_get_discord_id_from_user_id($user_id);
    
    // Check if profile exists
    $existing_row = $wpdb->get_row(
        $wpdb->prepare("SELECT id, user_id, discord_id FROM {$table_name} WHERE user_id = %d LIMIT 1", $user_id),
        ARRAY_A
    );
    
    if ($existing_row) {
        // Update existing profile with discord_id if we have it
        $update_data = [];
        $format = [];
        
        if ($discord_id !== null && empty($existing_row['discord_id'])) {
            $update_data['discord_id'] = $discord_id;
            $format[] = '%s';
        }
        
        if (!empty($update_data)) {
            $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $existing_row['id']],
                $format,
                ['%d']
            );
        }
    } else {
        // Profile doesn't exist, but we could create it with just IDs if needed
        // For now, we don't auto-create profiles, they must be created explicitly
    }
    
    return true;
}

/**
 * Synchronize user_id and discord_id when a profile is saved.
 * This ensures user_profiles stays in sync with subscription_accounts.
 * Called internally when profile is saved.
 *
 * @param int|null $user_id WordPress user ID
 * @param string|null $discord_id Discord ID
 * @return void
 */
function poke_hub_sync_user_profile_ids_on_save($user_id, $discord_id) {
    global $wpdb;
    
    // Get table name and check if it exists
    $table_name = pokehub_get_table('user_profiles');
    if (empty($table_name) || !function_exists('pokehub_table_exists') || !pokehub_table_exists($table_name)) {
        return;
    }
    
    // If we have user_id but not discord_id, try to get it from subscription_accounts
    if ($user_id !== null && $user_id > 0 && $discord_id === null) {
        $discord_id_from_sub = poke_hub_get_discord_id_from_user_id($user_id);
        if ($discord_id_from_sub !== null) {
            // Update the profile with discord_id
            $existing_row = $wpdb->get_row(
                $wpdb->prepare("SELECT id FROM {$table_name} WHERE user_id = %d LIMIT 1", $user_id),
                ARRAY_A
            );
            if ($existing_row) {
                $wpdb->update(
                    $table_name,
                    ['discord_id' => $discord_id_from_sub],
                    ['id' => $existing_row['id']],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }
    
    // If we have discord_id but not user_id, try to get it from subscription_accounts
    if ($discord_id !== null && $user_id === null) {
        $user_id_from_sub = poke_hub_get_user_id_from_discord_id($discord_id);
        if ($user_id_from_sub !== null && $user_id_from_sub > 0) {
            // Update the profile with user_id
            $existing_row = $wpdb->get_row(
                $wpdb->prepare("SELECT id FROM {$table_name} WHERE discord_id = %s LIMIT 1", $discord_id),
                ARRAY_A
            );
            if ($existing_row) {
                $wpdb->update(
                    $table_name,
                    ['user_id' => $user_id_from_sub],
                    ['id' => $existing_row['id']],
                    ['%d'],
                    ['%d']
                );
            }
        }
    }
}

/**
 * Get available teams list.
 * Uses centralized data definition with WordPress filter support.
 * 
 * To customize teams list, use the filter:
 * add_filter('poke_hub_user_profiles_teams', 'your_custom_teams_function');
 *
 * @return array Teams list with their labels (slug => label)
 */
function poke_hub_get_teams() {
    // Get default teams from centralized data
    $teams = poke_hub_get_default_teams();
    
    /**
     * Filter the teams list.
     *
     * @param array $teams Teams list (slug => label)
     * @return array Modified teams list
     */
    return apply_filters('poke_hub_user_profiles_teams', $teams);
}

/**
 * Get available reasons list.
 * Uses centralized data definition with WordPress filter support.
 * 
 * To customize reasons list, use the filter:
 * add_filter('poke_hub_user_profiles_reasons', 'your_custom_reasons_function');
 *
 * @return array Reasons list with their labels (slug => label)
 */
function poke_hub_get_reasons() {
    // Get default reasons from centralized data
    $reasons = poke_hub_get_default_reasons();
    
    /**
     * Filter the reasons list.
     *
     * @param array $reasons Reasons list (slug => label)
     * @return array Modified reasons list
     */
    return apply_filters('poke_hub_user_profiles_reasons', $reasons);
}

/**
 * Get available Scatterbug/Vivillon patterns list.
 * Try to retrieve from Pokemon module first, otherwise return a default list.
 *
 * @return array Patterns list with their labels (form_slug => label)
 */
function poke_hub_get_scatterbug_patterns() {
    if (function_exists('poke_hub_pokemon_get_scatterbug_patterns')) {
        $patterns = poke_hub_pokemon_get_scatterbug_patterns();
        
        // If patterns found, return them
        if (!empty($patterns)) {
            return $patterns;
        } else {
            error_log('[POKE-HUB] poke_hub_get_scatterbug_patterns - patterns vide, utilise fallback');
        }
    } else {
        error_log('[POKE-HUB] poke_hub_get_scatterbug_patterns - fonction poke_hub_pokemon_get_scatterbug_patterns inexistante');
    }

    // Fallback: default list if Pokemon module is not active or no patterns found
    error_log('[POKE-HUB] poke_hub_get_scatterbug_patterns - retourne liste par défaut');
    return [
        'archipelago' => __('Archipel', 'poke-hub'),
        'continental' => __('Continental', 'poke-hub'),
        'elegant'     => __('Élégant', 'poke-hub'),
        'garden'      => __('Jardin', 'poke-hub'),
        'high-plains' => __('Hautes Plaines', 'poke-hub'),
        'icy-snow'    => __('Neige Glacée', 'poke-hub'),
        'jungle'      => __('Jungle', 'poke-hub'),
        'marine'      => __('Marin', 'poke-hub'),
        'meadow'      => __('Prairie', 'poke-hub'),
        'modern'      => __('Moderne', 'poke-hub'),
        'monsoon'     => __('Mousson', 'poke-hub'),
        'ocean'       => __('Océan', 'poke-hub'),
        'polar'       => __('Polaire', 'poke-hub'),
        'river'       => __('Rivière', 'poke-hub'),
        'sandstorm'   => __('Tempête de Sable', 'poke-hub'),
        'savanna'     => __('Savane', 'poke-hub'),
        'sun'         => __('Soleil', 'poke-hub'),
        'tundra'      => __('Toundra', 'poke-hub'),
    ];
}

/**
 * Get team label.
 *
 * @param string $team Team slug
 * @return string Team label
 */
function poke_hub_get_team_label($team) {
    $teams = poke_hub_get_teams();
    return isset($teams[$team]) ? $teams[$team] : $team;
}

/**
 * Get base URL for user profile links from settings
 * 
 * @return string Base URL (with trailing slash removed)
 */
function poke_hub_get_user_profiles_base_url() {
    $base_url = get_option('poke_hub_user_profiles_base_url', '');
    if (!empty($base_url)) {
        return rtrim($base_url, '/');
    }
    // Fallback to current site URL
    return home_url();
}

/**
 * Replace URL domain with configured base URL or current site domain
 * Used when sites share database and plugins return URLs from main site
 * 
 * @param string $url Original URL
 * @return string URL with configured base URL or current site domain
 */
function poke_hub_replace_url_domain_with_current_site($url) {
    if (empty($url)) {
        return $url;
    }
    
    // Get base URL from settings, or fallback to current site URL
    $base_url = poke_hub_get_user_profiles_base_url();
    
    $base_domain = parse_url($base_url, PHP_URL_HOST);
    $base_scheme = parse_url($base_url, PHP_URL_SCHEME);
    $base_port = parse_url($base_url, PHP_URL_PORT);
    
    if (empty($base_domain)) {
        return $url;
    }
    
    $parsed_url = parse_url($url);
    
    // If domain is already the base domain, return as is
    if (isset($parsed_url['host']) && $parsed_url['host'] === $base_domain) {
        return $url;
    }
    
    // Rebuild URL with base domain and scheme
    $new_url = ($base_scheme ? $base_scheme . '://' : '') . $base_domain;
    
    // Add port if specified in base URL
    if ($base_port) {
        $new_url .= ':' . $base_port;
    }
    
    // Preserve path, query, and fragment from original URL
    if (isset($parsed_url['path'])) {
        $new_url .= $parsed_url['path'];
    }
    if (isset($parsed_url['query'])) {
        $new_url .= '?' . $parsed_url['query'];
    }
    if (isset($parsed_url['fragment'])) {
        $new_url .= '#' . $parsed_url['fragment'];
    }
    
    return $new_url;
}

/**
 * Get Ultimate Member profile tab URL for Pokémon GO profile.
 * Requires Ultimate Member plugin to be active.
 *
 * @param int $user_id User ID
 * @return string Profile tab URL or empty string if Ultimate Member is not available
 */
function poke_hub_get_um_profile_tab_url($user_id) {
    if (!function_exists('um_user_profile_url')) {
        return '';
    }
    
    $profile_url = um_user_profile_url($user_id);
    
    // Replace domain with current site domain (sites share database, so UM may return URLs from main site)
    $profile_url = poke_hub_replace_url_domain_with_current_site($profile_url);
    
    if (empty($profile_url)) {
        return '';
    }
    
    // Add tab parameter
    $separator = (strpos($profile_url, '?') !== false) ? '&' : '?';
    return $profile_url . $separator . 'tab=game-pokemon-go';
}

/**
 * Get user profile URL for Pokémon GO tab
 * 
 * @param int|null $user_id User ID. If null, uses current user ID
 * @return string Profile URL or empty string if user not found
 */
function poke_hub_get_user_profile_url($user_id = null) {
    if ($user_id === null) {
        if (!is_user_logged_in()) {
            return '';
        }
        $user_id = get_current_user_id();
    }
    
    $user = get_userdata($user_id);
    if (!$user || empty($user->user_nicename)) {
        return '';
    }
    
    // Use configured base URL for profiles, or current site URL
    $base_url = function_exists('poke_hub_get_user_profiles_base_url') 
        ? poke_hub_get_user_profiles_base_url() 
        : home_url();
    
    return $base_url . '/profil/' . $user->user_nicename . '/?tab=game-pokemon-go';
}

/**
 * Get login URL with redirect option
 * Always uses current site URL (not the configured base URL) to ensure proper redirection
 * 
 * @param string $redirect_type Redirect type: 'current' (default) or 'profile'
 * @param string|null $custom_url Optional custom redirect URL (overrides redirect_type)
 * @return string Login URL with redirect parameter
 */
function poke_hub_get_login_url_with_redirect($redirect_type = 'current', $custom_url = null) {
    $redirect_url = '';
    
    // If custom URL provided, use it
    if ($custom_url !== null) {
        $redirect_url = $custom_url;
    } elseif ($redirect_type === 'profile') {
        // Redirect to user profile
        $profile_url = function_exists('poke_hub_get_user_profile_url') 
            ? poke_hub_get_user_profile_url() 
            : '';
        
        if (!empty($profile_url)) {
            $redirect_url = $profile_url;
        } else {
            // Fallback to current page if profile URL not available
            $redirect_url = home_url($_SERVER['REQUEST_URI']);
        }
    } else {
        // Default: redirect to current page
        $redirect_url = home_url($_SERVER['REQUEST_URI']);
    }
    
    // Always use current site URL for login redirect (not the configured base URL)
    return home_url('/wp-login.php?redirect_to=' . urlencode($redirect_url));
}

/**
 * Format XP number with spaces (French format: groups of 3)
 * 
 * @param int|string $xp XP value
 * @return string Formatted XP (e.g., "738 000 000" or "10 000")
 */
function poke_hub_format_xp($xp) {
    // Handle empty/null values (but not 0)
    if ($xp === '' || $xp === null || $xp === false) {
        return '';
    }
    
    $xp_int = (int) $xp;
    return number_format($xp_int, 0, ',', ' ');
}

/**
 * Clean XP value (remove spaces)
 * 
 * @param string|int $xp XP value (with or without spaces)
 * @return int Cleaned XP (integer)
 */
function poke_hub_clean_xp($xp) {
    // Handle empty/null values
    if ($xp === '' || $xp === null || $xp === false) {
        return 0;
    }
    
    // Remove all spaces and non-digit characters, convert to int
    $cleaned = preg_replace('/[^0-9]/', '', (string) $xp);
    return (int) $cleaned;
}

/**
 * Clean Pokémon GO friend code (remove spaces and non-digit characters)
 * Must be exactly 12 digits
 * 
 * @param string $friend_code Friend code (with or without spaces)
 * @return string Cleaned friend code (digits only) or empty string if invalid
 */
function poke_hub_clean_friend_code($friend_code) {
    if (empty($friend_code)) {
        return '';
    }
    
    // Remove all spaces and non-digit characters
    $cleaned = preg_replace('/[^0-9]/', '', $friend_code);
    
    // Must be exactly 12 digits
    if (strlen($cleaned) !== 12) {
        return '';
    }
    
    return $cleaned;
}

/**
 * Format Pokémon GO friend code with spaces every 4 digits
 * Note: This function only formats, it doesn't validate the length
 * 
 * @param string $friend_code Friend code (with or without spaces)
 * @return string Formatted friend code (e.g., "1234 5678 9012")
 */
function poke_hub_format_friend_code($friend_code) {
    if (empty($friend_code)) {
        return '';
    }
    
    // Clean the code first (just remove non-digits, don't validate length for formatting)
    $cleaned = preg_replace('/[^0-9]/', '', $friend_code);
    
    if (empty($cleaned)) {
        return '';
    }
    
    // Add space every 4 digits
    return trim(chunk_split($cleaned, 4, ' '));
}

/**
 * Get custom country to UM country mapping.
 * Maps custom countries (like "Hawaï", "Galápagos") to Ultimate Member countries.
 * 
 * @return array Mapping custom_country => um_country
 */
function poke_hub_get_custom_country_to_um_mapping() {
    return [
        'Hawaï' => "États-Unis d'Amérique",
        'Galápagos' => 'Équateur',
        'Açores' => 'Portugal',
        'Îles Baléares' => 'Espagne',
        'Madère' => 'Portugal',
        'Kosovo' => 'Kosovo', // Si Kosovo est dans UM, sinon on garde tel quel
    ];
}

/**
 * Get UM country to custom country mapping (for display).
 * Maps UM countries to custom countries for display based on pattern.
 * 
 * @param string $pattern_slug Vivillon pattern slug (optional)
 * @return array Mapping um_country => custom_country
 */
function poke_hub_get_um_to_custom_country_mapping($pattern_slug = '') {
    $mapping = [];
    
    // Pattern Ocean: map countries to custom regions
    if ($pattern_slug === 'ocean') {
        $mapping["États-Unis d'Amérique"] = 'Hawaï';
        $mapping['Équateur'] = 'Galápagos';
        $mapping['Portugal'] = 'Açores'; // Si Portugal et pattern ocean, on affiche Açores
        $mapping['Espagne'] = 'Îles Baléares'; // Si Espagne et pattern ocean, on affiche Îles Baléares
    }
    
    // Pattern Marine: map countries to custom regions
    if ($pattern_slug === 'marine') {
        $mapping['Portugal'] = 'Açores'; // Si Portugal et pattern marine, on affiche Açores ou Madère selon contexte
        $mapping['Espagne'] = 'Îles Baléares'; // Si Espagne et pattern marine, on affiche Îles Baléares
    }
    
    // Pattern Sun (Ocean/Tropical): map Ecuador to Galápagos
    if ($pattern_slug === 'sun' || $pattern_slug === 'ocean') {
        $mapping['Équateur'] = 'Galápagos';
    }
    
    return $mapping;
}

/**
 * Get countries list from Ultimate Member.
 * Includes custom countries (like "Hawaï") that are mapped to UM countries internally.
 * 
 * @return array Countries list (code => label) or empty array if Ultimate Member is not available
 */
function poke_hub_get_countries() {
    // Cache avec transient (24 heures) pour éviter les appels répétés à UM et le tri
    $cache_key = 'poke_hub_countries_list';
    $cached = get_transient($cache_key);
    if ($cached !== false && is_array($cached)) {
        return $cached;
    }
    
    // Check if Ultimate Member is available
    if (!function_exists('UM') || !is_object(UM())) {
        return [];
    }
    
    // Get countries from Ultimate Member
    $countries = UM()->builtin()->get('countries');
    
    // Ensure we have an array
    if (!is_array($countries)) {
        return [];
    }
    
    // Add custom countries (like "Hawaï", "Galápagos", etc.) to the list
    // These are for selection/display only, they will be mapped to UM countries on save
    $custom_countries = [
        'Hawaï' => 'Hawaï',
        'Galápagos' => 'Galápagos',
        'Açores' => 'Açores',
        'Îles Baléares' => 'Îles Baléares',
        'Madère' => 'Madère',
        'Kosovo' => 'Kosovo',
    ];
    
    // Merge custom countries with UM countries
    $countries = array_merge($countries, $custom_countries);
    
    // Sort all countries alphabetically by label (value), preserving keys
    // Use collation that handles accents properly (French locale)
    uasort($countries, function($a, $b) {
        // Normalize accents for proper alphabetical sorting
        // Remove accents for comparison while keeping original display
        $a_normalized = remove_accents($a);
        $b_normalized = remove_accents($b);
        
        // First compare normalized strings (without accents)
        $cmp = strcasecmp($a_normalized, $b_normalized);
        if ($cmp !== 0) {
            return $cmp;
        }
        
        // If normalized strings are equal, compare original strings
        // This ensures consistent ordering when accents are the only difference
        return strcasecmp($a, $b);
    });
    
    // Cache pour 24 heures
    if (!empty($countries)) {
        set_transient($cache_key, $countries, DAY_IN_SECONDS);
    }
    
    return $countries;
}

/**
 * Get country label from country code.
 * 
 * @param string $country_code Country code (e.g., 'FR', 'US', 'GB')
 * @return string Country label or the code itself if not found
 */
function poke_hub_get_country_label($country_code) {
    if (empty($country_code)) {
        return '';
    }
    
    $countries = poke_hub_get_countries();
    
    if (isset($countries[$country_code])) {
        return $countries[$country_code];
    }
    
    // Return the code itself if not found
    return $country_code;
}

/**
 * Purge Ultimate Member user cache and WordPress cache.
 * This ensures that UM and WordPress display updated user meta immediately.
 * 
 * @param int $user_id WordPress user ID
 */
function poke_hub_purge_um_user_cache($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    // 1) Ultimate Member cache (stored in wp_options)
    // UM stores cached user data in options table, delete it to force refresh
    delete_option('um_cache_userdata_' . $user_id);

    // 2) WordPress/Redis object cache
    clean_user_cache($user_id);
    wp_cache_delete($user_id, 'user_meta');
    wp_cache_delete($user_id, 'users');

    // 3) Ultimate Member in-memory user context (same request)
    if (function_exists('um_fetch_user')) {
        um_fetch_user($user_id);
    }
}

/**
 * Force refresh of WP user meta cache (useful with Redis Object Cache).
 * 
 * @deprecated Use poke_hub_purge_um_user_cache() instead
 * @param int $user_id WordPress user ID
 */
function poke_hub_refresh_user_meta_cache($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    // WordPress core cache cleanup for a user
    clean_user_cache($user_id);

    // Extra deletions (often helps with persistent object cache)
    wp_cache_delete($user_id, 'user_meta');
    wp_cache_delete($user_id, 'users');
}

/**
 * Force Ultimate Member to reload the "current/profile" user context.
 * 
 * @deprecated Use poke_hub_purge_um_user_cache() instead
 * @param int $user_id WordPress user ID
 */
function poke_hub_um_refresh_user_context($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    if (function_exists('um_fetch_user')) {
        um_fetch_user($user_id);
    }
}
