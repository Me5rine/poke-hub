<?php
// File: modules/pokemon/includes/pokemon-official-names-fetcher.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 *  Bulbapedia Official Names Fetcher (Pokémon / Moves / Types)
 *
 *  Objectifs:
 *  - PAS de transient/cache persistant
 *  - Bulk "safe": set_time_limit(0), ignore_user_abort(true)
 *  - Évite les pages énormes: parse uniquement la section "In other languages"
 *    (MediaWiki API: prop=sections puis parse section index)
 *  - Pokémon:
 *      - Normalise Nidoran Female/Male => Nidoran♀ / Nidoran♂
 *      - Skip formes spéciales (costumes, alola, numéros, events...)
 *  - Types:
 *      - French: préférer Europe (ex Grass: Canada vs Europe)
 *  - Parsing tables:
 *      - Support rowspan/colspan (langue, sous-région)
 *      - Garde uniquement la 1ère ligne avant <br>
 *      - Nettoie <i>, <span class=explain>, <sup>, etc.
 *      - ja/ko: conserve seulement le début non-ASCII
 *  - Bulk updaters:
 *      - updated = lignes réellement modifiées en DB (wpdb->update > 0)
 *      - force = remplace si différent, mais ne compte pas si identique
 * ============================================================
 */

/* -------------------------------------------------------------------------
 *  Logging helper (désactivable)
 * ------------------------------------------------------------------------- */

if (!defined('POKE_HUB_TRANSLATIONS_DEBUG')) {
    define('POKE_HUB_TRANSLATIONS_DEBUG', false);
}

function poke_hub_tr_log($message, $context = null) {
    if (!POKE_HUB_TRANSLATIONS_DEBUG) return;
    $line = '[PokeHubTranslations] ' . $message;
    if ($context !== null) {
        $line .= ' | ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    error_log($line);
}

/* -------------------------------------------------------------------------
 *  MediaWiki API helpers
 * ------------------------------------------------------------------------- */

function poke_hub_bulbapedia_api_url() {
    return 'https://bulbapedia.bulbagarden.net/w/api.php';
}

/**
 * Effectue une requête HTTP avec retry automatique et meilleure gestion des erreurs.
 *
 * @param string $url URL à appeler
 * @param array  $args Arguments pour wp_remote_get/wp_remote_post
 * @param int    $max_retries Nombre maximum de tentatives (défaut: 2)
 * @return array|WP_Error Réponse ou erreur
 */
function poke_hub_http_request_with_retry($url, $args = [], $max_retries = 2) {
    $method = isset($args['method']) ? strtoupper($args['method']) : 'GET';
    $is_post = ($method === 'POST');
    
    // Timeouts par défaut améliorés (créer une copie pour ne pas modifier l'original)
    $default_args = [
        'timeout' => 30, // Augmenté de 15 à 30 secondes
        'connect_timeout' => 10,
        'user-agent' => 'Poke-Hub WordPress Plugin',
    ];
    
    // Fusionner avec les valeurs fournies (les valeurs fournies ont priorité)
    $request_args = array_merge($default_args, $args);
    
    $last_error = null;
    
    for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
        if ($attempt > 0) {
            // Backoff exponentiel : 1s, 2s, 4s
            $delay = pow(2, $attempt - 1);
            poke_hub_tr_log('http_retry', [
                'url' => $url,
                'attempt' => $attempt + 1,
                'max_retries' => $max_retries + 1,
                'delay' => $delay
            ]);
            sleep($delay);
        }
        
        // Pour GET, ne pas inclure 'body' et 'method' dans les arguments
        if ($is_post) {
            $response = wp_remote_post($url, $request_args);
        } else {
            $get_args = $request_args;
            unset($get_args['body'], $get_args['method']); // GET ne doit pas avoir ces arguments
            $response = wp_remote_get($url, $get_args);
        }
        
        // Succès immédiat
        if (!is_wp_error($response)) {
            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code >= 200 && $code < 400) {
                return $response;
            }
            
            // Codes d'erreur serveur : on retry
            if ($code >= 500 && $code < 600 && $attempt < $max_retries) {
                $last_error = new WP_Error(
                    'http_error',
                    sprintf('HTTP %d error (attempt %d/%d)', $code, $attempt + 1, $max_retries + 1)
                );
                continue;
            }
            
            // Autres codes d'erreur : on ne retry pas
            return new WP_Error(
                'http_error',
                sprintf('HTTP error: %d', $code),
                ['status_code' => $code, 'response' => $response]
            );
        }
        
        // Erreur WP_Error : vérifier si c'est retryable
        $last_error = $response;
        $error_code = $response->get_error_code();
        $error_message = $response->get_error_message();
        
        // Log de l'erreur
        if (function_exists('error_log')) {
            error_log(sprintf(
                '[PokeHub] HTTP request failed: %s - %s (URL: %s, attempt %d/%d)',
                $error_code,
                $error_message,
                $url,
                $attempt + 1,
                $max_retries + 1
            ));
        }
        
        // Erreurs retryables : timeout, connexion, DNS
        $retryable_errors = [
            'http_request_failed',
            'http_failure',
            'transport_error',
            'connect_timeout',
            'timeout',
            'name_resolution_failed'
        ];
        
        if (in_array($error_code, $retryable_errors, true) && $attempt < $max_retries) {
            // Vérifier aussi le message pour timeout
            if (stripos($error_message, 'timeout') !== false || 
                stripos($error_message, 'connection') !== false ||
                stripos($error_message, 'network') !== false) {
                continue; // On retry
            }
        }
        
        // Erreur non retryable ou toutes les tentatives épuisées
        break;
    }
    
    // Toutes les tentatives échouées
    return $last_error ?: new WP_Error('http_error', 'Unknown HTTP error');
}

