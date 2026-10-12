<?php
/** Real DAV XML can consist entirely of namespaced children. */
require __DIR__ . '/qa-provider-cleanup.php';
function trb_demo_settings() { return array(); }
function trb_webdav_request( ...$args ) { $GLOBALS['last_request'] = $args; return ! empty( $GLOBALS['response_queue'] ) ? array_shift( $GLOBALS['response_queue'] ) : $GLOBALS['response']; }
function is_wp_error( $value ) { return $value instanceof RuntimeException; }
function wp_remote_retrieve_response_code( $value ) { return $value['status']; }
function wp_remote_retrieve_body( $value ) { return $value['body']; }
$GLOBALS['response'] = array( 'status' => 207, 'body' => '<?xml version="1.0"?><D:multistatus xmlns:D="DAV:"><D:response><D:href>/Cartella%20prova/QA-AUDIT-0123456789abcdef/</D:href><D:propstat><D:prop><D:getlastmodified>Fri, 09 Oct 2026 12:00:00 GMT</D:getlastmodified></D:prop></D:propstat></D:response></D:multistatus>' );
$rows = trb_qa_provider_listing( '/Cartella prova' );
if ( count( $rows ) !== 1 || $rows[0]['path'] !== '/Cartella prova/QA-AUDIT-0123456789abcdef' || ! $rows[0]['modified'] ) throw new RuntimeException( 'Valid namespaced DAV response rejected.' );
$GLOBALS['response']['body'] = '<D:broken'; $rejected = false;
try { trb_qa_provider_listing( '/Cartella prova' ); } catch ( RuntimeException $error ) { $rejected = true; }
if ( ! $rejected ) throw new RuntimeException( 'Malformed DAV response accepted.' );

