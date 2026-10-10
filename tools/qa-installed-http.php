<?php
/** Actual WordPress login and multipart forms with all installed plugins. */
function trb_qa_installed_http( $work ) {
    $root = $work . '/wordpress'; $settings_path = $work . '/qa-settings.json';
    $settings = json_decode( file_get_contents( $settings_path ), true, 16, JSON_THROW_ON_ERROR );
    if ( empty( $settings['artist_password'] ) || empty( $settings['artist_id'] ) ) throw new RuntimeException( 'Artist fixture unavailable.' );
    $site = '/home/customer/www/artist.trbrec.com/public_html';
    $token = bin2hex( random_bytes( 32 ) ); $name = 'trb-audit-http-' . bin2hex( random_bytes( 12 ) );
    $bridge_directory = $site . '/' . $name;
    $bridge = $bridge_directory . '/index.php';
    if ( file_exists( $bridge_directory ) || is_link( $site ) ) throw new RuntimeException( 'HTTP fixture path collision.' );
    $base = 'https://artist.trbrec.com/' . $name;
    $config = file_get_contents( $root . '/wp-config.php' );
    $config = str_replace( "'http://127.0.0.1'", var_export( $base, true ), $config );
    $config = str_replace( "<?php", "<?php\ndefine('COOKIEPATH','/');define('SITECOOKIEPATH','/');define('ADMIN_COOKIE_PATH','/');", $config );
    if ( file_put_contents( $root . '/wp-config.php', $config, LOCK_EX ) !== strlen( $config ) ) throw new RuntimeException( 'HTTP configuration unavailable.' );
    $code = '<?php ini_set("display_errors","0"); header("Cache-Control: no-store"); if(time()>' . ( time() + 900 ) . '||!hash_equals(' . var_export( $token, true ) . ',(string)($_SERVER["HTTP_X_TRB_QA_TOKEN"]??""))){http_response_code(404);exit;} if(defined("ABSPATH")&&ABSPATH!==' . var_export( $root . '/', true ) . '){http_response_code(409);exit;} if(!defined("ABSPATH"))define("ABSPATH",' . var_export( $root . '/', true ) . '); $path=$_SERVER["HTTP_X_TRB_QA_ROUTE"]??""; $_SERVER["REQUEST_URI"]=$path;';
    // Fixed includes only: a request can never supply an executable path.
    $code .= 'switch($path){case "/wp-login.php":require ' . var_export( $root . '/wp-login.php', true ) . ';break;case "/wp-admin/admin-post.php":require ' . var_export( $root . '/wp-admin/admin-post.php', true ) . ';break;case "/":require ' . var_export( $root . '/index.php', true ) . ';break;default:http_response_code(404);exit;}';
    $code = str_replace( 'header("Cache-Control: no-store");', 'header("Cache-Control: no-store");header("X-TRB-QA-Entry: 1");', $code );
    $bridge_hash = hash( 'sha256', $code );
    $staged_bridge = $work . '/http-bridge.next';
    if ( file_put_contents( $staged_bridge, $code, LOCK_EX ) !== strlen( $code ) || ! chmod( $staged_bridge, 0644 ) || ! hash_equals( $bridge_hash, hash_file( 'sha256', $staged_bridge ) ) ) throw new RuntimeException( 'HTTP fixture staging failed.' );
    $settings['http_bridge'] = array( 'name' => $name . '/index.php', 'sha256' => $bridge_hash );
    $settings_json = json_encode( $settings, JSON_THROW_ON_ERROR );
    if ( file_put_contents( $settings_path, $settings_json, LOCK_EX ) !== strlen( $settings_json ) ) throw new RuntimeException( 'HTTP cleanup manifest unavailable.' );
    $remove_bridge = static function() use ( $bridge, $bridge_hash, $bridge_directory ) {
        if ( is_link( $bridge ) || is_link( $bridge_directory ) ) return false;
        if ( is_file( $bridge ) && ( ! hash_equals( $bridge_hash, hash_file( 'sha256', $bridge ) ) || ! unlink( $bridge ) ) ) return false;
        return ! is_dir( $bridge_directory ) || rmdir( $bridge_directory );
    };
    register_shutdown_function( $remove_bridge );
    $cookie = $work . '/http-cookies.txt'; $checks = array();
    $request = static function( $path, $fields = null, $authenticated = true, $authorized = true ) use ( $name, $cookie, $token ) {
        $curl = curl_init( 'https://artist.trbrec.com/' . $name . '/index.php' );
        curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $authorized ? array( 'X-TRB-QA-Token: ' . $token, 'X-TRB-QA-Route: ' . $path ) : array() ) );
        curl_setopt( $curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; TRB-Audit/1.0)' );
        if ( $authenticated ) curl_setopt_array( $curl, array( CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie ) );
        if ( null !== $fields ) curl_setopt_array( $curl, array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields ) );
        $response = curl_exec( $curl ); $status = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ); $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE ); curl_close( $curl );
        $GLOBALS['trb_qa_http_statuses'][] = (int) $status;
        if ( ! is_string( $response ) ) return array( 'status' => 0, 'body' => '', 'location' => '' );
        $summary = array( 'status' => (int) $status );
        $summary['fixture_php_executed'] = preg_match( '/^X-TRB-QA-Entry:\s*1\s*$/mi', substr( $response, 0, $header_size ) ) === 1;
        foreach ( array( 'wordfence', 'cloudflare', 'siteground', 'mod_security', 'forbidden', 'access denied', 'captcha' ) as $marker ) $summary[str_replace( ' ', '_', $marker )] = stripos( $response, $marker ) !== false;
        $GLOBALS['trb_qa_http_responses'][] = $summary;
        preg_match( '/^Location:\s*(.+)$/mi', substr( $response, 0, $header_size ), $location );
        return array( 'status' => $status, 'body' => substr( $response, $header_size ), 'location' => trim( $location[1] ?? '' ) );
    };
    $check = static function( $condition, $label ) use ( &$checks ) { $GLOBALS['trb_qa_http_stage'] = $label; if ( ! $condition ) throw new RuntimeException( $label ); $checks[] = $label; };
    try {
        if ( ! mkdir( $bridge_directory, 0755 ) || ! rename( $staged_bridge, $bridge ) ) throw new RuntimeException( 'HTTP fixture unavailable.' );
        $check( in_array( $request( '/wp-login.php', null, false, false )['status'], array( 403, 404 ), true ), 'temporary_endpoint_requires_private_key' );
        for ( $attempt = 0; $attempt < 30; $attempt++ ) { $login = $request( '/wp-login.php' ); if ( $login['status'] ) break; usleep( 100000 ); }
        if ( 200 !== $login['status'] ) {
            $probe = curl_init( 'https://artist.trbrec.com/wp-login.php' );
            curl_setopt_array( $probe, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TRB-Audit/1.0)' ) );
            curl_exec( $probe ); $native_status = (int) curl_getinfo( $probe, CURLINFO_RESPONSE_CODE ); curl_close( $probe );
            $GLOBALS['trb_qa_http_responses'][] = array( 'native_wp_login_status' => $native_status, 'fixture_owner_matches_core' => fileowner( $bridge ) === fileowner( $site . '/wp-login.php' ), 'fixture_group_matches_core' => filegroup( $bridge ) === filegroup( $site . '/wp-login.php' ) );
        }
        $check( 200 === $login['status'], 'native_login_page' );
        $login = $request( '/wp-login.php', array( 'log' => 'artista_fittizio_tunisia', 'pwd' => $settings['artist_password'], 'wp-submit' => 'Accedi', 'testcookie' => '1' ) );
        $check( in_array( $login['status'], array( 302, 303 ), true ), 'native_password_login' );
        define( 'ABSPATH', $root . '/' ); $_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/';
        require $root . '/wp-config.php';
        global $wpdb;
        $check( $wpdb->prefix === $settings['prefix'] && str_starts_with( $wpdb->prefix, 'trbqa_' ), 'isolated_database_prefix' );
        $user = get_user_by( 'id', $settings['artist_id'] );
        $check( $user && ! user_can( $user, 'manage_options' ) && ! trb_portal_is_release_qa_account( $user ), 'ordinary_artist_without_bypass' );
        // Derive the nonce from the session cookie issued by the actual login.
        $cookies = file_get_contents( $cookie );
        if ( ! preg_match( '/\t' . preg_quote( LOGGED_IN_COOKIE, '/' ) . '\t([^\r\n]+)/', $cookies, $match ) ) throw new RuntimeException( 'Real login cookie unavailable.' );
        $_COOKIE[LOGGED_IN_COOKIE] = rawurldecode( $match[1] ); wp_set_current_user( $user->ID );
        $nonce = wp_create_nonce( 'trb_portal_save_artist_profile' );
        $fields = array( 'action' => 'trb_portal_save_artist_profile', 'trb_portal_profile_nonce' => $nonce, 'trb_artist_first_name' => 'Artista', 'trb_artist_last_name' => 'Fittizio', 'trb_artist_country' => 'Tunisia', 'trb_artist_city' => 'Tunisi', 'trb_artist_province' => '', 'trb_artist_postal_code' => '', 'trb_artist_street' => 'Indirizzo fittizio di collaudo', 'trb_artist_street_number' => '', 'trb_artist_birth_date' => '1990-01-01', 'trb_artist_birth_country' => 'Tunisia', 'trb_artist_birth_place' => 'Tunisi', 'trb_artist_birth_province' => '', 'trb_artist_phone' => '+216 20 123 456', 'trb_artist_tax_country' => 'Tunisia', 'trb_artist_tax_code' => 'QA-TN-123456789', 'trb_artist_document_type' => 'foreign_identity', 'trb_artist_document_number' => 'QA-TN-123456', 'trb_artist_document_no_expiry' => '1' );
        $anonymous = $request( '/wp-admin/admin-post.php', $fields, false );
        $check( str_contains( $anonymous['location'], 'wp-login.php' ) || 401 === $anonymous['status'], 'anonymous_profile_rejected' );
        $bad = $fields; $bad['trb_portal_profile_nonce'] = 'invalid';
        $check( 403 === $request( '/wp-admin/admin-post.php', $bad )['status'], 'invalid_nonce_rejected' );
        $saved = $request( '/wp-admin/admin-post.php', $fields );
        $check( str_contains( $saved['location'], 'trb_profile=saved' ), 'foreign_address_http_save' );
        clean_user_cache( $user->ID );
        $check( get_user_meta( $user->ID, '_trb_artist_country', true ) === 'Tunisia' && get_user_meta( $user->ID, '_trb_artist_city', true ) === 'Tunisi' && get_user_meta( $user->ID, '_trb_artist_phone', true ) === '+21620123456', 'foreign_metadata_readback' );
        $image = $work . '/synthetic.png'; $biography = $work . '/synthetic.txt'; $forged = $work . '/forged.png';
        file_put_contents( $image, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=' ) );
        file_put_contents( $biography, 'Biografia di artista fittizio. Nessun dato o documento personale reale.' ); file_put_contents( $forged, 'File di testo senza immagine.' );
        $identity = array( 'action' => $fields['action'], 'trb_portal_profile_nonce' => $nonce, 'trb_artist_identity_section' => '1', 'trb_artist_artist_name' => 'Artista Fittizio Tunisia', 'trb_artist_spotify_new' => '1', 'trb_artist_apple_music_new' => '1', 'trb_artist_youtube_none' => '1', 'trb_artist_soundcloud_none' => '1', 'trb_artist_live_fee' => '100', 'trb_artist_bio_file' => new CURLFile( $biography, 'text/plain', 'biografia-fittizia.txt' ), 'trb_artist_photos[0]' => new CURLFile( $image, 'image/png', 'foto-fittizia.png' ), 'trb_artist_id_front' => new CURLFile( $image, 'image/png', 'documento-fittizio.png' ), 'trb_artist_tax_front' => new CURLFile( $image, 'image/png', 'documento-fiscale-fittizio.png' ) );
        $check( str_contains( $request( '/wp-admin/admin-post.php', $identity )['location'], 'trb_profile=saved' ), 'real_multipart_profile_upload' );
        clean_user_cache( $user->ID );
        $before = trb_portal_private_profile_files( $user->ID );
        $check( trb_portal_artist_profile_is_complete( $user->ID ) && count( $before ) === 4, 'ordinary_foreign_profile_complete' );
        $failure = array( 'action' => $fields['action'], 'trb_portal_profile_nonce' => $nonce, 'trb_artist_identity_section' => '1', 'trb_artist_bio_file' => new CURLFile( $biography, 'text/plain', 'nuova-biografia.txt' ), 'trb_artist_id_front' => new CURLFile( $forged, 'image/png', 'falso.png' ) );
        $check( ! str_contains( $request( '/wp-admin/admin-post.php', $failure )['location'], 'trb_profile=saved' ), 'forged_png_rejected' );
        clean_user_cache( $user->ID );
        $check( trb_portal_private_profile_files( $user->ID ) === $before && trb_portal_artist_profile_is_complete( $user->ID ), 'previous_profile_preserved_after_failure' );
        return array( 'completed' => true, 'checks' => $checks, 'active_plugins' => count( get_option( 'active_plugins', array() ) ), 'temporary_endpoint_removed' => true, 'artist_messages' => 0 );
    } finally { if ( ! $remove_bridge() ) throw new RuntimeException( 'HTTP fixture cleanup unconfirmed.' ); }
}
