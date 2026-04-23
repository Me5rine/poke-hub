<?php
// modules/collections/public/collections-shortcode.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode unique : affiche soit la page de gestion (liste, création, suppression),
 * soit la page d’une collection (remplissage, paramètres). Une seule vue à la fois.
 * Usage recommandé: [poke_hub_collections_page]
 */
add_shortcode('poke_hub_collections_page', function ($atts) {
    if (!poke_hub_is_module_active('collections') || !poke_hub_is_module_active('pokemon')) {
        return '';
    }
    $token = get_query_var('collection_share') ?: (isset($_GET['c']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) wp_unslash($_GET['c'])) : '');
    $id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $slug  = isset($_GET['collection']) ? sanitize_text_field(wp_unslash($_GET['collection'])) : '';
    $view  = isset($_GET['view']);
    $local = isset($_GET['local']);
    $has_collection_view = ($token !== '') || ($id > 0) || ($slug !== '' && $view) || ($local && $slug !== '');
    if ($has_collection_view) {
        return do_shortcode('[poke_hub_collection_view]');
    }
    return do_shortcode('[poke_hub_collections]');
});

/**
 * Shortcode page de gestion : liste des collections, création, suppression.
 * Usage: [poke_hub_collections] (ou via [poke_hub_collections_page] sur l’URL de base).
 */
