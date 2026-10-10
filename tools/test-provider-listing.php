<?php
/** Real DAV XML can consist entirely of namespaced children. */
require __DIR__ . '/qa-provider-cleanup.php';
function trb_demo_settings() { return array(); }
function trb_webdav_request( ...$args ) { return $GLOBALS['response']; }
function is_wp_error( $value ) { return false; }
function wp_remote_retrieve_response_code( $value ) { return $value['status']; }
function wp_remote_retrieve_body( $value ) { return $value['body']; }
$GLOBALS['response'] = array( 'status' => 207, 'body' => '<?xml version="1.0"?><D:multistatus xmlns:D="DAV:"><D:response><D:href>/Cartella%20prova/QA-AUDIT-0123456789abcdef/</D:href><D:propstat><D:prop><D:getlastmodified>Fri, 09 Oct 2026 12:00:00 GMT</D:getlastmodified></D:prop></D:propstat></D:response></D:multistatus>' );
$rows = trb_qa_provider_listing( '/Cartella prova' );
if ( count( $rows ) !== 1 || $rows[0]['path'] !== '/Cartella prova/QA-AUDIT-0123456789abcdef' || ! $rows[0]['modified'] ) throw new RuntimeException( 'Valid namespaced DAV response rejected.' );
$GLOBALS['response']['body'] = '<D:broken'; $rejected = false;
try { trb_qa_provider_listing( '/Cartella prova' ); } catch ( RuntimeException $error ) { $rejected = true; }
if ( ! $rejected ) throw new RuntimeException( 'Malformed DAV response accepted.' );
echo "Namespaced DAV listing, decoded paths, dates and malformed XML refusal verified.\n";
