<?php
/** Real WordPress/MySQL and HTTP multipart integration; disposable CI database only. */
if ( ! in_array( PHP_SAPI, array( 'cli', 'cli-server' ), true ) ) { http_response_code( 404 ); exit; }
$qaRoot = getenv( 'TRB_WP_MYSQL_ROOT' );
$qaRoot = is_string( $qaRoot ) ? str_replace( '\\', '/', $qaRoot ) : '';
$qaTemporaryRoot = str_replace( '\\', '/', realpath( sys_get_temp_dir() ) ?: '' );
if ( ! $qaTemporaryRoot || dirname( dirname( $qaRoot ) ) !== $qaTemporaryRoot || ! preg_match( '~^trb-wp-mysql-qa-[0-9]+$~D', basename( dirname( $qaRoot ) ) ) || 'wordpress' !== basename( $qaRoot ) || ! is_file( $qaRoot . '/wp-load.php' ) || is_link( $qaRoot ) || is_link( dirname( $qaRoot ) ) ) throw new RuntimeException( 'An isolated official WordPress CI installation is required.' );
$qaServing = PHP_SAPI === 'cli-server';
$qaDatabase = getenv( 'TRB_QA_MYSQL_DATABASE' ) ?: 'trb_wp_qa';
if ( ! preg_match( '/^trb_wp_qa(?:_[0-9]+)?$/D', $qaDatabase ) ) throw new RuntimeException( 'A disposable QA database is required.' );
if ( $qaServing && ( ( $_SERVER['REMOTE_ADDR'] ?? '' ) !== '127.0.0.1' || ! hash_equals( (string) getenv( 'TRB_QA_HTTP_TOKEN' ), (string) ( $_SERVER['HTTP_X_TRB_QA_TOKEN'] ?? '' ) ) ) ) { http_response_code( 404 ); exit; }
if ( ! $qaServing ) {
    if ( is_file( $qaRoot . '/wp-config.php' ) ) throw new RuntimeException( 'Refusing to reuse an existing WordPress configuration.' );
    $qaConfig = "<?php\ndefine('DB_NAME','" . $qaDatabase . "');\ndefine('DB_USER','root');\ndefine('DB_PASSWORD','qa_ci');\ndefine('DB_HOST','127.0.0.1:3307');\ndefine('DB_CHARSET','utf8mb4');\ndefine('DB_COLLATE','');\ndefine('DISABLE_WP_CRON',true);\ndefine('DISALLOW_FILE_MODS',true);\ndefine('AUTOMATIC_UPDATER_DISABLED',true);\ndefine('WP_DEBUG',true);\ndefine('WP_DEBUG_DISPLAY',false);\ndefine('WP_ENVIRONMENT_TYPE','local');\n\$table_prefix='qa_';\n";
    foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $qaKey ) $qaConfig .= "define('" . $qaKey . "','" . bin2hex( random_bytes( 32 ) ) . "');\n";
    $qaConfig .= "if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');\nrequire_once ABSPATH.'wp-settings.php';\n";
    file_put_contents( $qaRoot . '/wp-config.php', $qaConfig );
    mkdir( $qaRoot . '/wp-content/mu-plugins', 0700, true );
    file_put_contents( $qaRoot . '/wp-content/mu-plugins/qa-isolation.php', '<?php add_filter("pre_wp_mail",static function(){return true;},PHP_INT_MAX); add_filter("pre_http_request",static function(){return new WP_Error("qa_network_blocked","External HTTP is disabled in this isolated fixture.");},PHP_INT_MAX);' );
    $_SERVER['HTTP_HOST'] = '127.0.0.1';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    define( 'WP_INSTALLING', true );
}
require $qaRoot . '/wp-load.php';
if ( ! $qaServing ) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    wp_install( 'TRB isolated QA', 'qa_admin', 'qa-admin@example.invalid', false, '', wp_generate_password( 32 ), 'en_US' );
}
require dirname( __DIR__, 2 ) . '/inc/trb-release-integrity.php';
require dirname( __DIR__, 2 ) . '/inc/trb-artist-portal.php';
define( 'TRB_CRM_SYNC_SECRET', str_repeat( 's', 64 ) );
require dirname( __DIR__, 2 ) . '/integrations/portal-mu-plugins/trb-crm-sync.php';
require dirname( __DIR__, 2 ) . '/integrations/portal-mu-plugins/trb-z-crm-release-sync-r26.php';
require dirname( __DIR__, 2 ) . '/inc/trb-candidate-onboarding.php';
add_action( 'trb_crm_sync_storage_failure', static function( $scope, $error ) { error_log( 'Synthetic storage fault: ' . get_class( $error ) . ': ' . $error->getMessage() ); }, 10, 2 );
add_filter( 'template_directory', static function() { return dirname( __DIR__, 2 ); } );
if ( $qaServing ) {
    $qaIdentity = $_SERVER['HTTP_X_TRB_QA_USER'] ?? '';
    $qaUserId = 'anonymous' === $qaIdentity ? 0 : ( 'admin' === $qaIdentity ? get_user_by( 'login', 'qa_admin' )->ID : (int) getenv( 'TRB_QA_USER_ID' ) );
    wp_set_current_user( $qaUserId );
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        if ( ( $_POST['action'] ?? '' ) === 'qa_public_search' ) {
            define( 'DOING_AJAX', true );
            register_post_type( 'qa_internal', array( 'public' => false, 'publicly_queryable' => false ) );
            register_post_type( 'qa_public', array( 'public' => true, 'label' => 'Contenuti di prova' ) );
            require dirname( __DIR__, 2 ) . '/inc/template-functions.php';
            require dirname( __DIR__, 2 ) . '/inc/ajax_actions.php';
            ajax_search_handler();
        }
        if ( ( $_POST['action'] ?? '' ) === 'docy_edit_comment' ) {
            require dirname( __DIR__, 2 ) . '/inc/comment-functions.php';
            if ( ( $_POST['qa_delete_after_update'] ?? '' ) === '1' ) {
                add_action( 'comment_approved_comment', static function( $id ) { wp_delete_comment( $id, true ); }, PHP_INT_MAX );
            }
            docy_ajax_edit_comment();
        }
        if ( ( $_POST['action'] ?? '' ) === 'docy_buy_now_add_to_cart' ) {
            require dirname( __DIR__, 2 ) . '/inc/woo_config.php';
            docy_buy_now_add_to_cart();
        }
        if ( ( $_POST['action'] ?? '' ) === 'docy_submit_article_rating' ) {
            require dirname( __DIR__, 2 ) . '/inc/ajax_actions.php';
            docy_submit_article_rating();
        }
        if ( ( $_POST['action'] ?? '' ) === 'qa_csf_save' ) {
            require dirname( __DIR__, 2 ) . '/inc/csf/classes/abstract.class.php';
            require dirname( __DIR__, 2 ) . '/inc/csf/classes/admin-options.class.php';
            $qaSettings = ( new ReflectionClass( CSF_Options::class ) )->newInstanceWithoutConstructor();
            $qaSettings->unique = 'qa_csf_fixture';
            $qaSettings->pre_fields = array( array( 'id' => 'fixture' ) );
            $qaSettings->options = get_option( $qaSettings->unique );
            $qaSettings->ajax_save();
        }
        if ( in_array( $_POST['action'] ?? '', array( 'csf-import', 'csf-reset' ), true ) ) {
            require dirname( __DIR__, 2 ) . '/inc/csf/functions/actions.php';
            if ( 'csf-import' === $_POST['action'] ) csf_import_ajax();
            csf_reset_ajax();
        }
        if ( ( $_POST['action'] ?? '' ) === 'qa_mu_sync' ) {
            $route = (string) ( $_POST['qa_route'] ?? '' );
            if ( ! preg_match( '#^/(?:entitlement|release/[0-9]+|reconcile|onboarding/private|onboarding/public)$#D', $route ) ) wp_send_json_error( null, 400 );
            $body = (string) wp_unslash( $_POST['qa_body'] ?? '' );
            $onboarding = str_starts_with( $route, '/onboarding/' );
            $request = new WP_REST_Request( (string) ( $_POST['qa_method'] ?? 'POST' ), ( $onboarding ? '/trb/v1' : '/trb-crm/v1' ) . $route );
            $request->set_header( 'Content-Type', 'application/json' );
            $timestamp = (string) time();
            $request->set_header( 'X-TRB-Timestamp', $timestamp );
            $request->set_header( 'X-TRB-Signature', ( $_POST['qa_signed'] ?? '' ) === '1' ? 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, TRB_CRM_SYNC_SECRET ) : 'invalid' );
            $request->set_body( $body );
            if ( $onboarding ) {
                require_once dirname( __DIR__, 2 ) . '/inc/trb-crm-connector.php';
                require_once dirname( __DIR__, 2 ) . '/inc/trb-release-spreadsheet-bridge.php';
                $nonce = bin2hex( random_bytes( 16 ) ); $request->set_header( 'X-TRB-Nonce', $nonce );
                $request->set_header( 'X-TRB-Signature', 'sha256=' . hash_hmac( 'sha256', 'onboarding-portal-v1|' . $timestamp . '|' . $nonce . '|' . $body, trb_crm_connector_settings()['secret'] ) );
                if ( $route === '/onboarding/public' ) {
                    $_COOKIE['__Host-trb_onboarding'] = str_repeat( 'b', 64 );
                    $request->set_header( 'X-TRB-Onboarding-Csrf', trb_onboarding_csrf( $_COOKIE['__Host-trb_onboarding'] ) );
                    set_transient( trb_onboarding_browser_key(), array( 'session' => 'synthetic-onboarding-session' ), HOUR_IN_SECONDS );
                    add_filter( 'pre_http_request', static function( $pre, $args, $url ) {
                        if ( $url !== 'https://crm.trbrec.com/webhooks/artist-portal/onboarding' ) return $pre;
                        $payload = json_decode( $args['body'], true );
                        $reply = ( $payload['action'] ?? '' ) === 'registration_authorization' ? array( 'id' => str_repeat( 'c', 32 ), 'email' => 'qa-onboarding@example.invalid', 'first_name' => 'Artista', 'last_name' => 'Fittizio', 'artist_name' => 'Artista Fittizio Tunisia' ) : array( 'registered' => false );
                        return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $reply ), 'headers' => array() );
                    }, PHP_INT_MAX, 3 );
                }
            }
            $response = rest_do_request( $request );
            wp_send_json( $response->get_data(), $response->get_status() );
        }
        if ( ( $_POST['action'] ?? '' ) === 'qa_demo_worker' ) require __DIR__ . '/demo-worker-http.php';
        if ( ( $_POST['action'] ?? '' ) === 'trb_portal_stage_release_chunk' ) trb_portal_stage_release_chunk();
        if ( ( $_POST['action'] ?? '' ) === 'trb_portal_submit_demo' ) {
            require dirname( __DIR__, 2 ) . '/inc/trb-crm-connector.php';
            if ( ! empty( $_POST['qa_reject_demo_schedule'] ) ) add_filter( 'pre_schedule_event', static function( $pre, $event ) { return $event->hook === 'trb_portal_process_demo' ? new WP_Error( 'qa_rejected', 'Synthetic schedule failure' ) : $pre; }, 10, 2 );
            trb_portal_submit_demo();
        }
        trb_portal_handle_artist_profile();
    }
    header( 'Content-Type: application/json' );
    echo wp_json_encode( array( 'nonce' => wp_create_nonce( 'trb_portal_save_artist_profile' ), 'demo_nonce' => wp_create_nonce( 'trb_portal_submit_demo' ), 'stage_nonce' => wp_create_nonce( 'trb_portal_stage_release' ), 'settings_nonce' => wp_create_nonce( 'csf_backup_nonce' ), 'settings_form_nonce' => wp_create_nonce( 'csf_options_nonce' ), 'user_id' => $qaUserId, 'outbound_disabled' => ! function_exists( 'mail' ) && ! function_exists( 'curl_exec' ) && ! ini_get( 'allow_url_fopen' ), 'complete' => trb_portal_artist_profile_is_complete(), 'completion' => trb_portal_artist_profile_completion(), 'fields' => get_user_meta( $qaUserId ), 'files' => trb_portal_private_profile_files() ) );
    exit;
}
function qa_check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); $GLOBALS['qa_checks']++; }
function qa_http( $fields = null, $anonymous = false, $administrator = false, $cookie = '' ) {
    $qaCurl = curl_init( 'http://' . $GLOBALS['qa_address'] . '/' );
    curl_setopt_array( $qaCurl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => array( 'X-TRB-QA-Token: ' . $GLOBALS['qa_http_token'], 'X-TRB-QA-User: ' . ( $anonymous ? 'anonymous' : ( $administrator ? 'admin' : 'artist' ) ) ) ) );
    if ( '' !== $cookie ) curl_setopt( $qaCurl, CURLOPT_COOKIE, $cookie );
    if ( null !== $fields ) curl_setopt_array( $qaCurl, array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields ) );
    $qaResponse = curl_exec( $qaCurl );
    if ( false === $qaResponse ) throw new RuntimeException( 'QA HTTP request failed: ' . curl_error( $qaCurl ) );
    $qaHeaderSize = curl_getinfo( $qaCurl, CURLINFO_HEADER_SIZE );
    $qaStatus = curl_getinfo( $qaCurl, CURLINFO_RESPONSE_CODE );
    curl_close( $qaCurl );
    $qaHeader = substr( $qaResponse, 0, $qaHeaderSize );
    preg_match( '/^Location:\s*(.+)$/mi', $qaHeader, $qaLocation );
    return array( 'status' => $qaStatus, 'location' => trim( $qaLocation[1] ?? '' ), 'data' => json_decode( substr( $qaResponse, $qaHeaderSize ), true ), 'body' => substr( $qaResponse, $qaHeaderSize ) );
}
$GLOBALS['qa_checks'] = 0;
add_role( 'artista_d', 'Ordinary TRB artist', array( 'read' => true, 'trb_portal_trb' => true ) );
$qaPassword = wp_generate_password( 32 );
$qaUser = wp_insert_user( array( 'user_login' => 'fictional_tunisia_artist', 'user_pass' => $qaPassword, 'user_email' => 'qa-tunisia@example.invalid', 'role' => 'artista_d', 'display_name' => 'Fictional QA Artist' ) );
qa_check( ! is_wp_error( $qaUser ), 'Synthetic user creation failed.' );
qa_check( wp_authenticate( 'fictional_tunisia_artist', $qaPassword ) instanceof WP_User, 'Native WordPress password authentication failed.' );
qa_check( ! trb_portal_is_release_qa_account( get_userdata( $qaUser ) ), 'Fixture incorrectly bypasses normal artist requirements.' );
$qaSocket = stream_socket_server( 'tcp://127.0.0.1:0' );
$qaAddress = stream_socket_get_name( $qaSocket, false ); fclose( $qaSocket );
$GLOBALS['qa_address'] = $qaAddress;
$GLOBALS['qa_http_token'] = bin2hex( random_bytes( 32 ) );
update_option( 'home', 'http://' . $qaAddress ); update_option( 'siteurl', 'http://' . $qaAddress );
$qaPage = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'QA profile' ) );
update_option( 'trb_portal_dashboard_created', $qaPage );
$qaEnv = getenv(); $qaEnv['TRB_QA_HTTP_TOKEN'] = $GLOBALS['qa_http_token']; $qaEnv['TRB_QA_USER_ID'] = (string) $qaUser;
$qaProcess = proc_open( array( PHP_BINARY, '-d', 'display_errors=0', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=mail,curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client', '-S', $qaAddress, __FILE__ ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $qaRoot . '/qa-http.log', 'a' ), 2 => array( 'file', $qaRoot . '/qa-http.log', 'a' ) ), $qaPipes, __DIR__, $qaEnv );
if ( ! is_resource( $qaProcess ) ) throw new RuntimeException( 'QA HTTP server did not start.' );
try {
    for ( $qaAttempt = 0; $qaAttempt < 40; $qaAttempt++ ) {
        $qaReady = @stream_socket_client( 'tcp://' . $qaAddress, $qaErrno, $qaError, 0.1 );
        if ( $qaReady ) { fclose( $qaReady ); break; }
        usleep( 50000 );
    }
    $qaInitial = qa_http()['data'];
    qa_check( is_array( $qaInitial ) && $qaInitial['user_id'] === $qaUser && ! $qaInitial['complete'], 'Ordinary fictional artist did not start incomplete.' );
    qa_check( $qaInitial['outbound_disabled'], 'HTTP fixture did not disable its native outbound transports.' );
    $qaContract = array( 'trb_portal_profile_nonce' => $qaInitial['nonce'], 'trb_artist_first_name' => 'Artista', 'trb_artist_last_name' => 'Fittizio', 'trb_artist_country' => 'Tunisia', 'trb_artist_city' => 'تونس', 'trb_artist_province' => '', 'trb_artist_postal_code' => '', 'trb_artist_street' => 'Indirizzo QA fittizio', 'trb_artist_street_number' => '', 'trb_artist_birth_date' => '1990-01-01', 'trb_artist_birth_country' => 'Tunisia', 'trb_artist_birth_place' => 'تونس', 'trb_artist_birth_province' => '', 'trb_artist_phone' => '+216 20 123 456', 'trb_artist_tax_country' => 'Tunisia', 'trb_artist_tax_code' => 'QA-TN-123456789', 'trb_artist_document_type' => 'foreign_identity', 'trb_artist_document_number' => 'QA-TN-123456', 'trb_artist_document_no_expiry' => '1' );
    $qaBadNonce = $qaContract; $qaBadNonce['trb_portal_profile_nonce'] = 'invalid';
    qa_check( qa_http( $qaBadNonce )['status'] === 403, 'A forged nonce was accepted.' );
    qa_check( qa_http()['data']['fields'] === $qaInitial['fields'], 'Invalid nonce changed stored metadata.' );
    qa_check( str_contains( qa_http( $qaContract, true )['location'], 'wp-login.php' ), 'Anonymous profile submission was accepted.' );
    $qaSaved = qa_http( $qaContract );
    qa_check( str_contains( $qaSaved['location'], 'trb_profile=saved' ), 'Foreign contract form failed: ' . $qaSaved['location'] );
    $qaAfterContract = qa_http()['data'];
    qa_check( $qaAfterContract['fields']['_trb_artist_city'][0] === 'تونس' && $qaAfterContract['fields']['_trb_artist_phone'][0] === '+21620123456', 'Real MySQL lost Unicode geography or phone normalization.' );
    qa_check( ! $qaAfterContract['complete'], 'Missing artist files incorrectly counted as complete.' );
    $qaBadPhone = $qaContract; $qaBadPhone['trb_artist_phone'] = 'wrong';
    qa_check( str_contains( qa_http( $qaBadPhone )['location'], 'invalid_phone' ), 'Invalid phone did not fail before saving.' );
    qa_check( qa_http()['data']['fields'] === $qaAfterContract['fields'], 'Invalid contract input partially changed metadata.' );
    $qaBiography = $qaRoot . '/qa-biography.txt'; $qaImage = $qaRoot . '/qa-image.png'; $qaForged = $qaRoot . '/qa-forged.png';
    file_put_contents( $qaBiography, 'Biography of a fictional Tunisian QA artist; no real personal document.' );
    file_put_contents( $qaImage, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=' ) );
    file_put_contents( $qaForged, 'This is plain text, not a PNG.' );
    $qaIdentity = array( 'trb_portal_profile_nonce' => $qaInitial['nonce'], 'trb_artist_identity_section' => '1', 'trb_artist_artist_name' => 'Artista Fittizio QA تونس', 'trb_artist_spotify_new' => '1', 'trb_artist_apple_music_new' => '1', 'trb_artist_youtube_none' => '1', 'trb_artist_soundcloud_none' => '1', 'trb_artist_live_fee' => '100', 'trb_artist_bio_file' => new CURLFile( $qaBiography, 'text/plain', 'biography.txt' ), 'trb_artist_photos[0]' => new CURLFile( $qaImage, 'image/png', 'photo.png' ), 'trb_artist_id_front' => new CURLFile( $qaImage, 'image/png', 'identity.png' ), 'trb_artist_tax_front' => new CURLFile( $qaImage, 'image/png', 'tax.png' ) );
    qa_check( str_contains( qa_http( $qaIdentity )['location'], 'trb_profile=saved' ), 'Real multipart identity/profile upload failed.' );
    $qaComplete = qa_http()['data'];
    qa_check( $qaComplete['complete'] && $qaComplete['completion']['remaining'] === 0 && count( $qaComplete['files'] ) === 4, 'Ordinary foreign profile did not become complete through real WordPress metadata and file storage.' );
    qa_check( str_contains( qa_http( $qaIdentity )['location'], 'trb_profile=saved' ), 'Identical multipart retry failed.' );
    qa_check( qa_http()['data']['files'] === $qaComplete['files'], 'Identical multipart retry duplicated stored files.' );
    global $wpdb;
    $wpdb->query( "CREATE TRIGGER qa_reject_profile BEFORE UPDATE ON {$wpdb->usermeta} FOR EACH ROW BEGIN IF NEW.meta_key='_trb_artist_tax_code' AND NEW.meta_value='QA-TN-FAIL123456' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA profile write failure'; END IF; END" );
    file_put_contents( $qaBiography, 'Biography pending a deliberately failed database save.' );
    $qaDatabaseFailure = $qaContract;
    $qaDatabaseFailure['trb_artist_phone'] = '+216 20 123 457';
    $qaDatabaseFailure['trb_artist_tax_code'] = 'QA-TN-FAIL123456';
    $qaDatabaseFailure['trb_artist_bio_file'] = new CURLFile( $qaBiography, 'text/plain', 'pending-biography.txt' );
    try {
        qa_check( str_contains( qa_http( $qaDatabaseFailure )['location'], 'profile_save_failed' ), 'A real mid-profile MySQL failure was reported as success.' );
        qa_check( qa_http()['data']['fields'] === $qaComplete['fields'], 'Failed MySQL write left a partially updated profile.' );
        $qaUploads = wp_upload_dir();
        qa_check( count( glob( $qaUploads['basedir'] . '/trb-artist-private/*' ) ) === 4, 'Database rollback left new orphan files or removed previous files.' );
        foreach ( $qaComplete['files'] as $qaStoredFile ) qa_check( is_file( $qaUploads['basedir'] . '/' . $qaStoredFile['path'] ) && hash_file( 'sha256', $qaUploads['basedir'] . '/' . $qaStoredFile['path'] ) === $qaStoredFile['sha256'], 'Database failure destroyed previously stored bytes.' );
    } finally { $wpdb->query( 'DROP TRIGGER qa_reject_profile' ); }
    file_put_contents( $qaBiography, 'Replacement fictional biography.' );
    $qaFailedUpload = array( 'trb_portal_profile_nonce' => $qaInitial['nonce'], 'trb_artist_identity_section' => '1', 'trb_artist_bio_file' => new CURLFile( $qaBiography, 'text/plain', 'new-biography.txt' ), 'trb_artist_id_front' => new CURLFile( $qaForged, 'image/png', 'forged-identity.png' ) );
    qa_check( str_contains( qa_http( $qaFailedUpload )['location'], 'file_upload_failed' ), 'Forged PNG replacement reported success.' );
    qa_check( qa_http()['data']['fields'] === $qaComplete['fields'], 'Failed real upload changed MySQL metadata.' );
    $qaUploads = wp_upload_dir();
    qa_check( count( glob( $qaUploads['basedir'] . '/trb-artist-private/*' ) ) === 4, 'Failed multipart left orphaned files.' );
    $qaLock = trb_release_process_lock( 'profile:' . $qaUser );
    try { qa_check( str_contains( qa_http( $qaContract )['location'], 'profile_busy' ), 'Concurrent profile save was not blocked.' ); }
    finally { trb_release_process_unlock( $qaLock ); }
    qa_check( qa_http()['data']['fields'] === $qaComplete['fields'], 'Blocked concurrent save changed the profile.' );
    // Real chunked multipart: storage protection failure, replay and malformed input.
    $qaChunkPath = $qaRoot . '/qa-chunk.txt'; file_put_contents( $qaChunkPath, '12345' );
    $qaChunk = array( 'action' => 'trb_portal_stage_release_chunk', 'trb_release_stage_nonce' => $qaInitial['stage_nonce'], 'session' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'file_key' => 'f0', 'file_name' => 'qa.txt', 'file_type' => 'text/plain', 'file_size' => '10', 'last_modified' => '1', 'chunk_index' => '0', 'chunk_total' => '2', 'upload_id' => 'qa-native', 'trb_release_chunk' => new CURLFile( $qaChunkPath, 'text/plain', 'chunk.txt' ) );
    $qaStagingBase = trb_portal_release_staging_base(); wp_mkdir_p( $qaStagingBase ); mkdir( $qaStagingBase . '/.htaccess' );
    try {
        qa_check( qa_http( $qaChunk )['status'] === 500, 'Chunk upload continued without private web access protection.' );
        qa_check( ! is_dir( $qaStagingBase . '/' . $qaUser ), 'Failed protection created an artist staging session.' );
    } finally { rmdir( $qaStagingBase . '/.htaccess' ); }
    qa_check( qa_http( $qaChunk )['data']['success'] === true, 'Real first multipart chunk failed.' );
    qa_check( qa_http( $qaChunk )['data']['data']['next_chunk'] === 1, 'Real repeated chunk was appended twice.' );
    file_put_contents( $qaChunkPath, '67890' ); $qaChunk['chunk_index'] = '1';
    qa_check( qa_http( $qaChunk )['data']['data']['complete'] === true, 'Real second multipart chunk did not complete.' );
    $qaPart = $qaStagingBase . '/' . $qaUser . '/' . $qaChunk['session'] . '/f0.part';
    qa_check( file_get_contents( $qaPart ) === '1234567890', 'Real multipart chunk assembly changed the bytes.' );
    $qaPartHash = hash_file( 'sha256', $qaPart );
    foreach ( array( 'file_key', 'file_size', 'upload_id', 'audio_status', 'trb_release_stage_nonce' ) as $qaMalformedField ) {
        $qaMalformed = $qaChunk; unset( $qaMalformed[$qaMalformedField] ); $qaMalformed[$qaMalformedField . '[0]'] = 'nested';
        qa_check( qa_http( $qaMalformed )['status'] === 422, 'Nested chunk field was accepted: ' . $qaMalformedField );
    }
    $qaOversizeChunk = $qaChunk; $qaOversizeChunk['chunk_index'] = '0'; $qaOversizeChunk['file_size'] = '1'; $qaOversizeChunk['upload_id'] = 'replacement';
    qa_check( qa_http( $qaOversizeChunk )['status'] === 422, 'Oversized first chunk replacement was accepted.' );
    qa_check( hash_file( 'sha256', $qaPart ) === $qaPartHash, 'Rejected chunk input destroyed a previously assembled file.' );
    require dirname( __DIR__, 2 ) . '/inc/trb-crm-connector.php';
    // Native permissions deny DDL; installation must not record a schema that is absent.
    $wpdb->query( "CREATE USER 'qa_schema_readonly'@'%' IDENTIFIED BY 'qa_ci'" );
    $wpdb->query( "GRANT SELECT ON {$qaDatabase}.* TO 'qa_schema_readonly'@'%'" );
    $qaRootDb = $wpdb;
    $qaRestrictedDb = new wpdb( 'qa_schema_readonly', 'qa_ci', $qaDatabase, '127.0.0.1:3307' );
    $qaRestrictedDb->set_prefix( $qaRootDb->prefix );
    $qaRestrictedDb->suppress_errors( true );
    $GLOBALS['wpdb'] = $qaRestrictedDb;
    try {
        qa_check( false === trb_crm_connector_install(), 'Failed native DDL was reported as installed.' );
        qa_check( false === get_option( 'trb_crm_connector_schema', false ), 'Failed DDL persisted the schema marker.' );
    } finally {
        $GLOBALS['wpdb'] = $qaRootDb;
        $qaRestrictedDb->close();
        $wpdb->query( "DROP USER 'qa_schema_readonly'@'%'" );
    }
    qa_check( trb_crm_connector_install(), 'Installation could not retry after denied DDL.' );
    // Native REST dispatch and real WordPress capabilities protect operational data.
    $qaPreviousUser = get_current_user_id();
    try {
        foreach ( array( array( 0, 401 ), array( $qaUser, 403 ), array( get_user_by( 'login', 'qa_admin' )->ID, 200 ) ) as list( $qaHealthUser, $qaHealthStatus ) ) {
            wp_set_current_user( $qaHealthUser );
            $qaHealthResponse = rest_do_request( new WP_REST_Request( 'GET', '/trb/v1/crm-sync-health' ) );
            qa_check( $qaHealthResponse->get_status() === $qaHealthStatus && ( $qaHealthStatus === 200 || ! isset( $qaHealthResponse->get_data()['queue'] ) ), 'CRM health permissions exposed operational data or denied the administrator.' );
        }
    } finally { wp_set_current_user( $qaPreviousUser ); }
    $qaOutbox = trb_crm_connector_table();
    qa_check( trb_crm_connector_queue( 'artist', $qaUser, array( 'name' => 'Fictional QA artist' ) ), 'Native MySQL outbox insert failed.' );
    $qaFirstEvent = $wpdb->get_row( "SELECT id,status FROM {$qaOutbox} ORDER BY id DESC LIMIT 1", ARRAY_A );
    $wpdb->query( "CREATE TRIGGER qa_reject_outbox BEFORE INSERT ON {$qaOutbox} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA insert failure'" );
    $qaPriorErrors = $wpdb->suppress_errors( true );
    qa_check( ! trb_crm_connector_queue( 'artist', $qaUser, array( 'name' => 'Replacement QA snapshot' ) ), 'Failed native MySQL insert was reported as successful.' );
    $qaBootstrapBefore = array( 'phase' => 'artists', 'artist_page' => 1, 'demo_page' => 1, 'complete' => false );
    update_option( 'trb_crm_bootstrap_state', $qaBootstrapBefore, false );
    qa_check( trb_crm_connector_bootstrap( 100 ) === $qaBootstrapBefore, 'Failed bootstrap skipped an unqueued profile.' );
    qa_check( get_option( 'trb_crm_bootstrap_state' ) === $qaBootstrapBefore, 'Failed bootstrap persisted progress.' );
    $wpdb->suppress_errors( $qaPriorErrors );
    qa_check( $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$qaOutbox} WHERE id=%d", $qaFirstEvent['id'] ) ) === 'queued', 'Failed native insert discarded the previous deliverable event.' );
    $wpdb->query( 'DROP TRIGGER qa_reject_outbox' );
    qa_check( trb_crm_connector_bootstrap( 100 )['phase'] === 'demos', 'Recovered bootstrap could not resume its page.' );
    qa_check( trb_crm_connector_queue( 'artist', $qaUser, array( 'name' => 'Replacement QA snapshot' ) ), 'Native replacement insert failed.' );
    qa_check( $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$qaOutbox} WHERE id=%d", $qaFirstEvent['id'] ) ) === 'superseded', 'Native replacement did not supersede the old event.' );
    // Independent native WordPress processes exercise the same MySQL counter.
    $qaVersionProcesses = array();
    $qaVersionScript = 'require ' . var_export( $qaRoot . '/wp-load.php', true ) . '; require ' . var_export( dirname( __DIR__, 2 ) . '/inc/trb-crm-connector.php', true ) . '; $versions=[];for($i=0;$i<50;$i++){$v=trb_crm_connector_version();if($v===false)exit(2);$versions[]=$v;}echo json_encode($versions);';
    for ( $qaWorker = 0; $qaWorker < 4; $qaWorker++ ) {
        $qaVersionOutput = $qaRoot . '/qa-versions-' . $qaWorker . '.json';
        $qaVersionProcess = proc_open( array( PHP_BINARY, '-d', 'allow_url_fopen=0', '-d', 'disable_functions=mail,curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client', '-r', $qaVersionScript ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $qaVersionOutput, 'w' ), 2 => array( 'file', $qaRoot . '/qa-version-errors.log', 'a' ) ), $qaVersionPipes );
        if ( ! is_resource( $qaVersionProcess ) ) throw new RuntimeException( 'QA counter worker could not start.' );
        fclose( $qaVersionPipes[0] ); $qaVersionProcesses[] = array( $qaVersionProcess, $qaVersionOutput );
    }
    $qaVersions = array();
    foreach ( $qaVersionProcesses as list( $qaVersionProcess, $qaVersionOutput ) ) {
        qa_check( proc_close( $qaVersionProcess ) === 0, 'Concurrent native WordPress counter worker failed.' );
        $qaWorkerVersions = json_decode( file_get_contents( $qaVersionOutput ), true );
        qa_check( is_array( $qaWorkerVersions ) && count( $qaWorkerVersions ) === 50, 'Counter worker did not return its actual versions.' );
        $qaSortedVersions = $qaWorkerVersions; sort( $qaSortedVersions );
        qa_check( $qaWorkerVersions === $qaSortedVersions, 'Counter decreased within a worker.' );
        $qaVersions = array_merge( $qaVersions, $qaWorkerVersions );
    }
    qa_check( count( array_unique( $qaVersions ) ) === 200, 'Concurrent processes received duplicate entity versions.' );
    qa_check( (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'trb_crm_last_entity_version' ) ) === max( $qaVersions ), 'Counter persisted an older concurrent version.' );
    $wpdb->query( "CREATE TRIGGER qa_reject_counter BEFORE UPDATE ON {$wpdb->options} FOR EACH ROW BEGIN IF NEW.option_name='trb_crm_last_entity_version' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA counter failure'; END IF; END" );
    $qaEventCount = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$qaOutbox}" );
    $qaPriorErrors = $wpdb->suppress_errors( true );
    try {
        qa_check( ! trb_crm_connector_queue( 'artist', $qaUser, array( 'name' => 'Rejected counter fixture' ) ), 'Failed version counter accepted an event.' );
        qa_check( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$qaOutbox}" ) === $qaEventCount, 'Failed counter changed the outbox.' );
    } finally { $wpdb->suppress_errors( $qaPriorErrors ); $wpdb->query( 'DROP TRIGGER qa_reject_counter' ); }
    // No provider request: native HTTP is intercepted before any network transport.
    update_option( 'trb_crm_connector_settings', array( 'enabled' => true, 'endpoint' => 'https://crm.trbrec.com/webhooks/artist-portal/sync', 'secret' => str_repeat( 'q', 64 ) ) );
    $qaInterceptCalls = 0; $qaResponseCode = 200; $qaMalformedResponse = false; $qaQueueDuringDelivery = false;
    $qaTransportFixture = static function( $pre, $args, $url ) use ( &$qaInterceptCalls, &$qaResponseCode, &$qaMalformedResponse, &$qaQueueDuringDelivery, $qaUser ) {
        if ( $url !== 'https://crm.trbrec.com/webhooks/artist-portal/sync' ) return $pre;
        $qaInterceptCalls++; $batch = json_decode( $args['body'], true );
        qa_check( $args['redirection'] === 0 && count( $batch['events'] ) === 1, 'Outbox transport fixture boundary changed.' );
        if ( $qaQueueDuringDelivery ) qa_check( trb_crm_connector_queue( 'artist', $qaUser, array( 'name' => 'Newer concurrent snapshot' ) ), 'Concurrent delivery replacement failed.' );
        $result = $qaMalformedResponse ? array( 'error' => array( 'unexpected-shape' ), 'results' => array( null, array( 'event_id' => array( 'nested' ) ) ) ) : array( 'results' => array( array( 'event_id' => $batch['events'][0]['event_id'], 'status' => 'applied' ) ) );
        return array( 'headers' => array(), 'body' => wp_json_encode( $result ), 'response' => array( 'code' => $qaResponseCode, 'message' => 'Synthetic response' ), 'cookies' => array() );
    };
    add_filter( 'pre_http_request', $qaTransportFixture, PHP_INT_MAX, 3 );
    foreach ( array( array( 200, false, false, 'acknowledged' ), array( 500, false, false, 'retry' ), array( 200, true, false, 'retry' ), array( 500, false, true, 'superseded' ), array( 200, false, true, 'superseded' ) ) as list( $qaResponseCode, $qaMalformedResponse, $qaQueueDuringDelivery, $qaExpectedStatus ) ) {
        $wpdb->query( "DELETE FROM {$qaOutbox}" );
        qa_check( trb_crm_connector_queue( 'artist', $qaUser, array( 'name' => 'Delivery fixture' ) ), 'Delivery fixture could not queue.' );
        $qaDeliveringId = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$qaOutbox}" );
        $qaPreviousCalls = $qaInterceptCalls; trb_crm_connector_deliver( 10 );
        qa_check( $qaInterceptCalls === $qaPreviousCalls + 1, 'Native HTTP was not intercepted exactly once.' );
        qa_check( $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$qaOutbox} WHERE id=%d", $qaDeliveringId ) ) === $qaExpectedStatus, 'Outbox acknowledged an HTTP failure or revived a superseded event.' );
        if ( $qaQueueDuringDelivery ) qa_check( $wpdb->get_var( "SELECT status FROM {$qaOutbox} ORDER BY id DESC LIMIT 1" ) === 'queued', 'Delivery changed the newer concurrent event.' );
    }
    remove_filter( 'pre_http_request', $qaTransportFixture, PHP_INT_MAX );
    $GLOBALS['trb_crm_connector_dirty_profiles'][$qaUser] = true;
    trb_crm_connector_profile_saved( $qaUser );
    $qaEventCount = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$qaOutbox}" );
    trb_crm_connector_flush_profiles();
    qa_check( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$qaOutbox}" ) === $qaEventCount, 'Successful profile save queued the same snapshot again at shutdown.' );
    require __DIR__ . '/demo-mysql-cases.php';
    require __DIR__ . '/demo-worker-mysql-cases.php';
    require __DIR__ . '/mu-sync-mysql-cases.php';
    require __DIR__ . '/onboarding-storage-mysql-cases.php';
    require __DIR__ . '/theme-settings-mysql-cases.php';
    require __DIR__ . '/theme-rating-mysql-cases.php';
    require __DIR__ . '/theme-comments-mysql-cases.php';
    require __DIR__ . '/theme-search-mysql-cases.php';
    $qaCartOldUser = get_current_user_id();
    wp_set_current_user( $qaUser );
    $qaCartNonce = wp_create_nonce( 'docy-buy-now-nonce' );
    wp_set_current_user( $qaCartOldUser );
    foreach ( array( array( array(), 503 ), array( array( 'variation' => 'malformed' ), 400 ), array( array( 'nonce' => '', 'nonce[0]' => 'malformed' ), 403 ) ) as [ $qaCartOverrides, $qaCartStatus ] ) {
        $qaCartResponse = qa_http( array_replace( array( 'action' => 'docy_buy_now_add_to_cart', 'product_id' => '123', 'nonce' => $qaCartNonce ), $qaCartOverrides ) );
        qa_check( $qaCartStatus === $qaCartResponse['status'] && false === $qaCartResponse['data']['success'], 'The real HTTP cart boundary accepted malformed input or failed without a controlled response when WooCommerce is absent.' );
    }
    preg_match_all( '/^.*PHP (?:Warning|Notice|Deprecated|Fatal error|Parse error).*$/m', file_get_contents( $qaRoot . '/qa-http.log' ), $qaPhpDiagnostics );
    qa_check( empty( $qaPhpDiagnostics[0] ), 'The real HTTP fixture emitted unexpected PHP diagnostics: ' . implode( "\n", array_slice( $qaPhpDiagnostics[0], 0, 6 ) ) );
    echo $GLOBALS['qa_checks'] . " real WordPress/MySQL/HTTP assertions passed; ordinary Tunisia artist, authentication/nonce, metadata, file rollback/retry, process lock and outbox failure.\n";
} finally {
    proc_terminate( $qaProcess ); fclose( $qaPipes[0] ); proc_close( $qaProcess );
}
