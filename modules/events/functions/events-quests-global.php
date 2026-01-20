<?php
// modules/events/functions/events-quests-global.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rendu de toutes les quêtes actives (saison + événements)
 *
 * @return string HTML
 */
function pokehub_render_all_active_quests() {
    $all_quests = pokehub_get_all_active_quests();
    
    if (empty($all_quests)) {
        return '<p>' . __('No active quests available.', 'poke-hub') . '</p>';
    }
    
    // Grouper par source
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
    <div class="pokehub-all-quests">
        <?php if (!empty($season_quests)) : ?>
            <section class="pokehub-quests-section pokehub-quests-season">
                <h2 class="pokehub-quests-section-title">
                    <?php _e('Quests', 'poke-hub'); ?>
                </h2>
                <?php echo pokehub_render_event_quests($season_quests); ?>
            </section>
        <?php endif; ?>
        
        <?php if (!empty($event_quests)) : ?>
            <section class="pokehub-quests-section pokehub-quests-events">
                <h2 class="pokehub-quests-section-title">
                    <?php _e('Event Quests', 'poke-hub'); ?>
                </h2>
                
                <?php
                // Grouper par événement
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
                
                foreach ($quests_by_event as $event_id => $event_data) :
                ?>
                    <div class="pokehub-quests-event-group">
                        <?php if (!empty($event_data['event_title'])) : ?>
                            <h3 class="pokehub-quests-event-title">
                                <?php echo esc_html($event_data['event_title']); ?>
                            </h3>
                        <?php endif; ?>
                        <?php echo pokehub_render_event_quests($event_data['quests']); ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode pour afficher toutes les quêtes actives
 */
function pokehub_all_quests_shortcode($atts) {
    return pokehub_render_all_active_quests();
}
add_shortcode('pokehub_all_quests', 'pokehub_all_quests_shortcode');


