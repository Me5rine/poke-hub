<?php
// modules/collections/functions/collections-pogo-keywords.php — pont vers le catalogue BDD des filtres GO.

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Mots-clés pour wp_localize : uniquement la table `go_search_filters` (voir `poke_hub_go_search_filters_get_flat_for_collections()`).
 * Même résolution de préfixe que les tables Collections : scope **local** (`pokehub_get_table`).
 *
 * @return array<string, string>
 */
function poke_hub_collections_get_pogo_search_keywords(): array {
    if (! function_exists('poke_hub_go_search_filters_get_flat_for_collections')) {
        return [];
    }

    return poke_hub_go_search_filters_get_flat_for_collections();
}
