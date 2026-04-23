<?php
// modules/collections/functions/collections-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration : ajoute la colonne share_token si absente et remplit les valeurs.
 */
function poke_hub_collections_maybe_add_share_token_column() {
    if (get_option('poke_hub_collections_share_token_migrated')) {
        return;
    }
    global $wpdb;
    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return;
    }
    $col = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'share_token'",
        DB_NAME,
        $collections_table
    ));
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$collections_table} ADD COLUMN share_token VARCHAR(20) NULL DEFAULT NULL AFTER slug");
        $wpdb->query("ALTER TABLE {$collections_table} ADD UNIQUE KEY share_token (share_token)");
    }
    $rows = $wpdb->get_results("SELECT id FROM {$collections_table} WHERE share_token IS NULL OR share_token = ''", ARRAY_A);
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $token = poke_hub_collections_generate_share_token();
            $wpdb->update($collections_table, ['share_token' => $token], ['id' => $row['id']], ['%s'], ['%d']);
        }
    }
    update_option('poke_hub_collections_share_token_migrated', 1);
    flush_rewrite_rules(false);
}

/**
 * Migration : ajoute la colonne anonymous_ip si absente (collections créées sans compte).
 */
function poke_hub_collections_maybe_add_anonymous_ip_column() {
    if (get_option('poke_hub_collections_anonymous_ip_migrated')) {
        return;
    }
    global $wpdb;
    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return;
    }
    $col = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'anonymous_ip'",
        DB_NAME,
        $collections_table
    ));
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$collections_table} ADD COLUMN anonymous_ip VARCHAR(45) NULL DEFAULT NULL AFTER share_token");
        $wpdb->query("ALTER TABLE {$collections_table} ADD KEY anonymous_ip (anonymous_ip)");
    }
    update_option('poke_hub_collections_anonymous_ip_migrated', 1);
}

/**
 * Génère un jeton aléatoire unique pour le partage (alphanumérique, 14 caractères).
 *
 * @return string
 */
function poke_hub_collections_generate_share_token(): string {
    global $wpdb;
    $collections_table = pokehub_get_table('collections');
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max_attempts = 10;
    for ($i = 0; $i < $max_attempts; $i++) {
        $token = '';
        for ($j = 0; $j < 14; $j++) {
            $token .= $chars[random_int(0, strlen($chars) - 1)];
        }
        if (!$collections_table) {
            return $token;
        }
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$collections_table} WHERE share_token = %s",
            $token
        ));
        if (!$exists) {
            return $token;
        }
    }
    return $chars[random_int(0, strlen($chars) - 1)] . wp_generate_password(13, true, true);
}

/**
 * Catégories de collection disponibles (slug => label)
 *
 * @return array<string, string>
 */
function poke_hub_collections_get_categories(): array {
    return [
        'perfect_4'       => __('Pokémon 4* (perfect)', 'poke-hub'),
        'shiny'           => __('Shiny', 'poke-hub'),
        'costume'         => __('Costumed Pokémon', 'poke-hub'),
        'costume_shiny'   => __('Shiny costumed', 'poke-hub'),
        'background'      => __('Pokémon with backgrounds (all)', 'poke-hub'),
        'background_special' => __('Backgrounds: special', 'poke-hub'),
        'background_places'  => __('Backgrounds: locations', 'poke-hub'),
        'background_shiny'=> __('Shiny backgrounds (all)', 'poke-hub'),
        'background_shiny_special' => __('Shiny backgrounds: special', 'poke-hub'),
        'background_shiny_places'  => __('Shiny backgrounds: locations', 'poke-hub'),
        'lucky'           => __('Lucky', 'poke-hub'),
        'shadow'          => __('Shadow', 'poke-hub'),
        'purified'        => __('Purified', 'poke-hub'),
        'gigantamax'     => __('Gigantamax', 'poke-hub'),
        'dynamax'        => __('Dynamax', 'poke-hub'),
        'custom'         => __('Custom list', 'poke-hub'),
    ];
}

/**
 * Catégories « spécifiques » : la collection affiche uniquement ce type (ex. Gigantamax, Dynamax).
 * Pour ces catégories on ne propose pas les options « afficher Méga / Gigantamax / Dynamax / costumes ».
 *
 * @return string[]
 */
function poke_hub_collections_get_specific_categories(): array {
    return [
        'gigantamax',
        'dynamax',
        'costume',
        'costume_shiny',
        'shadow',
        'purified',
        'background',
        'background_shiny',
        'background_special',
        'background_shiny_special',
        'background_places',
        'background_shiny_places',
    ];
}

/**
 * Indique si la catégorie est « spécifique » (liste = uniquement ce type, pas d’options d’ajout Méga/Giga/Dynamax/costumes).
 *
 * @param string $category Slug de catégorie
 * @return bool
 */
function poke_hub_collections_category_is_specific(string $category): bool {
    return in_array($category, poke_hub_collections_get_specific_categories(), true);
}

/**
 * Retourne l'URL de l'image du premier fond lié à un Pokémon (pour affichage fond + sprite).
 *
 * @param int  $pokemon_id          ID du Pokémon
 * @param bool $only_shiny_active  Si vrai, ne prend que les fonds où le Pokémon n'est PAS shiny lock
 * @return string URL du fond ou chaîne vide
 */
function poke_hub_collections_get_background_image_url_for_pokemon(int $pokemon_id, bool $only_shiny_active = false): string {
    global $wpdb;
    $links_table   = pokehub_get_table('pokemon_background_pokemon_links');
    $backgrounds_table = pokehub_get_table('pokemon_backgrounds');
    if (!$links_table || !$backgrounds_table) {
        return '';
    }

    $lock_sql = $only_shiny_active ? ' AND l.is_shiny_locked = 0' : '';
    $url = $wpdb->get_var($wpdb->prepare(
        "SELECT b.image_url FROM {$links_table} l
         INNER JOIN {$backgrounds_table} b ON l.background_id = b.id
         WHERE l.pokemon_id = %d{$lock_sql} AND TRIM(COALESCE(b.image_url, '')) != ''
         ORDER BY b.id ASC LIMIT 1",
        $pokemon_id
    ));
    return is_string($url) ? trim($url) : '';
}

