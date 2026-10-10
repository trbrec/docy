<?php
/** Own a new synthetic fixture; existing files and symlinks must never be reused. */
function trb_qa_provider_local_fixture( $path, $bytes, &$ownership = null ) {
    $handle = @fopen( $path, 'xb' );
    if ( ! $handle ) throw new RuntimeException( 'Synthetic fixture collision.' );
    try {
        $identity = fstat( $handle );
        $ownership = array( 'path' => $path, 'device' => $identity['dev'] ?? null, 'inode' => $identity['ino'] ?? null, 'sha256' => hash( 'sha256', $bytes ) );
        if ( ! chmod( $path, 0600 ) || fwrite( $handle, $bytes ) !== strlen( $bytes ) || ! fflush( $handle ) ) throw new RuntimeException( 'Synthetic fixture write unconfirmed.' );
        if ( false === $identity || ! hash_equals( hash( 'sha256', $bytes ), hash_file( 'sha256', $path ) ) ) throw new RuntimeException( 'Synthetic fixture readback mismatch.' );
        return $ownership;
    } finally { fclose( $handle ); }
}

function trb_qa_provider_remove_local_fixture( $fixture ) {
    $path = $fixture['path']; clearstatcache( true, $path );
    if ( ! file_exists( $path ) && ! is_link( $path ) ) return true;
    $identity = lstat( $path );
    if ( is_link( $path ) || ! is_file( $path ) || false === $identity || $identity['dev'] !== $fixture['device'] || $identity['ino'] !== $fixture['inode'] || ! hash_equals( $fixture['sha256'], hash_file( 'sha256', $path ) ) ) return false;
    return unlink( $path );
}

/** Remove only identifiable, old QA folders containing our exact synthetic bytes. */
function trb_qa_provider_listing( $folder ) {
    $response = trb_webdav_request( trb_demo_settings(), 'PROPFIND', $folder . '/', '', array( 'Depth' => '1' ) );
    if ( is_wp_error( $response ) || 207 !== (int) wp_remote_retrieve_response_code( $response ) ) throw new RuntimeException( 'QA archive listing unavailable.', is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response ) );
    $body = wp_remote_retrieve_body( $response );
    if ( strlen( $body ) > 1048576 ) throw new RuntimeException( 'QA listing exceeds its bound.' );
    $previous = libxml_use_internal_errors( true );
    try { $xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET ); }
    finally { libxml_clear_errors(); libxml_use_internal_errors( $previous ); }
    // A valid DAV document with only namespaced children casts to false in PHP.
    if ( false === $xml ) throw new RuntimeException( 'QA listing invalid.' );
    $rows = array();
    foreach ( $xml->xpath( '//*[local-name()="response"]' ) as $entry ) {
        $href = $entry->xpath( './*[local-name()="href"]' );
        $date = $entry->xpath( './/*[local-name()="getlastmodified"]' );
        $path = rawurldecode( (string) parse_url( (string) ( $href[0] ?? '' ), PHP_URL_PATH ) );
        $rows[] = array( 'path' => rtrim( $path, '/' ), 'modified' => strtotime( (string) ( $date[0] ?? '' ) ) ?: 0 );
    }
    return $rows;
}

function trb_qa_provider_cleanup_stale( $bytes ) {
    $base = '/Upload files - TRB rec/Audio/Demo files'; $removed = 0;
    foreach ( trb_qa_provider_listing( $base ) as $entry ) {
        $name = basename( $entry['path'] );
        if ( ! preg_match( '/^QA-AUDIT-[a-f0-9]{16}$/D', $name ) || ! $entry['modified'] || $entry['modified'] > time() - 600 ) continue;
        $folder = $base . '/' . $name; $file = $folder . '/testo-sintetico.txt';
        foreach ( trb_qa_provider_listing( $folder ) as $child ) {
            $child_name = basename( $child['path'] );
            if ( ! in_array( $child_name, array( $name, 'testo-sintetico.txt' ), true ) ) throw new RuntimeException( 'QA folder contains unexpected material.' );
        }
        $get = trb_webdav_request( trb_demo_settings(), 'GET', $file );
        if ( is_wp_error( $get ) ) throw new RuntimeException( 'QA bytes could not be verified.' );
        $status = (int) wp_remote_retrieve_response_code( $get );
        if ( 200 === $status ) {
            if ( ! hash_equals( hash( 'sha256', $bytes ), hash( 'sha256', wp_remote_retrieve_body( $get ) ) ) ) throw new RuntimeException( 'QA material differs.' );
            trb_webdav_request( trb_demo_settings(), 'DELETE', $file );
        } elseif ( ! in_array( $status, array( 404, 410 ), true ) ) throw new RuntimeException( 'QA readback unavailable.' );
        trb_webdav_request( trb_demo_settings(), 'DELETE', $folder . '/' );
        $absent = trb_webdav_request( trb_demo_settings(), 'GET', $folder . '/', null, array( 'Cache-Control' => 'no-cache' ) );
        if ( is_wp_error( $absent ) || ! in_array( (int) wp_remote_retrieve_response_code( $absent ), array( 404, 410 ), true ) ) throw new RuntimeException( 'Stale QA archive cleanup unconfirmed.' );
        $removed++;
    }
    return $removed;
}
