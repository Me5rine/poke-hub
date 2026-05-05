<?php
// modules/blocks/blocks.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Constantes de chemin / URL du module Blocks
 */
if (!defined('POKE_HUB_BLOCKS_PATH')) {
    define('POKE_HUB_BLOCKS_PATH', __DIR__);
}
if (!defined('POKE_HUB_BLOCKS_URL')) {
    define('POKE_HUB_BLOCKS_URL', POKE_HUB_URL . 'modules/blocks/');
}

/**
 * Chargement des fonctionnalités du module Blocks
 * Le fichier est inclus par poke_hub_load_modules() sur plugins_loaded (priorité 20)
 * On enregistre les hooks sur init pour s'assurer que tout est prêt, y compris côté front-end
 */
add_action('init', function() {
    /**
     * Chargement des fichiers du module
     */
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-helpers.php';    // Helpers (schéma shop, etc.) — avant blocks-register
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-register.php';   // Enregistrement des blocs
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-title-helpers.php'; // Titres + icône SVG optionnelle
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-field-research.php'; // Field Research (rendu bloc event-quests)
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-quests-helpers.php'; // Helpers quêtes (données post)
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-collection-challenges-helpers.php'; // Helpers défis de collection
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-special-research-helpers.php'; // Helpers études spéciales
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-eggs-helpers.php'; // Helpers bloc œufs
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-rest-go-pass.php'; // REST + chargement helpers Pass GO (fichier events) pour le bloc
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-go-pass-host-link.php'; // Liaison contenu → Pass GO (table locale)
    require_once POKE_HUB_BLOCKS_PATH . '/admin/collection-challenges-metabox.php'; // Meta box défis de collection
    require_once POKE_HUB_BLOCKS_PATH . '/admin/special-research-metabox.php'; // Meta box études spéciales
    require_once POKE_HUB_PATH . 'modules/blocks/admin/blocks-admin-ajax.php'; // AJAX helpers admin (indépendant du module Events)

    // Bloc bonus : tous les éléments (helpers + metabox) sont chargés par le module Blocks uniquement — aucune dépendance au module Bonus
    if (!function_exists('pokehub_get_all_bonuses_for_select')) {
        require_once POKE_HUB_PATH . 'modules/bonus/functions/bonus-helpers.php';
    }
    require_once POKE_HUB_PATH . 'modules/bonus/admin/bonus-metabox.php';

    // Metabox œufs (sélection des œufs pour l'article) : chargée ici si le module Eggs est inactif, pour que le bloc eggs reste utilisable
    if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('eggs')) {
        if (!function_exists('pokehub_add_eggs_metabox')) {
            if (!function_exists('pokehub_get_post_eggs')) {
                require_once POKE_HUB_PATH . 'modules/eggs/functions/eggs-helpers.php';
            }
            require_once POKE_HUB_PATH . 'modules/eggs/admin/eggs-metabox.php';
        }
    }

    // Metaboxs pour les blocs "events" (wild / field research / habitats / nouveaux Pokémon).
    // Elles doivent être disponibles dès que le module Blocks est actif, sans dépendre
    // du module Events.
    require_once POKE_HUB_PATH . 'modules/blocks/admin/blocks-wild-pokemon-metabox.php';
    require_once POKE_HUB_PATH . 'modules/blocks/admin/blocks-quests-metabox.php';
    require_once POKE_HUB_PATH . 'modules/blocks/admin/blocks-habitats-metabox.php';
    require_once POKE_HUB_PATH . 'modules/blocks/admin/blocks-new-pokemon-metabox.php';
    require_once POKE_HUB_PATH . 'modules/blocks/admin/blocks-featured-pokemon-hours-metabox.php';
    require_once POKE_HUB_PATH . 'modules/blocks/admin/blocks-go-pass-metabox.php';
    require_once POKE_HUB_PATH . 'modules/blocks/admin/blocks-go-pass-post-guard.php';

    // Blocs / métaboxes shop : uniquement module Blocks + schéma SQL présent (pas de dépendance au module shop-items).
    if (function_exists('poke_hub_is_module_active') && poke_hub_is_module_active('blocks')) {
        if (function_exists('pokehub_blocks_shop_avatar_schema_ready') && pokehub_blocks_shop_avatar_schema_ready()) {
            if (!function_exists('poke_hub_shop_avatar_get_items_by_ids')) {
                require_once POKE_HUB_PATH . 'modules/shop-items/includes/shop-avatar-helpers.php';
            }
            if (is_admin()) {
                require_once POKE_HUB_BLOCKS_PATH . '/admin/blocks-shop-avatar-metabox-ajax.php';
            }
            require_once POKE_HUB_PATH . 'modules/blocks/admin/blocks-shop-avatar-metabox.php';
        }
        if (function_exists('pokehub_blocks_shop_sticker_schema_ready') && pokehub_blocks_shop_sticker_schema_ready()) {
            if (!function_exists('poke_hub_shop_sticker_get_items_by_ids')) {
                require_once POKE_HUB_PATH . 'modules/shop-items/includes/shop-sticker-helpers.php';
            }
            if (is_admin()) {
                require_once POKE_HUB_BLOCKS_PATH . '/admin/blocks-shop-sticker-metabox-ajax.php';
            }
            require_once POKE_HUB_PATH . 'modules/blocks/admin/blocks-shop-sticker-metabox.php';
        }
    }

    // Debug file is optional - only load if needed for troubleshooting
    // Uncomment the line below if you need to debug block registration:
    // require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-debug.php';

    /**
     * Enregistre la catégorie de blocs Poké HUB
     */
    function pokehub_register_block_category($categories, $editor_context) {
        if (!empty($editor_context->post)) {
            array_unshift(
                $categories,
                [
                    'slug' => 'pokehub',
                    'title' => __('Poké HUB', 'poke-hub'),
                    'icon' => null,
                ]
            );
        }
        return $categories;
    }
    add_filter('block_categories_all', 'pokehub_register_block_category', 10, 2);
    
    // Enregistrer les blocs
    pokehub_blocks_register_all();

    // Select2 + aide Community Day : enregistrés sur init pour pouvoir rattacher une dépendance au script editor du bloc.
    if (!wp_style_is('pokehub-select2-editor', 'registered')) {
        wp_register_style(
            'pokehub-select2-editor',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );
        wp_add_inline_style(
            'pokehub-select2-editor',
            '.components-panel__body .pokehub-community-day-select-wrap select.pokehub-community-day-select{max-width:100%;}'
        );
    }
    if (!wp_script_is('pokehub-select2-editor', 'registered')) {
        wp_register_script(
            'pokehub-select2-editor',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );
    }
    if (!wp_script_is('pokehub-community-day-editor-select2', 'registered')) {
        wp_register_script(
            'pokehub-community-day-editor-select2',
            POKE_HUB_BLOCKS_URL . 'blocks/community-day/editor-select2.js',
            ['jquery', 'pokehub-select2-editor'],
            poke_hub_plugin_asset_version('modules/blocks/blocks/community-day/editor-select2.js'),
            true
        );
        wp_localize_script(
            'pokehub-community-day-editor-select2',
            'pokehubCommunityDayEditorCfg',
            [
                'nonce'          => wp_create_nonce('wp_rest'),
                'restPokemon'    => rest_url('poke-hub/v1/pokemon-for-select'),
                'searching'      => __('Recherche…', 'poke-hub'),
                'inputTooShort'  => __('Saisis au moins une lettre pour chercher.', 'poke-hub'),
            ]
        );
    }
    $cd_block_editor = WP_Block_Type_Registry::get_instance()->get_registered('pokehub/community-day');
    if ($cd_block_editor && !empty($cd_block_editor->editor_script) && wp_script_is('pokehub-community-day-editor-select2', 'registered')) {
        $wp_scripts_registry = wp_scripts();
        foreach ((array) $cd_block_editor->editor_script as $cd_editor_handle) {
            $cd_editor_handle = (string) $cd_editor_handle;
            if ($cd_editor_handle === '') {
                continue;
            }
            if (isset($wp_scripts_registry->registered[$cd_editor_handle])) {
                $dep = $wp_scripts_registry->registered[$cd_editor_handle];
                $dep->deps = array_values(array_unique(array_merge(array_filter((array) $dep->deps), ['pokehub-community-day-editor-select2'])));
            }
        }
    }

    /**
     * Enqueue des assets front-end pour les blocs
     */
    function pokehub_blocks_enqueue_frontend_assets() {
        if (wp_style_is('pokehub-type-icons', 'registered')) {
            wp_enqueue_style('pokehub-type-icons');
        }

        poke_hub_enqueue_bundled_front_style('pokehub-candy-display', 'poke-hub-candy-display.css', []);

        // CSS pour le bloc new-pokemon-evolutions
        // Variables de couleur (thème / Elementor) — nécessaire pour quêtes dépliées, etc.
        poke_hub_enqueue_bundled_front_style('poke-hub-global-colors', 'global-colors.css', []);

        // Styles front des blocs (dates / quests / habitats / wild / titres unifiés Field Research).
        // Chargé avant les CSS par bloc pour que les surcharges locales restent possibles.
        poke_hub_enqueue_bundled_front_style('pokehub-blocks-front-style', 'poke-hub-blocks-front.css', [
            'poke-hub-global-colors',
            'pokehub-candy-display',
        ]);

        poke_hub_enqueue_bundled_front_style('pokehub-new-pokemon-evolutions-front', 'poke-hub-new-pokemon-evolutions-front.css', [
            'pokehub-blocks-front-style',
            'pokehub-type-icons',
            'pokehub-candy-display',
        ]);

        // Toggle expand/collapse Field Research (bloc event-quests) — indépendant du module Events
        wp_enqueue_script(
            'pokehub-events-quests',
            POKE_HUB_URL . 'assets/js/pokehub-events-quests.js',
            ['jquery'],
            poke_hub_plugin_asset_version('assets/js/pokehub-events-quests.js'),
            true
        );

        // Le bloc "bonus" a ses propres styles (contenus dans le module Bonus),
        // mais le bloc est utilisable même quand le module Bonus est inactif.
        poke_hub_enqueue_bundled_front_style('pokehub-bonus-style', 'poke-hub-bonus-front.css', [
            'pokehub-blocks-front-style',
        ]);

        // CSS pour le bloc collection-challenges
        poke_hub_enqueue_bundled_front_style('pokehub-collection-challenges-front', 'poke-hub-collection-challenges-front.css', [
            'pokehub-blocks-front-style',
            'pokehub-candy-display',
        ]);

        // CSS pour le bloc special-research
        poke_hub_enqueue_bundled_front_style('pokehub-special-research-front', 'poke-hub-special-research-front.css', [
            'pokehub-blocks-front-style',
            'pokehub-candy-display',
        ]);

        // CSS pour le bloc eggs (utilisable en mode remote, ne dépend pas du module eggs)
        poke_hub_enqueue_bundled_front_style('pokehub-eggs-front', 'poke-hub-eggs-front.css', [
            'pokehub-blocks-front-style',
        ]);
    }
    add_action('wp_enqueue_scripts', 'pokehub_blocks_enqueue_frontend_assets');

    /**
     * Éditeur Gutenberg : titres principaux des blocs = même CSS que le front (Field Research).
     * Nécessaire pour l’aperçu des blocs dynamiques (ServerSideRender).
     */
    add_action('enqueue_block_editor_assets', function () {
        poke_hub_enqueue_bundled_front_style('poke-hub-global-colors', 'global-colors.css', []);
        poke_hub_enqueue_bundled_front_style('pokehub-blocks-front-style', 'poke-hub-blocks-front.css', [
            'poke-hub-global-colors',
        ]);
        if (wp_style_is('pokehub-type-icons', 'registered')) {
            wp_enqueue_style('pokehub-type-icons');
        }
        poke_hub_enqueue_bundled_front_style('pokehub-candy-display', 'poke-hub-candy-display.css', []);
        poke_hub_enqueue_bundled_front_style('pokehub-new-pokemon-evolutions-front', 'poke-hub-new-pokemon-evolutions-front.css', [
            'pokehub-blocks-front-style',
            'pokehub-type-icons',
            'pokehub-candy-display',
        ]);

        // Toujours charger Select2 + helper Community Day en éditeur pour éviter les états
        // où le script du bloc est présent sans ses dépendances (sélection native non voulue).
        wp_enqueue_style('pokehub-select2-editor');
        wp_enqueue_script('pokehub-community-day-editor-select2');

        poke_hub_register_bundled_front_style('pokehub-special-event-single', 'poke-hub-special-events-single.css', []);
        poke_hub_enqueue_bundled_front_style('pokehub-go-pass-block-front', 'poke-hub-go-pass-block-front.css', [
            'pokehub-blocks-front-style',
        ]);
        if (wp_style_is('pokehub-special-event-single', 'registered')) {
            wp_enqueue_style('pokehub-special-event-single');
        }
    });
    
    /**
     * Endpoint REST API pour récupérer les Pokémon (recherche par nom ou par IDs pour présélection).
     * Paramètres : search (texte), ids (liste d'IDs séparés par des virgules).
     */
    add_action('rest_api_init', function() {
        register_rest_route('poke-hub/v1', '/pokemon-for-select', [
            'methods' => 'GET',
            'callback' => function($request) {
                if (!function_exists('pokehub_get_pokemon_for_select_filtered')) {
                    return new WP_Error('function_not_found', 'pokehub_get_pokemon_for_select_filtered non disponible.', ['status' => 500]);
                }
                $search = is_string($request->get_param('search')) ? trim($request->get_param('search')) : '';
                $ids_param = $request->get_param('ids');
                $ids = [];
                if (is_string($ids_param) && $ids_param !== '') {
                    $parts = array_map('trim', explode(',', $ids_param));
                    foreach ($parts as $p) {
                        if ($p === '') {
                            continue;
                        }
                        if (preg_match('/^(\d+)\|(male|female)$/i', $p, $m)) {
                            $ids[] = $m[1] . '|' . strtolower($m[2]);
                        } else {
                            $n = (int) $p;
                            if ($n > 0) {
                                $ids[] = $n;
                            }
                        }
                    }
                } elseif (is_array($ids_param)) {
                    foreach ($ids_param as $p) {
                        if (is_string($p)) {
                            $p = trim($p);
                            if ($p === '') {
                                continue;
                            }
                        } elseif (is_int($p) && $p <= 0) {
                            continue;
                        }
                        if (is_string($p) && preg_match('/^(\d+)\|(male|female)$/i', $p, $m)) {
                            $ids[] = $m[1] . '|' . strtolower($m[2]);
                        } else {
                            $n = (int) $p;
                            if ($n > 0) {
                                $ids[] = $n;
                            }
                        }
                    }
                }
                $dimorphic_only = false;
                $raw_dimorphic_only = $request->get_param('dimorphic_only');
                if (is_string($raw_dimorphic_only)) {
                    $dimorphic_only = in_array(strtolower(trim($raw_dimorphic_only)), ['1', 'true', 'yes', 'on'], true);
                } elseif (is_numeric($raw_dimorphic_only)) {
                    $dimorphic_only = ((int) $raw_dimorphic_only) === 1;
                } elseif (is_bool($raw_dimorphic_only)) {
                    $dimorphic_only = $raw_dimorphic_only;
                }
                // Sans recherche ni ids : liste complète (local ou remote selon réglages), comme les métaboxes PHP.
                if ($search === '' && empty($ids) && function_exists('pokehub_get_pokemon_for_select')) {
                    $pokemon_list = pokehub_get_pokemon_for_select();
                } else {
                    $pokemon_list = pokehub_get_pokemon_for_select_filtered($ids, $search, $dimorphic_only);
                }
                return rest_ensure_response($pokemon_list);
            },
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('poke-hub/v1', '/pokemon-special-attacks', [
            'methods' => 'GET',
            'callback' => function($request) {
                if (!function_exists('pokehub_get_pokemon_special_attacks')) {
                    return rest_ensure_response([]);
                }
                $pokemon_id = (int) $request->get_param('pokemon_id');
                if ($pokemon_id <= 0) {
                    return rest_ensure_response([]);
                }
                $include_family = false;
                $raw_include_family = $request->get_param('include_family');
                if (is_string($raw_include_family)) {
                    $include_family = in_array(strtolower(trim($raw_include_family)), ['1', 'true', 'yes', 'on'], true);
                } elseif (is_numeric($raw_include_family)) {
                    $include_family = ((int) $raw_include_family) === 1;
                } elseif (is_bool($raw_include_family)) {
                    $include_family = $raw_include_family;
                }
                return rest_ensure_response(pokehub_get_pokemon_special_attacks($pokemon_id, $include_family));
            },
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    });
    
    // Protection contre Ultimate Member qui vide les blocs
    // Ultimate Member utilise um\core\Access::restrict_blocks à la priorité 10
    // Stratégie : sauvegarder le contenu AVANT Ultimate Member (priorité 9) et le restaurer APRÈS (priorité 11)
    
    // Sauvegarde du contenu AVANT Ultimate Member (priorité 9)
    add_filter('render_block', function ($block_content, $block) {
        // Seulement pour nos blocs pokehub
        if (empty($block['blockName']) || strpos($block['blockName'], 'pokehub/') !== 0) {
            return $block_content;
        }
        
        // Sauvegarder le contenu s'il n'est pas vide
        if (!empty($block_content)) {
            $block_key = $block['blockName'] . '_' . md5(serialize($block['attrs'] ?? []));
            
            if (!isset($GLOBALS['pokehub_block_content_cache'])) {
                $GLOBALS['pokehub_block_content_cache'] = [];
            }
            
            $GLOBALS['pokehub_block_content_cache'][$block_key . '_before_um'] = $block_content;
        }
        
        return $block_content;
    }, 9, 2); // Priorité 9 = avant Ultimate Member (priorité 10)
    
    // Restauration du contenu APRÈS Ultimate Member (priorité 11)
    add_filter('render_block', function ($block_content, $block) {
        // Seulement pour nos blocs pokehub
        if (empty($block['blockName']) || strpos($block['blockName'], 'pokehub/') !== 0) {
            return $block_content;
        }
        
        // Si le contenu est vide, essayer de le restaurer depuis le cache
        if (empty($block_content) && isset($GLOBALS['pokehub_block_content_cache'])) {
            $block_key = $block['blockName'] . '_' . md5(serialize($block['attrs'] ?? []));
            $cached_key = $block_key . '_before_um';
            
            if (isset($GLOBALS['pokehub_block_content_cache'][$cached_key])) {
                $restored_content = $GLOBALS['pokehub_block_content_cache'][$cached_key];

                return $restored_content;
            }
        }
        
        return $block_content;
    }, 11, 2); // Priorité 11 = après Ultimate Member (priorité 10)
}, 5);

