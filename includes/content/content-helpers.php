<?php
// File: includes/content/content-helpers.php
// Tables de contenu communes : source_type = post | special_event | global_pool, source_id = ID correspondant.
// Les dates (start_ts, end_ts) sont dupliquées ; la mise à jour des dates d'un event/post doit appeler pokehub_content_sync_dates_for_source().
//
// Lieu d'enregistrement unique : même préfixe que les tables Pokémon (poke_hub_pokemon_remote_prefix).
// Centralise : Pokémon nature, field research, habitats, bonus, nouveaux Pokémon, special research, collection challenges, œufs.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * À chaque sauvegarde d'un post (ou pokehub_event), synchronise les dates dans les tables de contenu.
 */
add_action('save_post', function ($post_id) {
    $post_type = get_post_type($post_id);
    $allowed = apply_filters('pokehub_content_sync_dates_post_types', ['post', 'pokehub_event']);
    if (!in_array($post_type, $allowed, true)) {
        return;
    }

    // Garde globale : pendant la génération des posts enfants "featured_hours",
    // on évite de relancer la sync `start_ts/end_ts` et d'entrer dans une cascade.
    if (!empty($GLOBALS['pokehub_skip_content_sync'])) {
        return;
    }

    // Les "featured_hours" créent des posts enfants classic event.
    // Pour éviter une cascade `save_post` -> sync -> update -> ... on saute
    // si c'est bien un child.
    if (function_exists('get_post_meta')) {
        $parent_id = get_post_meta($post_id, '_pokehub_featured_hours_parent_post_id', true);
        if (!empty($parent_id)) {
            return;
        }
    }

    if (function_exists('pokehub_content_get_dates_for_source')) {
        $dates = pokehub_content_get_dates_for_source('post', $post_id);
        if ($dates['start_ts'] > 0 || $dates['end_ts'] > 0) {
            pokehub_content_sync_dates_for_source('post', $post_id, $dates['start_ts'], $dates['end_ts']);
        }
    }
}, 20);

/**
 * Récupère les dates (start_ts, end_ts) pour une source.
 *
 * @param string $source_type 'post' | 'special_event' | 'global_pool'
 * @param int    $source_id   ID du post, de l'event spécial, ou du pool (0 pour global_pool sans id).
 * @return array{start_ts: int, end_ts: int} Timestamps (0 si non trouvé).
 */
function pokehub_content_get_dates_for_source($source_type, $source_id) {
    $source_type = (string) $source_type;
    $source_id   = (int) $source_id;

    if ($source_type === 'post' && $source_id > 0 && function_exists('poke_hub_events_get_post_dates')) {
        $dates = poke_hub_events_get_post_dates($source_id);
        return [
            'start_ts' => isset($dates['start_ts']) ? (int) $dates['start_ts'] : 0,
            'end_ts'   => isset($dates['end_ts'])   ? (int) $dates['end_ts']   : 0,
        ];
    }

    if ($source_type === 'special_event' && $source_id > 0 && function_exists('pokehub_get_table')) {
        global $wpdb;
        $table = pokehub_get_table('special_events');
        if ($table) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT start_ts, end_ts FROM {$table} WHERE id = %d",
                $source_id
            ));
            if ($row) {
                return [
                    'start_ts' => (int) $row->start_ts,
                    'end_ts'   => (int) $row->end_ts,
                ];
            }
        }
    }

    return ['start_ts' => 0, 'end_ts' => 0];
}

/**
 * Met à jour les dates (start_ts, end_ts) dans toutes les tables de contenu pour une source.
 * À appeler quand on modifie les dates d'un post ou d'un événement spécial.
 *
 * @param string $source_type 'post' | 'special_event' | 'global_pool'
 * @param int    $source_id   ID de la source.
 * @param int    $start_ts    Timestamp de début.
 * @param int    $end_ts      Timestamp de fin.
 */
function pokehub_content_sync_dates_for_source($source_type, $source_id, $start_ts, $end_ts) {
    global $wpdb;
    $source_type = (string) $source_type;
    $source_id   = (int) $source_id;
    $start_ts    = (int) $start_ts;
    $end_ts      = (int) $end_ts;

    static $content_tables_ensured = false;

    $tables_with_dates = [
        'content_eggs',
        'content_quests',
        'content_habitats',
        'content_special_research',
        'content_collection_challenges',
        'content_bonus',
        'content_wild_pokemon',
        'content_new_pokemon',
        'content_day_pokemon_hours',
        'content_raids',
        'content_go_pass',
    ];

    foreach ($tables_with_dates as $key) {
        if (!function_exists('pokehub_get_table')) {
            continue;
        }
        $table = pokehub_get_table($key);
        if (empty($table)) {
            continue;
        }

        // La sauvegarde de featured-hours déclenche souvent `save_post` en cascade.
        // Si certaines tables n'existent pas encore (install fraîche / mauvaise config modules),
        // on évite de planter toute la sauvegarde.
        $table_name = $table;
        if (strpos($table_name, '.') !== false) {
            $parts = explode('.', $table_name);
            $table_name = (string) end($parts);
        }

        $table_exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            $wpdb->dbname,
            $table_name
        )) > 0;

        if (!$table_exists) {
            if (!$content_tables_ensured && class_exists('Pokehub_DB') && function_exists('pokehub_get_table')) {
                // Tente une (re)création des tables de contenu manquantes.
                try {
                    // Certains blocs (ex: Day Pokémon Hours / Featured Hours) utilisent des tables
                    // de "content" qui ne sont créées que si le module `blocks` est inclus.
                    Pokehub_DB::getInstance()->createTables(['events', 'eggs', 'quests', 'blocks']);
                } catch (Throwable $t) {
                    // ignore; la table pourrait rester manquante dans certains setups.
                }
                $content_tables_ensured = true;
            } elseif (!$content_tables_ensured && !class_exists('Pokehub_DB') && function_exists('pokehub_get_table')) {
                // Si la classe n'est pas encore chargée, on tente de la charger.
                try {
                    $db_file = dirname(__DIR__) . '/pokehub-db.php';
                    if (file_exists($db_file)) {
                        require_once $db_file;
                    }
                    if (class_exists('Pokehub_DB')) {
                        // Certains blocs (ex: Day Pokémon Hours / Featured Hours) utilisent des tables
                        // de "content" qui ne sont créées que si le module `blocks` est inclus.
                        Pokehub_DB::getInstance()->createTables(['events', 'eggs', 'quests', 'blocks']);
                        $content_tables_ensured = true;
                    }
                } catch (Throwable $t) {
                    // ignore
                }
            }

            $table_exists = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                $wpdb->dbname,
                $table_name
            )) > 0;
        }

        if (!$table_exists) {
            continue;
        }

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET start_ts = %d, end_ts = %d WHERE source_type = %s AND source_id = %d",
            $start_ts,
            $end_ts,
            $source_type,
            $source_id
        ));
    }
}

if (!function_exists('pokehub_content_table_has_column')) {
    /**
     * Vérifie la présence d'une colonne SQL pour une table de contenu.
     */
    function pokehub_content_table_has_column(string $table_name, string $column_name): bool {
        static $cache = [];
        $table_name = trim($table_name);
        $column_name = trim($column_name);
        if ($table_name === '' || $column_name === '') {
            return false;
        }
        $key = $table_name . '::' . $column_name;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        global $wpdb;
        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s
               AND COLUMN_NAME = %s",
            $wpdb->dbname,
            $table_name,
            $column_name
        ));
        $cache[$key] = !empty($col);
        return $cache[$key];
    }
}

// --- Œufs ---

/**
 * Récupère l'enregistrement content_eggs pour une source (ou null).
 *
 * @param string $source_type 'post' | 'special_event' | 'global_pool'
 * @param int    $source_id   ID.
 * @return object|null
 */
function pokehub_content_get_eggs_row($source_type, $source_id) {
    global $wpdb;
    $table = function_exists('pokehub_get_table') ? pokehub_get_table('content_eggs') : '';
    if (!$table) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1",
        (string) $source_type,
        (int) $source_id
    ));
}

/**
 * Récupère les œufs pour une source (structure par type d'œuf + Pokémon).
 *
 * @param string $source_type 'post' | 'special_event' | 'global_pool'
 * @param int    $source_id   ID.
 * @return array Format [ ['egg_type_id' => int, 'pokemon' => [ ['pokemon_id', 'rarity', 'is_forced_shiny', 'is_worldwide_override', ...] ] ], ... ]
 */
function pokehub_content_get_eggs($source_type, $source_id) {
    global $wpdb;
    $row = pokehub_content_get_eggs_row($source_type, $source_id);
    if (!$row) {
        return [];
    }
    $egg_tbl  = pokehub_get_table('content_egg_pokemon');
    if (!$egg_tbl) {
        return [];
    }
    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$egg_tbl} WHERE content_egg_id = %d ORDER BY egg_type_id ASC, sort_order ASC, id ASC",
        (int) $row->id
    ));
    $by_type = [];
    foreach ($entries as $e) {
        $et_id = (int) $e->egg_type_id;
        if (!isset($by_type[$et_id])) {
            $by_type[$et_id] = ['egg_type_id' => $et_id, 'pokemon' => []];
        }
        // is_shiny, is_regional, cp_min/cp_max sont calculés à l'affichage (helpers Pokémon)
        $by_type[$et_id]['pokemon'][] = [
            'pokemon_id'            => (int) $e->pokemon_id,
            'rarity'                => (int) $e->rarity,
            'is_worldwide_override' => (bool) $e->is_worldwide_override,
            'is_forced_shiny'       => (bool) $e->is_forced_shiny,
            'gender'                => isset($e->gender) && in_array((string) $e->gender, ['male', 'female'], true) ? (string) $e->gender : null,
        ];
    }
    return array_values($by_type);
}

/**
 * Sauvegarde les œufs pour une source. Crée ou met à jour la ligne content_eggs et les entrées content_egg_pokemon.
 * Les dates sont déduites de la source (post/special_event) ou déjà présentes (global_pool).
 *
 * @param string $source_type 'post' | 'special_event' | 'global_pool'
 * @param int    $source_id   ID.
 * @param array  $eggs        Format [ ['egg_type_id' => int, 'pokemon' => [ [...] ] ], ... ]
 * @param int    $start_ts    Optionnel. Si fourni (et > 0), utilisé pour la ligne content_eggs.
 * @param int    $end_ts      Optionnel.
 * @param string $name        Optionnel. Pour global_pool.
 */
function pokehub_content_save_eggs($source_type, $source_id, array $eggs, $start_ts = 0, $end_ts = 0, $name = null) {
    global $wpdb;
    $source_type = (string) $source_type;
    $source_id   = (int) $source_id;

    $eggs_tbl = pokehub_get_table('content_eggs');
    $pokemon_tbl = pokehub_get_table('content_egg_pokemon');
    if (!$eggs_tbl || !$pokemon_tbl) {
        return;
    }
    $egg_has_gender_col = pokehub_content_table_has_column($pokemon_tbl, 'gender');

    $dates = ($start_ts > 0 || $end_ts > 0)
        ? ['start_ts' => (int) $start_ts, 'end_ts' => (int) $end_ts]
        : pokehub_content_get_dates_for_source($source_type, $source_id);

    $row = pokehub_content_get_eggs_row($source_type, $source_id);
    if ($row) {
        $wpdb->update(
            $eggs_tbl,
            [
                'start_ts'   => $dates['start_ts'],
                'end_ts'     => $dates['end_ts'],
                'name'       => $name !== null ? (string) $name : $row->name,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $row->id],
            ['%d', '%d', '%s', '%s'],
            ['%d']
        );
        $content_egg_id = (int) $row->id;
        $wpdb->query($wpdb->prepare("DELETE FROM {$pokemon_tbl} WHERE content_egg_id = %d", $content_egg_id));
    } else {
        $wpdb->insert(
            $eggs_tbl,
            [
                'source_type' => $source_type,
                'source_id'   => $source_id,
                'start_ts'    => $dates['start_ts'],
                'end_ts'      => $dates['end_ts'],
                'name'        => $name !== null ? (string) $name : null,
            ],
            ['%s', '%d', '%d', '%d', '%s']
        );
        $content_egg_id = (int) $wpdb->insert_id;
    }

    $sort = 0;
    foreach ($eggs as $group) {
        $et_id = isset($group['egg_type_id']) ? (int) $group['egg_type_id'] : 0;
        if ($et_id <= 0) {
            continue;
        }
        $list = isset($group['pokemon']) && is_array($group['pokemon']) ? $group['pokemon'] : [];
        foreach ($list as $p) {
            $pid = isset($p['pokemon_id']) ? (int) $p['pokemon_id'] : 0;
            if ($pid <= 0) {
                continue;
            }
            $insert_data = [
                'content_egg_id'         => $content_egg_id,
                'egg_type_id'             => $et_id,
                'pokemon_id'              => $pid,
                'rarity'                  => isset($p['rarity']) ? max(1, min(5, (int) $p['rarity'])) : 1,
                'is_worldwide_override'   => !empty($p['is_worldwide_override']) ? 1 : 0,
                'is_forced_shiny'         => !empty($p['is_forced_shiny']) ? 1 : 0,
                'sort_order'              => $sort++,
            ];
            $insert_format = ['%d', '%d', '%d', '%d', '%d', '%d', '%d'];
            if ($egg_has_gender_col) {
                $insert_data['gender'] = isset($p['gender']) && in_array((string) $p['gender'], ['male', 'female'], true) ? (string) $p['gender'] : null;
                array_splice($insert_format, 3, 0, '%s');
            }
            $wpdb->insert(
                $pokemon_tbl,
                $insert_data,
                $insert_format
            );
        }
    }
}

/**
 * Récupère les contenus œufs actifs à un instant T (ou tous si $timestamp = null).
 *
 * @param int|null    $timestamp   Timestamp UTC. Si null, tous les contenus.
 * @param string|null $source_type Optionnel. 'post'|'special_event'|'global_pool' pour filtrer.
 * @return array<object> Lignes content_eggs.
 */
function pokehub_content_get_eggs_active_at($timestamp = null, $source_type = null) {
    global $wpdb;
    $table = pokehub_get_table('content_eggs');
    if (!$table) {
        return [];
    }
    $where = ['1=1'];
    $params = [];
    if ($timestamp !== null) {
        $ts = (int) $timestamp;
        $where[] = 'start_ts <= %d AND (end_ts = 0 OR end_ts >= %d)';
        $params[] = $ts;
        $params[] = $ts;
    }
    if ($source_type !== null && $source_type !== '') {
        $where[] = 'source_type = %s';
        $params[] = (string) $source_type;
    }
    $sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY start_ts DESC';
    if (!empty($params)) {
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    return $wpdb->get_results($sql);
}

/**
 * Agrège tous les œufs actifs à un instant T : par egg_type_id, liste de pokemon (dédupliquée par pokemon_id, rareté max).
 *
 * @param int|null $timestamp Si null, time().
 * @return array [ egg_type_id => [ ['pokemon_id'=>, 'rarity'=>, 'is_worldwide_override'=>, 'is_forced_shiny'=>], ... ], ... ]
 */
function pokehub_content_get_all_eggs_aggregated_at($timestamp = null) {
    global $wpdb;
    if ($timestamp === null) {
        $timestamp = time();
    }
    $timestamp = (int) $timestamp;
    $rows = pokehub_content_get_eggs_active_at($timestamp, null);
    if (empty($rows)) {
        return [];
    }
    $egg_tbl = pokehub_get_table('content_egg_pokemon');
    if (!$egg_tbl) {
        return [];
    }
    $by_type = [];
    foreach ($rows as $row) {
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$egg_tbl} WHERE content_egg_id = %d ORDER BY egg_type_id ASC, sort_order ASC, id ASC",
            (int) $row->id
        ));
        if (empty($entries)) {
            continue;
        }
        foreach ($entries as $e) {
            $et_id = (int) $e->egg_type_id;
            if (!isset($by_type[$et_id])) {
                $by_type[$et_id] = [];
            }
            $key = (int) $e->pokemon_id;
            $entry = [
                'pokemon_id'            => (int) $e->pokemon_id,
                'rarity'                => max(1, min(5, (int) $e->rarity)),
                'is_worldwide_override' => !empty($e->is_worldwide_override),
                'is_forced_shiny'       => !empty($e->is_forced_shiny),
                'gender'                => isset($e->gender) && in_array((string) $e->gender, ['male', 'female'], true) ? (string) $e->gender : null,
            ];
            if (!isset($by_type[$et_id][$key]) || $entry['rarity'] > $by_type[$et_id][$key]['rarity']) {
                $by_type[$et_id][$key] = $entry;
            } elseif ($by_type[$et_id][$key]['gender'] === null && $entry['gender'] !== null) {
                $by_type[$et_id][$key]['gender'] = $entry['gender'];
            }
        }
    }
    foreach ($by_type as $et_id => $list) {
        $by_type[$et_id] = array_values($list);
    }
    return $by_type;
}

// --- Quêtes ---

/**
 * Récupère toutes les lignes content_quests (pour l’admin module Quêtes).
 *
 * @return array<object>
 */
function pokehub_content_get_all_quests_rows() {
    global $wpdb;
    $table = pokehub_get_table('content_quests');
    if (!$table) {
        return [];
    }
    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY source_type ASC, source_id ASC, id ASC");
}

/**
 * Récupère les lignes content_quests actives à un instant T.
 *
 * @param int|null $timestamp Timestamp UTC. Si null, tous les enregistrements.
 * @return array<object> Lignes content_quests.
 */
