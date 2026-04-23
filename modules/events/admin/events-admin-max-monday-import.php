<?php
/**
 * Outil temporaire : import des Max Monday depuis le wikitext de la page Fandom
 * (récupéré via l’API publique MediaWiki, sans Cloudflare).
 * Affiché sous Poké Hub → Outils temporaires lorsque le module événements est actif.
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/fandom-import-helpers.php';

/** @var string */
const POKEHUB_MAX_MONDAY_WIKI_API = 'https://pokemongo.fandom.com/api.php?action=parse&page=Max_Monday&prop=wikitext&format=json';

/** @var string */
const POKEHUB_MAX_MONDAY_EVENT_TYPE = 'max-monday';

/**
 * Clé transient : wikitext Max Monday en session (par utilisateur connecté).
 */
function pokehub_max_monday_wikitext_transient_key(): string {
    return 'pokehub_mm_wikitext_' . get_current_user_id();
}

/**
 * Clé transient : dernier message d’erreur (affichage unique).
 */
function pokehub_max_monday_error_flash_transient_key(): string {
    return 'pokehub_mm_errflash_' . get_current_user_id();
}

/**
 * URL de la page « Outils temporaires » (toujours avec ?page= pour POST/redirects).
 */
function pokehub_max_monday_admin_tools_url(): string {
    return function_exists('poke_hub_admin_tools_url')
        ? poke_hub_admin_tools_url('max-monday')
        : add_query_arg('tab', 'max-monday', admin_url('admin.php?page=poke-hub-tools'));
}

/**
 * Télécharge le wikitext de la page Max Monday.
 */
