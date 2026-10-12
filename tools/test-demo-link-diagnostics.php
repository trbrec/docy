<?php
define( 'TRB_QA_DEMO_DIAGNOSTIC_LIBRARY_ONLY', true );
require __DIR__ . '/qa-demo-link-diagnostics.php';
function is_wp_error( $value ) { return $value instanceof RuntimeException; }
function wp_remote_retrieve_body( $value ) { return $value['body']; }
function wp_remote_retrieve_response_code( $value ) { return $value['status']; }
function wp_remote_retrieve_header( $value, $key ) { return $value[$key] ?? ''; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_remote_post( $url, $args ) { $GLOBALS['requests'][] = array( 'POST', $url, $args ); return $GLOBALS['response']; }
function wp_remote_get( $url, $args ) { $GLOBALS['requests'][] = array( 'GET', $url, $args ); return array( 'status' => 200, 'body' => '{"success":true,"protocol":"trb-demo-sheet-health-v1","sheet_available":true,"read_only":true}' ); }
$url = 'https://script.google.com/macros/s/fixture/exec';
$envelope = array( 'payload_base64' => base64_encode( '{"action":"health"}' ), 'signature' => str_repeat( '0', 64 ) );
$GLOBALS['requests'] = array();
$GLOBALS['response'] = array( 'status' => 302, 'body' => '', 'location' => 'https://script.googleusercontent.com/macros/echo?fixture=synthetic' );
foreach ( array( array_replace( $envelope, array( 'payload_base64' => base64_encode( '{"qa_action":"write"}' ) ) ), array_replace( $envelope, array( 'signature' => 'invalid' ) ), $envelope + array( 'extra' => true ) ) as $unsafe ) {
    try { trb_qa_demo_direct_health( $url, $unsafe ); throw new LogicException( 'A mutating or malformed envelope was transmitted.' ); } catch ( RuntimeException $expected ) {}
}
if ( $GLOBALS['requests'] ) throw new RuntimeException( 'Unsafe envelope reached the transport.' );
$receipt = trb_qa_demo_direct_health( $url, $envelope );
if ( ! $receipt['authenticated_read_only'] || count( $GLOBALS['requests'] ) !== 2 || $GLOBALS['requests'][1][2]['redirection'] !== 0 || isset( $GLOBALS['requests'][1][2]['body'] ) ) throw new RuntimeException( 'Signed envelope leaked on redirect.' );
foreach ( array( 'http://script.googleusercontent.com/echo', 'https://attacker.googleusercontent.com/echo', 'https://script.googleusercontent.com.attacker.invalid/echo', 'https://user:password@script.googleusercontent.com/echo', 'https://script.googleusercontent.com:8443/echo', '' ) as $location ) {
    $GLOBALS['response']['location'] = $location;
    try { trb_qa_demo_direct_health( $url, $envelope ); throw new LogicException( 'Unsafe response destination accepted.' ); } catch ( RuntimeException $expected ) {}
}
foreach ( array( null, false, array(), array( 'success' => true ), array( 'success' => true, 'protocol' => 'trb-demo-sheet-health-v1', 'sheet_available' => true, 'read_only' => 'true' ) ) as $body ) {
    $receipt = trb_qa_demo_health_receipt( array( 'status' => 200, 'body' => json_encode( $body ) ) );
    if ( $receipt['authenticated_read_only'] ) throw new RuntimeException( 'Incomplete health receipt accepted.' );
}
$receipt = trb_qa_demo_health_receipt( array( 'status' => 200, 'body' => '{"success":false,"error":"unauthorized","secret":"SYNTHETIC-DO-NOT-EXPORT"}' ) );
if ( ! $receipt['unauthorized'] || str_contains( json_encode( $receipt ), 'SYNTHETIC' ) ) throw new RuntimeException( 'Provider body or credential exported.' );
if ( trb_qa_demo_health_receipt( new RuntimeException() )['authenticated_read_only'] ) throw new RuntimeException( 'Transport error accepted.' );
if ( trb_qa_demo_health_receipt( array( 'status' => 500, 'body' => '{"success":true,"protocol":"trb-demo-sheet-health-v1","sheet_available":true,"read_only":true}' ) )['authenticated_read_only'] ) throw new RuntimeException( 'Server failure accepted.' );
echo "Read-only health receipts, exact redirect destination, credential-free diagnostics and malformed receipts verified.\n";
