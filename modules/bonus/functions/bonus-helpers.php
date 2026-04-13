<?php
// modules/bonus/bonus-helpers.php
if (!defined('ABSPATH')) { exit; }

/**
 * Liste des bonus disponibles pour les selects (metabox, etc.).
 * Source : table catalogue bonus_types (locale sur le site principal, distante via préfixe Pokémon ailleurs).
 */
function pokehub_get_all_bonuses_for_select(): array {
    $table = function_exists('pokehub_get_bonus_types_table') ? pokehub_get_bonus_types_table() : '';
    if ($table === '' || !function_exists('pokehub_table_exists') || !pokehub_table_exists($table)) {
        return [];
    }
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT id, title_en, title_fr FROM {$table} ORDER BY id DESC",
        OBJECT_K
    );
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $en = isset($row->title_en) ? (string) $row->title_en : '';
        $fr = isset($row->title_fr) ? (string) $row->title_fr : '';
        if ($fr !== '' && $en !== '' && $fr !== $en) {
            $label = sprintf('%s (%s)', $fr, $en);
        } elseif ($fr !== '') {
            $label = $fr;
        } else {
            $label = $en;
        }
        $out[] = [
            'id'    => (int) $row->id,
            'label' => $label,
        ];
    }
    return $out;
}

/**
 * Récupère les infos d’un bonus à partir de son ID (table bonus_types uniquement).
 */
function pokehub_get_bonus_data($bonus_id) {
    $bonus_id = (int) $bonus_id;
    if (!$bonus_id) {
        return null;
    }

    $table = function_exists('pokehub_get_bonus_types_table') ? pokehub_get_bonus_types_table() : '';
    if ($table === '' || !function_exists('pokehub_table_exists') || !pokehub_table_exists($table)) {
        return null;
    }

    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, title_en, title_fr, slug, description FROM {$table} WHERE id = %d",
        $bonus_id
    ));
    if (!$row) {
        return null;
    }

    $title_en = isset($row->title_en) ? (string) $row->title_en : '';
    $title_fr = isset($row->title_fr) ? (string) $row->title_fr : '';
    $display  = $title_fr !== '' ? $title_fr : $title_en;

    $slug       = (string) $row->slug;
    $image_url  = '';
    $image_tag  = '';
    if ($slug !== '') {
        $svg_url = function_exists('poke_hub_get_asset_url') ? poke_hub_get_asset_url('bonus', $slug, 'svg') : '';
        $chain   = function_exists('poke_hub_get_raster_asset_url_chain') ? poke_hub_get_raster_asset_url_chain('bonus', $slug) : [];
        $image_url = $svg_url !== '' ? $svg_url : ($chain[0] ?? '');
        if (function_exists('poke_hub_render_bonus_asset_markup')) {
            $image_tag = poke_hub_render_bonus_asset_markup($slug, [
                'alt'       => $display,
                'icon_size' => 80,
            ]);
        }
    }

    $description = wpautop((string) $row->description);
    $ctx = (object) [
        'id'       => (int) $row->id,
        'title'    => $display,
        'title_en' => $title_en,
        'title_fr' => $title_fr,
        'slug'     => $slug,
    ];
    $description = apply_filters('pokehub_bonus_description', $description, $ctx);

    return [
        'ID'          => (int) $row->id,
        'title'       => $display,
        'title_en'    => $title_en,
        'title_fr'    => $title_fr,
        'slug'        => $slug,
        'image_url'   => $image_url,
        'image_html'  => $image_tag,
        'description' => $description,
    ];
}

/**
 * Récupère un bonus par slug.
 */
function pokehub_get_bonus_by_slug($slug) {
    $slug = is_string($slug) ? trim($slug) : '';
    if ($slug === '') {
        return null;
    }
    $table = function_exists('pokehub_get_bonus_types_table') ? pokehub_get_bonus_types_table() : '';
    if ($table === '' || !function_exists('pokehub_table_exists') || !pokehub_table_exists($table)) {
        return null;
    }
    global $wpdb;
    $id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
        $slug
    ));
    if ($id <= 0) {
        return null;
    }
    return pokehub_get_bonus_data($id);
}

