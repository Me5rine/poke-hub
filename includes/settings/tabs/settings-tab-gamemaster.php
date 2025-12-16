<?php
// File: includes/settings/tabs/settings-tab-gamemaster.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

$messages        = [];
$importer_loaded = false;

// Options Game Master
$gm_s3_bucket  = get_option( 'poke_hub_gm_s3_bucket', '' );
$gm_s3_prefix  = get_option( 'poke_hub_gm_s3_prefix', 'gamemaster' );
$gm_s3_region  = get_option( 'poke_hub_gm_s3_region', 'eu-west-3' );
$gm_local_path = get_option( 'poke_hub_gm_local_path', '' );

$last_mtime   = (int) get_option( 'poke_hub_gm_last_mtime', 0 );
$last_run     = (string) get_option( 'poke_hub_gm_last_run', '' );
$last_summary = get_option( 'poke_hub_gm_last_summary', [] );

$last_s3_bucket      = get_option( 'poke_hub_gm_last_s3_bucket', '' );
$last_s3_key         = get_option( 'poke_hub_gm_last_s3_key', '' );
$last_s3_uploaded_at = get_option( 'poke_hub_gm_last_uploaded_at', '' );

/**
 * Upload du latest.json vers S3 à l'aide du SDK AWS installé via Composer dans le plugin.
 *
 * Utilise la classe :
 *   \Aws\S3\S3Client
 *
 * @param string $bucket     Nom du bucket S3 (ex: pokemon.me5rine-lab.com)
 * @param string $key        Clé/chemin dans le bucket (ex: gamemaster/gamemaster-20251207-233152.json)
 * @param string $region     Région S3 (ex: eu-west-3)
 * @param string $local_file Chemin absolu vers latest.json
 *
 * @return true|WP_Error
 */
if ( ! function_exists( 'poke_hub_gm_upload_to_s3' ) ) {
    function poke_hub_gm_upload_to_s3( string $bucket, string $key, string $region, string $local_file ) {

        if ( ! file_exists( $local_file ) ) {
            return new WP_Error(
                'poke_hub_gm_local_file_missing',
                __( 'Local Game Master file does not exist, S3 upload aborted.', 'poke-hub' )
            );
        }

        // 1) Charger l'autoloader Composer du plugin
        $plugin_root   = dirname( __DIR__, 3 ); // remonte de /includes/settings/tabs à la racine du plugin
        $composer_auto = $plugin_root . '/vendor/autoload.php';

        if ( ! file_exists( $composer_auto ) ) {
            return new WP_Error(
                'poke_hub_gm_composer_autoload_missing',
                __( 'Composer autoloader not found. Please run "composer install" in the plugin directory.', 'poke-hub' )
            );
        }

        require_once $composer_auto;

        // 2) Vérifier que la classe S3Client existe
        if ( ! class_exists( '\Aws\S3\S3Client' ) ) {
            return new WP_Error(
                'poke_hub_gm_s3client_missing',
                __( 'AWS S3 client class not found (Aws\\S3\\S3Client). Check Composer dependencies.', 'poke-hub' )
            );
        }

        // 3) Vérifier les credentials (via wp-config.php)
        if ( ! defined( 'POKE_HUB_GM_AWS_KEY' ) || ! defined( 'POKE_HUB_GM_AWS_SECRET' ) ) {
            return new WP_Error(
                'poke_hub_gm_missing_credentials',
                __( 'Please define POKE_HUB_GM_AWS_KEY and POKE_HUB_GM_AWS_SECRET in wp-config.php for Game Master uploads.', 'poke-hub' )
            );
        }

        $aws_key    = POKE_HUB_GM_AWS_KEY;
        $aws_secret = POKE_HUB_GM_AWS_SECRET;

        // 4) Instancier le client S3
        try {
            /** @var \Aws\S3\S3Client $s3 */
            $s3 = new \Aws\S3\S3Client(
                [
                    'version'     => 'latest',
                    'region'      => $region,
                    'credentials' => [
                        'key'    => $aws_key,
                        'secret' => $aws_secret,
                    ],
                ]
            );
        } catch ( \Exception $e ) {
            return new WP_Error(
                'poke_hub_gm_s3client_init_error',
                sprintf(
                    /* translators: %s = error message */
                    __( 'Error initializing AWS S3 client: %s', 'poke-hub' ),
                    $e->getMessage()
                )
            );
        }

        // 5) Upload du fichier
        try {
            $result = $s3->putObject(
                [
                    'Bucket'      => $bucket,
                    'Key'         => $key,
                    'SourceFile'  => $local_file,
                    'ContentType' => 'application/json',
                    // Pas d’ACL explicite : on laisse la policy du bucket décider
                ]
            );

            // Si besoin, on pourrait exploiter $result['ETag'] ou autre.
            return true;

        } catch ( \Exception $e ) {
            return new WP_Error(
                'poke_hub_gm_s3_upload_error',
                sprintf(
                    /* translators: %s = error message */
                    __( 'Error uploading Game Master file to S3: %s', 'poke-hub' ),
                    $e->getMessage()
                )
            );
        }
    }
}

