<?php
// modules/pokemon/admin/pokemon-admin.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retourne le label humain pour une section Pokémon
 *
 * @param string $section
 * @return string
 */
function poke_hub_pokemon_get_section_label($section) {
    switch ($section) {
        case 'pokemon':
            return __('Pokémon', 'poke-hub');
        case 'generations':
            return __('Generations', 'poke-hub');
        case 'regions':
            return __('Regions', 'poke-hub');
        case 'types':
            return __('Types', 'poke-hub');
        case 'moves':
            return __('Attacks', 'poke-hub');
        case 'forms':
            return __('Form variants', 'poke-hub');
        case 'form_mappings':
            return __('Form mappings', 'poke-hub');
        case 'weathers':
            return __('Weathers', 'poke-hub');
        case 'items':
            return __('Items', 'poke-hub');
        case 'backgrounds':
            return __('Backgrounds', 'poke-hub');
        case 'overview':
        default:
            return __('Pokémon data', 'poke-hub');
    }
}

/**
 * Inclure les sections admin Pokémon (list tables, écrans, formulaires)
 */
require_once POKE_HUB_POKEMON_PATH . '/admin/sections/pokemon.php';
require_once POKE_HUB_POKEMON_PATH . '/admin/sections/generations.php';
require_once POKE_HUB_POKEMON_PATH . '/admin/sections/regions.php';
require_once POKE_HUB_POKEMON_PATH . '/admin/sections/types.php';
require_once POKE_HUB_POKEMON_PATH . '/admin/sections/moves.php';
require_once POKE_HUB_POKEMON_PATH . '/admin/sections/forms.php';
require_once POKE_HUB_POKEMON_PATH . '/admin/sections/form-mappings.php';
require_once POKE_HUB_POKEMON_PATH . '/admin/sections/weathers.php';
require_once POKE_HUB_POKEMON_PATH . '/admin/sections/items.php';
require_once POKE_HUB_POKEMON_PATH . '/admin/sections/backgrounds.php'; // 🔹 NOUVEAU

/**
 * Screen options pour la page Pokémon (onglet "Pokémon" + "Attacks").
 */
function poke_hub_pokemon_screen_options() {
    $screen = get_current_screen();

    if (!is_object($screen) || $screen->id !== 'poke-hub_page_poke-hub-pokemon') {
        return;
    }

    $current_section = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : 'overview';

    if ($current_section === 'pokemon') {
        add_screen_option(
            'per_page',
            [
                'label'   => __('Pokémon per page', 'poke-hub'),
                'default' => 20,
                'option'  => 'poke_hub_pokemon_per_page',
            ]
        );
    }

    if ($current_section === 'moves') {
        add_screen_option(
            'per_page',
            [
                'label'   => __('Attacks per page', 'poke-hub'),
                'default' => 20,
                'option'  => 'poke_hub_pokemon_attacks_per_page',
            ]
        );
    }

    // Tu pourras ajouter un screen option "forms/items/weathers" plus tard si besoin.
}

/**
 * Sauvegarde des options "per page".
 */
function poke_hub_set_pokemon_screen_option($status, $option, $value) {
    if ($option === 'poke_hub_pokemon_per_page' || $option === 'poke_hub_pokemon_attacks_per_page') {
        return (int) $value;
    }
    return $status;
}
add_filter('set-screen-option', 'poke_hub_set_pokemon_screen_option', 10, 3);

/**
 * Déclare les colonnes disponibles pour la page Pokémon.
 */
