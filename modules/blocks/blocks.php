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
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-quests-helpers.php'; // Helpers quêtes (fallback sans module events)
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-collection-challenges-helpers.php'; // Helpers défis de collection
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-special-research-helpers.php'; // Helpers études spéciales
    require_once POKE_HUB_BLOCKS_PATH . '/functions/blocks-eggs-helpers.php'; // Helpers bloc œufs
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
        // CSS pour le bloc new-pokemon-evolutions
        wp_enqueue_style(
            'pokehub-new-pokemon-evolutions-front',
            POKE_HUB_URL . 'assets/css/poke-hub-new-pokemon-evolutions-front.css',
            [],
            POKE_HUB_VERSION
        );

        // Styles front des blocs (dates / quests / habitats / wild / etc.).
        // Déplacés dans un fichier dédié pour éviter une dépendance au module "events".
        wp_enqueue_style(
            'pokehub-blocks-front-style',
            POKE_HUB_URL . 'assets/css/poke-hub-blocks-front.css',
            [],
            POKE_HUB_VERSION
        );

        // Le bloc "bonus" a ses propres styles (contenus dans le module Bonus),
        // mais le bloc est utilisable même quand le module Bonus est inactif.
        wp_enqueue_style(
            'pokehub-bonus-style',
            POKE_HUB_URL . 'assets/css/poke-hub-bonus-front.css',
            [],
            POKE_HUB_VERSION
        );
        
        // CSS pour le bloc collection-challenges
        wp_enqueue_style(
            'pokehub-collection-challenges-front',
            POKE_HUB_URL . 'assets/css/poke-hub-collection-challenges-front.css',
            [],
            POKE_HUB_VERSION
        );
        
        // CSS pour le bloc special-research
        wp_enqueue_style(
            'pokehub-special-research-front',
            POKE_HUB_URL . 'assets/css/poke-hub-special-research-front.css',
            [],
            POKE_HUB_VERSION
        );

        // CSS pour le bloc eggs (utilisable en mode remote, ne dépend pas du module eggs)
        wp_enqueue_style(
            'pokehub-eggs-front',
            POKE_HUB_URL . 'assets/css/poke-hub-eggs-front.css',
            [],
            POKE_HUB_VERSION
        );
    }
    add_action('wp_enqueue_scripts', 'pokehub_blocks_enqueue_frontend_assets');
    
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
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[POKEHUB] SAUVEGARDE contenu pour ' . $block['blockName'] . ' len=' . strlen($block_content) . ' (priorité 9, avant UM)');
            }
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
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[POKEHUB] RESTAURATION du contenu pour ' . $block['blockName'] . ' depuis le cache, len=' . strlen($restored_content) . ' (priorité 11, après UM)');
                }
                
                return $restored_content;
            }
        }
        
        return $block_content;
    }, 11, 2); // Priorité 11 = après Ultimate Member (priorité 10)
    
    // Debug : vérifier que les blocs sont bien parsés
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // Log de l'état AU DÉBUT de la chaîne render_block (priorité 1)
        add_filter('render_block', function ($block_content, $block) {
            if (!empty($block['blockName']) && strpos($block['blockName'], 'pokehub/') === 0) {
                error_log('[POKEHUB] render_block START ' . $block['blockName'] . ' len=' . strlen($block_content));
            }
            return $block_content;
        }, 1, 2);

        // Dump la liste des callbacks branchés sur render_block (1 seule fois, priorité 2)
        add_filter('render_block', function ($block_content, $block) {
            static $done = false;
            if ($done) return $block_content;

            if (!empty($block['blockName']) && strpos($block['blockName'], 'pokehub/') === 0) {
                $done = true;
                global $wp_filter;

                if (isset($wp_filter['render_block']) && is_object($wp_filter['render_block'])) {
                    $callbacks = $wp_filter['render_block']->callbacks ?? [];
                    $callback_list = [];
                    
                    // Extraire seulement les infos essentielles pour éviter les erreurs mémoire
                    foreach ($callbacks as $priority => $hooks) {
                        foreach ($hooks as $hook) {
                            $callback_info = [];
                            
                            if (is_string($hook['function'])) {
                                $callback_info['type'] = 'function';
                                $callback_info['name'] = $hook['function'];
                            } elseif (is_array($hook['function'])) {
                                $callback_info['type'] = 'method';
                                if (is_object($hook['function'][0])) {
                                    $callback_info['class'] = get_class($hook['function'][0]);
                                } else {
                                    $callback_info['class'] = $hook['function'][0];
                                }
                                $callback_info['method'] = $hook['function'][1] ?? 'unknown';
                            } elseif (is_object($hook['function']) && ($hook['function'] instanceof Closure)) {
                                $callback_info['type'] = 'closure';
                                $callback_info['name'] = 'Closure';
                            } else {
                                $callback_info['type'] = 'unknown';
                                $callback_info['name'] = 'Unknown';
                            }
                            
                            $callback_info['priority'] = $priority;
                            $callback_info['accepted_args'] = $hook['accepted_args'] ?? 1;
                            
                            $callback_list[] = $callback_info;
                        }
                    }
                    
                    error_log('[POKEHUB] render_block callbacks (' . count($callback_list) . '): ' . json_encode($callback_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    error_log('[POKEHUB] render_block: aucun hook trouvé dans $wp_filter');
                }
            }
            return $block_content;
        }, 2, 2);

        // Hook sur render_block pour voir ce qui se passe lors du rendu (priorité 10)
        add_filter('render_block', function($block_content, $block) {
            if (isset($block['blockName']) && strpos($block['blockName'], 'pokehub/') === 0) {
                error_log('[POKEHUB] render_block appelé pour ' . $block['blockName'] . ', longueur=' . strlen($block_content));
                if (empty($block_content)) {
                    error_log('[POKEHUB] ATTENTION: render_block retourne une string vide pour ' . $block['blockName']);
                }
            }
            return $block_content;
        }, 10, 2);

        // Log de l'état À LA FIN de la chaîne render_block (priorité 999)
        add_filter('render_block', function ($block_content, $block) {
            if (!empty($block['blockName']) && strpos($block['blockName'], 'pokehub/') === 0) {
                error_log('[POKEHUB] render_block END   ' . $block['blockName'] . ' len=' . strlen($block_content));
            }
            return $block_content;
        }, 999, 2);
        
        // Debug : vérifier que les blocs sont bien parsés dans the_content
        add_filter('the_content', function($content) {
            // Vérifier si le contenu contient des commentaires de blocs
            if (preg_match('/<!-- wp:pokehub\//', $content)) {
                error_log('[POKEHUB] the_content contient des blocs pokehub avant parsing');
            }
            return $content;
        }, 1);
        
        // Log juste après le parsing des blocs (do_blocks s'exécute généralement avec priorité 9)
        add_filter('the_content', function($content) {
            // Vérifier si le contenu contient des commentaires de blocs (non parsés)
            if (preg_match('/<!-- wp:pokehub\//', $content)) {
                error_log('[POKEHUB] ATTENTION: the_content contient encore des commentaires de blocs pokehub après parsing (priorité 10)');
            }
            // Vérifier si le contenu contient du HTML généré par nos blocs
            if (strpos($content, 'pokehub-event-dates-block-wrapper') !== false ||
                strpos($content, 'pokehub-wild-pokemon-block-wrapper') !== false ||
                strpos($content, 'pokehub-event-quests-block-wrapper') !== false ||
                strpos($content, 'pokehub-bonus-block-wrapper') !== false) {
                error_log('[POKEHUB] the_content contient du HTML généré par nos blocs (priorité 10), longueur=' . strlen($content));
            }
            return $content;
        }, 10);
        
        add_filter('the_content', function($content) {
            // Vérifier si le contenu contient du HTML généré par nos blocs
            if (strpos($content, 'pokehub-event-dates-block-wrapper') !== false ||
                strpos($content, 'pokehub-wild-pokemon-block-wrapper') !== false ||
                strpos($content, 'pokehub-event-quests-block-wrapper') !== false ||
                strpos($content, 'pokehub-bonus-block-wrapper') !== false) {
                error_log('[POKEHUB] the_content contient du HTML généré par nos blocs après parsing (priorité 100), longueur=' . strlen($content));
            } else {
                // Vérifier si les commentaires de blocs sont toujours là (non parsés)
                if (preg_match('/<!-- wp:pokehub\//', $content)) {
                    error_log('[POKEHUB] ATTENTION: the_content contient encore des commentaires de blocs pokehub (non parsés) à priorité 100');
                }
            }
            return $content;
        }, 100);
    }
}, 5);

