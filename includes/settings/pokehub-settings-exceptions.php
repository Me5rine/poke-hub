<?php
// File: includes/settings/pokehub-settings-exceptions.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings « Exceptions » Poké HUB.
 *
 * Stocke dans une table dédiée (plutôt qu'en wp_options) les exceptions et
 * cas particuliers paramétrables par l'admin :
 *  - binary_sex_family : cas rare où le GM n’expose pas la structure attendue
 *    (fiches *-family / nu / *-female) alors que le dimorphisme est géré ailleurs
 *    dans le GM pour la plupart des espèces (NORMAL / FEMALE, ou MALE / FEMALE).
 *    En pratique seuls Hippopotas, Hippowdon et Unfezant nécessitent un clone
 *    post-import ; si d’autres manquent, c’est plutôt un bug d’import à corriger.
 *
 * Catégorie => slug anglais minuscule (ex. "unfezant", "tauros-paldea-aqua").
 * Le filtre `poke_hub_pokemon_gm_binary_sex_family_clone_protos` reçoit la
 * version PROTO (uppercase + underscores).
 */

/**
 * Slug logique de la table (via {@see pokehub_get_table()}).
 */
function pokehub_settings_exceptions_table_key(): string {
    return 'pokehub_settings_exceptions';
}

/**
 * Nom complet (préfixé) de la table.
 */
function pokehub_settings_exceptions_table(): string {
    if (!function_exists('pokehub_get_table')) {
        return '';
    }
    return pokehub_get_table(pokehub_settings_exceptions_table_key());
}

/**
 * Liste des catégories d'exceptions supportées (slug => métadonnées d'affichage).
 *
 * Pour ajouter une nouvelle catégorie :
 *  - ajouter une entrée ici (label, description, exemple)
 *  - brancher le code consommateur sur {@see pokehub_settings_exceptions_get_slugs()}
 *    (ou un filtre WordPress, comme `poke_hub_pokemon_gm_binary_sex_family_clone_protos`).
 *
 * @return array<string, array{label:string, description:string, example:string}>
 */
function pokehub_settings_exceptions_get_categories(): array {
    return [
        'binary_sex_family' => [
            'label'       => __('Binary sex family (♂/♀)', 'poke-hub'),
            'description' => __('Rare GM gaps only: the Game Master already exposes NORMAL/FEMALE (or MALE/FEMALE) for most dimorphic species (Frillish, Pyroar, Meowstic, Indeedee, Oinkologne…). Here we only list species that still need a post-import clone (Hippopotas, Hippowdon, Unfezant). If another species is missing family rows, fix the import instead of adding it here.', 'poke-hub'),
            'example'     => 'unfezant',
        ],
    ];
}

/**
 * Version courante du seed. À incrémenter à chaque fois que de nouvelles entrées
 * sont ajoutées par défaut — les installations déjà seedées injecteront alors les
 * lignes manquantes (les entrées déjà présentes ne sont pas touchées, et les
 * entrées supprimées manuellement par l'admin ne sont pas réintroduites tant que
 * la version n'est pas re-bumpée).
 */
function pokehub_settings_exceptions_seed_version(): int {
    return 3;
}

/**
 * Données par défaut (seed) à insérer à la création de la table.
 *
 * Catégorie binary_sex_family : seulement les espèces pour lesquelles le GM ne
 * fournit toujours pas la structure *-family / nu / *-female attendue par les
 * collections (Hippopotas, Hippowdon, Unfezant). Le reste (Frillish, Jellicent,
 * Pyroar, Meowstic, Indeedee, Oinkologne…) est déjà dans le GM : ne pas dupliquer
 * ici — renforcer l’import si une ligne manque.
 *
 * @return array<int, array{category:string, slug_en:string, note:string}>
 */
function pokehub_settings_exceptions_get_default_seed(): array {
    return [
        ['category' => 'binary_sex_family', 'slug_en' => 'hippopotas', 'note' => ''],
        ['category' => 'binary_sex_family', 'slug_en' => 'hippowdon',  'note' => ''],
        ['category' => 'binary_sex_family', 'slug_en' => 'unfezant',   'note' => ''],
    ];
}

/**
 * Slug anglais minuscule → PROTO uppercase (pour le filtre GM).
 */
function pokehub_settings_exceptions_slug_to_proto(string $slug_en): string {
    $slug_en = strtolower(trim($slug_en));
    if ($slug_en === '') {
        return '';
    }
    return strtoupper(str_replace('-', '_', $slug_en));
}

/**
 * Normalise un slug saisi (anglais, minuscule, dashes, [a-z0-9-]).
 */
function pokehub_settings_exceptions_normalize_slug(string $slug_en): string {
    $slug_en = strtolower(trim($slug_en));
    $slug_en = str_replace('_', '-', $slug_en);
    // Conserve uniquement lettres minuscules, chiffres et dashes.
    $slug_en = preg_replace('/[^a-z0-9-]/', '', $slug_en) ?? '';
    $slug_en = preg_replace('/-+/', '-', $slug_en) ?? '';
    return trim($slug_en, '-');
}

/**
 * Création / mise à jour du schéma de la table.
 */
function pokehub_settings_exceptions_install_table(): void {
    global $wpdb;
    $table = pokehub_settings_exceptions_table();
    if ($table === '') {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    // VARCHAR ramenés à (32 + 96) pour rester sous la limite d’index composite
    // utf8mb4 (sinon (64 × 4) + (191 × 4) = 1020 > 1000 bytes → dbDelta échoue
    // silencieusement et la table n’est jamais créée).
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        category VARCHAR(32) NOT NULL DEFAULT '',
        slug_en VARCHAR(96) NOT NULL DEFAULT '',
        note VARCHAR(255) NOT NULL DEFAULT '',
        is_seed TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY category_slug (category, slug_en),
        KEY category (category)
    ) {$charset_collate};";

    dbDelta($sql);
}

/**
 * Vérifie l'existence physique de la table.
 */
function pokehub_settings_exceptions_table_exists(): bool {
    global $wpdb;
    $table = pokehub_settings_exceptions_table();
    if ($table === '' || !isset($wpdb) || !is_object($wpdb)) {
        return false;
    }
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

/**
 * Pré-remplit la table avec la liste par défaut.
 *
 * Idempotent à deux niveaux :
 *  - n'insère que les lignes manquantes (jamais d'écrasement) → un slug supprimé
 *    manuellement par l'admin ne revient pas tant que la version du seed est inchangée ;
 *  - ne tourne que si la version du seed installée est inférieure à la version
 *    courante (voir {@see pokehub_settings_exceptions_seed_version()}). Bumpe la
 *    version pour réintroduire les manquants après ajout d'une nouvelle entrée par défaut.
 */
function pokehub_settings_exceptions_seed_defaults(): void {
    global $wpdb;
    if (!pokehub_settings_exceptions_table_exists()) {
        return;
    }

    $current_version  = pokehub_settings_exceptions_seed_version();
    $installed_marker = (int) get_option('poke_hub_settings_exceptions_seeded_version', 0);
    if ($installed_marker >= $current_version) {
        return;
    }

    $table = pokehub_settings_exceptions_table();

    // v3 : retirer les slugs seedés par erreur (v2) — le GM couvre déjà ces espèces.
    if ($current_version >= 3 && $installed_marker < 3) {
        $obsolete_seed_slugs = ['pyroar', 'frillish', 'jellicent'];
        $placeholders        = implode(',', array_fill(0, count($obsolete_seed_slugs), '%s'));
        $params              = array_merge(['binary_sex_family', 1], $obsolete_seed_slugs);
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder -- placeholders dynamiques pour IN().
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE category = %s AND is_seed = %d AND slug_en IN ({$placeholders})",
                $params
            )
        );
    }

    foreach (pokehub_settings_exceptions_get_default_seed() as $row) {
        $slug_en = pokehub_settings_exceptions_normalize_slug((string) ($row['slug_en'] ?? ''));
        $category = sanitize_key((string) ($row['category'] ?? ''));
        if ($slug_en === '' || $category === '') {
            continue;
        }
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE category = %s AND slug_en = %s LIMIT 1",
            $category,
            $slug_en
        ));
        if ($exists > 0) {
            continue;
        }
        $wpdb->insert(
            $table,
            [
                'category' => $category,
                'slug_en'  => $slug_en,
                'note'     => (string) ($row['note'] ?? ''),
                'is_seed'  => 1,
            ],
            ['%s', '%s', '%s', '%d']
        );
    }

    update_option('poke_hub_settings_exceptions_seeded_version', $current_version, false);
}

