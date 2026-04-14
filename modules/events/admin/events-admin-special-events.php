<?php
// modules/events/admin/events-admin-special-events.php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/events-admin-special-event-save-helpers.php';

// On inclut la classe de la liste si besoin
if ( ! class_exists( 'PokeHub_Events_List_Table' ) ) {
    require_once __DIR__ . '/events-class-pokehub-events-list-table.php';
}

/**
 * Page Events : liste + boutons "Ajouter".
 */
function pokehub_render_special_events_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to access this page.', 'poke-hub'));
    }

    // Nettoyer l'URL si c'est une soumission de formulaire avec des paramètres inutiles
    // (filtrage ou action en masse)
    if ((isset($_GET['filter_action']) && !empty($_GET['filter_action'])) || 
        (isset($_GET['bulk_action']) && !empty($_GET['bulk_action']))) {
        
        $clean_params = [];
        $useful_params = ['page', 'event_status', 'event_source', 'event_type', 's', 'paged', 'orderby', 'order', 'added', 'updated', 'deleted'];
        
        foreach ($useful_params as $param) {
            if (isset($_GET[$param]) && $_GET[$param] !== '' && $_GET[$param] !== '-1') {
                $clean_params[$param] = sanitize_text_field($_GET[$param]);
            }
        }
        
        // Supprimer les paramètres inutiles (event_ids, bulk_action, filter_action, etc.)
        $unwanted_params = ['event_ids', 'bulk_action', 'filter_action', 'action', 'action2', '_wpnonce', '_wp_http_referer', 'event_id'];
        foreach ($unwanted_params as $param) {
            unset($clean_params[$param]);
        }
        
        // Si on a des paramètres propres différents de ceux actuels, rediriger
        $current_clean = array_intersect_key($_GET, array_flip($useful_params));
        $current_clean = array_filter($current_clean, function($v) { return $v !== '' && $v !== '-1'; });
        
        // Vérifier s'il y a des paramètres indésirables dans l'URL actuelle
        $has_unwanted = false;
        foreach ($unwanted_params as $param) {
            if (isset($_GET[$param])) {
                $has_unwanted = true;
                break;
            }
        }
        
        if ($has_unwanted || count($clean_params) !== count($current_clean) || !empty(array_diff_assoc($clean_params, $current_clean))) {
            wp_redirect(add_query_arg($clean_params, admin_url('admin.php')));
            exit;
        }
    }

    $action   = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    $event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

    // ---------- Vue "Ajouter un événement spécial" ----------
    if ($action === 'add_special') {
        pokehub_render_special_event_form('add');
        return;
    }

    // ---------- Pass GO (formulaire dédié) ----------
    if ($action === 'add_go_pass' && function_exists('pokehub_render_go_pass_event_form')) {
        pokehub_render_go_pass_event_form('add');
        return;
    }

    if ($action === 'edit_go_pass' && $event_id > 0 && function_exists('pokehub_render_go_pass_event_form')) {
        global $wpdb;
        $table = pokehub_get_table('special_events');
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $event_id));
        if (!$row) {
            wp_die(__('Special event not found.', 'poke-hub'));
        }
        if (!pokehub_is_go_pass_special_event($row)) {
            wp_die(__('This event is not a GO Pass.', 'poke-hub'));
        }
        $etype = null;
        if (!empty($row->event_type) && function_exists('poke_hub_events_get_event_type_by_slug')) {
            $etype = poke_hub_events_get_event_type_by_slug((string) $row->event_type);
        }
        $event = (object) [
            'id'                        => (int) $row->id,
            'slug'                      => (string) $row->slug,
            'title'                     => (string) $row->title,
            'title_en'                  => isset($row->title_en) ? (string) $row->title_en : (string) $row->title,
            'title_fr'                  => isset($row->title_fr) ? (string) $row->title_fr : (string) $row->title,
            'description'               => (string) $row->description,
            'event_type_slug'           => (string) $row->event_type,
            'event_type_name'           => $etype ? (string) $etype->name : (string) $row->event_type,
            'event_type_color'          => $etype ? (string) $etype->event_type_color : '',
            'start_ts'                  => (int) $row->start_ts,
            'end_ts'                    => (int) $row->end_ts,
            'mode'                      => !empty($row->mode) ? (string) $row->mode : 'local',
            'recurring'                 => !empty($row->recurring) ? (int) $row->recurring : 0,
            'recurring_freq'            => !empty($row->recurring_freq) ? (string) $row->recurring_freq : 'weekly',
            'recurring_interval'        => !empty($row->recurring_interval) ? (int) $row->recurring_interval : 1,
            'recurring_window_end_ts'   => !empty($row->recurring_window_end_ts) ? (int) $row->recurring_window_end_ts : 0,
            'image_id'                  => !empty($row->image_id) ? (int) $row->image_id : 0,
            'image_url'                 => !empty($row->image_url) ? (string) $row->image_url : '',
        ];
        $payload = function_exists('pokehub_content_get_go_pass')
            ? pokehub_content_get_go_pass('special_event', (int) $event->id)
            : null;
        pokehub_render_go_pass_event_form('edit', $event, $payload);
        return;
    }

    // ---------- Vue "Dupliquer un événement spécial" ----------
    if ($action === 'duplicate_special' && $event_id > 0) {
        global $wpdb;

        $table = pokehub_get_table('special_events');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $event_id
            )
        );

        if (!$row) {
            wp_die(__('Special event not found.', 'poke-hub'));
        }

        if (function_exists('pokehub_is_go_pass_special_event') && pokehub_is_go_pass_special_event($row)) {
            $etype = null;
            if (!empty($row->event_type) && function_exists('poke_hub_events_get_event_type_by_slug')) {
                $etype = poke_hub_events_get_event_type_by_slug((string) $row->event_type);
            }
            $event = (object) [
                'id'                        => 0,
                'slug'                      => '',
                'title'                     => (string) $row->title,
                'title_en'                  => isset($row->title_en) ? (string) $row->title_en : (string) $row->title,
                'title_fr'                  => isset($row->title_fr) ? (string) $row->title_fr : (string) $row->title,
                'description'               => (string) $row->description,
                'event_type_slug'           => (string) $row->event_type,
                'event_type_name'           => $etype ? (string) $etype->name : (string) $row->event_type,
                'event_type_color'          => $etype ? (string) $etype->event_type_color : '',
                'start_ts'                  => (int) $row->start_ts,
                'end_ts'                    => (int) $row->end_ts,
                'mode'                      => !empty($row->mode) ? (string) $row->mode : 'local',
                'recurring'                 => !empty($row->recurring) ? (int) $row->recurring : 0,
                'recurring_freq'            => !empty($row->recurring_freq) ? (string) $row->recurring_freq : 'weekly',
                'recurring_interval'        => !empty($row->recurring_interval) ? (int) $row->recurring_interval : 1,
                'recurring_window_end_ts'   => !empty($row->recurring_window_end_ts) ? (int) $row->recurring_window_end_ts : 0,
                'image_id'                  => !empty($row->image_id) ? (int) $row->image_id : 0,
                'image_url'                 => !empty($row->image_url) ? (string) $row->image_url : '',
            ];
            $payload = function_exists('pokehub_content_get_go_pass')
                ? pokehub_content_get_go_pass('special_event', $event_id)
                : null;
            pokehub_render_go_pass_event_form('add', $event, $payload);
            return;
        }

        // Récupérer infos de type d'événement (nom / couleur)
        $etype = null;
        if (!empty($row->event_type) && function_exists('poke_hub_events_get_event_type_by_slug')) {
            $etype = poke_hub_events_get_event_type_by_slug((string) $row->event_type);
        }

        $event = (object) [
            'id'                        => 0, // Nouvel événement
            'slug'                      => '', // Sera généré
            'title'                     => (string) $row->title,
            'title_en'                  => isset($row->title_en) ? (string) $row->title_en : (string) $row->title,
            'title_fr'                  => isset($row->title_fr) ? (string) $row->title_fr : (string) $row->title,
            'description'               => (string) $row->description,
            'event_type_slug'           => (string) $row->event_type,
            'event_type_name'           => $etype ? (string) $etype->name : (string) $row->event_type,
            'event_type_color'          => $etype ? (string) $etype->event_type_color : '',
            'start_ts'                  => (int) $row->start_ts,
            'end_ts'                    => (int) $row->end_ts,

            'mode'                      => !empty($row->mode) ? (string) $row->mode : 'local',
            'recurring'                 => !empty($row->recurring) ? (int) $row->recurring : 0,
            'recurring_freq'            => !empty($row->recurring_freq) ? (string) $row->recurring_freq : 'weekly',
            'recurring_interval'        => !empty($row->recurring_interval) ? (int) $row->recurring_interval : 1,
            'recurring_window_end_ts'   => !empty($row->recurring_window_end_ts) ? (int) $row->recurring_window_end_ts : 0,

            'image_id'                  => !empty($row->image_id) ? (int) $row->image_id : 0,
            'image_url'                 => !empty($row->image_url) ? (string) $row->image_url : '',
        ];

        // Pré-remplissage Pokémon / bonus depuis l'événement original
        $pokemon_rows = function_exists('poke_hub_special_event_get_pokemon_rows')
            ? poke_hub_special_event_get_pokemon_rows($event_id)
            : [];

        $bonus_rows = function_exists('poke_hub_special_event_get_bonus_rows')
            ? poke_hub_special_event_get_bonus_rows($event_id)
            : [];

        pokehub_render_special_event_form('add', $event, $pokemon_rows, $bonus_rows);
        return;
    }

    // ---------- Vue "Éditer un événement spécial" ----------
    if ($action === 'edit_special' && $event_id > 0) {
        global $wpdb;

        $table = pokehub_get_table('special_events');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $event_id
            )
        );

        if (!$row) {
            wp_die(__('Special event not found.', 'poke-hub'));
        }

        if (function_exists('pokehub_is_go_pass_special_event') && pokehub_is_go_pass_special_event($row)) {
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'     => 'poke-hub-events',
                        'action'   => 'edit_go_pass',
                        'event_id' => $event_id,
                    ],
                    admin_url('admin.php')
                )
            );
            exit;
        }

        // Récupérer infos de type d’événement (nom / couleur)
        $etype = null;
        if (!empty($row->event_type) && function_exists('poke_hub_events_get_event_type_by_slug')) {
            $etype = poke_hub_events_get_event_type_by_slug((string) $row->event_type);
        }

        $event = (object) [
            'id'                        => (int) $row->id,
            'slug'                      => (string) $row->slug,
            'title'                     => (string) $row->title,
            'title_en'                  => isset($row->title_en) ? (string) $row->title_en : (string) $row->title,
            'title_fr'                  => isset($row->title_fr) ? (string) $row->title_fr : (string) $row->title,
            'description'               => (string) $row->description,
            'event_type_slug'           => (string) $row->event_type,
            'event_type_name'           => $etype ? (string) $etype->name : (string) $row->event_type,
            'event_type_color'          => $etype ? (string) $etype->event_type_color : '',
            'start_ts'                  => (int) $row->start_ts,
            'end_ts'                    => (int) $row->end_ts,

            'mode'                      => !empty($row->mode) ? (string) $row->mode : 'local',
            'recurring'                 => !empty($row->recurring) ? (int) $row->recurring : 0,
            'recurring_freq'            => !empty($row->recurring_freq) ? (string) $row->recurring_freq : 'weekly',
            'recurring_interval'        => !empty($row->recurring_interval) ? (int) $row->recurring_interval : 1,
            'recurring_window_end_ts'   => !empty($row->recurring_window_end_ts) ? (int) $row->recurring_window_end_ts : 0,

            'image_id'                  => !empty($row->image_id) ? (int) $row->image_id : 0,
            'image_url'                 => !empty($row->image_url) ? (string) $row->image_url : '',
        ];

        // Pré-remplissage Pokémon / bonus
        $pokemon_rows = function_exists('poke_hub_special_event_get_pokemon_rows')
            ? poke_hub_special_event_get_pokemon_rows($event->id)
            : [];

        $bonus_rows = function_exists('poke_hub_special_event_get_bonus_rows')
            ? poke_hub_special_event_get_bonus_rows($event->id)
            : [];

        pokehub_render_special_event_form('edit', $event, $pokemon_rows, $bonus_rows);
        return;
    }

    // ---------- Vue "liste d'événements" (remote + spéciaux) ----------
    $list_table = new PokeHub_Events_List_Table();
    $list_table->prepare_items();

    // URL "Ajouter un événement spécial"
    $add_special_url = add_query_arg(
        [
            'page'   => 'poke-hub-events',
            'action' => 'add_special',
        ],
        admin_url('admin.php')
    );

    $add_go_pass_url = add_query_arg(
        [
            'page'   => 'poke-hub-events',
            'action' => 'add_go_pass',
        ],
        admin_url('admin.php')
    );

    $remote_new_url = function_exists('pokehub_events_get_remote_new_post_url')
        ? pokehub_events_get_remote_new_post_url()
        : apply_filters('pokehub_remote_events_new_url', 'https://jv-actu.com/wp-admin/post-new.php');
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Events', 'poke-hub'); ?></h1>

        <a href="<?php echo esc_url($add_special_url); ?>"
           class="page-title-action">
            <?php esc_html_e('Add Special Event', 'poke-hub'); ?>
        </a>

        <a href="<?php echo esc_url($add_go_pass_url); ?>"
           class="page-title-action">
            <?php esc_html_e('Add GO Pass', 'poke-hub'); ?>
        </a>

        <a href="<?php echo esc_url($remote_new_url); ?>"
           class="page-title-action" target="_blank" rel="noopener">
            <?php esc_html_e('Add Remote Event', 'poke-hub'); ?>
        </a>

        <?php if (!empty($_GET['added'])) : ?>
            <div id="message" class="updated notice notice-success is-dismissible">
                <p><?php esc_html_e('Special event added.', 'poke-hub'); ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['updated'])) : ?>
            <div id="message" class="updated notice notice-success is-dismissible">
                <p><?php esc_html_e('Special event updated.', 'poke-hub'); ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['deleted'])) : ?>
            <div id="message" class="updated notice notice-success is-dismissible">
                <p><?php 
                    printf(
                        esc_html(_n('%d special event deleted.', '%d special events deleted.', (int) $_GET['deleted'], 'poke-hub')),
                        (int) $_GET['deleted']
                    );
                ?></p>
            </div>
        <?php endif; ?>

        <hr class="wp-header-end">

        <form method="get" id="pokehub-events-filter-form">
            <input type="hidden" name="page" value="poke-hub-events" />
            <?php 
            // Afficher le champ de recherche
            $list_table->search_box(__('Search events', 'poke-hub'), 'pokehub-events');
            ?>
            <?php
            $list_table->display();
            ?>
        </form>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Nettoyer l'URL après soumission du formulaire de filtrage
            $('#pokehub-events-filter-form').on('submit', function(e) {
                var $form = $(this);
                var params = {};
                
                // Récupérer uniquement les paramètres utiles et non vides
                $form.find('input, select').each(function() {
                    var $field = $(this);
                    var name = $field.attr('name');
                    var value = $field.val();
                    
                    // Ignorer les champs sans nom, les valeurs vides, et les paramètres WordPress inutiles
                    if (!name || !value || value === '' || value === '-1') {
                        return;
                    }
                    
                    // Ignorer les paramètres WordPress de sécurité et les actions vides
                    if (name.indexOf('_wp') === 0 || name === 'action' || name === 'action2' || name === 'filter_action') {
                        return;
                    }
                    
                    params[name] = value;
                });
                
                // Construire la nouvelle URL propre
                var baseUrl = '<?php echo esc_js(admin_url('admin.php')); ?>';
                var queryString = $.param(params);
                var cleanUrl = baseUrl + (queryString ? '?' + queryString : '');
                
                // Rediriger vers l'URL propre
                window.location.href = cleanUrl;
                e.preventDefault();
                return false;
            });
        });
        </script>
    </div>
    <?php
}

