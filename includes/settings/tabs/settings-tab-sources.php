<?php
// File: /includes/settings/tabs/settings-tab-sources.php

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    return;
}

global $wpdb;

// 🔎 Vérifie quels modules sont actifs
$active_modules = get_option('poke_hub_active_modules', []);
if (!is_array($active_modules)) {
    $active_modules = [];
}

$events_enabled  = in_array('events', $active_modules, true);
$pokemon_enabled = in_array('pokemon', $active_modules, true);

$messages = [];

// === Options actuelles ===

// Events source (JV Actu) - seulement si module events actif
$events_prefix      = get_option('poke_hub_events_remote_prefix', '');
$event_types_prefix = get_option('poke_hub_event_types_remote_prefix', '');

// Assets bucket base URL - URL commune à toutes les images
$assets_bucket_base_url = get_option('poke_hub_assets_bucket_base_url', 'https://pokemon.me5rine-lab.com/');

// Assets paths - chemins spécifiques pour chaque type d'asset
$assets_path_backgrounds = get_option('poke_hub_assets_path_backgrounds', '/pokemon-go/backgrounds/');
$assets_path_bonus = get_option('poke_hub_assets_path_bonus', '/pokemon-go/bonus/');
$assets_path_candies = get_option('poke_hub_assets_path_candies', '/pokemon-go/candies/');
$assets_path_collection_challenges = get_option('poke_hub_assets_path_collection_challenges', '/pokemon-go/collection-challenges/');
$assets_path_eggs = get_option('poke_hub_assets_path_eggs', '/pokemon-go/eggs/');
$assets_path_habitats = get_option('poke_hub_assets_path_habitats', '/pokemon-go/habitats/');
$assets_path_icons = get_option('poke_hub_assets_path_icons', '/pokemon-go/icons/');
$assets_path_mega_energies = get_option('poke_hub_assets_path_mega_energies', '/pokemon-go/mega-energies/');
$assets_path_objects = get_option('poke_hub_assets_path_objects', '/pokemon-go/objects/');
$assets_path_pokemon = get_option('poke_hub_assets_path_pokemon', '/pokemon-go/pokemon/');
$assets_path_raids = get_option('poke_hub_assets_path_raids', '/pokemon-go/raids/');
$assets_path_teams = get_option('poke_hub_assets_path_teams', '/pokemon-go/teams/');
$assets_path_types = get_option('poke_hub_assets_path_types', '/pokemon-go/types/');
$assets_path_vivillon = get_option('poke_hub_assets_path_vivillon', '/pokemon-go/vivillon/');
$assets_path_weathers = get_option('poke_hub_assets_path_weathers', '/pokemon-go/weathers/');

// Pokémon images base URL - toujours disponible (rétrocompatibilité)
$pokemon_assets_base_url     = get_option('poke_hub_pokemon_assets_base_url', '');
$pokemon_assets_fallback_url = get_option('poke_hub_pokemon_assets_fallback_base_url', '');

// Pokémon tables prefix - toujours disponible
$pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');

// User Profiles base URL - pour les sites partageant une base de données
$user_profiles_base_url = get_option('poke_hub_user_profiles_base_url', '');

