<?php
// modules/eggs/functions/eggs-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère tous les types d'œufs (nécessite module pokemon).
 *
 * @return array<object>
 */
function pokehub_get_egg_types() {
    if (!function_exists('pokehub_get_table')) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('pokemon_egg_types');
    if (!$table) {
        return [];
    }
    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY hatch_distance_km ASC, name_fr ASC");
}

/**
 * Récupère un type d'œuf par ID.
 *
 * @param int $id
 * @return object|null
 */
function pokehub_get_egg_type($id) {
    if (!function_exists('pokehub_get_table') || (int) $id <= 0) {
        return null;
    }
    global $wpdb;
    $table = pokehub_get_table('pokemon_egg_types');
    if (!$table) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id));
}

/**
 * Récupère l'icône d'un type d'œuf (URL).
 *
 * @param object $egg_type
 * @return string
 */
function pokehub_get_egg_type_icon_url($egg_type) {
    if (empty($egg_type->extra)) {
        return '';
    }
    $extra = json_decode($egg_type->extra, true);
    return is_array($extra) && isset($extra['image_url']) ? (string) $extra['image_url'] : '';
}

/**
 * Indique si le type d'œuf est "suivi d'exploration" (Adventure Sync).
 * Stocké dans extra['is_adventure_sync'].
 *
 * @param object $egg_type
 * @return bool
 */
function pokehub_egg_type_is_adventure_sync($egg_type) {
    if (empty($egg_type->extra)) {
        return false;
    }
    $extra = json_decode($egg_type->extra, true);
    return is_array($extra) && !empty($extra['is_adventure_sync']);
}

/**
 * Tous les œufs actifs agrégés, prêts pour l'affichage.
 * Tri : distance croissante, puis normaux avant suivi d'exploration, puis par rareté dans chaque section.
 *
 * @param int|null $timestamp Si null, time().
 * @return array [ ['egg_type' => object, 'pokemon' => array ], ... ]
 */
function pokehub_get_all_active_eggs_for_display($timestamp = null) {
    if (!function_exists('pokehub_content_get_all_eggs_aggregated_at') || !function_exists('pokehub_get_egg_type')) {
        return [];
    }
    if (!function_exists('pokehub_blocks_build_egg_pokemon_list') && defined('POKE_HUB_PATH')) {
        @include_once POKE_HUB_PATH . 'modules/blocks/functions/blocks-eggs-helpers.php';
    }
    if (!function_exists('pokehub_blocks_build_egg_pokemon_list')) {
        return [];
    }
    $by_type = pokehub_content_get_all_eggs_aggregated_at($timestamp);
    if (empty($by_type)) {
        return [];
    }
    $sections = [];
    foreach ($by_type as $egg_type_id => $list) {
        $egg_type = pokehub_get_egg_type($egg_type_id);
        if (!$egg_type || empty($list)) {
            continue;
        }
        $pokemon_items = pokehub_blocks_build_egg_pokemon_list($list);
        if (empty($pokemon_items)) {
            continue;
        }
        usort($pokemon_items, function ($a, $b) {
            $r = ($a['rarity'] ?? 1) - ($b['rarity'] ?? 1);
            if ($r !== 0) {
                return $r;
            }
            return ($a['pokemon_id'] ?? 0) - ($b['pokemon_id'] ?? 0);
        });
        $icon_url = function_exists('pokehub_get_egg_type_icon_url') ? pokehub_get_egg_type_icon_url($egg_type) : '';
        $sections[] = [
            'egg_type' => (object) [
                'id'               => (int) $egg_type->id,
                'name_fr'          => $egg_type->name_fr ?? '',
                'name_en'          => $egg_type->name_en ?? '',
                'slug'             => $egg_type->slug ?? '',
                'icon_url'         => $icon_url,
                'hatch_km'         => isset($egg_type->hatch_distance_km) ? (int) $egg_type->hatch_distance_km : 0,
                'is_adventure_sync' => function_exists('pokehub_egg_type_is_adventure_sync') && pokehub_egg_type_is_adventure_sync($egg_type),
            ],
            'pokemon' => $pokemon_items,
        ];
    }
    usort($sections, function ($a, $b) {
        $km_a = $a['egg_type']->hatch_km ?? 0;
        $km_b = $b['egg_type']->hatch_km ?? 0;
        if ($km_a !== $km_b) {
            return $km_a - $km_b;
        }
        $as_a = !empty($a['egg_type']->is_adventure_sync) ? 1 : 0;
        $as_b = !empty($b['egg_type']->is_adventure_sync) ? 1 : 0;
        return $as_a - $as_b;
    });
    return $sections;
}

/**
 * Rendu réutilisable d'une section "type d'œuf" (titre + liste de Pokémon).
 * Utilisé par le bloc œufs et par la page / eggs.
 *
 * @param object $egg_type   { id, name_fr, name_en, icon_url, hatch_km, is_adventure_sync? }
 * @param array  $pokemon_list Liste de [ display_name, image_url, rarity, cp_min, cp_max, is_shiny, ... ]
 * @return string HTML
 */