/**
 * Liste brute des entrées pour une catégorie.
 *
 * @return list<array{id:int, category:string, slug_en:string, note:string, is_seed:int, created_at:string}>
 */
function pokehub_settings_exceptions_get_entries(string $category): array {
    global $wpdb;
    if (!pokehub_settings_exceptions_table_exists()) {
        return [];
    }
    $category = sanitize_key($category);
    if ($category === '') {
        return [];
    }
    $table = pokehub_settings_exceptions_table();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, category, slug_en, note, is_seed, created_at
             FROM {$table}
             WHERE category = %s
             ORDER BY slug_en ASC",
            $category
        ),
        ARRAY_A
    );
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'         => (int) ($r['id'] ?? 0),
            'category'   => (string) ($r['category'] ?? ''),
            'slug_en'    => (string) ($r['slug_en'] ?? ''),
            'note'       => (string) ($r['note'] ?? ''),
            'is_seed'    => (int) ($r['is_seed'] ?? 0),
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];
    }
    return $out;
}

/**
 * Slugs anglais pour une catégorie.
 *
 * @return list<string>
 */
function pokehub_settings_exceptions_get_slugs(string $category): array {
    $entries = pokehub_settings_exceptions_get_entries($category);
    $slugs   = [];
    foreach ($entries as $e) {
        $s = pokehub_settings_exceptions_normalize_slug($e['slug_en']);
        if ($s !== '' && !in_array($s, $slugs, true)) {
            $slugs[] = $s;
        }
    }
    return $slugs;
}

