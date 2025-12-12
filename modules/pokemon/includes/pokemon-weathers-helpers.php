<?php
// File: modules/pokemon/functions/pokemon-weathers-helpers.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retourne un tableau indexé par slug des weathers.
 *
 * @return array<string,array> [ slug => [ 'name_en' => ..., 'name_fr' => ..., 'image_url' => ... ] ]
 */
function poke_hub_pokemon_get_weathers_index(): array {
    if ( ! function_exists( 'pokehub_get_table' ) ) {
        return [];
    }

    global $wpdb;
    $table = pokehub_get_table( 'pokemon_weathers' );
    if ( ! $table ) {
        return [];
    }

    $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name_fr ASC" );
    if ( ! $rows ) {
        return [];
    }

    $out = [];

    foreach ( $rows as $row ) {
        $extra = [];
        if ( ! empty( $row->extra ) ) {
            $decoded = json_decode( $row->extra, true );
            if ( is_array( $decoded ) ) {
                $extra = $decoded;
            }
        }

        $out[ $row->slug ] = [
            'id'       => (int) $row->id,
            'slug'     => (string) $row->slug,
            'name_fr'  => (string) ( $row->name_fr ?? '' ),
            'name_en'  => (string) ( $row->name_en ?? '' ),
            'image_url'=> (string) ( $extra['image_url'] ?? '' ),
        ];
    }

    return $out;
}

/**
 * Helper simple pour récupérer l’URL d’icône d’un weather.
 *
 * @param string $slug
 * @return string
 */
function poke_hub_pokemon_get_weather_icon_url( string $slug ): string {
    $weathers = poke_hub_pokemon_get_weathers_index();
    return $weathers[ $slug ]['image_url'] ?? '';
}
