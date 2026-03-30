<?php
// modules/quests/admin/quests-admin.php

if (!defined('ABSPATH')) {
    exit;
}

// Éditeur de quêtes partagé (indépendant du module Events)
require_once POKE_HUB_PATH . 'includes/content/content-quests-editor.php';

/**
 * Traitement POST : sauvegarde d’un content_quest (éditeur de lignes).
 */
function poke_hub_quests_handle_save_quest() {
    if (!current_user_can('manage_options') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    $section = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : '';
    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    $id = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
    if ($page !== 'poke-hub-quests' || $section !== 'quests' || $action !== 'edit' || $id <= 0) {
        return;
    }
    if (!isset($_POST['pokehub_event_quests_nonce']) || !wp_verify_nonce($_POST['pokehub_event_quests_nonce'], 'pokehub_save_event_quests')) {
        return;
    }
    $row = function_exists('pokehub_content_get_quests_row_by_id') ? pokehub_content_get_quests_row_by_id($id) : null;
    if (!$row || !function_exists('pokehub_content_save_quests') || !function_exists('pokehub_quests_clean_from_request')) {
        return;
    }
    $raw = isset($_POST['pokehub_quests']) && is_array($_POST['pokehub_quests']) ? $_POST['pokehub_quests'] : [];
    $cleaned = pokehub_quests_clean_from_request($raw);
    pokehub_content_save_quests($row->source_type, (int) $row->source_id, $cleaned);
    wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quests&action=edit&id=' . $id . '&updated=1'));
    exit;
}
add_action('admin_init', 'poke_hub_quests_handle_save_quest', 5);

/**
 * Traitement POST : sauvegarde d’un groupe de quêtes (onglet Groupes).
 */
function poke_hub_quests_handle_save_quest_group() {
    if (!isset($_POST['pokehub_quest_group_action']) || !current_user_can('manage_options') || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'pokehub_save_quest_group')) {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if ($page !== 'poke-hub-quests') {
        return;
    }
    $action = sanitize_key($_POST['pokehub_quest_group_action']);
    $id = isset($_POST['id']) ? max(0, (int) $_POST['id']) : 0;
    $data = [
        'title_en'   => isset($_POST['title_en']) ? sanitize_text_field(wp_unslash($_POST['title_en'])) : '',
        'title_fr'   => isset($_POST['title_fr']) ? sanitize_text_field(wp_unslash($_POST['title_fr'])) : '',
        'color'      => isset($_POST['color']) ? sanitize_hex_color(wp_unslash($_POST['color'])) : null,
        'sort_order' => isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order']) : 0,
    ];
    if (function_exists('pokehub_save_quest_group')) {
        if ($action === 'update' && $id > 0) {
            pokehub_save_quest_group($data, $id);
            wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quest_groups&ph_action=edit&id=' . $id . '&updated=1'));
        } else {
            pokehub_save_quest_group($data, 0);
            wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quest_groups&added=1'));
        }
        exit;
    }
}
add_action('admin_init', 'poke_hub_quests_handle_save_quest_group', 5);

/**
 * Traitement : création d’un content_quest global (source_type=global_pool) puis redirection vers édition.
 */
function poke_hub_quests_handle_add_global() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    $section = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : '';
    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    if ($page !== 'poke-hub-quests' || $section !== 'quests' || $action !== 'add_global') {
        return;
    }
    if (!function_exists('pokehub_content_get_quests_row') || !function_exists('pokehub_content_save_quests')) {
        return;
    }
    $existing = pokehub_content_get_quests_row('global_pool', 0);
    if ($existing) {
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quests&action=edit&id=' . (int) $existing->id));
        exit;
    }
    pokehub_content_save_quests('global_pool', 0, []);
    global $wpdb;
    $tbl = pokehub_get_table('content_quests');
    $row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$tbl} WHERE source_type = 'global_pool' AND source_id = 0 ORDER BY id DESC LIMIT 1"));
    if ($row) {
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quests&action=edit&id=' . (int) $row->id));
    } else {
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quests'));
    }
    exit;
}
add_action('admin_init', 'poke_hub_quests_handle_add_global', 5);

/**
 * Traitement POST : création d’un content_quest (global ou lié à un contenu local/remote).
 */
