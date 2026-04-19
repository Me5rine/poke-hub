<?php
// modules/blocks/admin/blocks-admin-ajax.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX admin : vérifie si un Pokémon a une dimorphie de genre.
 *
 * Les metabox Blocks (wild / new / habitats / field research) utilisent cette action via
 * `action=pokehub_check_pokemon_gender_dimorphism`. Elle doit donc être enregistrée même si
 * le module `events` est désactivé.
 */
add_action('wp_ajax_pokehub_check_pokemon_gender_dimorphism', function () {
    // Accepter plusieurs nonces possibles (mêmes clés que celles utilisées par les metabox).
    $valid_nonce = false;
    $nonce_actions = [
        'pokehub_special_events_nonce',
        'pokehub_wild_pokemon_ajax',
        'pokehub_new_pokemon_ajax',
        'pokehub_habitats_ajax',
        'pokehub_quests_ajax',
    ];

    foreach ($nonce_actions as $action) {
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], $action)) {
            $valid_nonce = true;
            break;
        }
    }

    if (!$valid_nonce) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    $pokemon_id = isset($_POST['pokemon_id']) ? (int) $_POST['pokemon_id'] : 0;
    if (!$pokemon_id) {
        wp_send_json_error(['message' => 'Invalid pokemon_id']);
    }

    $profile = function_exists('poke_hub_pokemon_get_gender_profile')
        ? poke_hub_pokemon_get_gender_profile($pokemon_id)
        : [
            'has_gender_dimorphism'   => false,
            'gender_ratio'            => ['male' => 0.0, 'female' => 0.0],
            'available_genders'       => [],
            'spawn_available_genders' => [],
            'default_gender'          => null,
        ];

    wp_send_json_success([
        'has_gender_dimorphism'   => !empty($profile['has_gender_dimorphism']),
        'gender_ratio'            => is_array($profile['gender_ratio'] ?? null) ? $profile['gender_ratio'] : ['male' => 0.0, 'female' => 0.0],
        'available_genders'       => is_array($profile['available_genders'] ?? null) ? array_values($profile['available_genders']) : [],
        'spawn_available_genders' => is_array($profile['spawn_available_genders'] ?? null) ? array_values($profile['spawn_available_genders']) : [],
        'default_gender'          => isset($profile['default_gender']) ? $profile['default_gender'] : null,
    ]);
});

/**
 * AJAX admin : recherche de Pass GO (special_events type go-pass) pour la metabox article.
 */
add_action('wp_ajax_pokehub_go_pass_metabox_search', function (): void {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'pokehub_go_pass_metabox_ajax')) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'poke-hub')], 403);
    }
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Forbidden.', 'poke-hub')], 403);
    }

    $path = defined('POKE_HUB_PATH') ? POKE_HUB_PATH . 'modules/events/functions/events-go-pass-helpers.php' : '';
    if ($path === '' || !is_readable($path)) {
        wp_send_json_error(['message' => __('Pass GO helpers are not available.', 'poke-hub')], 500);
    }
    require_once $path;
    if (!function_exists('pokehub_go_pass_event_type_slug')) {
        wp_send_json_error(['message' => __('Pass GO helpers are not available.', 'poke-hub')], 500);
    }

    global $wpdb;
    $table = function_exists('pokehub_get_table') ? pokehub_get_table('special_events') : '';
    if ($table === '' || (function_exists('pokehub_table_exists') && !pokehub_table_exists($table))) {
        wp_send_json_success(['results' => []]);
    }

    $slug = function_exists('pokehub_blocks_go_pass_metabox_type_slug')
        ? pokehub_blocks_go_pass_metabox_type_slug()
        : pokehub_go_pass_event_type_slug();
    $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash((string) $_POST['q'])) : '';
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 120) : substr($q, 0, 120);
    $like  = '%' . $wpdb->esc_like($q) . '%';

    if ($q === '') {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, title_en, title_fr FROM {$table} WHERE event_type = %s ORDER BY start_ts DESC LIMIT 25",
                $slug
            ),
            ARRAY_A
        );
    } else {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, title_en, title_fr FROM {$table} WHERE event_type = %s
                AND (title LIKE %s OR title_en LIKE %s OR title_fr LIKE %s)
                ORDER BY start_ts DESC LIMIT 40",
                $slug,
                $like,
                $like,
                $like
            ),
            ARRAY_A
        );
    }

    if (!is_array($rows)) {
        wp_send_json_success(['results' => []]);
    }

    $results = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $label = function_exists('pokehub_blocks_go_pass_metabox_row_label')
            ? pokehub_blocks_go_pass_metabox_row_label($row)
            : (string) $id;
        $results[] = [
            'id'   => $id,
            'text' => $label,
        ];
    }

    wp_send_json_success(['results' => $results]);
});

