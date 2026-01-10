<?php
// modules/user-profiles/functions/user-profiles-keycloak-sync.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Keycloak nickname attribute from user meta or token
 * 
 * @param int $user_id WordPress user ID
 * @return string|null Keycloak nickname or null if not found
 */
function poke_hub_get_keycloak_nickname($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }
    
    // Try multiple possible locations for Keycloak data
    // Method 1: Check user meta (common Keycloak plugin pattern)
    $nickname = get_user_meta($user_id, 'keycloak_nickname', true);
    if (!empty($nickname)) {
        return sanitize_text_field($nickname);
    }
    
    // Method 2: Check in keycloak_attributes or similar meta key
    $keycloak_attrs = get_user_meta($user_id, 'keycloak_attributes', true);
    if (is_array($keycloak_attrs) && isset($keycloak_attrs['nickname'])) {
        return sanitize_text_field($keycloak_attrs['nickname']);
    }
    
    // Method 3: Check in JWT token claims if available
    $jwt_claims = get_user_meta($user_id, 'keycloak_jwt_claims', true);
    if (is_array($jwt_claims) && isset($jwt_claims['nickname'])) {
        return sanitize_text_field($jwt_claims['nickname']);
    }
    
    // Method 4: Check via filter hook (allows other plugins to provide nickname)
    $nickname = apply_filters('poke_hub_get_keycloak_nickname', null, $user_id);
    if (!empty($nickname)) {
        return sanitize_text_field($nickname);
    }
    
    return null;
}

/**
 * Synchronize Keycloak nickname to WordPress user display name
 * 
 * @param int $user_id WordPress user ID
 * @return bool True if synchronization was successful, false otherwise
 */
function poke_hub_sync_keycloak_nickname($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    
    $keycloak_nickname = poke_hub_get_keycloak_nickname($user_id);
    if (empty($keycloak_nickname)) {
        return false;
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Update display_name with Keycloak nickname
    $result = wp_update_user([
        'ID' => $user_id,
        'display_name' => $keycloak_nickname,
    ]);
    
    if (is_wp_error($result)) {
        return false;
    }
    
    // Also update nickname meta if different from display_name
    $current_nickname = get_user_meta($user_id, 'nickname', true);
    if ($current_nickname !== $keycloak_nickname) {
        update_user_meta($user_id, 'nickname', $keycloak_nickname);
    }
    
    // Trigger action hook for other code that might need to react to nickname sync
    do_action('poke_hub_keycloak_nickname_synced', $user_id, $keycloak_nickname);
    
    return true;
}

/**
 * Hook into user login to sync Keycloak nickname
 */
add_action('wp_login', 'poke_hub_sync_keycloak_nickname_on_login', 10, 2);
function poke_hub_sync_keycloak_nickname_on_login($user_login, $user) {
    if (!isset($user->ID)) {
        return;
    }
    
    // Sync nickname on login
    poke_hub_sync_keycloak_nickname($user->ID);
}

/**
 * Hook into user update to sync Keycloak nickname
 */
add_action('profile_update', 'poke_hub_sync_keycloak_nickname_on_update', 10, 2);
function poke_hub_sync_keycloak_nickname_on_update($user_id, $old_user_data) {
    // Sync nickname when user profile is updated
    poke_hub_sync_keycloak_nickname($user_id);
}

/**
 * Hook into set_user_meta to detect when Keycloak data is saved
 */
add_action('updated_user_meta', 'poke_hub_check_keycloak_meta_update', 10, 4);
add_action('added_user_meta', 'poke_hub_check_keycloak_meta_update', 10, 4);
function poke_hub_check_keycloak_meta_update($meta_id, $user_id, $meta_key, $meta_value) {
    // Check if Keycloak-related meta was updated
    $keycloak_meta_keys = [
        'keycloak_nickname',
        'keycloak_attributes',
        'keycloak_jwt_claims',
    ];
    
    if (in_array($meta_key, $keycloak_meta_keys, true)) {
        // Sync nickname when Keycloak data is updated
        poke_hub_sync_keycloak_nickname($user_id);
    }
    
    // Also check if it's an array containing nickname
    if (is_array($meta_value) && isset($meta_value['nickname'])) {
        poke_hub_sync_keycloak_nickname($user_id);
    }
}

