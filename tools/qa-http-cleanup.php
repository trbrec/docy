<?php
/** Preserve hosting-generated logs before removing only our synthetic endpoint. */
function trb_qa_archive_http_error_log( $public_root, $archive_root, $directory ) {
    foreach ( array( $public_root, $archive_root, $directory ) as $path ) {
        if ( is_link( $path ) || realpath( $path ) !== $path ) throw new RuntimeException( 'Unsafe synthetic log storage.' );
    }
    if ( dirname( $directory ) !== $public_root || ! preg_match( '/^trb-audit-http-[a-f0-9]{24}$/D', basename( $directory ) ) ) throw new RuntimeException( 'Unexpected synthetic endpoint directory.' );
    $source = $directory . '/php_errorlog';
    if ( is_link( $source ) ) throw new RuntimeException( 'Synthetic error log changed to a link.' );
    if ( ! is_file( $source ) ) return false;
    $archive = $archive_root . '/portal-' . gmdate( 'YmdHis' ) . '-' . bin2hex( random_bytes( 4 ) ) . '.log';
    $hash = hash_file( 'sha256', $source );
    if ( file_exists( $archive ) || ! rename( $source, $archive ) || ! chmod( $archive, 0600 ) || ! touch( $archive ) || ! hash_equals( $hash, hash_file( 'sha256', $archive ) ) ) throw new RuntimeException( 'Synthetic error log archive unconfirmed.' );
    return true;
}