/**
 * AJAX : récupérer les attaques spéciales d’un Pokémon
 */
add_action('wp_ajax_pokehub_get_pokemon_special_attacks', function () {
    check_ajax_referer('pokehub_pokemon_attacks', 'nonce');

    $raw_pid = isset($_POST['pokemon_id']) ? wp_unslash((string) $_POST['pokemon_id']) : '';
    $pokemon_id = 0;
    if ($raw_pid !== '' && preg_match('/^(\d+)\|(male|female)$/i', $raw_pid, $m)) {
        $pokemon_id = (int) $m[1];
    } else {
        $pokemon_id = (int) $raw_pid;
    }
    if ($pokemon_id <= 0) {
        wp_send_json_error(['message' => 'Invalid Pokémon ID']);
    }

    // Cette fonction doit exister (dans ton module Pokémon ou un helper commun)
    $attacks = pokehub_get_pokemon_special_attacks($pokemon_id);

    wp_send_json_success($attacks);
});

add_action('wp_ajax_pokehub_check_pokemon_gender_dimorphism', function () {
    // Accepter plusieurs nonces possibles
    $valid_nonce = false;
    $nonce_actions = [
        'pokehub_special_events_nonce',
        'pokehub_wild_pokemon_ajax',
        'pokehub_new_pokemon_ajax',
        'pokehub_habitats_ajax',
        'pokehub_quests_ajax',
    ];
    
    foreach ($nonce_actions as $action) {
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], $action)) {
            $valid_nonce = true;
            break;
        }
    }
    
    if (!$valid_nonce) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    $raw_pid = isset($_POST['pokemon_id']) ? wp_unslash((string) $_POST['pokemon_id']) : '';
    $pokemon_id = 0;
    if ($raw_pid !== '' && preg_match('/^(\d+)\|(male|female)$/i', $raw_pid, $m)) {
        $pokemon_id = (int) $m[1];
    } else {
        $pokemon_id = (int) $raw_pid;
    }
    if (!$pokemon_id) {
        wp_send_json_error(['message' => 'Invalid pokemon_id']);
    }

    $profile = function_exists('poke_hub_pokemon_get_gender_profile')
        ? poke_hub_pokemon_get_gender_profile($pokemon_id)
        : [
            'has_gender_dimorphism'   => false,
            'gender_ratio'            => ['male' => 0.0, 'female' => 0.0],
            'available_genders'       => [],
            'spawn_available_genders' => [],
            'default_gender'          => null,
        ];

    wp_send_json_success([
        'has_gender_dimorphism'   => !empty($profile['has_gender_dimorphism']),
        'gender_ratio'            => is_array($profile['gender_ratio'] ?? null) ? $profile['gender_ratio'] : ['male' => 0.0, 'female' => 0.0],
        'available_genders'       => is_array($profile['available_genders'] ?? null) ? array_values($profile['available_genders']) : [],
        'spawn_available_genders' => is_array($profile['spawn_available_genders'] ?? null) ? array_values($profile['spawn_available_genders']) : [],
        'default_gender'          => isset($profile['default_gender']) ? $profile['default_gender'] : null,
    ]);
});


