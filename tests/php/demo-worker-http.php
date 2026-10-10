<?php
/** Loaded only behind the loopback/token gate of profile-mysql-http.php. */
if ( ! isset( $qaServing ) || ! $qaServing || PHP_SAPI !== 'cli-server' ) exit( 1 );
require dirname( __DIR__, 2 ) . '/inc/trb-demo-automation.php';
$qaWorkerMail = 0; $qaWorkerHttp = 0; $qaWorkerBytes = array();
add_filter( 'pre_wp_mail', static function() use ( &$qaWorkerMail ) {
    $qaWorkerMail++;
    if ( ( $_POST['qa_mail_mode'] ?? '' ) === 'throw' ) throw new RuntimeException( 'Synthetic interrupted transport' );
    return ( $_POST['qa_mail_mode'] ?? '' ) !== 'false';
}, PHP_INT_MAX );
add_filter( 'pre_http_request', static function( $pre, $args, $url ) use ( &$qaWorkerHttp, &$qaWorkerBytes ) {
    $qaWorkerHttp++;
    if ( strpos( $url, 'https://qa-webdav.example.invalid/' ) !== 0 ) return new WP_Error( 'qa_network_blocked' );
    $method = $args['method'] ?? 'GET'; $body = '';
    if ( $method === 'MKCOL' ) $code = 201;
    elseif ( $method === 'PUT' ) { $qaWorkerBytes[$url] = $args['body']; $code = 201; }
    elseif ( $method === 'GET' ) { $code = 200; $body = $qaWorkerBytes[$url] ?? ''; }
    elseif ( $method === 'DELETE' ) $code = (int) ( $_POST['qa_delete_code'] ?? 204 );
    else return new WP_Error( 'qa_unexpected_http' );
    return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => $code ), 'cookies' => array() );
}, PHP_INT_MAX, 3 );
$qaWorkerId = absint( $_POST['qa_request_id'] ?? 0 );
$qaWorkerActions = array( 'process' => 'trb_demo_process_request', 'send' => 'trb_demo_send_review', 'cleanup' => 'trb_demo_cleanup_request', 'recover' => 'trb_demo_recover_stalled_requests' );
$qaWorkerName = $_POST['qa_worker'] ?? '';
if ( ! isset( $qaWorkerActions[$qaWorkerName] ) ) exit( 1 );
$qaWorkerInterrupted = false;
try { $qaWorkerActions[$qaWorkerName]( $qaWorkerId ); }
catch ( RuntimeException $error ) { $qaWorkerInterrupted = true; }
wp_cache_delete( $qaWorkerId, 'post_meta' );
$qaWorkerPayload = get_post_meta( $qaWorkerId, '_trb_demo_payload', true );
header( 'Content-Type: application/json' );
echo wp_json_encode( array(
    'mail_calls' => $qaWorkerMail, 'http_calls' => $qaWorkerHttp, 'interrupted' => $qaWorkerInterrupted,
    'payload' => $qaWorkerPayload, 'remote' => get_post_meta( $qaWorkerId, '_trb_demo_remote', true ),
    'cleaned_at' => get_post_meta( $qaWorkerId, '_trb_demo_cleaned_at', true ),
    'error_code' => get_post_meta( $qaWorkerId, '_trb_demo_last_error_code', true ),
    'text_exists' => ! empty( $qaWorkerPayload['text_file'] ) && (bool) trb_demo_local_path( $qaWorkerPayload['text_file'] ),
    'send_scheduled' => wp_next_scheduled( 'trb_portal_send_demo_review', array( $qaWorkerId ) ),
    'process_scheduled' => wp_next_scheduled( 'trb_portal_process_demo', array( $qaWorkerId ) ),
    'cleanup_scheduled' => wp_next_scheduled( 'trb_portal_cleanup_demo', array( $qaWorkerId ) ),
) );
exit;
