<?php
/** Shared WebDAV transport: preserve collection URLs and verify callers' errors. */
function trb_webdav_url( $endpoint, $relative_path ) {
    $relative_path = str_replace( '\\', '/', $relative_path );
    $segments = array_filter( explode( '/', $relative_path ), 'strlen' );
    $url = rtrim( $endpoint, '/' ) . '/' . implode( '/', array_map( 'rawurlencode', $segments ) );
    return $segments && str_ends_with( $relative_path, '/' ) ? $url . '/' : $url;
}

function trb_webdav_request( array $settings, $method, $relative_path, $body = null, $headers = array() ) {
    if ( empty( $settings['webdav_endpoint'] ) || empty( $settings['pcloud_user'] ) || empty( $settings['pcloud_pass'] ) ) return new WP_Error( 'missing_webdav_settings' );
    if ( 'PUT' === strtoupper( $method ) && function_exists( 'trb_resource_pcloud_guard' ) ) {
        $guard = trb_resource_pcloud_guard( is_string( $body ) ? strlen( $body ) : 0 );
        if ( is_wp_error( $guard ) ) return $guard;
    }
    $headers['Authorization'] = 'Basic ' . base64_encode( $settings['pcloud_user'] . ':' . $settings['pcloud_pass'] );
    $args = array( 'method' => $method, 'headers' => $headers, 'timeout' => 90, 'redirection' => 0 );
    if ( null !== $body ) $args['body'] = $body;
    return wp_remote_request( trb_webdav_url( $settings['webdav_endpoint'], $relative_path ), $args );
}
