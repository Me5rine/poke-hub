<?php
/**
 * Imports Fandom : Heure de raids (Raid Hour) et Heure vedette (Pokémon Spotlight Hour).
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/fandom-import-helpers.php';

/**
 * @return array<string, array{page:string, event_type:string, title_en:string, title_fr:string, label:string}>
 */
function pokehub_fandom_recurring_import_defs(): array {
    return [
        'raid_hour' => [
            'page'       => 'Legendary_Raid_Hour',
            'event_type' => 'raid-hour',
            'title_en'   => 'Raid Hour',
            'title_fr'   => 'Heure de raids',
            'label'      => __('Heure de raids — Raid Hour (Fandom)', 'poke-hub'),
        ],
        'spotlight_hour' => [
            'page'       => 'Pokémon_Spotlight_Hour',
            'event_type' => 'pokemon-spotlight-hour',
            'title_en'   => 'Pokémon Spotlight Hour',
            'title_fr'   => 'Heure vedette Pokémon',
            'label'      => __('Heure vedette Pokémon — Spotlight Hour (Fandom)', 'poke-hub'),
        ],
    ];
}

/**
 * URL de la page Outils temporaires ; avec $kind, conserve l’onglet (raid / spotlight).
 */
function pokehub_fandom_recurring_tools_url(string $kind = ''): string {
    if ($kind === '') {
        return function_exists('poke_hub_admin_tools_url')
            ? poke_hub_admin_tools_url()
            : admin_url('admin.php?page=poke-hub-tools');
    }
    $tab = ($kind === 'spotlight_hour') ? 'spotlight-hour' : (($kind === 'raid_hour') ? 'raid-hour' : '');
    if ($tab !== '' && function_exists('poke_hub_admin_tools_url')) {
        return poke_hub_admin_tools_url($tab);
    }
    if ($tab !== '') {
        return add_query_arg('tab', $tab, admin_url('admin.php?page=poke-hub-tools'));
    }
    return function_exists('poke_hub_admin_tools_url')
        ? poke_hub_admin_tools_url()
        : admin_url('admin.php?page=poke-hub-tools');
}

function pokehub_fandom_recurring_wikitext_key(string $kind): string {
    return 'pokehub_fr_wt_' . sanitize_key($kind) . '_' . get_current_user_id();
}

function pokehub_fandom_recurring_error_key(string $kind): string {
    return 'pokehub_fr_er_' . sanitize_key($kind) . '_' . get_current_user_id();
}

/**
 * Extrait les attaques événement depuis le bloc wiki (knew / chance to know + [[Move]]).
 *
 * @param string[] $wiki_names Noms EN wiki des Pokémon (ordre).
 * @return array<int, array<int, array{label:string, forced:bool}>> pokemon_index => moves
 */