/**
 * Options par défaut d'une collection
 *
 * @return array
 */
function poke_hub_collections_default_options(): array {
    return [
        'include_national_dex'    => true,
        'include_gender'         => true,
        'include_forms'          => true,
        'include_costumes'       => true,
        'include_mega'           => true,
        'include_gigantamax'     => true,
        'include_dynamax'        => true,
        'include_special_attacks'=> false,
        'one_per_species'        => false,
        'group_by_generation'   => true,
        'generations_collapsed'  => false,
        'display_mode'          => 'tiles',
        'public'                => false,
        'card_background_image_url' => '',
    ];
}

/**
 * URL d'image de fond pour la carte d'une collection (liste des collections).
 * Priorité : 1) image personnalisée (options.card_background_image_url), 2) filtre par catégorie.
 *
 * @param array $collection Ligne collection avec au minimum 'category' et optionnellement 'options' (array)
 * @return string URL à utiliser pour le background de la carte, ou chaîne vide
 */
function poke_hub_collections_get_card_background_image_url(array $collection): string {
    $options = isset($collection['options']) && is_array($collection['options']) ? $collection['options'] : [];
    $custom  = isset($options['card_background_image_url']) ? trim((string) $options['card_background_image_url']) : '';
    if ($custom !== '') {
        return esc_url($custom);
    }
    $category = isset($collection['category']) ? $collection['category'] : 'custom';
    return (string) apply_filters('poke_hub_collections_card_background_image_url', '', $category);
}

/**
 * Récupère le pool de Pokémon (IDs) pour une collection selon sa catégorie et options.
 *
 * @param string $category Slug de catégorie
 * @param array  $options  Options de composition (include_*, display_mode, etc.)
 * @return array Liste de tableaux avec au minimum 'id', 'dex_number', 'name_fr', 'name_en', 'form_variant_id', 'slug'
 */
