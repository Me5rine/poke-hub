<?php
// modules/games/public/games-shortcode-pokedle.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode [pokedle] pour afficher le jeu Pokedle
 */
function poke_hub_shortcode_pokedle($atts) {
    $atts = shortcode_atts([
        // Valeurs par défaut, mais configurables en front
    ], $atts, 'pokedle');

    // Vérifier que le module pokemon est actif
    if (!poke_hub_is_module_active('pokemon')) {
        return '<p>' . esc_html__('The Pokémon module must be active to play Pokedle.', 'poke-hub') . '</p>';
    }

    // Récupérer la date et génération depuis les paramètres URL (pour l'historique)
    $selected_date = isset($_GET['pokedle_date']) ? sanitize_text_field($_GET['pokedle_date']) : current_time('Y-m-d');
    $selected_generation_id = isset($_GET['pokedle_gen']) ? (int) $_GET['pokedle_gen'] : 0;
    
    // Valider la date
    $selected_date = date('Y-m-d', strtotime($selected_date)) ?: current_time('Y-m-d');
    $today = current_time('Y-m-d');
    
    // Déterminer les Pokémon disponibles selon la génération sélectionnée
    if ($selected_generation_id > 0) {
        $available_pokemon = poke_hub_games_get_pokemon_by_generation($selected_generation_id);
        if (empty($available_pokemon)) {
            return '<p>' . esc_html__('No Pokémon available for this generation.', 'poke-hub') . '</p>';
        }
    } else {
        // Mode toutes générations par défaut
        $available_pokemon = poke_hub_games_get_all_pokemon();
        if (empty($available_pokemon)) {
            return '<p>' . esc_html__('No Pokémon available for the game.', 'poke-hub') . '</p>';
        }
    }
    
    // Récupérer le Pokémon du jour pour cette date et génération
    $daily_pokemon = poke_hub_pokedle_get_daily_pokemon_from_list($available_pokemon, $selected_date);
    if (!$daily_pokemon) {
        return '<p>' . esc_html__('No Pokémon available for the game.', 'poke-hub') . '</p>';
    }
    
    // Sauvegarder le Pokémon du jour dans la table dédiée
    // Cela permet de garder une trace même si personne ne joue
    $normalized_generation_id = $selected_generation_id > 0 ? $selected_generation_id : null;
    poke_hub_pokedle_save_daily_pokemon($selected_date, $normalized_generation_id, (int) $daily_pokemon['id']);

    // Vérifier si l'utilisateur a déjà joué pour cette date/génération
    // En mode dev, ignorer la vérification du score pour permettre de rejouer à chaque refresh
    $is_dev_mode = (bool) get_option('poke_hub_games_dev_mode', false);
    $user_id = is_user_logged_in() ? get_current_user_id() : null;

    // En mode dev, ne pas vérifier le score pour permettre de rejouer
    $user_score = $is_dev_mode ? null : poke_hub_pokedle_get_user_score($user_id, $selected_date);

    // Récupérer toutes les générations pour le sélecteur
    $all_generations = poke_hub_games_get_all_generations();

    // Récupérer la génération du Pokémon du jour
    $daily_pokemon_generation = poke_hub_games_get_pokemon_generation((int) $daily_pokemon['id']);

    // S'assurer que le Pokémon du jour est dans la liste allPokemon
    // Vérifier si le Pokémon du jour est déjà dans $available_pokemon
    $daily_pokemon_in_list = false;
    foreach ($available_pokemon as $p) {
        if ((int) $p['id'] === (int) $daily_pokemon['id']) {
            $daily_pokemon_in_list = true;
            break;
        }
    }
    
    // Si le Pokémon du jour n'est pas dans la liste, l'ajouter
    if (!$daily_pokemon_in_list) {
        $daily_pokemon_full = poke_hub_games_get_pokemon_by_id((int) $daily_pokemon['id']);
        if ($daily_pokemon_full) {
            $available_pokemon[] = $daily_pokemon_full;
        }
    }

    // Préparer les données pour le JavaScript
    $pokedle_data = [
        'dailyPokemonId' => (int) $daily_pokemon['id'],
        'dailyPokemonGenerationId' => $daily_pokemon_generation ? (int) $daily_pokemon_generation['id'] : null,
        'currentGameDate' => $selected_date,
        'currentGenerationId' => $selected_generation_id > 0 ? $selected_generation_id : null,
        'isAllGenerationsMode' => $selected_generation_id === 0,
        'allPokemon' => array_map(function($p) {
            // Récupérer l'URL de l'image du Pokémon
            $pokemon_obj = (object) $p;
            $image_url = '';
            if (function_exists('poke_hub_pokemon_get_image_url')) {
                $image_url = poke_hub_pokemon_get_image_url($pokemon_obj);
            }
            
            // Récupérer la génération du Pokémon
            $generation = poke_hub_games_get_pokemon_generation((int) $p['id']);
            
            return [
                'id' => (int) $p['id'],
                'name' => $p['name_fr'] ?: $p['name_en'],
                'slug' => $p['slug'],
                'dex_number' => (int) $p['dex_number'],
                'image_url' => $image_url,
                'generation_id' => $generation ? (int) $generation['id'] : null,
                'generation_number' => $generation ? (int) $generation['generation_number'] : null,
            ];
        }, $available_pokemon),
        'allGenerations' => array_map(function($g) {
            return [
                'id' => (int) $g['id'],
                'slug' => $g['slug'],
                'name' => $g['name_fr'] ?: $g['name_en'],
                'number' => (int) $g['generation_number'],
            ];
        }, $all_generations),
        'isLoggedIn' => is_user_logged_in(),
        'userId' => $user_id,
        'today' => $today,
        'hasPlayed' => $user_score !== null,
        'userScore' => $user_score ? [
            'attempts' => (int) $user_score['attempts'],
            'is_success' => (bool) $user_score['is_success'],
            'completion_time' => (int) $user_score['completion_time'],
        ] : null,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('poke_hub_pokedle_nonce'),
        'imageBaseUrl' => function_exists('poke_hub_pokemon_get_assets_base_url') ? poke_hub_pokemon_get_assets_base_url() : '',
        'i18n' => [
            'allGenerations' => __('All Generations', 'poke-hub'),
            'attempts' => __('attempts', 'poke-hub'),
            'attempt' => __('attempt', 'poke-hub'),
            'checking' => __('Checking...', 'poke-hub'),
            'guess' => __('Guess', 'poke-hub'),
            'errorVerification' => __('Error during verification.', 'poke-hub'),
            'errorConnection' => __('Connection error. Please try again.', 'poke-hub'),
            'alreadyGuessed' => __('You have already guessed this Pokémon!', 'poke-hub'),
            'pokemonNotFound' => __('Pokémon not found. Please try again.', 'poke-hub'),
            'pleaseSelectDate' => __('Please select a date.', 'poke-hub'),
            'noPokemonForGeneration' => __('No Pokémon available for this generation.', 'poke-hub'),
            'today' => __('Today', 'poke-hub'),
            'allTime' => __('All Time', 'poke-hub'),
            'noScoresYet' => __('No scores yet.', 'poke-hub'),
            'first' => __('1st', 'poke-hub'),
            'second' => __('2nd', 'poke-hub'),
            'third' => __('3rd', 'poke-hub'),
            'anonymous' => __('Anonymous', 'poke-hub'),
            'revealedHints' => __('Revealed Hints', 'poke-hub'),
            'type1' => __('Type 1', 'poke-hub'),
            'type2' => __('Type 2', 'poke-hub'),
            'generation' => __('Generation', 'poke-hub'),
            'evolutionStage' => __('Evolution Stage', 'poke-hub'),
            'height' => __('Height', 'poke-hub'),
            'weight' => __('Weight', 'poke-hub'),
            'pokemon' => __('Pokémon', 'poke-hub'),
            'congratulations' => __('Congratulations!', 'poke-hub'),
            'youFound' => __('You found', 'poke-hub'),
            'in' => __('in', 'poke-hub'),
            'time' => __('Time', 'poke-hub'),
            'tooBad' => __('Too bad!', 'poke-hub'),
            'theMysteryPokemonWas' => __('The mystery Pokémon was', 'poke-hub'),
            'youMade' => __('You made', 'poke-hub'),
        ],
    ];

    // Enqueue le script JavaScript
    wp_enqueue_script(
        'poke-hub-pokedle',
        POKE_HUB_URL . 'assets/js/poke-hub-pokedle.js',
        ['jquery'],
        POKE_HUB_VERSION,
        true
    );

    wp_localize_script('poke-hub-pokedle', 'pokeHubPokedle', $pokedle_data);

    ob_start();
    ?>
    <div id="poke-hub-pokedle" class="poke-hub-pokedle" data-daily-pokemon-id="<?php echo esc_attr($daily_pokemon['id']); ?>">
        
        <!-- Interface de jeu -->
        <div id="pokedle-game-container" class="pokedle-game-container">
            <div class="pokedle-header">
                <div class="me5rine-lab-dashboard-header">
                    <h2 class="me5rine-lab-title-large"><?php echo esc_html__('Pokedle', 'poke-hub'); ?></h2>
                    <button id="pokedle-change-game" class="me5rine-lab-form-button me5rine-lab-form-button-secondary">
                        <?php echo esc_html__('Choose another Pokedle', 'poke-hub'); ?>
                    </button>
                </div>
                <p class="me5rine-lab-subtitle" id="pokedle-description">
                    <?php echo esc_html__('Guess the mystery Pokémon of the day!', 'poke-hub'); ?>
                </p>
                <div class="pokedle-game-info">
                    <span id="pokedle-current-mode" class="me5rine-lab-status me5rine-lab-status-info"><?php echo esc_html__('All Generations', 'poke-hub'); ?></span>
                    <span id="pokedle-attempts-count" class="me5rine-lab-status me5rine-lab-status-info"></span>
                </div>
            </div>
            
            <!-- Sélecteur de Pokedle (masqué par défaut) -->
            <div id="pokedle-game-selector" class="pokedle-game-selector" style="display:none;">
                <h3 class="me5rine-lab-title-medium"><?php echo esc_html__('Choose a Pokedle', 'poke-hub'); ?></h3>
                <div class="me5rine-lab-filters">
                    <div class="me5rine-lab-filter-group">
                        <label for="pokedle-select-date" class="me5rine-lab-form-label me5rine-lab-filter-label">
                            <?php echo esc_html__('Date', 'poke-hub'); ?>
                        </label>
                        <input type="date" id="pokedle-select-date" class="me5rine-lab-form-input me5rine-lab-filter-input" value="<?php echo esc_attr($selected_date); ?>" max="<?php echo esc_attr($today); ?>" />
                    </div>
                    <div class="me5rine-lab-filter-group">
                        <label for="pokedle-select-generation" class="me5rine-lab-form-label me5rine-lab-filter-label">
                            <?php echo esc_html__('Generation', 'poke-hub'); ?>
                        </label>
                        <select id="pokedle-select-generation" class="me5rine-lab-form-select me5rine-lab-filter-select">
                            <option value="all" <?php selected($selected_generation_id, 0); ?>><?php echo esc_html__('All Generations', 'poke-hub'); ?></option>
                            <?php foreach ($all_generations as $gen): ?>
                                <option value="<?php echo esc_attr($gen['id']); ?>" <?php selected($selected_generation_id, $gen['id']); ?>>
                                    <?php echo esc_html($gen['name_fr'] ?: $gen['name_en']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button id="pokedle-load-game" class="me5rine-lab-form-button">
                        <?php echo esc_html__('Load this Pokedle', 'poke-hub'); ?>
                    </button>
                </div>
            </div>

            <?php if ($user_score && $user_score['is_success']): ?>
                <div class="pokedle-completed">
                    <p>
                        <?php echo esc_html__('You have already solved today\'s Pokedle in', 'poke-hub'); ?>
                        <strong><?php echo esc_html($user_score['attempts']); ?></strong>
                        <?php 
                        $attempts_text = $user_score['attempts'] === 1 
                            ? esc_html__('attempt', 'poke-hub') 
                            : esc_html__('attempts', 'poke-hub');
                        echo $attempts_text;
                        ?>!
                    </p>
                    <button class="me5rine-lab-form-button pokedle-show-result" data-pokemon-id="<?php echo esc_attr($daily_pokemon['id']); ?>">
                        <?php echo esc_html__('View Result', 'poke-hub'); ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="pokedle-game" <?php echo $user_score && $user_score['is_success'] ? 'style="display:none;"' : ''; ?>>
            <div class="pokedle-input-container">
                <input 
                    type="text" 
                    id="pokedle-pokemon-input" 
                    class="me5rine-lab-form-input pokedle-pokemon-input" 
                    placeholder="<?php echo esc_attr__('Type a Pokémon name...', 'poke-hub'); ?>"
                    autocomplete="off"
                />
                <button id="pokedle-submit" class="me5rine-lab-form-button">
                    <?php echo esc_html__('Guess', 'poke-hub'); ?>
                </button>
                <button id="pokedle-show-answer" class="me5rine-lab-form-button me5rine-lab-form-button-secondary" style="display:none;">
                    <?php echo esc_html__('See Answer', 'poke-hub'); ?>
                </button>
            </div>

            <div class="pokedle-attempts-header" id="pokedle-attempts-header">
                <div class="pokedle-header-cell pokedle-header-pokemon"><?php echo esc_html__('Pokémon', 'poke-hub'); ?></div>
                <div class="pokedle-header-cell pokedle-header-type1"><?php echo esc_html__('Type 1', 'poke-hub'); ?></div>
                <div class="pokedle-header-cell pokedle-header-type2"><?php echo esc_html__('Type 2', 'poke-hub'); ?></div>
                <?php if ($selected_generation_id === 0): ?>
                <div class="pokedle-header-cell pokedle-header-generation"><?php echo esc_html__('Generation', 'poke-hub'); ?></div>
                <?php endif; ?>
                <div class="pokedle-header-cell pokedle-header-evo"><?php echo esc_html__('Stage', 'poke-hub'); ?></div>
                <div class="pokedle-header-cell pokedle-header-height"><?php echo esc_html__('Height', 'poke-hub'); ?></div>
                <div class="pokedle-header-cell pokedle-header-weight"><?php echo esc_html__('Weight', 'poke-hub'); ?></div>
            </div>
            <div class="pokedle-attempts" id="pokedle-attempts">
                <!-- Les tentatives seront ajoutées ici par JavaScript -->
            </div>

            <div class="pokedle-result" id="pokedle-result" style="display:none;">
                <!-- Le résultat sera affiché ici -->
            </div>
        </div>

            <div class="pokedle-leaderboard">
                <h3 class="me5rine-lab-title-medium"><?php echo esc_html__('Today\'s Leaderboard', 'poke-hub'); ?></h3>
                <div id="pokedle-leaderboard-content">
                    <!-- Le classement sera chargé via AJAX -->
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('pokedle', 'poke_hub_shortcode_pokedle');

/**
 * Endpoint AJAX pour soumettre une tentative
 */
function poke_hub_pokedle_ajax_submit_guess() {
    check_ajax_referer('poke_hub_pokedle_nonce', 'nonce');

    $pokemon_id = isset($_POST['pokemon_id']) ? (int) $_POST['pokemon_id'] : 0;
    $daily_pokemon_id = isset($_POST['daily_pokemon_id']) ? (int) $_POST['daily_pokemon_id'] : 0;
    $wrong_attempts_count = isset($_POST['wrong_attempts_count']) ? (int) $_POST['wrong_attempts_count'] : 0;
    $is_all_generations_mode = isset($_POST['is_all_generations_mode']) ? filter_var($_POST['is_all_generations_mode'], FILTER_VALIDATE_BOOLEAN) : true;

    if ($pokemon_id <= 0 || $daily_pokemon_id <= 0) {
        wp_send_json_error(['message' => __('Invalid data.', 'poke-hub')]);
    }

    $comparison = poke_hub_pokedle_compare_pokemon($pokemon_id, $daily_pokemon_id);
    
    // Ajouter les indices progressifs révélés selon le nombre de mauvaises réponses
    // Indices aléatoires (mais déterministes) à 5, 10 et 15 tentatives
    if (!$comparison['is_correct']) {
        $comparison['revealed_hints'] = [];
        
        // Récupérer les informations du Pokémon mystère pour les indices
        $mystery_pokemon = poke_hub_games_get_pokemon_by_id($daily_pokemon_id);
        if ($mystery_pokemon) {
            $mystery_types = poke_hub_games_get_pokemon_types($daily_pokemon_id);
            $mystery_gen = poke_hub_games_get_pokemon_generation($daily_pokemon_id);
            $mystery_stage = poke_hub_games_get_pokemon_evolution_stage($daily_pokemon_id);
            $mystery_height = poke_hub_games_get_pokemon_height($daily_pokemon_id);
            $mystery_weight = poke_hub_games_get_pokemon_weight($daily_pokemon_id);
            
            // Définir les indices disponibles (ordre aléatoire mais déterministe basé sur le Pokémon)
            $available_hints = [];
            
            // Type 1 (toujours disponible)
            if (!empty($mystery_types[0])) {
                $available_hints[] = ['type' => 'type1', 'value' => $mystery_types[0]['name_fr'] ?: $mystery_types[0]['name_en']];
            }
            
            // Type 2 (toujours disponible)
            if (!empty($mystery_types[1])) {
                $available_hints[] = ['type' => 'type2', 'value' => $mystery_types[1]['name_fr'] ?: $mystery_types[1]['name_en']];
            } else {
                $available_hints[] = ['type' => 'type2', 'value' => 'Aucun'];
            }
            
            // Génération (uniquement en mode toutes générations)
            if ($is_all_generations_mode && $mystery_gen) {
                $available_hints[] = ['type' => 'generation', 'value' => $mystery_gen['name_fr'] ?: $mystery_gen['name_en']];
            }
            
            // Stade d'évolution
            if ($mystery_stage !== null) {
                $available_hints[] = ['type' => 'evolution_stage', 'value' => (string) $mystery_stage];
            }
            
            // Taille
            if ($mystery_height !== null) {
                $available_hints[] = ['type' => 'height', 'value' => number_format($mystery_height, 2) . ' m'];
            }
            
            // Poids
            if ($mystery_weight !== null) {
                $available_hints[] = ['type' => 'weight', 'value' => number_format($mystery_weight, 2) . ' kg'];
            }
            
            // Mélanger les indices de manière déterministe (basé sur l'ID du Pokémon du jour)
            // Pour que le même Pokémon révèle les mêmes indices dans le même ordre
            mt_srand($daily_pokemon_id);
            $shuffled_hints = $available_hints;
            for ($i = count($shuffled_hints) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                [$shuffled_hints[$i], $shuffled_hints[$j]] = [$shuffled_hints[$j], $shuffled_hints[$i]];
            }
            mt_srand(); // Réinitialiser le seed
            
            // Révéler les indices aux seuils : 5, 10, 15 tentatives
            $revealed_count = 0;
            if ($wrong_attempts_count >= 5 && $revealed_count < count($shuffled_hints)) {
                $hint = $shuffled_hints[$revealed_count];
                $comparison['revealed_hints'][$hint['type']] = $hint['value'];
                $revealed_count++;
            }
            if ($wrong_attempts_count >= 10 && $revealed_count < count($shuffled_hints)) {
                $hint = $shuffled_hints[$revealed_count];
                $comparison['revealed_hints'][$hint['type']] = $hint['value'];
                $revealed_count++;
            }
            if ($wrong_attempts_count >= 15 && $revealed_count < count($shuffled_hints)) {
                $hint = $shuffled_hints[$revealed_count];
                $comparison['revealed_hints'][$hint['type']] = $hint['value'];
            }
        }
    }

    wp_send_json_success($comparison);
}
add_action('wp_ajax_poke_hub_pokedle_submit_guess', 'poke_hub_pokedle_ajax_submit_guess');
add_action('wp_ajax_nopriv_poke_hub_pokedle_submit_guess', 'poke_hub_pokedle_ajax_submit_guess');

/**
 * Endpoint AJAX pour sauvegarder un score
 */
function poke_hub_pokedle_ajax_save_score() {
    check_ajax_referer('poke_hub_pokedle_nonce', 'nonce');

    $user_id = is_user_logged_in() ? get_current_user_id() : null;
    $game_date = isset($_POST['game_date']) ? sanitize_text_field($_POST['game_date']) : current_time('Y-m-d');
    $pokemon_id = isset($_POST['pokemon_id']) ? (int) $_POST['pokemon_id'] : 0;
    $attempts = isset($_POST['attempts']) ? (int) $_POST['attempts'] : 0;
    $is_success = isset($_POST['is_success']) ? (bool) $_POST['is_success'] : false;
    $completion_time = isset($_POST['completion_time']) ? (int) $_POST['completion_time'] : 0;
    $score_data = isset($_POST['score_data']) ? (array) $_POST['score_data'] : [];

    if ($pokemon_id <= 0 || $attempts <= 0) {
        wp_send_json_error(['message' => __('Invalid data.', 'poke-hub')]);
    }

    $score_id = poke_hub_pokedle_save_score(
        $user_id,
        $game_date,
        $pokemon_id,
        $attempts,
        $is_success,
        $completion_time,
        $score_data
    );

    if ($score_id) {
        wp_send_json_success(['score_id' => $score_id]);
    } else {
        wp_send_json_error(['message' => __('Erreur lors de la sauvegarde du score.', 'poke-hub')]);
    }
}
add_action('wp_ajax_poke_hub_pokedle_save_score', 'poke_hub_pokedle_ajax_save_score');
add_action('wp_ajax_nopriv_poke_hub_pokedle_save_score', 'poke_hub_pokedle_ajax_save_score');

/**
 * Endpoint AJAX pour récupérer le classement
 */
function poke_hub_pokedle_ajax_get_leaderboard() {
    check_ajax_referer('poke_hub_pokedle_nonce', 'nonce');

    $game_date = isset($_POST['game_date']) ? sanitize_text_field($_POST['game_date']) : current_time('Y-m-d');
    $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 10;

    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'today'; // 'today' ou 'global'

    if ($type === 'global') {
        $leaderboard = poke_hub_pokedle_get_global_leaderboard($limit);
    } else {
        $leaderboard = poke_hub_pokedle_get_leaderboard($game_date, $limit);
    }

    wp_send_json_success(['leaderboard' => $leaderboard]);
}
add_action('wp_ajax_poke_hub_pokedle_get_leaderboard', 'poke_hub_pokedle_ajax_get_leaderboard');
add_action('wp_ajax_nopriv_poke_hub_pokedle_get_leaderboard', 'poke_hub_pokedle_ajax_get_leaderboard');

/**
 * Endpoint AJAX pour récupérer le classement général (points)
 */
function poke_hub_games_ajax_get_general_leaderboard() {
    check_ajax_referer('poke_hub_pokedle_nonce', 'nonce');
    
    $period_type = isset($_POST['period_type']) ? sanitize_text_field($_POST['period_type']) : 'daily';
    $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 10;
    
    // Valider le type de période
    $valid_periods = ['daily', 'weekly', 'monthly', 'yearly', 'total'];
    if (!in_array($period_type, $valid_periods, true)) {
        $period_type = 'daily';
    }
    
    // Calculer la date de début de période
    $today = current_time('Y-m-d');
    $date_obj = new DateTime($today);
    $period_start = $today;
    
    if ($period_type === 'weekly') {
        $date_obj->modify('monday this week');
        $period_start = $date_obj->format('Y-m-d');
    } elseif ($period_type === 'monthly') {
        $period_start = $date_obj->format('Y-m-01');
    } elseif ($period_type === 'yearly') {
        $period_start = $date_obj->format('Y-01-01');
    } elseif ($period_type === 'total') {
        $period_start = '1970-01-01';
    }
    
    $leaderboard = poke_hub_games_get_leaderboard($period_type, $period_start, $limit);
    
    wp_send_json_success([
        'leaderboard' => $leaderboard,
        'period_type' => $period_type,
        'period_start' => $period_start
    ]);
}
add_action('wp_ajax_poke_hub_games_get_general_leaderboard', 'poke_hub_games_ajax_get_general_leaderboard');
add_action('wp_ajax_nopriv_poke_hub_games_get_general_leaderboard', 'poke_hub_games_ajax_get_general_leaderboard');

