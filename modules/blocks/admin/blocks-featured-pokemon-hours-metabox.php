<?php
// Metabox "Jour -> Pokémon(s) -> Heures"

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pokehub_add_day_pokemon_hours_metabox')) {
    function pokehub_add_day_pokemon_hours_metabox() {
        $screens = apply_filters('pokehub_day_pokemon_hours_post_types', [
            'post',
            'pokehub_event',
        ]);

        foreach ($screens as $screen) {
            add_meta_box(
                'pokehub_day_pokemon_hours',
                __('Day Pokémon Hours', 'poke-hub'),
                'pokehub_render_day_pokemon_hours_metabox',
                $screen,
                'normal',
                'default'
            );
        }
    }
}
add_action('add_meta_boxes', 'pokehub_add_day_pokemon_hours_metabox');

if (!function_exists('pokehub_day_pokemon_hours_metabox_assets')) {
    function pokehub_day_pokemon_hours_metabox_assets($hook) {
        global $post_type;
        $allowed = apply_filters('pokehub_day_pokemon_hours_post_types', ['post', 'pokehub_event']);
        if (!in_array($post_type, $allowed, true)) {
            return;
        }

        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );

        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );

        wp_enqueue_script(
            'pokehub-admin-select2',
            POKE_HUB_URL . 'assets/js/pokehub-admin-select2.js',
            ['jquery', 'select2'],
            POKE_HUB_VERSION,
            true
        );

        $pokemon_list = function_exists('pokehub_get_pokemon_for_select')
            ? pokehub_get_pokemon_for_select()
            : [];

        wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
            'pokemon' => $pokemon_list,
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'rest_pokemon_url' => rest_url('poke-hub/v1/pokemon-for-select'),
        ]);
    }
}
add_action('admin_enqueue_scripts', 'pokehub_day_pokemon_hours_metabox_assets');

