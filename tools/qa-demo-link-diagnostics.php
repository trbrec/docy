<?php
/** Read-only signed health diagnostics; credential values never enter output. */
function trb_qa_demo_health_receipt( $response ) {
    $data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
    $status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
    return array(
        'http_status' => $status,
        'json' => is_array( $data ),
        'authenticated_read_only' => $status >= 200 && $status < 300 && is_array( $data ) && ( $data['success'] ?? null ) === true && ( $data['protocol'] ?? null ) === 'trb-demo-sheet-health-v1' && ( $data['sheet_available'] ?? null ) === true && ( $data['read_only'] ?? null ) === true,
        'unauthorized' => is_array( $data ) && ( $data['error'] ?? null ) === 'unauthorized',
    );
}

function trb_qa_demo_direct_health( $url, $envelope ) {
    if ( ! preg_match( '~^https://script\.google\.com/macros/s/[a-zA-Z0-9_-]+/exec$~D', $url ) ) throw new RuntimeException( 'Unexpected demo destination.' );
    if ( ( $envelope['payload_base64'] ?? null ) !== base64_encode( '{"action":"health"}' ) || ! preg_match( '/^[a-f0-9]{64}$/D', $envelope['signature'] ?? '' ) || count( $envelope ) !== 2 ) throw new RuntimeException( 'Only signed read-only health is allowed.' );
    $response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $envelope ) ) );
    if ( ! is_wp_error( $response ) && in_array( (int) wp_remote_retrieve_response_code( $response ), array( 301, 302, 303 ), true ) ) {
        $location = wp_remote_retrieve_header( $response, 'location' );
        $parts = is_string( $location ) ? wp_parse_url( $location ) : false;
        if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || ( $parts['host'] ?? '' ) !== 'script.googleusercontent.com' || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) throw new RuntimeException( 'Unexpected demo response destination.' );
        $response = wp_remote_get( $location, array( 'timeout' => 30, 'redirection' => 0 ) );
    }
    return trb_qa_demo_health_receipt( $response );
}

function trb_qa_demo_function_hash( $name ) {
    if ( ! function_exists( $name ) ) return null;
    $function = new ReflectionFunction( $name );
    $lines = file( $function->getFileName() );
    return hash( 'sha256', implode( '', array_slice( $lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1 ) ) );
}

if ( defined( 'TRB_QA_DEMO_DIAGNOSTIC_LIBRARY_ONLY' ) ) return;
if ( PHP_SAPI !== 'cli' || ! preg_match( '/^[a-f0-9]{16}$/D', $argv[1] ?? '' ) || isset( $argv[2] ) ) { http_response_code( 404 ); exit( 2 ); }
ini_set( 'display_errors', '0' );
$buffer = ob_get_level(); ob_start();
$result = array( 'completed' => false, 'read_only' => true, 'settings_changed' => false, 'credential_exported' => false, 'artist_messages' => 0 );
register_shutdown_function( static function() use ( &$result, $buffer ) { while ( ob_get_level() > $buffer ) ob_end_clean(); echo json_encode( $result, JSON_UNESCAPED_SLASHES ) . "\n"; } );
try {
    $site = '/home/customer/www/artist.trbrec.com';
    $directory = $site . '/private/demo-key-alignment-' . $argv[1];
    $path = $directory . '/rollback-and-pending.json';
    if ( is_link( $directory ) || realpath( $directory ) !== $directory || is_link( $path ) ) throw new RuntimeException( 'Unsafe pending backup.' );
    $plan = json_decode( file_get_contents( $path ), true, 8, JSON_THROW_ON_ERROR );
    if ( ! is_string( $plan['previous'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $plan['next'] ?? '' ) || ! is_int( $plan['prepared_at'] ?? null ) || time() > $plan['prepared_at'] + 86400 ) throw new RuntimeException( 'Pending diagnosis unavailable.' );
    define( 'DISABLE_WP_CRON', true );
    $_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
    require $site . '/public_html/wp-load.php';
    add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
    $settings = trb_demo_settings();
    if ( ( $settings['sheet_webhook_url'] ?? null ) !== $plan['url'] || ! in_array( $settings['sheet_webhook_secret'] ?? null, array( $plan['previous'], $plan['next'] ), true ) ) throw new RuntimeException( 'Concurrent demo configuration.' );
    $result['wordpress_setting_is_previous'] = $settings['sheet_webhook_secret'] === $plan['previous'];
    $result['wordpress_setting_is_pending'] = $settings['sheet_webhook_secret'] === $plan['next'];
    $health = wp_remote_get( $plan['url'], array( 'timeout' => 30, 'redirection' => 3 ) );
    $protocol = is_wp_error( $health ) ? null : json_decode( wp_remote_retrieve_body( $health ), true );
    if ( ( $protocol['protocol'] ?? null ) !== 'trb-demo-sheet-v2' || ( $protocol['isolated_qa'] ?? null ) !== true ) throw new RuntimeException( 'Read-only provider protocol not identified.' );
    foreach ( array( 'settings' => 'trb_demo_settings', 'sheet_transport' => 'trb_demo_post_sheet_webhook', 'dav_url' => 'trb_webdav_url', 'dav_transport' => 'trb_webdav_request' ) as $key => $function ) $result['loaded_function_sha256'][$key] = trb_qa_demo_function_hash( $function );
    $body = '{"action":"health"}';
    foreach ( array( 'pending' => $plan['next'], 'previous' => $plan['previous'], 'invalid' => null ) as $name => $secret ) {
        $envelope = array( 'payload_base64' => base64_encode( $body ), 'signature' => null === $secret ? str_repeat( '0', 64 ) : hash_hmac( 'sha256', $body, $secret ) );
        $result['direct'][$name] = trb_qa_demo_direct_health( $plan['url'], $envelope );
        $result['installed_transport'][$name] = trb_qa_demo_health_receipt( trb_demo_post_sheet_webhook( $plan['url'], $envelope ) );
    }
    $result['completed'] = true;
} catch ( Throwable $error ) { $result['failure'] = array( 'class' => get_class( $error ), 'line' => $error->getLine() ); }
