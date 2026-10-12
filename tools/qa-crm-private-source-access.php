<?php
/** Read-only private Git readiness; all results remain on the CRM hosting. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
$result = array( 'success' => false, 'read_only' => true, 'source_code_transferred' => false, 'credential_values_disclosed' => false );
try {
    if ( ! function_exists( 'proc_open' ) ) throw new RuntimeException();
    putenv( 'GIT_TERMINAL_PROMPT=0' ); putenv( 'GIT_ASKPASS=/bin/false' );
    $process = proc_open( array( 'git', 'ls-remote', '--exit-code', 'https://github.com/trbrec/trb-crm.git', 'HEAD' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, '/home/customer/www/crm.trbrec.com/private' );
    if ( ! is_resource( $process ) ) throw new RuntimeException();
    fclose( $pipes[0] ); stream_set_blocking( $pipes[1], false ); stream_set_blocking( $pipes[2], false );
    $output = ''; $deadline = microtime( true ) + 12; $exit = null;
    do {
        $output .= stream_get_contents( $pipes[1] ); stream_get_contents( $pipes[2] );
        $status = proc_get_status( $process );
        if ( ! $status['running'] ) { $exit = $status['exitcode']; break; }
        if ( microtime( true ) > $deadline || strlen( $output ) > 4096 ) { proc_terminate( $process ); break; }
        usleep( 10000 );
    } while ( true );
    $output .= stream_get_contents( $pipes[1] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $closed = proc_close( $process );
    $result['success'] = ( $exit ?? $closed ) === 0 && preg_match( '/^[a-f0-9]{40}\s+HEAD\s*$/D', $output ) === 1;
} catch ( Throwable $error ) {}
// Neither the source revision nor authentication diagnostics leave this process.
echo json_encode( $result, JSON_THROW_ON_ERROR ) . "\n";
exit( $result['success'] ? 0 : 1 );
