<?php
// File: modules/pokemon/admin/forms/form-mapping-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Form Mapping
 *
 * @param object|null $edit_row
 */
function poke_hub_pokemon_form_mappings_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit form mappings.', 'poke-hub'));
    }

    $is_edit = ($edit_row && isset($edit_row->id));

    // Valeurs initiales
    $pokemon_id_proto = '';
    $form_proto       = '';
    $form_slug        = '';
    $label_suffix     = '';
    $sort_order       = 0;

    if ($is_edit) {
        $pokemon_id_proto = isset($edit_row->pokemon_id_proto) ? (string) $edit_row->pokemon_id_proto : '';
        $form_proto       = isset($edit_row->form_proto) ? (string) $edit_row->form_proto : '';
        $form_slug        = isset($edit_row->form_slug) ? (string) $edit_row->form_slug : '';
        $label_suffix     = isset($edit_row->label_suffix) ? (string) $edit_row->label_suffix : '';
        $sort_order       = isset($edit_row->sort_order) ? (int) $edit_row->sort_order : 0;
    }

    global $wpdb;

    // Liste des variants globaux pour l'association
    $variant_row       = null;
    $variants_table    = pokehub_get_table('pokemon_form_variants');
    $existing_variants = [];

    if ($variants_table) {
        if ($form_slug !== '') {
            $variant_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$variants_table} WHERE form_slug = %s LIMIT 1",
                    $form_slug
                )
            );
        }

        $existing_variants = $wpdb->get_results(
            "SELECT form_slug, label, category, `group`
             FROM {$variants_table}
             ORDER BY category ASC, label ASC"
        );
    }

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'form_mappings',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit form mapping', 'poke-hub')
                : esc_html__('Add form mapping', 'poke-hub');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
                <?php esc_html_e('Back to list', 'poke-hub'); ?>
            </a>
        </h1>

        <form method="post">
            <?php wp_nonce_field('poke_hub_pokemon_edit_form_mapping'); ?>
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="form_mappings" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_form_mapping' : 'add_form_mapping'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <!-- Section: Game Master Identifiers -->
            <div class="pokehub-section">
                <h3><?php esc_html_e('Game Master Identifiers', 'poke-hub'); ?></h3>
                <p class="description" style="margin-top: 0;">
                    <?php esc_html_e('These values come from the Game Master JSON file.', 'poke-hub'); ?>
                </p>
                
                <div class="pokehub-form-row">
                    <div class="pokehub-form-col-50">
                        <div class="pokehub-form-group">
                            <label for="pokemon_id_proto"><?php esc_html_e('Pokémon ID (proto)', 'poke-hub'); ?> *</label>
                            <input type="text" id="pokemon_id_proto" name="pokemon_id_proto" value="<?php echo esc_attr($pokemon_id_proto); ?>" required />
                            <p class="description"><?php esc_html_e('Example: MEWTWO, PIKACHU, BULBASAUR…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="pokehub-form-col-50">
                        <div class="pokehub-form-group">
                            <label for="form_proto"><?php esc_html_e('Form (proto)', 'poke-hub'); ?></label>
                            <input type="text" id="form_proto" name="form_proto" value="<?php echo esc_attr($form_proto); ?>" />
                            <p class="description"><?php esc_html_e('Example: MEWTWO_A, PIKACHU_FALL_2019…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Form Variant Association -->
            <div class="pokehub-section">
                <h3><?php esc_html_e('Form Variant Association', 'poke-hub'); ?></h3>
                
                <div class="pokehub-form-group">
                    <label for="form_slug"><?php esc_html_e('Form Variant', 'poke-hub'); ?></label>
                    <select name="form_slug" id="form_slug">
                        <option value=""><?php esc_html_e('— Base form / no special variant —', 'poke-hub'); ?></option>
                        <?php if (!empty($existing_variants)) : ?>
                            <?php foreach ($existing_variants as $v) : ?>
                                <?php
                                $option_label = $v->form_slug;
                                if (!empty($v->label)) {
                                    $option_label .= ' — ' . $v->label;
                                }
                                if (!empty($v->category)) {
                                    $option_label .= ' [' . $v->category;
                                    if (!empty($v->group)) {
                                        $option_label .= ' • ' . $v->group;
                                    }
                                    $option_label .= ']';
                                } elseif (!empty($v->group)) {
                                    $option_label .= ' [' . $v->group . ']';
                                }
                                ?>
                                <option value="<?php echo esc_attr($v->form_slug); ?>" <?php selected($form_slug, $v->form_slug); ?>>
                                    <?php echo esc_html($option_label); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Choose the global form variant to associate with this proto form.', 'poke-hub'); ?>
                        <?php if ($variants_table) : ?>
                            <a href="<?php echo esc_url( add_query_arg( ['page' => 'poke-hub-pokemon', 'ph_section' => 'forms'], admin_url('admin.php') ) ); ?>" style="margin-left: 10px;">
                                <?php esc_html_e('Manage form variants', 'poke-hub'); ?> →
                            </a>
                        <?php endif; ?>
                    </p>

                    <?php if ($variant_row) : ?>
                        <div style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 3px solid #2271b1; border-radius: 3px;">
                            <strong><?php esc_html_e('Current linked variant:', 'poke-hub'); ?></strong><br>
                            <?php
                            printf(
                                esc_html__('Category: %1$s | Group: %2$s | Label: %3$s', 'poke-hub'),
                                '<code>' . esc_html($variant_row->category) . '</code>',
                                '<code>' . esc_html($variant_row->group) . '</code>',
                                '<strong>' . esc_html($variant_row->label) . '</strong>'
                            );
                            ?>
                        </div>
                    <?php elseif ($form_slug !== '') : ?>
                        <div style="margin-top: 10px; padding: 10px; background: #fcf3cf; border-left: 3px solid #f39c12; border-radius: 3px;">
                            <?php esc_html_e('⚠️ No global variant found for this slug. It may be created automatically by import.', 'poke-hub'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section: Display Options -->
            <div class="pokehub-section">
                <h3><?php esc_html_e('Display Options', 'poke-hub'); ?></h3>
                
                <div class="pokehub-form-row">
                    <div class="pokehub-form-col">
                        <div class="pokehub-form-group">
                            <label for="label_suffix"><?php esc_html_e('Label Suffix', 'poke-hub'); ?></label>
                            <input type="text" id="label_suffix" name="label_suffix" value="<?php echo esc_attr($label_suffix); ?>" />
                            <p class="description"><?php esc_html_e('Optional suffix appended to the Pokémon name (e.g. "Armored", "Fall 2019").', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="pokehub-form-col">
                        <div class="pokehub-form-group">
                            <label for="sort_order"><?php esc_html_e('Sort Order', 'poke-hub'); ?></label>
                            <input type="number" id="sort_order" name="sort_order" value="<?php echo esc_attr($sort_order); ?>" style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Optional ordering between forms.', 'poke-hub'); ?></p>
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
