<?php
/** Validate reviewed source in disposable storage, without keeping release backups. */
function trb_integration_syntax_preflight( array $changes ) {
    $directory = sys_get_temp_dir() . '/trb-integration-lint-' . bin2hex( random_bytes( 12 ) );
    if ( ! mkdir( $directory, 0700 ) ) throw new RuntimeException( 'Syntax workspace unavailable.' );
    $files = array();
    try {
        foreach ( $changes as $target => $source ) {
            $file = $directory . '/' . hash( 'sha256', $target ) . '.php';
            $files[] = $file;
            if ( file_put_contents( $file, $source, LOCK_EX ) !== strlen( $source ) ) throw new RuntimeException( 'Syntax staging failed.' );
            exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file ) . ' 2>&1', $output, $status );
            if ( 0 !== $status ) throw new RuntimeException( 'Integration syntax invalid.' );
        }
    } finally {
        foreach ( $files as $file ) if ( is_link( $file ) || is_file( $file ) && ! unlink( $file ) ) throw new RuntimeException( 'Syntax workspace cleanup failed.' );
        if ( ! rmdir( $directory ) ) throw new RuntimeException( 'Syntax workspace cleanup incomplete.' );
    }
}
