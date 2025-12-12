<?php
// File: uninstall.php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Vérifie si l'utilisateur a demandé la suppression des données
$delete_data = get_option( 'poke_hub_delete_data_on_uninstall', false );

// Si l'option n'est pas activée, on nettoie juste le minimum et on s'arrête
if ( ! $delete_data ) {
    delete_option( 'poke_hub_active_modules' );
    delete_option( 'poke_hub_delete_data_on_uninstall' );
    return;
}

global $wpdb;

/**
 * 1. Supprimer toutes les options liées au plugin
 *    Tout ce qui commence par "poke_hub_"
 */
$option_like = $wpdb->esc_like( 'poke_hub_' ) . '%';
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $option_like
    )
);

/**
 * 2. Supprimer les metas utilisateurs liées au plugin
 *    (meta_key commençant par "poke_hub_")
 */
$usermeta_like = $wpdb->esc_like( 'poke_hub_' ) . '%';
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $usermeta_like
    )
);

/**
 * 3. Supprimer les tables locales du plugin
 *
 * On NE SUPPRIME PAS les tables distantes (JV Actu) :
 * elles utilisent un préfixe séparé stocké dans poke_hub_events_remote_prefix.
 *
 * Ici on ne cible que les tables locales dont le nom commence par :
 *   $wpdb->prefix . 'pokehub_'
 */
$table_like = $wpdb->esc_like( $wpdb->prefix . 'pokehub_' ) . '%';
$tables     = $wpdb->get_col(
    $wpdb->prepare(
        'SHOW TABLES LIKE %s',
        $table_like
    )
);

if ( ! empty( $tables ) ) {
    foreach ( $tables as $table_name ) {
        // Sécurité basique : on s’assure que le nom commence bien par le prefix courant
        if ( strpos( $table_name, $wpdb->prefix . 'pokehub_' ) === 0 ) {
            $wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
        }
    }
}

/**
 * 4. Supprimer les posts du CPT "pokehub_bonus"
 *    (lié au sous-menu Bonus si le module est actif)
 */
if ( post_type_exists( 'pokehub_bonus' ) ) {
    $bonus_ids = get_posts(
        [
            'post_type'      => 'pokehub_bonus',
            'post_status'    => 'any',
            'numberposts'    => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ]
    );

    if ( ! empty( $bonus_ids ) ) {
        foreach ( $bonus_ids as $post_id ) {
            wp_delete_post( $post_id, true );
        }
    }
}

// (Éventuellement plus tard : suppression de taxonomies custom liées au CPT)

/**
 * 5. Optionnel : tu peux ajouter ici d’autres nettoyages spécifiques
 *    (transients, etc.) si tu en rajoutes dans le futur.
 */

