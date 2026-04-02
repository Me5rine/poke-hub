<?php
/**
 * Rendu du bloc "New Pokemon"
 *
 * @var array    $attributes Les attributs du bloc.
 * @var string   $content    Le contenu HTML du bloc.
 * @var WP_Block $block      L'instance du bloc.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Récupération robuste du post_id (compatible Elementor et autres contextes)
$post_id = 0;

// 1) Contexte Gutenberg (fiable même hors loop)
if (isset($block) && is_object($block) && !empty($block->context['postId'])) {
    $post_id = (int) $block->context['postId'];
}

// 2) Fallback loop
if (!$post_id) {
    $post_id = (int) get_the_ID();
}

// 3) Fallback requête courante
if (!$post_id) {
    $post_id = (int) get_queried_object_id();
}

// 4) Fallback global
if (!$post_id && !empty($GLOBALS['post']->ID)) {
    $post_id = (int) $GLOBALS['post']->ID;
}

if (!$post_id) {
    return '';
}

$auto_detect = $attributes['autoDetect'] ?? true;
$pokemon_ids = $attributes['pokemonIds'] ?? [];

$meta_pokemon_genders = [];
if ($auto_detect && function_exists('pokehub_content_get_new_pokemon')) {
    $data = pokehub_content_get_new_pokemon('post', (int) $post_id);
    if (!empty($data['ids'])) {
        $pokemon_ids = array_map('intval', $data['ids']);
    }
    if (!empty($data['genders']) && is_array($data['genders'])) {
        $meta_pokemon_genders = $data['genders'];
    }
}

// Si toujours vide, retourner vide
if (empty($pokemon_ids)) {
    return '';
}

// Vérifier que les fonctions nécessaires sont disponibles
if (!function_exists('pokehub_get_pokemon_data_by_id') ||
    !function_exists('poke_hub_pokemon_get_image_sources') ||
    !function_exists('pokehub_get_table') ||
    !function_exists('pokehub_get_pokemon_types_for_display')) {
    return '';
}

global $wpdb;

// Déterminer si on doit utiliser les tables distantes ou locales
$use_remote_pokemon = false;
if (function_exists('poke_hub_pokemon_get_table_prefix')) {
    $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
    $pokemon_remote_prefix = trim($pokemon_remote_prefix);
    
    if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
        $actual_prefix = poke_hub_pokemon_get_table_prefix();
        if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
            $use_remote_pokemon = true;
        }
    }
}

// Utiliser les bonnes tables selon la configuration
if ($use_remote_pokemon) {
    $pokemon_table = pokehub_get_table('remote_pokemon');
    $evolutions_table = pokehub_get_table('remote_pokemon_evolutions');
    $form_variants_table = pokehub_get_table('remote_pokemon_form_variants');
    $items_table = pokehub_get_table('remote_items');
    $weathers_table = pokehub_get_table('remote_pokemon_weathers');
} else {
    $pokemon_table = pokehub_get_table('pokemon');
    $evolutions_table = pokehub_get_table('pokemon_evolutions');
    $form_variants_table = pokehub_get_table('pokemon_form_variants');
    $items_table = pokehub_get_table('items');
    $weathers_table = pokehub_get_table('pokemon_weathers');
}

if (!$pokemon_table || !$evolutions_table) {
    return '';
}

/**
 * Récupère toutes les évolutions d'un Pokémon (vers les évolutions supérieures)
 * 
 * @param int $pokemon_id ID du Pokémon
 * @param int $form_variant_id ID de la forme (par défaut 0)
 * @return array Liste des évolutions avec leurs conditions
 */
