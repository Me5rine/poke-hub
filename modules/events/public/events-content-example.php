<?php
/**
 * Exemple d'enrichissement du contenu d'un événement spécial
 * 
 * Ce fichier montre comment ajouter du contenu personnalisé
 * via le hook 'pokehub_special_event_content'
 * 
 * Pour l'utiliser, copiez ce code dans votre functions.php
 * ou dans le fichier custom-hooks.php du plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Exemple : Afficher les Pokémon associés à l'événement
 */
add_action('pokehub_special_event_content', function($event) {
    
    // Récupérer les Pokémon liés à cet événement
    if (function_exists('poke_hub_special_event_get_pokemon')) {
        $pokemon_ids = poke_hub_special_event_get_pokemon($event->id);
        
        if (!empty($pokemon_ids)) {
            echo '<div class="pokehub-event-section">';
            echo '<h2>' . esc_html__('Pokémon en vedette', 'poke-hub') . '</h2>';
            echo '<div class="pokehub-event-pokemon-list">';
            
            foreach ($pokemon_ids as $pokemon_id) {
                global $wpdb;
                $pokemon_table = pokehub_get_table('pokemon');
                
                $pokemon = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id, name_fr, name_en, slug FROM {$pokemon_table} WHERE id = %d",
                        $pokemon_id
                    )
                );
                
                if ($pokemon) {
                    $name = !empty($pokemon->name_fr) ? $pokemon->name_fr : $pokemon->name_en;
                    
                    echo '<div class="pokehub-event-pokemon-item">';
                    
                    // Image du Pokémon (à adapter selon votre système d'images)
                    // echo '<img src="..." alt="' . esc_attr($name) . '">';
                    
                    echo '<div class="pokemon-name">' . esc_html($name) . '</div>';
                    echo '</div>';
                }
            }
            
            echo '</div>';
            echo '</div>';
        }
    }
}, 10);

/**
 * Exemple : Afficher les bonus de l'événement
 */
add_action('pokehub_special_event_content', function($event) {
    
    // Récupérer les bonus liés à cet événement
    if (function_exists('poke_hub_special_event_get_bonus_rows')) {
        $bonuses = poke_hub_special_event_get_bonus_rows($event->id);
        
        if (!empty($bonuses)) {
            echo '<div class="pokehub-event-section">';
            echo '<h2>' . esc_html__('Bonus actifs', 'poke-hub') . '</h2>';
            echo '<div class="pokehub-event-bonus-list">';
            
            foreach ($bonuses as $bonus) {
                // Récupérer les infos du bonus depuis votre table de bonus
                // (si vous avez un module bonus actif)
                
                echo '<div class="pokehub-event-bonus-item">';
                
                if (!empty($bonus['description'])) {
                    echo wp_kses_post($bonus['description']);
                }
                
                echo '</div>';
            }
            
            echo '</div>';
            echo '</div>';
        }
    }
}, 20);

/**
 * Exemple : Afficher le type d'événement avec sa couleur
 */
add_action('pokehub_special_event_content', function($event) {
    
    if (!empty($event->event_type)) {
        // Récupérer les infos du type d'événement
        if (function_exists('poke_hub_events_get_event_type_by_slug')) {
            $event_type = poke_hub_events_get_event_type_by_slug($event->event_type);
            
            if ($event_type) {
                $color = !empty($event_type->event_type_color) ? $event_type->event_type_color : '#0073aa';
                $name = !empty($event_type->name) ? $event_type->name : $event->event_type;
                
                echo '<div class="pokehub-event-type-wrapper" style="margin: 1em 0;">';
                echo '<span class="pokehub-event-type-badge" style="background-color: ' . esc_attr($color) . ';">';
                echo esc_html($name);
                echo '</span>';
                echo '</div>';
            }
        }
    }
}, 5); // Priorité 5 pour l'afficher en premier

/**
 * Exemple : Afficher l'image de l'événement
 */
add_action('pokehub_special_event_content', function($event) {
    
    $image_url = '';
    
    // 1. Utiliser l'URL calculée automatiquement (local ou distant)
    if (!empty($event->computed_image_url)) {
        $image_url = $event->computed_image_url;
    }
    // 2. Sinon, image par défaut du type d'événement
    elseif (!empty($event->event_type)) {
        if (function_exists('poke_hub_events_get_event_type_by_slug')) {
            $event_type = poke_hub_events_get_event_type_by_slug($event->event_type);
            
            if ($event_type && !empty($event_type->default_image_url)) {
                $image_url = $event_type->default_image_url;
            } elseif ($event_type && !empty($event_type->default_image_id)) {
                if (function_exists('poke_hub_events_get_remote_attachment_url')) {
                    $image_url = poke_hub_events_get_remote_attachment_url($event_type->default_image_id);
                }
            }
        }
    }
    
    if ($image_url) {
        echo '<div class="pokehub-event-image" style="margin: 2em 0; text-align: center;">';
        echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($event->title) . '" style="max-width: 100%; height: auto; border-radius: 8px;">';
        echo '</div>';
    }
}, 3); // Priorité 3 pour l'afficher au début

