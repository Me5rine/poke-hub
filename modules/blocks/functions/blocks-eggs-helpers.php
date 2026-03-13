<?php
// modules/blocks/functions/blocks-eggs-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère les données d'œufs à afficher pour le bloc.
 * source = 'post' → meta du post (article/événement)
 * source = 'global' → pool actif à la date du jour (ou pool_id si fourni)
 *
 * @param int    $post_id  ID du post (contexte article).
 * @param string $source   'post' ou 'global'.
 * @param int    $pool_id  Optionnel. Si source = 'global' et > 0, utilise ce pool.
 * @return array Liste de [ 'egg_type' => object, 'pokemon' => array ]
 */
function pokehub_blocks_get_eggs_for_display($post_id, $source = 'post', $pool_id = 0) {
    $result = [];

    if ($source === 'post' && $post_id > 0 && function_exists('pokehub_get_post_eggs')) {
        $saved = pokehub_get_post_eggs($post_id);
        if (empty($saved)) {
            return [];
        }
        foreach ($saved as $group) {
            $et_id = isset($group['egg_type_id']) ? (int) $group['egg_type_id'] : 0;
            if ($et_id <= 0 || !function_exists('pokehub_get_egg_type')) {
                continue;
            }
            $egg_type = pokehub_get_egg_type($et_id);
            if (!$egg_type) {
                continue;
            }
            $icon_url = function_exists('pokehub_get_egg_type_icon_url') ? pokehub_get_egg_type_icon_url($egg_type) : '';
            $list = isset($group['pokemon']) && is_array($group['pokemon']) ? $group['pokemon'] : [];
            $pokemon_items = pokehub_blocks_build_egg_pokemon_list($list);
            if (!empty($pokemon_items)) {
                $result[] = [
                    'egg_type' => (object) [
                        'id'        => (int) $egg_type->id,
                        'name_fr'   => $egg_type->name_fr ?? '',
                        'name_en'   => $egg_type->name_en ?? '',
                        'slug'      => $egg_type->slug ?? '',
                        'icon_url'  => $icon_url,
                        'hatch_km'  => isset($egg_type->hatch_distance_km) ? (int) $egg_type->hatch_distance_km : 0,
                    ],
                    'pokemon' => $pokemon_items,
                ];
            }
        }
        return $result;
    }

    if ($source === 'global' && function_exists('pokehub_get_global_egg_pools') && function_exists('pokehub_get_global_egg_pool_pokemon')) {
        $pools = [];
        if ($pool_id > 0) {
            $pools_table = function_exists('pokehub_get_table') ? pokehub_get_table('global_egg_pools') : '';
            if ($pools_table) {
                global $wpdb;
                $pool = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pools_table} WHERE id = %d", $pool_id));
                if ($pool) {
                    $pools = [$pool];
                }
            }
        } else {
            $pools = pokehub_get_global_egg_pools(time());
        }
        if (empty($pools)) {
            return [];
        }
        $by_egg_type = [];
        foreach ($pools as $pool) {
            $rows = pokehub_get_global_egg_pool_pokemon((int) $pool->id, null);
            foreach ($rows as $egg_type_id => $type_rows) {
                if (!isset($by_egg_type[$egg_type_id])) {
                    $by_egg_type[$egg_type_id] = [];
                }
                foreach ($type_rows as $row) {
                    $by_egg_type[$egg_type_id][] = [
                        'pokemon_id'             => (int) $row->pokemon_id,
                        'rarity'                 => isset($row->rarity) ? max(1, min(5, (int) $row->rarity)) : 1,
                        'is_worldwide_override'  => !empty($row->is_worldwide_override),
                        'is_forced_shiny'        => !empty($row->is_forced_shiny),
                    ];
                }
            }
        }
        foreach ($by_egg_type as $egg_type_id => $list) {
            $egg_type = function_exists('pokehub_get_egg_type') ? pokehub_get_egg_type($egg_type_id) : null;
            if (!$egg_type) {
                continue;
            }
            $icon_url = function_exists('pokehub_get_egg_type_icon_url') ? pokehub_get_egg_type_icon_url($egg_type) : '';
            $pokemon_items = pokehub_blocks_build_egg_pokemon_list($list);
            if (!empty($pokemon_items)) {
                $result[] = [
                    'egg_type' => (object) [
                        'id'       => (int) $egg_type->id,
                        'name_fr'  => $egg_type->name_fr ?? '',
                        'name_en'  => $egg_type->name_en ?? '',
                        'slug'     => $egg_type->slug ?? '',
                        'icon_url' => $icon_url,
                        'hatch_km' => isset($egg_type->hatch_distance_km) ? (int) $egg_type->hatch_distance_km : 0,
                    ],
                    'pokemon' => $pokemon_items,
                ];
            }
        }
        return $result;
    }

    return $result;
}

