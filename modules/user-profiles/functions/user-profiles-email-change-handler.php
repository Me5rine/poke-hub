<?php
// modules/user-profiles/functions/user-profiles-email-change-handler.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle email change: detect change and store info for redirect
 */
add_action('profile_update', 'poke_hub_handle_email_change', 10, 2);
function poke_hub_handle_email_change($user_id, $old_user_data) {
    // Check if email was changed
    if (!isset($old_user_data->user_email)) {
        return;
    }
    
    // Get new email from POST or from updated user
    $new_email = '';
    if (isset($_POST['email'])) {
        $new_email = sanitize_email($_POST['email']);
    } else {
        $updated_user = get_userdata($user_id);
        if ($updated_user && isset($updated_user->user_email)) {
            $new_email = $updated_user->user_email;
        }
    }
    
    $old_email = $old_user_data->user_email;
    
    // Email hasn't changed, nothing to do
    if (empty($new_email) || $old_email === $new_email) {
        return;
    }
    
    // Store flag that email was changed (before logout happens)
    // Use a unique key that doesn't depend on user_id after logout
    $unique_key = wp_generate_password(12, false);
    set_transient('poke_hub_email_changed_' . $unique_key, [
        'old_email' => $old_email,
        'new_email' => $new_email,
        'user_id' => $user_id,
        'timestamp' => time(),
    ], 600); // 10 minutes expiry
    
    // Store the key in a cookie so we can retrieve it after logout
    setcookie('poke_hub_email_change_key', $unique_key, time() + 600, '/', '', is_ssl(), true);
}

/**
 * Hook into logout to redirect to profile page with message
 */
add_action('wp_logout', 'poke_hub_redirect_after_email_change_logout', 999);
function poke_hub_redirect_after_email_change_logout() {
    // Get the key from cookie
    $unique_key = isset($_COOKIE['poke_hub_email_change_key']) ? sanitize_text_field($_COOKIE['poke_hub_email_change_key']) : '';
    
    if (empty($unique_key)) {
        return;
    }
    
    // Get email change data
    $email_change_data = get_transient('poke_hub_email_changed_' . $unique_key);
    
    if (!$email_change_data || !isset($email_change_data['user_id'])) {
        return;
    }
    
    $user_id = $email_change_data['user_id'];
    
    // Clear the transient and cookie
    delete_transient('poke_hub_email_changed_' . $unique_key);
    setcookie('poke_hub_email_change_key', '', time() - 3600, '/', '', is_ssl(), true);
    
    // Get profile URL (Ultimate Member profile or custom profile page)
    $profile_url = '';
    
    // Try Ultimate Member profile URL first
    if (function_exists('um_user_profile_url')) {
        $profile_url = um_user_profile_url($user_id);
    } elseif (function_exists('um_get_user_profile_url')) {
        $profile_url = um_get_user_profile_url($user_id);
    }
    
    // Fallback: try to get profile URL from user
    if (empty($profile_url) && function_exists('get_author_posts_url')) {
        $profile_url = get_author_posts_url($user_id);
    }
    
    // If still no profile URL, use home URL
    if (empty($profile_url)) {
        $profile_url = home_url();
    }
    
    // Set transient for notification message (will be displayed on profile page)
    set_transient('poke_hub_email_change_notification_' . $user_id, true, 600); // 10 minutes
    
    // Add notification parameter to URL
    $redirect_url = add_query_arg([
        'poke_hub_notice' => 'email_changed',
        'user_id' => $user_id,
    ], $profile_url);
    
    // Redirect after logout
    add_filter('logout_redirect', function($redirect_to, $requested_redirect_to, $user) use ($redirect_url) {
        return $redirect_url;
    }, 999, 3);
}

/**
 * Alternative: Hook into send_email_change_email to detect email change
 * This fires after the email is changed but before logout
 */
add_action('send_email_change_email', 'poke_hub_prepare_email_change_redirect', 10, 3);
function poke_hub_prepare_email_change_redirect($user, $old_user_email, $userdata) {
    if (!isset($user->ID)) {
        return;
    }
    
    // Store info for redirect after logout
    $unique_key = wp_generate_password(12, false);
    set_transient('poke_hub_email_changed_' . $unique_key, [
        'old_email' => $old_user_email,
        'new_email' => isset($userdata['user_email']) ? $userdata['user_email'] : $user->user_email,
        'user_id' => $user->ID,
        'timestamp' => time(),
    ], 600);
    
    // Store in cookie
    setcookie('poke_hub_email_change_key', $unique_key, time() + 600, '/', '', is_ssl(), true);
}

/**
 * Display notification message on profile page after email change
 */
add_action('wp', 'poke_hub_display_email_change_notification');
function poke_hub_display_email_change_notification() {
    // Only show if notice parameter is present
    if (!isset($_GET['poke_hub_notice']) || $_GET['poke_hub_notice'] !== 'email_changed') {
        return;
    }
    
    // Get user_id from URL if present
    $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    
    if ($user_id <= 0) {
        return;
    }
    
    // Check if notification should be shown
    if (get_transient('poke_hub_email_change_notification_' . $user_id)) {
        // Clear the transient
        delete_transient('poke_hub_email_change_notification_' . $user_id);
        // Set a transient to display the message on the profile page
        set_transient('poke_hub_email_change_message_display', true, 60); // 1 minute for display
    }
}

/**
 * Get email change notification message
 */
function poke_hub_get_email_change_notification_message() {
    // Check if we should display the message (set by wp action)
    if (!get_transient('poke_hub_email_change_message_display')) {
        return '';
    }
    
    // Clear the transient after displaying
    delete_transient('poke_hub_email_change_message_display');
    
    $message = __('Check your new email to log in.', 'poke-hub');
    
    return $message;
}

/**
 * Filter to add notification to profile pages
 */
add_filter('poke_hub_profile_notification_message', 'poke_hub_add_email_change_notification', 10, 1);
function poke_hub_add_email_change_notification($existing_message) {
    $email_change_message = poke_hub_get_email_change_notification_message();
    
    if (!empty($email_change_message)) {
        if (!empty($existing_message)) {
            return $existing_message . ' ' . $email_change_message;
        }
        return $email_change_message;
    }
    
    return $existing_message;
}

