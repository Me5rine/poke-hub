<?php
// modules/user-profiles/admin/user-profiles-admin.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User profiles admin page for Pokémon GO
 */
function poke_hub_user_profiles_admin_ui() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to access this page.', 'poke-hub'));
    }

    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

    // Handle form submission
    $admin_error_message = '';
    if (isset($_POST['poke_hub_save_profile']) && wp_verify_nonce($_POST['poke_hub_profile_nonce'], 'poke_hub_save_profile')) {
        $user_id_to_save = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        
        if ($user_id_to_save > 0) {
            // Clean and validate friend code (must be exactly 12 digits)
            $friend_code_raw = isset($_POST['friend_code']) ? sanitize_text_field($_POST['friend_code']) : '';
            $friend_code = '';
            
            if (!empty($friend_code_raw)) {
                if (function_exists('poke_hub_clean_friend_code')) {
                    $friend_code = poke_hub_clean_friend_code($friend_code_raw);
                } else {
                    $cleaned = preg_replace('/[^0-9]/', '', $friend_code_raw);
                    // Must be exactly 12 digits
                    $friend_code = (strlen($cleaned) === 12) ? $cleaned : '';
                }
                
                // Validate: if friend code was provided but is invalid, show error
                if (empty($friend_code) && !empty($friend_code_raw)) {
                    $admin_error_message = __('The friend code must be exactly 12 digits (e.g., 1234 5678 9012).', 'poke-hub');
                }
            }
            
            // Only save if no validation errors
            if (empty($admin_error_message)) {
                $profile = [
                    'team'                => isset($_POST['team']) ? sanitize_text_field($_POST['team']) : '',
                    'friend_code'         => $friend_code,
                    'friend_code_public'  => isset($_POST['friend_code_public']) ? true : false,
                    'xp'                  => isset($_POST['xp']) ? (function_exists('poke_hub_clean_xp') ? poke_hub_clean_xp($_POST['xp']) : absint(preg_replace('/[^0-9]/', '', $_POST['xp']))) : 0,
                    'country'             => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
                    'pokemon_go_username' => isset($_POST['pokemon_go_username']) ? sanitize_text_field($_POST['pokemon_go_username']) : '',
                    'scatterbug_pattern'  => isset($_POST['scatterbug_pattern']) ? sanitize_text_field($_POST['scatterbug_pattern']) : '',
                    'reasons'             => isset($_POST['reasons']) && is_array($_POST['reasons']) ? array_map('sanitize_text_field', $_POST['reasons']) : [],
                ];

                poke_hub_save_user_profile($user_id_to_save, $profile);
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Profile saved successfully.', 'poke-hub') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($admin_error_message) . '</p></div>';
            }
        }
    }

    // Edit user view
    if ($action === 'edit' && $user_id > 0) {
        poke_hub_render_user_profile_form($user_id);
        return;
    }

    // Users list view
    poke_hub_render_user_profiles_list();
}

/**
 * Render user profile edit form
 */
