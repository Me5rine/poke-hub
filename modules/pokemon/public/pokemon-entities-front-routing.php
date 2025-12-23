<?php
// modules/pokemon/public/pokemon-entities-front-routing.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration des entités avec leurs routes et tables
 */
function pokehub_get_entity_config() {
    return [
        'types' => [
            'route' => 'types',
            'table' => 'pokemon_types',
            'query_var' => 'pokehub_type',
            'name_field' => 'name_fr',
            'name_field_fallback' => 'name_en',
        ],
        'items' => [
            'route' => 'items',
            'table' => 'items',
            'query_var' => 'pokehub_item',
            'name_field' => 'name_fr',
            'name_field_fallback' => 'name_en',
        ],
        'regions' => [
            'route' => 'regions',
            'table' => 'regions',
            'query_var' => 'pokehub_region',
            'name_field' => 'name_fr',
            'name_field_fallback' => 'name_en',
        ],
        'generations' => [
            'route' => 'generations',
            'table' => 'generations',
            'query_var' => 'pokehub_generation',
            'name_field' => 'name_fr',
            'name_field_fallback' => 'name_en',
        ],
        'attacks' => [
            'route' => 'attacks',
            'table' => 'attacks',
            'query_var' => 'pokehub_attack',
            'name_field' => 'name_fr',
            'name_field_fallback' => 'name_en',
        ],
        'weathers' => [
            'route' => 'weathers',
            'table' => 'pokemon_weathers',
            'query_var' => 'pokehub_weather',
            'name_field' => 'name_fr',
            'name_field_fallback' => 'name_en',
        ],
        'backgrounds' => [
            'route' => 'backgrounds',
            'table' => 'pokemon_backgrounds',
            'query_var' => 'pokehub_background',
            'name_field' => 'name_fr',
            'name_field_fallback' => 'name_en',
        ],
    ];
}

/**
 * Ajoute les rewrite rules pour toutes les entités
 */
function pokehub_entities_add_rewrite_rules() {
    $configs = pokehub_get_entity_config();
    
    foreach ($configs as $entity_key => $config) {
        add_rewrite_rule(
            '^pokemon-go/' . $config['route'] . '/([^/]+)/?$',
            'index.php?' . $config['query_var'] . '=$matches[1]',
            'top'
        );
    }
}
add_action('init', 'pokehub_entities_add_rewrite_rules');

/**
 * Enregistre les query vars personnalisées
 */
function pokehub_entities_query_vars($vars) {
    $configs = pokehub_get_entity_config();
    
    foreach ($configs as $entity_key => $config) {
        $vars[] = $config['query_var'];
    }
    
    return $vars;
}
add_filter('query_vars', 'pokehub_entities_query_vars');

/**
 * Intercepte la requête et simule une page WordPress pour chaque entité
 */
function pokehub_entities_setup_query() {
    $configs = pokehub_get_entity_config();
    
    foreach ($configs as $entity_key => $config) {
        $slug = get_query_var($config['query_var']);
        
        if (!$slug) {
            continue;
        }
        
        // Récupérer l'entité depuis la base de données
        global $wpdb;
        
        $table = pokehub_get_table($config['table']);
        if (!$table) {
            continue;
        }
        
        $entity = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE slug = %s LIMIT 1",
                $slug
            )
        );
        
        if (!$entity) {
            // Entité non trouvée, retourner une 404
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }
        
        // Rendre l'entité disponible globalement
        global $pokehub_current_entity;
        $pokehub_current_entity = $entity;
        $pokehub_current_entity_type = $entity_key;
        
        // Récupérer le nom français de l'entité
        $name_field = $config['name_field'];
        $name_field_fallback = $config['name_field_fallback'];
        $entity_name = !empty($entity->$name_field) 
            ? $entity->$name_field 
            : $entity->$name_field_fallback;
        
        // Créer un faux post pour que WordPress/Elementor le reconnaisse
        global $wp_query, $post;
        
        $fake_post = new stdClass();
        $fake_post->ID = -997 - array_search($entity_key, array_keys($configs)); // ID unique par type
        $fake_post->post_author = 1;
        $fake_post->post_date = current_time('mysql');
        $fake_post->post_date_gmt = current_time('mysql', 1);
        $fake_post->post_content = ''; // Le contenu sera généré via un hook
        $fake_post->post_title = $entity_name;
        $fake_post->post_excerpt = '';
        $fake_post->post_status = 'publish';
        $fake_post->comment_status = 'closed';
        $fake_post->ping_status = 'closed';
        $fake_post->post_password = '';
        $fake_post->post_name = $entity->slug;
        $fake_post->to_ping = '';
        $fake_post->pinged = '';
        $fake_post->post_modified = current_time('mysql');
        $fake_post->post_modified_gmt = current_time('mysql', 1);
        $fake_post->post_content_filtered = '';
        $fake_post->post_parent = 0;
        $fake_post->guid = pokehub_get_entity_url($entity_key, $entity->slug);
        $fake_post->menu_order = 0;
        $fake_post->post_type = 'page';
        $fake_post->post_mime_type = '';
        $fake_post->comment_count = 0;
        $fake_post->filter = 'raw';
        
        // Convertir en objet WP_Post
        $post = new WP_Post($fake_post);
        
        // Configurer la query principale
        $wp_query->post = $post;
        $wp_query->posts = [$post];
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = $post->ID;
        $wp_query->found_posts = 1;
        $wp_query->post_count = 1;
        $wp_query->max_num_pages = 1;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_single = false;
        $wp_query->is_attachment = false;
        $wp_query->is_archive = false;
        $wp_query->is_category = false;
        $wp_query->is_tag = false;
        $wp_query->is_tax = false;
        $wp_query->is_author = false;
        $wp_query->is_date = false;
        $wp_query->is_year = false;
        $wp_query->is_month = false;
        $wp_query->is_day = false;
        $wp_query->is_time = false;
        $wp_query->is_search = false;
        $wp_query->is_feed = false;
        $wp_query->is_comment_feed = false;
        $wp_query->is_trackback = false;
        $wp_query->is_home = false;
        $wp_query->is_embed = false;
        $wp_query->is_404 = false;
        $wp_query->is_paged = false;
        $wp_query->is_admin = false;
        $wp_query->is_preview = false;
        $wp_query->is_robots = false;
        $wp_query->is_posts_page = false;
        $wp_query->is_post_type_archive = false;
        
        // Supprimer les filtres qui pourraient interférer
        remove_all_filters('the_content');
        remove_all_filters('the_excerpt');
        
        // Ajouter les filtres de base de WordPress
        add_filter('the_content', 'wptexturize');
        add_filter('the_content', 'convert_smilies');
        add_filter('the_content', 'wpautop');
        add_filter('the_content', 'shortcode_unautop');
        add_filter('the_content', 'prepend_attachment');
        add_filter('the_content', 'wp_filter_content_tags');
        add_filter('the_content', 'do_shortcode', 11);
        
        // Ajouter notre filtre pour injecter le contenu de l'entité
        add_filter('the_content', 'pokehub_entities_inject_content', 20);
        
        // Sortir après avoir traité la première entité trouvée
        return;
    }
}
add_action('wp', 'pokehub_entities_setup_query', 1);