/**
 * 1) Traitement UPLOAD JSON (upload + copie locale + S3 optionnel)
 */
if ( ! empty( $_POST['poke_hub_gm_upload_action'] ) ) {

    check_admin_referer( 'poke_hub_gm_upload', 'poke_hub_gm_upload_nonce' );

    if ( empty( $_FILES['gm_upload_file']['tmp_name'] ) ) {
        $messages[] = [
            'type' => 'error',
            'text' => __( 'No file selected for upload.', 'poke-hub' ),
        ];
    } else {
        $file_tmp  = $_FILES['gm_upload_file']['tmp_name'];
        $file_name = $_FILES['gm_upload_file']['name'];

        $ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

        if ( 'json' !== $ext ) {
            $messages[] = [
                'type' => 'error',
                'text' => __( 'Uploaded file must be a JSON file.', 'poke-hub' ),
            ];
        } else {
            $contents = file_get_contents( $file_tmp );
            if ( false === $contents || '' === $contents ) {
                $messages[] = [
                    'type' => 'error',
                    'text' => __( 'Could not read uploaded file contents or file is empty.', 'poke-hub' ),
                ];
            } else {
                // 1) Copie locale
                $upload_dir = wp_upload_dir();

                $gm_dir = trailingslashit( $upload_dir['basedir'] ) . 'poke-hub/gamemaster/';
                if ( ! wp_mkdir_p( $gm_dir ) ) {
                    $messages[] = [
                        'type' => 'error',
                        'text' => sprintf(
                            __( 'Could not create local directory for Game Master: %s', 'poke-hub' ),
                            esc_html( $gm_dir )
                        ),
                    ];
                } else {
                    $local_file = $gm_dir . 'latest.json';

                    $written = file_put_contents( $local_file, $contents );
                    if ( false === $written ) {
                        $messages[] = [
                            'type' => 'error',
                            'text' => sprintf(
                                __( 'Could not write local Game Master file: %s', 'poke-hub' ),
                                esc_html( $local_file )
                            ),
                        ];
                    } else {
                        update_option( 'poke_hub_gm_local_path', $local_file );
                        $gm_local_path = $local_file;

                        $current_mtime = (int) @filemtime( $local_file );
                        if ( $current_mtime ) {
                            $last_mtime = $current_mtime;
                            update_option( 'poke_hub_gm_last_mtime', $current_mtime );
                        }

                        $messages[] = [
                            'type' => 'success',
                            'text' => sprintf(
                                __( 'Local Game Master file updated: %s', 'poke-hub' ),
                                esc_html( $local_file )
                            ),
                        ];
                    }
                }

                // 2) S3 optionnel
                if ( $gm_s3_bucket !== '' && ! empty( $gm_local_path ) && file_exists( $gm_local_path ) ) {

                    $timestamp = current_time( 'Ymd-His' );
                    $base_name = 'gamemaster-' . $timestamp . '.json';

                    $prefix = trim( $gm_s3_prefix );
                    if ( '' !== $prefix ) {
                        $prefix = rtrim( $prefix, '/' );
                    }

                    $object_key = ( '' !== $prefix ) ? $prefix . '/' . $base_name : $base_name;

                    $uploaded = poke_hub_gm_upload_to_s3(
                        $gm_s3_bucket,
                        $object_key,
                        $gm_s3_region,
                        $gm_local_path
                    );

                    if ( is_wp_error( $uploaded ) ) {
                        $messages[] = [
                            'type' => 'error',
                            'text' => sprintf(
                                __( 'Game Master S3 upload error: %s', 'poke-hub' ),
                                esc_html( $uploaded->get_error_message() )
                            ),
                        ];
                    } else {
                        $last_s3_bucket      = $gm_s3_bucket;
                        $last_s3_key         = $object_key;
                        $last_s3_uploaded_at = current_time( 'mysql' );

                        update_option( 'poke_hub_gm_last_s3_bucket', $last_s3_bucket );
                        update_option( 'poke_hub_gm_last_s3_key', $last_s3_key );
                        update_option( 'poke_hub_gm_last_uploaded_at', $last_s3_uploaded_at );

                        $messages[] = [
                            'type' => 'success',
                            'text' => __( 'Game Master also uploaded to S3 archive.', 'poke-hub' ),
                        ];
                    }
                }
            }
        }
    }
}