/**
 * Résout les redirects MediaWiki proprement (sans parser du HTML).
 */
function poke_hub_bulbapedia_resolve_title($title) {
    $title = trim((string) $title);
    if ($title === '') return false;

    $api = poke_hub_bulbapedia_api_url();
    $url = add_query_arg([
        'action'    => 'query',
        'titles'    => $title,
        'redirects' => 1,
        'format'    => 'json',
    ], $api);

    $resp = poke_hub_http_request_with_retry($url, [
        'timeout' => 30,
    ]);

    if (is_wp_error($resp)) {
        poke_hub_tr_log('bulbapedia_resolve_title_error', [
            'title' => $title,
            'error' => $resp->get_error_message(),
            'code' => $resp->get_error_code()
        ]);
        return false;
    }
    
    $code = (int) wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        poke_hub_tr_log('bulbapedia_resolve_title_bad_status', [
            'title' => $title,
            'status_code' => $code
        ]);
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($data) || empty($data['query']['pages']) || !is_array($data['query']['pages'])) {
        return false;
    }

    $pages = $data['query']['pages'];
    $page = reset($pages);
    if (!is_array($page)) return false;

    if (!empty($page['missing'])) {
        return false;
    }

    $resolved = isset($page['title']) ? (string) $page['title'] : '';
    $resolved = trim($resolved);
    return $resolved !== '' ? $resolved : false;
}

/**
 * Liste les sections d'une page.
 */
function poke_hub_bulbapedia_api_get_sections($page_title) {
    $page_title = trim((string) $page_title);
    if ($page_title === '') return false;

    $api = poke_hub_bulbapedia_api_url();
    $url = add_query_arg([
        'action' => 'parse',
        'page'   => $page_title,
        'prop'   => 'sections',
        'format' => 'json',
    ], $api);

    $resp = poke_hub_http_request_with_retry($url, [
        'timeout' => 30,
    ]);

    if (is_wp_error($resp)) {
        poke_hub_tr_log('bulbapedia_get_sections_error', [
            'page_title' => $page_title,
            'error' => $resp->get_error_message(),
            'code' => $resp->get_error_code()
        ]);
        return false;
    }
    
    $code = (int) wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        poke_hub_tr_log('bulbapedia_get_sections_bad_status', [
            'page_title' => $page_title,
            'status_code' => $code
        ]);
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($data) || empty($data['parse']['sections']) || !is_array($data['parse']['sections'])) {
        return false;
    }

    return $data['parse']['sections'];
}

function poke_hub_bulbapedia_find_section_index(array $sections, array $wanted_titles) {
    foreach ($sections as $sec) {
        $line = isset($sec['line']) ? trim((string) $sec['line']) : '';
        if ($line === '') continue;

        foreach ($wanted_titles as $wanted) {
            if (strcasecmp($line, $wanted) === 0) {
                return isset($sec['index']) ? (string) $sec['index'] : null;
            }
        }
    }
    return null;
}

/**
 * Parse HTML de page: tente d'abord la section "In other languages"
 * pour éviter les pages énormes. Fallback sur full parse si besoin.
 */
function poke_hub_bulbapedia_api_parse_page_html($page_title) {
    $page_title = trim((string) $page_title);
    if ($page_title === '') return false;

    $api = poke_hub_bulbapedia_api_url();

    // 1) Sections -> "In other languages"
    $sections = poke_hub_bulbapedia_api_get_sections($page_title);
    if (is_array($sections)) {
        $idx = poke_hub_bulbapedia_find_section_index($sections, [
            'In other languages',
            'Names in other languages',
            'Other languages',
        ]);

        if ($idx !== null) {
            poke_hub_tr_log('api_parse: section', ['page' => $page_title, 'section' => $idx]);

            $resp = poke_hub_http_request_with_retry($api, [
                'method' => 'POST',
                'timeout' => 40,
                'connect_timeout' => 10,
                'body' => [
                    'action'  => 'parse',
                    'page'    => $page_title,
                    'prop'    => 'text',
                    'section' => $idx,
                    'format'  => 'json',
                ],
            ]);

            if (!is_wp_error($resp)) {
                $code = (int) wp_remote_retrieve_response_code($resp);
                if ($code === 200) {
                    $data = json_decode(wp_remote_retrieve_body($resp), true);
                    if (is_array($data) && !empty($data['parse']['text']['*'])) {
                        return [
                            'title' => $data['parse']['title'] ?? $page_title,
                            'html'  => (string) $data['parse']['text']['*'],
                        ];
                    }
                } else {
                    poke_hub_tr_log('api_parse_section_bad_status', [
                        'page' => $page_title,
                        'section' => $idx,
                        'status_code' => $code
                    ]);
                }
            } else {
                poke_hub_tr_log('api_parse_section_error', [
                    'page' => $page_title,
                    'section' => $idx,
                    'error' => $resp->get_error_message(),
                    'code' => $resp->get_error_code()
                ]);
            }
        }
    }

    // 2) Fallback: full parse (rare)
    poke_hub_tr_log('api_parse: full', ['page' => $page_title]);

    $resp = poke_hub_http_request_with_retry($api, [
        'method' => 'POST',
        'timeout' => 40,
        'connect_timeout' => 10,
        'body' => [
            'action' => 'parse',
            'page'   => $page_title,
            'prop'   => 'text',
            'format' => 'json',
        ],
    ]);

    if (is_wp_error($resp)) {
        poke_hub_tr_log('api_parse_full_error', [
            'page' => $page_title,
            'error' => $resp->get_error_message(),
            'code' => $resp->get_error_code()
        ]);
        return false;
    }
    
    $code = (int) wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        poke_hub_tr_log('api_parse_full_bad_status', [
            'page' => $page_title,
            'status_code' => $code
        ]);
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($data) || empty($data['parse']['text']['*'])) return false;

    return [
        'title' => $data['parse']['title'] ?? $page_title,
        'html'  => (string) $data['parse']['text']['*'],
    ];
}