foreach ( array( 200, 204, 207, 301, 401, 403, 423, 500 ) as $code ) {
    $GLOBALS['response'] = array( 'status' => $code, 'body' => '' );
    if ( trb_qa_provider_resource_absent( '/Cartella prova/QA-AUDIT-0123456789abcdef/', $status ) || $status !== $code ) throw new RuntimeException( 'Existing, inaccessible or ambiguous resource treated as absent.' );
}
foreach ( array( 404, 410 ) as $code ) {
    $GLOBALS['response'] = array( 'status' => $code, 'body' => '' );
    if ( ! trb_qa_provider_resource_absent( '/Cartella prova/QA-AUDIT-0123456789abcdef/', $status ) || $status !== $code ) throw new RuntimeException( 'Native DAV absence not recognized.' );
}
$GLOBALS['response'] = new RuntimeException( 'Synthetic transport failure' );
if ( trb_qa_provider_resource_absent( '/Cartella prova/QA-AUDIT-0123456789abcdef/', $status ) || 0 !== $status ) throw new RuntimeException( 'Transport failure treated as absence.' );
if ( $GLOBALS['last_request'][1] !== 'PROPFIND' || $GLOBALS['last_request'][4] !== array( 'Depth' => '0', 'Cache-Control' => 'no-cache' ) ) throw new RuntimeException( 'Collection absence relies on an ordinary GET.' );
$pauses = array();
$pause = static function( $seconds ) use ( &$pauses ) { $pauses[] = $seconds; };
$GLOBALS['response_queue'] = array_map( static fn( $code ) => array( 'status' => $code, 'body' => '' ), array( 207, 207, 404 ) );
if ( ! trb_qa_provider_await_resource_absent( '/owned-test/', $status, $observations, $pause ) || $observations !== array( 207, 207, 404 ) || $pauses !== array( 2, 5 ) ) throw new RuntimeException( 'Delayed native absence must be independently observed.' );
$pauses = array(); $GLOBALS['response'] = array( 'status' => 207, 'body' => '' );
if ( trb_qa_provider_await_resource_absent( '/owned-test/', $status, $observations, $pause ) || $observations !== array_fill( 0, 6, 207 ) || $pauses !== array( 2, 5, 10, 20, 30 ) ) throw new RuntimeException( 'Persistent collection must fail after bounded read-only checks.' );
foreach ( array( 0, 200, 204, 301, 401, 403, 423, 500 ) as $code ) {
    $pauses = array(); $GLOBALS['response'] = array( 'status' => $code, 'body' => '' );
    if ( trb_qa_provider_await_resource_absent( '/owned-test/', $status, $observations, $pause ) || $observations !== array( $code ) || $pauses ) throw new RuntimeException( 'Ambiguous or denied absence must fail without retry.' );
}
$multi = array( 'status' => 207, 'body' => '<D:multistatus xmlns:D="DAV:"><D:response><D:href>/private-provider-path</D:href><D:status>HTTP/1.1 403 Forbidden</D:status></D:response><D:response><D:status>HTTP/1.1 423 Locked</D:status></D:response></D:multistatus>' );
if ( trb_qa_provider_multistatus_codes( $multi ) !== array( 403, 423 ) ) throw new RuntimeException( 'Member failures must remain visible without exporting provider paths.' );
foreach ( array( '<broken', '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///unreadable">]><multistatus>&e;</multistatus>', str_repeat( 'x', 1048577 ) ) as $body ) {
    $multi['body'] = $body;
    if ( null !== trb_qa_provider_multistatus_codes( $multi ) ) throw new RuntimeException( 'Unsafe or malformed provider body accepted.' );
}
$owned = '/Upload files - TRB rec/Audio/Demo files/QA-AUDIT-0123456789abcdef/';
$xml = static fn( $paths ) => '<D:multistatus xmlns:D="DAV:">' . implode( '', array_map( static fn( $path ) => '<D:response><D:href>' . htmlspecialchars( $path, ENT_XML1 ) . '</D:href></D:response>', $paths ) ) . '</D:multistatus>';
foreach ( array( '/real-artist/', $owned . '../', str_replace( '0123456789abcdef', 'unknown', $owned ) ) as $path ) {
    $GLOBALS['last_request'] = null;
    if ( trb_qa_provider_delete_empty_owned_collection( $path ) || null !== $GLOBALS['last_request'] ) throw new RuntimeException( 'Non-owned collection reached the provider.' );
}
foreach ( array( array(), array( '/other/' ), array( $owned, $owned . 'unexpected.pdf' ) ) as $paths ) {
    $GLOBALS['response'] = array( 'status' => 207, 'body' => $xml( $paths ) );
    if ( trb_qa_provider_delete_empty_owned_collection( $owned ) || $GLOBALS['last_request'][1] !== 'PROPFIND' ) throw new RuntimeException( 'Unconfirmed or nonempty collection was deleted.' );
}
$GLOBALS['response_queue'] = array( array( 'status' => 207, 'body' => $xml( array( $owned ) ) ), array( 'status' => 204, 'body' => '' ) );
if ( ! trb_qa_provider_delete_empty_owned_collection( $owned ) || $GLOBALS['last_request'][1] !== 'DELETE' ) throw new RuntimeException( 'Owned empty collection could not be reconciled.' );
$GLOBALS['response_queue'] = array( array( 'status' => 207, 'body' => '' ), array( 'status' => 207, 'body' => $xml( array( $owned ) ) ), array( 'status' => 204, 'body' => '' ), array( 'status' => 404, 'body' => '' ) );
$pauses = array();
if ( ! trb_qa_provider_await_resource_absent( $owned, $status, $observations, $pause, static fn() => trb_qa_provider_delete_empty_owned_collection( $owned ) ) || $observations !== array( 207, 404 ) ) throw new RuntimeException( 'A retry must still observe independent DAV absence.' );
echo "DAV absence, ambiguous failures and bounded reconciliation of only an owned empty collection verified; unrelated and nonempty collections preserved.\n";