/**
 * 2) Traitement SETTINGS + IMPORT / SYNC (sur le fichier local)
 */
if ( ! empty( $_POST['poke_hub_gm_submit'] ) ) {

    check_admin_referer( 'poke_hub_gm_settings', 'poke_hub_gm_nonce' );

    // Mise à jour des réglages S3 depuis le formulaire "Settings"
    if ( isset( $_POST['gm_s3_bucket'] ) ) {
        $gm_s3_bucket = trim( sanitize_text_field( wp_unslash( $_POST['gm_s3_bucket'] ) ) );
        update_option( 'poke_hub_gm_s3_bucket', $gm_s3_bucket );
    }
    if ( isset( $_POST['gm_s3_prefix'] ) ) {
        $gm_s3_prefix = trim( sanitize_text_field( wp_unslash( $_POST['gm_s3_prefix'] ) ) );
        update_option( 'poke_hub_gm_s3_prefix', $gm_s3_prefix );
    }
    if ( isset( $_POST['gm_s3_region'] ) ) {
        $gm_s3_region = trim( sanitize_text_field( wp_unslash( $_POST['gm_s3_region'] ) ) );
        update_option( 'poke_hub_gm_s3_region', $gm_s3_region );
    }

    // On laisse la possibilité de mettre à jour le chemin manuellement si besoin,
    // même si ce champ n'est plus affiché dans le formulaire.
    if ( isset( $_POST['gm_local_path'] ) ) {
        $gm_local_path = trim( sanitize_text_field( wp_unslash( $_POST['gm_local_path'] ) ) );
        update_option( 'poke_hub_gm_local_path', $gm_local_path );
    }

    $action       = sanitize_text_field( $_POST['poke_hub_gm_submit'] ); // save / save_import
    $force_import = ! empty( $_POST['gm_force_import'] );

    $messages[] = [
        'type' => 'success',
        'text' => __( 'Settings saved.', 'poke-hub' ),
    ];

    if ( 'save_import' === $action ) {

        $path = $gm_local_path;

        if ( '' === $path ) {
            $messages[] = [
                'type' => 'error',
                'text' => __( 'No local Game Master file path defined.', 'poke-hub' ),
            ];
        } else {
            // Si le chemin n’est pas absolu, on le normalise depuis wp-content
            if ( ! preg_match( '#^(/|[A-Za-z]:\\\\)#', $path ) ) {
                $path = WP_CONTENT_DIR . '/' . ltrim( $path, '/' );
            }

            if ( ! file_exists( $path ) ) {
                $messages[] = [
                    'type' => 'error',
                    'text' => sprintf(
                        __( 'Game Master file not found: %s', 'poke-hub' ),
                        esc_html( $path )
                    ),
                ];
            } else {
                $skip_for_mtime = false;

                $current_mtime = (int) @filemtime( $path );

                if ( $current_mtime && $last_mtime && $current_mtime <= $last_mtime && ! $force_import ) {
                    $skip_for_mtime = true;

                    $messages[] = [
                        'type' => 'info',
                        'text' => __( 'Game Master file has not changed since last import. Import skipped (check “Force import” to override).', 'poke-hub' ),
                    ];
                }

                if ( ! $skip_for_mtime || $force_import ) {
                    if ( ! function_exists( 'poke_hub_pokemon_import_game_master' ) ) {
                        if ( defined( 'POKE_HUB_POKEMON_PATH' ) ) {
                            $import_file = POKE_HUB_POKEMON_PATH . '/functions/pokemon-import-game-master.php';
                            if ( file_exists( $import_file ) ) {
                                require_once $import_file;
                                $importer_loaded = true;
                            }
                        }
                    } else {
                        $importer_loaded = true;
                    }

                    if ( ! $importer_loaded || ! function_exists( 'poke_hub_pokemon_import_game_master' ) ) {
                        $messages[] = [
                            'type' => 'error',
                            'text' => __( 'Game Master importer not found. Make sure functions/pokemon-import-game-master.php is included.', 'poke-hub' ),
                        ];
                    } else {
                        $result = poke_hub_pokemon_import_game_master( $path );

                        if ( is_wp_error( $result ) ) {
                            $messages[] = [
                                'type' => 'error',
                                'text' => sprintf(
                                    __( 'Game Master import error: %s', 'poke-hub' ),
                                    esc_html( $result->get_error_message() )
                                ),
                            ];
                        } else {
                            $last_run     = current_time( 'mysql' );
                            $last_summary = is_array( $result ) ? $result : [];

                            update_option( 'poke_hub_gm_last_run', $last_run );
                            update_option( 'poke_hub_gm_last_summary', $last_summary );

                            if ( $current_mtime ) {
                                $last_mtime = $current_mtime;
                                update_option( 'poke_hub_gm_last_mtime', $current_mtime );
                            }

                            $messages[] = [
                                'type' => 'success',
                                'text' => __( 'Game Master import completed successfully.', 'poke-hub' ),
                            ];
                        }
                    }
                }
            }
        }
    }
}

