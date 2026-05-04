<?php
// modules/pokemon/admin/forms/generation-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit génération
 *
 * @param object|null $edit_row Ligne existante (mode édition) ou null (mode ajout)
 */
function poke_hub_pokemon_generations_edit_form($edit_row = null) {
    global $wpdb;

    // Liste des RÉGIONS Pokémon
    $table_regions = pokehub_get_table('pokemon_regions');
    $regions       = [];

    if ($table_regions) {
        $regions = $wpdb->get_results(
            "SELECT 
                id,
                slug,
                name_fr,
                name_en,
                COALESCE(name_fr, name_en, slug) AS label
             FROM {$table_regions}
             ORDER BY name_fr ASC, name_en ASC, slug ASC"
        );
    }

    $is_edit = ($edit_row !== null);

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'generations',
        ],
        admin_url('admin.php')
    );

    // Valeurs par défaut / édition
    $current_gen_number = $is_edit ? (int) $edit_row->generation_number : '';
    $current_slug       = $is_edit ? (string) $edit_row->slug : '';
    $current_region_ids = [];
    if ($is_edit && !empty($edit_row->id) && function_exists('poke_hub_get_generation_region_ids_ordered')) {
        $current_region_ids = poke_hub_get_generation_region_ids_ordered((int) $edit_row->id);
    }
    if ($current_region_ids === [] && $is_edit && !empty($edit_row->region_id)) {
        $current_region_ids = [(int) $edit_row->region_id];
    }

    // Noms multilingues
    $current_name_fr = '';
    $current_name_en = '';

    if ($is_edit) {
        if (isset($edit_row->name_fr)) {
            $current_name_fr = (string) $edit_row->name_fr;
        }
        if (isset($edit_row->name_en)) {
            $current_name_en = (string) $edit_row->name_en;
        }

        // Compat rétro
        if ($current_name_fr === '' && $current_name_en === '' && isset($edit_row->label)) {
            $current_name_fr = (string) $edit_row->label;
        }
    }
    ?>
    <div class="wrap">
        <?php poke_hub_admin_back_to_list_bar($back_url); ?>
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit generation', 'poke-hub')
                : esc_html__('Add generation', 'poke-hub');
            ?>
        </h1>

        <form method="post" action="">
            <?php wp_nonce_field('poke_hub_pokemon_form', 'poke_hub_pokemon_nonce'); ?>
            <input type="hidden" name="ph_section" value="generations" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_generation' : 'add_generation'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <!-- Section: Basic Information -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                
                <!-- Generation Number & Region -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="gen_number"><?php esc_html_e('Generation Number', 'poke-hub'); ?> *</label>
                            <input type="number" id="gen_number" name="generation_number" 
                                   value="<?php echo esc_attr($current_gen_number); ?>" 
                                   min="1" style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Example: 1, 2, 3…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="gen_regions"><?php esc_html_e('Regions (game)', 'poke-hub'); ?></label>
                            <select name="region_ids[]" id="gen_regions" class="pokehub-generation-game-regions-select" multiple="multiple"
                                data-placeholder="<?php echo esc_attr(__('Search regions by name…', 'poke-hub')); ?>"
                                style="min-width: 220px; width: 100%; max-width: 480px;">
                                <?php foreach ($regions as $r) : ?>
                                    <?php
                                    $rid = (int) $r->id;
                                    $nf  = isset($r->name_fr) ? (string) $r->name_fr : '';
                                    $ne  = isset($r->name_en) ? (string) $r->name_en : '';
                                    $sl  = isset($r->slug) ? (string) $r->slug : '';
                                    $opt_label = (string) $r->label;
                                    if ($sl !== '' && stripos($opt_label, $sl) === false) {
                                        $opt_label .= ' (' . $sl . ')';
                                    }
                                    ?>
                                    <option value="<?php echo $rid; ?>"
                                        data-name-fr="<?php echo esc_attr($nf); ?>"
                                        data-name-en="<?php echo esc_attr($ne); ?>"
                                        <?php echo in_array($rid, $current_region_ids, true) ? 'selected="selected"' : ''; ?>>
                                        <?php echo esc_html($opt_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('One generation can cover several regions (e.g. Galar and Hisui). Hold Ctrl or Cmd to select multiple. The first selected is also stored in the legacy single region field for compatibility.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Name FR / Name EN -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="gen_name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?> *</label>
                            <input type="text" id="gen_name_fr" name="name_fr" value="<?php echo esc_attr($current_name_fr); ?>" />
                            <p class="description"><?php esc_html_e('Example: Generation 1, First generation…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="gen_name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?> *</label>
                            <input type="text" id="gen_name_en" name="name_en" value="<?php echo esc_attr($current_name_en); ?>" />
                            <p class="description"><?php esc_html_e('Example: Generation 1, First generation…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Slug -->
                <div class="admin-lab-form-group">
                    <label for="gen_slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                    <input type="text" id="gen_slug" name="slug" value="<?php echo esc_attr($current_slug); ?>" />
                    <p class="description"><?php esc_html_e('Leave empty to auto-generate from name.', 'poke-hub'); ?></p>
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
