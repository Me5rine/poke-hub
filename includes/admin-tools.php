<?php
// File: includes/admin-tools.php
// Module temporaire : scripts / outils ponctuels (ex. import Pokekalos). À supprimer quand inutile.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Indique si le sous-menu et la page « Outils temporaires » sont activés (réglage Poké HUB).
 */
function poke_hub_temporary_tools_enabled(): bool {
    return (bool) get_option('poke_hub_temporary_tools_enabled', true);
}

/**
 * URL de la page Outils temporaires (optionnel : onglet actif).
 */
function poke_hub_admin_tools_url(string $tab = ''): string {
    $u = admin_url('admin.php?page=poke-hub-tools');
    if ($tab !== '') {
        $u = add_query_arg('tab', sanitize_key($tab), $u);
    }
    return $u;
}

/**
 * Helpers download images (admin tools).
 */
function poke_hub_tools_normalize_text($value): string {
    if ($value === null) {
        return '';
    }
    return trim((string) $value);
}

function poke_hub_tools_normalize_bool($value): bool {
    $v = strtolower(poke_hub_tools_normalize_text($value));
    return in_array($v, ['1', 'true', 'yes', 'y', 'oui'], true);
}

function poke_hub_tools_normalize_slug_token($value): string {
    $token = strtolower(poke_hub_tools_normalize_text($value));
    if ($token === '') {
        return '';
    }
    $token = preg_replace('/[^a-z0-9\-]+/', '-', $token);
    $token = trim((string) $token, '-');
    return $token;
}

function poke_hub_tools_add_missing_token(string $stem, string $token): string {
    if ($token === '') {
        return $stem;
    }
    if (preg_match('/(?:^|-)' . preg_quote($token, '/') . '(?:-|$)/', $stem)) {
        return $stem;
    }
    return $stem . '-' . $token;
}

function poke_hub_tools_build_image_stem(array $row): string {
    $slug = poke_hub_tools_normalize_slug_token($row['slug'] ?? '');
    if ($slug === '') {
        return '';
    }

    $gender      = poke_hub_tools_normalize_slug_token($row['gender'] ?? '');
    $mode        = poke_hub_tools_normalize_slug_token($row['mode'] ?? '');
    $form_slug   = poke_hub_tools_normalize_slug_token($row['form_slug'] ?? '');
    $costume_slug = poke_hub_tools_normalize_slug_token($row['costume_slug'] ?? '');

    $is_shiny     = poke_hub_tools_normalize_bool($row['is_shiny'] ?? false);
    $is_gigamax   = poke_hub_tools_normalize_bool($row['is_gigamax'] ?? false) || $mode === 'gigantamax';
    $is_dynamax   = poke_hub_tools_normalize_bool($row['is_dynamax'] ?? false) || $mode === 'dynamax';
    $is_mega      = poke_hub_tools_normalize_bool($row['is_mega'] ?? false) || $mode === 'mega';
    $is_shadow    = poke_hub_tools_normalize_bool($row['is_shadow'] ?? false) || $mode === 'shadow';
    $is_costume   = poke_hub_tools_normalize_bool($row['is_costume'] ?? false) || $mode === 'costume';

    $stem = $slug;
    if ($is_gigamax && strpos($stem, 'gigantamax-') !== 0) {
        $stem = 'gigantamax-' . $stem;
    } elseif ($is_dynamax && strpos($stem, 'dynamax-') !== 0) {
        $stem = 'dynamax-' . $stem;
    } elseif ($is_mega && strpos($stem, 'mega-') !== 0) {
        $stem = 'mega-' . $stem;
    }

    if ($gender === 'male' && !preg_match('/-male(?:-|$)/', $stem)) {
        $stem .= '-male';
    } elseif ($gender === 'female' && !preg_match('/-female(?:-|$)/', $stem)) {
        $stem .= '-female';
    }
    if ($is_shiny && !preg_match('/-shiny(?:-|$)/', $stem)) {
        $stem .= '-shiny';
    }
    if ($is_shadow && !preg_match('/-shadow(?:-|$)/', $stem)) {
        $stem .= '-shadow';
    }
    if ($is_costume) {
        $stem = poke_hub_tools_add_missing_token($stem, 'costume');
    }
    if ($costume_slug !== '') {
        $stem = poke_hub_tools_add_missing_token($stem, $costume_slug);
    }
    if ($form_slug !== '') {
        $stem = poke_hub_tools_add_missing_token($stem, $form_slug);
    }

    return $stem;
}

/**
 * Tige de fichier type dépôt PokeMiners (ex. pokemon_icon_003_51), sans suffixe _shiny ni .png.
 * Par défaut : forme « 00 » sur le n° de Pokédex — insuffisant pour Méga / Alola / costumes : utilisez le filtre {@see 'poke_hub_pogo_icon_stem'}.
 *
 * @param array<string, mixed> $extra Contenu décodé de pokemon.extra (pokemon_id_proto, form_proto, …).
 */
function poke_hub_tools_compute_pogo_icon_stem(int $dex, array $extra, string $slug = ''): string {
    $dex = max(0, min(9999, $dex));
    $default = 'pokemon_icon_' . sprintf('%03d', $dex) . '_00';
    /**
     * Tige pokemon_icon_XXX_YY… pour les assets GO 256×256 (PokeMiners / client Niantic).
     *
     * @param string               $default Tige par défaut pokemon_icon_{dex3}_00.
     * @param int                  $dex     Numéro Pokédex de la fiche.
     * @param array<string, mixed> $extra   JSON extra (form_proto, pokemon_id_proto, …).
     * @param string               $slug    Slug Poké HUB (souvent dérivé du GM).
     */
    return (string) apply_filters('poke_hub_pogo_icon_stem', $default, $dex, $extra, $slug);
}

/**
 * Base de nom de fichier « Addressable Assets » (dépôt PokeMiners), sans extension ni `.s` chromatique.
 * Ex. pm25, pm103.fALOLA, pm115.fMEGA, pm12.fGIGANTAMAX — voir {@see 'poke_hub_pogo_addressable_base'}.
 *
 * @param array<string, mixed> $extra Contenu décodé de pokemon.extra.
 */
function poke_hub_tools_compute_pogo_addressable_base(int $dex, array $extra, string $slug = ''): string {
    $mid = (int) $dex;
    if ($mid < 0) {
        $mid = 0;
    }
    $default = 'pm' . (string) $mid;
    $form_proto = '';
    if (isset($extra['form_proto']) && is_scalar($extra['form_proto'])) {
        $form_proto = strtoupper((string) $extra['form_proto']);
    }
    $form_proto = trim((string) preg_replace('/[^A-Z0-9_]+/', '_', $form_proto), '_');
    if ($form_proto !== '') {
        if (strpos($form_proto, 'FORM_') === 0) {
            $form_proto = (string) substr($form_proto, 5);
        }
        $species = strtoupper(str_replace('-', '_', poke_hub_tools_normalize_slug_token(strtok($slug, '-') ?: $slug)));
        if ($species !== '' && strpos($form_proto, $species . '_') === 0) {
            $form_proto = (string) substr($form_proto, strlen($species) + 1);
        }
        if ($form_proto !== '' && !in_array($form_proto, ['NORMAL', '00', 'FAMILY'], true)) {
            $default = 'pm' . (string) $mid . '.f' . $form_proto;
        }
    }
    /**
     * Base fichier Images/Pokemon/Addressable%20Assets (pm… + suffixes .fMEGA, .fALOLA, costumes, etc.).
     *
     * @param string               $default pm{dex} par défaut.
     * @param int                  $dex     dex_number BDD.
     * @param array<string, mixed> $extra   extra JSON (form_proto, …).
     * @param string               $slug    slug Poké HUB.
     */
    return (string) apply_filters('poke_hub_pogo_addressable_base', $default, $dex, $extra, $slug);
}

/**
 * Génère des candidats de base Addressable Assets (pmXXX[.f...]) depuis une ligne manifest.
 *
 * @param array<string, string> $row
 * @return array<int, string>
 */