function poke_hub_collections_get_pool(string $category, array $options = []): array {
    global $wpdb;

    $pokemon_table       = pokehub_get_table('pokemon');
    $form_variants_table = pokehub_get_table('pokemon_form_variants');
    $generations_table   = pokehub_get_table('generations');

    if (empty($pokemon_table) || empty($form_variants_table)) {
        return [];
    }

    $opts = array_merge(poke_hub_collections_default_options(), $options);
    if (array_key_exists('exclude_mega', $options) && !array_key_exists('include_mega', $options)) {
        $opts['include_mega'] = empty($options['exclude_mega']);
    }
    $where = ['1 = 1'];

    // Filtre par catégorie
    switch ($category) {
        case 'costume':
        case 'costume_shiny':
            $where[] = "(LOWER(TRIM(COALESCE(fv.category, ''))) = 'costume' OR (p.extra IS NOT NULL AND (p.extra LIKE '%\"is_event_costumed\":true%' OR p.extra LIKE '%\"is_event_costumed\": true%')))";
            break;
        case 'shadow':
            $where[] = 'p.has_shadow = 1';
            break;
        case 'purified':
            $where[] = 'p.has_purified = 1';
            break;
        case 'gigantamax':
            // Fiches forme Gigamax OU fiche (souvent forme par défaut) dont la date est dans extra → release.gigantamax
            // (certaines bases n’ont pas de ligne « variante gigantamax » distincte, seulement le champ extra).
            $where[] = "(
                fv.category = 'gigantamax'
                OR fv.form_slug LIKE '%gigantamax%'
                OR (
                    p.extra IS NOT NULL
                    AND p.extra != ''
                    AND TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p.extra, '$.release.gigantamax')), '')) != ''
                )
            )";
            break;
        case 'dynamax':
            $where[] = "(fv.category = 'dynamax' OR fv.form_slug LIKE '%dynamax%' OR fv.form_slug = 'normal')";
            break;
        case 'background':
            $links_table = pokehub_get_table('pokemon_background_pokemon_links');
            if ($links_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l WHERE l.pokemon_id = p.id)";
            }
            break;
        case 'background_shiny':
            $links_table = pokehub_get_table('pokemon_background_pokemon_links');
            if ($links_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l WHERE l.pokemon_id = p.id AND (l.is_shiny_locked = 0 OR l.is_shiny_locked IS NULL))";
            }
            break;
        case 'background_special':
            $links_table   = pokehub_get_table('pokemon_background_pokemon_links');
            $bg_table     = pokehub_get_table('pokemon_backgrounds');
            if ($links_table && $bg_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l INNER JOIN {$bg_table} b ON l.background_id = b.id WHERE l.pokemon_id = p.id AND LOWER(TRIM(COALESCE(b.background_type, ''))) = 'special')";
            }
            break;
        case 'background_shiny_special': {
            $links_table   = pokehub_get_table('pokemon_background_pokemon_links');
            $bg_table     = pokehub_get_table('pokemon_backgrounds');
            if ($links_table && $bg_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l INNER JOIN {$bg_table} b ON l.background_id = b.id WHERE l.pokemon_id = p.id AND l.is_shiny_locked = 0 AND LOWER(TRIM(COALESCE(b.background_type, ''))) = 'special')";
            }
            break;
        }
        case 'background_places':
            $links_table   = pokehub_get_table('pokemon_background_pokemon_links');
            $bg_table     = pokehub_get_table('pokemon_backgrounds');
            if ($links_table && $bg_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l INNER JOIN {$bg_table} b ON l.background_id = b.id WHERE l.pokemon_id = p.id AND LOWER(TRIM(COALESCE(b.background_type, ''))) IN ('location', 'lieu', 'place'))";
            }
            break;
        case 'background_shiny_places': {
            $links_table   = pokehub_get_table('pokemon_background_pokemon_links');
            $bg_table     = pokehub_get_table('pokemon_backgrounds');
            if ($links_table && $bg_table) {
                $where[] = "EXISTS (SELECT 1 FROM {$links_table} l INNER JOIN {$bg_table} b ON l.background_id = b.id WHERE l.pokemon_id = p.id AND l.is_shiny_locked = 0 AND LOWER(TRIM(COALESCE(b.background_type, ''))) IN ('location', 'lieu', 'place'))";
            }
            break;
        }
        case 'perfect_4':
        case 'shiny':
        case 'lucky':
        case 'custom':
        default:
            break;
    }

    // Options « en plus » (Méga, Gigantamax, Dynamax, costumes) : uniquement pour les catégories non spécifiques
    $is_specific = poke_hub_collections_category_is_specific($category);
    if (!$is_specific) {
        if (!empty($opts['include_costumes'])) {
            // rien
        } elseif (empty($opts['include_costumes'])) {
            $where[] = "(fv.category IS NULL OR fv.category = '' OR fv.category = 'normal')";
        }
        if (empty($opts['include_mega'])) {
            $where[] = "(fv.category IS NULL OR fv.category = '' OR fv.category != 'mega')";
        }
        if (empty($opts['include_gigantamax'])) {
            $where[] = "(fv.category IS NULL OR fv.category = '' OR (fv.category != 'gigantamax' AND fv.form_slug NOT LIKE '%gigantamax%'))";
        }
        if (empty($opts['include_dynamax'])) {
            $where[] = "(fv.category IS NULL OR fv.category = '' OR (fv.category != 'dynamax' AND fv.form_slug NOT LIKE '%dynamax%'))";
        }
    }

    // Une seule entrée par espèce (forme par défaut)
    if (!empty($opts['one_per_species'])) {
        $where[] = '(p.is_default = 1 OR p.form_variant_id = 0)';
    }

    $where_sql = implode(' AND ', $where);

    // Contexte de date de sortie selon la catégorie (uniquement les Pokémon sortis dans GO)
    $release_context = 'normal';
    switch ($category) {
        case 'shiny':
        case 'costume_shiny':
        case 'background_shiny':
        case 'background_shiny_special':
        case 'background_shiny_places':
            $release_context = 'shiny';
            break;
        case 'shadow':
        case 'purified':
            $release_context = 'shadow';
            break;
        case 'gigantamax':
            $release_context = 'gigantamax';
            break;
        case 'dynamax':
            $release_context = 'dynamax';
            break;
        case 'perfect_4':
        case 'costume':
        case 'background':
        case 'background_special':
        case 'background_places':
        case 'lucky':
        case 'custom':
        default:
            $release_context = 'normal';
            break;
    }

    $gen_select = $generations_table
        ? "p.generation_id, COALESCE(g.name_fr, g.name_en, g.slug, '') AS generation_name, COALESCE(g.generation_number, 0) AS generation_number"
        : "p.generation_id, '' AS generation_name, 0 AS generation_number";
    $gen_join   = $generations_table
        ? "LEFT JOIN {$generations_table} g ON p.generation_id = g.id"
        : '';
    $order_gen  = $generations_table
        ? "COALESCE(g.generation_number, 999) ASC,"
        : "p.generation_id ASC,";

    $sql = "SELECT p.id, p.dex_number, p.name_fr, p.name_en, p.slug, p.form_variant_id, p.extra,
                   COALESCE(fv.label, fv.form_slug, '') AS form_label,
                   COALESCE(fv.category, 'normal') AS form_category,
                   {$gen_select}
            FROM {$pokemon_table} p
            LEFT JOIN {$form_variants_table} fv ON p.form_variant_id = fv.id
            {$gen_join}
            WHERE {$where_sql}
            ORDER BY {$order_gen} p.dex_number ASC, p.slug ASC";

    $results = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($results)) {
        return [];
    }

    // Ne garder que les Pokémon ayant une sortie GO pour ce contexte.
    //
    // Note shiny (règle GO récente) :
    // une évolution peut être "shiny trouvable directement" si le Pokémon de base est shiny.
    // On ne peut donc pas se limiter à extra->release->shiny sur chaque évolution.
    if (function_exists('poke_hub_pokemon_is_released_in_go')) {
        $filtered = [];
        foreach ($results as $row) {
            $pokemon_id = (int) ($row['id'] ?? 0);

            // Cas spécial shiny : propager la logique via pokehub_pokemon_can_be_shiny().
            if ($release_context === 'shiny' && function_exists('pokehub_pokemon_can_be_shiny')) {
                if (pokehub_pokemon_can_be_shiny($pokemon_id)) {
                    $row = poke_hub_collections_maybe_mark_gigantamax_synthetic_base_row($row, $category, $opts);
                    unset($row['extra']);
                    $filtered[] = $row;
                }
                continue;
            }

            if (poke_hub_pokemon_is_released_in_go($pokemon_id, $release_context)) {
                $row = poke_hub_collections_maybe_mark_gigantamax_synthetic_base_row($row, $category, $opts);
                unset($row['extra']);
                $filtered[] = $row;
            }
        }
        $results = $filtered;
    } else {
        foreach ($results as &$row) {
            unset($row['extra']);
        }
        unset($row);
    }

    if (!poke_hub_collections_category_is_specific($category)
        && !empty($opts['include_gigantamax'])
        && $category !== 'gigantamax'
        && $results !== []) {
        $results = poke_hub_collections_apply_marked_synthetic_gigantamax($results);
    }

    if ($category === 'gigantamax' && $results !== []) {
        $results = poke_hub_collections_merge_gigantamax_synthetic_pool($results);
    }

    return poke_hub_collections_sort_pool_display($results);
}

/**
 * true si extra contient une date / valeur pour release.gigantamax (même règle que le WHERE SQL de la catégorie dédiée).
 */
function poke_hub_collections_row_has_gigantamax_release_in_extra(array $row): bool {
    $extra = $row['extra'] ?? null;
    if (!is_string($extra) || $extra === '') {
        return false;
    }
    $data = json_decode($extra, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_array($data)) {
        return false;
    }
    $g = $data['release']['gigantamax'] ?? null;
    if (is_string($g)) {
        return trim($g) !== '';
    }
    if (is_array($g)) {
        return $g !== [];
    }

    return (bool) $g;
}

/**
 * Avant supprimer le JSON extra : marque les fiches de base (sans variante G-Max en table) éligibles,
 * pour {@see poke_hub_collections_apply_marked_synthetic_gigantamax()} (collections custom / shiny + « Afficher Gigantamax »).
 */