function pokehub_max_monday_fetch_wikitext(): array {
    $response = wp_remote_get(
        POKEHUB_MAX_MONDAY_WIKI_API,
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
            'error' => sprintf(
                /* translators: %s: technical error message */
                __('Réseau : %s', 'poke-hub'),
                $response->get_error_message()
            ),
        ];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        if ($code === 403) {
            return [
                'ok'    => false,
                'error' => __(
                    'Fandom a répondu 403 Forbidden : ce serveur est souvent bloqué (hébergeur, pare-feu, User-Agent). Utilisez le collage manuel du wikitext (voir le champ ci-dessous).',
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
            'error' => sprintf(
                /* translators: %d: HTTP status code */
                __('L’API Fandom a renvoyé le code HTTP %d (réponse non exploitable).', 'poke-hub'),
                $code
            ),
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
 * Traite chargement API / collage / effacement (POST vers Outils temporaires, PRG).
 * Utilise admin_init : le hook load-* ne reçoit pas toujours page= en POST si l’URL du formulaire est incorrecte.
 */
function pokehub_max_monday_handle_tools_screen_load(): void {
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
    if (empty($_POST['pokehub_mm_action']) || empty($_POST['pokehub_mm_nonce'])) {
        return;
    }
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['pokehub_mm_nonce'])), 'pokehub_mm_tools')) {
        return;
    }

    $base   = pokehub_max_monday_admin_tools_url();
    $action = sanitize_key((string) wp_unslash((string) $_POST['pokehub_mm_action']));
    $wkey   = pokehub_max_monday_wikitext_transient_key();
    $ekey   = pokehub_max_monday_error_flash_transient_key();

    if ($action === 'fetch') {
        $result = pokehub_max_monday_fetch_wikitext();
        if (!empty($result['ok']) && !empty($result['wikitext'])) {
            set_transient($wkey, $result['wikitext'], HOUR_IN_SECONDS);
            delete_transient($ekey);
            $url = add_query_arg(['mm_loaded' => '1'], $base);
        } else {
            delete_transient($wkey);
            $msg = isset($result['error']) ? (string) $result['error'] : __('Échec du téléchargement.', 'poke-hub');
            set_transient($ekey, $msg, 5 * MINUTE_IN_SECONDS);
            $url = add_query_arg(['mm_loaded' => '0'], $base);
        }
        wp_safe_redirect($url . '#pokehub-max-monday-import');
        exit;
    }

    if ($action === 'paste') {
        $raw = isset($_POST['pokehub_mm_wikitext']) ? wp_unslash((string) $_POST['pokehub_mm_wikitext']) : '';
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = substr($raw, 0, 600000);
        if (trim($raw) === '') {
            set_transient($ekey, __('Collez d’abord le wikitext dans le champ.', 'poke-hub'), 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect(add_query_arg(['mm_loaded' => '0'], $base) . '#pokehub-max-monday-import');
            exit;
        }
        set_transient($wkey, $raw, HOUR_IN_SECONDS);
        delete_transient($ekey);
        wp_safe_redirect(add_query_arg(['mm_loaded' => '1'], $base) . '#pokehub-max-monday-import');
        exit;
    }

    if ($action === 'clear') {
        delete_transient($wkey);
        delete_transient($ekey);
        wp_safe_redirect(add_query_arg(['mm_cleared' => '1'], $base) . '#pokehub-max-monday-import');
        exit;
    }
}

add_action('admin_init', 'pokehub_max_monday_handle_tools_screen_load', 1);

/**
 * Extrait les noms anglais depuis la cellule « Featured Pokémon » (templates {{I|...}}).
 *
 * @return string[] Noms uniques dans l’ordre d’apparition.
 */
function pokehub_max_monday_extract_i_template_names(string $cell): array {
    return pokehub_fandom_extract_i_template_names($cell);
}

/**
 * @return array{0:int,1:int,2:int}|null Année, mois, jour
 */
function pokehub_max_monday_parse_date_cell(string $date_cell, int $section_year): ?array {
    return pokehub_fandom_parse_event_date_cell($date_cell, $section_year);
}

/**
 * Analyse le wikitext et retourne les entrées exploitables.
 *
 * @return array<int, array<string, mixed>>
 */
function pokehub_max_monday_parse_wikitext(string $wikitext): array {
    global $wpdb;

    $pokemon_table = pokehub_get_table('pokemon');
    $events_table   = pokehub_get_table('special_events');

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
                if (count($cells) < 2) {
                    continue;
                }

                $date_raw = $cells[0];
                $feat_raw = $cells[1];

                $parsed_date = pokehub_max_monday_parse_date_cell($date_raw, $year);
                if ($parsed_date === null) {
                    continue;
                }

                [$y, $mon, $day] = $parsed_date;
                $ymd = sprintf('%04d-%02d-%02d', $y, $mon, $day);

                $wiki_names = pokehub_max_monday_extract_i_template_names($feat_raw);
                foreach (pokehub_fandom_extract_wiki_link_display_texts($feat_raw) as $link_disp) {
                    if ($link_disp !== '' && !in_array($link_disp, $wiki_names, true)) {
                        $wiki_names[] = $link_disp;
                    }
                }

                $skip       = false;
                $reason     = '';
                $pokemon_rows = [];

                if (count($wiki_names) === 0) {
                    $skip   = true;
                    $reason = __('Pokémon absent ou non reconnu (cellule vide).', 'poke-hub');
                } else {
                    foreach ($wiki_names as $raw_w) {
                        $mods = pokehub_fandom_split_pokemon_wiki_mods($raw_w);
                        $wen   = $mods['label'];
                        if ($wen === '') {
                            continue;
                        }
                        $pid = pokehub_fandom_resolve_pokemon_id_from_wiki_label($wen);
                        if ($pid <= 0) {
                            $skip   = true;
                            $reason = sprintf(
                                /* translators: %s: English Pokémon name from wiki */
                                __('Pokémon « %s » introuvable en base (name_en).', 'poke-hub'),
                                $wen
                            );
                            $pokemon_rows = [];
                            break;
                        }
                        $name_row = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT TRIM(name_en) AS name_en, TRIM(name_fr) AS name_fr FROM {$pokemon_table} WHERE id = %d",
                                $pid
                            ),
                            ARRAY_A
                        );
                        $name_row  = is_array($name_row) ? $name_row : [];
                        $name_en   = trim((string) ($name_row['name_en'] ?? ''));
                        $name_fr   = trim((string) ($name_row['name_fr'] ?? ''));
                        if ($name_fr === '') {
                            $name_fr = $name_en !== '' ? $name_en : $wen;
                        }
                        if ($name_en === '') {
                            $name_en = $wen;
                        }
                        $wiki_disp = trim($raw_w) !== '' ? trim($raw_w) : $wen;
                        $pokemon_rows[] = [
                            'wiki'                  => $wiki_disp,
                            'id'                    => $pid,
                            'name_en'               => $name_en,
                            'name_fr'               => $name_fr,
                            'force_shadow'          => !empty($mods['force_shadow']) ? 1 : 0,
                            'force_shiny'           => !empty($mods['force_shiny']) ? 1 : 0,
                            'region_note'           => '',
                            'is_worldwide_override' => 0,
                        ];
                    }
                }

                $seen_pid = [];
                $deduped  = [];
                foreach ($pokemon_rows as $pr) {
                    $pid = (int) ($pr['id'] ?? 0);
                    if ($pid <= 0 || isset($seen_pid[$pid])) {
                        continue;
                    }
                    $seen_pid[$pid] = true;
                    $deduped[]      = $pr;
                }
                $pokemon_rows = $deduped;

                $pokemon_en = '';
                $pokemon_id = 0;
                $name_fr    = '';
                if (!$skip && $pokemon_rows !== []) {
                    $pokemon_en = implode(' · ', array_column($pokemon_rows, 'name_en'));
                    $pokemon_id = (int) $pokemon_rows[0]['id'];
                    $name_fr    = (string) $pokemon_rows[0]['name_fr'];
                }

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
                            "SELECT id FROM {$events_table} 
                             WHERE event_type = %s AND start_ts = %d LIMIT 1",
                            POKEHUB_MAX_MONDAY_EVENT_TYPE,
                            $start_ts
                        )
                    );
                }

                $entries[] = [
                    'ymd'             => $ymd,
                    'start_ts'        => $start_ts,
                    'end_ts'          => $end_ts,
                    'pokemon_rows'    => $pokemon_rows,
                    'pokemon_en_wiki' => $pokemon_en,
                    'pokemon_id'      => $pokemon_id,
                    'name_fr'         => $name_fr,
                    'skip'            => $skip,
                    'skip_reason'     => $reason,
                    'exists'          => $exists,
                    'raw_date'        => $date_raw,
                    'raw_featured'    => $feat_raw,
                ];
            }
        }
    }

    return $entries;
}