function poke_hub_tools_build_pogo_aa_base_candidates(int $dex, array $row, string $slug): array {
    $dex = max(0, $dex);
    $base_default = poke_hub_tools_compute_pogo_addressable_base($dex, [], $slug);
    $pm_plain = 'pm' . (string) $dex;
    $bases = [];
    if ($base_default !== '' && $base_default !== $pm_plain) {
        $bases[] = $base_default;
    }

    $species = poke_hub_tools_normalize_slug_token(strtok($slug, '-') ?: $slug);
    $species_proto = strtoupper(str_replace('-', '_', $species));

    $tokens = [];
    $raw_parts = [
        poke_hub_tools_normalize_text($row['form'] ?? ''),
        poke_hub_tools_normalize_text($row['form_slug'] ?? ''),
        poke_hub_tools_normalize_text($row['costume_slug'] ?? ''),
    ];
    $mode = poke_hub_tools_normalize_slug_token($row['mode'] ?? '');
    if ($mode !== '' && !in_array($mode, ['normal', 'costume'], true)) {
        $raw_parts[] = $mode;
    }
    $is_gigamax_row = poke_hub_tools_normalize_bool($row['is_gigamax'] ?? false);
    if ($is_gigamax_row || $mode === 'gigantamax' || strpos($slug, 'gigantamax-') === 0) {
        $raw_parts[] = 'gigantamax';
    }
    $is_mega_row = poke_hub_tools_normalize_bool($row['is_mega'] ?? false);
    $slug_lc = strtolower($slug);
    if ($is_mega_row || $mode === 'mega' || strpos($slug_lc, 'mega') !== false) {
        $raw_parts[] = 'mega';
        if (preg_match('/(?:^|-)(x)(?:-|$)/i', $slug_lc)) {
            $raw_parts[] = 'mega_x';
        }
        if (preg_match('/(?:^|-)(y)(?:-|$)/i', $slug_lc)) {
            $raw_parts[] = 'mega_y';
        }
    }

    // Ajoute aussi les suffixes présents dans le slug (ex: unown-z, deerling-summer, vivillon-tundra).
    $slug_tail = '';
    if (strpos($slug, '-') !== false) {
        $slug_tail = (string) substr($slug, strpos($slug, '-') + 1);
    }
    $is_base_slug = ($slug_tail === '');
    if ($slug_tail !== '') {
        $raw_parts[] = $slug_tail;
        foreach (explode('-', $slug_tail) as $part) {
            $raw_parts[] = $part;
        }
    }

    foreach ($raw_parts as $part) {
        $part = strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '_', trim((string) $part)));
        $part = trim($part, '_');
        if ($part !== '' && !in_array($part, ['NORMAL', 'FORME'], true)) {
            $tokens[] = $part;
        }
    }
    $tokens = array_values(array_unique($tokens));

    foreach ($tokens as $tk) {
        $bases[] = 'pm' . (string) $dex . '.f' . $tk;
        if ($species_proto !== '') {
            $bases[] = 'pm' . (string) $dex . '.f' . $species_proto . '_' . $tk;
        }
        if (strpos($tk, 'COSTUME_') === 0) {
            $trimmed_costume = (string) substr($tk, 8);
            if ($trimmed_costume !== '') {
                $bases[] = 'pm' . (string) $dex . '.f' . $trimmed_costume;
                if ($species_proto !== '') {
                    $bases[] = 'pm' . (string) $dex . '.f' . $species_proto . '_' . $trimmed_costume;
                }
            }
        }
        if ($tk === 'ALOLAN') {
            $bases[] = 'pm' . (string) $dex . '.fALOLA';
        } elseif ($tk === 'GALARIAN') {
            $bases[] = 'pm' . (string) $dex . '.fGALAR';
        } elseif ($tk === 'HISUIAN') {
            $bases[] = 'pm' . (string) $dex . '.fHISUI';
        } elseif ($tk === 'PALDEAN') {
            $bases[] = 'pm' . (string) $dex . '.fPALDEA';
        } elseif ($tk === 'MEGA_X') {
            $bases[] = 'pm' . (string) $dex . '.fMEGAX';
        } elseif ($tk === 'MEGA_Y') {
            $bases[] = 'pm' . (string) $dex . '.fMEGAY';
        }
    }

    if (in_array('MEGA', $tokens, true)) {
        $bases[] = 'pm' . (string) $dex . '.fMEGA';
    }
    if (in_array('MEGA_X', $tokens, true)) {
        $bases[] = 'pm' . (string) $dex . '.fMEGA_X';
    }
    if (in_array('MEGA_Y', $tokens, true)) {
        $bases[] = 'pm' . (string) $dex . '.fMEGA_Y';
    }

    $has_variant_hint = ($tokens !== []) || ($mode !== '' && $mode !== 'normal') || !$is_base_slug || $is_gigamax_row || $is_mega_row;

    // Cas fréquent: pour certains dex partagés (ex. Kyurem), la forme de base est stockée en fNORMAL.
    if ($is_base_slug) {
        $bases[] = 'pm' . (string) $dex . '.fNORMAL';
        if ($species_proto !== '') {
            $bases[] = 'pm' . (string) $dex . '.f' . $species_proto . '_NORMAL';
        }
    }

    // Règles ciblées sur cas fréquents Addressable Assets.
    if ($species === 'unown') {
        foreach ($tokens as $tk) {
            if (preg_match('/^[A-Z0-9]+$/', $tk)) {
                $bases[] = 'pm' . (string) $dex . '.fUNOWN_' . $tk;
            }
        }
    } elseif ($species === 'spinda') {
        $bases[] = 'pm' . (string) $dex . '.f00';
        foreach ($tokens as $tk) {
            if (preg_match('/^\d{1,2}$/', $tk)) {
                $bases[] = 'pm' . (string) $dex . '.f' . sprintf('%02d', (int) $tk);
            }
        }
    } elseif (in_array($species, ['burmy', 'wormadam', 'mothim'], true)) {
        foreach ($tokens as $tk) {
            $bases[] = 'pm' . (string) $dex . '.fBURMY_' . $tk;
        }
    } elseif (in_array($species, ['deerling', 'sawsbuck', 'vivillon', 'oricorio'], true)) {
        foreach ($tokens as $tk) {
            $bases[] = 'pm' . (string) $dex . '.f' . $tk;
        }
    }

    // Le fallback forme de base pm{dex} ne doit jamais primer pour une forme spéciale (méga, gmax, costumes…).
    if (!$has_variant_hint) {
        $bases[] = $pm_plain;
    }

    return array_values(array_unique(array_filter($bases, static fn($v) => $v !== '')));
}

function poke_hub_tools_resolve_row_url(array $row, string $template): string {
    $direct = poke_hub_tools_normalize_text($row['url'] ?? '');
    if ($direct !== '') {
        return esc_url_raw($direct);
    }
    if ($template === '') {
        return '';
    }

    $dex = (int) poke_hub_tools_normalize_text($row['dex'] ?? '0');
    $slug = poke_hub_tools_normalize_text($row['slug'] ?? '');
    $gender = poke_hub_tools_normalize_slug_token($row['gender'] ?? '');
    $form = poke_hub_tools_normalize_text($row['form'] ?? '');
    $form_slug = poke_hub_tools_normalize_slug_token($row['form_slug'] ?? '');
    $costume_slug = poke_hub_tools_normalize_slug_token($row['costume_slug'] ?? '');
    $mode = poke_hub_tools_normalize_slug_token($row['mode'] ?? '');
    $is_shiny = poke_hub_tools_normalize_bool($row['is_shiny'] ?? false);
    $is_gigamax = poke_hub_tools_normalize_bool($row['is_gigamax'] ?? false);
    $is_dynamax = poke_hub_tools_normalize_bool($row['is_dynamax'] ?? false);
    $is_mega = poke_hub_tools_normalize_bool($row['is_mega'] ?? false);
    $is_shadow = poke_hub_tools_normalize_bool($row['is_shadow'] ?? false);

    $stem = poke_hub_tools_build_image_stem($row);
    if ($stem === '') {
        return '';
    }

    $gender_suffix = $gender === 'male' ? '-male' : ($gender === 'female' ? '-female' : '');
    $shiny_suffix = $is_shiny ? '-shiny' : '';
    $form_suffix = $form !== '' ? '-' . strtolower($form) : '';
    $mode_prefix = '';
    if ($mode === 'gigantamax' || $is_gigamax) {
        $mode_prefix = 'gigantamax-';
    } elseif ($mode === 'dynamax' || $is_dynamax) {
        $mode_prefix = 'dynamax-';
    } elseif ($mode === 'mega' || $is_mega) {
        $mode_prefix = 'mega-';
    }
    $mode_suffix = $is_shadow || $mode === 'shadow' ? '-shadow' : '';

    $pogo_stem_cell = trim((string) ($row['pogo_stem'] ?? ''));
    if ($pogo_stem_cell === '') {
        $pogo_stem_cell = poke_hub_tools_compute_pogo_icon_stem($dex, [], $slug);
    }
    /**
     * Suffixe fichier entre tige PokeMiners et _shiny (ex. _11 / _14 pour variantes genre côté client GO).
     *
     * @param array<string, string> $row Ligne manifest.
     */
    $pogo_gender_infix = '';
    if ($gender === 'male') {
        $pogo_gender_infix = (string) apply_filters('poke_hub_pogo_asset_male_infix', '', $row);
    } elseif ($gender === 'female') {
        $pogo_gender_infix = (string) apply_filters('poke_hub_pogo_asset_female_infix', '', $row);
    }
    $pogo_shiny_infix = $is_shiny ? '_shiny' : '';
    $pogo_file = $pogo_stem_cell . $pogo_gender_infix . $pogo_shiny_infix;

    $pogo_aa_base_cell = trim((string) ($row['pogo_aa_base'] ?? ''));
    if ($pogo_aa_base_cell === '') {
        $aa_candidates = poke_hub_tools_build_pogo_aa_base_candidates($dex, $row, $slug);
        $pogo_aa_base_cell = $aa_candidates[0] ?? poke_hub_tools_compute_pogo_addressable_base($dex, [], $slug);
    }
    $pogo_aa_gender_infix = '';
    if ($gender === 'male') {
        $pogo_aa_gender_infix = (string) apply_filters('poke_hub_pogo_aa_male_infix', '', $row);
    } elseif ($gender === 'female') {
        $pogo_aa_gender_infix = (string) apply_filters('poke_hub_pogo_aa_female_infix', '.g2', $row);
    }
    $pogo_aa_file = $pogo_aa_base_cell . $pogo_aa_gender_infix . ($is_shiny ? '.s' : '') . '.icon.png';

    $replacements = [
        '{dex}' => (string) $dex,
        '{dex3}' => sprintf('%03d', max(0, $dex)),
        '{slug}' => $slug,
        '{stem}' => $stem,
        '{gender}' => $gender,
        '{gender_suffix}' => $gender_suffix,
        '{shiny_suffix}' => $shiny_suffix,
        '{form}' => $form,
        '{form_suffix}' => $form_suffix,
        '{form_slug}' => $form_slug,
        '{costume_slug}' => $costume_slug,
        '{mode}' => $mode,
        '{mode_prefix}' => $mode_prefix,
        '{mode_suffix}' => $mode_suffix,
        '{pogo_stem}' => $pogo_stem_cell,
        '{pogo_gender_infix}' => $pogo_gender_infix,
        '{pogo_shiny_infix}' => $pogo_shiny_infix,
        '{pogo_file}' => $pogo_file,
        '{pogo_aa_base}' => $pogo_aa_base_cell,
        '{pogo_aa_gender_infix}' => $pogo_aa_gender_infix,
        '{pogo_aa_file}' => $pogo_aa_file,
    ];

    $url = strtr($template, $replacements);
    return esc_url_raw($url);
}

