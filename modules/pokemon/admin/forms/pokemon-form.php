<?php
// File: modules/pokemon/admin/forms/pokemon-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Pokémon
 *
 * @param object|null $edit_row
 */
function poke_hub_pokemon_pokemon_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit Pokémon.', 'poke-hub'));
    }

    global $wpdb;

    $is_edit = ($edit_row && isset($edit_row->id));

    // Récupérer toutes les météos disponibles pour les select2
    $all_weathers = [];
    $table_weathers = pokehub_get_table('pokemon_weathers');
    if ($table_weathers) {
        $all_weathers = $wpdb->get_results("SELECT id, slug, name_fr, name_en FROM {$table_weathers} ORDER BY name_fr ASC, name_en ASC");
    }

    // Récupérer tous les items d'évolution et leurres pour les select2
    $all_evolution_items = [];
    $all_lure_items = [];
    $table_items = pokehub_get_table('items');
    if ($table_items) {
        $all_evolution_items = $wpdb->get_results("SELECT id, slug, name_fr, name_en FROM {$table_items} WHERE category = 'evolution_item' ORDER BY name_fr ASC, name_en ASC");
        $all_lure_items = $wpdb->get_results("SELECT id, slug, name_fr, name_en FROM {$table_items} WHERE category = 'lure' ORDER BY name_fr ASC, name_en ASC");
    }

    // Décoder extra si présent
    $extra = [];
    if ($is_edit && !empty($edit_row->extra)) {
        $decoded = json_decode($edit_row->extra, true);
        if (is_array($decoded)) {
            $extra = $decoded;
        }
    }

    // Pré-remplissage "core"
    $dex_number     = $is_edit ? (int) $edit_row->dex_number : 0;
    $slug           = $is_edit ? (string) $edit_row->slug : '';

    // Slug de forme stocké en base
    $form_variant_id = ($is_edit && isset($edit_row->form_variant_id)) ? (int) $edit_row->form_variant_id : 0;

    $is_default    = $is_edit ? (int) $edit_row->is_default : 0;
    $generation_id = $is_edit ? (int) $edit_row->generation_id : 0;
    $base_atk      = $is_edit ? (int) $edit_row->base_atk : 0;
    $base_def      = $is_edit ? (int) $edit_row->base_def : 0;
    $base_sta      = $is_edit ? (int) $edit_row->base_sta : 0;

    // Noms en base
    $base_name_en = $is_edit ? (string) $edit_row->name_en : '';
    $base_name_fr = $is_edit ? (string) $edit_row->name_fr : '';

    $category = $extra['category'] ?? '';
    $about    = $extra['about'] ?? '';

    // Genre : on s'assure que c'est bien un tableau
    $gender = [];
    if (isset($extra['gender']) && is_array($extra['gender'])) {
        $gender = $extra['gender'];
    }

    $gender_male   = $gender['male']   ?? 0;
    $gender_female = $gender['female'] ?? 0;

    // Noms localisés depuis extra
    $names = $extra['names'] ?? [];

    // On s'assure que FR/EN sont bien initialisés à partir de la table principale
    if (empty($names['fr']) && $base_name_fr !== '') {
        $names['fr'] = $base_name_fr;
    }
    if (empty($names['en']) && $base_name_en !== '') {
        $names['en'] = $base_name_en;
    }

    // Nom "principal" pour le champ General information > Name (affichage)
    $name = '';
    if (!empty($names['fr'])) {
        $name = $names['fr'];
    } elseif (!empty($names['en'])) {
        $name = $names['en'];
    }

    // ================================
    // Bloc par jeu : Pokémon GO
    // ================================
    $games   = is_array($extra['games'] ?? null) ? $extra['games'] : [];
    $game_go = is_array($games['pokemon_go'] ?? null) ? $games['pokemon_go'] : [];

    // Fallback éventuel sur l’ancien bloc "go"
    if (empty($game_go) && isset($extra['go']) && is_array($extra['go'])) {
        $game_go = $extra['go'];
    }

    // Pokedex / buddy / encounter / second_move
    $go_pokedex   = is_array($game_go['pokedex']   ?? null) ? $game_go['pokedex']   : (is_array($extra['pokedex']   ?? null) ? $extra['pokedex']   : []);
    $go_buddy     = is_array($game_go['buddy']     ?? null) ? $game_go['buddy']     : (is_array($extra['buddy']     ?? null) ? $extra['buddy']     : []);
    $go_encounter = is_array($game_go['encounter'] ?? null) ? $game_go['encounter'] : (is_array($extra['encounter'] ?? null) ? $extra['encounter'] : []);
    $go_second    = is_array($game_go['second_move'] ?? null) ? $game_go['second_move'] : (is_array($extra['second_move'] ?? null) ? $extra['second_move'] : []);

    $go_cp_sets   = is_array($game_go['cp_sets'] ?? null) ? $game_go['cp_sets'] : [];

    // Valeurs individuelles GO pour les champs
    $go_height_m          = $go_pokedex['height_m']  ?? '';
    $go_weight_kg         = $go_pokedex['weight_kg'] ?? '';
    $go_catch_rate        = $go_encounter['base_capture_rate'] ?? '';
    $go_flee_rate         = $go_encounter['base_flee_rate']    ?? '';
    $go_buddy_distance_km = $go_buddy['km_buddy_distance']     ?? '';

    $go_second_cost    = is_array($go_second['cost'] ?? null) ? $go_second['cost'] : [];
    $go_second_stardust = $go_second_cost['stardust'] ?? '';
    $go_second_candy    = $go_second_cost['candy']    ?? '';

    // Shadow / trade (extra)
    $shadow_extra = is_array($extra['shadow'] ?? null) ? $extra['shadow'] : [];
    $trade_extra  = is_array($extra['trade'] ?? null)  ? $extra['trade']  : [];

    // Flags BDD + extra
    $is_tradable = $is_edit
        ? (int) $edit_row->is_tradable
        : (!empty($trade_extra['is_tradable']) ? 1 : 0);

    $is_transferable = $is_edit
        ? (int) $edit_row->is_transferable
        : (!empty($trade_extra['is_transferable']) ? 1 : 0);

    $has_shadow = $is_edit
        ? (int) $edit_row->has_shadow
        : (!empty($shadow_extra['has_shadow']) ? 1 : 0);

    $has_purified = $is_edit
        ? (int) $edit_row->has_purified
        : (!empty($shadow_extra['has_purified']) ? 1 : 0);

    $shadow_pur_stardust = $is_edit
        ? (int) $edit_row->shadow_purification_stardust
        : (int) ($shadow_extra['stardust'] ?? 0);

    $shadow_pur_candy = $is_edit
        ? (int) $edit_row->shadow_purification_candy
        : (int) ($shadow_extra['candy'] ?? 0);

    $buddy_mega_energy_award = $is_edit
        ? (int) $edit_row->buddy_walked_mega_energy_award
        : (int) ($go_buddy['buddy_mega_energy_award'] ?? 0);

    $attack_probability = $is_edit
        ? (float) $edit_row->attack_probability
        : (float) ($go_encounter['attack_probability'] ?? 0.0);

    $dodge_probability = $is_edit
        ? (float) $edit_row->dodge_probability
        : (float) ($go_encounter['dodge_probability'] ?? 0.0);

    // CP par niveau (max / min 10 / min shadow 6)
    $cp_max        = is_array($go_cp_sets['max_cp']        ?? null) ? $go_cp_sets['max_cp']        : [];
    $cp_min_10     = is_array($go_cp_sets['min_cp_10']     ?? null) ? $go_cp_sets['min_cp_10']     : [];
    $cp_min_shadow = is_array($go_cp_sets['min_cp_shadow'] ?? null) ? $go_cp_sets['min_cp_shadow'] : [];

    // fallback éventuel sur un ancien "max_cp" plat
    if (empty($cp_max)) {
        if (!empty($game_go['max_cp']) && is_array($game_go['max_cp'])) {
            $cp_max = $game_go['max_cp'];
        } elseif (!empty($extra['max_cp']) && is_array($extra['max_cp'])) {
            $cp_max = $extra['max_cp'];
        }
    }

    // Release / regional (global, pas spécifiques à un jeu pour l’instant)
    $release  = is_array($extra['release']  ?? null) ? $extra['release']  : [];
    $regional = is_array($extra['regional'] ?? null) ? $extra['regional'] : [];

    // ================================
    // Listes de référence
    // ================================

    // 1) Générations
    $gens_table = pokehub_get_table('generations');
    $gens       = [];

    if ($gens_table) {
        $gens = $wpdb->get_results("
            SELECT id, generation_number, name_en, name_fr
            FROM {$gens_table}
            ORDER BY generation_number ASC
        ");
    }

    // 2) Variantes globales (pokemon_form_variants)
    $form_variants       = [];
    $form_variant_labels = [];

    $form_variants_table = pokehub_get_table('pokemon_form_variants');

    if ($form_variants_table) {
        $form_variants = $wpdb->get_results("
            SELECT id, form_slug, label, category, `group`
            FROM {$form_variants_table}
            ORDER BY label ASC, form_slug ASC
        ");

        foreach ($form_variants as $fv) {
            $display = $fv->label !== '' ? $fv->label : $fv->form_slug;

            $meta = [];
            if (!empty($fv->category) && $fv->category !== 'normal') {
                $meta[] = $fv->category;
            }
            if (!empty($fv->group)) {
                $meta[] = $fv->group;
            }
            if ($meta) {
                $display .= ' (' . implode(' – ', $meta) . ')';
            }

            $form_variant_labels[$fv->id] = [
                'label'     => $display,
                'form_slug' => $fv->form_slug,
            ];
        }
    }

    // 3) Liste des attaques Pokémon GO (via category + attack_stats.game_key = 'pokemon_go')
    $attacks_table       = pokehub_get_table('attacks');
    $attack_stats_table  = pokehub_get_table('attack_stats');

    $all_fast_moves    = [];
    $all_charged_moves = [];

    if ($attacks_table && $attack_stats_table) {
        $rows = $wpdb->get_results("
            SELECT DISTINCT a.id, a.name_en, a.name_fr, a.slug, a.category
            FROM {$attacks_table} AS a
            INNER JOIN {$attack_stats_table} AS s ON s.attack_id = a.id
            WHERE s.game_key = 'pokemon_go'
            ORDER BY a.category ASC, a.name_fr ASC, a.name_en ASC, a.slug ASC
        ");

        foreach ($rows as $row) {
            $name_fr = isset($row->name_fr) ? (string) $row->name_fr : '';
            $name_en = isset($row->name_en) ? (string) $row->name_en : '';
            $label   = $name_fr !== '' ? $name_fr : ($name_en !== '' ? $name_en : $row->slug);

            $entry = (object) [
                'id'       => (int) $row->id,
                'label'    => $label,
                'category' => (string) $row->category,
            ];

            switch ($row->category) {
                case 'fast':
                    $all_fast_moves[] = $entry;
                    break;
                case 'charged':
                    $all_charged_moves[] = $entry;
                    break;
                default:
                    // Autres catégories ignorées pour ce formulaire
                    break;
            }
        }
    }

    // Attaques déjà liées à ce Pokémon (helper basé sur pokemon_attack_links)
    $current_fast_moves    = [];
    $current_charged_moves = [];

    if ($is_edit && function_exists('poke_hub_pokemon_get_pokemon_attacks')) {
        $attacks_data = poke_hub_pokemon_get_pokemon_attacks((int) $edit_row->id);
        if (is_array($attacks_data)) {
            if (!empty($attacks_data['fast']) && is_array($attacks_data['fast'])) {
                $current_fast_moves = $attacks_data['fast'];
            }
            if (!empty($attacks_data['charged']) && is_array($attacks_data['charged'])) {
                $current_charged_moves = $attacks_data['charged'];
            }
        }
    }

    // Index de départ pour JS
    $fast_index    = 0;
    $charged_index = 0;

    // ================================
    // Evolutions (si en édition)
    // ================================
    $evolutions_out = [];
    $evolutions_in  = [];
    $all_pokemon_for_evo = [];

    if ($is_edit && function_exists('pokehub_get_table')) {
        $evo_table      = pokehub_get_table('pokemon_evolutions');
        $pokemon_table  = pokehub_get_table('pokemon');
        $variants_table = pokehub_get_table('pokemon_form_variants');

        if ($evo_table && $pokemon_table) {

            // Liste de tous les Pokémon pour le select
            $query_all = "
                SELECT p.id, p.dex_number, p.name_fr, p.name_en, p.form_variant_id,
                       fv.label AS variant_label, fv.form_slug
                FROM {$pokemon_table} p
                LEFT JOIN {$variants_table} fv ON fv.id = p.form_variant_id
                ORDER BY p.dex_number ASC, p.name_fr ASC, p.name_en ASC
            ";
            $all_pokemon_for_evo = $wpdb->get_results($query_all);

            // Evolutions sortantes (ce Pokémon -> autre)
            $evolutions_out = $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT e.*,
                           t.dex_number AS target_dex_number,
                           t.name_fr    AS target_name_fr,
                           t.name_en    AS target_name_en,
                           tv.label     AS target_variant_label,
                           tv.form_slug AS target_form_slug
                    FROM {$evo_table} e
                    INNER JOIN {$pokemon_table} t ON t.id = e.target_pokemon_id
                    LEFT JOIN {$variants_table} tv ON tv.id = e.target_form_variant_id
                    WHERE e.base_pokemon_id = %d
                      AND e.base_form_variant_id = %d
                    ORDER BY e.priority ASC, e.id ASC
                    ",
                    (int) $edit_row->id,
                    (int) $form_variant_id
                )
            );

            // Pré-évolutions (autres Pokémon -> ce Pokémon)
            $evolutions_in = $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT e.*,
                           b.dex_number AS base_dex_number,
                           b.name_fr    AS base_name_fr,
                           b.name_en    AS base_name_en,
                           bv.label     AS base_variant_label,
                           bv.form_slug AS base_form_slug
                    FROM {$evo_table} e
                    INNER JOIN {$pokemon_table} b ON b.id = e.base_pokemon_id
                    LEFT JOIN {$variants_table} bv ON bv.id = e.base_form_variant_id
                    WHERE e.target_pokemon_id = %d
                      AND e.target_form_variant_id = %d
                    ORDER BY e.priority ASC, e.id ASC
                    ",
                    (int) $edit_row->id,
                    (int) $form_variant_id
                )
            );
        }
    }

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'pokemon',
        ],
        admin_url('admin.php')
    );
    ?>