// === Traitement du formulaire dédié à cet onglet ===
if (!empty($_POST['poke_hub_sources_submit'])) {

    check_admin_referer('poke_hub_sources_settings', 'poke_hub_sources_nonce');

    // On re-calcul les flags, au cas où ça ait changé entre temps
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules)) {
        $active_modules = [];
    }

    $events_enabled  = in_array('events', $active_modules, true);
    $pokemon_enabled = in_array('pokemon', $active_modules, true);

    // Events
    if ($events_enabled) {
        if (isset($_POST['poke_hub_events_remote_prefix'])) {
            $events_prefix = sanitize_text_field(wp_unslash($_POST['poke_hub_events_remote_prefix']));
            update_option('poke_hub_events_remote_prefix', $events_prefix);
        }

        if (isset($_POST['poke_hub_event_types_remote_prefix'])) {
            $event_types_prefix = sanitize_text_field(wp_unslash($_POST['poke_hub_event_types_remote_prefix']));
            update_option('poke_hub_event_types_remote_prefix', $event_types_prefix);
        }
    }

    // Assets bucket base URL
    if (isset($_POST['poke_hub_assets_bucket_base_url'])) {
        $assets_bucket_base_url = esc_url_raw(wp_unslash($_POST['poke_hub_assets_bucket_base_url']));
        update_option('poke_hub_assets_bucket_base_url', $assets_bucket_base_url);
    }
    
    // Assets paths
    if (isset($_POST['poke_hub_assets_path_backgrounds'])) {
        update_option('poke_hub_assets_path_backgrounds', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_backgrounds'])));
    }
    if (isset($_POST['poke_hub_assets_path_bonus'])) {
        update_option('poke_hub_assets_path_bonus', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_bonus'])));
    }
    if (isset($_POST['poke_hub_assets_path_candies'])) {
        update_option('poke_hub_assets_path_candies', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_candies'])));
    }
    if (isset($_POST['poke_hub_assets_path_collection_challenges'])) {
        update_option('poke_hub_assets_path_collection_challenges', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_collection_challenges'])));
    }
    if (isset($_POST['poke_hub_assets_path_eggs'])) {
        update_option('poke_hub_assets_path_eggs', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_eggs'])));
    }
    if (isset($_POST['poke_hub_assets_path_habitats'])) {
        update_option('poke_hub_assets_path_habitats', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_habitats'])));
    }
    if (isset($_POST['poke_hub_assets_path_icons'])) {
        update_option('poke_hub_assets_path_icons', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_icons'])));
    }
    if (isset($_POST['poke_hub_assets_path_mega_energies'])) {
        update_option('poke_hub_assets_path_mega_energies', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_mega_energies'])));
    }
    if (isset($_POST['poke_hub_assets_path_objects'])) {
        update_option('poke_hub_assets_path_objects', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_objects'])));
    }
    if (isset($_POST['poke_hub_assets_path_pokemon'])) {
        update_option('poke_hub_assets_path_pokemon', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_pokemon'])));
    }
    if (isset($_POST['poke_hub_assets_path_raids'])) {
        update_option('poke_hub_assets_path_raids', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_raids'])));
    }
    if (isset($_POST['poke_hub_assets_path_teams'])) {
        update_option('poke_hub_assets_path_teams', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_teams'])));
    }
    if (isset($_POST['poke_hub_assets_path_types'])) {
        update_option('poke_hub_assets_path_types', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_types'])));
    }
    if (isset($_POST['poke_hub_assets_path_vivillon'])) {
        update_option('poke_hub_assets_path_vivillon', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_vivillon'])));
    }
    if (isset($_POST['poke_hub_assets_path_weathers'])) {
        update_option('poke_hub_assets_path_weathers', sanitize_text_field(wp_unslash($_POST['poke_hub_assets_path_weathers'])));
    }

    // Pokémon - toujours disponible (sources image et préfixe) - rétrocompatibilité
    if (isset($_POST['poke_hub_pokemon_assets_base_url'])) {
        $pokemon_assets_base_url = esc_url_raw(wp_unslash($_POST['poke_hub_pokemon_assets_base_url']));
        update_option('poke_hub_pokemon_assets_base_url', $pokemon_assets_base_url);
    }

    if (isset($_POST['poke_hub_pokemon_assets_fallback_base_url'])) {
        $pokemon_assets_fallback_url = esc_url_raw(wp_unslash($_POST['poke_hub_pokemon_assets_fallback_base_url']));
        update_option('poke_hub_pokemon_assets_fallback_base_url', $pokemon_assets_fallback_url);
    }

    // Préfixe source des tables Pokémon
    if (isset($_POST['poke_hub_pokemon_remote_prefix'])) {
        $pokemon_remote_prefix = sanitize_text_field(wp_unslash($_POST['poke_hub_pokemon_remote_prefix']));
        update_option('poke_hub_pokemon_remote_prefix', $pokemon_remote_prefix);
        $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    }

    // User Profiles base URL
    if (isset($_POST['poke_hub_user_profiles_base_url'])) {
        $user_profiles_base_url = esc_url_raw(wp_unslash($_POST['poke_hub_user_profiles_base_url']));
        update_option('poke_hub_user_profiles_base_url', $user_profiles_base_url);
    }

    $messages[] = [
        'type' => 'success',
        'text' => __('Sources settings saved.', 'poke-hub'),
    ];
}

// Affichage des notices locales à l’onglet
foreach ($messages as $msg) {
    $class = 'notice';
    if ($msg['type'] === 'success') {
        $class .= ' notice-success';
    } elseif ($msg['type'] === 'error') {
        $class .= ' notice-error';
    } else {
        $class .= ' notice-info';
    }

    printf(
        '<div class="%1$s is-dismissible"><p>%2$s</p></div>',
        esc_attr($class),
        esc_html($msg['text'])
    );
}

?>
<form method="post" action="">
    <?php wp_nonce_field('poke_hub_sources_settings', 'poke_hub_sources_nonce'); ?>
    <input type="hidden" name="poke_hub_sources_submit" value="1" />

    <?php if ($events_enabled): ?>
        <h2><?php _e('Events Source (JV Actu)', 'poke-hub'); ?></h2>
        <p><?php _e('Events and event types are stored on another site (JV Actu). Set here the table prefixes used on that site.', 'poke-hub'); ?></p>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('JV Actu table prefix (events)', 'poke-hub'); ?></th>
                <td>
                    <input type="text"
                           name="poke_hub_events_remote_prefix"
                           value="<?php echo esc_attr($events_prefix); ?>"
                           class="regular-text"
                           placeholder="jvactu_">
                    <p class="description">
                        <?php _e('Used for posts, postmeta and special events tables (e.g. "jvactu_posts", "jvactu_postmeta", "jvactu_pokehubevents"...).', 'poke-hub'); ?>
                    </p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('JV Actu table prefix (event types)', 'poke-hub'); ?></th>
                <td>
                    <input type="text"
                           name="poke_hub_event_types_remote_prefix"
                           value="<?php echo esc_attr($event_types_prefix); ?>"
                           class="regular-text"
                           placeholder="jvactu_">
                    <p class="description">
                        <?php _e('Used for "event_type" taxonomy tables (terms, termmeta, term_taxonomy, term_relationships). Leave empty to reuse the events prefix above.', 'poke-hub'); ?>
                    </p>
                </td>
            </tr>
        </table>
    <?php endif; ?>

    <h2><?php _e('Pokémon Sources', 'poke-hub'); ?></h2>
    <p><?php _e('Configure the sources for Pokémon data and images. These settings are available even when the Pokémon module is not active.', 'poke-hub'); ?></p>

    <table class="form-table">
        <tr valign="top">
            <th scope="row"><?php _e('Pokémon table prefix (remote)', 'poke-hub'); ?></th>
            <td>
                <input type="text"
                       name="poke_hub_pokemon_remote_prefix"
                       value="<?php echo esc_attr($pokemon_remote_prefix); ?>"
                       class="regular-text"
                       placeholder="<?php echo esc_attr($wpdb->prefix); ?>">
                <p class="description">
                    <?php _e('Used for Pokémon tables when the plugin is in remote mode to fetch Pokémon data from another site. Leave empty to use local prefix.', 'poke-hub'); ?>
                </p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Assets bucket base URL', 'poke-hub'); ?></th>
            <td>
                <input type="url"
                    name="poke_hub_assets_bucket_base_url"
                    value="<?php echo esc_attr($assets_bucket_base_url); ?>"
                    class="regular-text"
                    placeholder="https://pokemon.me5rine-lab.com/">
                <p class="description">
                    <?php _e('Base URL for all assets (common bucket). Example: https://pokemon.me5rine-lab.com/', 'poke-hub'); ?>
                </p>
            </td>
        </tr>
    </table>

    <h3><?php _e('Image Sources', 'poke-hub'); ?></h3>
    <p class="description"><?php _e('Configure the paths for different asset types. SVG files are used for: types, weathers, icons, bonus, collection-challenges. PNG files (slug.png) are used for all others.', 'poke-hub'); ?></p>
    
    <table class="form-table">
        <tbody>
            <tr>
                <td style="width: 50%; padding-right: 15px; vertical-align: top;">
                    <table style="width: 100%;">
                        <tr valign="top">
                            <th scope="row" style="width: 40%;"><?php _e('Backgrounds', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_backgrounds"
                                    value="<?php echo esc_attr($assets_path_backgrounds); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/backgrounds/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('PNG: slug.png', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Bonus', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_bonus"
                                    value="<?php echo esc_attr($assets_path_bonus); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/bonus/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('SVG: slug.svg', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Candies', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_candies"
                                    value="<?php echo esc_attr($assets_path_candies); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/candies/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('PNG: slug.png', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Collection Challenges', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_collection_challenges"
                                    value="<?php echo esc_attr($assets_path_collection_challenges); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/collection-challenges/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('SVG: shadow.svg, evolution.svg, etc.', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Eggs', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_eggs"
                                    value="<?php echo esc_attr($assets_path_eggs); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/eggs/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('PNG: slug.png', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Habitats', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_habitats"
                                    value="<?php echo esc_attr($assets_path_habitats); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/habitats/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('PNG: slug.png', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Icons', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_icons"
                                    value="<?php echo esc_attr($assets_path_icons); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/icons/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('SVG: stardust.svg, xp.svg, etc.', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Mega Energies', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_mega_energies"
                                    value="<?php echo esc_attr($assets_path_mega_energies); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/mega-energies/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('PNG: slug.png', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 50%; padding-left: 15px; vertical-align: top;">
                    <table style="width: 100%;">
                        <tr valign="top">
                            <th scope="row" style="width: 40%;"><?php _e('Objects', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_objects"
                                    value="<?php echo esc_attr($assets_path_objects); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/objects/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('PNG: slug.png', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Pokémon', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_pokemon"
                                    value="<?php echo esc_attr($assets_path_pokemon); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/pokemon/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('PNG: slug.png', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Raids', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_raids"
                                    value="<?php echo esc_attr($assets_path_raids); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/raids/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('PNG: slug.png', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Teams', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_teams"
                                    value="<?php echo esc_attr($assets_path_teams); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/teams/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('PNG: slug.png', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Types', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_types"
                                    value="<?php echo esc_attr($assets_path_types); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/types/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('SVG: slug.svg', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Vivillon', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_vivillon"
                                    value="<?php echo esc_attr($assets_path_vivillon); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/vivillon/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('PNG: slug.png (patterns)', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Weathers', 'poke-hub'); ?></th>
                            <td>
                                <input type="text"
                                    name="poke_hub_assets_path_weathers"
                                    value="<?php echo esc_attr($assets_path_weathers); ?>"
                                    class="regular-text"
                                    placeholder="/pokemon-go/weathers/">
                                <p class="description" style="margin-top: 4px; font-size: 11px;">
                                    <?php _e('SVG: slug.svg', 'poke-hub'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <h2><?php _e('User Profiles Source', 'poke-hub'); ?></h2>
    <p><?php _e('Configure the base URL for user profile links. Useful when sites share a database.', 'poke-hub'); ?></p>

    <table class="form-table">
        <tr valign="top">
            <th scope="row">
                <label for="poke_hub_user_profiles_base_url">
                    <?php _e('Base URL for profile links', 'poke-hub'); ?>
                </label>
            </th>
            <td>
                <input type="url"
                       name="poke_hub_user_profiles_base_url"
                       id="poke_hub_user_profiles_base_url"
                       value="<?php echo esc_attr($user_profiles_base_url); ?>"
                       class="regular-text"
                       placeholder="<?php echo esc_attr(home_url()); ?>">
                <p class="description">
                    <?php _e('Base URL to use for user profile links. Leave empty to use the current site URL. This setting is useful when multiple sites share the same database and user tables.', 'poke-hub'); ?>
                    <br>
                    <strong><?php _e('Current site URL:', 'poke-hub'); ?></strong> <code><?php echo esc_html(home_url()); ?></code>
                </p>
            </td>
        </tr>
    </table>

    <?php submit_button(); ?>
</form>
