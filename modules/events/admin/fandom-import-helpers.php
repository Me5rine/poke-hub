<?php
/**
 * Helpers communs pour imports wikitext Fandom (MediaWiki).
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Blocs internes des tableaux d’événements Fandom (classe legacy ou équivalents).
 *
 * @return string[]
 */
function pokehub_fandom_extract_legacy_wiki_tables(string $section): array {
    if (preg_match_all('/\{\|\s*class="[^"]*pogo-legacy-table[^"]*"\s*(.*?)\R\|\}/s', $section, $m)) {
        return $m[1];
    }
    if (preg_match_all('/\{\|\s*class="[^"]*(?:wikitable|roundy)[^"]*"\s*(.*?)\R\|\}/s', $section, $m2)) {
        return $m2[1];
    }
    if (preg_match_all('/\{\|[^\n]*\n(.*?)\R\|\}/s', $section, $m3)) {
        $keep = [];
        foreach ($m3[1] as $inner) {
            if (strpos($inner, '!') === false || strpos($inner, '|') === false) {
                continue;
            }
            if (!preg_match('/January|February|March|April|May|June|July|August|September|October|November|December/i', $inner)) {
                continue;
            }
            if (preg_match('/!\s*(Date|Featured|Bonus)/i', $inner)) {
                $keep[] = $inner;
            }
        }
        return $keep;
    }
    return [];
}

/**
 * URL API parse pour une page titre wiki (espaces → _).
 */
function pokehub_fandom_mediawiki_parse_url(string $page_title): string {
    $page_title = trim(str_replace(' ', '_', $page_title));
    return 'https://pokemongo.fandom.com/api.php?action=parse&page=' . rawurlencode($page_title) . '&prop=wikitext&format=json';
}

/**
 * Télécharge le wikitext d’une page Fandom.
 *
 * @return array{ok:bool, wikitext?:string, error?:string}
 */
function pokehub_fandom_fetch_wikitext_by_title(string $page_title): array {
    $url = pokehub_fandom_mediawiki_parse_url($page_title);
    $response = wp_remote_get(
        $url,
        [
            'timeout' => 30,
            'headers' => [
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept'          => 'application/json,text/plain,*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ]
    );

    if (is_wp_error($response)) {
        return [
            'ok'    => false,
            'error' => sprintf(__('Réseau : %s', 'poke-hub'), $response->get_error_message()),
        ];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        if ($code === 403) {
            return [
                'ok'    => false,
                'error' => __(
                    'Fandom a répondu 403 Forbidden : ce serveur est souvent bloqué. Utilisez le collage manuel du wikitext.',
                    'poke-hub'
                ),
            ];
        }
        if ($code === 429) {
            return [
                'ok'    => false,
                'error' => __('Fandom a répondu 429 (trop de requêtes). Réessayez plus tard ou collez le wikitext à la main.', 'poke-hub'),
            ];
        }

        return [
            'ok'    => false,
            'error' => sprintf(__('L’API Fandom a renvoyé le code HTTP %d.', 'poke-hub'), $code),
        ];
    }

    $body = (string) wp_remote_retrieve_body($response);
    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['parse']['wikitext']['*'])) {
        return ['ok' => false, 'error' => __('Réponse API invalide (pas de wikitext dans le JSON).', 'poke-hub')];
    }

    return ['ok' => true, 'wikitext' => (string) $json['parse']['wikitext']['*']];
}

/**
 * Noms depuis {{I|Nom|…}} dans une cellule wiki.
 *
 * @return string[]
 */
function pokehub_fandom_extract_i_template_names(string $cell): array {
    if (preg_match_all('/\{\{I\|([^}|]+)(?:\|[^}]*)?\}\}/u', $cell, $m)) {
        $names = [];
        foreach ($m[1] as $raw) {
            $n = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($n !== '' && !in_array($n, $names, true)) {
                $names[] = $n;
            }
        }
        return $names;
    }
    return [];
}

/**
 * Textes affichables des liens wiki [[Page]] / [[Page|Label]] (Fandom).
 *
 * @return string[]
 */
