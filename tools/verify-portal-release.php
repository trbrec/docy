<?php
/** Hosting-only full bundle readback and WordPress readiness receipt. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
set_exception_handler( static function() { fwrite( STDERR, "Portal bundle verification unconfirmed.\n" ); exit( 1 ); } );
$revision = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) ) exit( 2 );
$site = '/home/customer/www/artist.trbrec.com'; $release = $site . '/private/portal-release-' . $revision;
$candidate = $release . '/candidate'; $theme = $site . '/public_html/wp-content/themes/docy';
if ( realpath( dirname( __DIR__ ) ) !== $candidate || is_link( $release ) || trim( (string) @file_get_contents( $theme . '/.trb-deployed-sha' ) ) !== $revision ) throw new RuntimeException( 'Release path mismatch.' );
require __DIR__ . '/portal-release-backup.php';
$files = trb_portal_release_files( $candidate );
foreach ( $files as $path => $spec ) if ( is_link( $theme . '/' . $path ) || ! is_file( $theme . '/' . $path ) || ! hash_equals( $spec['sha256'], hash_file( 'sha256', $theme . '/' . $path ) ) ) throw new RuntimeException( 'Theme byte mismatch.' );
$manifest_path = $site . '/private/portal-audit-' . $revision . '/verified-sources.json';
if ( is_link( $manifest_path ) || ! is_file( $manifest_path ) ) throw new RuntimeException( 'Integration manifest unavailable.' );
$integrations = json_decode( file_get_contents( $manifest_path ), true, 32, JSON_THROW_ON_ERROR );
foreach ( $integrations as $entry ) if ( ! str_starts_with( $entry['target'], $site . '/public_html/wp-content/' ) || is_link( $entry['target'] ) || ! is_file( $entry['target'] ) || ! hash_equals( $entry['next_sha256'], hash_file( 'sha256', $entry['target'] ) ) ) throw new RuntimeException( 'Integration byte mismatch.' );
define( 'DISABLE_WP_CRON', true ); define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
ob_start(); require $site . '/public_html/wp-load.php';
if ( realpath( get_template_directory() ) !== $theme || ! trb_docy_deployment_is_ssh_only() ) throw new RuntimeException( 'Theme activation mismatch.' );
foreach ( array( 'trb_webdav_request', 'trb_portal_handle_artist_profile', 'trb_crm_sync_atomic', 'trb_portal_maintain_error_log' ) as $function ) if ( ! function_exists( $function ) ) throw new RuntimeException( 'Portal component missing.' );
$log_event = wp_get_scheduled_event( 'trb_portal_daily_log_maintenance' );
if ( ! $log_event || $log_event->schedule !== 'daily' || ! has_action( 'trb_portal_daily_log_maintenance', 'trb_portal_maintain_error_log' ) ) throw new RuntimeException( 'Daily log maintenance unconfirmed.' );
global $wpdb;
foreach ( array( $wpdb->users, $wpdb->usermeta, $wpdb->posts, $wpdb->postmeta, $wpdb->options ) as $table ) {
    $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
    if ( strtoupper( (string) $engine ) !== 'INNODB' ) throw new RuntimeException( 'Atomic storage unavailable.' );
}
while ( ob_get_level() ) ob_end_clean();
$receipt = json_encode( array( 'revision' => $revision, 'verified' => true, 'theme_files' => count( $files ), 'integration_files' => count( $integrations ), 'verified_at' => gmdate( 'c' ) ), JSON_THROW_ON_ERROR );
$temporary = $release . '/complete.json.next';
if ( file_put_contents( $temporary, $receipt, LOCK_EX ) !== strlen( $receipt ) || ! chmod( $temporary, 0600 ) || ! rename( $temporary, $release . '/complete.json' ) ) throw new RuntimeException( 'Release receipt failed.' );
echo "Complete portal bundle, active theme, atomic database and private release receipt verified.\n";
