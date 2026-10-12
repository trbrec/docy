<?php
/** Hosting-only full bundle readback and WordPress readiness receipt. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
$trb_release_verification_stage = 'bootstrap'; $trb_release_path_fingerprint = null; $trb_release_table_label = null;
set_exception_handler( static function() use ( &$trb_release_verification_stage, &$trb_release_path_fingerprint, &$trb_release_table_label ) {
    while ( ob_get_level() ) ob_end_clean();
    echo json_encode( array( 'stage' => $trb_release_verification_stage, 'path_fingerprint' => $trb_release_path_fingerprint, 'table_label' => $trb_release_table_label ), JSON_THROW_ON_ERROR ) . "\n";
    exit( 1 );
} );
$trb_release_revision = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $trb_release_revision ) ) exit( 2 );
$trb_release_site = '/home/customer/www/artist.trbrec.com'; $trb_release_directory = $trb_release_site . '/private/portal-release-' . $trb_release_revision;
$trb_release_candidate = $trb_release_directory . '/candidate'; $trb_release_theme = $trb_release_site . '/public_html/wp-content/themes/docy';
$trb_release_verification_stage = 'release-path';
if ( realpath( dirname( __DIR__ ) ) !== $trb_release_candidate || is_link( $trb_release_directory ) || trim( (string) @file_get_contents( $trb_release_theme . '/.trb-deployed-sha' ) ) !== $trb_release_revision ) throw new RuntimeException( 'Release path mismatch.' );
require __DIR__ . '/portal-release-backup.php';
$trb_release_files = trb_portal_release_files( $trb_release_candidate );
$trb_release_verification_stage = 'obsolete-modules';
foreach ( array( 'integrations/onboarding/crm/OnboardingPcloud.php', 'tools/test-onboarding-pcloud.php' ) as $trb_release_path ) if ( file_exists( $trb_release_theme . '/' . $trb_release_path ) || is_link( $trb_release_theme . '/' . $trb_release_path ) ) throw new RuntimeException( 'Obsolete module cleanup unconfirmed.' );
$trb_release_verification_stage = 'theme-bytes';
foreach ( $trb_release_files as $trb_release_path => $trb_release_spec ) if ( is_link( $trb_release_theme . '/' . $trb_release_path ) || ! is_file( $trb_release_theme . '/' . $trb_release_path ) || ! hash_equals( $trb_release_spec['sha256'], hash_file( 'sha256', $trb_release_theme . '/' . $trb_release_path ) ) ) { $trb_release_path_fingerprint = substr( hash( 'sha256', $trb_release_path ), 0, 12 ); throw new RuntimeException( 'Theme byte mismatch.' ); }
$trb_release_verification_stage = 'integration-bytes';
$trb_release_manifest_path = $trb_release_site . '/private/portal-audit-' . $trb_release_revision . '/verified-sources.json';
if ( is_link( $trb_release_manifest_path ) || ! is_file( $trb_release_manifest_path ) ) throw new RuntimeException( 'Integration manifest unavailable.' );
$trb_release_integrations = json_decode( file_get_contents( $trb_release_manifest_path ), true, 32, JSON_THROW_ON_ERROR );
foreach ( $trb_release_integrations as $trb_release_entry ) if ( ! str_starts_with( $trb_release_entry['target'], $trb_release_site . '/public_html/wp-content/' ) || is_link( $trb_release_entry['target'] ) || ! is_file( $trb_release_entry['target'] ) || ! hash_equals( $trb_release_entry['next_sha256'], hash_file( 'sha256', $trb_release_entry['target'] ) ) ) throw new RuntimeException( 'Integration byte mismatch.' );
define( 'DISABLE_WP_CRON', true ); define( 'WP_USE_THEMES', false );
$trb_release_verification_stage = 'wordpress-bootstrap';
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
ob_start(); require $trb_release_site . '/public_html/wp-load.php';
$trb_release_verification_stage = 'theme-activation';
if ( realpath( get_template_directory() ) !== $trb_release_theme || ! trb_docy_deployment_is_ssh_only() ) throw new RuntimeException( 'Theme activation mismatch.' );
$trb_release_verification_stage = 'announcement-pause';
if ( ! function_exists( 'trb_portal_launch_campaign_is_ready' ) || trb_portal_launch_campaign_is_ready()
    || file_exists( $trb_release_directory . '/artist-communications.json' ) || is_link( $trb_release_directory . '/artist-communications.json' )
    || false !== has_action( 'init', 'trb_portal_schedule_launch_campaign' )
    || false !== has_action( 'trb_portal_send_launch_campaign_batch', 'trb_portal_send_launch_campaign_batch' ) ) throw new RuntimeException( 'Artist communications are not paused for the complete audit.' );
$trb_release_verification_stage = 'runtime-components';
foreach ( array( 'trb_webdav_request', 'trb_portal_handle_artist_profile', 'trb_crm_sync_atomic', 'trb_portal_maintain_error_log' ) as $trb_release_function ) if ( ! function_exists( $trb_release_function ) ) { $trb_release_path_fingerprint = substr( hash( 'sha256', $trb_release_function ), 0, 12 ); throw new RuntimeException( 'Portal component missing.' ); }
$trb_release_verification_stage = 'daily-log-schedule';
$trb_release_log_event = wp_get_scheduled_event( 'trb_portal_daily_log_maintenance' );
if ( ! $trb_release_log_event || $trb_release_log_event->schedule !== 'daily' || ! has_action( 'trb_portal_daily_log_maintenance', 'trb_portal_maintain_error_log' ) ) throw new RuntimeException( 'Daily log maintenance unconfirmed.' );
global $wpdb;
$trb_release_verification_stage = 'atomic-storage';
foreach ( array( 'users', 'usermeta', 'posts', 'postmeta', 'options' ) as $trb_release_table_label ) {
    $trb_release_table = $wpdb->$trb_release_table_label;
    $trb_release_engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $trb_release_table ) );
    if ( strtoupper( (string) $trb_release_engine ) !== 'INNODB' ) throw new RuntimeException( 'Atomic storage unavailable.' );
}
$trb_release_table_label = null; $trb_release_verification_stage = 'private-receipt';
while ( ob_get_level() ) ob_end_clean();
$trb_release_receipt = json_encode( array( 'revision' => $trb_release_revision, 'verified' => true, 'theme_files' => count( $trb_release_files ), 'integration_files' => count( $trb_release_integrations ), 'verified_at' => gmdate( 'c' ) ), JSON_THROW_ON_ERROR );
$trb_release_temporary = $trb_release_directory . '/complete.json.next';
if ( file_put_contents( $trb_release_temporary, $trb_release_receipt, LOCK_EX ) !== strlen( $trb_release_receipt ) || ! chmod( $trb_release_temporary, 0600 ) || ! rename( $trb_release_temporary, $trb_release_directory . '/complete.json' ) ) throw new RuntimeException( 'Release receipt failed.' );
echo "Complete portal bundle, active theme, atomic database and private release receipt verified.\n";