function pokehub_fandom_extract_wiki_link_display_texts(string $cell): array {
    if (!preg_match_all('/\[\[([^\]]+)\]\]/u', $cell, $m)) {
        return [];
    }
    $out = [];
    foreach ($m[1] as $inner) {
        $inner = trim(html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($inner === '') {
            continue;
        }
        $colon = strpos($inner, ':');
        if ($colon !== false) {
            $ns = strtolower(substr($inner, 0, $colon));
            if (in_array($ns, ['file', 'image', 'category', 'template'], true)) {
                continue;
            }
        }
        if (strpos($inner, '|') !== false) {
            $parts   = explode('|', $inner, 2);
            $display = trim($parts[1]);
        } else {
            $display = $inner;
        }
        if (($p = strpos($display, '#')) !== false) {
            $display = substr($display, 0, $p);
        }
        $display = trim(str_replace('_', ' ', $display));
        if ($display !== '' && !in_array($display, $out, true)) {
            $out[] = $display;
        }
    }
    return $out;
}

/**
 * Wikitext / cellule → chaîne lisible pour recherche de sous-chaînes (bonus, heuristiques).
 */
function pokehub_fandom_wiki_cell_to_search_plain(string $cell): string {
    $s = (string) $cell;
    $s = preg_replace_callback(
        '/\[\[([^\]]+)\]\]/u',
        static function ($m) {
            $inner = trim($m[1]);
            if ($inner === '') {
                return ' ';
            }
            $colon = strpos($inner, ':');
            if ($colon !== false) {
                $ns = strtolower(substr($inner, 0, $colon));
                if (in_array($ns, ['file', 'image', 'category', 'template'], true)) {
                    return ' ';
                }
            }
            if (strpos($inner, '|') !== false) {
                $parts   = explode('|', $inner, 2);
                $display = trim($parts[1]);
            } else {
                $display = $inner;
            }
            if (($p = strpos($display, '#')) !== false) {
                $display = substr($display, 0, $p);
            }
            return ' ' . str_replace('_', ' ', trim($display)) . ' ';
        },
        $s
    ) ?? $s;
    $s = str_replace('{{!}}', ' ', $s);
    $s = preg_replace('/\{\{I\|([^}|]+)(?:\|[^}]*)?\}\}/u', ' $1 ', $s) ?? $s;
    $s = preg_replace("/'''/", '', $s) ?? $s;
    $s = preg_replace('/\{\{[^}]+\}\}/u', ' ', $s) ?? $s;
    $s = wp_strip_all_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return trim($s);
}

/**
 * Date depuis « January {{Nth|26}} » (même logique que Max Monday).
 *
 * @return array{0:int,1:int,2:int}|null y, m, d
 */
function pokehub_fandom_parse_date_nth_cell(string $date_cell, int $section_year): ?array {
    $date_cell = trim($date_cell);
    if (!preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)\s*\{\{Nth\|(\d{1,2})\}\}/i', $date_cell, $m)) {
        return null;
    }
    $months = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
        'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
    ];
    $mon = $months[strtolower($m[1])] ?? 0;
    $day = (int) $m[2];
    if ($mon < 1 || $day < 1 || $day > 31) {
        return null;
    }
    if (!checkdate($mon, $day, $section_year)) {
        return null;
    }
    return [$section_year, $mon, $day];
}

/**
 * Date anglaise type « April 15th », « February 1st », références [12] retirées.
 *
 * @return array{0:int,1:int,2:int}|null
 */
function pokehub_fandom_parse_date_ordinal_cell(string $date_cell, int $section_year): ?array {
    $date_cell = trim(preg_replace('/\[\d+\]/', '', $date_cell));
    if (!preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2})(?:st|nd|rd|th)(?:,?\s*(\d{4}))?/i', $date_cell, $m)) {
        return null;
    }
    $months = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
        'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
    ];
    $mon = $months[strtolower($m[1])] ?? 0;
    $day = (int) $m[2];
    $year = !empty($m[3]) ? (int) $m[3] : $section_year;
    if ($year < 2000 || $year > 2100) {
        $year = $section_year;
    }
    if ($mon < 1 || $day < 1 || $day > 31) {
        return null;
    }
    if (!checkdate($mon, $day, $year)) {
        return null;
    }
    return [$year, $mon, $day];
}