/**
 * PROTOs (uppercase / underscores) pour une catégorie — directement
 * consommables par les filtres GM.
 *
 * @return list<string>
 */
function pokehub_settings_exceptions_get_protos(string $category): array {
    $out = [];
    foreach (pokehub_settings_exceptions_get_slugs($category) as $slug_en) {
        $proto = pokehub_settings_exceptions_slug_to_proto($slug_en);
        if ($proto !== '' && !in_array($proto, $out, true)) {
            $out[] = $proto;
        }
    }
    return $out;
}

/**
 * Insère un slug. Retourne true si nouvelle ligne, false sinon (déjà présent / invalide).
 */
function pokehub_settings_exceptions_add(string $category, string $slug_en, string $note = ''): bool {
    global $wpdb;
    if (!pokehub_settings_exceptions_table_exists()) {
        return false;
    }
    $category = sanitize_key($category);
    $slug_en  = pokehub_settings_exceptions_normalize_slug($slug_en);
    $note     = sanitize_text_field($note);
    if ($category === '' || $slug_en === '') {
        return false;
    }
    $cats = pokehub_settings_exceptions_get_categories();
    if (!isset($cats[$category])) {
        return false;
    }

    $table = pokehub_settings_exceptions_table();
    $exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE category = %s AND slug_en = %s LIMIT 1",
        $category,
        $slug_en
    ));
    if ($exists > 0) {
        return false;
    }

    $ok = $wpdb->insert(
        $table,
        [
            'category' => $category,
            'slug_en'  => $slug_en,
            'note'     => $note,
            'is_seed'  => 0,
        ],
        ['%s', '%s', '%s', '%d']
    );
    return (bool) $ok;
}

/**
 * Supprime une entrée par id (et catégorie pour double check).
 */