function pokehub_content_get_quests_active_at($timestamp = null) {
    global $wpdb;
    $table = pokehub_get_table('content_quests');
    if (!$table) {
        return [];
    }
    $where = ['1=1'];
    $params = [];
    if ($timestamp !== null) {
        $ts = (int) $timestamp;
        $where[] = 'start_ts <= %d AND (end_ts = 0 OR end_ts >= %d)';
        $params[] = $ts;
        $params[] = $ts;
    }
    $sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY source_type ASC, start_ts DESC';
    if (!empty($params)) {
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    return $wpdb->get_results($sql);
}

/**
 * Méta : IDs des lignes `content_quests` créées par la métabox « Day Pokémon Hours » (onglet type quêtes).
 * Ne doit pas être mélangé avec les Field Research (même table), sinon sauvegarde / affichage cassent.
 */
function pokehub_content_day_schedule_quest_meta_key(): string {
    return '_pokehub_day_schedule_content_quest_ids';
}

/**
 * @return int[] IDs d'en-têtes content_quests gérés uniquement par Day Pokémon Hours.
 */
function pokehub_content_get_day_schedule_quest_header_ids_for_source(string $source_type, int $source_id): array {
    if ((string) $source_type !== 'post' || $source_id <= 0 || !function_exists('get_post_meta')) {
        return [];
    }
    $raw = get_post_meta((int) $source_id, pokehub_content_day_schedule_quest_meta_key(), true);
    if (!is_array($raw)) {
        return [];
    }
    $ids = array_values(array_filter(array_map('intval', $raw), static function ($id) {
        return $id > 0;
    }));
    return array_values(array_unique($ids));
}

/**
 * Clé JSON dans chaque récompense créée par la métabox Day Pokémon Hours (quêtes par jour).
 * Permet de purger / filtrer sans confondre avec les Field Research (même table content_quests).
 */
function pokehub_content_day_quest_reward_marker_key(): string {
    return '_pokehub_day_hours';
}

/**
 * IDs d'en-têtes content_quests liés à la métabox : présence du marqueur dans content_quest_lines.rewards.
 *
 * @return int[]
 */
function pokehub_content_get_day_quest_ids_with_hours_marker(int $post_id): array {
    global $wpdb;
    if ($post_id <= 0 || !function_exists('pokehub_get_table')) {
        return [];
    }
    $quests_tbl = pokehub_get_table('content_quests');
    $lines_tbl = pokehub_get_table('content_quest_lines');
    if (!$quests_tbl || !$lines_tbl) {
        return [];
    }
    $needle = '%' . $wpdb->esc_like('"' . pokehub_content_day_quest_reward_marker_key() . '"') . '%';
    $col = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT cq.id FROM {$quests_tbl} cq
             INNER JOIN {$lines_tbl} ql ON ql.content_quest_id = cq.id
             WHERE cq.source_type = 'post' AND cq.source_id = %d
               AND ql.rewards LIKE %s",
            $post_id,
            $needle
        )
    );
    return array_values(array_filter(array_map('intval', (array) $col)));
}

/**
 * Récupère la ligne content_quests pour une source.
 */
function pokehub_content_get_quests_row($source_type, $source_id) {
    global $wpdb;
    $table = pokehub_get_table('content_quests');
    if (!$table) {
        return null;
    }
    $exclude_ids = pokehub_content_get_day_schedule_quest_header_ids_for_source((string) $source_type, (int) $source_id);
    if (!empty($exclude_ids)) {
        $placeholders = implode(',', array_fill(0, count($exclude_ids), '%d'));
        $sql          = "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d AND id NOT IN ({$placeholders}) ORDER BY id DESC LIMIT 1";
        $row          = $wpdb->get_row($wpdb->prepare($sql, array_merge([(string) $source_type, (int) $source_id], $exclude_ids)));
    } else {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d ORDER BY id DESC LIMIT 1",
            (string) $source_type,
            (int) $source_id
        ));
    }
    return $row;
}

/**
 * Récupère la ligne content_quests par ID (pour l’admin module Quêtes).
 *
 * @param int $id content_quests.id
 * @return object|null
 */
function pokehub_content_get_quests_row_by_id($id) {
    global $wpdb;
    $table = pokehub_get_table('content_quests');
    if (!$table || (int) $id <= 0) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
        (int) $id
    ));
}

/**
 * Récupère les lignes de quêtes (task, rewards, quest_group_id) pour un content_quest_id.
 * Même format que pokehub_content_get_quests().
 *
 * @param int $content_quest_id content_quests.id
 * @return array [ ['task' => '', 'rewards' => [], 'quest_group_id' => 0 ], ... ]
 */
function pokehub_content_get_quests_by_content_quest_id($content_quest_id) {
    global $wpdb;
    $content_quest_id = (int) $content_quest_id;
    if ($content_quest_id <= 0) {
        return [];
    }
    $table = pokehub_get_table('content_quest_lines');
    if (!$table) {
        return [];
    }
    $lines = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE content_quest_id = %d ORDER BY sort_order ASC, id ASC",
        $content_quest_id
    ));
    $out = [];
    foreach ($lines as $l) {
        $rewards_raw = isset($l->rewards) ? $l->rewards : '';
        $rewards     = [];
        if ($rewards_raw !== '' && $rewards_raw !== null) {
            $rewards = json_decode($rewards_raw, true);
            if (is_string($rewards)) {
                $rewards = json_decode($rewards, true);
            }
            $rewards = is_array($rewards) ? $rewards : [];
        }
        $out[] = [
            'task'           => (string) (isset($l->task) ? $l->task : ''),
            'rewards'        => pokehub_content_normalize_quest_rewards($rewards),
            'quest_group_id' => isset($l->quest_group_id) ? (int) $l->quest_group_id : 0,
        ];
    }
    return $out;
}

/**
 * Normalise les récompenses de quête pour que la metabox reçoive toujours la structure attendue
 * (notamment pokemon_ids en tableau d'entiers pour les récompenses Pokémon).
 *
 * @param array $rewards Tableau de récompenses (tel que décodé depuis la BDD).
 * @return array
 */
function pokehub_content_normalize_quest_rewards(array $rewards) {
    $normalized = [];
    foreach ($rewards as $reward) {
        if (is_object($reward)) {
            $reward = json_decode(wp_json_encode($reward), true);
        }
        if (!is_array($reward)) {
            continue;
        }
        $type = isset($reward['type']) ? sanitize_key($reward['type']) : 'pokemon';
        $norm = ['type' => $type];
        if ($type === 'pokemon') {
            $raw_tokens = [];
            if (isset($reward['pokemon_ids'])) {
                $raw = $reward['pokemon_ids'];
                if (is_array($raw)) {
                    $raw_tokens = $raw;
                } elseif (is_string($raw)) {
                    $raw_tokens = array_map('trim', explode(',', $raw));
                } else {
                    $raw_tokens = (array) $raw;
                }
            } elseif (isset($reward['pokemon_id']) && $reward['pokemon_id'] !== '' && $reward['pokemon_id'] !== null) {
                $raw_tokens = [(string) $reward['pokemon_id']];
            }
            $raw_genders = isset($reward['pokemon_genders']) && is_array($reward['pokemon_genders']) ? $reward['pokemon_genders'] : [];
            if (function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
                $parsed = pokehub_parse_post_pokemon_multiselect_tokens_with_genders($raw_tokens, $raw_genders);
                $norm['pokemon_ids'] = $parsed['pokemon_ids'];
                $norm['pokemon_genders'] = $parsed['pokemon_genders'];
            } else {
                $norm['pokemon_ids'] = array_values(array_map('intval', array_filter($raw_tokens, function ($id) {
                    return $id !== '' && $id !== null && is_numeric($id) && (int) $id > 0;
                })));
                $norm['pokemon_genders'] = [];
                foreach ($raw_genders as $pid => $gender) {
                    $pid = (int) $pid;
                    if ($pid > 0 && in_array($gender, ['male', 'female'], true)) {
                        $norm['pokemon_genders'][$pid] = $gender;
                    }
                }
            }
            $norm['force_shiny'] = !empty($reward['force_shiny']);
        } elseif (in_array($type, ['candy', 'xl_candy', 'mega_energy'], true)) {
            $norm['pokemon_id'] = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
            $norm['quantity']   = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
        } elseif ($type === 'item') {
            $norm['item_id']   = isset($reward['item_id']) ? (int) $reward['item_id'] : 0;
            $norm['item_name'] = isset($reward['item_name']) ? sanitize_text_field($reward['item_name']) : '';
            $norm['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
        } else {
            $norm['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
        }
        $normalized[] = $norm;
    }
    return $normalized;
}

/**
 * Récupère les quêtes pour une source (format comme avant : [ ['task' => '', 'rewards' => [] ], ... ]).
 */
function pokehub_content_get_quests($source_type, $source_id) {
    $row = pokehub_content_get_quests_row($source_type, $source_id);
    if (!$row) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('content_quest_lines');
    if (!$table) {
        return [];
    }
    $lines = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE content_quest_id = %d ORDER BY sort_order ASC, id ASC",
        (int) $row->id
    ));
    $out = [];
    foreach ($lines as $l) {
        $rewards_raw = isset($l->rewards) ? $l->rewards : '';
        $rewards     = [];
        if ($rewards_raw !== '' && $rewards_raw !== null) {
            $rewards = json_decode($rewards_raw, true);
            if (is_string($rewards)) {
                $rewards = json_decode($rewards, true);
            }
            $rewards = is_array($rewards) ? $rewards : [];
        }
        $out[] = [
            'task'           => (string) (isset($l->task) ? $l->task : ''),
            'rewards'        => pokehub_content_normalize_quest_rewards($rewards),
            'quest_group_id' => isset($l->quest_group_id) ? (int) $l->quest_group_id : 0,
        ];
    }
    return $out;
}

/**
 * Sauvegarde les quêtes pour une source.
 *
 * @param string $source_type
 * @param int    $source_id
 * @param array  $quests [ ['task' => '', 'rewards' => [], 'quest_group_id' => 0 ], ... ]
 */
function pokehub_content_save_quests($source_type, $source_id, array $quests) {
    global $wpdb;
    $source_type = (string) $source_type;
    $source_id   = (int) $source_id;
    $quests_tbl  = pokehub_get_table('content_quests');
    $lines_tbl   = pokehub_get_table('content_quest_lines');
    if (!$quests_tbl || !$lines_tbl) {
        return;
    }

    $dates = pokehub_content_get_dates_for_source($source_type, $source_id);
    $row   = pokehub_content_get_quests_row($source_type, $source_id);

    if ($row) {
        $wpdb->update($quests_tbl, [
            'start_ts'   => $dates['start_ts'],
            'end_ts'     => $dates['end_ts'],
            'updated_at' => current_time('mysql'),
        ], ['id' => $row->id], ['%d', '%d', '%s'], ['%d']);
        $content_quest_id = (int) $row->id;
        $wpdb->query($wpdb->prepare("DELETE FROM {$lines_tbl} WHERE content_quest_id = %d", $content_quest_id));
    } else {
        $header_inserted = $wpdb->insert($quests_tbl, [
            'source_type' => $source_type,
            'source_id'   => $source_id,
            'start_ts'    => $dates['start_ts'],
            'end_ts'      => $dates['end_ts'],
        ], ['%s', '%d', '%d', '%d']);
        if ($header_inserted === false) {
            return;
        }
        $content_quest_id = (int) $wpdb->insert_id;
        if ($content_quest_id <= 0) {
            return;
        }
    }

    $sort = 0;
    foreach ($quests as $q) {
        $task    = isset($q['task']) ? trim(sanitize_text_field($q['task'])) : '';
        $rewards = isset($q['rewards']) && is_array($q['rewards']) ? $q['rewards'] : [];
        if ($task === '' && empty($rewards)) {
            continue;
        }
        $quest_group_id = isset($q['quest_group_id']) ? max(0, (int) $q['quest_group_id']) : 0;
        $line_inserted = $wpdb->insert($lines_tbl, [
            'content_quest_id' => $content_quest_id,
            'quest_group_id'  => $quest_group_id > 0 ? $quest_group_id : null,
            'task'            => $task,
            'rewards'         => wp_json_encode($rewards),
            'sort_order'      => $sort++,
        ], ['%d', '%d', '%s', '%s', '%d']);
        if ($line_inserted === false) {
            continue;
        }
    }
}

if (!function_exists('pokehub_quests_parse_pokemon_ids_from_reward_input')) {
    /**
     * Extrait les IDs Pokémon d'une récompense brute (POST / parse_str / JSON).
     * Gère tableau, chaîne "25" ou "25,133", scalaire numérique, objet stdClass.
     *
     * @param array $reward
     * @return int[]
     */
    function pokehub_quests_parse_pokemon_ids_from_reward_input(array $reward): array {
        $tokens = [];
        if (isset($reward['pokemon_ids'])) {
            $raw = $reward['pokemon_ids'];
            if (is_object($raw)) {
                $raw = array_values((array) $raw);
            }
            if (is_array($raw)) {
                $tokens = $raw;
            } elseif (is_string($raw) || is_numeric($raw)) {
                $s = trim((string) $raw);
                if ($s === '') {
                    return [];
                }
                $tokens = strpos($s, ',') !== false ? array_map('trim', explode(',', $s)) : [$s];
            }
        } elseif (isset($reward['pokemon_id']) && $reward['pokemon_id'] !== '' && $reward['pokemon_id'] !== null) {
            $tokens = [(string) $reward['pokemon_id']];
        }
        if ($tokens !== [] && function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
            return pokehub_parse_post_pokemon_multiselect_tokens_with_genders($tokens, null)['pokemon_ids'];
        }
        $ids = [];
        foreach ($tokens as $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            if (is_string($v) && preg_match('/^(\d+)\|(male|female)$/i', (string) $v, $m)) {
                $n = (int) $m[1];
                if ($n > 0) {
                    $ids[] = $n;
                }
                continue;
            }
            if (is_numeric($v)) {
                $n = (int) $v;
                if ($n > 0) {
                    $ids[] = $n;
                }
            }
        }
        return array_values(array_unique($ids));
    }
}

/**
 * Nettoie un tableau de quêtes issu d'une requête (POST) pour enregistrement.
 * Utilisé par la metabox events et par le module Quêtes (indépendant du module events).
 *
 * @param array $quests Données brutes (ex. $_POST['pokehub_quests']).
 * @return array Quêtes nettoyées pour pokehub_content_save_quests().
 */
function pokehub_quests_clean_from_request(array $quests) {
    $cleaned_quests = [];
    foreach ($quests as $quest) {
        $task_input  = isset($quest['task']) ? trim((string) $quest['task']) : '';
        $has_rewards = !empty($quest['rewards']) && is_array($quest['rewards']) && count($quest['rewards']) > 0;
        $has_task    = $task_input !== '';
        if (!$has_task && !$has_rewards) {
            continue;
        }
        $cleaned_quest = [
            'task'           => $has_task ? sanitize_text_field($task_input) : '',
            'rewards'        => [],
            'quest_group_id' => isset($quest['quest_group_id']) ? max(0, (int) $quest['quest_group_id']) : 0,
        ];
        if (isset($quest['rewards']) && is_array($quest['rewards'])) {
            foreach ($quest['rewards'] as $reward) {
                if (is_object($reward)) {
                    $reward = json_decode(wp_json_encode($reward), true);
                }
                if (!is_array($reward)) {
                    continue;
                }
                $cleaned_reward = ['type' => sanitize_key($reward['type'] ?? 'pokemon')];
                if ($cleaned_reward['type'] === 'pokemon') {
                    $raw_tokens = [];
                    if (isset($reward['pokemon_ids'])) {
                        $raw = $reward['pokemon_ids'];
                        if (is_array($raw)) {
                            $raw_tokens = $raw;
                        } elseif (is_string($raw)) {
                            $raw_tokens = array_map('trim', explode(',', $raw));
                        }
                    }
                    if ($raw_tokens === [] && isset($reward['pokemon_id']) && $reward['pokemon_id'] !== '' && $reward['pokemon_id'] !== null) {
                        $raw_tokens = [(string) $reward['pokemon_id']];
                    }
                    $raw_genders = isset($reward['pokemon_genders']) && is_array($reward['pokemon_genders']) ? $reward['pokemon_genders'] : [];
                    if (function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
                        $parsed = pokehub_parse_post_pokemon_multiselect_tokens_with_genders($raw_tokens, $raw_genders);
                        $cleaned_reward['pokemon_ids'] = $parsed['pokemon_ids'];
                        $cleaned_reward['pokemon_genders'] = $parsed['pokemon_genders'];
                    } else {
                        $cleaned_reward['pokemon_ids'] = pokehub_quests_parse_pokemon_ids_from_reward_input($reward);
                        $pokemon_genders = [];
                        foreach ($raw_genders as $pokemon_id => $gender) {
                            $pokemon_id = (int) $pokemon_id;
                            if ($pokemon_id > 0 && in_array($gender, ['male', 'female'], true)) {
                                $pokemon_genders[$pokemon_id] = sanitize_text_field($gender);
                            }
                        }
                        $cleaned_reward['pokemon_genders'] = $pokemon_genders;
                    }
                    $cleaned_reward['force_shiny'] = !empty($reward['force_shiny']);
                } elseif (in_array($cleaned_reward['type'], ['candy', 'xl_candy', 'mega_energy'], true)) {
                    $cleaned_reward['pokemon_id'] = isset($reward['pokemon_id']) ? (int) $reward['pokemon_id'] : 0;
                    $cleaned_reward['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
                } elseif ($cleaned_reward['type'] === 'item') {
                    $cleaned_reward['item_id'] = isset($reward['item_id']) ? (int) $reward['item_id'] : 0;
                    if ($cleaned_reward['item_id'] > 0 && function_exists('pokehub_get_item_data_by_id')) {
                        $item_data = pokehub_get_item_data_by_id($cleaned_reward['item_id']);
                        $cleaned_reward['item_name'] = $item_data ? ($item_data['name_fr'] ?? $item_data['name_en'] ?? '') : sanitize_text_field($reward['item_name'] ?? '');
                    } else {
                        $cleaned_reward['item_name'] = sanitize_text_field($reward['item_name'] ?? '');
                    }
                    $cleaned_reward['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
                } else {
                    $cleaned_reward['quantity'] = isset($reward['quantity']) ? (int) $reward['quantity'] : 1;
                }
                $cleaned_quest['rewards'][] = $cleaned_reward;
            }
        }
        if ($cleaned_quest['task'] === '' && empty($cleaned_quest['rewards'])) {
            continue;
        }
        $cleaned_quests[] = $cleaned_quest;
    }
    return $cleaned_quests;
}

// --- Groupes de quêtes ---

/**
 * Récupère tous les groupes de quêtes (pour catégorisation type Leek Duck).
 *
 * @return array<object>
 */
function pokehub_get_quest_groups() {
    global $wpdb;
    $table = pokehub_get_table('quest_groups');
    if (!$table) {
        return [];
    }
    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC");
}

/**
 * Récupère un groupe de quêtes par ID.
 *
 * @param int $id
 * @return object|null
 */
function pokehub_get_quest_group($id) {
    global $wpdb;
    $table = pokehub_get_table('quest_groups');
    if (!$table || (int) $id <= 0) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id));
}

