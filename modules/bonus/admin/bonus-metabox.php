<?php
// modules/bonus/bonus-metabox.php
if (!defined('ABSPATH')) { exit; }

/**
 * Ajoute la metabox sur les types de contenus souhaités.
 * Tu peux ajouter ici tes CPT d’événements.
 */
function pokehub_add_bonus_metabox() {

    // À adapter : liste des post types qui peuvent avoir des bonus
    $screens = apply_filters('pokehub_bonus_metabox_post_types', [
        'post',
        // 'pokehub_event', etc.
    ]);

    foreach ($screens as $screen) {
        add_meta_box(
            'pokehub_event_bonuses',
            __('Event Bonuses', 'poke-hub'),
            'pokehub_render_event_bonuses_metabox',
            $screen,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'pokehub_add_bonus_metabox');

/**
 * Rendu HTML de la metabox.
 */
function pokehub_render_event_bonuses_metabox($post) {
    wp_nonce_field('pokehub_save_event_bonuses', 'pokehub_event_bonuses_nonce');

    $value = function_exists('pokehub_content_get_bonus') ? pokehub_content_get_bonus('post', (int) $post->ID) : [];
    if (!is_array($value)) {
        $value = [];
    }

    // Liste des bonus disponibles (site principal = table locale, sites distants = table via préfixe Pokémon)
    $bonuses = function_exists('pokehub_get_all_bonuses_for_select') ? pokehub_get_all_bonuses_for_select() : [];
    ?>
    <div class="pokehub-event-bonuses-wrapper">
        <p>
            <?php esc_html_e('Select one or more bonuses for this event. You can customize the text for this event only.', 'poke-hub'); ?>
        </p>

        <div class="pokehub-event-bonuses-list">
            <?php foreach ($value as $index => $row) :
                $bonus_id    = isset($row['bonus_id']) ? (int) $row['bonus_id'] : 0;
                $description = $row['description'] ?? '';
            ?>
                <div class="pokehub-event-bonus-item" style="margin-bottom:12px; border:1px solid #ddd; padding:8px;">
                    <p>
                        <strong>Bonus</strong><br>
                        <select name="pokehub_event_bonuses[<?php echo esc_attr($index); ?>][bonus_id]" style="min-width:250px;">
                            <option value=""><?php esc_html_e('— Select a bonus —', 'poke-hub'); ?></option>
                            <?php foreach ($bonuses as $b) : ?>
                                <option value="<?php echo esc_attr($b['id']); ?>" <?php selected($bonus_id, (int) $b['id']); ?>>
                                    <?php echo esc_html($b['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button-link-delete pokehub-remove-bonus" style="float:right;"><?php esc_html_e('Delete', 'poke-hub'); ?></button>
                    </p>

                    <p style="margin-top:8px;">
                        <strong><?php esc_html_e('Description specific to this event', 'poke-hub'); ?></strong><br>
                        <textarea name="pokehub_event_bonuses[<?php echo esc_attr($index); ?>][description]" rows="2" style="width:100%;"><?php
                            echo esc_textarea($description);
                        ?></textarea>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="button" class="button button-secondary" id="pokehub-add-bonus-row">
                <?php esc_html_e('Add bonus', 'poke-hub'); ?>
            </button>
        </p>
    </div>

    <script>
    (function($){
        $('#pokehub-add-bonus-row').on('click', function(){
            var $list = $('.pokehub-event-bonuses-list');
            var index = $list.children().length;

            var template =
                '<div class="pokehub-event-bonus-item" style="margin-bottom:12px; border:1px solid #ddd; padding:8px;">' +
                    '<p>' +
                        '<strong>Bonus</strong><br>' +
                        '<select name="pokehub_event_bonuses['+index+'][bonus_id]" style="min-width:250px;">' +
                            '<option value=""><?php echo esc_js(__('— Select a bonus —', 'poke-hub')); ?></option>' +
                            <?php foreach ($bonuses as $b) : ?>
                                '<option value="<?php echo esc_js($b['id']); ?>"><?php echo esc_js($b['label']); ?></option>' +
                            <?php endforeach; ?>
                        '</select>' +
                        '<button type="button" class="button-link-delete pokehub-remove-bonus" style="float:right;"><?php echo esc_js(__('Delete', 'poke-hub')); ?></button>' +
                    '</p>' +
                    '<p style="margin-top:8px;">' +
                        '<strong><?php echo esc_js(__('Description specific to this event', 'poke-hub')); ?></strong><br>' +
                        '<textarea name="pokehub_event_bonuses['+index+'][description]" rows="2" style="width:100%;"></textarea>' +
                    '</p>' +
                '</div>';

            $list.append(template);
        });

        $(document).on('click', '.pokehub-remove-bonus', function(){
            $(this).closest('.pokehub-event-bonus-item').remove();
        });
    })(jQuery);
    </script>
    <?php
}

/**
 * Sauvegarde des bonus d’un post.
 */
function pokehub_save_event_bonuses($post_id) {
    if (!isset($_POST['pokehub_event_bonuses_nonce']) ||
        !wp_verify_nonce($_POST['pokehub_event_bonuses_nonce'], 'pokehub_save_event_bonuses')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $data  = $_POST['pokehub_event_bonuses'] ?? [];
    $clean = [];

    if (is_array($data)) {
        foreach ($data as $row) {
            $bonus_id = isset($row['bonus_id']) ? (int) $row['bonus_id'] : 0;
            $desc     = isset($row['description']) ? wp_kses_post($row['description']) : '';

            if ($bonus_id) {
                $clean[] = [
                    'bonus_id'    => $bonus_id,
                    'description' => $desc,
                ];
            }
        }
    }

    if (function_exists('pokehub_content_save_bonus')) {
        pokehub_content_save_bonus('post', $post_id, $clean);
    }
}
add_action('save_post', 'pokehub_save_event_bonuses');
