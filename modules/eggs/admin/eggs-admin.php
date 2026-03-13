<?php
// modules/eggs/admin/eggs-admin.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enregistrement du sous-menu Eggs
 */
function poke_hub_admin_menu_eggs_register() {
    $active_modules = get_option('poke_hub_active_modules', []);
    if (!is_array($active_modules) || !in_array('eggs', $active_modules, true)) {
        return;
    }

    add_submenu_page(
        'poke-hub',
        __('Eggs', 'poke-hub'),
        __('Eggs', 'poke-hub'),
        'manage_options',
        'poke-hub-eggs',
        'poke_hub_eggs_admin_ui'
    );
}
add_action('admin_menu', 'poke_hub_admin_menu_eggs_register', 15);

/**
 * Traitement formulaire pool (add / update / delete)
 */
function poke_hub_eggs_handle_form() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    if (empty($_POST['poke_hub_eggs_action'])) {
        return;
    }

    $action = sanitize_key($_POST['poke_hub_eggs_action']);
    if (!in_array($action, ['add_pool', 'update_pool'], true)) {
        return;
    }

    check_admin_referer('poke_hub_eggs_edit_pool');

    if (!function_exists('pokehub_get_table')) {
        return;
    }

    global $wpdb;
    $pools_table   = pokehub_get_table('content_eggs');
    $pokemon_table = pokehub_get_table('content_egg_pokemon');

    $redirect_base = add_query_arg('page', 'poke-hub-eggs', admin_url('admin.php'));

    $name        = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $period_type = isset($_POST['period_type']) ? sanitize_key($_POST['period_type']) : 'month';
    if (!in_array($period_type, ['month', 'season'], true)) {
        $period_type = 'month';
    }
    $period_value = isset($_POST['period_value']) ? sanitize_text_field(wp_unslash($_POST['period_value'])) : '';
    $start_date   = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
    $end_date     = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

    $start_ts = $start_date ? strtotime($start_date . ' 00:00:00') : 0;
    $end_ts   = $end_date ? strtotime($end_date . ' 23:59:59') : 0;

    if ($name === '') {
        wp_safe_redirect(add_query_arg('ph_msg', 'missing_name', $redirect_base));
        exit;
    }

    $pool_data = [
        'source_type' => 'global_pool',
        'source_id'   => 0,
        'name'       => $name . ($period_value !== '' ? ' (' . $period_type . ' ' . $period_value . ')' : ''),
        'start_ts'   => $start_ts,
        'end_ts'     => $end_ts,
    ];
    $format = ['%s', '%d', '%s', '%d', '%d'];

    if ($action === 'add_pool') {
        $wpdb->insert($pools_table, $pool_data, $format);
        $pool_id = (int) $wpdb->insert_id;
        if ($pool_id > 0) {
            poke_hub_eggs_save_pool_pokemon($pool_id, $pokemon_table);
            wp_safe_redirect(add_query_arg(['ph_msg' => 'saved', 'edit' => $pool_id], $redirect_base));
        } else {
            wp_safe_redirect(add_query_arg('ph_msg', 'error', $redirect_base));
        }
        exit;
    }

    $pool_id = isset($_POST['pool_id']) ? (int) $_POST['pool_id'] : 0;
    if ($pool_id <= 0) {
        wp_safe_redirect(add_query_arg('ph_msg', 'invalid_id', $redirect_base));
        exit;
    }

    $wpdb->update($pools_table, $pool_data, ['id' => $pool_id], $format, ['%d']);
    poke_hub_eggs_save_pool_pokemon($pool_id, $pokemon_table);

    wp_safe_redirect(add_query_arg(['ph_msg' => 'updated', 'edit' => $pool_id], $redirect_base));
    exit;
}
add_action('admin_init', 'poke_hub_eggs_handle_form');

/**
 * Sauvegarde des blocs œufs du pool (content_egg_pokemon).
 */