/**
 * Essaie {{Nth|}} puis date ordinale anglaise.
 *
 * @return array{0:int,1:int,2:int}|null
 */
function pokehub_fandom_parse_event_date_cell(string $date_cell, int $section_year): ?array {
    $nth = pokehub_fandom_parse_date_nth_cell($date_cell, $section_year);
    if ($nth !== null) {
        return $nth;
    }
    return pokehub_fandom_parse_date_ordinal_cell($date_cell, $section_year);
}

/**
 * Variantes de libellé wiki / Fandom pour retrouver un Pokémon (name_en ou slug).
 *
 * @return string[]
 */
function pokehub_fandom_pokemon_wiki_name_candidates(string $wiki): array {
    $wiki = trim(html_entity_decode($wiki, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($wiki === '') {
        return [];
    }
    $c = [$wiki];
    // Tirets unicode (tiret cadratin, etc.) → ASCII (ex. Ho‑Oh vs Ho-Oh)
    $hyphen_norm = preg_replace('/[\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]/u', '-', $wiki);
    if (is_string($hyphen_norm) && $hyphen_norm !== '' && $hyphen_norm !== $wiki) {
        $c[] = $hyphen_norm;
    }
    // Ho-Oh : fautes / variantes fréquentes sur Fandom
    if (preg_match('/^ho[-\x{2010}-\x{2015}\x{2212}]+o(h|g)$/iu', $wiki)) {
        $c[] = 'Ho-Oh';
        $c[] = 'ho-oh';
    }
    $wl = strtolower($wiki);
    if (strpos($wl, 'nidoran') === 0) {
        $female_sym = (strpos($wiki, '♀') !== false || strpos($wiki, "\xe2\x99\x80") !== false);
        $male_sym   = (strpos($wiki, '♂') !== false || strpos($wiki, "\xe2\x99\x82") !== false);
        if ($female_sym) {
            array_push($c, 'Nidoran♀', 'Nidoran Female', 'Nidoran-Female', 'nidoran-female', 'Nidoran F', 'Nidoran f');
        }
        if ($male_sym) {
            array_push($c, 'Nidoran♂', 'Nidoran Male', 'Nidoran-Male', 'nidoran-male', 'Nidoran M', 'Nidoran m');
        }
    }
    $out = [];
    foreach ($c as $x) {
        $x = trim((string) $x);
        if ($x === '') {
            continue;
        }
        if (!in_array($x, $out, true)) {
            $out[] = $x;
        }
    }
    return $out;
}

/**
 * Résout un Pokémon depuis un libellé wiki (name_en, slug, symboles Nidoran, tirets).
 *
 * @return int 0 si introuvable
 */
function pokehub_fandom_resolve_pokemon_id_from_wiki_label(string $wiki_label): int {
    global $wpdb;
    $table = pokehub_get_table('pokemon');
    if ($table === '' || !function_exists('pokehub_table_exists') || !pokehub_table_exists($table)) {
        return 0;
    }
    foreach (pokehub_fandom_pokemon_wiki_name_candidates($wiki_label) as $cand) {
        $id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE LOWER(TRIM(name_en)) = LOWER(%s) OR LOWER(TRIM(slug)) = LOWER(%s)
                 ORDER BY is_default DESC, id ASC LIMIT 1",
                $cand,
                $cand
            )
        );
        if ($id > 0) {
            return $id;
        }
    }
    return 0;
}

/**
 * Retire les préfixes wiki « Shadow » / « Shiny » et retourne le libellé de base + drapeaux.
 *
 * @return array{label:string, force_shadow:bool, force_shiny:bool}
 */
function pokehub_fandom_split_pokemon_wiki_mods(string $raw): array {
    $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $force_shadow = false;
    $force_shiny  = false;
    while ($raw !== '') {
        if (preg_match('/^Shadow\s+/iu', $raw)) {
            $force_shadow = true;
            $raw          = trim(preg_replace('/^Shadow\s+/iu', '', $raw));
            continue;
        }
        if (preg_match('/^Shiny\s+/iu', $raw)) {
            $force_shiny = true;
            $raw         = trim(preg_replace('/^Shiny\s+/iu', '', $raw));
            continue;
        }
        break;
    }
    return [
        'label'        => $raw,
        'force_shadow' => $force_shadow,
        'force_shiny'  => $force_shiny,
    ];
}

