<?php
/**
 * Présentation des icônes de types Pokémon (classes CSS + enregistrement de la feuille de style).
 * S’appuie sur {@see pokehub_render_inline_svg_from_url()} (pokehub-inline-svg.php).
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * URL publique du fichier SVG d’un type Pokémon sur le bucket (réglages Sources).
 *
 * @param string $slug Slug du type
 */
function poke_hub_get_type_icon_url(string $slug): string {
    return poke_hub_get_asset_url('types', $slug, 'svg');
}

/**
 * Icône de type Pokémon : SVG inline, classes UI types (currentColor).
 *
 * @param string $icon_url URL .svg (média ou bucket HTTP(S)).
 * @param array  $args     class, color, aria_hidden (voir pokehub_render_inline_svg_from_url).
 */
function pokehub_render_pokemon_type_icon_html(string $icon_url, array $args = []): string {
    $args = wp_parse_args(
        $args,
        [
            'color'       => '',
            'class'       => '',
            'aria_hidden' => true,
        ]
    );

    $type_classes = trim(
        'pokehub-type-icon pokehub-type-icon--inline-svg pokehub-type-icon--tinted ' . (string) $args['class']
    );

    return pokehub_render_inline_svg_from_url(
        $icon_url,
        [
            'class'       => $type_classes,
            'color'       => (string) $args['color'],
            'aria_hidden' => !empty($args['aria_hidden']),
        ]
    );
}

add_action(
    'init',
    static function (): void {
        if (!defined('POKE_HUB_URL') || !defined('POKE_HUB_VERSION')) {
            return;
        }
        wp_register_style(
            'pokehub-type-icons',
            POKE_HUB_URL . 'assets/css/poke-hub-type-icons.css',
            [],
            POKE_HUB_VERSION
        );
    },
    20
);
