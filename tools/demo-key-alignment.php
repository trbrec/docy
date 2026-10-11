<?php
/** One prepared key, two named settings; existing keys never leave their service. */
function trb_demo_key_settings( $raw ) {
    $settings = @unserialize( $raw, array( 'allowed_classes' => false ) );
    if ( ! is_array( $settings ) || ! is_string( $settings['sheet_webhook_secret'] ?? null ) || ! is_string( $settings['sheet_webhook_url'] ?? null ) ) throw new RuntimeException( 'Unexpected demo settings.' );
    foreach ( $settings as $value ) if ( ! is_scalar( $value ) && null !== $value ) throw new RuntimeException( 'Unexpected demo setting value.' );
    if ( ! preg_match( '~^https://script\.google\.com/macros/s/[a-zA-Z0-9_-]+/exec$~D', $settings['sheet_webhook_url'] ) ) throw new RuntimeException( 'Unexpected demo destination.' );
    return $settings;
}
function trb_demo_key_next_raw( $raw, $plan, $rollback ) {
    $settings = trb_demo_key_settings( $raw );
    if ( $settings['sheet_webhook_url'] !== $plan['url'] || ! in_array( $settings['sheet_webhook_secret'], array( $plan['previous'], $plan['next'] ), true ) ) throw new RuntimeException( 'Demo configuration changed concurrently.' );
    $settings['sheet_webhook_secret'] = $rollback ? $plan['previous'] : $plan['next'];
    return serialize( $settings );
}
function trb_demo_key_cas( $plan, $rollback = false ) {
    global $wpdb;
    $name = 'trb_demo_automation_settings';
    $raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $name ) );
    if ( ! is_string( $raw ) ) throw new RuntimeException( 'Demo settings absent.' );
    $next = trb_demo_key_next_raw( $raw, $plan, $rollback );
    if ( $raw !== $next && 1 !== $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=%s", $next, $name, $raw ) ) ) throw new RuntimeException( 'Demo settings update unconfirmed.' );
    wp_cache_delete( $name, 'options' ); wp_cache_delete( 'alloptions', 'options' );
    $actual = get_option( $name, array() );
    if ( ! is_array( $actual ) || $actual['sheet_webhook_secret'] !== ( $rollback ? $plan['previous'] : $plan['next'] ) || $actual['sheet_webhook_url'] !== $plan['url'] ) throw new RuntimeException( 'Demo settings readback unconfirmed.' );
}
function trb_demo_key_prepare( $directory, $settings, $recipient ) {
    if ( is_link( $directory ) || file_exists( $directory ) || ! mkdir( $directory, 0700 ) ) throw new RuntimeException( 'Private key staging collision.' );
    $plan = array( 'previous' => $settings['sheet_webhook_secret'], 'next' => bin2hex( random_bytes( 32 ) ), 'url' => $settings['sheet_webhook_url'], 'prepared_at' => time() );
    if ( ! openssl_public_encrypt( $plan['next'], $sealed, $recipient, OPENSSL_PKCS1_OAEP_PADDING ) ) throw new RuntimeException( 'New key encryption failed.' );
    $path = $directory . '/rollback-and-pending.json'; $bytes = json_encode( $plan, JSON_THROW_ON_ERROR );
    $file = fopen( $path, 'x' ); if ( ! $file ) throw new RuntimeException( 'Private rollback backup unavailable.' );
    try { if ( ! chmod( $path, 0600 ) || fwrite( $file, $bytes ) !== strlen( $bytes ) || ! fflush( $file ) ) throw new RuntimeException( 'Private rollback backup incomplete.' ); }
    finally { fclose( $file ); }
    if ( ! hash_equals( hash( 'sha256', $bytes ), hash_file( 'sha256', $path ) ) ) throw new RuntimeException( 'Private rollback backup readback failed.' );
    return array( 'prepared' => true, 'existing_credential_changed' => false, 'previous_credential_exported' => false, 'sealed_new_key' => base64_encode( $sealed ) );
}
if ( defined( 'TRB_DEMO_KEY_LIBRARY_ONLY' ) ) return;
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' ); $buffer = ob_get_level(); ob_start();
set_exception_handler( static function() use ( $buffer ) { while ( ob_get_level() > $buffer ) ob_end_clean(); fwrite( STDERR, "Demo key operation unconfirmed. No credential exported.\n" ); exit( 1 ); } );
$phase = $argv[1] ?? ''; $tag = $argv[2] ?? '';
if ( ! in_array( $phase, array( 'prepare', 'apply', 'rollback' ), true ) || ! preg_match( '/^[a-f0-9]{16}$/D', $tag ) || isset( $argv[3] ) ) exit( 2 );
$site = '/home/customer/www/artist.trbrec.com'; $private = $site . '/private';
if ( is_link( $private ) || realpath( $private ) !== $private ) throw new RuntimeException( 'Unsafe private hosting.' );
$directory = $private . '/demo-key-alignment-' . $tag;
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
require $site . '/public_html/wp-load.php'; add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
if ( 'prepare' === $phase ) {
    $settings = get_option( 'trb_demo_automation_settings', array() ); trb_demo_key_settings( serialize( $settings ) );
    $result = trb_demo_key_prepare( $directory, $settings, file_get_contents( __DIR__ . '/qa-browser-recipient.pem' ) );
} else {
    $path = $directory . '/rollback-and-pending.json';
    if ( is_link( $directory ) || realpath( $directory ) !== $directory || is_link( $path ) ) throw new RuntimeException( 'Unsafe private rollback backup.' );
    $plan = json_decode( file_get_contents( $path ), true, 8, JSON_THROW_ON_ERROR );
    if ( ! is_array( $plan ) || ! is_string( $plan['previous'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $plan['next'] ?? '' ) || ! is_int( $plan['prepared_at'] ?? null ) ) throw new RuntimeException( 'Invalid pending key.' );
    if ( 'apply' === $phase && time() > $plan['prepared_at'] + 86400 ) throw new RuntimeException( 'Pending key expired.' );
    trb_demo_key_cas( $plan, 'rollback' === $phase );
    $result = array( 'completed' => true, 'phase' => $phase, 'only_demo_sheet_key_changed' => true, 'readback_verified' => true, 'previous_credential_exported' => false, 'artist_messages' => 0 );
}
while ( ob_get_level() > $buffer ) ob_end_clean(); echo json_encode( $result, JSON_THROW_ON_ERROR ) . "\n";
