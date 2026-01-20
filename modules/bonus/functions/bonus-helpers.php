<?php
// modules/bonus/bonus-helpers.php
if (!defined('ABSPATH')) { exit; }

/**
 * Liste des bonus disponibles (à adapter à ton système de bonus).
 */
function pokehub_get_all_bonuses_for_select(): array {
    $posts = get_posts([
        'post_type'        => 'pokehub_bonus',
        'post_status'      => 'publish',
        'numberposts'      => -1,
        'orderby'          => 'title',
        'order'            => 'ASC',
        'suppress_filters' => false,
    ]);

    if (empty($posts)) {
        return [];
    }

    $out = [];
    foreach ($posts as $post) {
        $out[] = [
            'id'    => (int) $post->ID,
            'label' => $post->post_title,
        ];
    }

    return $out;
}

/**
 * Récupère les infos d’un bonus à partir de son ID.
 */
function pokehub_get_bonus_data($bonus_id) {
    $bonus_id = (int) $bonus_id;
    if (!$bonus_id) {
        return null;
    }

    $post = get_post($bonus_id);
    if (!$post || $post->post_type !== 'pokehub_bonus') {
        return null;
    }

    $image_id  = get_post_thumbnail_id($bonus_id);
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
    $image_tag = $image_id ? get_the_post_thumbnail($bonus_id, 'medium') : '';

    // ⚠️ NE PAS utiliser the_content ici, ça déclenche notre filtre the_content et boucle.
    $raw_description = $post->post_content;

    // On peut quand même mettre un peu de mise en forme de base
    $description = wpautop($raw_description);
    $description = apply_filters('pokehub_bonus_description', $description, $post);

    return [
        'ID'          => $bonus_id,
        'title'       => get_the_title($bonus_id),
        'slug'        => $post->post_name,
        'image_url'   => $image_url,
        'image_html'  => $image_tag,
        'description' => $description,
    ];
}

/**
 * Récupère un bonus par slug (pratique plus tard, dans des shortcodes ou config).
 */
function pokehub_get_bonus_by_slug($slug) {
    $post = get_page_by_path($slug, OBJECT, 'pokehub_bonus');
    if (!$post) {
        return null;
    }
    return pokehub_get_bonus_data($post->ID);
}

/**
 * Récupère la liste des bonus associés à un post (avec description spécifique).
 */
function pokehub_get_bonuses_for_post($post_id) {
    $rows = get_post_meta($post_id, '_pokehub_event_bonuses', true);
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

    // Post types sur lesquels on active l’injection auto
    $allowed_post_types = apply_filters('pokehub_bonus_auto_post_types', [
        'post',
        'pokehub_event', // à adapter à ton CPT event réel
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
add_filter('the_content', 'pokehub_bonus_append_to_content', 20);

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
    <section class="pokehub-bonuses-visual pokehub-bonuses-layout-<?php echo esc_attr($layout); ?>">
        <?php foreach ($bonuses as $bonus) : 
            // Extraire le ratio du titre ou de la description (ex: "1/2", "1/4")
            $ratio = '';
            $title = $bonus['title'] ?? '';
            $description = $bonus['event_description'] ?? ($bonus['description'] ?? '');
            
            // Chercher un pattern comme "1/2", "1/4", etc. dans le titre ou la description
            if (preg_match('/(\d+\/\d+)/', $title . ' ' . $description, $matches)) {
                $ratio = $matches[1];
            }
            
            $image_url = $bonus['image_url'] ?? '';
            $bonus_title = $bonus['title'] ?? '';
            $bonus_description = $bonus['event_description'] ?? ($bonus['description'] ?? '');
        ?>
            <article class="pokehub-bonus-card">
                <div class="pokehub-bonus-card-inner">
                    <div class="pokehub-bonus-card-header">
                        <h3 class="pokehub-bonus-card-title"><?php echo esc_html($bonus_title); ?></h3>
                    </div>
                    
                    <div class="pokehub-bonus-card-icon-wrapper">
                        <?php if ($image_url) : ?>
                            <div class="pokehub-bonus-card-icon">
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($bonus_title); ?>" />
                                <?php if ($ratio) : ?>
                                    <span class="pokehub-bonus-card-badge"><?php echo esc_html($ratio); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <div class="pokehub-bonus-card-icon pokehub-bonus-card-icon-placeholder">
                                <?php if ($ratio) : ?>
                                    <span class="pokehub-bonus-card-badge"><?php echo esc_html($ratio); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($bonus_description)) : ?>
                        <div class="pokehub-bonus-card-description">
                            <?php echo wp_kses_post($bonus_description); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
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
        'post',
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

// Remplacer l'ancien filtre par le nouveau (priorité plus basse pour être exécuté après)
add_action('plugins_loaded', function() {
    // Retirer l'ancien filtre s'il existe
    remove_filter('the_content', 'pokehub_bonus_append_to_content', 20);
    // Ajouter le nouveau filtre avec le rendu visuel
    add_filter('the_content', 'pokehub_bonus_append_to_content_visual', 20);
}, 25); // Priorité 25 pour être exécuté après le chargement des helpers