function poke_hub_tools_download_images_from_manifest(array $args): array {
    $defaults = [
        'manifest_csv' => '',
        'go_template' => '',
        'home_template' => '',
        'skip_existing' => true,
        'timeout' => 30,
    ];
    $args = wp_parse_args($args, $defaults);

    $manifest_csv = poke_hub_tools_normalize_text($args['manifest_csv']);
    if ($manifest_csv === '') {
        return ['log' => [__('Manifest CSV is required.', 'poke-hub')]];
    }

    $uploads = wp_upload_dir();
    $base_dir = trailingslashit($uploads['basedir']) . 'poke-hub/gamemaster';
    $go_dir = trailingslashit($base_dir) . 'pokemon-go/pokemon';
    $home_dir = trailingslashit($base_dir) . 'home/pokemon';
    wp_mkdir_p($go_dir);
    wp_mkdir_p($home_dir);

    $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", $manifest_csv));
    if (count($rows) < 2) {
        return ['log' => [__('Manifest CSV has no data rows.', 'poke-hub')]];
    }
    $headers = array_map(static fn($v) => trim((string) $v), (array) array_shift($rows));
    $log = [];
    $ok = 0;
    $fail = 0;
    $skip = 0;

    foreach ($rows as $i => $values) {
        if (!is_array($values) || count(array_filter($values, static fn($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($headers as $idx => $key) {
            if ($key === '') {
                continue;
            }
            $row[$key] = $values[$idx] ?? '';
        }

        $slug = poke_hub_tools_normalize_text($row['slug'] ?? '');
        $source = strtolower(poke_hub_tools_normalize_text($row['source'] ?? ''));
        if ($slug === '' || !in_array($source, ['go', 'home'], true)) {
            $log[] = sprintf('Ligne %d ignorée: slug/source invalide.', $i + 2);
            $fail++;
            continue;
        }

        $stem = poke_hub_tools_build_image_stem($row);
        if ($stem === '') {
            $log[] = sprintf('Ligne %d ignorée: stem vide.', $i + 2);
            $fail++;
            continue;
        }

        $ext = strtolower(ltrim(poke_hub_tools_normalize_text($row['extension'] ?? 'png'), '.'));
        if ($ext === '') {
            $ext = 'png';
        }
        $out_dir = $source === 'go' ? $go_dir : $home_dir;
        $out_file = trailingslashit($out_dir) . $stem . '.' . $ext;

        if (!empty($args['skip_existing']) && file_exists($out_file)) {
            $skip++;
            $log[] = sprintf('[SKIP] %s', wp_basename($out_file));
            continue;
        }

        $tpl = $source === 'go' ? poke_hub_tools_normalize_text($args['go_template']) : poke_hub_tools_normalize_text($args['home_template']);
        $url = poke_hub_tools_resolve_row_url($row, $tpl);
        if ($url === '') {
            $log[] = sprintf('Ligne %d: aucune URL résolue.', $i + 2);
            $fail++;
            continue;
        }

        $res = wp_remote_get($url, [
            'timeout' => max(5, (int) $args['timeout']),
            'redirection' => 5,
            'user-agent' => 'PokeHubImageSync/1.0',
        ]);
        if (is_wp_error($res)) {
            $fail++;
            $log[] = sprintf('[FAIL] %s (%s)', $url, $res->get_error_message());
            continue;
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        if ($code < 200 || $code >= 300 || $body === '') {
            $fail++;
            $log[] = sprintf('[FAIL] %s (HTTP %d)', $url, $code);
            continue;
        }
        $written = @file_put_contents($out_file, $body);
        if ($written === false) {
            $fail++;
            $log[] = sprintf('[FAIL] Ecriture impossible: %s', $out_file);
            continue;
        }
        $ok++;
        $log[] = sprintf('[OK] %s -> %s', $url, wp_basename($out_file));
    }

    $log[] = '';
    $log[] = sprintf('Terminé. OK=%d | FAIL=%d | SKIP=%d', $ok, $fail, $skip);
    $log[] = sprintf('Dossier de sortie: %s', $base_dir);

    return ['log' => $log];
}

/**
 * Copie locale depuis un dossier d'assets (Addressable Assets, etc.) vers les noms Poké HUB.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function poke_hub_tools_copy_images_from_local_manifest(array $args): array {
    $defaults = [
        'manifest_csv' => '',
        'source_dir' => '',
        'skip_existing' => true,
    ];
    $args = wp_parse_args($args, $defaults);

    $manifest_csv = poke_hub_tools_normalize_text($args['manifest_csv']);
    if ($manifest_csv === '') {
        return ['log' => [__('Manifest CSV is required.', 'poke-hub')]];
    }
    $source_dir_input = poke_hub_tools_normalize_text($args['source_dir']);
    if ($source_dir_input === '') {
        return ['log' => [__('Local assets folder is required.', 'poke-hub')]];
    }
    $uploads = wp_upload_dir();
    $source_dir = $source_dir_input;
    if (preg_match('#^https?://#i', $source_dir_input)) {
        $baseurl = rtrim((string) ($uploads['baseurl'] ?? ''), '/');
        $basedir = wp_normalize_path((string) ($uploads['basedir'] ?? ''));
        if ($baseurl !== '' && $basedir !== '' && stripos($source_dir_input, $baseurl . '/') === 0) {
            $rel = ltrim((string) substr($source_dir_input, strlen($baseurl)), '/');
            $source_dir = $basedir . '/' . str_replace('\\', '/', $rel);
        }
    }
    $source_dir = wp_normalize_path($source_dir);
    if (!is_dir($source_dir)) {
        return ['log' => [
            sprintf(__('Local assets folder not found: %s', 'poke-hub'), $source_dir_input),
            sprintf(__('Tip: use a server path (example: %s)', 'poke-hub'), trailingslashit((string) ($uploads['basedir'] ?? '')) . 'poke-hub'),
        ]];
    }
    $base_dir = trailingslashit($uploads['basedir']) . 'poke-hub/gamemaster';
    $go_dir = trailingslashit($base_dir) . 'pokemon-go/pokemon';
    $home_dir = trailingslashit($base_dir) . 'home/pokemon';
    wp_mkdir_p($go_dir);
    wp_mkdir_p($home_dir);

    $asset_index = [];
    $asset_index_by_pm = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $path = $file->getRealPath();
        if ($path === false) {
            continue;
        }
        $basename = strtolower((string) $file->getBasename());
        if ($basename === '') {
            continue;
        }
        if (!isset($asset_index[$basename])) {
            $asset_index[$basename] = $path;
        }
        if (preg_match('/^(pm\d+)(?:\.f[^.]+)?(\.s)?\.icon\.(png|jpg|webp)$/', $basename, $m)) {
            $pm_prefix = (string) $m[1];
            $bucket = !empty($m[2]) ? 'shiny' : 'normal';
            if (!isset($asset_index_by_pm[$pm_prefix])) {
                $asset_index_by_pm[$pm_prefix] = ['normal' => [], 'shiny' => []];
            }
            $asset_index_by_pm[$pm_prefix][$bucket][] = $path;
        }
    }
    if (empty($asset_index)) {
        return ['log' => [sprintf(__('No files found in local assets folder: %s', 'poke-hub'), $source_dir_input)]];
    }

    $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", $manifest_csv));
    if (count($rows) < 2) {
        return ['log' => [__('Manifest CSV has no data rows.', 'poke-hub')]];
    }
    $headers = array_map(static fn($v) => trim((string) $v), (array) array_shift($rows));
    $log = [];
    $ok = 0;
    $fail = 0;
    $skip = 0;
    $missing_rows = [];

    foreach ($rows as $i => $values) {
        if (!is_array($values) || count(array_filter($values, static fn($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($headers as $idx => $key) {
            if ($key === '') {
                continue;
            }
            $row[$key] = $values[$idx] ?? '';
        }

        $slug = poke_hub_tools_normalize_text($row['slug'] ?? '');
        $source = strtolower(poke_hub_tools_normalize_text($row['source'] ?? ''));
        if ($slug === '' || !in_array($source, ['go', 'home'], true)) {
            $log[] = sprintf('Ligne %d ignorée: slug/source invalide.', $i + 2);
            $fail++;
            continue;
        }

        $stem = poke_hub_tools_build_image_stem($row);
        if ($stem === '') {
            $log[] = sprintf('Ligne %d ignorée: stem vide.', $i + 2);
            $fail++;
            continue;
        }

        $out_ext = strtolower(ltrim(poke_hub_tools_normalize_text($row['extension'] ?? 'png'), '.'));
        if ($out_ext === '') {
            $out_ext = 'png';
        }
        $out_dir = $source === 'go' ? $go_dir : $home_dir;
        $out_file = trailingslashit($out_dir) . $stem . '.' . $out_ext;

        if (!empty($args['skip_existing']) && file_exists($out_file)) {
            $skip++;
            $log[] = sprintf('[SKIP] %s', wp_basename($out_file));
            continue;
        }

        $dex = (int) poke_hub_tools_normalize_text($row['dex'] ?? '0');
        $is_shiny = poke_hub_tools_normalize_bool($row['is_shiny'] ?? false);
        $gender = poke_hub_tools_normalize_slug_token($row['gender'] ?? '');

        $pogo_stem_cell = trim((string) ($row['pogo_stem'] ?? ''));
        if ($pogo_stem_cell === '') {
            $pogo_stem_cell = poke_hub_tools_compute_pogo_icon_stem($dex, [], $slug);
        }
        $pogo_gender_infix = '';
        if ($gender === 'male') {
            $pogo_gender_infix = (string) apply_filters('poke_hub_pogo_asset_male_infix', '', $row);
        } elseif ($gender === 'female') {
            $pogo_gender_infix = (string) apply_filters('poke_hub_pogo_asset_female_infix', '', $row);
        }
        $pogo_shiny_infix = $is_shiny ? '_shiny' : '';
        $pogo_file = $pogo_stem_cell . $pogo_gender_infix . $pogo_shiny_infix;

        $aa_bases = [];
        $pogo_aa_base_cell = trim((string) ($row['pogo_aa_base'] ?? ''));
        if ($pogo_aa_base_cell !== '') {
            $aa_bases[] = $pogo_aa_base_cell;
        }
        foreach (poke_hub_tools_build_pogo_aa_base_candidates($dex, $row, $slug) as $cand_base) {
            $aa_bases[] = $cand_base;
        }
        $aa_bases = array_values(array_unique(array_filter($aa_bases, static fn($v) => $v !== '')));

        $aa_files = [];
        $pogo_aa_gender_infix = '';
        if ($gender === 'male') {
            $pogo_aa_gender_infix = (string) apply_filters('poke_hub_pogo_aa_male_infix', '', $row);
        } elseif ($gender === 'female') {
            $pogo_aa_gender_infix = (string) apply_filters('poke_hub_pogo_aa_female_infix', '.g2', $row);
        }
        foreach ($aa_bases as $aa_base) {
            $aa_files[] = $aa_base . $pogo_aa_gender_infix . ($is_shiny ? '.s' : '') . '.icon.png';
            if ($pogo_aa_gender_infix !== '') {
                // Fallback possible si la variante genre n'existe pas pour cette forme.
                $aa_files[] = $aa_base . ($is_shiny ? '.s' : '') . '.icon.png';
            }
        }

        $explicit_local = poke_hub_tools_normalize_text($row['local_file'] ?? '');
        $candidates = [];
        if ($explicit_local !== '') {
            $candidates[] = wp_basename($explicit_local);
        }
        foreach ($aa_files as $aa_file) {
            $candidates[] = $aa_file;
        }
        $candidates[] = $pogo_file . '.png';
        $candidates[] = $pogo_file . '.jpg';
        $candidates = array_values(array_unique(array_filter($candidates, static fn($v) => $v !== '')));

        $src_path = '';
        foreach ($candidates as $candidate) {
            $key = strtolower((string) $candidate);
            if (isset($asset_index[$key])) {
                $src_path = (string) $asset_index[$key];
                break;
            }
        }

        $used_fallback = false;
        $mode = poke_hub_tools_normalize_slug_token($row['mode'] ?? '');
        $has_variant_hint = (
            strpos($slug, '-') !== false
            || poke_hub_tools_normalize_text($row['form'] ?? '') !== ''
            || poke_hub_tools_normalize_text($row['form_slug'] ?? '') !== ''
            || poke_hub_tools_normalize_text($row['costume_slug'] ?? '') !== ''
            || !in_array($mode, ['', 'normal'], true)
        );
        if ($src_path === '' && !$has_variant_hint) {
            $pm_prefix = 'pm' . (string) max(0, $dex);
            $bucket = $is_shiny ? 'shiny' : 'normal';
            $fallback_list = $asset_index_by_pm[$pm_prefix][$bucket] ?? [];
            if (!empty($fallback_list)) {
                usort($fallback_list, static function ($a, $b): int {
                    return strcmp(strtolower((string) wp_basename((string) $a)), strtolower((string) wp_basename((string) $b)));
                });
                $src_path = (string) $fallback_list[0];
                $used_fallback = true;
            }
        }

        if ($src_path === '') {
            $fail++;
            $missing_rows[] = [
                'line' => (string) ($i + 2),
                'source' => $source,
                'slug' => $slug,
                'output_file' => wp_basename($out_file),
                'candidates' => implode(' | ', $candidates),
            ];
            $log[] = sprintf('[MISS] %s -> %s (candidats: %s)', $slug, wp_basename($out_file), implode(', ', $candidates));
            continue;
        }

        if (!@copy($src_path, $out_file)) {
            $fail++;
            $log[] = sprintf('[FAIL] Copie impossible: %s -> %s', $src_path, $out_file);
            continue;
        }

        $ok++;
        if ($used_fallback) {
            $log[] = sprintf('[OK][FALLBACK] %s -> %s', wp_basename($src_path), wp_basename($out_file));
        } else {
            $log[] = sprintf('[OK] %s -> %s', wp_basename($src_path), wp_basename($out_file));
        }
    }

    $missing_csv_path = '';
    if (!empty($missing_rows)) {
        $exports_dir = trailingslashit($uploads['basedir']) . 'poke-hub/exports';
        wp_mkdir_p($exports_dir);
        $missing_csv_path = trailingslashit($exports_dir) . 'poke-hub-missing-images-' . gmdate('Ymd-His') . '.csv';
        $mh = @fopen($missing_csv_path, 'wb');
        if ($mh !== false) {
            fputcsv($mh, ['line', 'source', 'slug', 'output_file', 'candidates']);
            foreach ($missing_rows as $mr) {
                fputcsv($mh, [$mr['line'], $mr['source'], $mr['slug'], $mr['output_file'], $mr['candidates']]);
            }
            fclose($mh);
        } else {
            $missing_csv_path = '';
        }
    }

    $log[] = '';
    $log[] = sprintf('Terminé (local rename). OK=%d | FAIL=%d | SKIP=%d', $ok, $fail, $skip);
    $log[] = sprintf('Dossier source local: %s', $source_dir_input);
    $log[] = sprintf('Dossier de sortie: %s', $base_dir);
    if ($missing_csv_path !== '') {
        $log[] = sprintf('CSV manquants: %s', $missing_csv_path);
    }

    return ['log' => $log];
}

function poke_hub_tools_get_gamemaster_base_paths(): array {
    $uploads = wp_upload_dir();
    $base_dir = trailingslashit($uploads['basedir']) . 'poke-hub/gamemaster';
    $base_url = trailingslashit($uploads['baseurl']) . 'poke-hub/gamemaster';
    return [
        'dir' => $base_dir,
        'url' => $base_url,
    ];
}

function poke_hub_tools_create_gamemaster_zip(): array {
    $paths = poke_hub_tools_get_gamemaster_base_paths();
    $base_dir = (string) ($paths['dir'] ?? '');
    if ($base_dir === '' || !is_dir($base_dir)) {
        return [
            'log' => [__('Gamemaster folder not found. Run download first.', 'poke-hub')],
        ];
    }
    if (!class_exists('ZipArchive')) {
        return [
            'log' => [__('ZipArchive is not available on this server.', 'poke-hub')],
        ];
    }

    $uploads = wp_upload_dir();
    $zip_dir = trailingslashit($uploads['basedir']) . 'poke-hub/exports';
    wp_mkdir_p($zip_dir);

    $zip_name = 'poke-hub-gamemaster-' . gmdate('Ymd-His') . '.zip';
    $zip_path = trailingslashit($zip_dir) . $zip_name;

    $zip = new ZipArchive();
    $open = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($open !== true) {
        return [
            'log' => [sprintf(__('Failed to create ZIP file (%s).', 'poke-hub'), (string) $open)],
        ];
    }

    $base_real = realpath($base_dir);
    if ($base_real === false) {
        $zip->close();
        return [
            'log' => [__('Invalid gamemaster path.', 'poke-hub')],
        ];
    }
    $base_real = rtrim($base_real, '\\/') . DIRECTORY_SEPARATOR;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $count = 0;
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }
        if (!$file->isFile()) {
            continue;
        }
        $file_path = $file->getRealPath();
        if ($file_path === false) {
            continue;
        }
        $local_name = str_replace('\\', '/', substr($file_path, strlen($base_real)));
        if ($local_name === '') {
            continue;
        }
        $zip->addFile($file_path, $local_name);
        $count++;
    }

    $zip->close();

    $download_url = add_query_arg([
        'action' => 'poke_hub_tools_download_export_zip',
        'file' => rawurlencode($zip_name),
        '_wpnonce' => wp_create_nonce('poke_hub_tools_download_export_zip:' . $zip_name),
    ], admin_url('admin-post.php'));

    return [
        'log' => [
            sprintf(__('ZIP created with %d files.', 'poke-hub'), $count),
            sprintf(__('ZIP path: %s', 'poke-hub'), $zip_path),
        ],
        'download_url' => esc_url_raw($download_url),
        'download_label' => $zip_name,
    ];
}

/**
 * Téléchargement sécurisé d'un ZIP exports via admin-post (évite les blocages nginx sur /uploads).
 */
function poke_hub_tools_handle_download_export_zip(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to download this file.', 'poke-hub'), '', 403);
    }

    $file_param = isset($_GET['file']) ? wp_unslash((string) $_GET['file']) : '';
    $file_name = rawurldecode($file_param);
    $file_name = wp_basename($file_name);
    if ($file_name === '' || substr($file_name, -4) !== '.zip') {
        wp_die(esc_html__('Invalid ZIP file.', 'poke-hub'), '', 400);
    }
    if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '', 'poke_hub_tools_download_export_zip:' . $file_name)) {
        wp_die(esc_html__('Invalid download nonce.', 'poke-hub'), '', 403);
    }

    $uploads = wp_upload_dir();
    $exports_dir = trailingslashit((string) ($uploads['basedir'] ?? '')) . 'poke-hub/exports';
    $file_path = trailingslashit($exports_dir) . $file_name;
    if (!is_file($file_path)) {
        wp_die(esc_html__('ZIP file not found.', 'poke-hub'), '', 404);
    }

    nocache_headers();
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $file_name) . '"');
    header('Content-Length: ' . (string) filesize($file_path));
    header('X-Content-Type-Options: nosniff');
    readfile($file_path);
    exit;
}
add_action('admin_post_poke_hub_tools_download_export_zip', 'poke_hub_tools_handle_download_export_zip');

