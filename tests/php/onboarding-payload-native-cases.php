<?php
/** Real WordPress JSON decoding, cookies and persisted session preservation. */
defined( 'ABSPATH' ) || exit;
if ( ! isset( $qa_database, $qa_root, $qa_checks ) || ! preg_match( '/^trb_wp_qa_[0-9]+$/D', $qa_database ) ) throw new RuntimeException( 'Owned native fixture required.' );
require_once dirname( __DIR__, 2 ) . '/inc/trb-candidate-onboarding.php';
$qa_onboarding_before = $qa_checks;
$qa_onboarding_nonce = str_repeat( 'b', 64 );
$qa_onboarding_cookie_before = $_COOKIE['__Host-trb_onboarding'] ?? null;
$_COOKIE['__Host-trb_onboarding'] = $qa_onboarding_nonce;
$qa_onboarding_key = trb_onboarding_browser_key();
$qa_onboarding_session = array( 'session' => str_repeat( 'a', 64 ), 'invite' => str_repeat( 'c', 64 ) );
set_transient( $qa_onboarding_key, $qa_onboarding_session, HOUR_IN_SECONDS );
$qa_onboarding_cron = _get_cron_array();
$qa_onboarding_http = 0;
$qa_onboarding_http_filter = static function( $result ) use ( &$qa_onboarding_http ) { $qa_onboarding_http++; return $result; };
add_filter( 'pre_http_request', $qa_onboarding_http_filter, PHP_INT_MAX - 1 );
$qa_onboarding_enabled_before = get_option( 'trb_candidate_onboarding_enabled', null );
update_option( 'trb_candidate_onboarding_enabled', true );
function qa_onboarding_request( $payload ) {
    $request = new WP_REST_Request( 'POST', '/trb/v1/onboarding/public' );
    $request->set_header( 'Content-Type', 'application/json' );
    $request->set_header( 'Origin', home_url() );
    $request->set_header( 'X-TRB-Onboarding-Csrf', trb_onboarding_csrf( str_repeat( 'b', 64 ) ) );
    $request->set_body( wp_json_encode( $payload ) );
    return $request;
}
try {
    foreach ( array(
        array( 'action' => 'details', 'billing' => array( 'country' => array( 'TN' ) ) ),
        array( 'action' => 'challenge', 'token' => array( 'invalid' ) ),
        array( 'action' => 'register', 'password' => array( 'invalid' ) ),
        array( 'action' => 'view', 'profile' => array( 'birth_place' => array( 'Roma' ) ) ),
        array( 'action' => 'upload', 'file' => array( 'name' => array( 'identity_front.pdf' ), 'mime' => 'application/pdf', 'size' => 10 ) ),
        array( 'action' => 'upload', 'file' => array( 'name' => 'identity_front.pdf', 'mime' => array( 'application/pdf' ), 'size' => 10 ) ),
        array( 'action' => 'upload', 'file' => array( 'name' => 'identity_front.pdf', 'mime' => 'application/pdf', 'size' => array( 10 ) ) ),
        array( 'action' => 'upload' ),
        null, 'not-an-object', array( 'view' ),
    ) as $qa_onboarding_payload ) {
        $qa_onboarding_result = trb_onboarding_public( qa_onboarding_request( $qa_onboarding_payload ) );
        qa_woo_check( is_wp_error( $qa_onboarding_result ) && 422 === $qa_onboarding_result->get_error_data()['status'], 'Malformed native WordPress JSON must return controlled 422.' );
        qa_woo_check( 0 === $qa_onboarding_http, 'Malformed native JSON must not start CRM, Drive or another provider request.' );
        qa_woo_check( $qa_onboarding_session === get_transient( $qa_onboarding_key ) && $qa_onboarding_cron === _get_cron_array(), 'Malformed native JSON must preserve the stored session and queued jobs.' );
    }
    foreach ( array(
        array( 'name' => 'identity_front.pdf', 'mime' => 'application/pdf', 'size' => 1 ),
        array( 'name' => 'tax_front.png', 'mime' => 'image/png', 'size' => 10485760 ),
        array( 'name' => 'identity_front.jpeg', 'mime' => 'image/jpeg', 'size' => 10 ),
    ) as $qa_onboarding_file ) {
        $qa_onboarding_payload = array( 'action' => 'upload', 'slot' => 'identity_front', 'file' => $qa_onboarding_file );
        qa_woo_check( $qa_onboarding_payload === trb_onboarding_public_payload( qa_onboarding_request( $qa_onboarding_payload ) ), 'Native JSON decoding must preserve valid upload metadata and integer sizes.' );
    }
    qa_woo_check( true === trb_onboarding_public_permission( qa_onboarding_request( array( 'action' => 'view' ) ) ), 'Native same-origin cookie and CSRF must remain valid.' );
    foreach ( array( array( 'nested-cookie' ), array(), 'not-a-nonce', 123 ) as $qa_onboarding_bad_cookie ) {
        $_COOKIE['__Host-trb_onboarding'] = $qa_onboarding_bad_cookie;
        qa_woo_check( '' === trb_onboarding_browser_key(), 'Malformed native cookies must not produce a browser session key.' );
        $qa_onboarding_result = trb_onboarding_public_permission( qa_onboarding_request( array( 'action' => 'view' ) ) );
        qa_woo_check( is_wp_error( $qa_onboarding_result ) && 403 === $qa_onboarding_result->get_error_data()['status'], 'Malformed native cookies must fail CSRF without a PHP warning.' );
    }
} finally {
    remove_filter( 'pre_http_request', $qa_onboarding_http_filter, PHP_INT_MAX - 1 );
    delete_transient( $qa_onboarding_key );
    if ( null === $qa_onboarding_enabled_before ) delete_option( 'trb_candidate_onboarding_enabled' );
    else update_option( 'trb_candidate_onboarding_enabled', $qa_onboarding_enabled_before );
    if ( null === $qa_onboarding_cookie_before ) unset( $_COOKIE['__Host-trb_onboarding'] );
    else $_COOKIE['__Host-trb_onboarding'] = $qa_onboarding_cookie_before;
}
$qa_onboarding_payload_checks = $qa_checks - $qa_onboarding_before;
