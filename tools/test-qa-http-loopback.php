<?php
require __DIR__ . '/qa-http-loopback.php';
$work = str_replace( '\\', '/', sys_get_temp_dir() ) . '/trb-loopback-protocol-' . bin2hex( random_bytes( 8 ) );
mkdir( $work, 0700 ); $process = null;
try {
    $router = $work . '/router.php';
    file_put_contents( $router, '<?php if(($_SERVER["REMOTE_ADDR"]??"")!=="127.0.0.1"||($_SERVER["HTTP_X_TRB_QA_TOKEN"]??"")!=="synthetic"){http_response_code(404);exit;} echo isset($_FILES["fixture"])&&is_uploaded_file($_FILES["fixture"]["tmp_name"])?file_get_contents($_FILES["fixture"]["tmp_name"]):"Synthetic loopback reply";' );
    $port = trb_qa_loopback_port(); $process = trb_qa_loopback_start( $work, $work, $router, $port );
    $curl = curl_init( 'http://127.0.0.1:' . $port . '/native' );
    curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5 ) );
    curl_exec( $curl );
    if ( curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ) !== 404 ) throw new RuntimeException( 'Loopback token guard failed.' );
    $bytes = 'Synthetic real multipart payload'; file_put_contents( $work . '/fixture.txt', $bytes );
    curl_setopt_array( $curl, array( CURLOPT_HTTPHEADER => array( 'X-TRB-QA-Token: synthetic' ), CURLOPT_POST => true, CURLOPT_POSTFIELDS => array( 'fixture' => new CURLFile( $work . '/fixture.txt', 'text/plain', 'fixture.txt' ) ) ) );
    if ( curl_exec( $curl ) !== $bytes || curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ) !== 200 ) throw new RuntimeException( 'Loopback native multipart failed.' );
    curl_close( $curl );
    trb_qa_loopback_stop( $process );
    if ( is_resource( $process ) || @stream_socket_client( 'tcp://127.0.0.1:' . $port, $number, $error, 0.1 ) ) throw new RuntimeException( 'Loopback process cleanup unconfirmed.' );
    echo "Private loopback readiness, native multipart, token rejection and process termination passed.\n";
} finally {
    trb_qa_loopback_stop( $process );
    foreach ( glob( $work . '/*' ) as $path ) if ( is_file( $path ) ) unlink( $path );
    $removed = false;
    for ( $attempt = 0; $attempt < 20; $attempt++ ) { if ( @rmdir( $work ) ) { $removed = true; break; } usleep( 100000 ); }
    if ( ! $removed ) throw new RuntimeException( 'Loopback test directory cleanup unconfirmed.' );
}
