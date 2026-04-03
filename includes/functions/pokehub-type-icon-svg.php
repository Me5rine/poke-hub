<?php
/**
 * Icônes de types Pokémon en SVG inline : teinte via la couleur admin (currentColor).
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Balises / attributs autorisés pour un SVG inline (wp_kses).
 *
 * @return array<string, array<string, bool>>
 */
function pokehub_type_icon_get_kses_allowed_svg(): array {
    $a = [
        'class'               => true,
        'id'                  => true,
        'style'               => true,
        'transform'           => true,
        'xmlns'               => true,
        'xmlns:xlink'         => true,
        'version'             => true,
        'fill'                => true,
        'fill-opacity'        => true,
        'fill-rule'           => true,
        'stroke'              => true,
        'stroke-width'        => true,
        'stroke-linecap'      => true,
        'stroke-linejoin'     => true,
        'stroke-miterlimit'   => true,
        'stroke-opacity'      => true,
        'stroke-dasharray'    => true,
        'stroke-dashoffset'   => true,
        'd'                   => true,
        'cx'                  => true,
        'cy'                  => true,
        'r'                   => true,
        'rx'                  => true,
        'ry'                  => true,
        'x'                   => true,
        'y'                   => true,
        'width'               => true,
        'height'              => true,
        'x1'                  => true,
        'y1'                  => true,
        'x2'                  => true,
        'y2'                  => true,
        'points'              => true,
        'viewbox'             => true,
        'viewBox'             => true,
        'preserveaspectratio' => true,
        'preserveAspectRatio' => true,
        'clip-path'           => true,
        'clip-rule'           => true,
        'mask'                => true,
        'opacity'             => true,
        'href'                => true,
        'xlink:href'          => true,
        'aria-hidden'         => true,
        'role'                => true,
        'focusable'           => true,
        'offset'              => true,
        'stop-color'          => true,
        'stop-opacity'        => true,
        'gradientunits'       => true,
        'gradientUnits'       => true,
        'gradienttransform'   => true,
        'spreadmethod'        => true,
    ];

    return [
        'svg'            => $a,
        'g'              => $a,
        'path'           => $a,
        'circle'         => $a,
        'ellipse'        => $a,
        'rect'           => $a,
        'line'           => $a,
        'polyline'       => $a,
        'polygon'        => $a,
        'defs'           => $a,
        'lineargradient' => $a,
        'radialgradient' => $a,
        'stop'           => $a,
        'use'            => $a,
        'symbol'         => $a,
        'title'          => $a,
        'desc'           => $a,
        'mask'           => $a,
        'clippath'       => $a,
    ];
}

/**
 * Retire les fragments dangereux avant wp_kses.
 */
function pokehub_type_icon_strip_unsafe_fragments(string $svg): string {
    $svg = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $svg);
    $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg);
    $svg = preg_replace('/<\?.*?\?>/s', '', $svg);
    $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
    $svg = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $svg);
    $svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg);
    $svg = preg_replace('/\s(on[a-z]+\s*=\s*(["\'])(?:(?!\2).)*\2)/i', '', $svg);
    $svg = preg_replace('/\s(on[a-z]+\s*=\s*[^\s>]+)/i', '', $svg);
    return (string) $svg;
}

/**
 * Extrait le premier fragment <svg>...</svg>.
 */
function pokehub_type_icon_extract_svg_markup(string $body): ?string {
    $body = trim($body);
    if ($body === '' || stripos($body, '<svg') === false) {
        return null;
    }
    if (preg_match('/<svg\b[^>]*>[\s\S]*?<\/svg>/is', $body, $m)) {
        return $m[0];
    }
    if (preg_match('/<svg\b[^>]*\/>/is', $body, $m)) {
        return $m[0];
    }
    $body = preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $body);
    $body = ltrim($body);
    if (preg_match('/^<svg\b[^>]*>/i', $body)) {
        return $body;
    }
    return null;
}

/**
 * Indique si une URL peut être chargée par HTTP(S) pour une icône (bucket externe inclus).
 * Seuls les schémas http et https avec un hôte sont acceptés.
 */
