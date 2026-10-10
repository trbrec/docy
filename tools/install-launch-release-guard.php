<?php
/** Install independent launch and field-preservation guards with byte recovery and native hooks. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
set_exception_handler( static function() { fwrite( STDERR, "Launch announcement guard installation unconfirmed.\n" ); exit( 1 ); } );
require __DIR__ . '/release-file-transaction.php';
require __DIR__ . '/integration-syntax-preflight.php';
$revision = $argv[1] ?? ''; $bundle = dirname( __DIR__ );
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || isset( $argv[2] ) || trim( (string) @file_get_contents( $bundle . '/.trb-deployed-sha' ) ) !== $revision ) exit( 2 );
$site = '/home/customer/www/artist.trbrec.com'; $root = $site . '/public_html'; $content = $root . '/wp-content';
if ( is_link( $root ) || realpath( $root ) !== $root || is_link( $site . '/private' ) || realpath( $site . '/private' ) !== $site . '/private' ) throw new RuntimeException( 'Unsafe launch guard root.' );
$lock_path = $site . '/private/launch-release-guard-install.lock';
if ( is_link( $lock_path ) ) throw new RuntimeException( 'Unsafe launch guard lock.' );
$lock = fopen( $lock_path, 'c' );
if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) throw new RuntimeException( 'Launch guard installation busy.' );
$changes = array(); $guard_bytes = array();
foreach ( array( 'trb-launch-release-guard.php', 'trb-legacy-field-preservation.php' ) as $guard ) {
    $source = $bundle . '/integrations/portal-mu-plugins/' . $guard;
    $path = $content . '/mu-plugins/' . $guard;
    trb_release_file_check_path( $path, $content );
    if ( is_link( $source ) || ! is_file( $source ) ) throw new RuntimeException( 'Independent guard source unavailable.' );
    $next = file_get_contents( $source ); $original = is_file( $path ) ? file_get_contents( $path ) : null;
    if ( null !== $original && $original !== $next ) throw new RuntimeException( 'Existing independent guard diverged.' );
    $guard_bytes[ $path ] = $next;
    if ( $original !== $next ) $changes[] = compact( 'path', 'original', 'next' );
}
trb_integration_syntax_preflight( $guard_bytes );
$backup = $site . '/private/launch-release-guard-' . $revision . '/files';
try {
    trb_release_file_install( $changes, $backup, $content );
    define( 'DISABLE_WP_CRON', true );
    $_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['SERVER_NAME'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['HTTPS'] = 'on';
    ob_start(); require $root . '/wp-load.php'; ob_end_clean();
    $ready = function_exists( 'trb_docy_has_completed_release' ) && trb_docy_has_completed_release();
    if ( ! $ready && ( false !== has_action( 'init', 'trb_portal_schedule_launch_campaign' ) || false !== has_action( 'trb_portal_send_launch_campaign_batch', 'trb_portal_send_launch_campaign_batch' ) ) ) throw new RuntimeException( 'Unverified native launch hooks remain active.' );
    if ( false !== has_action( 'init', 'docy_remove_acf_fields_if_exists_in_codestar' ) ) throw new RuntimeException( 'Legacy field deletion remains active.' );
    foreach ( $guard_bytes as $path => $next ) {
        if ( is_link( $path ) || ! hash_equals( hash( 'sha256', $next ), hash_file( 'sha256', $path ) ) ) throw new RuntimeException( 'Independent guard readback failed.' );
    }
    echo json_encode( array( 'completed' => true, 'file_readback_verified' => true, 'native_hooks_verified' => true, 'legacy_field_deletion_blocked' => true, 'announcement_waiting_for_verified_release' => ! $ready, 'artist_messages' => 0 ), JSON_THROW_ON_ERROR ) . "\n";
} catch ( Throwable $error ) {
    if ( $changes ) trb_release_file_rollback( $backup, $content );
    throw $error;
} finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
