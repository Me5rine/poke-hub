<?php
// File: /includes/settings/tabs/settings-tab-sources.php

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    return;
}

// 🔎 Vérifie quels modules sont actifs
$active_modules = get_option('poke_hub_active_modules', []);
if (!is_array($active_modules)) {
    $active_modules = [];
}

$events_enabled  = in_array('events', $active_modules, true);
$pokemon_enabled = in_array('pokemon', $active_modules, true);

$messages = [];

// Si pour une raison quelconque on arrive ici sans module concerné actif
if (!$events_enabled && !$pokemon_enabled) {
    echo '<div class="notice notice-info"><p>'
        . esc_html__('No source-based modules are currently active. Enable the Events and/or Pokémon modules to configure their sources.', 'poke-hub')
        . '</p></div>';
    return;
}

// === Options actuelles ===

// Events source (JV Actu)
$events_prefix      = get_option('poke_hub_events_remote_prefix', '');
$event_types_prefix = get_option('poke_hub_event_types_remote_prefix', '');

// Pokémon images base URL
$pokemon_assets_base_url     = get_option('poke_hub_pokemon_assets_base_url', '');
$pokemon_assets_fallback_url = get_option('poke_hub_pokemon_assets_fallback_base_url', '');

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

    // Pokémon
    if ($pokemon_enabled) {
        if (isset($_POST['poke_hub_pokemon_assets_base_url'])) {
            $pokemon_assets_base_url = esc_url_raw(wp_unslash($_POST['poke_hub_pokemon_assets_base_url']));
            update_option('poke_hub_pokemon_assets_base_url', $pokemon_assets_base_url);
        }

        if (isset($_POST['poke_hub_pokemon_assets_fallback_base_url'])) {
            $pokemon_assets_fallback_url = esc_url_raw(wp_unslash($_POST['poke_hub_pokemon_assets_fallback_base_url']));
            update_option('poke_hub_pokemon_assets_fallback_base_url', $pokemon_assets_fallback_url);
        }
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

    <?php if ($pokemon_enabled): ?>
        <h2><?php _e('Pokémon Images Source', 'poke-hub'); ?></h2>
        <p><?php _e('Set the base URL used to load Pokémon assets (sprites, artwork, etc.) from your bucket/CDN.', 'poke-hub'); ?></p>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Pokémon assets base URL', 'poke-hub'); ?></th>
                <td>
                    <input type="url"
                        name="poke_hub_pokemon_assets_base_url"
                        value="<?php echo esc_attr($pokemon_assets_base_url); ?>"
                        class="regular-text"
                        placeholder="https://cdn.example.com/pokemon">
                    <p class="description">
                        <?php _e('Example: https://cdn.example.com/pokemon. The helpers will append the slug-based filename (e.g. "pikachu.png", "noibat-headband-male-shiny.png").', 'poke-hub'); ?>
                    </p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('Fallback assets base URL', 'poke-hub'); ?></th>
                <td>
                    <input type="url"
                        name="poke_hub_pokemon_assets_fallback_base_url"
                        value="<?php echo esc_attr($pokemon_assets_fallback_url); ?>"
                        class="regular-text"
                        placeholder="https://backup.example.com/pokemon">
                    <p class="description">
                        <?php _e('Optional. If set, this URL will be used as a fallback source with the same slug-based filenames when the primary source is unavailable.', 'poke-hub'); ?>
                    </p>
                </td>
            </tr>
        </table>
    <?php endif; ?>

    <?php submit_button(); ?>
</form>
