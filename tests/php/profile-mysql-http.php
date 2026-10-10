<?php
/** Real WordPress/MySQL and HTTP multipart integration; disposable CI database only. */
if ( ! in_array( PHP_SAPI, array( 'cli', 'cli-server' ), true ) ) { http_response_code( 404 ); exit; }
$qaRoot = getenv( 'TRB_WP_MYSQL_ROOT' );
if ( ! is_string( $qaRoot ) || ! preg_match( '~^/tmp/trb-wp-mysql-qa-[0-9]+/wordpress$~D', $qaRoot ) || ! is_file( $qaRoot . '/wp-load.php' ) || is_link( $qaRoot ) ) throw new RuntimeException( 'An isolated official WordPress CI installation is required.' );
$qaServing = PHP_SAPI === 'cli-server';
if ( $qaServing && ( ( $_SERVER['REMOTE_ADDR'] ?? '' ) !== '127.0.0.1' || ! hash_equals( (string) getenv( 'TRB_QA_HTTP_TOKEN' ), (string) ( $_SERVER['HTTP_X_TRB_QA_TOKEN'] ?? '' ) ) ) ) { http_response_code( 404 ); exit; }
if ( ! $qaServing ) {
    if ( is_file( $qaRoot . '/wp-config.php' ) ) throw new RuntimeException( 'Refusing to reuse an existing WordPress configuration.' );
    $qaConfig = "<?php\ndefine('DB_NAME','trb_wp_qa');\ndefine('DB_USER','root');\ndefine('DB_PASSWORD','qa_ci');\ndefine('DB_HOST','127.0.0.1:3307');\ndefine('DB_CHARSET','utf8mb4');\ndefine('DB_COLLATE','');\ndefine('DISABLE_WP_CRON',true);\ndefine('DISALLOW_FILE_MODS',true);\ndefine('AUTOMATIC_UPDATER_DISABLED',true);\ndefine('WP_DEBUG',true);\ndefine('WP_DEBUG_DISPLAY',false);\ndefine('WP_ENVIRONMENT_TYPE','local');\n\$table_prefix='qa_';\n";
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
if ( $qaServing ) {
    $qaUserId = ( $_SERVER['HTTP_X_TRB_QA_USER'] ?? '' ) === 'anonymous' ? 0 : (int) getenv( 'TRB_QA_USER_ID' );
    wp_set_current_user( $qaUserId );
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) trb_portal_handle_artist_profile();
    header( 'Content-Type: application/json' );
    echo wp_json_encode( array( 'nonce' => wp_create_nonce( 'trb_portal_save_artist_profile' ), 'user_id' => $qaUserId, 'outbound_disabled' => ! function_exists( 'mail' ) && ! function_exists( 'curl_exec' ) && ! ini_get( 'allow_url_fopen' ), 'complete' => trb_portal_artist_profile_is_complete(), 'completion' => trb_portal_artist_profile_completion(), 'fields' => get_user_meta( $qaUserId ), 'files' => trb_portal_private_profile_files() ) );
    exit;
}
function qa_check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); $GLOBALS['qa_checks']++; }
function qa_http( $fields = null, $anonymous = false ) {
    $qaCurl = curl_init( 'http://' . $GLOBALS['qa_address'] . '/' );
    curl_setopt_array( $qaCurl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => array( 'X-TRB-QA-Token: ' . $GLOBALS['qa_http_token'], 'X-TRB-QA-User: ' . ( $anonymous ? 'anonymous' : 'artist' ) ) ) );
    if ( null !== $fields ) curl_setopt_array( $qaCurl, array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields ) );
    $qaResponse = curl_exec( $qaCurl );
    if ( false === $qaResponse ) throw new RuntimeException( 'QA HTTP request failed: ' . curl_error( $qaCurl ) );
    $qaHeaderSize = curl_getinfo( $qaCurl, CURLINFO_HEADER_SIZE );
    $qaStatus = curl_getinfo( $qaCurl, CURLINFO_RESPONSE_CODE );
    curl_close( $qaCurl );
    $qaHeader = substr( $qaResponse, 0, $qaHeaderSize );
    preg_match( '/^Location:\s*(.+)$/mi', $qaHeader, $qaLocation );
    return array( 'status' => $qaStatus, 'location' => trim( $qaLocation[1] ?? '' ), 'data' => json_decode( substr( $qaResponse, $qaHeaderSize ), true ) );
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
    require dirname( __DIR__, 2 ) . '/inc/trb-crm-connector.php';
    trb_crm_connector_install();
    $qaOutbox = trb_crm_connector_table();
    qa_check( trb_crm_connector_queue( 'artist', $qaUser, array( 'name' => 'Fictional QA artist' ) ), 'Native MySQL outbox insert failed.' );
    global $wpdb;
    $qaFirstEvent = $wpdb->get_row( "SELECT id,status FROM {$qaOutbox} ORDER BY id DESC LIMIT 1", ARRAY_A );
    $wpdb->query( "CREATE TRIGGER qa_reject_outbox BEFORE INSERT ON {$qaOutbox} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA insert failure'" );
    $qaPriorErrors = $wpdb->suppress_errors( true );
    qa_check( ! trb_crm_connector_queue( 'artist', $qaUser, array( 'name' => 'Replacement QA snapshot' ) ), 'Failed native MySQL insert was reported as successful.' );
    $wpdb->suppress_errors( $qaPriorErrors );
    qa_check( $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$qaOutbox} WHERE id=%d", $qaFirstEvent['id'] ) ) === 'queued', 'Failed native insert discarded the previous deliverable event.' );
    $wpdb->query( 'DROP TRIGGER qa_reject_outbox' );
    qa_check( trb_crm_connector_queue( 'artist', $qaUser, array( 'name' => 'Replacement QA snapshot' ) ), 'Native replacement insert failed.' );
    qa_check( $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$qaOutbox} WHERE id=%d", $qaFirstEvent['id'] ) ) === 'superseded', 'Native replacement did not supersede the old event.' );
    echo $GLOBALS['qa_checks'] . " real WordPress/MySQL/HTTP assertions passed; ordinary Tunisia artist, authentication/nonce, metadata, file rollback/retry, process lock and outbox failure.\n";
} finally {
    proc_terminate( $qaProcess ); fclose( $qaPipes[0] ); proc_close( $qaProcess );
}
