<?php
// modules/collections/public/collections-list-render.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Slugs de catégorie BDD associés à un préréglage de création (filtre liste publique).
 *
 * @return list<string>|null null = pas de filtre catégorie.
 */
function poke_hub_collections_public_filter_preset_categories(string $preset): ?array {
    $preset = sanitize_key($preset);
    if ($preset === '') {
        return null;
    }

    $map = [
        'all'                      => [
            POKE_HUB_COLLECTIONS_CAT_ALL_POKEMON,
            'shiny',
            'custom',
        ],
        'costumes'                 => ['costume', 'costume_shiny'],
        'backgrounds'              => [
            'background',
            'background_special',
            'background_places',
            'background_shiny',
            'background_shiny_special',
            'background_shiny_places',
        ],
        'lucky'                    => ['lucky', 'lucky_dex'],
        'shadow_purified'          => ['shadow', 'purified'],
        'regional_go'              => [POKE_HUB_COLLECTIONS_CAT_POGO_GEO_EXCLUSIVE],
        'gigantamax'               => ['gigantamax'],
        'dynamax'                  => ['dynamax'],
        'mega_primal'              => ['mega'],
        'legendary_mythical_ultra' => ['legendary_mythical_ultra'],
        'babies'                   => [POKE_HUB_COLLECTIONS_CAT_BABIES_ONLY],
        'custom'                   => ['custom'],
    ];

    return $map[$preset] ?? null;
}

/**
 * Requête les collections publiques de comptes enregistrés.
 *
 * @param array<string, mixed> $args category_preset, paged, per_page, exclude_user_id
 * @return array{items: array, total: int, total_pages: int, paged: int, per_page: int}
 */
function poke_hub_collections_query_public(array $args = []): array {
    global $wpdb;

    $args = wp_parse_args($args, [
        'category_preset'  => '',
        'paged'            => 1,
        'per_page'         => (int) apply_filters('poke_hub_collections_public_per_page', 12),
        'exclude_user_id'  => 0,
    ]);

    $per_page = max(1, min(48, (int) $args['per_page']));
    $paged    = max(1, (int) $args['paged']);
    $offset   = ($paged - 1) * $per_page;

    $empty = [
        'items'       => [],
        'total'       => 0,
        'total_pages' => 0,
        'paged'       => $paged,
        'per_page'    => $per_page,
    ];

    $collections_table = pokehub_get_table('collections');
    if (!$collections_table) {
        return $empty;
    }

    $where  = 'WHERE c.is_public = 1 AND c.user_id > 0 AND c.share_token IS NOT NULL AND c.share_token != %s';
    $params = [''];

    $cat_slugs = poke_hub_collections_public_filter_preset_categories((string) $args['category_preset']);
    if (is_array($cat_slugs) && $cat_slugs !== []) {
        $placeholders = implode(',', array_fill(0, count($cat_slugs), '%s'));
        $where       .= " AND c.category IN ({$placeholders})";
        foreach ($cat_slugs as $slug) {
            $params[] = poke_hub_collections_normalize_storage_category_slug($slug);
        }
    }

    $exclude_uid = (int) $args['exclude_user_id'];
    if ($exclude_uid > 0) {
        $where   .= ' AND c.user_id != %d';
        $params[] = $exclude_uid;
    }

    $count_sql = "SELECT COUNT(*) FROM {$collections_table} c {$where}";
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));

    if ($total <= 0) {
        return $empty;
    }

    $total_pages = (int) ceil($total / $per_page);
    if ($paged > $total_pages) {
        $paged  = $total_pages;
        $offset = ($paged - 1) * $per_page;
    }

    $select_sql = "SELECT c.id, c.user_id, c.name, c.slug, c.share_token, c.anonymous_ip, c.category, c.options, c.is_public, c.created_at, c.updated_at
         FROM {$collections_table} c {$where}
         ORDER BY c.updated_at DESC
         LIMIT %d OFFSET %d";
    $select_params   = $params;
    $select_params[] = $per_page;
    $select_params[] = $offset;

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $rows = $wpdb->get_results($wpdb->prepare($select_sql, $select_params), ARRAY_A);

    if (!is_array($rows)) {
        $rows = [];
    }

    foreach ($rows as &$row) {
        $row['category'] = poke_hub_collections_normalize_storage_category_slug((string) ($row['category'] ?? ''));
        $row['options']  = !empty($row['options'])
            ? (json_decode($row['options'], true) ?: poke_hub_collections_default_options())
            : poke_hub_collections_default_options();
    }
    unset($row);

    return [
        'items'       => $rows,
        'total'       => $total,
        'total_pages' => $total_pages,
        'paged'       => $paged,
        'per_page'    => $per_page,
    ];
}

