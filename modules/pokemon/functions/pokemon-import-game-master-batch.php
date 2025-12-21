<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Batch importer orchestrator (state machine).
 *
 * Stocke l'état dans une option:
 * - poke_hub_gm_batch_state
 * Et le statut global dans:
 * - poke_hub_gm_import_status
 */

const POKE_HUB_GM_BATCH_STATE_OPT  = 'poke_hub_gm_batch_state';
const POKE_HUB_GM_IMPORT_STATUS_OPT = 'poke_hub_gm_import_status';
const POKE_HUB_GM_IMPORT_LOCK      = 'poke_hub_gm_import_lock';

function poke_hub_gm_status_set( array $data ) {
    $current = get_option( POKE_HUB_GM_IMPORT_STATUS_OPT, [] );
    if ( ! is_array( $current ) ) $current = [];
    update_option( POKE_HUB_GM_IMPORT_STATUS_OPT, array_merge( $current, $data ) );
}

function poke_hub_gm_state_get() : array {
    $state = get_option( POKE_HUB_GM_BATCH_STATE_OPT, [] );
    return is_array( $state ) ? $state : [];
}

function poke_hub_gm_state_set( array $state ) {
    update_option( POKE_HUB_GM_BATCH_STATE_OPT, $state );
}

function poke_hub_gm_state_reset() {
    delete_option( POKE_HUB_GM_BATCH_STATE_OPT );
}

function poke_hub_gm_acquire_lock( int $ttl = 30 * MINUTE_IN_SECONDS ) : bool {
    if ( get_transient( POKE_HUB_GM_IMPORT_LOCK ) ) return false;
    set_transient( POKE_HUB_GM_IMPORT_LOCK, 1, $ttl );
    return true;
}
function poke_hub_gm_release_lock() {
    delete_transient( POKE_HUB_GM_IMPORT_LOCK );
}

/**
 * Queue helper: Action Scheduler si dispo, sinon WP-Cron
 */
function poke_hub_gm_queue_next( array $args = [] ) {
    if ( function_exists( 'as_enqueue_async_action' ) ) {
        as_enqueue_async_action( 'poke_hub_run_gm_import_batch', $args, 'poke-hub' );
        return;
    }
    // fallback
    wp_schedule_single_event( time() + 5, 'poke_hub_run_gm_import_batch', [ $args ] );
}

/**
 * Initialise un import batch (appelé depuis ton settings tab)
 */