function poke_hub_collections_maybe_mark_gigantamax_synthetic_base_row(array $row, string $category, array $opts): array {
    if (poke_hub_collections_category_is_specific($category)
        || empty($opts['include_gigantamax'])
        || $category === 'gigantamax') {
        return $row;
    }
    if (poke_hub_collections_gigantamax_row_is_real_form($row)) {
        return $row;
    }
    if (!poke_hub_collections_row_has_gigantamax_release_in_extra($row)) {
        return $row;
    }
    $pokemon_id = (int) ($row['id'] ?? 0);
    if ($pokemon_id <= 0) {
        return $row;
    }
    if (function_exists('poke_hub_pokemon_is_released_in_go') && !poke_hub_pokemon_is_released_in_go($pokemon_id, 'gigantamax')) {
        return $row;
    }
    $row['__pokehub_c_gigantamax_src'] = 1;

    return $row;
}

/**
 * Remplace les fiches de base marquées par des entrées G-Max synthétiques (id 2100…),
 * en évitant le doublon si une vraie forme G-Max est déjà dans le pool pour le même n° de dex.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function poke_hub_collections_apply_marked_synthetic_gigantamax(array $rows): array {
    if ($rows === []) {
        return [];
    }
    $real_dex = [];
    foreach ($rows as $r) {
        if (!poke_hub_collections_gigantamax_row_is_real_form($r)) {
            continue;
        }
        $dex = isset($r['dex_number']) ? (int) $r['dex_number'] : 0;
        if ($dex > 0) {
            $real_dex[$dex] = true;
        }
    }
    $out       = [];
    $seen_base = [];
    foreach ($rows as $row) {
        $mark = !empty($row['__pokehub_c_gigantamax_src']);
        unset($row['__pokehub_c_gigantamax_src']);
        if (poke_hub_collections_gigantamax_row_is_real_form($row)) {
            $out[] = $row;
            continue;
        }
        if (!$mark) {
            $out[] = $row;
            continue;
        }
        $base_id = (int) ($row['id'] ?? 0);
        if ($base_id > 0 && isset($seen_base[$base_id])) {
            continue;
        }
        if ($base_id > 0) {
            $seen_base[$base_id] = true;
        }
        $dex = isset($row['dex_number']) ? (int) $row['dex_number'] : 0;
        if ($dex > 0 && !empty($real_dex[$dex])) {
            continue;
        }
        $out[] = poke_hub_collections_gigantamax_build_synthetic_from_base_row($row);
    }

    return $out;
}

/**
 * ID d’enregistrement en collection (collection_items) pour un Gigamax « virtuel » dérivé d’une fiche de base
 * (pas de variante gigantamax en table pokemon).
 */
function poke_hub_collections_gigantamax_synthetic_pokemon_id(int $base_pokemon_id): int {
    $offset = 2100000000; // Réservé : hors plage des id auto-incrémentés habituels
    return $offset + $base_pokemon_id;
}

/**
 * @param int $pokemon_id ID possibly returned by {@see poke_hub_collections_gigantamax_synthetic_pokemon_id()}
 */
function poke_hub_collections_gigantamax_is_synthetic_pokemon_id(int $pokemon_id): bool {
    return $pokemon_id >= 2100000000 && $pokemon_id < 2200000000;
}

/**
 * Ligne de pool = vraie forme Gigantamax en base (variante) ?
 */
function poke_hub_collections_gigantamax_row_is_real_form(array $row): bool {
    $cat = strtolower(trim((string) ($row['form_category'] ?? '')));
    if ($cat === 'gigantamax') {
        return true;
    }
    $slug = strtolower((string) ($row['slug'] ?? ''));
    if ($slug !== '' && strpos($slug, 'gigantamax') !== false) {
        return true;
    }
    $label = strtolower((string) ($row['form_label'] ?? ''));
    return $label !== '' && strpos($label, 'gigantamax') !== false;
}

/**
 * Construit une entrée de pool côté Gigamax quand seule la fiche d’évolution (ex. Florizarre) existe avec release.gigantamax dans extra.
 */
function poke_hub_collections_gigantamax_build_synthetic_from_base_row(array $base): array {
    $base_id = (int) ($base['id'] ?? 0);
    $slug    = trim((string) ($base['slug'] ?? ''), " \t\n\r\0\x0B");
    if ($slug === '' || $slug === '0') {
        $dex = isset($base['dex_number']) ? (int) $base['dex_number'] : 0;
        if ($dex > 0) {
            $slug = sprintf('%03d', $dex);
        } else {
            $slug = 'pokemon';
        }
    }
    if (strpos($slug, 'gigantamax-') === 0) {
        $gmax_slug = $slug;
    } else {
        $gmax_slug = 'gigantamax-' . $slug;
    }
    $name_fr_base = trim((string) ($base['name_fr'] ?? ''));
    $name_en_base = trim((string) ($base['name_en'] ?? ''));
    if ($name_fr_base !== '') {
        $name_fr = $name_fr_base . ' Gigamax';
    } elseif ($name_en_base !== '') {
        $name_fr = $name_en_base . ' Gigamax';
    } else {
        $name_fr = 'Gigamax';
    }
    if ($name_en_base !== '') {
        $name_en = 'Gigantamax ' . $name_en_base;
    } else {
        $name_en = $name_fr;
    }
    $out = $base;
    unset($out['extra']);
    $out['id']                 = poke_hub_collections_gigantamax_synthetic_pokemon_id($base_id);
    $out['form_variant_id']   = 0;
    $out['slug']              = $gmax_slug;
    $out['name_fr']            = $name_fr;
    $out['name_en']            = $name_en;
    $out['form_category']      = 'gigantamax';
    $out['form_label']         = 'Gigantamax';
    $out['synthetic_gigantamax'] = true;
    $out['gigantamax_base_pokemon_id'] = $base_id;

    return (array) apply_filters('poke_hub_collections_synthetic_gigantamax_row', $out, $base);
}