function poke_hub_render_user_profile_form($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        wp_die(__('User not found.', 'poke-hub'));
    }

    $profile = poke_hub_get_user_profile($user_id);
    $teams = poke_hub_get_teams();
    $reasons = poke_hub_get_reasons();
    $scatterbug_patterns = poke_hub_get_scatterbug_patterns();
    $countries = get_option('poke_hub_countries_list', []); // Can be filled later

    // Get country from Ultimate Member or WordPress
    $current_country = poke_hub_get_user_country($user_id);
    if (empty($profile['country']) && !empty($current_country)) {
        $profile['country'] = $current_country;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(sprintf(__('Edit Profile: %s', 'poke-hub'), $user->display_name)); ?></h1>
        <p class="description">
            <a href="<?php echo esc_url(admin_url('admin.php?page=poke-hub-user-profiles')); ?>">&larr; <?php _e('Back to list', 'poke-hub'); ?></a>
        </p>

        <form method="post" action="" id="poke-hub-profile-admin-form">
            <?php wp_nonce_field('poke_hub_save_profile', 'poke_hub_profile_nonce'); ?>
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="team"><?php _e('Team', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <select name="team" id="team">
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
                        <label for="friend_code"><?php _e('Friend Code', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <?php 
                        // Format friend code for display in input (with spaces)
                        $formatted_friend_code = !empty($profile['friend_code']) && function_exists('poke_hub_format_friend_code')
                            ? poke_hub_format_friend_code($profile['friend_code'])
                            : $profile['friend_code'];
                        ?>
                        <input type="text" name="friend_code" id="friend_code" value="<?php echo esc_attr($formatted_friend_code); ?>" class="regular-text" placeholder="1234 5678 9012" maxlength="14" pattern="[0-9\s]{0,14}" title="<?php esc_attr_e('The friend code must be exactly 12 digits (e.g., 1234 5678 9012)', 'poke-hub'); ?>">
                        <p class="description"><?php _e('Your Pokémon GO friend code (must be exactly 12 digits, e.g., 1234 5678 9012)', 'poke-hub'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="friend_code_public"><?php _e('Friend Code Visibility', 'poke-hub'); ?></label>
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
                        <label for="xp"><?php _e('XP', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <?php 
                        // Format XP for display in input (with spaces)
                        $formatted_xp = !empty($profile['xp']) && function_exists('poke_hub_format_xp')
                            ? poke_hub_format_xp($profile['xp'])
                            : $profile['xp'];
                        ?>
                        <input type="text" name="xp" id="xp" value="<?php echo esc_attr($formatted_xp); ?>" class="regular-text" pattern="[0-9\s]*" placeholder="0">
                        <p class="description"><?php _e('Your total XP in Pokémon GO', 'poke-hub'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="country"><?php _e('Country', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="country" id="country" value="<?php echo esc_attr($profile['country']); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('Country code (e.g., FR, US, GB). This will be synchronized with Ultimate Member if available.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="pokemon_go_username"><?php _e('Pokémon GO Username', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="pokemon_go_username" id="pokemon_go_username" value="<?php echo esc_attr($profile['pokemon_go_username']); ?>" class="regular-text">
                        <p class="description"><?php _e('Your in-game username', 'poke-hub'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="scatterbug_pattern"><?php _e('Scatterbug Pattern', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <select name="scatterbug_pattern" id="scatterbug_pattern">
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
                        <label><?php _e('Reasons', 'poke-hub'); ?></label>
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
        </form>
    </div>
    <?php
}

/**
 * Render users list with their profiles
 */
function poke_hub_render_user_profiles_list() {
    // Pagination
    $per_page = 20;
    $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $offset = ($paged - 1) * $per_page;

    // Search
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    $args = [
        'number' => $per_page,
        'offset' => $offset,
        'search' => $search ? '*' . $search . '*' : '',
        'search_columns' => ['user_login', 'user_nicename', 'user_email', 'display_name'],
    ];

    $user_query = new WP_User_Query($args);
    $users = $user_query->get_results();
    $total_users = $user_query->get_total();

    $teams = poke_hub_get_teams();
    ?>
    <div class="wrap">
        <h1><?php _e('User Profiles', 'poke-hub'); ?></h1>

        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" action="">
                    <input type="hidden" name="page" value="poke-hub-user-profiles">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search users...', 'poke-hub'); ?>">
                    <?php submit_button(__('Search', 'poke-hub'), 'secondary', false, false); ?>
                </form>
            </div>
            <div class="tablenav-pages">
                <?php
                $pagination_args = [
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => ceil($total_users / $per_page),
                    'current'   => $paged,
                ];
                echo paginate_links($pagination_args);
                ?>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('User', 'poke-hub'); ?></th>
                    <th scope="col"><?php _e('Team', 'poke-hub'); ?></th>
                    <th scope="col"><?php _e('Friend Code', 'poke-hub'); ?></th>
                    <th scope="col"><?php _e('XP', 'poke-hub'); ?></th>
                    <th scope="col"><?php _e('Country', 'poke-hub'); ?></th>
                    <th scope="col"><?php _e('Pokémon GO Username', 'poke-hub'); ?></th>
                    <th scope="col"><?php _e('Scatterbug Pattern', 'poke-hub'); ?></th>
                    <th scope="col"><?php _e('Actions', 'poke-hub'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)) : ?>
                    <tr>
                        <td colspan="8"><?php _e('No users found.', 'poke-hub'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($users as $user) : ?>
                        <?php
                        $profile = poke_hub_get_user_profile($user->ID);
                        $team_label = !empty($profile['team']) ? ($teams[$profile['team']] ?? $profile['team']) : '—';
                        $empty_dash = '—';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                <small><?php echo esc_html($user->user_email); ?></small>
                            </td>
                            <td><?php echo esc_html($team_label); ?></td>
                            <td><?php 
                                if (!empty($profile['friend_code'])) {
                                    $formatted_code = function_exists('poke_hub_format_friend_code') 
                                        ? poke_hub_format_friend_code($profile['friend_code']) 
                                        : $profile['friend_code'];
                                    echo esc_html($formatted_code);
                                } else {
                                    echo esc_html($empty_dash);
                                }
                            ?></td>
                            <td><?php 
                                if (!empty($profile['xp']) || $profile['xp'] === '0' || $profile['xp'] === 0) {
                                    $formatted_xp = function_exists('poke_hub_format_xp') 
                                        ? poke_hub_format_xp($profile['xp']) 
                                        : number_format($profile['xp'], 0, ',', ' ');
                                    echo esc_html($formatted_xp);
                                } else {
                                    echo esc_html($empty_dash);
                                }
                            ?></td>
                            <td><?php echo esc_html($profile['country'] ?: $empty_dash); ?></td>
                            <td><?php echo esc_html($profile['pokemon_go_username'] ?: $empty_dash); ?></td>
                            <td><?php echo esc_html($profile['scatterbug_pattern'] ?: $empty_dash); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'user_id' => $user->ID])); ?>" class="button button-small">
                                    <?php _e('Edit', 'poke-hub'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php echo paginate_links($pagination_args); ?>
            </div>
        </div>
    </div>
    <?php
}

