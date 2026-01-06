<?php
// File: modules/pokemon/admin/forms/form-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit d'une forme globale (pokemon_form_variants).
 *
 * @param object|null $edit_row Ligne existante de pokemon_form_variants ou null pour un ajout.
 */
function poke_hub_pokemon_forms_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit forms.', 'poke-hub'));
    }

    $is_edit = ($edit_row && isset($edit_row->id));

    // Valeurs initiales
    $form_slug        = '';
    $label            = '';
    $category         = 'normal';
    $group_key        = '';
    $parent_form_slug = '';

    if ($is_edit) {
        $form_slug        = isset($edit_row->form_slug) ? (string) $edit_row->form_slug : '';
        $label            = isset($edit_row->label) ? (string) $edit_row->label : '';
        $category         = isset($edit_row->category) ? (string) $edit_row->category : 'normal';
        $group_key        = isset($edit_row->group_key) ? (string) $edit_row->group_key : '';
        $parent_form_slug = isset($edit_row->parent_form_slug) ? (string) $edit_row->parent_form_slug : '';
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon_form_variants');

    // Liste des autres formes possibles comme parent
    $parent_candidates = [];
    if ($table) {
        $parent_candidates = $wpdb->get_results(
            "SELECT form_slug, label, category FROM {$table} ORDER BY category ASC, label ASC",
            ARRAY_A
        );
    }

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'forms',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit form', 'poke-hub')
                : esc_html__('Add form', 'poke-hub');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
                <?php esc_html_e('Back to list', 'poke-hub'); ?>
            </a>
        </h1>

        <form method="post">
            <?php wp_nonce_field('poke_hub_pokemon_edit_form'); ?>
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="forms" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_form' : 'add_form'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <!-- Section: Basic Information -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                
                <!-- Form Slug / Label -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="form_slug"><?php esc_html_e('Form Slug', 'poke-hub'); ?> *</label>
                            <input type="text" id="form_slug" name="form_slug" value="<?php echo esc_attr($form_slug); ?>" required />
                            <p class="description"><?php esc_html_e('Example: "armored", "fall-2019", "costume".', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="label"><?php esc_html_e('Label', 'poke-hub'); ?> *</label>
                            <input type="text" id="label" name="label" value="<?php echo esc_attr($label); ?>" required />
                            <p class="description"><?php esc_html_e('Example: "Armored", "Fall 2019", "Costume".', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Category / Group Key -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="category"><?php esc_html_e('Category', 'poke-hub'); ?></label>
                            <input type="text" id="category" name="category" value="<?php echo esc_attr($category); ?>" />
                            <p class="description"><?php esc_html_e('Example: costume, clone, regional, shadow, purified, normal…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="group_key"><?php esc_html_e('Group Key', 'poke-hub'); ?></label>
                            <input type="text" id="group_key" name="group_key" value="<?php echo esc_attr($group_key); ?>" />
                            <p class="description"><?php esc_html_e('Optional sub-grouping key.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Parent Form -->
                <div class="admin-lab-form-group">
                    <label for="parent_form_slug"><?php esc_html_e('Parent Form', 'poke-hub'); ?></label>
                    <select name="parent_form_slug" id="parent_form_slug">
                        <option value=""><?php esc_html_e('— No parent —', 'poke-hub'); ?></option>
                        <?php
                        if (!empty($parent_candidates)) :
                            foreach ($parent_candidates as $parent) :
                                if ($is_edit && $parent['form_slug'] === $form_slug) {
                                    continue;
                                }

                                $option_label = sprintf(
                                    '%s (%s)',
                                    $parent['label'] !== '' ? $parent['label'] : $parent['form_slug'],
                                    $parent['category'] !== '' ? $parent['category'] : 'normal'
                                );
                                ?>
                                <option value="<?php echo esc_attr($parent['form_slug']); ?>"
                                    <?php selected($parent_form_slug, $parent['form_slug']); ?>>
                                    <?php echo esc_html($option_label); ?>
                                </option>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    </select>
                    <p class="description"><?php esc_html_e('Optional parent form to build hierarchies.', 'poke-hub'); ?></p>
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