/**
 * Récupère la liste des bonus associés à un post (avec description spécifique).
 */
function pokehub_get_bonuses_for_post($post_id) {
    $rows = function_exists('pokehub_content_get_bonus')
        ? pokehub_content_get_bonus('post', (int) $post_id)
        : [];
    if (!is_array($rows) || empty($rows)) {
        return [];
    }

    $result = [];
    foreach ($rows as $row) {
        $bonus = pokehub_get_bonus_data((int) ($row['bonus_id'] ?? 0));
        if (!$bonus) {
            continue;
        }
        $bonus['event_description'] = $row['description'] ?? '';
        $result[] = $bonus;
    }

    return $result;
}

/**
 * Affiche les bonus d’un post (image + titre + description spécifique).
 */
function pokehub_render_post_bonuses($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $bonuses = pokehub_get_bonuses_for_post($post_id);

    if (empty($bonuses)) {
        return;
    }

    echo '<section class="pokehub-event-bonuses">';

    foreach ($bonuses as $bonus) {
        echo '<article class="pokehub-event-bonus">';
            if (!empty($bonus['image_html'])) {
                echo '<div class="pokehub-event-bonus-image">';
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG inline ou img (poke_hub_render_bonus_asset_markup)
                    echo $bonus['image_html'];
                echo '</div>';
            }

            echo '<div class="pokehub-event-bonus-content">';
                echo '<h3 class="pokehub-event-bonus-title">' . esc_html($bonus['title']) . '</h3>';

                // Description spécifique à l’événement en priorité
                if (!empty($bonus['event_description'])) {
                    echo '<p class="pokehub-event-bonus-desc">' . wp_kses_post($bonus['event_description']) . '</p>';
                } elseif (!empty($bonus['description'])) {
                    // fallback: description globale du bonus (optionnel)
                    echo '<div class="pokehub-event-bonus-desc-global">' . wp_kses_post($bonus['description']) . '</div>';
                }
            echo '</div>';
        echo '</article>';
    }

    echo '</section>';
}

/**
 * Ajoute automatiquement les bonus à la fin du contenu
 * pour certains post types (ex: post, pokehub_event).
 */
function pokehub_bonus_append_to_content($content) {
    static $in_bonus_filter = false;

    // Si on est déjà en train de traiter les bonus, on ne refait rien
    if ($in_bonus_filter) {
        return $content;
    }

    // Pas dans l'admin ou les feeds
    if (is_admin() || is_feed()) {
        return $content;
    }

    if (!in_the_loop() || !is_main_query()) {
        return $content;
    }

    global $post;
    if (!$post) {
        return $content;
    }

    $post_type = get_post_type($post);

    // Post types sur lesquels on active l'injection auto
    $allowed_post_types = apply_filters('pokehub_bonus_auto_post_types', [
        'pokehub_event',
    ]);

    if (!in_array($post_type, $allowed_post_types, true)) {
        return $content;
    }

    $in_bonus_filter = true;
    ob_start();
    pokehub_render_post_bonuses($post->ID);
    $bonus_html = ob_get_clean();
    $in_bonus_filter = false;

    if (empty($bonus_html)) {
        return $content;
    }

    // Ajout à la fin du contenu
    return $content . $bonus_html;
}
if (function_exists('poke_hub_is_module_active') && poke_hub_is_module_active('bonus')) {
    add_filter('the_content', 'pokehub_bonus_append_to_content', 20);
}

/**
 * Rendu visuel des bonus (cartes avec badges)
 *
 * @param array $bonuses Liste des bonus
 * @param string $layout Layout ('cards' par défaut)
 * @return string HTML
 */