function poke_hub_gm_start_batch_import( string $path, bool $force = false ) {

    $state = [
        'path'   => $path,
        'force'  => $force ? 1 : 0,
        'step'   => 'bootstrap',
        'cursor' => [],
        'counts' => [
            'pokemon_processed' => 0,
            'attacks_processed' => 0,
            'links_processed'   => 0,
            'pve_processed'     => 0,
            'pvp_processed'     => 0,
        ],
        'started_at' => current_time( 'mysql' ),
        'updated_at' => current_time( 'mysql' ),
        'errors'     => [],
        // progress “best effort”
        'progress'   => [
            'phase' => 'bootstrap',
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

    poke_hub_gm_queue_next( [ 'path' => $path ] );
}

/**
 * Hook principal: exécute un “tick” batch puis re-queue si besoin.
 */
add_action( 'poke_hub_run_gm_import_batch', function( $arg1 = null ) {

    // compat AS (args array) vs WP-Cron (args array in first param)
    $args = is_array( $arg1 ) ? $arg1 : [];
    $state = poke_hub_gm_state_get();

    // Si pas d'état => rien à faire
    if ( empty( $state['path'] ) ) return;

    if ( ! poke_hub_gm_acquire_lock() ) {
        // déjà en cours
        return;
    }

    poke_hub_gm_status_set( [
        'state'      => 'running',
        'started_at' => $state['started_at'] ?? current_time('mysql'),
        'path'       => $state['path'],
        'message'    => 'Running',
    ] );

    try {
        $keep_going = poke_hub_gm_batch_tick();
        if ( $keep_going ) {
            poke_hub_gm_queue_next( [ 'path' => $state['path'] ] );
        }
    } catch ( Throwable $e ) {
        $state['step'] = $state['step'] ?? 'unknown';
        $state['errors'][] = [
            'time' => current_time('mysql'),
            'step' => $state['step'],
            'msg'  => $e->getMessage(),
        ];
        $state['updated_at'] = current_time('mysql');
        poke_hub_gm_state_set( $state );

        poke_hub_gm_status_set( [
            'state'   => 'error',
            'ended_at'=> current_time('mysql'),
            'path'    => $state['path'],
            'message' => 'Error: ' . $e->getMessage(),
        ] );
    } finally {
        poke_hub_gm_release_lock();
    }

}, 10, 1 );

/**
 * 1 tick = traite un petit morceau, selon l'étape.
 * Retourne true si on doit continuer (re-queue), false si terminé.
 *
 * IMPORTANT:
 * - Ici je te donne une architecture + exemples de “chunk loops”.
 * - Il faut brancher tes fonctions existantes (insert/update/links/stats)
 *   dans les handlers ci-dessous.
 */
function poke_hub_gm_batch_tick() : bool {

    $state = poke_hub_gm_state_get();
    $path  = (string) ($state['path'] ?? '');
    if ( $path === '' || ! file_exists( $path ) ) {
        throw new Exception( 'Game Master file not found: ' . $path );
    }

    $step = $state['step'] ?? 'bootstrap';

    // Budget : on garde des ticks courts (évite CPU long et mémoire)
    $batch_size = 250; // ajuste (100–1000). 250 safe.
    $max_seconds = 10; // chaque tick vise < 10s
    $t0 = microtime(true);

    // --- Étape 0: bootstrap ---
    if ( $step === 'bootstrap' ) {

        // Charger le JSON une seule fois par tick (OK si énorme? => sinon, voir note plus bas)
        // Pour un “vrai streaming”, on changera, mais là on fait déjà batch par sections.
        $gm = json_decode( file_get_contents( $path ), true );
        if ( ! is_array( $gm ) ) {
            throw new Exception( 'Invalid JSON (decode failed)' );
        }

        // Exemple : indexer ce dont on a besoin pour les étapes suivantes
        // IMPORTANT: si GM énorme, évite de stocker $gm dans option.
        // On stocke juste des "pointers" / compte approximatif.
        $state['cursor'] = [
            'pokemon_i' => 0,
            'attacks_i' => 0,
            // …
        ];

        // Optionnel: calculer des totals (best effort) pour progress
        $state['progress'] = [ 'phase' => 'pokemon', 'pct' => 2 ];
        $state['step'] = 'pokemon';
        $state['updated_at'] = current_time('mysql');
        poke_hub_gm_state_set( $state );

        return true;
    }

    // Dans les étapes suivantes, on relit le JSON (oui) mais on ne traite qu’un morceau.
    // Ça évite de garder une énorme structure en mémoire entre ticks.
    $gm = json_decode( file_get_contents( $path ), true );
    if ( ! is_array( $gm ) ) {
        throw new Exception( 'Invalid JSON (decode failed)' );
    }

    // --- Étape 1: Pokémon ---
    if ( $step === 'pokemon' ) {

        $cursor = (int) ( $state['cursor']['pokemon_i'] ?? 0 );

        // TODO: adapte l’accès à tes données réelles GM
        // Ici c’est un exemple : $pokemon_list = ...
        $pokemon_list = poke_hub_gm_extract_pokemon_rows( $gm ); // à créer

        $total = count( $pokemon_list );
        $processed = 0;

        while ( $cursor < $total && $processed < $batch_size && (microtime(true)-$t0) < $max_seconds ) {

            $row = $pokemon_list[$cursor];

            // TODO: appelle ta logique existante (insert/update) ici
            // poke_hub_import_upsert_pokemon_row($row);

            $cursor++;
            $processed++;
            $state['counts']['pokemon_processed']++;
        }

        $state['cursor']['pokemon_i'] = $cursor;

        $pct = $total > 0 ? (int) min( 30, 2 + floor( 28 * ($cursor / $total) ) ) : 30;
        $state['progress'] = [ 'phase' => 'pokemon', 'pct' => $pct ];
        $state['updated_at'] = current_time('mysql');
        poke_hub_gm_state_set( $state );

        if ( $cursor < $total ) {
            return true; // continuer pokemon
        }

        $state['step'] = 'attacks';
        $state['progress'] = [ 'phase' => 'attacks', 'pct' => 30 ];
        $state['updated_at'] = current_time('mysql');
        poke_hub_gm_state_set( $state );
        return true;
    }

    // --- Étape 2: Attacks ---
    if ( $step === 'attacks' ) {

        $cursor = (int) ( $state['cursor']['attacks_i'] ?? 0 );
        $attacks = poke_hub_gm_extract_attack_rows( $gm ); // à créer

        $total = count( $attacks );
        $processed = 0;

        while ( $cursor < $total && $processed < $batch_size && (microtime(true)-$t0) < $max_seconds ) {
            $row = $attacks[$cursor];

            // TODO upsert attaque
            // poke_hub_import_upsert_attack_row($row);

            $cursor++;
            $processed++;
            $state['counts']['attacks_processed']++;
        }

        $state['cursor']['attacks_i'] = $cursor;
        $pct = $total > 0 ? (int) min( 55, 30 + floor( 25 * ($cursor / $total) ) ) : 55;
        $state['progress'] = [ 'phase' => 'attacks', 'pct' => $pct ];
        $state['updated_at'] = current_time('mysql');
        poke_hub_gm_state_set( $state );

        if ( $cursor < $total ) return true;

        $state['step'] = 'links';
        $state['progress'] = [ 'phase' => 'links', 'pct' => 55 ];
        $state['updated_at'] = current_time('mysql');
        poke_hub_gm_state_set( $state );
        return true;
    }

    // --- Étape 3: Links (pokemon<->types, pokemon<->attacks, etc.) ---
    if ( $step === 'links' ) {

        // Ici tu peux batcher par sous-étapes: links_substep = pokemon_types / attack_types / pokemon_attacks…
        $sub = $state['cursor']['links_sub'] ?? 'pokemon_types';
        $i   = (int) ( $state['cursor']['links_i'] ?? 0 );

        $work = poke_hub_gm_extract_links_work( $gm, (string)$sub ); // à créer
        $total = count( $work );
        $processed = 0;

        while ( $i < $total && $processed < $batch_size && (microtime(true)-$t0) < $max_seconds ) {
            $job = $work[$i];

            // TODO appliquer link
            // poke_hub_import_apply_link_job($sub, $job);

            $i++;
            $processed++;
            $state['counts']['links_processed']++;
        }

        $state['cursor']['links_i'] = $i;

        $pct = $total > 0 ? (int) min( 75, 55 + floor( 20 * ($i / $total) ) ) : 75;
        $state['progress'] = [ 'phase' => 'links:' . $sub, 'pct' => $pct ];
        $state['updated_at'] = current_time('mysql');
        poke_hub_gm_state_set( $state );

        if ( $i < $total ) return true;

        // next substep
        $next = poke_hub_gm_next_links_substep( (string)$sub );
        if ( $next ) {
            $state['cursor']['links_sub'] = $next;
            $state['cursor']['links_i']   = 0;
            $state['updated_at'] = current_time('mysql');
            poke_hub_gm_state_set( $state );
            return true;
        }

        $state['step'] = 'pve';
        $state['progress'] = [ 'phase' => 'pve', 'pct' => 75 ];
        $state['updated_at'] = current_time('mysql');
        poke_hub_gm_state_set( $state );
        return true;
    }

    // --- Étape 4: PvE ---
    if ( $step === 'pve' ) {
        // Même mécanique: cursor + batch
        // TODO
        $state['counts']['pve_processed'] += 1;
        $state['step'] = 'pvp';
        $state['progress'] = [ 'phase' => 'pvp', 'pct' => 85 ];
        $state['updated_at'] = current_time('mysql');
        poke_hub_gm_state_set( $state );
        return true;
    }

    // --- Étape 5: PvP ---
    if ( $step === 'pvp' ) {
        // TODO
        $state['counts']['pvp_processed'] += 1;
        $state['step'] = 'finalize';
        $state['progress'] = [ 'phase' => 'finalize', 'pct' => 95 ];
        $state['updated_at'] = current_time('mysql');
        poke_hub_gm_state_set( $state );
        return true;
    }

    // --- Finalize ---
    if ( $step === 'finalize' ) {

        // Construire une summary “comme avant”
        $summary = [
            // Ici tu branches tes compteurs réels (inserted/updated)
            'pokemon_processed' => (int)($state['counts']['pokemon_processed'] ?? 0),
            'attacks_processed' => (int)($state['counts']['attacks_processed'] ?? 0),
            'links_processed'   => (int)($state['counts']['links_processed'] ?? 0),
            'pve_processed'     => (int)($state['counts']['pve_processed'] ?? 0),
            'pvp_processed'     => (int)($state['counts']['pvp_processed'] ?? 0),
        ];

        update_option( 'poke_hub_gm_last_run', current_time( 'mysql' ) );
        update_option( 'poke_hub_gm_last_summary', $summary );

        poke_hub_gm_status_set( [
            'state'    => 'done',
            'ended_at' => current_time('mysql'),
            'path'     => $path,
            'message'  => 'Done',
        ] );

        poke_hub_gm_state_reset();
        return false;
    }

    // step inconnu => stop
    throw new Exception( 'Unknown batch step: ' . $step );
}

/**
 * Helpers à brancher sur TON GM réel.
 * Là je mets des stubs: tu les remplis avec ta logique d’extraction.
 */
function poke_hub_gm_extract_pokemon_rows( array $gm ) : array {
    // TODO: extraire la liste Pokémon depuis le JSON GM
    return [];
}
function poke_hub_gm_extract_attack_rows( array $gm ) : array {
    // TODO
    return [];
}
function poke_hub_gm_extract_links_work( array $gm, string $substep ) : array {
    // TODO
    return [];
}
function poke_hub_gm_next_links_substep( string $current ) : ?string {
    $order = [ 'pokemon_types', 'attack_types', 'type_weakness', 'type_resistance', 'pokemon_attacks' ];
    $idx = array_search( $current, $order, true );
    if ( $idx === false ) return $order[0];
    $next = $idx + 1;
    return isset( $order[$next] ) ? $order[$next] : null;
}
