<?php
/** Install only the reviewed guard before merging a release into main. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
set_exception_handler( static function() { fwrite( STDERR, "Deployment guard installation unconfirmed.\n" ); exit( 1 ); } );
$source = dirname( __DIR__ ) . '/integrations/portal-mu-plugins/trb-release-deploy-guard.php';
$target = '/home/customer/www/artist.trbrec.com/public_html/wp-content/mu-plugins/trb-release-deploy-guard.php';
if ( ! is_file( $source ) || is_link( $source ) || is_link( $target ) || ( is_file( $target ) && hash_file( 'sha256', $target ) !== hash_file( 'sha256', $source ) ) ) throw new RuntimeException( 'Deployment guard source mismatch.' );
exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $source ) . ' 2>&1', $output, $status );
if ( 0 !== $status ) throw new RuntimeException( 'Deployment guard syntax invalid.' );
if ( ! is_file( $target ) ) {
    $next = $target . '.audit-next';
    if ( ! copy( $source, $next ) || ! chmod( $next, 0644 ) || ! rename( $next, $target ) ) throw new RuntimeException( 'Deployment guard install failed.' );
}
if ( hash_file( 'sha256', $target ) !== hash_file( 'sha256', $source ) ) throw new RuntimeException( 'Deployment guard readback mismatch.' );
if ( function_exists( 'opcache_invalidate' ) ) opcache_invalidate( $target, true );
echo "Reviewed SSH deployment guard installed and verified.\n";