function pokehub_render_bonuses_visual($bonuses, $layout = 'cards') {
    if (empty($bonuses)) {
        return '';
    }

    ob_start();
    ?>
    <div class="pokehub-bonuses-grid">
        <?php foreach ($bonuses as $bonus) :
            // Extraire le ratio du titre ou de la description (ex: "1/2", "1/4")
            $ratio = '';
            $title = $bonus['title'] ?? '';
            $title_scan = trim($title . ' ' . ($bonus['title_en'] ?? '') . ' ' . ($bonus['title_fr'] ?? ''));
            $description = $bonus['event_description'] ?? ($bonus['description'] ?? '');

            // Chercher un pattern comme "1/2", "1/4", etc. dans le titre ou la description
            if (preg_match('/(\d+\/\d+)/', $title_scan . ' ' . $description, $matches)) {
                $ratio = $matches[1];
            }

            $image_url = $bonus['image_url'] ?? '';
            $image_html = $bonus['image_html'] ?? '';
            $bonus_title = $bonus['title'] ?? '';
            $bonus_description = $bonus['event_description'] ?? ($bonus['description'] ?? '');
            $bonus_description = is_string($bonus_description) ? trim($bonus_description) : '';
        ?>
            <div class="pokehub-bonus-cell">
                <div class="pokehub-bonus-card">
                    <div class="pokehub-bonus-card-inner">
                        <?php if ($image_html || $image_url) : ?>
                            <div class="pokehub-bonus-image-wrapper">
                                <?php if ($image_html) : ?>
                                    <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG inline (bucket) ou img raster depuis poke_hub_render_bonus_asset_markup
                                    echo $image_html; ?>
                                <?php else : ?>
                                <img
                                    src="<?php echo esc_url($image_url); ?>"
                                    alt="<?php echo esc_attr($bonus_title); ?>"
                                    class="pokehub-bonus-image"
                                    loading="lazy"
                                    onerror="this.style.display='none';"
                                />
                                <?php endif; ?>
                                <?php if ($ratio) : ?>
                                    <span class="pokehub-bonus-badge" title="<?php echo esc_attr($ratio); ?>"><?php echo esc_html($ratio); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($bonus_title !== '') : ?>
                            <div class="pokehub-bonus-name"><?php echo esc_html($bonus_title); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($bonus_description !== '') : ?>
                    <div class="pokehub-bonus-description pokehub-bonus-description--below">
                        <?php echo wp_kses_post($bonus_description); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Améliore l'affichage automatique des bonus avec le style visuel
 */
function pokehub_bonus_append_to_content_visual($content) {
    static $in_bonus_filter = false;

    // Si on est déjà en train de traiter les bonus, on ne refait rien
    if ($in_bonus_filter) {
        return $content;
    }

    // Pas dans l'admin ou les feeds
    if (is_admin() || is_feed()) {
        return $content;
    }

    if (!in_the_loop() || !is_main_query()) {
        return $content;
    }

    global $post;
    if (!$post) {
        return $content;
    }

    $post_type = get_post_type($post);

    // Post types sur lesquels on active l'injection auto
    $allowed_post_types = apply_filters('pokehub_bonus_auto_post_types', [
        'pokehub_event',
    ]);

    if (!in_array($post_type, $allowed_post_types, true)) {
        return $content;
    }

    $in_bonus_filter = true;
    $bonuses = pokehub_get_bonuses_for_post($post->ID);
    $in_bonus_filter = false;

    if (empty($bonuses)) {
        return $content;
    }

    // Utiliser le nouveau rendu visuel
    $bonus_html = pokehub_render_bonuses_visual($bonuses, 'cards');

    if (empty($bonus_html)) {
        return $content;
    }

    // Ajout à la fin du contenu
    return $content . $bonus_html;
}

// Remplacer l'ancien filtre par le nouveau (priorité plus basse pour être exécuté après) — uniquement si le module Bonus est actif
add_action('plugins_loaded', function() {
    if (!function_exists('poke_hub_is_module_active') || !poke_hub_is_module_active('bonus')) {
        return;
    }
    remove_filter('the_content', 'pokehub_bonus_append_to_content', 20);
    add_filter('the_content', 'pokehub_bonus_append_to_content_visual', 20);
}, 25);
