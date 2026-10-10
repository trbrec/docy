<?php
/** File-only rollback: no live database or uploaded artist material is restored. */
function trb_portal_release_files( $root ) {
    $root = realpath( $root );
    if ( false === $root || is_link( $root ) ) throw new RuntimeException( 'Release directory unavailable.' );
    $files = array();
    $entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
    foreach ( $entries as $entry ) {
        $relative = str_replace( '\\', '/', substr( $entry->getPathname(), strlen( $root ) + 1 ) );
        if ( preg_match( '#(^|/)(\.git|\.github)(/|$)#', $relative ) || str_starts_with( $relative, '.trb-' ) && '.trb-deployed-sha' !== $relative ) continue;
        if ( $entry->isLink() ) throw new RuntimeException( 'Release symlinks require review.' );
        if ( ! $entry->isFile() ) continue;
        $files[$relative] = array( 'sha256' => hash_file( 'sha256', $entry->getPathname() ), 'mode' => $entry->getPerms() & 0777 );
    }
    ksort( $files );
    return $files;
}

function trb_portal_release_snapshot( $theme, $candidate, $backup, $revision ) {
    if ( is_link( $backup ) || file_exists( $backup ) ) throw new RuntimeException( 'Release backup already exists.' );
    $previous = trb_portal_release_files( $theme ); $next = trb_portal_release_files( $candidate );
    if ( ! isset( $previous['functions.php'], $next['functions.php'] ) || ! mkdir( $backup, 0700, true ) ) throw new RuntimeException( 'Theme snapshot unavailable.' );
    foreach ( $previous as $path => $spec ) {
        $target = $backup . '/files/' . $path;
        if ( ! is_dir( dirname( $target ) ) && ! mkdir( dirname( $target ), 0700, true ) ) throw new RuntimeException( 'Snapshot directory failed.' );
        if ( ! copy( $theme . '/' . $path, $target ) || ! hash_equals( $spec['sha256'], hash_file( 'sha256', $target ) ) ) throw new RuntimeException( 'Snapshot readback mismatch.' );
    }
    $next['.trb-deployed-sha'] = array( 'sha256' => hash( 'sha256', $revision . "\n" ), 'mode' => 0644 );
    $manifest = array( 'revision' => $revision, 'previous' => $previous, 'next' => $next );
    $json = json_encode( $manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
    if ( file_put_contents( $backup . '/manifest.json', $json, LOCK_EX ) !== strlen( $json ) ) throw new RuntimeException( 'Snapshot manifest failed.' );
    return count( $previous );
}

function trb_portal_release_restore( $theme, $backup, $revision ) {
    $manifest = json_decode( file_get_contents( $backup . '/manifest.json' ), true, 64, JSON_THROW_ON_ERROR );
    if ( ( $manifest['revision'] ?? '' ) !== $revision ) throw new RuntimeException( 'Rollback revision mismatch.' );
    $previous = $manifest['previous']; $next = $manifest['next'];
    // Validate every path and byte before modifying any public file.
    foreach ( array_unique( array_merge( array_keys( $previous ), array_keys( $next ) ) ) as $path ) {
        if ( ! preg_match( '#^[a-zA-Z0-9_. /@+-]+$#D', $path ) || preg_match( '#(^|/)\.\.(/|$)#', $path ) ) throw new RuntimeException( 'Unsafe rollback path.' );
        $target = $theme . '/' . $path;
        for ( $parent = $target; strlen( $parent ) >= strlen( $theme ); $parent = dirname( $parent ) ) if ( is_link( $parent ) ) throw new RuntimeException( 'Unsafe rollback link.' );
        if ( isset( $previous[$path] ) && ( ! is_file( $backup . '/files/' . $path ) || ! hash_equals( $previous[$path]['sha256'], hash_file( 'sha256', $backup . '/files/' . $path ) ) ) ) throw new RuntimeException( 'Rollback backup changed.' );
        if ( is_file( $target ) ) {
            $current = hash_file( 'sha256', $target );
            if ( $current !== ( $previous[$path]['sha256'] ?? null ) && $current !== ( $next[$path]['sha256'] ?? null ) ) throw new RuntimeException( 'Public file changed outside this release.' );
        }
    }
    foreach ( $previous as $path => $spec ) {
        $target = $theme . '/' . $path; $temporary = $target . '.audit-restore';
        if ( ! is_dir( dirname( $target ) ) && ! mkdir( dirname( $target ), 0755, true ) ) throw new RuntimeException( 'Rollback directory failed.' );
        if ( ! copy( $backup . '/files/' . $path, $temporary ) || ! chmod( $temporary, $spec['mode'] ) || ! rename( $temporary, $target ) || ! hash_equals( $spec['sha256'], hash_file( 'sha256', $target ) ) ) throw new RuntimeException( 'Theme rollback failed.' );
        if ( function_exists( 'opcache_invalidate' ) ) opcache_invalidate( $target, true );
    }
    foreach ( array_diff_key( $next, $previous ) as $path => $spec ) if ( is_file( $theme . '/' . $path ) && ! unlink( $theme . '/' . $path ) ) throw new RuntimeException( 'Added release file cleanup failed.' );
    return count( $previous );
}
