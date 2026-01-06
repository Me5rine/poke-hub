<?php
// modules/pokemon/admin/forms/move-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit d'une attaque (move)
 *
 * @param object|null $edit_row Ligne existante depuis la table "attacks" ou null
 */
function poke_hub_pokemon_attacks_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    global $wpdb;

    $is_edit   = ($edit_row !== null);
    $attack_id = $is_edit ? (int) $edit_row->id : 0;

    // Jeu principal (pour l'instant : Pokémon GO uniquement)
    $game_key = 'pokemon_go';

    // ---------- NOMS MULTILINGUES (FR / EN) + fallback ancienne colonne "name" ----------
    $current_name_fr = '';
    $current_name_en = '';

    if ($is_edit) {
        if (isset($edit_row->name_fr)) {
            $current_name_fr = (string) $edit_row->name_fr;
        }
        if (isset($edit_row->name_en)) {
            $current_name_en = (string) $edit_row->name_en;
        }

        // Compat rétro : si les 2 sont vides mais qu'on a encore "name"
        if ($current_name_fr === '' && $current_name_en === '' && isset($edit_row->name)) {
            $current_name_fr = (string) $edit_row->name;
        }
    }

    // ---------- Catégorie du move (fast / charged / autre) ----------
    $current_category = '';
    if ($is_edit && isset($edit_row->category)) {
        $current_category = sanitize_key($edit_row->category);
    }

    // ---------- Types disponibles ----------
    $types_table = pokehub_get_table('pokemon_types');
    $all_types   = [];
    if ($types_table) {
        $all_types = $wpdb->get_results(
            "SELECT 
                id,
                slug,
                color,
                COALESCE(name_fr, name_en) AS label,
                name_fr,
                name_en
             FROM {$types_table}
             ORDER BY sort_order ASC, name_fr ASC, name_en ASC"
        );
    }

    // Types déjà liés à ce move
    $selected_type_ids = [];
    if ($is_edit && $attack_id > 0) {
        $link_table = pokehub_get_table('attack_type_links');
        if ($link_table) {
            $rows = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT type_id FROM {$link_table} WHERE attack_id = %d",
                    $attack_id
                )
            );
            $selected_type_ids = array_map('intval', (array) $rows);
        }
    }

    // On stocke le jeu principal dans extra
    $extra = [];
    if ($is_edit && !empty($edit_row->extra)) {
        $decoded = json_decode($edit_row->extra, true);
        if (is_array($decoded)) {
            $extra = $decoded;
        }
    }
    if (!empty($extra['game_key'])) {
        $game_key = sanitize_key($extra['game_key']);
    }

    // ---------- Stats existantes pour Pokémon GO ----------
    // Stats globales (context='')
    $global = [
        'duration_ms'            => 0,
        'damage_window_start_ms' => 0,
        'damage_window_end_ms'   => 0,
    ];

    // Stats PvE (context='pve')
    $pve = [
        'damage' => 0,
        'dps'    => 0,
        'eps'    => 0,
        'energy' => 0,
    ];

    // Stats PvP (context='pvp')
    $pvp = [
        'damage' => 0,
        'dps'    => 0,
        'eps'    => 0,
        'energy' => 0,
        'duration_ms' => 0,
        'turns' => 0,
        'dpt' => 0.0,
        'ept' => 0.0,
        'buffs' => [
            'buff_activation_chance' => 0.0,
            'attacker_attack_stat_stage_change' => 0,
            'attacker_defense_stat_stage_change' => 0,
            'target_attack_stat_stage_change' => 0,
            'target_defense_stat_stage_change' => 0,
        ],
    ];

    if ($is_edit && $attack_id > 0) {
        $stats_table = pokehub_get_table('attack_stats');

        if ($stats_table) {
            // Global stats
            $row_global = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$stats_table}
                     WHERE attack_id = %d AND game_key = %s AND context = %s LIMIT 1",
                    $attack_id,
                    $game_key,
                    ''
                )
            );
            if ($row_global) {
                $global['duration_ms']            = (int) $row_global->duration_ms;
                $global['damage_window_start_ms'] = (int) $row_global->damage_window_start_ms;
                $global['damage_window_end_ms']   = (int) $row_global->damage_window_end_ms;
            }

            // PvE stats
            $row_pve = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$stats_table}
                     WHERE attack_id = %d AND game_key = %s AND context = %s LIMIT 1",
                    $attack_id,
                    $game_key,
                    'pve'
                )
            );
            if ($row_pve) {
                $pve['damage'] = (int) $row_pve->damage;
                $pve['dps']    = (float) $row_pve->dps;
                $pve['eps']    = (float) $row_pve->eps;
                $pve['energy'] = (int) $row_pve->energy;
            }

            // PvP stats
            $row_pvp = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$stats_table}
                     WHERE attack_id = %d AND game_key = %s AND context = %s LIMIT 1",
                    $attack_id,
                    $game_key,
                    'pvp'
                )
            );
            if ($row_pvp) {
                $pvp['damage'] = (int) $row_pvp->damage;
                $pvp['dps']    = (float) $row_pvp->dps;
                $pvp['eps']    = (float) $row_pvp->eps;
                $pvp['energy'] = (int) $row_pvp->energy;
                $pvp['duration_ms'] = (int) $row_pvp->duration_ms;
                
                // Chargement des données extra (buffs, turns, dpt, ept)
                if (!empty($row_pvp->extra)) {
                    $extra_data = json_decode($row_pvp->extra, true);
                    if (is_array($extra_data)) {
                        // Buffs
                        if (isset($extra_data['buffs']) && is_array($extra_data['buffs'])) {
                            $pvp['buffs'] = array_merge($pvp['buffs'], $extra_data['buffs']);
                        }
                        // Statistiques par tour
                        if (isset($extra_data['turns'])) {
                            $pvp['turns'] = (int) $extra_data['turns'];
                        }
                        if (isset($extra_data['dpt'])) {
                            $pvp['dpt'] = (float) $extra_data['dpt'];
                        }
                        if (isset($extra_data['ept'])) {
                            $pvp['ept'] = (float) $extra_data['ept'];
                        }
                    }
                }
            }
        }
    }

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'moves',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit move', 'poke-hub')
                : esc_html__('Add move', 'poke-hub');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
                <?php esc_html_e('Back to list', 'poke-hub'); ?>
            </a>
        </h1>

        <form method="post" action="">
            <?php wp_nonce_field('poke_hub_pokemon_form', 'poke_hub_pokemon_nonce'); ?>
            <input type="hidden" name="ph_section" value="moves" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_move' : 'add_move'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $attack_id; ?>" />
            <?php endif; ?>

            <!-- Section : Informations de base -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                
                <!-- Nom FR / Nom EN sur la même ligne -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="attack_name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?> *</label>
                            <input type="text" id="attack_name_fr" name="name_fr" value="<?php echo esc_attr($current_name_fr); ?>" />
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="attack_name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?> *</label>
                            <input type="text" id="attack_name_en" name="name_en" value="<?php echo esc_attr($current_name_en); ?>" />
                        </div>
                    </div>
                </div>

                <!-- Slug -->
                <div class="admin-lab-form-group">
                    <label for="attack_slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                    <input type="text" id="attack_slug" name="slug" value="<?php echo esc_attr($is_edit ? $edit_row->slug : ''); ?>" />
                    <p class="description"><?php esc_html_e('Leave empty to auto-generate from name.', 'poke-hub'); ?></p>
                </div>

                <!-- Game & Category -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="attack_game"><?php esc_html_e('Game', 'poke-hub'); ?></label>
                            <select name="game_key" id="attack_game">
                                <option value="pokemon_go" <?php selected($game_key, 'pokemon_go'); ?>>Pokémon GO</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="attack_category"><?php esc_html_e('Category', 'poke-hub'); ?></label>
                            <select name="category" id="attack_category">
                                <option value=""><?php esc_html_e('-- Select --', 'poke-hub'); ?></option>
                                <option value="fast" <?php selected($current_category, 'fast'); ?>><?php esc_html_e('Fast Move', 'poke-hub'); ?></option>
                                <option value="charged" <?php selected($current_category, 'charged'); ?>><?php esc_html_e('Charged Move', 'poke-hub'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Types (sur une seule ligne) -->
                <div class="admin-lab-form-group">
                    <label><?php esc_html_e('Types', 'poke-hub'); ?></label>
                    <?php if (empty($all_types)) : ?>
                        <p class="description" style="color: #d63638;">
                            <?php esc_html_e('No types found. Please import types first.', 'poke-hub'); ?>
                        </p>
                    <?php else : ?>
                        <div class="pokehub-types-grid">
                            <?php foreach ($all_types as $t) : ?>
                                <?php
                                // Fallback si le label est vide
                                $type_label = !empty($t->label) ? $t->label : $t->slug;
                                ?>
                                <label class="pokehub-type-checkbox">
                                    <input type="checkbox" name="type_ids[]" value="<?php echo (int) $t->id; ?>"
                                        <?php checked(in_array((int) $t->id, $selected_type_ids, true)); ?> />
                                    <span><?php echo esc_html($type_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section : Global Stats -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Global Statistics', 'poke-hub'); ?></h3>
                <p class="description"><?php esc_html_e('These statistics apply to both PvE and PvP unless overridden.', 'poke-hub'); ?></p>
                
                <!-- Duration & Damage Window sur la même ligne -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="global_duration_ms"><?php esc_html_e('Duration (ms)', 'poke-hub'); ?></label>
                            <input type="number" id="global_duration_ms" name="global_duration_ms" 
                                   value="<?php echo esc_attr($global['duration_ms']); ?>" min="0" />
                        </div>
                    </div>
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="global_damage_window_start_ms"><?php esc_html_e('Damage Window Start (ms)', 'poke-hub'); ?></label>
                            <input type="number" id="global_damage_window_start_ms" name="global_damage_window_start_ms" 
                                   value="<?php echo esc_attr($global['damage_window_start_ms']); ?>" min="0" />
                        </div>
                    </div>
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="global_damage_window_end_ms"><?php esc_html_e('Damage Window End (ms)', 'poke-hub'); ?></label>
                            <input type="number" id="global_damage_window_end_ms" name="global_damage_window_end_ms" 
                                   value="<?php echo esc_attr($global['damage_window_end_ms']); ?>" min="0" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section : Stats PvE & PvP côte à côte -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Battle Statistics', 'poke-hub'); ?></h3>
                
                <div class="admin-lab-form-row">
                    <!-- PvE Stats -->
                    <div class="admin-lab-form-col-50">
                        <h4><?php esc_html_e('PvE (Raids, Gyms)', 'poke-hub'); ?></h4>
                        
                        <div class="admin-lab-form-group">
                            <label for="pve_damage"><?php esc_html_e('Damage', 'poke-hub'); ?></label>
                            <input type="number" id="pve_damage" name="pve_damage" 
                                   value="<?php echo esc_attr($pve['damage']); ?>" min="0" />
                        </div>

                        <div class="admin-lab-form-group">
                            <label for="pve_energy"><?php esc_html_e('Energy', 'poke-hub'); ?></label>
                            <input type="number" id="pve_energy" name="pve_energy" 
                                   value="<?php echo esc_attr($pve['energy']); ?>" />
                            <p class="description"><?php esc_html_e('Positive = gain, Negative = cost', 'poke-hub'); ?></p>
                        </div>

                        <div class="admin-lab-form-group">
                            <label for="pve_dps"><?php esc_html_e('DPS', 'poke-hub'); ?></label>
                            <input type="text" id="pve_dps" name="pve_dps" step="0.001"
                                   value="<?php echo esc_attr($pve['dps']); ?>" />
                        </div>

                        <div class="admin-lab-form-group">
                            <label for="pve_eps"><?php esc_html_e('EPS', 'poke-hub'); ?></label>
                            <input type="text" id="pve_eps" name="pve_eps" step="0.001"
                                   value="<?php echo esc_attr($pve['eps']); ?>" />
                        </div>
                    </div>

                    <!-- PvP Stats -->
                    <div class="admin-lab-form-col-50">
                        <h4><?php esc_html_e('PvP (Battles)', 'poke-hub'); ?></h4>
                        
                        <div class="admin-lab-form-group">
                            <label for="pvp_damage"><?php esc_html_e('Damage', 'poke-hub'); ?></label>
                            <input type="number" id="pvp_damage" name="pvp_damage" 
                                   value="<?php echo esc_attr($pvp['damage']); ?>" min="0" />
                        </div>

                        <div class="admin-lab-form-group">
                            <label for="pvp_energy"><?php esc_html_e('Energy', 'poke-hub'); ?></label>
                            <input type="number" id="pvp_energy" name="pvp_energy" 
                                   value="<?php echo esc_attr($pvp['energy']); ?>" />
                            <p class="description"><?php esc_html_e('Positive = gain, Negative = cost', 'poke-hub'); ?></p>
                        </div>

                        <div class="admin-lab-form-group">
                            <label for="pvp_duration_ms"><?php esc_html_e('Duration (ms)', 'poke-hub'); ?></label>
                            <input type="number" id="pvp_duration_ms" name="pvp_duration_ms" 
                                   value="<?php echo esc_attr($pvp['duration_ms']); ?>" min="0" />
                            <p class="description"><?php esc_html_e('Leave 0 to use global duration.', 'poke-hub'); ?></p>
                        </div>

                        <div class="admin-lab-form-row">
                            <div class="admin-lab-form-col">
                                <div class="admin-lab-form-group">
                                    <label for="pvp_dps"><?php esc_html_e('DPS', 'poke-hub'); ?></label>
                                    <input type="text" id="pvp_dps" name="pvp_dps" step="0.001"
                                           value="<?php echo esc_attr($pvp['dps']); ?>" />
                                </div>
                            </div>
                            <div class="admin-lab-form-col">
                                <div class="admin-lab-form-group">
                                    <label for="pvp_eps"><?php esc_html_e('EPS', 'poke-hub'); ?></label>
                                    <input type="text" id="pvp_eps" name="pvp_eps" step="0.001"
                                           value="<?php echo esc_attr($pvp['eps']); ?>" />
                                </div>
                            </div>
                        </div>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #dcdcde;" />
                        
                        <h5 style="margin-top: 15px; margin-bottom: 10px;"><?php esc_html_e('Turn Statistics', 'poke-hub'); ?></h5>
                        <p class="description" style="margin-bottom: 10px;"><?php esc_html_e('1 turn = 0.5 seconds', 'poke-hub'); ?></p>
                        
                        <div class="admin-lab-form-group">
                            <label for="pvp_turns"><?php esc_html_e('Turns', 'poke-hub'); ?></label>
                            <input type="number" id="pvp_turns" name="pvp_turns" 
                                   value="<?php echo esc_attr($pvp['turns']); ?>" min="0" />
                        </div>

                        <div class="admin-lab-form-row">
                            <div class="admin-lab-form-col">
                                <div class="admin-lab-form-group">
                                    <label for="pvp_dpt"><?php esc_html_e('DPT', 'poke-hub'); ?></label>
                                    <input type="text" id="pvp_dpt" name="pvp_dpt" step="0.001"
                                           value="<?php echo esc_attr($pvp['dpt']); ?>" />
                                </div>
                            </div>
                            <div class="admin-lab-form-col">
                                <div class="admin-lab-form-group">
                                    <label for="pvp_ept"><?php esc_html_e('EPT', 'poke-hub'); ?></label>
                                    <input type="text" id="pvp_ept" name="pvp_ept" step="0.001"
                                           value="<?php echo esc_attr($pvp['ept']); ?>" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section : PvP Buffs/Debuffs -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('PvP Buffs & Debuffs', 'poke-hub'); ?></h3>
                
                <div class="admin-lab-form-group">
                    <label for="pvp_buff_activation_chance"><?php esc_html_e('Activation Chance', 'poke-hub'); ?></label>
                    <input type="number" id="pvp_buff_activation_chance" name="pvp_buff_activation_chance" 
                           step="0.01" min="0" max="1" style="max-width: 200px;"
                           value="<?php echo esc_attr($pvp['buffs']['buff_activation_chance']); ?>" />
                    <p class="description"><?php esc_html_e('Value between 0.0 and 1.0 (e.g., 0.3 = 30%).', 'poke-hub'); ?></p>
                </div>

                <div class="pokehub-buffs-grid">
                    <!-- Colonne Attacker -->
                    <div class="pokehub-buffs-col">
                        <h4><?php esc_html_e('Attacker', 'poke-hub'); ?></h4>
                        
                        <div class="admin-lab-form-group">
                            <label for="pvp_attacker_attack_stat_stage_change"><?php esc_html_e('Attack Change', 'poke-hub'); ?></label>
                            <input type="number" id="pvp_attacker_attack_stat_stage_change" 
                                   name="pvp_attacker_attack_stat_stage_change" 
                                   value="<?php echo esc_attr($pvp['buffs']['attacker_attack_stat_stage_change']); ?>" 
                                   style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Stat stage change (-4 to +4).', 'poke-hub'); ?></p>
                        </div>

                        <div class="admin-lab-form-group">
                            <label for="pvp_attacker_defense_stat_stage_change"><?php esc_html_e('Defense Change', 'poke-hub'); ?></label>
                            <input type="number" id="pvp_attacker_defense_stat_stage_change" 
                                   name="pvp_attacker_defense_stat_stage_change" 
                                   value="<?php echo esc_attr($pvp['buffs']['attacker_defense_stat_stage_change']); ?>" 
                                   style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Stat stage change (-4 to +4).', 'poke-hub'); ?></p>
                        </div>
                    </div>

                    <!-- Colonne Target -->
                    <div class="pokehub-buffs-col">
                        <h4><?php esc_html_e('Target', 'poke-hub'); ?></h4>
                        
                        <div class="admin-lab-form-group">
                            <label for="pvp_target_attack_stat_stage_change"><?php esc_html_e('Attack Change', 'poke-hub'); ?></label>
                            <input type="number" id="pvp_target_attack_stat_stage_change" 
                                   name="pvp_target_attack_stat_stage_change" 
                                   value="<?php echo esc_attr($pvp['buffs']['target_attack_stat_stage_change']); ?>" 
                                   style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Stat stage change (-4 to +4).', 'poke-hub'); ?></p>
                        </div>

                        <div class="admin-lab-form-group">
                            <label for="pvp_target_defense_stat_stage_change"><?php esc_html_e('Defense Change', 'poke-hub'); ?></label>
                            <input type="number" id="pvp_target_defense_stat_stage_change" 
                                   name="pvp_target_defense_stat_stage_change" 
                                   value="<?php echo esc_attr($pvp['buffs']['target_defense_stat_stage_change']); ?>" 
                                   style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Stat stage change (-4 to +4).', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                       value="<?php echo $is_edit ? esc_attr__('Update', 'poke-hub') : esc_attr__('Add', 'poke-hub'); ?>" />
                <a href="<?php echo esc_url($back_url); ?>" class="button">
                    <?php esc_html_e('Cancel', 'poke-hub'); ?>
                </a>
            </p>
        </form>
    </div>
    <?php
}