function pokehub_type_icon_is_fetch_host_allowed(string $url): bool {
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    return wp_parse_url($url, PHP_URL_HOST) !== null
        && wp_parse_url($url, PHP_URL_HOST) !== '';
}

/**
 * URL publique HTTP(S) pointant vers un chemin .svg (hors résolution locale).
 */
function pokehub_type_icon_is_remote_http_svg_url(string $url): bool {
    if (!pokehub_type_icon_url_path_ends_with_svg($url)) {
        return false;
    }

    return pokehub_type_icon_is_fetch_host_allowed($url)
        && pokehub_type_icon_resolve_local_file_from_url($url) === null;
}

/**
 * Vérifie que le chemin fichier reste sous le répertoire d’upload.
 */
function pokehub_type_icon_is_path_under_uploads(string $abspath): bool {
    $upload = wp_upload_dir();
    if (empty($upload['basedir'])) {
        return false;
    }
    $base = wp_normalize_path($upload['basedir']);
    $full = wp_normalize_path($abspath);

    return strpos($full, $base) === 0;
}

/**
 * Résout une URL vers un fichier local sur ce serveur (même si l’hôte de l’URL
 * diffère de home_url(), ex. poke-hub.local enregistré vs localhost en visite).
 * Sécurité : le chemin résolu doit rester sous uploads / wp-content / ABSPATH, sans « .. ».
 */
function pokehub_type_icon_resolve_local_file_from_url(string $url): ?string {
    $url = esc_url_raw(trim($url));
    if ($url === '') {
        return null;
    }
    $parsed = wp_parse_url($url);
    if (empty($parsed['path'])) {
        return null;
    }

    $url_path = rawurldecode((string) $parsed['path']);

    // 1) uploads : préfixe issu de upload[baseurl] (indépendant du host de l’URL).
    $upload = wp_upload_dir();
    if (empty($upload['error']) && !empty($upload['basedir'])) {
        $bu      = wp_parse_url($upload['baseurl']);
        $bu_path = isset($bu['path']) ? untrailingslashit((string) $bu['path']) : '';
        if ($bu_path !== '' && strpos($url_path, $bu_path) === 0) {
            $rel = ltrim(substr($url_path, strlen($bu_path)), '/');
            if ($rel !== '' && strpos($rel, '..') === false) {
                $full = wp_normalize_path($upload['basedir'] . '/' . $rel);
                $base = wp_normalize_path($upload['basedir']);
                if (strpos($full, $base) === 0 && is_readable($full) && is_file($full)) {
                    return $full;
                }
            }
        }

        // URL enregistrée sans préfixe de sous-dossier (ex. /wp-content/uploads/... alors que le site est /dev/).
        if (!is_multisite() && preg_match('#/wp-content/uploads/(.+)$#i', $url_path, $m)) {
            $tail = $m[1];
            if ($tail !== '' && strpos($tail, '..') === false) {
                $full = wp_normalize_path($upload['basedir'] . '/' . $tail);
                $base = wp_normalize_path($upload['basedir']);
                if (strpos($full, $base) === 0 && is_readable($full) && is_file($full)) {
                    return $full;
                }
            }
        }
    }

    // 2) wp-content (chemin relatif sous WP_CONTENT_DIR).
    $cu      = wp_parse_url(content_url());
    $cu_path = isset($cu['path']) ? untrailingslashit((string) $cu['path']) : '';
    if ($cu_path !== '' && strpos($url_path, $cu_path) === 0) {
        $rel = ltrim(substr($url_path, strlen($cu_path)), '/');
        if ($rel !== '' && strpos($rel, '..') === false) {
            $full = wp_normalize_path(WP_CONTENT_DIR . '/' . $rel);
            $cdir = wp_normalize_path(WP_CONTENT_DIR);
            if (strpos($full, $cdir) === 0 && is_readable($full) && is_file($full)) {
                return $full;
            }
        }
    }

    // 3) Fallback ABSPATH + chemin (sous-répertoire WordPress dans l’URL).
    $path      = $url_path;
    $home_path = (string) wp_parse_url(home_url(), PHP_URL_PATH);
    $home_path = untrailingslashit($home_path ?: '/');
    if ($home_path !== '/' && $home_path !== '' && strpos($path, $home_path) === 0) {
        $path = substr($path, strlen($home_path));
    }
    $path = ltrim((string) $path, '/');
    if ($path !== '' && strpos($path, '..') === false) {
        $abspath_root = wp_normalize_path(ABSPATH);
        $full          = wp_normalize_path($abspath_root . $path);
        if (strpos($full, $abspath_root) === 0 && is_readable($full) && is_file($full)) {
            return $full;
        }
    }

    // 4) Pièce jointe en médiathèque (plusieurs variantes d’URL).
    if (function_exists('attachment_url_to_postid') && function_exists('get_attached_file')) {
        $path_for_url = (string) $parsed['path'];
        $host_part    = isset($parsed['host']) ? (string) $parsed['host'] : '';
        $qpos         = strpos($url, '?');
        $url_no_query = $qpos !== false ? substr($url, 0, $qpos) : $url;

        $site_host = strtolower((string) wp_parse_url(site_url(), PHP_URL_HOST));
        $home_host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));

        $try_urls = array_filter(
            array_unique(
                [
                    $url,
                    $url_no_query,
                    $site_host !== '' && $path_for_url !== '' ? 'https://' . $site_host . $path_for_url : '',
                    $site_host !== '' && $path_for_url !== '' ? 'http://' . $site_host . $path_for_url : '',
                    $home_host !== '' && $path_for_url !== '' && $home_host !== $site_host ? 'https://' . $home_host . $path_for_url : '',
                    $home_host !== '' && $path_for_url !== '' && $home_host !== $site_host ? 'http://' . $home_host . $path_for_url : '',
                ]
            )
        );

        if ($host_part !== '' && $path_for_url !== '') {
            $try_urls[] = 'https://' . $host_part . $path_for_url;
            $try_urls[] = 'http://' . $host_part . $path_for_url;
        }
        $try_urls = array_unique(array_filter($try_urls));

        foreach ($try_urls as $try) {
            if ($try === '') {
                continue;
            }
            $aid = attachment_url_to_postid($try);
            if ($aid) {
                $apath = get_attached_file($aid);
                if (is_string($apath) && $apath !== '' && is_readable($apath) && is_file($apath)) {
                    return wp_normalize_path($apath);
                }
            }
        }
    }

    return null;
}

