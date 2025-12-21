<?php
// File: modules/pokemon/functions/pokemon-import-game-master-batch.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Batch importer orchestrator (simple async runner).
 *
 * Objectifs :
 * - Lancer l'import complet en arrière-plan (Action Scheduler si dispo, sinon WP-Cron)
 * - Exposer un endpoint Ajax "poke_hub_gm_status" pour l'UI settings tab
 * - Stocker status/state dans des options
 */

if ( ! defined( 'POKE_HUB_GM_BATCH_STATE_OPT' ) ) {
    define( 'POKE_HUB_GM_BATCH_STATE_OPT', 'poke_hub_gm_batch_state' );
}
if ( ! defined( 'POKE_HUB_GM_IMPORT_STATUS_OPT' ) ) {
    define( 'POKE_HUB_GM_IMPORT_STATUS_OPT', 'poke_hub_gm_import_status' );
}
if ( ! defined( 'POKE_HUB_GM_IMPORT_LOCK' ) ) {
    define( 'POKE_HUB_GM_IMPORT_LOCK', 'poke_hub_gm_import_lock' );
}

if ( ! function_exists( 'poke_hub_gm_status_set' ) ) {
    function poke_hub_gm_status_set( array $data ) : void {
        $current = get_option( POKE_HUB_GM_IMPORT_STATUS_OPT, [] );
        if ( ! is_array( $current ) ) {
            $current = [];
        }
        update_option( POKE_HUB_GM_IMPORT_STATUS_OPT, array_merge( $current, $data ), false );
    }
}

if ( ! function_exists( 'poke_hub_gm_state_get' ) ) {
    function poke_hub_gm_state_get() : array {
        $state = get_option( POKE_HUB_GM_BATCH_STATE_OPT, [] );
        return is_array( $state ) ? $state : [];
    }
}

if ( ! function_exists( 'poke_hub_gm_state_set' ) ) {
    function poke_hub_gm_state_set( array $state ) : void {
        update_option( POKE_HUB_GM_BATCH_STATE_OPT, $state, false );
    }
}

if ( ! function_exists( 'poke_hub_gm_state_reset' ) ) {
    function poke_hub_gm_state_reset() : void {
        delete_option( POKE_HUB_GM_BATCH_STATE_OPT );
    }
}

if ( ! function_exists( 'poke_hub_gm_acquire_lock' ) ) {
    function poke_hub_gm_acquire_lock( int $ttl = 30 * MINUTE_IN_SECONDS ) : bool {
        if ( get_transient( POKE_HUB_GM_IMPORT_LOCK ) ) {
            return false;
        }
        set_transient( POKE_HUB_GM_IMPORT_LOCK, 1, $ttl );
        return true;
    }
}

if ( ! function_exists( 'poke_hub_gm_release_lock' ) ) {
    function poke_hub_gm_release_lock() : void {
        delete_transient( POKE_HUB_GM_IMPORT_LOCK );
    }
}

/**
 * Progress helper (utilisable par d'autres fichiers pendant l'import).
 * - Met à jour state.progress
 * - Optionnellement met à jour status.message
 */
if ( ! function_exists( 'poke_hub_gm_progress' ) ) {
    function poke_hub_gm_progress( string $phase, int $pct, string $message = '' ) : void {
        $state = poke_hub_gm_state_get();
        if ( ! is_array( $state ) ) {
            $state = [];
        }

        $state['updated_at'] = current_time( 'mysql' );
        $state['progress']   = [
            'phase' => $phase,
            'pct'   => max( 0, min( 100, $pct ) ),
        ];

        poke_hub_gm_state_set( $state );

        if ( $message !== '' ) {
            poke_hub_gm_status_set( [
                'message' => $message,
            ] );
        }
    }
}

/**
 * Queue helper: Action Scheduler si dispo, sinon WP-Cron
 */