add_shortcode('poke_hub_collections', function ($atts) {
    if (!poke_hub_is_module_active('collections') || !poke_hub_is_module_active('pokemon')) {
        return '';
    }

    poke_hub_collections_enqueue_front_assets();

    $is_logged_in = is_user_logged_in();
    $user_id      = $is_logged_in ? get_current_user_id() : 0;
    $collections  = $user_id > 0 ? poke_hub_collections_get_by_user($user_id) : [];
    $categories   = poke_hub_collections_get_categories();

    ob_start();
    ?>
    <div class="pokehub-collections-wrap me5rine-lab-dashboard" data-logged-in="<?php echo $is_logged_in ? '1' : '0'; ?>">
        <h2 class="me5rine-lab-title-large"><?php esc_html_e('My collections', 'poke-hub'); ?></h2>
        <div class="me5rine-lab-dashboard-header">
            <p class="me5rine-lab-subtitle"><?php esc_html_e('Track your shiny, costumed, 100% and more in one place.', 'poke-hub'); ?></p>
            <div class="me5rine-lab-dashboard-header-actions">
                <button type="button" class="pokehub-collections-btn-create me5rine-lab-form-button button button-primary">
                    <?php esc_html_e('New collection', 'poke-hub'); ?>
                </button>
            </div>
        </div>

        <?php if ($is_logged_in) : ?>
        <div class="pokehub-collections-anonymous-banner me5rine-lab-form-message me5rine-lab-form-message-info is-hidden" id="pokehub-collections-anonymous-banner" aria-hidden="true">
            <p class="pokehub-collections-anonymous-banner-text"></p>
            <ul class="pokehub-collections-anonymous-list"></ul>
            <p class="pokehub-collections-anonymous-actions">
                <button type="button" class="pokehub-collections-claim-all me5rine-lab-form-button button button-primary"><?php esc_html_e('Add all to my account', 'poke-hub'); ?></button>
                <button type="button" class="pokehub-collections-dismiss-banner me5rine-lab-form-button-secondary button"><?php esc_html_e('Close', 'poke-hub'); ?></button>
            </p>
        </div>
        <?php endif; ?>

        <div class="pokehub-collections-list">
            <?php if (empty($collections)) : ?>
                <p class="pokehub-collections-empty me5rine-lab-state-message" role="status">
                    <?php esc_html_e('You don\'t have any collection yet. Create one to track your 100%, shiny, costumed, etc.', 'poke-hub'); ?>
                </p>
            <?php else : ?>
                <ul class="pokehub-collections-grid">
                    <?php
                    $collections_base_url = rtrim(get_permalink(), '/');
                    foreach ($collections as $col) :
                        $col_token = $col['share_token'] ?? '';
                        $col_view_url = $col_token !== '' ? $collections_base_url . '/' . $col_token : add_query_arg(['id' => $col['id'], 'view' => '1'], get_permalink());
                        $col_edit_url = $col_token !== '' ? $collections_base_url . '/' . $col_token . '?edit=1' : add_query_arg(['id' => $col['id'], 'view' => '1', 'edit' => '1'], get_permalink());
                        $card_bg_image = poke_hub_collections_get_card_background_image_url($col);
                        $card_bg_style = $card_bg_image !== '' ? ' style="background-image: url(' . esc_url($card_bg_image) . '); background-size: cover; background-position: center;"' : '';
                    ?>
                        <li class="me5rine-lab-card" data-collection-id="<?php echo (int) $col['id']; ?>" data-collection-name="<?php echo esc_attr($col['name']); ?>" data-category="<?php echo esc_attr($col['category']); ?>">
                            <a href="<?php echo esc_url($col_view_url); ?>" class="pokehub-collections-card-link">
                                <span class="pokehub-collections-card-bg"<?php echo $card_bg_style; ?>></span>
                                <span class="me5rine-lab-card-name"><?php echo esc_html($col['name']); ?></span>
                                <span class="me5rine-lab-card-meta"><?php echo esc_html($categories[$col['category']] ?? $col['category']); ?></span>
                            </a>
                            <div class="me5rine-lab-card-actions">
                                <a href="<?php echo esc_url($col_edit_url); ?>" class="pokehub-collections-card-btn pokehub-collections-card-btn-settings me5rine-lab-form-button-secondary" title="<?php esc_attr_e('Settings', 'poke-hub'); ?>"><span class="me5rine-lab-sr-only"><?php esc_html_e('Settings', 'poke-hub'); ?></span></a>
                                <button type="button" class="pokehub-collections-card-btn pokehub-collections-card-btn-delete pokehub-collections-btn-delete-list me5rine-lab-form-button-remove" data-collection-id="<?php echo (int) $col['id']; ?>" data-collection-name="<?php echo esc_attr($col['name']); ?>" title="<?php esc_attr_e('Delete', 'poke-hub'); ?>"><span class="me5rine-lab-sr-only"><?php esc_html_e('Delete', 'poke-hub'); ?></span></button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Drawer création (panneau latéral, comme l’édition) -->
        <div class="pokehub-collections-drawer pokehub-collections-drawer-create" id="pokehub-collections-drawer-create" role="dialog" aria-label="<?php esc_attr_e('New collection', 'poke-hub'); ?>" aria-hidden="true">
            <div class="pokehub-collections-drawer-backdrop" id="pokehub-collections-drawer-create-backdrop"></div>
            <div class="pokehub-collections-drawer-panel">
                <div class="me5rine-lab-card-header pokehub-collections-drawer-header">
                    <h3 class="me5rine-lab-title-medium"><?php esc_html_e('New collection', 'poke-hub'); ?></h3>
                    <button type="button" class="pokehub-collections-drawer-close" id="pokehub-collections-drawer-create-close" aria-label="<?php esc_attr_e('Close', 'poke-hub'); ?>">&times;</button>
                </div>
                <div class="pokehub-collections-drawer-body">
                    <p class="me5rine-lab-subtitle"><?php esc_html_e('Choose a name and type; you can refine options later.', 'poke-hub'); ?></p>
                    <div class="pokehub-collections-form me5rine-lab-form-block">
                        <div class="me5rine-lab-form-field">
                            <label for="pokehub-collection-name" class="me5rine-lab-form-label"><?php esc_html_e('Name', 'poke-hub'); ?></label>
                            <input type="text" id="pokehub-collection-name" class="me5rine-lab-form-input" placeholder="<?php esc_attr_e('e.g. My Shiny Pokémon', 'poke-hub'); ?>" />
                        </div>
                        <div class="me5rine-lab-form-field" data-specific-categories="<?php echo esc_attr(wp_json_encode(poke_hub_collections_get_specific_categories())); ?>">
                            <label for="pokehub-collection-category" class="me5rine-lab-form-label"><?php esc_html_e('Type', 'poke-hub'); ?></label>
                            <select id="pokehub-collection-category" class="me5rine-lab-form-select">
                                <?php foreach ($categories as $slug => $label) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="me5rine-lab-form-field">
                            <label><input type="checkbox" id="pokehub-collection-public" /> <?php esc_html_e('Make this collection public', 'poke-hub'); ?></label>
                        </div>

                        <details class="pokehub-collections-advanced me5rine-lab-form-block" id="pokehub-collection-advanced">
                            <summary class="me5rine-lab-title-medium pokehub-collections-advanced-summary"><?php esc_html_e('Customize content and display', 'poke-hub'); ?></summary>
                            <div class="pokehub-collections-advanced-inner">
                                <fieldset class="me5rine-lab-form-block">
                                    <legend class="me5rine-lab-form-label"><?php esc_html_e('Content to show', 'poke-hub'); ?></legend>
                                    <div class="pokehub-collections-options-additive" id="pokehub-collection-options-additive">
                                        <label><input type="checkbox" id="pokehub-collection-include-national" checked /> <?php esc_html_e('Show national Pokédex', 'poke-hub'); ?></label>
                                        <label><input type="checkbox" id="pokehub-collection-include-gender" checked /> <?php esc_html_e('Show gender differences', 'poke-hub'); ?></label>
                                        <label><input type="checkbox" id="pokehub-collection-include-forms" checked /> <?php esc_html_e('Show alternate forms', 'poke-hub'); ?></label>
                                        <label><input type="checkbox" id="pokehub-collection-include-costumes" checked /> <?php esc_html_e('Show costumed Pokémon', 'poke-hub'); ?></label>
                                        <label><input type="checkbox" id="pokehub-collection-include-mega" checked /> <?php esc_html_e('Show Mega evolutions', 'poke-hub'); ?></label>
                                        <label><input type="checkbox" id="pokehub-collection-include-gigantamax" checked /> <?php esc_html_e('Show Gigantamax', 'poke-hub'); ?></label>
                                        <label><input type="checkbox" id="pokehub-collection-include-dynamax" checked /> <?php esc_html_e('Show Dynamax', 'poke-hub'); ?></label>
                                        <label><input type="checkbox" id="pokehub-collection-include-special-attacks" /> <?php esc_html_e('Show special attacks', 'poke-hub'); ?></label>
                                    </div>
                                    <p class="pokehub-collections-options-specific-hint me5rine-lab-form-hint is-hidden" id="pokehub-collection-options-specific-hint" aria-live="polite">
                                        <?php esc_html_e('This collection type only shows that specific category (e.g. Gigantamax only). No extra filters.', 'poke-hub'); ?>
                                    </p>
                                </fieldset>
                                <fieldset class="me5rine-lab-form-block">
                                    <legend class="me5rine-lab-form-label"><?php esc_html_e('Display', 'poke-hub'); ?></legend>
                                    <label><input type="checkbox" id="pokehub-collection-one-per-species" /> <?php esc_html_e('One entry per species (e.g. one Unown)', 'poke-hub'); ?></label>
                                    <label><input type="checkbox" id="pokehub-collection-group-by-generation" checked /> <?php esc_html_e('Group by region / generation', 'poke-hub'); ?></label>
                                    <label><input type="checkbox" id="pokehub-collection-generations-collapsed" /> <?php esc_html_e('Collapse generations by default', 'poke-hub'); ?></label>
                                    <label><input type="radio" name="pokehub-collection-display" value="tiles" checked /> <?php esc_html_e('Tiles (tap to mark owned)', 'poke-hub'); ?></label>
                                    <label><input type="radio" name="pokehub-collection-display" value="select" /> <?php esc_html_e('List with missing selector', 'poke-hub'); ?></label>
                                </fieldset>
                            </div>
                        </details>
                    <?php if (!$is_logged_in) : ?>
                        <p class="pokehub-collections-warning me5rine-lab-form-message me5rine-lab-form-message-warning" role="alert">
                            <?php esc_html_e('You are not logged in. This collection will be stored locally on this device.', 'poke-hub'); ?>
                            <?php esc_html_e('Create an account to save your collections.', 'poke-hub'); ?>
                        </p>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="pokehub-collections-drawer-footer">
                    <button type="button" class="pokehub-collections-modal-cancel me5rine-lab-form-button-secondary button"><?php esc_html_e('Cancel', 'poke-hub'); ?></button>
                    <button type="button" class="pokehub-collections-modal-create-btn me5rine-lab-form-button button button-primary"><?php esc_html_e('Create', 'poke-hub'); ?></button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * Shortcode vue d'une collection (tuiles ou select).
 * Utilisé quand on ouvre une collection (query var collection=slug ou id).
 * Peut être sur la même page que [poke_hub_collections] avec détection view=1.
 */
add_shortcode('poke_hub_collection_view', function ($atts) {
    if (!poke_hub_is_module_active('collections') || !poke_hub_is_module_active('pokemon')) {
        return '';
    }

    $atts = shortcode_atts([
        'slug' => '',
        'id'   => 0,
    ], $atts, 'poke_hub_collection_view');

    $slug  = sanitize_text_field($atts['slug']);
    $id    = (int) $atts['id'];
    $local = !empty($atts['local']) || isset($_GET['local']);

    if (empty($slug) && $id <= 0) {
        $slug  = isset($_GET['collection']) ? sanitize_text_field(wp_unslash($_GET['collection'])) : '';
        $id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $local = $local || isset($_GET['local']);
    }

    $token = get_query_var('collection_share') ?: (isset($_GET['c']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) wp_unslash($_GET['c'])) : '');
    if ($token !== '' && empty($slug) && $id <= 0) {
        $slug = '';
        $id   = 0;
    }

    if (empty($slug) && $id <= 0 && $token === '') {
        return '';
    }

    poke_hub_collections_enqueue_front_assets();

    $user_id    = is_user_logged_in() ? get_current_user_id() : 0;
    $collection = null;

    // Collection locale (stockée en localStorage, pas en base)
    if ($local && $slug !== '' && (strpos($slug, 'local-') === 0 || $slug !== '')) {
        ob_start();
        ?>
        <div class="pokehub-collection-view-wrap me5rine-lab-dashboard" data-local="1" data-collection-slug="<?php echo esc_attr($slug); ?>" data-can-edit="1">
            <header class="me5rine-lab-dashboard-header pokehub-collection-view-header">
                <div class="pokehub-collection-view-header-left">
                    <a href="<?php echo esc_url(remove_query_arg(['collection', 'id', 'view', 'local'])); ?>" class="pokehub-collection-back">← <?php esc_html_e('Back to collections', 'poke-hub'); ?></a>
                </div>
                <div class="pokehub-collection-view-header-main">
                    <h2 class="me5rine-lab-title-large pokehub-collection-view-title pokehub-collection-local-title"><?php esc_html_e('Loading…', 'poke-hub'); ?></h2>
                    <div class="pokehub-collection-stats pokehub-collection-local-stats">—</div>
                </div>
                <div class="me5rine-lab-dashboard-header-actions pokehub-collection-view-actions">
                    <div class="pokehub-collections-reset-inline" data-reset-context="local">
                        <div class="pokehub-collections-reset-step pokehub-collections-reset-step-initial">
                            <button type="button" class="pokehub-collections-btn-reset-launch me5rine-lab-form-button-secondary button" disabled><?php esc_html_e('Reset progress', 'poke-hub'); ?></button>
                        </div>
                        <div class="pokehub-collections-reset-step pokehub-collections-reset-step-confirm" hidden>
                            <p class="me5rine-lab-form-hint pokehub-collections-reset-hint"><?php esc_html_e('All entries will be shown as missing. You can change them again anytime.', 'poke-hub'); ?></p>
                            <div class="pokehub-collections-reset-confirm-row">
                                <button type="button" class="pokehub-collections-btn-reset-apply me5rine-lab-form-button button button-primary"><?php esc_html_e('Clear progress', 'poke-hub'); ?></button>
                                <button type="button" class="pokehub-collections-btn-reset-dismiss me5rine-lab-form-button-secondary button"><?php esc_html_e('Back', 'poke-hub'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="pokehub-collection-status-filters me5rine-lab-form-block" role="group" aria-label="<?php esc_attr_e('Filter Pokémon by status in the grid', 'poke-hub'); ?>">
                <div class="pokehub-collection-status-filters-inner">
                    <span class="me5rine-lab-form-label pokehub-collection-status-filters-heading"><?php esc_html_e('Show in grid', 'poke-hub'); ?></span>
                    <div class="pokehub-collection-status-filters-checkboxes">
                        <label class="pokehub-collection-status-filter-label"><input type="checkbox" class="pokehub-collection-filter-status" data-filter-status="owned" checked /> <span class="pokehub-collection-legend-dot pokehub-legend-owned" aria-hidden="true"></span> <?php esc_html_e('Owned', 'poke-hub'); ?></label>
                        <label class="pokehub-collection-status-filter-label"><input type="checkbox" class="pokehub-collection-filter-status" data-filter-status="for_trade" checked /> <span class="pokehub-collection-legend-dot pokehub-legend-for-trade" aria-hidden="true"></span> <?php esc_html_e('For trade', 'poke-hub'); ?></label>
                        <label class="pokehub-collection-status-filter-label"><input type="checkbox" class="pokehub-collection-filter-status" data-filter-status="missing" checked /> <span class="pokehub-collection-legend-dot pokehub-legend-missing" aria-hidden="true"></span> <?php esc_html_e('Missing', 'poke-hub'); ?></label>
                    </div>
                </div>
                <p class="pokehub-collection-filter-empty-hint me5rine-lab-form-message me5rine-lab-form-message-warning is-hidden" role="status" aria-live="polite"><?php esc_html_e('Select at least one status to see Pokémon in the grid.', 'poke-hub'); ?></p>
            </div>
            <div class="pokehub-collection-tiles pokehub-collection-tiles-local" data-pool="[]" data-items="{}"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    if ($token !== '') {
        $collection = poke_hub_collections_get_by_share_token($token);
    }
    if (!$collection && $id > 0) {
        $collection = poke_hub_collections_get_one($id);
    }
    if (!$collection && $slug !== '') {
        if ($user_id > 0) {
            $collection = poke_hub_collections_get_one(0, $slug, $user_id);
        }
        if (!$collection) {
            $collection = poke_hub_collections_get_public_by_slug($slug);
        }
    }

    if (!$collection) {
        return '<p class="pokehub-collections-not-found">' . esc_html__('Collection not found.', 'poke-hub') . '</p>';
    }

    $collections_base = rtrim(get_permalink(), '/');
    $canonical_url    = $collections_base . '/' . ($collection['share_token'] ?? '');

    // Redirection vers l’URL canonique (token unique) si on est arrivé avec id ou slug
    if (!empty($collection['share_token']) && $token === '' && (isset($_GET['id']) || isset($_GET['collection']))) {
        wp_safe_redirect($canonical_url, 302);
        exit;
    }

    $pool     = poke_hub_collections_get_pool($collection['category'], $collection['options']);
    $category = $collection['category'];
    if (in_array($category, ['background', 'background_shiny', 'background_special', 'background_places', 'background_shiny_special', 'background_shiny_places'], true)) {
        $only_shiny_active = in_array($category, ['background_shiny', 'background_shiny_special', 'background_shiny_places'], true);
        foreach ($pool as &$p) {
            $p['background_image_url'] = function_exists('poke_hub_collections_get_background_image_url_for_pokemon')
                ? poke_hub_collections_get_background_image_url_for_pokemon((int) $p['id'], $only_shiny_active)
                : '';
        }
        unset($p);
    }
    $items   = poke_hub_collections_get_items((int) $collection['id']);
    $gen_progress = $pool ? poke_hub_collections_get_generation_progress($pool, $items) : [];
    $can_edit = ($user_id > 0 && (int) $collection['user_id'] === $user_id)
        || ((int) $collection['user_id'] === 0 && !empty($collection['anonymous_ip']) && poke_hub_collections_get_client_ip() === $collection['anonymous_ip']);

    if (!empty($collection['options']['display_mode']) && $collection['options']['display_mode'] === 'select') {
        if (!wp_script_is('select2', 'registered')) {
            wp_register_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
            wp_register_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
        }
        wp_enqueue_style('select2');
        wp_enqueue_script('select2');
    }

    ob_start();
    ?>
    <?php $opts = $collection['options']; ?>
    <?php $collection_category = $collection['category']; $is_specific_category = poke_hub_collections_category_is_specific($collection_category); ?>
    <div class="pokehub-collection-view-wrap collection-view-dashboard me5rine-lab-dashboard" data-collection-id="<?php echo (int) $collection['id']; ?>" data-collection-category="<?php echo esc_attr($collection_category); ?>" data-specific-categories="<?php echo esc_attr(wp_json_encode(poke_hub_collections_get_specific_categories())); ?>" data-can-edit="<?php echo $can_edit ? '1' : '0'; ?>"
         data-share-token="<?php echo esc_attr($collection['share_token'] ?? ''); ?>"
         data-share-url="<?php echo esc_url($canonical_url); ?>"
         data-edit-name="<?php echo esc_attr($collection['name']); ?>"
         data-edit-options="<?php echo esc_attr(wp_json_encode($opts)); ?>"
         data-edit-is-public="<?php echo !empty($collection['is_public']) ? '1' : '0'; ?>">
        <div class="me5rine-lab-dashboard-header pokehub-collection-view-header">
            <div class="pokehub-collection-view-header-left">
                <a href="<?php echo esc_url($collections_base); ?>" class="pokehub-collection-back">← <?php esc_html_e('Back to my collections', 'poke-hub'); ?></a>
            </div>
            <?php
            $owned = count(array_filter($items, function ($s) { return $s === 'owned'; }));
            $total = count($pool);
            ?>
            <div class="pokehub-collection-view-header-main">
                <h2 class="me5rine-lab-title-large pokehub-collection-view-title"><?php echo esc_html($collection['name']); ?></h2>
                <div class="pokehub-collection-stats">
                    <span class="pokehub-collection-progress"><?php echo (int) $owned; ?> / <?php echo (int) $total; ?></span>
                </div>
            </div>
            <?php if ($can_edit) : ?>
            <div class="me5rine-lab-dashboard-header-actions pokehub-collection-view-actions">
                <button type="button" class="pokehub-collections-btn-edit-settings me5rine-lab-form-button-secondary button"><?php esc_html_e('Settings', 'poke-hub'); ?></button>
                <div class="pokehub-collections-reset-inline" data-reset-context="server">
                    <div class="pokehub-collections-reset-step pokehub-collections-reset-step-initial">
                        <button type="button" class="pokehub-collections-btn-reset-launch me5rine-lab-form-button-secondary button"><?php esc_html_e('Reset progress', 'poke-hub'); ?></button>
                    </div>
                    <div class="pokehub-collections-reset-step pokehub-collections-reset-step-confirm" hidden>
                        <p class="me5rine-lab-form-hint pokehub-collections-reset-hint"><?php esc_html_e('All entries will be shown as missing. You can change them again anytime.', 'poke-hub'); ?></p>
                        <div class="pokehub-collections-reset-confirm-row">
                            <button type="button" class="pokehub-collections-btn-reset-apply me5rine-lab-form-button button button-primary"><?php esc_html_e('Clear progress', 'poke-hub'); ?></button>
                            <button type="button" class="pokehub-collections-btn-reset-dismiss me5rine-lab-form-button-secondary button"><?php esc_html_e('Back', 'poke-hub'); ?></button>
                        </div>
                    </div>
                </div>
                <button type="button" class="pokehub-collections-btn-share me5rine-lab-form-button button"><?php esc_html_e('Share', 'poke-hub'); ?></button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($total > 0) : ?>
        <div class="pokehub-collection-status-filters me5rine-lab-form-block" role="group" aria-label="<?php esc_attr_e('Filter Pokémon by status in the grid', 'poke-hub'); ?>">
            <div class="pokehub-collection-status-filters-inner">
                <span class="me5rine-lab-form-label pokehub-collection-status-filters-heading"><?php esc_html_e('Show in grid', 'poke-hub'); ?></span>
                <div class="pokehub-collection-status-filters-checkboxes">
                    <label class="pokehub-collection-status-filter-label"><input type="checkbox" class="pokehub-collection-filter-status" data-filter-status="owned" checked /> <span class="pokehub-collection-legend-dot pokehub-legend-owned" aria-hidden="true"></span> <?php esc_html_e('Owned', 'poke-hub'); ?></label>
                    <label class="pokehub-collection-status-filter-label"><input type="checkbox" class="pokehub-collection-filter-status" data-filter-status="for_trade" checked /> <span class="pokehub-collection-legend-dot pokehub-legend-for-trade" aria-hidden="true"></span> <?php esc_html_e('For trade', 'poke-hub'); ?></label>
                    <label class="pokehub-collection-status-filter-label"><input type="checkbox" class="pokehub-collection-filter-status" data-filter-status="missing" checked /> <span class="pokehub-collection-legend-dot pokehub-legend-missing" aria-hidden="true"></span> <?php esc_html_e('Missing', 'poke-hub'); ?></label>
                </div>
            </div>
            <p class="pokehub-collection-filter-empty-hint me5rine-lab-form-message me5rine-lab-form-message-warning is-hidden" role="status" aria-live="polite"><?php esc_html_e('Select at least one status to see Pokémon in the grid.', 'poke-hub'); ?></p>
        </div>
        <?php endif; ?>

        <?php
        $display_mode = $collection['options']['display_mode'] ?? 'tiles';
        if ($total > 0 && $display_mode !== 'select') :
            ?>
        <p class="pokehub-collection-legend me5rine-lab-form-hint" role="note" aria-hidden="true">
            <span class="pokehub-collection-legend-item"><span class="pokehub-collection-legend-dot pokehub-legend-owned" aria-hidden="true"></span> <?php esc_html_e('Owned', 'poke-hub'); ?></span>
            <span class="pokehub-collection-legend-item"><span class="pokehub-collection-legend-dot pokehub-legend-for-trade" aria-hidden="true"></span> <?php esc_html_e('For trade', 'poke-hub'); ?></span>
            <span class="pokehub-collection-legend-item"><span class="pokehub-collection-legend-dot pokehub-legend-missing" aria-hidden="true"></span> <?php esc_html_e('Missing', 'poke-hub'); ?></span>
            — <?php esc_html_e('Click a tile to cycle status.', 'poke-hub'); ?>
        </p>
        <?php endif; ?>

        <?php if ($total === 0) : ?>
            <p class="pokehub-collection-empty-pool me5rine-lab-form-message me5rine-lab-form-message-warning">
                <?php esc_html_e('No Pokémon in this category at the moment. Check the collection settings (category, options) or Pokémon data import.', 'poke-hub'); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($collection['options']['display_mode']) && $collection['options']['display_mode'] === 'select') : ?>
            <div class="pokehub-collection-multiselect-wrap me5rine-lab-form-block">
                <div class="me5rine-lab-form-field">
                    <label for="pokehub-collection-missing-select" class="me5rine-lab-form-label"><?php esc_html_e('Add missing Pokémon', 'poke-hub'); ?></label>
                    <select id="pokehub-collection-missing-select" class="pokehub-collection-missing-select me5rine-lab-form-select" multiple="multiple" data-placeholder="<?php esc_attr_e('Search by name or #…', 'poke-hub'); ?>" style="width: 100%;"></select>
                </div>
                <button type="button" class="pokehub-collection-multiselect-add me5rine-lab-form-button button button-primary"><?php esc_html_e('Add selection', 'poke-hub'); ?></button>
            </div>
        <?php endif; ?>

        <?php
        $group_by_gen = !empty($opts['group_by_generation']);
        $pool_by_gen  = $group_by_gen ? poke_hub_collections_group_pool_by_generation($pool) : ['' => $pool];
        $gens_collapsed = !empty($opts['generations_collapsed']);
        $is_shiny_collection = in_array($collection_category, ['shiny', 'costume_shiny', 'background_shiny', 'background_shiny_special', 'background_shiny_places'], true);
        ?>
        <div class="pokehub-collection-tiles" data-pool="<?php echo esc_attr(wp_json_encode($pool)); ?>" data-items="<?php echo esc_attr(wp_json_encode($items)); ?>">
            <?php foreach ($pool_by_gen as $gen_key => $gen_pool) : ?>
                <?php
                $g_prog = $gen_progress[ $gen_key ] ?? [ 'owned' => 0, 'total' => count($gen_pool) ];
                if ($gen_key !== '') : ?>
                    <details class="pokehub-collection-generation-block me5rine-lab-form-block" data-generation="<?php echo esc_attr($gen_key); ?>" <?php echo $gens_collapsed ? '' : ' open'; ?>>
                        <summary class="me5rine-lab-title-medium"><?php echo esc_html($gen_key); ?> <span class="pokehub-collection-gen-progress">(<?php echo (int) $g_prog['owned']; ?> / <?php echo (int) $g_prog['total']; ?>)</span></summary>
                        <div class="pokehub-collection-generation-tiles">
                <?php endif; ?>
                <?php foreach ($gen_pool as $p) :
                    $status = $items[$p['id']] ?? 'missing';
                    // Image URL calculée côté PHP via le helper global.
                    $img_src = function_exists('poke_hub_pokemon_get_image_url')
                        ? poke_hub_pokemon_get_image_url((object) $p, ['shiny' => $is_shiny_collection])
                        : '';
                    $bg_url = isset($p['background_image_url']) ? trim((string) $p['background_image_url']) : '';
                    $dex_n = isset($p['dex_number']) ? (int) $p['dex_number'] : 0;
                ?>
                <div class="pokehub-collection-tile" data-pokemon-id="<?php echo (int) $p['id']; ?>" data-status="<?php echo esc_attr($status); ?>" tabindex="0" role="button">
                    <div class="pokehub-collection-tile-figure">
                        <?php if ($bg_url) : ?><div class="pokehub-collection-tile-bg" style="background-image: url(<?php echo esc_url($bg_url); ?>);" aria-hidden="true"></div><?php endif; ?>
                        <?php if ($img_src) : ?><img src="<?php echo esc_url($img_src); ?>" alt="" loading="lazy" /><?php endif; ?>
                    </div>
                    <div class="pokehub-collection-tile-text">
                        <?php if ($dex_n > 0) : ?>
                        <span class="pokehub-collection-tile-dex" aria-label="<?php echo esc_attr(sprintf(/* translators: %d = National Pokédex number */ __('Pokédex #%d', 'poke-hub'), $dex_n)); ?>">#<?php echo (int) $dex_n; ?></span>
                        <?php endif; ?>
                        <span class="pokehub-collection-tile-name"><?php echo esc_html($p['name_fr'] ?: $p['name_en']); ?></span>
                    </div>
                    <span class="pokehub-collection-tile-status pokehub-status-<?php echo esc_attr($status); ?>"></span>
                </div>
                <?php endforeach; ?>
                <?php if ($gen_key !== '') : ?>
                        </div>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($can_edit) : ?>
        <?php
        $edit_include_mega = array_key_exists('include_mega', $opts) ? $opts['include_mega'] : (array_key_exists('exclude_mega', $opts) ? !$opts['exclude_mega'] : true);
        ?>
        <!-- Panneau latéral (drawer) paramètres : différenciant vs popup classique -->
        <div class="pokehub-collections-drawer" id="pokehub-collections-drawer" role="dialog" aria-label="<?php esc_attr_e('Collection settings', 'poke-hub'); ?>" aria-hidden="true">
            <div class="pokehub-collections-drawer-backdrop" id="pokehub-collections-drawer-backdrop"></div>
            <div class="pokehub-collections-drawer-panel">
                <div class="me5rine-lab-card-header pokehub-collections-drawer-header">
                    <h3 class="me5rine-lab-title-medium"><?php esc_html_e('Settings', 'poke-hub'); ?></h3>
                    <button type="button" class="pokehub-collections-drawer-close" id="pokehub-collections-drawer-close" aria-label="<?php esc_attr_e('Close', 'poke-hub'); ?>">&times;</button>
                </div>
                <div class="pokehub-collections-drawer-body">
                    <div class="pokehub-collections-form-edit me5rine-lab-form-block">
                        <div class="me5rine-lab-form-field">
                            <label for="pokehub-edit-collection-name" class="me5rine-lab-form-label"><?php esc_html_e('Name', 'poke-hub'); ?></label>
                            <input type="text" id="pokehub-edit-collection-name" class="me5rine-lab-form-input" value="<?php echo esc_attr($collection['name']); ?>" />
                        </div>
                        <div class="me5rine-lab-form-field">
                            <label><input type="checkbox" id="pokehub-edit-collection-public" <?php checked(!empty($collection['is_public'])); ?> /> <?php esc_html_e('Make this collection public', 'poke-hub'); ?></label>
                        </div>
                        <div class="me5rine-lab-form-field">
                            <label for="pokehub-edit-card-background-image" class="me5rine-lab-form-label"><?php esc_html_e('Cover image (card on collections list)', 'poke-hub'); ?></label>
                            <input type="url" id="pokehub-edit-card-background-image" class="me5rine-lab-form-input" placeholder="https://…" value="<?php echo esc_attr($opts['card_background_image_url'] ?? ''); ?>" />
                            <p class="me5rine-lab-form-description"><?php esc_html_e('Optional. Leave empty to use the default image for this collection type.', 'poke-hub'); ?></p>
                        </div>
                        <fieldset class="me5rine-lab-form-block">
                            <legend class="me5rine-lab-form-label"><?php esc_html_e('Content to show', 'poke-hub'); ?></legend>
                            <?php if ($is_specific_category) : ?>
                                <p class="pokehub-collections-options-specific-hint me5rine-lab-form-hint"><?php echo esc_html(sprintf(__('This collection only shows %s. No extra form filters.', 'poke-hub'), $categories[$collection_category] ?? $collection_category)); ?></p>
                                <label><input type="checkbox" id="pokehub-edit-one-per-species" <?php checked(!empty($opts['one_per_species'])); ?> /> <?php esc_html_e('One entry per species (e.g. one Unown)', 'poke-hub'); ?></label>
                            <?php else : ?>
                                <label><input type="checkbox" id="pokehub-edit-include-costumes" <?php checked(!empty($opts['include_costumes'])); ?> /> <?php esc_html_e('Show costumed Pokémon', 'poke-hub'); ?></label>
                                <label><input type="checkbox" id="pokehub-edit-include-mega" <?php checked($edit_include_mega); ?> /> <?php esc_html_e('Show Mega evolutions', 'poke-hub'); ?></label>
                                <label><input type="checkbox" id="pokehub-edit-include-gigantamax" <?php checked(!empty($opts['include_gigantamax'])); ?> /> <?php esc_html_e('Show Gigantamax', 'poke-hub'); ?></label>
                                <label><input type="checkbox" id="pokehub-edit-include-dynamax" <?php checked(!empty($opts['include_dynamax'])); ?> /> <?php esc_html_e('Show Dynamax', 'poke-hub'); ?></label>
                                <label><input type="checkbox" id="pokehub-edit-one-per-species" <?php checked(!empty($opts['one_per_species'])); ?> /> <?php esc_html_e('One entry per species (e.g. one Unown)', 'poke-hub'); ?></label>
                            <?php endif; ?>
                        </fieldset>
                        <fieldset class="me5rine-lab-form-block">
                            <legend class="me5rine-lab-form-label"><?php esc_html_e('Display', 'poke-hub'); ?></legend>
                            <label><input type="checkbox" id="pokehub-edit-group-by-generation" <?php checked(!empty($opts['group_by_generation'])); ?> /> <?php esc_html_e('Group by region / generation', 'poke-hub'); ?></label>
                            <label><input type="checkbox" id="pokehub-edit-generations-collapsed" <?php checked(!empty($opts['generations_collapsed'])); ?> /> <?php esc_html_e('Collapse generations by default', 'poke-hub'); ?></label>
                            <label><input type="radio" name="pokehub-edit-collection-display" value="tiles" <?php checked(($opts['display_mode'] ?? 'tiles') === 'tiles'); ?> /> <?php esc_html_e('Tiles (tap to mark owned)', 'poke-hub'); ?></label>
                            <label><input type="radio" name="pokehub-edit-collection-display" value="select" <?php checked(($opts['display_mode'] ?? '') === 'select'); ?> /> <?php esc_html_e('List with missing selector', 'poke-hub'); ?></label>
                        </fieldset>
                    </div>
                </div>
                <div class="pokehub-collections-drawer-footer">
                    <button type="button" class="pokehub-collections-modal-edit-save me5rine-lab-form-button button button-primary" id="pokehub-collections-drawer-save"><?php esc_html_e('Save', 'poke-hub'); ?></button>
                    <button type="button" class="pokehub-collections-btn-delete-collection me5rine-lab-form-button-remove button"><?php esc_html_e('Delete collection', 'poke-hub'); ?></button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});