<div class="wrap">
    <h1>
        <?php
        echo $is_edit
            ? esc_html__('Edit Pokémon', 'poke-hub')
            : esc_html__('Add Pokémon', 'poke-hub');
        ?>
        <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
            <?php esc_html_e('Back to list', 'poke-hub'); ?>
        </a>
    </h1>

    <form method="post">
        <?php wp_nonce_field('poke_hub_pokemon_edit_pokemon'); ?>
        <input type="hidden" name="page" value="poke-hub-pokemon" />
        <input type="hidden" name="ph_section" value="pokemon" />
        <input type="hidden" name="poke_hub_pokemon_action"
               value="<?php echo $is_edit ? 'update_pokemon' : 'add_pokemon'; ?>" />
        <?php if ($is_edit) : ?>
            <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
        <?php endif; ?>

        <!-- Section: General Information -->
        <div class="pokehub-section">
            <h2><?php esc_html_e('General information', 'poke-hub'); ?></h2>
            
            <div class="pokehub-form-row">
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="dex_number"><?php esc_html_e('National Dex #', 'poke-hub'); ?> *</label>
                        <input type="number" min="1" style="max-width: 150px;" name="dex_number" id="dex_number" value="<?php echo esc_attr($dex_number); ?>" />
                    </div>
                </div>
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="generation_id"><?php esc_html_e('Generation', 'poke-hub'); ?></label>
                        <select name="generation_id" id="generation_id">
                            <option value="0"><?php esc_html_e('— None —', 'poke-hub'); ?></option>
                            <?php foreach ($gens as $gen) : ?>
                                <option value="<?php echo (int) $gen->id; ?>" <?php selected($generation_id, $gen->id); ?>>
                                    <?php
                                    $gen_label = $gen->generation_number
                                        ? sprintf(esc_html__('Generation %d', 'poke-hub'), (int) $gen->generation_number)
                                        : (!empty($gen->name_fr) ? $gen->name_fr : $gen->name_en);
                                    echo esc_html($gen_label);
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="is_default" value="1" <?php checked($is_default, 1); ?> />
                            <span><?php esc_html_e('Default form', 'poke-hub'); ?></span>
                        </label>
                        <p class="description"><?php esc_html_e('Only one form per Dex number should be marked as default.', 'poke-hub'); ?></p>
                    </div>
                </div>
            </div>

            <div class="pokehub-form-row">
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="name"><?php esc_html_e('Name', 'poke-hub'); ?> *</label>
                        <input type="text" name="name" id="name" value="<?php echo esc_attr($name); ?>" />
                        <p class="description"><?php esc_html_e('Main display name (usually FR or EN). Localized names are handled below.', 'poke-hub'); ?></p>
                    </div>
                </div>
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                        <input type="text" name="slug" id="slug" value="<?php echo esc_attr($slug); ?>" />
                        <p class="description"><?php esc_html_e('Used in URLs, must be unique.', 'poke-hub'); ?></p>
                    </div>
                </div>
            </div>

            <div class="pokehub-form-row">
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="form_variant_id"><?php esc_html_e('Form / variant', 'poke-hub'); ?></label>
                        <select name="form_variant_id" id="form_variant_id">
                            <option value="0"><?php esc_html_e('Default form (no variant)', 'poke-hub'); ?></option>
                            <?php if (!empty($form_variant_labels)) : ?>
                                <?php foreach ($form_variant_labels as $variant_id => $data) : ?>
                                    <option value="<?php echo (int) $variant_id; ?>" <?php selected($form_variant_id, (int) $variant_id); ?>>
                                        <?php echo esc_html($data['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php
                        if ($form_variant_id > 0 && !empty($form_variant_labels[$form_variant_id]['form_slug'])) :
                            ?>
                            <p class="description">
                                <?php
                                printf(
                                    esc_html__('Current variant slug: %s', 'poke-hub'),
                                    '<code>' . esc_html($form_variant_labels[$form_variant_id]['form_slug']) . '</code>'
                                );
                                ?>
                            </p>
                        <?php else : ?>
                            <p class="description"><?php esc_html_e('Leave empty for the default form. Otherwise choose a variant from the registry.', 'poke-hub'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="category"><?php esc_html_e('Category', 'poke-hub'); ?></label>
                        <input type="text" name="category" id="category" value="<?php echo esc_attr($category); ?>" />
                        <p class="description"><?php esc_html_e('Example: Mouse Pokémon', 'poke-hub'); ?></p>
                    </div>
                </div>
            </div>

            <div class="pokehub-form-group">
                <label for="about"><?php esc_html_e('About / flavor text', 'poke-hub'); ?></label>
                <textarea name="about" id="about" rows="4" style="width: 100%;"><?php echo esc_textarea($about); ?></textarea>
            </div>
        </div>

        <!-- Section: Localization -->
        <div class="pokehub-section">
            <h2><?php esc_html_e('Localization', 'poke-hub'); ?></h2>
            
            <div class="pokehub-form-row">
                <?php
                $locales = ['fr', 'en', 'de', 'it', 'es', 'ja', 'ko'];
                foreach ($locales as $loc) :
                    $val = $names[$loc] ?? '';
                ?>
                    <div class="pokehub-form-col">
                        <div class="pokehub-form-group">
                            <label for="name_<?php echo esc_attr($loc); ?>"><?php echo esc_html(strtoupper($loc)); ?></label>
                            <input type="text" name="name_<?php echo esc_attr($loc); ?>" id="name_<?php echo esc_attr($loc); ?>" value="<?php echo esc_attr($val); ?>" />
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Section: Gender Ratio -->
        <div class="pokehub-section">
            <h2><?php esc_html_e('Gender ratio', 'poke-hub'); ?></h2>
            
            <div class="pokehub-form-row">
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="gender_male"><?php esc_html_e('Male %', 'poke-hub'); ?></label>
                        <input type="number" step="0.1" min="0" max="100" name="gender_male" id="gender_male" value="<?php echo esc_attr($gender_male); ?>" /> %
                    </div>
                </div>
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="gender_female"><?php esc_html_e('Female %', 'poke-hub'); ?></label>
                        <input type="number" step="0.1" min="0" max="100" name="gender_female" id="gender_female" value="<?php echo esc_attr($gender_female); ?>" /> %
                    </div>
                </div>
            </div>
        </div>

        <?php
        // ============================
        // SECTION SPÉCIALE POKÉMON GO
        // ============================
        ?>
        
        <!-- Section: Pokémon GO - Stats & CP -->
        <div class="pokehub-section">
            <h2><?php esc_html_e('Pokémon GO', 'poke-hub'); ?></h2>
            <h3><?php esc_html_e('Base stats & CP', 'poke-hub'); ?></h3>
            
            <div class="pokehub-form-row">
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="base_atk"><?php esc_html_e('ATK', 'poke-hub'); ?></label>
                        <input type="number" style="max-width: 150px;" name="base_atk" id="base_atk" value="<?php echo esc_attr($base_atk); ?>" />
                    </div>
                </div>
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="base_def"><?php esc_html_e('DEF', 'poke-hub'); ?></label>
                        <input type="number" style="max-width: 150px;" name="base_def" id="base_def" value="<?php echo esc_attr($base_def); ?>" />
                    </div>
                </div>
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="base_sta"><?php esc_html_e('STA', 'poke-hub'); ?></label>
                        <input type="number" style="max-width: 150px;" name="base_sta" id="base_sta" value="<?php echo esc_attr($base_sta); ?>" />
                    </div>
                </div>
            </div>
            <p class="description"><?php esc_html_e('CP values are automatically computed from these base stats when possible.', 'poke-hub'); ?></p>
            
            <div class="pokehub-form-group">
                <label><?php esc_html_e('CP by level (auto)', 'poke-hub'); ?></label>
                <?php
                $levels = [15, 20, 25, 30, 35, 40, 50, 51];

                if (empty($cp_max) && empty($cp_min_10) && empty($cp_min_shadow)) :
                    ?>
                    <p class="description"><?php esc_html_e('No CP data available yet. They will be filled automatically after a Game Master import or a manual save if base stats and CP helpers are available.', 'poke-hub'); ?></p>
                <?php else : ?>
                    <table class="widefat fixed striped pokehub-table-max-width">
                        <thead>
                        <tr>
                            <th><?php esc_html_e('Level', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Max CP (15/15/15)', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Min CP 10/10/10', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Min Shadow 6/6/6', 'poke-hub'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($levels as $lvl) :
                            $key = (string) $lvl;
                            $v_max    = $cp_max[$key]        ?? '';
                            $v_10     = $cp_min_10[$key]     ?? '';
                            $v_shadow = $cp_min_shadow[$key] ?? '';
                            ?>
                            <tr>
                                <td><?php echo esc_html($lvl); ?></td>
                                <td><?php echo $v_max !== '' ? esc_html($v_max) : '&mdash;'; ?></td>
                                <td><?php echo $v_10 !== '' ? esc_html($v_10) : '&mdash;'; ?></td>
                                <td><?php echo $v_shadow !== '' ? esc_html($v_shadow) : '&mdash;'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section: Pokémon GO Details -->
        <div class="pokehub-section">
            <h3><?php esc_html_e('Details', 'poke-hub'); ?></h3>
            
            <div class="pokehub-form-row">
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="go_height_m"><?php esc_html_e('Height (m)', 'poke-hub'); ?></label>
                        <input type="number" step="0.01" name="go_height_m" id="go_height_m" value="<?php echo esc_attr($go_height_m); ?>" />
                    </div>
                </div>
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="go_weight_kg"><?php esc_html_e('Weight (kg)', 'poke-hub'); ?></label>
                        <input type="number" step="0.01" name="go_weight_kg" id="go_weight_kg" value="<?php echo esc_attr($go_weight_kg); ?>" />
                    </div>
                </div>
            </div>

            <div class="pokehub-form-row">
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="go_catch_rate"><?php esc_html_e('Catch rate', 'poke-hub'); ?></label>
                        <input type="number" step="0.01" name="go_catch_rate" id="go_catch_rate" value="<?php echo esc_attr($go_catch_rate); ?>" />
                        <p class="description"><?php esc_html_e('Value between 0 and 1 (Game Master baseCaptureRate).', 'poke-hub'); ?></p>
                    </div>
                </div>
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="go_flee_rate"><?php esc_html_e('Flee rate', 'poke-hub'); ?></label>
                        <input type="number" step="0.01" name="go_flee_rate" id="go_flee_rate" value="<?php echo esc_attr($go_flee_rate); ?>" />
                        <p class="description"><?php esc_html_e('Value between 0 and 1 (Game Master baseFleeRate).', 'poke-hub'); ?></p>
                    </div>
                </div>
            </div>

            <div class="pokehub-form-row">
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="go_buddy_distance_km"><?php esc_html_e('Buddy distance (km)', 'poke-hub'); ?></label>
                        <input type="number" step="0.1" name="go_buddy_distance_km" id="go_buddy_distance_km" value="<?php echo esc_attr($go_buddy_distance_km); ?>" />
                    </div>
                </div>
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="buddy_mega_energy_award"><?php esc_html_e('Buddy walked mega energy', 'poke-hub'); ?></label>
                        <input type="number" style="max-width: 150px;" name="buddy_mega_energy_award" id="buddy_mega_energy_award" value="<?php echo esc_attr($buddy_mega_energy_award); ?>" />
                        <p class="description"><?php esc_html_e('Mega energy awarded when walking this Pokémon as buddy.', 'poke-hub'); ?></p>
                    </div>
                </div>
            </div>

            <div class="pokehub-form-row">
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label><?php esc_html_e('Second charged move cost', 'poke-hub'); ?></label>
                        <div style="display: flex; gap: 10px;">
                            <label style="flex: 1;"><?php esc_html_e('Stardust', 'poke-hub'); ?>
                                <input type="number" style="width: 100%;" name="go_second_stardust" value="<?php echo esc_attr($go_second_stardust); ?>" />
                            </label>
                            <label style="flex: 1;"><?php esc_html_e('Candy', 'poke-hub'); ?>
                                <input type="number" style="width: 100%;" name="go_second_candy" value="<?php echo esc_attr($go_second_candy); ?>" />
                            </label>
                        </div>
                    </div>
                </div>
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label><?php esc_html_e('Trade & transfer', 'poke-hub'); ?></label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="is_tradable" value="1" <?php checked($is_tradable, 1); ?> />
                            <span><?php esc_html_e('Tradable', 'poke-hub'); ?></span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="is_transferable" value="1" <?php checked($is_transferable, 1); ?> />
                            <span><?php esc_html_e('Transferable (can be sent to Professor)', 'poke-hub'); ?></span>
                        </label>
                        <p class="description"><?php esc_html_e('These flags map the Game Master isTradable / isTransferable fields.', 'poke-hub'); ?></p>
                    </div>
                </div>
            </div>

            <div class="pokehub-form-group">
                <label><?php esc_html_e('Shadow & purification', 'poke-hub'); ?></label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <input type="checkbox" name="has_shadow" value="1" <?php checked($has_shadow, 1); ?> />
                    <span><?php esc_html_e('Has shadow form', 'poke-hub'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                    <input type="checkbox" name="has_purified" value="1" <?php checked($has_purified, 1); ?> />
                    <span><?php esc_html_e('Has purified form', 'poke-hub'); ?></span>
                </label>
                <div style="display: flex; gap: 10px;">
                    <label style="flex: 1;">
                        <?php esc_html_e('Purification stardust cost', 'poke-hub'); ?>
                        <input type="number" style="width: 100%;" name="shadow_purification_stardust" value="<?php echo esc_attr($shadow_pur_stardust); ?>" />
                    </label>
                    <label style="flex: 1;">
                        <?php esc_html_e('Purification candy cost', 'poke-hub'); ?>
                        <input type="number" style="width: 100%;" name="shadow_purification_candy" value="<?php echo esc_attr($shadow_pur_candy); ?>" />
                    </label>
                </div>
                <p class="description"><?php esc_html_e('Return / Frustration moves are linked automatically by the Game Master importer and stored in the shadow metadata.', 'poke-hub'); ?></p>
            </div>

            <div class="pokehub-form-row">
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="attack_probability"><?php esc_html_e('Attack probability', 'poke-hub'); ?></label>
                        <input type="number" step="0.01" min="0" max="1" name="attack_probability" id="attack_probability" value="<?php echo esc_attr($attack_probability); ?>" />
                        <p class="description"><?php esc_html_e('Value between 0 and 1.', 'poke-hub'); ?></p>
                    </div>
                </div>
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="dodge_probability"><?php esc_html_e('Dodge probability', 'poke-hub'); ?></label>
                        <input type="number" step="0.01" min="0" max="1" name="dodge_probability" id="dodge_probability" value="<?php echo esc_attr($dodge_probability); ?>" />
                        <p class="description"><?php esc_html_e('Value between 0 and 1.', 'poke-hub'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Release Dates -->
        <div class="pokehub-section">
            <h2><?php esc_html_e('Release dates', 'poke-hub'); ?></h2>
            
            <div class="pokehub-form-row">
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="release_normal"><?php esc_html_e('Normal', 'poke-hub'); ?></label>
                        <input type="text" placeholder="YYYY-MM-DD" name="release_normal" id="release_normal" value="<?php echo esc_attr($release['normal'] ?? ''); ?>" />
                    </div>
                </div>
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="release_shiny"><?php esc_html_e('Shiny', 'poke-hub'); ?></label>
                        <input type="text" placeholder="YYYY-MM-DD" name="release_shiny" id="release_shiny" value="<?php echo esc_attr($release['shiny'] ?? ''); ?>" />
                    </div>
                </div>
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="release_shadow"><?php esc_html_e('Shadow', 'poke-hub'); ?></label>
                        <input type="text" placeholder="YYYY-MM-DD" name="release_shadow" id="release_shadow" value="<?php echo esc_attr($release['shadow'] ?? ''); ?>" />
                    </div>
                </div>
            </div>

            <div class="pokehub-form-row">
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="release_mega"><?php esc_html_e('Mega', 'poke-hub'); ?></label>
                        <input type="text" placeholder="YYYY-MM-DD" name="release_mega" id="release_mega" value="<?php echo esc_attr($release['mega'] ?? ''); ?>" />
                    </div>
                </div>
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="release_dynamax"><?php esc_html_e('Dynamax', 'poke-hub'); ?></label>
                        <input type="text" placeholder="YYYY-MM-DD" name="release_dynamax" id="release_dynamax" value="<?php echo esc_attr($release['dynamax'] ?? ''); ?>" />
                    </div>
                </div>
                <div class="pokehub-form-col">
                    <div class="pokehub-form-group">
                        <label for="release_gigantamax"><?php esc_html_e('Gigantamax', 'poke-hub'); ?></label>
                        <input type="text" placeholder="YYYY-MM-DD" name="release_gigantamax" id="release_gigantamax" value="<?php echo esc_attr($release['gigantamax'] ?? ''); ?>" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Regional Availability -->
        <div class="pokehub-section">
            <h2><?php esc_html_e('Regional availability', 'poke-hub'); ?></h2>
            
            <div class="pokehub-form-row">
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="regional_is_regional" value="1" <?php checked(!empty($regional['is_regional'])); ?> />
                            <span><?php esc_html_e('Is regional?', 'poke-hub'); ?></span>
                        </label>
                    </div>
                </div>
                <div class="pokehub-form-col-50">
                    <div class="pokehub-form-group">
                        <label for="regional_map_image_id"><?php esc_html_e('Region map image ID', 'poke-hub'); ?></label>
                        <input type="number" style="max-width: 150px;" name="regional_map_image_id" id="regional_map_image_id" value="<?php echo esc_attr($regional['map_image_id'] ?? 0); ?>" />
                        <p class="description"><?php esc_html_e('WordPress media attachment ID of the regional map.', 'poke-hub'); ?></p>
                    </div>
                </div>
            </div>

            <div class="pokehub-form-group">
                <label for="regional_description"><?php esc_html_e('Regional description', 'poke-hub'); ?></label>
                <textarea name="regional_description" id="regional_description" rows="3" style="width: 100%;"><?php echo esc_textarea($regional['description'] ?? ''); ?></textarea>
                <p class="description"><?php esc_html_e('Example: Available only in North America, etc.', 'poke-hub'); ?></p>
            </div>
        </div>

        <?php
        // ============================
        //  SECTION ATTAQUES
        // ============================
        ?>
        
        <!-- Section: Attacks -->
        <div class="pokehub-section">
            <h2><?php esc_html_e('Pokémon GO – Attacks', 'poke-hub'); ?></h2>
            <p class="description">
                <?php esc_html_e('Manage fast and charged moves for this Pokémon. Legacy is stored per Pokémon, not globally on the move.', 'poke-hub'); ?>
            </p>

            <style>
                .pokehub-moves-grid {
                    display: flex;
                    gap: 24px;
                    align-items: flex-start;
                    margin-top: 10px;
                }
                .pokehub-moves-column {
                    flex: 1 1 50%;
                    min-width: 0;
                }
                .pokehub-moves-column h3 {
                    margin-top: 0;
                }
                @media (max-width: 960px) {
                    .pokehub-moves-grid {
                        flex-direction: column;
                    }
                }
            </style>

        <div class="pokehub-moves-grid">
            <!-- Colonne FAST -->
            <div class="pokehub-moves-column">
                <h3><?php esc_html_e('Fast moves', 'poke-hub'); ?></h3>
                <?php if (empty($all_fast_moves)) : ?>
                    <em><?php esc_html_e('No fast moves found for Pokémon GO. Please create moves first.', 'poke-hub'); ?></em>
                <?php else : ?>
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Attack', 'poke-hub'); ?></th>
                                <th class="pokehub-col-legacy"><?php esc_html_e('Legacy?', 'poke-hub'); ?></th>
                                <th class="pokehub-col-remove"><?php esc_html_e('Remove', 'poke-hub'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="pokehub-fast-moves-rows" data-next-index="<?php
                            echo max(count($current_fast_moves), 1);
                        ?>">
                            <?php
                            if (!empty($current_fast_moves)) :
                                foreach ($current_fast_moves as $fm) :
                                    $attack_id = (int) ($fm['attack_id'] ?? 0);
                                    $is_legacy = !empty($fm['is_legacy']);
                            ?>
                                <tr>
                                    <td>
                                        <select class="pokehub-move-select" name="fast_moves[<?php echo (int) $fast_index; ?>][attack_id]">
                                            <option value="0"><?php esc_html_e('— Select move —', 'poke-hub'); ?></option>
                                            <?php foreach ($all_fast_moves as $move) : ?>
                                                <option value="<?php echo (int) $move->id; ?>"
                                                    <?php selected($attack_id, (int) $move->id); ?>>
                                                    <?php echo esc_html($move->label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="fast_moves[<?php echo (int) $fast_index; ?>][is_legacy]"
                                                   value="1"
                                                   <?php checked($is_legacy); ?> />
                                            <?php esc_html_e('Legacy', 'poke-hub'); ?>
                                        </label>
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="button link-delete-row pokehub-remove-move-row">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                                <?php
                                    $fast_index++;
                                endforeach;
                            else :
                            ?>
                                <tr>
                                    <td>
                                        <select class="pokehub-move-select" name="fast_moves[0][attack_id]">
                                            <option value="0"><?php esc_html_e('— Select move —', 'poke-hub'); ?></option>
                                            <?php foreach ($all_fast_moves as $move) : ?>
                                                <option value="<?php echo (int) $move->id; ?>">
                                                    <?php echo esc_html($move->label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="fast_moves[0][is_legacy]"
                                                   value="1" />
                                            <?php esc_html_e('Legacy', 'poke-hub'); ?>
                                        </label>
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="button link-delete-row pokehub-remove-move-row">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" class="button button-secondary pokehub-add-fast-move">
                            <?php esc_html_e('Add fast move', 'poke-hub'); ?>
                        </button>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Colonne CHARGED -->
            <div class="pokehub-moves-column">
                <h3><?php esc_html_e('Charged moves', 'poke-hub'); ?></h3>
                <?php if (empty($all_charged_moves)) : ?>
                    <em><?php esc_html_e('No charged moves found for Pokémon GO. Please create moves first.', 'poke-hub'); ?></em>
                <?php else : ?>
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Attack', 'poke-hub'); ?></th>
                                <th class="pokehub-col-legacy"><?php esc_html_e('Legacy?', 'poke-hub'); ?></th>
                                <th class="pokehub-col-remove"><?php esc_html_e('Remove', 'poke-hub'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="pokehub-charged-moves-rows" data-next-index="<?php
                            echo max(count($current_charged_moves), 1);
                        ?>">
                            <?php
                            if (!empty($current_charged_moves)) :
                                foreach ($current_charged_moves as $cm) :
                                    $attack_id = (int) ($cm['attack_id'] ?? 0);
                                    $is_legacy = !empty($cm['is_legacy']);
                            ?>
                                <tr>
                                    <td>
                                        <select class="pokehub-move-select" name="charged_moves[<?php echo (int) $charged_index; ?>][attack_id]">
                                            <option value="0"><?php esc_html_e('— Select move —', 'poke-hub'); ?></option>
                                            <?php foreach ($all_charged_moves as $move) : ?>
                                                <option value="<?php echo (int) $move->id; ?>"
                                                    <?php selected($attack_id, (int) $move->id); ?>>
                                                    <?php echo esc_html($move->label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="charged_moves[<?php echo (int) $charged_index; ?>][is_legacy]"
                                                   value="1"
                                                   <?php checked($is_legacy); ?> />
                                            <?php esc_html_e('Legacy', 'poke-hub'); ?>
                                        </label>
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="button link-delete-row pokehub-remove-move-row">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                                <?php
                                    $charged_index++;
                                endforeach;
                            else :
                            ?>
                                <tr>
                                    <td>
                                        <select class="pokehub-move-select" name="charged_moves[0][attack_id]">
                                            <option value="0"><?php esc_html_e('— Select move —', 'poke-hub'); ?></option>
                                            <?php foreach ($all_charged_moves as $move) : ?>
                                                <option value="<?php echo (int) $move->id; ?>">
                                                    <?php echo esc_html($move->label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="charged_moves[0][is_legacy]"
                                                   value="1" />
                                            <?php esc_html_e('Legacy', 'poke-hub'); ?>
                                        </label>
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="button link-delete-row pokehub-remove-move-row">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" class="button button-secondary pokehub-add-charged-move">
                            <?php esc_html_e('Add charged move', 'poke-hub'); ?>
                        </button>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        </div> <!-- Fin section pokehub-section Attacks -->

        <?php
        // Templates pour JS (lignes vides)
        if (!empty($all_fast_moves) || !empty($all_charged_moves)) :
            ob_start();
            ?>
            <tr>
                <td>
                    <select class="pokehub-move-select" name="fast_moves[__INDEX__][attack_id]">
                        <option value="0"><?php esc_html_e('— Select move —', 'poke-hub'); ?></option>
                        <?php foreach ($all_fast_moves as $move) : ?>
                            <option value="<?php echo (int) $move->id; ?>">
                                <?php echo esc_html($move->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <label>
                        <input type="checkbox"
                               name="fast_moves[__INDEX__][is_legacy]"
                               value="1" />
                        <?php esc_html_e('Legacy', 'poke-hub'); ?>
                    </label>
                </td>
                <td>
                    <button type="button"
                            class="button link-delete-row pokehub-remove-move-row">
                        &times;
                    </button>
                </td>
            </tr>
            <?php
            $fast_template = trim(ob_get_clean());

            ob_start();
            ?>
            <tr>
                <td>
                    <select class="pokehub-move-select" name="charged_moves[__INDEX__][attack_id]">
                        <option value="0"><?php esc_html_e('— Select move —', 'poke-hub'); ?></option>
                        <?php foreach ($all_charged_moves as $move) : ?>
                            <option value="<?php echo (int) $move->id; ?>">
                                <?php echo esc_html($move->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <label>
                        <input type="checkbox"
                               name="charged_moves[__INDEX__][is_legacy]"
                               value="1" />
                        <?php esc_html_e('Legacy', 'poke-hub'); ?>
                    </label>
                </td>
                <td>
                    <button type="button"
                            class="button link-delete-row pokehub-remove-move-row">
                        &times;
                    </button>
                </td>
            </tr>
            <?php
            $charged_template = trim(ob_get_clean());

        // Construire pokemon_label_map AVANT le template pour qu'il soit disponible
        $pokemon_label_map = [];
        if (!empty($all_pokemon_for_evo)) {
            foreach ($all_pokemon_for_evo as $p_row) {
                $name_fr = $p_row->name_fr ?? '';
                $name_en = $p_row->name_en ?? '';
                $label   = $name_fr !== '' ? $name_fr : $name_en;
                $full    = sprintf(
                    '#%03d %s',
                    (int) $p_row->dex_number,
                    $label
                );
                if (!empty($p_row->variant_label)) {
                    $full .= ' (' . $p_row->variant_label . ')';
                } elseif (!empty($p_row->form_slug)) {
                    $full .= ' (' . $p_row->form_slug . ')';
                }
                $pokemon_label_map[(int) $p_row->id] = $full;
            }
        }

        // Template pour une ligne d'évolution
            ob_start();
            ?>
            <tr>
                <td>
                    <select class="pokehub-pokemon-select2 regular-text"
                            name="evolutions[__INDEX__][target_pokemon_id]"
                            data-placeholder="<?php esc_attr_e('Select target Pokémon', 'poke-hub'); ?>">
                        <option value="0"><?php esc_html_e('— Select target —', 'poke-hub'); ?></option>
                        <?php if (!empty($all_pokemon_for_evo)) : ?>
                            <?php
                            foreach ($all_pokemon_for_evo as $p_row) :
                                $label_full = $pokemon_label_map[(int) $p_row->id] ?? '';
                                if ($label_full === '') {
                                    // Fallback si pas dans le map
                                    $name_fr = $p_row->name_fr ?? '';
                                    $name_en = $p_row->name_en ?? '';
                                    $label   = $name_fr !== '' ? $name_fr : $name_en;
                                    $label_full = sprintf(
                                        '#%03d %s',
                                        (int) $p_row->dex_number,
                                        $label
                                    );
                                }
                                ?>
                                <option value="<?php echo (int) $p_row->id; ?>">
                                    <?php echo esc_html($label_full); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </td>
                <td>
                    <input type="number" class="small-text"
                           name="evolutions[__INDEX__][candy_cost]" value="0" />
                </td>
                <td>
                    <input type="number" class="small-text"
                           name="evolutions[__INDEX__][candy_cost_purified]" value="0" />
                </td>
                <td>
                    <label>
                        <input type="checkbox"
                               name="evolutions[__INDEX__][is_trade_evolution]"
                               value="1" />
                        <?php esc_html_e('Trade', 'poke-hub'); ?>
                    </label><br />
                    <label>
                        <input type="checkbox"
                               name="evolutions[__INDEX__][no_candy_cost_via_trade]"
                               value="1" />
                        <?php esc_html_e('No candy when traded', 'poke-hub'); ?>
                    </label><br />
                    <label>
                        <input type="checkbox"
                               name="evolutions[__INDEX__][is_random_evolution]"
                               value="1" />
                        <?php esc_html_e('Random', 'poke-hub'); ?>
                    </label>
                </td>
                <td>
                    <label class="pokehub-evolution-label">
                        <?php esc_html_e('Method', 'poke-hub'); ?>
                    </label>
                    <select name="evolutions[__INDEX__][method]" class="regular-text pokehub-evolution-method">
                        <option value=""><?php esc_html_e('Level up (default)', 'poke-hub'); ?></option>
                        <option value="levelup"><?php esc_html_e('Level up', 'poke-hub'); ?></option>
                        <option value="item"><?php esc_html_e('Item', 'poke-hub'); ?></option>
                        <option value="lure"><?php esc_html_e('Lure', 'poke-hub'); ?></option>
                        <option value="quest"><?php esc_html_e('Quest', 'poke-hub'); ?></option>
                        <option value="stats"><?php esc_html_e('Stats', 'poke-hub'); ?></option>
                        <option value="other"><?php esc_html_e('Other', 'poke-hub'); ?></option>
                    </select>
                    <br /><br />
                    
                    <!-- Méthode: Item -->
                    <div class="pokehub-evolution-conditional pokehub-evo-method-item" style="display: none;">
                        <label class="pokehub-evolution-label">
                            <?php esc_html_e('Item requirement', 'poke-hub'); ?>
                        </label>
                        <select class="pokehub-item-select2 regular-text"
                                name="evolutions[__INDEX__][item_requirement_slug]"
                                data-placeholder="<?php esc_attr_e('Select item', 'poke-hub'); ?>">
                            <option value=""><?php esc_html_e('No item required', 'poke-hub'); ?></option>
                            <?php if (!empty($all_evolution_items)) : ?>
                                <?php foreach ($all_evolution_items as $item) : ?>
                                    <?php
                                    $i_slug = (string) $item->slug;
                                    $i_label = !empty($item->name_fr) ? $item->name_fr : (!empty($item->name_en) ? $item->name_en : $i_slug);
                                    ?>
                                    <option value="<?php echo esc_attr($i_slug); ?>">
                                        <?php echo esc_html($i_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <br />
                        <label class="pokehub-evolution-label">
                            <?php esc_html_e('Item cost', 'poke-hub'); ?>
                        </label>
                        <input type="number" class="small-text"
                               name="evolutions[__INDEX__][item_requirement_cost]" value="0" />
                        <br /><br />
                    </div>
                    
                    <!-- Méthode: Lure -->
                    <div class="pokehub-evolution-conditional pokehub-evo-method-lure" style="display: none;">
                        <label class="pokehub-evolution-label">
                            <?php esc_html_e('Lure item', 'poke-hub'); ?>
                        </label>
                        <select class="pokehub-lure-select2 regular-text"
                                name="evolutions[__INDEX__][lure_item_slug]"
                                data-placeholder="<?php esc_attr_e('Select lure', 'poke-hub'); ?>">
                            <option value=""><?php esc_html_e('No lure required', 'poke-hub'); ?></option>
                            <?php if (!empty($all_lure_items)) : ?>
                                <?php foreach ($all_lure_items as $lure) : ?>
                                    <?php
                                    $l_slug = (string) $lure->slug;
                                    $l_label = !empty($lure->name_fr) ? $lure->name_fr : (!empty($lure->name_en) ? $lure->name_en : $l_slug);
                                    ?>
                                    <option value="<?php echo esc_attr($l_slug); ?>">
                                        <?php echo esc_html($l_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <br /><br />
                    </div>
                    
                    <!-- Méthode: Quest -->
                    <div class="pokehub-evolution-conditional pokehub-evo-method-quest" style="display: none;">
                        <label class="pokehub-evolution-label">
                            <?php esc_html_e('Quest template ID', 'poke-hub'); ?>
                        </label>
                        <input type="text" class="regular-text"
                               name="evolutions[__INDEX__][quest_template_id]"
                               placeholder="<?php esc_attr_e('Quest template ID', 'poke-hub'); ?>" />
                        <br /><br />
                    </div>
                    
                    <!-- Méthode: Stats -->
                    <div class="pokehub-evolution-conditional pokehub-evo-method-stats" style="display: none;">
                        <label class="pokehub-evolution-label">
                            <?php esc_html_e('Stat type', 'poke-hub'); ?>
                        </label>
                        <select name="evolutions[__INDEX__][stats_requirement_type]" class="regular-text">
                            <option value=""><?php esc_html_e('Select stat', 'poke-hub'); ?></option>
                            <option value="attack"><?php esc_html_e('Attack', 'poke-hub'); ?></option>
                            <option value="defense"><?php esc_html_e('Defense', 'poke-hub'); ?></option>
                            <option value="stamina"><?php esc_html_e('Stamina (HP)', 'poke-hub'); ?></option>
                        </select>
                        <br />
                        <label class="pokehub-evolution-label">
                            <?php esc_html_e('Stat condition', 'poke-hub'); ?>
                        </label>
                        <select name="evolutions[__INDEX__][stats_requirement_condition]" class="regular-text">
                            <option value=""><?php esc_html_e('Select condition', 'poke-hub'); ?></option>
                            <option value="min"><?php esc_html_e('Minimum', 'poke-hub'); ?></option>
                            <option value="max"><?php esc_html_e('Maximum', 'poke-hub'); ?></option>
                        </select>
                        <br /><br />
                    </div>
                    
                    <!-- Time of day (condition supplémentaire) -->
                    <div class="pokehub-evolution-conditional pokehub-evo-method-time" style="display: none;">
                        <label class="pokehub-evolution-label">
                            <?php esc_html_e('Time of day', 'poke-hub'); ?>
                        </label>
                        <select name="evolutions[__INDEX__][time_of_day]" class="regular-text">
                            <option value=""><?php esc_html_e('Any time', 'poke-hub'); ?></option>
                            <option value="day"><?php esc_html_e('Day', 'poke-hub'); ?></option>
                            <option value="night"><?php esc_html_e('Night', 'poke-hub'); ?></option>
                            <option value="dusk"><?php esc_html_e('Dusk', 'poke-hub'); ?></option>
                            <option value="full_moon"><?php esc_html_e('Full moon', 'poke-hub'); ?></option>
                        </select>
                        <br /><br />
                    </div>
                    
                    <!-- Toujours visible: Weather requirement -->
                    <label class="pokehub-evolution-label">
                        <?php esc_html_e('Weather requirement', 'poke-hub'); ?>
                    </label>
                    <select class="pokehub-weather-select2 regular-text"
                            name="evolutions[__INDEX__][weather_requirement_slug]"
                            data-placeholder="<?php esc_attr_e('Select weather (optional)', 'poke-hub'); ?>">
                        <option value=""><?php esc_html_e('No weather requirement', 'poke-hub'); ?></option>
                        <?php if (!empty($all_weathers)) : ?>
                            <?php foreach ($all_weathers as $weather) : ?>
                                <?php
                                $w_slug = (string) $weather->slug;
                                $w_label = !empty($weather->name_fr) ? $weather->name_fr : (!empty($weather->name_en) ? $weather->name_en : $w_slug);
                                ?>
                                <option value="<?php echo esc_attr($w_slug); ?>">
                                    <?php echo esc_html($w_label); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <br /><br />
                    
                    <!-- Toujours visible: Gender requirement -->
                    <label class="pokehub-evolution-label">
                        <?php esc_html_e('Gender requirement', 'poke-hub'); ?>
                    </label>
                    <select name="evolutions[__INDEX__][gender_requirement]" class="regular-text">
                        <option value=""><?php esc_html_e('Any gender', 'poke-hub'); ?></option>
                        <option value="MALE"><?php esc_html_e('Male only', 'poke-hub'); ?></option>
                        <option value="FEMALE"><?php esc_html_e('Female only', 'poke-hub'); ?></option>
                    </select>
                </td>
                <td>
                    <input type="number" class="small-text"
                           name="evolutions[__INDEX__][priority]" value="0" />
                </td>
                <td>
                    <button type="button"
                            class="button link-delete-row pokehub-remove-move-row">
                        &times;
                    </button>
                </td>
            </tr>
            <?php
            $evo_template = trim(ob_get_clean());
        endif;
        ?>

                <?php
        // ============================
        //  SECTION EVOLUTIONS
        // ============================
        ?>
        
        <!-- Section: Evolutions -->
        <div class="pokehub-section">
            <h2><?php esc_html_e('Pokémon GO – Evolutions', 'poke-hub'); ?></h2>

            <?php if (!$is_edit) : ?>
                <p class="description">
                    <?php esc_html_e('You must save the Pokémon once before managing evolutions.', 'poke-hub'); ?>
                </p>
            <?php else : ?>
            <?php if (empty($evolutions_out) && empty($evolutions_in) && empty($all_pokemon_for_evo)) : ?>
                <p class="description">
                    <?php esc_html_e('Evolution data will be available once the pokemon_evolutions table is ready.', 'poke-hub'); ?>
                </p>
            <?php else : ?>

                <?php if (!empty($evolutions_in)) : ?>
                    <h3><?php esc_html_e('Pre-evolutions (read-only)', 'poke-hub'); ?></h3>
                    <table class="widefat fixed striped">
                        <thead>
                        <tr>
                            <th><?php esc_html_e('From', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Candy cost', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Purified cost', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Method / notes', 'poke-hub'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($evolutions_in as $row) : ?>
                            <?php
                            $from_name_fr = $row->base_name_fr ?? '';
                            $from_name_en = $row->base_name_en ?? '';
                            $from_label   = $from_name_fr !== '' ? $from_name_fr : $from_name_en;

                            $from_label_full = sprintf(
                                '#%03d %s',
                                (int) ($row->base_dex_number ?? 0),
                                $from_label
                            );

                            if (!empty($row->base_variant_label)) {
                                $from_label_full .= ' (' . $row->base_variant_label . ')';
                            } elseif (!empty($row->base_form_slug)) {
                                $from_label_full .= ' (' . $row->base_form_slug . ')';
                            }

                            $method_parts = [];
                            if (!empty($row->method)) {
                                $method_parts[] = $row->method;
                            }
                            if (!empty($row->item_requirement_slug)) {
                                $method_parts[] = sprintf(
                                    __('Item: %s', 'poke-hub'),
                                    $row->item_requirement_slug
                                );
                            }
                            if (!empty($row->lure_item_slug)) {
                                $method_parts[] = sprintf(
                                    __('Lure: %s', 'poke-hub'),
                                    $row->lure_item_slug
                                );
                            }
                            if (!empty($row->weather_requirement_slug)) {
                                $method_parts[] = sprintf(
                                    __('Weather: %s', 'poke-hub'),
                                    $row->weather_requirement_slug
                                );
                            }
                            if (!empty($row->gender_requirement)) {
                                $method_parts[] = sprintf(
                                    __('Gender: %s', 'poke-hub'),
                                    $row->gender_requirement
                                );
                            }
                            if (!empty($row->time_of_day)) {
                                $time_label = $row->time_of_day;
                                switch ($row->time_of_day) {
                                    case 'day':
                                    case 'DAY':
                                        $time_label = __('Day', 'poke-hub');
                                        break;
                                    case 'night':
                                    case 'NIGHT':
                                        $time_label = __('Night', 'poke-hub');
                                        break;
                                    case 'dusk':
                                    case 'DUSK':
                                        $time_label = __('Dusk', 'poke-hub');
                                        break;
                                    case 'full_moon':
                                    case 'FULL_MOON':
                                        $time_label = __('Full moon', 'poke-hub');
                                        break;
                                }

                                $method_parts[] = sprintf(
                                    __('Time: %s', 'poke-hub'),
                                    $time_label
                                );
                            }
                            if (!empty($row->quest_template_id)) {
                                $method_parts[] = sprintf(
                                    __('Quest: %s', 'poke-hub'),
                                    $row->quest_template_id
                                );
                            }
                            if (!empty($row->is_trade_evolution)) {
                                $method_parts[] = __('Trade evolution', 'poke-hub');
                            }
                            if (!empty($row->no_candy_cost_via_trade)) {
                                $method_parts[] = __('No candy cost when traded', 'poke-hub');
                            }
                            if (!empty($row->is_random_evolution)) {
                                $method_parts[] = __('Random evolution', 'poke-hub');
                            }

                            $method_desc = !empty($method_parts) ? implode(' · ', $method_parts) : '&mdash;';
                            ?>
                            <tr>
                                <td><?php echo esc_html($from_label_full); ?></td>
                                <td><?php echo (int) $row->candy_cost; ?></td>
                                <td><?php echo (int) $row->candy_cost_purified; ?></td>
                                <td><?php echo wp_kses_post($method_desc); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h3><?php esc_html_e('Evolves into', 'poke-hub'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Manage all evolution branches starting from this Pokémon and this form.', 'poke-hub'); ?>
                </p>

                <?php if (empty($all_pokemon_for_evo)) : ?>
                    <p><em><?php esc_html_e('No Pokémon found to build evolution list.', 'poke-hub'); ?></em></p>
                <?php else : ?>
                    <table class="widefat fixed striped">
                        <thead>
                        <tr>
                            <th><?php esc_html_e('Target Pokémon', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Candy (normal)', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Candy (purified)', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Flags', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Conditions', 'poke-hub'); ?></th>
                            <th class="pokehub-col-priority"><?php esc_html_e('Priority', 'poke-hub'); ?></th>
                            <th class="pokehub-col-remove-small"><?php esc_html_e('Remove', 'poke-hub'); ?></th>
                        </tr>
                        </thead>
                        <tbody class="pokehub-evolutions-rows" data-next-index="<?php
                            echo max(count($evolutions_out), 1);
                        ?>">
                        <?php
                        $evo_index = 0;

                        // Helper pour label Pokémon
                        $pokemon_label_map = [];
                        foreach ($all_pokemon_for_evo as $p_row) {
                            $name_fr = $p_row->name_fr ?? '';
                            $name_en = $p_row->name_en ?? '';
                            $label   = $name_fr !== '' ? $name_fr : $name_en;
                            $full    = sprintf(
                                '#%03d %s',
                                (int) $p_row->dex_number,
                                $label
                            );
                            if (!empty($p_row->variant_label)) {
                                $full .= ' (' . $p_row->variant_label . ')';
                            } elseif (!empty($p_row->form_slug)) {
                                $full .= ' (' . $p_row->form_slug . ')';
                            }
                            $pokemon_label_map[(int) $p_row->id] = $full;
                        }

                        if (!empty($evolutions_out)) :
                            foreach ($evolutions_out as $row) :
                                $target_id = (int) $row->target_pokemon_id;
                                $candy     = (int) $row->candy_cost;
                                $candy_pur = (int) $row->candy_cost_purified;
                                
                                // Décoder extra pour récupérer les stats requirements
                                $row_extra = [];
                                $stats_type = '';
                                $stats_condition = '';
                                if (!empty($row->extra)) {
                                    $row_extra = json_decode($row->extra, true);
                                    if (is_array($row_extra)) {
                                        $stats_type = $row_extra['stats_requirement_type'] ?? '';
                                        $stats_condition = $row_extra['stats_requirement_condition'] ?? '';
                                    }
                                }
                                
                                $current_method = $row->method ?? '';
                                ?>
                                <tr>
                                    <td>
                                        <select class="pokehub-pokemon-select2 regular-text"
                                                name="evolutions[<?php echo (int) $evo_index; ?>][target_pokemon_id]"
                                                data-placeholder="<?php esc_attr_e('Select target Pokémon', 'poke-hub'); ?>">
                                            <option value="0"><?php esc_html_e('— Select target —', 'poke-hub'); ?></option>
                                            <?php foreach ($all_pokemon_for_evo as $p_row) : ?>
                                                <?php
                                                $label_full = $pokemon_label_map[(int) $p_row->id] ?? '';
                                                ?>
                                                <option value="<?php echo (int) $p_row->id; ?>"
                                                    <?php selected($target_id, (int) $p_row->id); ?>>
                                                    <?php echo esc_html($label_full); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="small-text"
                                               name="evolutions[<?php echo (int) $evo_index; ?>][candy_cost]"
                                               value="<?php echo esc_attr($candy); ?>" />
                                    </td>
                                    <td>
                                        <input type="number" class="small-text"
                                               name="evolutions[<?php echo (int) $evo_index; ?>][candy_cost_purified]"
                                               value="<?php echo esc_attr($candy_pur); ?>" />
                                    </td>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="evolutions[<?php echo (int) $evo_index; ?>][is_trade_evolution]"
                                                   value="1" <?php checked(!empty($row->is_trade_evolution)); ?> />
                                            <?php esc_html_e('Trade', 'poke-hub'); ?>
                                        </label><br />
                                        <label>
                                            <input type="checkbox"
                                                   name="evolutions[<?php echo (int) $evo_index; ?>][no_candy_cost_via_trade]"
                                                   value="1" <?php checked(!empty($row->no_candy_cost_via_trade)); ?> />
                                            <?php esc_html_e('No candy when traded', 'poke-hub'); ?>
                                        </label><br />
                                        <label>
                                            <input type="checkbox"
                                                   name="evolutions[<?php echo (int) $evo_index; ?>][is_random_evolution]"
                                                   value="1" <?php checked(!empty($row->is_random_evolution)); ?> />
                                            <?php esc_html_e('Random', 'poke-hub'); ?>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="pokehub-evolution-label">
                                            <?php esc_html_e('Method', 'poke-hub'); ?>
                                        </label>
                                        <select name="evolutions[<?php echo (int) $evo_index; ?>][method]" class="regular-text pokehub-evolution-method">
                                            <option value=""><?php esc_html_e('Level up (default)', 'poke-hub'); ?></option>
                                            <option value="levelup" <?php selected($current_method, 'levelup'); ?>><?php esc_html_e('Level up', 'poke-hub'); ?></option>
                                            <option value="item" <?php selected($current_method, 'item'); ?>><?php esc_html_e('Item', 'poke-hub'); ?></option>
                                            <option value="lure" <?php selected($current_method, 'lure'); ?>><?php esc_html_e('Lure', 'poke-hub'); ?></option>
                                            <option value="quest" <?php selected($current_method, 'quest'); ?>><?php esc_html_e('Quest', 'poke-hub'); ?></option>
                                            <option value="stats" <?php selected($current_method, 'stats'); ?>><?php esc_html_e('Stats', 'poke-hub'); ?></option>
                                            <option value="other" <?php selected($current_method, 'other'); ?>><?php esc_html_e('Other', 'poke-hub'); ?></option>
                                        </select>
                                        <br /><br />
                                        
                                        <!-- Méthode: Item -->
                                        <div class="pokehub-evolution-conditional pokehub-evo-method-item" style="display: <?php echo ($current_method === 'item') ? 'block' : 'none'; ?>;">
                                            <label class="pokehub-evolution-label">
                                                <?php esc_html_e('Item requirement', 'poke-hub'); ?>
                                            </label>
                                            <select class="pokehub-item-select2 regular-text"
                                                    name="evolutions[<?php echo (int) $evo_index; ?>][item_requirement_slug]"
                                                    data-placeholder="<?php esc_attr_e('Select item', 'poke-hub'); ?>">
                                                <option value=""><?php esc_html_e('No item required', 'poke-hub'); ?></option>
                                                <?php if (!empty($all_evolution_items)) : ?>
                                                    <?php foreach ($all_evolution_items as $item) : ?>
                                                        <?php
                                                        $i_slug = (string) $item->slug;
                                                        $i_label = !empty($item->name_fr) ? $item->name_fr : (!empty($item->name_en) ? $item->name_en : $i_slug);
                                                        $i_selected = ($row->item_requirement_slug ?? '') === $i_slug;
                                                        ?>
                                                        <option value="<?php echo esc_attr($i_slug); ?>" <?php selected($i_selected); ?>>
                                                            <?php echo esc_html($i_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                            <br />
                                            <label class="pokehub-evolution-label">
                                                <?php esc_html_e('Item cost', 'poke-hub'); ?>
                                            </label>
                                            <input type="number" class="small-text"
                                                   name="evolutions[<?php echo (int) $evo_index; ?>][item_requirement_cost]"
                                                   value="<?php echo esc_attr((int) ($row->item_requirement_cost ?? 0)); ?>" />
                                            <br /><br />
                                        </div>
                                        
                                        <!-- Méthode: Lure -->
                                        <div class="pokehub-evolution-conditional pokehub-evo-method-lure" style="display: <?php echo ($current_method === 'lure') ? 'block' : 'none'; ?>;">
                                            <label class="pokehub-evolution-label">
                                                <?php esc_html_e('Lure item', 'poke-hub'); ?>
                                            </label>
                                            <select class="pokehub-lure-select2 regular-text"
                                                    name="evolutions[<?php echo (int) $evo_index; ?>][lure_item_slug]"
                                                    data-placeholder="<?php esc_attr_e('Select lure', 'poke-hub'); ?>">
                                                <option value=""><?php esc_html_e('No lure required', 'poke-hub'); ?></option>
                                                <?php if (!empty($all_lure_items)) : ?>
                                                    <?php foreach ($all_lure_items as $lure) : ?>
                                                        <?php
                                                        $l_slug = (string) $lure->slug;
                                                        $l_label = !empty($lure->name_fr) ? $lure->name_fr : (!empty($lure->name_en) ? $lure->name_en : $l_slug);
                                                        $l_selected = ($row->lure_item_slug ?? '') === $l_slug;
                                                        ?>
                                                        <option value="<?php echo esc_attr($l_slug); ?>" <?php selected($l_selected); ?>>
                                                            <?php echo esc_html($l_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                            <br /><br />
                                        </div>
                                        
                                        <!-- Méthode: Quest -->
                                        <div class="pokehub-evolution-conditional pokehub-evo-method-quest" style="display: <?php echo ($current_method === 'quest') ? 'block' : 'none'; ?>;">
                                            <label class="pokehub-evolution-label">
                                                <?php esc_html_e('Quest template ID', 'poke-hub'); ?>
                                            </label>
                                            <input type="text" class="regular-text"
                                                   name="evolutions[<?php echo (int) $evo_index; ?>][quest_template_id]"
                                                   value="<?php echo esc_attr($row->quest_template_id ?? ''); ?>"
                                                   placeholder="<?php esc_attr_e('Quest template ID', 'poke-hub'); ?>" />
                                            <br /><br />
                                        </div>
                                        
                                        <!-- Méthode: Stats -->
                                        <div class="pokehub-evolution-conditional pokehub-evo-method-stats" style="display: <?php echo ($current_method === 'stats') ? 'block' : 'none'; ?>;">
                                            <label class="pokehub-evolution-label">
                                                <?php esc_html_e('Stat type', 'poke-hub'); ?>
                                            </label>
                                            <select name="evolutions[<?php echo (int) $evo_index; ?>][stats_requirement_type]" class="regular-text">
                                                <option value=""><?php esc_html_e('Select stat', 'poke-hub'); ?></option>
                                                <option value="attack" <?php selected($stats_type, 'attack'); ?>><?php esc_html_e('Attack', 'poke-hub'); ?></option>
                                                <option value="defense" <?php selected($stats_type, 'defense'); ?>><?php esc_html_e('Defense', 'poke-hub'); ?></option>
                                                <option value="stamina" <?php selected($stats_type, 'stamina'); ?>><?php esc_html_e('Stamina (HP)', 'poke-hub'); ?></option>
                                            </select>
                                            <br />
                                            <label class="pokehub-evolution-label">
                                                <?php esc_html_e('Stat condition', 'poke-hub'); ?>
                                            </label>
                                            <select name="evolutions[<?php echo (int) $evo_index; ?>][stats_requirement_condition]" class="regular-text">
                                                <option value=""><?php esc_html_e('Select condition', 'poke-hub'); ?></option>
                                                <option value="min" <?php selected($stats_condition, 'min'); ?>><?php esc_html_e('Minimum', 'poke-hub'); ?></option>
                                                <option value="max" <?php selected($stats_condition, 'max'); ?>><?php esc_html_e('Maximum', 'poke-hub'); ?></option>
                                            </select>
                                            <br /><br />
                                        </div>
                                        
                                        <!-- Time of day (condition supplémentaire) -->
                                        <div class="pokehub-evolution-conditional pokehub-evo-method-time" style="display: <?php echo (!empty($row->time_of_day)) ? 'block' : 'none'; ?>;">
                                            <label class="pokehub-evolution-label">
                                                <?php esc_html_e('Time of day', 'poke-hub'); ?>
                                            </label>
                                            <select name="evolutions[<?php echo (int) $evo_index; ?>][time_of_day]" class="regular-text">
                                                <option value=""><?php esc_html_e('Any time', 'poke-hub'); ?></option>
                                                <option value="day" <?php selected(strtolower($row->time_of_day ?? ''), 'day'); ?>>
                                                    <?php esc_html_e('Day', 'poke-hub'); ?>
                                                </option>
                                                <option value="night" <?php selected(strtolower($row->time_of_day ?? ''), 'night'); ?>>
                                                    <?php esc_html_e('Night', 'poke-hub'); ?>
                                                </option>
                                                <option value="dusk" <?php selected(strtolower($row->time_of_day ?? ''), 'dusk'); ?>>
                                                    <?php esc_html_e('Dusk', 'poke-hub'); ?>
                                                </option>
                                                <option value="full_moon" <?php selected(strtolower($row->time_of_day ?? ''), 'full_moon'); ?>>
                                                    <?php esc_html_e('Full moon', 'poke-hub'); ?>
                                                </option>
                                            </select>
                                            <br /><br />
                                        </div>
                                        
                                        <!-- Toujours visible: Weather requirement -->
                                        <label class="pokehub-evolution-label">
                                            <?php esc_html_e('Weather requirement', 'poke-hub'); ?>
                                        </label>
                                        <select class="pokehub-weather-select2 regular-text"
                                                name="evolutions[<?php echo (int) $evo_index; ?>][weather_requirement_slug]"
                                                data-placeholder="<?php esc_attr_e('Select weather (optional)', 'poke-hub'); ?>">
                                            <option value=""><?php esc_html_e('No weather requirement', 'poke-hub'); ?></option>
                                            <?php if (!empty($all_weathers)) : ?>
                                                <?php foreach ($all_weathers as $weather) : ?>
                                                    <?php
                                                    $w_slug = (string) $weather->slug;
                                                    $w_label = !empty($weather->name_fr) ? $weather->name_fr : (!empty($weather->name_en) ? $weather->name_en : $w_slug);
                                                    $w_selected = ($row->weather_requirement_slug ?? '') === $w_slug;
                                                    ?>
                                                    <option value="<?php echo esc_attr($w_slug); ?>" <?php selected($w_selected); ?>>
                                                        <?php echo esc_html($w_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <br /><br />
                                        
                                        <!-- Toujours visible: Gender requirement -->
                                        <label class="pokehub-evolution-label">
                                            <?php esc_html_e('Gender requirement', 'poke-hub'); ?>
                                        </label>
                                        <select name="evolutions[<?php echo (int) $evo_index; ?>][gender_requirement]" class="regular-text">
                                            <option value=""><?php esc_html_e('Any gender', 'poke-hub'); ?></option>
                                            <option value="MALE" <?php selected(($row->gender_requirement ?? ''), 'MALE'); ?>>
                                                <?php esc_html_e('Male only', 'poke-hub'); ?>
                                            </option>
                                            <option value="FEMALE" <?php selected(($row->gender_requirement ?? ''), 'FEMALE'); ?>>
                                                <?php esc_html_e('Female only', 'poke-hub'); ?>
                                            </option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="small-text"
                                               name="evolutions[<?php echo (int) $evo_index; ?>][priority]"
                                               value="<?php echo esc_attr((int) ($row->priority ?? 0)); ?>" />
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="button link-delete-row pokehub-remove-move-row">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                                <?php
                                $evo_index++;
                            endforeach;
                        else :
                            // Ligne vide par défaut
                            ?>
                            <tr>
                                <td>
                                    <select class="pokehub-pokemon-select2 regular-text"
                                            name="evolutions[0][target_pokemon_id]"
                                            data-placeholder="<?php esc_attr_e('Select target Pokémon', 'poke-hub'); ?>">
                                        <option value="0"><?php esc_html_e('— Select target —', 'poke-hub'); ?></option>
                                        <?php foreach ($all_pokemon_for_evo as $p_row) : ?>
                                            <?php
                                            $label_full = $pokemon_label_map[(int) $p_row->id] ?? '';
                                            ?>
                                            <option value="<?php echo (int) $p_row->id; ?>">
                                                <?php echo esc_html($label_full); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="small-text"
                                           name="evolutions[0][candy_cost]" value="0" />
                                </td>
                                <td>
                                    <input type="number" class="small-text"
                                           name="evolutions[0][candy_cost_purified]" value="0" />
                                </td>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="evolutions[0][is_trade_evolution]"
                                               value="1" />
                                        <?php esc_html_e('Trade', 'poke-hub'); ?>
                                    </label><br />
                                    <label>
                                        <input type="checkbox"
                                               name="evolutions[0][no_candy_cost_via_trade]"
                                               value="1" />
                                        <?php esc_html_e('No candy when traded', 'poke-hub'); ?>
                                    </label><br />
                                    <label>
                                        <input type="checkbox"
                                               name="evolutions[0][is_random_evolution]"
                                               value="1" />
                                        <?php esc_html_e('Random', 'poke-hub'); ?>
                                    </label>
                                </td>
                                <td>
                                    <label class="pokehub-evolution-label">
                                        <?php esc_html_e('Method', 'poke-hub'); ?>
                                    </label>
                                    <select name="evolutions[0][method]" class="regular-text pokehub-evolution-method">
                                        <option value=""><?php esc_html_e('Level up (default)', 'poke-hub'); ?></option>
                                        <option value="levelup"><?php esc_html_e('Level up', 'poke-hub'); ?></option>
                                        <option value="item"><?php esc_html_e('Item', 'poke-hub'); ?></option>
                                        <option value="lure"><?php esc_html_e('Lure', 'poke-hub'); ?></option>
                                        <option value="quest"><?php esc_html_e('Quest', 'poke-hub'); ?></option>
                                        <option value="stats"><?php esc_html_e('Stats', 'poke-hub'); ?></option>
                                        <option value="other"><?php esc_html_e('Other', 'poke-hub'); ?></option>
                                    </select>
                                    <br /><br />
                                    
                                    <!-- Méthode: Item -->
                                    <div class="pokehub-evolution-conditional pokehub-evo-method-item" style="display: none;">
                                        <label class="pokehub-evolution-label">
                                            <?php esc_html_e('Item requirement', 'poke-hub'); ?>
                                        </label>
                                        <select class="pokehub-item-select2 regular-text"
                                                name="evolutions[0][item_requirement_slug]"
                                                data-placeholder="<?php esc_attr_e('Select item', 'poke-hub'); ?>">
                                            <option value=""><?php esc_html_e('No item required', 'poke-hub'); ?></option>
                                            <?php if (!empty($all_evolution_items)) : ?>
                                                <?php foreach ($all_evolution_items as $item) : ?>
                                                    <?php
                                                    $i_slug = (string) $item->slug;
                                                    $i_label = !empty($item->name_fr) ? $item->name_fr : (!empty($item->name_en) ? $item->name_en : $i_slug);
                                                    ?>
                                                    <option value="<?php echo esc_attr($i_slug); ?>">
                                                        <?php echo esc_html($i_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <br />
                                        <label class="pokehub-evolution-label">
                                            <?php esc_html_e('Item cost', 'poke-hub'); ?>
                                        </label>
                                        <input type="number" class="small-text"
                                               name="evolutions[0][item_requirement_cost]" value="0" />
                                        <br /><br />
                                    </div>
                                    
                                    <!-- Méthode: Lure -->
                                    <div class="pokehub-evolution-conditional pokehub-evo-method-lure" style="display: none;">
                                        <label class="pokehub-evolution-label">
                                            <?php esc_html_e('Lure item', 'poke-hub'); ?>
                                        </label>
                                        <select class="pokehub-lure-select2 regular-text"
                                                name="evolutions[0][lure_item_slug]"
                                                data-placeholder="<?php esc_attr_e('Select lure', 'poke-hub'); ?>">
                                            <option value=""><?php esc_html_e('No lure required', 'poke-hub'); ?></option>
                                            <?php if (!empty($all_lure_items)) : ?>
                                                <?php foreach ($all_lure_items as $lure) : ?>
                                                    <?php
                                                    $l_slug = (string) $lure->slug;
                                                    $l_label = !empty($lure->name_fr) ? $lure->name_fr : (!empty($lure->name_en) ? $lure->name_en : $l_slug);
                                                    ?>
                                                    <option value="<?php echo esc_attr($l_slug); ?>">
                                                        <?php echo esc_html($l_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <br /><br />
                                    </div>
                                    
                                    <!-- Méthode: Quest -->
                                    <div class="pokehub-evolution-conditional pokehub-evo-method-quest" style="display: none;">
                                        <label class="pokehub-evolution-label">
                                            <?php esc_html_e('Quest template ID', 'poke-hub'); ?>
                                        </label>
                                        <input type="text" class="regular-text"
                                               name="evolutions[0][quest_template_id]"
                                               placeholder="<?php esc_attr_e('Quest template ID', 'poke-hub'); ?>" />
                                        <br /><br />
                                    </div>
                                    
                                    <!-- Méthode: Stats -->
                                    <div class="pokehub-evolution-conditional pokehub-evo-method-stats" style="display: none;">
                                        <label class="pokehub-evolution-label">
                                            <?php esc_html_e('Stat type', 'poke-hub'); ?>
                                        </label>
                                        <select name="evolutions[0][stats_requirement_type]" class="regular-text">
                                            <option value=""><?php esc_html_e('Select stat', 'poke-hub'); ?></option>
                                            <option value="attack"><?php esc_html_e('Attack', 'poke-hub'); ?></option>
                                            <option value="defense"><?php esc_html_e('Defense', 'poke-hub'); ?></option>
                                            <option value="stamina"><?php esc_html_e('Stamina (HP)', 'poke-hub'); ?></option>
                                        </select>
                                        <br />
                                        <label class="pokehub-evolution-label">
                                            <?php esc_html_e('Stat condition', 'poke-hub'); ?>
                                        </label>
                                        <select name="evolutions[0][stats_requirement_condition]" class="regular-text">
                                            <option value=""><?php esc_html_e('Select condition', 'poke-hub'); ?></option>
                                            <option value="min"><?php esc_html_e('Minimum', 'poke-hub'); ?></option>
                                            <option value="max"><?php esc_html_e('Maximum', 'poke-hub'); ?></option>
                                        </select>
                                        <br /><br />
                                    </div>
                                    
                                    <!-- Time of day (condition supplémentaire) -->
                                    <div class="pokehub-evolution-conditional pokehub-evo-method-time" style="display: none;">
                                        <label class="pokehub-evolution-label">
                                            <?php esc_html_e('Time of day', 'poke-hub'); ?>
                                        </label>
                                        <select name="evolutions[0][time_of_day]" class="regular-text">
                                            <option value=""><?php esc_html_e('Any time', 'poke-hub'); ?></option>
                                            <option value="day"><?php esc_html_e('Day', 'poke-hub'); ?></option>
                                            <option value="night"><?php esc_html_e('Night', 'poke-hub'); ?></option>
                                            <option value="dusk"><?php esc_html_e('Dusk', 'poke-hub'); ?></option>
                                            <option value="full_moon"><?php esc_html_e('Full moon', 'poke-hub'); ?></option>
                                        </select>
                                        <br /><br />
                                    </div>
                                    
                                    <!-- Toujours visible: Weather requirement -->
                                    <label class="pokehub-evolution-label">
                                        <?php esc_html_e('Weather requirement', 'poke-hub'); ?>
                                    </label>
                                    <select class="pokehub-weather-select2 regular-text"
                                            name="evolutions[0][weather_requirement_slug]"
                                            data-placeholder="<?php esc_attr_e('Select weather (optional)', 'poke-hub'); ?>">
                                        <option value=""><?php esc_html_e('No weather requirement', 'poke-hub'); ?></option>
                                        <?php if (!empty($all_weathers)) : ?>
                                            <?php foreach ($all_weathers as $weather) : ?>
                                                <?php
                                                $w_slug = (string) $weather->slug;
                                                $w_label = !empty($weather->name_fr) ? $weather->name_fr : (!empty($weather->name_en) ? $weather->name_en : $w_slug);
                                                ?>
                                                <option value="<?php echo esc_attr($w_slug); ?>">
                                                    <?php echo esc_html($w_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <br /><br />
                                    
                                    <!-- Toujours visible: Gender requirement -->
                                    <label class="pokehub-evolution-label">
                                        <?php esc_html_e('Gender requirement', 'poke-hub'); ?>
                                    </label>
                                    <select name="evolutions[0][gender_requirement]" class="regular-text">
                                        <option value=""><?php esc_html_e('Any gender', 'poke-hub'); ?></option>
                                        <option value="MALE"><?php esc_html_e('Male only', 'poke-hub'); ?></option>
                                        <option value="FEMALE"><?php esc_html_e('Female only', 'poke-hub'); ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="small-text"
                                           name="evolutions[0][priority]" value="0" />
                                </td>
                                <td>
                                    <button type="button"
                                            class="button link-delete-row pokehub-remove-move-row">
                                        &times;
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <p>
                        <button type="button" class="button button-secondary pokehub-add-evolution">
                            <?php esc_html_e('Add evolution', 'poke-hub'); ?>
                        </button>
                    </p>
                <?php endif; // all_pokemon_for_evo ?>
            <?php endif; // table ready ?>
        <?php endif; // is_edit ?>
        </div> <!-- Fin section pokehub-section Evolutions -->

        <?php
        submit_button(
            $is_edit
                ? __('Update Pokémon', 'poke-hub')
                : __('Add Pokémon', 'poke-hub')
        );
        ?>
    </form>
</div>

<?php if ( ! empty( $all_fast_moves ) || ! empty( $all_charged_moves ) || ! empty( $all_pokemon_for_evo ) ) : ?>
    <script>
    (function() {
        function addRow(tbodySelector, templateHtml, dataAttrName) {
            var tbody = document.querySelector(tbodySelector);
            if (!tbody) return;

            var nextIndex = parseInt(tbody.getAttribute(dataAttrName) || '0', 10);
            if (!templateHtml) return;

            var html = templateHtml.replace(/__INDEX__/g, String(nextIndex));

            var temp = document.createElement('tbody');
            temp.innerHTML = html;
            var row = temp.querySelector('tr');
            if (!row) return;

            tbody.appendChild(row);
            tbody.setAttribute(dataAttrName, String(nextIndex + 1));

            // Ré-init Select2 sur la nouvelle ligne si dispo
            if (window.pokehubInitAttackSelect2) {
                window.pokehubInitAttackSelect2(row);
            }
            
            // Ré-init Select2 sur les champs météo, items, leurres et Pokémon
            if (window.pokehubInitWeatherSelect2) {
                window.pokehubInitWeatherSelect2(row);
            }
            if (window.pokehubInitItemSelect2) {
                window.pokehubInitItemSelect2(row);
            }
            if (window.pokehubInitLureSelect2) {
                window.pokehubInitLureSelect2(row);
            }
            if (window.pokehubInitPokemonSelect2) {
                window.pokehubInitPokemonSelect2(row);
            }
            
            // Initialiser l'affichage conditionnel pour la nouvelle ligne
            var methodSelect = row.querySelector('.pokehub-evolution-method');
            if (methodSelect && window.pokehubToggleEvolutionFields) {
                window.pokehubToggleEvolutionFields(methodSelect);
            }
        }

        document.addEventListener('click', function (e) {

            // Ajout fast move
            if (e.target && e.target.classList.contains('pokehub-add-fast-move')) {
                e.preventDefault();
                var templateFast = <?php echo wp_json_encode( isset( $fast_template ) ? $fast_template : '' ); ?>;
                addRow('.pokehub-fast-moves-rows', templateFast, 'data-next-index');
            }

            // Ajout charged move
            if (e.target && e.target.classList.contains('pokehub-add-charged-move')) {
                e.preventDefault();
                var templateCharged = <?php echo wp_json_encode( isset( $charged_template ) ? $charged_template : '' ); ?>;
                addRow('.pokehub-charged-moves-rows', templateCharged, 'data-next-index');
            }

            // Ajout evolution
            if (e.target && e.target.classList.contains('pokehub-add-evolution')) {
                e.preventDefault();
                var templateEvo = <?php echo wp_json_encode( isset( $evo_template ) ? $evo_template : '' ); ?>;
                addRow('.pokehub-evolutions-rows', templateEvo, 'data-next-index');
            }

            // Suppression d'une ligne (moves + évolutions)
            if (e.target && e.target.classList.contains('pokehub-remove-move-row')) {
                e.preventDefault();
                var row = e.target.closest('tr');
                if (row && row.parentNode) {
                    row.parentNode.removeChild(row);
                }
            }
        });
    })();
    </script>
<?php endif; ?>
<?php
}