/**
 * FULL parse (forcé) : utile uniquement pour certains fallbacks (ex: moves -> ja en infobox).
 */
function poke_hub_bulbapedia_api_parse_page_html_full($page_title) {
    $page_title = trim((string) $page_title);
    if ($page_title === '') return false;

    $api = poke_hub_bulbapedia_api_url();
    $resp = poke_hub_http_request_with_retry($api, [
        'method' => 'POST',
        'timeout' => 40,
        'connect_timeout' => 10,
        'body' => [
            'action' => 'parse',
            'page'   => $page_title,
            'prop'   => 'text',
            'format' => 'json',
        ],
    ]);

    if (is_wp_error($resp)) {
        poke_hub_tr_log('api_parse_full_forced_error', [
            'page' => $page_title,
            'error' => $resp->get_error_message(),
            'code' => $resp->get_error_code()
        ]);
        return false;
    }
    
    $code = (int) wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        poke_hub_tr_log('api_parse_full_forced_bad_status', [
            'page' => $page_title,
            'status_code' => $code
        ]);
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($data) || empty($data['parse']['text']['*'])) return false;

    return [
        'title' => $data['parse']['title'] ?? $page_title,
        'html'  => (string) $data['parse']['text']['*'],
    ];
}

/* -------------------------------------------------------------------------
 *  DOM helpers
 * ------------------------------------------------------------------------- */

function poke_hub_dom_from_html($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . (string) $html);
    libxml_clear_errors();
    return $dom;
}

function poke_hub_dom_inner_html(DOMDocument $dom, DOMNode $node) {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $dom->saveHTML($child);
    }
    return $html;
}

/* -------------------------------------------------------------------------
 *  Cleaning helpers
 * ------------------------------------------------------------------------- */

 function poke_hub_bulbapedia_clean_title_cell_html($cell_html) {
    $cell_html = (string) $cell_html;

    // Keep first line only
    $first_line = preg_split('/<br\s*\/?>/i', $cell_html, 2)[0];

    // Remove things where we DON'T want the content
    $first_line = preg_replace('/<sup[^>]*>.*?<\/sup>/is', '', $first_line);
    $first_line = preg_replace('/<small[^>]*>.*?<\/small>/is', '', $first_line);
    $first_line = preg_replace('/<i[^>]*>.*?<\/i>/is', '', $first_line);
    $first_line = preg_replace('/<img[^>]*>/is', '', $first_line);

    // IMPORTANT: keep text inside <span> and <a>, remove only the tags
    $first_line = preg_replace('/<\/?span[^>]*>/i', '', $first_line);
    $first_line = preg_replace('/<\/?a[^>]*>/i', '', $first_line);

    // Now strip any remaining tags
    $value = strip_tags($first_line);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Cleanup parentheses/brackets/asterisks
    $value = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $value);
    $value = preg_replace('/\s*\[[^\]]*\]\s*/u', ' ', $value);
    $value = preg_replace('/\s*\*+\s*/u', ' ', $value);

    // Normalize spaces
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value);
}