/**
 * Crée ou met à jour un groupe de quêtes.
 *
 * @param array $data [ title_en, title_fr, color, sort_order ]
 * @param int   $id   0 pour créer.
 * @return int|false ID du groupe ou false.
 */
function pokehub_save_quest_group(array $data, $id = 0) {
    global $wpdb;
    $table = pokehub_get_table('quest_groups');
    if (!$table) {
        return false;
    }
    $title_en   = isset($data['title_en']) ? sanitize_text_field($data['title_en']) : '';
    $title_fr   = isset($data['title_fr']) ? sanitize_text_field($data['title_fr']) : '';
    $color      = isset($data['color']) ? sanitize_hex_color($data['color']) : null;
    $sort_order = isset($data['sort_order']) ? max(0, (int) $data['sort_order']) : 0;
    if ($id > 0) {
        $wpdb->update($table, [
            'title_en'   => $title_en,
            'title_fr'   => $title_fr,
            'color'      => $color,
            'sort_order' => $sort_order,
            'updated_at' => current_time('mysql'),
        ], ['id' => $id], ['%s', '%s', '%s', '%d', '%s'], ['%d']);
        return $id;
    }
    $wpdb->insert($table, [
        'title_en'   => $title_en,
        'title_fr'   => $title_fr,
        'color'      => $color,
        'sort_order' => $sort_order,
    ], ['%s', '%s', '%s', '%d']);
    return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
}

/**
 * Supprime un groupe de quêtes (les lignes gardent quest_group_id mais le groupe n'existe plus).
 *
 * @param int $id
 * @return bool
 */
function pokehub_delete_quest_group($id) {
    global $wpdb;
    $table = pokehub_get_table('quest_groups');
    if (!$table || (int) $id <= 0) {
        return false;
    }
    return (bool) $wpdb->delete($table, ['id' => (int) $id], ['%d']);
}

// --- Bonus ---

/**
 * Récupère la ligne content_bonus pour une source.
 */
function pokehub_content_get_bonus_row($source_type, $source_id) {
    global $wpdb;
    $table = pokehub_get_table('content_bonus');
    if (!$table) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1",
        (string) $source_type,
        (int) $source_id
    ));
}

/**
 * Récupère les bonus pour une source. Format [ ['bonus_id' => int, 'description' => ''], ... ].
 */
function pokehub_content_get_bonus($source_type, $source_id) {
    $row = pokehub_content_get_bonus_row($source_type, $source_id);
    if (!$row) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('content_bonus_entries');
    if (!$table) {
        return [];
    }
    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE content_bonus_id = %d ORDER BY sort_order ASC, id ASC",
        (int) $row->id
    ));
    $out = [];
    foreach ($entries as $e) {
        $out[] = [
            'bonus_id'    => (int) $e->bonus_id,
            'description' => (string) $e->description,
        ];
    }
    return $out;
}

/**
 * Sauvegarde les bonus pour une source.
 */
function pokehub_content_save_bonus($source_type, $source_id, array $bonus_list) {
    global $wpdb;
    $bonus_tbl  = pokehub_get_table('content_bonus');
    $entries_tbl = pokehub_get_table('content_bonus_entries');
    if (!$bonus_tbl || !$entries_tbl) {
        return;
    }
    $dates = pokehub_content_get_dates_for_source($source_type, $source_id);
    $row   = pokehub_content_get_bonus_row($source_type, $source_id);

    if ($row) {
        $wpdb->update($bonus_tbl, [
            'start_ts' => $dates['start_ts'],
            'end_ts'   => $dates['end_ts'],
            'updated_at' => current_time('mysql'),
        ], ['id' => $row->id], ['%d', '%d', '%s'], ['%d']);
        $content_bonus_id = (int) $row->id;
        $wpdb->query($wpdb->prepare("DELETE FROM {$entries_tbl} WHERE content_bonus_id = %d", $content_bonus_id));
    } else {
        $wpdb->insert($bonus_tbl, [
            'source_type' => (string) $source_type,
            'source_id'   => (int) $source_id,
            'start_ts'    => $dates['start_ts'],
            'end_ts'      => $dates['end_ts'],
        ], ['%s', '%d', '%d', '%d']);
        $content_bonus_id = (int) $wpdb->insert_id;
    }

    $sort = 0;
    foreach ($bonus_list as $b) {
        $bonus_id = isset($b['bonus_id']) ? (int) $b['bonus_id'] : 0;
        if ($bonus_id <= 0) {
            continue;
        }
        $wpdb->insert($entries_tbl, [
            'content_bonus_id' => $content_bonus_id,
            'bonus_id'         => $bonus_id,
            'description'       => isset($b['description']) ? wp_kses_post($b['description']) : '',
            'sort_order'       => $sort++,
        ], ['%d', '%d', '%s', '%d']);
    }
}

// --- Wild Pokémon ---

/**
 * Récupère la ligne content_wild_pokemon pour une source.
 */
function pokehub_content_get_wild_pokemon_row($source_type, $source_id) {
    global $wpdb;
    $table = pokehub_get_table('content_wild_pokemon');
    if (!$table) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1",
        (string) $source_type,
        (int) $source_id
    ));
}

/**
 * Récupère les Pokémon sauvages. Format [ ['pokemon_id' => int, 'is_rare' => bool, 'force_shiny' => bool, 'gender' => ''], ... ].
 */
function pokehub_content_get_wild_pokemon($source_type, $source_id) {
    $row = pokehub_content_get_wild_pokemon_row($source_type, $source_id);
    if (!$row) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('content_wild_pokemon_entries');
    if (!$table) {
        return [];
    }
    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE content_wild_pokemon_id = %d ORDER BY sort_order ASC, id ASC",
        (int) $row->id
    ));
    $out = [];
    foreach ($entries as $e) {
        $out[] = [
            'pokemon_id'   => (int) $e->pokemon_id,
            'is_rare'      => (bool) $e->is_rare,
            'force_shiny'  => (bool) $e->is_forced_shiny,
            'gender'       => $e->gender ? (string) $e->gender : null,
        ];
    }
    return $out;
}

/**
 * Sauvegarde les Pokémon sauvages pour une source.
 */
function pokehub_content_save_wild_pokemon($source_type, $source_id, array $wild_list) {
    global $wpdb;
    $main_tbl   = pokehub_get_table('content_wild_pokemon');
    $entries_tbl = pokehub_get_table('content_wild_pokemon_entries');
    if (!$main_tbl || !$entries_tbl) {
        return;
    }
    $dates = pokehub_content_get_dates_for_source($source_type, $source_id);
    $row   = pokehub_content_get_wild_pokemon_row($source_type, $source_id);

    if ($row) {
        $wpdb->update($main_tbl, [
            'start_ts'   => $dates['start_ts'],
            'end_ts'     => $dates['end_ts'],
            'updated_at' => current_time('mysql'),
        ], ['id' => $row->id], ['%d', '%d', '%s'], ['%d']);
        $content_id = (int) $row->id;
        $wpdb->query($wpdb->prepare("DELETE FROM {$entries_tbl} WHERE content_wild_pokemon_id = %d", $content_id));
    } else {
        $wpdb->insert($main_tbl, [
            'source_type' => (string) $source_type,
            'source_id'   => (int) $source_id,
            'start_ts'    => $dates['start_ts'],
            'end_ts'      => $dates['end_ts'],
        ], ['%s', '%d', '%d', '%d']);
        $content_id = (int) $wpdb->insert_id;
    }

    $sort = 0;
    foreach ($wild_list as $w) {
        $pid = isset($w['pokemon_id']) ? (int) $w['pokemon_id'] : 0;
        if ($pid <= 0) {
            continue;
        }
        $wpdb->insert($entries_tbl, [
            'content_wild_pokemon_id' => $content_id,
            'pokemon_id'               => $pid,
            'is_rare'                  => !empty($w['is_rare']) ? 1 : 0,
            'is_forced_shiny'          => !empty($w['force_shiny']) ? 1 : 0,
            'gender'                   => isset($w['gender']) && in_array($w['gender'], ['male', 'female'], true) ? $w['gender'] : null,
            'sort_order'               => $sort++,
        ], ['%d', '%d', '%d', '%d', '%s', '%d']);
    }
}

// --- Nouveaux Pokémon ---

/**
 * Récupère la ligne content_new_pokemon pour une source.
 */
function pokehub_content_get_new_pokemon_row($source_type, $source_id) {
    global $wpdb;
    $table = pokehub_get_table('content_new_pokemon');
    if (!$table) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1",
        (string) $source_type,
        (int) $source_id
    ));
}

/**
 * Récupère les IDs des nouveaux Pokémon (et optionnellement genders par pokemon_id).
 *
 * @return array{ids: int[], genders: array<int, string>}
 */
function pokehub_content_get_new_pokemon($source_type, $source_id) {
    $row = pokehub_content_get_new_pokemon_row($source_type, $source_id);
    if (!$row) {
        return ['ids' => [], 'genders' => []];
    }
    global $wpdb;
    $table = pokehub_get_table('content_new_pokemon_entries');
    if (!$table) {
        return ['ids' => [], 'genders' => []];
    }
    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE content_new_pokemon_id = %d ORDER BY sort_order ASC, id ASC",
        (int) $row->id
    ));
    $ids = [];
    $genders = [];
    foreach ($entries as $e) {
        $pid = (int) $e->pokemon_id;
        $ids[] = $pid;
        if ($e->gender) {
            $genders[$pid] = (string) $e->gender;
        }
    }
    return ['ids' => $ids, 'genders' => $genders];
}

/**
 * Sauvegarde les nouveaux Pokémon pour une source.
 *
 * @param string $source_type
 * @param int    $source_id
 * @param int[]  $pokemon_ids
 * @param array<int, string> $genders Optionnel. pokemon_id => 'male'|'female'
 */
function pokehub_content_save_new_pokemon($source_type, $source_id, array $pokemon_ids, array $genders = []) {
    global $wpdb;
    $main_tbl   = pokehub_get_table('content_new_pokemon');
    $entries_tbl = pokehub_get_table('content_new_pokemon_entries');
    if (!$main_tbl || !$entries_tbl) {
        return;
    }
    if (function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
        $parsed = pokehub_parse_post_pokemon_multiselect_tokens_with_genders($pokemon_ids, $genders);
        $pokemon_ids = $parsed['pokemon_ids'];
        $genders = [];
        foreach ($parsed['pokemon_genders'] as $gk => $gv) {
            $genders[(int) $gk] = $gv;
        }
    }
    $dates = pokehub_content_get_dates_for_source($source_type, $source_id);
    $row   = pokehub_content_get_new_pokemon_row($source_type, $source_id);

    if ($row) {
        $wpdb->update($main_tbl, [
            'start_ts'   => $dates['start_ts'],
            'end_ts'     => $dates['end_ts'],
            'updated_at' => current_time('mysql'),
        ], ['id' => $row->id], ['%d', '%d', '%s'], ['%d']);
        $content_id = (int) $row->id;
        $wpdb->query($wpdb->prepare("DELETE FROM {$entries_tbl} WHERE content_new_pokemon_id = %d", $content_id));
    } else {
        $wpdb->insert($main_tbl, [
            'source_type' => (string) $source_type,
            'source_id'   => (int) $source_id,
            'start_ts'    => $dates['start_ts'],
            'end_ts'      => $dates['end_ts'],
        ], ['%s', '%d', '%d', '%d']);
        $content_id = (int) $wpdb->insert_id;
    }

    $sort = 0;
    foreach ($pokemon_ids as $pid) {
        $pid = (int) $pid;
        if ($pid <= 0) {
            continue;
        }
        $gender = isset($genders[$pid]) && in_array($genders[$pid], ['male', 'female'], true) ? $genders[$pid] : null;
        $wpdb->insert($entries_tbl, [
            'content_new_pokemon_id' => $content_id,
            'pokemon_id'             => $pid,
            'gender'                => $gender,
            'sort_order'            => $sort++,
        ], ['%d', '%d', '%s', '%d']);
    }
}

// --- Habitats ---

function pokehub_content_get_habitats_row($source_type, $source_id) {
    global $wpdb;
    $table = pokehub_get_table('content_habitats');
    return $table ? $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1",
        (string) $source_type,
        (int) $source_id
    )) : null;
}

/**
 * Récupère les habitats. Format comme avant : [ ['name','slug','pokemon_ids','forced_shiny_ids','pokemon_genders','schedule','all_pokemon_available'], ... ].
 */
function pokehub_content_get_habitats($source_type, $source_id) {
    $row = pokehub_content_get_habitats_row($source_type, $source_id);
    if (!$row) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('content_habitat_entries');
    if (!$table) {
        return [];
    }
    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE content_habitat_id = %d ORDER BY sort_order ASC, id ASC",
        (int) $row->id
    ));
    $out = [];
    foreach ($entries as $e) {
        $pokemon = $e->pokemon_data ? json_decode($e->pokemon_data, true) : [];
        $schedule = $e->schedule_data ? json_decode($e->schedule_data, true) : [];
        if (!is_array($pokemon)) {
            $pokemon = [];
        }
        if (!is_array($schedule)) {
            $schedule = [];
        }
        $out[] = [
            'name'                   => (string) $e->name,
            'slug'                   => (string) $e->slug,
            'pokemon_ids'            => isset($pokemon['pokemon_ids']) ? $pokemon['pokemon_ids'] : [],
            'forced_shiny_ids'       => isset($pokemon['forced_shiny_ids']) ? $pokemon['forced_shiny_ids'] : [],
            'pokemon_genders'        => isset($pokemon['pokemon_genders']) ? $pokemon['pokemon_genders'] : [],
            'schedule'                => $schedule,
            'all_pokemon_available'  => (bool) $e->all_pokemon_available,
        ];
    }
    return $out;
}

/**
 * Sauvegarde les habitats. $habitats = [ ['name','slug','pokemon_ids','forced_shiny_ids','pokemon_genders','schedule','all_pokemon_available'], ... ].
 */
function pokehub_content_save_habitats($source_type, $source_id, array $habitats) {
    global $wpdb;
    $main_tbl = pokehub_get_table('content_habitats');
    $entries_tbl = pokehub_get_table('content_habitat_entries');
    if (!$main_tbl || !$entries_tbl) {
        return;
    }
    $dates = pokehub_content_get_dates_for_source($source_type, $source_id);
    $row   = pokehub_content_get_habitats_row($source_type, $source_id);

    if ($row) {
        $wpdb->update($main_tbl, [
            'start_ts' => $dates['start_ts'],
            'end_ts'   => $dates['end_ts'],
            'updated_at' => current_time('mysql'),
        ], ['id' => $row->id], ['%d', '%d', '%s'], ['%d']);
        $content_id = (int) $row->id;
        $wpdb->query($wpdb->prepare("DELETE FROM {$entries_tbl} WHERE content_habitat_id = %d", $content_id));
    } else {
        $ok = $wpdb->insert($main_tbl, [
            'source_type' => (string) $source_type,
            'source_id'   => (int) $source_id,
            'start_ts'    => $dates['start_ts'],
            'end_ts'      => $dates['end_ts'],
        ], ['%s', '%d', '%d', '%d']);
        $content_id = $ok ? (int) $wpdb->insert_id : 0;
    }

    if ($content_id <= 0) {
        return;
    }

    $sort = 0;
    foreach ($habitats as $h) {
        $name = sanitize_text_field($h['name'] ?? '');
        $slug = sanitize_title($h['slug'] ?? '');
        if (empty($slug) && $name !== '') {
            $slug = sanitize_title($name);
        }
        if ($name === '' || $slug === '') {
            continue;
        }
        $pokemon_payload = function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')
            ? pokehub_parse_post_pokemon_multiselect_tokens_with_genders(
                isset($h['pokemon_ids']) ? (array) $h['pokemon_ids'] : [],
                isset($h['pokemon_genders']) && is_array($h['pokemon_genders']) ? $h['pokemon_genders'] : null
            )
            : [
                'pokemon_ids'     => isset($h['pokemon_ids']) ? array_map('intval', (array) $h['pokemon_ids']) : [],
                'pokemon_genders' => isset($h['pokemon_genders']) && is_array($h['pokemon_genders']) ? $h['pokemon_genders'] : [],
            ];
        $pokemon_data = wp_json_encode([
            'pokemon_ids'      => $pokemon_payload['pokemon_ids'],
            'forced_shiny_ids' => isset($h['forced_shiny_ids']) ? array_map('intval', (array) $h['forced_shiny_ids']) : [],
            'pokemon_genders'  => $pokemon_payload['pokemon_genders'],
        ]);
        $schedule_data = wp_json_encode(isset($h['schedule']) && is_array($h['schedule']) ? $h['schedule'] : []);
        $wpdb->insert($entries_tbl, [
            'content_habitat_id'     => $content_id,
            'name'                   => $name,
            'slug'                   => $slug,
            'pokemon_data'           => $pokemon_data,
            'schedule_data'          => $schedule_data,
            'all_pokemon_available'  => !empty($h['all_pokemon_available']) ? 1 : 0,
            'sort_order'             => $sort++,
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%d']);
    }
}

// --- Études spéciales ---

/**
 * Convertit récursivement tous les objets (stdClass) en tableaux dans une structure.
 * Garantit que la BDD (ou json_decode) ne laisse pas de sous-éléments en objet.
 *
 * @param mixed $data
 * @return mixed
 */
function pokehub_content_special_research_ensure_arrays($data) {
    if (is_object($data)) {
        $data = json_decode(wp_json_encode($data), true);
    }
    if (!is_array($data)) {
        return $data;
    }
    $out = [];
    foreach ($data as $k => $v) {
        $out[$k] = pokehub_content_special_research_ensure_arrays($v);
    }
    return $out;
}

function pokehub_content_get_special_research_row($source_type, $source_id) {
    global $wpdb;
    $table = pokehub_get_table('content_special_research');
    $row = $table ? $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1",
        (string) $source_type,
        (int) $source_id
    )) : null;
    return $row;
}

