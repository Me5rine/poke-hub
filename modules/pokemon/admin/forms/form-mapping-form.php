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

    // --- Préparation des valeurs initiales ---

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
        // Variant actuellement lié ?
        if ($form_slug !== '') {
            $variant_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$variants_table} WHERE form_slug = %s LIMIT 1",
                    $form_slug
                )
            );
        }

        // Tous les variants existants
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

            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row">
                        <label for="pokemon_id_proto"><?php esc_html_e('Pokémon ID (proto)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" class="regular-text" name="pokemon_id_proto" id="pokemon_id_proto"
                               value="<?php echo esc_attr($pokemon_id_proto); ?>" />
                        <p class="description">
                            <?php esc_html_e('Example: MEWTWO, PIKACHU, BULBASAUR… (as in Game Master "pokemonId").', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="form_proto"><?php esc_html_e('Form (proto)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" class="regular-text" name="form_proto" id="form_proto"
                               value="<?php echo esc_attr($form_proto); ?>" />
                        <p class="description">
                            <?php esc_html_e('Example: MEWTWO_A, PIKACHU_FALL_2019, PIKACHU_COSTUME… (as in "form").', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="form_slug"><?php esc_html_e('Form variant', 'poke-hub'); ?></label>
                    </th>
                    <td>
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
                            <?php esc_html_e('Choose the global form variant to associate with this proto form. Variants (slug, label, category, group) are managed in the “Form variants” screen.', 'poke-hub'); ?>
                        </p>

                        <?php if ($variants_table) : ?>
                            <p class="description">
                                <a href="<?php echo esc_url( add_query_arg( ['page' => 'poke-hub-pokemon', 'ph_section' => 'form_variants'], admin_url('admin.php') ) ); ?>">
                                    <?php esc_html_e('Manage global form variants', 'poke-hub'); ?>
                                </a>
                            </p>
                        <?php endif; ?>

                        <?php if ($variant_row) : ?>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: 1: category, 2: group, 3: label */
                                    esc_html__('Current linked variant: category=%1$s, group=%2$s, label=%3$s', 'poke-hub'),
                                    esc_html($variant_row->category),
                                    esc_html($variant_row->group),
                                    esc_html($variant_row->label)
                                );
                                ?>
                            </p>
                        <?php elseif ($form_slug !== '') : ?>
                            <p class="description">
                                <?php esc_html_e('No global variant was found for this slug. It may be created automatically by the Game Master import, then editable in the “Form variants” screen.', 'poke-hub'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="label_suffix"><?php esc_html_e('Label suffix (per mapping)', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="text" class="regular-text" name="label_suffix" id="label_suffix"
                               value="<?php echo esc_attr($label_suffix); ?>" />
                        <p class="description">
                            <?php esc_html_e('Optional suffix appended to the Pokémon name for this specific mapping (e.g. "Armored", "Fall 2019", "Clone"). This does not change the global form label.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="sort_order"><?php esc_html_e('Sort order', 'poke-hub'); ?></label>
                    </th>
                    <td>
                        <input type="number" class="small-text" name="sort_order" id="sort_order"
                               value="<?php echo esc_attr($sort_order); ?>" />
                        <p class="description">
                            <?php esc_html_e('Optional ordering between forms for the same Pokémon.', 'poke-hub'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php
            submit_button(
                $is_edit
                    ? __('Update form mapping', 'poke-hub')
                    : __('Add form mapping', 'poke-hub')
            );
            ?>
        </form>
    </div>
    <?php
}
