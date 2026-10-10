<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
set_exception_handler( static function() { fwrite( STDERR, "Store integration recovery unconfirmed.\n" ); exit( 1 ); } );
$revision = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || isset( $argv[2] ) ) exit( 2 );
require __DIR__ . '/release-file-transaction.php';
trb_release_file_rollback( '/home/customer/www/artist.trbrec.com/private/onboarding-' . $revision . '/store-files', '/home/customer/www/store.trbrec.com/public_html/wp-content/themes' );
echo "Store integration recovery verified; no database restored.\n";