/**
 * Charge le corps brut (SVG ou page HTML contenant un svg).
 */
function pokehub_type_icon_fetch_raw_body(string $url): ?string {
    $max = 524288; // 512 Ko

    $local = pokehub_type_icon_resolve_local_file_from_url($url);
    if ($local !== null) {
        $size = @filesize($local);
        if ($size !== false && $size > $max) {
            return null;
        }
        $raw = @file_get_contents($local);
        return is_string($raw) ? $raw : null;
    }

    if (!pokehub_type_icon_is_fetch_host_allowed($url)) {
        return null;
    }

    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    $sslverify = true;
    $hlen      = strlen($host);
    if ($host === 'localhost' || ($hlen > 6 && substr($host, -6) === '.local')) {
        $sslverify = false;
    }
    $sslverify = (bool) apply_filters('pokehub_type_icon_remote_sslverify', $sslverify, $url);

    $resp = wp_remote_get(
        $url,
        [
            'timeout'     => 10,
            'redirection' => 2,
            'sslverify'   => $sslverify,
        ]
    );
    if (is_wp_error($resp)) {
        return null;
    }
    if ((int) wp_remote_retrieve_response_code($resp) !== 200) {
        return null;
    }
    $body = (string) wp_remote_retrieve_body($resp);
    if (strlen($body) > $max) {
        return null;
    }
    return $body;
}

/**
 * Sanitize un fragment SVG pour affichage inline.
 */
function pokehub_type_icon_sanitize_svg_markup(string $svg): string {
    $svg = pokehub_type_icon_strip_unsafe_fragments($svg);
    return wp_kses($svg, pokehub_type_icon_get_kses_allowed_svg());
}

