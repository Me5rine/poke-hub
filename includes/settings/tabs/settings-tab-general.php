<?php
// File: /includes/settings/tabs/settings-tab-general.php

if (!defined('ABSPATH')) exit;

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

// Détection des plugins externes éventuellement nécessaires
$is_me5rine_lab_active = is_plugin_active('me5rine-lab/me5rine-lab.php');
$is_offload_media_active = (
    is_plugin_active('amazon-s3-and-cloudfront/wordpress-s3.php') ||
    is_plugin_active('amazon-s3-and-cloudfront/amazon-s3-and-cloudfront.php')
);

// Récupération des modules actifs Poké HUB
$active_modules = get_option('poke_hub_active_modules', []);
if (!is_array($active_modules)) {
    $active_modules = [];
}

/**
 * Liste des modules disponibles dans Poké HUB
 */
$available_modules = array(
    'events'        => __('Events', 'poke-hub'),
    'bonus'         => __('Bonus', 'poke-hub'),
    'pokemon'       => __('Pokémon', 'poke-hub'),
    'user-profiles' => __('User Profiles', 'poke-hub'),
    'games'         => __('Games', 'poke-hub'),
);

// Option : suppression des données à la désinstallation
$delete_data = get_option('poke_hub_delete_data_on_uninstall', false);

// Restriction éventuelle du cleanup
$can_manage_cleanup = true;
?>

<form method="post" action="options.php">
    <?php
    settings_fields('poke_hub_settings');
    do_settings_sections('poke-hub');
    ?>

    <h2><?php _e('Active Modules', 'poke-hub'); ?></h2>
    <p><?php _e('Select the modules you want to activate.', 'poke-hub'); ?></p>

    <table class="form-table available-modules-table">
        <tr valign="top">
            <th scope="row"><?php _e('Available Modules', 'poke-hub'); ?>:</th>
            <td>
                <?php
                foreach ($available_modules as $module_key => $module_label) {
                    $disabled = '';
                    $message  = '';

                    // Dépendance : Events → Me5rine LAB
                    if ($module_key === 'events' && !$is_me5rine_lab_active) {
                        $disabled = 'disabled';
                        $message  = ' <em>(' . __('Requires Me5rine LAB (events source)', 'poke-hub') . ')</em>';
                    }

                    // Dépendance : User Profiles → Me5rine LAB
                    if ($module_key === 'user-profiles' && !$is_me5rine_lab_active) {
                        $disabled = 'disabled';
                        $message  = ' <em>(' . __('Requires Me5rine LAB (subscription_accounts table)', 'poke-hub') . ')</em>';
                    }

                    // Dépendance : Games → Pokémon
                    if ($module_key === 'games' && !in_array('pokemon', $active_modules, true)) {
                        $disabled = 'disabled';
                        $message  = ' <em>(' . __('Requires Pokémon module', 'poke-hub') . ')</em>';
                    }

                    $checked = in_array($module_key, $active_modules, true) ? 'checked="checked"' : '';

                    echo '<label>';
                    echo '<input type="checkbox" name="poke_hub_active_modules[]" value="' . esc_attr($module_key) . '" ' . $checked . ' ' . $disabled . ' />';
                    echo '<span> ' . esc_html($module_label) . $message . '</span>';
                    echo '</label><br>';
                }
                ?>
            </td>
        </tr>
    </table>

    <h2><?php _e('Plugin Cleanup', 'poke-hub'); ?></h2>
    <p><?php _e('Choose whether to delete all plugin data when uninstalling.', 'poke-hub'); ?></p>

    <?php if ($can_manage_cleanup): ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Delete data on uninstall', 'poke-hub'); ?></th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="poke_hub_delete_data_on_uninstall"
                               value="1"
                            <?php checked((bool) $delete_data, true); ?> />
                        <?php _e('Delete all plugin data when the plugin is deleted from WordPress.', 'poke-hub'); ?>
                    </label>
                </td>
            </tr>
        </table>
    <?php else: ?>
        <div class="notice notice-info inline">
            <p><?php _e('Data deletion on uninstall is only available on the main site.', 'poke-hub'); ?></p>
        </div>
    <?php endif; ?>

    <h2><?php _e('Module Settings', 'poke-hub'); ?></h2>
    <p><?php _e('Configure settings for individual modules. These settings apply when modules are activated.', 'poke-hub'); ?></p>

    <?php
    $auto_create_pages = get_option('poke_hub_user_profiles_auto_create_pages', true);
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">
                <label for="poke_hub_user_profiles_auto_create_pages">
                    <?php _e('User Profiles: Auto-create pages', 'poke-hub'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox"
                           name="poke_hub_user_profiles_auto_create_pages"
                           id="poke_hub_user_profiles_auto_create_pages"
                           value="1"
                        <?php checked((bool) $auto_create_pages, true); ?> />
                    <?php _e('Automatically create "Friend Codes" and "Vivillon Patterns" pages when the User Profiles module is activated.', 'poke-hub'); ?>
                </label>
                <p class="description">
                    <?php _e('If disabled, you will need to manually create pages with the shortcodes [poke_hub_friend_codes] and [poke_hub_vivillon] after activating the module.', 'poke-hub'); ?>
                    <?php if (!in_array('user-profiles', $active_modules, true)): ?>
                        <br><em><?php _e('Note: The User Profiles module is not currently activated.', 'poke-hub'); ?></em>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="poke_hub_games_auto_create_pages">
                    <?php _e('Games: Auto-create pages', 'poke-hub'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox"
                           name="poke_hub_games_auto_create_pages"
                           id="poke_hub_games_auto_create_pages"
                           value="1"
                        <?php checked((bool) get_option('poke_hub_games_auto_create_pages', true), true); ?> />
                    <?php _e('Automatically create "Pokedle" page when the Games module is activated.', 'poke-hub'); ?>
                </label>
                <p class="description">
                    <?php _e('If disabled, you will need to manually create a page with the shortcode [pokedle] after activating the module.', 'poke-hub'); ?>
                    <?php if (!in_array('games', $active_modules, true)): ?>
                        <br><em><?php _e('Note: The Games module is not currently activated.', 'poke-hub'); ?></em>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="poke_hub_games_dev_mode">
                    <?php _e('Games: Development mode', 'poke-hub'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox"
                           name="poke_hub_games_dev_mode"
                           id="poke_hub_games_dev_mode"
                           value="1"
                        <?php checked((bool) get_option('poke_hub_games_dev_mode', false), true); ?> />
                    <?php _e('Enable development mode for games (Pokémon changes on each page refresh, scores are not checked).', 'poke-hub'); ?>
                </label>
                <p class="description">
                    <?php _e('⚠️ Development mode: The daily Pokémon will change on every page refresh and previous scores will be ignored. Use this only for testing!', 'poke-hub'); ?>
                    <?php if (!in_array('games', $active_modules, true)): ?>
                        <br><em><?php _e('Note: The Games module is not currently activated.', 'poke-hub'); ?></em>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
    </table>

    <?php submit_button(); ?>
</form>