/**
 * Traitement du formulaire d’ajout / édition d’événement spécial
 */
add_action('admin_post_pokehub_save_special_event', function () {
    if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to do this.', 'poke-hub'));
    }

    if (empty($_POST['pokehub_special_event_nonce']) ||
        !wp_verify_nonce($_POST['pokehub_special_event_nonce'], 'pokehub_save_special_event')
    ) {
        wp_die(__('Security check failed.', 'poke-hub'));
    }

    global $wpdb;

    $event_pokemon_table       = pokehub_get_table('special_event_pokemon');
    $event_pokemon_attacks_tbl = pokehub_get_table('special_event_pokemon_attacks');
    $event_bonus_table         = pokehub_get_table('special_event_bonus');

    $incoming_event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $was_new             = $incoming_event_id <= 0;

    $save_result = pokehub_special_events_save_row_from_post(null);
    if (is_string($save_result)) {
        wp_die(esc_html($save_result));
    }
    $event_id = (int) $save_result;

    $events_table_ts = pokehub_get_table('special_events');
    $row_ts          = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT start_ts, end_ts FROM {$events_table_ts} WHERE id = %d LIMIT 1",
            $event_id
        )
    );
    $start_ts = $row_ts && isset($row_ts->start_ts) ? (int) $row_ts->start_ts : 0;
    $end_ts   = $row_ts && isset($row_ts->end_ts) ? (int) $row_ts->end_ts : 0;

    // 🔹 Pokémon & bonus : on reset tout pour cet event puis on ré-insère

    // Supprimer les anciennes liaisons (en édition)
    $wpdb->delete($event_pokemon_attacks_tbl, ['event_id' => $event_id], ['%d']);
    $wpdb->delete($event_pokemon_table,       ['event_id' => $event_id], ['%d']);
    $wpdb->delete($event_bonus_table,         ['event_id' => $event_id], ['%d']);

    // Décodage JSON Pokémon & bonus
    $pokemon_payload = !empty($_POST['pokemon_payload']) ? json_decode(stripslashes($_POST['pokemon_payload']), true) : [];
    $bonuses_payload = !empty($_POST['bonuses_payload']) ? json_decode(stripslashes($_POST['bonuses_payload']), true) : [];

    if (is_array($pokemon_payload)) {
        foreach ($pokemon_payload as $p) {
            $raw_pokemon = isset($p['pokemon_id']) ? (string) $p['pokemon_id'] : '';
            $pokemon_id = 0;
            $gender_from_token = null;
            if ($raw_pokemon !== '' && preg_match('/^(\d+)\|(male|female)$/i', $raw_pokemon, $m)) {
                $pokemon_id = (int) $m[1];
                $gender_from_token = strtolower((string) $m[2]);
            } else {
                $pokemon_id = (int) $raw_pokemon;
            }
            if (!$pokemon_id) {
                continue;
            }

            // Récupérer le genre (male, female, ou null)
            $gender = null;
            if ($gender_from_token !== null && in_array($gender_from_token, ['male', 'female'], true)) {
                $gender = $gender_from_token;
            }
            if (!empty($p['gender']) && in_array($p['gender'], ['male', 'female'], true)) {
                $gender = sanitize_text_field((string) $p['gender']);
            }

            $wpdb->insert(
                $event_pokemon_table,
                [
                    'event_id'   => $event_id,
                    'pokemon_id' => $pokemon_id,
                    'gender'     => $gender,
                ],
                ['%d', '%d', '%s']
            );

            if (!empty($p['attacks']) && is_array($p['attacks'])) {
                foreach ($p['attacks'] as $atk) {
                    $attack_id = isset($atk['id']) ? (int) $atk['id'] : 0;
                    $forced    = !empty($atk['forced']) ? 1 : 0;
                    if (!$attack_id) {
                        continue;
                    }

                    $wpdb->insert(
                        $event_pokemon_attacks_tbl,
                        [
                            'event_id'   => $event_id,
                            'pokemon_id' => $pokemon_id,
                            'attack_id'  => $attack_id,
                            'is_forced'  => $forced,
                        ],
                        ['%d', '%d', '%d', '%d']
                    );
                }
            }
        }
    }

    if (is_array($bonuses_payload)) {
        foreach ($bonuses_payload as $b) {
            $bonus_id = isset($b['bonus_id']) ? (int) $b['bonus_id'] : 0;
            $desc     = isset($b['description']) ? wp_kses_post($b['description']) : '';
            if (!$bonus_id) {
                continue;
            }

            $wpdb->insert(
                $event_bonus_table,
                [
                    'event_id'    => $event_id,
                    'bonus_id'    => $bonus_id,
                    'description' => $desc,
                ],
                ['%d', '%d', '%s']
            );
        }
    }

    if (function_exists('pokehub_content_sync_dates_for_source')) {
        pokehub_content_sync_dates_for_source('special_event', $event_id, $start_ts, $end_ts);
    }

    pokehub_special_events_redirect_after_save($event_id, $was_new);
});
