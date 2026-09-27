<?php
/** Isolated production transport check: QA account, no request, analysis or email. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$revision = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || trim( (string) @file_get_contents( dirname( __DIR__ ) . '/.trb-deployed-sha' ) ) !== $revision ) exit( 2 );
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS'] = 'on';
define( 'DISABLE_WP_CRON', true );
require dirname( __DIR__, 4 ) . '/wp-load.php';
if ( rtrim( home_url(), '/' ) !== 'https://artist.trbrec.com' || ! function_exists( 'curl_init' ) ) throw new RuntimeException( 'QA prerequisites missing' );
add_filter( 'pre_wp_mail', static function () { throw new RuntimeException( 'QA must not send mail' ); }, PHP_INT_MAX );
add_filter( 'pre_http_request', static function () { throw new RuntimeException( 'QA must not call external services' ); }, PHP_INT_MAX );
$user = get_user_by( 'login', 'spotify4' );
if ( ! $user || ! trb_portal_is_demo_test_account( $user ) ) throw new RuntimeException( 'Dedicated QA account missing' );
$previous_user = get_current_user_id();
$session = wp_generate_uuid4();
$stored = array();
$scratch = array();
wp_set_current_user( $user->ID );
$token = WP_Session_Tokens::get_instance( $user->ID )->create( time() + 1800 );
$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user->ID, time() + 1800, 'logged_in', $token );
$nonce = wp_create_nonce( 'trb_portal_stage_release' );
$directory = trb_portal_release_staging_session_dir( $session, true );
if ( ! $directory ) throw new RuntimeException( 'Staging directory unavailable' );
try {
 $fixtures = array(
  array( 'field' => 'trb_demo_text', 'key' => 'f2000', 'name' => 'qa-demo.txt', 'type' => 'text/plain', 'body' => str_repeat( "Testo QA, senza dati artista.\n", 5000 ), 'limit' => 2 * MB_IN_BYTES, 'mimes' => array( 'txt' => 'text/plain' ) ),
  array( 'field' => 'trb_demo_audio', 'key' => 'f2001', 'name' => 'qa-demo.mp3', 'type' => 'audio/mpeg', 'body' => str_repeat( base64_decode( 'SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjYwLjE2LjEwMAAAAAAAAAAAAAAA//tQwAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAAFAAAE5ABVVVVVVVVVVVVVVVVVVVVVVVVVf39/f39/f39/f39/f39/f39/f3+qqqqqqqqqqqqqqqqqqqqqqqqqqtXV1dXV1dXV1dXV1dXV1dXV1dXV//////////////////////////8AAAAATGF2YzYwLjMxAAAAAAAAAAAAAAAAJAMGAAAAAAAABOSrzcSyAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAP/7UMQAA8AAAaQAAAAgAAA0gAAABExBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVMQU1FMy4xMDBVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVX/+1LEXYPAAAGkAAAAIAAANIAAAARVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVUxBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVf/7UsShg8AAAaQAAAAgAAA0gAAABFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVTEFNRTMuMTAwVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV//tSxKGDwAABpAAAACAAADSAAAAEVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVX/+1LEoYPAAAGkAAAAIAAANIAAAARVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVQ==' ), 20000 ), 'limit' => 25 * MB_IN_BYTES, 'mimes' => array( 'mp3' => 'audio/mpeg' ) ),
 );
 foreach ( $fixtures as $fixture ) {
  $body = $fixture['body'];
  if ( strlen( $body ) > $fixture['limit'] ) $body = substr( $body, 0, $fixture['limit'] );
  $size = strlen( $body );
  $chunk_size = 5 * MB_IN_BYTES;
  $total = (int) ceil( $size / $chunk_size );
  $upload_id = str_replace( '-', '', wp_generate_uuid4() );
  for ( $index = 0; $index < $total; $index++ ) {
   $part = tempnam( sys_get_temp_dir(), 'trb-demo-qa-' );
   if ( ! $part || file_put_contents( $part, substr( $body, $index * $chunk_size, $chunk_size ) ) === false ) throw new RuntimeException( 'QA chunk preparation failed' );
   $scratch[] = $part;
   $fields = array( 'action' => 'trb_portal_stage_release_chunk', 'trb_release_stage_nonce' => $nonce, 'session' => $session, 'file_key' => $fixture['key'], 'upload_id' => $upload_id, 'field_name' => $fixture['field'], 'file_name' => $fixture['name'], 'file_type' => $fixture['type'], 'file_size' => $size, 'last_modified' => 123, 'chunk_index' => $index, 'chunk_total' => $total, 'trb_release_chunk' => new CURLFile( $part, 'application/octet-stream', 'chunk.part' ) );
   $handle = curl_init( home_url( '/wp-admin/admin-post.php' ) );
   curl_setopt_array( $handle, array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields, CURLOPT_COOKIE => LOGGED_IN_COOKIE . '=' . $_COOKIE[ LOGGED_IN_COOKIE ], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true ) );
   $response = curl_exec( $handle );
   $status = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
   curl_close( $handle );
   $json = is_string( $response ) ? json_decode( $response, true ) : null;
   if ( $status !== 200 || ! is_array( $json ) || empty( $json['success'] ) || (int) ( $json['data']['next_chunk'] ?? -1 ) !== $index + 1 ) throw new RuntimeException( 'QA HTTP chunk failed at ' . $fixture['field'] . ':' . $index . ' status ' . $status );
   wp_delete_file( $part );
  }
  $_POST['trb_release_submission_token'] = $session;
  $_POST['trb_staged_uploads_json'] = wp_json_encode( array( $fixture['field'] => array( 'key' => $fixture['key'], 'session' => $session, 'upload_id' => $upload_id ) ) );
  $item = trb_portal_demo_upload_item( $fixture['field'] );
  if ( empty( $item['_trb_staged'] ) || $item['size'] !== $size || hash_file( 'sha256', $item['tmp_name'] ) !== hash( 'sha256', $body ) ) throw new RuntimeException( 'QA staged bytes differ' );
  $saved = trb_portal_store_demo_file( $fixture['field'], $fixture['mimes'], $fixture['limit'], $item );
  if ( ! is_array( $saved ) ) throw new RuntimeException( 'QA private sideload failed: ' . ( is_wp_error( $saved ) ? $saved->get_error_message() : 'unknown' ) );
  $path = trailingslashit( wp_upload_dir()['basedir'] ) . $saved['path'];
  $stored[] = $path;
  if ( ! is_file( $path ) || hash_file( 'sha256', $path ) !== hash( 'sha256', $body ) ) throw new RuntimeException( 'QA saved bytes differ' );
 }
 echo "PASS isolated demo HTTP staging (5 MiB chunks), TXT and 25 MiB MP3 private sideload\n";
} finally {
 foreach ( $scratch as $path ) if ( is_file( $path ) ) wp_delete_file( $path );
 foreach ( $stored as $path ) if ( is_file( $path ) ) wp_delete_file( $path );
 trb_portal_cleanup_release_staging_session( $session, $user->ID );
 WP_Session_Tokens::get_instance( $user->ID )->destroy( $token );
 wp_set_current_user( $previous_user );
}