/**
 * Remplace les fiches non-Gigamax (seulement extra.release.gigantamax) par une tuile « gigantamax-slug » ;
 * supprime le doublon si une vraie forme Gigamax existe déjà pour le même n° de Pokédex.
 *
 * @param array $rows Résultat filtré pour la catégorie gigantamax
 * @return array
 */
function poke_hub_collections_merge_gigantamax_synthetic_pool(array $rows): array {
    if ($rows === []) {
        return [];
    }
    $real_gigantamax_dex = [];
    foreach ($rows as $r) {
        if (!poke_hub_collections_gigantamax_row_is_real_form($r)) {
            continue;
        }
        $dex = isset($r['dex_number']) ? (int) $r['dex_number'] : 0;
        if ($dex > 0) {
            $real_gigantamax_dex[$dex] = true;
        }
    }
    $out       = [];
    $seen_base = [];
    foreach ($rows as $row) {
        if (poke_hub_collections_gigantamax_row_is_real_form($row)) {
            $out[] = $row;
            continue;
        }
        $base_id = (int) ($row['id'] ?? 0);
        if ($base_id <= 0) {
            continue;
        }
        if (isset($seen_base[$base_id])) {
            continue;
        }
        $seen_base[$base_id] = true;
        $dex = isset($row['dex_number']) ? (int) $row['dex_number'] : 0;
        if ($dex > 0 && !empty($real_gigantamax_dex[$dex])) {
            continue;
        }
        $out[] = poke_hub_collections_gigantamax_build_synthetic_from_base_row($row);
    }

    return $out;
}

/**
 * Tri d’affichage harmonisé : génération -> dex -> nom -> base/costume/méga -> forme.
 *
 * @param array $rows
 * @return array
 */
function poke_hub_collections_sort_pool_display(array $rows): array {
    usort(
        $rows,
        static function (array $a, array $b): int {
            $genA = isset($a['generation_number']) ? (int) $a['generation_number'] : 0;
            $genB = isset($b['generation_number']) ? (int) $b['generation_number'] : 0;
            if ($genA !== $genB) {
                return $genA <=> $genB;
            }

            $dexA = isset($a['dex_number']) ? (int) $a['dex_number'] : 0;
            $dexB = isset($b['dex_number']) ? (int) $b['dex_number'] : 0;
            if ($dexA !== $dexB) {
                return $dexA <=> $dexB;
            }

            $nameA = trim((string) (($a['name_fr'] ?? '') !== '' ? $a['name_fr'] : ($a['name_en'] ?? '')));
            $nameB = trim((string) (($b['name_fr'] ?? '') !== '' ? $b['name_fr'] : ($b['name_en'] ?? '')));
            $nameAN = function_exists('mb_strtolower') ? mb_strtolower($nameA, 'UTF-8') : strtolower($nameA);
            $nameBN = function_exists('mb_strtolower') ? mb_strtolower($nameB, 'UTF-8') : strtolower($nameB);
            if ($nameAN !== $nameBN) {
                return $nameAN <=> $nameBN;
            }

            $rankA = function_exists('pokehub_pokemon_select_category_rank')
                ? pokehub_pokemon_select_category_rank((string) ($a['form_category'] ?? ''))
                : 3;
            $rankB = function_exists('pokehub_pokemon_select_category_rank')
                ? pokehub_pokemon_select_category_rank((string) ($b['form_category'] ?? ''))
                : 3;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            $formA = trim((string) ($a['form_label'] ?? ''));
            $formB = trim((string) ($b['form_label'] ?? ''));
            $formAN = function_exists('mb_strtolower') ? mb_strtolower($formA, 'UTF-8') : strtolower($formA);
            $formBN = function_exists('mb_strtolower') ? mb_strtolower($formB, 'UTF-8') : strtolower($formB);
            if ($formAN !== $formBN) {
                return $formAN <=> $formBN;
            }

            $idA = isset($a['id']) ? (int) $a['id'] : 0;
            $idB = isset($b['id']) ? (int) $b['id'] : 0;
            return $idA <=> $idB;
        }
    );

    return $rows;
}

/**
 * Groupe le pool de Pokémon par génération pour l'affichage (clé = libellé génération).
 *
 * @param array $pool Liste retournée par poke_hub_collections_get_pool (avec generation_id, generation_name, generation_number)
 * @return array [ 'Génération 1' => [ ... ], 'Génération 2' => [ ... ], '' => [ ... ] ] ('' = sans génération, en dernier)
 */
function poke_hub_collections_group_pool_by_generation(array $pool): array {
    $by_gen = [];
    foreach ($pool as $p) {
        $gen_num = isset($p['generation_number']) ? (int) $p['generation_number'] : 0;
        $gen_name = isset($p['generation_name']) && (string) $p['generation_name'] !== ''
            ? $p['generation_name']
            : ($gen_num > 0 ? sprintf(__('Generation %d', 'poke-hub'), $gen_num) : '');
        $key = $gen_name !== '' ? $gen_name : "\xFF"; // sans nom en dernier après tri
        if (!isset($by_gen[$key])) {
            $by_gen[$key] = ['order' => $gen_num, 'items' => []];
        }
        $by_gen[$key]['items'][] = $p;
    }
    uasort($by_gen, function ($a, $b) {
        return $a['order'] <=> $b['order'];
    });
    $out = [];
    foreach ($by_gen as $key => $data) {
        $label = $key === "\xFF" ? '' : $key;
        $out[$label] = $data['items'];
    }
    return $out;
}

/**
 * Nombre d’entrées possédées par région / génération (mêmes clés que poke_hub_collections_group_pool_by_generation()).
 *
 * @param array $pool  Pool complet (poke_hub_collections_get_pool)
 * @param array $items  pokemon_id => status
 * @return array [ libellé_générations => [ 'owned' => int, 'total' => int ], ... ]
 */
function poke_hub_collections_get_generation_progress(array $pool, array $items): array {
    $by_gen = poke_hub_collections_group_pool_by_generation($pool);
    $out    = [];
    foreach ($by_gen as $label => $gen_pool) {
        $total = count($gen_pool);
        $owned = 0;
        foreach ($gen_pool as $p) {
            $pid = (int) ($p['id'] ?? 0);
            if (($items[$pid] ?? 'missing') === 'owned') {
                $owned++;
            }
        }
        $out[$label] = [
            'owned' => $owned,
            'total' => $total,
        ];
    }
    return $out;
}

