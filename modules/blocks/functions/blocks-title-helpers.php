<?php
/**
 * Titres des blocs Gutenberg : icône SVG optionnelle à gauche (thème / enfant via filtre).
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pokehub_block_title_icon_svg_allowed_tags')) {
    /**
     * Balises / attributs autorisés pour un SVG décoratif inline (filtre pokehub_block_title_icon_svg).
     *
     * @return array<string, array<string, bool>>
     */
    function pokehub_block_title_icon_svg_allowed_tags(): array {
        $bool = true;

        return [
            'svg'      => [
                'xmlns'           => $bool,
                'viewbox'         => $bool,
                'width'           => $bool,
                'height'          => $bool,
                'class'           => $bool,
                'fill'            => $bool,
                'stroke'          => $bool,
                'stroke-width'    => $bool,
                'stroke-linecap'  => $bool,
                'stroke-linejoin' => $bool,
                'stroke-miterlimit' => $bool,
                'aria-hidden'     => $bool,
                'role'            => $bool,
                'focusable'       => $bool,
                'preserveaspectratio' => $bool,
            ],
            'g'        => ['class' => $bool, 'fill' => $bool, 'stroke' => $bool, 'transform' => $bool],
            'path'     => ['d' => $bool, 'fill' => $bool, 'stroke' => $bool, 'class' => $bool, 'opacity' => $bool],
            'circle'   => ['cx' => $bool, 'cy' => $bool, 'r' => $bool, 'fill' => $bool, 'stroke' => $bool, 'class' => $bool],
            'rect'     => ['x' => $bool, 'y' => $bool, 'width' => $bool, 'height' => $bool, 'rx' => $bool, 'ry' => $bool, 'fill' => $bool, 'stroke' => $bool, 'class' => $bool],
            'line'     => ['x1' => $bool, 'y1' => $bool, 'x2' => $bool, 'y2' => $bool, 'stroke' => $bool, 'class' => $bool],
            'polyline' => ['points' => $bool, 'fill' => $bool, 'stroke' => $bool, 'class' => $bool],
            'polygon'  => ['points' => $bool, 'fill' => $bool, 'stroke' => $bool, 'class' => $bool],
            'title'    => [],
        ];
    }
}

if (!function_exists('pokehub_sanitize_block_title_icon_svg')) {
    /**
     * @param string $svg Markup SVG inline.
     */
    function pokehub_sanitize_block_title_icon_svg(string $svg): string {
        $svg = trim($svg);
        if ($svg === '') {
            return '';
        }
        $allowed = apply_filters('pokehub_block_title_icon_svg_allowed_tags', pokehub_block_title_icon_svg_allowed_tags());

        return wp_kses($svg, is_array($allowed) ? $allowed : pokehub_block_title_icon_svg_allowed_tags());
    }
}

if (!function_exists('pokehub_render_block_title')) {
    /**
     * Affiche le titre standard des blocs (h2.pokehub-block-title), avec icône SVG optionnelle à gauche.
     *
     * Icône : filtre `pokehub_block_title_icon_svg` — retourner une chaîne SVG inline (décoratif, sans scripts).
     * Clé de bloc (ex. `wild-pokemon`, `event-quests`) pour cibler une icône par bloc.
     *
     * @param string $label     Texte du titre (déjà passé par __() / variable ; échappé en HTML).
     * @param string $block_key Identifiant du bloc pour le filtre d’icône.
     * @param array  $args {
     *     @type string $title_class Classes CSS supplémentaires sur le &lt;h2&gt; (ex. pokehub-eggs-block-title).
     * }
     */
    function pokehub_render_block_title(string $label, string $block_key = 'default', array $args = []): string {
        $label = trim((string) $label);
        $block_key = $block_key !== '' ? sanitize_key((string) $block_key) : 'default';

        $svg_raw = apply_filters('pokehub_block_title_icon_svg', '', $block_key, [
            'label' => $label,
        ]);
        $svg_safe = is_string($svg_raw) ? pokehub_sanitize_block_title_icon_svg($svg_raw) : '';

        $extra = isset($args['title_class']) ? trim(preg_replace('/[^a-z0-9_\- ]/i', '', (string) $args['title_class'])) : '';
        $classes = ['pokehub-block-title'];
        if ($svg_safe !== '') {
            $classes[] = 'pokehub-block-title--has-icon';
        }
        if ($extra !== '') {
            foreach (preg_split('/\s+/', $extra, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
                $classes[] = $c;
            }
        }
        $class_attr = implode(' ', array_unique(array_filter($classes)));

        $label_esc = esc_html($label);

        if ($svg_safe === '') {
            return '<h2 class="' . esc_attr($class_attr) . '">' . $label_esc . '</h2>';
        }

        return '<h2 class="' . esc_attr($class_attr) . '">'
            . '<span class="pokehub-block-title-icon" aria-hidden="true">' . $svg_safe . '</span>'
            . '<span class="pokehub-block-title-text">' . $label_esc . '</span>'
            . '</h2>';
    }
}

/*
 * Clés $block_key pour pokehub_render_block_title() / filtre pokehub_block_title_icon_svg :
 * bonus, collection-challenges, day-pokemon-hours, eggs, event-dates, event-quests,
 * go-pass, habitats, new-pokemon-evolutions, special-research, wild-pokemon.
 *
 * Exemple (thème enfant) :
 *
 * add_filter( 'pokehub_block_title_icon_svg', function ( $svg, $block_key ) {
 *     if ( $block_key === 'wild-pokemon' ) {
 *         return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>';
 *     }
 *     return $svg;
 * }, 10, 2 );
 *
 * Taille : variable CSS --pokehub-block-title-icon-box sur le .pokehub-*-block-wrapper (défaut 1.5rem).
 */