function poke_hub_pokemon_manage_columns($columns) {

    $current_section = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : 'overview';

    if ($current_section === 'pokemon' && class_exists('Poke_Hub_Pokemon_List_Table')) {
        $table   = new Poke_Hub_Pokemon_List_Table();
        $columns = $table->get_columns();
    }

    if ($current_section === 'moves' && class_exists('Poke_Hub_Pokemon_attacks_List_Table')) {
        $table   = new Poke_Hub_Pokemon_attacks_List_Table();
        $columns = $table->get_columns();
    }

    if ($current_section === 'forms' && class_exists('Poke_Hub_Pokemon_Form_Variants_List_Table')) {
        $table   = new Poke_Hub_Pokemon_Form_Variants_List_Table();
        $columns = $table->get_columns();
    }

    if ($current_section === 'form_mappings' && class_exists('Poke_Hub_Pokemon_Form_Mappings_List_Table')) {
        $table   = new Poke_Hub_Pokemon_Form_Mappings_List_Table();
        $columns = $table->get_columns();
    }

    if ($current_section === 'weathers' && class_exists('Poke_Hub_Pokemon_Weathers_List_Table')) {
        $table   = new Poke_Hub_Pokemon_Weathers_List_Table();
        $columns = $table->get_columns();
    }

    if ($current_section === 'items' && class_exists('Poke_Hub_Pokemon_Items_List_Table')) {
        $table   = new Poke_Hub_Pokemon_Items_List_Table();
        $columns = $table->get_columns();
    }

    if ($current_section === 'backgrounds' && class_exists('Poke_Hub_Pokemon_Backgrounds_List_Table')) {
        $table   = new Poke_Hub_Pokemon_Backgrounds_List_Table();
        $columns = $table->get_columns();
    }

    return $columns;
}
add_filter(
    'manage_poke-hub_page_poke-hub-pokemon_columns',
    'poke_hub_pokemon_manage_columns'
);

