<?php
if (!defined('ABSPATH')) {
    exit;
}

$post_id = 0;
if (isset($block) && is_object($block) && !empty($block->context['postId'])) {
    $post_id = (int) $block->context['postId'];
}
if (!$post_id) {
    $post_id = (int) get_the_ID();
}
if (!$post_id) {
    $post_id = (int) get_queried_object_id();
}
if (!$post_id && !empty($GLOBALS['post']->ID)) {
    $post_id = (int) $GLOBALS['post']->ID;
}

$pokemon_id = isset($attributes['pokemonId']) ? (int) $attributes['pokemonId'] : 0;
$attack_id = isset($attributes['specialAttackId']) ? (int) $attributes['specialAttackId'] : 0;
$evolution_start = isset($attributes['evolutionStart']) ? trim((string) $attributes['evolutionStart']) : '';
$evolution_end = isset($attributes['evolutionEnd']) ? trim((string) $attributes['evolutionEnd']) : '';
$entries = [];
if (!empty($attributes['entries']) && is_array($attributes['entries'])) {
    foreach ((array) $attributes['entries'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $pid = isset($entry['pokemonId']) ? (int) $entry['pokemonId'] : 0;
        $attack_ids = [];
        if (!empty($entry['specialAttackIds']) && is_array($entry['specialAttackIds'])) {
            foreach ((array) $entry['specialAttackIds'] as $aid_raw) {
                $aid_val = (int) $aid_raw;
                if ($aid_val > 0) {
                    $attack_ids[] = $aid_val;
                }
            }
        }
        $entries[] = [
            'pokemon_id' => $pid,
            'attack_ids' => array_values(array_unique($attack_ids)),
        ];
    }
}
if (empty($entries)) {
    $entries[] = [
        'pokemon_id' => $pokemon_id,
        'attack_ids' => $attack_id > 0 ? [$attack_id] : [],
    ];
}

// Repli : table content_community_day (sync à l’enregistrement), si les attributs du bloc sont vides.
if ($pokemon_id <= 0 && $post_id && function_exists('pokehub_content_get_community_day')) {
    $cd = pokehub_content_get_community_day('post', $post_id);
    if (!empty($cd['pokemon_id']) || !empty($cd['blocks'][0]['entries'])) {
        $pokemon_id      = (int) $cd['pokemon_id'];
        $attack_id       = $attack_id <= 0 && !empty($cd['special_attack_id']) ? (int) $cd['special_attack_id'] : $attack_id;
        $evolution_start = $evolution_start === '' && $cd['evolution_start'] !== '' ? (string) $cd['evolution_start'] : $evolution_start;
        $evolution_end   = $evolution_end === '' && $cd['evolution_end'] !== '' ? (string) $cd['evolution_end'] : $evolution_end;
        if (empty($attributes['entries']) && !empty($cd['blocks']) && is_array($cd['blocks'])) {
            $entry_from_blocks = [];
            foreach ((array) $cd['blocks'] as $block_row) {
                if (!empty($block_row['entries']) && is_array($block_row['entries'])) {
                    foreach ($block_row['entries'] as $entry_row) {
                        if (!is_array($entry_row)) {
                            continue;
                        }
                        $entry_from_blocks[] = [
                            'pokemon_id' => isset($entry_row['pokemon_id']) ? (int) $entry_row['pokemon_id'] : 0,
                            'attack_ids' => !empty($entry_row['special_attack_ids']) && is_array($entry_row['special_attack_ids']) ? array_values(array_map('intval', $entry_row['special_attack_ids'])) : [],
                        ];
                    }
                }
            }
            if (!empty($entry_from_blocks)) {
                $entries = $entry_from_blocks;
            } elseif ($pokemon_id > 0) {
                $entries = [[
                    'pokemon_id' => $pokemon_id,
                    'attack_ids' => $attack_id > 0 ? [$attack_id] : [],
                ]];
            }
        }
    }
}

$evolution_start_display = str_replace('T', ' ', $evolution_start);
$evolution_end_display = str_replace('T', ' ', $evolution_end);

if ((!$entries || !is_array($entries)) || !function_exists('pokehub_get_table') || !function_exists('pokehub_get_pokemon_data_by_id')) {
    return '';
}

global $wpdb;
$use_remote = function_exists('pokehub_pokemon_uses_remote_dataset') && pokehub_pokemon_uses_remote_dataset();
$pokemon_table = $use_remote ? pokehub_get_table('remote_pokemon') : pokehub_get_table('pokemon');
$evolutions_table = $use_remote ? pokehub_get_table('remote_pokemon_evolutions') : pokehub_get_table('pokemon_evolutions');
$form_variants_table = $use_remote ? pokehub_get_table('remote_pokemon_form_variants') : pokehub_get_table('pokemon_form_variants');
$types_table = $use_remote ? pokehub_get_table('remote_pokemon_types') : pokehub_get_table('pokemon_types');
$type_links_table = $use_remote ? pokehub_get_table('remote_pokemon_type_links') : pokehub_get_table('pokemon_type_links');
$links_table = $use_remote ? pokehub_get_table('remote_pokemon_attack_links') : pokehub_get_table('pokemon_attack_links');
$attacks_table = $use_remote ? pokehub_get_table('remote_attacks') : pokehub_get_table('attacks');
$attack_stats_table = $use_remote ? pokehub_get_table('remote_attack_stats') : pokehub_get_table('attack_stats');

if (!$pokemon_table || !$evolutions_table || !$types_table || !$type_links_table) {
    return '';
}

if (!function_exists('pokehub_cd_collect_family_ids')) {
    function pokehub_cd_collect_family_ids(int $start_id, string $pokemon_table, string $evolutions_table): array {
        global $wpdb;
        $queue = [$start_id];
        $seen = [];
        while (!empty($queue)) {
            $current = (int) array_shift($queue);
            if ($current <= 0 || isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            $parents = $wpdb->get_col($wpdb->prepare("SELECT base_pokemon_id FROM {$evolutions_table} WHERE target_pokemon_id = %d", $current));
            $children = $wpdb->get_col($wpdb->prepare("SELECT target_pokemon_id FROM {$evolutions_table} WHERE base_pokemon_id = %d", $current));
            foreach (array_merge((array) $parents, (array) $children) as $id) {
                $id = (int) $id;
                if ($id > 0 && !isset($seen[$id])) {
                    $queue[] = $id;
                }
            }
        }

        $ids = array_keys($seen);
        if (empty($ids)) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        $fam_sql = function_exists('pokehub_pokemon_sql_exclude_family_placeholder_slug_expr')
            ? pokehub_pokemon_sql_exclude_family_placeholder_slug_expr('slug')
            : "( LENGTH(TRIM(COALESCE(slug, ''))) < 7 OR RIGHT(LOWER(TRIM(COALESCE(slug, ''))), 7) <> '-family' )";
        $ordered = $wpdb->get_col("SELECT id FROM {$pokemon_table} WHERE id IN ({$in}) AND {$fam_sql} ORDER BY dex_number ASC, id ASC");
        return array_map('intval', (array) $ordered);
    }
}

if (!function_exists('pokehub_cd_best_pvp_ivs')) {
    function pokehub_cd_best_pvp_ivs(int $base_atk, int $base_def, int $base_sta, int $cp_cap): array {
        $cpm = [
            1 => 0.094, 1.5 => 0.135137432, 2 => 0.16639787, 2.5 => 0.192650919, 3 => 0.21573247,
            3.5 => 0.236572661, 4 => 0.25572005, 4.5 => 0.273530381, 5 => 0.29024988, 5.5 => 0.306057377,
            6 => 0.3210876, 6.5 => 0.335445036, 7 => 0.34921268, 7.5 => 0.362457751, 8 => 0.37523559,
            8.5 => 0.387592406, 9 => 0.39956728, 9.5 => 0.411193551, 10 => 0.42250001, 10.5 => 0.432926419,
            11 => 0.44310755, 11.5 => 0.4530599578, 12 => 0.46279839, 12.5 => 0.472336083, 13 => 0.48168495,
            13.5 => 0.4908558, 14 => 0.49985844, 14.5 => 0.508701765, 15 => 0.51739395, 15.5 => 0.525942511,
            16 => 0.53435433, 16.5 => 0.542635767, 17 => 0.55079269, 17.5 => 0.558830576, 18 => 0.56675452,
            18.5 => 0.574569153, 19 => 0.58227891, 19.5 => 0.589887917, 20 => 0.5974, 20.5 => 0.604818814,
            21 => 0.61215729, 21.5 => 0.619399365, 22 => 0.62656713, 22.5 => 0.633644533, 23 => 0.64065295,
            23.5 => 0.647576426, 24 => 0.65443563, 24.5 => 0.661214806, 25 => 0.667934, 25.5 => 0.674577537,
            26 => 0.68116492, 26.5 => 0.687680648, 27 => 0.69414365, 27.5 => 0.700538673, 28 => 0.70688421,
            28.5 => 0.713164996, 29 => 0.71939909, 29.5 => 0.725571552, 30 => 0.7317, 30.5 => 0.734741009,
            31 => 0.73776948, 31.5 => 0.740785574, 32 => 0.74378943, 32.5 => 0.746781211, 33 => 0.74976104,
            33.5 => 0.752729087, 34 => 0.75568551, 34.5 => 0.758630378, 35 => 0.76156384, 35.5 => 0.764486065,
            36 => 0.76739717, 36.5 => 0.770297266, 37 => 0.7731865, 37.5 => 0.776064962, 38 => 0.77893275,
            38.5 => 0.781790055, 39 => 0.78463697, 39.5 => 0.787473578, 40 => 0.7903, 40.5 => 0.79280395,
            41 => 0.79530001, 41.5 => 0.797800015, 42 => 0.8003, 42.5 => 0.802799995, 43 => 0.8053,
            43.5 => 0.8078, 44 => 0.81029999, 44.5 => 0.812799985, 45 => 0.81529999, 45.5 => 0.81779999,
            46 => 0.82029999, 46.5 => 0.82279999, 47 => 0.82529999, 47.5 => 0.82779999, 48 => 0.83029999,
            48.5 => 0.83279999, 49 => 0.83529999, 49.5 => 0.83779999, 50 => 0.84029999, 50.5 => 0.84279999,
            51 => 0.84529999,
        ];

        $best = ['iv_atk' => 0, 'iv_def' => 0, 'iv_sta' => 0, 'level' => 1, 'cp' => 10, 'stat_product' => -1];
        foreach ($cpm as $level => $mult) {
            for ($iva = 0; $iva <= 15; $iva++) {
                for ($ivd = 0; $ivd <= 15; $ivd++) {
                    for ($ivs = 0; $ivs <= 15; $ivs++) {
                        $atk = ($base_atk + $iva) * $mult;
                        $def = ($base_def + $ivd) * $mult;
                        $sta = floor(($base_sta + $ivs) * $mult);
                        $cp = (int) floor((($base_atk + $iva) * sqrt($base_def + $ivd) * sqrt($base_sta + $ivs) * $mult * $mult) / 10);
                        if ($cp < 10) {
                            $cp = 10;
                        }
                        if ($cp > $cp_cap) {
                            continue;
                        }
                        $sp = $atk * $def * $sta;
                        if ($sp > $best['stat_product']) {
                            $best = ['iv_atk' => $iva, 'iv_def' => $ivd, 'iv_sta' => $ivs, 'level' => $level, 'cp' => $cp, 'stat_product' => $sp];
                        }
                    }
                }
            }
        }
        return $best;
    }
}

$primary_pokemon_id = 0;
foreach ($entries as $entry) {
    if (!empty($entry['pokemon_id'])) {
        $primary_pokemon_id = (int) $entry['pokemon_id'];
        break;
    }
}
if ($primary_pokemon_id <= 0) {
    return '';
}

$family_ids = [];
foreach ($entries as $entry) {
    $pid = isset($entry['pokemon_id']) ? (int) $entry['pokemon_id'] : 0;
    if ($pid <= 0) {
        continue;
    }
    $ids = pokehub_cd_collect_family_ids($pid, $pokemon_table, $evolutions_table);
    if (empty($ids)) {
        $ids = [$pid];
    }
    foreach ($ids as $fid) {
        $family_ids[(int) $fid] = true;
    }
}
$family_ids = array_values(array_map('intval', array_keys($family_ids)));

$family = [];
foreach ($family_ids as $fid) {
    $row = pokehub_get_pokemon_data_by_id((int) $fid);
    if (!$row) {
        continue;
    }
    $img = function_exists('poke_hub_pokemon_get_image_sources') ? poke_hub_pokemon_get_image_sources((object) $row, ['shiny' => true]) : ['primary' => '', 'fallback' => ''];
    $family[] = ['id' => (int) $row['id'], 'name' => (string) ($row['name'] ?? ''), 'img' => (string) ($img['primary'] ?: $img['fallback'])];
}

$attack_type_links_table = $use_remote ? pokehub_get_table('remote_attack_type_links') : pokehub_get_table('attack_type_links');
$attack_details = [];
foreach ($entries as $entry) {
    $pid = isset($entry['pokemon_id']) ? (int) $entry['pokemon_id'] : 0;
    if ($pid <= 0) {
        continue;
    }
    $entry_family_ids = pokehub_cd_collect_family_ids($pid, $pokemon_table, $evolutions_table);
    if (empty($entry_family_ids)) {
        $entry_family_ids = [$pid];
    }
    $entry_family_in = implode(',', array_map('intval', $entry_family_ids));

    $attack_ids_for_entry = [];
    if (!empty($entry['attack_ids']) && is_array($entry['attack_ids'])) {
        foreach ((array) $entry['attack_ids'] as $entry_attack_id) {
            $entry_attack_id = (int) $entry_attack_id;
            if ($entry_attack_id > 0) {
                $attack_ids_for_entry[] = $entry_attack_id;
            }
        }
    }
    if (empty($attack_ids_for_entry) && function_exists('pokehub_get_pokemon_special_attacks')) {
        $fallback_attacks = pokehub_get_pokemon_special_attacks($pid, true);
        foreach ((array) $fallback_attacks as $fallback_attack) {
            $fallback_id = isset($fallback_attack['id']) ? (int) $fallback_attack['id'] : 0;
            if ($fallback_id > 0) {
                $attack_ids_for_entry[] = $fallback_id;
            }
        }
        if (!empty($attack_ids_for_entry)) {
            $attack_ids_for_entry = [(int) $attack_ids_for_entry[0]];
        }
    }
    $attack_ids_for_entry = array_values(array_unique($attack_ids_for_entry));
    if (empty($attack_ids_for_entry)) {
        continue;
    }

    $pokemon_label = '';
    $pokemon_row = pokehub_get_pokemon_data_by_id($pid);
    if ($pokemon_row) {
        $pokemon_label = (string) ($pokemon_row['name'] ?? '');
    }

    foreach ($attack_ids_for_entry as $attack_id_item) {
        $special_attack = null;
        if ($attacks_table) {
            $special_attack = $wpdb->get_row($wpdb->prepare("SELECT id, slug, name_fr, name_en, category FROM {$attacks_table} WHERE id = %d LIMIT 1", $attack_id_item), ARRAY_A);
        }
        if (!$special_attack) {
            continue;
        }
        $attack_pve = null;
        $attack_pvp = null;
        if ($attack_stats_table) {
            $attack_pve = $wpdb->get_row($wpdb->prepare("SELECT damage, dps, eps, energy, duration_ms FROM {$attack_stats_table} WHERE attack_id = %d AND game_key = %s AND context = %s LIMIT 1", $attack_id_item, 'pokemon_go', 'pve'), ARRAY_A);
            $attack_pvp = $wpdb->get_row($wpdb->prepare("SELECT damage, dps, eps, energy, duration_ms FROM {$attack_stats_table} WHERE attack_id = %d AND game_key = %s AND context = %s LIMIT 1", $attack_id_item, 'pokemon_go', 'pvp'), ARRAY_A);
        }
        $attack_types = [];
        if ($types_table && $attack_type_links_table) {
            $attack_types = $wpdb->get_col($wpdb->prepare("SELECT t.name_fr FROM {$types_table} t INNER JOIN {$attack_type_links_table} atl ON atl.type_id = t.id WHERE atl.attack_id = %d ORDER BY t.name_fr ASC", $attack_id_item));
        }
        $attack_pokemon_names = [];
        if ($links_table && $entry_family_in !== '') {
            $attack_pokemon_names = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT COALESCE(NULLIF(p.name_fr, ''), p.name_en) AS n
                 FROM {$links_table} l
                 INNER JOIN {$pokemon_table} p ON p.id = l.pokemon_id
                 WHERE l.attack_id = %d
                   AND (l.is_event = 1 OR l.role = %s)
                   AND l.pokemon_id IN ({$entry_family_in})
                 ORDER BY p.dex_number ASC",
                $attack_id_item,
                'special'
            ));
        }
        $attack_details[] = [
            'pokemon_name' => $pokemon_label,
            'attack_label' => (string) (!empty($special_attack['name_fr']) ? $special_attack['name_fr'] : ($special_attack['name_en'] ?? '')),
            'attack_types' => $attack_types,
            'attack_pokemon_names' => $attack_pokemon_names,
            'pvp' => $attack_pvp,
            'pve' => $attack_pve,
        ];
    }
}

