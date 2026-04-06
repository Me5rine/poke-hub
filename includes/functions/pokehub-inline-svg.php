<?php
/**
 * SVG inline depuis une URL : fetch, sanitisation wp_kses, pas de &lt;img&gt;.
 * Helper global (chargé avec le plugin, indépendant des modules).
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
function pokehub_inline_svg_get_kses_allowed(): array {
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
function pokehub_inline_svg_strip_unsafe_fragments(string $svg): string {
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
function pokehub_inline_svg_extract_markup(string $body): ?string {
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
 * Indique si une URL peut être chargée par HTTP(S) (bucket externe inclus).
 */
function pokehub_inline_svg_is_fetch_host_allowed(string $url): bool {
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
 * URL HTTP(S) publique vers un .svg (hors résolution fichier local).
 */
function pokehub_inline_svg_is_remote_http_url(string $url): bool {
    if (!pokehub_inline_svg_url_path_ends_with_svg($url)) {
        return false;
    }

    return pokehub_inline_svg_is_fetch_host_allowed($url)
        && pokehub_inline_svg_resolve_local_file_from_url($url) === null;
}

/**
 * Vérifie que le chemin fichier reste sous le répertoire d’upload.
 */
function pokehub_inline_svg_is_path_under_uploads(string $abspath): bool {
    $upload = wp_upload_dir();
    if (empty($upload['basedir'])) {
        return false;
    }
    $base = wp_normalize_path($upload['basedir']);
    $full = wp_normalize_path($abspath);

    return strpos($full, $base) === 0;
}

/**
 * Résout une URL vers un fichier local sur ce serveur.
 * Sécurité : chemin sous uploads / wp-content / ABSPATH, sans « .. ».
 */
function pokehub_inline_svg_resolve_local_file_from_url(string $url): ?string {
    $url = esc_url_raw(trim($url));
    if ($url === '') {
        return null;
    }
    $parsed = wp_parse_url($url);
    if (empty($parsed['path'])) {
        return null;
    }

    $url_path = rawurldecode((string) $parsed['path']);

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
 * Charge le corps brut (SVG ou page contenant un svg).
 */
function pokehub_inline_svg_fetch_raw_body(string $url): ?string {
    $max = 524288; // 512 Ko

    $local = pokehub_inline_svg_resolve_local_file_from_url($url);
    if ($local !== null) {
        $size = @filesize($local);
        if ($size !== false && $size > $max) {
            return null;
        }
        $raw = @file_get_contents($local);

        return is_string($raw) ? $raw : null;
    }

    if (!pokehub_inline_svg_is_fetch_host_allowed($url)) {
        return null;
    }

    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    $sslverify = true;
    $hlen      = strlen($host);
    if ($host === 'localhost' || ($hlen > 6 && substr($host, -6) === '.local')) {
        $sslverify = false;
    }
    $sslverify = (bool) apply_filters('pokehub_inline_svg_remote_sslverify', $sslverify, $url);
    /** @deprecated 2.0.7 Utiliser {@see pokehub_inline_svg_remote_sslverify} */
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
function pokehub_inline_svg_sanitize_markup(string $svg): string {
    $svg = pokehub_inline_svg_strip_unsafe_fragments($svg);

    return wp_kses($svg, pokehub_inline_svg_get_kses_allowed());
}

/**
 * URL vers un .svg dans les uploads (fichiers ajoutés en admin).
 */
function pokehub_inline_svg_is_trusted_upload_svg_url(string $url): bool {
    if (!pokehub_inline_svg_url_path_ends_with_svg($url)) {
        return false;
    }
    $path = pokehub_inline_svg_resolve_local_file_from_url($url);
    if ($path === null) {
        return false;
    }
    if (strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'svg') {
        return false;
    }

    return pokehub_inline_svg_is_path_under_uploads($path);
}

/**
 * Sanitize SVG pour sortie ; repli si wp_kses vide tout le markup.
 */
function pokehub_inline_svg_sanitize_for_output(string $fragment, string $source_url): string {
    $stripped = pokehub_inline_svg_strip_unsafe_fragments($fragment);
    $safe     = wp_kses($stripped, pokehub_inline_svg_get_kses_allowed());
    if ($safe !== '' && stripos($safe, '<svg') !== false) {
        return $safe;
    }
    if ($stripped !== '' && stripos($stripped, '<svg') !== false) {
        if (pokehub_inline_svg_is_trusted_upload_svg_url($source_url)) {
            return $stripped;
        }
        if (pokehub_inline_svg_is_remote_http_url($source_url)) {
            return $stripped;
        }
    }

    return '';
}

/**
 * URL vide ou chemin se terminant par .svg.
 */
function pokehub_inline_svg_url_is_empty_or_svg(string $url): bool {
    $url = trim($url);
    if ($url === '') {
        return true;
    }

    return pokehub_inline_svg_url_path_ends_with_svg($url);
}

/**
 * Le chemin d’URL se termine par .svg (avant ? ou #).
 */
function pokehub_inline_svg_url_path_ends_with_svg(string $url): bool {
    $url  = trim($url);
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    if ($path === '') {
        return false;
    }
    $ext = strtolower((string) pathinfo(rawurldecode($path), PATHINFO_EXTENSION));

    return $ext === 'svg';
}

/**
 * SVG inline depuis une URL (.svg) : fetch, nettoyage, &lt;span&gt; + SVG.
 *
 * @param array $args class (span), color (currentColor), aria_hidden (bool). class défaut : pokehub-inline-svg.
 */
function pokehub_render_inline_svg_from_url(string $svg_url, array $args = []): string {
    $svg_url = trim($svg_url);
    if ($svg_url === '') {
        return '';
    }

    if (!pokehub_inline_svg_url_path_ends_with_svg($svg_url)) {
        return '';
    }

    $args = wp_parse_args(
        $args,
        [
            'class'       => 'pokehub-inline-svg',
            'color'       => '',
            'aria_hidden' => true,
        ]
    );

    $raw = pokehub_inline_svg_fetch_raw_body($svg_url);
    if ($raw === null || $raw === '') {
        return '';
    }

    $fragment = pokehub_inline_svg_extract_markup($raw);
    if ($fragment === null) {
        return '';
    }

    $safe = pokehub_inline_svg_sanitize_for_output($fragment, $svg_url);
    if ($safe === '' || stripos($safe, '<svg') === false) {
        return '';
    }

    $classes = trim((string) $args['class']);
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
