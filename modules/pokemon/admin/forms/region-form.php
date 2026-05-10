<?php
// modules/pokemon/admin/forms/region-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit région
 *
 * @param object|null $edit_row Ligne existante (mode édition) ou null (mode ajout)
 */
function poke_hub_pokemon_regions_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    $is_edit = ($edit_row !== null);

    // Noms multilingues actuels
    $current_name_fr = '';
    $current_name_en = '';
    $current_slug = '';
    $current_pr_form_name_en = '';
    $current_pr_form_name_fr = '';
    $current_pr_form_slug      = '';
    $current_pr_form_aliases   = '';

    if ($is_edit) {
        if (isset($edit_row->name_fr)) {
            $current_name_fr = (string) $edit_row->name_fr;
        }
        if (isset($edit_row->name_en)) {
            $current_name_en = (string) $edit_row->name_en;
        }

        // Compat rétro : si les deux sont vides mais qu'on a encore "name"
        if ($current_name_fr === '' && $current_name_en === '' && isset($edit_row->name)) {
            $current_name_fr = (string) $edit_row->name;
        }
        $current_slug = isset($edit_row->slug) ? sanitize_title((string) $edit_row->slug) : '';
        $current_pr_form_name_en = isset($edit_row->pokemon_regional_form_name_en) ? (string) $edit_row->pokemon_regional_form_name_en : '';
        $current_pr_form_name_fr = isset($edit_row->pokemon_regional_form_name_fr) ? (string) $edit_row->pokemon_regional_form_name_fr : '';
        $current_pr_form_slug     = isset($edit_row->pokemon_regional_form_slug) ? sanitize_title((string) $edit_row->pokemon_regional_form_slug) : '';
        $raw_aliases             = isset($edit_row->pokemon_regional_form_slug_aliases) ? (string) $edit_row->pokemon_regional_form_slug_aliases : '';
        $alias_dec               = json_decode($raw_aliases, true);
        $current_pr_form_aliases   = is_array($alias_dec) ? implode(', ', array_map('strval', $alias_dec)) : '';
    }

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'regions',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <?php poke_hub_admin_back_to_list_bar($back_url); ?>
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit region', 'poke-hub')
                : esc_html__('Add region', 'poke-hub');
            ?>
        </h1>

        <form method="post" action="">
            <?php wp_nonce_field('poke_hub_pokemon_region_form', 'poke_hub_pokemon_region_nonce'); ?>
            <input type="hidden" name="ph_section" value="regions" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_region' : 'add_region'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <!-- Section: Basic Information -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                
                <!-- Name FR / Name EN -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="region_name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?> *</label>
                            <input type="text" id="region_name_fr" name="name_fr" value="<?php echo esc_attr($current_name_fr); ?>" />
                            <p class="description"><?php esc_html_e('Example: Kanto, Johto, Hoenn…', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="region_name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?> *</label>
                            <input type="text" id="region_name_en" name="name_en" value="<?php echo esc_attr($current_name_en); ?>" />
                            <p class="description"><?php esc_html_e('At least one name (FR or EN) is required.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Slug & Sort Order -->
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="region_slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                            <input type="text" id="region_slug" name="slug" value="<?php echo esc_attr($is_edit ? $edit_row->slug : ''); ?>" />
                            <p class="description"><?php esc_html_e('Leave empty to auto-generate from name.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label for="region_sort_order"><?php esc_html_e('Sort Order', 'poke-hub'); ?></label>
                            <input type="number" id="region_sort_order" name="sort_order" 
                                   value="<?php echo esc_attr($is_edit ? (int) $edit_row->sort_order : '0'); ?>" 
                                   style="max-width: 150px;" />
                            <p class="description"><?php esc_html_e('Lower = appears first in lists.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Formes Pokémon régionales (segments slug / form_slug, ex. tauros-paldea) -->
                <div class="admin-lab-form-section" style="margin-top:1.5em;padding-top:1em;border-top:1px solid #c3c4c7;">
                    <h3><?php esc_html_e('Pokémon regional form (slug tokens)', 'poke-hub'); ?></h3>
                    <p class="description">
                        <?php esc_html_e('Used for species slugs (e.g. meowth-alola, tauros-paldea-aqua) and collection filters — not the « Geographical areas » screen (countries / map groups).', 'poke-hub'); ?>
                    </p>
                    <div class="admin-lab-form-row">
                        <div class="admin-lab-form-col-50">
                            <div class="admin-lab-form-group">
                                <label for="pokemon_regional_form_name_fr"><?php esc_html_e('Form name (French)', 'poke-hub'); ?></label>
                                <input type="text" id="pokemon_regional_form_name_fr" name="pokemon_regional_form_name_fr" value="<?php echo esc_attr($current_pr_form_name_fr); ?>" placeholder="<?php echo esc_attr__('e.g. Hisui', 'poke-hub'); ?>" />
                            </div>
                        </div>
                        <div class="admin-lab-form-col-50">
                            <div class="admin-lab-form-group">
                                <label for="pokemon_regional_form_name_en"><?php esc_html_e('Form name (English)', 'poke-hub'); ?></label>
                                <input type="text" id="pokemon_regional_form_name_en" name="pokemon_regional_form_name_en" value="<?php echo esc_attr($current_pr_form_name_en); ?>" placeholder="<?php echo esc_attr__('e.g. Hisuian', 'poke-hub'); ?>" />
                                <p class="description"><?php esc_html_e('If the slug token is empty, it is derived from this field.', 'poke-hub'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="admin-lab-form-row">
                        <div class="admin-lab-form-col">
                            <div class="admin-lab-form-group">
                                <label for="pokemon_regional_form_slug"><?php esc_html_e('Slug token', 'poke-hub'); ?></label>
                                <input type="text" id="pokemon_regional_form_slug" name="pokemon_regional_form_slug" value="<?php echo esc_attr($current_pr_form_slug); ?>" autocapitalize="none" autocomplete="off" />
                                <p class="description"><?php esc_html_e('Primary slug segment used in Pokémon rows (often same as region slug, e.g. paldea, hisui). Leave empty to auto-fill from English form name.', 'poke-hub'); ?></p>
                            </div>
                        </div>
                        <div class="admin-lab-form-col">
                            <div class="admin-lab-form-group">
                                <label for="pokemon_regional_form_slug_aliases"><?php esc_html_e('Extra slug tokens', 'poke-hub'); ?></label>
                                <textarea id="pokemon_regional_form_slug_aliases" name="pokemon_regional_form_slug_aliases" rows="3" cols="60" placeholder="<?php echo esc_attr__('alolan, galarian — comma-separated', 'poke-hub'); ?>"><?php echo esc_textarea($current_pr_form_aliases); ?></textarea>
                                <p class="description"><?php esc_html_e('Alternate slug segments matching the Game Master or Bulbapedia (e.g. hisuian, paldean).', 'poke-hub'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col">
                        <div class="admin-lab-form-group">
                            <label><?php esc_html_e('Icon preview', 'poke-hub'); ?></label>
                            <p class="description"><?php esc_html_e('Resolved automatically from region slug via Settings > Sources > Regions path: {slug}.png', 'poke-hub'); ?></p>
                            <?php $preview_icon_url = ($current_slug !== '' && function_exists('poke_hub_get_region_icon_url')) ? poke_hub_get_region_icon_url($current_slug) : ''; ?>
                            <?php if ($preview_icon_url !== '') : ?>
                                <div style="margin-top:10px;display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border:1px solid #ddd;border-radius:4px;background:#fff;padding:4px;">
                                    <img src="<?php echo esc_url($preview_icon_url); ?>" alt="" style="max-width:100%;max-height:100%;" loading="lazy" decoding="async" />
                                </div>
                                <p class="description" style="margin-top:8px;">
                                    <code><?php echo esc_html($preview_icon_url); ?></code>
                                </p>
                            <?php else : ?>
                                <p class="description" style="margin-top:8px;color:#646970;">
                                    <?php esc_html_e('Save a slug first to show the preview.', 'poke-hub'); ?>
                                </p>
                            <?php endif; ?>
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