// Préparer un éventuel lien public vers le fichier local (si placé dans uploads)
$gm_local_url = '';
if ( $gm_local_path && file_exists( $gm_local_path ) ) {
    $upload_dir = wp_upload_dir();
    $basedir    = wp_normalize_path( $upload_dir['basedir'] );
    $filepath   = wp_normalize_path( $gm_local_path );

    if ( 0 === strpos( $filepath, $basedir ) ) {
        $relative      = ltrim( substr( $filepath, strlen( $basedir ) ), '/\\' );
        $gm_local_url  = trailingslashit( $upload_dir['baseurl'] ) . str_replace( DIRECTORY_SEPARATOR, '/', $relative );
    }
}

// Affichage des notices (erreurs / succès / infos)
foreach ( $messages as $msg ) {
    $class = 'notice';
    if ( 'success' === $msg['type'] ) {
        $class .= ' notice-success';
    } elseif ( 'error' === $msg['type'] ) {
        $class .= ' notice-error';
    } else {
        $class .= ' notice-info';
    }

    printf(
        '<div class="%1$s is-dismissible"><p>%2$s</p></div>',
        esc_attr( $class ),
        esc_html( $msg['text'] )
    );
}

// Mémo wp-config : affiché uniquement si les constantes ne sont pas définies
if ( ! defined( 'POKE_HUB_GM_AWS_KEY' ) || ! defined( 'POKE_HUB_GM_AWS_SECRET' ) ) : ?>
    <div class="notice notice-warning" style="margin-top:20px;">
        <p><strong><?php esc_html_e( 'Game Master – AWS configuration required', 'poke-hub' ); ?></strong></p>
        <p>
            <?php esc_html_e( 'Please add the following lines to your wp-config.php (with your own AWS access key and secret):', 'poke-hub' ); ?>
        </p>
        <pre><code>/* Configuration Poké HUB */
define( 'POKE_HUB_GM_AWS_KEY',    'your-key' );
define( 'POKE_HUB_GM_AWS_SECRET', 'your-secret' );</code></pre>
    </div>
<?php endif; ?>

