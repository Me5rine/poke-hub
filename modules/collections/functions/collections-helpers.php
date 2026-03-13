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
        'perfect_4'       => __('Pokémon 4* (parfaits)', 'poke-hub'),
        'shiny'           => __('Chromatiques', 'poke-hub'),
        'costume'         => __('Pokémon costumés', 'poke-hub'),
        'costume_shiny'   => __('Costumés chromatiques', 'poke-hub'),
        'background'      => __('Pokémon avec fonds', 'poke-hub'),
        'background_shiny'=> __('Fonds chromatiques', 'poke-hub'),
        'lucky'           => __('Chanceux', 'poke-hub'),
        'shadow'          => __('Obscurs', 'poke-hub'),
        'purified'        => __('Purifiés', 'poke-hub'),
        'gigantamax'     => __('Gigamax', 'poke-hub'),
        'dynamax'        => __('Dynamax', 'poke-hub'),
        'custom'         => __('Liste personnalisée', 'poke-hub'),
    ];
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
        'include_special_attacks'=> false,
        'exclude_mega'           => false,
        'one_per_species'        => false, // une seule entrée par dex_number (forme par défaut)
        'group_by_generation'   => true,  // grouper les tuiles par génération
        'display_mode'          => 'tiles', // tiles | select
        'public'                => false,
    ];
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
    $where = ['1 = 1'];

    // Filtre par catégorie
    switch ($category) {
        case 'costume':
        case 'costume_shiny':
            $where[] = "fv.category = 'costume'";
            break;
        case 'shadow':
            $where[] = 'p.has_shadow = 1';
            break;
        case 'purified':
            $where[] = 'p.has_purified = 1';
            break;
        case 'gigantamax':
            $where[] = "(fv.category = 'gigantamax' OR fv.form_slug LIKE '%gigantamax%')";
            break;
        case 'dynamax':
            $where[] = "(fv.category = 'dynamax' OR fv.form_slug LIKE '%dynamax%' OR fv.form_slug = 'normal')";
            break;
        case 'perfect_4':
        case 'shiny':
        case 'lucky':
        case 'background':
        case 'background_shiny':
        case 'custom':
        default:
            break;
    }

    if (!empty($opts['include_costumes']) && !in_array($category, ['costume', 'costume_shiny'], true)) {
        // rien
    } elseif (empty($opts['include_costumes']) && !in_array($category, ['costume', 'costume_shiny'], true)) {
        $where[] = "(fv.category IS NULL OR fv.category = '' OR fv.category = 'normal')";
    }

    // Exclure les Méga si demandé
    if (!empty($opts['exclude_mega'])) {
        $where[] = "(fv.category IS NULL OR fv.category = '' OR fv.category != 'mega')";
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

    // Ne garder que les Pokémon ayant une date de sortie dans GO pour ce contexte
    if (function_exists('poke_hub_pokemon_is_released_in_go')) {
        $filtered = [];
        foreach ($results as $row) {
            $extra = isset($row['extra']) ? json_decode($row['extra'], true) : null;
            $release = is_array($extra) ? trim((string) ($extra['release'][$release_context] ?? '')) : '';
            if ($release !== '') {
                unset($row['extra']);
                $filtered[] = $row;
            }
        }
        // Si aucun Pokémon n'a de date de sortie, afficher quand même la liste (données pas encore renseignées)
        if (count($filtered) > 0) {
            $results = $filtered;
        } else {
            foreach ($results as &$row) {
                unset($row['extra']);
            }
            unset($row);
        }
    } else {
        foreach ($results as &$row) {
            unset($row['extra']);
        }
        unset($row);
    }

    return $results;
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
            : ($gen_num > 0 ? sprintf(__('Génération %d', 'poke-hub'), $gen_num) : '');
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
            'name'        => $name ?: __('Ma collection', 'poke-hub'),
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
        'message'       => __('Collection créée.', 'poke-hub'),
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
        return ['success' => false, 'message' => __('Erreur technique.', 'poke-hub')];
    }

    $ip = preg_replace('/[^0-9a-f.:]/', '', $ip);
    if ($ip === '') {
        return ['success' => false, 'message' => __('Impossible d’identifier votre connexion.', 'poke-hub')];
    }

    $name     = isset($data['name']) ? sanitize_text_field($data['name']) : '';
    $category = isset($data['category']) ? sanitize_key($data['category']) : 'custom';
    $options  = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];

    $categories = array_keys(poke_hub_collections_get_categories());
    if (!in_array($category, $categories, true)) {
        $category = 'custom';
    }

    $options    = array_merge(poke_hub_collections_default_options(), $options);
    $options_json = wp_json_encode($options);

    $share_token = poke_hub_collections_generate_share_token();
    $slug        = 'anon-' . time() . '-' . substr($share_token, 0, 6);

    $r = $wpdb->insert(
        $collections_table,
        [
            'user_id'      => 0,
            'name'         => $name ?: __('Ma collection', 'poke-hub'),
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
        return ['success' => false, 'message' => __('Impossible de créer la collection.', 'poke-hub')];
    }

    $collection_id = (int) $wpdb->insert_id;
    return [
        'success'        => true,
        'message'        => __('Collection créée.', 'poke-hub'),
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
        return ['success' => false, 'message' => __('Vous ne pouvez pas modifier cette collection.', 'poke-hub')];
    }

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Erreur technique.', 'poke-hub')];
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
        $updates['options'] = wp_json_encode($options);
        $formats[] = '%s';
    }

    if (array_key_exists('is_public', $data)) {
        $updates['is_public'] = !empty($data['is_public']) ? 1 : 0;
        $formats[] = '%d';
    }

    if (empty($updates)) {
        return ['success' => true, 'message' => __('Aucune modification.', 'poke-hub')];
    }

    $r = $wpdb->update(
        $collections_table,
        $updates,
        ['id' => $collection_id],
        $formats,
        ['%d']
    );

    if ($r === false) {
        return ['success' => false, 'message' => __('Impossible de sauvegarder.', 'poke-hub')];
    }

    return ['success' => true, 'message' => __('Paramètres enregistrés.', 'poke-hub')];
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
        return ['success' => false, 'message' => __('Collection introuvable.', 'poke-hub')];
    }
    if ((int) $col['user_id'] !== 0) {
        return ['success' => false, 'message' => __('Cette collection est déjà rattachée à un compte.', 'poke-hub')];
    }
    $ip = preg_replace('/[^0-9a-f.:]/', '', $ip);
    if ($ip === '' || empty($col['anonymous_ip']) || $col['anonymous_ip'] !== $ip) {
        return ['success' => false, 'message' => __('Cette collection n’a pas été créée depuis cette connexion.', 'poke-hub')];
    }

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Erreur technique.', 'poke-hub')];
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
        return ['success' => false, 'message' => __('Impossible de rattacher.', 'poke-hub')];
    }

    return ['success' => true, 'message' => __('Collection rattachée à votre compte.', 'poke-hub')];
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
            return ['success' => false, 'message' => __('Vous ne pouvez pas supprimer cette collection.', 'poke-hub')];
        }
    } else {
        if ($ip === null || !poke_hub_collections_can_edit_anonymous($collection_id, $ip)) {
            return ['success' => false, 'message' => __('Vous ne pouvez pas supprimer cette collection.', 'poke-hub')];
        }
    }

    $collections_table = pokehub_get_table('collections');
    $items_table       = pokehub_get_table('collection_items');
    if (!$collections_table) {
        return ['success' => false, 'message' => __('Erreur technique.', 'poke-hub')];
    }

    if ($items_table) {
        $wpdb->delete($items_table, ['collection_id' => $collection_id], ['%d']);
    }
    $r = $wpdb->delete($collections_table, ['id' => $collection_id], ['%d']);

    if ($r === false) {
        return ['success' => false, 'message' => __('Impossible de supprimer.', 'poke-hub')];
    }

    return ['success' => true, 'message' => __('Collection supprimée.', 'poke-hub')];
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
