<?php
/** Private log rotation with a grace period for still-running PHP writers. */
if ( ! defined( 'ABSPATH' ) ) exit;
function trb_portal_maintain_error_log() {
    $root = '/home/customer/www/artist.trbrec.com/public_html';
    if ( rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) !== $root ) return array( 'skipped' => 'isolated_environment' );
    try { $result = array( 'completed' => true ) + trb_portal_rotate_error_log( $root ); }
    catch ( Throwable $error ) { $result = array( 'completed' => false, 'error' => 'maintenance_failed' ); }
    if ( function_exists( 'update_option' ) ) update_option( 'trb_portal_log_maintenance_state', array( 'checked_at' => time() ) + $result, false );
    return $result;
}
function trb_portal_rotate_error_log( $root, $threshold = 20971520 ) {
    $root = str_replace( '\\', '/', $root );
    if ( ! is_dir( $root ) || is_link( $root ) || str_replace( '\\', '/', (string) realpath( $root ) ) !== $root ) throw new RuntimeException( 'Unsafe log root.' );
    $directory = dirname( $root ) . '/private/error-log-archive';
    if ( ! is_dir( $directory ) && ! mkdir( $directory, 0700, true ) ) throw new RuntimeException( 'Private log archive unavailable.' );
    if ( is_link( $directory ) || str_replace( '\\', '/', (string) realpath( $directory ) ) !== $directory ) throw new RuntimeException( 'Unsafe log archive.' );
    $lock = fopen( $directory . '/rotation.lock', 'c' );
    if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) return array( 'skipped' => 'busy' );
    $result = array( 'rotated' => false, 'compressed' => 0, 'expired_archives_removed' => 0, 'bytes_reclaimed' => 0 );
    try {
        $path = $root . '/php_errorlog';
        clearstatcache( true, $path );
        if ( is_link( $path ) ) throw new RuntimeException( 'Unsafe log source.' );
        if ( is_file( $path ) && filesize( $path ) >= $threshold ) {
            $archive = $directory . '/portal-' . gmdate( 'YmdHis' ) . '-' . bin2hex( random_bytes( 4 ) ) . '.log';
            if ( ! rename( $path, $archive ) ) throw new RuntimeException( 'Log rotation failed.' );
            chmod( $archive, 0600 );
            $fresh = @fopen( $path, 'x' );
            if ( is_resource( $fresh ) ) { fclose( $fresh ); chmod( $path, 0600 ); }
            elseif ( ! is_file( $path ) ) { rename( $archive, $path ); throw new RuntimeException( 'New log unavailable.' ); }
            $result['rotated'] = true;
        }
        foreach ( glob( $directory . '/portal-*.log' ) as $archive ) {
            if ( is_link( $archive ) || ! preg_match( '/^portal-[0-9]{14}-[a-f0-9]{8}\.log$/D', basename( $archive ) ) ) continue;
            clearstatcache( true, $archive ); $before = stat( $archive );
            // Existing workers may retain the old file descriptor. Leave them 24h.
            if ( $before['mtime'] > time() - DAY_IN_SECONDS ) continue;
            $temporary = $archive . '.gz.next'; $input = fopen( $archive, 'rb' ); $output = gzopen( $temporary, 'wb6' );
            if ( ! $input || ! $output ) throw new RuntimeException( 'Log compression unavailable.' );
            $hash = hash_init( 'sha256' );
            try {
                while ( ! feof( $input ) ) {
                    $chunk = fread( $input, 1024 * 1024 );
                    if ( false === $chunk || gzwrite( $output, $chunk ) !== strlen( $chunk ) ) throw new RuntimeException( 'Log compression failed.' );
                    hash_update( $hash, $chunk );
                }
            } finally { fclose( $input ); gzclose( $output ); }
            clearstatcache( true, $archive ); $after = stat( $archive );
            if ( $before['size'] !== $after['size'] || $before['mtime'] !== $after['mtime'] ) { unlink( $temporary ); continue; }
            $readback = gzopen( $temporary, 'rb' ); $verified = hash_init( 'sha256' );
            while ( ! gzeof( $readback ) ) { $chunk = gzread( $readback, 1024 * 1024 ); if ( false === $chunk ) throw new RuntimeException( 'Log readback failed.' ); hash_update( $verified, $chunk ); }
            gzclose( $readback );
            if ( ! hash_equals( hash_final( $hash ), hash_final( $verified ) ) ) throw new RuntimeException( 'Compressed log integrity mismatch.' );
            clearstatcache( true, $archive ); $final = stat( $archive );
            if ( $final['size'] !== $after['size'] || $final['mtime'] !== $after['mtime'] ) { unlink( $temporary ); continue; }
            chmod( $temporary, 0600 );
            if ( ! rename( $temporary, $archive . '.gz' ) || ! unlink( $archive ) ) throw new RuntimeException( 'Log archive finalization failed.' );
            $result['compressed']++; $result['bytes_reclaimed'] += max( 0, $before['size'] - filesize( $archive . '.gz' ) );
        }
        foreach ( glob( $directory . '/portal-*.log.gz' ) as $archive ) {
            if ( ! is_link( $archive ) && preg_match( '/^portal-([0-9]{14})-[a-f0-9]{8}\.log\.gz$/D', basename( $archive ), $date ) && DateTimeImmutable::createFromFormat( '!YmdHis', $date[1], new DateTimeZone( 'UTC' ) )->getTimestamp() < time() - 60 * DAY_IN_SECONDS ) {
                $bytes = filesize( $archive );
                if ( ! unlink( $archive ) ) throw new RuntimeException( 'Expired log archive removal failed.' );
                $result['expired_archives_removed']++; $result['bytes_reclaimed'] += $bytes;
            }
        }
        return $result;
    } finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
}
function trb_portal_schedule_log_maintenance() {
    if ( ! wp_next_scheduled( 'trb_portal_daily_log_maintenance' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'trb_portal_daily_log_maintenance' );
}
add_action( 'init', 'trb_portal_schedule_log_maintenance' );
add_action( 'trb_portal_daily_log_maintenance', 'trb_portal_maintain_error_log' );