/**
 * Écrit les lignes manifest pour un slug donné (variantes genre / shiny selon options).
 *
 * @param resource $fh Handle ouvert en écriture binaire.
 * @param int      $profile_pokemon_id ID fiche pour poke_hub_pokemon_get_gender_profile (fiche de base pour Gigamax synthétique).
 * @param array<string, mixed> $extra_for_pogo JSON extra décodé (pogo_stem, pogo_aa_base / filtres).
 */
function poke_hub_tools_manifest_fput_lines_for_pokemon($fh, int &$count, int $profile_pokemon_id, int $dex, string $slug, array $sources, array $args, bool $mark_synthetic_gigantamax = false, array $extra_for_pogo = []): void {
    if ($fh === false) {
        return;
    }
    $slug = sanitize_title($slug);
    if ($slug === '' || $slug === '0') {
        return;
    }

    $variants = [
        [
            'gender' => '',
            'is_shiny' => false,
        ],
    ];

    if (!empty($args['include_gender_variants']) && $profile_pokemon_id > 0 && function_exists('poke_hub_pokemon_get_gender_profile')) {
        $prof = poke_hub_pokemon_get_gender_profile($profile_pokemon_id);
        if (!empty($prof['has_gender_dimorphism'])) {
            $avail = isset($prof['available_genders']) && is_array($prof['available_genders']) ? $prof['available_genders'] : [];
            $has_m = in_array('male', $avail, true);
            $has_f = in_array('female', $avail, true);
            if ($has_m && $has_f) {
                $variants = [
                    ['gender' => 'male', 'is_shiny' => false],
                    ['gender' => 'female', 'is_shiny' => false],
                ];
            }
        }
    }

    // Toujours dupliquer les lignes shiny si demandé (même si pas encore « dispo en jeu » selon extra / can_be_shiny).
    if (!empty($args['include_shiny_variants'])) {
        $expanded = [];
        foreach ($variants as $v) {
            $expanded[] = $v;
            $expanded[] = [
                'gender' => $v['gender'],
                'is_shiny' => true,
            ];
        }
        $variants = $expanded;
    }

    $is_giga_csv = $mark_synthetic_gigantamax ? 'true' : 'false';
    $pogo_stem = poke_hub_tools_compute_pogo_icon_stem($dex, $extra_for_pogo, $slug);
    $pogo_aa_base = poke_hub_tools_compute_pogo_addressable_base($dex, $extra_for_pogo, $slug);
    if ($mark_synthetic_gigantamax && strpos($pogo_aa_base, '.f') === false) {
        $pogo_aa_base .= '.fGIGANTAMAX';
    }

    foreach ($variants as $v) {
        foreach ($sources as $src) {
            $line = [
                $src,
                (string) $dex,
                $slug,
                '',
                '',
                '',
                $mark_synthetic_gigantamax ? 'gigantamax' : '',
                (string) ($v['gender'] ?? ''),
                !empty($v['is_shiny']) ? 'true' : 'false',
                $is_giga_csv,
                'false',
                'false',
                'false',
                'false',
                $pogo_stem,
                $pogo_aa_base,
                'png',
                '',
            ];
            fputcsv($fh, $line);
            $count++;
        }
    }
}