function poke_hub_eggs_save_pool_pokemon($pool_id, $pokemon_table) {
    global $wpdb;

    $wpdb->delete($pokemon_table, ['content_egg_id' => $pool_id], ['%d']);

    $blocks = isset($_POST['pokehub_egg_blocks']) && is_array($_POST['pokehub_egg_blocks']) ? $_POST['pokehub_egg_blocks'] : [];
    $sort_order = 0;

    foreach ($blocks as $block) {
        $et_id = isset($block['egg_type_id']) ? (int) $block['egg_type_id'] : 0;
        if ($et_id <= 0) {
            continue;
        }
        $rows = isset($block['rows']) && is_array($block['rows']) ? $block['rows'] : [];
        $entries = [];

        foreach ($rows as $row) {
            $r = isset($row['rarity']) ? max(1, min(5, (int) $row['rarity'])) : 1;
            $pids = isset($row['pokemon']) && is_array($row['pokemon']) ? array_map('intval', array_filter($row['pokemon'])) : [];
            $forced = isset($row['forced_shiny']) && is_array($row['forced_shiny']) ? array_map('intval', array_filter($row['forced_shiny'])) : [];
            $ww = isset($row['worldwide']) && is_array($row['worldwide']) ? array_map('intval', array_filter($row['worldwide'])) : [];

            foreach ($pids as $pid) {
                if ($pid <= 0) {
                    continue;
                }
                $entries[$pid] = [
                    'pokemon_id' => $pid,
                    'rarity'     => $r,
                    'is_forced_shiny' => in_array($pid, $forced, true),
                    'is_worldwide'    => in_array($pid, $ww, true),
                ];
            }
            foreach ($forced as $pid) {
                if ($pid <= 0) {
                    continue;
                }
                if (!isset($entries[$pid])) {
                    $entries[$pid] = ['pokemon_id' => $pid, 'rarity' => $r, 'is_forced_shiny' => true, 'is_worldwide' => false];
                } else {
                    $entries[$pid]['is_forced_shiny'] = true;
                }
            }
            foreach ($ww as $pid) {
                if ($pid <= 0) {
                    continue;
                }
                if (!isset($entries[$pid])) {
                    $entries[$pid] = ['pokemon_id' => $pid, 'rarity' => $r, 'is_forced_shiny' => false, 'is_worldwide' => true];
                } else {
                    $entries[$pid]['is_worldwide'] = true;
                }
            }
        }

        foreach ($entries as $e) {
            $wpdb->insert($pokemon_table, [
                'content_egg_id'          => $pool_id,
                'egg_type_id'             => $et_id,
                'pokemon_id'              => $e['pokemon_id'],
                'rarity'                  => $e['rarity'],
                'is_worldwide_override'   => !empty($e['is_worldwide']) ? 1 : 0,
                'is_forced_shiny'         => !empty($e['is_forced_shiny']) ? 1 : 0,
                'sort_order'              => $sort_order++,
            ], ['%d', '%d', '%d', '%d', '%d', '%d', '%d']);
        }
    }
}

/**
 * Enqueue Select2 sur la page d’édition de pool
 */
function poke_hub_eggs_admin_assets($hook) {
    if ($hook !== 'poke-hub_page_poke-hub-eggs') {
        return;
    }
    if (empty($_GET['edit']) && (empty($_GET['action']) || $_GET['action'] !== 'add')) {
        return;
    }
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
    wp_enqueue_script('pokehub-admin-select2', POKE_HUB_URL . 'assets/js/pokehub-admin-select2.js', ['jquery', 'select2'], POKE_HUB_VERSION, true);
    $pokemon_list = function_exists('pokehub_get_pokemon_for_select') ? pokehub_get_pokemon_for_select() : [];
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
        'pokemon' => $pokemon_list,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_eggs_admin'),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'rest_pokemon_url' => rest_url('poke-hub/v1/pokemon-for-select'),
    ]);
}
add_action('admin_enqueue_scripts', 'poke_hub_eggs_admin_assets');

/**
 * Convertit les lignes pool (groupées par egg_type_id) en blocs pour l’UI
 */