/**
 * Remet à zéro la progression d’une collection (tous les statuts = absents côté base : lignes supprimées).
 *
 * @param int         $collection_id
 * @param int         $user_id
 * @param string|null $ip
 * @return array{ success: bool, message: string }
 */
function poke_hub_collections_reset_items(int $collection_id, int $user_id = 0, ?string $ip = null): array {
    if ($user_id > 0) {
        if (!poke_hub_collections_can_edit($collection_id, $user_id)) {
            return ['success' => false, 'message' => __('You cannot modify this collection.', 'poke-hub')];
        }
    } else {
        if ($ip === null || !poke_hub_collections_can_edit_anonymous($collection_id, $ip)) {
            return ['success' => false, 'message' => __('You cannot modify this collection.', 'poke-hub')];
        }
    }

    global $wpdb;

    $items_table = pokehub_get_table('collection_items');
    if (!$items_table) {
        return ['success' => false, 'message' => __('Technical error.', 'poke-hub')];
    }

    $r = $wpdb->delete($items_table, ['collection_id' => $collection_id], ['%d']);
    if ($r === false) {
        return ['success' => false, 'message' => __('Could not reset the collection.', 'poke-hub')];
    }

    return [
        'success' => true,
        'message' => __('Collection progress cleared.', 'poke-hub'),
    ];
}

/**
 * Retourne l’IP du client (prise en compte des proxies).
 *
 * @return string
 */
function poke_hub_collections_get_client_ip(): string {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $val = wp_unslash($_SERVER[$key]);
            if (strpos($val, ',') !== false) {
                $val = trim(explode(',', $val)[0]);
            }
            $val = preg_replace('/[^0-9a-f.:]/', '', $val);
            if ($val !== '') {
                return $val;
            }
        }
    }
    return '';
}

/**
 * Sanitize les options de collection (URLs, etc.).
 *
 * @param array $options Options brutes
 * @return array Options sanitized
 */
function poke_hub_collections_sanitize_options(array $options): array {
    if (isset($options['card_background_image_url']) && is_string($options['card_background_image_url'])) {
        $options['card_background_image_url'] = esc_url_raw(trim($options['card_background_image_url']));
    }
    return $options;
}

/**
 * Crée une collection pour un utilisateur connecté.
 *
 * @param int   $user_id User ID
 * @param array $data    name, category, options, is_public
 * @return array { success, message, collection_id?, slug?, share_token? }
 */
function poke_hub_collections_create(int $user_id, array $data): array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table || $user_id <= 0) {
        return ['success' => false, 'message' => __('Invalid request.', 'poke-hub')];
    }

    $name     = isset($data['name']) ? sanitize_text_field($data['name']) : '';
    $category = isset($data['category']) ? sanitize_key($data['category']) : 'custom';
    $slug     = sanitize_title($name ?: 'collection-' . $user_id . '-' . time());
    $options  = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
    $is_public = !empty($data['is_public']);

    $categories = array_keys(poke_hub_collections_get_categories());
    if (!in_array($category, $categories, true)) {
        $category = 'custom';
    }

    $options = array_merge(poke_hub_collections_default_options(), $options);
    $options = poke_hub_collections_sanitize_options($options);
    $options_json = wp_json_encode($options);

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$collections_table} WHERE user_id = %d AND slug = %s",
        $user_id,
        $slug
    ));

    if ($existing) {
        $slug = $slug . '-' . time();
    }

    $share_token = poke_hub_collections_generate_share_token();

    $r = $wpdb->insert(
        $collections_table,
        [
            'user_id'     => $user_id,
            'name'        => $name ?: __('My collection', 'poke-hub'),
            'slug'        => $slug,
            'share_token' => $share_token,
            'category'    => $category,
            'options'     => $options_json,
            'is_public'   => $is_public ? 1 : 0,
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%d']
    );

    if ($r === false) {
        return ['success' => false, 'message' => __('Could not create collection.', 'poke-hub')];
    }

    $collection_id = (int) $wpdb->insert_id;
    return [
        'success'        => true,
        'message'       => __('Collection created.', 'poke-hub'),
        'collection_id'  => $collection_id,
        'slug'           => $slug,
        'share_token'    => $share_token,
    ];
}

/**
 * Crée une collection anonyme (sans compte), liée à l’IP pour rattachement ultérieur.
 *
 * @param array  $data name, category, options
 * @param string $ip   IP du client
 * @return array { success, message, collection_id?, share_token? }
 */
function poke_hub_collections_create_anonymous(array $data, string $ip): array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Technical error.', 'poke-hub')];
    }

    $ip = preg_replace('/[^0-9a-f.:]/', '', $ip);
    if ($ip === '') {
        return ['success' => false, 'message' => __('Unable to identify your connection.', 'poke-hub')];
    }

    $name     = isset($data['name']) ? sanitize_text_field($data['name']) : '';
    $category = isset($data['category']) ? sanitize_key($data['category']) : 'custom';
    $options  = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];

    $categories = array_keys(poke_hub_collections_get_categories());
    if (!in_array($category, $categories, true)) {
        $category = 'custom';
    }

    $options    = array_merge(poke_hub_collections_default_options(), $options);
    $options    = poke_hub_collections_sanitize_options($options);
    $options_json = wp_json_encode($options);

    $share_token = poke_hub_collections_generate_share_token();
    $slug        = 'anon-' . time() . '-' . substr($share_token, 0, 6);

    $r = $wpdb->insert(
        $collections_table,
        [
            'user_id'      => 0,
            'name'         => $name ?: __('My collection', 'poke-hub'),
            'slug'         => $slug,
            'share_token'  => $share_token,
            'anonymous_ip' => $ip,
            'category'     => $category,
            'options'      => $options_json,
            'is_public'    => 0,
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
    );

    if ($r === false) {
        return ['success' => false, 'message' => __('Could not create collection.', 'poke-hub')];
    }

    $collection_id = (int) $wpdb->insert_id;
    return [
        'success'        => true,
        'message'        => __('Collection created.', 'poke-hub'),
        'collection_id'   => $collection_id,
        'share_token'    => $share_token,
    ];
}