if (!function_exists('pokehub_get_pokemon_evolutions_out')) {
function pokehub_get_pokemon_evolutions_out($pokemon_id, $form_variant_id = 0) {
    global $wpdb;
    
    // Déterminer si on doit utiliser les tables distantes ou locales
    $use_remote_pokemon = false;
    if (function_exists('poke_hub_pokemon_get_table_prefix')) {
        $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
        $pokemon_remote_prefix = trim($pokemon_remote_prefix);
        
        if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote_pokemon = true;
            }
        }
    }
    
    // Utiliser les bonnes tables selon la configuration
    if ($use_remote_pokemon) {
        $evolutions_table = pokehub_get_table('remote_pokemon_evolutions');
        $pokemon_table = pokehub_get_table('remote_pokemon');
        $form_variants_table = pokehub_get_table('remote_pokemon_form_variants');
        $items_table = pokehub_get_table('remote_items');
        $weathers_table = pokehub_get_table('remote_pokemon_weathers');
    } else {
        $evolutions_table = pokehub_get_table('pokemon_evolutions');
        $pokemon_table = pokehub_get_table('pokemon');
        $form_variants_table = pokehub_get_table('pokemon_form_variants');
        $items_table = pokehub_get_table('items');
        $weathers_table = pokehub_get_table('pokemon_weathers');
    }
    
    if (!$evolutions_table || !$pokemon_table) {
        return [];
    }

    // D'abord, chercher avec la forme spécifique
    // Utiliser LEFT JOIN au lieu de INNER JOIN pour ne pas exclure les évolutions si le target_pokemon n'existe pas
    $evolutions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT e.*,
                    t.id AS target_id,
                    t.dex_number AS target_dex_number,
                    t.name_fr AS target_name_fr,
                    t.name_en AS target_name_en,
                    t.slug AS target_slug,
                    tv.label AS target_variant_label,
                    tv.form_slug AS target_form_slug,
                    i.name_fr AS item_name_fr,
                    i.name_en AS item_name_en,
                    w.name_fr AS weather_name_fr,
                    w.name_en AS weather_name_en
             FROM {$evolutions_table} e
             LEFT JOIN {$pokemon_table} t ON t.id = e.target_pokemon_id
             LEFT JOIN {$form_variants_table} tv ON tv.id = e.target_form_variant_id
             LEFT JOIN {$items_table} i ON i.id = e.item_id
             LEFT JOIN {$items_table} li ON li.id = e.lure_item_id
             LEFT JOIN {$weathers_table} w ON w.slug = e.weather_requirement_slug
             WHERE e.base_pokemon_id = %d
               AND e.base_form_variant_id = %d
             ORDER BY e.priority ASC, e.id ASC",
            (int) $pokemon_id,
            (int) $form_variant_id
        ),
        ARRAY_A
    );

    // Si pas de résultats, chercher aussi avec form_variant_id = 0
    if (empty($evolutions)) {
        $evolutions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*,
                        t.id AS target_id,
                        t.dex_number AS target_dex_number,
                        t.name_fr AS target_name_fr,
                        t.name_en AS target_name_en,
                        t.slug AS target_slug,
                        tv.label AS target_variant_label,
                        tv.form_slug AS target_form_slug,
                        i.name_fr AS item_name_fr,
                        i.name_en AS item_name_en,
                        w.name_fr AS weather_name_fr,
                        w.name_en AS weather_name_en
                 FROM {$evolutions_table} e
                 LEFT JOIN {$pokemon_table} t ON t.id = e.target_pokemon_id
                 LEFT JOIN {$form_variants_table} tv ON tv.id = e.target_form_variant_id
                 LEFT JOIN {$items_table} i ON i.id = e.item_id
                 LEFT JOIN {$items_table} li ON li.id = e.lure_item_id
                 LEFT JOIN {$weathers_table} w ON w.slug = e.weather_requirement_slug
                 WHERE e.base_pokemon_id = %d
                   AND e.base_form_variant_id = 0
                 ORDER BY e.priority ASC, e.id ASC",
                (int) $pokemon_id
            ),
            ARRAY_A
        );
    }
    
    // Si toujours pas de résultats, chercher sans condition sur base_form_variant_id
    // (certaines évolutions peuvent être stockées sans forme spécifique)
    if (empty($evolutions)) {
        $evolutions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*,
                        t.id AS target_id,
                        t.dex_number AS target_dex_number,
                        t.name_fr AS target_name_fr,
                        t.name_en AS target_name_en,
                        t.slug AS target_slug,
                        tv.label AS target_variant_label,
                        tv.form_slug AS target_form_slug,
                        i.name_fr AS item_name_fr,
                        i.name_en AS item_name_en,
                        w.name_fr AS weather_name_fr,
                        w.name_en AS weather_name_en
                 FROM {$evolutions_table} e
                 LEFT JOIN {$pokemon_table} t ON t.id = e.target_pokemon_id
                 LEFT JOIN {$form_variants_table} tv ON tv.id = e.target_form_variant_id
                 LEFT JOIN {$items_table} i ON i.id = e.item_id
                 LEFT JOIN {$items_table} li ON li.id = e.lure_item_id
                 LEFT JOIN {$weathers_table} w ON w.slug = e.weather_requirement_slug
                 WHERE e.base_pokemon_id = %d
                 ORDER BY e.priority ASC, e.id ASC",
                (int) $pokemon_id
            ),
            ARRAY_A
        );
    }

    return $evolutions ?: [];
}
}

