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
                COALESCE(name_fr, name_en) AS label
             FROM {$table_regions}
             ORDER BY name_fr ASC, name_en ASC"
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
    $current_region_id  = $is_edit ? (int) $edit_row->region_id : 0;

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
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit generation', 'poke-hub')
                : esc_html__('Add generation', 'poke-hub');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
                <?php esc_html_e('Back to list', 'poke-hub'); ?>
            </a>
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
            <div class="pokehub-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                
                <!-- Generation Number & Region -->
                <div class="pokehub-form-row">
                    <div class="pokehub-form-col">
                        <div class="pokehub-form-group">
                            <label for="gen_number"><?php esc_html_e('Generation Number', 'poke-hub'); ?> *</label>
                            <input type="number" id="gen_number" name="generation_number" 
                                   value="<?php echo esc_attr($current_gen_number); ?>" 
                                   min="1" style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Example: 1, 2, 3…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="pokehub-form-col">
                        <div class="pokehub-form-group">
                            <label for="gen_region"><?php esc_html_e('Region', 'poke-hub'); ?></label>
                            <select name="region_id" id="gen_region">
                                <option value="0"><?php esc_html_e('-- No region --', 'poke-hub'); ?></option>
                                <?php foreach ($regions as $r) : ?>
                                    <option value="<?php echo (int) $r->id; ?>"
                                        <?php selected($current_region_id, (int) $r->id); ?>>
                                        <?php echo esc_html($r->label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Associated Pokémon region.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Name FR / Name EN -->
                <div class="pokehub-form-row">
                    <div class="pokehub-form-col-50">
                        <div class="pokehub-form-group">
                            <label for="gen_name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?> *</label>
                            <input type="text" id="gen_name_fr" name="name_fr" value="<?php echo esc_attr($current_name_fr); ?>" />
                            <p class="description"><?php esc_html_e('Example: Génération 1, Première génération…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="pokehub-form-col-50">
                        <div class="pokehub-form-group">
                            <label for="gen_name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?> *</label>
                            <input type="text" id="gen_name_en" name="name_en" value="<?php echo esc_attr($current_name_en); ?>" />
                            <p class="description"><?php esc_html_e('Example: Generation 1, First generation…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Slug -->
                <div class="pokehub-form-group">
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