/**
 * Parse les lignes « Pokémon : zone » dans les notes (heures de raids Fandom).
 *
 * @return array<string, string> clé = nom wiki normalisé (comparaison), valeur = texte après « : »
 */
function pokehub_fandom_parse_regional_distribution_notes(string $notes): array {
    $notes = str_replace(["\r\n", "\r"], "\n", $notes);
    $out   = [];
    foreach (preg_split('/\n+/u', $notes) as $ln) {
        $ln = trim($ln);
        if ($ln === '') {
            continue;
        }
        if (preg_match('/^\s*(knew|chance)\b/iu', $ln)) {
            continue;
        }
        if (!preg_match('/^([\p{L}\d][\p{L}\d\s\'\-\x{00B7}:]{0,40})\s*:\s*(.+)$/u', $ln, $m)) {
            continue;
        }
        $left  = trim($m[1]);
        $right = trim($m[2]);
        if ($left === '' || $right === '') {
            continue;
        }
        $mods = pokehub_fandom_split_pokemon_wiki_mods($left);
        $key  = pokehub_fandom_normalize_compare_string($mods['label']);
        if ($key === '') {
            continue;
        }
        $out[$key] = $right;
    }
    return $out;
}

/**
 * Texte régional pour un nom wiki donné (tableau retourné par pokehub_fandom_parse_regional_distribution_notes).
 */
function pokehub_fandom_match_region_note_for_wiki(string $wiki_label, array $regional_map): string {
    $mods = pokehub_fandom_split_pokemon_wiki_mods($wiki_label);
    $key  = pokehub_fandom_normalize_compare_string($mods['label']);
    if ($key !== '' && isset($regional_map[$key])) {
        return (string) $regional_map[$key];
    }
    $full = pokehub_fandom_normalize_compare_string($wiki_label);
    return $full !== '' && isset($regional_map[$full]) ? (string) $regional_map[$full] : '';
}

/**
 * Chaîne pour comparaison souple (casse, tirets unicode).
 */
