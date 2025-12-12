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
            'Bonus de l’événement',
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

    $value = get_post_meta($post->ID, '_pokehub_event_bonuses', true);
    if (!is_array($value)) {
        $value = [];
    }

    // Liste des bonus disponibles
    $bonuses = get_posts([
        'post_type'      => 'pokehub_bonus',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
    ?>
    <div class="pokehub-event-bonuses-wrapper">
        <p>
            Sélectionne un ou plusieurs bonus pour cet événement.  
            Tu peux personnaliser le texte pour cet événement uniquement.
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
                            <option value="">— Sélectionner un bonus —</option>
                            <?php foreach ($bonuses as $bonus_post) : ?>
                                <option value="<?php echo esc_attr($bonus_post->ID); ?>" <?php selected($bonus_id, $bonus_post->ID); ?>>
                                    <?php echo esc_html($bonus_post->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button-link-delete pokehub-remove-bonus" style="float:right;">Supprimer</button>
                    </p>

                    <p style="margin-top:8px;">
                        <strong>Description spécifique à cet événement</strong><br>
                        <textarea name="pokehub_event_bonuses[<?php echo esc_attr($index); ?>][description]" rows="2" style="width:100%;"><?php
                            echo esc_textarea($description);
                        ?></textarea>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="button" class="button button-secondary" id="pokehub-add-bonus-row">
                + Ajouter un bonus
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
                            '<option value="">— Sélectionner un bonus —</option>' +
                            <?php foreach ($bonuses as $bonus_post) : ?>
                                '<option value="<?php echo esc_js($bonus_post->ID); ?>"><?php echo esc_js($bonus_post->post_title); ?></option>' +
                            <?php endforeach; ?>
                        '</select>' +
                        '<button type="button" class="button-link-delete pokehub-remove-bonus" style="float:right;">Supprimer</button>' +
                    '</p>' +
                    '<p style="margin-top:8px;">' +
                        '<strong>Description spécifique à cet événement</strong><br>' +
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

    if (!empty($clean)) {
        update_post_meta($post_id, '_pokehub_event_bonuses', $clean);
    } else {
        delete_post_meta($post_id, '_pokehub_event_bonuses');
    }
}
add_action('save_post', 'pokehub_save_event_bonuses');
