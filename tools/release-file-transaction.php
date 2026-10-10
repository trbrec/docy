<?php
/** Durable, file-only recovery for a small explicitly selected integration. */
function trb_release_file_check_path( $path, $root ) {
    if ( ! str_starts_with( $path, $root . '/' ) || str_contains( $path, '..' ) || ! preg_match( '#^[a-zA-Z0-9_./ -]+$#D', substr( $path, strlen( $root ) + 1 ) ) ) throw new RuntimeException( 'Unsafe integration path.' );
    for ( $parent = $path; strlen( $parent ) >= strlen( $root ); $parent = dirname( $parent ) ) if ( is_link( $parent ) ) throw new RuntimeException( 'Integration symlink refused.' );
}
function trb_release_file_install( $changes, $backup, $root ) {
    if ( ! $changes ) return;
    if ( is_link( $backup ) || file_exists( $backup ) || ! mkdir( $backup, 0700, true ) ) throw new RuntimeException( 'Integration backup unavailable.' );
    $manifest = array();
    foreach ( $changes as $change ) {
        $path = $change['path']; trb_release_file_check_path( $path, $root );
        $original = is_file( $path ) ? file_get_contents( $path ) : null;
        if ( $original !== $change['original'] ) throw new RuntimeException( 'Concurrent integration edit.' );
        $id = hash( 'sha256', $path );
        if ( null !== $original ) {
            $previous = $backup . '/' . $id . '.before';
            if ( file_put_contents( $previous, $original, LOCK_EX ) !== strlen( $original ) || ! chmod( $previous, 0600 ) || ! hash_equals( hash( 'sha256', $original ), hash_file( 'sha256', $previous ) ) ) throw new RuntimeException( 'Integration backup verification failed.' );
        }
        $manifest[] = array( 'path' => $path, 'previous' => null === $original ? null : hash( 'sha256', $original ), 'next' => hash( 'sha256', $change['next'] ), 'mode' => null === $original ? 0644 : fileperms( $path ) & 0777, 'backup' => $id . '.before' );
    }
    $json = json_encode( $manifest, JSON_THROW_ON_ERROR );
    $manifest_path = $backup . '/manifest.json';
    if ( file_put_contents( $manifest_path, $json, LOCK_EX ) !== strlen( $json ) || ! chmod( $manifest_path, 0600 ) ) throw new RuntimeException( 'Integration recovery manifest unavailable.' );
    try {
        foreach ( $changes as $index => $change ) {
            $path = $change['path']; trb_release_file_check_path( $path, $root );
            if ( ( is_file( $path ) ? file_get_contents( $path ) : null ) !== $change['original'] ) throw new RuntimeException( 'Concurrent integration install.' );
            trb_release_file_replace( $path, $change['next'], $manifest[$index]['mode'] );
        }
    } catch ( Throwable $error ) { trb_release_file_rollback( $backup, $root ); throw $error; }
}
function trb_release_file_replace( $path, $bytes, $mode ) {
    $temporary = $path . '.trb-release-' . bin2hex( random_bytes( 8 ) );
    $handle = @fopen( $temporary, 'x' );
    if ( ! $handle ) throw new RuntimeException( 'Integration staging unavailable.' );
    try {
        if ( fwrite( $handle, $bytes ) !== strlen( $bytes ) || ! fflush( $handle ) || ! chmod( $temporary, $mode ) ) throw new RuntimeException( 'Integration staging failed.' );
        fclose( $handle ); $handle = null;
        if ( ! hash_equals( hash( 'sha256', $bytes ), hash_file( 'sha256', $temporary ) ) || ! rename( $temporary, $path ) || ! hash_equals( hash( 'sha256', $bytes ), hash_file( 'sha256', $path ) ) ) throw new RuntimeException( 'Integration replacement unconfirmed.' );
        if ( function_exists( 'opcache_invalidate' ) ) opcache_invalidate( $path, true );
    } finally { if ( is_resource( $handle ) ) fclose( $handle ); if ( is_file( $temporary ) ) unlink( $temporary ); }
}
function trb_release_file_rollback( $backup, $root ) {
    if ( ! file_exists( $backup ) ) return;
    if ( is_link( $backup ) || ! is_file( $backup . '/manifest.json' ) ) throw new RuntimeException( 'Integration recovery manifest missing.' );
    $manifest = json_decode( file_get_contents( $backup . '/manifest.json' ), true, 16, JSON_THROW_ON_ERROR );
    if ( ! is_array( $manifest ) || ! $manifest ) throw new RuntimeException( 'Integration recovery manifest invalid.' );
    // Refuse every concurrent edit before restoring any selected file.
    foreach ( $manifest as $item ) {
        trb_release_file_check_path( $item['path'], $root );
        if ( $item['backup'] !== hash( 'sha256', $item['path'] ) . '.before' ) throw new RuntimeException( 'Unsafe integration backup.' );
        $current = is_file( $item['path'] ) ? hash_file( 'sha256', $item['path'] ) : null;
        if ( $current !== $item['previous'] && $current !== $item['next'] ) throw new RuntimeException( 'Integration changed outside this release.' );
        if ( null !== $item['previous'] && ( is_link( $backup . '/' . $item['backup'] ) || ! is_file( $backup . '/' . $item['backup'] ) || ! hash_equals( $item['previous'], hash_file( 'sha256', $backup . '/' . $item['backup'] ) ) ) ) throw new RuntimeException( 'Integration original bytes changed.' );
    }
    foreach ( array_reverse( $manifest ) as $item ) {
        if ( null === $item['previous'] ) { if ( is_file( $item['path'] ) && ! unlink( $item['path'] ) ) throw new RuntimeException( 'Added integration cleanup failed.' ); }
        else trb_release_file_replace( $item['path'], file_get_contents( $backup . '/' . $item['backup'] ), $item['mode'] );
    }
}