$mega_rows = [];
$selected_types = pokehub_get_pokemon_types_for_display($primary_pokemon_id);
$selected_type_ids = [];
foreach ($selected_types as $t) {
    if (!empty($t['id'])) {
        $selected_type_ids[] = (int) $t['id'];
    }
}
if (!empty($selected_type_ids) && $form_variants_table) {
    $type_in = implode(',', array_map('intval', $selected_type_ids));
    $fam_mega = function_exists('pokehub_pokemon_sql_exclude_family_placeholder_slug_expr')
        ? pokehub_pokemon_sql_exclude_family_placeholder_slug_expr('p.slug')
        : "( LENGTH(TRIM(COALESCE(p.slug, ''))) < 7 OR RIGHT(LOWER(TRIM(COALESCE(p.slug, ''))), 7) <> '-family' )";
    $mega_rows = $wpdb->get_results(
        "SELECT DISTINCT p.id, p.name_fr, p.name_en, p.slug, p.dex_number
         FROM {$pokemon_table} p
         INNER JOIN {$form_variants_table} fv ON fv.id = p.form_variant_id
         INNER JOIN {$type_links_table} ptl ON ptl.pokemon_id = p.id
         WHERE fv.category IN ('mega', 'primal')
           AND ptl.type_id IN ({$type_in})
           AND {$fam_mega}
         ORDER BY p.dex_number ASC, p.id ASC",
        ARRAY_A
    );
}