<!-- 1) SETTINGS : S3 bucket / prefix / region -->
<form method="post" action="" style="margin-top:20px;">
    <?php wp_nonce_field( 'poke_hub_gm_settings', 'poke_hub_gm_nonce' ); ?>
    <h2><?php esc_html_e( 'Game Master – Settings', 'poke-hub' ); ?></h2>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">
                <label for="gm_s3_bucket"><?php esc_html_e( 'S3 bucket (optional)', 'poke-hub' ); ?></label>
            </th>
            <td>
                <input type="text"
                       class="regular-text"
                       id="gm_s3_bucket"
                       name="gm_s3_bucket"
                       value="<?php echo esc_attr( $gm_s3_bucket ); ?>" />
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="gm_s3_prefix"><?php esc_html_e( 'S3 folder (prefix)', 'poke-hub' ); ?></label>
            </th>
            <td>
                <input type="text"
                       class="regular-text"
                       id="gm_s3_prefix"
                       name="gm_s3_prefix"
                       value="<?php echo esc_attr( $gm_s3_prefix ); ?>" />
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="gm_s3_region"><?php esc_html_e( 'S3 region', 'poke-hub' ); ?></label>
            </th>
            <td>
                <input type="text"
                       class="regular-text"
                       id="gm_s3_region"
                       name="gm_s3_region"
                       value="<?php echo esc_attr( $gm_s3_region ); ?>" />
            </td>
        </tr>
    </table>

    <p class="submit">
        <button type="submit"
                name="poke_hub_gm_submit"
                value="save"
                class="button button-secondary">
            <?php esc_html_e( 'Save settings', 'poke-hub' ); ?>
        </button>
    </p>
</form>

<!-- 2) UPLOAD : choix du fichier + upload -->
<form method="post" action="" enctype="multipart/form-data" style="margin-top:30px;">
    <?php wp_nonce_field( 'poke_hub_gm_upload', 'poke_hub_gm_upload_nonce' ); ?>
    <h2><?php esc_html_e( 'Game Master – Upload & Archive', 'poke-hub' ); ?></h2>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">
                <label for="gm_upload_file"><?php esc_html_e( 'Game Master JSON file', 'poke-hub' ); ?></label>
            </th>
            <td>
                <input type="file"
                       id="gm_upload_file"
                       name="gm_upload_file"
                       accept="application/json" />
            </td>
        </tr>
        <?php if ( $gm_local_path ) : ?>
            <tr>
                <th scope="row">
                    <?php esc_html_e( 'Current local file', 'poke-hub' ); ?>
                </th>
                <td>
                    <code><?php echo esc_html( $gm_local_path ); ?></code>
                    <?php if ( $gm_local_url ) : ?>
                        <br />
                        <a href="<?php echo esc_url( $gm_local_url ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e( 'View file in browser', 'poke-hub' ); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <p class="submit">
        <button type="submit"
                name="poke_hub_gm_upload_action"
                value="upload"
                class="button button-secondary">
            <?php esc_html_e( 'Upload JSON & update local copy', 'poke-hub' ); ?>
        </button>
    </p>
</form>

