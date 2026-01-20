<?php
// modules/user-profiles/admin/user-profiles-admin.php

if (!defined('ABSPATH')) {
    exit;
}

// Inclure la classe WP_List_Table
require_once dirname(__FILE__) . '/class-user-profiles-list-table.php';

/**
 * User profiles admin page for Pokémon GO
 */
function poke_hub_user_profiles_admin_ui() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to access this page.', 'poke-hub'));
    }

    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    $profile_id = isset($_GET['profile_id']) ? (int) $_GET['profile_id'] : 0;

    // Handle individual profile deletion
    if ($action === 'delete' && $profile_id > 0) {
        // Verify nonce
        if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_profile_' . $profile_id)) {
            global $wpdb;
            $table_name = pokehub_get_table('user_profiles');
            
            if (!empty($table_name)) {
                $wpdb->delete(
                    $table_name,
                    ['id' => $profile_id],
                    ['%d']
                );
                
                // Redirect with success message
                $redirect_args = [
                    'page' => 'poke-hub-user-profiles',
                    'deleted' => 1,
                ];
                
                // Preserve filters
                $preserve_params = ['filter_team', 'filter_scatterbug_pattern', 's', 'orderby', 'order'];
                foreach ($preserve_params as $param) {
                    if (isset($_GET[$param]) && $_GET[$param] !== '') {
                        $redirect_args[$param] = sanitize_text_field($_GET[$param]);
                    }
                }
                
                wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
                exit;
            }
        } else {
            wp_die(__('Security check failed.', 'poke-hub'));
        }
    }

    // Handle form submission
    $admin_error_message = '';
    if (isset($_POST['poke_hub_save_profile']) && wp_verify_nonce($_POST['poke_hub_profile_nonce'], 'poke_hub_save_profile')) {
        $user_id_to_save = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $profile_id_to_save = isset($_POST['profile_id']) ? (int) $_POST['profile_id'] : 0;
        
        // Allow saving by profile_id even if user_id is 0 (for anonymous/discord profiles)
        if ($profile_id_to_save > 0 || $user_id_to_save > 0) {
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
                
                // If profile_id is provided, include it in profile array for update by ID
                if ($profile_id_to_save > 0) {
                    $profile['id'] = $profile_id_to_save;
                    // Use user_id 0 or null if profile_id is used (will be handled by save function)
                    $user_id_to_save = $user_id_to_save > 0 ? $user_id_to_save : null;
                }

                poke_hub_save_user_profile($user_id_to_save, $profile);
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Pokémon GO profile updated successfully', 'poke-hub') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($admin_error_message) . '</p></div>';
            }
        }
    }

    // Edit user view - support both user_id and profile_id
    if ($action === 'edit') {
        if ($profile_id > 0) {
            if (function_exists('poke_hub_render_user_profile_form_by_id')) {
                poke_hub_render_user_profile_form_by_id($profile_id);
            } else {
                wp_die(__('Form functions not loaded.', 'poke-hub'));
            }
            return;
        } elseif ($user_id > 0) {
            if (function_exists('poke_hub_render_user_profile_form')) {
                poke_hub_render_user_profile_form($user_id);
            } else {
                wp_die(__('Form functions not loaded.', 'poke-hub'));
            }
            return;
        }
    }

    // Users list view
    poke_hub_render_user_profiles_list();
}

/**
 * Render users list with their profiles using WP_List_Table
 */
function poke_hub_render_user_profiles_list() {
    $list_table = new PokeHub_User_Profiles_List_Table();
    $list_table->prepare_items();
    
    // Préserver les paramètres de filtrage dans l'URL
    $base_url = admin_url('admin.php?page=poke-hub-user-profiles');
    $preserve_params = ['filter_team', 'filter_scatterbug_pattern', 's', 'orderby', 'order'];
    foreach ($preserve_params as $param) {
        if (isset($_GET[$param]) && $_GET[$param] !== '') {
            $base_url = add_query_arg($param, sanitize_text_field($_GET[$param]), $base_url);
        }
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('User Profiles', 'poke-hub'); ?></h1>
        <hr class="wp-header-end">

        <?php if (isset($_GET['deleted'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    $deleted_count = (int) $_GET['deleted'];
                    printf(
                        _n(
                            '%d profile deleted successfully.',
                            '%d profiles deleted successfully.',
                            $deleted_count,
                            'poke-hub'
                        ),
                        $deleted_count
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="poke-hub-user-profiles">
            
            <?php
            // Préserver les filtres dans le formulaire de recherche
            if (isset($_GET['filter_team'])) {
                echo '<input type="hidden" name="filter_team" value="' . esc_attr($_GET['filter_team']) . '">';
            }
            if (isset($_GET['filter_scatterbug_pattern'])) {
                echo '<input type="hidden" name="filter_scatterbug_pattern" value="' . esc_attr($_GET['filter_scatterbug_pattern']) . '">';
            }
            if (isset($_GET['orderby'])) {
                echo '<input type="hidden" name="orderby" value="' . esc_attr($_GET['orderby']) . '">';
            }
            if (isset($_GET['order'])) {
                echo '<input type="hidden" name="order" value="' . esc_attr($_GET['order']) . '">';
            }
            
            $list_table->search_box(__('Search profiles', 'poke-hub'), 'user_profile');
            $list_table->display();
            ?>
        </form>
    </div>
    <?php
}