function pokehub_fandom_recurring_parse_moves_blob(string $blob, array $wiki_names): array {
    $out = [];
    foreach (array_keys($wiki_names) as $i) {
        $out[$i] = [];
    }
    if ($wiki_names === []) {
        return $out;
    }

    $blob = str_replace(["\r\n", "\r"], "\n", $blob);
    $blob = preg_replace('#<br\s*/?>#i', "\n", $blob) ?? $blob;

    $move_label = static function (string $raw): string {
        return pokehub_fandom_wiki_attack_link_to_label($raw);
    };

    $append = static function (array &$out, int $idx, string $mv, bool $forced): void {
        if (!isset($out[$idx])) {
            $out[$idx] = [];
        }
        foreach ($out[$idx] as $ex) {
            if (strcasecmp((string) ($ex['label'] ?? ''), $mv) === 0) {
                return;
            }
        }
        $out[$idx][] = ['label' => $mv, 'forced' => $forced];
    };

    $append_all = static function (array &$out, array $wiki_names, string $mv, bool $forced) use ($append): void {
        foreach (array_keys($wiki_names) as $ti) {
            $append($out, (int) $ti, $mv, $forced);
        }
    };

    // « Reshiram knew [[Fusion Flare]] »
    if (preg_match_all('/([\p{L}\d][\p{L}\d\s\'\-\x{00B7}:]{0,50}?)\s+knew\s*\[\[([^\]]+)\]\]/u', $blob, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $who = trim($m[1]);
            $mv  = $move_label(trim($m[2]));
            if ($mv === '') {
                continue;
            }
            $idx = pokehub_fandom_recurring_match_wiki_name_index($who, $wiki_names);
            if ($idx !== null) {
                $append($out, $idx, $mv, true);
            }
        }
    }

    // « Reshiram knew Fusion Flare » (sans [[ ]])
    if (preg_match_all('/([\p{L}\d][\p{L}\d\s\'\-\x{00B7}:]{0,50}?)\s+knew\s+([^\[\n][^\n]*)/u', $blob, $pm, PREG_SET_ORDER)) {
        foreach ($pm as $m) {
            $who = trim($m[1]);
            $mv  = pokehub_fandom_move_plain_tail_to_label(trim($m[2]));
            if ($mv === '') {
                continue;
            }
            $idx = pokehub_fandom_recurring_match_wiki_name_index($who, $wiki_names);
            if ($idx !== null) {
                $append($out, $idx, $mv, true);
            }
        }
    }

    // « Kyurem chance to know [[Glaciate]] » ; sans Pokémon → tous les Pokémon de la ligne (trios, etc.)
    if (preg_match_all(
        '/(?:([\p{L}\d][\p{L}\d\s\'\-\x{00B7}:]{0,50}?)\s+)?chance(?:\s+to)?\s+know(?:n)?\s*\[\[([^\]]+)\]\]/iu',
        $blob,
        $cm,
        PREG_SET_ORDER
    )) {
        foreach ($cm as $m) {
            $who = isset($m[1]) ? trim((string) $m[1]) : '';
            $mv  = $move_label(trim((string) $m[2]));
            if ($mv === '') {
                continue;
            }
            $idx = null;
            if ($who !== '') {
                $idx = pokehub_fandom_recurring_match_wiki_name_index($who, $wiki_names);
            }
            if ($idx !== null) {
                $append($out, $idx, $mv, false);
            } else {
                $append_all($out, $wiki_names, $mv, false);
            }
        }
    }

    // Lignes du type « knew [[Move]] » (sans nom de Pokémon devant) ou « Knew Mind Blown » (texte seul)
    foreach (preg_split('/\n+/u', $blob) as $raw_line) {
        $line = trim($raw_line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^\s*knew\s*\[\[([^\]]+)\]\]/iu', $line, $km)) {
            $mv = $move_label(trim($km[1]));
            if ($mv !== '') {
                $append_all($out, $wiki_names, $mv, true);
            }
            continue;
        }

        if (preg_match('/^\s*(chance(?:\s+to)?\s+know(?:n)?)\s+(.+)$/iu', $line, $xm)) {
            $mv = pokehub_fandom_move_plain_tail_to_label(trim($xm[2]));
            if ($mv !== '' && strpos($line, '[[') === false) {
                $append_all($out, $wiki_names, $mv, false);
            }
            continue;
        }

        if (preg_match('/^\s*knew\s+/iu', $line) && !preg_match('/\S.+\s+knew\s*\[\[/u', $line)) {
            if (preg_match('/^\s*knew\s+(.+)$/iu', $line, $lm)) {
                $mv = pokehub_fandom_move_plain_tail_to_label(trim($lm[1]));
                if ($mv !== '') {
                    $append_all($out, $wiki_names, $mv, true);
                }
            }
        }
    }

    return $out;
}

/**
 * @param string[] $wiki_names
 */