function pokehub_fandom_normalize_compare_string(string $s): string {
    $s = strtolower(trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $s = preg_replace('/[\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]/u', '-', $s) ?? $s;
    return preg_replace('/\s+/u', ' ', $s) ?? $s;
}

/**
 * Texte affiché d’un lien wiki d’attaque [[Move]] ou [[Page|Move]].
 */
function pokehub_fandom_wiki_attack_link_to_label(string $inner): string {
    $inner = trim(html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($inner === '') {
        return '';
    }
    if (strpos($inner, '|') !== false) {
        $parts = explode('|', $inner, 2);
        $inner = trim($parts[1]);
    }
    return trim(str_replace('_', ' ', $inner));
}

/**
 * Libellé d’attaque depuis une fin de ligne (texte brut ou lien wiki).
 */
function pokehub_fandom_move_plain_tail_to_label(string $tail): string {
    $tail = trim(html_entity_decode($tail, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($tail === '') {
        return '';
    }
    if (preg_match('/\[\[([^\]]+)\]\]/u', $tail, $m)) {
        return pokehub_fandom_wiki_attack_link_to_label($m[1]);
    }
    $tail = preg_replace("/'''+/u", '', $tail);
    $tail = trim(preg_replace('/\{\{[^}]+\}\}/u', '', $tail));
    return trim(preg_replace('/\s+/u', ' ', $tail));
}

/**
 * Libellés Pokémon (lignes / mots) repérés dans une cellule sans uniquement {{I|}}.
 *
 * @return string[]
 */
function pokehub_fandom_extract_wiki_plain_pokemon_labels(string $cell): array {
    $cell = str_replace(["\r\n", "\r"], "\n", $cell);
    $seen_pid = [];
    $out      = [];

    foreach (preg_split('/\n+/u', $cell) as $ln) {
        $ln = trim($ln);
        if ($ln === '') {
            continue;
        }
        if (preg_match('/^\s*(knew|chance)\b/iu', $ln)) {
            continue;
        }
        if (preg_match('/\b(shiny|available|during|only|exclusive|event)\b/i', $ln)) {
            continue;
        }

        foreach (pokehub_fandom_extract_wiki_link_display_texts($ln) as $disp) {
            if (strlen($disp) < 2 || strlen($disp) > 40) {
                continue;
            }
            $pid = pokehub_fandom_resolve_pokemon_id_from_wiki_label($disp);
            if ($pid <= 0 || isset($seen_pid[$pid])) {
                continue;
            }
            $seen_pid[$pid] = true;
            $out[]          = $disp;
        }

        $bare = preg_replace('/\[\[[^\]]+\]\]/u', ' ', $ln);
        $bare = trim(preg_replace('/\{\{[^}]+\}\}/u', ' ', (string) $bare));
        $bare = trim(preg_replace('/\s+/u', ' ', $bare));
        if ($bare === '') {
            continue;
        }

        $candidates = [$bare];
        if (strpos($bare, ' ') !== false) {
            foreach (preg_split('/\s+/', $bare) as $w) {
                $w = trim($w, " ,.;");
                if ($w !== '') {
                    $candidates[] = $w;
                }
            }
        }
        $candidates = array_unique($candidates);

        foreach ($candidates as $cand) {
            if (strlen($cand) < 2 || strlen($cand) > 40) {
                continue;
            }
            $pid = pokehub_fandom_resolve_pokemon_id_from_wiki_label($cand);
            if ($pid <= 0 || isset($seen_pid[$pid])) {
                continue;
            }
            $seen_pid[$pid] = true;
            $out[]          = $cand;
        }
    }

    return $out;
}

/**
 * Résout une attaque « spéciale / événement » pour un Pokémon (name_en).
 *
 * @return int 0 si introuvable
 */
function pokehub_fandom_resolve_event_move_id(int $pokemon_id, string $move_en_label): int {
    global $wpdb;
    $pokemon_id = (int) $pokemon_id;
    if ($pokemon_id <= 0) {
        return 0;
    }
    $move_en_label = trim(html_entity_decode($move_en_label, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($move_en_label === '') {
        return 0;
    }
    $links_table   = pokehub_get_table('pokemon_attack_links');
    $attacks_table = pokehub_get_table('attacks');
    // 1) Attaques marquées événement ou rôle charged/special
    $id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT a.id FROM {$attacks_table} a
             INNER JOIN {$links_table} l ON l.attack_id = a.id
             WHERE l.pokemon_id = %d
               AND (l.is_event = 1 OR l.role IN ('special', 'charged'))
               AND (
                    LOWER(TRIM(a.name_en)) = LOWER(%s)
                 OR LOWER(TRIM(a.name_fr)) = LOWER(%s)
               )
             ORDER BY l.is_event DESC, a.id ASC LIMIT 1",
            $pokemon_id,
            $move_en_label,
            $move_en_label
        )
    );
    if ($id > 0) {
        return $id;
    }
    // 2) Dernier recours : nom d’attaque pour ce Pokémon (tout rôle)
    $id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT a.id FROM {$attacks_table} a
             INNER JOIN {$links_table} l ON l.attack_id = a.id
             WHERE l.pokemon_id = %d
               AND (
                    LOWER(TRIM(a.name_en)) = LOWER(%s)
                 OR LOWER(TRIM(a.name_fr)) = LOWER(%s)
               )
             ORDER BY a.id ASC LIMIT 1",
            $pokemon_id,
            $move_en_label,
            $move_en_label
        )
    );
    return $id > 0 ? $id : 0;
}

/**
 * Texte bonus Spotlight / cellule wiki → paires (slug bonus catalogue, description FR affichée).
 *
 * @return array<int, array{slug:string, description:string}>
 */
function pokehub_spotlight_parse_bonus_lines(string $cell): array {
    $plain = pokehub_fandom_wiki_cell_to_search_plain($cell);
    $norm  = strtolower($plain);
    $norm  = preg_replace('/\s+/u', ' ', $norm) ?? $norm;
    $compact = str_replace([' ', "\xc2\xa0"], '', $norm);

    $rules = [
        [
            'needles'     => ['double catch xp', '2x catch xp', '2× catch xp', 'double catch experience', 'doublecatchxp'],
            'bonus_slug'  => 'xp',
            'description' => 'Capture x2',
            'title_fr'    => 'Double catch XP',
        ],
        [
            'needles'     => ['double catch stardust', '2x catch stardust', '2× catch stardust', 'doublecatchstardust'],
            'bonus_slug'  => 'stardust',
            'description' => 'Capture x2',
            'title_fr'    => 'Double catch Stardust',
        ],
        [
            'needles'     => ['double evolving xp', 'double evolve xp', 'double evolution xp', 'doubleevolutionxp'],
            'bonus_slug'  => 'xp',
            'description' => 'Évolution x2',
            'title_fr'    => 'Double evolving XP',
        ],
        [
            'needles'     => ['double catch candy', '2x catch candy', '2× catch candy', 'doublecatchcandy'],
            'bonus_slug'  => 'candy',
            'description' => 'Capture x2',
            'title_fr'    => 'Double catch Candy',
        ],
        [
            'needles'     => ['double transfer candy', '2x transfer candy', '2× transfer candy', 'doubletransfercandy'],
            'bonus_slug'  => 'candy',
            'description' => 'Transfert x2',
            'title_fr'    => 'Double transfer Candy',
        ],
        [
            'needles'     => ['quadruple catch xp', '4x catch xp', '4× catch xp'],
            'bonus_slug'  => 'xp',
            'description' => 'Capture x4',
            'title_fr'    => 'Quadruple catch XP',
        ],
        [
            'needles'     => ['quadruple catch stardust', '4x catch stardust', '4× catch stardust'],
            'bonus_slug'  => 'stardust',
            'description' => 'Capture x4',
            'title_fr'    => 'Quadruple catch Stardust',
        ],
        [
            'needles'     => ['quadruple catch candy', '4x catch candy', '4× catch candy'],
            'bonus_slug'  => 'candy',
            'description' => 'Capture x4',
            'title_fr'    => 'Quadruple catch Candy',
        ],
        [
            'needles'     => ['double raid xp', 'doubleraidxp'],
            'bonus_slug'  => 'xp',
            'description' => 'Raids x2',
            'title_fr'    => 'Double Raid XP',
        ],
    ];

    $out    = [];
    $slugs  = [];
    foreach ($rules as $rule) {
        foreach ($rule['needles'] as $needle) {
            $ncompact = str_replace([' ', "\xc2\xa0"], '', $needle);
            if (strpos($norm, $needle) === false && ($ncompact === '' || strpos($compact, $ncompact) === false)) {
                continue;
            }
            $slug = (string) $rule['bonus_slug'];
            if (isset($slugs[$slug])) {
                break;
            }
            $slugs[$slug] = true;
            $out[]        = [
                'slug'        => $slug,
                'description' => (string) $rule['description'],
                'title_fr'    => (string) $rule['title_fr'],
            ];
            break;
        }
    }

    return $out;
}

/**
 * Convertit les paires slug/description en bonus_id pour insertion special_event_bonus.
 *
 * @param array<int, array{slug:string, description:string, title_fr?:string}> $parsed
 * @return array<int, array{bonus_id:int, description:string, label:string}>
 */
function pokehub_spotlight_bonus_rows_to_ids(array $parsed): array {
    $rows = [];
    foreach ($parsed as $p) {
        if (empty($p['slug']) || !function_exists('pokehub_get_bonus_by_slug')) {
            continue;
        }
        $b = pokehub_get_bonus_by_slug((string) $p['slug']);
        if (!$b || empty($b['ID'])) {
            $rows[] = [
                'bonus_id'    => 0,
                'description' => (string) ($p['description'] ?? ''),
                'label'       => sprintf(
                    /* translators: %s: bonus slug */
                    __('Bonus « %s » introuvable (slug catalogue).', 'poke-hub'),
                    (string) $p['slug']
                ),
            ];
            continue;
        }
        $rows[] = [
            'bonus_id'    => (int) $b['ID'],
            'description' => (string) ($p['description'] ?? ''),
            'label'       => (string) ($p['title_fr'] ?? $p['slug']),
        ];
    }
    return $rows;
}