$mega_list = [];
foreach ((array) $mega_rows as $m) {
    $pid = (int) ($m['id'] ?? 0);
    if ($pid <= 0 || (function_exists('poke_hub_pokemon_is_released_in_go') && !poke_hub_pokemon_is_released_in_go($pid, 'mega'))) {
        continue;
    }
    $img = function_exists('poke_hub_pokemon_get_image_sources') ? poke_hub_pokemon_get_image_sources((object) $m, ['shiny' => false]) : ['primary' => '', 'fallback' => ''];
    $mega_list[] = [
        'name' => (string) (!empty($m['name_fr']) ? $m['name_fr'] : ($m['name_en'] ?? '')),
        'img' => (string) ($img['primary'] ?: $img['fallback']),
    ];
}

$base_stats = $wpdb->get_row($wpdb->prepare("SELECT base_atk, base_def, base_sta FROM {$pokemon_table} WHERE id = %d LIMIT 1", $primary_pokemon_id), ARRAY_A);
$pvp = [];
if ($base_stats) {
    $ba = (int) ($base_stats['base_atk'] ?? 0);
    $bd = (int) ($base_stats['base_def'] ?? 0);
    $bs = (int) ($base_stats['base_sta'] ?? 0);
    if ($ba > 0 && $bd > 0 && $bs > 0) {
        $pvp = [
            'little' => pokehub_cd_best_pvp_ivs($ba, $bd, $bs, 500),
            'great' => pokehub_cd_best_pvp_ivs($ba, $bd, $bs, 1500),
            'ultra' => pokehub_cd_best_pvp_ivs($ba, $bd, $bs, 2500),
        ];
    }
}

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-community-day-block-wrapper']);

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php echo function_exists('pokehub_render_block_title') ? pokehub_render_block_title(__('Community Day', 'poke-hub'), 'community-day') : '<h2 class="pokehub-block-title">' . esc_html__('Community Day', 'poke-hub') . '</h2>'; ?>

    <h3 class="pokehub-block-subtitle"><?php esc_html_e('Shiny booste', 'poke-hub'); ?></h3>
    <div class="pokehub-wild-pokemon-grid">
        <?php foreach ($family as $fp) : ?>
            <div class="pokehub-wild-pokemon-card"><div class="pokehub-wild-pokemon-card-inner">
                <div class="pokehub-wild-pokemon-image-wrapper"><?php if ($fp['img'] !== '') : ?><img src="<?php echo esc_url($fp['img']); ?>" alt="<?php echo esc_attr($fp['name']); ?>" loading="lazy" /><?php endif; ?></div>
                <div class="pokehub-wild-pokemon-name"><?php echo esc_html($fp['name']); ?></div>
            </div></div>
        <?php endforeach; ?>
    </div>

    <h3 class="pokehub-block-subtitle"><?php esc_html_e('Attaque speciale', 'poke-hub'); ?></h3>
    <p>
        <?php if ($evolution_start !== '' || $evolution_end !== '') : ?>
            <strong><?php esc_html_e('Periode evolution:', 'poke-hub'); ?></strong>
            <?php echo esc_html(trim($evolution_start_display . ' - ' . $evolution_end_display, ' -')); ?><br>
        <?php endif; ?>
        <?php foreach ($attack_details as $attack_item) : ?>
            <?php if (!empty($attack_item['pokemon_name'])) : ?>
                <strong><?php esc_html_e('Pokemon selectionne:', 'poke-hub'); ?></strong>
                <?php echo esc_html((string) $attack_item['pokemon_name']); ?><br>
            <?php endif; ?>
            <?php if (!empty($attack_item['attack_pokemon_names'])) : ?>
                <strong><?php esc_html_e('Pokemon avec l attaque:', 'poke-hub'); ?></strong>
                <?php echo esc_html(implode(', ', array_map('strval', (array) $attack_item['attack_pokemon_names']))); ?><br>
            <?php endif; ?>
            <?php if (!empty($attack_item['attack_label'])) : ?>
                <strong><?php esc_html_e('Attaque:', 'poke-hub'); ?></strong> <?php echo esc_html((string) $attack_item['attack_label']); ?><br>
            <?php endif; ?>
            <?php if (!empty($attack_item['attack_types'])) : ?>
                <strong><?php esc_html_e('Type:', 'poke-hub'); ?></strong> <?php echo esc_html(implode(', ', array_map('strval', (array) $attack_item['attack_types']))); ?><br>
            <?php endif; ?>
            <?php if (!empty($attack_item['pvp'])) : ?>
                <strong><?php esc_html_e('PvP:', 'poke-hub'); ?></strong>
                <?php echo esc_html(sprintf(__('Degats %1$d | Energie %2$d', 'poke-hub'), (int) ($attack_item['pvp']['damage'] ?? 0), (int) ($attack_item['pvp']['energy'] ?? 0))); ?><br>
            <?php endif; ?>
            <?php if (!empty($attack_item['pve'])) : ?>
                <strong><?php esc_html_e('PvE:', 'poke-hub'); ?></strong>
                <?php echo esc_html(sprintf(__('Degats %1$d | Energie %2$d', 'poke-hub'), (int) ($attack_item['pve']['damage'] ?? 0), (int) ($attack_item['pve']['energy'] ?? 0))); ?><br>
            <?php endif; ?>
            <br>
        <?php endforeach; ?>
    </p>

    <h3 class="pokehub-block-subtitle"><?php esc_html_e('Mega pour bonbons et bonbons L', 'poke-hub'); ?></h3>
    <div class="pokehub-wild-pokemon-grid">
        <?php foreach ($mega_list as $m) : ?>
            <div class="pokehub-wild-pokemon-card"><div class="pokehub-wild-pokemon-card-inner">
                <div class="pokehub-wild-pokemon-image-wrapper"><?php if ($m['img'] !== '') : ?><img src="<?php echo esc_url($m['img']); ?>" alt="<?php echo esc_attr($m['name']); ?>" loading="lazy" /><?php endif; ?></div>
                <div class="pokehub-wild-pokemon-name"><?php echo esc_html($m['name']); ?></div>
            </div></div>
        <?php endforeach; ?>
    </div>

    <h3 class="pokehub-block-subtitle"><?php esc_html_e('PvP - IV rank 1 capture nature', 'poke-hub'); ?></h3>
    <?php if (!empty($pvp)) : ?>
        <ul>
            <li><?php echo esc_html(sprintf(__('Little Cup (500): %1$d/%2$d/%3$d (niv %4$s, PC %5$d)', 'poke-hub'), $pvp['little']['iv_atk'], $pvp['little']['iv_def'], $pvp['little']['iv_sta'], $pvp['little']['level'], $pvp['little']['cp'])); ?></li>
            <li><?php echo esc_html(sprintf(__('Ligue Super (1500): %1$d/%2$d/%3$d (niv %4$s, PC %5$d)', 'poke-hub'), $pvp['great']['iv_atk'], $pvp['great']['iv_def'], $pvp['great']['iv_sta'], $pvp['great']['level'], $pvp['great']['cp'])); ?></li>
            <li><?php echo esc_html(sprintf(__('Ligue Hyper (2500): %1$d/%2$d/%3$d (niv %4$s, PC %5$d)', 'poke-hub'), $pvp['ultra']['iv_atk'], $pvp['ultra']['iv_def'], $pvp['ultra']['iv_sta'], $pvp['ultra']['level'], $pvp['ultra']['cp'])); ?></li>
        </ul>
    <?php endif; ?>
</div>
<?php
return ob_get_clean();