function poke_hub_eggs_pool_to_blocks($pool_pokemon_by_type) {
    $blocks = [];
    if (!is_array($pool_pokemon_by_type)) {
        return $blocks;
    }
    foreach ($pool_pokemon_by_type as $egg_type_id => $rows) {
        $by_rarity = [];
        foreach ($rows as $row) {
            $pid = (int) $row->pokemon_id;
            if ($pid <= 0) {
                continue;
            }
            $r = isset($row->rarity) ? max(1, min(5, (int) $row->rarity)) : 1;
            if (!isset($by_rarity[$r])) {
                $by_rarity[$r] = ['pokemon' => [], 'forced_shiny' => [], 'worldwide' => []];
            }
            $by_rarity[$r]['pokemon'][] = $pid;
            if (!empty($row->is_forced_shiny)) {
                $by_rarity[$r]['forced_shiny'][] = $pid;
            }
            if (!empty($row->is_worldwide_override)) {
                $by_rarity[$r]['worldwide'][] = $pid;
            }
        }
        $block_rows = [];
        foreach (range(1, 5) as $r) {
            if (!empty($by_rarity[$r]['pokemon'])) {
                $block_rows[] = [
                    'rarity'       => $r,
                    'pokemon'      => array_unique($by_rarity[$r]['pokemon']),
                    'forced_shiny' => array_unique($by_rarity[$r]['forced_shiny']),
                    'worldwide'    => array_unique($by_rarity[$r]['worldwide']),
                ];
            }
        }
        if (empty($block_rows)) {
            $block_rows = [['rarity' => 1, 'pokemon' => [], 'forced_shiny' => [], 'worldwide' => []]];
        }
        $blocks[] = [
            'egg_type_id' => (int) $egg_type_id,
            'rows'        => $block_rows,
        ];
    }
    return $blocks;
}

/**
 * Suppression d'un pool
 */
function poke_hub_eggs_handle_delete() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-eggs' || empty($_GET['action']) || $_GET['action'] !== 'delete' || empty($_GET['id'])) {
        return;
    }

    $id = (int) $_GET['id'];
    if ($id <= 0) {
        return;
    }
    check_admin_referer('poke_hub_delete_egg_pool_' . $id);

    global $wpdb;
    $pools_table   = pokehub_get_table('content_eggs');
    $pokemon_table = pokehub_get_table('content_egg_pokemon');
    if ($pokemon_table) {
        $wpdb->delete($pokemon_table, ['content_egg_id' => $id], ['%d']);
    }
    if ($pools_table) {
        $wpdb->delete($pools_table, ['id' => $id], ['%d']);
    }

    wp_safe_redirect(add_query_arg(['page' => 'poke-hub-eggs', 'ph_msg' => 'deleted'], admin_url('admin.php')));
    exit;
}
add_action('admin_init', 'poke_hub_eggs_handle_delete');

/**
 * Page admin Eggs
 */