/**
 * Normalise une étape d'étude spéciale pour que les récompenses aient la structure attendue par la metabox
 * (notamment pokemon_ids en tableau d'entiers pour les récompenses Pokémon).
 *
 * @param array $step Données d'étape (quests, rewards).
 * @return array
 */
function pokehub_content_normalize_special_research_step(array $step) {
    if (!is_array($step)) {
        return [];
    }
    if (isset($step['quests']) && is_array($step['quests'])) {
        foreach ($step['quests'] as $i => $quest) {
            if (is_object($quest)) {
                $quest = json_decode(wp_json_encode($quest), true);
                $step['quests'][$i] = $quest;
            }
            if (is_array($quest) && isset($quest['rewards'])) {
                $raw = $quest['rewards'];
                if (is_string($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_object($raw)) {
                    $raw = json_decode(wp_json_encode($raw), true);
                }
                $step['quests'][$i]['rewards'] = pokehub_content_normalize_quest_rewards(
                    is_array($raw) ? $raw : []
                );
            }
        }
    }
    $raw_rewards = isset($step['rewards']) ? $step['rewards'] : [];
    if (is_string($raw_rewards)) {
        $raw_rewards = json_decode($raw_rewards, true);
    }
    if (is_object($raw_rewards)) {
        $raw_rewards = json_decode(wp_json_encode($raw_rewards), true);
    }
    if (is_array($raw_rewards)) {
        $step['rewards'] = pokehub_content_normalize_quest_rewards($raw_rewards);
    }
    return $step;
}

/**
 * Normalise un item d'étude spéciale (une "étude" avec common_initial_steps, paths, common_final_steps).
 * Chaque étape imbriquée a ses récompenses normalisées pour que pokemon_ids soit un tableau d'entiers.
 *
 * @param array $item Données d'un research item tel que décodé depuis la BDD.
 * @return array
 */
function pokehub_content_normalize_special_research_item(array $item) {
    if (!is_array($item)) {
        return [];
    }
    if (isset($item['common_initial_steps']) && is_array($item['common_initial_steps'])) {
        foreach ($item['common_initial_steps'] as $i => $step) {
            if (is_object($step)) {
                $step = json_decode(wp_json_encode($step), true);
                $item['common_initial_steps'][$i] = $step;
            }
            if (is_array($step)) {
                $item['common_initial_steps'][$i] = pokehub_content_normalize_special_research_step($step);
            }
        }
    }
    if (isset($item['paths']) && is_array($item['paths'])) {
        foreach ($item['paths'] as $path_index => $path) {
            if (is_object($path)) {
                $path = json_decode(wp_json_encode($path), true);
                $item['paths'][$path_index] = $path;
            }
            if (!is_array($path) || !isset($path['steps']) || !is_array($path['steps'])) {
                continue;
            }
            foreach ($path['steps'] as $step_index => $step) {
                if (is_object($step)) {
                    $step = json_decode(wp_json_encode($step), true);
                    $item['paths'][$path_index]['steps'][$step_index] = $step;
                }
                if (is_array($step)) {
                    $item['paths'][$path_index]['steps'][$step_index] = pokehub_content_normalize_special_research_step($step);
                }
            }
        }
    }
    if (isset($item['common_final_steps']) && is_array($item['common_final_steps'])) {
        foreach ($item['common_final_steps'] as $i => $step) {
            if (is_object($step)) {
                $step = json_decode(wp_json_encode($step), true);
                $item['common_final_steps'][$i] = $step;
            }
            if (is_array($step)) {
                $item['common_final_steps'][$i] = pokehub_content_normalize_special_research_step($step);
            }
        }
    }
    return $item;
}

/**
 * Récupère études spéciales. Retourne [ 'research_type' => string, 'steps' => [ research_item, ... ] ].
 * Chaque research_item a la structure name, common_initial_steps, paths, common_final_steps.
 */
function pokehub_content_get_special_research($source_type, $source_id) {
    $row = pokehub_content_get_special_research_row($source_type, $source_id);
    if (!$row) {
        return ['research_type' => 'special', 'steps' => []];
    }
    global $wpdb;
    $table = pokehub_get_table('content_special_research_steps');
    if (!$table) {
        return ['research_type' => (string) $row->research_type, 'steps' => []];
    }
    $steps = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE content_research_id = %d ORDER BY sort_order ASC, id ASC",
        (int) $row->id
    ));
    $out = [];
    foreach ($steps as $s) {
        $step_data = $s->step_data;
        if (is_string($step_data)) {
            $decoded = json_decode($step_data, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            $step_data = is_array($decoded) ? $decoded : [];
        } elseif (is_object($step_data)) {
            // MySQL ou le driver peut renvoyer un stdClass pour une colonne JSON/LONGTEXT décodée
            $step_data = json_decode(wp_json_encode($step_data), true);
            $step_data = is_array($step_data) ? $step_data : [];
        } elseif (!is_array($step_data)) {
            $step_data = [];
        }
        // Conversion récursive : tout objet restant (ex. rewards, quests) devient un tableau
        $step_data = pokehub_content_special_research_ensure_arrays($step_data);
        if (!is_array($step_data)) {
            $step_data = [];
        }
        $out[] = pokehub_content_normalize_special_research_item($step_data);
    }
    return ['research_type' => (string) $row->research_type, 'steps' => $out];
}

/**
 * Sauvegarde études spéciales. $data = [ 'research_type' => string, 'steps' => [ ... ] ].
 */
function pokehub_content_save_special_research($source_type, $source_id, array $data) {
    global $wpdb;
    $main_tbl = pokehub_get_table('content_special_research');
    $steps_tbl = pokehub_get_table('content_special_research_steps');
    if (!$main_tbl || !$steps_tbl) {
        return;
    }
    $dates = pokehub_content_get_dates_for_source($source_type, $source_id);
    $row   = pokehub_content_get_special_research_row($source_type, $source_id);
    $research_type = isset($data['research_type']) ? sanitize_key($data['research_type']) : 'special';
    $steps = isset($data['steps']) && is_array($data['steps']) ? $data['steps'] : [];

    if ($row) {
        $wpdb->update($main_tbl, [
            'start_ts'   => $dates['start_ts'],
            'end_ts'     => $dates['end_ts'],
            'research_type' => $research_type,
            'updated_at' => current_time('mysql'),
        ], ['id' => $row->id], ['%d', '%d', '%s', '%s'], ['%d']);
        $content_id = (int) $row->id;
        $wpdb->query($wpdb->prepare("DELETE FROM {$steps_tbl} WHERE content_research_id = %d", $content_id));
    } else {
        $wpdb->insert($main_tbl, [
            'source_type'   => (string) $source_type,
            'source_id'     => (int) $source_id,
            'start_ts'      => $dates['start_ts'],
            'end_ts'        => $dates['end_ts'],
            'research_type' => $research_type,
        ], ['%s', '%d', '%d', '%d', '%s']);
        $content_id = (int) $wpdb->insert_id;
    }

    $sort = 0;
    foreach ($steps as $step) {
        $wpdb->insert($steps_tbl, [
            'content_research_id' => $content_id,
            'step_data'          => wp_json_encode($step),
            'sort_order'         => $sort++,
        ], ['%d', '%s', '%d']);
    }
}

// --- Défis de collection ---

function pokehub_content_get_collection_challenges_row($source_type, $source_id) {
    global $wpdb;
    $table = pokehub_get_table('content_collection_challenges');
    return $table ? $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1",
        (string) $source_type,
        (int) $source_id
    )) : null;
}

/**
 * Récupère les défis de collection. Format [ ['name','color','use_global_dates', ...], ... ].
 */
function pokehub_content_get_collection_challenges($source_type, $source_id) {
    $row = pokehub_content_get_collection_challenges_row($source_type, $source_id);
    if (!$row) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('content_collection_challenge_items');
    if (!$table) {
        return [];
    }
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE content_challenge_id = %d ORDER BY sort_order ASC, id ASC",
        (int) $row->id
    ));
    $out = [];
    foreach ($items as $i) {
        $out[] = $i->item_data ? json_decode($i->item_data, true) : [];
    }
    return $out;
}

/**
 * Sauvegarde les défis de collection. $challenges = [ ['name','color', ...], ... ].
 */
function pokehub_content_save_collection_challenges($source_type, $source_id, array $challenges) {
    global $wpdb;
    $main_tbl = pokehub_get_table('content_collection_challenges');
    $items_tbl = pokehub_get_table('content_collection_challenge_items');
    if (!$main_tbl || !$items_tbl) {
        return;
    }
    $dates = pokehub_content_get_dates_for_source($source_type, $source_id);
    $row   = pokehub_content_get_collection_challenges_row($source_type, $source_id);

    if ($row) {
        $wpdb->update($main_tbl, [
            'start_ts'   => $dates['start_ts'],
            'end_ts'     => $dates['end_ts'],
            'updated_at' => current_time('mysql'),
        ], ['id' => $row->id], ['%d', '%d', '%s'], ['%d']);
        $content_id = (int) $row->id;
        $wpdb->query($wpdb->prepare("DELETE FROM {$items_tbl} WHERE content_challenge_id = %d", $content_id));
    } else {
        $wpdb->insert($main_tbl, [
            'source_type' => (string) $source_type,
            'source_id'   => (int) $source_id,
            'start_ts'    => $dates['start_ts'],
            'end_ts'      => $dates['end_ts'],
        ], ['%s', '%d', '%d', '%d']);
        $content_id = (int) $wpdb->insert_id;
    }

    $sort = 0;
    foreach ($challenges as $c) {
        $wpdb->insert($items_tbl, [
            'content_challenge_id' => $content_id,
            'item_data'           => wp_json_encode($c),
            'sort_order'          => $sort++,
        ], ['%d', '%s', '%d']);
    }
}

// --- Bloc "Jour -> Pokémon(s) -> Heures" ---

if (!function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
    /**
     * Normalise les valeurs de multiselect Pokémon issues du POST : IDs simples et tokens "123|male" / "123|female".
     * Les entrées `pokemon_genders` du POST (champs cachés) priment sur le suffixe du token.
     *
     * @param array<int|string> $tokens
     * @param array<string|int, mixed>|null $post_genders
     * @return array{pokemon_ids: array<int, int>, pokemon_genders: array<string, string>}
     */
    function pokehub_parse_post_pokemon_multiselect_tokens_with_genders(array $tokens, $post_genders = null): array {
        $genders_from_post = [];
        if (is_array($post_genders)) {
            foreach ($post_genders as $pid => $gender) {
                $pid = (int) $pid;
                $g = is_string($gender) ? sanitize_key($gender) : '';
                if ($pid > 0 && in_array($g, ['male', 'female'], true)) {
                    $genders_from_post[(string) $pid] = $g;
                }
            }
        }

        $genders_from_tokens = [];
        $ids_order = [];
        $seen = [];

        $push_id = static function (int $pid) use (&$ids_order, &$seen): void {
            if ($pid <= 0) {
                return;
            }
            if (!isset($seen[$pid])) {
                $ids_order[] = $pid;
                $seen[$pid] = true;
            }
        };

        foreach ($tokens as $token) {
            if (is_array($token) || is_object($token)) {
                continue;
            }
            $t = is_string($token) ? trim(wp_unslash($token)) : trim((string) $token);
            if ($t === '') {
                continue;
            }
            if (preg_match('/^(\d+)\|(male|female)$/i', $t, $m)) {
                $pid = (int) $m[1];
                $g = strtolower((string) $m[2]);
                if ($pid > 0 && in_array($g, ['male', 'female'], true)) {
                    $push_id($pid);
                    $genders_from_tokens[(string) $pid] = $g;
                }
                continue;
            }
            if (preg_match('/^\d+$/', $t)) {
                $pid = (int) $t;
                $push_id($pid);
            }
        }

        $merged_genders = $genders_from_tokens;
        foreach ($genders_from_post as $pk => $g) {
            $merged_genders[(string) $pk] = $g;
        }

        foreach ($merged_genders as $pk => $_g) {
            $ipi = (int) $pk;
            if ($ipi > 0 && !isset($seen[$ipi])) {
                $push_id($ipi);
            }
        }

        foreach (array_keys($merged_genders) as $pk) {
            if (!in_array((int) $pk, $ids_order, true)) {
                unset($merged_genders[$pk]);
            }
        }

        return [
            'pokemon_ids'     => $ids_order,
            'pokemon_genders' => $merged_genders,
        ];
    }
}

if (!function_exists('pokehub_content_normalize_pokemon_ids_with_genders')) {
    /**
     * @param mixed $raw
     * @return array{pokemon_ids: array<int, int>, pokemon_genders: array<string, string>}
     */
    function pokehub_content_normalize_pokemon_ids_with_genders($raw): array {
        $result = [
            'pokemon_ids' => [],
            'pokemon_genders' => [],
        ];

        $decoded = $raw;
        if (is_string($decoded) && $decoded !== '') {
            $json = json_decode($decoded, true);
            if (is_array($json)) {
                $decoded = $json;
            } else {
                $decoded = [$decoded];
            }
        }

        $pokemon_ids = [];
        $pokemon_genders = [];

        if (is_array($decoded) && isset($decoded['pokemon_ids'])) {
            $pokemon_ids = is_array($decoded['pokemon_ids']) ? $decoded['pokemon_ids'] : [];
            if (isset($decoded['pokemon_genders']) && is_array($decoded['pokemon_genders'])) {
                foreach ($decoded['pokemon_genders'] as $pid => $gender) {
                    $pid = (int) $pid;
                    $gender = is_string($gender) ? sanitize_key($gender) : '';
                    if ($pid > 0 && in_array($gender, ['male', 'female'], true)) {
                        $pokemon_genders[(string) $pid] = $gender;
                    }
                }
            }
        } elseif (is_array($decoded)) {
            $pokemon_ids = $decoded;
        }

        $parsed = pokehub_parse_post_pokemon_multiselect_tokens_with_genders((array) $pokemon_ids, $pokemon_genders);
        $pokemon_ids = $parsed['pokemon_ids'];
        $pokemon_genders = $parsed['pokemon_genders'];

        $result['pokemon_ids'] = $pokemon_ids;
        $result['pokemon_genders'] = $pokemon_genders;

        return $result;
    }
}

/**
 * Récupère les "sets" jour/pokémon/heures pour une source.
 *
 * Retourne une structure :
 * [
 *   [
 *     'content_type' => 'featured_hours',
 *     'days' => [
 *        ['date' => 'YYYY-MM-DD', 'start_time' => '18:00', 'end_time' => '19:00', 'pokemon_ids' => [1,2]],
 *        ...
 *     ]
 *   ],
 *   ...
 * ]
 *
 * @param string $source_type 'post' | 'special_event' | 'global_pool'
 * @param int    $source_id
 * @return array
 */