function poke_hub_quests_handle_add_with_source() {
    if (!current_user_can('manage_options') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    $section = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : '';
    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    if ($page !== 'poke-hub-quests' || $section !== 'quests' || $action !== 'add') {
        return;
    }
    if (!isset($_POST['pokehub_add_quest_set_nonce']) || !wp_verify_nonce($_POST['pokehub_add_quest_set_nonce'], 'pokehub_add_quest_set')) {
        return;
    }
    if (!function_exists('pokehub_content_save_quests') || !function_exists('pokehub_content_get_quests_row')) {
        return;
    }
    $source_type = isset($_POST['source_type']) ? sanitize_key($_POST['source_type']) : 'global_pool';
    $source_id = 0;
    if ($source_type === 'post') {
        $source_id = isset($_POST['source_id']) ? max(0, (int) $_POST['source_id']) : 0;
        if ($source_id <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quests&action=add&error=missing_post'));
            exit;
        }
    }
    $existing = pokehub_content_get_quests_row($source_type, $source_id);
    if ($existing) {
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quests&action=edit&id=' . (int) $existing->id));
        exit;
    }
    pokehub_content_save_quests($source_type, $source_id, []);
    global $wpdb;
    $tbl = pokehub_get_table('content_quests');
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$tbl} WHERE source_type = %s AND source_id = %d ORDER BY id DESC LIMIT 1",
        $source_type,
        $source_id
    ));
    if ($row) {
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quests&action=edit&id=' . (int) $row->id));
    } else {
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quests'));
    }
    exit;
}
add_action('admin_init', 'poke_hub_quests_handle_add_with_source', 5);

/**
 * Enqueue des assets pour l’éditeur de quêtes.
 */
function poke_hub_quests_enqueue_editor_assets($hook) {
    if ($hook !== 'poke-hub_page_poke-hub-quests') {
        return;
    }
    $section = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : '';
    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    if ($section !== 'quests' || $action !== 'edit') {
        return;
    }
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
    wp_enqueue_script('pokehub-admin-select2', POKE_HUB_URL . 'assets/js/pokehub-admin-select2.js', ['jquery', 'select2'], POKE_HUB_VERSION, true);
    $pokemon_list = function_exists('pokehub_get_pokemon_for_select') ? pokehub_get_pokemon_for_select() : [];
    $mega_pokemon_list = function_exists('pokehub_get_mega_pokemon_for_select') ? pokehub_get_mega_pokemon_for_select() : [];
    $base_pokemon_list = function_exists('pokehub_get_base_pokemon_for_select') ? pokehub_get_base_pokemon_for_select() : [];
    $items_list = function_exists('pokehub_get_items_for_select') ? pokehub_get_items_for_select() : [];
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsData', [
        'pokemon' => $pokemon_list,
        'mega_pokemon' => $mega_pokemon_list,
        'base_pokemon' => $base_pokemon_list,
        'items' => $items_list,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_quests_ajax'),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'rest_pokemon_url' => rest_url('poke-hub/v1/pokemon-for-select'),
    ]);
    wp_localize_script('pokehub-admin-select2', 'pokehubQuestsGender', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pokehub_quests_ajax'),
        'saved_genders' => [],
    ]);
}
add_action('admin_enqueue_scripts', 'poke_hub_quests_enqueue_editor_assets');

/**
 * Page admin principale : onglets Quêtes / Groupes de quêtes.
 */