function poke_hub_eggs_admin_ui() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'poke-hub'));
    }

    $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
    $action  = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';

    if ($action === 'delete' && !empty($_GET['id'])) {
        // Delete is handled by poke_hub_eggs_handle_delete
        return;
    }

    if ($edit_id > 0 || (isset($_GET['action']) && $_GET['action'] === 'add')) {
        poke_hub_eggs_render_edit_form($edit_id);
        return;
    }

    // Liste des pools
    $pools = poke_hub_is_module_active('eggs') && function_exists('pokehub_get_global_egg_pools')
        ? pokehub_get_global_egg_pools()
        : [];

    $msg = isset($_GET['ph_msg']) ? sanitize_key($_GET['ph_msg']) : '';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Global egg pools', 'poke-hub'); ?></h1>
        <p><?php esc_html_e('Define egg pools by period (month/season) with their Pokémon per egg type. These are long-term pools; events can override content for specific egg types.', 'poke-hub'); ?></p>

        <?php
        if ($msg === 'saved') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Pool saved.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Pool updated.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Pool deleted.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'missing_name') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Name is required.', 'poke-hub') . '</p></div>';
        } elseif ($msg === 'invalid_id' || $msg === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('An error occurred.', 'poke-hub') . '</p></div>';
        }
        ?>

        <p>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'poke-hub-eggs', 'action' => 'add'], admin_url('admin.php'))); ?>" class="button button-primary">
                <?php esc_html_e('Add pool', 'poke-hub'); ?>
            </a>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'poke-hub'); ?></th>
                    <th><?php esc_html_e('Period', 'poke-hub'); ?></th>
                    <th><?php esc_html_e('Start', 'poke-hub'); ?></th>
                    <th><?php esc_html_e('End', 'poke-hub'); ?></th>
                    <th><?php esc_html_e('Actions', 'poke-hub'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pools)) : ?>
                    <tr><td colspan="5"><?php esc_html_e('No pools yet.', 'poke-hub'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($pools as $pool) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($pool->name ?? ''); ?></strong></td>
                            <td>—</td>
                            <td><?php echo !empty($pool->start_ts) ? esc_html(gmdate('Y-m-d', $pool->start_ts)) : '—'; ?></td>
                            <td><?php echo !empty($pool->end_ts) ? esc_html(gmdate('Y-m-d', $pool->end_ts)) : '—'; ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'poke-hub-eggs', 'edit' => (int) $pool->id], admin_url('admin.php'))); ?>"><?php esc_html_e('Edit', 'poke-hub'); ?></a>
                                |
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page' => 'poke-hub-eggs', 'action' => 'delete', 'id' => (int) $pool->id], admin_url('admin.php')), 'poke_hub_delete_egg_pool_' . (int) $pool->id)); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js(__('Delete this pool?', 'poke-hub')); ?>');"><?php esc_html_e('Delete', 'poke-hub'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Rendu d’un bloc « type d’œuf » dans l’édition de pool (lignes = rareté + Pokémon + forced + worldwide)
 */
