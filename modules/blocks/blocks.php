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
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-register.php';   // Enregistrement des blocs
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-helpers.php';    // Helpers pour les blocs
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
    
    /**
     * Enqueue des assets front-end pour les blocs
     */
    function pokehub_blocks_enqueue_frontend_assets() {
        wp_enqueue_style('pokehub-type-icons');

        wp_enqueue_style(
            'pokehub-candy-display',
            POKE_HUB_URL . 'assets/css/poke-hub-candy-display.css',
            [],
            POKE_HUB_VERSION
        );

        // CSS pour le bloc new-pokemon-evolutions
        // Variables de couleur (thème / Elementor) — nécessaire pour quêtes dépliées, etc.
        wp_enqueue_style(
            'poke-hub-global-colors',
            POKE_HUB_URL . 'assets/css/global-colors.css',
            [],
            POKE_HUB_VERSION
        );

        // Styles front des blocs (dates / quests / habitats / wild / titres unifiés Field Research).
        // Chargé avant les CSS par bloc pour que les surcharges locales restent possibles.
        wp_enqueue_style(
            'pokehub-blocks-front-style',
            POKE_HUB_URL . 'assets/css/poke-hub-blocks-front.css',
            ['poke-hub-global-colors', 'pokehub-candy-display'],
            POKE_HUB_VERSION
        );

        wp_enqueue_style(
            'pokehub-new-pokemon-evolutions-front',
            POKE_HUB_URL . 'assets/css/poke-hub-new-pokemon-evolutions-front.css',
            ['pokehub-blocks-front-style', 'pokehub-type-icons', 'pokehub-candy-display'],
            POKE_HUB_VERSION
        );

        // Toggle expand/collapse Field Research (bloc event-quests) — indépendant du module Events
        wp_enqueue_script(
            'pokehub-events-quests',
            POKE_HUB_URL . 'assets/js/pokehub-events-quests.js',
            ['jquery'],
            POKE_HUB_VERSION,
            true
        );

        // Le bloc "bonus" a ses propres styles (contenus dans le module Bonus),
        // mais le bloc est utilisable même quand le module Bonus est inactif.
        wp_enqueue_style(
            'pokehub-bonus-style',
            POKE_HUB_URL . 'assets/css/poke-hub-bonus-front.css',
            ['pokehub-blocks-front-style'],
            POKE_HUB_VERSION
        );
        
        // CSS pour le bloc collection-challenges
        wp_enqueue_style(
            'pokehub-collection-challenges-front',
            POKE_HUB_URL . 'assets/css/poke-hub-collection-challenges-front.css',
            ['pokehub-blocks-front-style', 'pokehub-candy-display'],
            POKE_HUB_VERSION
        );
        
        // CSS pour le bloc special-research
        wp_enqueue_style(
            'pokehub-special-research-front',
            POKE_HUB_URL . 'assets/css/poke-hub-special-research-front.css',
            ['pokehub-blocks-front-style', 'pokehub-candy-display'],
            POKE_HUB_VERSION
        );

        // CSS pour le bloc eggs (utilisable en mode remote, ne dépend pas du module eggs)
        wp_enqueue_style(
            'pokehub-eggs-front',
            POKE_HUB_URL . 'assets/css/poke-hub-eggs-front.css',
            ['pokehub-blocks-front-style'],
            POKE_HUB_VERSION
        );
    }
    add_action('wp_enqueue_scripts', 'pokehub_blocks_enqueue_frontend_assets');

    /**
     * Éditeur Gutenberg : titres principaux des blocs = même CSS que le front (Field Research).
     * Nécessaire pour l’aperçu des blocs dynamiques (ServerSideRender).
     */
    add_action('enqueue_block_editor_assets', function () {
        wp_enqueue_style(
            'poke-hub-global-colors',
            POKE_HUB_URL . 'assets/css/global-colors.css',
            [],
            POKE_HUB_VERSION
        );
        wp_enqueue_style(
            'pokehub-blocks-front-style',
            POKE_HUB_URL . 'assets/css/poke-hub-blocks-front.css',
            ['poke-hub-global-colors'],
            POKE_HUB_VERSION
        );
        wp_enqueue_style('pokehub-type-icons');
        wp_enqueue_style(
            'pokehub-candy-display',
            POKE_HUB_URL . 'assets/css/poke-hub-candy-display.css',
            [],
            POKE_HUB_VERSION
        );
        wp_enqueue_style(
            'pokehub-new-pokemon-evolutions-front',
            POKE_HUB_URL . 'assets/css/poke-hub-new-pokemon-evolutions-front.css',
            ['pokehub-blocks-front-style', 'pokehub-type-icons', 'pokehub-candy-display'],
            POKE_HUB_VERSION
        );

        wp_register_style(
            'pokehub-special-event-single',
            POKE_HUB_URL . 'assets/css/poke-hub-special-events-single.css',
            [],
            POKE_HUB_VERSION
        );
        wp_enqueue_style(
            'pokehub-go-pass-block-front',
            POKE_HUB_URL . 'assets/css/poke-hub-go-pass-block-front.css',
            ['pokehub-blocks-front-style'],
            POKE_HUB_VERSION
        );
        wp_enqueue_style('pokehub-special-event-single');
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
                    $ids = array_values(array_filter(array_map('intval', explode(',', $ids_param)), function($id) { return $id > 0; }));
                } elseif (is_array($ids_param)) {
                    $ids = array_values(array_filter(array_map('intval', $ids_param), function($id) { return $id > 0; }));
                }
                $pokemon_list = pokehub_get_pokemon_for_select_filtered($ids, $search);
                return rest_ensure_response($pokemon_list);
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