/**
 * Génère un manifest CSV à partir des fiches Pokémon en base (slug déjà complet par forme, import GM).
 *
 * @param array{
 *   include_go?: bool,
 *   include_home?: bool,
 *   include_shiny_variants?: bool,
 *   include_gender_variants?: bool,
 *   include_synthetic_gigantamax?: bool,
 *   limit?: int
 * } $args
 * @return array{csv: string, log: string[], rows: int}
 */
function poke_hub_tools_build_manifest_csv_from_db(array $args): array {
    $defaults = [
        'include_go' => true,
        'include_home' => true,
        'include_shiny_variants' => true,
        'include_gender_variants' => true,
        'include_synthetic_gigantamax' => true,
        'limit' => 0,
    ];
    $args = wp_parse_args($args, $defaults);

    $sources = [];
    if (!empty($args['include_go'])) {
        $sources[] = 'go';
    }
    if (!empty($args['include_home'])) {
        $sources[] = 'home';
    }
    if ($sources === []) {
        return [
            'csv' => '',
            'rows' => 0,
            'log' => [__('Select at least one target: Pokémon GO and/or HOME.', 'poke-hub')],
        ];
    }

    $use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
    $pokemon_table = $use_remote ? pokehub_get_table('remote_pokemon') : pokehub_get_table('pokemon');
    if (!function_exists('pokehub_pokemon_tables_ready_for_query') || !pokehub_pokemon_tables_ready_for_query($use_remote, $pokemon_table)) {
        return [
            'csv' => '',
            'rows' => 0,
            'log' => [__('Pokemon table is not available.', 'poke-hub')],
        ];
    }

    global $wpdb;
    $limit = (int) $args['limit'];
    if ($limit < 0) {
        $limit = 0;
    }
    if ($limit > 50000) {
        $limit = 50000;
    }

    $sql = "SELECT p.id, p.dex_number, p.slug, p.extra FROM {$pokemon_table} p
        WHERE p.slug IS NOT NULL AND TRIM(p.slug) <> '' AND TRIM(p.slug) <> '0'
        ORDER BY p.dex_number ASC, p.id ASC";
    if ($limit > 0) {
        $sql = $wpdb->prepare($sql . ' LIMIT %d', $limit);
    }

    $rows_db = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($rows_db)) {
        return [
            'csv' => '',
            'rows' => 0,
            'log' => [__('Database query failed.', 'poke-hub')],
        ];
    }

    $header = [
        'source',
        'dex',
        'slug',
        'form',
        'form_slug',
        'costume_slug',
        'mode',
        'gender',
        'is_shiny',
        'is_gigamax',
        'is_dynamax',
        'is_mega',
        'is_shadow',
        'is_costume',
        'pogo_stem',
        'pogo_aa_base',
        'extension',
        'url',
    ];

    $fh = fopen('php://memory', 'wb+');
    if ($fh === false) {
        return [
            'csv' => '',
            'rows' => 0,
            'log' => [__('Could not open memory stream for CSV.', 'poke-hub')],
        ];
    }
    fputcsv($fh, $header);

    $count = 0;
    foreach ($rows_db as $prow) {
        $pid = (int) ($prow['id'] ?? 0);
        $dex = (int) ($prow['dex_number'] ?? 0);
        $slug = (string) ($prow['slug'] ?? '');
        $extra_decoded = [];
        $raw_extra = (string) ($prow['extra'] ?? '');
        if ($raw_extra !== '') {
            if (function_exists('poke_hub_pokemon_decode_extra_json')) {
                $ok = true;
                $extra_decoded = poke_hub_pokemon_decode_extra_json($raw_extra, $ok);
                if (!is_array($extra_decoded)) {
                    $extra_decoded = [];
                }
            } else {
                $d = json_decode($raw_extra, true);
                $extra_decoded = is_array($d) ? $d : [];
            }
        }
        poke_hub_tools_manifest_fput_lines_for_pokemon($fh, $count, $pid, $dex, $slug, $sources, $args, false, $extra_decoded);
    }

    $synth_added = 0;
    if (!empty($args['include_synthetic_gigantamax']) && function_exists('poke_hub_pokemon_gigantamax_fetch_synthetic_base_candidate_rows') && function_exists('poke_hub_pokemon_gigantamax_build_synthetic_data_from_base')) {
        foreach (poke_hub_pokemon_gigantamax_fetch_synthetic_base_candidate_rows() as $base) {
            $data = poke_hub_pokemon_gigantamax_build_synthetic_data_from_base($base);
            $g_slug = (string) ($data['slug'] ?? '');
            $g_dex = (int) ($data['dex_number'] ?? 0);
            $base_pid = (int) ($data['gigantamax_base_pokemon_id'] ?? 0);
            if ($base_pid <= 0) {
                $base_pid = (int) ($base['id'] ?? 0);
            }
            $extra_synth = [];
            $raw_b = (string) ($base['extra'] ?? '');
            if ($raw_b !== '') {
                if (function_exists('poke_hub_pokemon_decode_extra_json')) {
                    $okb = true;
                    $extra_synth = poke_hub_pokemon_decode_extra_json($raw_b, $okb);
                    if (!is_array($extra_synth)) {
                        $extra_synth = [];
                    }
                } else {
                    $db = json_decode($raw_b, true);
                    $extra_synth = is_array($db) ? $db : [];
                }
            }
            $before = $count;
            poke_hub_tools_manifest_fput_lines_for_pokemon($fh, $count, $base_pid, $g_dex, $g_slug, $sources, $args, true, $extra_synth);
            $synth_added += ($count - $before);
        }
    }

    rewind($fh);
    $csv = (string) stream_get_contents($fh);
    fclose($fh);
    $log = [
        sprintf(
            /* translators: %d: number of data rows (excluding header). */
            __('Generated manifest: %d data rows (all Pokémon rows from DB: normal, mega, costumes, etc. via slug; shiny duplicated whenever enabled, regardless of in-game shiny release; gender variants when data allows).', 'poke-hub'),
            $count
        ),
        sprintf(
            /* translators: %d: synthetic gigantamax lines added. */
            __('Synthetic Gigantamax rows added: %d (same slug rules as collections).', 'poke-hub'),
            $synth_added
        ),
        __('Mega and real Gigantamax forms that exist as their own DB row are already in the main list. Set URL templates to your image source; row count can be large.', 'poke-hub'),
        __('Column pogo_stem defaults to pokemon_icon_{dex3}_00 (folder Pokemon - 256x256). Column pogo_aa_base defaults to pm{dex} (folder Pokemon/Addressable Assets). Refine both with filters poke_hub_pogo_icon_stem and poke_hub_pogo_addressable_base.', 'poke-hub'),
    ];

    return [
        'csv' => $csv,
        'rows' => $count,
        'log' => $log,
    ];
}