function poke_hub_quests_admin_ui() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $current_tab = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : 'quests';
    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    $edit_id = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;

    $tabs = [
        'quests'       => __('Quests', 'poke-hub'),
        'quest_groups' => __('Quest categories', 'poke-hub'),
    ];
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Quests', 'poke-hub'); ?></h1>
        <hr class="wp-header-end" />
        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                <?php
                $tab_url = add_query_arg(['page' => 'poke-hub-quests', 'ph_section' => $tab_key], admin_url('admin.php'));
                $class = 'nav-tab' . ($current_tab === $tab_key ? ' nav-tab-active' : '');
                ?>
                <a href="<?php echo esc_url($tab_url); ?>" class="<?php echo esc_attr($class); ?>"><?php echo esc_html($tab_label); ?></a>
            <?php endforeach; ?>
        </h2>
        <div class="poke-hub-quests-tab-content">
            <?php if ($current_tab === 'quest_groups') : ?>
                <?php poke_hub_quests_render_quest_groups_tab($edit_id); ?>
            <?php else : ?>
                <?php poke_hub_quests_render_quests_tab($action, $edit_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Libellé lisible pour une source (content_quest).
 */
function poke_hub_quests_source_label($source_type, $source_id) {
    if ($source_type === 'global_pool' && (int) $source_id === 0) {
        return __('Global pool', 'poke-hub');
    }
    if ($source_type === 'post' && (int) $source_id > 0) {
        $post = get_post((int) $source_id);
        if ($post) {
            $type_label = $post->post_type === 'pokehub_event' ? __('Event', 'poke-hub') : __('Post', 'poke-hub');
            return $type_label . ' : ' . esc_html($post->post_title);
        }
    }
    return $source_type . ' #' . (int) $source_id;
}

/**
 * Onglet Quêtes : liste des content_quests, formulaire d’ajout, écran d’édition.
 */
function poke_hub_quests_render_quests_tab($action, $edit_id) {
    if ($action === 'edit' && $edit_id > 0) {
        poke_hub_quests_render_quest_edit($edit_id);
        return;
    }
    if ($action === 'add') {
        poke_hub_quests_render_add_form();
        return;
    }
    $rows = function_exists('pokehub_content_get_all_quests_rows') ? pokehub_content_get_all_quests_rows() : [];
    $add_url = add_query_arg(['page' => 'poke-hub-quests', 'ph_section' => 'quests', 'action' => 'add'], admin_url('admin.php'));
    ?>
    <p class="description"><?php esc_html_e('Quest sets are linked to a source: global pool or local content (post, event). The quests block displays the quests for the current content.', 'poke-hub'); ?></p>
    <?php if (isset($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Quests updated.', 'poke-hub'); ?></p></div>
    <?php endif; ?>
    <p>
        <a href="<?php echo esc_url($add_url); ?>" class="button button-primary"><?php esc_html_e('Add quest set', 'poke-hub'); ?></a>
    </p>
    <?php if (empty($rows)) : ?>
        <p><?php esc_html_e('No quest sets yet.', 'poke-hub'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'poke-hub'); ?></th>
                    <th><?php esc_html_e('Source', 'poke-hub'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo (int) $row->id; ?></td>
                        <td><?php echo wp_kses_post(poke_hub_quests_source_label($row->source_type, $row->source_id)); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=poke-hub-quests&ph_section=quests&action=edit&id=' . (int) $row->id)); ?>"><?php esc_html_e('Edit', 'poke-hub'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif;
}

/**
 * Formulaire d’ajout d’un ensemble de quêtes (source : global ou contenu local).
 */
function poke_hub_quests_render_add_form() {
    $add_url = add_query_arg(['page' => 'poke-hub-quests', 'ph_section' => 'quests', 'action' => 'add'], admin_url('admin.php'));
    $list_url = add_query_arg(['page' => 'poke-hub-quests', 'ph_section' => 'quests'], admin_url('admin.php'));
    $post_types = apply_filters('pokehub_quests_source_post_types', ['post', 'pokehub_event']);
    $posts = get_posts([
        'post_type'   => $post_types,
        'numberposts' => 500,
        'orderby'    => 'date',
        'order'      => 'DESC',
        'post_status'=> 'publish',
    ]);
    ?>
    <p><a href="<?php echo esc_url($list_url); ?>" class="button"><?php esc_html_e('&larr; Back to list', 'poke-hub'); ?></a></p>
    <h2><?php esc_html_e('Add quest set', 'poke-hub'); ?></h2>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'missing_post') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('Please select content.', 'poke-hub'); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url($add_url); ?>" class="pokehub-add-quest-set-form">
        <?php wp_nonce_field('pokehub_add_quest_set', 'pokehub_add_quest_set_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="source_type"><?php esc_html_e('Link to', 'poke-hub'); ?></label></th>
                <td>
                    <select name="source_type" id="source_type">
                        <option value="global_pool"><?php esc_html_e('Global pool (general quests)', 'poke-hub'); ?></option>
                        <option value="post"><?php esc_html_e('Local content (post or event)', 'poke-hub'); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="pokehub-quest-source-post-row" style="display: none;">
                <th scope="row"><label for="source_id"><?php esc_html_e('Content', 'poke-hub'); ?></label></th>
                <td>
                    <select name="source_id" id="source_id">
                        <option value=""><?php esc_html_e('— Select —', 'poke-hub'); ?></option>
                        <?php foreach ($posts as $p) : ?>
                            <?php
                            $type_label = $p->post_type === 'pokehub_event' ? __('Event', 'poke-hub') : __('Post', 'poke-hub');
                            ?>
                            <option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html($type_label . ' : ' . $p->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Quests in this set will display with the quests block on this content (or on remote content if the source is remote).', 'poke-hub'); ?></p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Create quest set', 'poke-hub'); ?></button>
        </p>
    </form>
    <script>
    jQuery(function($) {
        $('#source_type').on('change', function() {
            $('.pokehub-quest-source-post-row').toggle($(this).val() === 'post');
        }).trigger('change');
    });
    </script>
    <?php
}

/**
 * Écran d’édition d’un content_quest (lignes de quêtes).
 */
function poke_hub_quests_render_quest_edit($content_quest_id) {
    $row = function_exists('pokehub_content_get_quests_row_by_id') ? pokehub_content_get_quests_row_by_id($content_quest_id) : null;
    if (!$row) {
        echo '<p>' . esc_html__('Quest set not found.', 'poke-hub') . '</p>';
        return;
    }
    $quests = function_exists('pokehub_content_get_quests_by_content_quest_id') ? pokehub_content_get_quests_by_content_quest_id($content_quest_id) : [];
    $back_url = add_query_arg(['page' => 'poke-hub-quests', 'ph_section' => 'quests'], admin_url('admin.php'));
    $form_url = add_query_arg(['page' => 'poke-hub-quests', 'ph_section' => 'quests', 'action' => 'edit', 'id' => $content_quest_id], admin_url('admin.php'));
    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Quests updated.', 'poke-hub') . '</p></div>';
    }
    echo '<p><a href="' . esc_url($back_url) . '" class="button">' . esc_html__('&larr; Retour à la liste', 'poke-hub') . '</a></p>';
    echo '<h2>' . esc_html__('Edit quest set', 'poke-hub') . ' — ' . wp_kses_post(poke_hub_quests_source_label($row->source_type, $row->source_id)) . '</h2>';
    ?>
    <form method="post" action="<?php echo esc_url($form_url); ?>">
        <?php wp_nonce_field('pokehub_save_event_quests', 'pokehub_event_quests_nonce'); ?>
        <div class="pokehub-quests-metabox">
            <div id="pokehub-quests-list">
                <?php if (!empty($quests)) : ?>
                    <?php foreach ($quests as $index => $quest) : ?>
                        <?php pokehub_render_quest_editor_item($index, $quest, 'event'); ?>
                    <?php endforeach; ?>
                <?php else : ?>
                    <?php pokehub_render_quest_editor_item(0, ['task' => '', 'rewards' => []], 'event'); ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button button-secondary" id="pokehub-add-quest"><?php esc_html_e('Add quest', 'poke-hub'); ?></button>
        </div>
        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Save quests', 'poke-hub'); ?></button>
        </p>
    </form>
    <script type="text/template" id="pokehub-quest-template">
        <?php pokehub_render_quest_editor_item('{{INDEX}}', ['task' => '', 'rewards' => []], 'event'); ?>
    </script>
    <script>
    jQuery(document).ready(function($) {
        var questIndex = <?php echo count($quests); ?>;
        function initSelect2() {
            if (window.pokehubInitQuestPokemonSelect2) window.pokehubInitQuestPokemonSelect2(document);
            if (window.pokehubInitQuestItemSelect2) window.pokehubInitQuestItemSelect2(document);
        }
        initSelect2();
        $('#pokehub-add-quest').on('click', function() {
            var template = $('#pokehub-quest-template').html().replace(/\{\{INDEX\}\}/g, questIndex);
            $('#pokehub-quests-list').append(template);
            questIndex++;
            setTimeout(initSelect2, 100);
        });
        $(document).on('click', '.pokehub-remove-quest', function() {
            if (confirm('<?php echo esc_js(__('Delete this quest?', 'poke-hub')); ?>')) $(this).closest('.pokehub-quest-item-editor').remove();
        });
        $(document).on('click', '.pokehub-remove-reward', function() {
            $(this).closest('.pokehub-quest-reward-editor').remove();
        });
    });
    </script>
    <?php
}

/**
 * Onglet Groupes de quêtes : liste + formulaire add/edit.
 */
function poke_hub_quests_render_quest_groups_tab($edit_id) {
    $action = isset($_GET['ph_action']) ? sanitize_key($_GET['ph_action']) : 'list';
    if ($action === 'delete' && $edit_id > 0 && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'pokehub_delete_quest_group_' . $edit_id)) {
        if (function_exists('pokehub_delete_quest_group')) {
            pokehub_delete_quest_group($edit_id);
        }
        wp_safe_redirect(admin_url('admin.php?page=poke-hub-quests&ph_section=quest_groups&deleted=1'));
        exit;
    }
    $group = null;
    if ($action === 'edit' && $edit_id > 0 && function_exists('pokehub_get_quest_group')) {
        $group = pokehub_get_quest_group($edit_id);
        if (!$group) {
            $edit_id = 0;
            $action = 'list';
        }
    }
    $groups = function_exists('pokehub_get_quest_groups') ? pokehub_get_quest_groups() : [];
    ?>
    <p class="description"><?php esc_html_e('Categories to group quests (e.g. Catches, Throws). Used as section titles with optional color.', 'poke-hub'); ?></p>
    <?php if (isset($_GET['deleted'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Group deleted.', 'poke-hub'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['added'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Group added.', 'poke-hub'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Group updated.', 'poke-hub'); ?></p></div>
    <?php endif; ?>
    <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
        <div style="min-width: 280px;">
            <h2><?php echo $edit_id > 0 ? esc_html__('Edit group', 'poke-hub') : esc_html__('Add group', 'poke-hub'); ?></h2>
            <?php poke_hub_quests_render_quest_group_form($group); ?>
        </div>
        <div style="flex: 1;">
            <h2><?php esc_html_e('Groupes', 'poke-hub'); ?></h2>
            <?php if (empty($groups)) : ?>
                <p><?php esc_html_e('Aucun groupe pour l’instant. Ajoutez-en un ci-dessus.', 'poke-hub'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Title (FR)', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Title (EN)', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Color', 'poke-hub'); ?></th>
                            <th><?php esc_html_e('Order', 'poke-hub'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $g) : ?>
                            <tr>
                                <td><?php echo esc_html($g->title_fr); ?></td>
                                <td><?php echo esc_html($g->title_en); ?></td>
                                <td>
                                    <?php if (!empty($g->color)) : ?>
                                        <span style="display:inline-block;width:20px;height:20px;background:<?php echo esc_attr($g->color); ?>;border:1px solid #ccc;"></span>
                                        <?php echo esc_html($g->color); ?>
                                    <?php else : ?>—<?php endif; ?>
                                </td>
                                <td><?php echo (int) $g->sort_order; ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=poke-hub-quests&ph_section=quest_groups&ph_action=edit&id=' . (int) $g->id)); ?>"><?php esc_html_e('Edit', 'poke-hub'); ?></a>
                                    |
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=poke-hub-quests&ph_section=quest_groups&ph_action=delete&id=' . (int) $g->id), 'pokehub_delete_quest_group_' . (int) $g->id)); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this group?', 'poke-hub')); ?>');"><?php esc_html_e('Delete', 'poke-hub'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Formulaire add/edit groupe de quêtes (même structure que events, URL vers page Quêtes).
 */
function poke_hub_quests_render_quest_group_form($group = null) {
    $is_edit = $group && isset($group->id);
    $title_en = $is_edit ? $group->title_en : '';
    $title_fr = $is_edit ? $group->title_fr : '';
    $color = $is_edit ? $group->color : '';
    $sort_order = $is_edit ? (int) $group->sort_order : 0;
    $action_url = add_query_arg(['page' => 'poke-hub-quests', 'ph_section' => 'quest_groups'], admin_url('admin.php'));
    if ($is_edit) {
        $action_url = add_query_arg(['ph_action' => 'edit', 'id' => (int) $group->id], $action_url);
    }
    ?>
    <form method="post" action="<?php echo esc_url($action_url); ?>">
        <?php wp_nonce_field('pokehub_save_quest_group'); ?>
        <input type="hidden" name="pokehub_quest_group_action" value="<?php echo $is_edit ? 'update' : 'add'; ?>" />
        <?php if ($is_edit) : ?>
            <input type="hidden" name="id" value="<?php echo (int) $group->id; ?>" />
        <?php endif; ?>
        <table class="form-table">
            <tr>
                <th><label for="qg_title_en"><?php esc_html_e('Title (EN)', 'poke-hub'); ?></label></th>
                <td><input type="text" id="qg_title_en" name="title_en" value="<?php echo esc_attr($title_en); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="qg_title_fr"><?php esc_html_e('Title (FR)', 'poke-hub'); ?></label></th>
                <td><input type="text" id="qg_title_fr" name="title_fr" value="<?php echo esc_attr($title_fr); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="qg_color"><?php esc_html_e('Color', 'poke-hub'); ?></label></th>
                <td>
                    <input type="text" id="qg_color" name="color" value="<?php echo esc_attr($color); ?>" class="small-text" placeholder="#hex" />
                    <p class="description"><?php esc_html_e('Optional. Hex color for the section header (e.g. #3498db).', 'poke-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="qg_sort_order"><?php esc_html_e('Order', 'poke-hub'); ?></label></th>
                <td><input type="number" id="qg_sort_order" name="sort_order" value="<?php echo esc_attr($sort_order); ?>" min="0" /></td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__('Update', 'poke-hub') : esc_html__('Add group', 'poke-hub'); ?></button>
        </p>
    </form>
    <?php
}
