<?php
// File: modules/events/public/shortcode-events.php

if (!defined('ABSPATH')) {
    exit;
}

function poke_hub_shortcode_events($atts) {

    $default_status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'current';

    $atts = shortcode_atts([
        'status'            => $default_status,
        'category'          => '',
        'event_type'        => '',
        'event_type_parent' => '',
        'order'             => 'asc',

        // Backdoor dev : possibilité de passer une taxo générique
        'taxonomy'          => '',
        'term'              => '',

        // Pagination
        'per_page'          => 15,
        'page_var'          => 'pg', // <- j'ai harmonisé avec l'usage plus bas
    ], $atts, 'poke_hub_events');

    // Normalisation du statut
    $status = in_array($atts['status'], ['current', 'upcoming', 'past', 'all'], true)
        ? $atts['status']
        : 'current';

    // Normalisation de l'ordre
    $order = strtolower($atts['order']);
    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'asc';
    }

    if ($status === 'past') {
        $order = 'desc';
    }

    /**
     * 🎯 Sélection des event_type (multi-select)
     *
     * - Priorité à $_GET['event_type'] (peut être tableau ou string)
     * - Sinon, on lit l'attribut event_type du shortcode (virgule possible)
     */
    $selected_event_types = [];

    if (isset($_GET['event_type'])) {
        $raw = wp_unslash($_GET['event_type']);

        if (is_array($raw)) {
            $selected_event_types = array_map(
                'sanitize_title',
                array_filter($raw, 'strlen')
            );
        } elseif ($raw !== '') {
            $selected_event_types = [sanitize_title($raw)];
        }

    } elseif (!empty($atts['event_type'])) {
        // On accepte "raid,pvp,whatever"
        $parts = array_map('trim', explode(',', $atts['event_type']));
        $selected_event_types = array_map('sanitize_title', array_filter($parts, 'strlen'));
    }

    // Préparation des arguments pour la fonction PHP
    $args = [
        'order' => $order,
    ];

    if (!empty($atts['category'])) {
        $args['category'] = $atts['category'];
    }

    if (!empty($atts['event_type_parent'])) {
        $args['event_type_parent'] = $atts['event_type_parent'];
    }

    // On passe un tableau de slugs si on a une sélection
    if (!empty($selected_event_types)) {
        $args['event_type'] = $selected_event_types; // ⚠ ta fonction interne doit gérer ça en tax_query 'IN'
    }

    // Option dev : combinaison taxonomy/term générique
    if (!empty($atts['taxonomy']) && !empty($atts['term'])) {
        $args['taxonomy'] = $atts['taxonomy'];
        $args['term']     = $atts['term'];
    }

    if (!function_exists('poke_hub_events_get_all_sources_by_status')) {
        return '<p>' . esc_html__('Events module is not loaded.', 'poke-hub') . '</p>';
    }

    // 🔁 Récupérer les types enfants pour la barre de filtre (si event_type_parent est défini)
    $child_types = [];
    if (!empty($atts['event_type_parent']) && function_exists('poke_hub_events_get_child_event_types')) {
        $child_types = poke_hub_events_get_child_event_types($atts['event_type_parent']);
    }

    // ============================
    // Récupérer / organiser les événements
    // ============================

    $is_all_view   = ($status === 'all');
    $grouped_items = []; // uniquement utilisé pour "all"
    $events        = []; // liste plate pour les autres cas

    if ($is_all_view) {
        // On veut :
        //  - current   → dans l'ordre par défaut
        //  - upcoming  → ordre asc (plus proche -> plus loin)
        //  - past      → ordre desc (plus récent -> plus ancien)

        // Current
        $args_current = $args;
        $args_current['order'] = 'asc';
        $current_events = poke_hub_events_get_all_sources_by_status('current', $args_current);
        $current_events = is_array($current_events) ? $current_events : [];

        // Upcoming
        $args_upcoming = $args;
        $args_upcoming['order'] = 'asc';
        $upcoming_events = poke_hub_events_get_all_sources_by_status('upcoming', $args_upcoming);
        $upcoming_events = is_array($upcoming_events) ? $upcoming_events : [];

        // Past → triés du plus récent au plus ancien, basés sur la date de fin
        $args_past = $args;
        // L'ordre renvoyé par la fonction n'a plus trop d'importance, on va retrier derrière
        $past_events = poke_hub_events_get_all_sources_by_status('past', $args_past);
        $past_events = is_array($past_events) ? $past_events : [];

        // Tri local sur la date de fin (end_ts) en DESC
        usort($past_events, function ($a, $b) {
            // Adapte ces noms de propriétés si besoin
            $a_end = isset($a->end_ts) ? (int) $a->end_ts : 0;
            $b_end = isset($b->end_ts) ? (int) $b->end_ts : 0;

            if ($a_end === $b_end) {
                return 0;
            }

            // DESC : plus grand end_ts (fin la plus récente) en premier
            return ($a_end > $b_end) ? -1 : 1;
        });

        // On combine dans l'ordre demandé, en annotant le "groupe"
        $grouped_items = [];

        foreach ($current_events as $ev) {
            $grouped_items[] = [
                'group' => 'current',
                'event' => $ev,
            ];
        }

        foreach ($upcoming_events as $ev) {
            $grouped_items[] = [
                'group' => 'upcoming',
                'event' => $ev,
            ];
        }

        foreach ($past_events as $ev) {
            $grouped_items[] = [
                'group' => 'past',
                'event' => $ev,
            ];
        }

        $total_items = count($grouped_items);

    } else {
        // Vue simple : current / upcoming / past
        $events = poke_hub_events_get_all_sources_by_status($status, $args);
        $events = is_array($events) ? $events : [];

        // Optionnel : si tu veux que l’onglet "Past" soit aussi trié sur la date de fin
        if ($status === 'past') {
            usort($events, function ($a, $b) {
                $a_end = isset($a->end_ts) ? (int) $a->end_ts : 0;
                $b_end = isset($b->end_ts) ? (int) $b->end_ts : 0;

                if ($a_end === $b_end) {
                    return 0;
                }
                return ($a_end > $b_end) ? -1 : 1; // DESC
            });
        }

        $total_items = count($events);
    }

    // ============================
    // Pagination
    // ============================

    $per_page = max(1, (int) $atts['per_page']);
    $page_var = preg_replace('/[^a-z0-9_\-]/i', '', $atts['page_var']) ?: 'pg';

    $total_pages = $total_items > 0 ? (int) ceil($total_items / $per_page) : 1;

    $paged = isset($_GET[$page_var]) ? max(1, (int) $_GET[$page_var]) : 1;
    if ($paged > $total_pages) {
        $paged = $total_pages;
    }

    $offset = ($paged - 1) * $per_page;

    // Slice différent selon le mode
    if ($is_all_view) {
        $paged_items = array_slice($grouped_items, $offset, $per_page);
    } else {
        $paged_events = array_slice($events, $offset, $per_page);
    }

    ob_start();

    /**
     * 🌍 Wrapper global avec classes utiles
     */
    $wrapper_classes = [
        'me5rine-lab-dashboard',
        'pokehub-events-wrapper',
        'pokehub-events-wrapper--status-' . $status,
    ];

    if (!empty($atts['event_type_parent'])) {
        $wrapper_classes[] = 'pokehub-events-wrapper--parent-' . sanitize_html_class($atts['event_type_parent']);
    }

    echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '">';

    // === Menu déroulant de filtres par type d'événement enfant ===
    if (!empty($child_types)) {

        echo '<form method="get" class="pokehub-event-type-filter-form">';

        // On garde tous les paramètres GET existants (status, page_id, etc.)
        // sauf event_type (remplacé par le select multiple)
        foreach ($_GET as $key => $value) {
            if ($key === 'event_type') {
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            printf(
                '<input type="hidden" name="%s" value="%s" />',
                esc_attr($key),
                esc_attr(wp_unslash($value))
            );
        }

        echo '<label class="me5rine-lab-form-label me5rine-lab-filter-label" for="pokehub-event-type-select">';
        echo esc_html__('Event type:', 'poke-hub') . ' ';
        echo '</label>';

        // 🔽 Select multiple
        echo '<select id="pokehub-event-type-select" name="event_type[]" multiple="multiple" class="me5rine-lab-form-select me5rine-lab-filter-select">';

        // Option "Tous les types" – sélectionnée si aucun type choisi
        $all_selected = empty($selected_event_types) ? 'selected="selected"' : '';
        echo '<option value="" ' . $all_selected . '>' . esc_html__('All types', 'poke-hub') . '</option>';

        foreach ($child_types as $term) {
            $selected = in_array($term->slug, $selected_event_types, true) ? 'selected="selected"' : '';
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($term->slug),
                $selected,
                esc_html($term->name)
            );
        }

        echo '</select>';

        echo '</form>';
    }

    // Onglets d'état (current / upcoming / past / all)
    $view_tabs_file = POKE_HUB_MODULES_DIR . 'events/public/view-events-tabs.php';
    if (file_exists($view_tabs_file)) {
        include $view_tabs_file;
    }

    // Liste d'événements
    if (function_exists('poke_hub_events_render_list')) {

        if ($is_all_view) {
            // On regroupe les items paginés par groupe (current / upcoming / past)
            $by_group = [
                'current'  => [],
                'upcoming' => [],
                'past'     => [],
            ];

            foreach ($paged_items as $row) {
                if (empty($row['event']) || empty($row['group'])) {
                    continue;
                }
                if (!isset($by_group[$row['group']])) {
                    $by_group[$row['group']] = [];
                }
                $by_group[$row['group']][] = $row['event'];
            }

            // Labels de titres pour chaque groupe
            $group_labels = [
                'current'  => __('Ongoing', 'poke-hub'),
                'upcoming' => __('Upcoming', 'poke-hub'),
                'past'     => __('Past', 'poke-hub'),
            ];

            foreach (['current', 'upcoming', 'past'] as $group_key) {
                if (empty($by_group[$group_key])) {
                    continue;
                }

                echo '<h2 class="pokehub-events-group-title pokehub-events-group-title--' . esc_attr($group_key) . '">';
                echo esc_html($group_labels[$group_key]);
                echo '</h2>';

                poke_hub_events_render_list($by_group[$group_key]);
            }

        } else {
            // Comportement classique par statut
            poke_hub_events_render_list($paged_events);
        }

    } else {
        echo '<p>' . esc_html__('No events renderer found.', 'poke-hub') . '</p>';
    }

    // 🔽 Pagination selon la documentation PLUGIN_INTEGRATION.md
    echo poke_hub_render_pagination([
        'total_items' => $total_items,
        'paged'        => $paged,
        'total_pages'  => $total_pages,
        'page_var'     => $page_var,
        'text_domain'  => 'poke-hub',
    ]);

    echo '</div>'; // 🔚 fin wrapper global

    return ob_get_clean();
}
add_shortcode('poke_hub_events', 'poke_hub_shortcode_events');