function poke_hub_eggs_render_pool_row($block_index, $row_index, $row, $pokemon_list) {
    $rarity = isset($row['rarity']) ? max(1, min(5, (int) $row['rarity'])) : 1;
    $pokemon = isset($row['pokemon']) && is_array($row['pokemon']) ? $row['pokemon'] : [];
    $forced_shiny = isset($row['forced_shiny']) && is_array($row['forced_shiny']) ? $row['forced_shiny'] : [];
    $worldwide = isset($row['worldwide']) && is_array($row['worldwide']) ? $row['worldwide'] : [];
    $bi = is_numeric($block_index) ? (int) $block_index : $block_index;
    $ri = is_numeric($row_index) ? (int) $row_index : $row_index;
    $prefix = 'pokehub_egg_blocks[' . $bi . '][rows][' . $ri . ']';
    ?>
    <div class="pokehub-eggs-pool-row-item" data-pool-row-index="<?php echo esc_attr($ri); ?>">
        <div class="pokehub-eggs-pool-row-fields">
            <label class="pokehub-eggs-pool-row-rarity">
                <span class="pokehub-eggs-pool-row-label"><?php esc_html_e('Rarity', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[rarity]" class="pokehub-eggs-pool-rarity-select">
                    <?php for ($r = 1; $r <= 5; $r++) : ?>
                        <option value="<?php echo $r; ?>" <?php selected($rarity, $r); ?>><?php echo $r === 1 ? esc_html__('Common (1 egg)', 'poke-hub') : ($r === 5 ? esc_html__('Very rare (5 eggs)', 'poke-hub') : sprintf(esc_html__('Rarity %d eggs', 'poke-hub'), $r)); ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label class="pokehub-eggs-pool-row-pokemon">
                <span class="pokehub-eggs-pool-row-label"><?php esc_html_e('Pokémon', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[pokemon][]" class="pokehub-select-pokemon pokehub-eggs-pool-select" multiple style="width:100%; min-width:200px;" data-placeholder="<?php esc_attr_e('Select Pokémon', 'poke-hub'); ?>">
                    <?php foreach ($pokemon_list as $p) : ?>
                        <option value="<?php echo (int) $p['id']; ?>" <?php selected(in_array((int) $p['id'], $pokemon, true)); ?>><?php echo esc_html($p['text']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="pokehub-eggs-pool-row-forced">
                <span class="pokehub-eggs-pool-row-label"><?php esc_html_e('Forced shiny', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[forced_shiny][]" class="pokehub-select-pokemon pokehub-eggs-pool-select" multiple style="width:100%; min-width:180px;" data-placeholder="<?php esc_attr_e('Select', 'poke-hub'); ?>">
                    <?php foreach ($pokemon_list as $p) : ?>
                        <option value="<?php echo (int) $p['id']; ?>" <?php selected(in_array((int) $p['id'], $forced_shiny, true)); ?>><?php echo esc_html($p['text']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="pokehub-eggs-pool-row-ww">
                <span class="pokehub-eggs-pool-row-label"><?php esc_html_e('Worldwide', 'poke-hub'); ?></span>
                <select name="<?php echo esc_attr($prefix); ?>[worldwide][]" class="pokehub-select-pokemon pokehub-eggs-pool-select" multiple style="width:100%; min-width:180px;" data-placeholder="<?php esc_attr_e('Select', 'poke-hub'); ?>">
                    <?php foreach ($pokemon_list as $p) : ?>
                        <option value="<?php echo (int) $p['id']; ?>" <?php selected(in_array((int) $p['id'], $worldwide, true)); ?>><?php echo esc_html($p['text']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <button type="button" class="button-link pokehub-eggs-pool-remove-row" style="color:#a00;"><?php esc_html_e('Remove row', 'poke-hub'); ?></button>
    </div>
    <?php
}

/**
 * Rendu d'un bloc type d'œuf dans l'édition de pool (lignes = rareté + Pokémon + forced + worldwide)
 */
function poke_hub_eggs_render_pool_block_item($index, $block, $egg_types, $pokemon_list) {
    $et_id = isset($block['egg_type_id']) ? (int) $block['egg_type_id'] : 0;
    $rows = isset($block['rows']) && is_array($block['rows']) ? $block['rows'] : [['rarity' => 1, 'pokemon' => [], 'forced_shiny' => [], 'worldwide' => []]];
    $idx = is_numeric($index) ? (int) $index : $index;
    $prefix = 'pokehub_egg_blocks[' . $idx . ']';
    ?>
    <div class="pokehub-eggs-pool-block-item" data-pool-block-index="<?php echo esc_attr($idx); ?>">
        <div class="pokehub-eggs-pool-block-header">
            <label><strong><?php esc_html_e('Egg type', 'poke-hub'); ?>:</strong>
                <select name="<?php echo esc_attr($prefix); ?>[egg_type_id]" class="pokehub-eggs-pool-egg-type-select">
                    <option value="0">—</option>
                    <?php foreach ($egg_types as $et) : ?>
                        <option value="<?php echo (int) $et->id; ?>" <?php selected($et_id, (int) $et->id); ?>><?php echo esc_html($et->name_fr ?: $et->name_en); ?> (<?php echo (int) $et->hatch_distance_km; ?> km)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="button" class="button-link pokehub-eggs-pool-remove-block" style="color:#a00; margin-left:10px;"><?php esc_html_e('Remove', 'poke-hub'); ?></button>
        </div>
        <div class="pokehub-eggs-pool-block-rows" data-block-index="<?php echo esc_attr($idx); ?>">
            <?php foreach ($rows as $ri => $row) : ?>
                <?php poke_hub_eggs_render_pool_row($idx, $ri, $row, $pokemon_list); ?>
            <?php endforeach; ?>
        </div>
        <p style="margin:8px 0 0 0;">
            <button type="button" class="button button-small pokehub-eggs-pool-add-row"><?php esc_html_e('Add row', 'poke-hub'); ?></button>
        </p>
    </div>
    <?php
}

/**
 * Formulaire d’édition / ajout de pool (blocs par type d’œuf, multi-select)
 */
function poke_hub_eggs_render_edit_form($pool_id) {
    global $wpdb;

    $pool = null;
    $pool_pokemon = [];
    $egg_types = function_exists('pokehub_get_egg_types') ? pokehub_get_egg_types() : [];
    $pokemon_list = function_exists('pokehub_get_pokemon_for_select') ? pokehub_get_pokemon_for_select() : [];

    if ($pool_id > 0 && function_exists('pokehub_get_table')) {
        $pools_table = pokehub_get_table('content_eggs');
        $pool = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pools_table} WHERE id = %d", $pool_id));
        if ($pool && function_exists('pokehub_get_global_egg_pool_pokemon')) {
            $pool_pokemon = pokehub_get_global_egg_pool_pokemon($pool_id, null);
        }
    }

    $blocks = poke_hub_eggs_pool_to_blocks($pool_pokemon);
    if (empty($blocks)) {
        $blocks = [['egg_type_id' => 0, 'rows' => [['rarity' => 1, 'pokemon' => [], 'forced_shiny' => [], 'worldwide' => []]]]];
    }

    $name = $pool ? $pool->name : '';
    $period_type = 'month';
    $period_value = '';
    $start_date = $pool && !empty($pool->start_ts) ? gmdate('Y-m-d', $pool->start_ts) : '';
    $end_date = $pool && !empty($pool->end_ts) ? gmdate('Y-m-d', $pool->end_ts) : '';

    $back_url = add_query_arg('page', 'poke-hub-eggs', admin_url('admin.php'));
    ?>
    <div class="wrap">
        <h1>
            <?php echo $pool_id > 0 ? esc_html__('Edit pool', 'poke-hub') : esc_html__('Add pool', 'poke-hub'); ?>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action"><?php esc_html_e('Back to list', 'poke-hub'); ?></a>
        </h1>

        <form method="post">
            <?php wp_nonce_field('poke_hub_eggs_edit_pool'); ?>
            <input type="hidden" name="poke_hub_eggs_action" value="<?php echo $pool_id > 0 ? 'update_pool' : 'add_pool'; ?>" />
            <?php if ($pool_id > 0) : ?>
                <input type="hidden" name="pool_id" value="<?php echo (int) $pool_id; ?>" />
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><label for="name"><?php esc_html_e('Name', 'poke-hub'); ?> *</label></th>
                    <td><input type="text" id="name" name="name" value="<?php echo esc_attr($name); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="period_type"><?php esc_html_e('Period type', 'poke-hub'); ?></label></th>
                    <td>
                        <select id="period_type" name="period_type">
                            <option value="month" <?php selected($period_type, 'month'); ?>><?php esc_html_e('Month', 'poke-hub'); ?></option>
                            <option value="season" <?php selected($period_type, 'season'); ?>><?php esc_html_e('Season', 'poke-hub'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="period_value"><?php esc_html_e('Period value', 'poke-hub'); ?></label></th>
                    <td><input type="text" id="period_value" name="period_value" value="<?php echo esc_attr($period_value); ?>" placeholder="e.g. 2025-02 or winter_2025" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="start_date"><?php esc_html_e('Start date', 'poke-hub'); ?></label></th>
                    <td><input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="end_date"><?php esc_html_e('End date', 'poke-hub'); ?></label></th>
                    <td><input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>" /></td>
                </tr>
            </table>

            <h2><?php esc_html_e('Egg contents (per egg type)', 'poke-hub'); ?></h2>
            <p class="description"><?php esc_html_e('Add one block per egg type. In each block, one row = one rarity level: choose rarity, select Pokémon, and optionally forced shiny and worldwide in the same row. Use "Add row" to add more rarity lines.', 'poke-hub'); ?></p>

            <div id="pokehub-eggs-pool-blocks-list">
                <?php foreach ($blocks as $i => $block) : ?>
                    <?php poke_hub_eggs_render_pool_block_item($i, $block, $egg_types, $pokemon_list); ?>
                <?php endforeach; ?>
            </div>
            <p>
                <button type="button" class="button button-secondary" id="pokehub-eggs-pool-add-block"><?php esc_html_e('Add egg type', 'poke-hub'); ?></button>
            </p>

            <script type="text/template" id="pokehub-eggs-pool-block-template">
                <?php
                $empty_block = ['egg_type_id' => 0, 'rows' => [['rarity' => 1, 'pokemon' => [], 'forced_shiny' => [], 'worldwide' => []]]];
                poke_hub_eggs_render_pool_block_item('{{INDEX}}', $empty_block, $egg_types, $pokemon_list);
                ?>
            </script>
            <script type="text/template" id="pokehub-eggs-pool-row-template">
                <?php poke_hub_eggs_render_pool_row('{{BLOCK_INDEX}}', '{{ROW_INDEX}}', ['rarity' => 1, 'pokemon' => [], 'forced_shiny' => [], 'worldwide' => []], $pokemon_list); ?>
            </script>
            <style>
                .pokehub-eggs-pool-block-item { border: 1px solid #c3c4c7; padding: 12px 16px; margin-bottom: 16px; background: #fff; border-radius: 4px; }
                .pokehub-eggs-pool-block-header { margin-bottom: 10px; }
                .pokehub-eggs-pool-row-item { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; padding: 8px 0; border-bottom: 1px solid #eee; }
                .pokehub-eggs-pool-row-item:last-of-type { border-bottom: none; }
                .pokehub-eggs-pool-row-fields { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; flex: 1; min-width: 0; }
                .pokehub-eggs-pool-row-rarity { min-width: 140px; }
                .pokehub-eggs-pool-row-pokemon { flex: 1; min-width: 200px; }
                .pokehub-eggs-pool-row-forced { min-width: 160px; }
                .pokehub-eggs-pool-row-ww { min-width: 160px; }
                .pokehub-eggs-pool-row-label { display: block; margin-bottom: 4px; font-size: 12px; color: #50575e; }
            </style>
            <script>
            jQuery(document).ready(function($) {
                var poolBlockIndex = <?php echo count($blocks); ?>;
                function initPoolSelect2() {
                    if (window.pokehubInitQuestPokemonSelect2) {
                        window.pokehubInitQuestPokemonSelect2(document);
                    }
                }
                initPoolSelect2();
                $('#pokehub-eggs-pool-add-block').on('click', function() {
                    var template = $('#pokehub-eggs-pool-block-template').html();
                    template = template.replace(/\{\{INDEX\}\}/g, poolBlockIndex);
                    $('#pokehub-eggs-pool-blocks-list').append(template);
                    poolBlockIndex++;
                    setTimeout(initPoolSelect2, 100);
                });
                $(document).on('click', '.pokehub-eggs-pool-remove-block', function() {
                    if (confirm('<?php echo esc_js(__('Remove this egg type block?', 'poke-hub')); ?>')) {
                        $(this).closest('.pokehub-eggs-pool-block-item').remove();
                    }
                });
                $(document).on('click', '.pokehub-eggs-pool-add-row', function() {
                    var $block = $(this).closest('.pokehub-eggs-pool-block-item');
                    var blockIndex = $block.data('pool-block-index');
                    var $rows = $block.find('.pokehub-eggs-pool-block-rows .pokehub-eggs-pool-row-item');
                    var rowIndex = $rows.length;
                    var template = $('#pokehub-eggs-pool-row-template').html();
                    template = template.replace(/\{\{BLOCK_INDEX\}\}/g, blockIndex).replace(/\{\{ROW_INDEX\}\}/g, rowIndex);
                    $block.find('.pokehub-eggs-pool-block-rows').append(template);
                    setTimeout(initPoolSelect2, 100);
                });
                $(document).on('click', '.pokehub-eggs-pool-remove-row', function() {
                    $(this).closest('.pokehub-eggs-pool-row-item').remove();
                });
            });
            </script>

            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e('Save', 'poke-hub'); ?>" />
                <a href="<?php echo esc_url($back_url); ?>" class="button"><?php esc_html_e('Cancel', 'poke-hub'); ?></a>
            </p>
        </form>
    </div>
    <?php
}
