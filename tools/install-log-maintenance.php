<?php
/** Install only reviewed log maintenance, with file recovery and native cron readback. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
set_exception_handler( static function() { fwrite( STDERR, "Private log maintenance installation unconfirmed.\n" ); exit( 1 ); } );
require __DIR__ . '/release-file-transaction.php';
require __DIR__ . '/integration-syntax-preflight.php';
$revision = $argv[1] ?? ''; $bundle = dirname( __DIR__ );
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || isset( $argv[2] ) || trim( (string) @file_get_contents( $bundle . '/.trb-deployed-sha' ) ) !== $revision ) exit( 2 );
$site = '/home/customer/www/artist.trbrec.com'; $root = $site . '/public_html'; $content = $root . '/wp-content';
if ( is_link( $root ) || realpath( $root ) !== $root || is_link( $site . '/private' ) || realpath( $site . '/private' ) !== $site . '/private' ) throw new RuntimeException( 'Unsafe log installation root.' );
$lock_path = $site . '/private/log-maintenance-install.lock';
if ( is_link( $lock_path ) ) throw new RuntimeException( 'Unsafe log installation lock.' );
$lock = fopen( $lock_path, 'c' );
if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) throw new RuntimeException( 'Log installation busy.' );
$sources = array(
    $content . '/themes/docy/inc/trb-log-maintenance.php' => file_get_contents( $bundle . '/inc/trb-log-maintenance.php' ),
    $content . '/mu-plugins/trb-log-maintenance-bootstrap.php' => file_get_contents( $bundle . '/integrations/portal-mu-plugins/trb-log-maintenance-bootstrap.php' ),
);
$changes = array();
foreach ( $sources as $path => $next ) {
    trb_release_file_check_path( $path, $content );
    $original = is_file( $path ) ? file_get_contents( $path ) : null;
    if ( null !== $original && $original !== $next ) throw new RuntimeException( 'Existing log source diverged.' );
    if ( $original !== $next ) $changes[] = compact( 'path', 'next', 'original' );
}
trb_integration_syntax_preflight( $sources );
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['SERVER_NAME'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['HTTPS'] = 'on';
ob_start(); require $root . '/wp-load.php'; ob_end_clean();
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
$previous_event = wp_next_scheduled( 'trb_portal_daily_log_maintenance' );
$backup = $site . '/private/log-maintenance-' . $revision . '/files';
try {
    trb_release_file_install( $changes, $backup, $content );
    require_once $content . '/themes/docy/inc/trb-log-maintenance.php';
    trb_portal_schedule_log_maintenance();
    $event = wp_get_scheduled_event( 'trb_portal_daily_log_maintenance' );
    if ( ! $event || $event->schedule !== 'daily' || ! has_action( 'trb_portal_daily_log_maintenance', 'trb_portal_maintain_error_log' ) ) throw new RuntimeException( 'Native daily maintenance readback failed.' );
    $maintenance = trb_portal_maintain_error_log();
    if ( true !== ( $maintenance['completed'] ?? false ) ) throw new RuntimeException( 'Installed log maintenance failed.' );
    foreach ( $sources as $path => $next ) if ( is_link( $path ) || ! hash_equals( hash( 'sha256', $next ), hash_file( 'sha256', $path ) ) ) throw new RuntimeException( 'Installed log bytes changed.' );
    echo json_encode( array( 'completed' => true, 'daily_schedule_verified' => true, 'handler_verified' => true, 'file_readback_verified' => true, 'artist_messages' => 0, 'maintenance' => $maintenance ), JSON_THROW_ON_ERROR ) . "\n";
} catch ( Throwable $error ) {
    if ( ! $previous_event && isset( $event ) && $event && wp_next_scheduled( 'trb_portal_daily_log_maintenance' ) === $event->timestamp ) wp_unschedule_event( $event->timestamp, 'trb_portal_daily_log_maintenance' );
    if ( $changes ) trb_release_file_rollback( $backup, $content );
    throw $error;
} finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
