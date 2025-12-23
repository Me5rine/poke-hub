<?php
// modules/pokemon/public/pokemon-front-routing.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute les rewrite rules pour les pages Pokémon
 */
function pokehub_pokemon_add_rewrite_rules() {
    // Route pour afficher un Pokémon
    // Exemple: /pokemon-go/pokemon/pikachu
    add_rewrite_rule(
        '^pokemon-go/pokemon/([^/]+)/?$',
        'index.php?pokehub_pokemon=$matches[1]',
        'top'
    );
}
add_action('init', 'pokehub_pokemon_add_rewrite_rules');

/**
 * Enregistre la query var personnalisée
 */
function pokehub_pokemon_query_vars($vars) {
    $vars[] = 'pokehub_pokemon';
    return $vars;
}
add_filter('query_vars', 'pokehub_pokemon_query_vars');

/**
 * Intercepte la requête et simule une page WordPress
 */
function pokehub_pokemon_setup_query() {
    $pokemon_slug = get_query_var('pokehub_pokemon');
    
    if (!$pokemon_slug) {
        return;
    }
    
    // Récupérer le Pokémon depuis la base de données
    global $wpdb;
    
    $pokemon_table = pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        return;
    }
    
    $pokemon = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$pokemon_table} WHERE slug = %s LIMIT 1",
            $pokemon_slug
        )
    );
    
    if (!$pokemon) {
        // Pokémon non trouvé, retourner une 404
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }
    
    // Rendre le Pokémon disponible globalement
    global $pokehub_current_pokemon;
    $pokehub_current_pokemon = $pokemon;
    
    // Créer un faux post pour que WordPress/Elementor le reconnaisse
    global $wp_query, $post;
    
    // Récupérer le nom français du Pokémon
    $pokemon_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
    
    $fake_post = new stdClass();
    $fake_post->ID = -998; // ID négatif pour éviter les conflits
    $fake_post->post_author = 1;
    $fake_post->post_date = current_time('mysql');
    $fake_post->post_date_gmt = current_time('mysql', 1);
    $fake_post->post_content = ''; // Le contenu sera généré via un hook
    $fake_post->post_title = $pokemon_name;
    $fake_post->post_excerpt = '';
    $fake_post->post_status = 'publish';
    $fake_post->comment_status = 'closed';
    $fake_post->ping_status = 'closed';
    $fake_post->post_password = '';
    $fake_post->post_name = $pokemon->slug;
    $fake_post->to_ping = '';
    $fake_post->pinged = '';
    $fake_post->post_modified = current_time('mysql');
    $fake_post->post_modified_gmt = current_time('mysql', 1);
    $fake_post->post_content_filtered = '';
    $fake_post->post_parent = 0;
    $fake_post->guid = pokehub_get_pokemon_url($pokemon->slug);
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
    
    // Ajouter notre filtre pour injecter le contenu du Pokémon
    add_filter('the_content', 'pokehub_pokemon_inject_content', 20);
}
add_action('wp', 'pokehub_pokemon_setup_query', 1);

/**
 * Injecte le contenu du Pokémon dans the_content
 */
function pokehub_pokemon_inject_content($content) {
    global $pokehub_current_pokemon;
    
    if (!isset($pokehub_current_pokemon)) {
        return $content;
    }
    
    // Vérifier qu'on est dans la boucle principale pour éviter les duplications
    if (is_main_query() && in_the_loop()) {
        $pokemon = $pokehub_current_pokemon;
    } else {
        // Si on n'est pas dans la boucle, on retourne le contenu vide pour la première fois
        // (ça permet de générer le contenu même si le thème n'utilise pas in_the_loop correctement)
        static $content_generated = false;
        if (!$content_generated) {
            $pokemon = $pokehub_current_pokemon;
            $content_generated = true;
        } else {
            return $content;
        }
    }
    
    // Récupérer le nom français du Pokémon
    $pokemon_name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
    
    // Générer le contenu du Pokémon
    ob_start();
    ?>
    <div class="pokehub-pokemon-content">
        <p><?php echo esc_html($pokemon_name); ?></p>
        <?php
        // Hook pour ajouter du contenu personnalisé
        do_action('pokehub_pokemon_content', $pokemon);
        ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Modifie le titre de la page pour les Pokémon
 */
function pokehub_pokemon_document_title($title) {
    $pokemon_slug = get_query_var('pokehub_pokemon');
    
    if (!$pokemon_slug) {
        return $title;
    }
    
    global $pokehub_current_pokemon;
    
    if (isset($pokehub_current_pokemon)) {
        // Utiliser le nom français si disponible, sinon le nom anglais
        $pokemon_name = !empty($pokehub_current_pokemon->name_fr) 
            ? $pokehub_current_pokemon->name_fr 
            : $pokehub_current_pokemon->name_en;
        $title['title'] = esc_html($pokemon_name);
        $title['page'] = '';
    }
    
    return $title;
}
add_filter('document_title_parts', 'pokehub_pokemon_document_title');

/**
 * Fonction helper pour récupérer l'URL d'un Pokémon
 */
function pokehub_get_pokemon_url($slug) {
    return home_url('/pokemon-go/pokemon/' . $slug);
}

