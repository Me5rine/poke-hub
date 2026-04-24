<?php
// modules/collections/public/collections-shortcode.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bloc : phrases de filtre collables dans l’inventaire Pokémon GO (générées côté client).
 *
 * @param string $instance_suffix Suffixe unique pour les id HTML (évite doublons si plusieurs blocs ; labels for= alignés).
 */
function poke_hub_collections_output_pogo_search_block($instance_suffix = '') {
    $instance_suffix = is_string($instance_suffix) ? $instance_suffix : '';
    $uid = $instance_suffix !== '' ? sanitize_title($instance_suffix) : 'pogo-' . wp_unique_id('');
    if ($uid === '') {
        $uid = 'pogo-' . wp_unique_id('');
    }
    ?>
    <details class="pokehub-collection-pogo-search me5rine-lab-form-block" id="pokehub-collection-pogo-search-<?php echo esc_attr($uid); ?>">
        <summary class="pokehub-collection-pogo-search-summary"><?php esc_html_e('Pokémon GO: your hunt strings, ready to paste', 'poke-hub'); ?></summary>
        <div class="pokehub-collection-pogo-search-body">
            <div class="pokehub-collection-pogo-search-toolbar me5rine-lab-form-block">
                <div class="me5rine-lab-form-field pokehub-collection-pogo-search-toolbar-field pokehub-collection-pogo-search-toolbar-field--status">
                    <label for="pokehub-pogo-search-status-<?php echo esc_attr($uid); ?>" class="me5rine-lab-form-label"><?php esc_html_e('List for status', 'poke-hub'); ?></label>
                    <select id="pokehub-pogo-search-status-<?php echo esc_attr($uid); ?>" class="me5rine-lab-form-select no-select2 pokehub-pogo-search-status">
                        <option value="missing"><?php esc_html_e('Missing', 'poke-hub'); ?></option>
                        <option value="owned"><?php esc_html_e('Owned', 'poke-hub'); ?></option>
                        <option value="for_trade"><?php esc_html_e('For trade', 'poke-hub'); ?></option>
                    </select>
                </div>
                <div class="me5rine-lab-form-field pokehub-collection-pogo-search-toolbar-field pokehub-collection-pogo-search-toolbar-field--token">
                    <label for="pokehub-pogo-search-token-mode-<?php echo esc_attr($uid); ?>" class="me5rine-lab-form-label"><?php esc_html_e('Names or Pokédex #', 'poke-hub'); ?></label>
                    <select id="pokehub-pogo-search-token-mode-<?php echo esc_attr($uid); ?>" class="me5rine-lab-form-select no-select2 pokehub-pogo-search-token-mode">
                        <option value="name_fr" selected><?php esc_html_e('French names', 'poke-hub'); ?></option>
                        <option value="name_en"><?php esc_html_e('English names', 'poke-hub'); ?></option>
                        <option value="number"><?php esc_html_e('National Pokédex #', 'poke-hub'); ?></option>
                    </select>
                </div>
            </div>
            <p class="pokehub-collection-pogo-search-hint-refresh me5rine-lab-form-hint" role="status" aria-hidden="true"></p>
            <div class="pokehub-pogo-search-groups" id="pokehub-pogo-search-groups-<?php echo esc_attr($uid); ?>" aria-live="polite"></div>
        </div>
    </details>
    <?php
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
                <button type="button" class="pokehub-collections-dismiss-banner me5rine-lab-form-button me5rine-lab-form-button-secondary button"><?php esc_html_e('Close', 'poke-hub'); ?></button>
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
                                <a href="<?php echo esc_url($col_edit_url); ?>" class="pokehub-collections-card-btn pokehub-collections-card-btn-settings me5rine-lab-form-button me5rine-lab-form-button-secondary" title="<?php esc_attr_e('Settings', 'poke-hub'); ?>"><span class="me5rine-lab-sr-only"><?php esc_html_e('Settings', 'poke-hub'); ?></span></a>
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
                                <fieldset class="me5rine-lab-form-block" id="pokehub-collection-content-filter-wrap">
                                    <legend class="me5rine-lab-form-label"><?php esc_html_e('Content filter', 'poke-hub'); ?></legend>
                                    <div class="pokehub-collections-options-additive" id="pokehub-collection-options-additive">
                                        <label data-collections-control="include_gender"><input type="checkbox" id="pokehub-collection-include-gender" checked /> <?php esc_html_e('Include sexual dimorphism', 'poke-hub'); ?></label>
                                        <label data-collections-control="include_both_sexes_collector"><input type="checkbox" id="pokehub-collection-both-sexes-collector" /> <?php esc_html_e('Include male and female', 'poke-hub'); ?></label>
                                        <label data-collections-control="include_regional_forms"><input type="checkbox" id="pokehub-collection-include-regional-forms" checked /> <?php esc_html_e('Include regional forms', 'poke-hub'); ?></label>
                                        <div class="pokehub-collections-pool-special-species" role="group" aria-label="<?php esc_attr_e('Legendary, Mythical, Ultra Beasts', 'poke-hub'); ?>">
                                            <label><input type="checkbox" id="pokehub-collection-include-legendary" checked /> <?php esc_html_e('Include Legendary', 'poke-hub'); ?></label>
                                            <label><input type="checkbox" id="pokehub-collection-include-mythical" checked /> <?php esc_html_e('Include Mythical', 'poke-hub'); ?></label>
                                            <label><input type="checkbox" id="pokehub-collection-include-ultra-beast" checked /> <?php esc_html_e('Include Ultra Beast', 'poke-hub'); ?></label>
                                        </div>
                                        <label data-collections-control="include_mega"><input type="checkbox" id="pokehub-collection-include-mega" checked /> <?php esc_html_e('Include Mega evolutions', 'poke-hub'); ?></label>
                                        <label data-collections-control="include_gigantamax"><input type="checkbox" id="pokehub-collection-include-gigantamax" checked /> <?php esc_html_e('Include Gigantamax', 'poke-hub'); ?></label>
                                        <label data-collections-control="include_dynamax"><input type="checkbox" id="pokehub-collection-include-dynamax" checked /> <?php esc_html_e('Include Dynamax', 'poke-hub'); ?></label>
                                        <label data-collections-control="include_special_attacks"><input type="checkbox" id="pokehub-collection-include-special-attacks" /> <?php esc_html_e('Include special attacks', 'poke-hub'); ?></label>
                                        <label data-collections-control="include_backgrounds"><input type="checkbox" id="pokehub-collection-include-backgrounds" checked /> <?php esc_html_e('Inclure les fonds d’arrière-plan (Pokémon GO, in-game : backgrounds)', 'poke-hub'); ?></label>
                                        <label data-collections-control="include_costumes"><input type="checkbox" id="pokehub-collection-include-costumes" checked /> <?php esc_html_e('Include costumed Pokémon', 'poke-hub'); ?></label>
                                        <label data-collections-control="include_baby_pokemon"><input type="checkbox" id="pokehub-collection-include-babies" checked /> <?php esc_html_e('Include baby Pokémon', 'poke-hub'); ?></label>
                                    </div>
                                </fieldset>
                                <div class="me5rine-lab-form-field is-hidden" id="pokehub-collection-only-shiny-wrap">
                                    <label><input type="checkbox" id="pokehub-collection-only-shiny" /> <?php esc_html_e('Custom list: only include Pokémon that can be Shiny in Pokémon GO', 'poke-hub'); ?></label>
                                </div>
                                <fieldset class="me5rine-lab-form-block pokehub-collections-create-pool-show-fieldset" id="pokehub-collection-pool-show-only-wrap">
                                    <legend class="me5rine-lab-form-label"><?php esc_html_e('Include only', 'poke-hub'); ?></legend>
                                    <p class="me5rine-lab-form-hint" style="margin-top:0;"><?php esc_html_e('Restrict which Pokémon appear in the pool (one choice).', 'poke-hub'); ?></p>
                                    <div class="me5rine-lab-form-field">
                                        <label for="pokehub-collection-pool-show-only" class="me5rine-lab-form-label"><?php esc_html_e('Pool restriction', 'poke-hub'); ?></label>
                                        <select id="pokehub-collection-pool-show-only" class="me5rine-lab-form-select">
                                            <option value=""><?php esc_html_e('No extra restriction', 'poke-hub'); ?></option>
                                            <option value="final"><?php esc_html_e('Only final evolutions (Pokémon that do not evolve further in GO)', 'poke-hub'); ?></option>
                                            <option value="baby" data-collections-control="pool_option_baby"><?php esc_html_e('Only baby Pokémon', 'poke-hub'); ?></option>
                                            <option value="special_all" data-collections-control="pool_option_special_all"><?php esc_html_e('Only Legendary, Mythical & Ultra Beasts', 'poke-hub'); ?></option>
                                            <option value="legendary"><?php esc_html_e('Only Legendary', 'poke-hub'); ?></option>
                                            <option value="mythical"><?php esc_html_e('Only Mythical', 'poke-hub'); ?></option>
                                            <option value="ultra_beast"><?php esc_html_e('Only Ultra Beast', 'poke-hub'); ?></option>
                                            <option value="special_attacks"><?php esc_html_e('Only Pokémon with special attacks', 'poke-hub'); ?></option>
                                        </select>
                                        <p class="me5rine-lab-form-description"><?php esc_html_e('Filtering by special attacks will apply fully once attack data is wired to the pool.', 'poke-hub'); ?></p>
                                    </div>
                                </fieldset>
                                <fieldset class="me5rine-lab-form-block">
                                    <legend class="me5rine-lab-form-label"><?php esc_html_e('Display', 'poke-hub'); ?></legend>
                                    <label><input type="checkbox" id="pokehub-collection-include-national" checked /> <?php esc_html_e('Show Pokédex numbers', 'poke-hub'); ?></label>
                                    <label><input type="checkbox" id="pokehub-collection-one-per-species" /> <?php esc_html_e('Show only one entry per species', 'poke-hub'); ?></label>
                                    <label><input type="checkbox" id="pokehub-collection-group-by-generation" checked /> <?php esc_html_e('Group by generation', 'poke-hub'); ?></label>
                                    <label><input type="checkbox" id="pokehub-collection-generations-collapsed" /> <?php esc_html_e('Collapse generations by default', 'poke-hub'); ?></label>
                                    <label><input type="checkbox" id="pokehub-collection-add-selectors" /> <?php esc_html_e('Add selectors to add Pokémon', 'poke-hub'); ?></label>
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
                    <button type="button" class="pokehub-collections-modal-cancel me5rine-lab-form-button me5rine-lab-form-button-secondary button"><?php esc_html_e('Cancel', 'poke-hub'); ?></button>
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
            <header class="me5rine-lab-dashboard-header pokehub-collection-view-header pokehub-collection-view-header--no-cover">
                <div class="pokehub-collection-header-inner pokehub-collection-header-inner--local">
                    <div class="pokehub-collection-view-header-left">
                        <a href="<?php echo esc_url(remove_query_arg(['collection', 'id', 'view', 'local'])); ?>"
                            class="pokehub-collection-back me5rine-lab-form-button me5rine-lab-form-button-secondary button"
                            title="<?php echo esc_attr__('Back to the collections list', 'poke-hub'); ?>">
                            <span class="pokehub-collection-back-text"><?php esc_html_e('Back', 'poke-hub'); ?></span>
                        </a>
                    </div>
                    <div class="pokehub-collection-view-header-main">
                        <h2 class="me5rine-lab-title-large pokehub-collection-view-title pokehub-collection-local-title"><?php esc_html_e('Loading…', 'poke-hub'); ?></h2>
                        <div class="pokehub-collection-stats pokehub-collection-local-stats" aria-label="<?php esc_attr_e('Collection progress', 'poke-hub'); ?>">—</div>
                    </div>
                    <div class="me5rine-lab-dashboard-header-actions pokehub-collection-view-actions">
                        <div class="pokehub-collections-reset-inline" data-reset-context="local">
                            <div class="pokehub-collections-reset-step pokehub-collections-reset-step-initial">
                                <button type="button" class="pokehub-collections-btn-reset-launch me5rine-lab-form-button me5rine-lab-form-button-secondary button" disabled><?php esc_html_e('Reset progress', 'poke-hub'); ?></button>
                            </div>
                            <div class="pokehub-collections-reset-step pokehub-collections-reset-step-confirm" hidden>
                                <p class="me5rine-lab-form-hint pokehub-collections-reset-hint"><?php esc_html_e('All entries will be shown as missing. You can change them again anytime.', 'poke-hub'); ?></p>
                                <div class="pokehub-collections-reset-confirm-row">
                                    <button type="button" class="pokehub-collections-btn-reset-apply me5rine-lab-form-button button button-primary"><?php esc_html_e('Clear progress', 'poke-hub'); ?></button>
                                    <button type="button" class="pokehub-collections-btn-reset-dismiss me5rine-lab-form-button me5rine-lab-form-button-secondary button"><?php esc_html_e('Back', 'poke-hub'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="pokehub-collection-status-filters me5rine-lab-form-block" role="group" aria-label="<?php esc_attr_e('Filter Pokémon by status in the grid', 'poke-hub'); ?>">
                <div class="pokehub-collection-status-filters-inner">
                    <span class="me5rine-lab-form-label pokehub-collection-status-filters-heading"><?php esc_html_e('Include in grid', 'poke-hub'); ?></span>
                    <div class="pokehub-collection-status-filters-checkboxes">
                        <label class="pokehub-collection-status-filter-label"><input type="checkbox" class="pokehub-collection-filter-status" data-filter-status="owned" checked /> <span class="pokehub-collection-legend-dot pokehub-legend-owned" aria-hidden="true"></span> <?php esc_html_e('Owned', 'poke-hub'); ?></label>
                        <label class="pokehub-collection-status-filter-label"><input type="checkbox" class="pokehub-collection-filter-status" data-filter-status="for_trade" checked /> <span class="pokehub-collection-legend-dot pokehub-legend-for-trade" aria-hidden="true"></span> <?php esc_html_e('For trade', 'poke-hub'); ?></label>
                        <label class="pokehub-collection-status-filter-label"><input type="checkbox" class="pokehub-collection-filter-status" data-filter-status="missing" checked /> <span class="pokehub-collection-legend-dot pokehub-legend-missing" aria-hidden="true"></span> <?php esc_html_e('Missing', 'poke-hub'); ?></label>
                    </div>
                </div>
                <p class="pokehub-collection-filter-empty-hint me5rine-lab-form-message me5rine-lab-form-message-warning is-hidden" role="status" aria-live="polite"><?php esc_html_e('Select at least one status to see Pokémon in the grid.', 'poke-hub'); ?></p>
            </div>
            <?php poke_hub_collections_output_pogo_search_block('local-' . $slug); ?>
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

    $raw_collection_opts = is_array($collection['options'] ?? null) ? $collection['options'] : [];
    $opts = array_merge(poke_hub_collections_default_options(), $raw_collection_opts);
    if (array_key_exists('include_forms', $raw_collection_opts)) {
        $opts = poke_hub_collections_merge_legacy_include_forms_option($opts);
    }
    $opts = poke_hub_collections_derive_gender_symbol_option($opts);

    $pool     = poke_hub_collections_get_pool($collection['category'], $opts);
    $header_cover_url = function_exists('poke_hub_collections_get_card_background_image_url')
        ? poke_hub_collections_get_card_background_image_url($collection)
        : '';
    $header_has_cover = is_string($header_cover_url) && $header_cover_url !== '';
    $category = $collection['category'];
    $items   = poke_hub_collections_get_items((int) $collection['id']);
    if (in_array($category, ['background', 'background_shiny', 'background_special', 'background_places', 'background_shiny_special', 'background_shiny_places'], true)) {
        $only_shiny_active = in_array($category, ['background_shiny', 'background_shiny_special', 'background_shiny_places'], true);
        foreach ($pool as &$p) {
            if ( ! empty( $p['synthetic_go_background'] ) && ! empty( $p['background_image_url'] ) ) {
                continue;
            }
            if ( function_exists( 'poke_hub_collections_get_background_image_url_for_pool_row' ) ) {
                $p['background_image_url'] = poke_hub_collections_get_background_image_url_for_pool_row( $p, $category, $only_shiny_active );
            } else {
                $for_bg = function_exists( 'poke_hub_collections_pool_row_pokemon_id_for_go_background_link' )
                    ? poke_hub_collections_pool_row_pokemon_id_for_go_background_link( $p )
                    : ( ! empty( $p['synthetic_sex_base_id'] ) ? (int) $p['synthetic_sex_base_id'] : (int) ( $p['id'] ?? 0 ) );
                $p['background_image_url'] = function_exists( 'poke_hub_collections_get_background_image_url_for_pokemon' )
                    ? poke_hub_collections_get_background_image_url_for_pokemon( $for_bg, $only_shiny_active, 'base' )
                    : '';
            }
        }
        unset($p);
    }
    $is_shiny_collection = in_array($category, ['shiny', 'costume_shiny', 'background_shiny', 'background_shiny_special', 'background_shiny_places'], true)
        || ($category === 'custom' && !empty($opts['only_shiny']));
    if (is_array($pool) && $pool !== [] && function_exists('poke_hub_collections_get_image_sources_for_pool_row')) {
        foreach ($pool as &$p_img) {
            $srcs = poke_hub_collections_get_image_sources_for_pool_row($p_img, $is_shiny_collection);
            $p_img['image_url']        = (string) ($srcs['primary'] !== '' ? $srcs['primary'] : $srcs['fallback']);
            if ($srcs['primary'] !== '' && $srcs['fallback'] !== '' && $srcs['primary'] !== $srcs['fallback']) {
                $p_img['image_url_fallback'] = $srcs['fallback'];
            }
        }
        unset($p_img);
    }
    $gen_progress = $pool ? poke_hub_collections_get_generation_progress($pool, $items) : [];
    $items_resolved = function_exists('poke_hub_collections_resolved_items_map')
        ? poke_hub_collections_resolved_items_map($items, is_array($pool) ? $pool : [])
        : $items;
    $can_edit = ($user_id > 0 && (int) $collection['user_id'] === $user_id)
        || ((int) $collection['user_id'] === 0 && !empty($collection['anonymous_ip']) && poke_hub_collections_get_client_ip() === $collection['anonymous_ip']);

    $display_mode_assets = $opts['display_mode'] ?? 'tiles';
    if (in_array($display_mode_assets, ['select', 'tiles_select'], true)) {
        if (!wp_script_is('select2', 'registered')) {
            wp_register_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
            wp_register_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
        }
        wp_enqueue_style('select2');
        wp_enqueue_script('select2');
    }

    ob_start();
    ?>
    <?php $collection_category = $collection['category']; $is_specific_category = poke_hub_collections_category_is_specific($collection_category); ?>
    <div class="pokehub-collection-view-wrap collection-view-dashboard me5rine-lab-dashboard" data-collection-id="<?php echo (int) $collection['id']; ?>" data-collection-category="<?php echo esc_attr($collection_category); ?>" data-specific-categories="<?php echo esc_attr(wp_json_encode(poke_hub_collections_get_specific_categories())); ?>" data-can-edit="<?php echo $can_edit ? '1' : '0'; ?>"
         data-share-token="<?php echo esc_attr($collection['share_token'] ?? ''); ?>"
         data-share-url="<?php echo esc_url($canonical_url); ?>"
         data-edit-name="<?php echo esc_attr($collection['name']); ?>"
         data-edit-options="<?php echo esc_attr(wp_json_encode($opts)); ?>"
         data-edit-is-public="<?php echo !empty($collection['is_public']) ? '1' : '0'; ?>">
        <?php
        $owned = count(array_filter($items_resolved, function ($s) { return $s === 'owned'; }));
        $total = count($pool);
        $progress_aria = sprintf(
            /* translators: 1: owned count, 2: total count */
            __('Progress: %1$d out of %2$d Pokémon owned', 'poke-hub'),
            (int) $owned,
            (int) $total
        );
        ?>
        <header class="me5rine-lab-dashboard-header pokehub-collection-view-header<?php echo $header_has_cover ? ' pokehub-collection-view-header--has-cover' : ' pokehub-collection-view-header--no-cover'; ?>">
            <?php if ($header_has_cover) : ?>
                <div class="pokehub-collection-header-bg" style="background-image: url(<?php echo esc_url($header_cover_url); ?>);"></div>
                <div class="pokehub-collection-header-scrim" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="pokehub-collection-header-inner<?php echo $can_edit ? '' : ' pokehub-collection-header-inner--two-cols'; ?>">
            <div class="pokehub-collection-view-header-left">
                <a href="<?php echo esc_url($collections_base); ?>"
                    class="pokehub-collection-back me5rine-lab-form-button me5rine-lab-form-button-secondary button"
                    title="<?php echo esc_attr__('Back to the collections list', 'poke-hub'); ?>">
                    <span class="pokehub-collection-back-text"><?php esc_html_e('Back', 'poke-hub'); ?></span>
                </a>
            </div>
            <div class="pokehub-collection-view-header-main">
                <h2 class="me5rine-lab-title-large pokehub-collection-view-title"><?php echo esc_html($collection['name']); ?></h2>
                <div class="pokehub-collection-stats">
                    <span class="pokehub-collection-progress-badge" aria-label="<?php echo esc_attr($progress_aria); ?>">
                        <span class="pokehub-collection-progress-n"><?php echo (int) $owned; ?></span>
                        <span class="pokehub-collection-progress-sep" aria-hidden="true">/</span>
                        <span class="pokehub-collection-progress-total"><?php echo (int) $total; ?></span>
                    </span>
                </div>
            </div>
            <?php if ($can_edit) : ?>
            <div class="me5rine-lab-dashboard-header-actions pokehub-collection-view-actions">
                <button type="button" class="pokehub-collections-btn-edit-settings me5rine-lab-form-button me5rine-lab-form-button-secondary button"><?php esc_html_e('Settings', 'poke-hub'); ?></button>
                <div class="pokehub-collections-reset-inline" data-reset-context="server">
                    <div class="pokehub-collections-reset-step pokehub-collections-reset-step-initial">
                        <button type="button" class="pokehub-collections-btn-reset-launch me5rine-lab-form-button me5rine-lab-form-button-secondary button"><?php esc_html_e('Reset progress', 'poke-hub'); ?></button>
                    </div>
                    <div class="pokehub-collections-reset-step pokehub-collections-reset-step-confirm" hidden>
                        <p class="me5rine-lab-form-hint pokehub-collections-reset-hint"><?php esc_html_e('All entries will be shown as missing. You can change them again anytime.', 'poke-hub'); ?></p>
                        <div class="pokehub-collections-reset-confirm-row">
                            <button type="button" class="pokehub-collections-btn-reset-apply me5rine-lab-form-button button button-primary"><?php esc_html_e('Clear progress', 'poke-hub'); ?></button>
                            <button type="button" class="pokehub-collections-btn-reset-dismiss me5rine-lab-form-button me5rine-lab-form-button-secondary button"><?php esc_html_e('Back', 'poke-hub'); ?></button>
                        </div>
                    </div>
                </div>
                <button type="button" class="pokehub-collections-btn-share me5rine-lab-form-button button"><?php esc_html_e('Share', 'poke-hub'); ?></button>
            </div>
            <?php endif; ?>
            </div>
        </header>

        <?php if ($total > 0) : ?>
        <div class="pokehub-collection-status-filters me5rine-lab-form-block" role="group" aria-label="<?php esc_attr_e('Filter Pokémon by status in the grid', 'poke-hub'); ?>">
            <div class="pokehub-collection-status-filters-inner">
                <span class="me5rine-lab-form-label pokehub-collection-status-filters-heading"><?php esc_html_e('Include in grid', 'poke-hub'); ?></span>
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
        if ($total > 0) {
            poke_hub_collections_output_pogo_search_block('c' . (int) $collection['id']);
        }
        ?>

        <?php
        $display_mode = $opts['display_mode'] ?? 'tiles';
        if ($total > 0 && in_array($display_mode, ['tiles', 'tiles_select'], true)) :
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

        <?php if (!empty($opts['display_mode']) && in_array($opts['display_mode'], ['select', 'tiles_select'], true)) : ?>
            <div class="pokehub-collection-multiselect-wrap me5rine-lab-form-block">
                <div class="pokehub-collection-multiselect-col pokehub-collection-multiselect-col--owned">
                    <label for="pokehub-collection-missing-select" class="pokehub-collection-multiselect-label me5rine-lab-form-label"><?php esc_html_e('Add as owned (from missing)', 'poke-hub'); ?></label>
                    <div class="pokehub-collection-multiselect-row">
                        <div class="pokehub-collection-multiselect-field">
                            <select id="pokehub-collection-missing-select" class="pokehub-collection-missing-select me5rine-lab-form-select" multiple="multiple" data-placeholder="<?php esc_attr_e('Search by name or #…', 'poke-hub'); ?>" data-select-purpose="owned"></select>
                        </div>
                        <button type="button" class="pokehub-collection-multiselect-add me5rine-lab-form-button button button-primary" data-add-status="owned"><?php esc_html_e('Add as owned', 'poke-hub'); ?></button>
                    </div>
                </div>
                <div class="pokehub-collection-multiselect-col pokehub-collection-multiselect-col--trade">
                    <label for="pokehub-collection-fortrade-select" class="pokehub-collection-multiselect-label me5rine-lab-form-label"><?php esc_html_e('Mark as for trade (from missing)', 'poke-hub'); ?></label>
                    <div class="pokehub-collection-multiselect-row">
                        <div class="pokehub-collection-multiselect-field">
                            <select id="pokehub-collection-fortrade-select" class="pokehub-collection-fortrade-select me5rine-lab-form-select" multiple="multiple" data-placeholder="<?php esc_attr_e('Search by name or #…', 'poke-hub'); ?>" data-select-purpose="for_trade"></select>
                        </div>
                        <button type="button" class="pokehub-collection-fortrade-add me5rine-lab-form-button me5rine-lab-form-button-secondary button" data-add-status="for_trade"><?php esc_html_e('Set as for trade', 'poke-hub'); ?></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $group_by_gen = !empty($opts['group_by_generation']);
        $pool_by_gen  = $group_by_gen ? poke_hub_collections_group_pool_by_generation($pool) : ['' => $pool];
        $gens_collapsed = !empty($opts['generations_collapsed']);
        ?>
        <div class="pokehub-collection-tiles" data-pool="<?php echo esc_attr(wp_json_encode($pool)); ?>" data-items="<?php echo esc_attr(wp_json_encode($items_resolved)); ?>">
            <?php foreach ($pool_by_gen as $gen_key => $gen_pool) : ?>
                <?php
                $g_prog = $gen_progress[ $gen_key ] ?? [ 'owned' => 0, 'total' => count($gen_pool) ];
                if ($gen_key !== '') : ?>
                    <details class="pokehub-collection-generation-block me5rine-lab-form-block" data-generation="<?php echo esc_attr($gen_key); ?>" <?php echo $gens_collapsed ? '' : ' open'; ?>>
                        <summary class="me5rine-lab-title-medium"><?php echo esc_html($gen_key); ?> <span class="pokehub-collection-gen-progress">(<?php echo (int) $g_prog['owned']; ?> / <?php echo (int) $g_prog['total']; ?>)</span></summary>
                        <div class="pokehub-collection-generation-tiles">
                <?php endif; ?>
                <?php foreach ($gen_pool as $p) :
                    $status = $items_resolved[ $p['id'] ] ?? 'missing';
                    $img_src = isset($p['image_url']) && (string) $p['image_url'] !== ''
                        ? trim((string) $p['image_url'])
                        : (function_exists('poke_hub_collections_get_image_url_for_pool_row')
                            ? poke_hub_collections_get_image_url_for_pool_row($p, $is_shiny_collection)
                            : (function_exists('poke_hub_pokemon_get_image_url') ? poke_hub_pokemon_get_image_url((object) $p, ['shiny' => $is_shiny_collection]) : ''));
                    $img_src_fallback = isset($p['image_url_fallback']) ? trim((string) $p['image_url_fallback']) : '';
                    $bg_url = isset($p['background_image_url']) ? trim((string) $p['background_image_url']) : '';
                    $dex_n = isset($p['dex_number']) ? (int) $p['dex_number'] : 0;
                    $primary_name = trim((string) ($p['name_fr'] ?: $p['name_en']));
                    $form_line      = trim((string) ($p['form_label'] ?? ''));
                    $form_slug_low  = strtolower(trim((string) ($p['form_slug'] ?? '')));
                    $line_is_normal = $form_line !== '' && in_array(strtolower($form_line), ['normal', 'normale'], true);
                    $slug_is_base   = $form_slug_low !== '' && in_array($form_slug_low, ['normal', 'form-normal', 'form_normal'], true);
                    $show_form_line = ( $form_line !== ''
                        && ! $line_is_normal
                        && ! $slug_is_base
                        && strcasecmp($form_line, $primary_name) !== 0
                        && stripos($primary_name, $form_line) === false
                    ) || ( ! empty( $p['synthetic_go_background'] ) && $form_line !== '' );
                ?>
                <div class="pokehub-collection-tile" data-pokemon-id="<?php echo (int) $p['id']; ?>" data-status="<?php echo esc_attr($status); ?>" tabindex="0" role="button">
                    <div class="pokehub-collection-tile-figure">
                        <?php if ($bg_url) : ?><div class="pokehub-collection-tile-bg" style="background-image: url(<?php echo esc_url($bg_url); ?>);" aria-hidden="true"></div><?php endif; ?>
                        <?php if ($img_src) : ?><img class="pokehub-collection-sprite" src="<?php echo esc_url($img_src); ?>" alt="" loading="lazy"<?php
                        if (function_exists('poke_hub_pokemon_get_sprite_image_fallback_attr_html')) {
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans poke_hub_pokemon_get_sprite_image_fallback_attr_html
                            echo poke_hub_pokemon_get_sprite_image_fallback_attr_html($img_src, $img_src_fallback);
                        }
                        ?> /><?php endif; ?>
                    </div>
                    <div class="pokehub-collection-tile-text">
                        <?php if (!empty($opts['include_national_dex']) && $dex_n > 0) : ?>
                        <span class="pokehub-collection-tile-dex" aria-label="<?php echo esc_attr(sprintf(/* translators: %d = National Pokédex number */ __('Pokédex #%d', 'poke-hub'), $dex_n)); ?>">#<?php echo (int) $dex_n; ?></span>
                        <?php endif; ?>
                        <span class="pokehub-collection-tile-line pokehub-collection-tile-line--name">
                            <span class="pokehub-collection-tile-name-stack">
                                <span class="pokehub-collection-tile-name-row">
                                    <span class="pokehub-collection-tile-name"><?php echo esc_html($primary_name); ?></span>
                                    <?php
                                    $g_sym  = (string) ($p['gender_display'] ?? '');
                                    $is_sex = ! empty($p['synthetic_sex_collector']);
                            $show_g_sym = $g_sym !== ''
                                && (
                                    ( $is_sex && ( ! empty($opts['include_both_sexes_collector']) || ! empty($opts['include_gender']) ) )
                                    || ( ! $is_sex && ! empty($opts['include_gender']) )
                                );
                                    if ( $show_g_sym ) :
                                        $g_label = '♀' === $g_sym ? __( 'Female', 'poke-hub' ) : __( 'Male', 'poke-hub' );
                                        ?>
                                    <span class="pokehub-collection-tile-gender" title="<?php echo esc_attr($g_label); ?>" aria-label="<?php echo esc_attr($g_label); ?>"><?php echo esc_html($g_sym); ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($show_form_line) : ?>
                                <span class="pokehub-collection-tile-form-line"><?php echo esc_html($form_line); ?></span>
                                <?php endif; ?>
                            </span>
                        </span>
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
        $edit_include_mega     = array_key_exists('include_mega', $opts) ? $opts['include_mega'] : (array_key_exists('exclude_mega', $opts) ? !$opts['exclude_mega'] : true);
        $pool_show_only_edit   = poke_hub_collections_normalize_pool_show_only($opts);
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
                        <?php
                        $edit_settings_hidden = $is_specific_category ? [] : poke_hub_collections_settings_hidden_control_keys($collection_category);
                        ?>
                        <?php if (!$is_specific_category) : ?>
                        <fieldset class="me5rine-lab-form-block">
                            <legend class="me5rine-lab-form-label"><?php esc_html_e('Content filter', 'poke-hub'); ?></legend>
                            <?php if (!in_array('include_gender', $edit_settings_hidden, true)) : ?>
                            <label data-collections-control="include_gender"><input type="checkbox" id="pokehub-edit-include-gender" <?php checked(!empty($opts['include_gender'])); ?> /> <?php esc_html_e('Include sexual dimorphism', 'poke-hub'); ?></label>
                            <?php endif; ?>
                            <?php if (!in_array('include_both_sexes_collector', $edit_settings_hidden, true)) : ?>
                            <label data-collections-control="include_both_sexes_collector"><input type="checkbox" id="pokehub-edit-both-sexes-collector" <?php checked(!empty($opts['include_both_sexes_collector'])); ?> /> <?php esc_html_e('Include male and female', 'poke-hub'); ?></label>
                            <?php endif; ?>
                            <?php if (!in_array('include_regional_forms', $edit_settings_hidden, true)) : ?>
                            <label data-collections-control="include_regional_forms"><input type="checkbox" id="pokehub-edit-include-regional-forms" <?php checked(!isset($opts['include_regional_forms']) || !empty($opts['include_regional_forms'])); ?> /> <?php esc_html_e('Include regional forms', 'poke-hub'); ?></label>
                            <?php endif; ?>
                            <div class="pokehub-collections-pool-special-species" role="group" aria-label="<?php esc_attr_e('Legendary, Mythical, Ultra Beasts', 'poke-hub'); ?>">
                                <label><input type="checkbox" id="pokehub-edit-include-legendary" <?php checked(!isset($opts['include_legendary_pokemon']) || !empty($opts['include_legendary_pokemon'])); ?> /> <?php esc_html_e('Include Legendary', 'poke-hub'); ?></label>
                                <label><input type="checkbox" id="pokehub-edit-include-mythical" <?php checked(!isset($opts['include_mythical_pokemon']) || !empty($opts['include_mythical_pokemon'])); ?> /> <?php esc_html_e('Include Mythical', 'poke-hub'); ?></label>
                                <label><input type="checkbox" id="pokehub-edit-include-ultra-beast" <?php checked(!isset($opts['include_ultra_beast_pokemon']) || !empty($opts['include_ultra_beast_pokemon'])); ?> /> <?php esc_html_e('Include Ultra Beast', 'poke-hub'); ?></label>
                            </div>
                            <?php if (!in_array('include_mega', $edit_settings_hidden, true)) : ?>
                            <label data-collections-control="include_mega"><input type="checkbox" id="pokehub-edit-include-mega" <?php checked($edit_include_mega); ?> /> <?php esc_html_e('Include Mega evolutions', 'poke-hub'); ?></label>
                            <?php endif; ?>
                            <?php if (!in_array('include_gigantamax', $edit_settings_hidden, true)) : ?>
                            <label data-collections-control="include_gigantamax"><input type="checkbox" id="pokehub-edit-include-gigantamax" <?php checked(!empty($opts['include_gigantamax'])); ?> /> <?php esc_html_e('Include Gigantamax', 'poke-hub'); ?></label>
                            <?php endif; ?>
                            <?php if (!in_array('include_dynamax', $edit_settings_hidden, true)) : ?>
                            <label data-collections-control="include_dynamax"><input type="checkbox" id="pokehub-edit-include-dynamax" <?php checked(!empty($opts['include_dynamax'])); ?> /> <?php esc_html_e('Include Dynamax', 'poke-hub'); ?></label>
                            <?php endif; ?>
                            <?php if (!in_array('include_special_attacks', $edit_settings_hidden, true)) : ?>
                            <label data-collections-control="include_special_attacks"><input type="checkbox" id="pokehub-edit-include-special-attacks" <?php checked(!empty($opts['include_special_attacks'])); ?> /> <?php esc_html_e('Include special attacks', 'poke-hub'); ?></label>
                            <?php endif; ?>
                            <?php if (!in_array('include_backgrounds', $edit_settings_hidden, true)) : ?>
                            <label data-collections-control="include_backgrounds"><input type="checkbox" id="pokehub-edit-include-backgrounds" <?php checked(!empty($opts['include_backgrounds'])); ?> /> <?php esc_html_e('Inclure les fonds d’arrière-plan (Pokémon GO, in-game : backgrounds)', 'poke-hub'); ?></label>
                            <?php endif; ?>
                            <?php if (!in_array('include_costumes', $edit_settings_hidden, true)) : ?>
                            <label data-collections-control="include_costumes"><input type="checkbox" id="pokehub-edit-include-costumes" <?php checked(!empty($opts['include_costumes'])); ?> /> <?php esc_html_e('Include costumed Pokémon', 'poke-hub'); ?></label>
                            <?php endif; ?>
                            <?php if (!in_array('include_baby_pokemon', $edit_settings_hidden, true)) : ?>
                            <label data-collections-control="include_baby_pokemon"><input type="checkbox" id="pokehub-edit-include-babies" <?php checked(!isset($opts['include_baby_pokemon']) || !empty($opts['include_baby_pokemon'])); ?> /> <?php esc_html_e('Include baby Pokémon', 'poke-hub'); ?></label>
                            <?php endif; ?>
                            <?php if ($collection_category === 'custom') : ?>
                            <label><input type="checkbox" id="pokehub-edit-only-shiny" <?php checked(!empty($opts['only_shiny'])); ?> /> <?php esc_html_e('Custom list: only include Pokémon that can be Shiny in Pokémon GO', 'poke-hub'); ?></label>
                            <?php endif; ?>
                        </fieldset>
                        <?php endif; ?>
                        <?php if (!$is_specific_category && $collection_category !== 'legendary_mythical_ultra') : ?>
                        <fieldset class="me5rine-lab-form-block">
                            <legend class="me5rine-lab-form-label"><?php esc_html_e('Include only', 'poke-hub'); ?></legend>
                            <p class="me5rine-lab-form-hint" style="margin-top:0;"><?php esc_html_e('Restrict which Pokémon appear in the pool (one choice).', 'poke-hub'); ?></p>
                            <div class="me5rine-lab-form-field">
                                <label for="pokehub-edit-pool-show-only" class="me5rine-lab-form-label"><?php esc_html_e('Pool restriction', 'poke-hub'); ?></label>
                                <select id="pokehub-edit-pool-show-only" class="me5rine-lab-form-select">
                                    <option value="" <?php selected($pool_show_only_edit, ''); ?>><?php esc_html_e('No extra restriction', 'poke-hub'); ?></option>
                                    <option value="final" <?php selected($pool_show_only_edit, 'final'); ?>><?php esc_html_e('Only final evolutions (Pokémon that do not evolve further in GO)', 'poke-hub'); ?></option>
                                    <?php if (!in_array('pool_option_baby', $edit_settings_hidden, true)) : ?>
                                    <option value="baby" data-collections-control="pool_option_baby" <?php selected($pool_show_only_edit, 'baby'); ?>><?php esc_html_e('Only baby Pokémon', 'poke-hub'); ?></option>
                                    <?php endif; ?>
                                    <?php if (!in_array('pool_option_special_all', $edit_settings_hidden, true)) : ?>
                                    <option value="special_all" data-collections-control="pool_option_special_all" <?php selected($pool_show_only_edit, 'special_all'); ?>><?php esc_html_e('Only Legendary, Mythical & Ultra Beasts', 'poke-hub'); ?></option>
                                    <?php endif; ?>
                                    <option value="legendary" <?php selected($pool_show_only_edit, 'legendary'); ?>><?php esc_html_e('Only Legendary', 'poke-hub'); ?></option>
                                    <option value="mythical" <?php selected($pool_show_only_edit, 'mythical'); ?>><?php esc_html_e('Only Mythical', 'poke-hub'); ?></option>
                                    <option value="ultra_beast" <?php selected($pool_show_only_edit, 'ultra_beast'); ?>><?php esc_html_e('Only Ultra Beast', 'poke-hub'); ?></option>
                                    <option value="special_attacks" <?php selected($pool_show_only_edit, 'special_attacks'); ?>><?php esc_html_e('Only Pokémon with special attacks', 'poke-hub'); ?></option>
                                </select>
                                <p class="me5rine-lab-form-description"><?php esc_html_e('Filtering by special attacks will apply fully once attack data is wired to the pool.', 'poke-hub'); ?></p>
                            </div>
                        </fieldset>
                        <?php endif; ?>
                        <fieldset class="me5rine-lab-form-block">
                            <legend class="me5rine-lab-form-label"><?php esc_html_e('Display', 'poke-hub'); ?></legend>
                            <label><input type="checkbox" id="pokehub-edit-include-national" <?php checked(!empty($opts['include_national_dex'])); ?> /> <?php esc_html_e('Show Pokédex numbers', 'poke-hub'); ?></label>
                            <label><input type="checkbox" id="pokehub-edit-one-per-species" <?php checked(!empty($opts['one_per_species'])); ?> /> <?php esc_html_e('Show only one entry per species', 'poke-hub'); ?></label>
                            <label><input type="checkbox" id="pokehub-edit-group-by-generation" <?php checked(!empty($opts['group_by_generation'])); ?> /> <?php esc_html_e('Group by generation', 'poke-hub'); ?></label>
                            <label><input type="checkbox" id="pokehub-edit-generations-collapsed" <?php checked(!empty($opts['generations_collapsed'])); ?> /> <?php esc_html_e('Collapse generations by default', 'poke-hub'); ?></label>
                            <label><input type="checkbox" id="pokehub-edit-add-selectors" <?php checked(in_array($opts['display_mode'] ?? 'tiles', ['select', 'tiles_select'], true)); ?> /> <?php esc_html_e('Add selectors to add Pokémon', 'poke-hub'); ?></label>
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