function pokehub_content_get_day_pokemon_hours_sets($source_type, $source_id): array {
    global $wpdb;

    $source_type = (string) $source_type;
    $source_id   = (int) $source_id;

    $tz = function_exists('wp_timezone') ? wp_timezone() : null;
    if (!$tz) {
        $tz = new DateTimeZone('UTC');
    }

    // Helpers locaux : timestamp UTC -> date (Y-m-d) + time (H:i) en heure locale.
    $format_local_parts = function (int $timestamp) use ($tz): array {
        if ($timestamp <= 0) {
            return [];
        }
        try {
            $dt = new DateTime('@' . $timestamp);
            $dt->setTimezone($tz);
            return [
                'date' => $dt->format('Y-m-d'),
                'time' => $dt->format('H:i'),
            ];
        } catch (Exception $e) {
            return [];
        }
    };

    // 1) Raiders (content_raids + content_raid_bosses)
    $out = [];
    $raid_sets_map = []; // set_key => set struct
    $raid_days_map = []; // set_key => [day_key => ['pokemon_ids'=>[pid=>true], 'pokemon_genders'=>[pid=>gender]]]
    $raid_tbl = function_exists('pokehub_get_table') ? pokehub_get_table('content_raids') : '';
    $raid_bosses_tbl = function_exists('pokehub_get_table') ? pokehub_get_table('content_raid_bosses') : '';
    if ($raid_tbl && $raid_bosses_tbl) {
        $raid_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$raid_tbl} WHERE source_type = %s AND source_id = %d ORDER BY start_ts ASC, id ASC",
            $source_type,
            $source_id
        ), ARRAY_A);

        foreach ($raid_rows as $rr) {
            $rid = (int) ($rr['id'] ?? 0);
            $start_ts = (int) ($rr['start_ts'] ?? 0);
            $end_ts = (int) ($rr['end_ts'] ?? 0);
            if ($rid <= 0 || $start_ts <= 0 || $end_ts <= 0) {
                continue;
            }

            $st_parts = $format_local_parts($start_ts);
            $et_parts = $format_local_parts($end_ts);
            if (empty($st_parts) || empty($et_parts)) {
                continue;
            }
            $date = (string) ($st_parts['date'] ?? '');
            $end_date = (string) ($et_parts['date'] ?? $date);
            $start_time = (string) ($st_parts['time'] ?? '');
            $end_time = (string) ($et_parts['time'] ?? '');
            if ($date === '' || $start_time === '' || $end_time === '') {
                continue;
            }
            $day_key = $date . '|' . $end_date . '|' . $start_time . '|' . $end_time;

            $bosses = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$raid_bosses_tbl} WHERE content_raid_id = %d ORDER BY sort_order ASC, id ASC",
                $rid
            ), ARRAY_A);

            if (empty($bosses)) {
                continue;
            }

            $first = $bosses[0];
            $tier = (int) ($first['tier'] ?? 1);
            $is_mega = !empty($first['is_mega']) ? 1 : 0;
            $set_key = $tier . '|' . $is_mega;

            if (!isset($raid_sets_map[$set_key])) {
                $raid_sets_map[$set_key] = [
                    'content_type' => 'raids',
                    'raid_tier' => $tier,
                    'raid_is_mega' => (int) $is_mega,
                    'days_map' => [],
                ];
                $raid_days_map[$set_key] = [];
            }

            if (!isset($raid_days_map[$set_key][$day_key])) {
                $raid_days_map[$set_key][$day_key] = ['pokemon_ids' => [], 'pokemon_genders' => []];
            }

            foreach ($bosses as $b) {
                $pid = (int) ($b['pokemon_id'] ?? 0);
                if ($pid > 0) {
                    $raid_days_map[$set_key][$day_key]['pokemon_ids'][$pid] = true;
                    $gender = isset($b['gender']) ? sanitize_key((string) $b['gender']) : '';
                    if (in_array($gender, ['male', 'female'], true)) {
                        $raid_days_map[$set_key][$day_key]['pokemon_genders'][(string) $pid] = $gender;
                    }
                }
            }
        }

        foreach ($raid_sets_map as $set_key => $set) {
            $days = [];
            if (!empty($raid_days_map[$set_key])) {
                $day_items = [];
                foreach ($raid_days_map[$set_key] as $day_key => $day_data) {
                    $parts = explode('|', (string) $day_key);
                    if (count($parts) !== 4) continue;
                    $day_items[] = [
                        'date' => $parts[0],
                        'end_date' => $parts[1],
                        'start_time' => $parts[2],
                        'end_time' => $parts[3],
                        'pokemon_ids' => array_values(array_map('intval', array_keys((array) ($day_data['pokemon_ids'] ?? [])))),
                        'pokemon_genders' => isset($day_data['pokemon_genders']) && is_array($day_data['pokemon_genders']) ? $day_data['pokemon_genders'] : [],
                    ];
                }
                usort($day_items, function($a, $b) {
                    $da = (string) ($a['date'] ?? '');
                    $db = (string) ($b['date'] ?? '');
                    if ($da === $db) {
                        $sa = (string) ($a['start_time'] ?? '');
                        $sb = (string) ($b['start_time'] ?? '');
                        return $sa <=> $sb;
                    }
                    return $da <=> $db;
                });
                $days = $day_items;
            }
            $out[] = [
                'content_type' => 'raids',
                'raid_tier' => (int) ($set['raid_tier'] ?? 1),
                'raid_is_mega' => (int) ($set['raid_is_mega'] ?? 0),
                'days' => $days,
            ];
        }
    }

    // 2) Œufs (content_eggs + content_egg_pokemon)
    $egg_sets_map = []; // egg_type_id => set struct
    $egg_days_map = []; // egg_type_id => [day_key => ['pokemon_ids'=>[pid=>true], 'pokemon_genders'=>[pid=>gender]]]
    $eggs_tbl = function_exists('pokehub_get_table') ? pokehub_get_table('content_eggs') : '';
    $egg_pokemon_tbl = function_exists('pokehub_get_table') ? pokehub_get_table('content_egg_pokemon') : '';
    if ($eggs_tbl && $egg_pokemon_tbl) {
        $egg_has_gender_col = pokehub_content_table_has_column($egg_pokemon_tbl, 'gender');
        $egg_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$eggs_tbl} WHERE source_type = %s AND source_id = %d ORDER BY start_ts ASC, id ASC",
            $source_type,
            $source_id
        ), ARRAY_A);

        foreach ($egg_rows as $er) {
            $eid = (int) ($er['id'] ?? 0);
            $start_ts = (int) ($er['start_ts'] ?? 0);
            $end_ts = (int) ($er['end_ts'] ?? 0);
            if ($eid <= 0 || $start_ts <= 0 || $end_ts <= 0) {
                continue;
            }

            $st_parts = $format_local_parts($start_ts);
            $et_parts = $format_local_parts($end_ts);
            if (empty($st_parts) || empty($et_parts)) {
                continue;
            }

            $date = (string) ($st_parts['date'] ?? '');
            $end_date = (string) ($et_parts['date'] ?? $date);
            $start_time = (string) ($st_parts['time'] ?? '');
            $end_time = (string) ($et_parts['time'] ?? '');
            if ($date === '' || $start_time === '' || $end_time === '') {
                continue;
            }
            $day_key = $date . '|' . $end_date . '|' . $start_time . '|' . $end_time;

            $egg_cols = $egg_has_gender_col ? 'egg_type_id, pokemon_id, gender' : 'egg_type_id, pokemon_id';
            $egg_pokemons = $wpdb->get_results($wpdb->prepare(
                "SELECT {$egg_cols} FROM {$egg_pokemon_tbl} WHERE content_egg_id = %d ORDER BY sort_order ASC, id ASC",
                $eid
            ), ARRAY_A);

            if (empty($egg_pokemons)) {
                continue;
            }

            foreach ($egg_pokemons as $ep) {
                $egg_type_id = (int) ($ep['egg_type_id'] ?? 0);
                $pid = (int) ($ep['pokemon_id'] ?? 0);
                if ($egg_type_id <= 0 || $pid <= 0) {
                    continue;
                }

                if (!isset($egg_sets_map[$egg_type_id])) {
                    $egg_sets_map[$egg_type_id] = [
                        'content_type' => 'eggs',
                        'egg_type_id' => $egg_type_id,
                    ];
                    $egg_days_map[$egg_type_id] = [];
                }

                if (!isset($egg_days_map[$egg_type_id][$day_key])) {
                    $egg_days_map[$egg_type_id][$day_key] = ['pokemon_ids' => [], 'pokemon_genders' => []];
                }
                $egg_days_map[$egg_type_id][$day_key]['pokemon_ids'][$pid] = true;
                $gender = isset($ep['gender']) ? sanitize_key((string) $ep['gender']) : '';
                if (in_array($gender, ['male', 'female'], true)) {
                    $egg_days_map[$egg_type_id][$day_key]['pokemon_genders'][(string) $pid] = $gender;
                }
            }
        }

        $egg_type_ids = array_keys($egg_sets_map);
        sort($egg_type_ids, SORT_NUMERIC);
        foreach ($egg_type_ids as $egg_type_id) {
            $day_items = [];
            foreach (($egg_days_map[$egg_type_id] ?? []) as $day_key => $day_data) {
                $parts = explode('|', (string) $day_key);
                if (count($parts) !== 4) continue;
                $day_items[] = [
                    'date' => $parts[0],
                    'end_date' => $parts[1],
                    'start_time' => $parts[2],
                    'end_time' => $parts[3],
                    'pokemon_ids' => array_values(array_map('intval', array_keys((array) ($day_data['pokemon_ids'] ?? [])))),
                    'pokemon_genders' => isset($day_data['pokemon_genders']) && is_array($day_data['pokemon_genders']) ? $day_data['pokemon_genders'] : [],
                ];
            }
            usort($day_items, function($a, $b) {
                $da = (string) ($a['date'] ?? '');
                $db = (string) ($b['date'] ?? '');
                if ($da === $db) {
                    $sa = (string) ($a['start_time'] ?? '');
                    $sb = (string) ($b['start_time'] ?? '');
                    return $sa <=> $sb;
                }
                return $da <=> $db;
            });

            $out[] = [
                'content_type' => 'eggs',
                'egg_type_id' => (int) ($egg_type_id),
                'days' => $day_items,
            ];
        }
    }

    // 3) Quêtes (content_quests + content_quest_lines) -> une seule set dans l'éditeur
    $quest_days_map = []; // day_key => ['pokemon_ids'=>[pid=>true], 'pokemon_genders'=>[pid=>gender]]
    $quests_tbl = function_exists('pokehub_get_table') ? pokehub_get_table('content_quests') : '';
    $quest_lines_tbl = function_exists('pokehub_get_table') ? pokehub_get_table('content_quest_lines') : '';
    if ($quests_tbl && $quest_lines_tbl) {
        $day_quest_meta_ids = [];
        $day_quest_marked_map = [];
        if ((string) $source_type === 'post' && $source_id > 0) {
            if (function_exists('pokehub_content_get_day_schedule_quest_header_ids_for_source')) {
                $day_quest_meta_ids = pokehub_content_get_day_schedule_quest_header_ids_for_source('post', $source_id);
            }
            if (function_exists('pokehub_content_get_day_quest_ids_with_hours_marker')) {
                foreach (pokehub_content_get_day_quest_ids_with_hours_marker($source_id) as $mid) {
                    if ($mid > 0) {
                        $day_quest_marked_map[$mid] = true;
                    }
                }
            }
        }

        $quest_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$quests_tbl} WHERE source_type = %s AND source_id = %d ORDER BY start_ts ASC, id ASC",
            $source_type,
            $source_id
        ), ARRAY_A);

        foreach ($quest_rows as $qr) {
            $qid = (int) ($qr['id'] ?? 0);
            $start_ts = (int) ($qr['start_ts'] ?? 0);
            $end_ts = (int) ($qr['end_ts'] ?? 0);
            if ($qid <= 0 || $start_ts <= 0 || $end_ts <= 0) {
                continue;
            }

            if ((string) $source_type === 'post' && $source_id > 0) {
                $in_meta = in_array($qid, $day_quest_meta_ids, true);
                $in_marker = isset($day_quest_marked_map[$qid]);
                if (!$in_meta && !$in_marker) {
                    continue;
                }
            }

            $st_parts = $format_local_parts($start_ts);
            $et_parts = $format_local_parts($end_ts);
            if (empty($st_parts) || empty($et_parts)) {
                continue;
            }

            $date = (string) ($st_parts['date'] ?? '');
            $end_date = (string) ($et_parts['date'] ?? $date);
            $start_time = (string) ($st_parts['time'] ?? '');
            $end_time = (string) ($et_parts['time'] ?? '');
            if ($date === '' || $start_time === '' || $end_time === '') {
                continue;
            }

            $day_key = $date . '|' . $end_date . '|' . $start_time . '|' . $end_time;

            $lines = $wpdb->get_results($wpdb->prepare(
                "SELECT rewards FROM {$quest_lines_tbl} WHERE content_quest_id = %d ORDER BY sort_order ASC, id ASC",
                $qid
            ), ARRAY_A);

            foreach ($lines as $l) {
                $raw_rewards = isset($l['rewards']) ? $l['rewards'] : '';
                if ($raw_rewards === '' || $raw_rewards === null) {
                    continue;
                }
                $rewards = json_decode($raw_rewards, true);
                if (is_string($rewards)) {
                    $rewards = json_decode($rewards, true);
                }
                if (!is_array($rewards)) {
                    continue;
                }

                foreach ($rewards as $reward) {
                    if (empty($reward) || !is_array($reward)) {
                        continue;
                    }
                    $rtype = isset($reward['type']) ? sanitize_key((string) $reward['type']) : '';
                    if ($rtype !== 'pokemon') {
                        continue;
                    }
                    if (!empty($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])) {
                        foreach ($reward['pokemon_ids'] as $pid) {
                            $pid = (int) $pid;
                            if ($pid > 0) {
                                if (!isset($quest_days_map[$day_key])) $quest_days_map[$day_key] = ['pokemon_ids' => [], 'pokemon_genders' => []];
                                $quest_days_map[$day_key]['pokemon_ids'][$pid] = true;
                                $g = isset($reward['pokemon_genders'][$pid]) ? sanitize_key((string) $reward['pokemon_genders'][$pid]) : '';
                                if (in_array($g, ['male', 'female'], true)) {
                                    $quest_days_map[$day_key]['pokemon_genders'][(string) $pid] = $g;
                                }
                            }
                        }
                    } elseif (isset($reward['pokemon_id'])) {
                        $pid = (int) $reward['pokemon_id'];
                        if ($pid > 0) {
                            if (!isset($quest_days_map[$day_key])) $quest_days_map[$day_key] = ['pokemon_ids' => [], 'pokemon_genders' => []];
                            $quest_days_map[$day_key]['pokemon_ids'][$pid] = true;
                            $g = isset($reward['gender']) ? sanitize_key((string) $reward['gender']) : '';
                            if (in_array($g, ['male', 'female'], true)) {
                                $quest_days_map[$day_key]['pokemon_genders'][(string) $pid] = $g;
                            }
                        }
                    }
                }
            }
        }

        if (!empty($quest_days_map)) {
            $day_items = [];
            foreach ($quest_days_map as $day_key => $day_data) {
                $parts = explode('|', (string) $day_key);
                if (count($parts) !== 4) continue;
                $day_items[] = [
                    'date' => $parts[0],
                    'end_date' => $parts[1],
                    'start_time' => $parts[2],
                    'end_time' => $parts[3],
                    'pokemon_ids' => array_values(array_map('intval', array_keys((array) ($day_data['pokemon_ids'] ?? [])))),
                    'pokemon_genders' => isset($day_data['pokemon_genders']) && is_array($day_data['pokemon_genders']) ? $day_data['pokemon_genders'] : [],
                ];
            }
            usort($day_items, function($a, $b) {
                $da = (string) ($a['date'] ?? '');
                $db = (string) ($b['date'] ?? '');
                if ($da === $db) {
                    $sa = (string) ($a['start_time'] ?? '');
                    $sb = (string) ($b['start_time'] ?? '');
                    return $sa <=> $sb;
                }
                return $da <=> $db;
            });

            $out[] = [
                'content_type' => 'quests',
                'days' => $day_items,
            ];
        }
    }

    // 4) Autres contenus : content_day_pokemon_hours (hors raids/eggs/quests)
    $main_tbl    = function_exists('pokehub_get_table') ? pokehub_get_table('content_day_pokemon_hours') : '';
    $entries_tbl = function_exists('pokehub_get_table') ? pokehub_get_table('content_day_pokemon_hour_entries') : '';
    if ($main_tbl && $entries_tbl) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$main_tbl} WHERE source_type = %s AND source_id = %d ORDER BY sort_order ASC, id ASC",
            $source_type,
            $source_id
        ), ARRAY_A);

        foreach ((array) $rows as $r) {
            $content_hours_id = (int) ($r['id'] ?? 0);
            $ct = (string) ($r['content_type'] ?? 'featured_hours');
            if ($content_hours_id <= 0) continue;
            if (in_array($ct, ['raids', 'eggs', 'quests'], true)) continue;

            $entries = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$entries_tbl} WHERE content_day_pokemon_hours_id = %d ORDER BY sort_order ASC, id ASC",
                $content_hours_id
            ), ARRAY_A);

            $days = [];
            foreach ($entries as $e) {
                $pokemon_ids_raw = $e['pokemon_ids'] ?? '';
                $pokemon_payload = pokehub_content_normalize_pokemon_ids_with_genders($pokemon_ids_raw);
                $pokemon_ids = $pokemon_payload['pokemon_ids'];
                $pokemon_genders = $pokemon_payload['pokemon_genders'];

                $days[] = [
                    'date' => (string) ($e['day_date'] ?? ''),
                'end_date' => (string) ($e['end_day_date'] ?? ''),
                    'start_time' => (string) ($e['start_time'] ?? ''),
                    'end_time' => (string) ($e['end_time'] ?? ''),
                    'pokemon_ids' => $pokemon_ids,
                    'pokemon_genders' => $pokemon_genders,
                ];
            }

            $out[] = [
                'content_type' => $ct,
                'days' => $days,
            ];
        }
    }

    // 5) Heures vedette (featured_hours) : même source que le bloc front — special_events si les tables existent.
    // Sinon la section 4 ci-dessus (content_day_pokemon_hours) reste la seule source pour ce type.
    if ($source_type === 'post' && $source_id > 0
        && function_exists('pokehub_get_table')
        && function_exists('pokehub_content_get_featured_hours_classic_events_entries_for_parent')) {
        $se_tbl = (string) pokehub_get_table('special_events');
        $sep_tbl = (string) pokehub_get_table('special_event_pokemon');
        if ($se_tbl !== '' && $sep_tbl !== '') {
            $out = array_values(array_filter($out, static function ($set) {
                return (($set['content_type'] ?? '') !== 'featured_hours');
            }));
            $spotlight_days = pokehub_content_get_featured_hours_classic_events_entries_for_parent($source_id);
            array_unshift($out, [
                'content_type' => 'featured_hours',
                'days' => $spotlight_days,
            ]);
        }
    }

    return $out;
}

/**
 * Récupère les entrées (days) pour un content_type donné.
 *
 * @param string $source_type
 * @param int    $source_id
 * @param string $content_type
 * @return array[] ['date','start_time','end_time','pokemon_ids']
 */