/**
 * Collections d'un utilisateur pour le profil (publiques seulement, ou toutes si propriétaire).
 *
 * @return array<int, array<string, mixed>>
 */
function poke_hub_collections_get_for_profile_display(int $user_id, bool $is_owner): array {
    if ($user_id <= 0) {
        return [];
    }

    $rows = poke_hub_collections_get_by_user($user_id);
    if ($is_owner) {
        return $rows;
    }

    return array_values(array_filter($rows, static function ($row) {
        return is_array($row) && poke_hub_collections_row_is_public($row);
    }));
}

/**
 * URL du profil Ultimate Member — onglet Pokémon GO.
 */
function poke_hub_collections_get_owner_profile_url(int $user_id): string {
    if ($user_id <= 0) {
        return '';
    }

    if (function_exists('poke_hub_get_um_profile_tab_url')) {
        $url = (string) poke_hub_get_um_profile_tab_url($user_id);
        if ($url !== '') {
            return $url;
        }
    }

    if (function_exists('poke_hub_get_user_profile_url')) {
        $url = (string) poke_hub_get_user_profile_url($user_id);
        if ($url !== '') {
            return $url;
        }
    }

    if (function_exists('um_user_profile_url')) {
        $url = (string) um_user_profile_url($user_id);
        if ($url !== '' && function_exists('poke_hub_replace_url_domain_with_current_site')) {
            $url = poke_hub_replace_url_domain_with_current_site($url);
        }
        if ($url !== '') {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            return $url . $separator . 'tab=game-pokemon-go';
        }
    }

    return (string) get_author_posts_url($user_id);
}

/**
 * Libellé affiché pour le propriétaire (pseudo GO ou display_name).
 */
function poke_hub_collections_get_owner_display_label(int $user_id): string {
    $user = get_userdata($user_id);
    if (!$user) {
        return '';
    }

    if (function_exists('poke_hub_get_user_profile')) {
        $profile = poke_hub_get_user_profile($user_id);
        if (is_array($profile) && !empty($profile['pokemon_go_username'])) {
            return (string) $profile['pokemon_go_username'];
        }
    }

    return (string) ($user->display_name ?: $user->user_login);
}

/**
 * URL de la page liste des collections (option poke_hub_page_collections).
 */
function poke_hub_collections_get_dashboard_page_url(): string {
    $page_id = (int) get_option('poke_hub_page_collections', 0);
    if ($page_id <= 0 || get_post_status($page_id) !== 'publish') {
        return '';
    }

    return (string) get_permalink($page_id);
}

/**
 * Totaux de progression pour une carte.
 *
 * @return array{owned: int, total: int, percent: int}
 */
