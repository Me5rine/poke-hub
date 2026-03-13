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
    <div class="poke-hub-collections-wrap" data-logged-in="<?php echo $is_logged_in ? '1' : '0'; ?>">
        <header class="poke-hub-collections-header">
            <h1 class="poke-hub-collections-title"><?php esc_html_e('Gestion des collections', 'poke-hub'); ?></h1>
            <div class="poke-hub-collections-header-row">
                <p class="poke-hub-collections-subtitle"><?php esc_html_e('Créez et gérez vos collections Pokémon GO (chromatiques, costumés, 100%, etc.).', 'poke-hub'); ?></p>
                <button type="button" class="poke-hub-collections-btn-create button button-primary">
                    <?php esc_html_e('Créer une collection', 'poke-hub'); ?>
                </button>
            </div>
        </header>

        <?php if ($is_logged_in) : ?>
        <div class="poke-hub-collections-anonymous-banner" id="poke-hub-collections-anonymous-banner" style="display: none;">
            <p class="poke-hub-collections-anonymous-banner-text"></p>
            <ul class="poke-hub-collections-anonymous-list"></ul>
            <p class="poke-hub-collections-anonymous-actions">
                <button type="button" class="button button-primary poke-hub-collections-claim-all"><?php esc_html_e('Tout ajouter à mon compte', 'poke-hub'); ?></button>
                <button type="button" class="button poke-hub-collections-dismiss-banner"><?php esc_html_e('Fermer', 'poke-hub'); ?></button>
            </p>
        </div>
        <?php endif; ?>

        <div class="poke-hub-collections-list">
            <?php if (empty($collections)) : ?>
                <p class="poke-hub-collections-empty">
                    <?php esc_html_e('Vous n\'avez pas encore de collection. Créez-en une pour suivre vos 100%, chromatiques, costumés, etc.', 'poke-hub'); ?>
                </p>
            <?php else : ?>
                <ul class="poke-hub-collections-grid">
                    <?php
                    $collections_base_url = rtrim(get_permalink(), '/');
                    foreach ($collections as $col) :
                        $col_token = $col['share_token'] ?? '';
                        $col_view_url = $col_token !== '' ? $collections_base_url . '/' . $col_token : add_query_arg(['id' => $col['id'], 'view' => '1'], get_permalink());
                        $col_edit_url = $col_token !== '' ? $collections_base_url . '/' . $col_token . '?edit=1' : add_query_arg(['id' => $col['id'], 'view' => '1', 'edit' => '1'], get_permalink());
                    ?>
                        <li class="poke-hub-collections-card" data-collection-id="<?php echo (int) $col['id']; ?>" data-collection-name="<?php echo esc_attr($col['name']); ?>">
                            <a href="<?php echo esc_url($col_view_url); ?>" class="poke-hub-collections-card-link">
                                <span class="poke-hub-collections-card-bg"></span>
                                <span class="poke-hub-collections-card-name"><?php echo esc_html($col['name']); ?></span>
                                <span class="poke-hub-collections-card-meta"><?php echo esc_html($categories[$col['category']] ?? $col['category']); ?></span>
                            </a>
                            <div class="poke-hub-collections-card-actions">
                                <a href="<?php echo esc_url($col_edit_url); ?>" class="poke-hub-collections-card-btn poke-hub-collections-card-btn-settings" title="<?php esc_attr_e('Paramètres', 'poke-hub'); ?>"><span class="screen-reader-text"><?php esc_html_e('Paramètres', 'poke-hub'); ?></span></a>
                                <button type="button" class="poke-hub-collections-card-btn poke-hub-collections-card-btn-delete poke-hub-collections-btn-delete-list" data-collection-id="<?php echo (int) $col['id']; ?>" data-collection-name="<?php echo esc_attr($col['name']); ?>" title="<?php esc_attr_e('Supprimer', 'poke-hub'); ?>"><span class="screen-reader-text"><?php esc_html_e('Supprimer', 'poke-hub'); ?></span></button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Modal création -->
        <div class="poke-hub-collections-modal poke-hub-collections-modal-create" role="dialog" aria-hidden="true">
            <div class="poke-hub-collections-modal-backdrop"></div>
            <div class="poke-hub-collections-modal-content">
                <h3><?php esc_html_e('Informations de la collection', 'poke-hub'); ?></h3>
                <p class="poke-hub-collections-modal-desc"><?php esc_html_e('Entrez les détails de votre collection Pokémon GO.', 'poke-hub'); ?></p>

                <div class="poke-hub-collections-form">
                    <p class="form-field">
                        <label for="poke-hub-collection-name"><?php esc_html_e('Nom de la collection', 'poke-hub'); ?></label>
                        <input type="text" id="poke-hub-collection-name" placeholder="<?php esc_attr_e('Ex: Mes Pokémon Shiny', 'poke-hub'); ?>" />
                    </p>
                    <p class="form-field">
                        <label for="poke-hub-collection-category"><?php esc_html_e('Catégorie de collection', 'poke-hub'); ?></label>
                        <select id="poke-hub-collection-category">
                            <?php foreach ($categories as $slug => $label) : ?>
                                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <fieldset class="poke-hub-collections-options">
                        <legend><?php esc_html_e('Options', 'poke-hub'); ?></legend>
                        <label><input type="checkbox" id="poke-hub-collection-public" /> <?php esc_html_e('Rendre cette collection publique', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-collection-include-national" checked /> <?php esc_html_e('Inclure le Pokédex national', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-collection-include-gender" checked /> <?php esc_html_e('Inclure les différences de genre', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-collection-include-forms" checked /> <?php esc_html_e('Inclure les formes alternatives', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-collection-include-costumes" checked /> <?php esc_html_e('Inclure les Pokémon costumés', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-collection-include-special-attacks" /> <?php esc_html_e('Inclure les attaques spéciales', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-collection-exclude-mega" /> <?php esc_html_e('Exclure les Méga', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-collection-one-per-species" /> <?php esc_html_e('Une seule entrée par espèce (ex. un Zarbi)', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-collection-group-by-generation" checked /> <?php esc_html_e('Grouper par région / génération', 'poke-hub'); ?></label>
                        <label><input type="radio" name="poke-hub-collection-display" value="tiles" checked /> <?php esc_html_e('Afficher en tuiles (1 clic = possédé)', 'poke-hub'); ?></label>
                        <label><input type="radio" name="poke-hub-collection-display" value="select" /> <?php esc_html_e('Liste + sélection des manquants', 'poke-hub'); ?></label>
                    </fieldset>
                    <?php if (!$is_logged_in) : ?>
                        <p class="poke-hub-collections-warning" role="alert">
                            <?php esc_html_e('Vous n\'êtes pas connecté. Cette collection sera stockée localement sur cet appareil.', 'poke-hub'); ?>
                            <?php esc_html_e('Créez un compte pour sauvegarder vos collections.', 'poke-hub'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="poke-hub-collections-modal-actions">
                    <button type="button" class="button poke-hub-collections-modal-cancel"><?php esc_html_e('Annuler', 'poke-hub'); ?></button>
                    <button type="button" class="button button-primary poke-hub-collections-modal-create-btn"><?php esc_html_e('Créer la collection', 'poke-hub'); ?></button>
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
        <div class="poke-hub-collection-view-wrap poke-hub-collection-view-local" data-local="1" data-collection-slug="<?php echo esc_attr($slug); ?>" data-can-edit="1">
            <header class="poke-hub-collection-view-header">
                <a href="<?php echo esc_url(remove_query_arg(['collection', 'id', 'view', 'local'])); ?>" class="poke-hub-collection-back">&larr; <?php esc_html_e('Retour aux collections', 'poke-hub'); ?></a>
                <h2 class="poke-hub-collection-view-title poke-hub-collection-local-title"><?php esc_html_e('Chargement…', 'poke-hub'); ?></h2>
            </header>
            <div class="poke-hub-collection-stats poke-hub-collection-local-stats">—</div>
            <div class="poke-hub-collection-tiles poke-hub-collection-tiles-local" data-pool="[]" data-items="{}"></div>
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
        return '<p class="poke-hub-collections-not-found">' . esc_html__('Collection introuvable.', 'poke-hub') . '</p>';
    }

    $collections_base = rtrim(get_permalink(), '/');
    $canonical_url    = $collections_base . '/' . ($collection['share_token'] ?? '');

    // Redirection vers l’URL canonique (token unique) si on est arrivé avec id ou slug
    if (!empty($collection['share_token']) && $token === '' && (isset($_GET['id']) || isset($_GET['collection']))) {
        wp_safe_redirect($canonical_url, 302);
        exit;
    }

    $pool    = poke_hub_collections_get_pool($collection['category'], $collection['options']);
    $items   = poke_hub_collections_get_items((int) $collection['id']);
    $can_edit = ($user_id > 0 && (int) $collection['user_id'] === $user_id)
        || ((int) $collection['user_id'] === 0 && !empty($collection['anonymous_ip']) && poke_hub_collections_get_client_ip() === $collection['anonymous_ip']);

    ob_start();
    ?>
    <?php $opts = $collection['options']; ?>
    <div class="poke-hub-collection-view-wrap" data-collection-id="<?php echo (int) $collection['id']; ?>" data-can-edit="<?php echo $can_edit ? '1' : '0'; ?>"
         data-share-token="<?php echo esc_attr($collection['share_token'] ?? ''); ?>"
         data-share-url="<?php echo esc_url($canonical_url); ?>"
         data-edit-name="<?php echo esc_attr($collection['name']); ?>"
         data-edit-options="<?php echo esc_attr(wp_json_encode($opts)); ?>"
         data-edit-is-public="<?php echo !empty($collection['is_public']) ? '1' : '0'; ?>">
        <header class="poke-hub-collection-view-header">
            <a href="<?php echo esc_url($collections_base); ?>" class="poke-hub-collection-back">&larr; <?php esc_html_e('Retour à la gestion des collections', 'poke-hub'); ?></a>
            <h1 class="poke-hub-collection-view-title"><?php echo esc_html($collection['name']); ?></h1>
            <div class="poke-hub-collection-view-actions">
                <?php if ($can_edit) : ?>
                    <button type="button" class="button poke-hub-collections-btn-edit-settings"><?php esc_html_e('Paramètres', 'poke-hub'); ?></button>
                    <button type="button" class="button poke-hub-collections-btn-share"><?php esc_html_e('Partager', 'poke-hub'); ?></button>
                <?php endif; ?>
            </div>
        </header>

        <div class="poke-hub-collection-stats">
            <?php
            $owned = count(array_filter($items, function ($s) { return $s === 'owned'; }));
            $total = count($pool);
            ?>
            <span class="poke-hub-collection-progress"><?php echo (int) $owned; ?> / <?php echo (int) $total; ?></span>
        </div>

        <?php if ($total === 0) : ?>
            <p class="poke-hub-collection-empty-pool">
                <?php esc_html_e('Aucun Pokémon dans cette catégorie pour le moment. Vérifiez les paramètres de la collection (catégorie, options) ou l\'import des données Pokémon.', 'poke-hub'); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($collection['options']['display_mode']) && $collection['options']['display_mode'] === 'select') : ?>
            <div class="poke-hub-collection-select-mode poke-hub-collection-multiselect-wrap">
                <label for="poke-hub-collection-missing-search"><?php esc_html_e('Rechercher un Pokémon manquant', 'poke-hub'); ?></label>
                <input type="text" id="poke-hub-collection-missing-search" class="poke-hub-collection-search" placeholder="<?php esc_attr_e('Nom ou n°…', 'poke-hub'); ?>" autocomplete="off" />
                <div class="poke-hub-collection-multiselect-list-wrap">
                    <div id="poke-hub-collection-multiselect-list" class="poke-hub-collection-multiselect-list" role="listbox" aria-multiselectable="true" aria-label="<?php esc_attr_e('Pokémon manquants', 'poke-hub'); ?>"></div>
                </div>
                <button type="button" class="button button-primary poke-hub-collection-multiselect-add"><?php esc_html_e('Ajouter la sélection', 'poke-hub'); ?></button>
            </div>
        <?php endif; ?>

        <?php
        $group_by_gen = !empty($opts['group_by_generation']);
        $pool_by_gen  = $group_by_gen ? poke_hub_collections_group_pool_by_generation($pool) : ['' => $pool];
        $assets_base  = function_exists('poke_hub_pokemon_asset_url') ? poke_hub_pokemon_asset_url('pokemon') : (get_option('poke_hub_pokemon_assets_base_url', '') . get_option('poke_hub_assets_path_pokemon', '/pokemon-go/pokemon/'));
        ?>
        <div class="poke-hub-collection-tiles" data-pool="<?php echo esc_attr(wp_json_encode($pool)); ?>" data-items="<?php echo esc_attr(wp_json_encode($items)); ?>">
            <?php foreach ($pool_by_gen as $gen_key => $gen_pool) : ?>
                <?php if ($gen_key !== '') : ?>
                    <div class="poke-hub-collection-generation-block" data-generation="<?php echo esc_attr($gen_key); ?>">
                        <h3 class="poke-hub-collection-generation-title"><?php echo esc_html($gen_key); ?></h3>
                        <div class="poke-hub-collection-generation-tiles">
                <?php endif; ?>
                <?php foreach ($gen_pool as $p) :
                    $status = $items[$p['id']] ?? 'missing';
                    $img_src = $assets_base ? rtrim($assets_base, '/') . '/' . $p['id'] . '.png' : '';
                ?>
                <div class="poke-hub-collection-tile" data-pokemon-id="<?php echo (int) $p['id']; ?>" data-status="<?php echo esc_attr($status); ?>" tabindex="0" role="button">
                    <?php if ($img_src) : ?><img src="<?php echo esc_url($img_src); ?>" alt="" loading="lazy" /><?php endif; ?>
                    <span class="poke-hub-collection-tile-name"><?php echo esc_html($p['name_fr'] ?: $p['name_en']); ?></span>
                    <span class="poke-hub-collection-tile-status poke-hub-status-<?php echo esc_attr($status); ?>"></span>
                </div>
                <?php endforeach; ?>
                <?php if ($gen_key !== '') : ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($can_edit) : ?>
        <!-- Modal édition paramètres -->
        <div class="poke-hub-collections-modal poke-hub-collections-modal-edit" role="dialog" aria-hidden="true">
            <div class="poke-hub-collections-modal-backdrop"></div>
            <div class="poke-hub-collections-modal-content">
                <h3><?php esc_html_e('Modifier les paramètres', 'poke-hub'); ?></h3>
                <div class="poke-hub-collections-form poke-hub-collections-form-edit">
                    <p class="form-field">
                        <label for="poke-hub-edit-collection-name"><?php esc_html_e('Nom de la collection', 'poke-hub'); ?></label>
                        <input type="text" id="poke-hub-edit-collection-name" value="<?php echo esc_attr($collection['name']); ?>" />
                    </p>
                    <p class="form-field">
                        <label><input type="checkbox" id="poke-hub-edit-collection-public" <?php checked(!empty($collection['is_public'])); ?> /> <?php esc_html_e('Rendre cette collection publique', 'poke-hub'); ?></label>
                    </p>
                    <fieldset class="poke-hub-collections-options">
                        <legend><?php esc_html_e('Affichage', 'poke-hub'); ?></legend>
                        <label><input type="radio" name="poke-hub-edit-collection-display" value="tiles" <?php checked(($opts['display_mode'] ?? 'tiles') === 'tiles'); ?> /> <?php esc_html_e('Tuiles (1 clic = possédé)', 'poke-hub'); ?></label>
                        <label><input type="radio" name="poke-hub-edit-collection-display" value="select" <?php checked(($opts['display_mode'] ?? '') === 'select'); ?> /> <?php esc_html_e('Liste + sélection des manquants', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-edit-exclude-mega" <?php checked(!empty($opts['exclude_mega'])); ?> /> <?php esc_html_e('Exclure les Méga', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-edit-one-per-species" <?php checked(!empty($opts['one_per_species'])); ?> /> <?php esc_html_e('Une seule entrée par espèce (ex. un Zarbi)', 'poke-hub'); ?></label>
                        <label><input type="checkbox" id="poke-hub-edit-group-by-generation" <?php checked(!empty($opts['group_by_generation'])); ?> /> <?php esc_html_e('Grouper par région / génération', 'poke-hub'); ?></label>
                    </fieldset>
                </div>
                <div class="poke-hub-collections-modal-actions">
                    <button type="button" class="button poke-hub-collections-modal-edit-cancel"><?php esc_html_e('Annuler', 'poke-hub'); ?></button>
                    <button type="button" class="button button-primary poke-hub-collections-modal-edit-save"><?php esc_html_e('Enregistrer', 'poke-hub'); ?></button>
                </div>
                <div class="poke-hub-collections-modal-actions poke-hub-collections-modal-actions-danger">
                    <button type="button" class="button poke-hub-collections-btn-delete-collection"><?php esc_html_e('Supprimer la collection', 'poke-hub'); ?></button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});
