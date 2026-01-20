<?php
// File: modules/user-profiles/admin/forms/user-profile-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render user profile edit form by user ID
 * 
 * @param int $user_id WordPress User ID
 */
function poke_hub_render_user_profile_form($user_id) {
    $profile = poke_hub_get_user_profile($user_id);
    if (empty($profile)) {
        // If profile doesn't exist, get user data to display name
        $user = get_userdata($user_id);
        if (!$user) {
            wp_die(__('User not found.', 'poke-hub'));
        }
        // Create empty profile for display
        $profile = [
            'team' => '',
            'friend_code' => '',
            'friend_code_public' => true,
            'xp' => 0,
            'country' => '',
            'pokemon_go_username' => '',
            'scatterbug_pattern' => '',
            'reasons' => [],
            'user_id' => $user_id,
        ];
    }
    
    // Get profile ID if exists (to allow update by ID if needed)
    $profile_id = isset($profile['id']) ? (int) $profile['id'] : 0;
    
    // Get user data
    $user = get_userdata($user_id);
    if (!$user) {
        wp_die(__('User not found.', 'poke-hub'));
    }

    $teams = poke_hub_get_teams();
    $reasons = poke_hub_get_reasons();
    $scatterbug_patterns = poke_hub_get_scatterbug_patterns();
    $countries = function_exists('poke_hub_get_countries') ? poke_hub_get_countries() : [];

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(sprintf(__('Edit Profile: %s', 'poke-hub'), $user->display_name)); ?></h1>
        <p class="description">
            <a href="<?php echo esc_url(admin_url('admin.php?page=poke-hub-user-profiles')); ?>">&larr; <?php _e('Back to list', 'poke-hub'); ?></a>
        </p>

        <form method="post" action="" id="poke-hub-profile-admin-form">
            <?php wp_nonce_field('poke_hub_save_profile', 'poke_hub_profile_nonce'); ?>
            <?php if ($profile_id > 0) : ?>
                <input type="hidden" name="profile_id" value="<?php echo esc_attr($profile_id); ?>">
            <?php endif; ?>
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">

            <div class="admin-lab-form-section">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="team" class="admin-lab-field-label"><?php _e('Team', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <select name="team" id="team" class="admin-lab-field-select">
                                <option value=""><?php _e('-- Select a team --', 'poke-hub'); ?></option>
                                <?php foreach ($teams as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($profile['team'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="friend_code" class="admin-lab-field-label"><?php _e('Friend Code', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <?php 
                            // Format friend code for display in input (with spaces)
                            $formatted_friend_code = !empty($profile['friend_code']) && function_exists('poke_hub_format_friend_code')
                                ? poke_hub_format_friend_code($profile['friend_code'])
                                : $profile['friend_code'];
                            ?>
                            <input type="text" name="friend_code" id="friend_code" value="<?php echo esc_attr($formatted_friend_code); ?>" class="admin-lab-field-input" placeholder="1234 5678 9012" maxlength="14" pattern="[0-9\s]{0,14}" title="<?php esc_attr_e('The friend code must be exactly 12 digits (e.g., 1234 5678 9012)', 'poke-hub'); ?>">
                            <p class="description"><?php _e('Your Pokémon GO friend code (must be exactly 12 digits, e.g., 1234 5678 9012)', 'poke-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="friend_code_public" class="admin-lab-field-label"><?php _e('Friend Code Visibility', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="friend_code_public" id="friend_code_public" value="1" <?php checked($profile['friend_code_public'], true); ?>>
                                <?php _e('Display friend code publicly on user profile', 'poke-hub'); ?>
                            </label>
                            <p class="description"><?php _e('If unchecked, the friend code will only be visible to the user and administrators.', 'poke-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="xp" class="admin-lab-field-label"><?php _e('XP', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <?php 
                            // Format XP for display in input (with spaces)
                            $formatted_xp = !empty($profile['xp']) && function_exists('poke_hub_format_xp')
                                ? poke_hub_format_xp($profile['xp'])
                                : $profile['xp'];
                            ?>
                            <input type="text" name="xp" id="xp" value="<?php echo esc_attr($formatted_xp); ?>" class="admin-lab-field-input" pattern="[0-9\s]*" placeholder="0">
                            <p class="description"><?php _e('Your total XP in Pokémon GO', 'poke-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="country" class="admin-lab-field-label"><?php _e('Country', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <select name="country" id="country" class="admin-lab-field-select">
                                <option value=""><?php _e('-- Select a country --', 'poke-hub'); ?></option>
                                <?php foreach ($countries as $code => $label) : ?>
                                    <option value="<?php echo esc_attr($label); ?>" <?php selected($profile['country'], $label); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Your country. This will be synchronized with Ultimate Member if available.', 'poke-hub'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pokemon_go_username" class="admin-lab-field-label"><?php _e('Pokémon GO Username', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="pokemon_go_username" id="pokemon_go_username" value="<?php echo esc_attr($profile['pokemon_go_username']); ?>" class="admin-lab-field-input">
                            <p class="description"><?php _e('Your in-game username', 'poke-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="scatterbug_pattern" class="admin-lab-field-label"><?php _e('Scatterbug Pattern', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <select name="scatterbug_pattern" id="scatterbug_pattern" class="admin-lab-field-select">
                                <option value=""><?php _e('-- Select a pattern --', 'poke-hub'); ?></option>
                                <?php foreach ($scatterbug_patterns as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($profile['scatterbug_pattern'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('The Scatterbug/Vivillon pattern for your region', 'poke-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label class="admin-lab-field-label"><?php _e('Reasons', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <?php foreach ($reasons as $value => $label) : ?>
                                    <?php
                                    // Ensure value is string for comparison (profile['reasons'] is already normalized as strings)
                                    $value_str = (string) $value;
                                    $is_checked = in_array($value_str, $profile['reasons'], true);
                                    ?>
                                    <label>
                                        <input type="checkbox" name="reasons[]" value="<?php echo esc_attr($value); ?>" <?php checked($is_checked); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php _e('Select why you are here (you can select multiple options)', 'poke-hub'); ?></p>
                        </td>
                    </tr>
                </table>

                <input type="hidden" name="poke_hub_save_profile" value="1">
                <?php submit_button(__('Save Profile', 'poke-hub')); ?>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Render user profile edit form by profile ID (for anonymous/discord profiles)
 * 
 * @param int $profile_id Profile ID
 */
function poke_hub_render_user_profile_form_by_id($profile_id) {
    $profile = poke_hub_get_user_profile_by_id($profile_id);
    if (empty($profile)) {
        wp_die(__('Profile not found.', 'poke-hub'));
    }
    
    // Get profile type labels
    $profile_type_labels = [
        'classic' => __('Classic (WordPress user)', 'poke-hub'),
        'discord' => __('Discord', 'poke-hub'),
        'anonymous' => __('Anonymous (Front without login)', 'poke-hub'),
    ];
    
    $profile_type = isset($profile['profile_type']) ? $profile['profile_type'] : 'classic';
    $profile_type_label = isset($profile_type_labels[$profile_type]) ? $profile_type_labels[$profile_type] : $profile_type;
    
    // Try to get user if user_id exists
    $user = null;
    if (!empty($profile['user_id'])) {
        $user = get_userdata($profile['user_id']);
    }

    $teams = poke_hub_get_teams();
    $reasons = poke_hub_get_reasons();
    $scatterbug_patterns = poke_hub_get_scatterbug_patterns();
    $countries = function_exists('poke_hub_get_countries') ? poke_hub_get_countries() : [];

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(sprintf(__('Edit Profile: %s', 'poke-hub'), $user ? $user->display_name : __('Anonymous Profile', 'poke-hub'))); ?></h1>
        <p class="description">
            <strong><?php _e('Profile Type:', 'poke-hub'); ?></strong> <?php echo esc_html($profile_type_label); ?><br>
            <?php if (!empty($profile['discord_id'])) : ?>
                <strong><?php _e('Discord ID:', 'poke-hub'); ?></strong> <?php echo esc_html($profile['discord_id']); ?><br>
            <?php endif; ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=poke-hub-user-profiles')); ?>">&larr; <?php _e('Back to list', 'poke-hub'); ?></a>
        </p>

        <form method="post" action="" id="poke-hub-profile-admin-form">
            <?php wp_nonce_field('poke_hub_save_profile', 'poke_hub_profile_nonce'); ?>
            <input type="hidden" name="profile_id" value="<?php echo esc_attr($profile_id); ?>">
            <?php if (!empty($profile['user_id'])) : ?>
                <input type="hidden" name="user_id" value="<?php echo esc_attr($profile['user_id']); ?>">
            <?php else : ?>
                <input type="hidden" name="user_id" value="0">
            <?php endif; ?>

            <div class="admin-lab-form-section">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="team" class="admin-lab-field-label"><?php _e('Team', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <select name="team" id="team" class="admin-lab-field-select">
                                <option value=""><?php _e('-- Select a team --', 'poke-hub'); ?></option>
                                <?php foreach ($teams as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($profile['team'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="friend_code" class="admin-lab-field-label"><?php _e('Friend Code', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <?php 
                            // Format friend code for display in input (with spaces)
                            $formatted_friend_code = !empty($profile['friend_code']) && function_exists('poke_hub_format_friend_code')
                                ? poke_hub_format_friend_code($profile['friend_code'])
                                : $profile['friend_code'];
                            ?>
                            <input type="text" name="friend_code" id="friend_code" value="<?php echo esc_attr($formatted_friend_code); ?>" class="admin-lab-field-input" placeholder="1234 5678 9012" maxlength="14" pattern="[0-9\s]{0,14}" title="<?php esc_attr_e('The friend code must be exactly 12 digits (e.g., 1234 5678 9012)', 'poke-hub'); ?>">
                            <p class="description"><?php _e('Your Pokémon GO friend code (must be exactly 12 digits, e.g., 1234 5678 9012)', 'poke-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="friend_code_public" class="admin-lab-field-label"><?php _e('Friend Code Visibility', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="friend_code_public" id="friend_code_public" value="1" <?php checked($profile['friend_code_public'], true); ?>>
                                <?php _e('Display friend code publicly on user profile', 'poke-hub'); ?>
                            </label>
                            <p class="description"><?php _e('If unchecked, the friend code will only be visible to the user and administrators.', 'poke-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="xp" class="admin-lab-field-label"><?php _e('XP', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <?php 
                            // Format XP for display in input (with spaces)
                            $formatted_xp = !empty($profile['xp']) && function_exists('poke_hub_format_xp')
                                ? poke_hub_format_xp($profile['xp'])
                                : $profile['xp'];
                            ?>
                            <input type="text" name="xp" id="xp" value="<?php echo esc_attr($formatted_xp); ?>" class="admin-lab-field-input" pattern="[0-9\s]*" placeholder="0">
                            <p class="description"><?php _e('Your total XP in Pokémon GO', 'poke-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="country" class="admin-lab-field-label"><?php _e('Country', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <select name="country" id="country" class="admin-lab-field-select">
                                <option value=""><?php _e('-- Select a country --', 'poke-hub'); ?></option>
                                <?php foreach ($countries as $code => $label) : ?>
                                    <option value="<?php echo esc_attr($label); ?>" <?php selected($profile['country'], $label); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Your country. This will be synchronized with Ultimate Member if available.', 'poke-hub'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pokemon_go_username" class="admin-lab-field-label"><?php _e('Pokémon GO Username', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="pokemon_go_username" id="pokemon_go_username" value="<?php echo esc_attr($profile['pokemon_go_username']); ?>" class="admin-lab-field-input">
                            <p class="description"><?php _e('Your in-game username', 'poke-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="scatterbug_pattern" class="admin-lab-field-label"><?php _e('Scatterbug Pattern', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <select name="scatterbug_pattern" id="scatterbug_pattern" class="admin-lab-field-select">
                                <option value=""><?php _e('-- Select a pattern --', 'poke-hub'); ?></option>
                                <?php foreach ($scatterbug_patterns as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($profile['scatterbug_pattern'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('The Scatterbug/Vivillon pattern for your region', 'poke-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label class="admin-lab-field-label"><?php _e('Reasons', 'poke-hub'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <?php foreach ($reasons as $value => $label) : ?>
                                    <?php
                                    // Ensure value is string for comparison (profile['reasons'] is already normalized as strings)
                                    $value_str = (string) $value;
                                    $is_checked = in_array($value_str, $profile['reasons'], true);
                                    ?>
                                    <label>
                                        <input type="checkbox" name="reasons[]" value="<?php echo esc_attr($value); ?>" <?php checked($is_checked); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php _e('Select why you are here (you can select multiple options)', 'poke-hub'); ?></p>
                        </td>
                    </tr>
                </table>

                <input type="hidden" name="poke_hub_save_profile" value="1">
                <?php submit_button(__('Save Profile', 'poke-hub')); ?>
            </div>
        </form>
    </div>
    <?php
}