function pokehub_content_get_day_pokemon_hours_entries($source_type, $source_id, string $content_type): array {
    $content_type = sanitize_key($content_type);
    if ($content_type === '') {
        $content_type = 'featured_hours';
    }

    $sets = pokehub_content_get_day_pokemon_hours_sets($source_type, $source_id);
    $merged = []; // day_key => ['date','end_date','start_time','end_time','pokemon_ids_map'=>[pid=>true],'pokemon_genders'=>[pid=>gender]]
    foreach ($sets as $set) {
        if (($set['content_type'] ?? '') !== $content_type) {
            continue;
        }
        if (!empty($set['days']) && is_array($set['days'])) {
            foreach ($set['days'] as $d) {
                if (is_array($d)) {
                    $date = (string) ($d['date'] ?? '');
                    $end_date = trim((string) ($d['end_date'] ?? ''));
                    if ($end_date === '') {
                        $end_date = $date;
                    }
                    $start_time = (string) ($d['start_time'] ?? '');
                    $end_time = (string) ($d['end_time'] ?? '');
                    if ($date === '') {
                        continue;
                    }
                    $day_key = $date . '|' . $end_date . '|' . $start_time . '|' . $end_time;
                    if (!isset($merged[$day_key])) {
                        $merged[$day_key] = [
                            'date' => $date,
                            'end_date' => $end_date,
                            'start_time' => $start_time,
                            'end_time' => $end_time,
                            'pokemon_ids_map' => [],
                            'pokemon_genders' => [],
                        ];
                    }
                    $pokemon_ids = isset($d['pokemon_ids']) && is_array($d['pokemon_ids']) ? $d['pokemon_ids'] : [];
                    foreach ($pokemon_ids as $pid) {
                        $pid = (int) $pid;
                        if ($pid > 0) {
                            $merged[$day_key]['pokemon_ids_map'][$pid] = true;
                        }
                    }
                    $pokemon_genders = isset($d['pokemon_genders']) && is_array($d['pokemon_genders']) ? $d['pokemon_genders'] : [];
                    foreach ($pokemon_genders as $pid => $gender) {
                        $pid = (int) $pid;
                        $gender = is_string($gender) ? sanitize_key($gender) : '';
                        if ($pid > 0 && in_array($gender, ['male', 'female'], true) && isset($merged[$day_key]['pokemon_ids_map'][$pid])) {
                            $merged[$day_key]['pokemon_genders'][(string) $pid] = $gender;
                        }
                    }
                }
            }
        }
    }

    $days = [];
    foreach ($merged as $item) {
        $days[] = [
            'date' => (string) ($item['date'] ?? ''),
            'end_date' => (string) ($item['end_date'] ?? ($item['date'] ?? '')),
            'start_time' => (string) ($item['start_time'] ?? ''),
            'end_time' => (string) ($item['end_time'] ?? ''),
            'pokemon_ids' => array_values(array_map('intval', array_keys((array) ($item['pokemon_ids_map'] ?? [])))),
            'pokemon_genders' => isset($item['pokemon_genders']) && is_array($item['pokemon_genders']) ? $item['pokemon_genders'] : [],
        ];
    }

    usort($days, function($a, $b) {
        $da = (string) ($a['date'] ?? '');
        $db = (string) ($b['date'] ?? '');
        if ($da === $db) {
            $sa = (string) ($a['start_time'] ?? '');
            $sb = (string) ($b['start_time'] ?? '');
            return $sa <=> $sb;
        }
        return $da <=> $db;
    });

    return $days;
}

/**
 * Sauvegarde les "sets" jour/pokémon/heures pour une source.
 *
 * @param string $source_type
 * @param int    $source_id
 * @param array  $sets [
 *   [
 *     'content_type' => 'featured_hours',
 *     'days' => [
 *       ['date'=>'YYYY-MM-DD','start_time'=>'18:00','end_time'=>'19:00','pokemon_ids'=>[1,2]],
 *       ...
 *     ]
 *   ],
 *   ...
 * ]
 */
function pokehub_content_save_day_pokemon_hours($source_type, $source_id, array $sets): void {
    global $wpdb;

    $source_type = (string) $source_type;
    $source_id   = (int) $source_id;

    $main_tbl    = pokehub_get_table('content_day_pokemon_hours');
    $entries_tbl = pokehub_get_table('content_day_pokemon_hour_entries');
    if (!$main_tbl || !$entries_tbl) {
        return;
    }

    // 1) Nettoyer la structure reçue
    $cleaned_sets = [];
    foreach ($sets as $set) {
        if (!is_array($set)) continue;

        $content_type = isset($set['content_type']) ? sanitize_key((string) $set['content_type']) : 'featured_hours';
        if ($content_type === '') {
            $content_type = 'featured_hours';
        }

        $days_raw = isset($set['days']) && is_array($set['days']) ? $set['days'] : [];
        $days = [];
        foreach ($days_raw as $day) {
            if (!is_array($day)) continue;

            $date = sanitize_text_field((string) ($day['date'] ?? ''));
            $start_time = sanitize_text_field((string) ($day['start_time'] ?? ''));
            $end_time   = sanitize_text_field((string) ($day['end_time'] ?? ''));
            $end_date   = sanitize_text_field((string) ($day['end_date'] ?? ''));
            if ($end_date === '') {
                $end_date = $date;
            }

            $pokemon_ids = isset($day['pokemon_ids']) ? $day['pokemon_ids'] : [];
            $pokemon_payload = pokehub_content_normalize_pokemon_ids_with_genders($pokemon_ids);
            $pokemon_ids = $pokemon_payload['pokemon_ids'];
            $pokemon_genders = [];
            if (isset($day['pokemon_genders']) && is_array($day['pokemon_genders'])) {
                foreach ($day['pokemon_genders'] as $pid => $gender) {
                    $pid = (int) $pid;
                    $gender = is_string($gender) ? sanitize_key($gender) : '';
                    if ($pid > 0 && in_array($pid, $pokemon_ids, true) && in_array($gender, ['male', 'female'], true)) {
                        $pokemon_genders[(string) $pid] = $gender;
                    }
                }
            } else {
                $pokemon_genders = $pokemon_payload['pokemon_genders'];
            }

            if ($date === '' || empty($pokemon_ids)) {
                continue;
            }

            $days[] = [
                'date'        => $date,
                'end_date'    => $end_date,
                'start_time'  => $start_time,
                'end_time'    => $end_time,
                'pokemon_ids' => $pokemon_ids,
                'pokemon_genders' => $pokemon_genders,
            ];
        }

        if (empty($days)) {
            continue;
        }

        $cleaned_sets[] = [
            'content_type' => $content_type,
            'days'          => $days,
        ];
    }

    if (empty($cleaned_sets)) {
        // Supprimer les données existantes (si rien n'est fourni)
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$main_tbl} WHERE source_type = %s AND source_id = %d",
            $source_type,
            $source_id
        ), ARRAY_A);
        if (!empty($existing)) {
            foreach ($existing as $r) {
                $mid = (int) ($r['id'] ?? 0);
                if ($mid > 0) {
                    $wpdb->query($wpdb->prepare("DELETE FROM {$entries_tbl} WHERE content_day_pokemon_hours_id = %d", $mid));
                    $wpdb->query($wpdb->prepare("DELETE FROM {$main_tbl} WHERE id = %d", $mid));
                }
            }
        }
        return;
    }

    $desired_types = array_values(array_unique(array_map(function($s) {
        return (string) ($s['content_type'] ?? 'featured_hours');
    }, $cleaned_sets)));

    // 2) Récupérer les rows existantes
    $existing_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, content_type FROM {$main_tbl} WHERE source_type = %s AND source_id = %d",
        $source_type,
        $source_id
    ), ARRAY_A);

    $existing_by_type = [];
    foreach ($existing_rows as $r) {
        $cid = (int) ($r['id'] ?? 0);
        $ct  = (string) ($r['content_type'] ?? '');
        if ($cid > 0 && $ct !== '') {
            $existing_by_type[$ct] = $cid;
        }
    }

    // 3) Supprimer les content_type non soumis
    foreach ($existing_by_type as $ct => $mid) {
        if (!in_array($ct, $desired_types, true)) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$entries_tbl} WHERE content_day_pokemon_hours_id = %d", (int) $mid));
            $wpdb->query($wpdb->prepare("DELETE FROM {$main_tbl} WHERE id = %d", (int) $mid));
            unset($existing_by_type[$ct]);
        }
    }

    // 4) Upsert + insert des entries
    $sort_order = 0;
    foreach ($cleaned_sets as $set) {
        $content_type = (string) ($set['content_type'] ?? 'featured_hours');
        $days = isset($set['days']) && is_array($set['days']) ? $set['days'] : [];
        if (empty($days)) continue;

        $start_ts = 0;
        $end_ts = 0;
        $tz = wp_timezone();

        foreach ($days as $d) {
            $date = (string) ($d['date'] ?? '');
            $end_date_raw = trim((string) ($d['end_date'] ?? ''));
            $end_date_effective = ($end_date_raw !== '') ? $end_date_raw : $date;
            $st = (string) ($d['start_time'] ?? '');
            $et = (string) ($d['end_time'] ?? '');
            if ($date === '') continue;

            // Calcul optionnel : sert surtout si tu veux un filtre "actif"
            if ($st !== '') {
                try {
                    $dtStart = new DateTime($date . ' ' . $st, $tz);
                    $ts = (int) $dtStart->getTimestamp();
                    if ($ts > 0 && ($start_ts === 0 || $ts < $start_ts)) {
                        $start_ts = $ts;
                    }
                } catch (Exception $e) {
                    // ignore parse errors
                }
            }
            if ($et !== '') {
                try {
                    $dtEnd = new DateTime($end_date_effective . ' ' . $et, $tz);
                    $ts = (int) $dtEnd->getTimestamp();
                    if ($ts > 0 && $ts > $end_ts) {
                        $end_ts = $ts;
                    }
                } catch (Exception $e) {
                    // ignore parse errors
                }
            }
        }

        $row_id = isset($existing_by_type[$content_type]) ? (int) $existing_by_type[$content_type] : 0;
        if ($row_id > 0) {
            $wpdb->update($main_tbl, [
                'content_type' => $content_type,
                'start_ts'     => (int) $start_ts,
                'end_ts'       => (int) $end_ts,
                'sort_order'   => (int) $sort_order,
                'updated_at'   => current_time('mysql'),
            ], ['id' => $row_id], ['%s', '%d', '%d', '%d', '%s'], ['%d']);
        } else {
            $wpdb->insert($main_tbl, [
                'source_type'  => $source_type,
                'source_id'    => $source_id,
                'content_type' => $content_type,
                'start_ts'     => (int) $start_ts,
                'end_ts'       => (int) $end_ts,
                'sort_order'   => (int) $sort_order,
            ], ['%s', '%d', '%s', '%d', '%d', '%d']);
            $row_id = (int) $wpdb->insert_id;
            $existing_by_type[$content_type] = $row_id;
        }

        if ($row_id <= 0) continue;

        // On remplace les entries pour ce set
        $wpdb->query($wpdb->prepare("DELETE FROM {$entries_tbl} WHERE content_day_pokemon_hours_id = %d", $row_id));

        $entry_sort = 0;
        foreach ($days as $d) {
            $date = (string) ($d['date'] ?? '');
            $end_date_raw = trim((string) ($d['end_date'] ?? ''));
            $st = (string) ($d['start_time'] ?? '');
            $et = (string) ($d['end_time'] ?? '');
            $pokemon_ids = isset($d['pokemon_ids']) && is_array($d['pokemon_ids']) ? $d['pokemon_ids'] : [];
            $pokemon_genders = isset($d['pokemon_genders']) && is_array($d['pokemon_genders']) ? $d['pokemon_genders'] : [];

            if ($date === '' || empty($pokemon_ids)) continue;

            $gender_map = [];
            foreach ($pokemon_genders as $pid => $gender) {
                $pid = (int) $pid;
                $gender = is_string($gender) ? sanitize_key($gender) : '';
                if ($pid > 0 && in_array($pid, $pokemon_ids, true) && in_array($gender, ['male', 'female'], true)) {
                    $gender_map[(string) $pid] = $gender;
                }
            }

            $wpdb->insert($entries_tbl, [
                'content_day_pokemon_hours_id' => $row_id,
                'day_date'  => $date,
                'start_time'=> $st,
                'end_time'  => $et,
                'end_day_date' => $end_date_raw,
                'pokemon_ids' => wp_json_encode([
                    'pokemon_ids' => array_values(array_map('intval', $pokemon_ids)),
                    'pokemon_genders' => $gender_map,
                ]),
                'sort_order'=> (int) $entry_sort,
            ], ['%d', '%s', '%s', '%s', '%s', '%s', '%d']);

            $entry_sort++;
        }

        $sort_order++;
    }
}

/**
 * Sauvegarde day-by-day des raids dans les tables existantes.
 * - Un row `content_raids` par entrée (date + start/end).
 * - Des rows `content_raid_bosses` par Pokémon.
 *
 * @param string $source_type
 * @param int $source_id
 * @param array $sets Structure: [ [ 'raid_tier'=>int, 'raid_is_mega'=>bool, 'days'=>[...] ], ... ]
 *                      Chaque day: ['date'=>YYYY-MM-DD,'start_time'=>'HH:MM','end_time'=>'HH:MM','pokemon_ids'=>[...]]
 */
function pokehub_content_save_day_pokemon_hours_raids(string $source_type, int $source_id, array $sets): void {
    global $wpdb;
    $source_type = (string) $source_type;
    $source_id = (int) $source_id;

    $raids_tbl = pokehub_get_table('content_raids');
    $raid_bosses_tbl = pokehub_get_table('content_raid_bosses');
    if (!$raids_tbl || !$raid_bosses_tbl) {
        return;
    }
    $raid_has_gender_col = pokehub_content_table_has_column($raid_bosses_tbl, 'gender');

    $tz = wp_timezone();
    $make_ts = function(string $date, string $time) use ($tz): int {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '') return 0;
        try {
            $dt = new DateTime($date . ' ' . $time, $tz);
            return (int) $dt->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
    };

    // Nettoyage complet pour cette source (évite de mélanger plusieurs sauvegardes).
    $raid_ids = $wpdb->get_results($wpdb->prepare(
        "SELECT id FROM {$raids_tbl} WHERE source_type = %s AND source_id = %d",
        $source_type,
        $source_id
    ), ARRAY_A);

    foreach ((array) $raid_ids as $r) {
        $rid = (int) ($r['id'] ?? 0);
        if ($rid > 0) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$raid_bosses_tbl} WHERE content_raid_id = %d", $rid));
        }
    }
    $wpdb->query($wpdb->prepare("DELETE FROM {$raids_tbl} WHERE source_type = %s AND source_id = %d", $source_type, $source_id));

    $sort_order = 0;
    foreach ($sets as $set) {
        if (!is_array($set)) continue;
        $tier = isset($set['raid_tier']) ? max(1, min(5, (int) $set['raid_tier'])) : 1;
        $is_mega = !empty($set['raid_is_mega']) ? 1 : 0;
        $days_raw = isset($set['days']) && is_array($set['days']) ? $set['days'] : [];

        foreach ($days_raw as $day) {
            if (!is_array($day)) continue;
            $date = sanitize_text_field((string) ($day['date'] ?? ''));
            $end_date = sanitize_text_field((string) ($day['end_date'] ?? ''));
            $st = sanitize_text_field((string) ($day['start_time'] ?? ''));
            $et = sanitize_text_field((string) ($day['end_time'] ?? ''));
            if ($end_date === '') {
                $end_date = $date;
            }
            $pokemon_ids = isset($day['pokemon_ids']) ? $day['pokemon_ids'] : [];
            $pokemon_payload = pokehub_content_normalize_pokemon_ids_with_genders($pokemon_ids);
            $pokemon_ids = $pokemon_payload['pokemon_ids'];
            $pokemon_genders = isset($day['pokemon_genders']) && is_array($day['pokemon_genders']) ? $day['pokemon_genders'] : $pokemon_payload['pokemon_genders'];
            $gender_map = [];
            foreach ($pokemon_genders as $pid => $gender) {
                $pid = (int) $pid;
                $gender = is_string($gender) ? sanitize_key($gender) : '';
                if ($pid > 0 && in_array($pid, $pokemon_ids, true) && in_array($gender, ['male', 'female'], true)) {
                    $gender_map[$pid] = $gender;
                }
            }

            if ($date === '' || empty($pokemon_ids)) continue;

            $start_ts = $make_ts($date, $st);
            $end_ts = $make_ts($end_date, $et);
            if ($start_ts <= 0 || $end_ts <= 0) continue;

            $wpdb->insert($raids_tbl, [
                'source_type' => $source_type,
                'source_id' => $source_id,
                'start_ts' => (int) $start_ts,
                'end_ts' => (int) $end_ts,
                'name' => null,
                'sort_order' => (int) $sort_order,
            ], ['%s', '%d', '%d', '%d', '%s', '%d']);

            $raid_id = (int) $wpdb->insert_id;
            if ($raid_id <= 0) continue;

            $boss_sort = 0;
            foreach ($pokemon_ids as $pid) {
                $current_sort = (int) $boss_sort++;
                if ($raid_has_gender_col) {
                    $wpdb->insert($raid_bosses_tbl, [
                        'content_raid_id' => $raid_id,
                        'tier' => (int) $tier,
                        'pokemon_id' => (int) $pid,
                        'gender' => isset($gender_map[$pid]) ? (string) $gender_map[$pid] : null,
                        'is_mega' => (int) $is_mega,
                        'sort_order' => $current_sort,
                    ], ['%d', '%d', '%d', '%s', '%d', '%d']);
                } else {
                    $wpdb->insert($raid_bosses_tbl, [
                        'content_raid_id' => $raid_id,
                        'tier' => (int) $tier,
                        'pokemon_id' => (int) $pid,
                        'is_mega' => (int) $is_mega,
                        'sort_order' => $current_sort,
                    ], ['%d', '%d', '%d', '%d', '%d']);
                }
            }

            $sort_order++;
        }
    }
}