if (!function_exists('pokehub_save_day_pokemon_hours')) {
    function pokehub_save_day_pokemon_hours($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Anti-cascade : pendant la génération des posts enfants "featured_hours",
        // wp_insert_post déclenche aussi save_post pour ces enfants.
        // On ignore totalement la sauvegarde du metabox dans ce contexte.
        if (!empty($GLOBALS['pokehub_skip_content_sync'])) {
            return;
        }

        if (empty($_POST['pokehub_day_pokemon_hours_nonce']) || !wp_verify_nonce($_POST['pokehub_day_pokemon_hours_nonce'], 'pokehub_save_day_pokemon_hours')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $raw_sets = isset($_POST['pokehub_day_pokemon_hours']) && is_array($_POST['pokehub_day_pokemon_hours'])
            ? $_POST['pokehub_day_pokemon_hours']
            : [];

        $cleaned_sets = [];
        foreach ($raw_sets as $set) {
            if (!is_array($set)) {
                continue;
            }

            $content_type = isset($set['content_type']) ? sanitize_key((string) $set['content_type']) : 'featured_hours';
            if ($content_type === '') {
                $content_type = 'featured_hours';
            }

            $raid_tier = 1;
            $raid_is_mega = 0;
            $egg_type_id = 0;
            if ($content_type === 'raids') {
                $raid_tier = isset($set['raid_tier']) ? max(1, min(5, (int) $set['raid_tier'])) : 1;
                $raid_is_mega = !empty($set['raid_is_mega']) ? 1 : 0;
            } elseif ($content_type === 'eggs') {
                $egg_type_id = isset($set['egg_type_id']) ? max(0, (int) $set['egg_type_id']) : 0;
            }

            $days_raw = isset($set['days']) && is_array($set['days']) ? $set['days'] : [];
            $days = [];
            foreach ($days_raw as $day) {
                if (!is_array($day)) {
                    continue;
                }

                $date = sanitize_text_field((string) ($day['date'] ?? ''));
                $end_date = sanitize_text_field((string) ($day['end_date'] ?? ''));
                $start_time = sanitize_text_field((string) ($day['start_time'] ?? ''));
                $end_time = sanitize_text_field((string) ($day['end_time'] ?? ''));

                $pokemon_ids = isset($day['pokemon_ids']) ? $day['pokemon_ids'] : [];
                if (!is_array($pokemon_ids)) {
                    $pokemon_ids = is_string($pokemon_ids) ? explode(',', $pokemon_ids) : [];
                }
                $pokemon_ids = array_values(array_map('intval', array_filter((array) $pokemon_ids, function ($id) {
                    return is_numeric($id) && (int) $id > 0;
                })));

                if ($date === '' || empty($pokemon_ids)) {
                    continue;
                }

                $days[] = [
                    'date' => $date,
                    'end_date' => $end_date,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'pokemon_ids' => $pokemon_ids,
                ];
            }

            if (!empty($days) || in_array($content_type, ['raids', 'eggs', 'quests', 'featured_hours'], true)) {
                $cleaned_set = [
                    'content_type' => $content_type,
                    'days' => $days,
                ];

                if ($content_type === 'raids') {
                    $cleaned_set['raid_tier'] = (int) $raid_tier;
                    $cleaned_set['raid_is_mega'] = (int) $raid_is_mega;
                } elseif ($content_type === 'eggs') {
                    $cleaned_set['egg_type_id'] = (int) $egg_type_id;
                }

                $cleaned_sets[] = $cleaned_set;
            }
        }

        $raid_sets = [];
        $egg_sets = [];
        $quest_sets = [];
        $featured_sets = [];
        $other_sets = [];
        foreach ($cleaned_sets as $s) {
            $ct = (string) ($s['content_type'] ?? '');
            if ($ct === 'raids') {
                $raid_sets[] = $s;
            } elseif ($ct === 'eggs') {
                $egg_sets[] = $s;
            } elseif ($ct === 'quests') {
                $quest_sets[] = $s;
            } elseif ($ct === 'featured_hours') {
                $featured_sets[] = $s;
            } else {
                $other_sets[] = $s;
            }
        }

        // Enregistrer raids/œufs/quêtes dans les tables existantes.
        if (function_exists('pokehub_content_save_day_pokemon_hours_raids')) {
            pokehub_content_save_day_pokemon_hours_raids('post', (int) $post_id, $raid_sets);
        }
        if (function_exists('pokehub_content_save_day_pokemon_hours_eggs')) {
            pokehub_content_save_day_pokemon_hours_eggs('post', (int) $post_id, $egg_sets);
        }
        if (function_exists('pokehub_content_save_day_pokemon_hours_quests')) {
            pokehub_content_save_day_pokemon_hours_quests('post', (int) $post_id, $quest_sets);
        }

        // "Heure vedette" : utiliser le système "événement classique" (événements enfants liés)
        if (!empty($featured_sets) && function_exists('pokehub_content_save_day_pokemon_hours_featured_hours_classic_events')) {
            pokehub_content_save_day_pokemon_hours_featured_hours_classic_events('post', (int) $post_id, $featured_sets);
        } elseif (function_exists('pokehub_content_save_day_pokemon_hours') && !empty($featured_sets)) {
            // Fallback : si le système classique n'est pas dispo, sauvegarder quand même.
            pokehub_content_save_day_pokemon_hours('post', (int) $post_id, $featured_sets);
        }

        // Les autres content_types (encens, leurres, heure vedette, etc.) restent dans la table dédiée.
        if (function_exists('pokehub_content_save_day_pokemon_hours')) {
            pokehub_content_save_day_pokemon_hours('post', (int) $post_id, $other_sets);
        }
    }
}
add_action('save_post', 'pokehub_save_day_pokemon_hours');

if (!function_exists('pokehub_render_day_pokemon_hours_metabox')) {
    function pokehub_render_day_pokemon_hours_metabox($post) {
        wp_nonce_field('pokehub_save_day_pokemon_hours', 'pokehub_day_pokemon_hours_nonce');

        $saved_sets = function_exists('pokehub_content_get_day_pokemon_hours_sets')
            ? pokehub_content_get_day_pokemon_hours_sets('post', (int) $post->ID)
            : [];

        if (empty($saved_sets)) {
            $saved_sets = [
                [
                    'content_type' => 'featured_hours',
                    'days' => [],
                ]
            ];
        }

        $pokemon_list = function_exists('pokehub_get_pokemon_for_select')
            ? pokehub_get_pokemon_for_select()
            : [];
        $pokemon_map = [];
        foreach ($pokemon_list as $p) {
            $pokemon_map[(string) ((int) ($p['id'] ?? 0))] = (string) ($p['text'] ?? ('#' . (int) ($p['id'] ?? 0)));
        }

        $egg_types = function_exists('pokehub_get_egg_types') ? pokehub_get_egg_types() : [];
        $egg_type_map = [];
        foreach ($egg_types as $et) {
            $id = (int) ($et->id ?? 0);
            if ($id <= 0) continue;
            $label = !empty($et->name_fr) ? (string) $et->name_fr : (!empty($et->name_en) ? (string) $et->name_en : ('#' . $id));
            $egg_type_map[(string) $id] = $label;
        }
        $default_egg_type_id = !empty($egg_type_map) ? (int) array_key_first($egg_type_map) : 0;

        $types = [
            'raids' => __('Raids', 'poke-hub'),
            'eggs' => __('Eggs', 'poke-hub'),
            'incense' => __('Incense', 'poke-hub'),
            'lures' => __('Lures', 'poke-hub'),
            'featured_hours' => __('Featured Hours', 'poke-hub'),
            'quests' => __('Quests', 'poke-hub'),
        ];
        ?>
        <div class="pokehub-featured-pokemon-hours-metabox">
            <p class="description">
                <?php esc_html_e('Configure day-by-day Pokémon with start/end hours. You can reuse this data for raids/eggs/incense/lures/featured hours/quests blocks.', 'poke-hub'); ?>
            </p>

            <div id="pokehub-day-pokemon-hours-sets">
                <?php foreach ($saved_sets as $set_index => $set) : ?>
                    <?php
                    $content_type = (string) ($set['content_type'] ?? 'featured_hours');
                    $days = isset($set['days']) && is_array($set['days']) ? $set['days'] : [];
                    $raid_tier = (int) ($set['raid_tier'] ?? 1);
                    $raid_is_mega = !empty($set['raid_is_mega']) ? 1 : 0;
                    $egg_type_id = (int) ($set['egg_type_id'] ?? $default_egg_type_id);
                    ?>

                    <div class="pokehub-featured-pokemon-hours-set" data-set-index="<?php echo esc_attr((string) $set_index); ?>">
                        <h4 style="margin-top: 1.25rem;">
                            <?php esc_html_e('Type', 'poke-hub'); ?>:
                            <select
                                name="pokehub_day_pokemon_hours[<?php echo esc_attr((string) $set_index); ?>][content_type]"
                                class="pokehub-day-pokemon-hours-type-select"
                                style="margin-left: 10px;"
                            >
                                <?php foreach ($types as $key => $label) : ?>
                                    <option value="<?php echo esc_attr((string) $key); ?>" <?php selected($content_type, $key, true); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button
                                type="button"
                                class="button-link pokehub-featured-pokemon-hours-remove-set"
                                style="color:#a00; float:right;"
                            >
                                <?php esc_html_e('Remove type', 'poke-hub'); ?>
                            </button>
                        </h4>

                        <div class="pokehub-day-pokemon-hours-conditional pokehub-day-pokemon-hours-conditional--raids" data-ct="raids" style="<?php echo $content_type === 'raids' ? '' : 'display:none;'; ?>">
                            <p style="margin:0.5rem 0 0;">
                                <label>
                                    <?php esc_html_e('Raid tier', 'poke-hub'); ?>:
                                    <select name="pokehub_day_pokemon_hours[<?php echo esc_attr((string) $set_index); ?>][raid_tier]">
                                        <?php for ($t = 1; $t <= 5; $t++) : ?>
                                            <option value="<?php echo esc_attr((string) $t); ?>" <?php selected($raid_tier, $t, true); ?>>
                                                <?php echo esc_html((string) $t); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                            </p>
                            <p style="margin:0.25rem 0 0;">
                                <label>
                                    <input type="checkbox" name="pokehub_day_pokemon_hours[<?php echo esc_attr((string) $set_index); ?>][raid_is_mega]" value="1" <?php checked($raid_is_mega, 1, true); ?> />
                                    <?php esc_html_e('Mega raid', 'poke-hub'); ?>
                                </label>
                            </p>
                        </div>

                        <div class="pokehub-day-pokemon-hours-conditional pokehub-day-pokemon-hours-conditional--eggs" data-ct="eggs" style="<?php echo $content_type === 'eggs' ? '' : 'display:none;'; ?>">
                            <p style="margin:0.5rem 0 0;">
                                <label>
                                    <?php esc_html_e('Egg type', 'poke-hub'); ?>:
                                    <select name="pokehub_day_pokemon_hours[<?php echo esc_attr((string) $set_index); ?>][egg_type_id]">
                                        <?php if (!empty($egg_types)) : ?>
                                            <?php foreach ($egg_types as $et) : ?>
                                                <?php
                                                $et_id = (int) ($et->id ?? 0);
                                                if ($et_id <= 0) continue;
                                                $label = !empty($et->name_fr) ? (string) $et->name_fr : (!empty($et->name_en) ? (string) $et->name_en : ('#' . $et_id));
                                                ?>
                                                <option value="<?php echo esc_attr((string) $et_id); ?>" <?php selected($egg_type_id, $et_id, true); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <option value="0"><?php esc_html_e('No egg types available', 'poke-hub'); ?></option>
                                        <?php endif; ?>
                                    </select>
                                </label>
                            </p>
                        </div>

                        <div class="pokehub-featured-pokemon-hours-days" style="margin-top: 0.5rem;">
                            <?php if (!empty($days)) : ?>
                                <?php foreach ($days as $day_index => $day) : ?>
                                    <?php
                                    $date = (string) ($day['date'] ?? '');
                                    $end_date = (string) ($day['end_date'] ?? '');
                                    $start_time = (string) ($day['start_time'] ?? '');
                                    $end_time = (string) ($day['end_time'] ?? '');
                                    $pokemon_ids = isset($day['pokemon_ids']) && is_array($day['pokemon_ids']) ? $day['pokemon_ids'] : [];
                                    ?>

                                    <div class="pokehub-featured-pokemon-hours-day-editor">
                                        <label>
                                            <?php esc_html_e('Date', 'poke-hub'); ?>:
                                            <input
                                                type="date"
                                                name="pokehub_day_pokemon_hours[<?php echo esc_attr((string) $set_index); ?>][days][<?php echo esc_attr((string) $day_index); ?>][date]"
                                                value="<?php echo esc_attr($date); ?>"
                                            />
                                        </label>

                                        <label style="margin-left: 10px;">
                                            <?php esc_html_e('Start time', 'poke-hub'); ?>:
                                            <input
                                                type="time"
                                                name="pokehub_day_pokemon_hours[<?php echo esc_attr((string) $set_index); ?>][days][<?php echo esc_attr((string) $day_index); ?>][start_time]"
                                                value="<?php echo esc_attr($start_time); ?>"
                                            />
                                        </label>

                                        <label style="margin-left: 10px;">
                                            <?php esc_html_e('End date', 'poke-hub'); ?>:
                                            <input
                                                type="date"
                                                name="pokehub_day_pokemon_hours[<?php echo esc_attr((string) $set_index); ?>][days][<?php echo esc_attr((string) $day_index); ?>][end_date]"
                                                value="<?php echo esc_attr($end_date); ?>"
                                            />
                                        </label>

                                        <label style="margin-left: 10px;">
                                            <?php esc_html_e('End time', 'poke-hub'); ?>:
                                            <input
                                                type="time"
                                                name="pokehub_day_pokemon_hours[<?php echo esc_attr((string) $set_index); ?>][days][<?php echo esc_attr((string) $day_index); ?>][end_time]"
                                                value="<?php echo esc_attr($end_time); ?>"
                                            />
                                        </label>

                                        <div style="margin-top: 10px;">
                                            <label style="display:block;">
                                                <?php esc_html_e('Pokémon(s)', 'poke-hub'); ?>:
                                                <select
                                                    multiple
                                                    class="pokehub-select-pokemon"
                                                    data-placeholder="<?php echo esc_attr(__('Select Pokémon', 'poke-hub')); ?>"
                                                    name="pokehub_day_pokemon_hours[<?php echo esc_attr((string) $set_index); ?>][days][<?php echo esc_attr((string) $day_index); ?>][pokemon_ids][]"
                                                    style="width:100%; min-width:220px;"
                                                >
                                                    <?php foreach ($pokemon_ids as $pid) : ?>
                                                        <?php
                                                        $pid = (int) $pid;
                                                        if ($pid <= 0) continue;
                                                        $opt_label = $pokemon_map[(string) $pid] ?? ('#' . $pid);
                                                        ?>
                                                        <option value="<?php echo esc_attr((string) $pid); ?>" selected>
                                                            <?php echo esc_html($opt_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                        </div>

                                        <button type="button" class="button-link pokehub-featured-pokemon-hours-remove-day" style="color:#a00; margin-left:0;">
                                            <?php esc_html_e('Remove day', 'poke-hub'); ?>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="button button-small pokehub-featured-pokemon-hours-add-day" style="margin-top: 10px;">
                            <?php esc_html_e('Add day', 'poke-hub'); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button button-secondary" id="pokehub-day-pokemon-hours-add-set" style="margin-top: 1rem;">
                <?php esc_html_e('Add type', 'poke-hub'); ?>
            </button>

            <script type="text/template" id="pokehub-day-pokemon-hours-set-template">
                <div class="pokehub-featured-pokemon-hours-set" data-set-index="__SET_INDEX__">
                    <h4 style="margin-top: 1.25rem;">
                        <?php esc_html_e('Type', 'poke-hub'); ?>:
                        <select
                            name="pokehub_day_pokemon_hours[__SET_INDEX__][content_type]"
                            class="pokehub-day-pokemon-hours-type-select"
                            style="margin-left: 10px;"
                        >
                            <?php foreach ($types as $key => $label) : ?>
                                <option value="<?php echo esc_attr((string) $key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button
                            type="button"
                            class="button-link pokehub-featured-pokemon-hours-remove-set"
                            style="color:#a00; float:right;"
                        >
                            <?php esc_html_e('Remove type', 'poke-hub'); ?>
                        </button>
                    </h4>

                    <div class="pokehub-day-pokemon-hours-conditional pokehub-day-pokemon-hours-conditional--raids" data-ct="raids" style="display:none;">
                        <p style="margin:0.5rem 0 0;">
                            <label>
                                <?php esc_html_e('Raid tier', 'poke-hub'); ?>:
                                <select name="pokehub_day_pokemon_hours[__SET_INDEX__][raid_tier]">
                                    <?php for ($t = 1; $t <= 5; $t++) : ?>
                                        <option value="<?php echo esc_attr((string) $t); ?>"><?php echo esc_html((string) $t); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                        </p>
                        <p style="margin:0.25rem 0 0;">
                            <label>
                                <input type="checkbox" name="pokehub_day_pokemon_hours[__SET_INDEX__][raid_is_mega]" value="1" />
                                <?php esc_html_e('Mega raid', 'poke-hub'); ?>
                            </label>
                        </p>
                    </div>

                    <div class="pokehub-day-pokemon-hours-conditional pokehub-day-pokemon-hours-conditional--eggs" data-ct="eggs" style="display:none;">
                        <p style="margin:0.5rem 0 0;">
                            <label>
                                <?php esc_html_e('Egg type', 'poke-hub'); ?>:
                                <select name="pokehub_day_pokemon_hours[__SET_INDEX__][egg_type_id]">
                                    <?php if (!empty($egg_types)) : ?>
                                        <?php foreach ($egg_types as $et) : ?>
                                            <?php
                                            $et_id = (int) ($et->id ?? 0);
                                            if ($et_id <= 0) continue;
                                            $label = !empty($et->name_fr) ? (string) $et->name_fr : (!empty($et->name_en) ? (string) $et->name_en : ('#' . $et_id));
                                            ?>
                                            <option value="<?php echo esc_attr((string) $et_id); ?>">
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <option value="0"><?php esc_html_e('No egg types available', 'poke-hub'); ?></option>
                                    <?php endif; ?>
                                </select>
                            </label>
                        </p>
                    </div>

                    <div class="pokehub-featured-pokemon-hours-days" style="margin-top: 0.5rem;"></div>

                    <button type="button" class="button button-small pokehub-featured-pokemon-hours-add-day" style="margin-top: 10px;">
                        <?php esc_html_e('Add day', 'poke-hub'); ?>
                    </button>
                </div>
            </script>

            <script type="text/template" id="pokehub-day-pokemon-hours-day-template">
                <div class="pokehub-featured-pokemon-hours-day-editor">
                    <label>
                        <?php esc_html_e('Date', 'poke-hub'); ?>:
                        <input type="date" name="pokehub_day_pokemon_hours[__SET_INDEX__][days][__DAY_INDEX__][date]" value="" />
                    </label>

                    <label style="margin-left: 10px;">
                        <?php esc_html_e('Start time', 'poke-hub'); ?>:
                        <input type="time" name="pokehub_day_pokemon_hours[__SET_INDEX__][days][__DAY_INDEX__][start_time]" value="" />
                    </label>

                    <label style="margin-left: 10px;">
                        <?php esc_html_e('End date', 'poke-hub'); ?>:
                        <input type="date" name="pokehub_day_pokemon_hours[__SET_INDEX__][days][__DAY_INDEX__][end_date]" value="" />
                    </label>

                    <label style="margin-left: 10px;">
                        <?php esc_html_e('End time', 'poke-hub'); ?>:
                        <input type="time" name="pokehub_day_pokemon_hours[__SET_INDEX__][days][__DAY_INDEX__][end_time]" value="" />
                    </label>

                    <div style="margin-top: 10px;">
                        <label style="display:block;">
                            <?php esc_html_e('Pokémon(s)', 'poke-hub'); ?>:
                            <select
                                multiple
                                class="pokehub-select-pokemon"
                                data-placeholder="<?php echo esc_attr(__('Select Pokémon', 'poke-hub')); ?>"
                                name="pokehub_day_pokemon_hours[__SET_INDEX__][days][__DAY_INDEX__][pokemon_ids][]"
                                style="width:100%; min-width:220px;"
                            ></select>
                        </label>
                    </div>

                    <button type="button" class="button-link pokehub-featured-pokemon-hours-remove-day" style="color:#a00; margin-left:0;">
                        <?php esc_html_e('Remove day', 'poke-hub'); ?>
                    </button>
                </div>
            </script>

            <script>
                jQuery(document).ready(function($) {
                    var setIndex = <?php echo (int) count($saved_sets); ?>;

                    function updateConditionalFields($set) {
                        var ct = ($set.find('.pokehub-day-pokemon-hours-type-select').val() || '').toString();
                        $set.find('.pokehub-day-pokemon-hours-conditional').hide();
                        $set.find('.pokehub-day-pokemon-hours-conditional[data-ct="' + ct + '"]').show();
                    }

                    function initSelect2($context) {
                        if (window.pokehubInitLargePokemonSelect2) {
                            window.pokehubInitLargePokemonSelect2($context);
                        }
                    }

                    $('#pokehub-day-pokemon-hours-add-set').on('click', function() {
                        var tpl = $('#pokehub-day-pokemon-hours-set-template').html();
                        tpl = tpl.replace(/__SET_INDEX__/g, String(setIndex));
                        $('#pokehub-day-pokemon-hours-sets').append(tpl);
                        updateConditionalFields($('.pokehub-featured-pokemon-hours-set').last());
                        setIndex++;
                    });

                    $(document).on('change', '.pokehub-day-pokemon-hours-type-select', function() {
                        updateConditionalFields($(this).closest('.pokehub-featured-pokemon-hours-set'));
                    });

                    $(document).on('click', '.pokehub-featured-pokemon-hours-remove-day', function() {
                        $(this).closest('.pokehub-featured-pokemon-hours-day-editor').remove();
                    });

                    $(document).on('click', '.pokehub-featured-pokemon-hours-remove-set', function() {
                        if (confirm('<?php echo esc_js(__('Remove this type set?', 'poke-hub')); ?>')) {
                            $(this).closest('.pokehub-featured-pokemon-hours-set').remove();
                        }
                    });

                    $(document).on('click', '.pokehub-featured-pokemon-hours-add-day', function() {
                        var $set = $(this).closest('.pokehub-featured-pokemon-hours-set');
                        var sIndex = $set.data('set-index');

                        var $days = $set.find('.pokehub-featured-pokemon-hours-days');
                        var dIndex = $days.find('.pokehub-featured-pokemon-hours-day-editor').length;

                        var tpl = $('#pokehub-day-pokemon-hours-day-template').html();
                        tpl = tpl
                            .replace(/__SET_INDEX__/g, String(sIndex))
                            .replace(/__DAY_INDEX__/g, String(dIndex));

                        $days.append(tpl);

                        // Le sélecteur dans pokehub-admin-select2 inclut le conteneur .pokehub-featured-pokemon-hours-metabox,
                        // donc on initialise avec le bon ancestor.
                        // On passe `document` pour que pokehubInitLargePokemonSelect2 puisse
                        // retrouver le sélecteur .pokehub-featured-pokemon-hours-metabox via .find().
                        initSelect2(document);
                    });

                    // Init initial pour les selects déjà présents
                    // Idem: passer `document` évite un .find() qui ne "voit" pas l'élément metabox lui-même.
                    initSelect2(document);
                    $('.pokehub-featured-pokemon-hours-set').each(function() {
                        updateConditionalFields($(this));
                    });
                });
            </script>
        </div>
        <?php
    }
}

