<?php
// modules/quests/functions/quests-active-research.php

if (!defined('ABSPATH')) {
    exit;
}

// Même rendu que le bloc Field Research (module Blocks), sans dépendre du module Events
if (!function_exists('pokehub_blocks_render_event_quests')) {
    require_once POKE_HUB_PATH . 'modules/blocks/functions/blocks-field-research.php';
}

/**
 * Récupère toutes les quêtes actives à l'instant donné (saison + événements).
 * Chaque élément : task, rewards, source ('season'|'event'), event_id, event_title, content_quest_id.
 *
 * @param int|null $timestamp Si null, utilise time().
 * @return array
 */
function pokehub_get_all_active_quests($timestamp = null) {
    if ($timestamp === null) {
        $timestamp = time();
    }
    $timestamp = (int) $timestamp;
    if (!function_exists('pokehub_content_get_quests_active_at') || !function_exists('pokehub_content_get_quests')) {
        return [];
    }

    $rows = pokehub_content_get_quests_active_at($timestamp);
    $out = [];

    foreach ($rows as $row) {
        $source_type = (string) $row->source_type;
        $source_id   = (int) $row->source_id;
        $quests      = pokehub_content_get_quests($source_type, $source_id);
        $event_title = '';
        $source      = 'season';
        if ($source_type === 'global_pool') {
            $event_title = __('Season', 'poke-hub');
        } elseif ($source_type === 'post' && $source_id > 0 && function_exists('poke_hub_events_get_event_title')) {
            $source      = 'event';
            $event_title = poke_hub_events_get_event_title($source_id);
        } elseif ($source_type === 'special_event' && $source_id > 0 && function_exists('pokehub_get_table')) {
            global $wpdb;
            $table = pokehub_get_table('special_events');
            if ($table) {
                $ev = $wpdb->get_row($wpdb->prepare(
                    "SELECT title, title_fr FROM {$table} WHERE id = %d LIMIT 1",
                    $source_id
                ));
                if ($ev) {
                    $source      = 'event';
                    $event_title = !empty($ev->title_fr) ? $ev->title_fr : $ev->title;
                }
            }
        }
        foreach ($quests as $q) {
            if (empty($q['task']) && empty($q['rewards'])) {
                continue;
            }
            $out[] = [
                'task'             => $q['task'] ?? '',
                'rewards'          => $q['rewards'] ?? [],
                'quest_group_id'   => isset($q['quest_group_id']) ? (int) $q['quest_group_id'] : 0,
                'source'           => $source,
                'event_id'         => $source_type !== 'global_pool' ? $source_id : 0,
                'event_title'      => $event_title,
                'content_quest_id' => (int) $row->id,
            ];
        }
    }
    return $out;
}

/**
 * Rendu d'une liste de quêtes groupées par catégorie (quest_group).
 * Chaque groupe a un en-tête avec titre (FR/EN) et couleur.
 *
 * @param array $quests Liste de quêtes (avec quest_group_id)
 * @return string HTML
 */
function pokehub_render_quests_by_groups(array $quests) {
    if (empty($quests)) {
        return '';
    }
    $groups_index = [];
    $no_group = [];
    foreach ($quests as $q) {
        $gid = isset($q['quest_group_id']) ? (int) $q['quest_group_id'] : 0;
        if ($gid > 0) {
            if (!isset($groups_index[$gid])) {
                $groups_index[$gid] = [];
            }
            $groups_index[$gid][] = $q;
        } else {
            $no_group[] = $q;
        }
    }
    $group_objects = function_exists('pokehub_get_quest_groups') ? pokehub_get_quest_groups() : [];
    ob_start();
    foreach ($group_objects as $g) {
        $gid = (int) $g->id;
        if (empty($groups_index[$gid])) {
            continue;
        }
        $title = !empty($g->title_fr) ? $g->title_fr : $g->title_en;
        $color_style = !empty($g->color) ? ' style="--pokehub-quest-group-color:' . esc_attr($g->color) . ';"' : '';
        ?>
        <div class="pokehub-quest-group"<?php echo $color_style; ?>>
            <h3 class="pokehub-quest-group-title"><?php echo esc_html($title); ?></h3>
            <?php echo pokehub_blocks_render_event_quests($groups_index[$gid]); ?>
        </div>
        <?php
    }
    if (!empty($no_group)) {
        ?>
        <div class="pokehub-quest-group pokehub-quest-group-uncategorized">
            <?php echo pokehub_blocks_render_event_quests($no_group); ?>
        </div>
        <?php
    }
    return ob_get_clean();
}