/**
 * Injecte le contenu de l'entité dans the_content
 */
function pokehub_entities_inject_content($content) {
    global $pokehub_current_entity, $pokehub_current_entity_type;
    
    if (!isset($pokehub_current_entity) || !isset($pokehub_current_entity_type)) {
        return $content;
    }
    
    // Vérifier qu'on est dans la boucle principale pour éviter les duplications
    if (is_main_query() && in_the_loop()) {
        $entity = $pokehub_current_entity;
        $entity_type = $pokehub_current_entity_type;
    } else {
        // Si on n'est pas dans la boucle, on retourne le contenu vide pour la première fois
        static $content_generated = false;
        if (!$content_generated) {
            $entity = $pokehub_current_entity;
            $entity_type = $pokehub_current_entity_type;
            $content_generated = true;
        } else {
            return $content;
        }
    }
    
    $configs = pokehub_get_entity_config();
    $config = $configs[$entity_type];
    
    // Récupérer le nom français de l'entité
    $name_field = $config['name_field'];
    $name_field_fallback = $config['name_field_fallback'];
    $entity_name = !empty($entity->$name_field) 
        ? $entity->$name_field 
        : $entity->$name_field_fallback;
    
    // Générer le contenu de l'entité
    ob_start();
    ?>
    <div class="pokehub-entity-content pokehub-entity-content--<?php echo esc_attr($entity_type); ?>">
        <p><?php echo esc_html($entity_name); ?></p>
        <?php
        // Hook pour ajouter du contenu personnalisé
        do_action('pokehub_entity_content', $entity, $entity_type);
        ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Modifie le titre de la page pour les entités
 */
function pokehub_entities_document_title($title) {
    $configs = pokehub_get_entity_config();
    
    foreach ($configs as $entity_key => $config) {
        $slug = get_query_var($config['query_var']);
        
        if (!$slug) {
            continue;
        }
        
        global $pokehub_current_entity;
        
        if (isset($pokehub_current_entity)) {
            // Utiliser le nom français si disponible, sinon le nom anglais
            $name_field = $config['name_field'];
            $name_field_fallback = $config['name_field_fallback'];
            $entity_name = !empty($pokehub_current_entity->$name_field) 
                ? $pokehub_current_entity->$name_field 
                : $pokehub_current_entity->$name_field_fallback;
            $title['title'] = esc_html($entity_name);
            $title['page'] = '';
            break;
        }
    }
    
    return $title;
}
add_filter('document_title_parts', 'pokehub_entities_document_title');

/**
 * Fonction helper pour récupérer l'URL d'une entité
 */
function pokehub_get_entity_url($entity_type, $slug) {
    $configs = pokehub_get_entity_config();
    
    if (!isset($configs[$entity_type])) {
        return '';
    }
    
    $config = $configs[$entity_type];
    return home_url('/pokemon-go/' . $config['route'] . '/' . $slug);
}

/**
 * Fonctions helpers spécifiques pour chaque type d'entité
 */
function pokehub_get_type_url($slug) {
    return pokehub_get_entity_url('types', $slug);
}

function pokehub_get_item_url($slug) {
    return pokehub_get_entity_url('items', $slug);
}

function pokehub_get_region_url($slug) {
    return pokehub_get_entity_url('regions', $slug);
}

function pokehub_get_generation_url($slug) {
    return pokehub_get_entity_url('generations', $slug);
}

function pokehub_get_attack_url($slug) {
    return pokehub_get_entity_url('attacks', $slug);
}

function pokehub_get_weather_url($slug) {
    return pokehub_get_entity_url('weathers', $slug);
}

function pokehub_get_background_url($slug) {
    return pokehub_get_entity_url('backgrounds', $slug);
}

