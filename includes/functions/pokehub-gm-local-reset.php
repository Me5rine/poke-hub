<?php
// File: includes/functions/pokehub-gm-local-reset.php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chemin absolu interne sans filtre : `wp-content/uploads/.../poke-hub/gamemaster/latest.json`
 * pour **ce** site (voir {@see wp_upload_dir()}).
 *
 * @return string Vide si le dossier d’uploads WordPress est indisponible.
 */
function poke_hub_gm_canonical_latest_json_path(): string {
	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['error'] ) ) {
		return '';
	}
	$basedir = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
	$basedir = trim( $basedir );
	if ( '' === $basedir ) {
		return '';
	}

	return wp_normalize_path( trailingslashit( $basedir ) . 'poke-hub/gamemaster/latest.json' );
}

/**
 * Chemin ABS du fichier Game Master lu/écrit par l’outil d’upload local.
 *
 * Dérivé de l’installation WordPress uniquement (ignore l’ancienne option « poke_hub_gm_local_path » en base si elle existe encore).
 *
 * Le filtre {@see 'poke_hub_gm_local_latest_json_path'} permet un autre fichier (installation avancée).
 *
 * @return string
 */
function poke_hub_gm_local_latest_json_path(): string {
	$canonical = poke_hub_gm_canonical_latest_json_path();

	/**
	 * Filtre le chemin ABS du dernier fichier Game Master téléversé.
	 *
	 * @since  (Poké HUB) valeur par défaut = chemin uploads/poke-hub/gamemaster/latest.json pour ce WP.
	 * @param string $path Chemin par défaut (peut être vide si uploads KO).
	 */
	return (string) apply_filters( 'poke_hub_gm_local_latest_json_path', $canonical );
}

/**
 * Indique si l’action destructive « vider les données importées Game Master » est autorisée.
 *
 * Par défaut : uniquement lorsque {@see wp_get_environment_type()} vaut `local`
 * (voir `WP_ENVIRONMENT_TYPE` dans wp-config.php).
 *
 * Filtre {@see 'poke_hub_allow_gm_imported_data_local_reset'} pour les cas avancés (CI, etc.).
 *
 * @return bool
 */
function poke_hub_allow_gm_imported_data_local_reset(): bool {
	if ( ! function_exists( 'wp_get_environment_type' ) ) {
		return false;
	}
	$default = wp_get_environment_type() === 'local';

	return (bool) apply_filters( 'poke_hub_allow_gm_imported_data_local_reset', $default );
}

/**
 * Vide les tables Pokédex alimentées par l’import Game Master (et PASS types Bulbapedia).
 *
 * Réservé à l’environnement local — voir {@see poke_hub_allow_gm_imported_data_local_reset()}.
 *
 * @return true|\WP_Error
 */
function poke_hub_truncate_gm_imported_pokemon_tables() {
	if ( ! poke_hub_allow_gm_imported_data_local_reset() ) {
		return new \WP_Error(
			'poke_hub_gm_reset_forbidden',
			__( 'This action is only allowed in a local environment.', 'poke-hub' )
		);
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return new \WP_Error(
			'poke_hub_gm_reset_cap',
			__( 'You do not have permission to run this action.', 'poke-hub' )
		);
	}

	if ( ! function_exists( 'pokehub_get_table' ) || ! function_exists( 'pokehub_table_exists' ) ) {
		return new \WP_Error(
			'poke_hub_gm_reset_helpers',
			__( 'Required database helpers are missing.', 'poke-hub' )
		);
	}

	global $wpdb;

	// Ordre : tables de liaison / dépendantes d’abord, puis entités.
	$table_keys = [
		'pokemon_attack_links',
		'pokemon_type_links',
		'pokemon_evolutions',
		'pokemon_pokemon_events',
		'pokemon_form_variant_events',
		'pokemon_background_pokemon_links',
		'pokemon_biome_pokemon_links',
		'pokemon',
		'pokemon_form_variants',
		'attack_type_links',
		'attack_stats',
		'attacks',
		'pokemon_type_weakness_links',
		'pokemon_type_resistance_links',
		'pokemon_types',
		'generation_regions',
		'generations',
	];

	foreach ( $table_keys as $key ) {
		$t = pokehub_get_table( $key );
		if ( $t === '' ) {
			continue;
		}
		if ( ! pokehub_table_exists( $t ) ) {
			continue;
		}
		// Noms de tables issus uniquement de pokehub_get_table() : caractères [A-Za-z0-9_].
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $t ) ) {
			continue;
		}
		$wpdb->query( 'TRUNCATE TABLE `' . $t . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// État d’import batch : éviter un import « bloqué » ou un résumé trompeur après reset.
	update_option(
		'poke_hub_gm_import_status',
		[
			'state'   => 'idle',
			'message' => '',
		],
		false
	);
	delete_option( 'poke_hub_gm_batch_state' );
	update_option( 'poke_hub_gm_last_summary', [], false );
	update_option( 'poke_hub_gm_last_run', '', false );
	update_option( 'poke_hub_gm_last_mtime', 0, false );

	return true;
}