function pokehub_render_egg_type_section($egg_type, $pokemon_list) {
    if (empty($pokemon_list)) {
        return '';
    }
    $type_name  = !empty($egg_type->name_fr) ? $egg_type->name_fr : $egg_type->name_en;
    $type_label = $type_name . (isset($egg_type->hatch_km) && $egg_type->hatch_km > 0 ? ' (' . (int) $egg_type->hatch_km . ' km)' : '');
    if (!empty($egg_type->is_adventure_sync)) {
        $type_label .= ' – ' . __('Adventure Sync', 'poke-hub');
    }
    ob_start();
    ?>
    <div class="pokehub-eggs-type-section" data-egg-type="<?php echo esc_attr((string) $egg_type->id); ?>">
        <h3 class="pokehub-eggs-type-title">
            <?php if (!empty($egg_type->icon_url)) : ?>
                <img src="<?php echo esc_url($egg_type->icon_url); ?>" alt="" class="pokehub-eggs-type-icon" loading="lazy" />
            <?php endif; ?>
            <span><?php echo esc_html($type_label); ?></span>
        </h3>
        <ul class="pokehub-eggs-pokemon-list">
            <?php foreach ($pokemon_list as $p) : ?>
                <li class="pokehub-eggs-pokemon-item pokehub-eggs-rarity-<?php echo (int) ($p['rarity'] ?? 1); ?>"
                    <?php if (!empty($p['cp_min']) || !empty($p['cp_max'])) : ?>
                        title="<?php
                        $cp_parts = [];
                        if (!empty($p['cp_min'])) $cp_parts[] = __('CP min', 'poke-hub') . ': ' . (int) $p['cp_min'];
                        if (!empty($p['cp_max'])) $cp_parts[] = __('CP max', 'poke-hub') . ': ' . (int) $p['cp_max'];
                        echo esc_attr(implode(' | ', $cp_parts));
                        ?>"
                    <?php endif; ?>>
                    <?php if (!empty($p['image_url'])) : ?>
                        <span class="pokehub-eggs-pokemon-image-wrap">
                            <img src="<?php echo esc_url($p['image_url']); ?>" alt="<?php echo esc_attr($p['display_name'] ?? ''); ?>" class="pokehub-eggs-pokemon-image" loading="lazy" />
                        </span>
                    <?php endif; ?>
                    <span class="pokehub-eggs-pokemon-name"><?php echo esc_html($p['display_name'] ?? ''); ?></span>
                    <?php if (!empty($p['is_shiny'])) : ?>
                        <span class="pokehub-eggs-pokemon-shiny" title="<?php echo !empty($p['is_forced_shiny']) ? esc_attr__('Shiny forced', 'poke-hub') : esc_attr__('Shiny available', 'poke-hub'); ?>">✨</span>
                    <?php endif; ?>
                    <?php if (!empty($p['is_regional']) && empty($p['is_worldwide_override'])) : ?>
                        <span class="pokehub-eggs-pokemon-regional" title="<?php esc_attr_e('Regional', 'poke-hub'); ?>">🌍</span>
                    <?php endif; ?>
                    <?php if (!empty($p['is_worldwide_override'])) : ?>
                        <span class="pokehub-eggs-pokemon-worldwide" title="<?php esc_attr_e('Temporarily worldwide', 'poke-hub'); ?>">🌐</span>
                    <?php endif; ?>
                    <?php if (!empty($p['cp_min']) || !empty($p['cp_max'])) : ?>
                        <span class="pokehub-eggs-pokemon-cp">
                            <?php
                            $cp_text = [];
                            if (!empty($p['cp_min'])) $cp_text[] = (int) $p['cp_min'];
                            if (!empty($p['cp_max'])) $cp_text[] = (int) $p['cp_max'];
                            echo esc_html(implode('–', $cp_text) . ' PC');
                            ?>
                        </span>
                    <?php endif; ?>
                    <span class="pokehub-eggs-pokemon-rarity" aria-label="<?php echo esc_attr(sprintf(__('Rarity: %d egg(s)', 'poke-hub'), (int) ($p['rarity'] ?? 1))); ?>">
                        <?php for ($r = 1; $r <= 5; $r++) : ?>
                            <span class="pokehub-eggs-egg-dot <?php echo $r <= (int) ($p['rarity'] ?? 1) ? 'filled' : ''; ?>">🥚</span>
                        <?php endfor; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Récupère les pools d'œufs globaux (content_eggs avec source_type = 'global_pool').
 *
 * @param int|null $timestamp Si fourni, ne retourne que les pools actifs à cette date.
 * @return array<object>
 */
function pokehub_get_global_egg_pools($timestamp = null) {
    if (!function_exists('pokehub_content_get_eggs_active_at')) {
        return [];
    }
    return pokehub_content_get_eggs_active_at($timestamp, 'global_pool');
}

/**
 * Récupère les Pokémon d'un pool (content_egg_id = id de la ligne content_eggs).
 *
 * @param int $pool_id content_eggs.id (ex. pool global_pool)
 * @param int|null $egg_type_id Si null, retourne tous les types groupés.
 * @return array
 */
function pokehub_get_global_egg_pool_pokemon($pool_id, $egg_type_id = null) {
    if (!function_exists('pokehub_get_table') || (int) $pool_id <= 0) {
        return [];
    }
    global $wpdb;
    $table = pokehub_get_table('content_egg_pokemon');
    if (!$table) {
        return [];
    }
    $pool_id = (int) $pool_id;
    if ($egg_type_id !== null) {
        $egg_type_id = (int) $egg_type_id;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE content_egg_id = %d AND egg_type_id = %d ORDER BY sort_order ASC, rarity ASC, id ASC",
            $pool_id,
            $egg_type_id
        ));
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE content_egg_id = %d ORDER BY egg_type_id ASC, sort_order ASC, rarity ASC, id ASC",
        $pool_id
    ));
    $by_type = [];
    foreach ($rows as $row) {
        $by_type[(int) $row->egg_type_id][] = $row;
    }
    return $by_type;
}

/**
 * Récupère les œufs définis sur un post (tables de contenu).
 *
 * @param int $post_id
 * @return array
 */
function pokehub_get_post_eggs($post_id) {
    if (function_exists('pokehub_content_get_eggs')) {
        return pokehub_content_get_eggs('post', (int) $post_id);
    }
    return [];
}