/**
 * Met à jour une collection (nom, options, is_public). L'utilisateur doit en être propriétaire.
 *
 * @param int   $collection_id
 * @param int   $user_id
 * @param array $data name, options, is_public (category non modifié pour éviter incohérence avec les items)
 * @return array { success, message }
 */
function poke_hub_collections_update(int $collection_id, int $user_id, array $data): array {
    global $wpdb;

    if (!poke_hub_collections_can_edit($collection_id, $user_id)) {
        return ['success' => false, 'message' => __('You cannot edit this collection.', 'poke-hub')];
    }

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Technical error.', 'poke-hub')];
    }

    $updates = [];
    $formats = [];

    if (array_key_exists('name', $data)) {
        $name = sanitize_text_field($data['name']);
        if ($name !== '') {
            $updates['name'] = $name;
            $formats[] = '%s';
        }
    }

    if (isset($data['options']) && is_array($data['options'])) {
        $options = array_merge(poke_hub_collections_default_options(), $data['options']);
        $options = poke_hub_collections_sanitize_options($options);
        $updates['options'] = wp_json_encode($options);
        $formats[] = '%s';
    }

    if (array_key_exists('is_public', $data)) {
        $updates['is_public'] = !empty($data['is_public']) ? 1 : 0;
        $formats[] = '%d';
    }

    if (empty($updates)) {
        return ['success' => true, 'message' => __('No changes.', 'poke-hub')];
    }

    $r = $wpdb->update(
        $collections_table,
        $updates,
        ['id' => $collection_id],
        $formats,
        ['%d']
    );

    if ($r === false) {
        return ['success' => false, 'message' => __('Could not save.', 'poke-hub')];
    }

    return ['success' => true, 'message' => __('Settings saved.', 'poke-hub')];
}

/**
 * Récupère les collections d'un utilisateur.
 *
 * @param int $user_id
 * @return array
 */
function poke_hub_collections_get_by_user(int $user_id): array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table || $user_id <= 0) {
        return [];
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
         FROM {$collections_table} WHERE user_id = %d ORDER BY updated_at DESC",
        $user_id
    ), ARRAY_A);

    if (!is_array($rows)) {
        return [];
    }

    foreach ($rows as &$row) {
        if (!empty($row['options'])) {
            $row['options'] = json_decode($row['options'], true) ?: [];
        } else {
            $row['options'] = poke_hub_collections_default_options();
        }
    }
    unset($row);

    return $rows;
}

/**
 * Récupère une collection par ID ou par slug+user_id.
 *
 * @param int         $collection_id ID (prioritaire)
 * @param string|null $slug          Slug (si pas d'ID)
 * @param int|null    $user_id       User (requis si slug)
 * @return array|null
 */
function poke_hub_collections_get_one(int $collection_id = 0, ?string $slug = null, ?int $user_id = null): ?array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return null;
    }

    if ($collection_id > 0) {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
             FROM {$collections_table} WHERE id = %d",
            $collection_id
        ), ARRAY_A);
    } elseif ($slug !== null && $slug !== '' && $user_id > 0) {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
             FROM {$collections_table} WHERE user_id = %d AND slug = %s",
            $user_id,
            $slug
        ), ARRAY_A);
    } else {
        return null;
    }

    if (!is_array($row)) {
        return null;
    }

    $row['options'] = !empty($row['options']) ? (json_decode($row['options'], true) ?: []) : poke_hub_collections_default_options();
    return $row;
}

/**
 * Récupère une collection par son jeton de partage (unique, public ou propriétaire).
 *
 * @param string $token
 * @return array|null
 */
function poke_hub_collections_get_by_share_token(string $token): ?array {
    global $wpdb;

    $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
    if ($token === '') {
        return null;
    }

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return null;
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
         FROM {$collections_table} WHERE share_token = %s",
        $token
    ), ARRAY_A);

    if (!is_array($row)) {
        return null;
    }

    $row['options'] = !empty($row['options']) ? (json_decode($row['options'], true) ?: []) : poke_hub_collections_default_options();
    return $row;
}

/**
 * Récupère une collection publique par slug (sans user_id).
 *
 * @param string $slug
 * @return array|null
 */
function poke_hub_collections_get_public_by_slug(string $slug): ?array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return null;
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
         FROM {$collections_table} WHERE slug = %s AND is_public = 1",
        $slug
    ), ARRAY_A);

    if (!is_array($row)) {
        return null;
    }

    $row['options'] = !empty($row['options']) ? (json_decode($row['options'], true) ?: []) : poke_hub_collections_default_options();
    return $row;
}

/**
 * Vérifie que l'utilisateur peut modifier la collection.
 */
function poke_hub_collections_can_edit(int $collection_id, int $user_id): bool {
    $col = poke_hub_collections_get_one($collection_id);
    return $col && (int) $col['user_id'] === $user_id;
}

/**
 * Vérifie qu’une collection anonyme peut être modifiée depuis cette IP.
 */
function poke_hub_collections_can_edit_anonymous(int $collection_id, string $ip): bool {
    $col = poke_hub_collections_get_one($collection_id);
    return $col && (int) $col['user_id'] === 0 && !empty($col['anonymous_ip']) && $col['anonymous_ip'] === $ip;
}

/**
 * Récupère les collections anonymes créées depuis cette IP (pour proposition de rattachement au compte).
 *
 * @param string $ip
 * @return array
 */
function poke_hub_collections_get_anonymous_by_ip(string $ip): array {
    global $wpdb;

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table || $ip === '') {
        return [];
    }

    $ip = preg_replace('/[^0-9a-f.:]/', '', $ip);
    if ($ip === '') {
        return [];
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, name, slug, share_token, anonymous_ip, category, options, is_public, created_at, updated_at
         FROM {$collections_table} WHERE user_id = 0 AND anonymous_ip = %s ORDER BY updated_at DESC",
        $ip
    ), ARRAY_A);

    if (!is_array($rows)) {
        return [];
    }

    foreach ($rows as &$row) {
        $row['options'] = !empty($row['options']) ? (json_decode($row['options'], true) ?: []) : poke_hub_collections_default_options();
    }
    unset($row);

    return $rows;
}

