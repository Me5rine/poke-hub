<?php
// File: includes/functions/pokehub-pokekalos-release-parser.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parse le bloc "Notes supplémentaires" (div#notes) d'une fiche Pokédex Pokekalos
 * et extrait les dates de sortie (normal, shiny, shadow, dynamax, gigantamax).
 *
 * Chaque note est dans un <div class="ui blue message"><p>...<strong>DATE + contexte</strong>...</p></div>
 *
 * @param string $html Contenu HTML de la page (ou du bloc #notes).
 * @return array Clés : normal, shiny, shadow, dynamax, gigantamax. Valeurs : chaîne (date + contexte) ou ''.
 */
function poke_hub_parse_pokekalos_notes_release(string $html): array {
    $release = [
        'normal'     => '',
        'shiny'      => '',
        'shadow'     => '',
        'dynamax'    => '',
        'gigantamax' => '',
    ];

    // Chaque message : <div class="ui blue message"><p>...<strong>...</strong>...</p></div>
    // (On parse tout le HTML ; les notes sont en général les seules "ui blue message" avec ces formulations.)
    if (!preg_match_all('#<div\s+class="ui blue message"[^>]*>\s*<p>(.*?)</p>\s*</div>#is', $html, $messages, PREG_SET_ORDER)) {
        return $release;
    }

    foreach ($messages as $msg) {
        $content = $msg[1];
        // Contenu du <strong> (date + contexte éventuel)
        $strong_content = '';
        if (preg_match('#<strong>([^<]*)</strong>#', $content, $strong)) {
            $strong_content = trim(html_entity_decode($strong[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($strong_content === '') {
            continue;
        }

        // Associer au type de sortie selon le texte du paragraphe
        if (strpos($content, 'est disponible dans Pokémon GO depuis le') !== false) {
            $release['normal'] = $strong_content;
        } elseif (strpos($content, 'est disponible dans sa forme chromatique depuis le') !== false) {
            $release['shiny'] = $strong_content;
        } elseif (strpos($content, 'est disponible dans sa forme obscure et purifiée depuis le') !== false) {
            $release['shadow'] = $strong_content;
        } elseif (strpos($content, 'peut apparaître dans sa forme Dynamax depuis le') !== false) {
            $release['dynamax'] = $strong_content;
        } elseif (strpos($content, 'peut apparaître dans sa forme Gigamax depuis le') !== false
            || strpos($content, 'peut apparaître dans sa forme Gigantamax depuis le') !== false) {
            $release['gigantamax'] = $strong_content;
        }
    }

    return $release;
}