/**
 * Récupère toutes les évolutions précédentes d'un Pokémon (depuis les formes de base)
 * 
 * @param int $pokemon_id ID du Pokémon
 * @return array Liste des Pokémon de base avec leurs conditions d'évolution
 */
if (!function_exists('pokehub_get_pokemon_evolutions_in')) {
function pokehub_get_pokemon_evolutions_in($pokemon_id) {
    global $wpdb;
    
    // Déterminer si on doit utiliser les tables distantes ou locales
    $use_remote_pokemon = false;
    if (function_exists('poke_hub_pokemon_get_table_prefix')) {
        $pokemon_remote_prefix = get_option('poke_hub_pokemon_remote_prefix', '');
        $pokemon_remote_prefix = trim($pokemon_remote_prefix);
        
        if (!empty($pokemon_remote_prefix) && $pokemon_remote_prefix !== $wpdb->prefix) {
            $actual_prefix = poke_hub_pokemon_get_table_prefix();
            if (!empty($actual_prefix) && $actual_prefix !== $wpdb->prefix) {
                $use_remote_pokemon = true;
            }
        }
    }
    
    // Utiliser les bonnes tables selon la configuration
    if ($use_remote_pokemon) {
        $evolutions_table = pokehub_get_table('remote_pokemon_evolutions');
        $pokemon_table = pokehub_get_table('remote_pokemon');
        $form_variants_table = pokehub_get_table('remote_pokemon_form_variants');
        $items_table = pokehub_get_table('remote_items');
        $weathers_table = pokehub_get_table('remote_pokemon_weathers');
    } else {
        $evolutions_table = pokehub_get_table('pokemon_evolutions');
        $pokemon_table = pokehub_get_table('pokemon');
        $form_variants_table = pokehub_get_table('pokemon_form_variants');
        $items_table = pokehub_get_table('items');
        $weathers_table = pokehub_get_table('pokemon_weathers');
    }
    
    if (!$evolutions_table || !$pokemon_table) {
        return [];
    }
    
    $evolutions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT e.*,
                    b.id AS base_id,
                    b.dex_number AS base_dex_number,
                    b.name_fr AS base_name_fr,
                    b.name_en AS base_name_en,
                    b.slug AS base_slug,
                    bv.label AS base_variant_label,
                    bv.form_slug AS base_form_slug,
                    i.name_fr AS item_name_fr,
                    i.name_en AS item_name_en,
                    w.name_fr AS weather_name_fr,
                    w.name_en AS weather_name_en
             FROM {$evolutions_table} e
             INNER JOIN {$pokemon_table} b ON b.id = e.base_pokemon_id
             LEFT JOIN {$form_variants_table} bv ON bv.id = e.base_form_variant_id
             LEFT JOIN {$items_table} i ON i.id = e.item_id
             LEFT JOIN {$items_table} li ON li.id = e.lure_item_id
             LEFT JOIN {$weathers_table} w ON w.slug = e.weather_requirement_slug
             WHERE e.target_pokemon_id = %d
             ORDER BY e.priority ASC, e.id ASC",
            (int) $pokemon_id
        ),
        ARRAY_A
    );
    
    return $evolutions ?: [];
}
}

