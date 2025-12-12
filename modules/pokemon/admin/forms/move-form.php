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

    // Jeu principal (pour l’instant : Pokémon GO uniquement)
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
        // On construit un label d’affichage = COALESCE(name_fr, name_en)
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

    // ---------- Stats existantes pour Pokémon GO (contexts: pve, pvp) ----------
    $pve = [
        'damage'                 => 0,
        'dps'                    => 0,
        'eps'                    => 0,
        'duration_ms'            => 0,
        'damage_window_start_ms' => 0,
        'damage_window_end_ms'   => 0,
        'energy'                 => 0,
    ];
    $pvp = $pve;

    if ($is_edit && $attack_id > 0) {
        $stats_table = pokehub_get_table('attack_stats');

        if ($stats_table) {
            // PVE
            $row_pve = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$stats_table} 
                     WHERE attack_id = %d AND game_key = %s AND context = %s 
                     LIMIT 1",
                    $attack_id,
                    'pokemon_go',
                    'pve'
                )
            );
            if ($row_pve) {
                $pve['damage']                 = (int) $row_pve->damage;
                $pve['dps']                    = (float) $row_pve->dps;
                $pve['eps']                    = (float) $row_pve->eps;
                $pve['duration_ms']            = (int) $row_pve->duration_ms;
                $pve['damage_window_start_ms'] = (int) $row_pve->damage_window_start_ms;
                $pve['damage_window_end_ms']   = (int) $row_pve->damage_window_end_ms;
                $pve['energy']                 = (int) $row_pve->energy;
            }

            // PVP
            $row_pvp = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$stats_table} 
                     WHERE attack_id = %d AND game_key = %s AND context = %s 
                     LIMIT 1",
                    $attack_id,
                    'pokemon_go',
                    'pvp'
                )
            );
            if ($row_pvp) {
                $pvp['damage']                 = (int) $row_pvp->damage;
                $pvp['dps']                    = (float) $row_pvp->dps;
                $pvp['eps']                    = (float) $row_pvp->eps;
                $pvp['duration_ms']            = (int) $row_pvp->duration_ms;
                $pvp['damage_window_start_ms'] = (int) $row_pvp->damage_window_start_ms;
                $pvp['damage_window_end_ms']   = (int) $row_pvp->damage_window_end_ms;
                $pvp['energy']                 = (int) $row_pvp->energy;
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

            <table class="form-table" role="presentation">
                <!-- Nom FR -->
                <tr>
                    <th scope="row">
                        <label for="attack_name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="attack_name_fr"
                               name="name_fr"
                               value="<?php echo esc_attr($current_name_fr); ?>" />
                        <p class="description">
                            <?php esc_html_e('Displayed name in French. At least one name (FR or EN) is required.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Nom EN -->
                <tr>
                    <th scope="row">
                        <label for="attack_name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="attack_name_en"
                               name="name_en"
                               value="<?php echo esc_attr($current_name_en); ?>" />
                        <p class="description">
                            <?php esc_html_e('Displayed name in English.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Slug -->
                <tr>
                    <th scope="row">
                        <label for="attack_slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="attack_slug"
                               name="slug"
                               value="<?php echo esc_attr($is_edit ? $edit_row->slug : ''); ?>" />
                        <p class="description">
                            <?php esc_html_e('Leave empty to auto-generate from name (FR first, then EN).', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Game -->
                <tr>
                    <th scope="row">
                        <label for="attack_game"><?php esc_html_e('Game', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <select name="game_key" id="attack_game">
                            <option value="pokemon_go" <?php selected($game_key, 'pokemon_go'); ?>>
                                <?php esc_html_e('Pokémon GO', 'poke-hub'); ?>
                            </option>
                            <!-- plus tard : autres jeux -->
                        </select>
                    </td>
                </tr>

                <!-- Catégorie (Fast / Charged) -->
                <tr>
                    <th scope="row">
                        <label for="attack_category"><?php esc_html_e('Category', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <select name="category" id="attack_category">
                            <option value=""><?php esc_html_e('— Not set —', 'poke-hub'); ?></option>
                            <option value="fast" <?php selected($current_category, 'fast'); ?>>
                                <?php esc_html_e('Fast move', 'poke-hub'); ?>
                            </option>
                            <option value="charged" <?php selected($current_category, 'charged'); ?>>
                                <?php esc_html_e('Charged move', 'poke-hub'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('A move is either fast or charged (for Pokémon GO). This is global, not per Pokémon.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Types -->
                <?php if (!empty($all_types)) : ?>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Types', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <p class="description">
                            <?php esc_html_e('Select one or more types for this move.', 'poke-hub'); ?>
                        </p>

                        <?php foreach ($all_types as $type) :
                            $checked = in_array((int) $type->id, $selected_type_ids, true);
                            $color   = trim((string) $type->color);
                            // Label d’affichage priorise FR puis EN
                            $label = $type->label !== null && $type->label !== ''
                                ? $type->label
                                : ($type->name_fr ?: $type->name_en);
                        ?>
                            <label style="display:inline-block;margin-right:12px;margin-bottom:4px;">
                                <input type="checkbox"
                                    name="type_ids[]"
                                    value="<?php echo (int) $type->id; ?>"
                                    <?php checked($checked); ?> />
                                <?php if ($color !== '') : ?>
                                    <span style="display:inline-block;width:14px;height:14px;border-radius:3px;vertical-align:middle;margin-right:4px;background:<?php echo esc_attr($color); ?>;"></span>
                                <?php endif; ?>
                                <?php echo esc_html($label); ?>
                            </label><br/>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php else : ?>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Types', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <p class="description">
                            <?php esc_html_e('No types defined yet. Please add types first.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <h2><?php esc_html_e('Pokémon GO – PvE stats', 'poke-hub'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="pve_damage"><?php esc_html_e('Damage', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="number" class="small-text" id="pve_damage" name="pve_damage"
                               value="<?php echo esc_attr($pve['damage']); ?>" min="0" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pve_dps"><?php esc_html_e('DPS', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="text" class="small-text" id="pve_dps" name="pve_dps"
                               value="<?php echo esc_attr($pve['dps']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pve_eps"><?php esc_html_e('EPS', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="text" class="small-text" id="pve_eps" name="pve_eps"
                               value="<?php echo esc_attr($pve['eps']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pve_duration_ms"><?php esc_html_e('Duration (ms)', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="number" class="small-text" id="pve_duration_ms" name="pve_duration_ms"
                               value="<?php echo esc_attr($pve['duration_ms']); ?>" min="0" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Damage window (ms)', 'poke-hub'); ?></th>
                    <td>
                        <label>
                            <?php esc_html_e('Start', 'poke-hub'); ?>
                            <input type="number" class="small-text"
                                   name="pve_damage_window_start_ms"
                                   value="<?php echo esc_attr($pve['damage_window_start_ms']); ?>" min="0" />
                        </label>
                        &nbsp;
                        <label>
                            <?php esc_html_e('End', 'poke-hub'); ?>
                            <input type="number" class="small-text"
                                   name="pve_damage_window_end_ms"
                                   value="<?php echo esc_attr($pve['damage_window_end_ms']); ?>" min="0" />
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pve_energy"><?php esc_html_e('Energy', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="number" class="small-text" id="pve_energy" name="pve_energy"
                               value="<?php echo esc_attr($pve['energy']); ?>" />
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Pokémon GO – PvP stats', 'poke-hub'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="pvp_damage"><?php esc_html_e('Damage', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="number" class="small-text" id="pvp_damage" name="pvp_damage"
                               value="<?php echo esc_attr($pvp['damage']); ?>" min="0" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pvp_dps"><?php esc_html_e('DPS', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="text" class="small-text" id="pvp_dps" name="pvp_dps"
                               value="<?php echo esc_attr($pvp['dps']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pvp_eps"><?php esc_html_e('EPS', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="text" class="small-text" id="pvp_eps" name="pvp_eps"
                               value="<?php echo esc_attr($pvp['eps']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pvp_duration_ms"><?php esc_html_e('Duration (ms)', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="number" class="small-text" id="pvp_duration_ms" name="pvp_duration_ms"
                               value="<?php echo esc_attr($pvp['duration_ms']); ?>" min="0" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Damage window (ms)', 'poke-hub'); ?></th>
                    <td>
                        <label>
                            <?php esc_html_e('Start', 'poke-hub'); ?>
                            <input type="number" class="small-text"
                                   name="pvp_damage_window_start_ms"
                                   value="<?php echo esc_attr($pvp['damage_window_start_ms']); ?>" min="0" />
                        </label>
                        &nbsp;
                        <label>
                            <?php esc_html_e('End', 'poke-hub'); ?>
                            <input type="number" class="small-text"
                                   name="pvp_damage_window_end_ms"
                                   value="<?php echo esc_attr($pvp['damage_window_end_ms']); ?>" min="0" />
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pvp_energy"><?php esc_html_e('Energy', 'poke-hub'); ?></label></th>
                    <td>
                        <input type="number" class="small-text" id="pvp_energy" name="pvp_energy"
                               value="<?php echo esc_attr($pvp['energy']); ?>" />
                    </td>
                </tr>
            </table>

            <?php
            submit_button(
                $is_edit
                    ? __('Update move', 'poke-hub')
                    : __('Add move', 'poke-hub')
            );
            ?>
        </form>
    </div>
    <?php
}
