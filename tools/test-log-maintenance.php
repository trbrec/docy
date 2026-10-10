<?php
define( 'ABSPATH', __DIR__ ); define( 'DAY_IN_SECONDS', 86400 );
function add_action() {}
require dirname( __DIR__ ) . '/inc/trb-log-maintenance.php';
$work = str_replace( '\\', '/', sys_get_temp_dir() ) . '/trb-log-qa-' . bin2hex( random_bytes( 8 ) );
mkdir( $work . '/public_html', 0700, true ); $root = realpath( $work . '/public_html' );
$archive_dir = $work . '/private/error-log-archive';
try {
    $bytes = str_repeat( "Synthetic diagnostic only.\n", 1000 );
    file_put_contents( $root . '/php_errorlog', $bytes );
    $first = trb_portal_rotate_error_log( $root, 1024 );
    $archives = glob( $archive_dir . '/portal-*.log' );
    if ( ! $first['rotated'] || count( $archives ) !== 1 || file_get_contents( $archives[0] ) !== $bytes || filesize( $root . '/php_errorlog' ) !== 0 ) throw new RuntimeException( 'Rotation did not preserve every original byte.' );
    if ( trb_portal_rotate_error_log( $root, 1024 )['compressed'] !== 0 ) throw new RuntimeException( 'Active writer grace period ignored.' );
    touch( $archives[0], time() - 2 * DAY_IN_SECONDS );
    $second = trb_portal_rotate_error_log( $root, 1024 );
    if ( $second['compressed'] !== 1 || $second['bytes_reclaimed'] <= 0 || gzdecode( file_get_contents( $archives[0] . '.gz' ) ) !== $bytes || is_file( $archives[0] ) ) throw new RuntimeException( 'Compression readback or reclamation failed.' );
    file_put_contents( $archive_dir . '/unrelated.txt', 'preserve' );
    file_put_contents( $archive_dir . '/portal-20000101000000-aaaaaaaa.log.gz', gzencode( 'Expired synthetic log' ) );
    if ( trb_portal_rotate_error_log( $root, 1024 )['expired_archives_removed'] !== 1 || file_get_contents( $archive_dir . '/unrelated.txt' ) !== 'preserve' ) throw new RuntimeException( 'Retention removed an unrelated or recent file.' );
    if ( trb_portal_maintain_error_log()['skipped'] !== 'isolated_environment' ) throw new RuntimeException( 'Local environment entered production maintenance.' );
    echo "Log byte preservation, writer grace, compressed readback, retention boundaries and isolated environment guards passed.\n";
} finally {
    foreach ( glob( $archive_dir . '/*' ) ?: array() as $path ) unlink( $path );
    if ( is_dir( $archive_dir ) ) rmdir( $archive_dir );
    if ( is_dir( $work . '/private' ) ) rmdir( $work . '/private' );
    if ( is_file( $root . '/php_errorlog' ) ) unlink( $root . '/php_errorlog' );
    rmdir( $root ); rmdir( $work );
}
