<?php
// modules/eggs/admin/eggs-metabox.php – Œufs : une ligne = rareté + Pokémon + forced shiny + worldwide

if (!defined('ABSPATH')) {
    exit;
}

function pokehub_add_eggs_metabox() {
    $screens = apply_filters('pokehub_eggs_post_types', ['post', 'pokehub_event']);
    foreach ($screens as $screen) {
        add_meta_box(
            'pokehub_eggs',
            __('Eggs', 'poke-hub'),
            'pokehub_render_eggs_metabox',
            $screen,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'pokehub_add_eggs_metabox');

function pokehub_eggs_metabox_assets($hook) {
    global $post_type;
    $allowed = apply_filters('pokehub_eggs_post_types', ['post', 'pokehub_event']);
    if (!in_array($post_type, $allowed, true)) {
        return;
    }
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
    wp_enqueue_script('pokehub-admin-select2', POKE_HUB_URL . 'assets/js/pokehub-admin-select2.js', ['jquery', 'select2'], POKE_HUB_VERSION, true);
    $pokemon_list = function_exists('pokehub_get_pokemon_for_select') ? pokehub_get_pokemon_for_select() : [];
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
        'pokemon' => $pokemon_list,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_eggs_ajax'),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'rest_pokemon_url' => rest_url('poke-hub/v1/pokemon-for-select'),
    ]);
    wp_localize_script('pokehub-admin-select2', 'pokehubPokemonGenderConfig', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('pokehub_check_pokemon_gender_dimorphism_nonce'),
    ]);
}
add_action('admin_enqueue_scripts', 'pokehub_eggs_metabox_assets');

/**
 * Sauvegarde : blocks[egg_type_id]->rows[] = { rarity, pokemon[], forced_shiny[], worldwide[] }
 */
function pokehub_save_eggs_metabox($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (empty($_POST['pokehub_eggs_nonce']) || !wp_verify_nonce($_POST['pokehub_eggs_nonce'], 'pokehub_save_eggs')) {
        return;
    }

    $blocks = isset($_POST['pokehub_eggs']) && is_array($_POST['pokehub_eggs']) ? $_POST['pokehub_eggs'] : [];
    $by_type = [];

    foreach ($blocks as $block) {
        $et_id = isset($block['egg_type_id']) ? (int) $block['egg_type_id'] : 0;
        if ($et_id <= 0) {
            continue;
        }
        $rows = isset($block['rows']) && is_array($block['rows']) ? $block['rows'] : [];
        $entries = [];

        foreach ($rows as $row) {
            $r = isset($row['rarity']) ? max(1, min(5, (int) $row['rarity'])) : 1;
            $pokemon_raw = isset($row['pokemon']) && is_array($row['pokemon']) ? wp_unslash($row['pokemon']) : [];
            $gender_map_post = isset($row['pokemon_genders']) && is_array($row['pokemon_genders']) ? wp_unslash($row['pokemon_genders']) : [];
            if (function_exists('pokehub_parse_post_pokemon_multiselect_tokens_with_genders')) {
                $parsed_p = pokehub_parse_post_pokemon_multiselect_tokens_with_genders($pokemon_raw, $gender_map_post);
                $pids = $parsed_p['pokemon_ids'];
                $gender_map = [];
                foreach ($parsed_p['pokemon_genders'] as $gk => $gv) {
                    $gender_map[(int) $gk] = $gv;
                }
            } else {
                $pids = array_map('intval', array_filter($pokemon_raw));
                $gender_map = [];
                foreach ($gender_map_post as $pid => $gender) {
                    $pid = (int) $pid;
                    $gender = is_string($gender) ? sanitize_key($gender) : '';
                    if ($pid > 0 && in_array($gender, ['male', 'female'], true)) {
                        $gender_map[$pid] = $gender;
                    }
                }
            }
            $forced = isset($row['forced_shiny']) && is_array($row['forced_shiny']) ? array_map('intval', array_filter($row['forced_shiny'])) : [];
            $ww = isset($row['worldwide']) && is_array($row['worldwide']) ? array_map('intval', array_filter($row['worldwide'])) : [];

            foreach ($pids as $pid) {
                if ($pid <= 0) {
                    continue;
                }
                $entries[$pid] = [
                    'pokemon_id'            => $pid,
                    'rarity'                => $r,
                    'is_forced_shiny'       => in_array($pid, $forced, true),
                    'is_worldwide_override' => in_array($pid, $ww, true),
                    'gender'                => $gender_map[$pid] ?? null,
                ];
            }
            foreach ($forced as $pid) {
                if ($pid <= 0) {
                    continue;
                }
                if (!isset($entries[$pid])) {
                    $entries[$pid] = ['pokemon_id' => $pid, 'rarity' => $r, 'is_forced_shiny' => true, 'is_worldwide_override' => false, 'gender' => $gender_map[$pid] ?? null];
                } else {
                    $entries[$pid]['is_forced_shiny'] = true;
                }
            }
            foreach ($ww as $pid) {
                if ($pid <= 0) {
                    continue;
                }
                if (!isset($entries[$pid])) {
                    $entries[$pid] = ['pokemon_id' => $pid, 'rarity' => $r, 'is_forced_shiny' => false, 'is_worldwide_override' => true, 'gender' => $gender_map[$pid] ?? null];
                } else {
                    $entries[$pid]['is_worldwide_override'] = true;
                }
            }
        }

        if (!empty($entries)) {
            $by_type[] = [
                'egg_type_id' => $et_id,
                'pokemon'     => array_values($entries),
            ];
        }
    }

    if (function_exists('pokehub_content_save_eggs')) {
        pokehub_content_save_eggs('post', $post_id, $by_type);
    }
}
add_action('save_post', 'pokehub_save_eggs_metabox');

