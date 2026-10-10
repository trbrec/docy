<?php
define( 'TRB_PRIVATE_UPLOAD_LIBRARY_ONLY', true );
require __DIR__ . '/qa-private-upload.php';
$work = str_replace( '\\', '/', sys_get_temp_dir() ) . '/trb-private-transfer-test-' . bin2hex( random_bytes( 8 ) );
mkdir( $work, 0700 ); mkdir( $work . '/incoming', 0700 ); $process = null; $curl = null;
try {
    $crypto_options = array( 'private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA );
    $windows_config = dirname( PHP_BINARY ) . '/extras/ssl/openssl.cnf';
    if ( is_file( $windows_config ) ) $crypto_options['config'] = $windows_config;
    $receiver = openssl_pkey_new( $crypto_options ); $client = openssl_pkey_new( $crypto_options );
    if ( ! $receiver || ! $client || ! openssl_pkey_export( $receiver, $private, null, $crypto_options ) ) throw new RuntimeException( 'Test keys unavailable.' );
    file_put_contents( $work . '/recipient-private.pem', $private );
    file_put_contents( $work . '/signer-public.pem', openssl_pkey_get_details( $client )['key'] );
    file_put_contents( $work . '/filesystem.php', file_get_contents( __DIR__ . '/release-file-transaction.php' ) );
    file_put_contents( $work . '/library.php', '<?php define("TRB_PRIVATE_UPLOAD_LIBRARY_ONLY",true); require __DIR__."/filesystem.php"; ?>' . file_get_contents( __DIR__ . '/qa-private-upload.php' ) );
    $tag = str_repeat( 'a', 16 ); $token = str_repeat( 'b', 64 );
    $router = $work . '/router.php'; file_put_contents( $router, trb_private_upload_controller_source( $work, $work . '/incoming', $token, $tag, time() + 30 ) );
    $socket = stream_socket_server( 'tcp://127.0.0.1:0', $number, $error );
    if ( ! $socket ) throw new RuntimeException( 'Test port unavailable.' );
    $address = stream_socket_get_name( $socket, false ); fclose( $socket );
    $process = proc_open( array( PHP_BINARY, '-d', 'display_errors=0', '-d', 'disable_functions=mail', '-S', $address, '-t', $work, $router ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $work . '/http.log', 'a' ), 2 => array( 'file', $work . '/http.log', 'a' ) ), $pipes );
    if ( ! is_resource( $process ) ) throw new RuntimeException( 'Test HTTP process unavailable.' ); fclose( $pipes[0] );
    for ( $attempt = 0; $attempt < 50; $attempt++ ) { $ready = @stream_socket_client( 'tcp://' . $address, $number, $error, 0.1 ); if ( $ready ) { fclose( $ready ); break; } usleep( 100000 ); }
    $curl = curl_init( 'http://' . $address . '/' ); curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5 ) ); curl_exec( $curl );
    if ( curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ) !== 404 ) throw new RuntimeException( 'Private receiver accepted missing token.' );
    curl_setopt( $curl, CURLOPT_URL, 'http://' . $address . '/?token=' . $token );
    if ( ! str_contains( curl_exec( $curl ), 'Consegna privata' ) ) throw new RuntimeException( 'Private transfer form unavailable.' );
    $zip = new ZipArchive(); $zip->open( $work . '/fixture.zip', ZipArchive::CREATE | ZipArchive::EXCL ); $zip->addFromString( 'README.txt', 'Synthetic private transfer fixture. No production source.' ); $zip->close(); $zip = null;
    $metadata = json_encode( array( 'tag' => $tag, 'sha256' => hash_file( 'sha256', $work . '/fixture.zip' ), 'revision' => str_repeat( 'c', 40 ), 'bytes' => filesize( $work . '/fixture.zip' ) ), JSON_THROW_ON_ERROR );
    openssl_public_encrypt( $metadata, $sealed, openssl_pkey_get_details( $receiver )['key'], OPENSSL_PKCS1_OAEP_PADDING ); openssl_sign( $metadata, $signature, $client, OPENSSL_ALGO_SHA256 );
    $fields = array( 'metadata' => base64_encode( $sealed ), 'signature' => base64_encode( $signature ), 'archive' => new CURLFile( $work . '/fixture.zip', 'application/zip', 'candidate.zip' ) );
    $bad = $fields; $bad['signature'] = base64_encode( str_repeat( 'x', 384 ) ); curl_setopt_array( $curl, array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => $bad ) ); curl_exec( $curl );
    if ( curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ) !== 400 || is_file( $work . '/incoming/candidate.zip' ) ) throw new RuntimeException( 'Unverified sender archive accepted.' );
    file_put_contents( $work . '/tampered.zip', str_repeat( 'x', filesize( $work . '/fixture.zip' ) ) );
    $tampered = $fields; $tampered['archive'] = new CURLFile( $work . '/tampered.zip', 'application/zip', 'candidate.zip' );
    curl_setopt( $curl, CURLOPT_POSTFIELDS, $tampered ); curl_exec( $curl );
    if ( curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ) !== 400 || is_file( $work . '/incoming/candidate.zip' ) ) throw new RuntimeException( 'Changed archive bytes accepted.' );
    curl_setopt( $curl, CURLOPT_POSTFIELDS, $fields ); $answer = json_decode( curl_exec( $curl ), true );
    if ( ( $answer['completed'] ?? false ) !== true || ( $answer['installed'] ?? true ) !== false || file_get_contents( $work . '/incoming/candidate.zip' ) !== file_get_contents( $work . '/fixture.zip' ) ) throw new RuntimeException( 'Signed direct private upload did not preserve bytes.' );
    $answer = json_decode( curl_exec( $curl ), true );
    if ( ( $answer['duplicate'] ?? false ) !== true || count( glob( $work . '/incoming/candidate*' ) ) !== 1 ) throw new RuntimeException( 'Repeated upload was not idempotent.' );
    $wrong_revision = str_replace( str_repeat( 'c', 40 ), str_repeat( 'd', 40 ), $metadata );
    openssl_public_encrypt( $wrong_revision, $other_sealed, openssl_pkey_get_details( $receiver )['key'], OPENSSL_PKCS1_OAEP_PADDING ); openssl_sign( $wrong_revision, $other_signature, $client, OPENSSL_ALGO_SHA256 );
    $other = $fields; $other['metadata'] = base64_encode( $other_sealed ); $other['signature'] = base64_encode( $other_signature );
    curl_setopt( $curl, CURLOPT_POSTFIELDS, $other ); curl_exec( $curl );
    if ( curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ) !== 400 || json_decode( file_get_contents( $work . '/incoming/upload.json' ), true )['revision'] !== str_repeat( 'c', 40 ) ) throw new RuntimeException( 'Retry changed the original private revision receipt.' );
    curl_close( $curl ); $curl = null; unset( $fields, $bad, $tampered, $other );
    echo "Direct private multipart staging, encrypted manifest, signer and token rejection, byte readback and idempotent retry passed.\n";
} finally {
    $curl = null;
    if ( is_resource( $process ) ) { proc_terminate( $process ); proc_close( $process ); $process = null; }
    $entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $work, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $entries as $entry ) { if ( $entry->isDir() && ! $entry->isLink() ) rmdir( $entry->getPathname() ); else unlink( $entry->getPathname() ); }
    unset( $entry, $entries ); if ( ! rmdir( $work ) ) throw new RuntimeException( 'Private transfer test cleanup failed.' );
}