function pokehub_fandom_recurring_match_wiki_name_index(string $fragment, array $wiki_names): ?int {
    $fragment = trim(html_entity_decode($fragment, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($fragment === '') {
        return null;
    }
    $frag_mods = pokehub_fandom_split_pokemon_wiki_mods($fragment);
    $fl         = pokehub_fandom_normalize_compare_string($fragment);
    $fb         = pokehub_fandom_normalize_compare_string($frag_mods['label']);
    foreach ($wiki_names as $i => $name) {
        $nl = pokehub_fandom_normalize_compare_string((string) $name);
        if ($nl !== '' && (strpos($fl, $nl) !== false || strpos($nl, $fl) !== false)) {
            return (int) $i;
        }
        $mods = pokehub_fandom_split_pokemon_wiki_mods((string) $name);
        $nb   = pokehub_fandom_normalize_compare_string($mods['label']);
        if ($nb !== '' && ($fb !== '') && (strpos($fb, $nb) !== false || strpos($nb, $fb) !== false)) {
            return (int) $i;
        }
        if ($nb !== '' && $fb !== '' && (strpos($fl, $nb) !== false || strpos($nb, $fl) !== false)) {
            return (int) $i;
        }
    }
    return null;
}

/**
 * Découpe une ligne de tableau legacy (même principe que Max Monday).
 *
 * @return array<int, string>|null [date_raw, feat_raw, bonus_cell, notes_combined]
 */
function pokehub_fandom_recurring_split_row_cells(array $cells, string $kind): ?array {
    $cells = array_values(array_map('trim', $cells));
    $n     = count($cells);

    if ($kind === 'spotlight_hour') {
        if ($n >= 3) {
            return [$cells[0], $cells[1], $cells[2], ''];
        }
        if ($n === 2) {
            return [$cells[0], $cells[1], $cells[1], ''];
        }
        return null;
    }

    if ($kind === 'raid_hour') {
        if ($n >= 4 && preg_match('/^\d+$/', $cells[0])) {
            return [$cells[1], $cells[2], '', ($cells[3] ?? '')];
        }
        if ($n === 3 && preg_match('/^\d+$/', $cells[0])) {
            return [$cells[1], $cells[2], '', ''];
        }
        if ($n === 2 && preg_match('/^\d+$/', $cells[0])) {
            return [$cells[1], '', '', ''];
        }
        if ($n >= 3 && !preg_match('/^\d+$/', $cells[0])) {
            return [$cells[0], $cells[1], '', ($cells[2] ?? '')];
        }
        if ($n === 2 && !preg_match('/^\d+$/', $cells[0])) {
            return [$cells[0], $cells[1], '', ''];
        }
    }

    return null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function pokehub_fandom_recurring_parse_wikitext(string $wikitext, string $kind): array {
    global $wpdb;

    $defs = pokehub_fandom_recurring_import_defs();
    if (!isset($defs[$kind])) {
        return [];
    }
    $event_type = (string) $defs[$kind]['event_type'];
    $pokemon_table = pokehub_get_table('pokemon');
    $events_table  = pokehub_get_table('special_events');

    $pairs = preg_split('/^===\s*(\d{4})\s*===\s*$/m', $wikitext, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($pairs) < 3) {
        $pairs = preg_split('/^###\s*(\d{4})\s*$/m', $wikitext, -1, PREG_SPLIT_DELIM_CAPTURE);
    }
    if (count($pairs) < 3) {
        $fallback_y = (int) gmdate('Y');
        if (preg_match('/\b(20\d{2})\b/', $wikitext, $gy)) {
            $fallback_y = (int) $gy[1];
        }
        $pairs = ['', $fallback_y, $wikitext];
    }
    $entries = [];

    for ($i = 1; $i + 1 < count($pairs); $i += 2) {
        $year = (int) $pairs[$i];
        $section = (string) $pairs[$i + 1];
        if ($year < 2000 || $year > 2100) {
            continue;
        }

        $table_inners = pokehub_fandom_extract_legacy_wiki_tables($section);
        if ($table_inners === []) {
            continue;
        }

        foreach ($table_inners as $tableInner) {
            $chunks = preg_split('/\n\|-\s*\n/', $tableInner);
            foreach ($chunks as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '' || strpos($chunk, '!') === 0) {
                    continue;
                }
                if (!preg_match_all('/^\|\s*(.*)$/m', $chunk, $cm)) {
                    continue;
                }
                $cells = array_map('trim', $cm[1]);
                $split = pokehub_fandom_recurring_split_row_cells($cells, $kind);
                if ($split === null) {
                    continue;
                }

                [$date_raw, $feat_raw, $bonus_cell, $notes_raw] = $split;
                $parsed_date = pokehub_fandom_parse_event_date_cell($date_raw, $year);
                if ($parsed_date === null) {
                    continue;
                }

                [$y, $mon, $day] = $parsed_date;
                $ymd = sprintf('%04d-%02d-%02d', $y, $mon, $day);

                $skip          = false;
                $skip_reason   = '';
                $raw_labels    = pokehub_fandom_extract_i_template_names($feat_raw . ' ' . $notes_raw);
                if ($kind === 'raid_hour' && $raw_labels === []) {
                    $raw_labels = pokehub_fandom_extract_i_template_names($feat_raw);
                }
                foreach (pokehub_fandom_extract_wiki_link_display_texts($feat_raw) as $link_disp) {
                    if ($link_disp !== '' && !in_array($link_disp, $raw_labels, true)) {
                        $raw_labels[] = $link_disp;
                    }
                }
                foreach (pokehub_fandom_extract_wiki_plain_pokemon_labels($feat_raw) as $plain_lbl) {
                    if ($plain_lbl !== '' && !in_array($plain_lbl, $raw_labels, true)) {
                        $raw_labels[] = $plain_lbl;
                    }
                }

                $spec_list = [];
                $seen_spec = [];
                foreach ($raw_labels as $raw_w) {
                    $mods = pokehub_fandom_split_pokemon_wiki_mods($raw_w);
                    if (trim($mods['label']) === '') {
                        continue;
                    }
                    $dedupe_key = pokehub_fandom_normalize_compare_string($mods['label'])
                        . '|' . ($mods['force_shadow'] ? '1' : '0')
                        . '|' . ($mods['force_shiny'] ? '1' : '0');
                    if (isset($seen_spec[$dedupe_key])) {
                        continue;
                    }
                    $seen_spec[$dedupe_key] = true;
                    $spec_list[]           = [
                        'raw_wiki'     => trim($raw_w),
                        'resolve_name' => $mods['label'],
                        'force_shadow' => $mods['force_shadow'],
                        'force_shiny'  => $mods['force_shiny'],
                    ];
                }

                $pokemon_rows = [];
                foreach ($spec_list as $sp) {
                    $wen = (string) $sp['resolve_name'];
                    $pid = pokehub_fandom_resolve_pokemon_id_from_wiki_label($wen);
                    if ($pid <= 0) {
                        $skip = true;
                        $skip_reason = sprintf(
                            __('Pokémon « %s » introuvable (name_en).', 'poke-hub'),
                            $wen
                        );
                        break;
                    }
                    $name_fr = (string) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COALESCE(NULLIF(TRIM(name_fr), ''), TRIM(name_en)) FROM {$pokemon_table} WHERE id = %d",
                            $pid
                        )
                    );
                    $wiki_disp = $sp['raw_wiki'] !== '' ? (string) $sp['raw_wiki'] : $wen;
                    $pokemon_rows[] = [
                        'wiki'                  => $wiki_disp,
                        'id'                    => $pid,
                        'name_fr'               => $name_fr,
                        'force_shadow'          => !empty($sp['force_shadow']) ? 1 : 0,
                        'force_shiny'           => !empty($sp['force_shiny']) ? 1 : 0,
                        'region_note'           => '',
                        'is_worldwide_override' => 0,
                    ];
                }

                if ($kind === 'raid_hour' && $pokemon_rows !== []) {
                    $reg_map       = pokehub_fandom_parse_regional_distribution_notes($notes_raw);
                    $has_regional  = $reg_map !== [];
                    foreach ($pokemon_rows as &$pr) {
                        $pr['region_note']           = pokehub_fandom_match_region_note_for_wiki((string) ($pr['wiki'] ?? ''), $reg_map);
                        $pr['is_worldwide_override'] = $has_regional ? 0 : 1;
                    }
                    unset($pr);
                }

                if ($kind === 'spotlight_hour' || $kind === 'raid_hour') {
                    if (!$skip && count($pokemon_rows) === 0) {
                        $skip = true;
                        $skip_reason = __('Aucun {{I|Pokémon}} exploitable sur la ligne.', 'poke-hub');
                    }
                }

                $bonus_payload = [];
                if ($kind === 'spotlight_hour') {
                    $parsed_b = pokehub_spotlight_parse_bonus_lines($bonus_cell);
                    $bonus_payload = pokehub_spotlight_bonus_rows_to_ids($parsed_b);
                    if (!$skip) {
                        $has_ok = false;
                        foreach ($bonus_payload as $bp) {
                            if (!empty($bp['bonus_id'])) {
                                $has_ok = true;
                                break;
                            }
                        }
                        $bonus_heuristic = strtolower(
                            pokehub_fandom_wiki_cell_to_search_plain($bonus_cell . ' ' . $feat_raw)
                        );
                        if (
                            !$has_ok
                            && preg_match(
                                '/\b(double|quadruple|2x|4x|2×|4×)\b/i',
                                $bonus_heuristic
                            )
                        ) {
                            $skip       = true;
                            $skip_reason = __('Bonus wiki non reconnu (mapping à étendre).', 'poke-hub');
                            foreach ($bonus_payload as $bp) {
                                if (empty($bp['bonus_id']) && !empty($bp['label'])) {
                                    $skip_reason = (string) $bp['label'];
                                    break;
                                }
                            }
                        }
                    }
                }

                $blob_moves = $feat_raw . ' ' . $notes_raw;
                $wiki_only  = array_column($pokemon_rows, 'wiki');
                $moves_by_idx = pokehub_fandom_recurring_parse_moves_blob($blob_moves, $wiki_only);

                $start_ts = 0;
                $end_ts   = 0;
                if (!$skip && function_exists('poke_hub_special_event_parse_date_time_for_save')) {
                    $start_ts = poke_hub_special_event_parse_date_time_for_save($ymd, '18:00', 'local');
                    $end_ts   = poke_hub_special_event_parse_date_time_for_save($ymd, '19:00', 'local');
                }

                $exists = false;
                if (!$skip && $start_ts > 0 && $events_table) {
                    $exists = (bool) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM {$events_table} WHERE event_type = %s AND start_ts = %d LIMIT 1",
                            $event_type,
                            $start_ts
                        )
                    );
                }

                $entries[] = [
                    'kind'          => $kind,
                    'ymd'           => $ymd,
                    'start_ts'      => $start_ts,
                    'end_ts'        => $end_ts,
                    'event_type'    => $event_type,
                    'pokemon_rows'  => $pokemon_rows,
                    'moves_by_idx'  => $moves_by_idx,
                    'bonuses'       => $bonus_payload,
                    'skip'          => $skip,
                    'skip_reason'   => $skip_reason,
                    'exists'        => $exists,
                    'raw_date'      => $date_raw,
                    'raw_featured'  => $feat_raw,
                ];
            }
        }
    }

    return $entries;
}