/**
 * Sauvé -> structure pour l’UI : blocks[].rows[] = { rarity, pokemon[], forced_shiny[], worldwide[] }
 */
function pokehub_eggs_saved_to_blocks($saved) {
    $blocks = [];
    if (!is_array($saved)) {
        return $blocks;
    }
    foreach ($saved as $group) {
        $et_id = isset($group['egg_type_id']) ? (int) $group['egg_type_id'] : 0;
        if ($et_id <= 0) {
            continue;
        }
        $list = isset($group['pokemon']) && is_array($group['pokemon']) ? $group['pokemon'] : [];
        $by_rarity = [];
        foreach ($list as $p) {
            $pid = isset($p['pokemon_id']) ? (int) $p['pokemon_id'] : 0;
            if ($pid <= 0) {
                continue;
            }
            $r = isset($p['rarity']) ? max(1, min(5, (int) $p['rarity'])) : 1;
            if (!isset($by_rarity[$r])) {
                $by_rarity[$r] = ['pokemon' => [], 'forced_shiny' => [], 'worldwide' => [], 'pokemon_genders' => []];
            }
            $by_rarity[$r]['pokemon'][] = $pid;
            if (isset($p['gender']) && in_array($p['gender'], ['male', 'female'], true)) {
                $by_rarity[$r]['pokemon_genders'][(string) $pid] = (string) $p['gender'];
            }
            if (!empty($p['is_forced_shiny'])) {
                $by_rarity[$r]['forced_shiny'][] = $pid;
            }
            if (!empty($p['is_worldwide_override'])) {
                $by_rarity[$r]['worldwide'][] = $pid;
            }
        }
        $rows = [];
        foreach (range(1, 5) as $r) {
            if (!empty($by_rarity[$r]['pokemon'])) {
                $rows[] = [
                    'rarity'      => $r,
                    'pokemon'     => array_unique($by_rarity[$r]['pokemon']),
                    'forced_shiny' => array_unique($by_rarity[$r]['forced_shiny']),
                    'worldwide'   => array_unique($by_rarity[$r]['worldwide']),
                    'pokemon_genders' => $by_rarity[$r]['pokemon_genders'],
                ];
            }
        }
        if (empty($rows)) {
            $rows = [['rarity' => 1, 'pokemon' => [], 'forced_shiny' => [], 'worldwide' => [], 'pokemon_genders' => []]];
        }
        $blocks[] = [
            'egg_type_id' => $et_id,
            'rows'        => $rows,
        ];
    }
    if (empty($blocks)) {
        $blocks = [['egg_type_id' => 0, 'rows' => [['rarity' => 1, 'pokemon' => [], 'forced_shiny' => [], 'worldwide' => [], 'pokemon_genders' => []]]]];
    }
    return $blocks;
}

/**
 * Une ligne dans un bloc : rareté + Pokémon + forced shiny + worldwide
 */
