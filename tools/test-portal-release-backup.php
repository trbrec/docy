<?php
require __DIR__ . '/portal-release-backup.php';
$base = sys_get_temp_dir() . '/trb-release-test-' . bin2hex( random_bytes( 8 ) );
$theme = $base . '/theme'; $candidate = $base . '/candidate'; $backup = $base . '/private/snapshot'; $revision = str_repeat( 'a', 40 );
mkdir( $theme, 0700, true ); mkdir( $candidate, 0700 );
file_put_contents( $theme . '/functions.php', '<?php /* precedente */' );
file_put_contents( $theme . '/.trb-deployed-sha', str_repeat( 'b', 40 ) . "\n" );
file_put_contents( $candidate . '/functions.php', '<?php /* nuovo */' );
file_put_contents( $candidate . '/added.php', '<?php /* aggiunto */' );
try {
    if ( 2 !== trb_portal_release_snapshot( $theme, $candidate, $backup, $revision ) ) throw new RuntimeException( 'Snapshot count.' );
    copy( $candidate . '/functions.php', $theme . '/functions.php' ); copy( $candidate . '/added.php', $theme . '/added.php' );
    file_put_contents( $theme . '/.trb-deployed-sha', $revision . "\n" );
    file_put_contents( $theme . '/unrelated.txt', 'Creato da un altro processo.' );
    file_put_contents( $theme . '/functions.php', 'Modifica concorrente.' );
    $refused = false;
    try { trb_portal_release_restore( $theme, $backup, $revision ); } catch ( RuntimeException $error ) { $refused = true; }
    if ( ! $refused || ! is_file( $theme . '/added.php' ) ) throw new RuntimeException( 'Concurrent edit must stop rollback before writes.' );
    copy( $candidate . '/functions.php', $theme . '/functions.php' );
    trb_portal_release_restore( $theme, $backup, $revision );
    if ( file_get_contents( $theme . '/functions.php' ) !== '<?php /* precedente */' || is_file( $theme . '/added.php' ) || ! is_file( $theme . '/unrelated.txt' ) || trim( file_get_contents( $theme . '/.trb-deployed-sha' ) ) !== str_repeat( 'b', 40 ) ) throw new RuntimeException( 'Rollback byte preservation failed.' );
    echo "Private snapshot hashes, partial release rollback, concurrent edit refusal and unrelated file preservation verified.\n";
} finally {
    $entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $entries as $entry ) $entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
    rmdir( $base );
}