/**
 * URL d’icône pointant vers un fichier .svg dans le dossier d’uploads (fichiers ajoutés en admin).
 */
function pokehub_type_icon_is_trusted_upload_svg_url(string $url): bool {
    if (!pokehub_type_icon_url_path_ends_with_svg($url)) {
        return false;
    }
    $path = pokehub_type_icon_resolve_local_file_from_url($url);
    if ($path === null) {
        return false;
    }
    if (strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'svg') {
        return false;
    }

    return pokehub_type_icon_is_path_under_uploads($path);
}

/**
 * Sanitize SVG ; si wp_kses vide tout le markup (balises export Illustrator/Inkscape),
 * on conserve le SVG nettoyé uniquement pour les fichiers .svg dans uploads.
 */
function pokehub_type_icon_sanitize_svg_for_output(string $fragment, string $icon_url): string {
    $stripped = pokehub_type_icon_strip_unsafe_fragments($fragment);
    $safe     = wp_kses($stripped, pokehub_type_icon_get_kses_allowed_svg());
    if ($safe !== '' && stripos($safe, '<svg') !== false) {
        return $safe;
    }
    if ($stripped !== '' && stripos($stripped, '<svg') !== false) {
        if (pokehub_type_icon_is_trusted_upload_svg_url($icon_url)) {
            return $stripped;
        }
        if (pokehub_type_icon_is_remote_http_svg_url($icon_url)) {
            return $stripped;
        }
    }

    return '';
}

/**
 * Indique si l’URL d’icône de type est vide ou pointe vers un fichier .svg (chemin, insensible à la casse).
 */
function pokehub_type_icon_url_is_empty_or_svg(string $url): bool {
    $url = trim($url);
    if ($url === '') {
        return true;
    }

    return pokehub_type_icon_url_path_ends_with_svg($url);
}

/**
 * L’URL doit se terminer par .svg avant ? ou #.
 */
function pokehub_type_icon_url_path_ends_with_svg(string $url): bool {
    $url  = trim($url);
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    if ($path === '') {
        return false;
    }
    $ext = strtolower((string) pathinfo(rawurldecode($path), PATHINFO_EXTENSION));

    return $ext === 'svg';
}

/**
 * Icône de type Pokémon : **uniquement** SVG en markup inline (currentColor). Pas de &lt;img&gt;.
 *
 * @param string $icon_url URL .svg (média ou externe autorisée côté fetch).
 * @param array  $args Voir doc précédente.
 */
function pokehub_render_pokemon_type_icon_html(string $icon_url, array $args = []): string {
    $icon_url = trim($icon_url);
    if ($icon_url === '') {
        return '';
    }

    if (!pokehub_type_icon_url_path_ends_with_svg($icon_url)) {
        return '';
    }

    $args = wp_parse_args(
        $args,
        [
            'color'       => '',
            'class'       => '',
            'aria_hidden' => true,
        ]
    );

    $raw = pokehub_type_icon_fetch_raw_body($icon_url);
    if ($raw === null || $raw === '') {
        return '';
    }

    $fragment = pokehub_type_icon_extract_svg_markup($raw);
    if ($fragment === null) {
        return '';
    }

    $safe = pokehub_type_icon_sanitize_svg_for_output($fragment, $icon_url);
    if ($safe === '' || stripos($safe, '<svg') === false) {
        return '';
    }

    $classes = trim('pokehub-type-icon pokehub-type-icon--inline-svg pokehub-type-icon--tinted ' . (string) $args['class']);
    $style   = '';
    if ((string) $args['color'] !== '') {
        $style = 'color:' . esc_attr((string) $args['color']) . ';';
    }
    $aria = !empty($args['aria_hidden']) ? ' aria-hidden="true" focusable="false"' : '';

    return sprintf(
        '<span class="%s"%s%s role="presentation">%s</span>',
        esc_attr($classes),
        $style !== '' ? ' style="' . esc_attr($style) . '"' : '',
        $aria,
        $safe
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
