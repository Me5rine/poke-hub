<?php
// modules/pokemon/admin/forms/background-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Background
 *
 * @param object|null $edit_row
 */
function poke_hub_pokemon_backgrounds_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit backgrounds.', 'poke-hub'));
    }

    if (function_exists('pokehub_install_tables_for_modules')) {
        pokehub_install_tables_for_modules(['pokemon'], ['skip_allow_filter' => true, 'try_require_db_class' => true]);
    }
    if (function_exists('poke_hub_ensure_background_pokemon_link_unique_index')) {
        poke_hub_ensure_background_pokemon_link_unique_index();
    }

    global $wpdb;

    $is_edit = ($edit_row && isset($edit_row->id));

    // Valeurs par défaut / édition
    $title = '';
    $name_fr = '';
    $name_en = '';
    $slug = '';
    $background_type = defined('POKE_HUB_BACKGROUND_TYPE_SPECIAL') ? POKE_HUB_BACKGROUND_TYPE_SPECIAL : 'special';
    $image_url = '';
    $current_events = [];
    $current_pokemon_ids = [];
    $current_shiny_locked_ids = [];
    $current_shiny_active_ids = [];
    $current_shadow_ids = [];
    $current_dynamax_ids = [];
    $current_gigantamax_ids = [];

    if ($is_edit) {
        $title = isset($edit_row->title) ? (string) $edit_row->title : '';
        $name_fr = isset($edit_row->name_fr) ? (string) $edit_row->name_fr : '';
        $name_en = isset($edit_row->name_en) ? (string) $edit_row->name_en : '';
        $slug = isset($edit_row->slug) ? (string) $edit_row->slug : '';
        if (isset($edit_row->background_type) && (string) $edit_row->background_type !== '') {
            $background_type = (string) $edit_row->background_type;
        }

        // Événements associés (plusieurs par fond)
        if (function_exists('poke_hub_get_background_events')) {
            $current_events = poke_hub_get_background_events((int) $edit_row->id);
        }
        if (empty($current_events) && isset($edit_row->event_id) && (int) $edit_row->event_id > 0 && !empty(trim((string) ($edit_row->event_type ?? '')))) {
            $current_events = [['event_type' => (string) $edit_row->event_type, 'event_id' => (int) $edit_row->event_id]];
        }

        $links_table = pokehub_get_table('pokemon_background_pokemon_links');
        $link_base  = defined('POKE_HUB_BG_LINK_BASE') ? POKE_HUB_BG_LINK_BASE : 'base';
        $link_sh    = defined('POKE_HUB_BG_LINK_SHADOW') ? POKE_HUB_BG_LINK_SHADOW : 'shadow';
        $link_dx    = defined('POKE_HUB_BG_LINK_DYNAMAX') ? POKE_HUB_BG_LINK_DYNAMAX : 'dynamax';
        $link_gx    = defined('POKE_HUB_BG_LINK_GIGANTAMAX') ? POKE_HUB_BG_LINK_GIGANTAMAX : 'gigantamax';

        if ($links_table) {
            $link_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pokemon_id, is_shiny_locked, link_kind FROM {$links_table} WHERE background_id = %d",
                    (int) $edit_row->id
                )
            );
            if (is_array($link_rows)) {
                foreach ($link_rows as $ln) {
                    $pid = (int) $ln->pokemon_id;
                    if ($pid <= 0) {
                        continue;
                    }
                    $k = isset($ln->link_kind) ? (string) $ln->link_kind : $link_base;
                    if ($k === '' || $k === '0') {
                        $k = $link_base;
                    }
                    if ($k === $link_base) {
                        $current_pokemon_ids[] = $pid;
                        if ((int) $ln->is_shiny_locked === 1) {
                            $current_shiny_locked_ids[] = $pid;
                        } else {
                            $current_shiny_active_ids[] = $pid;
                        }
                    } elseif ($k === $link_sh) {
                        $current_shadow_ids[] = $pid;
                    } elseif ($k === $link_dx) {
                        $current_dynamax_ids[] = $pid;
                    } elseif ($k === $link_gx) {
                        $current_gigantamax_ids[] = $pid;
                    }
                }
            }
        }
    }

    // Tous les événements pour le picker (recherche par nom, tous types)
    $all_events = function_exists('poke_hub_get_events_for_picker') ? poke_hub_get_events_for_picker() : [];

    // Récupérer tous les Pokémon
    $pokemon_table = pokehub_get_table('pokemon');
    $all_pokemon = [];
    if ($pokemon_table) {
        $all_pokemon = $wpdb->get_results(
            "SELECT id, dex_number, name_fr, name_en
             FROM {$pokemon_table}
             ORDER BY dex_number ASC, name_fr ASC, name_en ASC"
        );
    }
    $pokemon_shadow = function_exists('poke_hub_get_pokemon_list_for_background_link_kind')
        ? poke_hub_get_pokemon_list_for_background_link_kind(defined('POKE_HUB_BG_LINK_SHADOW') ? POKE_HUB_BG_LINK_SHADOW : 'shadow') : [];
    $pokemon_dynamax = function_exists('poke_hub_get_pokemon_list_for_background_link_kind')
        ? poke_hub_get_pokemon_list_for_background_link_kind(defined('POKE_HUB_BG_LINK_DYNAMAX') ? POKE_HUB_BG_LINK_DYNAMAX : 'dynamax') : [];
    $pokemon_gigantamax = function_exists('poke_hub_get_pokemon_list_for_background_link_kind')
        ? poke_hub_get_pokemon_list_for_background_link_kind(defined('POKE_HUB_BG_LINK_GIGANTAMAX') ? POKE_HUB_BG_LINK_GIGANTAMAX : 'gigantamax') : [];

    // Variantes : SQL filtré + liens déjà enregistrés + tout Pokémon déjà en « classique » (sinon pas d’option / pas de POST).
    if ( $is_edit && function_exists( 'poke_hub_merge_pokemon_filter_rows_with_saved_ids' ) ) {
        $ids_for_variants = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        array_merge(
                            $current_shiny_active_ids,
                            $current_shiny_locked_ids,
                            $current_dynamax_ids,
                            $current_gigantamax_ids,
                            $current_shadow_ids
                        )
                    ),
                    static function ( int $x ): bool {
                        return $x > 0;
                    }
                )
            )
        );
        $pokemon_dynamax    = poke_hub_merge_pokemon_filter_rows_with_saved_ids( $pokemon_dynamax, $ids_for_variants );
        $pokemon_gigantamax = poke_hub_merge_pokemon_filter_rows_with_saved_ids( $pokemon_gigantamax, $ids_for_variants );
        $pokemon_shadow     = poke_hub_merge_pokemon_filter_rows_with_saved_ids( $pokemon_shadow, $ids_for_variants );
    }

    if ($name_fr === '' && $title !== '') {
        $name_fr = $title;
    }
    $preview_slug = trim($slug);
    if ($preview_slug === '') {
        if ($name_en !== '') {
            $preview_slug = sanitize_title($name_en);
        } elseif ($name_fr !== '') {
            $preview_slug = sanitize_title($name_fr);
        } elseif ($title !== '') {
            $preview_slug = sanitize_title($title);
        }
    }
    $image_url = function_exists('poke_hub_get_background_image_url')
        ? poke_hub_get_background_image_url($preview_slug)
        : '';

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'backgrounds',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <?php poke_hub_admin_back_to_list_bar($back_url); ?>
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit background', 'poke-hub')
                : esc_html__('Add background', 'poke-hub');
            ?>
        </h1>

        <form method="post" id="pokehub-background-edit-form" class="pokehub-background-edit-form">
            <?php wp_nonce_field('poke_hub_pokemon_edit_background'); ?>
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="backgrounds" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_background' : 'add_background'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <!-- Section: Basic Information -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Basic Information', 'poke-hub'); ?></h3>
                <div class="admin-lab-form-row" style="display: flex; gap: 1em;">
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 0;">
                        <div class="admin-lab-form-group">
                            <label for="title"><?php esc_html_e('Internal title', 'poke-hub'); ?></label>
                            <input type="text" id="title" name="title" value="<?php echo esc_attr($title); ?>" />
                            <p class="description"><?php esc_html_e('Optional internal label in admin. If empty, translation names are used.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 0;">
                        <div class="admin-lab-form-group">
                            <label for="slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                            <input type="text" id="slug" name="slug" value="<?php echo esc_attr($slug); ?>" />
                            <p class="description"><?php esc_html_e('Leave empty to auto-generate from English name.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 0;">
                        <div class="admin-lab-form-group">
                            <label for="background_type"><?php esc_html_e('Background type', 'poke-hub'); ?></label>
                            <select id="background_type" name="background_type">
                                <?php
                                $background_types = function_exists('poke_hub_get_background_types') ? poke_hub_get_background_types() : ['location' => __('Location background', 'poke-hub'), 'special' => __('Special background', 'poke-hub')];
                                foreach ($background_types as $type_value => $type_label) :
                                    ?>
                                    <option value="<?php echo esc_attr($type_value); ?>" <?php selected($background_type, $type_value); ?>><?php echo esc_html($type_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Location or special (event/theme).', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Translations -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Translations', 'poke-hub'); ?></h3>
                <p class="description"><?php esc_html_e('Used in front-end labels. English name is used to auto-generate slug when empty.', 'poke-hub'); ?></p>
                <div class="admin-lab-form-row">
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="name_fr"><?php esc_html_e('French Name', 'poke-hub'); ?></label>
                            <input type="text" id="name_fr" name="name_fr" value="<?php echo esc_attr($name_fr); ?>" />
                        </div>
                    </div>
                    <div class="admin-lab-form-col-50">
                        <div class="admin-lab-form-group">
                            <label for="name_en"><?php esc_html_e('English Name', 'poke-hub'); ?> *</label>
                            <input type="text" id="name_en" name="name_en" value="<?php echo esc_attr($name_en); ?>" required />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Background Image -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Background Image', 'poke-hub'); ?></h3>
                <div class="admin-lab-form-group">
                    <label><?php esc_html_e('Image preview', 'poke-hub'); ?></label>
                    <p class="description">
                        <?php esc_html_e('The image URL is generated automatically from settings (Sources > Backgrounds path) and the background slug: {bucket}{path}{slug}.png.', 'poke-hub'); ?>
                    </p>
                    <?php if ($image_url !== '') : ?>
                        <img src="<?php echo esc_url($image_url); ?>"
                             class="pokehub-background-image-preview"
                             style="width:300px;height:180px;display:block;object-fit:cover;object-position:center 12%;transform:scale(1.08);transform-origin:center top;border:1px solid #c3c4c7;padding:0;background:#fff;border-radius:4px;" />
                        <p class="description" style="margin-top:8px;">
                            <code><?php echo esc_html($image_url); ?></code>
                        </p>
                    <?php else : ?>
                        <p class="description" style="margin-top:8px;color:#646970;">
                            <?php esc_html_e('Set an English name or slug to preview. Also verify Sources settings for bucket URL and backgrounds path.', 'poke-hub'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section: Event Association (sélecteur unique : recherche par nom, tous types) -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Event Association', 'poke-hub'); ?></h3>
                <p class="description"><?php esc_html_e('Search and select one or more events by name. Event type is detected automatically.', 'poke-hub'); ?></p>
                <div id="pokehub-background-events-list">
                    <?php
                    $event_index = 0;
                    foreach ($current_events as $ev) :
                        $ev_type = isset($ev['event_type']) ? (string) $ev['event_type'] : '';
                        $ev_id = isset($ev['event_id']) ? (int) $ev['event_id'] : 0;
                        if (function_exists('poke_hub_render_event_picker_row')) {
                            poke_hub_render_event_picker_row($event_index, $ev_id, $ev_type, $all_events, 'event_links', 'pokehub-background-event-row', null, 'pokehub-remove-event');
                        }
                        $event_index++;
                    endforeach;
                    ?>
                </div>
                <p><button type="button" class="button pokehub-add-event"><?php esc_html_e('Add event', 'poke-hub'); ?></button></p>
                <?php if (function_exists('poke_hub_render_event_picker_row')) : ?>
                <template id="pokehub-background-event-row-tpl">
                    <?php poke_hub_render_event_picker_row('__INDEX__', 0, '', $all_events, 'event_links', 'pokehub-background-event-row', null, 'pokehub-remove-event'); ?>
                </template>
                <?php endif; ?>
            </div>

            <!-- Section: Linked Pokémon (classique : shiny) + obscur / dynamax / gigamax (collections) -->
            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Linked Pokémon', 'poke-hub'); ?></h3>
                <p class="description" style="margin-bottom:12px;"><?php esc_html_e('The same Pokémon ID can be selected in classic (shiny) and in each variant. Pick classic lists first, or reload after a save: every Pokémon listed under classic (or already saved in a variant) is offered in the three variant fields so the form can post them. If a combined save still drops rows, reload once so the unique index (background, Pokémon, mode) is applied.', 'poke-hub'); ?></p>
                <h4 style="margin-top:0;"><?php esc_html_e('Classic (standard forms)', 'poke-hub'); ?></h4>
                <p class="description" style="margin-bottom:12px;"><?php esc_html_e('Two separate lists: Pokémon with shiny available for this background, and Pokémon that are shiny lock. A Pokémon can only appear in one of the two lists below.', 'poke-hub'); ?></p>
                <div class="admin-lab-form-row" style="display: flex; gap: 1em; flex-wrap: wrap; align-items: flex-start;">
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 0; min-width: 280px;">
                        <div class="admin-lab-form-group">
                            <label for="pokemon_ids_shiny_active"><?php esc_html_e('Pokémon (shiny active)', 'poke-hub'); ?></label>
                            <select name="pokemon_ids_shiny_active[]" id="pokemon_ids_shiny_active" class="pokehub-pokemon-select" multiple="multiple" style="width:100%;">
                                <?php if (!empty($all_pokemon)) : ?>
                                    <?php foreach ($all_pokemon as $pokemon) : ?>
                                        <?php
                                        $p_id = (int) $pokemon->id;
                                        $p_dex = (int) $pokemon->dex_number;
                                        $p_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
                                        $label = sprintf('#%03d %s', $p_dex, esc_html($p_name));
                                        ?>
                                        <option value="<?php echo $p_id; ?>"
                                                data-name-fr="<?php echo esc_attr(!empty($pokemon->name_fr) ? $pokemon->name_fr : ''); ?>"
                                                data-name-en="<?php echo esc_attr(!empty($pokemon->name_en) ? $pokemon->name_en : ''); ?>"
                                                data-label="<?php echo esc_attr($label); ?>"
                                                <?php selected(in_array($p_id, $current_shiny_active_ids, true)); ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Shiny available for this background.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 0; min-width: 280px;">
                        <div class="admin-lab-form-group">
                            <label for="shiny_locked_ids"><?php esc_html_e('Pokémon (shiny locked)', 'poke-hub'); ?></label>
                            <select name="shiny_locked_ids[]" id="shiny_locked_ids" class="pokehub-pokemon-select pokehub-shiny-lock-select" multiple="multiple" style="width:100%;">
                                <?php if (!empty($all_pokemon)) : ?>
                                    <?php foreach ($all_pokemon as $pokemon) : ?>
                                        <?php
                                        $p_id = (int) $pokemon->id;
                                        $p_dex = (int) $pokemon->dex_number;
                                        $p_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
                                        $label = sprintf('#%03d %s', $p_dex, esc_html($p_name));
                                        ?>
                                        <option value="<?php echo $p_id; ?>"
                                                data-name-fr="<?php echo esc_attr(!empty($pokemon->name_fr) ? $pokemon->name_fr : ''); ?>"
                                                data-name-en="<?php echo esc_attr(!empty($pokemon->name_en) ? $pokemon->name_en : ''); ?>"
                                                data-label="<?php echo esc_attr($label); ?>"
                                                <?php selected(in_array($p_id, $current_shiny_locked_ids, true)); ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Shiny lock (background released before the shiny).', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
                <h4 style="margin-top:1.25em;"><?php esc_html_e('Variant displays (Dynamax / Gigantamax / Shadow)', 'poke-hub'); ?></h4>
                <p class="description" style="margin-bottom:10px;"><?php esc_html_e('Filtered Pokédex lists. First row: Dynamax and Gigantamax side by side. Second row: Shadow (obscur) on full width.', 'poke-hub'); ?></p>
                <style>
                .pokehub-bg-variant-grid { display:grid; grid-template-columns:1fr 1fr; gap:1em 1.5em; align-items:start; margin-top:0.5em; }
                @media (max-width:782px) { .pokehub-bg-variant-grid { grid-template-columns:1fr; } }
                </style>
                <div class="pokehub-bg-variant-grid">
                    <div class="admin-lab-form-group" style="min-width:0;">
                        <h4 style="margin:0 0 0.25em 0;"><?php esc_html_e('Dynamax', 'poke-hub'); ?></h4>
                        <p class="description" style="margin-bottom:8px;"><?php esc_html_e('Pokémon with a Dynamax form or a Dynamax release date in the database.', 'poke-hub'); ?></p>
                        <label class="screen-reader-text" for="pokemon_ids_dynamax"><?php esc_html_e('Pokémon (Dynamax)', 'poke-hub'); ?></label>
                        <select name="pokemon_ids_dynamax[]" id="pokemon_ids_dynamax" class="pokehub-pokemon-select" multiple="multiple" style="width:100%;">
                            <?php if (!empty($pokemon_dynamax)) : ?>
                                <?php foreach ($pokemon_dynamax as $pokemon) : ?>
                                    <?php
                                    $p_id = (int) $pokemon->id;
                                    $p_dex = (int) $pokemon->dex_number;
                                    $p_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
                                    $label = sprintf('#%03d %s', $p_dex, esc_html($p_name));
                                    ?>
                                    <option value="<?php echo $p_id; ?>"
                                            data-name-fr="<?php echo esc_attr(!empty($pokemon->name_fr) ? $pokemon->name_fr : ''); ?>"
                                            data-name-en="<?php echo esc_attr(!empty($pokemon->name_en) ? $pokemon->name_en : ''); ?>"
                                            data-label="<?php echo esc_attr($label); ?>"
                                            <?php selected(in_array($p_id, $current_dynamax_ids, true)); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="admin-lab-form-group" style="min-width:0;">
                        <h4 style="margin:0 0 0.25em 0;"><?php esc_html_e('Gigantamax', 'poke-hub'); ?></h4>
                        <p class="description" style="margin-bottom:8px;"><?php esc_html_e('Pokémon with a Gigantamax form or a Gigantamax release date in the database.', 'poke-hub'); ?></p>
                        <label class="screen-reader-text" for="pokemon_ids_gigantamax"><?php esc_html_e('Pokémon (Gigantamax)', 'poke-hub'); ?></label>
                        <select name="pokemon_ids_gigantamax[]" id="pokemon_ids_gigantamax" class="pokehub-pokemon-select" multiple="multiple" style="width:100%;">
                            <?php if (!empty($pokemon_gigantamax)) : ?>
                                <?php foreach ($pokemon_gigantamax as $pokemon) : ?>
                                    <?php
                                    $p_id = (int) $pokemon->id;
                                    $p_dex = (int) $pokemon->dex_number;
                                    $p_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
                                    $label = sprintf('#%03d %s', $p_dex, esc_html($p_name));
                                    ?>
                                    <option value="<?php echo $p_id; ?>"
                                            data-name-fr="<?php echo esc_attr(!empty($pokemon->name_fr) ? $pokemon->name_fr : ''); ?>"
                                            data-name-en="<?php echo esc_attr(!empty($pokemon->name_en) ? $pokemon->name_en : ''); ?>"
                                            data-label="<?php echo esc_attr($label); ?>"
                                            <?php selected(in_array($p_id, $current_gigantamax_ids, true)); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="admin-lab-form-group" style="grid-column:1 / -1;min-width:0;">
                        <h4 style="margin:0 0 0.25em 0;"><?php esc_html_e('Shadow (Obscur)', 'poke-hub'); ?></h4>
                        <p class="description" style="margin-bottom:8px;"><?php esc_html_e('Only species with a non-empty Shadow release in extra (JSON path release.shadow), same as the Shadow collection. Not the whole has_shadow flag.', 'poke-hub'); ?></p>
                        <label class="screen-reader-text" for="pokemon_ids_shadow"><?php esc_html_e('Pokémon (Shadow)', 'poke-hub'); ?></label>
                        <select name="pokemon_ids_shadow[]" id="pokemon_ids_shadow" class="pokehub-pokemon-select" multiple="multiple" style="width:100%;">
                            <?php if (!empty($pokemon_shadow)) : ?>
                                <?php foreach ($pokemon_shadow as $pokemon) : ?>
                                    <?php
                                    $p_id = (int) $pokemon->id;
                                    $p_dex = (int) $pokemon->dex_number;
                                    $p_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
                                    $label = sprintf('#%03d %s', $p_dex, esc_html($p_name));
                                    ?>
                                    <option value="<?php echo $p_id; ?>"
                                            data-name-fr="<?php echo esc_attr(!empty($pokemon->name_fr) ? $pokemon->name_fr : ''); ?>"
                                            data-name-en="<?php echo esc_attr(!empty($pokemon->name_en) ? $pokemon->name_en : ''); ?>"
                                            data-label="<?php echo esc_attr($label); ?>"
                                            <?php selected(in_array($p_id, $current_shadow_ids, true)); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
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

    <script type="text/javascript">
    jQuery(function($) {
        var matcherFn = window.pokehubMultilingualMatcher || function(params, data) {
            if (!params.term || params.term.trim() === '') return data;
            var term = params.term.toLowerCase().trim();
            var text = (data.text || '').toLowerCase();
            if (text.indexOf(term) !== -1) return data;
            if (data.element) {
                var el = data.element;
                var nameFr = (el.getAttribute && el.getAttribute('data-name-fr') || '').toLowerCase();
                var nameEn = (el.getAttribute && el.getAttribute('data-name-en') || '').toLowerCase();
                if (nameFr && nameFr.indexOf(term) !== -1) return data;
                if (nameEn && nameEn.indexOf(term) !== -1) return data;
            }
            return null;
        };

        // Initialiser Select2 sur les champs Pokémon
        if ($.fn.select2) {
            var s2 = { allowClear: true, width: '100%', matcher: matcherFn };
            $('#pokemon_ids_shiny_active').select2($.extend({ placeholder: '<?php echo esc_js(__('Search Pokémon (shiny active)...', 'poke-hub')); ?>' }, s2));
            $('#shiny_locked_ids').select2($.extend({ placeholder: '<?php echo esc_js(__('Search Pokémon (shiny locked)...', 'poke-hub')); ?>' }, s2));
            $('#pokemon_ids_dynamax').select2($.extend({ placeholder: '<?php echo esc_js(__('Search Dynamax Pokémon...', 'poke-hub')); ?>' }, s2));
            $('#pokemon_ids_gigantamax').select2($.extend({ placeholder: '<?php echo esc_js(__('Search Gigantamax Pokémon...', 'poke-hub')); ?>' }, s2));
            $('#pokemon_ids_shadow').select2($.extend({ placeholder: '<?php echo esc_js(__('Search Shadow Pokémon...', 'poke-hub')); ?>' }, s2));
        }

        // Les deux selects sont exclusifs : un Pokémon ne peut être que dans l'un ou l'autre
        function removeFromOtherSelect(sourceSelectId, addedId) {
            var otherId = (sourceSelectId === 'pokemon_ids_shiny_active') ? 'shiny_locked_ids' : 'pokemon_ids_shiny_active';
            var $other = $('#' + otherId);
            var val = ($other.val() || []).filter(function(x) { return x != addedId; });
            if (val.length !== ($other.val() || []).length) {
                $other.val(val).trigger('change');
            }
        }
        function pokehubEnsureVariantOptionsFromChoice(data) {
            if (!data || data.id == null || data.id === '') {
                return;
            }
            var id = String(data.id);
            var $targets = $('#pokemon_ids_dynamax, #pokemon_ids_gigantamax, #pokemon_ids_shadow');
            $targets.each(function() {
                var $sel = $(this);
                if ($sel.find('option').filter(function() { return String($(this).val()) === id; }).length) {
                    return;
                }
                var $src = data.element ? $(data.element) : $();
                var text = (data.text != null && data.text !== '') ? String(data.text) : ( $src.length ? $src.text() : id );
                var $opt = $('<option></option>').val(id).text(text);
                if ($src.length) {
                    $opt.attr('data-name-fr', $src.attr('data-name-fr') || '');
                    $opt.attr('data-name-en', $src.attr('data-name-en') || '');
                    $opt.attr('data-label', $src.attr('data-label') || text);
                }
                $sel.append($opt);
                if ($sel.hasClass('select2-hidden-accessible')) {
                    $sel.trigger('change');
                }
            });
        }
        function pokehubSyncClassicSelectionsToVariantOptions() {
            $('#pokemon_ids_shiny_active, #shiny_locked_ids').find('option:selected').each(function() {
                var $o = $(this);
                pokehubEnsureVariantOptionsFromChoice({
                    id: $o.val(),
                    text: $o.text(),
                    element: this
                });
            });
        }
        $('#pokemon_ids_shiny_active').on('select2:select', function(e) {
            removeFromOtherSelect('pokemon_ids_shiny_active', e.params.data.id);
            pokehubEnsureVariantOptionsFromChoice(e.params.data);
        });
        $('#shiny_locked_ids').on('select2:select', function(e) {
            removeFromOtherSelect('shiny_locked_ids', e.params.data.id);
            pokehubEnsureVariantOptionsFromChoice(e.params.data);
        });
        pokehubSyncClassicSelectionsToVariantOptions();

        // Select2 peut laisser le <select> natif hors sync : destroy avant envoi pour que le navigateur poste toutes les valeurs.
        $('#pokehub-background-edit-form').on('submit', function() {
            if (!$.fn.select2) {
                return;
            }
            $(this).find('select.pokehub-pokemon-select').each(function() {
                var $s = $(this);
                if ($s.data('select2')) {
                    $s.select2('destroy');
                }
            });
        });

        // Sync champ caché event_type depuis l'option sélectionnée (data-source)
        $(document).on('change', '.pokehub-event-picker-select', function() {
            var $select = $(this);
            var $row = $select.closest('.pokehub-event-picker-row');
            var $hidden = $row.find('.pokehub-event-picker-type');
            var $opt = $select.find('option:selected');
            var src = $opt.length ? ($opt.data('source') || '') : '';
            $hidden.val(src);
        });
        $('#pokehub-background-events-list .pokehub-event-picker-select').each(function() {
            $(this).trigger('change');
        });

        // Événements : ajouter une ligne
        var eventRowIndex = <?php echo (int) count($current_events); ?>;
        $('.pokehub-add-event').on('click', function() {
            var tpl = document.getElementById('pokehub-background-event-row-tpl');
            if (!tpl || !tpl.content) return;
            var html = tpl.innerHTML.replace(/__INDEX__/g, eventRowIndex);
            $('#pokehub-background-events-list').append(html);
            eventRowIndex++;
            reindexEventRows();
            if ($.fn.select2) {
                $('#pokehub-background-events-list .pokehub-background-event-row').last().find('.pokehub-event-picker-select').select2({ placeholder: '<?php echo esc_js(__('Search event...', 'poke-hub')); ?>', allowClear: true, width: '100%' });
            }
        });
        $(document).on('click', '.pokehub-remove-event', function() {
            $(this).closest('.pokehub-background-event-row').remove();
            reindexEventRows();
        });
        function reindexEventRows() {
            $('#pokehub-background-events-list .pokehub-background-event-row').each(function(i) {
                $(this).find('.pokehub-event-picker-type').attr('name', 'event_links[' + i + '][event_type]');
                $(this).find('.pokehub-event-picker-select').attr('name', 'event_links[' + i + '][event_id]');
            });
        }
        if ($.fn.select2) {
            $('#pokehub-background-events-list .pokehub-event-picker-select').select2({ placeholder: '<?php echo esc_js(__('Search event...', 'poke-hub')); ?>', allowClear: true, width: '100%' });
        }
    });
    </script>
    <?php
}
