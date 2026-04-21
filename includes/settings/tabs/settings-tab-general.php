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

// Liste des modules : source unique dans includes/settings/settings-modules.php (poke_hub_get_modules_config).
$modules_config = function_exists('poke_hub_get_modules_config') ? poke_hub_get_modules_config() : [];

// Option : suppression des données à la désinstallation
$delete_data = get_option('poke_hub_delete_data_on_uninstall', false);

// Sous-menu « Outils temporaires » dans l’admin Poké HUB
$temporary_tools_enabled = get_option('poke_hub_temporary_tools_enabled', true);

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
                foreach ($modules_config as $module_key => $module_data) {
                    $module_label = isset($module_data['label']) ? $module_data['label'] : $module_key;
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

                    // Dépendance : Eggs → Pokémon (types d'œufs dans l'admin Pokémon)
                    if ($module_key === 'eggs' && !in_array('pokemon', $active_modules, true)) {
                        $disabled = 'disabled';
                        $message  = ' <em>(' . __('Requires Pokémon module (egg types)', 'poke-hub') . ')</em>';
                    }

                    // Dépendance : Collections → Pokémon (pool de Pokémon)
                    if ($module_key === 'collections' && !in_array('pokemon', $active_modules, true)) {
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

    <h2><?php _e('Temporary tools (admin)', 'poke-hub'); ?></h2>
    <p><?php _e('The Poké HUB submenu « Temporary tools » hosts one-off imports (Pokekalos dates, Fandom recurring events, etc.). Disable it when you do not need these screens.', 'poke-hub'); ?></p>
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><?php _e('Show Temporary tools', 'poke-hub'); ?></th>
            <td>
                <input type="hidden" name="poke_hub_temporary_tools_enabled" value="0" />
                <label>
                    <input type="checkbox"
                           name="poke_hub_temporary_tools_enabled"
                           value="1"
                        <?php checked((bool) $temporary_tools_enabled, true); ?> />
                    <?php _e('Enable the « Temporary tools » submenu under Poké HUB.', 'poke-hub'); ?>
                </label>
            </td>
        </tr>
    </table>

    <h2><?php _e('Module Settings', 'poke-hub'); ?></h2>
    <p><?php _e('Configure settings for individual modules. These settings apply when modules are activated.', 'poke-hub'); ?></p>

    <?php
    $auto_create_pages = get_option('poke_hub_user_profiles_auto_create_pages', true);
    $friend_code_report_threshold = (int) get_option('poke_hub_friend_code_report_threshold', 3);
    if ($friend_code_report_threshold < 1) {
        $friend_code_report_threshold = 1;
    } elseif ($friend_code_report_threshold > 20) {
        $friend_code_report_threshold = 20;
    }
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
                <label for="poke_hub_friend_code_report_threshold">
                    <?php _e('User Profiles: Obsolete code report threshold', 'poke-hub'); ?>
                </label>
            </th>
            <td>
                <input type="number"
                       name="poke_hub_friend_code_report_threshold"
                       id="poke_hub_friend_code_report_threshold"
                       min="1"
                       max="20"
                       step="1"
                       value="<?php echo esc_attr($friend_code_report_threshold); ?>" />
                <p class="description">
                    <?php _e('Number of obsolete reports required before a friend code is hidden from public lists. The code stays in database.', 'poke-hub'); ?>
                    <?php if (!in_array('user-profiles', $active_modules, true)): ?>
                        <br><em><?php _e('Note: The User Profiles module is not currently activated.', 'poke-hub'); ?></em>
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
        <tr valign="top">
            <th scope="row">
                <label for="poke_hub_collections_auto_create_pages">
                    <?php _e('Collections: Auto-create page', 'poke-hub'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox"
                           name="poke_hub_collections_auto_create_pages"
                           id="poke_hub_collections_auto_create_pages"
                           value="1"
                        <?php checked((bool) get_option('poke_hub_collections_auto_create_pages', true), true); ?> />
                    <?php _e('Automatically create the "Collections Pokémon GO" page when the Collections module is activated.', 'poke-hub'); ?>
                </label>
                <p class="description">
                    <?php _e('If disabled, create a page with the shortcode [poke_hub_collections_page].', 'poke-hub'); ?>
                    <?php if (!in_array('collections', $active_modules, true)): ?>
                        <br><em><?php _e('Note: The Collections module is not currently activated.', 'poke-hub'); ?></em>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
    </table>

    <?php submit_button(); ?>
</form>