/**
 * Sauvegarde day-by-day des œufs dans les tables existantes.
 * - Un row `content_eggs` par entrée (date + start/end).
 * - Des rows `content_egg_pokemon` par Pokémon (avec egg_type_id).
 *
 * @param string $source_type
 * @param int $source_id
 * @param array $sets Structure: [ [ 'egg_type_id'=>int, 'days'=>[...] ], ... ]
 */
function pokehub_content_save_day_pokemon_hours_eggs(string $source_type, int $source_id, array $sets): void {
    global $wpdb;
    $source_type = (string) $source_type;
    $source_id = (int) $source_id;

    $eggs_tbl = pokehub_get_table('content_eggs');
    $egg_pokemon_tbl = pokehub_get_table('content_egg_pokemon');
    if (!$eggs_tbl || !$egg_pokemon_tbl) {
        return;
    }
    $egg_has_gender_col = pokehub_content_table_has_column($egg_pokemon_tbl, 'gender');

    $tz = wp_timezone();
    $make_ts = function(string $date, string $time) use ($tz): int {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '') return 0;
        try {
            $dt = new DateTime($date . ' ' . $time, $tz);
            return (int) $dt->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
    };

    // Nettoyage complet pour cette source.
    $egg_ids = $wpdb->get_results($wpdb->prepare(
        "SELECT id FROM {$eggs_tbl} WHERE source_type = %s AND source_id = %d",
        $source_type,
        $source_id
    ), ARRAY_A);

    foreach ((array) $egg_ids as $r) {
        $eid = (int) ($r['id'] ?? 0);
        if ($eid > 0) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$egg_pokemon_tbl} WHERE content_egg_id = %d", $eid));
        }
    }
    $wpdb->query($wpdb->prepare("DELETE FROM {$eggs_tbl} WHERE source_type = %s AND source_id = %d", $source_type, $source_id));

    $sort_order = 0;
    foreach ($sets as $set) {
        if (!is_array($set)) continue;
        $egg_type_id = isset($set['egg_type_id']) ? (int) $set['egg_type_id'] : 0;
        $days_raw = isset($set['days']) && is_array($set['days']) ? $set['days'] : [];
        if ($egg_type_id <= 0) continue;

        foreach ($days_raw as $day) {
            if (!is_array($day)) continue;
            $date = sanitize_text_field((string) ($day['date'] ?? ''));
            $end_date = sanitize_text_field((string) ($day['end_date'] ?? ''));
            $st = sanitize_text_field((string) ($day['start_time'] ?? ''));
            $et = sanitize_text_field((string) ($day['end_time'] ?? ''));
            if ($end_date === '') {
                $end_date = $date;
            }
            $pokemon_ids = isset($day['pokemon_ids']) ? $day['pokemon_ids'] : [];
            $pokemon_payload = pokehub_content_normalize_pokemon_ids_with_genders($pokemon_ids);
            $pokemon_ids = $pokemon_payload['pokemon_ids'];
            $pokemon_genders = isset($day['pokemon_genders']) && is_array($day['pokemon_genders']) ? $day['pokemon_genders'] : $pokemon_payload['pokemon_genders'];
            $gender_map = [];
            foreach ($pokemon_genders as $pid => $gender) {
                $pid = (int) $pid;
                $gender = is_string($gender) ? sanitize_key($gender) : '';
                if ($pid > 0 && in_array($pid, $pokemon_ids, true) && in_array($gender, ['male', 'female'], true)) {
                    $gender_map[$pid] = $gender;
                }
            }

            if ($date === '' || empty($pokemon_ids)) continue;

            $start_ts = $make_ts($date, $st);
            $end_ts = $make_ts($end_date, $et);
            if ($start_ts <= 0 || $end_ts <= 0) continue;

            $wpdb->insert($eggs_tbl, [
                'source_type' => $source_type,
                'source_id' => $source_id,
                'start_ts' => (int) $start_ts,
                'end_ts' => (int) $end_ts,
                'name' => null,
                'sort_order' => (int) $sort_order,
            ], ['%s', '%d', '%d', '%d', '%s', '%d']);

            $egg_id = (int) $wpdb->insert_id;
            if ($egg_id <= 0) continue;

            $boss_sort = 0;
            foreach ($pokemon_ids as $pid) {
                $current_sort = (int) $boss_sort++;
                if ($egg_has_gender_col) {
                    $wpdb->insert($egg_pokemon_tbl, [
                        'content_egg_id' => $egg_id,
                        'egg_type_id' => (int) $egg_type_id,
                        'pokemon_id' => (int) $pid,
                        'gender' => isset($gender_map[$pid]) ? (string) $gender_map[$pid] : null,
                        'rarity' => 1,
                        'is_worldwide_override' => 0,
                        'is_forced_shiny' => 0,
                        'sort_order' => $current_sort,
                    ], ['%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d']);
                } else {
                    $wpdb->insert($egg_pokemon_tbl, [
                        'content_egg_id' => $egg_id,
                        'egg_type_id' => (int) $egg_type_id,
                        'pokemon_id' => (int) $pid,
                        'rarity' => 1,
                        'is_worldwide_override' => 0,
                        'is_forced_shiny' => 0,
                        'sort_order' => $current_sort,
                    ], ['%d', '%d', '%d', '%d', '%d', '%d', '%d']);
                }
            }

            $sort_order++;
        }
    }
}

/**
 * Sauvegarde day-by-day des quêtes dans les tables existantes.
 * - Un row `content_quests` par entrée (date + start/end).
 * - Une (ou plusieurs) row(s) `content_quest_lines` avec rewards pokemon.
 *
 * @param string $source_type
 * @param int $source_id
 * @param array $sets Structure: [ [ 'days'=>[...] ], ... ]
 */
function pokehub_content_save_day_pokemon_hours_quests(string $source_type, int $source_id, array $sets): void {
    global $wpdb;
    $source_type = (string) $source_type;
    $source_id = (int) $source_id;

    $quests_tbl = pokehub_get_table('content_quests');
    $quest_lines_tbl = pokehub_get_table('content_quest_lines');
    if (!$quests_tbl || !$quest_lines_tbl) {
        return;
    }

    $tz = wp_timezone();
    $make_ts = function(string $date, string $time) use ($tz): int {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '') return 0;
        try {
            $dt = new DateTime($date . ' ' . $time, $tz);
            return (int) $dt->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
    };

    // Supprimer les en-têtes gérés par cette métabox : méta + marqueur JSON (orphelins si méta vide / désynchronisée).
    $ids_to_purge = [];
    if ((string) $source_type === 'post' && $source_id > 0) {
        $from_meta = [];
        $stored_day_ids = pokehub_content_get_day_schedule_quest_header_ids_for_source($source_type, $source_id);
        if (!empty($stored_day_ids)) {
            $placeholders = implode(',', array_fill(0, count($stored_day_ids), '%d'));
            $sql = $wpdb->prepare(
                "SELECT id FROM {$quests_tbl} WHERE source_type = %s AND source_id = %d AND id IN ({$placeholders})",
                array_merge([$source_type, $source_id], $stored_day_ids)
            );
            foreach ((array) $wpdb->get_col($sql) as $col) {
                $qid = (int) $col;
                if ($qid > 0) {
                    $from_meta[] = $qid;
                }
            }
        }
        $from_marker = function_exists('pokehub_content_get_day_quest_ids_with_hours_marker')
            ? pokehub_content_get_day_quest_ids_with_hours_marker($source_id)
            : [];
        $ids_to_purge = array_values(array_unique(array_merge($from_meta, $from_marker)));
    } elseif ($source_type !== 'post') {
        // Comportement historique si un appelant externe utilise un autre source_type.
        $quest_ids = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$quests_tbl} WHERE source_type = %s AND source_id = %d",
            $source_type,
            $source_id
        ), ARRAY_A);
        foreach ((array) $quest_ids as $r) {
            $qid = (int) ($r['id'] ?? 0);
            if ($qid > 0) {
                $ids_to_purge[] = $qid;
            }
        }
    }

    foreach ($ids_to_purge as $qid) {
        $wpdb->query($wpdb->prepare("DELETE FROM {$quest_lines_tbl} WHERE content_quest_id = %d", $qid));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$quests_tbl} WHERE id = %d AND source_type = %s AND source_id = %d",
            $qid,
            $source_type,
            $source_id
        ));
    }

    $sort_order = 0;
    $new_day_quest_ids = [];
    foreach ($sets as $set) {
        if (!is_array($set)) continue;
        $days_raw = isset($set['days']) && is_array($set['days']) ? $set['days'] : [];

        foreach ($days_raw as $day) {
            if (!is_array($day)) continue;
            $date = sanitize_text_field((string) ($day['date'] ?? ''));
            $end_date = sanitize_text_field((string) ($day['end_date'] ?? ''));
            $st = sanitize_text_field((string) ($day['start_time'] ?? ''));
            $et = sanitize_text_field((string) ($day['end_time'] ?? ''));
            if ($end_date === '') {
                $end_date = $date;
            }
            $pokemon_ids = isset($day['pokemon_ids']) ? $day['pokemon_ids'] : [];
            $pokemon_payload = pokehub_content_normalize_pokemon_ids_with_genders($pokemon_ids);
            $pokemon_ids = $pokemon_payload['pokemon_ids'];
            $pokemon_genders = isset($day['pokemon_genders']) && is_array($day['pokemon_genders']) ? $day['pokemon_genders'] : $pokemon_payload['pokemon_genders'];
            $gender_map = [];
            foreach ($pokemon_genders as $pid => $gender) {
                $pid = (int) $pid;
                $gender = is_string($gender) ? sanitize_key($gender) : '';
                if ($pid > 0 && in_array($pid, $pokemon_ids, true) && in_array($gender, ['male', 'female'], true)) {
                    $gender_map[(string) $pid] = $gender;
                }
            }

            if ($date === '' || empty($pokemon_ids)) continue;

            $start_ts = $make_ts($date, $st);
            $end_ts = $make_ts($end_date, $et);
            if ($start_ts <= 0 || $end_ts <= 0) continue;

            $wpdb->insert($quests_tbl, [
                'source_type' => $source_type,
                'source_id' => $source_id,
                'start_ts' => (int) $start_ts,
                'end_ts' => (int) $end_ts,
                'sort_order' => (int) $sort_order,
            ], ['%s', '%d', '%d', '%d', '%d']);

            $quest_id = (int) $wpdb->insert_id;
            if ($quest_id <= 0) continue;

            // Une line unique (task vide) contenant tous les Pokémon récompensés (+ marqueur métabox pour purge fiable).
            $marker_key = pokehub_content_day_quest_reward_marker_key();
            $rewards = [[
                'type' => 'pokemon',
                'pokemon_ids' => $pokemon_ids,
                'force_shiny' => false,
                'pokemon_genders' => $gender_map,
                $marker_key => true,
            ]];

            $wpdb->insert($quest_lines_tbl, [
                'content_quest_id' => $quest_id,
                'quest_group_id' => null,
                'task' => '',
                'rewards' => wp_json_encode($rewards),
                'sort_order' => 0,
            ], ['%d', '%d', '%s', '%s', '%d']);

            $new_day_quest_ids[] = $quest_id;
            $sort_order++;
        }
    }

    if ($source_type === 'post' && $source_id > 0 && function_exists('update_post_meta')) {
        update_post_meta((int) $source_id, pokehub_content_day_schedule_quest_meta_key(), $new_day_quest_ids);
    }
}

if (!function_exists('pokehub_special_events_has_content_source_columns')) {
    /**
     * Indique si la table special_events expose content_source_* (liaison stable vers un post).
     * Ne met en cache que le positif : après migration admin la même requête doit revoir la colonne.
     */
    function pokehub_special_events_has_content_source_columns(): bool {
        static $cached_yes = false;
        if ($cached_yes) {
            return true;
        }
        global $wpdb;
        $table = function_exists('pokehub_get_table') ? pokehub_get_table('special_events') : '';
        if ($table === '' || $wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return false;
        }
        $n = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                DB_NAME,
                $table,
                'content_source_type'
            )
        );
        if ($n > 0) {
            $cached_yes = true;
        }
        return $n > 0;
    }
}

if (!function_exists('pokehub_spotlight_event_type_candidate_slugs')) {
    /**
     * Slugs candidats pour le type d'événement Spotlight (ordre = priorité de résolution).
     */
    function pokehub_spotlight_event_type_candidate_slugs(): array {
        return [
            'pokemon-spotlight-hour',
            'pokemon_spotlight_hour',
            'spotlight-hour',
            'spotlight_hour',
            'spotlighthour',
            'spotlighthourly',
        ];
    }
}

if (!function_exists('pokehub_resolve_spotlight_event_type_slug')) {
    /**
     * Résout le slug de type enregistré en base (premier candidat trouvé), sinon le canonique Pokémon GO.
     */
    function pokehub_resolve_spotlight_event_type_slug(): string {
        foreach (pokehub_spotlight_event_type_candidate_slugs() as $slug) {
            if (function_exists('poke_hub_events_get_event_type_by_slug')) {
                $obj = poke_hub_events_get_event_type_by_slug((string) $slug);
                if ($obj && !empty($obj->slug)) {
                    return (string) $obj->slug;
                }
            }
        }
        return 'pokemon-spotlight-hour';
    }
}

if (!function_exists('pokehub_spotlight_event_type_slugs_for_query')) {
    /**
     * Liste des valeurs event_type à considérer comme Spotlight (requêtes IN / nettoyage).
     */
    function pokehub_spotlight_event_type_slugs_for_query(): array {
        $out = [];
        foreach (pokehub_spotlight_event_type_candidate_slugs() as $cand) {
            $out[] = $cand;
            if (function_exists('poke_hub_events_get_event_type_by_slug')) {
                $obj = poke_hub_events_get_event_type_by_slug((string) $cand);
                if ($obj && !empty($obj->slug)) {
                    $out[] = (string) $obj->slug;
                }
            }
        }
        $resolved = pokehub_resolve_spotlight_event_type_slug();
        if ($resolved !== '') {
            $out[] = $resolved;
        }
        $out = array_values(array_unique(array_filter(array_map('strval', $out))));
        return $out;
    }
}