/**
 * @param array<string, mixed> $row
 * @return int|string
 */
function pokehub_fandom_recurring_insert_one(array $row) {
    global $wpdb;

    if (!empty($row['skip'])) {
        return __('Ligne non importable.', 'poke-hub');
    }
    if (empty($row['pokemon_rows']) || !is_array($row['pokemon_rows'])) {
        return __('Aucun Pokémon.', 'poke-hub');
    }

    $defs = pokehub_fandom_recurring_import_defs();
    $kind = (string) ($row['kind'] ?? '');
    if (!isset($defs[$kind])) {
        return __('Type inconnu.', 'poke-hub');
    }
    $base_en = (string) $defs[$kind]['title_en'];
    $base_fr = (string) $defs[$kind]['title_fr'];

    $ymd = (string) $row['ymd'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return __('Date invalide.', 'poke-hub');
    }

    $start_ts = (int) ($row['start_ts'] ?? 0);
    $end_ts   = (int) ($row['end_ts'] ?? 0);
    if ($start_ts <= 0 || $end_ts <= 0) {
        if (function_exists('poke_hub_special_event_parse_date_time_for_save')) {
            $start_ts = poke_hub_special_event_parse_date_time_for_save($ymd, '18:00', 'local');
            $end_ts   = poke_hub_special_event_parse_date_time_for_save($ymd, '19:00', 'local');
        }
    }
    if ($start_ts <= 0 || $end_ts <= 0) {
        return __('Impossible de calculer les horodatages.', 'poke-hub');
    }

    $event_type = (string) $row['event_type'];
    $events_table = pokehub_get_table('special_events');
    $dup          = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$events_table} WHERE event_type = %s AND start_ts = %d LIMIT 1",
            $event_type,
            $start_ts
        )
    );
    if ($dup > 0) {
        return __('Doublon : événement déjà présent à cette date/heure.', 'poke-hub');
    }

    $pokemon_rows = is_array($row['pokemon_rows'] ?? null) ? $row['pokemon_rows'] : [];
    $wiki_labels  = array_column($pokemon_rows, 'wiki');
    $title_en     = $base_en . ' – ' . implode(', ', $wiki_labels);
    $title_fr     = $base_fr . ' – ' . implode(
        ', ',
        array_map(
            static function (array $r): string {
                return (string) ($r['name_fr'] ?? $r['wiki'] ?? '');
            },
            $pokemon_rows
        )
    );

    if (!function_exists('pokehub_generate_unique_event_slug')) {
        if (!function_exists('pokehub_ensure_events_admin_helpers_loaded') || !pokehub_ensure_events_admin_helpers_loaded()) {
            return __('Slug helper indisponible.', 'poke-hub');
        }
    }
    $slug = pokehub_generate_unique_event_slug($title_en, 0);

    $inserted = $wpdb->insert(
        $events_table,
        [
            'slug'                    => $slug,
            'title'                   => $title_en,
            'title_en'                => $title_en,
            'title_fr'                => $title_fr,
            'description'             => '',
            'event_type'              => $event_type,
            'start_ts'                => $start_ts,
            'end_ts'                  => $end_ts,
            'mode'                    => 'local',
            'recurring'               => 0,
            'recurring_freq'          => 'weekly',
            'recurring_interval'      => 1,
            'recurring_window_end_ts' => 0,
            'image_id'                => null,
            'image_url'               => '',
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%d', '%s']
    );

    if (!$inserted) {
        return __('Échec insertion SQL (special_events).', 'poke-hub');
    }

    $event_id = (int) $wpdb->insert_id;
    if ($event_id <= 0) {
        return __('ID événement invalide.', 'poke-hub');
    }

    $event_pokemon_table = pokehub_get_table('special_event_pokemon');
    $atk_table           = pokehub_get_table('special_event_pokemon_attacks');
    $bonus_table         = pokehub_get_table('special_event_bonus');

    $moves_by_idx = is_array($row['moves_by_idx'] ?? null) ? $row['moves_by_idx'] : [];

    foreach ($pokemon_rows as $idx => $pr) {
        $pid = (int) ($pr['id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $wpdb->insert(
            $event_pokemon_table,
            [
                'event_id'              => $event_id,
                'pokemon_id'            => $pid,
                'is_forced_shadow'      => (int) ($pr['force_shadow'] ?? 0),
                'is_forced_shiny'       => (int) ($pr['force_shiny'] ?? 0),
                'is_worldwide_override' => (int) ($pr['is_worldwide_override'] ?? 0),
                'region_note'           => (string) ($pr['region_note'] ?? ''),
            ],
            ['%d', '%d', '%d', '%d', '%d', '%s']
        );

        $moves = $moves_by_idx[$idx] ?? [];
        foreach ($moves as $mv) {
            $label  = (string) ($mv['label'] ?? '');
            $forced = !empty($mv['forced']);
            $aid    = pokehub_fandom_resolve_event_move_id($pid, $label);
            if ($aid <= 0) {
                continue;
            }
            $wpdb->insert(
                $atk_table,
                [
                    'event_id'   => $event_id,
                    'pokemon_id' => $pid,
                    'attack_id'  => $aid,
                    'is_forced'  => $forced ? 1 : 0,
                ],
                ['%d', '%d', '%d', '%d']
            );
        }
    }

    $bonuses = is_array($row['bonuses'] ?? null) ? $row['bonuses'] : [];
    foreach ($bonuses as $b) {
        $bid = (int) ($b['bonus_id'] ?? 0);
        if ($bid <= 0) {
            continue;
        }
        $wpdb->insert(
            $bonus_table,
            [
                'event_id'    => $event_id,
                'bonus_id'    => $bid,
                'description' => (string) ($b['description'] ?? ''),
            ],
            ['%d', '%d', '%s']
        );
    }

    if (function_exists('pokehub_content_sync_dates_for_source')) {
        pokehub_content_sync_dates_for_source('special_event', $event_id, $start_ts, $end_ts);
    }

    return $event_id;
}

function pokehub_fandom_recurring_handle_tools_post(): void {
    if (!is_admin() || (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST')) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!function_exists('poke_hub_temporary_tools_enabled') || !poke_hub_temporary_tools_enabled()) {
        return;
    }
    $page = isset($_REQUEST['page']) ? sanitize_key(wp_unslash((string) $_REQUEST['page'])) : '';
    if ($page !== 'poke-hub-tools') {
        return;
    }
    if (empty($_POST['pokehub_fr_action']) || empty($_POST['pokehub_fr_nonce']) || empty($_POST['pokehub_fr_kind'])) {
        return;
    }
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['pokehub_fr_nonce'])), 'pokehub_fandom_recurring_tools')) {
        return;
    }

    $kind   = sanitize_key((string) wp_unslash((string) $_POST['pokehub_fr_kind']));
    $defs   = pokehub_fandom_recurring_import_defs();
    $action = sanitize_key((string) wp_unslash((string) $_POST['pokehub_fr_action']));
    $base   = pokehub_fandom_recurring_tools_url($kind);
    $wkey   = pokehub_fandom_recurring_wikitext_key($kind);
    $ekey   = pokehub_fandom_recurring_error_key($kind);
    $hash   = '#' . 'pokehub-fandom-' . $kind;

    if (!isset($defs[$kind])) {
        return;
    }

    if ($action === 'fetch') {
        $result = pokehub_fandom_fetch_wikitext_by_title($defs[$kind]['page']);
        if (!empty($result['ok']) && !empty($result['wikitext'])) {
            set_transient($wkey, $result['wikitext'], HOUR_IN_SECONDS);
            delete_transient($ekey);
            wp_safe_redirect(add_query_arg(['fr_loaded' => '1', 'fr_k' => $kind], $base) . $hash);
        } else {
            delete_transient($wkey);
            set_transient($ekey, (string) ($result['error'] ?? __('Échec.', 'poke-hub')), 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect(add_query_arg(['fr_loaded' => '0', 'fr_k' => $kind], $base) . $hash);
        }
        exit;
    }

    if ($action === 'paste') {
        $raw = isset($_POST['pokehub_fr_wikitext']) ? wp_unslash((string) $_POST['pokehub_fr_wikitext']) : '';
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = substr($raw, 0, 600000);
        if (trim($raw) === '') {
            set_transient($ekey, __('Collez d’abord le wikitext.', 'poke-hub'), 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect(add_query_arg(['fr_loaded' => '0', 'fr_k' => $kind], $base) . $hash);
            exit;
        }
        set_transient($wkey, $raw, HOUR_IN_SECONDS);
        delete_transient($ekey);
        wp_safe_redirect(add_query_arg(['fr_loaded' => '1', 'fr_k' => $kind], $base) . $hash);
        exit;
    }

    if ($action === 'clear') {
        delete_transient($wkey);
        delete_transient($ekey);
        wp_safe_redirect(add_query_arg(['fr_cleared' => '1', 'fr_k' => $kind], $base) . $hash);
        exit;
    }
}

add_action('admin_init', 'pokehub_fandom_recurring_handle_tools_post', 1);

/**
 * Rend une carte d’import (Raid Hour ou Spotlight / heure vedette).
 */
function pokehub_fandom_recurring_render_card(string $kind): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    $defs = pokehub_fandom_recurring_import_defs();
    if (!isset($defs[$kind])) {
        return;
    }
    $def  = $defs[$kind];
    $wkey = pokehub_fandom_recurring_wikitext_key($kind);
    $ekey = pokehub_fandom_recurring_error_key($kind);
    $id   = 'pokehub-fandom-' . esc_attr($kind);

    $wikitext_mem = get_transient($wkey);
    $wikitext_mem = is_string($wikitext_mem) ? $wikitext_mem : '';

    echo '<div class="card" id="' . $id . '" style="max-width: 960px; margin-top: 0;">';
    echo '<h2 class="title">' . esc_html($def['label']) . '</h2>';
    echo '<p class="description">' . esc_html($def['page']) . ' → type <code>' . esc_html($def['event_type']) . '</code> · 18:00–19:00 (fuseau du site).</p>';

    $tools_action = esc_url(pokehub_fandom_recurring_tools_url($kind));
    $nonce        = wp_create_nonce('pokehub_fandom_recurring_tools');
    $nonce_in     = '<input type="hidden" name="pokehub_fr_nonce" value="' . esc_attr($nonce) . '" /><input type="hidden" name="pokehub_fr_kind" value="' . esc_attr($kind) . '" />';

    if (isset($_GET['fr_imported'], $_GET['fr_k']) && sanitize_key((string) $_GET['fr_k']) === $kind) {
        $n = (int) $_GET['fr_imported'];
        echo '<div class="notice notice-success inline"><p>' . sprintf(esc_html__('%d événement(s) importé(s).', 'poke-hub'), $n) . '</p></div>';
    }
    if (!empty($_GET['fr_cleared']) && isset($_GET['fr_k']) && sanitize_key((string) $_GET['fr_k']) === $kind) {
        echo '<div class="notice notice-info inline"><p>' . esc_html__('Mémoire wikitext effacée pour cet outil.', 'poke-hub') . '</p></div>';
    }
    if (isset($_GET['fr_loaded'], $_GET['fr_k']) && sanitize_key((string) $_GET['fr_k']) === $kind) {
        $ok = sanitize_key((string) $_GET['fr_loaded']) === '1';
        if ($ok) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Wikitext chargé.', 'poke-hub') . '</p></div>';
        } else {
            $err = get_transient($ekey);
            if (is_string($err) && $err !== '') {
                echo '<div class="notice notice-error inline"><p>' . esc_html($err) . '</p></div>';
                delete_transient($ekey);
            }
        }
    }

    echo '<div style="margin-bottom:10px;">';
    echo '<form method="post" action="' . $tools_action . '" style="display:inline-block;margin-right:12px;">';
    echo $nonce_in . '<input type="hidden" name="pokehub_fr_action" value="fetch" />';
    submit_button(__('Télécharger via l’API Fandom', 'poke-hub'), 'secondary', 'pokehub_fr_fetch_' . $kind, false);
    echo '</form>';
    echo '<form method="post" action="' . $tools_action . '" style="display:inline-block;">';
    echo $nonce_in . '<input type="hidden" name="pokehub_fr_action" value="clear" />';
    submit_button(__('Effacer la mémoire', 'poke-hub'), 'delete', 'pokehub_fr_clear_' . $kind, false, [
        'onclick' => 'return confirm(' . wp_json_encode(__('Effacer le wikitext chargé ?', 'poke-hub')) . ');',
    ]);
    echo '</form></div>';

    echo '<form method="post" action="' . $tools_action . '" style="margin-top:12px;">';
    echo $nonce_in . '<input type="hidden" name="pokehub_fr_action" value="paste" />';
    echo '<p><label><strong>' . esc_html__('Wikitext (collage)', 'poke-hub') . '</strong></label></p>';
    echo '<textarea name="pokehub_fr_wikitext" rows="6" class="large-text code">' . esc_textarea($wikitext_mem) . '</textarea>';
    echo '<p class="submit">';
    submit_button(__('Utiliser ce texte', 'poke-hub'), 'primary', 'pokehub_fr_paste_' . $kind, false);
    echo '</p></form>';

    $wikitext = $wikitext_mem;
    $rows     = $wikitext !== '' ? pokehub_fandom_recurring_parse_wikitext($wikitext, $kind) : [];

    echo '<h3>' . esc_html__('Aperçu', 'poke-hub') . '</h3>';
    echo '<p>' . sprintf(esc_html__('%d ligne(s) extraite(s).', 'poke-hub'), count($rows)) . '</p>';

    if ($wikitext === '') {
        echo '<p class="description">' . esc_html__('Aucun wikitext en mémoire.', 'poke-hub') . '</p></div>';
        return;
    }

    $not_done = [];
    foreach ($rows as $idx => $r) {
        if (!empty($r['exists'])) {
            $not_done[] = ['idx' => $idx, 'row' => $r, 'reason' => __('Déjà en base (même date/heure + type).', 'poke-hub')];
        } elseif (!empty($r['skip'])) {
            $not_done[] = ['idx' => $idx, 'row' => $r, 'reason' => (string) ($r['skip_reason'] ?? '')];
        }
    }

    if ($rows !== []) {
        echo '<h4>' . esc_html__('Lignes non importées (aperçu)', 'poke-hub') . '</h4>';
        if ($not_done === []) {
            echo '<p class="description">' . esc_html__('Aucune : toutes les lignes sont importables ou déjà présentes (voir cases à cocher).', 'poke-hub') . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Date', 'poke-hub') . '</th><th>' . esc_html__('Motif', 'poke-hub') . '</th></tr></thead><tbody>';
            foreach ($not_done as $nd) {
                $r = $nd['row'];
                echo '<tr><td>' . esc_html((string) ($r['ymd'] ?? '')) . '</td><td>' . esc_html((string) $nd['reason']) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
    }

    if ($rows === []) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('Aucune ligne exploitable.', 'poke-hub') . '</p></div></div>';
        return;
    }

    $post_url = esc_url(admin_url('admin-post.php'));
    echo '<form method="post" action="' . $post_url . '">';
    wp_nonce_field('pokehub_fandom_recurring_import_' . $kind, 'pokehub_fr_import_nonce');
    echo '<input type="hidden" name="action" value="pokehub_fandom_recurring_import" />';
    echo '<input type="hidden" name="pokehub_fr_import_kind" value="' . esc_attr($kind) . '" />';

    echo '<table class="widefat striped"><thead><tr>';
    echo '<th style="width:40px;"><input type="checkbox" id="pokehub-fr-all-' . esc_attr($kind) . '" /></th>';
    echo '<th>' . esc_html__('Date', 'poke-hub') . '</th><th>' . esc_html__('Pokémon', 'poke-hub') . '</th><th>' . esc_html__('Statut', 'poke-hub') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $idx => $r) {
        $disabled = !empty($r['skip']) || !empty($r['exists']);
        echo '<tr><td>';
        if (!$disabled) {
            echo '<input type="checkbox" name="pokehub_fr_rows[]" value="' . (int) $idx . '" class="pokehub-fr-row-' . esc_attr($kind) . '" checked="checked" />';
        } else {
            echo '—';
        }
        echo '</td><td>' . esc_html((string) ($r['ymd'] ?? '')) . '</td><td>';
        $labels = [];
        foreach ($r['pokemon_rows'] ?? [] as $pr) {
            $labels[] = ($pr['wiki'] ?? '') . ' → #' . (int) ($pr['id'] ?? 0);
        }
        echo esc_html(implode(' · ', $labels) ?: '—');
        echo '</td><td>';
        if (!empty($r['skip'])) {
            echo esc_html((string) $r['skip_reason']);
        } elseif (!empty($r['exists'])) {
            esc_html_e('Déjà en base.', 'poke-hub');
        } else {
            esc_html_e('Prêt', 'poke-hub');
        }
        echo '</td></tr>';
        echo '<input type="hidden" name="pokehub_fr_row_' . (int) $idx . '" value="' . esc_attr(wp_json_encode($r, JSON_UNESCAPED_UNICODE)) . '" />';
    }
    echo '</tbody></table>';
    echo '<p style="margin-top:12px;">';
    submit_button(__('Importer la sélection', 'poke-hub'), 'primary large', 'pokehub_fr_do_' . $kind, false);
    echo '</p></form>';
    echo '<script>document.getElementById("pokehub-fr-all-' . esc_js($kind) . '")?.addEventListener("change",function(){document.querySelectorAll(".pokehub-fr-row-' . esc_js($kind) . '").forEach(function(c){c.checked=this.checked;}.bind(this));});</script>';
    echo '</div>';
}

