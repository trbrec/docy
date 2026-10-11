<?php
/** Real DAV XML can consist entirely of namespaced children. */
require __DIR__ . '/qa-provider-cleanup.php';
function trb_demo_settings() { return array(); }
function trb_webdav_request( ...$args ) { $GLOBALS['last_request'] = $args; return $GLOBALS['response']; }
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
$multi = array( 'status' => 207, 'body' => '<D:multistatus xmlns:D="DAV:"><D:response><D:href>/private-provider-path</D:href><D:status>HTTP/1.1 403 Forbidden</D:status></D:response><D:response><D:status>HTTP/1.1 423 Locked</D:status></D:response></D:multistatus>' );
if ( trb_qa_provider_multistatus_codes( $multi ) !== array( 403, 423 ) ) throw new RuntimeException( 'Member failures must remain visible without exporting provider paths.' );
foreach ( array( '<broken', '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///unreadable">]><multistatus>&e;</multistatus>', str_repeat( 'x', 1048577 ) ) as $body ) {
    $multi['body'] = $body;
    if ( null !== trb_qa_provider_multistatus_codes( $multi ) ) throw new RuntimeException( 'Unsafe or malformed provider body accepted.' );
}
echo "Namespaced DAV listing and malformed XML refusal; actual resource absence, permission/transport failures and ambiguous statuses verified.\n";
