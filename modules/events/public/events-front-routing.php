<?php
// modules/events/public/events-front-routing.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute les rewrite rules pour les événements spéciaux
 */
function pokehub_special_events_add_rewrite_rules() {
    // Route pour afficher un événement spécial
    // Exemple: /pokemon-go/events/slug-evenement
    add_rewrite_rule(
        '^pokemon-go/events/([^/]+)/?$',
        'index.php?pokehub_special_event=$matches[1]',
        'top'
    );
}
add_action('init', 'pokehub_special_events_add_rewrite_rules');

/**
 * Enregistre la query var personnalisée
 */
function pokehub_special_events_query_vars($vars) {
    $vars[] = 'pokehub_special_event';
    return $vars;
}
add_filter('query_vars', 'pokehub_special_events_query_vars');

/**
 * Intercepte la requête et simule une page WordPress
 */
function pokehub_special_events_setup_query() {
    $event_slug = get_query_var('pokehub_special_event');
    
    if (!$event_slug) {
        return;
    }
    
    // Récupérer l'événement depuis la base de données
    global $wpdb;
    
    // 1. Chercher d'abord dans la table locale
    $local_table = pokehub_get_table('special_events');
    $event = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$local_table} WHERE slug = %s LIMIT 1",
            $event_slug
        )
    );
    
    // 2. Si non trouvé, chercher dans la table distante
    if (!$event) {
        $remote_table = pokehub_get_table('remote_special_events');
        if ($remote_table) {
            // Vérifier que la table existe
            if (function_exists('pokehub_table_exists') && pokehub_table_exists($remote_table)) {
                $event = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$remote_table} WHERE slug = %s LIMIT 1",
                        $event_slug
                    )
                );
                
                // Marquer comme événement distant pour traitement différencié si besoin
                if ($event) {
                    $event->_source = 'remote';
                }
            }
        }
    } else {
        $event->_source = 'local';
    }
    
    if (!$event) {
        // Événement non trouvé (ni local ni distant), retourner une 404
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }
    
    // Rendre l'événement disponible globalement
    global $pokehub_current_special_event;
    $pokehub_current_special_event = $event;
    
    // Créer un faux post pour que WordPress/Elementor le reconnaisse
    global $wp_query, $post;
    
    // Utiliser le titre français si disponible, sinon le titre par défaut
    $event_title = !empty($event->title_fr) ? $event->title_fr : $event->title;
    
    $fake_post = new stdClass();
    $fake_post->ID = -999; // ID négatif pour éviter les conflits
    $fake_post->post_author = 1;
    $fake_post->post_date = current_time('mysql');
    $fake_post->post_date_gmt = current_time('mysql', 1);
    $fake_post->post_content = ''; // Le contenu sera généré via un hook
    $fake_post->post_title = $event_title;
    $fake_post->post_excerpt = '';
    $fake_post->post_status = 'publish';
    $fake_post->comment_status = 'closed';
    $fake_post->ping_status = 'closed';
    $fake_post->post_password = '';
    $fake_post->post_name = $event->slug;
    $fake_post->to_ping = '';
    $fake_post->pinged = '';
    $fake_post->post_modified = current_time('mysql');
    $fake_post->post_modified_gmt = current_time('mysql', 1);
    $fake_post->post_content_filtered = '';
    $fake_post->post_parent = 0;
    $fake_post->guid = pokehub_get_special_event_url($event->slug);
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
    
    // Ajouter notre filtre pour injecter le contenu de l'événement
    add_filter('the_content', 'pokehub_special_events_inject_content', 20);
}
add_action('wp', 'pokehub_special_events_setup_query', 1);

/**
 * Injecte le contenu de l'événement dans the_content
 */
function pokehub_special_events_inject_content($content) {
    global $pokehub_current_special_event;
    
    if (!isset($pokehub_current_special_event)) {
        return $content;
    }
    
    // Vérifier qu'on est dans la boucle principale pour éviter les duplications
    if (is_main_query() && in_the_loop()) {
        $event = $pokehub_current_special_event;
    } else {
        // Si on n'est pas dans la boucle, on retourne le contenu vide pour la première fois
        // (ça permet de générer le contenu même si le thème n'utilise pas in_the_loop correctement)
        static $content_generated = false;
        if (!$content_generated) {
            $event = $pokehub_current_special_event;
            $content_generated = true;
        } else {
            return $content;
        }
    }
    
    // Déterminer si c'est un événement local ou distant
    $is_remote = !empty($event->_source) && $event->_source === 'remote';
    
    // Gérer l'URL de l'image selon la source
    $image_url = '';
    if (!empty($event->image_url)) {
        $image_url = $event->image_url;
    } elseif (!empty($event->image_id)) {
        if ($is_remote && function_exists('poke_hub_events_get_remote_attachment_url')) {
            // Pour les événements distants, utiliser la fonction de récupération d'URL distante
            $image_url = poke_hub_events_get_remote_attachment_url((int) $event->image_id);
        } else {
            // Pour les événements locaux, utiliser la fonction WordPress standard
            $image_url = wp_get_attachment_image_url((int) $event->image_id, 'large');
        }
    }
    
    // Stocker l'URL de l'image dans l'événement pour utilisation dans les hooks
    if ($image_url) {
        $event->computed_image_url = $image_url;
    }
    
    // Générer le contenu de l'événement
    ob_start();
    ?>
    <div class="pokehub-special-event-content" data-source="<?php echo esc_attr($is_remote ? 'remote' : 'local'); ?>">
        <?php
        // Vous pouvez personnaliser ce template ici
        if (!empty($event->description)) {
            echo wp_kses_post($event->description);
        } else {
            echo '<p>' . esc_html__('Détails de l\'événement à venir...', 'poke-hub') . '</p>';
        }
        
        // Informations supplémentaires
        if (!empty($event->start_ts) && !empty($event->end_ts)) {
            echo '<div class="pokehub-event-dates">';
            echo '<p><strong>' . esc_html__('Début :', 'poke-hub') . '</strong> ';
            echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $event->start_ts, wp_timezone()));
            echo '</p>';
            echo '<p><strong>' . esc_html__('Fin :', 'poke-hub') . '</strong> ';
            echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $event->end_ts, wp_timezone()));
            echo '</p>';
            echo '</div>';
        }
        
        // Hook pour ajouter du contenu personnalisé
        // L'événement passé au hook contient maintenant computed_image_url si disponible
        do_action('pokehub_special_event_content', $event);
        ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Modifie le titre de la page pour les événements spéciaux
 */
function pokehub_special_events_document_title($title) {
    $event_slug = get_query_var('pokehub_special_event');
    
    if (!$event_slug) {
        return $title;
    }
    
    global $pokehub_current_special_event;
    
    if (isset($pokehub_current_special_event)) {
        // Utiliser le titre français si disponible, sinon le titre par défaut
        $event_title = !empty($pokehub_current_special_event->title_fr) 
            ? $pokehub_current_special_event->title_fr 
            : $pokehub_current_special_event->title;
        $title['title'] = esc_html($event_title);
        $title['page'] = '';
    }
    
    return $title;
}
add_filter('document_title_parts', 'pokehub_special_events_document_title');

/**
 * Fonction helper pour récupérer l'URL d'un événement spécial
 */
function pokehub_get_special_event_url($slug) {
    return home_url('/pokemon-go/events/' . $slug);
}