function poke_hub_pokemon_admin_ui() {

    if (!current_user_can('manage_options')) {
        return;
    }

    $current_tab   = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : 'overview';
    $action        = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    $section_label = poke_hub_pokemon_get_section_label($current_tab);

    /**
     * MODE ADD / EDIT
     */
    if (in_array($action, ['add', 'edit'], true)) {
        switch ($current_tab) {
            case 'pokemon':
                global $wpdb;
                $edit_row = null;

                if ($action === 'edit' && !empty($_GET['id'])) {
                    $id    = (int) $_GET['id'];
                    $table = pokehub_get_table('pokemon');
                    $edit_row = $wpdb->get_row(
                        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
                    );
                }

                if (function_exists('poke_hub_pokemon_pokemon_edit_form')) {
                    poke_hub_pokemon_pokemon_edit_form($edit_row);
                } else {
                    echo '<div class="wrap"><h1>Missing function: poke_hub_pokemon_pokemon_edit_form()</h1></div>';
                }
                break;

            case 'generations':
                global $wpdb;
                $edit_row = null;

                if ($action === 'edit' && !empty($_GET['id'])) {
                    $id = (int) $_GET['id'];

                    $table_gens = pokehub_get_table('pokemon_generations');
                    if ($table_gens) {
                        $edit_row = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM {$table_gens} WHERE id = %d", $id)
                        );
                    }
                }

                poke_hub_pokemon_generations_edit_form($edit_row);
                break;

            case 'regions':
                global $wpdb;
                $edit_row = null;

                if ($action === 'edit' && !empty($_GET['id'])) {
                    $id         = (int) $_GET['id'];
                    $table_regs = pokehub_get_table('pokemon_regions');
                    $edit_row   = $wpdb->get_row(
                        $wpdb->prepare("SELECT * FROM {$table_regs} WHERE id = %d", $id)
                    );
                }

                if (function_exists('poke_hub_pokemon_regions_edit_form')) {
                    poke_hub_pokemon_regions_edit_form($edit_row);
                } else {
                    echo '<div class="wrap"><h1>Missing function: poke_hub_pokemon_regions_edit_form()</h1></div>';
                }
                break;

            case 'types':
                global $wpdb;
                $edit_row = null;
                $all_weathers = [];
                $all_types = [];
                $current_weather_ids = [];
                $current_weakness_ids = [];
                $current_resistance_ids = [];
                $current_immune_ids = [];
                $current_offensive_super_effective_ids = [];
                $current_offensive_not_very_effective_ids = [];
                $current_offensive_no_effect_ids = [];

                // Récupérer la table des types dès le début
                $table_types = pokehub_get_table('pokemon_types');

                if ($action === 'edit' && !empty($_GET['id'])) {
                    $id          = (int) $_GET['id'];
                    $edit_row    = $wpdb->get_row(
                        $wpdb->prepare("SELECT * FROM {$table_types} WHERE id = %d", $id)
                    );

                    if ($edit_row) {
                        // Récupérer les météos liées
                        $table_weather_links = pokehub_get_table('pokemon_type_weather_links');
                        if ($table_weather_links) {
                            $weather_rows = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT weather_id FROM {$table_weather_links} WHERE type_id = %d",
                                    $id
                                )
                            );
                            $current_weather_ids = array_map(function($row) {
                                return (int) $row->weather_id;
                            }, $weather_rows);
                        }

                        // Récupérer les faiblesses (défense ×2) - priorité à pokemon_go, sinon core_series
                        $table_weakness_links = pokehub_get_table('pokemon_type_weakness_links');
                        if ($table_weakness_links) {
                            $weakness_rows = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT weakness_type_id FROM {$table_weakness_links} 
                                     WHERE type_id = %d 
                                     ORDER BY CASE game_key WHEN 'pokemon_go' THEN 1 WHEN 'core_series' THEN 2 ELSE 3 END
                                     LIMIT 50",
                                    $id
                                )
                            );
                            $current_weakness_ids = array_map(function($row) {
                                return (int) $row->weakness_type_id;
                            }, $weakness_rows);
                        }

                        // Récupérer les résistances (défense ×½) - priorité à pokemon_go
                        $table_resistance_links = pokehub_get_table('pokemon_type_resistance_links');
                        if ($table_resistance_links) {
                            $resistance_rows = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT resistance_type_id FROM {$table_resistance_links} 
                                     WHERE type_id = %d 
                                     ORDER BY CASE game_key WHEN 'pokemon_go' THEN 1 WHEN 'core_series' THEN 2 ELSE 3 END
                                     LIMIT 50",
                                    $id
                                )
                            );
                            $current_resistance_ids = array_map(function($row) {
                                return (int) $row->resistance_type_id;
                            }, $resistance_rows);
                        }

                        // Récupérer les immunités (défense ×0) - priorité à pokemon_go
                        $table_immune_links = pokehub_get_table('pokemon_type_immune_links');
                        if ($table_immune_links) {
                            $immune_rows = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT immune_type_id FROM {$table_immune_links} 
                                     WHERE type_id = %d 
                                     ORDER BY CASE game_key WHEN 'pokemon_go' THEN 1 WHEN 'core_series' THEN 2 ELSE 3 END
                                     LIMIT 50",
                                    $id
                                )
                            );
                            $current_immune_ids = array_map(function($row) {
                                return (int) $row->immune_type_id;
                            }, $immune_rows);
                        }

                        // Récupérer les efficacités offensives - Super efficace (×2) - priorité à pokemon_go
                        $table_offensive_super_effective_links = pokehub_get_table('pokemon_type_offensive_super_effective_links');
                        if ($table_offensive_super_effective_links) {
                            $offensive_super_effective_rows = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT target_type_id FROM {$table_offensive_super_effective_links} 
                                     WHERE type_id = %d 
                                     ORDER BY CASE game_key WHEN 'pokemon_go' THEN 1 WHEN 'core_series' THEN 2 ELSE 3 END
                                     LIMIT 50",
                                    $id
                                )
                            );
                            $current_offensive_super_effective_ids = array_map(function($row) {
                                return (int) $row->target_type_id;
                            }, $offensive_super_effective_rows);
                        }

                        // Récupérer les efficacités offensives - Peu efficace (×½) - priorité à pokemon_go
                        $table_offensive_not_very_effective_links = pokehub_get_table('pokemon_type_offensive_not_very_effective_links');
                        if ($table_offensive_not_very_effective_links) {
                            $offensive_not_very_effective_rows = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT target_type_id FROM {$table_offensive_not_very_effective_links} 
                                     WHERE type_id = %d 
                                     ORDER BY CASE game_key WHEN 'pokemon_go' THEN 1 WHEN 'core_series' THEN 2 ELSE 3 END
                                     LIMIT 50",
                                    $id
                                )
                            );
                            $current_offensive_not_very_effective_ids = array_map(function($row) {
                                return (int) $row->target_type_id;
                            }, $offensive_not_very_effective_rows);
                        }

                        // Récupérer les efficacités offensives - Sans effet (×0) - priorité à pokemon_go
                        $table_offensive_no_effect_links = pokehub_get_table('pokemon_type_offensive_no_effect_links');
                        if ($table_offensive_no_effect_links) {
                            $offensive_no_effect_rows = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT target_type_id FROM {$table_offensive_no_effect_links} 
                                     WHERE type_id = %d 
                                     ORDER BY CASE game_key WHEN 'pokemon_go' THEN 1 WHEN 'core_series' THEN 2 ELSE 3 END
                                     LIMIT 50",
                                    $id
                                )
                            );
                            $current_offensive_no_effect_ids = array_map(function($row) {
                                return (int) $row->target_type_id;
                            }, $offensive_no_effect_rows);
                        }
                    }
                }

                // Récupérer toutes les météos disponibles
                $table_weathers = pokehub_get_table('pokemon_weathers');
                if ($table_weathers) {
                    $all_weathers = $wpdb->get_results("SELECT * FROM {$table_weathers} ORDER BY name_fr ASC, name_en ASC");
                }

                // Récupérer tous les types disponibles (inclure TOUS les types, y compris le type en cours d'édition)
                if ($table_types) {
                    $all_types = $wpdb->get_results("SELECT * FROM {$table_types} ORDER BY name_fr ASC, name_en ASC");
                }

                if (function_exists('poke_hub_pokemon_types_edit_form')) {
                    poke_hub_pokemon_types_edit_form(
                        $edit_row,
                        $all_weathers,
                        $current_weather_ids,
                        $all_types,
                        $current_weakness_ids,
                        $current_resistance_ids,
                        $current_immune_ids,
                        $current_offensive_super_effective_ids,
                        $current_offensive_not_very_effective_ids,
                        $current_offensive_no_effect_ids
                    );
                } else {
                    echo '<div class="wrap"><h1>Missing function: poke_hub_pokemon_types_edit_form()</h1></div>';
                }
                break;

            case 'moves':
                global $wpdb;
                $edit_row  = null;
                $pve_stats = null;
                $pvp_stats = null;

                if ($action === 'edit' && !empty($_GET['id'])) {
                    $id = (int) $_GET['id'];

                    $table_attacks = pokehub_get_table('attacks');
                    $table_stats   = pokehub_get_table('attack_stats');

                    $edit_row = $wpdb->get_row(
                        $wpdb->prepare("SELECT * FROM {$table_attacks} WHERE id = %d", $id)
                    );

                    if ($edit_row) {
                        $stats_rows = $wpdb->get_results(
                            $wpdb->prepare(
                                "SELECT * FROM {$table_stats} WHERE attack_id = %d AND game_key = %s",
                                $id,
                                'pokemon_go'
                            )
                        );

                        foreach ($stats_rows as $s) {
                            if ($s->context === 'pve') {
                                $pve_stats = $s;
                            } elseif ($s->context === 'pvp') {
                                $pvp_stats = $s;
                            }
                        }
                    }
                }

                if (function_exists('poke_hub_pokemon_attacks_edit_form')) {
                    poke_hub_pokemon_attacks_edit_form($edit_row);
                } else {
                    echo '<div class="wrap"><h1>Missing function: poke_hub_pokemon_attacks_edit_form()</h1></div>';
                }

                return;

            case 'forms':
                global $wpdb;
                $edit_row = null;

                if ($action === 'edit' && !empty($_GET['id'])) {
                    $id            = (int) $_GET['id'];
                    $variants_table = pokehub_get_table('pokemon_form_variants');
                    if ($variants_table) {
                        $edit_row = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM {$variants_table} WHERE id = %d", $id)
                        );
                    }
                }

                if (function_exists('poke_hub_pokemon_forms_edit_form')) {
                    poke_hub_pokemon_forms_edit_form($edit_row);
                } else {
                    echo '<div class="wrap"><h1>Missing function: poke_hub_pokemon_forms_edit_form()</h1></div>';
                }
                break;

            case 'form_mappings':
                global $wpdb;
                $edit_row = null;

                if ($action === 'edit' && !empty($_GET['id'])) {
                    $id    = (int) $_GET['id'];
                    $table = pokehub_get_table('pokemon_form_mappings');
                    if ($table) {
                        $edit_row = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
                        );
                    }
                }

                if (function_exists('poke_hub_pokemon_form_mappings_edit_form')) {
                    poke_hub_pokemon_form_mappings_edit_form($edit_row);
                } else {
                    echo '<div class="wrap"><h1>Missing function: poke_hub_pokemon_form_mappings_edit_form()</h1></div>';
                }
                break;

            case 'weathers':
                global $wpdb;
                $edit_row = null;

                if ($action === 'edit' && !empty($_GET['id'])) {
                    $id    = (int) $_GET['id'];
                    $table = pokehub_get_table('pokemon_weathers');
                    $edit_row = $wpdb->get_row(
                        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
                    );
                }

                if (function_exists('poke_hub_pokemon_weathers_edit_form')) {
                    poke_hub_pokemon_weathers_edit_form($edit_row);
                } else {
                    echo '<div class="wrap"><h1>Missing function: poke_hub_pokemon_weathers_edit_form()</h1></div>';
                }
                break;

            case 'items': // 🔹 NOUVEAU : ADD/EDIT Item
                global $wpdb;
                $edit_row = null;

                if ($action === 'edit' && !empty($_GET['id'])) {
                    $id    = (int) $_GET['id'];
                    $table = pokehub_get_table('pokemon_items');
                    if ($table) {
                        $edit_row = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
                        );
                    }
                }

                if (function_exists('poke_hub_pokemon_items_edit_form')) {
                    poke_hub_pokemon_items_edit_form($edit_row);
                } else {
                    echo '<div class="wrap"><h1>Missing function: poke_hub_pokemon_items_edit_form()</h1></div>';
                }
                break;

            case 'backgrounds':
                global $wpdb;
                $edit_row = null;

                if ($action === 'edit' && !empty($_GET['id'])) {
                    $id    = (int) $_GET['id'];
                    $table = pokehub_get_table('pokemon_backgrounds');
                    if ($table) {
                        $edit_row = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
                        );
                    }
                }

                if (function_exists('poke_hub_pokemon_backgrounds_edit_form')) {
                    poke_hub_pokemon_backgrounds_edit_form($edit_row);
                } else {
                    echo '<div class="wrap"><h1>Missing function: poke_hub_pokemon_backgrounds_edit_form()</h1></div>';
                }
                break;
        }

        return;
    }

    /**
     * MODE LISTE
     */

    $add_button = null;

    switch ($current_tab) {
        case 'pokemon':
            $add_button = [
                'label' => __('Add Pokémon', 'poke-hub'),
                'url'   => add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'pokemon',
                        'action'     => 'add',
                    ],
                    admin_url('admin.php')
                ),
            ];
            break;

        case 'generations':
            $add_button = [
                'label' => __('Add generation', 'poke-hub'),
                'url'   => add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'generations',
                        'action'     => 'add',
                    ],
                    admin_url('admin.php')
                ),
            ];
            break;

        case 'regions':
            $add_button = [
                'label' => __('Add region', 'poke-hub'),
                'url'   => add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'regions',
                        'action'     => 'add',
                    ],
                    admin_url('admin.php')
                ),
            ];
            break;

        case 'types':
            $add_button = [
                'label' => __('Add type', 'poke-hub'),
                'url'   => add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'types',
                        'action'     => 'add',
                    ],
                    admin_url('admin.php')
                ),
            ];
            break;

        case 'moves':
            $add_button = [
                'label' => __('Add move', 'poke-hub'),
                'url'   => add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'moves',
                        'action'     => 'add',
                    ],
                    admin_url('admin.php')
                ),
            ];
            break;

        case 'forms':
            $add_button = [
                'label' => __('Add form / variant', 'poke-hub'),
                'url'   => add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'forms',
                        'action'     => 'add',
                    ],
                    admin_url('admin.php')
                ),
            ];
            break;

        case 'form_mappings':
            $add_button = [
                'label' => __('Add mapping', 'poke-hub'),
                'url'   => add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'form_mappings',
                        'action'     => 'add',
                    ],
                    admin_url('admin.php')
                ),
            ];
            break;

        case 'weathers':
            $add_button = [
                'label' => __('Add weather', 'poke-hub'),
                'url'   => add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'weathers',
                        'action'     => 'add',
                    ],
                    admin_url('admin.php')
                ),
            ];
            break;

        case 'items':
            $add_button = [
                'label' => __('Add item', 'poke-hub'),
                'url'   => add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'items',
                        'action'     => 'add',
                    ],
                    admin_url('admin.php')
                ),
            ];
            break;

        case 'backgrounds':
            $add_button = [
                'label' => __('Add background', 'poke-hub'),
                'url'   => add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => 'backgrounds',
                        'action'     => 'add',
                    ],
                    admin_url('admin.php')
                ),
            ];
            break;

        default:
            $add_button = null;
            break;
    }

    // Tabs
    $tabs = [
        'overview'      => __('Overview', 'poke-hub'),
        'pokemon'       => __('Pokémon', 'poke-hub'),
        'generations'   => __('Generations', 'poke-hub'),
        'regions'       => __('Regions', 'poke-hub'),
        'types'         => __('Types', 'poke-hub'),
        'moves'         => __('Attacks', 'poke-hub'),
        'forms'         => __('Form variants', 'poke-hub'),
        'form_mappings' => __('Form mappings', 'poke-hub'),
        'weathers'      => __('Weathers', 'poke-hub'),
        'items'         => __('Items', 'poke-hub'),
        'backgrounds'   => __('Backgrounds', 'poke-hub'),
    ];
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php
            echo esc_html($section_label . ' – Poké HUB');
            ?>
        </h1>

        <?php if ($add_button) : ?>
            <a href="<?php echo esc_url($add_button['url']); ?>" class="page-title-action">
                <?php echo esc_html($add_button['label']); ?>
            </a>
        <?php endif; ?>

        <hr class="wp-header-end" />

        <h2 class="nav-tab-wrapper">
            <?php
            foreach ($tabs as $tab_key => $tab_label) {
                $tab_url = add_query_arg(
                    [
                        'page'       => 'poke-hub-pokemon',
                        'ph_section' => $tab_key,
                    ],
                    admin_url('admin.php')
                );

                $class = 'nav-tab';
                if ($current_tab === $tab_key) {
                    $class .= ' nav-tab-active';
                }

                printf(
                    '<a href="%s" class="%s">%s</a>',
                    esc_url($tab_url),
                    esc_attr($class),
                    esc_html($tab_label)
                );
            }
            ?>
        </h2>

        <div class="poke-hub-pokemon-tab-content">
            <?php
            switch ($current_tab) {
                case 'pokemon':
                    if (function_exists('poke_hub_pokemon_admin_pokemon_screen')) {
                        poke_hub_pokemon_admin_pokemon_screen();
                    } else {
                        echo '<p>Pokemon screen not implemented yet.</p>';
                    }
                    break;

                case 'generations':
                    if (function_exists('poke_hub_pokemon_admin_generations_screen')) {
                        poke_hub_pokemon_admin_generations_screen();
                    } else {
                        echo '<p>Generations screen not implemented yet.</p>';
                    }
                    break;

                case 'regions':
                    if (function_exists('poke_hub_pokemon_admin_regions_screen')) {
                        poke_hub_pokemon_admin_regions_screen();
                    } else {
                        echo '<p>Regions screen not implemented yet.</p>';
                    }
                    break;

                case 'types':
                    if (function_exists('poke_hub_pokemon_admin_types_screen')) {
                        poke_hub_pokemon_admin_types_screen();
                    } else {
                        echo '<p>Types screen not implemented yet.</p>';
                    }
                    break;

                case 'moves':
                    if (function_exists('poke_hub_pokemon_admin_attacks_screen')) {
                        poke_hub_pokemon_admin_attacks_screen();
                    } else {
                        echo '<p>Attacks screen not implemented yet.</p>';
                    }
                    break;

                case 'forms':
                    if (function_exists('poke_hub_pokemon_admin_forms_screen')) {
                        poke_hub_pokemon_admin_forms_screen();
                    } else {
                        echo '<p>Form variants screen not implemented yet.</p>';
                    }
                    break;

                case 'form_mappings':
                    if (function_exists('poke_hub_pokemon_admin_form_mappings_screen')) {
                        poke_hub_pokemon_admin_form_mappings_screen();
                    } else {
                        echo '<p>Form mappings screen not implemented yet.</p>';
                    }
                    break;

                case 'weathers':
                    if (function_exists('poke_hub_pokemon_admin_weathers_screen')) {
                        poke_hub_pokemon_admin_weathers_screen();
                    } else {
                        echo '<p>Weathers screen not implemented yet.</p>';
                    }
                    break;

                case 'items':
                    if (function_exists('poke_hub_pokemon_admin_items_screen')) {
                        poke_hub_pokemon_admin_items_screen();
                    } else {
                        echo '<p>Items screen not implemented yet.</p>';
                    }
                    break;

                case 'backgrounds':
                    if (function_exists('poke_hub_pokemon_admin_backgrounds_screen')) {
                        poke_hub_pokemon_admin_backgrounds_screen();
                    } else {
                        echo '<p>Backgrounds screen not implemented yet.</p>';
                    }
                    break;

                default:
                    if (function_exists('poke_hub_pokemon_admin_overview_screen')) {
                        poke_hub_pokemon_admin_overview_screen();
                    } else {
                        echo '<p>' . esc_html__('Welcome to Pokémon data admin.', 'poke-hub') . '</p>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Change le titre de l’onglet navigateur pour la page Pokémon
 */
function poke_hub_pokemon_change_admin_title($admin_title, $title) {
    if (!is_admin()) {
        return $admin_title;
    }

    if (empty($_GET['page']) || $_GET['page'] !== 'poke-hub-pokemon') {
        return $admin_title;
    }

    $section       = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : 'overview';
    $section_label = poke_hub_pokemon_get_section_label($section);

    $blogname = get_bloginfo('name');

    return $section_label . ' ‹ ' . $blogname . ' — WordPress';
}
add_filter('admin_title', 'poke_hub_pokemon_change_admin_title', 10, 2);

/**
 * Enqueue scripts/styles pour la page Pokémon (Select2 sur les attaques).
 */
function poke_hub_pokemon_admin_enqueue_assets($hook) {
    if ($hook !== 'poke-hub_page_poke-hub-pokemon') {
        return;
    }

    $section = isset($_GET['ph_section']) ? sanitize_key($_GET['ph_section']) : 'overview';

    // Le CSS admin est maintenant chargé pour toutes les sections
    wp_enqueue_style(
        'poke-hub-pokemon-admin',
        POKE_HUB_URL . 'assets/css/poke-hub-pokemon-admin.css',
        [],
        POKE_HUB_VERSION
    );

    // On limite Select2 aux onglets qui en ont besoin (pokemon, backgrounds)
    // Mais on charge toujours le script d'évolutions pour pokemon
    if ($section === 'pokemon') {
        // Script pour gérer l'affichage conditionnel des champs d'évolution
        wp_enqueue_script(
            'pokehub-pokemon-evolutions-admin',
            POKE_HUB_URL . 'assets/js/pokehub-pokemon-evolutions-admin.js',
            ['jquery'],
            POKE_HUB_VERSION,
            true
        );
    }
    
    if ($section !== 'pokemon' && $section !== 'backgrounds') {
        return;
    }

    wp_enqueue_style(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        [],
        '4.1.0'
    );

    wp_enqueue_script(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );

    // Script pour l'initialisation Select2 avec recherche multilingue
    wp_enqueue_script(
        'pokehub-pokemon-admin-select2',
        POKE_HUB_URL . 'assets/js/pokehub-admin-select2.js',
        ['jquery', 'select2'],
        POKE_HUB_VERSION,
        true
    );
    
    // Localiser les chaînes de traduction pour Select2
    wp_localize_script(
        'pokehub-pokemon-admin-select2',
        'pokehubSelect2Strings',
        [
            'selectMove'         => __('Select move', 'poke-hub'),
            'selectWeather'      => __('Select weather (optional)', 'poke-hub'),
            'selectItem'         => __('Select item (optional)', 'poke-hub'),
            'selectLure'         => __('Select lure (optional)', 'poke-hub'),
            'selectTargetPokemon' => __('Select target Pokémon', 'poke-hub'),
        ]
    );
}
add_action('admin_enqueue_scripts', 'poke_hub_pokemon_admin_enqueue_assets');
