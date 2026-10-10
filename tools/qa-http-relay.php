<?php
/** Ephemeral synthetic HTTP packets, carried only through the private SSH pipe. */
function trb_qa_http_relay_request( $url, $headers, $fields, $authenticated, $cookie, $work ) {
    $serialized = $fields;
    if ( is_array( $serialized ) ) foreach ( $serialized as &$value ) if ( $value instanceof CURLFile ) {
        $path = $value->getFilename();
        if ( is_link( $path ) || dirname( $path ) !== $work || ! in_array( basename( $path ), array( 'synthetic.png', 'synthetic.txt', 'forged.png' ), true ) || filesize( $path ) > 65536 ) throw new RuntimeException( 'Unexpected relay upload.' );
        $value = array( 'filename' => $value->getPostFilename(), 'mime' => $value->getMimeType(), 'base64' => base64_encode( file_get_contents( $path ) ) );
    }
    unset( $value );
    echo json_encode( array( 'relay' => 'isolated-http-v1', 'url' => $url, 'headers' => $headers, 'fields' => $serialized, 'authenticated' => $authenticated ), JSON_THROW_ON_ERROR ) . "\n";
    fflush( STDOUT );
    $line = fgets( STDIN, 8 * 1024 * 1024 );
    if ( ! is_string( $line ) ) throw new RuntimeException( 'HTTP relay disconnected.' );
    $response = json_decode( $line, true, 16, JSON_THROW_ON_ERROR );
    $body = base64_decode( $response['body_base64'] ?? '', true );
    if ( ! is_string( $body ) || ! is_int( $response['status'] ?? null ) || ! is_string( $response['headers'] ?? null ) ) throw new RuntimeException( 'HTTP relay response invalid.' );
    if ( $authenticated ) {
        $cookies = $response['cookies'] ?? '';
        if ( file_put_contents( $cookie, $cookies, LOCK_EX ) !== strlen( $cookies ) || ! chmod( $cookie, 0600 ) ) throw new RuntimeException( 'Synthetic session unavailable.' );
    }
    return array( $response['status'], $response['headers'], $body );
}