if (!function_exists('pokehub_spotlight_sql_parent_scope')) {
    /**
     * Fragment WHERE + arguments pour $wpdb->prepare : événements Spotlight liés à un post parent.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    function pokehub_spotlight_sql_parent_scope(int $parent_post_id): array {
        global $wpdb;
        $slugs = pokehub_spotlight_event_type_slugs_for_query();
        if (empty($slugs)) {
            $slugs = ['pokemon-spotlight-hour'];
        }

        if (pokehub_special_events_has_content_source_columns()) {
            $in_types = implode(',', array_fill(0, count($slugs), '%s'));
            $legacy = [];
            $args = $slugs;
            foreach ($slugs as $t) {
                $legacy[] = '( (content_source_type IS NULL OR content_source_type = \'\' OR content_source_id IS NULL OR content_source_id = 0) AND event_type = %s AND slug LIKE %s )';
                $args[] = $t;
                $args[] = $wpdb->esc_like(sanitize_title($t . '-' . $parent_post_id . '-')) . '%';
            }
            $args[] = 'post';
            $args[] = (int) $parent_post_id;
            $sql = "event_type IN ({$in_types}) AND (" . implode(' OR ', $legacy) . ' OR (content_source_type = %s AND content_source_id = %d))';
            return [$sql, $args];
        }

        $parts = [];
        $args = [];
        foreach ($slugs as $t) {
            $parts[] = '(event_type = %s AND slug LIKE %s)';
            $args[] = $t;
            $args[] = $wpdb->esc_like(sanitize_title($t . '-' . $parent_post_id . '-')) . '%';
        }
        $sql = '(' . implode(' OR ', $parts) . ')';
        return [$sql, $args];
    }
}

function pokehub_content_save_day_pokemon_hours_featured_hours_classic_events(string $source_type, int $source_id, array $sets): void {
/**
 * Sauvegarde "Heure vedette" (featured_hours) / Spotlight sous forme de "special events" SQL.
 *
 * Objectif : ne plus créer de "posts/articles" WordPress. On écrit uniquement dans :
 * - `special_events` (1 ligne par créneau time-slot)
 * - `special_event_pokemon` (les Pokémon liés à ce créneau)
 *
 * Les anciennes données (posts enfants classic events) sont optionnellement supprimées si présentes.
 *
 * @param string $source_type (attendu 'post')
 * @param int    $source_id   ID du post parent (source des données)
 * @param array  $sets        Structure metabox : [ [ 'content_type'=>'featured_hours', 'days'=>[...] ], ... ]
 */
    if (!function_exists('wp_delete_post')) {
        return;
    }

    global $wpdb;
    $source_type = (string) $source_type;
    $parent_id   = (int) $source_id;
    if ($parent_id <= 0) {
        return;
    }

    $events_tbl_early = function_exists('pokehub_get_table') ? pokehub_get_table('special_events') : '';
    $event_pokemon_tbl_early = function_exists('pokehub_get_table') ? pokehub_get_table('special_event_pokemon') : '';
    if (!$events_tbl_early || !$event_pokemon_tbl_early) {
        // Pas de tables événements : ne rien détruire ici — la metabox repasse sur content_day_pokemon_hours.
        return;
    }

    if (!function_exists('pokehub_generate_unique_event_slug')) {
        $slug_helper = (defined('POKE_HUB_MODULES_DIR') ? POKE_HUB_MODULES_DIR : '') . 'events/functions/events-admin-helpers.php';
        if (is_readable($slug_helper)) {
            require_once $slug_helper;
        }
    }

    // Flag anti-cascade : ce hook déclenche des wp_insert_post, qui vont relancer save_post.
    // On désactive la sync de dates pendant toute la génération.
    $GLOBALS['pokehub_skip_content_sync'] = true;

    $parent_post_type = function_exists('get_post_type') ? (string) get_post_type($parent_id) : 'post';
    if ($parent_post_type === '') {
        $parent_post_type = 'post';
    }

    $child_parent_meta_key = '_pokehub_featured_hours_parent_post_id';

    // 1) Nettoyage : supprimer les posts enfants générés précédemment
    $child_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT pm.post_id
         FROM {$wpdb->postmeta} pm
         WHERE pm.meta_key = %s
           AND pm.meta_value = %d",
        $child_parent_meta_key,
        $parent_id
    ));

    if (!empty($child_ids) && is_array($child_ids)) {
        foreach ($child_ids as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                if (function_exists('pokehub_content_save_day_pokemon_hours')) {
                    // Nettoie la table content_day_pokemon_hours pour éviter les orphelins.
                    pokehub_content_save_day_pokemon_hours('post', $cid, []);
                }
                wp_delete_post($cid, true);
            }
        }
    }

    $spotlight_event_type_slug = function_exists('pokehub_resolve_spotlight_event_type_slug')
        ? pokehub_resolve_spotlight_event_type_slug()
        : 'pokemon-spotlight-hour';

    // 3) Création des special_events + écriture dans special_event_pokemon
    $tz = wp_timezone();
    $make_ts = function(string $date, string $time) use ($tz): int {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '') return 0;
        try {
            $dt = new DateTime($date . ' ' . $time, $tz);
            return (int) $dt->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
    };

    $pokemon_data_fn = function_exists('pokehub_get_pokemon_data_by_id') ? 'pokehub_get_pokemon_data_by_id' : null;

    $events_tbl = $events_tbl_early;
    $event_pokemon_tbl = $event_pokemon_tbl_early;

    // Nettoyage : supprimer les special_events spotlight déjà créés pour ce parent
    // (liaison stable content_source_* si disponible, sinon préfixe de slug hérité).
    list($scope_sql, $scope_args) = pokehub_spotlight_sql_parent_scope((int) $parent_id);
    $sql_existing = "SELECT id, slug, title, title_en, title_fr, start_ts, end_ts FROM {$events_tbl} WHERE {$scope_sql}";
    $existing_rows = $wpdb->get_results(
        call_user_func_array([$wpdb, 'prepare'], array_merge([$sql_existing], $scope_args)),
        ARRAY_A
    );

    $preserved_titles_by_slug = [];
    $preserved_titles_by_slot = [];
    $existing_event_ids = [];
    if (!empty($existing_rows) && is_array($existing_rows)) {
        foreach ($existing_rows as $row) {
            $eid = (int) ($row['id'] ?? 0);
            if ($eid > 0) {
                $existing_event_ids[] = $eid;
            }
            $slug_key = isset($row['slug']) ? (string) $row['slug'] : '';
            $title_legacy = isset($row['title']) ? trim((string) $row['title']) : '';
            $title_en_prev = isset($row['title_en']) ? trim((string) $row['title_en']) : '';
            $title_fr_prev = isset($row['title_fr']) ? trim((string) $row['title_fr']) : '';
            if ($title_en_prev === '' && $title_legacy !== '') {
                $title_en_prev = $title_legacy;
            }
            $pack = [
                'en' => $title_en_prev,
                'fr' => $title_fr_prev,
                'legacy' => $title_legacy,
            ];
            $has_any_title = ($pack['en'] !== '' || $pack['fr'] !== '' || $pack['legacy'] !== '');
            if ($slug_key !== '' && $has_any_title) {
                $preserved_titles_by_slug[$slug_key] = $pack;
            }
            $sts = (int) ($row['start_ts'] ?? 0);
            $ets = (int) ($row['end_ts'] ?? 0);
            if ($sts > 0 && $ets > 0 && $has_any_title) {
                $preserved_titles_by_slot[$sts . '|' . $ets] = $pack;
            }
        }
    }

    if (!empty($existing_event_ids) && is_array($existing_event_ids)) {
        // Nettoyage des lignes dépendantes (pas de FK garanties).
        $event_pokemon_attacks_tbl = function_exists('pokehub_get_table') ? pokehub_get_table('special_event_pokemon_attacks') : '';
        $event_bonus_tbl = function_exists('pokehub_get_table') ? pokehub_get_table('special_event_bonus') : '';

        foreach ($existing_event_ids as $event_id) {
            $event_id = (int) $event_id;
            if ($event_id <= 0) continue;

            $wpdb->query($wpdb->prepare("DELETE FROM {$event_pokemon_tbl} WHERE event_id = %d", $event_id));
            if ($event_pokemon_attacks_tbl) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$event_pokemon_attacks_tbl} WHERE event_id = %d", $event_id));
            }
            if ($event_bonus_tbl) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$event_bonus_tbl} WHERE event_id = %d", $event_id));
            }
            $wpdb->query($wpdb->prepare("DELETE FROM {$events_tbl} WHERE id = %d", $event_id));
        }
    }

    // Construire un index time-slot => liste de Pokémon
    $slots = []; // slot_key => ['date','end_date','st','et','start_ts','end_ts','pokemon_ids'=>[]]
    foreach ($sets as $set) {
        if (!is_array($set)) continue;
        $ct = isset($set['content_type']) ? sanitize_key((string) $set['content_type']) : 'featured_hours';
        if ($ct !== 'featured_hours') continue;

        $days_raw = isset($set['days']) && is_array($set['days']) ? $set['days'] : [];
        foreach ($days_raw as $day) {
            if (!is_array($day)) continue;

            $date = sanitize_text_field((string) ($day['date'] ?? ''));
            $st   = sanitize_text_field((string) ($day['start_time'] ?? ''));
            $et   = sanitize_text_field((string) ($day['end_time'] ?? ''));
            $end_date = sanitize_text_field((string) ($day['end_date'] ?? ''));
            if ($end_date === '') {
                $end_date = $date;
            }

            $pokemon_ids = isset($day['pokemon_ids']) ? $day['pokemon_ids'] : [];
            $pokemon_payload = pokehub_content_normalize_pokemon_ids_with_genders($pokemon_ids);
            $pokemon_ids = $pokemon_payload['pokemon_ids'];
            $pokemon_genders = isset($day['pokemon_genders']) && is_array($day['pokemon_genders']) ? $day['pokemon_genders'] : $pokemon_payload['pokemon_genders'];
            $gender_map = [];
            foreach ($pokemon_genders as $pid => $gender) {
                $pid = (int) $pid;
                $gender = is_string($gender) ? sanitize_key($gender) : '';
                if ($pid > 0 && in_array($pid, $pokemon_ids, true) && in_array($gender, ['male', 'female'], true)) {
                    $gender_map[(string) $pid] = $gender;
                }
            }

            if ($date === '' || $st === '' || $et === '' || empty($pokemon_ids)) {
                continue;
            }

            $start_ts = $make_ts($date, $st);
            $end_ts   = $make_ts($end_date, $et);
            if ($start_ts <= 0 || $end_ts <= 0 || $end_ts <= $start_ts) {
                continue;
            }

            $slot_key = $date . '|' . $end_date . '|' . $st . '|' . $et;
            if (!isset($slots[$slot_key])) {
                $slots[$slot_key] = [
                    'date' => $date,
                    'end_date' => $end_date,
                    'st' => $st,
                    'et' => $et,
                    'start_ts' => (int) $start_ts,
                    'end_ts' => (int) $end_ts,
                    'pokemon_ids' => [],
                    'pokemon_genders' => [],
                ];
            }

            $slots[$slot_key]['pokemon_ids'] = array_values(array_unique(array_merge(
                $slots[$slot_key]['pokemon_ids'],
                array_map('intval', $pokemon_ids)
            )));
            foreach ($gender_map as $pid => $gender) {
                if (in_array((int) $pid, $slots[$slot_key]['pokemon_ids'], true)) {
                    $slots[$slot_key]['pokemon_genders'][(string) $pid] = $gender;
                }
            }
        }
    }

    // Insérer les special_events + les liaisons Pokémon
    foreach ($slots as $slot_key => $slot) {
        $slot_date = (string) ($slot['date'] ?? '');
        $slot_end_date = (string) ($slot['end_date'] ?? $slot_date);
        $slot_st = (string) ($slot['st'] ?? '');
        $slot_et = (string) ($slot['et'] ?? '');
        $start_ts = (int) ($slot['start_ts'] ?? 0);
        $end_ts = (int) ($slot['end_ts'] ?? 0);
        $pokemon_ids = isset($slot['pokemon_ids']) && is_array($slot['pokemon_ids']) ? $slot['pokemon_ids'] : [];
        $pokemon_genders = isset($slot['pokemon_genders']) && is_array($slot['pokemon_genders']) ? $slot['pokemon_genders'] : [];

        if ($slot_date === '' || $slot_st === '' || $slot_et === '' || $start_ts <= 0 || $end_ts <= 0 || empty($pokemon_ids)) {
            continue;
        }

        $title_pid = (int) $pokemon_ids[0];
        $name_en = '';
        $name_fr = '';
        $poke_slug = '';
        if ($pokemon_data_fn && $title_pid > 0) {
            $p = call_user_func($pokemon_data_fn, $title_pid);
            if (is_array($p)) {
                $name_en = isset($p['name_en']) ? trim((string) $p['name_en']) : '';
                $name_fr = isset($p['name_fr']) ? trim((string) $p['name_fr']) : '';
                $poke_slug = isset($p['slug']) ? trim((string) $p['slug']) : '';
                if ($name_en === '' && $name_fr !== '') {
                    $name_en = $name_fr;
                }
                if ($name_fr === '' && $name_en !== '') {
                    $name_fr = $name_en;
                }
                if ($name_en === '' && !empty($p['name'])) {
                    $name_en = trim((string) $p['name']);
                }
                if ($name_fr === '' && $name_en !== '') {
                    $name_fr = $name_en;
                }
            }
        }
        if ($name_en === '' && $title_pid > 0) {
            $name_en = '#' . $title_pid;
        }
        if ($name_fr === '') {
            $name_fr = $name_en;
        }

        // Titres : EN "Mareep Spotlight Hour" ; FR "Heure vedette {nom FR}" ; slug : mareep-spotlight-hour, …
        $event_title_en = $name_en !== '' ? $name_en . ' Spotlight Hour' : 'Spotlight Hour';
        $fr_name = $name_fr !== '' ? $name_fr : $name_en;
        $event_title_fr = $fr_name !== '' ? 'Heure vedette ' . $fr_name : $event_title_en;
        $event_title = $event_title_en;

        $slug_core = $poke_slug !== '' ? $poke_slug : $name_en;
        $slug_core = sanitize_title($slug_core);
        if ($slug_core === '') {
            $slug_core = $title_pid > 0 ? 'pokemon-' . $title_pid : 'spotlight';
        }
        $slug_base = $slug_core . '-spotlight-hour';
        $event_slug = function_exists('pokehub_generate_unique_event_slug')
            ? pokehub_generate_unique_event_slug($slug_base, 0)
            : sanitize_title($slug_base);

        $slot_ts_key = (int) $start_ts . '|' . (int) $end_ts;
        $preserved_pack = null;
        if (isset($preserved_titles_by_slot[$slot_ts_key])) {
            $preserved_pack = $preserved_titles_by_slot[$slot_ts_key];
        } elseif (isset($preserved_titles_by_slug[$event_slug])) {
            $preserved_pack = $preserved_titles_by_slug[$event_slug];
        }
        if (is_array($preserved_pack)) {
            if (($preserved_pack['en'] ?? '') !== '') {
                $event_title_en = (string) $preserved_pack['en'];
            }
            if (($preserved_pack['fr'] ?? '') !== '') {
                $event_title_fr = (string) $preserved_pack['fr'];
            }
            if ($event_title_en !== '') {
                $event_title = $event_title_en;
            } elseif (($preserved_pack['legacy'] ?? '') !== '') {
                $event_title = (string) $preserved_pack['legacy'];
            }
        }

        $insert_row = [
            'slug' => (string) $event_slug,
            'title' => (string) $event_title,
            'title_en' => (string) $event_title_en,
            'title_fr' => (string) $event_title_fr,
            'description' => null,
            'event_type' => (string) $spotlight_event_type_slug,
            'start_ts' => (int) $start_ts,
            'end_ts' => (int) $end_ts,
            // Timestamps construits dans wp_timezone() : même sémantique que le mode « local » du formulaire admin.
            'mode' => 'local',
        ];
        $insert_formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'];
        if (function_exists('pokehub_special_events_has_content_source_columns') && pokehub_special_events_has_content_source_columns()) {
            $insert_row['content_source_type'] = 'post';
            $insert_row['content_source_id'] = (int) $parent_id;
            $insert_formats[] = '%s';
            $insert_formats[] = '%d';
        }
        $wpdb->insert($events_tbl, $insert_row, $insert_formats);

        $event_id = (int) $wpdb->insert_id;
        if ($event_id <= 0) continue;

        // Lier les Pokémon au special event
        foreach (array_values(array_unique(array_map('intval', $pokemon_ids))) as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;
            $wpdb->insert($event_pokemon_tbl, [
                'event_id' => (int) $event_id,
                'pokemon_id' => (int) $pid,
                'gender' => isset($pokemon_genders[(string) $pid]) ? (string) $pokemon_genders[(string) $pid] : null,
            ], ['%d', '%d', '%s']);
        }
    }

    unset($GLOBALS['pokehub_skip_content_sync']);
}

function pokehub_content_get_featured_hours_classic_events_entries_for_parent(int $parent_post_id): array {
/**
 * Lit l'affichage "featured_hours" (Spotlight) depuis `special_events` / `special_event_pokemon`.
 * Liaison au post : colonnes content_source_* si présentes, sinon préfixe de slug (rétrocompatibilité).
 *
 * @param int $parent_post_id
 * @return array[] ['date','end_date','start_time','end_time','pokemon_ids']
 */
    if ($parent_post_id <= 0) {
        return [];
    }

    global $wpdb;
    if (!function_exists('pokehub_get_table') || !function_exists('wp_timezone')) {
        return [];
    }

    $events_tbl = pokehub_get_table('special_events');
    $event_pokemon_tbl = pokehub_get_table('special_event_pokemon');
    if (!$events_tbl || !$event_pokemon_tbl) {
        return [];
    }

    list($scope_sql, $scope_args) = pokehub_spotlight_sql_parent_scope((int) $parent_post_id);
    $sql_rows = "SELECT id, start_ts, end_ts FROM {$events_tbl} WHERE {$scope_sql} ORDER BY start_ts ASC, id ASC";
    $rows = $wpdb->get_results(
        call_user_func_array([$wpdb, 'prepare'], array_merge([$sql_rows], $scope_args)),
        ARRAY_A
    );

    if (empty($rows) || !is_array($rows)) {
        // Fallback : si $parent_post_id correspond directement à l'ID d'un special event
        $single = $wpdb->get_row($wpdb->prepare(
            "SELECT id, start_ts, end_ts
             FROM {$events_tbl}
             WHERE id = %d",
            (int) $parent_post_id
        ), ARRAY_A);

        if (empty($single) || !is_array($single)) {
            return [];
        }

        $rows = [$single];
    }

    $tz = wp_timezone();

    $format_local = function(int $timestamp) use ($tz): array {
        if ($timestamp <= 0) return ['date' => '', 'time' => ''];
        try {
            $dt = new DateTime('@' . $timestamp);
            $dt->setTimezone($tz);
            return [
                'date' => (string) $dt->format('Y-m-d'),
                'time' => (string) $dt->format('H:i'),
            ];
        } catch (Exception $e) {
            return ['date' => '', 'time' => ''];
        }
    };

    $out = [];
    foreach ($rows as $r) {
        $event_id = (int) ($r['id'] ?? 0);
        $start_ts = (int) ($r['start_ts'] ?? 0);
        $end_ts = (int) ($r['end_ts'] ?? 0);
        if ($event_id <= 0 || $start_ts <= 0 || $end_ts <= 0) continue;

        $start_parts = $format_local($start_ts);
        $end_parts = $format_local($end_ts);
        if ($start_parts['date'] === '' || $start_parts['time'] === '' || $end_parts['time'] === '') {
            continue;
        }

        $pokemon_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pokemon_id, gender FROM {$event_pokemon_tbl} WHERE event_id = %d",
            $event_id
        ), ARRAY_A);
        $pokemon_ids = array_values(array_map('intval', wp_list_pluck((array) $pokemon_rows, 'pokemon_id')));
        $pokemon_ids = array_values(array_filter($pokemon_ids, function($pid) { return $pid > 0; }));
        if (empty($pokemon_ids)) continue;
        $pokemon_genders = [];
        foreach ((array) $pokemon_rows as $row) {
            $pid = isset($row['pokemon_id']) ? (int) $row['pokemon_id'] : 0;
            $gender = isset($row['gender']) ? sanitize_key((string) $row['gender']) : '';
            if ($pid > 0 && in_array($gender, ['male', 'female'], true)) {
                $pokemon_genders[(string) $pid] = $gender;
            }
        }

        $out[] = [
            'date' => (string) $start_parts['date'],
            'end_date' => (string) ($end_parts['date'] !== '' ? $end_parts['date'] : $start_parts['date']),
            'start_time' => (string) $start_parts['time'],
            'end_time' => (string) $end_parts['time'],
            'pokemon_ids' => $pokemon_ids,
            'pokemon_genders' => $pokemon_genders,
        ];
    }

    usort($out, function($a, $b) {
        $da = (string) ($a['date'] ?? '');
        $db = (string) ($b['date'] ?? '');
        if ($da === $db) {
            $sa = (string) ($a['start_time'] ?? '');
            $sb = (string) ($b['start_time'] ?? '');
            return $sa <=> $sb;
        }
        return $da <=> $db;
    });

    return $out;
}
