<?php
/**
 * CSS front « packagé » dans assets/css/ : chargé seulement si le filtre est true et si le fichier existe.
 * Sur les sites de prod (thème **Me5rine Lab**), le lot front est porté par le thème enfant
 * (`css/poke-hub/`, `poke-hub-front.css`, `poke-hub-late-overrides.css`) : le thème filtre
 * `poke_hub_load_default_plugin_front_css` à `false` et le déqueue collecte les handles enregistrés ici.
 * Voir : docs/THEME_FRONT_CSS.md.
 *
 * Fichiers typiquement conservés côté plugin : `global-colors.css` (Gutenberg / notices), feuilles **admin**,
 * `poke-hub-type-icons.css` (icônes types ; **admin** : enqueue explicite dans `poke-hub.php`, indépendant du filtre) ;
 * le module Collections peut enfiler en plus `poke-hub-collections-cascade-late.css` (hors de cette liste, voir doc).
 *
 * Filtre : `poke_hub_load_default_plugin_front_css` (défaut : true).
 * Si le thème retourne false : pas d’enqueue `poke_hub_enqueue_bundled_front_style` ; le hook
 * `poke_hub_maybe_dequeue_plugin_front_styles` retire les handles de `poke_hub_get_plugin_front_style_handles()`.
 *
 * Helpers : `poke_hub_enqueue_bundled_front_style`, `poke_hub_register_bundled_front_style`.
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Faux si le thème embarque le lot front (ex. me5rine-lab / css/poke-hub/).
 */
function poke_hub_is_plugin_bundled_front_css_enabled(): bool {
    return (bool) apply_filters('poke_hub_load_default_plugin_front_css', true);
}

/**
 * @param string $filename Nom sous assets/css/ (ex. global-colors.css)
 * @param list<string> $deps
 */
function poke_hub_enqueue_bundled_front_style(string $handle, string $filename, array $deps = []): void {
    if (!poke_hub_is_plugin_bundled_front_css_enabled()) {
        return;
    }
    $filename = ltrim($filename, '/\\');
    $path = POKE_HUB_PATH . 'assets/css/' . $filename;
    if (!is_readable($path)) {
        return;
    }
    wp_enqueue_style(
        $handle,
        POKE_HUB_URL . 'assets/css/' . $filename,
        $deps,
        POKE_HUB_VERSION
    );
}

/**
 * @param list<string> $deps
 * @param string|null  $ver
 */
function poke_hub_register_bundled_front_style(string $handle, string $filename, array $deps = [], $ver = null): void {
    if (!poke_hub_is_plugin_bundled_front_css_enabled()) {
        return;
    }
    $filename = ltrim($filename, '/\\');
    $path = POKE_HUB_PATH . 'assets/css/' . $filename;
    if (!is_readable($path)) {
        return;
    }
    $ver = $ver !== null ? $ver : POKE_HUB_VERSION;
    wp_register_style(
        $handle,
        POKE_HUB_URL . 'assets/css/' . $filename,
        $deps,
        $ver
    );
}

/**
 * Liste des handles `wp_enqueue_style` (front) gérés par le plugin pour les modules publics.
 * Peut être étendue via le filtre `poke_hub_plugin_front_style_handles` si un module custom enqueue un handle.
 *
 * @return list<string>
 */
function poke_hub_get_plugin_front_style_handles(): array {
    $handles = [
        'poke-hub-global-colors',
        'pokehub-type-icons',
        'pokehub-candy-display',
        'pokehub-blocks-front-style',
        'pokehub-new-pokemon-evolutions-front',
        'pokehub-bonus-style',
        'pokehub-collection-challenges-front',
        'pokehub-special-research-front',
        'pokehub-eggs-front',
        'pokehub-go-pass-block-front',
        'pokehub-special-event-single',
        'pokehub-events-style',
        'poke-hub-collections-front',
        'pokehub-games-style',
    ];

    return array_values(array_unique(apply_filters('poke_hub_plugin_front_style_handles', $handles)));
}

/**
 * Désenfile les feuilles du plugin lorsque le thème charge son propre lot CSS.
 */
function poke_hub_maybe_dequeue_plugin_front_styles(): void {
    if (apply_filters('poke_hub_load_default_plugin_front_css', true)) {
        return;
    }
    foreach (poke_hub_get_plugin_front_style_handles() as $handle) {
        wp_dequeue_style($handle);
    }
}
add_action('wp_enqueue_scripts', 'poke_hub_maybe_dequeue_plugin_front_styles', 1000);
add_action('enqueue_block_editor_assets', 'poke_hub_maybe_dequeue_plugin_front_styles', 1000);
