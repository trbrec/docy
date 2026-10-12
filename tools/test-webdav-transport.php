<?php
require __DIR__ . '/../inc/trb-webdav.php';
class WP_Error { public function __construct( public $code ) {} }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function trb_resource_pcloud_guard( $bytes ) { $GLOBALS['guard_bytes'] = $bytes; return $GLOBALS['guard'] ?? true; }
function wp_remote_request( $url, $args ) { return array( 'url' => $url, 'args' => $args ); }
$settings = array( 'webdav_endpoint' => 'https://example.invalid/', 'pcloud_user' => 'qa', 'pcloud_pass' => 'synthetic' );
foreach ( array( '/Cartella prova/' => 'Cartella%20prova/', '/Cartella prova/testo.txt' => 'Cartella%20prova/testo.txt', '/' => '', '\\Cartella prova\\' => 'Cartella%20prova/' ) as $path => $expected ) {
    if ( trb_webdav_url( $settings['webdav_endpoint'], $path ) !== 'https://example.invalid/' . $expected ) throw new RuntimeException( 'Collection URL changed.' );
}
$response = trb_webdav_request( $settings, 'DELETE', '/Cartella prova/' );
if ( $response['url'] !== 'https://example.invalid/Cartella%20prova/' || $response['args']['redirection'] !== 0 ) throw new RuntimeException( 'DELETE collection slash or redirect policy lost.' );
$response = trb_webdav_request( $settings, 'PUT', '/file.txt', 'abc', array( 'If-None-Match' => '*' ) );
if ( $GLOBALS['guard_bytes'] !== 3 || $response['args']['body'] !== 'abc' || $response['args']['headers']['If-None-Match'] !== '*' ) throw new RuntimeException( 'Upload bounds or headers lost.' );
$GLOBALS['guard'] = new WP_Error( 'quota' );
if ( ! is_wp_error( trb_webdav_request( $settings, 'PUT', '/file.txt', 'abc' ) ) || ! is_wp_error( trb_webdav_request( array(), 'GET', '/file.txt' ) ) ) throw new RuntimeException( 'Transport guard bypassed.' );
echo "WebDAV collection slash, encoded filenames, redirects, upload bounds and transport errors verified.\n";
