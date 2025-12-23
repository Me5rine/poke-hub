<?php
/**
 * Script de test pour le routing des événements spéciaux
 * 
 * À exécuter UNE SEULE FOIS après l'installation pour tester le système.
 * 
 * Pour utiliser ce script :
 * 1. Accédez à : wp-admin/admin.php?page=poke-hub-events-test
 * 2. Ou exécutez directement ce fichier via WP-CLI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Page de test du routing (admin uniquement)
 */
function pokehub_events_routing_test_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Test du routing des événements spéciaux', 'poke-hub'); ?></h1>
        
        <div class="card">
            <h2><?php _e('Vérification du système', 'poke-hub'); ?></h2>
            
            <?php
            global $wpdb;
            $table = pokehub_get_table('special_events');
            
            // Vérifier si la table existe
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            
            if (!$table_exists) {
                echo '<p style="color: red;">❌ ' . __('La table des événements spéciaux n\'existe pas.', 'poke-hub') . '</p>';
                return;
            }
            
            echo '<p style="color: green;">✅ ' . __('La table des événements spéciaux existe.', 'poke-hub') . '</p>';
            
            // Récupérer les événements
            $events = $wpdb->get_results("SELECT id, slug, title FROM {$table} LIMIT 10");
            
            if (empty($events)) {
                echo '<p style="color: orange;">⚠️ ' . __('Aucun événement spécial trouvé. Créez-en un d\'abord.', 'poke-hub') . '</p>';
            } else {
                echo '<p style="color: green;">✅ ' . sprintf(__('%d événement(s) trouvé(s).', 'poke-hub'), count($events)) . '</p>';
                
                echo '<h3>' . __('URLs des événements', 'poke-hub') . '</h3>';
                echo '<ul>';
                foreach ($events as $event) {
                    $url = pokehub_get_special_event_url($event->slug);
                    echo '<li>';
                    echo '<strong>' . esc_html($event->title) . '</strong><br>';
                    echo '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a>';
                    echo '</li>';
                }
                echo '</ul>';
            }
            
            // Vérifier les rewrite rules
            echo '<h3>' . __('Rewrite rules', 'poke-hub') . '</h3>';
            global $wp_rewrite;
            $rules = get_option('rewrite_rules');
            $found = false;
            
            if (is_array($rules)) {
                foreach ($rules as $pattern => $replacement) {
                    if (strpos($pattern, 'pokemon-go/events') !== false) {
                        echo '<p style="color: green;">✅ ' . __('Rewrite rule trouvée :', 'poke-hub') . ' <code>' . esc_html($pattern) . '</code></p>';
                        $found = true;
                        break;
                    }
                }
            }
            
            if (!$found) {
                echo '<p style="color: orange;">⚠️ ' . __('Rewrite rule non trouvée.', 'poke-hub') . '</p>';
                echo '<form method="post" action="">';
                echo '<input type="hidden" name="flush_rewrite" value="1">';
                wp_nonce_field('flush_rewrite', 'flush_rewrite_nonce');
                echo '<button type="submit" class="button button-primary">' . __('Flush les rewrite rules', 'poke-hub') . '</button>';
                echo '</form>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2><?php _e('Créer un événement de test', 'poke-hub'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('create_test_event', 'test_event_nonce'); ?>
                <input type="hidden" name="create_test_event" value="1">
                <p>
                    <button type="submit" class="button button-primary">
                        <?php _e('Créer un événement de test', 'poke-hub'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Traitement des actions de la page de test
 */
add_action('admin_init', function() {
    // Flush rewrite rules
    if (isset($_POST['flush_rewrite']) && check_admin_referer('flush_rewrite', 'flush_rewrite_nonce')) {
        flush_rewrite_rules();
        wp_redirect(add_query_arg(['page' => 'poke-hub-events-test', 'flushed' => '1'], admin_url('admin.php')));
        exit;
    }
    
    // Créer un événement de test
    if (isset($_POST['create_test_event']) && check_admin_referer('create_test_event', 'test_event_nonce')) {
        global $wpdb;
        
        $table = pokehub_get_table('special_events');
        $now = time();
        $in_week = $now + (7 * 24 * 60 * 60);
        
        $wpdb->insert(
            $table,
            [
                'slug'        => 'evenement-test-' . time(),
                'title'       => 'Événement de test',
                'description' => 'Ceci est un événement de test créé automatiquement.',
                'event_type'  => 'spotlight-hour', // Ajustez selon vos types
                'start_ts'    => $now,
                'end_ts'      => $in_week,
                'mode'        => 'local',
            ],
            [
                '%s', '%s', '%s', '%s', '%d', '%d', '%s'
            ]
        );
        
        wp_redirect(add_query_arg(['page' => 'poke-hub-events-test', 'created' => '1'], admin_url('admin.php')));
        exit;
    }
});

// Enregistrer la page de test (temporaire - commentez après utilisation)
/*
add_action('admin_menu', function() {
    add_submenu_page(
        'poke-hub',
        __('Test Routing', 'poke-hub'),
        __('Test Routing', 'poke-hub'),
        'manage_options',
        'poke-hub-events-test',
        'pokehub_events_routing_test_page'
    );
});
*/