if ( ! function_exists( 'poke_hub_gm_queue_next' ) ) {
    function poke_hub_gm_queue_next( array $args = [] ) : void {
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'poke_hub_run_gm_import_batch', $args, 'poke-hub' );
            return;
        }

        // fallback cron: on encapsule les args dans un unique param
        wp_schedule_single_event( time() + 5, 'poke_hub_run_gm_import_batch', [ $args ] );
    }
}

/**
 * Initialise un import batch (appelé depuis settings-tab-gamemaster.php)
 *
 * Note: on reset le state précédent ici (important pour l'UI).
 */
if ( ! function_exists( 'poke_hub_gm_start_batch_import' ) ) {
    function poke_hub_gm_start_batch_import( string $path, bool $force = false, array $options = [] ) : void {

        // Reset éventuel ancien state pour éviter les "barres bloquées"
        poke_hub_gm_state_reset();

        $state = [
            'path'       => $path,
            'force'      => $force ? 1 : 0,
            'options'    => $options,
            'step'       => 'queued',
            'started_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
            'errors'     => [],
            'progress'   => [
                'phase' => 'queued',
                'pct'   => 0,
            ],
        ];

        poke_hub_gm_state_set( $state );

        poke_hub_gm_status_set( [
            'state'     => 'queued',
            'queued_at' => current_time( 'mysql' ),
            'path'      => $path,
            'message'   => 'Queued',
        ] );

        // Optionnel: set progress via helper
        poke_hub_gm_progress( 'queued', 0, 'Queued' );

        poke_hub_gm_queue_next( [ 'path' => $path ] );
    }
}

/**
 * Runner: exécute l'import complet puis termine.
 *
 * Note: compatible ActionScheduler (args array) et WP-Cron (args array en arg1)
 */