function pokehub_settings_exceptions_delete_by_id(int $id, string $category = ''): bool {
    global $wpdb;
    if (!pokehub_settings_exceptions_table_exists() || $id <= 0) {
        return false;
    }
    $table = pokehub_settings_exceptions_table();
    $where = ['id' => $id];
    $fmt   = ['%d'];
    if ($category !== '') {
        $where['category'] = sanitize_key($category);
        $fmt[]             = '%s';
    }
    $rows = $wpdb->delete($table, $where, $fmt);
    return is_int($rows) && $rows > 0;
}

/**
 * Branche les exceptions « binary_sex_family » sur le filtre GM existant.
 * Le tableau par défaut (côté code GM) reste vide → la liste effective vient
 * exclusivement de la table d'exceptions (seed + entrées manuelles).
 */
function pokehub_settings_exceptions_filter_binary_sex_family_clone_protos(array $protos): array {
    if (!pokehub_settings_exceptions_table_exists()) {
        return $protos;
    }
    $from_settings = pokehub_settings_exceptions_get_protos('binary_sex_family');
    if ($from_settings === []) {
        return $protos;
    }
    $merged = array_values(array_unique(array_merge(array_map('strval', $protos), $from_settings)));
    return $merged;
}
add_filter('poke_hub_pokemon_gm_binary_sex_family_clone_protos', 'pokehub_settings_exceptions_filter_binary_sex_family_clone_protos', 10, 1);

/**
 * Création + seed différés (sur admin_init pour s'assurer que $wpdb / get_option sont prêts).
 * Idempotent grâce au marqueur poke_hub_settings_exceptions_seeded_version.
 */
function pokehub_settings_exceptions_bootstrap(): void {
    if (!function_exists('pokehub_get_table')) {
        return;
    }
    if (!pokehub_settings_exceptions_table_exists()) {
        pokehub_settings_exceptions_install_table();
    }
    pokehub_settings_exceptions_seed_defaults();
}
add_action('admin_init', 'pokehub_settings_exceptions_bootstrap', 5);

/**
 * Handler admin-post : ajout d'une exception.
 */
function pokehub_settings_exceptions_handle_add(): void {
    if (!current_user_can('manage_options')) {
        wp_die(__('Forbidden.', 'poke-hub'), '', ['response' => 403]);
    }
    check_admin_referer('poke_hub_settings_exceptions_add', 'poke_hub_settings_exceptions_nonce');

    $category = isset($_POST['category']) ? sanitize_key((string) wp_unslash($_POST['category'])) : '';
    $slug_en  = isset($_POST['slug_en']) ? (string) wp_unslash($_POST['slug_en']) : '';
    $note     = isset($_POST['note']) ? (string) wp_unslash($_POST['note']) : '';

    $added = pokehub_settings_exceptions_add($category, $slug_en, $note);

    $redirect = add_query_arg(
        [
            'page'            => 'poke-hub-settings',
            'tab'             => 'exceptions',
            'poke_hub_notice' => $added ? 'exception_added' : 'exception_skipped',
        ],
        admin_url('admin.php')
    );
    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_poke_hub_settings_exceptions_add', 'pokehub_settings_exceptions_handle_add');

/**
 * Handler admin-post : suppression d'une exception.
 */
function pokehub_settings_exceptions_handle_delete(): void {
    if (!current_user_can('manage_options')) {
        wp_die(__('Forbidden.', 'poke-hub'), '', ['response' => 403]);
    }
    check_admin_referer('poke_hub_settings_exceptions_delete', 'poke_hub_settings_exceptions_nonce');

    $id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $category = isset($_POST['category']) ? sanitize_key((string) wp_unslash($_POST['category'])) : '';

    $deleted = pokehub_settings_exceptions_delete_by_id($id, $category);

    $redirect = add_query_arg(
        [
            'page'            => 'poke-hub-settings',
            'tab'             => 'exceptions',
            'poke_hub_notice' => $deleted ? 'exception_deleted' : 'exception_delete_failed',
        ],
        admin_url('admin.php')
    );
    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_poke_hub_settings_exceptions_delete', 'pokehub_settings_exceptions_handle_delete');
