<?php
// modules/pokemon/admin/forms/biome-form.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulaire Add/Edit Biome
 *
 * @param object|null $edit_row
 */
function poke_hub_pokemon_biomes_edit_form($edit_row = null) {
    if (!function_exists('pokehub_get_table')) {
        echo '<div class="wrap"><h1>' . esc_html__('Missing helper pokehub_get_table()', 'poke-hub') . '</h1></div>';
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to edit biomes.', 'poke-hub'));
    }

    global $wpdb;

    $is_edit = ($edit_row && isset($edit_row->id));

    $name_fr = '';
    $name_en = '';
    $slug    = '';
    $description = '';
    $image_urls  = [];
    $pokemon_ids = [];

    if ($is_edit) {
        $name_fr = isset($edit_row->name_fr) ? (string) $edit_row->name_fr : '';
        $name_en = isset($edit_row->name_en) ? (string) $edit_row->name_en : '';
        $slug    = isset($edit_row->slug) ? (string) $edit_row->slug : '';
        $description = isset($edit_row->description) ? (string) $edit_row->description : '';

        if (function_exists('poke_hub_pokemon_get_biome_image_urls')) {
            $image_urls = poke_hub_pokemon_get_biome_image_urls((int) $edit_row->id);
        }

        $links_table = pokehub_get_table('pokemon_biome_pokemon_links');
        $pokemon_table = pokehub_get_table('pokemon');
        if ($links_table && $pokemon_table) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pokemon_id FROM {$links_table} WHERE biome_id = %d ORDER BY pokemon_id ASC",
                    (int) $edit_row->id
                )
            );
            $pokemon_ids = array_map(static function ($row) {
                return (int) $row->pokemon_id;
            }, $rows);
        }
    }

    if (empty($image_urls)) {
        $image_urls = [''];
    }

    $pokemon_table = pokehub_get_table('pokemon');
    $all_pokemon = [];
    if ($pokemon_table) {
        $all_pokemon = $wpdb->get_results(
            "SELECT id, dex_number, name_fr, name_en
             FROM {$pokemon_table}
             ORDER BY dex_number ASC, name_fr ASC, name_en ASC"
        );
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'pokehub-media-url',
        POKE_HUB_URL . 'assets/js/pokehub-media-url.js',
        ['jquery'],
        POKE_HUB_VERSION,
        true
    );

    wp_localize_script('pokehub-media-url', 'pokemonBiomesMedia', [
        'selectTitle' => __('Select or Upload Image', 'poke-hub'),
        'buttonText'  => __('Use this image', 'poke-hub'),
        'tabUrl'      => __('Insert from URL', 'poke-hub'),
        'inputLabel'  => __('Image URL:', 'poke-hub'),
        'inputDesc'   => __('Enter a direct image URL.', 'poke-hub'),
        'noImage'     => __('No row yet.', 'poke-hub'),
    ]);

    $back_url = add_query_arg(
        [
            'page'       => 'poke-hub-pokemon',
            'ph_section' => 'biomes',
        ],
        admin_url('admin.php')
    );
    ?>
    <div class="wrap">
        <?php poke_hub_admin_back_to_list_bar($back_url); ?>
        <h1>
            <?php
            echo $is_edit
                ? esc_html__('Edit biome', 'poke-hub')
                : esc_html__('Add biome', 'poke-hub');
            ?>
        </h1>

        <form method="post">
            <?php wp_nonce_field('poke_hub_pokemon_edit_biome'); ?>
            <input type="hidden" name="page" value="poke-hub-pokemon" />
            <input type="hidden" name="ph_section" value="biomes" />
            <input type="hidden" name="poke_hub_pokemon_action"
                   value="<?php echo $is_edit ? 'update_biome' : 'add_biome'; ?>" />
            <?php if ($is_edit) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row->id; ?>" />
            <?php endif; ?>

            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Names and slug', 'poke-hub'); ?></h3>
                <div class="admin-lab-form-row" style="display: flex; gap: 1em; flex-wrap: wrap;">
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 200px;">
                        <div class="admin-lab-form-group">
                            <label for="name_en"><?php esc_html_e('Name (English)', 'poke-hub'); ?> *</label>
                            <input type="text" id="name_en" name="name_en" value="<?php echo esc_attr($name_en); ?>" required />
                            <p class="description"><?php esc_html_e('Used to generate the slug if the slug field is left empty.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 200px;">
                        <div class="admin-lab-form-group">
                            <label for="name_fr"><?php esc_html_e('Name (French)', 'poke-hub'); ?> *</label>
                            <input type="text" id="name_fr" name="name_fr" value="<?php echo esc_attr($name_fr); ?>" required />
                        </div>
                    </div>
                    <div class="admin-lab-form-col" style="flex: 1; min-width: 200px;">
                        <div class="admin-lab-form-group">
                            <label for="slug"><?php esc_html_e('Slug', 'poke-hub'); ?></label>
                            <input type="text" id="slug" name="slug" value="<?php echo esc_attr($slug); ?>" />
                            <p class="description"><?php esc_html_e('Leave empty to auto-generate from the English name.', 'poke-hub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Description', 'poke-hub'); ?></h3>
                <div class="admin-lab-form-group">
                    <label for="description"><?php esc_html_e('Description', 'poke-hub'); ?></label>
                    <?php
                    wp_editor(
                        $description,
                        'description',
                        [
                            'textarea_name' => 'description',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                            'teeny'         => true,
                        ]
                    );
                    ?>
                </div>
            </div>

            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('In-game background images', 'poke-hub'); ?></h3>
                <p class="description"><?php esc_html_e('One URL per visual variant (order is preserved).', 'poke-hub'); ?></p>
                <div id="pokehub-biome-images-list">
                    <?php foreach ($image_urls as $idx => $url) : ?>
                        <div class="pokehub-biome-image-row" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:10px;flex-wrap:wrap;">
                            <input type="url" name="biome_image_urls[]" class="regular-text pokehub-biome-image-url"
                                   value="<?php echo esc_attr($url); ?>" style="flex:1;min-width:220px;" />
                            <button type="button" class="button pokehub-select-biome-image"><?php esc_html_e('Media library', 'poke-hub'); ?></button>
                            <button type="button" class="button pokehub-remove-biome-image-row"><?php esc_html_e('Remove', 'poke-hub'); ?></button>
                            <div class="pokehub-biome-image-preview-wrap" style="width:100%;">
                                <?php if ($url !== '') : ?>
                                    <img src="<?php echo esc_url($url); ?>" alt="" class="pokehub-biome-image-preview" style="max-width:160px;height:auto;border:1px solid #c3c4c7;padding:4px;background:#fff;" />
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p>
                    <button type="button" class="button" id="pokehub-add-biome-image-row"><?php esc_html_e('Add image row', 'poke-hub'); ?></button>
                </p>
            </div>

            <div class="admin-lab-form-section">
                <h3><?php esc_html_e('Pokémon in this biome', 'poke-hub'); ?></h3>
                <div class="admin-lab-form-group">
                    <label for="biome_pokemon_ids"><?php esc_html_e('Pokémon', 'poke-hub'); ?></label>
                    <select name="biome_pokemon_ids[]" id="biome_pokemon_ids" class="pokehub-pokemon-select" multiple="multiple" style="width:100%;max-width:640px;">
                        <?php foreach ($all_pokemon as $pokemon) : ?>
                            <?php
                            $p_id = (int) $pokemon->id;
                            $p_dex = (int) $pokemon->dex_number;
                            $p_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
                            $label = sprintf('#%03d %s', $p_dex, $p_name);
                            ?>
                            <option value="<?php echo $p_id; ?>"
                                    data-name-fr="<?php echo esc_attr(!empty($pokemon->name_fr) ? $pokemon->name_fr : ''); ?>"
                                    data-name-en="<?php echo esc_attr(!empty($pokemon->name_en) ? $pokemon->name_en : ''); ?>"
                                    data-label="<?php echo esc_attr($label); ?>"
                                    <?php selected(in_array($p_id, $pokemon_ids, true)); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                       value="<?php echo $is_edit ? esc_attr__('Update', 'poke-hub') : esc_attr__('Add', 'poke-hub'); ?>" />
                <a href="<?php echo esc_url($back_url); ?>" class="button"><?php esc_html_e('Cancel', 'poke-hub'); ?></a>
            </p>
        </form>
    </div>

    <template id="pokehub-biome-image-row-tpl">
        <div class="pokehub-biome-image-row" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:10px;flex-wrap:wrap;">
            <input type="url" name="biome_image_urls[]" class="regular-text pokehub-biome-image-url" value="" style="flex:1;min-width:220px;" />
            <button type="button" class="button pokehub-select-biome-image"><?php echo esc_html__('Media library', 'poke-hub'); ?></button>
            <button type="button" class="button pokehub-remove-biome-image-row"><?php echo esc_html__('Remove', 'poke-hub'); ?></button>
            <div class="pokehub-biome-image-preview-wrap" style="width:100%;"></div>
        </div>
    </template>

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
        if ($.fn.select2) {
            $('#biome_pokemon_ids').select2({
                placeholder: '<?php echo esc_js(__('Search Pokémon…', 'poke-hub')); ?>',
                allowClear: true,
                width: '100%',
                matcher: matcherFn
            });
        }

        $('#pokehub-add-biome-image-row').on('click', function() {
            var tpl = document.getElementById('pokehub-biome-image-row-tpl');
            if (!tpl || !tpl.content) return;
            $('#pokehub-biome-images-list').append(tpl.content.cloneNode(true));
        });
        $(document).on('click', '.pokehub-remove-biome-image-row', function() {
            var $list = $('#pokehub-biome-images-list');
            if ($list.find('.pokehub-biome-image-row').length <= 1) {
                $(this).closest('.pokehub-biome-image-row').find('.pokehub-biome-image-url').val('');
                $(this).closest('.pokehub-biome-image-row').find('.pokehub-biome-image-preview-wrap').empty();
                return;
            }
            $(this).closest('.pokehub-biome-image-row').remove();
        });

        function setRowPreview($row, url) {
            var $w = $row.find('.pokehub-biome-image-preview-wrap');
            if (!url) { $w.empty(); return; }
            $w.html('<img src="' + url.replace(/"/g, '&quot;') + '" alt="" class="pokehub-biome-image-preview" style="max-width:160px;height:auto;border:1px solid #c3c4c7;padding:4px;background:#fff;" />');
        }

        $(document).on('click', '.pokehub-select-biome-image', function(e) {
            e.preventDefault();
            var $row = $(this).closest('.pokehub-biome-image-row');
            var $urlInput = $row.find('.pokehub-biome-image-url');

            var frame = new wp.media.view.MediaFrame.PokeHubTypes({
                title: (window.pokemonBiomesMedia && pokemonBiomesMedia.selectTitle) || 'Select image',
                button: { text: (window.pokemonBiomesMedia && pokemonBiomesMedia.buttonText) || 'Use' },
                multiple: false
            });

            frame.on('select', function() {
                var att = frame.state().get('selection').first();
                if (!att) return;
                var data = att.toJSON();
                if (!data.url) return;
                $urlInput.val(data.url);
                setRowPreview($row, data.url);
            });

            frame.on('insert', function(state) {
                if (!state || state.id !== 'pokehub-types-url') return;
                var url = state.props.get('url');
                if (!url) return;
                $urlInput.val(url);
                setRowPreview($row, url);
            });

            frame.open();
        });

        $(document).on('input change', '.pokehub-biome-image-url', function() {
            var $row = $(this).closest('.pokehub-biome-image-row');
            setRowPreview($row, $(this).val());
        });
    });
    </script>
    <?php
}
