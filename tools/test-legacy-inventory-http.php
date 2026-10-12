<?php
/** The inventory fixture must reject real HTTP requests even without Apache access rules. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$socket = stream_socket_server( 'tcp://127.0.0.1:0', $error, $message );
if ( ! $socket ) throw new RuntimeException( 'Local HTTP guard test unavailable.' );
$address = stream_socket_get_name( $socket, false );
fclose( $socket );
$process = proc_open( array( PHP_BINARY, '-S', $address, '-t', dirname( __DIR__ ) ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
if ( ! is_resource( $process ) ) throw new RuntimeException( 'Local HTTP guard server unavailable.' );
try {
    fclose( $pipes[0] );
    for ( $attempt = 0; $attempt < 40; ++$attempt ) {
        $ready = @stream_socket_client( 'tcp://' . $address, $error, $message, 0.1 );
        if ( $ready ) { fclose( $ready ); break; }
        usleep( 20000 );
    }
    if ( ! $ready ) throw new RuntimeException( 'Local HTTP guard server did not become ready.' );
    $before = glob( sys_get_temp_dir() . '/trb-inventory-bootstrap-*' );
    $context = stream_context_create( array( 'http' => array( 'ignore_errors' => true, 'timeout' => 3 ) ) );
    $body = file_get_contents( 'http://' . $address . '/tools/test-legacy-inventory-bootstrap.php', false, $context );
    if ( '' !== $body || ! preg_match( '/^HTTP\/\S+ 404(?: |$)/', $http_response_header[0] ?? '' ) ) throw new RuntimeException( 'HTTP reached the command-line inventory fixture.' );
    if ( $before !== glob( sys_get_temp_dir() . '/trb-inventory-bootstrap-*' ) ) throw new RuntimeException( 'HTTP inventory rejection created temporary fixture files.' );
    echo "Inventory regression fixture rejects HTTP with 404 and creates no sandbox without Apache rules.\n";
} finally {
    proc_terminate( $process );
    fclose( $pipes[1] ); fclose( $pipes[2] );
    proc_close( $process );
}