/**
 * Rendu de toutes les quêtes actives (saison + événements).
 * Deux colonnes : quêtes d'événement(s) à gauche, autres (saison) à droite. Une seule colonne sur mobile.
 * En-têtes par catégorie (groupes de quêtes) comme Leek Duck.
 *
 * @return string HTML
 */
function pokehub_render_all_active_quests() {
    if (!is_admin()) {
        if (function_exists('poke_hub_enqueue_bundled_front_style')) {
            poke_hub_enqueue_bundled_front_style('poke-hub-global-colors', 'global-colors.css', []);
            poke_hub_enqueue_bundled_front_style('pokehub-blocks-front-style', 'poke-hub-blocks-front.css', [
                'poke-hub-global-colors',
            ]);
        }
        wp_enqueue_script(
            'pokehub-events-quests',
            POKE_HUB_URL . 'assets/js/pokehub-events-quests.js',
            ['jquery'],
            POKE_HUB_VERSION,
            true
        );
    }

    $all_quests = pokehub_get_all_active_quests();

    if (empty($all_quests)) {
        return '<p>' . __('No active quests available.', 'poke-hub') . '</p>';
    }

    $season_quests = [];
    $event_quests = [];

    foreach ($all_quests as $quest) {
        if ($quest['source'] === 'season') {
            $season_quests[] = $quest;
        } else {
            $event_quests[] = $quest;
        }
    }

    ob_start();
    ?>
    <div class="pokehub-all-quests pokehub-research-page">
        <h1 class="pokehub-page-title pokehub-research-page-title"><?php esc_html_e('Current Field Research', 'poke-hub'); ?></h1>
        <div class="pokehub-research-cols">
            <div class="pokehub-research-col pokehub-research-col-events">
                <section class="pokehub-quests-section pokehub-quests-events">
                    <h2 class="pokehub-quests-section-title"><?php esc_html_e('Event Quests', 'poke-hub'); ?></h2>
                    <?php
                    $quests_by_event = [];
                    foreach ($event_quests as $quest) {
                        $event_id = $quest['event_id'] ?? 0;
                        if (!isset($quests_by_event[$event_id])) {
                            $quests_by_event[$event_id] = [
                                'event_title' => $quest['event_title'] ?? '',
                                'quests' => [],
                            ];
                        }
                        $quests_by_event[$event_id]['quests'][] = $quest;
                    }
                    foreach ($quests_by_event as $event_data) :
                        if (!empty($event_data['event_title'])) {
                            echo '<h3 class="pokehub-quests-event-title">' . esc_html($event_data['event_title']) . '</h3>';
                        }
                        echo pokehub_render_quests_by_groups($event_data['quests']);
                    endforeach;
                    if (empty($event_quests)) {
                        echo '<p class="pokehub-quests-empty">' . esc_html__('No event quests at the moment.', 'poke-hub') . '</p>';
                    }
                    ?>
                </section>
            </div>
            <div class="pokehub-research-col pokehub-research-col-other">
                <section class="pokehub-quests-section pokehub-quests-season">
                    <h2 class="pokehub-quests-section-title"><?php esc_html_e('Quests', 'poke-hub'); ?></h2>
                    <?php
                    echo pokehub_render_quests_by_groups($season_quests);
                    if (empty($season_quests)) {
                        echo '<p class="pokehub-quests-empty">' . esc_html__('No season quests at the moment.', 'poke-hub') . '</p>';
                    }
                    ?>
                </section>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode pour afficher toutes les quêtes actives.
 */
function pokehub_all_quests_shortcode($atts) {
    return pokehub_render_all_active_quests();
}
add_shortcode('pokehub_all_quests', 'pokehub_all_quests_shortcode');
