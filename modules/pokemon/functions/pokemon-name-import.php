<?php
// File: modules/pokemon/tools/pokemon-import-game-master.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Met à jour :
 *  - name_fr (colonne SQL)
 *  - extra.names.fr
 *  - extra.names.de
 *  - extra.names.ja
 * à partir du fichier name.html (table Serebii).
 *
 * IMPORTANT : ne touche PAS à l'anglais (name_en ou extra.names.en).
 *
 * @param string $html_path     Chemin absolu vers name.html
 * @param string $pokemon_table Nom complet de la table (ex: $wpdb->prefix . 'pokego_pokemon')
 *
 * @return array Résumé : ['updated' => x, 'skipped' => y]
 */
function poke_hub_pokemon_update_names_from_html( $html_path, $pokemon_table ) {
    global $wpdb;

    if ( ! file_exists( $html_path ) ) {
        wp_die( 'Fichier HTML introuvable : ' . esc_html( $html_path ) );
    }

    // 1) Lire le fichier
    $raw_html = file_get_contents( $html_path );
    if ( $raw_html === false ) {
        wp_die( 'Impossible de lire le fichier HTML.' );
    }

    // 2) Forcer l'encodage pour DOMDocument (UTF-8 -> HTML entities)
    if ( function_exists( 'mb_convert_encoding' ) ) {
        $html = mb_convert_encoding( $raw_html, 'HTML-ENTITIES', 'UTF-8' );
    } else {
        $html = $raw_html;
    }

    // --- Parse du HTML ---
    // mapping[dex_number] = ['en' => ..., 'fr' => ..., 'de' => ..., 'ja' => ..., 'ko' => ...]
    $mapping = [];

    $dom = new DOMDocument();
    libxml_use_internal_errors( true );
    $dom->loadHTML( $html );
    libxml_clear_errors();

    $xpath = new DOMXPath( $dom );

    $rows = $xpath->query(
        '//table[contains(@class,"dextable")]//tr[td[contains(@class,"fooinfo")]]'
    );

    foreach ( $rows as $tr ) {
        /** @var DOMElement $tr */
        $cells = $tr->getElementsByTagName('td');

        // On veut au minimum jusqu'à la colonne coréenne (index 7)
        if ( $cells->length < 8 ) {
            continue;
        }

        // Nat No. (#001 etc.) => index 0
        $nat_raw = trim( $cells->item(0)->textContent );
        $nat_num = (int) preg_replace( '/[^0-9]/', '', $nat_raw );
        if ( $nat_num <= 0 ) {
            continue;
        }

        /*
         * Structure réelle du tableau :
         * 0 => Nat No.
         * 1 => image / vide
         * 2 => vide
         * 3 => English
         * 4 => Japanese ("Fushigidaneフシギダネ")
         * 5 => French
         * 6 => German
         * 7 => Korean
         */

        // English (pour la vérif, PAS pour mise à jour)
        $name_en = trim( $cells->item(3)->textContent );

        // Japonais : on garde uniquement la partie non ASCII (kana/kanji)
        $name_ja_full = trim( $cells->item(4)->textContent );
        $name_ja      = preg_replace( '/^[\x00-\x7F]+/u', '', $name_ja_full );
        $name_ja      = trim( $name_ja );

        // Français
        $name_fr = trim( $cells->item(5)->textContent );
        // Allemand
        $name_de = trim( $cells->item(6)->textContent );
        // Coréen
        $name_ko = trim( $cells->item(7)->textContent );

        if ( $name_en === '' && $name_fr === '' && $name_de === '' && $name_ja === '' && $name_ko === '' ) {
            continue;
        }

        $mapping[ $nat_num ] = [
            'en' => $name_en,
            'fr' => $name_fr,
            'de' => $name_de,
            'ja' => $name_ja,
            'ko' => $name_ko,
        ];
    }

    if ( empty( $mapping ) ) {
        wp_die( 'Aucun mapping EN/FR/DE/JA/KO trouvé dans le fichier HTML.' );
    }

    // --- 2) Mise à jour de la table à partir du mapping ---
    $updated_count = 0;
    $skipped_count = 0;

    foreach ( $mapping as $dex_number => $names ) {
        $name_en = $names['en']; // juste pour comparaison
        $name_fr = $names['fr'];
        $name_de = $names['de'];
        $name_ja = $names['ja'];
        $name_ko = $names['ko'];

        // ⚠️ On ne récupère QUE les formes par défaut
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name_en, name_fr, extra
                 FROM {$pokemon_table}
                 WHERE dex_number = %d
                   AND is_default = 1",
                $dex_number
            )
        );

        if ( empty( $rows ) ) {
            $skipped_count++;
            continue;
        }

        foreach ( $rows as $row ) {
            // 🔒 Sécurité : on ne modifie QUE si le name_en en BDD
            // correspond EXACTEMENT au nom anglais de Serebii.
            if ( $name_en !== '' && $row->name_en !== $name_en ) {
                continue;
            }

            $need_update = false;
            $data        = [];
            $format      = [];

            // 2.a) Mise à jour de name_fr (colonne SQL uniquement)
            if ( $name_fr !== '' && $row->name_fr !== $name_fr ) {
                $data['name_fr'] = $name_fr;
                $format[]        = '%s';
                $need_update     = true;
            }

            // 2.b) Mise à jour de extra.names.fr / de / ja / ko
            if ( ! empty( $row->extra ) ) {
                $decoded = json_decode( $row->extra, true );

                if ( is_array( $decoded ) ) {
                    $extra_array = $decoded;

                    if ( ! isset( $extra_array['names'] ) || ! is_array( $extra_array['names'] ) ) {
                        $extra_array['names'] = [];
                    }

                    // FR
                    if ( $name_fr !== '' ) {
                        if ( ! isset( $extra_array['names']['fr'] )
                             || $extra_array['names']['fr'] !== $name_fr
                        ) {
                            $extra_array['names']['fr'] = $name_fr;
                            $need_update = true;
                        }
                    }

                    // DE
                    if ( $name_de !== '' ) {
                        if ( ! isset( $extra_array['names']['de'] )
                             || $extra_array['names']['de'] !== $name_de
                        ) {
                            $extra_array['names']['de'] = $name_de;
                            $need_update = true;
                        }
                    }

                    // JA
                    if ( $name_ja !== '' ) {
                        if ( ! isset( $extra_array['names']['ja'] )
                             || $extra_array['names']['ja'] !== $name_ja
                        ) {
                            $extra_array['names']['ja'] = $name_ja;
                            $need_update = true;
                        }
                    }

                    // KO
                    if ( $name_ko !== '' ) {
                        if ( ! isset( $extra_array['names']['ko'] )
                             || $extra_array['names']['ko'] !== $name_ko
                        ) {
                            $extra_array['names']['ko'] = $name_ko;
                            $need_update = true;
                        }
                    }

                    // On NE TOUCHE PAS à names['en'] ici.

                    if ( $need_update ) {
                        $data['extra'] = wp_json_encode(
                            $extra_array,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        );
                        $format[] = '%s';
                    }
                }
            }

            if ( $need_update && ! empty( $data ) ) {
                $where   = [ 'id' => (int) $row->id ];
                $wformat = [ '%d' ];

                $res = $wpdb->update(
                    $pokemon_table,
                    $data,
                    $where,
                    $format,
                    $wformat
                );

                if ( $res !== false ) {
                    $updated_count++;
                }
            }
        }
    }

    return [
        'updated' => $updated_count,
        'skipped' => $skipped_count,
    ];
}

/**
 * Page technique pour lancer la mise à jour des noms Pokémon.
 * URL : /wp-admin/?admin_lab_update_pokemon_names=1
 */
add_action( 'admin_init', function () {
    if ( ! is_admin() ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! isset( $_GET['admin_lab_update_pokemon_names'] ) ) {
        return;
    }

    global $wpdb;

    // Chemin vers name.html dans ton plugin
    $html_path = plugin_dir_path( __FILE__ ) . 'data/name.html';

    // Nom de ta table Pokémon
    $pokemon_table = pokehub_get_table( 'pokemon' );

    $result = poke_hub_pokemon_update_names_from_html( $html_path, $pokemon_table );

    wp_die(
        'Mise à jour terminée.<br>' .
        'Lignes mises à jour : ' . intval( $result['updated'] ) . '<br>' .
        'Dex non trouvés dans la table : ' . intval( $result['skipped'] ) . '<br>' .
        '<a href="' . esc_url( admin_url() ) . '">Retourner à l’admin</a>'
    );
} );