function pokehub_fandom_recurring_render_all_cards(): void {
    foreach (array_keys(pokehub_fandom_recurring_import_defs()) as $kind) {
        pokehub_fandom_recurring_render_card($kind);
    }
}

add_action('admin_post_pokehub_fandom_recurring_import', static function (): void {
    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to do this.', 'poke-hub'));
    }
    if (!function_exists('poke_hub_temporary_tools_enabled') || !poke_hub_temporary_tools_enabled()) {
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-settings'));
        exit;
    }
    $kind = isset($_POST['pokehub_fr_import_kind']) ? sanitize_key(wp_unslash((string) $_POST['pokehub_fr_import_kind'])) : '';
    if ($kind === '' || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_POST['pokehub_fr_import_nonce'] ?? ''))), 'pokehub_fandom_recurring_import_' . $kind)) {
        wp_die(__('Security check failed.', 'poke-hub'));
    }

    $imported = 0;
    $indices  = isset($_POST['pokehub_fr_rows']) && is_array($_POST['pokehub_fr_rows']) ? array_map('intval', $_POST['pokehub_fr_rows']) : [];
    foreach ($indices as $idx) {
        $key = 'pokehub_fr_row_' . $idx;
        if (empty($_POST[$key])) {
            continue;
        }
        $row = json_decode(wp_unslash((string) $_POST[$key]), true);
        if (!is_array($row) || !empty($row['skip']) || !empty($row['exists'])) {
            continue;
        }
        $res = pokehub_fandom_recurring_insert_one($row);
        if (is_int($res) && $res > 0) {
            ++$imported;
        }
    }

    if (function_exists('poke_hub_purge_module_cache')) {
        poke_hub_purge_module_cache(['poke_hub_events'], 'poke_hub_events', 'poke_hub_events_all');
    }

    wp_safe_redirect(
        add_query_arg(
            ['fr_imported' => $imported, 'fr_k' => $kind],
            pokehub_fandom_recurring_tools_url($kind)
        ) . '#pokehub-fandom-' . $kind
    );
    exit;
});
