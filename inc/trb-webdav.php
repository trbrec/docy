<?php
require_once __DIR__ . '/PcloudPathMap.php';
/** Shared WebDAV transport: preserve collection URLs and verify callers' errors. */
function trb_webdav_candidature_root() {
    $settings = function_exists('trb_demo_settings') ? trb_demo_settings() : array();
    $root = $settings['pcloud_root'] ?? '/Candidature';
    $config = __DIR__ . '/PcloudPathRoot.php';
    if (is_file($config)) { $override = require $config; if ($override !== null) $root = $override; }
    if (!is_string($root)) throw new RuntimeException('Radice pCloud non valida');
    return \TrbCrm\PcloudPathMap::root($root);
}
function trb_webdav_path($relative_path) {
    if (!is_string($relative_path)) throw new RuntimeException('Percorso pCloud non valido');
    // Preserve the existing internal URL helper's separator and relative-path API.
    $relative_path = str_replace('\\', '/', $relative_path);
    if (!str_starts_with($relative_path, '/')) $relative_path = '/' . $relative_path;
    return \TrbCrm\PcloudPathMap::resolve($relative_path, trb_webdav_candidature_root());
}
function trb_webdav_url( $endpoint, $relative_path ) {
    $relative_path = trb_webdav_path($relative_path);
    return rtrim($endpoint, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($relative_path, '/'))));
}

function trb_webdav_request( array $settings, $method, $relative_path, $body = null, $headers = array() ) {
    try { $relative_path = trb_webdav_path($relative_path); } catch (Throwable $error) { return new WP_Error('pcloud_invalid_path'); }
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
