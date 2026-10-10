<?php
/** Hosting-only snapshot and rollback for the exact candidate revision. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
set_exception_handler( static function() { fwrite( STDERR, "Portal release snapshot or rollback unconfirmed.\n" ); exit( 1 ); } );
$mode = $argv[1] ?? ''; $revision = $argv[2] ?? '';
if ( ! in_array( $mode, array( 'snapshot', 'rollback' ), true ) || ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) ) exit( 2 );
$site = '/home/customer/www/artist.trbrec.com';
$theme = $site . '/public_html/wp-content/themes/docy';
$release = $site . '/private/portal-release-' . $revision;
$candidate = $release . '/candidate'; $backup = $release . '/theme.previous';
if ( realpath( dirname( __DIR__ ) ) !== $candidate || realpath( $theme ) !== $theme || is_link( $release ) ) throw new RuntimeException( 'Release path mismatch.' );
require __DIR__ . '/portal-release-backup.php';
$count = 'snapshot' === $mode ? trb_portal_release_snapshot( $theme, $candidate, $backup, $revision ) : trb_portal_release_restore( $theme, $backup, $revision );
echo json_encode( array( 'mode' => $mode, 'revision' => $revision, 'files_verified' => $count, 'database_restored' => false ) ) . "\n";
