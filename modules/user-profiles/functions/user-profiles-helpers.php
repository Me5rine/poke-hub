<?php
// modules/user-profiles/functions/user-profiles-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Pokémon GO user profile.
 *
 * @param int $user_id User ID
 * @return array User profile
 */
function poke_hub_get_user_profile($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    // Get friend_code_public meta (defaults to true if not set)
    $friend_code_public_meta = get_user_meta($user_id, 'poke_hub_friend_code_public', true);
    $friend_code_public = ($friend_code_public_meta !== false) ? (bool) $friend_code_public_meta : true;

    $profile = [
        'team'                => get_user_meta($user_id, 'poke_hub_team', true) ?: '',
        'friend_code'         => get_user_meta($user_id, 'poke_hub_friend_code', true) ?: '',
        'friend_code_public'  => $friend_code_public,
        'xp'                  => get_user_meta($user_id, 'poke_hub_xp', true) ?: 0,
        'country'             => get_user_meta($user_id, 'poke_hub_country', true) ?: '',
        'pokemon_go_username' => get_user_meta($user_id, 'poke_hub_pokemon_go_username', true) ?: '',
        'scatterbug_pattern'  => get_user_meta($user_id, 'poke_hub_scatterbug_pattern', true) ?: '',
        'reasons'             => [], // Will be filled by robust normalization below
    ];

    // Reasons: robust normalization -> always array of strings: ['raids','pvp',...]
    $raw = get_user_meta($user_id, 'poke_hub_reasons', true);

    // If already an array (WordPress may have auto-unserialized), use it directly
    if (is_array($raw)) {
        // If associative array ['raids'=>1,'pvp'=>true] => keep only active keys
        $is_assoc = array_keys($raw) !== range(0, count($raw) - 1);
        if ($is_assoc) {
            $raw = array_keys(array_filter($raw));
        }
    } elseif (is_string($raw)) {
        // Handle string format (serialized PHP, JSON, or CSV)
        $s = trim($raw);
        
        // First try maybe_unserialize (handles PHP serialized format)
        $unserialized = maybe_unserialize($s);
        if (is_array($unserialized)) {
            $raw = $unserialized;
        } elseif ($s !== '' && ($s[0] === '[' || $s[0] === '{')) {
            // Try JSON if it looks like JSON
            $decoded = json_decode($s, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            } else {
                // If still string => treat as CSV
                $raw = array_map('trim', explode(',', $s));
            }
        } elseif ($s !== '') {
            // If still string => treat as CSV
            $raw = array_map('trim', explode(',', $s));
        } else {
            $raw = [];
        }
    } else {
        // false, null, or other => empty array
        $raw = [];
    }

    // Final: clean strings array (remove empty values, normalize to strings)
    $profile['reasons'] = array_values(array_filter(array_map('strval', $raw), function($v) {
        return $v !== '';
    }));

    return $profile;
}

/**
 * Save Pokémon GO user profile.
 *
 * @param int   $user_id User ID
 * @param array $profile Profile data
 * @return bool Success or failure
 */