function poke_hub_bulbapedia_keep_non_ascii_leading($value) {
    $value = trim((string) $value);
    if ($value === '') return '';

    // Keep leading blocks of non-ascii until latin starts
    if (preg_match('/^([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $value, $m)) {
        return trim($m[1]);
    }

    // Fallback: first token
    $parts = preg_split('/\s+/u', $value, 2);
    return trim($parts[0] ?? $value);
}

/* -------------------------------------------------------------------------
 *  Language mapping
 * ------------------------------------------------------------------------- */

function poke_hub_bulbapedia_lang_map() {
    return [
        'English'  => 'en',
        'French'   => 'fr',
        'German'   => 'de',
        'Italian'  => 'it',
        'Spanish'  => 'es',
        'Japanese' => 'ja',
        'Korean'   => 'ko',
    ];
}

function poke_hub_bulbapedia_detect_lang_code_from_text($text, array $lang_map) {
    $text = trim((string) $text);
    if ($text === '') return null;

    foreach ($lang_map as $needle => $code) {
        if (stripos($text, $needle) !== false) {
            return $code;
        }
    }

    // Extra safety for Spanish variants
    if (stripos($text, 'Españ') !== false || stripos($text, 'Espan') !== false) {
        return 'es';
    }

    return null;
}

/* -------------------------------------------------------------------------
 *  Table finding/parsing
 * ------------------------------------------------------------------------- */

function poke_hub_bulbapedia_find_best_language_title_table(DOMXPath $xpath) {
    $tables = $xpath->query(
        '//table[.//th[contains(translate(normalize-space(.),"LANGUAGE","language"),"language")] and .//th[contains(translate(normalize-space(.),"TITLE","title"),"title")]]'
    );

    if (!$tables || $tables->length <= 0) return null;

    $best = null;
    $best_score = -1;

    foreach ($tables as $table) {
        if (!($table instanceof DOMElement)) continue;

        $rows = $xpath->query('.//tr[td]', $table);
        if (!$rows) continue;

        $score = 0;
        foreach ($rows as $row) {
            $tds = $row->getElementsByTagName('td');
            if ($tds->length >= 2) $score++;
        }

        // Boost nested tables slightly
        $anc = $xpath->query('ancestor::table', $table);
        if ($anc && $anc->length > 0) $score += 2;

        if ($score > $best_score) {
            $best_score = $score;
            $best = $table;
        }
    }

    return $best;
}

/**
 * Parse a Language/Title table (rowspan/colspan tolerant)
 */
function poke_hub_bulbapedia_parse_language_title_table(DOMDocument $dom, DOMXPath $xpath, DOMElement $table, array $lang_map) {
    $names = [];

    // Determine Title column index from first header row
    $title_index = null;
    $header_rows = $xpath->query('.//tr[th]', $table);
    if ($header_rows && $header_rows->length > 0) {
        $hdr = $header_rows->item(0);
        if ($hdr instanceof DOMElement) {
            $ths = $hdr->getElementsByTagName('th');
            $col = 0;
            foreach ($ths as $th) {
                $th_text = trim((string) $th->textContent);
                $colspan = (int) ($th->getAttribute('colspan') ?: 1);

                if ($title_index === null && stripos($th_text, 'Title') !== false) {
                    $title_index = $col;
                    break;
                }
                $col += max(1, $colspan);
            }
        }
    }
    if ($title_index === null) $title_index = -1;

    $rows = $xpath->query('.//tr[td]', $table);
    if (!$rows) return $names;

    $current_lang_code = null;

    foreach ($rows as $row) {
        if (!($row instanceof DOMElement)) continue;

        $tds = $row->getElementsByTagName('td');
        if ($tds->length < 2) continue;

        $cells = [];
        foreach ($tds as $td) $cells[] = $td;

        // Title cell
        $title_cell = null;
        if ($title_index >= 0 && isset($cells[$title_index])) {
            $title_cell = $cells[$title_index];
        } else {
            $title_cell = $cells[count($cells) - 1];
        }
        if (!($title_cell instanceof DOMElement)) continue;

        // Language candidate from first cell
        $lang_candidate = trim((string) $cells[0]->textContent);

        // Include first <a> text if any
        $a = $cells[0]->getElementsByTagName('a');
        if ($a && $a->length > 0) {
            $a_text = trim((string) $a->item(0)->textContent);
            if ($a_text !== '') {
                $lang_candidate = $a_text . ' ' . $lang_candidate;
            }
        }

        $detected = poke_hub_bulbapedia_detect_lang_code_from_text($lang_candidate, $lang_map);
        if ($detected) $current_lang_code = $detected;

        if (!$current_lang_code) continue;

        // Title extraction
        $title_html = poke_hub_dom_inner_html($dom, $title_cell);
        if ($title_html === '') $title_html = (string) $title_cell->textContent;

        $value = poke_hub_bulbapedia_clean_title_cell_html($title_html);
        if ($value === '') continue;

        if ($current_lang_code === 'ja' || $current_lang_code === 'ko') {
            $value = poke_hub_bulbapedia_keep_non_ascii_leading($value);
            if ($value === '') continue;
        }

        // French preference: if Canada & Europe exist -> keep Europe
        $row_text = strtolower(trim((string) $row->textContent));
        if ($current_lang_code === 'fr') {
            $is_canada = (strpos($row_text, 'canada') !== false);
            $is_europe = (strpos($row_text, 'europe') !== false);

            if ($is_canada && empty($names['fr'])) {
                continue;
            }
            if ($is_europe) {
                $names['fr'] = $value;
                continue;
            }
        }

        if (!isset($names[$current_lang_code])) {
            $names[$current_lang_code] = $value;
        }
    }

    return $names;
}

/* -------------------------------------------------------------------------
 *  Fallbacks
 * ------------------------------------------------------------------------- */

/**
 * Fallback: Japanese from infobox label row (works for some pages)
 */
function poke_hub_bulbapedia_fallback_japanese_from_infobox(DOMDocument $dom, DOMXPath $xpath) {
    $rows = $xpath->query('//table[contains(@class,"infobox") or contains(@class,"roundy")]//tr');
    if (!$rows) return '';

    foreach ($rows as $row) {
        if (!($row instanceof DOMElement)) continue;

        $ths = $row->getElementsByTagName('th');
        $tds = $row->getElementsByTagName('td');
        if ($ths->length < 1 || $tds->length < 1) continue;

        $label = trim((string) $ths->item(0)->textContent);
        if (stripos($label, 'Japanese') === false) continue;

        $val_html = poke_hub_dom_inner_html($dom, $tds->item($tds->length - 1));
        $val = poke_hub_bulbapedia_clean_title_cell_html($val_html);
        $val = poke_hub_bulbapedia_keep_non_ascii_leading($val);
        if ($val !== '') return $val;
    }

    return '';
}

/**
 * Fallback Bulbapedia attacks:
 * extrait le nom japonais depuis l'infobox (souvent absent du tableau "In other languages").
 * Exemple: <small><b><span class="explain" title="Hasamu">はさむ</span></b> <i>Clamp</i></small>
 */
function poke_hub_bulbapedia_attack_extract_ja_from_infobox($html) {
    if (empty($html)) {
        return '';
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($doc);

    // 1) Cas le plus fréquent: 1er <span class="explain"> dans le <small> du header infobox
    $nodes = $xpath->query(
        "//table[contains(concat(' ', normalize-space(@class), ' '), ' infobox ')]" .
        "//tr[1]//td//small//span[contains(concat(' ', normalize-space(@class), ' '), ' explain ')]"
    );

    if ($nodes && $nodes->length > 0) {
        $ja = trim($nodes->item(0)->textContent);
        $ja = html_entity_decode($ja, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ja = trim(preg_replace('/\xC2\xA0/u', ' ', $ja)); // nbsp
        return $ja;
    }

    // 2) Fallback plus large
    $nodes2 = $xpath->query(
        "//table[contains(concat(' ', normalize-space(@class), ' '), ' infobox ')]" .
        "//span[contains(concat(' ', normalize-space(@class), ' '), ' explain ')]"
    );

    if ($nodes2 && $nodes2->length > 0) {
        $ja = trim($nodes2->item(0)->textContent);
        $ja = html_entity_decode($ja, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ja = trim(preg_replace('/\xC2\xA0/u', ' ', $ja));
        return $ja;
    }

    return '';
}

/* -------------------------------------------------------------------------
 *  Core fetch: from page titles
 * ------------------------------------------------------------------------- */

function poke_hub_bulbapedia_fetch_official_names_from_page_titles(array $titles_to_try) {
    $lang_map = poke_hub_bulbapedia_lang_map();

    foreach ($titles_to_try as $raw_title) {
        $raw_title = trim((string) $raw_title);
        if ($raw_title === '') continue;

        $resolved = poke_hub_bulbapedia_resolve_title($raw_title);
        if ($resolved === false) {
            continue;
        }

        $parsed = poke_hub_bulbapedia_api_parse_page_html($resolved);
        if ($parsed === false || empty($parsed['html'])) {
            continue;
        }

        $dom = poke_hub_dom_from_html($parsed['html']);
        $xpath = new DOMXPath($dom);

        $table = poke_hub_bulbapedia_find_best_language_title_table($xpath);
        $names = [];

        if ($table) {
            $names = poke_hub_bulbapedia_parse_language_title_table($dom, $xpath, $table, $lang_map);
        }

        // Generic fallback ja (works sometimes)
        if (empty($names['ja'])) {
            $ja = poke_hub_bulbapedia_fallback_japanese_from_infobox($dom, $xpath);
            if ($ja !== '') $names['ja'] = $ja;
        }

        // Ensure en
        if (empty($names['en'])) {
            $names['en'] = $resolved;
        } else {
            $names['en'] = trim((string) $names['en']);
        }

        // Must have at least one translation besides en
        $has_translation = false;
        foreach ($names as $lang => $val) {
            if ($lang !== 'en' && trim((string) $val) !== '') {
                $has_translation = true;
                break;
            }
        }

        if (!$has_translation) {
            continue;
        }

        return $names;
    }

    return false;
}

/* -------------------------------------------------------------------------
 *  Pokémon specifics
 * ------------------------------------------------------------------------- */

function poke_hub_bulbapedia_is_special_form_name($name_en) {
    $name_en = trim((string) $name_en);
    if ($name_en === '') return false;

    // trailing numbers => costumes/variants (ex: "Pikachu 3017")
    if (preg_match('/\s+\d+$/u', $name_en)) return true;

    // regional forms
    if (preg_match('/\b(alola|alolan|galar|galarian|hisui|hisuian|paldea|paldean)\b/iu', $name_en)) return true;

    // common event/costume tokens
    if (preg_match('/\b(wcs|worlds|winter|summer|holiday|halloween|christmas|new year|go fest|gofest|fest|event|costume|hat|cap)\b/iu', $name_en)) return true;

    return false;
}

function poke_hub_bulbapedia_normalize_pokemon_base_title($name_en) {
    $name_en = trim((string) $name_en);
    if ($name_en === '') return '';

    $lower = strtolower($name_en);
    if ($lower === 'nidoran female') return 'Nidoran♀';
    if ($lower === 'nidoran male')   return 'Nidoran♂';

    return $name_en;
}

function poke_hub_pokemon_detect_mega_form($name_en) {
    $name_en = trim((string) $name_en);
    if ($name_en === '') return false;

    // Normalise espaces
    $normalized = preg_replace('/\s+/u', ' ', $name_en);

    // Formats acceptés :
    // - "Mega Charizard X"
    // - "Mega-Charizard X"
    // - "Mega Charizard (X)"
    // - "Mega Charizard (Y)"
    // - "Mega Blastoise"
    // - "Mega-Blastoise"
    if (!preg_match('/^mega[\s\-]+(.+)$/iu', $normalized, $m)) {
        return false;
    }

    $rest = trim($m[1]);

    // Détecte suffixe X/Y :
    // - fin " X" ou " Y"
    // - ou "(X)" "(Y)"
    $suffix = null;

    // Parenthèses
    if (preg_match('/^(.*)\s*\(([xy])\)\s*$/iu', $rest, $m2)) {
        $rest = trim($m2[1]);
        $suffix = strtoupper($m2[2]);
    } else {
        // fin " X" ou " Y"
        if (preg_match('/^(.*)\s+([xy])\s*$/iu', $rest, $m3)) {
            $rest = trim($m3[1]);
            $suffix = strtoupper($m3[2]);
        }
    }

    if ($rest === '') return false;

    return [
        'base_en' => $rest,
        'suffix'  => $suffix, // null | "X" | "Y"
    ];
}

function poke_hub_pokemon_value_already_mega($lang, $value) {
    $value = trim((string) $value);
    if ($value === '') return false;

    // Détection simple pour éviter "Méga-Méga-..."
    if ($lang === 'fr') {
        return (bool) preg_match('/^méga[\s\-]/iu', $value);
    }
    if ($lang === 'ja') {
        return (strpos($value, 'メガ') === 0);
    }
    if ($lang === 'ko') {
        return (mb_substr($value, 0, 2, 'UTF-8') === '메가');
    }

    // fallback latin
    return (bool) preg_match('/^mega[\s\-]/iu', $value);
}

function poke_hub_pokemon_apply_mega_prefix(array $names, $suffix = null) {
    // Préfixes par langue
    $prefix = [
        'en' => 'Mega ',
        'fr' => 'Méga-',
        'de' => 'Mega-',
        'it' => 'Mega',
        'es' => 'Mega',
        'ja' => 'メガ',
        'ko' => '메가',
    ];

    // Suffixe X/Y : la convention la plus “standard” (et celle vue sur Bulbapedia)
    // - en/fr/de/es : " X" / " Y"
    // - ja/ko : "X"/"Y" collé (ex: メガリザードンX)
    $suffix = $suffix ? strtoupper((string) $suffix) : null;
    if ($suffix !== 'X' && $suffix !== 'Y') $suffix = null;

    foreach ($names as $lang => $val) {
        $val = trim((string) $val);
        if ($val === '') continue;

        // Évite double Mega
        if (poke_hub_pokemon_value_already_mega($lang, $val)) {
            // Ajoute quand même suffixe si manquant
            if ($suffix && !preg_match('/\b' . preg_quote($suffix, '/') . '\b/u', $val)) {
                if ($lang === 'ja' || $lang === 'ko') {
                    $names[$lang] = $val . $suffix;
                } else {
                    $names[$lang] = $val . ' ' . $suffix;
                }
            }
            continue;
        }

        $p = $prefix[$lang] ?? $prefix['en'];

        // Concat selon langue
        if ($lang === 'ja' || $lang === 'ko') {
            // Pas d’espace, pas de tiret
            $new = $p . $val;
            if ($suffix) $new .= $suffix;
            $names[$lang] = $new;
            continue;
        }

        // Si préfixe finit par '-' => concat direct
        if (substr($p, -1) === '-') {
            $new = $p . $val;
        } else {
            $new = rtrim($p) . ' ' . $val;
        }

        if ($suffix) $new .= ' ' . $suffix;

        $names[$lang] = $new;
    }

    return $names;
}

/* -------------------------------------------------------------------------
 *  Public fetchers
 * ------------------------------------------------------------------------- */

 function poke_hub_pokemon_fetch_pokemon_official_names_from_bulbapedia($pokemon_name_en) {
    $pokemon_name_en = trim((string) $pokemon_name_en);
    if ($pokemon_name_en === '') return false;

    // ✅ Detect Mega (+ X/Y)
    $mega = poke_hub_pokemon_detect_mega_form($pokemon_name_en);
    $suffix = null;

    if ($mega) {
        $suffix = $mega['suffix'] ?? null;
        $pokemon_name_en = $mega['base_en'];
    }

    $base = poke_hub_bulbapedia_normalize_pokemon_base_title($pokemon_name_en);

    $titles_to_try = [
        $base . ' (Pokémon)',
        $base,
    ];

    $names = poke_hub_bulbapedia_fetch_official_names_from_page_titles($titles_to_try);
    if ($names === false) return false;

    // Normalize en suffix
    if (!empty($names['en'])) {
        $names['en'] = preg_replace('/\s*\(Pokémon\)\s*$/i', '', (string) $names['en']);
        $names['en'] = trim((string) $names['en']);
    }

    // ✅ Apply Mega prefix + X/Y suffix
    if ($mega) {
        $names = poke_hub_pokemon_apply_mega_prefix($names, $suffix);
    }

    return $names;
}

function poke_hub_pokemon_fetch_move_official_names_from_bulbapedia($move_name_en) {
    $move_name_en = trim((string) $move_name_en);
    if ($move_name_en === '') return false;

    $titles_to_try = [
        $move_name_en . ' (move)',
        $move_name_en,
    ];

    // 1) Parse section "In other languages" (rapide)
    $names = poke_hub_bulbapedia_fetch_official_names_from_page_titles($titles_to_try);
    if ($names === false) return false;

    // Normalize en
    if (!empty($names['en'])) {
        $names['en'] = preg_replace('/\s*\(move\)\s*$/i', '', (string) $names['en']);
        $names['en'] = trim((string) $names['en']);
    }

    // 2) ✅ Fallback JA spécifique aux moves: souvent uniquement dans l'infobox (span.explain)
    if (empty($names['ja'])) {
        // On tente d'abord le titre "(move)" (plus fiable), puis le titre brut
        $candidates = [$move_name_en . ' (move)', $move_name_en];

        foreach ($candidates as $candidate_title) {
            $resolved = poke_hub_bulbapedia_resolve_title($candidate_title);
            if ($resolved === false) continue;

            $parsed_full = poke_hub_bulbapedia_api_parse_page_html_full($resolved);
            if ($parsed_full === false || empty($parsed_full['html'])) continue;

            $ja = poke_hub_bulbapedia_attack_extract_ja_from_infobox($parsed_full['html']);
            if ($ja !== '') {
                $names['ja'] = $ja;
                break;
            }
        }
    }

    return $names;
}

function poke_hub_pokemon_fetch_type_official_names_from_bulbapedia($type_name_en) {
    $type_name_en = trim((string) $type_name_en);
    if ($type_name_en === '') return false;

    $type_uc = ucfirst(strtolower($type_name_en));

    $titles_to_try = [
        $type_uc . ' (type)',
        $type_uc . ' type',
        $type_uc,
    ];

    $names = poke_hub_bulbapedia_fetch_official_names_from_page_titles($titles_to_try);
    if ($names === false) return false;

    // Normalize en
    if (!empty($names['en'])) {
        $en = (string) $names['en'];
        $en = preg_replace('/\s*\(type\)\s*$/i', '', $en);
        $en = preg_replace('/\s+type\s*$/i', '', $en);
        $en = trim($en);
        if ($en !== '') $names['en'] = $en;
    }

    return $names;
}

// Compat wrapper (si tu avais encore du code qui appelle ça)
function poke_hub_pokemon_fetch_type_official_names_from_pokeapi($type_name) {
    return poke_hub_pokemon_fetch_type_official_names_from_bulbapedia($type_name);
}

/* -------------------------------------------------------------------------
 *  Bulk updaters (DB)
 * ------------------------------------------------------------------------- */

function poke_hub_bulbapedia_merge_names_into_extra($extra, $name_en, $name_fr_db, array $official, $force) {
    if (!is_array($extra)) $extra = [];
    if (!isset($extra['names']) || !is_array($extra['names'])) $extra['names'] = [];

    // Keep baseline
    $extra['names']['en'] = (string) $name_en;

    if (is_string($name_fr_db) && $name_fr_db !== '') {
        $extra['names']['fr'] = $name_fr_db;
    } elseif (!isset($extra['names']['fr'])) {
        $extra['names']['fr'] = '';
    }

    $changed = false;

    foreach ($official as $lang => $val) {
        if ($lang === 'en') continue;

        $val = (string) $val;
        if ($lang === 'ja' || $lang === 'ko') {
            $val = poke_hub_bulbapedia_keep_non_ascii_leading($val);
        }
        $val = trim($val);

        if ($val === '') continue;

        $existing = isset($extra['names'][$lang]) ? (string) $extra['names'][$lang] : '';

        if ($force) {
            if ($existing !== $val) {
                $extra['names'][$lang] = $val;
                $changed = true;
            }
        } else {
            if ($existing === '' || $existing === (string) $name_en) {
                if ($existing !== $val) {
                    $extra['names'][$lang] = $val;
                    $changed = true;
                }
            }
        }
    }

    return [$extra, $changed];
}

/**
 * Bulk: Pokémon
 */
function poke_hub_pokemon_fetch_official_names_existing($limit = 0, $force = false) {
    global $wpdb;

    if (function_exists('set_time_limit')) @set_time_limit(0);
    @ignore_user_abort(true);

    if (!function_exists('pokehub_get_table')) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0, 'message' => 'Helper function not found.'];
    }

    $pokemon_table = pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0, 'message' => 'Pokemon table not found.'];
    }

    $limit = (int) $limit;
    if ($limit <= 0) $limit = 20;

    $base_where = "WHERE name_en != ''";
    if (!$force) {
        // on met à jour surtout ceux dont le FR est manquant ou égal à EN
        $base_where .= " AND (name_fr = '' OR name_fr = name_en)";
    }

    $updated = 0;
    $skipped = 0;
    $errors  = 0;

    $processed_ids = [];
    $batch_size = max($limit * 3, 60);
    $loop_count = 0;
    $max_loops = 1000;

    while ($updated < $limit && $loop_count < $max_loops) {
        $loop_count++;

        $where = $base_where;
        if (!empty($processed_ids)) {
            $excluded_ids = implode(',', array_map('intval', $processed_ids));
            $where .= " AND id NOT IN ({$excluded_ids})";
        }

        $list = $wpdb->get_results(
            "SELECT id, dex_number, name_en, name_fr, extra
             FROM {$pokemon_table}
             {$where}
             ORDER BY id ASC
             LIMIT " . (int)$batch_size
        );

        if (empty($list)) break;

        foreach ($list as $row) {
            if ($updated >= $limit) break 2;

            $id = (int) $row->id;
            $processed_ids[] = $id;

            $name_en = trim((string) $row->name_en);
            if ($name_en === '') { $skipped++; continue; }

            if (poke_hub_bulbapedia_is_special_form_name($name_en)) {
                $skipped++;
                continue;
            }

            $official = poke_hub_pokemon_fetch_pokemon_official_names_from_bulbapedia($name_en);
            if ($official === false || empty($official)) {
                $errors++;
                continue;
            }

            $extra = [];
            if (!empty($row->extra)) {
                $decoded = json_decode((string)$row->extra, true);
                if (is_array($decoded)) $extra = $decoded;
            }

            list($extra_new, $changed) = poke_hub_bulbapedia_merge_names_into_extra(
                $extra,
                $name_en,
                (string)$row->name_fr,
                $official,
                (bool)$force
            );

            // Mirror fr into name_fr
            $new_name_fr = isset($extra_new['names']['fr']) ? trim((string)$extra_new['names']['fr']) : '';
            if ($new_name_fr === '' && !empty($official['fr'])) {
                $new_name_fr = trim((string)$official['fr']);
                if ($new_name_fr !== '') {
                    $extra_new['names']['fr'] = $new_name_fr;
                    $changed = true;
                }
            }

            $update_data = [
                'extra' => wp_json_encode($extra_new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $formats = ['%s'];

            if ($new_name_fr !== '' && (string)$row->name_fr !== $new_name_fr) {
                $update_data['name_fr'] = $new_name_fr;
                $formats[] = '%s';
                $changed = true;
            }

            if (!$changed) {
                $skipped++;
                continue;
            }

            $result = $wpdb->update(
                $pokemon_table,
                $update_data,
                ['id' => $id],
                $formats,
                ['%d']
            );

            if ($result === false) $errors++;
            elseif ($result > 0) $updated++;
            else $skipped++;
        }
    }

    return [
        'updated' => $updated,
        'skipped' => $skipped,
        'errors'  => $errors,
        'total'   => count($processed_ids),
    ];
}

/**
 * Bulk: Attacks/Moves
 */
function poke_hub_attacks_fetch_existing_official_names($limit = 0, $force = false) {
    global $wpdb;

    if (function_exists('set_time_limit')) @set_time_limit(0);
    @ignore_user_abort(true);

    if (!function_exists('pokehub_get_table')) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0, 'message' => 'Helper function not found.'];
    }

    $attacks_table = pokehub_get_table('attacks');
    if (!$attacks_table) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0, 'message' => 'Attacks table not found.'];
    }

    $limit = (int) $limit;
    if ($limit <= 0) $limit = 20;

    $base_where = "WHERE name_en != ''";
    if (!$force) {
        $base_where .= " AND (name_fr = '' OR name_fr = name_en)";
    }

    $updated = 0;
    $skipped = 0;
    $errors  = 0;

    $processed_ids = [];
    $batch_size = max($limit * 3, 60);
    $loop_count = 0;
    $max_loops = 1000;

    while ($updated < $limit && $loop_count < $max_loops) {
        $loop_count++;

        $where = $base_where;
        if (!empty($processed_ids)) {
            $excluded_ids = implode(',', array_map('intval', $processed_ids));
            $where .= " AND id NOT IN ({$excluded_ids})";
        }

        $list = $wpdb->get_results(
            "SELECT id, name_en, name_fr, extra
             FROM {$attacks_table}
             {$where}
             ORDER BY id ASC
             LIMIT " . (int)$batch_size
        );

        if (empty($list)) break;

        foreach ($list as $row) {
            if ($updated >= $limit) break 2;

            $id = (int) $row->id;
            $processed_ids[] = $id;

            $name_en = trim((string) $row->name_en);
            if ($name_en === '') { $skipped++; continue; }

            $official = poke_hub_pokemon_fetch_move_official_names_from_bulbapedia($name_en);
            if ($official === false || empty($official)) {
                $errors++;
                continue;
            }

            $extra = [];
            if (!empty($row->extra)) {
                $decoded = json_decode((string)$row->extra, true);
                if (is_array($decoded)) $extra = $decoded;
            }

            list($extra_new, $changed) = poke_hub_bulbapedia_merge_names_into_extra(
                $extra,
                $name_en,
                (string)$row->name_fr,
                $official,
                (bool)$force
            );

            // Mirror fr into name_fr
            $new_name_fr = isset($extra_new['names']['fr']) ? trim((string)$extra_new['names']['fr']) : '';
            if ($new_name_fr === '' && !empty($official['fr'])) {
                $new_name_fr = trim((string)$official['fr']);
                if ($new_name_fr !== '') {
                    $extra_new['names']['fr'] = $new_name_fr;
                    $changed = true;
                }
            }

            $update_data = [
                'extra' => wp_json_encode($extra_new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $formats = ['%s'];

            if ($new_name_fr !== '' && (string)$row->name_fr !== $new_name_fr) {
                $update_data['name_fr'] = $new_name_fr;
                $formats[] = '%s';
                $changed = true;
            }

            if (!$changed) { $skipped++; continue; }

            $result = $wpdb->update(
                $attacks_table,
                $update_data,
                ['id' => $id],
                $formats,
                ['%d']
            );

            if ($result === false) $errors++;
            elseif ($result > 0) $updated++;
            else $skipped++;
        }
    }

    return [
        'updated' => $updated,
        'skipped' => $skipped,
        'errors'  => $errors,
        'total'   => count($processed_ids),
    ];
}

/**
 * Bulk: Types
 */
function poke_hub_types_fetch_existing_official_names($limit = 0, $force = false) {
    global $wpdb;

    if (function_exists('set_time_limit')) @set_time_limit(0);
    @ignore_user_abort(true);

    if (!function_exists('pokehub_get_table')) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0, 'message' => 'Helper function not found.'];
    }

    $types_table = pokehub_get_table('pokemon_types');
    if (!$types_table) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0, 'message' => 'Types table not found.'];
    }

    $limit = (int) $limit;
    if ($limit <= 0) $limit = 20;

    $base_where = "WHERE name_en != ''";
    if (!$force) {
        $base_where .= " AND (name_fr = '' OR name_fr = name_en)";
    }

    $updated = 0;
    $skipped = 0;
    $errors  = 0;

    $processed_ids = [];
    $batch_size = max($limit * 3, 60);
    $loop_count = 0;
    $max_loops = 1000;

    while ($updated < $limit && $loop_count < $max_loops) {
        $loop_count++;

        $where = $base_where;
        if (!empty($processed_ids)) {
            $excluded_ids = implode(',', array_map('intval', $processed_ids));
            $where .= " AND id NOT IN ({$excluded_ids})";
        }

        $list = $wpdb->get_results(
            "SELECT id, name_en, name_fr, slug, extra
             FROM {$types_table}
             {$where}
             ORDER BY id ASC
             LIMIT " . (int)$batch_size
        );

        if (empty($list)) break;

        foreach ($list as $row) {
            if ($updated >= $limit) break 2;

            $id = (int) $row->id;
            $processed_ids[] = $id;

            $name_en = trim((string) $row->name_en);
            if ($name_en === '') { $skipped++; continue; }

            $official = poke_hub_pokemon_fetch_type_official_names_from_bulbapedia($name_en);
            if ($official === false || empty($official)) {
                $errors++;
                continue;
            }

            $extra = [];
            if (!empty($row->extra)) {
                $decoded = json_decode((string)$row->extra, true);
                if (is_array($decoded)) $extra = $decoded;
            }

            list($extra_new, $changed) = poke_hub_bulbapedia_merge_names_into_extra(
                $extra,
                $name_en,
                (string)$row->name_fr,
                $official,
                (bool)$force
            );

            // Mirror fr into name_fr
            $new_name_fr = isset($extra_new['names']['fr']) ? trim((string)$extra_new['names']['fr']) : '';
            if ($new_name_fr === '' && !empty($official['fr'])) {
                $new_name_fr = trim((string)$official['fr']);
                if ($new_name_fr !== '') {
                    $extra_new['names']['fr'] = $new_name_fr;
                    $changed = true;
                }
            }

            $update_data = [
                'extra' => wp_json_encode($extra_new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $formats = ['%s'];

            if ($new_name_fr !== '' && (string)$row->name_fr !== $new_name_fr) {
                $update_data['name_fr'] = $new_name_fr;
                $formats[] = '%s';
                $changed = true;
            }

            if (!$changed) { $skipped++; continue; }

            $result = $wpdb->update(
                $types_table,
                $update_data,
                ['id' => $id],
                $formats,
                ['%d']
            );

            if ($result === false) $errors++;
            elseif ($result > 0) $updated++;
            else $skipped++;
        }
    }

    return [
        'updated' => $updated,
        'skipped' => $skipped,
        'errors'  => $errors,
        'total'   => count($processed_ids),
    ];
}