/**
 * Fonction récursive pour construire la lignée d'évolution en remontant depuis un Pokémon
 */
if (!function_exists('pokehub_build_line_backward')) {
function pokehub_build_line_backward($current_id, $current_form_id, &$line, &$visited) {
    if (in_array($current_id . '_' . $current_form_id, $visited, true)) {
        return; // Éviter les boucles
    }
    $visited[] = $current_id . '_' . $current_form_id;
    
    $evolutions_in = pokehub_get_pokemon_evolutions_in($current_id);
    
    if (empty($evolutions_in)) {
        // C'est un Pokémon de base, l'ajouter au début de la ligne
        $pokemon_data = pokehub_get_pokemon_data_by_id($current_id);
        if ($pokemon_data) {
            array_unshift($line, [
                'pokemon' => $pokemon_data,
                'evolution' => null, // Pas d'évolution depuis ce Pokémon
            ]);
        }
        return;
    }
    
    // Pour chaque évolution précédente, construire récursivement
    foreach ($evolutions_in as $evo) {
        $base_id = (int) $evo['base_id'];
        $base_form_id = isset($evo['base_form_variant_id']) ? (int) $evo['base_form_variant_id'] : 0;
        
        pokehub_build_line_backward($base_id, $base_form_id, $line, $visited);
        
        // Ajouter le Pokémon actuel après sa base
        $pokemon_data = pokehub_get_pokemon_data_by_id($current_id);
        if ($pokemon_data) {
            // Trouver la position dans la ligne où insérer
            $inserted = false;
            foreach ($line as $idx => $item) {
                if (isset($item['pokemon']['id']) && $item['pokemon']['id'] == $base_id) {
                    // Insérer après ce Pokémon
                    array_splice($line, $idx + 1, 0, [[
                        'pokemon' => $pokemon_data,
                        'evolution' => $evo, // Conditions d'évolution depuis la base
                    ]]);
                    $inserted = true;
                    break;
                }
            }
            if (!$inserted) {
                // Si pas trouvé, ajouter à la fin
                $line[] = [
                    'pokemon' => $pokemon_data,
                    'evolution' => $evo,
                ];
            }
        }
    }
}
}

/**
 * Trouve le Pokémon de base d'une lignée d'évolution
 * 
 * @param int $pokemon_id ID du Pokémon
 * @return array|null Données du Pokémon de base ou null
 */
if (!function_exists('pokehub_find_base_pokemon')) {
function pokehub_find_base_pokemon($pokemon_id) {
    $visited = [];
    $current_id = $pokemon_id;
    $max_iterations = 50;
    $iteration = 0;
    
    while ($iteration < $max_iterations) {
        $iteration++;
        
        if (in_array($current_id, $visited, true)) {
            break; // Boucle détectée
        }
        $visited[] = $current_id;
        
        $evolutions_in = pokehub_get_pokemon_evolutions_in($current_id);
        
        if (empty($evolutions_in)) {
            // C'est le Pokémon de base
            return pokehub_get_pokemon_data_by_id($current_id);
        }
        
        // Prendre la première évolution précédente
        $base_id = (int) $evolutions_in[0]['base_id'];
        $current_id = $base_id;
    }
    
    // Si on arrive ici, retourner le Pokémon original
    return pokehub_get_pokemon_data_by_id($pokemon_id);
}
}

/**
 * Construit récursivement la structure d'évolution d'un Pokémon avec ses branches
 * 
 * @param int $pokemon_id ID du Pokémon
 * @param int $form_variant_id ID de la forme
 * @param array $visited Pokémon déjà visités (pour éviter les boucles)
 * @return array Structure avec pokemon, evolution, et evolutions (branches)
 */
