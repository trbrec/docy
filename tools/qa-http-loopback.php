<?php
/** Native PHP HTTP on loopback only, for unattended installed-plugin checks. */
function trb_qa_loopback_port() {
    $socket = stream_socket_server( 'tcp://127.0.0.1:0', $number, $error );
    if ( ! $socket ) throw new RuntimeException( 'Loopback port unavailable.' );
    $address = stream_socket_get_name( $socket, false ); fclose( $socket );
    $port = (int) substr( $address, strrpos( $address, ':' ) + 1 );
    if ( $port < 1024 || $port > 65535 ) throw new RuntimeException( 'Invalid loopback port.' );
    return $port;
}
function trb_qa_loopback_start( $work, $root, $router, $port ) {
    $command = array( PHP_BINARY, '-d', 'display_errors=0', '-d', 'error_log=' . $work . '/loopback-private.log', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=mail,curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client', '-S', '127.0.0.1:' . $port, '-t', $root, $router );
    $process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $work . '/loopback-server.log', 'a' ), 2 => array( 'file', $work . '/loopback-server.log', 'a' ) ), $pipes );
    if ( ! is_resource( $process ) ) throw new RuntimeException( 'Loopback HTTP process unavailable.' );
    fclose( $pipes[0] );
    try {
        for ( $attempt = 0; $attempt < 50; $attempt++ ) {
            if ( ! proc_get_status( $process )['running'] ) throw new RuntimeException( 'Loopback HTTP process stopped.' );
            $connection = @stream_socket_client( 'tcp://127.0.0.1:' . $port, $number, $error, 0.1 );
            if ( $connection ) { fclose( $connection ); return $process; }
            usleep( 100000 );
        }
        throw new RuntimeException( 'Loopback HTTP readiness unconfirmed.' );
    } catch ( Throwable $error ) { trb_qa_loopback_stop( $process ); throw $error; }
}
function trb_qa_loopback_stop( &$process ) {
    if ( is_resource( $process ) ) { proc_terminate( $process ); proc_close( $process ); }
    $process = null;
}