/**
 * Rattache une collection anonyme au compte de l’utilisateur (même IP requise).
 *
 * @param int    $collection_id
 * @param int    $user_id
 * @param string $ip IP du client
 * @return array { success, message }
 */
function poke_hub_collections_claim(int $collection_id, int $user_id, string $ip): array {
    global $wpdb;

    $col = poke_hub_collections_get_one($collection_id);
    if (!$col) {
        return ['success' => false, 'message' => __('Collection not found.', 'poke-hub')];
    }
    if ((int) $col['user_id'] !== 0) {
        return ['success' => false, 'message' => __('This collection is already linked to an account.', 'poke-hub')];
    }
    $ip = preg_replace('/[^0-9a-f.:]/', '', $ip);
    if ($ip === '' || empty($col['anonymous_ip']) || $col['anonymous_ip'] !== $ip) {
        return ['success' => false, 'message' => __('This collection was not created from this connection.', 'poke-hub')];
    }

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Technical error.', 'poke-hub')];
    }

    $slug = sanitize_title($col['name'] ?: 'collection-' . $user_id . '-' . time());
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$collections_table} WHERE user_id = %d AND slug = %s",
        $user_id,
        $slug
    ));
    if ($existing) {
        $slug = $slug . '-' . time();
    }

    $r = $wpdb->update(
        $collections_table,
        ['user_id' => $user_id, 'slug' => $slug, 'anonymous_ip' => null],
        ['id' => $collection_id],
        ['%d', '%s', '%s'],
        ['%d']
    );

    if ($r === false) {
        return ['success' => false, 'message' => __('Could not attach.', 'poke-hub')];
    }

    return ['success' => true, 'message' => __('Collection linked to your account.', 'poke-hub')];
}

/**
 * Supprime une collection et ses items. Propriétaire ou collection anonyme (même IP).
 *
 * @param int         $collection_id
 * @param int         $user_id 0 pour anonyme
 * @param string|null $ip      requis si user_id = 0
 * @return array { success, message }
 */
function poke_hub_collections_delete(int $collection_id, int $user_id = 0, ?string $ip = null): array {
    global $wpdb;

    if ($user_id > 0) {
        if (!poke_hub_collections_can_edit($collection_id, $user_id)) {
            return ['success' => false, 'message' => __('You cannot delete this collection.', 'poke-hub')];
        }
    } else {
        if ($ip === null || !poke_hub_collections_can_edit_anonymous($collection_id, $ip)) {
            return ['success' => false, 'message' => __('You cannot delete this collection.', 'poke-hub')];
        }
    }

    $collections_table = pokehub_get_table('collections');
    $items_table       = pokehub_get_table('collection_items');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Technical error.', 'poke-hub')];
    }

    if ($items_table) {
        $wpdb->delete($items_table, ['collection_id' => $collection_id], ['%d']);
    }
    $r = $wpdb->delete($collections_table, ['id' => $collection_id], ['%d']);

    if ($r === false) {
        return ['success' => false, 'message' => __('Could not delete.', 'poke-hub')];
    }

    return ['success' => true, 'message' => __('Collection deleted.', 'poke-hub')];
}

/**
 * Récupère les items (pokemon_id => status) d'une collection.
 *
 * @param int $collection_id
 * @return array [ pokemon_id => 'owned'|'for_trade'|'missing' ]
 */
function poke_hub_collections_get_items(int $collection_id): array {
    global $wpdb;

    $items_table = pokehub_get_table('collection_items');
    if (!$items_table || $collection_id <= 0) {
        return [];
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT pokemon_id, status FROM {$items_table} WHERE collection_id = %d",
        $collection_id
    ), ARRAY_A);

    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $out[(int) $row['pokemon_id']] = in_array($row['status'], ['owned', 'for_trade', 'missing'], true) ? $row['status'] : 'missing';
    }
    return $out;
}

/**
 * Met à jour le statut d'un Pokémon dans une collection.
 *
 * @param int    $collection_id
 * @param int    $pokemon_id
 * @param string $status   owned|for_trade|missing
 * @param int    $user_id  Vérification propriétaire (0 si collection anonyme)
 * @param string|null $ip  IP du client (requis si user_id = 0 pour collection anonyme)
 * @return bool
 */
function poke_hub_collections_set_item(int $collection_id, int $pokemon_id, string $status, int $user_id = 0, ?string $ip = null): bool {
    global $wpdb;

    if ($user_id > 0) {
        if (!poke_hub_collections_can_edit($collection_id, $user_id)) {
            return false;
        }
    } else {
        if ($ip === null || !poke_hub_collections_can_edit_anonymous($collection_id, $ip)) {
            return false;
        }
    }

    $status = in_array($status, ['owned', 'for_trade', 'missing'], true) ? $status : 'missing';
    $items_table = pokehub_get_table('collection_items');
    if (!$items_table) {
        return false;
    }

    $wpdb->replace(
        $items_table,
        [
            'collection_id' => $collection_id,
            'pokemon_id'     => $pokemon_id,
            'status'         => $status,
        ],
        ['%d', '%d', '%s']
    );

    return true;
}

/**
 * Met à jour plusieurs items en une fois.
 *
 * @param int   $collection_id
 * @param array $items  [ pokemon_id => status, ... ]
 * @param int   $user_id 0 pour anonyme
 * @param string|null $ip requis si user_id = 0
 * @return bool
 */
function poke_hub_collections_set_items(int $collection_id, array $items, int $user_id = 0, ?string $ip = null): bool {
    if ($user_id > 0) {
        if (!poke_hub_collections_can_edit($collection_id, $user_id)) {
            return false;
        }
    } else {
        if ($ip === null || !poke_hub_collections_can_edit_anonymous($collection_id, $ip)) {
            return false;
        }
    }

    foreach ($items as $pokemon_id => $status) {
        poke_hub_collections_set_item($collection_id, (int) $pokemon_id, (string) $status, $user_id, $ip);
    }
    return true;
}