if (!function_exists('pokehub_build_evolution_tree')) {
function pokehub_build_evolution_tree($pokemon_id, $form_variant_id = 0, &$visited = []) {
    $key = $pokemon_id . '_' . $form_variant_id;
    
    // Éviter les boucles
    if (in_array($key, $visited, true)) {
        return null;
    }
    $visited[] = $key;
    
    $pokemon_data = pokehub_get_pokemon_data_by_id($pokemon_id);
    if (!$pokemon_data) {
        return null;
    }
    
    // Récupérer les évolutions de ce Pokémon
    $evolutions_out = pokehub_get_pokemon_evolutions_out($pokemon_id, $form_variant_id);
    
    $result = [
        'pokemon' => $pokemon_data,
        'evolution' => null, // L'évolution qui mène à ce Pokémon (sera défini par le parent)
        'evolutions' => [], // Les évolutions depuis ce Pokémon
    ];
    
    // Construire récursivement les branches d'évolution
    if (!empty($evolutions_out)) {
        foreach ($evolutions_out as $evo) {
            $target_id = (int) $evo['target_id'];
            $target_form_id = isset($evo['target_form_variant_id']) ? (int) $evo['target_form_variant_id'] : 0;
            
            $branch = pokehub_build_evolution_tree($target_id, $target_form_id, $visited);
            if ($branch) {
                $branch['evolution'] = $evo; // L'évolution qui mène à ce Pokémon
                $result['evolutions'][] = $branch;
            }
        }
    }
    
    return $result;
}
}

/**
 * Construit la lignée d'évolution complète d'un Pokémon
 * Part toujours du Pokémon de base et construit toute la lignée jusqu'à la fin
 * Gère les branches d'évolution correctement
 * 
 * @param int $pokemon_id ID du Pokémon
 * @return array Structure de la lignée avec tous les Pokémon et leurs conditions
 */
if (!function_exists('pokehub_build_evolution_line')) {
function pokehub_build_evolution_line($pokemon_id) {
    // Trouver le Pokémon de base
    $base_pokemon = pokehub_find_base_pokemon($pokemon_id);
    if (!$base_pokemon) {
        return null;
    }
    
    $base_id = $base_pokemon['id'];
    $base_form_id = isset($base_pokemon['form_variant_id']) ? (int) $base_pokemon['form_variant_id'] : 0;
    
    // Construire l'arbre d'évolution depuis le Pokémon de base
    $visited = [];
    $tree = pokehub_build_evolution_tree($base_id, $base_form_id, $visited);
    
    if (!$tree) {
        return null;
    }
    
    // Convertir l'arbre en structure linéaire pour l'affichage
    // On va créer une structure qui permet d'afficher les branches
    return $tree;
}
}

/**
 * Formate les conditions d'évolution pour l'affichage
 * 
 * @param array $evolution Données de l'évolution
 * @return string Texte formaté des conditions
 */
if (!function_exists('pokehub_format_evolution_conditions')) {
function pokehub_format_evolution_conditions($evolution) {
    if (!$evolution || !is_array($evolution)) {
        return '';
    }
    
    $conditions = [];
    
    // Bonbons
    if (!empty($evolution['candy_cost']) && (int) $evolution['candy_cost'] > 0) {
        $conditions[] = sprintf(__('%d candies', 'poke-hub'), (int) $evolution['candy_cost']);
    }
    
    // Objet requis
    if (!empty($evolution['item_name_fr']) || !empty($evolution['item_name_en'])) {
        $item_name = !empty($evolution['item_name_fr']) ? $evolution['item_name_fr'] : $evolution['item_name_en'];
        $conditions[] = $item_name;
    }
    
    // Leurre
    if (!empty($evolution['lure_item_slug'])) {
        $conditions[] = __('Lure required', 'poke-hub');
    }
    
    // Weather
    if (!empty($evolution['weather_name_fr']) || !empty($evolution['weather_name_en'])) {
        $weather_name = !empty($evolution['weather_name_fr']) ? $evolution['weather_name_fr'] : $evolution['weather_name_en'];
        $conditions[] = sprintf(__('Weather: %s', 'poke-hub'), $weather_name);
    }
    
    // Gender
    if (!empty($evolution['gender_requirement'])) {
        $gender = strtoupper($evolution['gender_requirement']);
        if ($gender === 'MALE') {
            $conditions[] = __('Male', 'poke-hub');
        } elseif ($gender === 'FEMALE') {
            $conditions[] = __('Female', 'poke-hub');
        }
    }
    
    // Quest
    if (!empty($evolution['quest_template_id'])) {
        $conditions[] = __('Quest required', 'poke-hub');
    }
    
    // Trade
    if (!empty($evolution['is_trade_evolution']) && (int) $evolution['is_trade_evolution'] === 1) {
        $conditions[] = __('Trade required', 'poke-hub');
    }
    
    return implode(' • ', $conditions);
}
}