/**
 * Construit la liste des Pokémon pour l'affichage (noms, images, shiny, etc.).
 * Shiny et régional sont lus depuis la fiche Pokémon ; CP depuis la fiche (niveau 20) si non renseignés.
 * Seuls is_forced_shiny et is_worldwide_override sont des surcharges stockées.
 *
 * @param array $list Liste de { pokemon_id, rarity, is_worldwide_override?, is_forced_shiny?, cp_min?, cp_max? }
 * @return array
 */
function pokehub_blocks_build_egg_pokemon_list($list) {
    $out = [];
    if (!function_exists('pokehub_get_pokemon_data_by_id')) {
        return $out;
    }
    foreach ($list as $row) {
        $pokemon_id = isset($row['pokemon_id']) ? (int) $row['pokemon_id'] : (isset($row->pokemon_id) ? (int) $row->pokemon_id : 0);
        if ($pokemon_id <= 0) {
            continue;
        }
        $data = pokehub_get_pokemon_data_by_id($pokemon_id);
        if (!$data) {
            continue;
        }
        $name_fr = $data['name_fr'] ?? '';
        $name_en = $data['name_en'] ?? '';
        $display_name = $name_fr !== '' ? $name_fr : $name_en;

        // Shiny : depuis la fiche Pokémon + override "shiny forcé"
        $is_forced_shiny = !empty($row['is_forced_shiny']) || (!empty($row->is_forced_shiny));
        $image_url = '';
        $is_shiny = false;
        if (function_exists('poke_hub_pokemon_get_shiny_info')) {
            $shiny_info = poke_hub_pokemon_get_shiny_info($pokemon_id, [
                'forced_shiny_ids' => $is_forced_shiny ? [$pokemon_id] : [],
            ]);
            $image_url = $shiny_info['image_url'] ?? '';
            $is_shiny = !empty($shiny_info['should_show_shiny']);
        } else {
            $image_url = $data['image_url'] ?? '';
        }

        // Régional : depuis la fiche Pokémon ; "worldwide override" = temporairement mondial
        $is_worldwide = !empty($row['is_worldwide_override']) || (!empty($row->is_worldwide_override));
        $is_regional = false;
        if (!$is_worldwide && function_exists('poke_hub_pokemon_get_regional_info')) {
            $reg_info = poke_hub_pokemon_get_regional_info($pokemon_id);
            $is_regional = !empty($reg_info['is_regional']);
        }

        // CP : depuis la fiche Pokémon (niveau 20 = éclosion œuf) si non renseignés
        $raw_cp_min = isset($row['cp_min']) ? $row['cp_min'] : (isset($row->cp_min) ? $row->cp_min : null);
        $raw_cp_max = isset($row['cp_max']) ? $row['cp_max'] : (isset($row->cp_max) ? $row->cp_max : null);
        $cp_min = ($raw_cp_min !== null && $raw_cp_min !== '') ? (int) $raw_cp_min : null;
        $cp_max = ($raw_cp_max !== null && $raw_cp_max !== '') ? (int) $raw_cp_max : null;
        if (($cp_min === null && $cp_max === null) && function_exists('pokehub_get_pokemon_cp_for_level')) {
            $cp_level = pokehub_get_pokemon_cp_for_level($pokemon_id, 20);
            if ($cp_level) {
                $cp_min = $cp_level['min_cp'] ?? null;
                $cp_max = $cp_level['max_cp'] ?? null;
            }
        }

        $rarity = isset($row['rarity']) ? max(1, min(5, (int) $row['rarity'])) : (isset($row->rarity) ? max(1, min(5, (int) $row->rarity)) : 1);

        $out[] = [
            'pokemon_id'             => $pokemon_id,
            'display_name'           => $display_name,
            'image_url'              => $image_url,
            'is_shiny'               => $is_shiny,
            'is_forced_shiny'        => $is_forced_shiny,
            'is_regional'             => $is_regional,
            'is_worldwide_override'  => $is_worldwide,
            'cp_min'                 => $cp_min,
            'cp_max'                 => $cp_max,
            'rarity'                 => $rarity,
        ];
    }
    return $out;
}