<!-- 3) IMPORT : force import + last run / summary + bouton Import now -->
<form method="post" action="" style="margin-top:30px;">
    <?php wp_nonce_field( 'poke_hub_gm_settings', 'poke_hub_gm_nonce' ); ?>

    <h2><?php esc_html_e( 'Game Master import / sync', 'poke-hub' ); ?></h2>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e( 'Force import', 'poke-hub' ); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="gm_force_import" value="1" />
                    <?php esc_html_e( 'Import even if the local file has not changed since last run.', 'poke-hub' ); ?>
                </label>
            </td>
        </tr>
    </table>

    <h2><?php esc_html_e( 'Last import status', 'poke-hub' ); ?></h2>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e( 'Last run', 'poke-hub' ); ?></th>
            <td>
                <?php
                if ( $last_run ) {
                    echo esc_html( $last_run );
                } else {
                    esc_html_e( 'No import has been run yet.', 'poke-hub' );
                }
                ?>
            </td>
        </tr>

        <?php if ( is_array( $last_summary ) && ! empty( $last_summary ) ) : ?>
            <tr>
                <th scope="row"><?php esc_html_e( 'Summary', 'poke-hub' ); ?></th>
                <td>
                    <ul class="pokehub-gm-summary">
                        <?php foreach ( $last_summary as $key => $value ) : ?>

                            <?php
                            // Par sécurité : si un jour *_deleted réapparaît, on l’ignore
                            if ( substr( $key, -8 ) === '_deleted' ) {
                                continue;
                            }

                            // Labels plus lisibles
                            switch ( $key ) {
                                case 'pokemon_inserted':
                                    $label = __( 'Pokémon inserted', 'poke-hub' );
                                    break;
                                case 'pokemon_updated':
                                    $label = __( 'Pokémon updated', 'poke-hub' );
                                    break;
                                case 'attacks_inserted':
                                    $label = __( 'Attacks inserted', 'poke-hub' );
                                    break;
                                case 'attacks_updated':
                                    $label = __( 'Attacks updated', 'poke-hub' );
                                    break;
                                case 'pve_stats':
                                    $label = __( 'PvE stats updated', 'poke-hub' );
                                    break;
                                case 'pvp_stats':
                                    $label = __( 'PvP stats updated', 'poke-hub' );
                                    break;
                                case 'pokemon_type_links':
                                    $label = __( 'Pokémon ↔ Type links', 'poke-hub' );
                                    break;
                                case 'attack_type_links':
                                    $label = __( 'Attack ↔ Type links', 'poke-hub' );
                                    break;
                                case 'pokemon_type_weakness_links':
                                    $label = __( 'Type ↔ Weakness links', 'poke-hub' );
                                    break;
                                case 'pokemon_type_resistance_links':
                                    $label = __( 'Type ↔ Resistance links', 'poke-hub' );
                                    break;
                                case 'pokemon_attack_links':
                                    $label = __( 'Pokémon ↔ Attack links', 'poke-hub' );
                                    break;
                                default:
                                    $label = $key;
                                    break;
                            }

                            $is_array = is_array( $value );
                            $count    = $is_array ? count( $value ) : 0;

                            // ID safe pour le bloc de détails
                            $detail_id = 'gm-summary-detail-' . preg_replace( '/[^a-z0-9_\-]/i', '_', $key );
                            ?>
                            <li class="gm-summary-item">
                                <strong><?php echo esc_html( $label ); ?></strong> :

                                <?php if ( $is_array ) : ?>
                                    <?php
                                    // Nombre d'éléments (ce que tu voulais pour "nombre de Pokémon")
                                    $count_text = sprintf(
                                        /* translators: %d = number of items */
                                        esc_html( _n( '%d item', '%d items', $count, 'poke-hub' ) ),
                                        $count
                                    );
                                    echo esc_html( $count_text );
                                    ?>

                                    <?php if ( $count > 0 ) : ?>
                                        <button type="button"
                                                class="button button-small gm-toggle-btn"
                                                data-target="<?php echo esc_attr( $detail_id ); ?>">
                                            <?php esc_html_e( 'Show details', 'poke-hub' ); ?>
                                        </button>

                                        <div id="<?php echo esc_attr( $detail_id ); ?>"
                                            class="gm-summary-detail"
                                            style="display:none;">
                                            <ul>
                                                <?php foreach ( $value as $item ) : ?>
                                                    <li><?php echo esc_html( (string) $item ); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                <?php else : ?>

                                    <span><?php echo esc_html( (string) $value ); ?></span>

                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </td>
            </tr>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.gm-toggle-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var targetId = btn.getAttribute('data-target');
                        var content  = document.getElementById(targetId);

                        if (!content) {
                            return;
                        }

                        var isVisible = content.style.display !== 'none';

                        content.style.display = isVisible ? 'none' : 'block';
                        btn.textContent       = isVisible
                            ? '<?php echo esc_js( __( 'Show details', 'poke-hub' ) ); ?>'
                            : '<?php echo esc_js( __( 'Hide details', 'poke-hub' ) ); ?>';
                    });
                });
            });
            </script>
        <?php endif; ?>

    </table>

    <p class="submit">
        <button type="submit"
                name="poke_hub_gm_submit"
                value="save_import"
                class="button button-primary">
            <?php esc_html_e( 'Import now', 'poke-hub' ); ?>
        </button>
    </p>
</form>
