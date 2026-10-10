<?php
require __DIR__ . '/qa-http-local-browser.php';
$work = str_replace( '\\', '/', sys_get_temp_dir() ) . '/trb-browser-protocol-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $work, 0700 ) ) throw new RuntimeException( 'Protocol fixture unavailable.' );
try {
    $bridge = '<?php ' . trb_qa_browser_controller_source( $work, str_repeat( 'a', 64 ), 'https://artist.trbrec.com/trb-audit-http-' . str_repeat( 'a', 24 ) ) . 'http_response_code(404);';
    file_put_contents( $work . '/bridge.php', $bridge );
    exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $work . '/bridge.php' ) . ' 2>&1', $out, $status );
    if ( $status !== 0 ) throw new RuntimeException( 'Generated browser controller is not valid PHP.' );
    $recipient = file_get_contents( __DIR__ . '/qa-browser-recipient.pem' );
    if ( ! openssl_public_encrypt( 'Ephemeral fictional fixture only.', $sealed, $recipient, OPENSSL_PKCS1_OAEP_PADDING ) || strlen( $sealed ) !== 384 ) throw new RuntimeException( 'Synthetic bootstrap encryption failed.' );
    $worker = <<<'PHP'
<?php
$work=$argv[1];$deadline=time()+5;
while(!is_file($work.'/browser-request.json')&&time()<$deadline){clearstatcache();usleep(10000);}
$packet=json_decode(file_get_contents($work.'/browser-request.json'),true,16,JSON_THROW_ON_ERROR);
if($packet['fields']['city']!=='Tunisi'||$packet['sequence']!==1)exit(2);
$answer=['status'=>303,'headers'=>"Location: /saved\r\n",'body_base64'=>base64_encode('Synthetic reply'),'cookies'=>"# Netscape HTTP Cookie File\n"];
file_put_contents($work.'/browser-response-'.$packet['id'].'.json',json_encode($answer,JSON_THROW_ON_ERROR),LOCK_EX);
PHP;
    file_put_contents( $work . '/worker.php', $worker );
    $process = proc_open( array( PHP_BINARY, $work . '/worker.php', $work ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
    if ( ! is_resource( $process ) ) throw new RuntimeException( 'Protocol worker unavailable.' );
    fclose( $pipes[0] );
    $answer = trb_qa_browser_request( 'https://artist.trbrec.com/trb-audit-http-' . str_repeat( 'a', 24 ) . '/index.php', array(), array( 'city' => 'Tunisi' ), true, $work . '/cookies.txt', $work );
    $output = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
    if ( proc_close( $process ) !== 0 || $answer !== array( 303, "Location: /saved\r\n", 'Synthetic reply' ) || ! is_file( $work . '/cookies.txt' ) ) throw new RuntimeException( 'Browser request/cookie handoff failed.' );
    echo "Generated browser controller syntax, ephemeral bootstrap encryption and private request/response handoff passed.\n";
} finally { foreach ( glob( $work . '/*' ) as $path ) if ( is_file( $path ) ) unlink( $path ); rmdir( $work ); }