function poke_hub_save_user_profile($user_id, $profile) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    // Sanitize data
    $team = isset($profile['team']) ? sanitize_text_field($profile['team']) : '';
    $friend_code = isset($profile['friend_code']) ? sanitize_text_field($profile['friend_code']) : '';
    $friend_code_public = isset($profile['friend_code_public']) ? (bool) $profile['friend_code_public'] : true; // Default to public
    $xp = isset($profile['xp']) ? absint($profile['xp']) : 0;
    $country = isset($profile['country']) ? sanitize_text_field($profile['country']) : '';
    $pokemon_go_username = isset($profile['pokemon_go_username']) ? sanitize_text_field($profile['pokemon_go_username']) : '';
    $scatterbug_pattern = isset($profile['scatterbug_pattern']) ? sanitize_text_field($profile['scatterbug_pattern']) : '';
    $reasons = isset($profile['reasons']) && is_array($profile['reasons']) ? array_map('sanitize_text_field', $profile['reasons']) : [];

    // Validate team: Use the centralized teams list
    $allowed_teams = array_keys(poke_hub_get_teams());
    if (!empty($team) && !in_array($team, $allowed_teams, true)) {
        $team = '';
    }

    // Update user meta
    update_user_meta($user_id, 'poke_hub_team', $team);
    update_user_meta($user_id, 'poke_hub_friend_code', $friend_code);
    update_user_meta($user_id, 'poke_hub_friend_code_public', $friend_code_public ? 1 : 0);
    update_user_meta($user_id, 'poke_hub_xp', $xp);
    update_user_meta($user_id, 'poke_hub_pokemon_go_username', $pokemon_go_username);
    update_user_meta($user_id, 'poke_hub_scatterbug_pattern', $scatterbug_pattern);
    update_user_meta($user_id, 'poke_hub_reasons', $reasons);

    // Sync country: Use Ultimate Member's country_code as the single source of truth
    // This ensures no duplication and sync across all plugins/modules
    if (!empty($country)) {
        // Update Ultimate Member's country_code (primary source)
        update_user_meta($user_id, 'country_code', $country);
        
        // Also update other possible locations for compatibility
        update_user_meta($user_id, 'country', $country);
        update_user_meta($user_id, 'billing_country', $country);
        
        // Clear Ultimate Member cache if available
        if (function_exists('um_fetch_user')) {
            um_fetch_user($user_id);
        }
    } elseif (empty($country)) {
        // If country is empty, try to get it from Ultimate Member first
        $um_country = get_user_meta($user_id, 'country_code', true);
        if (!empty($um_country)) {
            $country = $um_country;
        }
    }
    
    // Store country in poke_hub_country as a backup/cache
    update_user_meta($user_id, 'poke_hub_country', $country);

    /**
     * Action after profile save
     *
     * @param int   $user_id User ID
     * @param array $profile Saved profile
     */
    do_action('poke_hub_user_profile_saved', $user_id, $profile);

    return true;
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
    // Try to retrieve from Pokemon module if available
    if (poke_hub_is_module_active('pokemon') && function_exists('poke_hub_pokemon_get_scatterbug_patterns')) {
        $patterns = poke_hub_pokemon_get_scatterbug_patterns();
        
        // If patterns found, return them
        if (!empty($patterns)) {
            return $patterns;
        }
    }

    // Fallback: default list if Pokemon module is not active or no patterns found
    return [
        'archipelago' => __('Archipelago', 'poke-hub'),
        'continental' => __('Continental', 'poke-hub'),
        'elegant'     => __('Elegant', 'poke-hub'),
        'garden'      => __('Garden', 'poke-hub'),
        'high-plains' => __('High Plains', 'poke-hub'),
        'icy-snow'    => __('Icy Snow', 'poke-hub'),
        'jungle'      => __('Jungle', 'poke-hub'),
        'marine'      => __('Marine', 'poke-hub'),
        'meadow'      => __('Meadow', 'poke-hub'),
        'modern'      => __('Modern', 'poke-hub'),
        'monsoon'     => __('Monsoon', 'poke-hub'),
        'ocean'       => __('Ocean', 'poke-hub'),
        'polar'       => __('Polar', 'poke-hub'),
        'river'       => __('River', 'poke-hub'),
        'sandstorm'   => __('Sandstorm', 'poke-hub'),
        'savanna'     => __('Savanna', 'poke-hub'),
        'sun'         => __('Sun', 'poke-hub'),
        'tundra'      => __('Tundra', 'poke-hub'),
    ];
}

/**
 * Get user country from Ultimate Member or WordPress.
 * Uses Ultimate Member's country_code as the single source of truth.
 *
 * @param int $user_id User ID
 * @return string Country code
 */
function poke_hub_get_user_country($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }

    // Use Ultimate Member's country_code as the single source of truth
    $country = get_user_meta($user_id, 'country_code', true);
    if (!empty($country)) {
        return $country;
    }

    // Fallback to other locations if country_code is empty
    $country = get_user_meta($user_id, 'country', true);
    if (!empty($country)) {
        return $country;
    }

    $country = get_user_meta($user_id, 'billing_country', true);
    if (!empty($country)) {
        return $country;
    }

    // Last fallback: poke_hub_country cache
    $country = get_user_meta($user_id, 'poke_hub_country', true);
    if (!empty($country)) {
        return $country;
    }

    return '';
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
    if (empty($profile_url)) {
        return '';
    }
    
    // Add tab parameter
    $separator = (strpos($profile_url, '?') !== false) ? '&' : '?';
    return $profile_url . $separator . 'profiletab=pokehub-profile';
}

/**
 * Sync country when Ultimate Member profile is updated
 * 
 * @param int $user_id User ID
 * @param array $args Profile update arguments
 */
function poke_hub_um_sync_country_on_profile_update($user_id, $args) {
    // Check if country_code is being updated
    if (isset($args['country_code']) && !empty($args['country_code'])) {
        $country = sanitize_text_field($args['country_code']);
        
        // Update our cache
        update_user_meta($user_id, 'poke_hub_country', $country);
        
        // Update other locations for compatibility
        update_user_meta($user_id, 'country', $country);
        update_user_meta($user_id, 'billing_country', $country);
    }
}

// Hook into Ultimate Member profile updates if Ultimate Member is active
if (function_exists('um_user_pre_updating_profile')) {
    add_action('um_user_pre_updating_profile', 'poke_hub_um_sync_country_on_profile_update', 10, 2);
    add_action('um_after_user_account_updated', 'poke_hub_um_sync_country_on_profile_update', 10, 2);
}