function pokehub_render_eggs_row($block_index, $row_index, $row, $egg_types, $pokemon_list) {
    $rarity = isset($row['rarity']) ? max(1, min(5, (int) $row['rarity'])) : 1;
    $pokemon = isset($row['pokemon']) && is_array($row['pokemon']) ? $row['pokemon'] : [];
    $forced_shiny = isset($row['forced_shiny']) && is_array($row['forced_shiny']) ? $row['forced_shiny'] : [];
    $worldwide = isset($row['worldwide']) && is_array($row['worldwide']) ? $row['worldwide'] : [];
    $pokemon_genders = isset($row['pokemon_genders']) && is_array($row['pokemon_genders']) ? $row['pokemon_genders'] : [];
    $bi = is_numeric($block_index) ? (int) $block_index : $block_index;
    $ri = is_numeric($row_index) ? (int) $row_index : $row_index;
    $prefix = 'pokehub_eggs[' . $bi . '][rows][' . $ri . ']';
    ?>
    <div class="pokehub-eggs-row-item" data-eggs-row-index="<?php echo esc_attr($ri); ?>">
        <div class="pokehub-eggs-row-fields">
            <label class="pokehub-eggs-row-rarity">
                <span class="pokehub-eggs-row-label"><?php esc_html_e('Rarity', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[rarity]" class="pokehub-eggs-rarity-select">
                    <?php for ($r = 1; $r <= 5; $r++) : ?>
                        <option value="<?php echo $r; ?>" <?php selected($rarity, $r); ?>><?php echo $r === 1 ? esc_html__('Common (1 egg)', 'poke-hub') : ($r === 5 ? esc_html__('Very rare (5 eggs)', 'poke-hub') : sprintf(esc_html__('Rarity %d eggs', 'poke-hub'), $r)); ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label class="pokehub-eggs-row-pokemon pokehub-gender-field-group">
                <span class="pokehub-eggs-row-label"><?php esc_html_e('Pokémon', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[pokemon][]" class="pokehub-select-pokemon pokehub-eggs-pokemon-select pokehub-gender-driven-select" multiple style="width:100%; min-width:200px;" data-placeholder="<?php esc_attr_e('Select Pokémon', 'poke-hub'); ?>" data-gender-name-template="<?php echo esc_attr($prefix); ?>[pokemon_genders][__POKEMON_ID__]" data-gender-scope="available" data-existing-genders="<?php echo esc_attr(wp_json_encode($pokemon_genders)); ?>">
                    <?php foreach ($pokemon_list as $p) : ?>
                        <option value="<?php echo (int) $p['id']; ?>" <?php selected(in_array((int) $p['id'], $pokemon, true)); ?>><?php echo esc_html($p['text']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="pokehub-pokemon-gender-options" style="display:none;margin-top:8px;"></div>
            </label>
            <label class="pokehub-eggs-row-forced">
                <span class="pokehub-eggs-row-label"><?php esc_html_e('Forced shiny', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[forced_shiny][]" class="pokehub-select-pokemon pokehub-eggs-pokemon-select" multiple style="width:100%; min-width:180px;" data-placeholder="<?php esc_attr_e('Select', 'poke-hub'); ?>">
                    <?php foreach ($pokemon_list as $p) : ?>
                        <option value="<?php echo (int) $p['id']; ?>" <?php selected(in_array((int) $p['id'], $forced_shiny, true)); ?>><?php echo esc_html($p['text']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="pokehub-eggs-row-ww">
                <span class="pokehub-eggs-row-label"><?php esc_html_e('Worldwide', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[worldwide][]" class="pokehub-select-pokemon pokehub-eggs-pokemon-select" multiple style="width:100%; min-width:180px;" data-placeholder="<?php esc_attr_e('Select', 'poke-hub'); ?>">
                    <?php foreach ($pokemon_list as $p) : ?>
                        <option value="<?php echo (int) $p['id']; ?>" <?php selected(in_array((int) $p['id'], $worldwide, true)); ?>><?php echo esc_html($p['text']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <button type="button" class="button-link pokehub-eggs-remove-row" style="color:#a00;"><?php esc_html_e('Remove row', 'poke-hub'); ?></button>
    </div>
    <?php
}

/**
 * Un bloc = un type d’œuf + N lignes (chaque ligne = rareté + Pokémon + forced + worldwide)
 */
function pokehub_render_eggs_block_item($block_index, $block, $egg_types, $pokemon_list) {
    $et_id = isset($block['egg_type_id']) ? (int) $block['egg_type_id'] : 0;
    $rows = isset($block['rows']) && is_array($block['rows']) ? $block['rows'] : [['rarity' => 1, 'pokemon' => [], 'forced_shiny' => [], 'worldwide' => [], 'pokemon_genders' => []]];
    $idx = is_numeric($block_index) ? (int) $block_index : $block_index;
    $prefix = 'pokehub_eggs[' . $idx . ']';
    ?>
    <div class="pokehub-eggs-block-item" data-eggs-block-index="<?php echo esc_attr($idx); ?>">
        <div class="pokehub-eggs-block-header">
            <label><strong><?php esc_html_e('Egg type', 'poke-hub'); ?>:</strong>
                <select name="<?php echo esc_attr($prefix); ?>[egg_type_id]" class="pokehub-eggs-egg-type-select">
                    <option value="0">—</option>
                    <?php foreach ($egg_types as $et) : ?>
                        <option value="<?php echo (int) $et->id; ?>" <?php selected($et_id, (int) $et->id); ?>><?php echo esc_html($et->name_fr ?: $et->name_en); ?> (<?php echo (int) $et->hatch_distance_km; ?> km)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="button" class="button-link pokehub-eggs-remove-block" style="color:#a00; margin-left:10px;"><?php esc_html_e('Remove block', 'poke-hub'); ?></button>
        </div>
        <div class="pokehub-eggs-block-rows" data-block-index="<?php echo esc_attr($idx); ?>">
            <?php foreach ($rows as $ri => $row) : ?>
                <?php pokehub_render_eggs_row($idx, $ri, $row, $egg_types, $pokemon_list); ?>
            <?php endforeach; ?>
        </div>
        <p style="margin:8px 0 0 0;">
            <button type="button" class="button button-small pokehub-eggs-add-row"><?php esc_html_e('Add row', 'poke-hub'); ?></button>
        </p>
    </div>
    <?php
}

/**
 * Template d’une ligne (pour JS add row)
 */
function pokehub_eggs_row_template($block_index_placeholder, $row_index_placeholder, $egg_types, $pokemon_list) {
    $row = ['rarity' => 1, 'pokemon' => [], 'forced_shiny' => [], 'worldwide' => [], 'pokemon_genders' => []];
    $prefix = 'pokehub_eggs[' . $block_index_placeholder . '][rows][' . $row_index_placeholder . ']';
    $rarity = 1;
    $pokemon = $forced_shiny = $worldwide = [];
    ?>
    <div class="pokehub-eggs-row-item" data-eggs-row-index="<?php echo esc_attr($row_index_placeholder); ?>">
        <div class="pokehub-eggs-row-fields">
            <label class="pokehub-eggs-row-rarity">
                <span class="pokehub-eggs-row-label"><?php esc_html_e('Rarity', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[rarity]" class="pokehub-eggs-rarity-select">
                    <?php for ($r = 1; $r <= 5; $r++) : ?>
                        <option value="<?php echo $r; ?>" <?php selected($rarity, $r); ?>><?php echo $r === 1 ? esc_html__('Common (1 egg)', 'poke-hub') : ($r === 5 ? esc_html__('Very rare (5 eggs)', 'poke-hub') : sprintf(esc_html__('Rarity %d eggs', 'poke-hub'), $r)); ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label class="pokehub-eggs-row-pokemon pokehub-gender-field-group">
                <span class="pokehub-eggs-row-label"><?php esc_html_e('Pokémon', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[pokemon][]" class="pokehub-select-pokemon pokehub-eggs-pokemon-select pokehub-gender-driven-select" multiple style="width:100%; min-width:200px;" data-placeholder="<?php esc_attr_e('Select Pokémon', 'poke-hub'); ?>" data-gender-name-template="<?php echo esc_attr($prefix); ?>[pokemon_genders][__POKEMON_ID__]" data-gender-scope="available">
                    <?php foreach ($pokemon_list as $p) : ?>
                        <option value="<?php echo (int) $p['id']; ?>"><?php echo esc_html($p['text']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="pokehub-pokemon-gender-options" style="display:none;margin-top:8px;"></div>
            </label>
            <label class="pokehub-eggs-row-forced">
                <span class="pokehub-eggs-row-label"><?php esc_html_e('Forced shiny', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[forced_shiny][]" class="pokehub-select-pokemon pokehub-eggs-pokemon-select" multiple style="width:100%; min-width:180px;" data-placeholder="<?php esc_attr_e('Select', 'poke-hub'); ?>">
                    <?php foreach ($pokemon_list as $p) : ?>
                        <option value="<?php echo (int) $p['id']; ?>"><?php echo esc_html($p['text']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="pokehub-eggs-row-ww">
                <span class="pokehub-eggs-row-label"><?php esc_html_e('Worldwide', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[worldwide][]" class="pokehub-select-pokemon pokehub-eggs-pokemon-select" multiple style="width:100%; min-width:180px;" data-placeholder="<?php esc_attr_e('Select', 'poke-hub'); ?>">
                    <?php foreach ($pokemon_list as $p) : ?>
                        <option value="<?php echo (int) $p['id']; ?>"><?php echo esc_html($p['text']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <button type="button" class="button-link pokehub-eggs-remove-row" style="color:#a00;"><?php esc_html_e('Remove row', 'poke-hub'); ?></button>
    </div>
    <?php
}

function pokehub_render_eggs_metabox($post) {
    wp_nonce_field('pokehub_save_eggs', 'pokehub_eggs_nonce');

    $saved = function_exists('pokehub_content_get_eggs') ? pokehub_content_get_eggs('post', (int) $post->ID) : [];
    $blocks = pokehub_eggs_saved_to_blocks($saved);

    $egg_types  = function_exists('pokehub_get_egg_types') ? pokehub_get_egg_types() : [];
    $pokemon_list = function_exists('pokehub_get_pokemon_for_select') ? pokehub_get_pokemon_for_select() : [];
    ?>
    <div class="pokehub-eggs-metabox">
        <p class="description"><?php esc_html_e('Add a block per egg type. In each block, one row = one rarity level: choose rarity, select Pokémon, and optionally forced shiny and worldwide in the same row. Use "Add row" to add more rarity lines.', 'poke-hub'); ?></p>
        <div id="pokehub-eggs-blocks-list">
            <?php foreach ($blocks as $i => $block) : ?>
                <?php pokehub_render_eggs_block_item($i, $block, $egg_types, $pokemon_list); ?>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary" id="pokehub-eggs-add-block"><?php esc_html_e('Add egg type', 'poke-hub'); ?></button>
        </p>
    </div>
    <script type="text/template" id="pokehub-eggs-block-template">
        <?php
        $empty_block = ['egg_type_id' => 0, 'rows' => [['rarity' => 1, 'pokemon' => [], 'forced_shiny' => [], 'worldwide' => [], 'pokemon_genders' => []]]];
        pokehub_render_eggs_block_item('{{BLOCK_INDEX}}', $empty_block, $egg_types, $pokemon_list);
        ?>
    </script>
    <script type="text/template" id="pokehub-eggs-row-template">
        <?php pokehub_eggs_row_template('{{BLOCK_INDEX}}', '{{ROW_INDEX}}', $egg_types, $pokemon_list); ?>
    </script>
    <script>
    jQuery(document).ready(function($) {
        var eggsBlockIndex = <?php echo count($blocks); ?>;
        function initEggsSelect2() {
            if (window.pokehubInitQuestPokemonSelect2) {
                window.pokehubInitQuestPokemonSelect2(document);
            }
            if (window.pokehubInitPokemonGenderSelectors) {
                window.pokehubInitPokemonGenderSelectors(document);
            }
        }
        initEggsSelect2();

        $('#pokehub-eggs-add-block').on('click', function() {
            var template = $('#pokehub-eggs-block-template').html();
            template = template.replace(/\{\{BLOCK_INDEX\}\}/g, eggsBlockIndex);
            $('#pokehub-eggs-blocks-list').append(template);
            eggsBlockIndex++;
            setTimeout(initEggsSelect2, 100);
        });

        $(document).on('click', '.pokehub-eggs-remove-block', function() {
            if (confirm('<?php echo esc_js(__('Remove this egg type block?', 'poke-hub')); ?>')) {
                $(this).closest('.pokehub-eggs-block-item').remove();
            }
        });

        $(document).on('click', '.pokehub-eggs-add-row', function() {
            var $block = $(this).closest('.pokehub-eggs-block-item');
            var blockIndex = $block.data('eggs-block-index');
            var $rows = $block.find('.pokehub-eggs-block-rows .pokehub-eggs-row-item');
            var rowIndex = $rows.length;
            var template = $('#pokehub-eggs-row-template').html();
            template = template.replace(/\{\{BLOCK_INDEX\}\}/g, blockIndex).replace(/\{\{ROW_INDEX\}\}/g, rowIndex);
            $block.find('.pokehub-eggs-block-rows').append(template);
            setTimeout(initEggsSelect2, 100);
        });

        $(document).on('click', '.pokehub-eggs-remove-row', function() {
            $(this).closest('.pokehub-eggs-row-item').remove();
        });
    });
    </script>
    <?php
}
