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

    $tables_with_dates = [
        'content_eggs',
        'content_quests',
        'content_habitats',
        'content_special_research',
        'content_collection_challenges',
        'content_bonus',
        'content_wild_pokemon',
        'content_new_pokemon',
        'content_raids',
    ];

    foreach ($tables_with_dates as $key) {
        if (!function_exists('pokehub_get_table')) {
            continue;
        }
        $table = pokehub_get_table($key);
        if (empty($table)) {
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
            $wpdb->insert(
                $pokemon_tbl,
                [
                    'content_egg_id'         => $content_egg_id,
                    'egg_type_id'             => $et_id,
                    'pokemon_id'              => $pid,
                    'rarity'                  => isset($p['rarity']) ? max(1, min(5, (int) $p['rarity'])) : 1,
                    'is_worldwide_override'   => !empty($p['is_worldwide_override']) ? 1 : 0,
                    'is_forced_shiny'         => !empty($p['is_forced_shiny']) ? 1 : 0,
                    'sort_order'              => $sort++,
                ],
                ['%d', '%d', '%d', '%d', '%d', '%d', '%d']
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
            ];
            if (!isset($by_type[$et_id][$key]) || $entry['rarity'] > $by_type[$et_id][$key]['rarity']) {
                $by_type[$et_id][$key] = $entry;
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
 * Récupère la ligne content_quests pour une source.
 */
function pokehub_content_get_quests_row($source_type, $source_id) {
    global $wpdb;
    $table = pokehub_get_table('content_quests');
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
            if (isset($reward['pokemon_ids'])) {
                $raw = $reward['pokemon_ids'];
                if (is_array($raw)) {
                    $norm['pokemon_ids'] = array_values(array_map('intval', array_filter($raw, function ($id) {
                        return $id !== '' && $id !== null && is_numeric($id) && (int) $id > 0;
                    })));
                } elseif (is_string($raw)) {
                    // Chaîne stockée en base (ex. "1,87" ou "1") : convertir en tableau d'entiers
                    $norm['pokemon_ids'] = array_values(array_filter(array_map(function ($id) {
                        return (int) trim($id);
                    }, explode(',', $raw)), function ($id) {
                        return $id > 0;
                    }));
                } else {
                    // Objet (stdClass) renvoyé par json_decode sans true, ou autre : convertir en tableau
                    $norm['pokemon_ids'] = array_values(array_map('intval', array_filter((array) $raw, function ($id) {
                        return $id !== '' && $id !== null && is_numeric($id) && (int) $id > 0;
                    })));
                }
            } elseif (isset($reward['pokemon_id']) && (is_numeric($reward['pokemon_id']) || $reward['pokemon_id'] !== '')) {
                $id = (int) $reward['pokemon_id'];
                $norm['pokemon_ids'] = $id > 0 ? [$id] : [];
            } else {
                $norm['pokemon_ids'] = [];
            }
            $norm['force_shiny']   = !empty($reward['force_shiny']);
            $norm['pokemon_genders'] = [];
            if (isset($reward['pokemon_genders']) && is_array($reward['pokemon_genders'])) {
                foreach ($reward['pokemon_genders'] as $pid => $gender) {
                    $pid = (int) $pid;
                    if ($pid > 0 && in_array($gender, ['male', 'female'], true)) {
                        $norm['pokemon_genders'][$pid] = $gender;
                    }
                }
            }
        } elseif ($type === 'candy' || $type === 'mega_energy') {
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
        $wpdb->insert($quests_tbl, [
            'source_type' => $source_type,
            'source_id'   => $source_id,
            'start_ts'    => $dates['start_ts'],
            'end_ts'      => $dates['end_ts'],
        ], ['%s', '%d', '%d', '%d']);
        $content_quest_id = (int) $wpdb->insert_id;
    }

    $sort = 0;
    foreach ($quests as $q) {
        $task    = isset($q['task']) ? sanitize_text_field($q['task']) : '';
        $rewards = isset($q['rewards']) && is_array($q['rewards']) ? $q['rewards'] : [];
        if ($task === '' && empty($rewards)) {
            continue;
        }
        $quest_group_id = isset($q['quest_group_id']) ? max(0, (int) $q['quest_group_id']) : 0;
        $wpdb->insert($lines_tbl, [
            'content_quest_id' => $content_quest_id,
            'quest_group_id'  => $quest_group_id > 0 ? $quest_group_id : null,
            'task'            => $task,
            'rewards'         => wp_json_encode($rewards),
            'sort_order'      => $sort++,
        ], ['%d', '%d', '%s', '%s', '%d']);
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
        $has_rewards = !empty($quest['rewards']) && is_array($quest['rewards']) && count($quest['rewards']) > 0;
        $has_task = !empty($quest['task']);
        if (!$has_task && !$has_rewards) {
            continue;
        }
        $cleaned_quest = [
            'task'           => $has_task ? sanitize_text_field($quest['task']) : '',
            'rewards'        => [],
            'quest_group_id' => isset($quest['quest_group_id']) ? max(0, (int) $quest['quest_group_id']) : 0,
        ];
        if (isset($quest['rewards']) && is_array($quest['rewards'])) {
            foreach ($quest['rewards'] as $reward) {
                $cleaned_reward = ['type' => sanitize_key($reward['type'] ?? 'pokemon')];
                if ($cleaned_reward['type'] === 'pokemon') {
                    if (isset($reward['pokemon_ids']) && is_array($reward['pokemon_ids'])) {
                        $cleaned_reward['pokemon_ids'] = array_map('intval', array_filter($reward['pokemon_ids'], function ($id) {
                            return !empty($id) && is_numeric($id);
                        }));
                    } elseif (isset($reward['pokemon_id'])) {
                        $pid = (int) $reward['pokemon_id'];
                        $cleaned_reward['pokemon_ids'] = $pid > 0 ? [$pid] : [];
                    } else {
                        $cleaned_reward['pokemon_ids'] = [];
                    }
                    $cleaned_reward['force_shiny'] = !empty($reward['force_shiny']);
                    $pokemon_genders = [];
                    if (isset($reward['pokemon_genders']) && is_array($reward['pokemon_genders'])) {
                        foreach ($reward['pokemon_genders'] as $pokemon_id => $gender) {
                            $pokemon_id = (int) $pokemon_id;
                            if ($pokemon_id > 0 && in_array($gender, ['male', 'female'], true)) {
                                $pokemon_genders[$pokemon_id] = sanitize_text_field($gender);
                            }
                        }
                    }
                    $cleaned_reward['pokemon_genders'] = $pokemon_genders;
                } elseif ($cleaned_reward['type'] === 'candy' || $cleaned_reward['type'] === 'mega_energy') {
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
    foreach (array_map('intval', array_filter($pokemon_ids)) as $pid) {
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
        if (defined('WP_DEBUG') && WP_DEBUG && $wpdb->last_error) {
            error_log('[Poke HUB] content_habitats insert failed: ' . $wpdb->last_error);
        }
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
        $pokemon_data = wp_json_encode([
            'pokemon_ids'      => isset($h['pokemon_ids']) ? array_map('intval', (array) $h['pokemon_ids']) : [],
            'forced_shiny_ids' => isset($h['forced_shiny_ids']) ? array_map('intval', (array) $h['forced_shiny_ids']) : [],
            'pokemon_genders'  => isset($h['pokemon_genders']) && is_array($h['pokemon_genders']) ? $h['pokemon_genders'] : [],
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
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[PokeHub SR LOG] get_special_research_row: table=' . ($table ?: 'NULL') . ' source_type=' . $source_type . ' source_id=' . $source_id);
    }
    $row = $table ? $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE source_type = %s AND source_id = %d LIMIT 1",
        (string) $source_type,
        (int) $source_id
    )) : null;
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[PokeHub SR LOG] get_special_research_row result: ' . ($row ? 'id=' . $row->id . ' research_type=' . ($row->research_type ?? '') : 'NULL'));
    }
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
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('[PokeHub SR LOG] get_special_research: no row, returning empty steps');
        }
        return ['research_type' => 'special', 'steps' => []];
    }
    global $wpdb;
    $table = pokehub_get_table('content_special_research_steps');
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[PokeHub SR LOG] get_special_research: steps_table=' . ($table ?: 'NULL') . ' content_research_id=' . $row->id);
    }
    if (!$table) {
        return ['research_type' => (string) $row->research_type, 'steps' => []];
    }
    $steps = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE content_research_id = %d ORDER BY sort_order ASC, id ASC",
        (int) $row->id
    ));
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[PokeHub SR LOG] get_special_research: steps count=' . (is_array($steps) ? count($steps) : 0));
    }
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
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log') && count($out) === 0) {
            error_log('[PokeHub SR LOG] get_special_research first step_data keys: ' . (is_array($step_data) ? implode(',', array_keys($step_data)) : 'not-array'));
        }
        $out[] = pokehub_content_normalize_special_research_item($step_data);
    }
    // Debug: récompenses Pokémon (étapes + quêtes) pour diagnostic
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        $summary = [];
        $collect = function (array $rewards, string $prefix) use (&$summary) {
            foreach ($rewards as $ri => $r) {
                if (isset($r['type']) && $r['type'] === 'pokemon') {
                    $summary[] = $prefix . $ri . '_pokemon_ids=' . wp_json_encode(isset($r['pokemon_ids']) ? $r['pokemon_ids'] : 'missing');
                }
            }
        };
        foreach ($out as $i => $item) {
            foreach (['common_initial_steps', 'common_final_steps'] as $key) {
                if (empty($item[ $key ]) || ! is_array($item[ $key ])) {
                    continue;
                }
                foreach ($item[ $key ] as $si => $step) {
                    $rewards = isset($step['rewards']) && is_array($step['rewards']) ? $step['rewards'] : [];
                    $collect($rewards, "item{$i}_{$key}_{$si}_reward");
                    if (isset($step['quests']) && is_array($step['quests'])) {
                        foreach ($step['quests'] as $qi => $quest) {
                            $qrewards = isset($quest['rewards']) && is_array($quest['rewards']) ? $quest['rewards'] : [];
                            $collect($qrewards, "item{$i}_{$key}_{$si}_quest{$qi}_reward");
                        }
                    }
                }
            }
            if (isset($item['paths']) && is_array($item['paths'])) {
                foreach ($item['paths'] as $pi => $path) {
                    $psteps = isset($path['steps']) && is_array($path['steps']) ? $path['steps'] : [];
                    foreach ($psteps as $si => $step) {
                        $rewards = isset($step['rewards']) && is_array($step['rewards']) ? $step['rewards'] : [];
                        $collect($rewards, "item{$i}_path{$pi}_step{$si}_reward");
                        if (isset($step['quests']) && is_array($step['quests'])) {
                            foreach ($step['quests'] as $qi => $quest) {
                                $qrewards = isset($quest['rewards']) && is_array($quest['rewards']) ? $quest['rewards'] : [];
                                $collect($qrewards, "item{$i}_path{$pi}_step{$si}_quest{$qi}_reward");
                            }
                        }
                    }
                }
            }
        }
        error_log('[PokeHub Special Research] get_special_research pokemon rewards: ' . implode(' ', $summary));
    }
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[PokeHub SR LOG] get_special_research return: research_type=' . $row->research_type . ' steps_count=' . count($out));
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
