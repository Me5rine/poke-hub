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

    <?php submit_button(); ?>
</form>
