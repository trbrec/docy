<?php
require __DIR__ . '/release-file-transaction.php';
$base = str_replace( '\\', '/', sys_get_temp_dir() ) . '/trb-file-transaction-' . bin2hex( random_bytes( 8 ) );
$root = $base . '/store-theme'; mkdir( $root, 0700, true );
$first = $root . '/functions.php'; $second = $root . '/bridge.php';
file_put_contents( $first, 'Original store functions.' );
$changes = array( array( 'path' => $first, 'original' => file_get_contents( $first ), 'next' => 'Updated store functions.' ), array( 'path' => $second, 'original' => null, 'next' => 'New integration.' ) );
$reject = static function( $operation ) { try { $operation(); } catch ( RuntimeException $expected ) { return; } throw new RuntimeException( 'Unsafe operation accepted.' ); };
try {
    $backup = $base . '/private/one';
    trb_release_file_install( $changes, $backup, $root );
    if ( file_get_contents( $second ) !== 'New integration.' ) throw new RuntimeException( 'Install readback mismatch.' );
    file_put_contents( $second, 'Concurrent store change.' );
    $reject( static function() use ( $backup, $root ) { trb_release_file_rollback( $backup, $root ); } );
    if ( file_get_contents( $first ) !== 'Updated store functions.' ) throw new RuntimeException( 'Rollback wrote before checking all concurrent edits.' );
    file_put_contents( $second, 'New integration.' );
    trb_release_file_rollback( $backup, $root ); trb_release_file_rollback( $backup, $root );
    if ( file_get_contents( $first ) !== 'Original store functions.' || is_file( $second ) ) throw new RuntimeException( 'Rollback did not restore exact previous state.' );
    // Fail the second installation after the first replacement succeeded.
    $partial = $changes; $partial[1]['path'] = $root . '/missing/bridge.php';
    $reject( static function() use ( $partial, $base, $root ) { trb_release_file_install( $partial, $base . '/private/two', $root ); } );
    if ( file_get_contents( $first ) !== 'Original store functions.' || glob( $root . '/*.trb-release-*' ) ) throw new RuntimeException( 'Partial install or staging residue survived recovery.' );
    $unsafe = $changes; $unsafe[1]['path'] = $root . '/../outside.php';
    $reject( static function() use ( $unsafe, $base, $root ) { trb_release_file_install( $unsafe, $base . '/private/three', $root ); } );
    if ( file_exists( $base . '/outside.php' ) ) throw new RuntimeException( 'Integration escaped its allowed root.' );
    echo "Store integration install, later release rollback, concurrent edit refusal, partial failure recovery, repeated rollback and path boundaries passed.\n";
} finally {
    $entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $entries as $entry ) $entry->isDir() && ! $entry->isLink() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
    rmdir( $base );
}