// Traiter chaque Pokémon
$evolution_lines = [];
foreach ($pokemon_ids as $pokemon_id) {
    $line = pokehub_build_evolution_line($pokemon_id);
    if ($line) {
        $evolution_lines[] = $line;
    }
}

if (empty($evolution_lines)) {
    return '';
}

// Wrapper avec les attributs du bloc
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'pokehub-new-pokemon-evolutions-block-wrapper']);

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
    <h2 class="pokehub-block-title"><?php esc_html_e('New Pokemon', 'poke-hub'); ?></h2>
    
    <?php 
    /**
     * Fonction récursive pour afficher un nœud d'évolution et ses branches
     */
    if (!function_exists('pokehub_render_evolution_node')) {
    function pokehub_render_evolution_node($node, $is_first = false, array $meta_genders = []) {
        if (!$node || !isset($node['pokemon'])) {
            return;
        }
        
        $pokemon = $node['pokemon'];
        $pokemon_row_id = (int) ($pokemon['id'] ?? 0);
        $evolution = isset($node['evolution']) ? $node['evolution'] : null;
        $evolutions = isset($node['evolutions']) && is_array($node['evolutions']) ? $node['evolutions'] : [];
        
        // Déterminer le sexe à utiliser pour l'image
        $gender_for_image = null;
        
        // Si c'est le premier Pokémon (base de la lignée), vérifier si toutes ses évolutions nécessitent le même sexe
        if ($is_first && !empty($evolutions)) {
            $required_genders = [];
            foreach ($evolutions as $branch) {
                if (isset($branch['evolution']) && is_array($branch['evolution']) && !empty($branch['evolution']['gender_requirement'])) {
                    $required_genders[] = strtoupper($branch['evolution']['gender_requirement']);
                }
            }
            // Si toutes les évolutions nécessitent le même sexe, utiliser ce sexe
            if (!empty($required_genders)) {
                $unique_genders = array_unique($required_genders);
                if (count($unique_genders) === 1) {
                    $gender = strtolower($unique_genders[0]);
                    if ($gender === 'male' || $gender === 'female') {
                        $gender_for_image = $gender;
                    }
                }
            }
        }
        
        // Si l'évolution qui mène à ce Pokémon nécessite un sexe spécifique, utiliser ce sexe
        if ($evolution && !empty($evolution['gender_requirement'])) {
            $gender = strtolower($evolution['gender_requirement']);
            if ($gender === 'male' || $gender === 'female') {
                $gender_for_image = $gender;
            }
        }
        
        // Vérifier si un genre est forcé dans les postmeta pour ce pokémon (priorité sur les requirements d'évolution)
        if ($pokemon_row_id > 0 && !empty($meta_genders) && isset($meta_genders[$pokemon_row_id])) {
            $forced_gender = $meta_genders[$pokemon_row_id];
            if (in_array($forced_gender, ['male', 'female'], true)) {
                $gender_for_image = $forced_gender;
            }
        }
        
        // Récupérer l'image du Pokémon avec le bon sexe si nécessaire
        $pokemon_obj = is_object($pokemon) ? $pokemon : (object) $pokemon;
        $image_args = ['shiny' => false];
        if ($gender_for_image !== null) {
            $image_args['gender'] = $gender_for_image;
        }
        
        $image_sources = poke_hub_pokemon_get_image_sources($pokemon_obj, $image_args);
        $image_url = !empty($image_sources['primary']) ? $image_sources['primary'] : $image_sources['fallback'];
        
        $pokemon_name = !empty($pokemon['name_fr']) ? $pokemon['name_fr'] : (!empty($pokemon['name_en']) ? $pokemon['name_en'] : 'Pokémon #' . $pokemon['id']);

        $pokemon_types = $pokemon_row_id > 0
            ? pokehub_get_pokemon_types_for_display($pokemon_row_id)
            : [];
        ?>
        
        <?php if (!$is_first) : ?>
            <div class="pokehub-evolution-arrow">
                <svg width="40" height="20" viewBox="0 0 40 20" xmlns="http://www.w3.org/2000/svg">
                    <path d="M 5 10 L 35 10 M 30 5 L 35 10 L 30 15" stroke="currentColor" stroke-width="2" fill="none"/>
                </svg>
                <?php if ($evolution) : ?>
                    <div class="pokehub-evolution-conditions">
                        <?php echo esc_html(pokehub_format_evolution_conditions($evolution)); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="pokehub-evolution-node<?php echo count($evolutions) > 1 ? ' has-branches' : ''; ?>">
            <div class="pokehub-evolution-pokemon">
                <?php if (!empty($pokemon_types)) : ?>
                    <div class="pokehub-evolution-pokemon-types" role="list">
                        <?php foreach ($pokemon_types as $ptype) :
                            $type_label = '';
                            if (!empty($ptype['name_fr'])) {
                                $type_label = (string) $ptype['name_fr'];
                            } elseif (!empty($ptype['name_en'])) {
                                $type_label = (string) $ptype['name_en'];
                            } elseif (!empty($ptype['slug'])) {
                                $type_label = (string) $ptype['slug'];
                            }
                            $type_color = isset($ptype['color']) ? trim((string) $ptype['color']) : '';
                            $type_icon  = isset($ptype['icon']) ? trim((string) $ptype['icon']) : '';
                            $pill_style = $type_color !== '' ? '--pokehub-type-pill-color: ' . esc_attr($type_color) . ';' : '';
                            ?>
                            <span class="pokehub-type-pill" role="listitem" <?php echo $pill_style !== '' ? ' style="' . $pill_style . '"' : ''; ?> title="<?php echo esc_attr($type_label); ?>">
                                <?php if ($type_icon !== '') : ?>
                                    <img src="<?php echo esc_url($type_icon); ?>" alt="" class="pokehub-type-pill-icon" width="18" height="18" loading="lazy" decoding="async" />
                                <?php endif; ?>
                                <?php if ($type_label !== '') : ?>
                                    <span class="pokehub-type-pill-label"><?php echo esc_html($type_label); ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($image_url) : ?>
                    <div class="pokehub-evolution-pokemon-image">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($pokemon_name); ?>" loading="lazy" onerror="this.style.display='none';" />
                    </div>
                <?php endif; ?>
                
                <div class="pokehub-evolution-pokemon-name">
                    <?php echo esc_html($pokemon_name); ?>
                </div>
            </div>
            
            <?php if (!empty($evolutions)) : ?>
                <?php if (count($evolutions) > 1) : ?>
                    <!-- Branches multiples : afficher en branches (comme l'image) -->
                    <div class="pokehub-evolution-branches">
                        <?php foreach ($evolutions as $idx => $branch) : ?>
                            <div class="pokehub-evolution-branch branch-<?php echo $idx === 0 ? 'first' : ($idx === count($evolutions) - 1 ? 'last' : 'middle'); ?>">
                                <?php if ($branch['evolution']) : ?>
                                    <div class="pokehub-evolution-branch-conditions">
                                        <?php echo esc_html(pokehub_format_evolution_conditions($branch['evolution'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php pokehub_render_evolution_node($branch, true, $meta_genders); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <!-- Une seule évolution : continuer en ligne -->
                    <?php foreach ($evolutions as $branch) : ?>
                        <?php pokehub_render_evolution_node($branch, false, $meta_genders); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    }
    
    foreach ($evolution_lines as $line) : ?>
        <div class="pokehub-evolution-line">
            <?php pokehub_render_evolution_node($line, true, $meta_pokemon_genders); ?>
        </div>
    <?php endforeach; ?>
</div>
<?php
$output = ob_get_clean();
return $output;

