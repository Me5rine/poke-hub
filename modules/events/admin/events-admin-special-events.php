<?php
// modules/events/admin/events-admin-special-events.php
if (!defined('ABSPATH')) exit;

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
        $unwanted_params = ['event_ids', 'bulk_action', 'filter_action', 'action', 'action2', '_wpnonce', '_wp_http_referer'];
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

    // URL "Ajouter un événement distant"
    $remote_new_url = apply_filters(
        'pokehub_remote_events_new_url',
        '/wp-admin/post-new.php' // à adapter si besoin
    );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Events', 'poke-hub'); ?></h1>

        <a href="<?php echo esc_url($add_special_url); ?>"
           class="page-title-action">
            <?php esc_html_e('Add Special Event', 'poke-hub'); ?>
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

    $pokemon_id = isset($_POST['pokemon_id']) ? (int) $_POST['pokemon_id'] : 0;
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

    $pokemon_id = isset($_POST['pokemon_id']) ? (int) $_POST['pokemon_id'] : 0;
    if (!$pokemon_id) {
        wp_send_json_error(['message' => 'Invalid pokemon_id']);
    }

    global $wpdb;
    $table = pokehub_get_table('pokemon');
    if (!$table) {
        wp_send_json_success(['has_gender_dimorphism' => false]);
    }

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT extra FROM {$table} WHERE id = %d", $pokemon_id)
    );

    $has_gender_dimorphism = false;
    if ($row && !empty($row->extra)) {
        $extra = json_decode($row->extra, true);
        if (is_array($extra) && !empty($extra['has_gender_dimorphism'])) {
            $has_gender_dimorphism = true;
        }
    }

    wp_send_json_success(['has_gender_dimorphism' => $has_gender_dimorphism]);
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

    $events_table              = pokehub_get_table('special_events');
    $event_pokemon_table       = pokehub_get_table('special_event_pokemon');
    $event_pokemon_attacks_tbl = pokehub_get_table('special_event_pokemon_attacks');
    $event_bonus_table         = pokehub_get_table('special_event_bonus');

    // 🔹 ID éventuel en édition
    $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

    $event = isset($_POST['event']) && is_array($_POST['event']) ? $_POST['event'] : [];

    $title_en = isset($event['title_en']) ? sanitize_text_field($event['title_en']) : '';
    $title_fr = isset($event['title_fr']) ? sanitize_text_field($event['title_fr']) : '';
    
    // Pour la compatibilité, on garde title = title_en
    $title = $title_en;

    // 🔹 Image : ID de média + URL
    $image_id  = !empty($event['image_id']) ? absint($event['image_id']) : 0;
    $image_url = !empty($event['image_url']) ? esc_url_raw($event['image_url']) : '';
    
    // ----- SLUG -----
    // Si vide → généré depuis le titre EN comme WordPress
    $raw_slug = '';
    if (!empty($event['slug'])) {
        $raw_slug = $event['slug'];
    } elseif (!empty($title_en)) {
        $raw_slug = $title_en;
    }

    // Si encore vide, on ne peut rien faire
    if (empty($raw_slug)) {
        wp_die(__('A title (EN) or slug is required to generate the event slug.', 'poke-hub'));
    }

    // Génère un slug unique (remote + special)
    $slug = pokehub_generate_unique_event_slug($raw_slug, $event_id);

    $event_type  = isset($event['event_type']) ? sanitize_title($event['event_type']) : '';
    $description = isset($event['description']) ? wp_kses_post($event['description']) : '';

    $start_raw = isset($event['start']) ? trim($event['start']) : '';
    $end_raw   = isset($event['end'])   ? trim($event['end'])   : '';

    // 🔹 Mode : local / fixed
    $mode = isset($event['mode']) ? sanitize_key($event['mode']) : 'local';
    if (!in_array($mode, ['local', 'fixed'], true)) {
        $mode = 'local';
    }

    // 🔹 Récurrence
    $recurring = !empty($event['recurring']) ? 1 : 0;

    $recurring_freq = isset($event['recurring_freq'])
        ? sanitize_key($event['recurring_freq'])
        : 'weekly';

    if (!in_array($recurring_freq, ['daily', 'weekly', 'monthly'], true)) {
        $recurring_freq = 'weekly';
    }

    $recurring_interval = isset($event['recurring_interval'])
        ? (int) $event['recurring_interval']
        : 1;

    if ($recurring_interval < 1) {
        $recurring_interval = 1;
    }

    // 🔹 Fenêtre de fin de récurrence
    $recurring_window_raw = isset($event['recurring_window_end'])
        ? trim($event['recurring_window_end'])
        : '';

    $recurring_window_end_ts = 0;

    if ($recurring && $recurring_window_raw !== '') {
        try {
            $tz        = wp_timezone();
            $dt_window = new DateTime($recurring_window_raw, $tz);
            $recurring_window_end_ts = $dt_window->getTimestamp();
        } catch (Exception $e) {
            $recurring_window_end_ts = 0;
        }
    }

    // 🔹 Conversion start/end en timestamp (timezone WP)
    $start_ts = 0;
    $end_ts   = 0;

    if ($start_raw !== '') {
        try {
            $tz       = wp_timezone();
            $dt_start = new DateTime($start_raw, $tz);
            $start_ts = $dt_start->getTimestamp();
        } catch (Exception $e) {
            $start_ts = 0;
        }
    }

    if ($end_raw !== '') {
        try {
            $tz     = wp_timezone();
            $dt_end = new DateTime($end_raw, $tz);
            $end_ts = $dt_end->getTimestamp();
        } catch (Exception $e) {
            $end_ts = 0;
        }
    }

    if (!$title_en || !$title_fr || !$slug || !$event_type || !$start_ts || !$end_ts) {
        wp_die(__('Missing required fields.', 'poke-hub'));
    }

    // 🔹 Unicité du slug : on vérifie qu’il n’est pas utilisé par un autre event
    $existing_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$events_table} WHERE slug = %s",
            $slug
        )
    );

    if ($existing_id && (int) $existing_id !== $event_id) {
        wp_die(__('This slug is already used by another event.', 'poke-hub'));
    }

    // Vérifier collision avec un remote event (post_name)
    $remote_posts = pokehub_get_table('remote_posts');

    $remote_slug_exists = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT ID
            FROM {$remote_posts}
            WHERE post_name = %s
            LIMIT 1
            ",
            $slug
        )
    );

    if ($remote_slug_exists) {
        wp_die(__('This slug is already used by a remote event.', 'poke-hub'));
    }

    $data = [
        'slug'                    => $slug,
        'title'                   => $title, // Compatibilité : garde title = title_en
        'title_en'                => $title_en,
        'title_fr'                => $title_fr,
        'description'             => $description,
        'event_type'              => $event_type,
        'start_ts'                => $start_ts,
        'end_ts'                  => $end_ts,
        'mode'                    => $mode,
        'recurring'               => $recurring,
        'recurring_freq'          => $recurring_freq,
        'recurring_interval'      => $recurring_interval,
        'recurring_window_end_ts' => $recurring_window_end_ts,
        'image_id'                => $image_id ?: null,
        'image_url'               => $image_id ? '' : $image_url,
    ];

    $formats = [
        '%s', // slug
        '%s', // title
        '%s', // title_en
        '%s', // title_fr
        '%s', // description
        '%s', // event_type
        '%d', // start_ts
        '%d', // end_ts
        '%s', // mode
        '%d', // recurring
        '%s', // recurring_freq
        '%d', // recurring_interval
        '%d', // recurring_window_end_ts
        '%d', // image_id
        '%s', // image_url
    ];

    // 🔹 Création vs mise à jour
    if ($event_id > 0) {
        // UPDATE
        $wpdb->update(
            $events_table,
            $data,
            ['id' => $event_id],
            $formats,
            ['%d']
        );
    } else {
        // INSERT
        $wpdb->insert(
            $events_table,
            $data,
            $formats
        );
        $event_id = (int) $wpdb->insert_id;
    }

    if ($event_id <= 0) {
        wp_die(__('Could not save event.', 'poke-hub'));
    }

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
            $pokemon_id = isset($p['pokemon_id']) ? (int) $p['pokemon_id'] : 0;
            if (!$pokemon_id) {
                continue;
            }

            // Récupérer le genre (male, female, ou null)
            $gender = null;
            if (!empty($p['gender']) && in_array($p['gender'], ['male', 'female'], true)) {
                $gender = sanitize_text_field($p['gender']);
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

    // Purge Nginx Helper cache and WordPress cache so the updated event appears immediately
    if (function_exists('poke_hub_purge_module_cache')) {
        poke_hub_purge_module_cache(
            ['poke_hub_events'],
            'poke_hub_events',
            'poke_hub_events_all'
        );
    }

    // Déterminer si c'est un ajout ou une mise à jour
    // Si event_id existe dans POST et est > 0, c'est une mise à jour
    $is_new = ($event_id <= 0);
    
    // Construire les arguments de redirection
    $redirect_args = [
        'page' => 'poke-hub-events',
    ];
    
    // Ajouter le paramètre added ou updated
    if ($is_new) {
        $redirect_args['added'] = 1;
    } else {
        $redirect_args['updated'] = 1;
    }
    
    // Préserver les paramètres de filtrage depuis la requête POST (champs cachés)
    // ou depuis le referer HTTP
    $preserve_params = ['event_status', 'event_source', 'event_type', 's', 'paged', 'orderby', 'order'];
    
    // Récupérer les paramètres depuis POST (champs cachés du formulaire)
    foreach ($preserve_params as $param) {
        if (isset($_POST[$param]) && $_POST[$param] !== '') {
            if ($param === 's') {
                $redirect_args[$param] = sanitize_text_field($_POST[$param]);
            } elseif (in_array($param, ['paged', 'orderby', 'order'])) {
                $redirect_args[$param] = sanitize_key($_POST[$param]);
            } else {
                $redirect_args[$param] = sanitize_text_field($_POST[$param]);
            }
        }
    }
    
    // Si pas de paramètres dans POST, essayer de les récupérer depuis le referer
    // Le referer devrait contenir l'URL de la page de liste avec les filtres
    $referer = wp_get_referer();
    if ($referer) {
        $referer_parsed = parse_url($referer);
        if (!empty($referer_parsed['query'])) {
            parse_str($referer_parsed['query'], $referer_params);
            foreach ($preserve_params as $param) {
                // Ne pas écraser si déjà défini depuis POST
                if (isset($redirect_args[$param])) {
                    continue;
                }
                
                if (isset($referer_params[$param]) && $referer_params[$param] !== '') {
                    if ($param === 's') {
                        $redirect_args[$param] = sanitize_text_field($referer_params[$param]);
                    } elseif (in_array($param, ['paged', 'orderby', 'order'])) {
                        $redirect_args[$param] = sanitize_key($referer_params[$param]);
                    } else {
                        $redirect_args[$param] = sanitize_text_field($referer_params[$param]);
                    }
                }
            }
        }
    }
    
    // Nettoyer les paramètres vides et inutiles pour éviter les URLs avec des paramètres vides
    $redirect_args = array_filter($redirect_args, function($value, $key) {
        // Supprimer les valeurs vides, null, et les paramètres WordPress inutiles
        if ($value === '' || $value === null || $value === '-1') {
            return false;
        }
        // Ignorer les paramètres WordPress de sécurité
        if (strpos($key, '_wp') === 0 || $key === 'action' || $key === 'action2' || $key === 'filter_action') {
            return false;
        }
        return true;
    }, ARRAY_FILTER_USE_BOTH);
    
    wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
    exit;
});
