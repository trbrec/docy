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

/** Collection GET may fail even when the collection exists; inspect its DAV resource. */
function trb_qa_provider_resource_absent( $path, &$status = null ) {
    $response = trb_webdav_request( trb_demo_settings(), 'PROPFIND', $path, '', array( 'Depth' => '0', 'Cache-Control' => 'no-cache' ) );
    $status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
    return in_array( $status, array( 404, 410 ), true );
}

/** A provider may acknowledge DELETE before its DAV collection disappears. */
function trb_qa_provider_await_resource_absent( $path, &$status = null, &$observations = null, $pause = null ) {
    $observations = array();
    foreach ( array( 0, 2, 5, 10, 20, 30 ) as $delay ) {
        if ( $delay ) { if ( null === $pause ) sleep( $delay ); else $pause( $delay ); }
        $absent = trb_qa_provider_resource_absent( $path, $status );
        $observations[] = $status;
        if ( $absent ) return true;
        // Retry only a resource that is still present. Authentication, transport
        // and other ambiguous failures must remain failures, never absence.
        if ( 207 !== $status ) return false;
    }
    return false;
}

/** Retain member status codes, never provider paths or response bodies, for ambiguous DELETEs. */
function trb_qa_provider_multistatus_codes( $response ) {
    if ( is_wp_error( $response ) || 207 !== (int) wp_remote_retrieve_response_code( $response ) ) return array();
    $body = wp_remote_retrieve_body( $response );
    if ( ! is_string( $body ) || strlen( $body ) > 1048576 || preg_match( '/<!DOCTYPE|<!ENTITY/i', $body ) ) return null;
    $previous = libxml_use_internal_errors( true );
    try { $xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET ); }
    finally { libxml_clear_errors(); libxml_use_internal_errors( $previous ); }
    if ( false === $xml || 'multistatus' !== $xml->getName() ) return null;
    $codes = array();
    foreach ( $xml->xpath( '//*[local-name()="status"]' ) as $entry ) {
        if ( ! preg_match( '~^HTTP/[0-9]+(?:\.[0-9]+)? ([1-5][0-9]{2})(?: |$)~D', trim( (string) $entry ), $match ) ) return null;
        $codes[] = (int) $match[1];
    }
    return array_values( array_unique( $codes ) );
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
        if ( ! trb_qa_provider_await_resource_absent( $folder . '/' ) ) throw new RuntimeException( 'Stale QA archive cleanup unconfirmed.' );
        $removed++;
    }
    return $removed;
}