/**
 * AJAX admin : crée un Pass GO minimal + liaison go_pass_host_links vers l’article courant.
 */
add_action('wp_ajax_pokehub_go_pass_metabox_create_and_link', function (): void {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'pokehub_go_pass_metabox_ajax')) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'poke-hub')], 403);
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => __('Forbidden.', 'poke-hub')], 403);
    }

    $path = defined('POKE_HUB_PATH') ? POKE_HUB_PATH . 'modules/events/functions/events-go-pass-helpers.php' : '';
    if ($path === '' || !is_readable($path)) {
        wp_send_json_error(['message' => __('Pass GO helpers are not available.', 'poke-hub')], 500);
    }
    require_once $path;
    if (!function_exists('pokehub_go_pass_create_empty_special_event')) {
        wp_send_json_error(['message' => __('Pass GO helpers are not available.', 'poke-hub')], 500);
    }

    $table_ok = function_exists('pokehub_get_table') && function_exists('pokehub_table_exists')
        && pokehub_get_table('go_pass_host_links') !== ''
        && pokehub_table_exists(pokehub_get_table('go_pass_host_links'));
    if (!$table_ok || !function_exists('pokehub_go_pass_host_link_save')) {
        wp_send_json_error(['message' => __('GO Pass link table is not available.', 'poke-hub')], 500);
    }

    $lab_title = isset($_POST['lab_event_title']) ? sanitize_text_field(wp_unslash((string) $_POST['lab_event_title'])) : '';
    $article_title = isset($_POST['article_title']) ? sanitize_text_field(wp_unslash((string) $_POST['article_title'])) : '';

    $events_queries = defined('POKE_HUB_PATH') ? POKE_HUB_PATH . 'modules/events/functions/events-queries.php' : '';
    if (!function_exists('poke_hub_events_get_event_meta_title') && $events_queries !== '' && is_readable($events_queries)) {
        require_once $events_queries;
    }

    $stub_title = function_exists('pokehub_blocks_go_pass_metabox_resolve_stub_title')
        ? pokehub_blocks_go_pass_metabox_resolve_stub_title($post_id, $lab_title, $article_title)
        : wp_strip_all_tags(get_the_title($post_id));

    $mode = isset($_POST['display_mode']) ? sanitize_key((string) wp_unslash($_POST['display_mode'])) : 'summary';
    if (!in_array($mode, ['summary', 'full'], true)) {
        $mode = 'summary';
    }

    $result = pokehub_go_pass_create_empty_special_event($stub_title, $stub_title, $post_id);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }
    $event_id = (int) $result;
    if ($event_id <= 0) {
        wp_send_json_error(['message' => __('Could not create GO Pass.', 'poke-hub')], 500);
    }

    $ptype = get_post_type($post_id);
    if ($ptype === false) {
        $ptype = '';
    }
    pokehub_go_pass_host_link_save('local_post', $post_id, $event_id, $mode, (string) $ptype);

    if (function_exists('poke_hub_purge_module_cache')) {
        poke_hub_purge_module_cache(
            ['poke_hub_events'],
            'poke_hub_events',
            'poke_hub_events_all'
        );
    }

    $row = function_exists('pokehub_blocks_go_pass_metabox_get_go_pass_row')
        ? pokehub_blocks_go_pass_metabox_get_go_pass_row($event_id)
        : null;
    $text = is_array($row) && function_exists('pokehub_blocks_go_pass_metabox_row_label')
        ? pokehub_blocks_go_pass_metabox_row_label($row)
        : ('#' . $event_id);

    wp_send_json_success(
        [
            'id'       => $event_id,
            'text'     => $text,
            'edit_url' => function_exists('pokehub_go_pass_admin_edit_url')
                ? pokehub_go_pass_admin_edit_url($event_id)
                : admin_url('admin.php?page=poke-hub-events&action=edit_go_pass&event_id=' . $event_id),
        ]
    );
});

