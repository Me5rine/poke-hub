<?php
/**
 * REST : liste des Pass GO (special_events) + création d’un brouillon vide.
 * Enregistré par le module Blocs (pas le module Events).
 * Réutilise les helpers Pass GO (fichier dans modules/events) pour la logique métier.
 *
 * @package PokeHub
 */

if (!defined('ABSPATH')) {
    exit;
}

$go_pass_helpers = POKE_HUB_PATH . 'modules/events/functions/events-go-pass-helpers.php';
if (is_readable($go_pass_helpers)) {
    require_once $go_pass_helpers;
}

/**
 * @return bool
 */
function pokehub_go_pass_rest_can_list(): bool {
    return current_user_can('manage_options');
}

/**
 * @return bool
 */
function pokehub_go_pass_rest_can_create(): bool {
    return current_user_can('manage_options');
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function pokehub_go_pass_rest_list_items(WP_REST_Request $request) {
    if (!function_exists('pokehub_go_pass_event_type_slug')) {
        return new WP_Error('missing_helpers', __('Pass GO helpers are not available.', 'poke-hub'), ['status' => 500]);
    }

    global $wpdb;
    $table = pokehub_get_table('special_events');
    if ($table === '' || (function_exists('pokehub_table_exists') && !pokehub_table_exists($table))) {
        return rest_ensure_response([]);
    }

    $slug = pokehub_go_pass_event_type_slug();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, slug, title, title_en, title_fr, start_ts, end_ts FROM {$table} WHERE event_type = %s ORDER BY start_ts DESC LIMIT 200",
            $slug
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return rest_ensure_response([]);
    }

    $out = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $payload = function_exists('pokehub_content_get_go_pass')
            ? pokehub_content_get_go_pass('special_event', $id)
            : null;
        $tier_count = ($payload && !empty($payload['tiers']) && is_array($payload['tiers']))
            ? count($payload['tiers'])
            : 0;

        $label_fr = isset($row['title_fr']) ? (string) $row['title_fr'] : '';
        $label_en = isset($row['title_en']) ? (string) $row['title_en'] : '';
        $label    = $label_fr !== '' ? $label_fr : ($label_en !== '' ? $label_en : (string) ($row['title'] ?? ''));

        $out[] = [
            'id'          => $id,
            'slug'        => (string) ($row['slug'] ?? ''),
            'title_en'    => $label_en,
            'title_fr'    => $label_fr,
            'label'       => $label,
            'start_ts'    => (int) ($row['start_ts'] ?? 0),
            'end_ts'      => (int) ($row['end_ts'] ?? 0),
            'tier_count'  => $tier_count,
            'has_content' => $tier_count > 0,
            'edit_url'    => function_exists('pokehub_go_pass_admin_edit_url')
                ? pokehub_go_pass_admin_edit_url($id)
                : admin_url('admin.php?page=poke-hub-events&action=edit_go_pass&event_id=' . $id),
            'public_url'  => function_exists('poke_hub_special_event_get_url')
                ? poke_hub_special_event_get_url((string) ($row['slug'] ?? ''))
                : '',
        ];
    }

    return rest_ensure_response($out);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function pokehub_go_pass_rest_create_item(WP_REST_Request $request) {
    if (!function_exists('pokehub_go_pass_create_empty_special_event')) {
        return new WP_Error('missing_helpers', __('Pass GO helpers are not available.', 'poke-hub'), ['status' => 500]);
    }

    $title_en = is_string($request->get_param('title_en')) ? sanitize_text_field($request->get_param('title_en')) : '';
    $title_fr = is_string($request->get_param('title_fr')) ? sanitize_text_field($request->get_param('title_fr')) : '';

    $result = pokehub_go_pass_create_empty_special_event($title_en, $title_fr);
    if (is_wp_error($result)) {
        return $result;
    }

    $id = (int) $result;
    if ($id <= 0) {
        return new WP_Error('create_failed', __('Could not create GO Pass.', 'poke-hub'), ['status' => 500]);
    }

    if (function_exists('poke_hub_purge_module_cache')) {
        poke_hub_purge_module_cache(
            ['poke_hub_events'],
            'poke_hub_events',
            'poke_hub_events_all'
        );
    }

    return rest_ensure_response(
        [
            'id'       => $id,
            'edit_url' => function_exists('pokehub_go_pass_admin_edit_url')
                ? pokehub_go_pass_admin_edit_url($id)
                : admin_url('admin.php?page=poke-hub-events&action=edit_go_pass&event_id=' . $id),
        ]
    );
}

add_action(
    'rest_api_init',
    static function (): void {
        register_rest_route(
            'poke-hub/v1',
            '/go-pass-special-events',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'pokehub_go_pass_rest_list_items',
                'permission_callback' => 'pokehub_go_pass_rest_can_list',
            ]
        );
        register_rest_route(
            'poke-hub/v1',
            '/go-pass-special-events/new',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'pokehub_go_pass_rest_create_item',
                'permission_callback' => 'pokehub_go_pass_rest_can_create',
                'args'                => [
                    'title_en' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'title_fr' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );
    }
);