if ( ! has_action( 'poke_hub_run_gm_import_batch' ) ) {
    add_action( 'poke_hub_run_gm_import_batch', function( $arg1 = null ) {

        $args  = is_array( $arg1 ) ? $arg1 : [];
        $state = poke_hub_gm_state_get();

        // Résolution du path : state prioritaire, sinon args, puis persistance dans state.
        $path = '';
        if ( ! empty( $state['path'] ) ) {
            $path = (string) $state['path'];
        } elseif ( ! empty( $args['path'] ) ) {
            $path = (string) $args['path'];
            $state['path'] = $path;
            poke_hub_gm_state_set( $state );
        }

        if ( $path === '' ) {
            return;
        }

        if ( ! poke_hub_gm_acquire_lock() ) {
            // Déjà en cours
            return;
        }

        poke_hub_gm_status_set( [
            'state'      => 'running',
            'started_at' => $state['started_at'] ?? current_time( 'mysql' ),
            'path'       => $path,
            'message'    => 'Running',
        ] );

        // UI: on fait avancer un peu
        poke_hub_gm_progress( 'import', 10, 'Running' );

        try {
            if ( $path === '' || ! file_exists( $path ) ) {
                throw new Exception( 'Game Master file not found: ' . $path );
            }

            // Charger l'importer si besoin
            if ( ! function_exists( 'poke_hub_pokemon_import_game_master' ) ) {
                if ( defined( 'POKE_HUB_POKEMON_PATH' ) ) {
                    $import_file = POKE_HUB_POKEMON_PATH . '/functions/pokemon-import-game-master.php';
                    if ( $import_file && file_exists( $import_file ) ) {
                        require_once $import_file;
                    }
                }
            }

            if ( ! function_exists( 'poke_hub_pokemon_import_game_master' ) ) {
                throw new Exception( 'Importer function poke_hub_pokemon_import_game_master() not found.' );
            }

            // Progress best-effort (avant import)
            $state['step']       = 'import';
            $state['updated_at'] = current_time( 'mysql' );
            $state['progress']   = [ 'phase' => 'import', 'pct' => 10 ];
            poke_hub_gm_state_set( $state );

            $options = [];
            if ( ! empty( $state['options'] ) && is_array( $state['options'] ) ) {
                $options = $state['options'];
            }

            if ( function_exists( 'set_time_limit' ) ) {
                @set_time_limit( 300 );
            }

            // Import complet (le vrai "progrès fin" doit être fait DANS l'importer via poke_hub_gm_progress())
            $result = poke_hub_pokemon_import_game_master( $path, $options );

            if ( is_wp_error( $result ) ) {
                throw new Exception( $result->get_error_message() );
            }
            if ( ! is_array( $result ) ) {
                throw new Exception( 'Import returned unexpected result (not array).' );
            }

            // Finalize
            $state['step']       = 'finalize';
            $state['updated_at'] = current_time( 'mysql' );
            $state['progress']   = [ 'phase' => 'finalize', 'pct' => 95 ];
            poke_hub_gm_state_set( $state );

            poke_hub_gm_progress( 'finalize', 95, 'Finalizing' );

            update_option( 'poke_hub_gm_last_run', current_time( 'mysql' ), false );
            update_option( 'poke_hub_gm_last_summary', $result, false );

            poke_hub_gm_status_set( [
                'state'    => 'done',
                'ended_at' => current_time( 'mysql' ),
                'path'     => $path,
                'message'  => 'Done',
            ] );

            // Important: NE PAS reset immédiatement le state.
            // Sinon l'UI perd progress/phase et peut rester "bloquée".
            $state = poke_hub_gm_state_get();
            if ( ! is_array( $state ) ) {
                $state = [];
            }
            $state['step']       = 'done';
            $state['updated_at'] = current_time( 'mysql' );
            $state['progress']   = [ 'phase' => 'done', 'pct' => 100 ];
            poke_hub_gm_state_set( $state );

            poke_hub_gm_progress( 'done', 100, 'Done' );

        } catch ( Throwable $e ) {
            $state = poke_hub_gm_state_get();
            if ( ! is_array( $state ) ) {
                $state = [];
            }

            if ( empty( $state['errors'] ) || ! is_array( $state['errors'] ) ) {
                $state['errors'] = [];
            }

            $state['errors'][] = [
                'time' => current_time( 'mysql' ),
                'step' => $state['step'] ?? 'unknown',
                'msg'  => $e->getMessage(),
            ];

            $state['updated_at'] = current_time( 'mysql' );
            $state['progress']   = [ 'phase' => 'error', 'pct' => 100 ];
            poke_hub_gm_state_set( $state );

            poke_hub_gm_status_set( [
                'state'    => 'error',
                'ended_at' => current_time( 'mysql' ),
                'path'     => $path,
                'message'  => 'Error: ' . $e->getMessage(),
            ] );

            poke_hub_gm_progress( 'error', 100, 'Error: ' . $e->getMessage() );

        } finally {
            poke_hub_gm_release_lock();
        }

    }, 10, 1 );
}

/**
 * Ajax status endpoint (utilisé par fetch(...?action=poke_hub_gm_status))
 */
if ( ! has_action( 'wp_ajax_poke_hub_gm_status' ) ) {
    add_action( 'wp_ajax_poke_hub_gm_status', function() {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
        }

        // Sécurise nocache_headers() si jamais
        if ( ! function_exists( 'nocache_headers' ) ) {
            require_once ABSPATH . WPINC . '/functions.php';
        }

        nocache_headers();
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );

        $status = get_option( POKE_HUB_GM_IMPORT_STATUS_OPT, [] );
        $state  = get_option( POKE_HUB_GM_BATCH_STATE_OPT, [] );

        if ( ! is_array( $status ) ) {
            $status = [];
        }
        if ( ! is_array( $state ) ) {
            $state = [];
        }

        $progress = [];
        if ( ! empty( $state['progress'] ) && is_array( $state['progress'] ) ) {
            $progress = $state['progress'];
        }

        wp_send_json_success( [
            'status'   => $status,
            'progress' => $progress,
            'errors'   => $state['errors'] ?? [],
        ] );
    } );
}
