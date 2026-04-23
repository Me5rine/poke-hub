<?php
// modules/collections/public/collections-rest.php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('poke-hub/v1', '/collections/pool', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'args'                => [
            'category' => ['required' => true, 'type' => 'string'],
            'options'  => ['type' => 'string'], // JSON
        ],
        'callback'            => function (WP_REST_Request $request) {
            $category = sanitize_key($request->get_param('category'));
            $options  = $request->get_param('options');
            $options  = is_string($options) ? json_decode($options, true) : [];
            if (!is_array($options)) {
                $options = [];
            }
            $pool = poke_hub_collections_get_pool($category, $options);

            // Génère l'URL image Pokémon via le helper central,
            // pour que la construction soit identique partout (slug + suffixes).
            $is_shiny_category = in_array(
                $category,
                ['shiny', 'costume_shiny', 'background_shiny', 'background_shiny_special', 'background_shiny_places'],
                true
            );
            if (function_exists('poke_hub_pokemon_get_image_url')) {
                foreach ($pool as &$p) {
                    $pokemon_obj = (object) $p;
                    $p['image_url'] = poke_hub_pokemon_get_image_url($pokemon_obj, [
                        'shiny' => $is_shiny_category,
                    ]);
                }
                unset($p);
            }

            if (in_array($category, ['background', 'background_shiny', 'background_special', 'background_places', 'background_shiny_special', 'background_shiny_places'], true) && function_exists('poke_hub_collections_get_background_image_url_for_pokemon')) {
                $only_shiny_active = in_array($category, ['background_shiny', 'background_shiny_special', 'background_shiny_places'], true);
                foreach ($pool as &$p) {
                    $p['background_image_url'] = poke_hub_collections_get_background_image_url_for_pokemon((int) $p['id'], $only_shiny_active);
                }
                unset($p);
            }
            return new WP_REST_Response($pool, 200);
        },
    ]);

    register_rest_route('poke-hub/v1', '/collections', [
        'methods'             => 'GET',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'callback'            => function (WP_REST_Request $request) {
            $user_id = get_current_user_id();
            $list    = poke_hub_collections_get_by_user($user_id);
            return new WP_REST_Response($list, 200);
        },
    ]);

    register_rest_route('poke-hub/v1', '/collections', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'args'                => [
            'name'      => ['type' => 'string'],
            'category'  => ['type' => 'string', 'default' => 'custom'],
            'options'   => ['type' => 'object'],
            'is_public' => ['type' => 'boolean', 'default' => false],
        ],
        'callback'            => function (WP_REST_Request $request) {
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $data    = [
                    'name'      => $request->get_param('name'),
                    'category'  => $request->get_param('category'),
                    'options'   => $request->get_param('options') ?: [],
                    'is_public' => $request->get_param('is_public'),
                ];
                return new WP_REST_Response(poke_hub_collections_create($user_id, $data), 200);
            }
            $ip   = poke_hub_collections_get_client_ip();
            $data = [
                'name'     => $request->get_param('name'),
                'category' => $request->get_param('category'),
                'options'  => $request->get_param('options') ?: [],
            ];
            $result = poke_hub_collections_create_anonymous($data, $ip);
            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        },
    ]);

    register_rest_route('poke-hub/v1', '/collections/(?P<id>\d+)', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'args'                => ['id' => ['required' => true, 'type' => 'integer']],
        'callback'            => function (WP_REST_Request $request) {
            $id  = (int) $request['id'];
            $col = poke_hub_collections_get_one($id);
            if (!$col) {
                return new WP_REST_Response(['error' => 'Not found'], 404);
            }
            $user_id = get_current_user_id();
            if ((int) $col['user_id'] === 0) {
                $ip = poke_hub_collections_get_client_ip();
                if (empty($col['anonymous_ip']) || $col['anonymous_ip'] !== $ip) {
                    return new WP_REST_Response(['error' => 'Forbidden'], 403);
                }
            } elseif (empty($col['is_public']) && (int) $col['user_id'] !== $user_id) {
                return new WP_REST_Response(['error' => 'Forbidden'], 403);
            }
            unset($col['anonymous_ip']);
            $col['items'] = poke_hub_collections_get_items($id);
            return new WP_REST_Response($col, 200);
        },
    ]);

    register_rest_route('poke-hub/v1', '/collections/(?P<id>\d+)', [
        'methods'             => 'PATCH',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args'                => [
            'id'        => ['required' => true, 'type' => 'integer'],
            'name'      => ['type' => 'string'],
            'options'   => ['type' => 'object'],
            'is_public' => ['type' => 'boolean'],
        ],
        'callback'            => function (WP_REST_Request $request) {
            $collection_id = (int) $request['id'];
            $user_id       = get_current_user_id();
            $data          = [];
            if ($request->has_param('name')) {
                $data['name'] = $request->get_param('name');
            }
            if ($request->has_param('options')) {
                $data['options'] = $request->get_param('options');
            }
            if ($request->has_param('is_public')) {
                $data['is_public'] = $request->get_param('is_public');
            }
            $result = poke_hub_collections_update($collection_id, $user_id, $data);
            if (!$result['success']) {
                return new WP_REST_Response($result, 400);
            }
            return new WP_REST_Response($result, 200);
        },
    ]);

    register_rest_route('poke-hub/v1', '/collections/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'permission_callback' => '__return_true',
        'args'                => ['id' => ['required' => true, 'type' => 'integer']],
        'callback'            => function (WP_REST_Request $request) {
            $collection_id = (int) $request['id'];
            $user_id       = get_current_user_id();
            $ip            = poke_hub_collections_get_client_ip();
            $result        = $user_id > 0
                ? poke_hub_collections_delete($collection_id, $user_id)
                : poke_hub_collections_delete($collection_id, 0, $ip);
            if (!$result['success']) {
                return new WP_REST_Response($result, 400);
            }
            return new WP_REST_Response($result, 200);
        },
    ]);

    register_rest_route('poke-hub/v1', '/collections/(?P<id>\d+)/items', [
        'methods'             => 'POST',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args'                => [
            'id'     => ['required' => true, 'type' => 'integer'],
            'items'  => ['required' => true, 'type' => 'object'], // { pokemon_id: status }
        ],
        'callback'            => function (WP_REST_Request $request) {
            $collection_id = (int) $request['id'];
            $user_id       = get_current_user_id();
            $items         = $request->get_param('items');
            if (!is_array($items)) {
                return new WP_REST_Response(['success' => false, 'message' => 'Invalid items'], 400);
            }
            $ok = poke_hub_collections_set_items($collection_id, $items, $user_id);
            return new WP_REST_Response(['success' => $ok], 200);
        },
    ]);

    register_rest_route('poke-hub/v1', '/collections/(?P<id>\d+)/item', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'args'                => [
            'id'         => ['required' => true, 'type' => 'integer'],
            'pokemon_id' => ['required' => true, 'type' => 'integer'],
            'status'     => ['required' => true, 'type' => 'string', 'enum' => ['owned', 'for_trade', 'missing']],
        ],
        'callback'            => function (WP_REST_Request $request) {
            $collection_id = (int) $request['id'];
            $pokemon_id    = (int) $request->get_param('pokemon_id');
            $status        = $request->get_param('status');
            $user_id       = get_current_user_id();
            $ip            = poke_hub_collections_get_client_ip();
            if ($user_id > 0) {
                $ok = poke_hub_collections_set_item($collection_id, $pokemon_id, $status, $user_id);
            } else {
                $ok = poke_hub_collections_set_item($collection_id, $pokemon_id, $status, 0, $ip);
            }
            return new WP_REST_Response(['success' => $ok], $ok ? 200 : 400);
        },
    ]);

    register_rest_route('poke-hub/v1', '/collections/(?P<id>\d+)/reset', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => ['required' => true, 'type' => 'integer'],
        ],
        'callback'            => function (WP_REST_Request $request) {
            $collection_id = (int) $request['id'];
            $user_id         = get_current_user_id();
            $ip              = poke_hub_collections_get_client_ip();
            if ($user_id > 0) {
                $result = poke_hub_collections_reset_items($collection_id, $user_id);
            } else {
                $result = poke_hub_collections_reset_items($collection_id, 0, $ip);
            }
            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        },
    ]);

    register_rest_route('poke-hub/v1', '/collections/anonymous-by-ip', [
        'methods'             => 'GET',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'callback'            => function (WP_REST_Request $request) {
            $ip   = poke_hub_collections_get_client_ip();
            $list = poke_hub_collections_get_anonymous_by_ip($ip);
            foreach ($list as &$row) {
                unset($row['anonymous_ip']);
            }
            unset($row);
            return new WP_REST_Response($list, 200);
        },
    ]);

    register_rest_route('poke-hub/v1', '/collections/claim', [
        'methods'             => 'POST',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args'                => [
            'collection_id' => ['required' => true, 'type' => 'integer'],
        ],
        'callback'            => function (WP_REST_Request $request) {
            $collection_id = (int) $request->get_param('collection_id');
            $user_id       = get_current_user_id();
            $ip            = poke_hub_collections_get_client_ip();
            $result        = poke_hub_collections_claim($collection_id, $user_id, $ip);
            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        },
    ]);
});