function poke_hub_collections_card_progress_for_row(array $col): array {
    $col_opts = is_array($col['options'] ?? null) ? $col['options'] : [];
    $col_opts = array_merge(poke_hub_collections_default_options(), $col_opts);
    $col_pool = poke_hub_collections_get_pool((string) ($col['category'] ?? 'custom'), $col_opts);
    $col_items = poke_hub_collections_get_items((int) ($col['id'] ?? 0));
    $col_items_resolved = function_exists('poke_hub_collections_resolved_items_map')
        ? poke_hub_collections_resolved_items_map($col_items, is_array($col_pool) ? $col_pool : [])
        : $col_items;
    $for_trade_owned = function_exists('poke_hub_collections_for_trade_counts_as_owned_from_options')
        ? poke_hub_collections_for_trade_counts_as_owned_from_options($col_opts)
        : true;

    if (function_exists('poke_hub_collections_compute_progress_totals') && is_array($col_pool)) {
        $totals = poke_hub_collections_compute_progress_totals($col_pool, is_array($col_items_resolved) ? $col_items_resolved : [], $for_trade_owned);

        return [
            'owned'   => (int) ($totals['owned'] ?? 0),
            'total'   => (int) ($totals['total'] ?? 0),
            'percent' => (int) min(100, (int) round((float) ($totals['percent_owned'] ?? 0))),
        ];
    }

    $owned = is_array($col_items_resolved)
        ? count(array_filter($col_items_resolved, static function ($s) use ($for_trade_owned) {
            return $s === 'owned' || ($for_trade_owned && $s === 'for_trade');
        }))
        : 0;
    $total = is_array($col_pool) ? count($col_pool) : 0;

    return [
        'owned'   => $owned,
        'total'   => $total,
        'percent' => ($total > 0) ? (int) min(100, round(($owned / $total) * 100)) : 0,
    ];
}

/**
 * Affiche une carte collection (grille).
 *
 * @param array<string, mixed> $col
 * @param array<string, mixed> $args show_owner, show_actions, link_to_edit, categories, view_base_url
 */
function poke_hub_collections_render_grid_card(array $col, array $args = []): void {
    $args = wp_parse_args($args, [
        'show_owner'     => false,
        'show_actions'   => false,
        'link_to_edit'   => false,
        'categories'     => [],
        'view_base_url'  => '',
    ]);

    $categories = is_array($args['categories']) ? $args['categories'] : [];
    $view_url   = function_exists('poke_hub_collections_public_view_url')
        ? poke_hub_collections_public_view_url($col)
        : '';
    if ($view_url === '' && $args['view_base_url'] !== '') {
        $tok = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($col['share_token'] ?? ''));
        if ($tok !== '') {
            $base     = rtrim((string) $args['view_base_url'], '/');
            $view_url = (strpos($base, '?') !== false)
                ? add_query_arg('c', $tok, $base)
                : $base . '/' . $tok;
        }
    }

    $edit_url = '';
    if ($view_url !== '' && (!empty($args['show_actions']) || !empty($args['link_to_edit']))) {
        $edit_url = add_query_arg('edit', '1', $view_url);
    }
    $card_href = (!empty($args['link_to_edit']) && $edit_url !== '') ? $edit_url : $view_url;

    $card_bg_image = function_exists('poke_hub_collections_get_card_background_image_url')
        ? poke_hub_collections_get_card_background_image_url($col)
        : '';
    $card_bg_style = $card_bg_image !== ''
        ? ' style="background-image: url(' . esc_url($card_bg_image) . '); background-size: cover; background-position: center top;"'
        : '';

    $progress  = poke_hub_collections_card_progress_for_row($col);
    $cat_key   = (string) ($col['category'] ?? '');
    $cat_label = $categories[$cat_key] ?? $cat_key;
    $owner_uid = (int) ($col['user_id'] ?? 0);
    ?>
    <?php
    $owner_url   = '';
    $owner_label = '';
    if (!empty($args['show_owner']) && $owner_uid > 0) {
        $owner_url   = poke_hub_collections_get_owner_profile_url($owner_uid);
        $owner_label = poke_hub_collections_get_owner_display_label($owner_uid);
    }
    ?>
    <li class="me5rine-lab-card<?php echo !empty($args['show_owner']) && $owner_uid > 0 ? ' pokehub-collections-card--has-owner' : ''; ?>" data-collection-id="<?php echo (int) ($col['id'] ?? 0); ?>" data-collection-name="<?php echo esc_attr((string) ($col['name'] ?? '')); ?>" data-category="<?php echo esc_attr($cat_key); ?>">
        <a href="<?php echo esc_url($card_href); ?>" class="pokehub-collections-card-link">
            <span class="pokehub-collections-card-bg"<?php echo $card_bg_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></span>
            <span class="pokehub-collections-card-head">
                <span class="me5rine-lab-card-name"><?php echo esc_html((string) ($col['name'] ?? '')); ?></span>
                <span class="me5rine-lab-card-meta"><?php echo esc_html($cat_label); ?></span>
            </span>
            <span class="pokehub-collections-card-progress" aria-hidden="true">
                <span class="pokehub-collections-card-progress-stats"><?php echo (int) $progress['owned']; ?> / <?php echo (int) $progress['total']; ?></span>
                <span class="pokehub-collections-card-progress-bar">
                    <span class="pokehub-collections-card-progress-fill" style="width: <?php echo (int) $progress['percent']; ?>%;"></span>
                </span>
            </span>
        </a>
        <?php if (!empty($args['show_owner']) && $owner_uid > 0 && $owner_label !== '') : ?>
            <span class="pokehub-collections-card-owner">
                <?php if ($owner_url !== '') : ?>
                    <a href="<?php echo esc_url($owner_url); ?>" class="pokehub-collections-card-owner-link">
                <?php endif; ?>
                <span class="pokehub-collections-card-owner-avatar" aria-hidden="true"><?php echo get_avatar($owner_uid, 28, '', '', ['class' => 'pokehub-collections-card-owner-avatar-img']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="pokehub-collections-card-owner-name"><?php echo esc_html($owner_label); ?></span>
                <?php if ($owner_url !== '') : ?>
                    </a>
                <?php endif; ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($args['show_actions']) && $edit_url !== '') : ?>
            <div class="me5rine-lab-card-actions">
                <?php if (empty($args['link_to_edit'])) : ?>
                    <a href="<?php echo esc_url($edit_url); ?>" class="pokehub-collections-card-btn pokehub-collections-card-btn-settings me5rine-lab-form-button me5rine-lab-form-button-secondary" title="<?php esc_attr_e('Settings', 'poke-hub'); ?>"><span class="me5rine-lab-sr-only"><?php esc_html_e('Settings', 'poke-hub'); ?></span></a>
                <?php endif; ?>
                <button type="button" class="pokehub-collections-card-btn pokehub-collections-card-btn-delete pokehub-collections-btn-delete-list me5rine-lab-form-button-remove" data-collection-id="<?php echo (int) ($col['id'] ?? 0); ?>" data-collection-name="<?php echo esc_attr((string) ($col['name'] ?? '')); ?>" title="<?php esc_attr_e('Delete', 'poke-hub'); ?>"><span class="me5rine-lab-sr-only"><?php esc_html_e('Delete', 'poke-hub'); ?></span></button>
            </div>
        <?php endif; ?>
    </li>
    <?php
}

