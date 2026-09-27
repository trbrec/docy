<?php
/** Owner-only end-to-end demo QA. No artist message is delivered. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$revision = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || trim( (string) @file_get_contents( dirname( __DIR__ ) . '/.trb-deployed-sha' ) ) !== $revision ) exit( 2 );
$status_file = dirname( __DIR__ ) . '/.trb-demo-flow-qa-' . $revision;
$GLOBALS['trb_demo_flow_step'] = 'bootstrap';
register_shutdown_function( static function () use ( $status_file ) {
 if ( 'done' !== $GLOBALS['trb_demo_flow_step'] ) file_put_contents( $status_file, $GLOBALS['trb_demo_flow_step'] );
} );
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS'] = 'on';
define( 'DISABLE_WP_CRON', true );
require dirname( __DIR__, 4 ) . '/wp-load.php';
if ( rtrim( home_url(), '/' ) !== 'https://artist.trbrec.com' || ! function_exists( 'curl_init' ) ) throw new RuntimeException( 'QA site mismatch' );
$source = get_post( 12314 );
$original = $source ? get_post_meta( $source->ID, '_trb_demo_payload', true ) : null;
$text_source = is_array( $original ) ? trb_demo_local_path( $original['text_file'] ?? array() ) : '';
$text_body = is_array( $original ) ? trb_demo_extract_text( $original['text_file'] ?? array() ) : '';
$user = $source ? get_user_by( 'id', $source->post_author ) : false;
if ( ! $source || 'trb_request' !== $source->post_type || ! is_array( $original ) || empty( $original['owner_qa'] ) || ! $text_source || ! $text_body || ! $user ) throw new RuntimeException( 'Owner QA source unavailable' );
$previous_user = get_current_user_id();
$session = wp_generate_uuid4();
$upload_id = str_replace( '-', '', wp_generate_uuid4() );
$title = '[QA FLOW] ' . substr( $session, 0, 8 );
$request_id = 0;
$saved_file = '';
$remote_folder = '';
$qa_uuid = '';
$mail_mode = 'fail';
$captured = array();
wp_set_current_user( $user->ID );
if ( ! current_user_can( 'manage_options' ) ) throw new RuntimeException( 'Owner QA privilege missing' );
$token = WP_Session_Tokens::get_instance( $user->ID )->create( time() + 1800 );
$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user->ID, time() + 1800, 'logged_in', $token );
add_filter( 'pre_wp_mail', static function ( $pre, $atts ) use ( &$mail_mode, &$captured ) {
 $to = is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : (string) $atts['to'];
 if ( 'andrea.tognassi@trbrec.com' !== strtolower( trim( $to ) ) ) throw new RuntimeException( 'QA recipient guard' );
 $captured[] = $atts;
 return 'pass' === $mail_mode;
}, PHP_INT_MAX, 2 );
try {
 $GLOBALS['trb_demo_flow_step'] = 'staging';
 $directory = trb_portal_release_staging_session_dir( $session, true );
 if ( ! $directory ) throw new RuntimeException( 'QA staging unavailable' );
 $part = trailingslashit( $directory ) . 'f2000.part';
 $size = strlen( $text_body );
 if ( ! $size || $size > 2 * MB_IN_BYTES || false === file_put_contents( $part, $text_body ) ) throw new RuntimeException( 'QA text fixture unavailable' );
 $meta = array( 'upload_id' => $upload_id, 'field_name' => 'trb_demo_text', 'name' => 'qa-flow.txt', 'type' => 'text/plain', 'size' => $size, 'last_modified' => 123, 'total' => 1, 'next_chunk' => 1, 'complete' => true );
 if ( false === file_put_contents( trailingslashit( $directory ) . 'f2000.json', wp_json_encode( $meta ) ) ) throw new RuntimeException( 'QA manifest unavailable' );
 $genre = in_array( $original['genre'] ?? '', trb_portal_genres(), true ) ? $original['genre'] : ( trb_portal_genres()[0] ?? '' );
 $fields = array(
  'action' => 'trb_portal_submit_demo', 'trb_demo_nonce' => wp_create_nonce( 'trb_portal_submit_demo' ),
  'trb_release_submission_token' => $session, 'trb_staged_uploads_json' => wp_json_encode( array( 'trb_demo_text' => array( 'key' => 'f2000', 'upload_id' => $upload_id, 'session' => $session ) ) ),
  'trb_demo_async' => '1', 'trb_demo_owner_qa' => '1', 'trb_demo_submission_kind' => 'new',
  'trb_demo_focus' => 'lyrics', 'trb_demo_origin_lyrics' => 'third_party',
  'trb_demo_title' => $title, 'trb_demo_genre' => $genre,
 );
 $GLOBALS['trb_demo_flow_step'] = 'submit';
 $handle = curl_init( home_url( '/wp-admin/admin-post.php' ) );
 curl_setopt_array( $handle, array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields, CURLOPT_COOKIE => LOGGED_IN_COOKIE . '=' . $_COOKIE[ LOGGED_IN_COOKIE ], CURLOPT_HTTPHEADER => array( 'X-TRB-Upload: 1', 'Accept: application/json' ), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true ) );
 $response = curl_exec( $handle );
 $status = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
 curl_close( $handle );
 $result = is_string( $response ) ? json_decode( $response, true ) : null;
 if ( 200 !== $status || ! is_array( $result ) || empty( $result['success'] ) || 'sent' !== ( $result['status'] ?? '' ) ) throw new RuntimeException( 'QA final submission failed' );
 $requests = get_posts( array( 'post_type' => 'trb_request', 'post_status' => 'private', 'author' => $user->ID, 'posts_per_page' => 2, 'title' => '[Demo] ' . $title ) );
 foreach ( $requests as $candidate ) if ( $candidate->post_title === '[Demo] ' . $title ) $request_id = $candidate->ID;
 if ( ! $request_id ) throw new RuntimeException( 'QA receipt missing' );
 $payload = get_post_meta( $request_id, '_trb_demo_payload', true );
 $qa_uuid = (string) ( $payload['uuid'] ?? '' );
 if ( ! is_array( $payload ) || empty( $payload['owner_qa'] ) || 'andrea.tognassi@trbrec.com' !== ( $payload['email'] ?? '' ) || 'queued' !== ( $payload['status'] ?? '' ) ) throw new RuntimeException( 'QA receipt invalid' );
 $saved_file = trb_demo_local_path( $payload['text_file'] ?? array() );
 if ( ! $saved_file || hash_file( 'sha256', $saved_file ) !== hash( 'sha256', $text_body ) ) throw new RuntimeException( 'QA final bytes mismatch' );
 wp_clear_scheduled_hook( 'trb_portal_process_demo', array( $request_id ) );
 $GLOBALS['trb_demo_flow_step'] = 'process';
 trb_demo_process_request( $request_id );
 $payload = get_post_meta( $request_id, '_trb_demo_payload', true );
 $remote = get_post_meta( $request_id, '_trb_demo_remote', true );
 $remote_folder = is_array( $remote ) ? ( $remote['folder'] ?? '' ) : '';
 if ( ! $remote_folder || empty( $remote['verification']['text_file']['sha256'] ) ) {
  $code = (string) get_post_meta( $request_id, '_trb_demo_last_error_code', true );
  $allowed = array( 'missing_webdav_settings', 'http_request_failed', 'webdav_mkdir_failed', 'webdav_upload_failed', 'webdav_local_hash_failed', 'webdav_verify_read_failed', 'webdav_verify_mismatch' );
  $GLOBALS['trb_demo_flow_step'] = 'archive-' . ( in_array( $code, $allowed, true ) ? $code : 'other' );
  throw new RuntimeException( 'QA archive failed' );
 }
 if ( empty( get_post_meta( $request_id, '_trb_demo_review', true ) ) || empty( get_post_meta( $request_id, '_trb_demo_openai_usage', true ) ) ) { $GLOBALS['trb_demo_flow_step'] = 'evaluation'; throw new RuntimeException( 'QA evaluation failed' ); }
 if ( 'ready' !== ( $payload['status'] ?? '' ) ) { $GLOBALS['trb_demo_flow_step'] = 'worker'; throw new RuntimeException( 'QA worker did not finish' ); }
 if ( ! get_post_meta( $request_id, '_trb_demo_sheet_synced', true ) ) { $GLOBALS['trb_demo_flow_step'] = 'sheet'; throw new RuntimeException( 'QA sheet did not sync' ); }
 $GLOBALS['trb_demo_flow_step'] = 'email';
 update_post_meta( $request_id, '_trb_demo_earliest_delivery', time() - 1 );
 wp_clear_scheduled_hook( 'trb_portal_send_demo_review', array( $request_id ) );
 trb_demo_send_review( $request_id );
 $payload = get_post_meta( $request_id, '_trb_demo_payload', true );
 if ( 'ready' !== ( $payload['status'] ?? '' ) || 1 !== (int) get_post_meta( $request_id, '_trb_demo_email_attempts', true ) ) throw new RuntimeException( 'QA mail retry failed' );
 $mail_mode = 'pass';
 wp_clear_scheduled_hook( 'trb_portal_send_demo_review', array( $request_id ) );
 trb_demo_send_review( $request_id );
 $payload = get_post_meta( $request_id, '_trb_demo_payload', true );
 $last_mail = end( $captured );
 if ( 'sent' !== ( $payload['status'] ?? '' ) || count( $captured ) !== 2 || ! str_contains( (string) ( $last_mail['subject'] ?? '' ), $title ) || ! str_contains( (string) ( $last_mail['message'] ?? '' ), 'Valutazione del provino' ) ) throw new RuntimeException( 'QA mail composition failed' );
 $GLOBALS['trb_demo_flow_step'] = 'done';
 echo "PASS final HTTP submission, pCloud verification, model evaluation, sheet, email retry/composition; owner QA only, no mail delivered\n";
} finally {
 if ( $request_id ) {
  foreach ( array( 'trb_portal_process_demo', 'trb_portal_send_demo_review', 'trb_portal_sync_demo_sheet', 'trb_portal_cleanup_demo' ) as $hook ) wp_clear_scheduled_hook( $hook, array( $request_id ) );
  if ( ! $remote_folder ) { $remote = get_post_meta( $request_id, '_trb_demo_remote', true ); $remote_folder = is_array( $remote ) ? ( $remote['folder'] ?? '' ) : ''; }
  wp_delete_post( $request_id, true );
 }
 if ( $saved_file && is_file( $saved_file ) ) wp_delete_file( $saved_file );
 trb_portal_cleanup_release_staging_session( $session, $user->ID );
 if ( ! $remote_folder && $qa_uuid && isset( $payload['title'] ) && str_starts_with( $payload['title'], '[QA FLOW]' ) ) {
  $folder_name = sanitize_file_name( trim( implode( ' ', array_filter( array( $payload['first_name'], $payload['last_name'], $payload['artist_name'], $payload['title'] ) ) ) ) );
  $remote_folder = '/Upload files - TRB rec/Audio/Demo files/' . $folder_name . ' - ' . sanitize_file_name( $qa_uuid );
 }
 if ( $remote_folder && $qa_uuid && str_contains( $remote_folder, $qa_uuid ) ) {
  foreach ( (array) ( $remote['files'] ?? array() ) as $remote_file ) trb_demo_webdav_request( 'DELETE', $remote_file );
  trb_demo_webdav_request( 'DELETE', $remote_folder . '/qa-flow.txt' );
  trb_demo_webdav_request( 'DELETE', $remote_folder );
 }
 WP_Session_Tokens::get_instance( $user->ID )->destroy( $token );
 wp_set_current_user( $previous_user );
}
