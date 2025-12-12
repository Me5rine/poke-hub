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

    // ✅ on veut la liste des RÉGIONS Pokémon (multilingues)
    $table_regions = pokehub_get_table('pokemon_regions');
    $regions       = [];

    if ($table_regions) {
        // On fabrique un label UI = COALESCE(name_fr, name_en)
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

    // Noms multilingues : si ancienne colonne label, on fallback dessus
    $current_name_fr = '';
    $current_name_en = '';

    if ($is_edit) {
        if (isset($edit_row->name_fr)) {
            $current_name_fr = (string) $edit_row->name_fr;
        }
        if (isset($edit_row->name_en)) {
            $current_name_en = (string) $edit_row->name_en;
        }

        // Compat rétro : si les deux sont vides mais qu'on a encore "label"
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

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="gen_number"><?php esc_html_e('Generation number', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               class="small-text"
                               id="gen_number"
                               name="generation_number"
                               value="<?php echo esc_attr($current_gen_number); ?>"
                               min="1" />
                        <p class="description">
                            <?php esc_html_e('Example: 1, 2, 3…', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Nom FR -->
                <tr>
                    <th scope="row">
                        <label for="gen_name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="gen_name_fr"
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
                        <label for="gen_name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="gen_name_en"
                               name="name_en"
                               value="<?php echo esc_attr($current_name_en); ?>" />
                        <p class="description">
                            <?php esc_html_e('Displayed name in English.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gen_slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               class="regular-text"
                               id="gen_slug"
                               name="slug"
                               value="<?php echo esc_attr($current_slug); ?>" />
                        <p class="description">
                            <?php esc_html_e('Leave empty to auto-generate from name (FR first, then EN).', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gen_region"><?php esc_html_e('Region', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <select name="region_id" id="gen_region">
                            <option value="0">
                                <?php esc_html_e('None / multiple regions', 'poke-hub'); ?>
                            </option>
                            <?php
                            if (!empty($regions)) :
                                foreach ($regions as $reg) : ?>
                                    <option value="<?php echo (int) $reg->id; ?>"
                                        <?php selected($current_region_id, (int) $reg->id); ?>>
                                        <?php echo esc_html($reg->label); ?>
                                    </option>
                                <?php
                                endforeach;
                            endif;
                            ?>
                        </select>
                    </td>
                </tr>
            </table>

            <?php
            submit_button(
                $is_edit
                    ? __('Update generation', 'poke-hub')
                    : __('Add generation', 'poke-hub')
            );
            ?>
        </form>
    </div>
    <?php
}