/**
 * Section « collections publiques » (page principale).
 */
function poke_hub_collections_render_public_browse_section(): void {
    $filter_preset = isset($_GET['pub_type']) ? sanitize_key(wp_unslash((string) $_GET['pub_type'])) : '';
    $paged         = isset($_GET['pub_pg']) ? max(1, (int) $_GET['pub_pg']) : 1;
    $presets       = function_exists('poke_hub_collections_get_creation_ui_presets')
        ? poke_hub_collections_get_creation_ui_presets()
        : [];
    $categories    = poke_hub_collections_get_categories();
    $query         = poke_hub_collections_query_public([
        'category_preset' => $filter_preset,
        'paged'           => $paged,
    ]);
    $dashboard_url = poke_hub_collections_get_dashboard_page_url();
    ?>
    <section class="pokehub-collections-section pokehub-collections-section--public" aria-labelledby="pokehub-collections-public-heading">
        <header class="pokehub-collections-public-header">
            <div class="pokehub-collections-public-header-text">
                <h3 id="pokehub-collections-public-heading" class="pokehub-collections-public-heading"><?php esc_html_e('Public collections', 'poke-hub'); ?></h3>
                <p class="pokehub-collections-public-description"><?php esc_html_e('Lists shared by registered trainers.', 'poke-hub'); ?></p>
            </div>

        <?php if ($presets !== []) : ?>
            <form method="get" action="" class="pokehub-collections-public-filter-form me5rine-lab-filters">
                <?php
                foreach ($_GET as $key => $value) {
                    if (in_array($key, ['pub_type', 'pub_pg'], true)) {
                        continue;
                    }
                    if (is_array($value)) {
                        continue;
                    }
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
                }
                ?>
                    <div class="pokehub-collections-public-filter-controls">
                        <label class="me5rine-lab-sr-only" for="pub_type"><?php esc_html_e('Collection type', 'poke-hub'); ?></label>
                        <select name="pub_type" id="pub_type" class="me5rine-lab-form-select no-select2" aria-label="<?php esc_attr_e('Collection type', 'poke-hub'); ?>">
                            <option value=""><?php esc_html_e('All types', 'poke-hub'); ?></option>
                            <?php foreach ($presets as $preset_id => $preset_label) : ?>
                                <option value="<?php echo esc_attr((string) $preset_id); ?>" <?php selected($filter_preset, (string) $preset_id); ?>><?php echo esc_html((string) $preset_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="me5rine-lab-form-button me5rine-lab-form-button-secondary"><?php esc_html_e('Filter', 'poke-hub'); ?></button>
                        <?php if ($filter_preset !== '') : ?>
                            <a href="<?php echo esc_url(remove_query_arg(['pub_type', 'pub_pg'])); ?>" class="me5rine-lab-form-button me5rine-lab-form-button-secondary"><?php esc_html_e('Reset', 'poke-hub'); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </header>

        <?php if (empty($query['items'])) : ?>
            <p class="pokehub-collections-empty me5rine-lab-state-message" role="status"><?php esc_html_e('No public collections match your filters yet.', 'poke-hub'); ?></p>
        <?php else : ?>
            <ul class="pokehub-collections-grid pokehub-collections-grid--public">
                <?php
                foreach ($query['items'] as $col) {
                    poke_hub_collections_render_grid_card($col, [
                        'show_owner'    => true,
                        'show_actions'  => false,
                        'categories'    => $categories,
                        'view_base_url' => $dashboard_url,
                    ]);
                }
                ?>
            </ul>
            <?php
            if (function_exists('poke_hub_render_pagination')) {
                echo poke_hub_render_pagination([
                    'total_items' => (int) $query['total'],
                    'paged'       => (int) $query['paged'],
                    'total_pages' => (int) $query['total_pages'],
                    'page_var'    => 'pub_pg',
                ]);
            }
            ?>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * Liste des collections sur le profil Ultimate Member (onglet Pokémon GO).
 */
function poke_hub_collections_render_profile_section(int $profile_user_id, bool $is_owner): void {
    if ($profile_user_id <= 0) {
        return;
    }

    $collections = poke_hub_collections_get_for_profile_display($profile_user_id, $is_owner);
    $categories  = poke_hub_collections_get_categories();
    $dashboard   = poke_hub_collections_get_dashboard_page_url();

    $heading = $is_owner
        ? __('My collections', 'poke-hub')
        : __('Public collections', 'poke-hub');
    ?>
    <section class="pokehub-collections-profile-section me5rine-lab-form-block" aria-labelledby="pokehub-collections-profile-heading">
        <div class="pokehub-collections-profile-section-header">
            <h3 id="pokehub-collections-profile-heading" class="me5rine-lab-title-medium"><?php echo esc_html($heading); ?></h3>
            <?php if ($is_owner && $dashboard !== '') : ?>
                <p class="pokehub-collections-profile-edit-link-wrap">
                    <a href="<?php echo esc_url($dashboard); ?>" class="me5rine-lab-form-button me5rine-lab-form-button-secondary pokehub-collections-profile-edit-link"><?php esc_html_e('Edit my collections', 'poke-hub'); ?></a>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($collections === []) : ?>
            <p class="me5rine-lab-form-description pokehub-collections-profile-empty">
                <?php
                echo $is_owner
                    ? esc_html__('You have not created any collection yet.', 'poke-hub')
                    : esc_html__('This trainer has not shared any public collection yet.', 'poke-hub');
                ?>
            </p>
        <?php else : ?>
            <ul class="pokehub-collections-grid pokehub-collections-grid--profile">
                <?php
                foreach ($collections as $col) {
                    poke_hub_collections_render_grid_card($col, [
                        'show_owner'    => false,
                        'show_actions'  => $is_owner,
                        'link_to_edit'  => $is_owner,
                        'categories'    => $categories,
                        'view_base_url' => $dashboard,
                    ]);
                }
                ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php
}