/**
 * Insère un événement Max Monday + liaison Pokémon.
 *
 * @return int|string ID ou message d’erreur
 */
function pokehub_max_monday_insert_one(array $row) {
    global $wpdb;

    if (!empty($row['skip'])) {
        return __('Ligne non importable.', 'poke-hub');
    }

    $pokemon_table  = pokehub_get_table('pokemon');
    $pokemon_rows   = isset($row['pokemon_rows']) && is_array($row['pokemon_rows']) ? $row['pokemon_rows'] : [];
    if ($pokemon_rows === [] && !empty($row['pokemon_id'])) {
        $pokemon_rows = [
            [
                'wiki'    => (string) ($row['pokemon_en_wiki'] ?? ''),
                'id'      => (int) $row['pokemon_id'],
                'name_en' => '',
                'name_fr' => (string) ($row['name_fr'] ?? ''),
            ],
        ];
    }
    if ($pokemon_rows === []) {
        return __('Aucun Pokémon.', 'poke-hub');
    }

    foreach ($pokemon_rows as $i => $pr) {
        $pid = (int) ($pr['id'] ?? 0);
        if ($pid <= 0) {
            return __('Pokémon invalide.', 'poke-hub');
        }
        $name_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT TRIM(name_en) AS name_en, TRIM(name_fr) AS name_fr FROM {$pokemon_table} WHERE id = %d LIMIT 1",
                $pid
            ),
            ARRAY_A
        );
        $name_row  = is_array($name_row) ? $name_row : [];
        $name_en   = trim((string) ($name_row['name_en'] ?? ''));
        $name_fr   = trim((string) ($name_row['name_fr'] ?? ''));
        if ($name_fr === '') {
            $name_fr = $name_en !== '' ? $name_en : '';
        }
        if ($name_en === '') {
            $name_en = trim((string) ($pr['wiki'] ?? ''));
        }
        if ($name_fr === '') {
            $name_fr = $name_en;
        }
        if ($name_fr === '' && $name_en === '') {
            return __('Pokémon invalide.', 'poke-hub');
        }
        $pokemon_rows[$i]['name_en'] = $name_en;
        $pokemon_rows[$i]['name_fr'] = $name_fr;
    }

    $en_labels = array_map(
        static function (array $pr): string {
            $ne = trim((string) ($pr['name_en'] ?? ''));
            return $ne !== '' ? $ne : trim((string) ($pr['wiki'] ?? ''));
        },
        $pokemon_rows
    );
    $pokemon_en = implode(', ', $en_labels);

    $names_fr = array_map(
        static function (array $pr): string {
            return (string) ($pr['name_fr'] ?? '');
        },
        $pokemon_rows
    );
    $ymd        = (string) $row['ymd'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return __('Date invalide.', 'poke-hub');
    }

    $start_ts = 0;
    $end_ts   = 0;
    if (function_exists('poke_hub_special_event_parse_date_time_for_save')) {
        $start_ts = poke_hub_special_event_parse_date_time_for_save($ymd, '18:00', 'local');
        $end_ts   = poke_hub_special_event_parse_date_time_for_save($ymd, '19:00', 'local');
    }
    if ($start_ts <= 0 || $end_ts <= 0) {
        return __('Impossible de calculer les horodatages.', 'poke-hub');
    }

    $events_table = pokehub_get_table('special_events');
    $dup          = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$events_table} WHERE event_type = %s AND start_ts = %d LIMIT 1",
            POKEHUB_MAX_MONDAY_EVENT_TYPE,
            $start_ts
        )
    );
    if ($dup > 0) {
        return __('Doublon : un événement max-monday existe déjà à cette date/heure.', 'poke-hub');
    }

    $title_en = 'Max Monday ' . $pokemon_en;
    $title_fr = 'Lundi Max ' . implode(', ', $names_fr);

    // Même logique que le formulaire événement spécial : slug dérivé du titre EN (sanitize_title + unicité).
    $slug = pokehub_generate_unique_event_slug($title_en, 0);

    $event_pokemon_table  = pokehub_get_table('special_event_pokemon');

    $inserted = $wpdb->insert(
        $events_table,
        [
            'slug'                    => $slug,
            'title'                   => $title_en,
            'title_en'                => $title_en,
            'title_fr'                => $title_fr,
            'description'             => '',
            'event_type'              => POKEHUB_MAX_MONDAY_EVENT_TYPE,
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

    foreach ($pokemon_rows as $pr) {
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
    }

    if (function_exists('pokehub_content_sync_dates_for_source')) {
        pokehub_content_sync_dates_for_source(
            'special_event',
            $event_id,
            $start_ts,
            $end_ts
        );
    }

    return $event_id;
}

/**
 * Bloc « Outils temporaires » : import Max Monday (affiché sur la page poke-hub-tools).
 */
function pokehub_render_max_monday_import_section(): void {
    if (!current_user_can('manage_options')) {
        return;
    }

    $ekey = pokehub_max_monday_error_flash_transient_key();
    $wkey = pokehub_max_monday_wikitext_transient_key();

    echo '<div class="card" id="pokehub-max-monday-import" style="max-width: 960px; margin-top: 0;">';
    echo '<h2 class="title">' . esc_html__('Import Max Monday (Fandom)', 'poke-hub') . '</h2>';

    echo '<p><strong>' . esc_html__('Comment faire :', 'poke-hub') . '</strong> ';
    echo esc_html__(
        '1) Téléchargez le wikitext via le bouton API, ou collez-le à la main. 2) Vérifiez l’aperçu. 3) Cochez les lignes à créer puis cliquez sur « Importer la sélection ».',
        'poke-hub'
    ) . '</p>';

    echo '<p>' . esc_html__(
        'Les événements sont en mode local, 18:00–19:00 le jour indiqué (fuseau du site). Type : max-monday. Plusieurs {{I|…}} ou [[Liens]] sur une même date sont regroupés dans un seul événement avec plusieurs Pokémon.',
        'poke-hub'
    ) . '</p>';

    if (isset($_GET['max_monday_imported'])) {
        $n = (int) $_GET['max_monday_imported'];
        echo '<div class="notice notice-success inline"><p>' . sprintf(
            /* translators: %d: number of events imported */
            esc_html__('%d événement(s) Max Monday importé(s).', 'poke-hub'),
            $n
        ) . '</p></div>';
    }

    if (!empty($_GET['mm_cleared'])) {
        echo '<div class="notice notice-info inline"><p>' . esc_html__(
            'Données Max Monday en mémoire effacées.',
            'poke-hub'
        ) . '</p></div>';
    }

    if (isset($_GET['mm_loaded'])) {
        $flag = sanitize_key((string) wp_unslash((string) $_GET['mm_loaded']));
        if ($flag === '1') {
            echo '<div class="notice notice-success inline"><p>' . esc_html__(
                'Wikitext chargé. Vous pouvez vérifier l’aperçu puis lancer l’import.',
                'poke-hub'
            ) . '</p></div>';
        } elseif ($flag === '0') {
            $err = get_transient($ekey);
            if (is_string($err) && $err !== '') {
                echo '<div class="notice notice-error inline"><p>' . esc_html($err) . '</p></div>';
                delete_transient($ekey);
            }
        }
    }

    $tools_action   = esc_url(pokehub_max_monday_admin_tools_url());
    $mm_nonce        = wp_create_nonce('pokehub_mm_tools');
    $mm_nonce_input = '<input type="hidden" name="pokehub_mm_nonce" value="' . esc_attr($mm_nonce) . '" />';

    echo '<h3>' . esc_html__('Charger le wikitext', 'poke-hub') . '</h3>';
    echo '<ol class="description" style="margin-left:1.25em;">';
    echo '<li>' . esc_html__('API : bouton ci-dessous (peut être bloqué par l’hébergeur).', 'poke-hub') . '</li>';
    echo '<li>' . esc_html__(
        'Manuel : sur Fandom, « Modifier la page » (ou « View source »), copiez tout le wikitext ou au moins les sections avec les tableaux, collez dans le champ puis « Utiliser ce texte ».',
        'poke-hub'
    ) . '</li>';
    echo '</ol>';

    echo '<div class="pokehub-mm-fetch-row" style="margin-bottom:10px;">';
    echo '<form method="post" action="' . $tools_action . '" style="display:inline-block;margin-right:12px;">';
    echo $mm_nonce_input;
    echo '<input type="hidden" name="pokehub_mm_action" value="fetch" />';
    submit_button(__('Télécharger via l’API Fandom', 'poke-hub'), 'secondary', 'pokehub_mm_fetch_btn', false);
    echo '</form>';

    echo '<form method="post" action="' . $tools_action . '" style="display:inline-block;">';
    echo $mm_nonce_input;
    echo '<input type="hidden" name="pokehub_mm_action" value="clear" />';
    submit_button(__('Effacer le wikitext en mémoire', 'poke-hub'), 'delete', 'pokehub_mm_clear_btn', false, [
        'onclick' => 'return confirm(' . wp_json_encode(__('Supprimer le wikitext chargé pour cet utilisateur ?', 'poke-hub')) . ');',
    ]);
    echo '</form>';
    echo '</div>';

    $wikitext_mem = get_transient($wkey);
    $wikitext_mem = is_string($wikitext_mem) ? $wikitext_mem : '';

    echo '<form method="post" action="' . $tools_action . '" style="margin-top:12px;">';
    echo $mm_nonce_input;
    echo '<input type="hidden" name="pokehub_mm_action" value="paste" />';
    echo '<p><label for="pokehub_mm_wikitext"><strong>' . esc_html__('Wikitext (collage manuel)', 'poke-hub') . '</strong></label></p>';
    echo '<textarea name="pokehub_mm_wikitext" id="pokehub_mm_wikitext" rows="8" class="large-text code" placeholder="{{I|Pikachu…}}">' . esc_textarea($wikitext_mem) . '</textarea>';
    echo '<p class="submit">';
    submit_button(__('Utiliser ce texte', 'poke-hub'), 'primary', 'pokehub_mm_paste_btn', false);
    echo '</p>';
    echo '</form>';

    $wikitext = $wikitext_mem;
    $rows     = $wikitext !== '' ? pokehub_max_monday_parse_wikitext($wikitext) : [];

    echo '<h3>' . esc_html__('Aperçu', 'poke-hub') . '</h3>';
    echo '<p>' . sprintf(
        /* translators: %d: number of rows */
        esc_html__('%d ligne(s) extraite(s) des tableaux annuels.', 'poke-hub'),
        count($rows)
    ) . '</p>';

    if ($wikitext === '') {
        echo '<p class="description">' . esc_html__(
            'Aucun wikitext en mémoire : utilisez l’API ou le collage pour afficher l’aperçu et le bouton d’import.',
            'poke-hub'
        ) . '</p>';
        echo '</div>';
        return;
    }

    if (empty($rows)) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html__(
            'Le wikitext est chargé mais aucune ligne de tableau exploitable n’a été trouvée (format wiki changé ?). Vérifiez le contenu collé.',
            'poke-hub'
        ) . '</p></div>';
        echo '</div>';
        return;
    }

    $action = esc_url(admin_url('admin-post.php'));
    echo '<form method="post" action="' . $action . '">';
    wp_nonce_field('pokehub_import_max_monday', 'pokehub_max_monday_nonce');
    echo '<input type="hidden" name="action" value="pokehub_import_max_monday" />';

    echo '<table class="widefat striped"><thead><tr>';
    echo '<th style="width:40px;"><input type="checkbox" id="pokehub-mm-checkall" /></th>';
    echo '<th>' . esc_html__('Date', 'poke-hub') . '</th>';
    echo '<th>' . esc_html__('Pokémon (wiki EN)', 'poke-hub') . '</th>';
    echo '<th>' . esc_html__('Pokémon (base)', 'poke-hub') . '</th>';
    echo '<th>' . esc_html__('Statut', 'poke-hub') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $idx => $r) {
        $disabled = !empty($r['skip']) || !empty($r['exists']);

        echo '<tr>';
        echo '<td>';
        if (!$disabled) {
            echo '<input type="checkbox" name="import_rows[]" value="' . (int) $idx . '" class="pokehub-mm-row" checked="checked" />';
        } else {
            echo '—';
        }
        echo '</td>';
        echo '<td>' . esc_html($r['ymd']) . '</td>';
        $wiki_col = '';
        $base_col = '';
        if (!empty($r['pokemon_rows']) && is_array($r['pokemon_rows'])) {
            $wiki_col = esc_html(implode(' · ', array_column($r['pokemon_rows'], 'wiki')));
            $base_bits = [];
            foreach ($r['pokemon_rows'] as $pr) {
                $base_bits[] = '#' . (int) ($pr['id'] ?? 0) . ' ' . esc_html((string) ($pr['name_fr'] ?? ''));
            }
            $base_col = implode(' · ', $base_bits);
        } else {
            $wiki_col = esc_html((string) ($r['pokemon_en_wiki'] ?? ''));
            $base_col = !empty($r['pokemon_id'])
                ? esc_html('#' . (int) $r['pokemon_id'] . ' ' . (string) ($r['name_fr'] ?? ''))
                : '—';
        }
        echo '<td>' . $wiki_col . '</td>';
        echo '<td>' . ($base_col !== '' ? $base_col : '—') . '</td>';
        echo '<td>';
        if (!empty($r['skip'])) {
            echo esc_html($r['skip_reason']);
        } elseif (!empty($r['exists'])) {
            esc_html_e('Déjà en base (même date / type).', 'poke-hub');
        } else {
            esc_html_e('Prêt à importer', 'poke-hub');
        }
        echo '</td>';
        echo '</tr>';

        echo '<input type="hidden" name="row_' . (int) $idx . '_json" value="' . esc_attr(wp_json_encode($r, JSON_UNESCAPED_UNICODE)) . '" />';
    }

    echo '</tbody></table>';

    $mm_not_done = [];
    foreach ($rows as $idx => $r) {
        if (!empty($r['exists'])) {
            $mm_not_done[] = [
                'ymd'    => (string) ($r['ymd'] ?? ''),
                'reason' => __('Déjà en base (même date / heure + type max-monday).', 'poke-hub'),
            ];
        } elseif (!empty($r['skip'])) {
            $mm_not_done[] = [
                'ymd'    => (string) ($r['ymd'] ?? ''),
                'reason' => (string) ($r['skip_reason'] ?? ''),
            ];
        }
    }
    echo '<h4>' . esc_html__('Lignes non importées (récapitulatif)', 'poke-hub') . '</h4>';
    if ($mm_not_done === []) {
        echo '<p class="description">' . esc_html__('Aucune ligne exclue : tout est importable ou déjà en base (voir tableau ci-dessus).', 'poke-hub') . '</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Date', 'poke-hub') . '</th><th>' . esc_html__('Motif', 'poke-hub') . '</th></tr></thead><tbody>';
        foreach ($mm_not_done as $nd) {
            echo '<tr><td>' . esc_html($nd['ymd']) . '</td><td>' . esc_html($nd['reason']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '<p style="margin-top:16px;">';
    submit_button(__('Importer la sélection', 'poke-hub'), 'primary large', 'pokehub_mm_import_submit', false);
    echo '</p>';
    echo '</form>';

    echo '<script>document.getElementById("pokehub-mm-checkall")?.addEventListener("change",function(){document.querySelectorAll(".pokehub-mm-row").forEach(function(c){c.checked=this.checked;}.bind(this));});</script>';

    echo '</div>';
}

add_action('admin_post_pokehub_import_max_monday', function (): void {
    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to do this.', 'poke-hub'));
    }
    if (!function_exists('poke_hub_temporary_tools_enabled') || !poke_hub_temporary_tools_enabled()) {
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-settings'));
        exit;
    }
    if (empty($_POST['pokehub_max_monday_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['pokehub_max_monday_nonce'])), 'pokehub_import_max_monday')) {
        wp_die(__('Security check failed.', 'poke-hub'));
    }

    $imported = 0;
    $indices  = isset($_POST['import_rows']) && is_array($_POST['import_rows']) ? array_map('intval', $_POST['import_rows']) : [];

    foreach ($indices as $idx) {
        $key = 'row_' . $idx . '_json';
        if (empty($_POST[$key])) {
            continue;
        }
        $raw = wp_unslash((string) $_POST[$key]);
        $row = json_decode($raw, true);
        if (!is_array($row)) {
            continue;
        }
        if (!empty($row['skip']) || !empty($row['exists'])) {
            continue;
        }

        $result = pokehub_max_monday_insert_one($row);
        if (is_int($result) && $result > 0) {
            ++$imported;
        }
    }

    if (function_exists('poke_hub_purge_module_cache')) {
        poke_hub_purge_module_cache(
            ['poke_hub_events'],
            'poke_hub_events',
            'poke_hub_events_all'
        );
    }

    $redirect_url = add_query_arg(
        ['max_monday_imported' => $imported],
        pokehub_max_monday_admin_tools_url()
    );
    wp_safe_redirect($redirect_url . '#pokehub-max-monday-import');
    exit;
});