/**
 * Enregistre le sous-menu "Outils temporaires" sous Poké HUB.
 */
function poke_hub_admin_menu_tools() {
    if (!poke_hub_temporary_tools_enabled()) {
        return;
    }
    add_submenu_page(
        'poke-hub',
        __('Temporary tools', 'poke-hub'),
        __('Temporary tools', 'poke-hub'),
        'manage_options',
        'poke-hub-tools',
        'poke_hub_admin_tools_page'
    );
}
add_action('admin_menu', 'poke_hub_admin_menu_tools', 25);

/**
 * Affiche la page Outils temporaires (formulaires + résultat d’exécution).
 */
function poke_hub_admin_tools_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!poke_hub_temporary_tools_enabled()) {
        wp_die(
            esc_html__('Temporary tools are disabled. Enable them under Poké HUB → Settings → General.', 'poke-hub'),
            esc_html__('Temporary tools', 'poke-hub'),
            403
        );
    }

    $result = null;
    $images_defaults = [
        'manifest_csv' => '',
        'go_template' => '',
        'home_template' => '',
        'local_source_dir' => '',
        'skip_existing' => true,
        'timeout' => 30,
        'include_go' => true,
        'include_home' => true,
        'include_shiny_variants' => true,
        'include_gender_variants' => true,
        'include_synthetic_gigantamax' => true,
        'manifest_limit' => 0,
    ];

    $run_pokekalos = isset($_POST['poke_hub_tools_pokekalos']) && $_POST['poke_hub_tools_pokekalos'] === '1';
    if ($run_pokekalos && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'poke_hub_tools_pokekalos')) {
        $dry_run       = !empty($_POST['poke_hub_pokekalos_dry_run']);
        $limit         = isset($_POST['poke_hub_pokekalos_limit']) ? (int) $_POST['poke_hub_pokekalos_limit'] : 0;
        $delay         = isset($_POST['poke_hub_pokekalos_delay']) ? (int) $_POST['poke_hub_pokekalos_delay'] : 1;
        $skip_existing = !empty($_POST['poke_hub_pokekalos_skip_existing']);
        if ($delay < 0) {
            $delay = 1;
        }
        set_time_limit(0);
        $result = poke_hub_run_pokekalos_import([
            'dry_run'       => $dry_run,
            'limit'         => $limit,
            'delay'         => $delay,
            'skip_existing' => $skip_existing,
        ]);
    }

    $img_action = isset($_POST['poke_hub_images_action']) ? sanitize_key((string) wp_unslash((string) $_POST['poke_hub_images_action'])) : '';
    if ($img_action !== '' && isset($_POST['_wpnonce']) && wp_verify_nonce((string) wp_unslash((string) $_POST['_wpnonce']), 'poke_hub_tools_images_sync')) {
        $images_defaults['manifest_csv'] = isset($_POST['poke_hub_images_manifest_csv']) ? (string) wp_unslash($_POST['poke_hub_images_manifest_csv']) : '';
        $images_defaults['go_template'] = isset($_POST['poke_hub_images_go_template']) ? (string) wp_unslash($_POST['poke_hub_images_go_template']) : '';
        $images_defaults['home_template'] = isset($_POST['poke_hub_images_home_template']) ? (string) wp_unslash($_POST['poke_hub_images_home_template']) : '';
        $images_defaults['local_source_dir'] = isset($_POST['poke_hub_images_local_source_dir']) ? (string) wp_unslash($_POST['poke_hub_images_local_source_dir']) : '';
        $images_defaults['skip_existing'] = !empty($_POST['poke_hub_images_skip_existing']);
        $images_defaults['timeout'] = isset($_POST['poke_hub_images_timeout']) ? max(5, (int) $_POST['poke_hub_images_timeout']) : 30;
        $images_defaults['include_go'] = !empty($_POST['poke_hub_images_include_go']);
        $images_defaults['include_home'] = !empty($_POST['poke_hub_images_include_home']);
        $images_defaults['include_shiny_variants'] = !empty($_POST['poke_hub_images_include_shiny']);
        $images_defaults['include_gender_variants'] = !empty($_POST['poke_hub_images_include_gender']);
        $images_defaults['include_synthetic_gigantamax'] = !empty($_POST['poke_hub_images_include_synthetic_gmax']);
        $images_defaults['manifest_limit'] = isset($_POST['poke_hub_images_manifest_limit']) ? max(0, (int) $_POST['poke_hub_images_manifest_limit']) : 0;

        set_time_limit(0);
        if ($img_action === 'download') {
            $result = poke_hub_tools_download_images_from_manifest([
                'manifest_csv' => $images_defaults['manifest_csv'],
                'go_template' => $images_defaults['go_template'],
                'home_template' => $images_defaults['home_template'],
                'skip_existing' => $images_defaults['skip_existing'],
                'timeout' => $images_defaults['timeout'],
            ]);
        } elseif ($img_action === 'local-sync') {
            $result = poke_hub_tools_copy_images_from_local_manifest([
                'manifest_csv' => $images_defaults['manifest_csv'],
                'source_dir' => $images_defaults['local_source_dir'],
                'skip_existing' => $images_defaults['skip_existing'],
            ]);
        } elseif ($img_action === 'generate') {
            $gen = poke_hub_tools_build_manifest_csv_from_db([
                'include_go' => $images_defaults['include_go'],
                'include_home' => $images_defaults['include_home'],
                'include_shiny_variants' => $images_defaults['include_shiny_variants'],
                'include_gender_variants' => $images_defaults['include_gender_variants'],
                'include_synthetic_gigantamax' => $images_defaults['include_synthetic_gigantamax'],
                'limit' => $images_defaults['manifest_limit'],
            ]);
            $images_defaults['manifest_csv'] = $gen['csv'];
            $result = ['log' => $gen['log']];
        }
    }

    $run_images_zip = isset($_POST['poke_hub_tools_images_zip']) && $_POST['poke_hub_tools_images_zip'] === '1';
    if ($run_images_zip && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'poke_hub_tools_images_zip')) {
        $result = poke_hub_tools_create_gamemaster_zip();
    }

    $events_on  = function_exists('poke_hub_is_module_active') && poke_hub_is_module_active('events');
    $pokemon_on = function_exists('poke_hub_is_module_active') && poke_hub_is_module_active('pokemon');

    $tab_defs = [
        'pokekalos' => [
            'label' => __('Dates (Pokekalos)', 'poke-hub'),
            'show'  => true,
        ],
        'raid-hour' => [
            'label' => __('Heure de raids (Fandom)', 'poke-hub'),
            'show'  => $events_on && function_exists('pokehub_fandom_recurring_render_card'),
        ],
        'spotlight-hour' => [
            'label' => __('Heure vedette (Fandom)', 'poke-hub'),
            'show'  => $events_on && function_exists('pokehub_fandom_recurring_render_card'),
        ],
        'max-monday' => [
            'label' => __('Lundi Max (Fandom)', 'poke-hub'),
            'show'  => $events_on && function_exists('pokehub_render_max_monday_import_section'),
        ],
        'gamemaster' => [
            'label' => __('Game Master', 'poke-hub'),
            'show'  => $pokemon_on,
        ],
        'translation' => [
            'label' => __('Translation', 'poke-hub'),
            'show'  => $pokemon_on,
        ],
        'images-sync' => [
            'label' => __('Images sync', 'poke-hub'),
            'show'  => $pokemon_on,
        ],
    ];

    $allowed_tabs = array_keys(array_filter($tab_defs, static function (array $d): bool {
        return !empty($d['show']);
    }));

    $current_tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash((string) $_GET['tab'])) : '';
    if ($current_tab === '' || !in_array($current_tab, $allowed_tabs, true)) {
        $current_tab = $allowed_tabs[0] ?? 'pokekalos';
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Temporary tools', 'poke-hub'); ?></h1>
        <p class="description"><?php esc_html_e('One-off scripts (imports, migrations…). This menu can be removed once operations are complete.', 'poke-hub'); ?></p>

        <h2 class="nav-tab-wrapper" style="padding-top:8px;margin-bottom:0;border-bottom:1px solid #c3c4c7;">
            <?php foreach ($tab_defs as $slug => $def) : ?>
                <?php if (empty($def['show'])) {
                    continue;
                } ?>
                <a href="<?php echo esc_url(poke_hub_admin_tools_url($slug)); ?>"
                   class="nav-tab<?php echo $current_tab === $slug ? ' nav-tab-active' : ''; ?>"
                   id="pokehub-tools-tab-<?php echo esc_attr($slug); ?>">
                    <?php echo esc_html((string) $def['label']); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <div class="pokehub-tools-tab-panels" style="border:1px solid #c3c4c7;border-top:none;background:#fff;padding:16px 20px 24px;max-width:1100px;">
            <div id="pokehub-tools-panel-pokekalos" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'pokekalos' ? 'display:none;' : ''; ?>">
        <div class="card" style="max-width: 640px; margin-top: 0;">
            <h2 class="title"><?php esc_html_e('Import Pokekalos release dates', 'poke-hub'); ?></h2>
            <p><?php esc_html_e('Fetches release dates (normal, shiny, shadow, dynamax, gigantamax) from Pokekalos Pokémon GO Pokédex pages and updates the database. Only Pokémon with a French name are processed.', 'poke-hub'); ?></p>

            <form method="post" action="<?php echo esc_url(poke_hub_admin_tools_url('pokekalos')); ?>">
                <?php wp_nonce_field('poke_hub_tools_pokekalos'); ?>
                <input type="hidden" name="poke_hub_tools_pokekalos" value="1" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Simulation mode', 'poke-hub'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="poke_hub_pokekalos_dry_run" value="1" />
                                <?php esc_html_e('Dry-run (show results without changing the database)', 'poke-hub'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="poke_hub_pokekalos_limit"><?php esc_html_e('Number to process', 'poke-hub'); ?></label></th>
                        <td>
                            <input type="number" name="poke_hub_pokekalos_limit" id="poke_hub_pokekalos_limit" value="0" min="0" step="1" class="small-text" />
                            <span class="description"><?php esc_html_e('0 = all. Otherwise: first X rows in database order (with French name).', 'poke-hub'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Skip existing entries', 'poke-hub'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="poke_hub_pokekalos_skip_existing" value="1" />
                                <?php esc_html_e('Only Pokémon with no release date at all', 'poke-hub'); ?>
                            </label>
                            <span class="description"><?php esc_html_e('If checked: process only the first X without release date (DB order). Otherwise: process the first X overall.', 'poke-hub'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="poke_hub_pokekalos_delay"><?php esc_html_e('Delay (seconds)', 'poke-hub'); ?></label></th>
                        <td>
                            <input type="number" name="poke_hub_pokekalos_delay" id="poke_hub_pokekalos_delay" value="1" min="0" step="1" class="small-text" />
                            <span class="description"><?php esc_html_e('Between each request to Pokekalos.', 'poke-hub'); ?></span>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Run Pokekalos import', 'poke-hub'); ?></button>
                </p>
            </form>
        </div>
            </div>

        <?php if ($events_on) : ?>
            <div id="pokehub-tools-panel-raid-hour" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'raid-hour' ? 'display:none;' : ''; ?>">
                <?php
                if (function_exists('pokehub_fandom_recurring_render_card')) {
                    pokehub_fandom_recurring_render_card('raid_hour');
                }
                ?>
            </div>
            <div id="pokehub-tools-panel-spotlight-hour" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'spotlight-hour' ? 'display:none;' : ''; ?>">
                <?php
                if (function_exists('pokehub_fandom_recurring_render_card')) {
                    pokehub_fandom_recurring_render_card('spotlight_hour');
                }
                ?>
            </div>
            <div id="pokehub-tools-panel-max-monday" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'max-monday' ? 'display:none;' : ''; ?>">
                <?php
                if (function_exists('pokehub_render_max_monday_import_section')) {
                    pokehub_render_max_monday_import_section();
                }
                ?>
            </div>
        <?php endif; ?>

            <?php if ($pokemon_on) : ?>
                <div id="pokehub-tools-panel-gamemaster" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'gamemaster' ? 'display:none;' : ''; ?>">
                    <?php
                    $gm_tab_file = __DIR__ . '/settings/tabs/settings-tab-gamemaster.php';
                    if (file_exists($gm_tab_file)) {
                        require $gm_tab_file;
                    }
                    ?>
                </div>
                <div id="pokehub-tools-panel-translation" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'translation' ? 'display:none;' : ''; ?>">
                    <?php
                    if (!defined('POKE_HUB_TRANSLATION_TAB_CONTEXT')) {
                        define('POKE_HUB_TRANSLATION_TAB_CONTEXT', 'tools');
                    }
                    $tr_tab_file = __DIR__ . '/settings/tabs/settings-tab-translation.php';
                    if (file_exists($tr_tab_file)) {
                        require $tr_tab_file;
                    }
                    ?>
                </div>
                <div id="pokehub-tools-panel-images-sync" class="pokehub-tools-panel" style="<?php echo $current_tab !== 'images-sync' ? 'display:none;' : ''; ?>">
                    <div class="card" style="max-width: 980px; margin-top: 0;">
                        <h2 class="title"><?php esc_html_e('Download Pokemon images (GO / HOME)', 'poke-hub'); ?></h2>
                        <p><?php esc_html_e('Generate a manifest from your Poké HUB Pokémon table (slugs already include forms from the Game Master import), or paste/edit CSV manually. Then run download using URL templates.', 'poke-hub'); ?></p>
                        <?php
                        $gm_paths = poke_hub_tools_get_gamemaster_base_paths();
                        $gm_dir = (string) ($gm_paths['dir'] ?? '');
                        ?>
                        <p class="description"><?php echo esc_html(sprintf(__('Local storage folder: %s', 'poke-hub'), $gm_dir)); ?></p>

                        <form method="post" action="<?php echo esc_url(poke_hub_admin_tools_url('images-sync')); ?>">
                            <?php wp_nonce_field('poke_hub_tools_images_sync'); ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Automatic manifest', 'poke-hub'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label><input type="checkbox" name="poke_hub_images_include_go" value="1" <?php checked($images_defaults['include_go']); ?> /> <?php esc_html_e('Include Pokémon GO rows (source=go)', 'poke-hub'); ?></label><br />
                                            <label><input type="checkbox" name="poke_hub_images_include_home" value="1" <?php checked($images_defaults['include_home']); ?> /> <?php esc_html_e('Include HOME rows (source=home)', 'poke-hub'); ?></label><br />
                                            <label><input type="checkbox" name="poke_hub_images_include_shiny" value="1" <?php checked($images_defaults['include_shiny_variants']); ?> /> <?php esc_html_e('Duplicate rows for shiny sprites (always when checked, even if not yet released shiny in-game)', 'poke-hub'); ?></label><br />
                                            <label><input type="checkbox" name="poke_hub_images_include_gender" value="1" <?php checked($images_defaults['include_gender_variants']); ?> /> <?php esc_html_e('Duplicate rows for male/female when dimorphism is enabled in data', 'poke-hub'); ?></label><br />
                                            <label><input type="checkbox" name="poke_hub_images_include_synthetic_gmax" value="1" <?php checked($images_defaults['include_synthetic_gigantamax']); ?> /> <?php esc_html_e('Add synthetic Gigantamax rows (release date in data, no separate G-Max form row)', 'poke-hub'); ?></label><br />
                                            <label for="poke_hub_images_manifest_limit"><?php esc_html_e('Row limit (0 = all)', 'poke-hub'); ?></label>
                                            <input type="number" name="poke_hub_images_manifest_limit" id="poke_hub_images_manifest_limit" class="small-text" value="<?php echo esc_attr((string) (int) $images_defaults['manifest_limit']); ?>" min="0" step="1" />
                                        </fieldset>
                                        <p class="description"><?php esc_html_e('“Generate manifest” lists every Pokémon row (each form = one slug: normal, Mega, costumes, etc.), adds a shiny row for each whenever the shiny checkbox is on (even before official shiny release in-game), adds male/female rows when dimorphism data allows, adds synthetic Gigamax when enabled, and duplicates each line for GO and/or HOME. You still need URL templates pointing to your allowed image host.', 'poke-hub'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="poke_hub_images_manifest_csv"><?php esc_html_e('Manifest CSV', 'poke-hub'); ?></label></th>
                                    <td>
                                        <textarea name="poke_hub_images_manifest_csv" id="poke_hub_images_manifest_csv" rows="12" class="large-text code" placeholder="source,dex,slug,...,pogo_stem,pogo_aa_base,extension,url"><?php echo esc_textarea($images_defaults['manifest_csv']); ?></textarea>
                                        <p class="description"><?php esc_html_e('One row per image. If URL is empty, template URL below is used.', 'poke-hub'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="poke_hub_images_go_template"><?php esc_html_e('GO URL template', 'poke-hub'); ?></label></th>
                                    <td>
                                        <input type="text" name="poke_hub_images_go_template" id="poke_hub_images_go_template" class="large-text code" value="<?php echo esc_attr($images_defaults['go_template']); ?>" placeholder="https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Pokemon/Addressable%20Assets/{pogo_aa_file}" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="poke_hub_images_home_template"><?php esc_html_e('HOME URL template', 'poke-hub'); ?></label></th>
                                    <td>
                                        <input type="text" name="poke_hub_images_home_template" id="poke_hub_images_home_template" class="large-text code" value="<?php echo esc_attr($images_defaults['home_template']); ?>" placeholder="https://example.com/home/{stem}.png" />
                                        <p class="description"><?php esc_html_e('Placeholders: …, {pogo_stem}, {pogo_gender_infix}, {pogo_shiny_infix}, {pogo_file} (256×256 pokemon_icon_*), and {pogo_aa_base}, {pogo_aa_gender_infix}, {pogo_aa_file} (Addressable Assets: pm25 / pm25.g2.icon.png / pm25.g2.s.icon.png). Recommended GO raw URL: https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Pokemon/Addressable%20Assets/{pogo_aa_file}', 'poke-hub'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="poke_hub_images_local_source_dir"><?php esc_html_e('Local assets folder', 'poke-hub'); ?></label></th>
                                    <td>
                                        <input type="text" name="poke_hub_images_local_source_dir" id="poke_hub_images_local_source_dir" class="large-text code" value="<?php echo esc_attr($images_defaults['local_source_dir']); ?>" placeholder="C:\path\to\pogo_assets\Images\Pokemon\Addressable Assets" />
                                        <p class="description"><?php esc_html_e('Used by "Run local rename/copy". The tool scans this folder recursively, matches files like {pogo_aa_file} (or optional CSV column local_file), then copies and renames to Poké HUB slugs. Missing matches are listed in logs and exported as CSV in uploads/poke-hub/exports.', 'poke-hub'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Skip existing', 'poke-hub'); ?></th>
                                    <td>
                                        <label><input type="checkbox" name="poke_hub_images_skip_existing" value="1" <?php checked($images_defaults['skip_existing']); ?> /> <?php esc_html_e('Do not overwrite files already present in uploads/poke-hub/gamemaster.', 'poke-hub'); ?></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="poke_hub_images_timeout"><?php esc_html_e('HTTP timeout (seconds)', 'poke-hub'); ?></label></th>
                                    <td>
                                        <input type="number" name="poke_hub_images_timeout" id="poke_hub_images_timeout" value="<?php echo esc_attr((string) (int) $images_defaults['timeout']); ?>" min="5" step="1" class="small-text" />
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" name="poke_hub_images_action" value="generate" class="button"><?php esc_html_e('Generate manifest from database', 'poke-hub'); ?></button>
                                <button type="submit" name="poke_hub_images_action" value="download" class="button button-primary"><?php esc_html_e('Run image download', 'poke-hub'); ?></button>
                                <button type="submit" name="poke_hub_images_action" value="local-sync" class="button"><?php esc_html_e('Run local rename/copy', 'poke-hub'); ?></button>
                            </p>
                        </form>

                        <hr />
                        <form method="post" action="<?php echo esc_url(poke_hub_admin_tools_url('images-sync')); ?>">
                            <?php wp_nonce_field('poke_hub_tools_images_zip'); ?>
                            <input type="hidden" name="poke_hub_tools_images_zip" value="1" />
                            <p class="submit" style="margin-top: 0;">
                                <button type="submit" class="button"><?php esc_html_e('Create and download ZIP', 'poke-hub'); ?></button>
                            </p>
                            <p class="description"><?php esc_html_e('Creates a ZIP from uploads/poke-hub/gamemaster and stores it in uploads/poke-hub/exports.', 'poke-hub'); ?></p>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($result !== null) : ?>
            <div class="card" style="max-width: 900px; margin-top: 20px;">
                <h3><?php esc_html_e('Result', 'poke-hub'); ?></h3>
                <?php if (!empty($result['download_url'])) : ?>
                    <p>
                        <a class="button button-secondary" href="<?php echo esc_url((string) $result['download_url']); ?>">
                            <?php
                            $label = !empty($result['download_label']) ? (string) $result['download_label'] : __('Download ZIP', 'poke-hub');
                            echo esc_html($label);
                            ?>
                        </a>
                    </p>
                <?php endif; ?>
                <pre style="background: #f5f5f5; padding: 12px; overflow: auto; max-height: 400px; white-space: pre-wrap;"><?php
                    echo esc_html(implode("\n", $result['log']));
                ?></pre>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
