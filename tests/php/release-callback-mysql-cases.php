<?php
/** Signature acknowledgements must describe durable native WordPress/MySQL state. */
defined( 'ABSPATH' ) || exit;
require_once dirname( __DIR__, 2 ) . '/inc/trb-release-spreadsheet-bridge.php';
require_once dirname( __DIR__, 2 ) . '/inc/trb-owner-dashboard.php';
$qaCallbackRelease = wp_insert_post( array( 'post_type' => 'trb_release', 'post_status' => 'publish', 'post_author' => $qaUser, 'post_title' => 'Synthetic callback storage failure' ) );
$qaCallbackPayload = array( 'release_id' => $qaCallbackRelease, 'status' => 'completed', 'dossier_id' => 'qa-native-dossier', 'signed_at' => '2026-10-11T12:00:00+00:00' );
update_post_meta( $qaCallbackRelease, '_trb_contract_state', 'contract_sent' );
update_post_meta( $qaCallbackRelease, '_trb_contract_error', 'Preserve until signature is durable' );
$qaCallbackBefore = get_post_meta( $qaCallbackRelease );
$qaCallbackPriorErrors = $wpdb->suppress_errors( true );
$qaCallbackOptions = get_option( TRB_RELEASE_BRIDGE_OPTION, false );
update_option( TRB_RELEASE_BRIDGE_OPTION, array( 'shared_secret' => str_repeat( 'c', 64 ), 'trb_webapp_url' => 'https://callback-qa.example.invalid/bridge' ) );
$qaCallbackSheetCalls = 0;
$qaCallbackSheetFails = true;
$qaCallbackTransport = static function( $pre, $args, $url ) use ( &$qaCallbackSheetCalls, &$qaCallbackSheetFails ) {
    if ( ! str_starts_with( $url, 'https://callback-qa.example.invalid/bridge?' ) ) return $pre;
    $qaCallbackSheetCalls++;
    return $qaCallbackSheetFails ? new WP_Error( 'qa_sheet_unavailable', 'Synthetic spreadsheet interruption' ) : array( 'headers' => array(), 'body' => '{"success":true}', 'response' => array( 'code' => 200 ) );
};
add_filter( 'pre_http_request', $qaCallbackTransport, PHP_INT_MAX, 3 );
$qaCallbackRead = static function() use ( $qaCallbackRelease ) { wp_cache_delete( $qaCallbackRelease, 'post_meta' ); return get_post_meta( $qaCallbackRelease ); };
try {
    foreach ( array( array( 'INSERT', 'NEW', '_trb_otp_dossier_id' ), array( 'INSERT', 'NEW', '_trb_contract_signed_at' ), array( 'DELETE', 'OLD', '_trb_contract_error' ), array( 'UPDATE', 'NEW', '_trb_contract_state' ) ) as [ $qaOperation, $qaRow, $qaKey ] ) {
        $wpdb->query( "CREATE TRIGGER qa_reject_signature BEFORE {$qaOperation} ON {$wpdb->postmeta} FOR EACH ROW BEGIN IF {$qaRow}.post_id={$qaCallbackRelease} AND {$qaRow}.meta_key='{$qaKey}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA signature write failure'; END IF; END" );
        try {
            $qaCallbackResult = trb_release_bridge_apply_callback( $qaCallbackPayload );
            qa_check( is_wp_error( $qaCallbackResult ) && $qaCallbackResult->get_error_data()['status'] === 503, 'A failed native signature write was acknowledged: ' . $qaKey );
            qa_check( $qaCallbackRead() === $qaCallbackBefore, 'A failed signature left partially changed dossier, state or errors: ' . $qaKey );
            qa_check( $qaCallbackSheetCalls === 0, 'A failed signature reached the spreadsheet.' );
            qa_check( ! wp_next_scheduled( 'trb_owner_dashboard_send_signed_summary', array( $qaCallbackRelease ) ), 'A failed signature retained a summary event.' );
        } finally { $wpdb->query( 'DROP TRIGGER qa_reject_signature' ); }
    }
    // Fail at COMMIT after the actual state hook has scheduled its native cron entry.
    $qaRejectCommit = static function( $query ) { return $query === 'COMMIT' ? "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA commit failure'" : $query; };
    add_filter( 'query', $qaRejectCommit );
    try { $qaCallbackResult = trb_release_bridge_apply_callback( $qaCallbackPayload ); }
    finally { remove_filter( 'query', $qaRejectCommit ); }
    qa_check( is_wp_error( $qaCallbackResult ) && $qaCallbackRead() === $qaCallbackBefore, 'A failed commit persisted or acknowledged a signature.' );
    qa_check( ! wp_next_scheduled( 'trb_owner_dashboard_send_signed_summary', array( $qaCallbackRelease ) ) && $qaCallbackSheetCalls === 0, 'Rollback left a cached summary event or contacted the spreadsheet.' );
    $qaCallbackLock = trb_release_process_lock( 'release:' . $qaCallbackRelease );
    try {
        $qaCallbackResult = trb_release_bridge_apply_callback( $qaCallbackPayload );
        qa_check( is_wp_error( $qaCallbackResult ) && $qaCallbackResult->get_error_code() === 'callback_busy', 'A concurrent signature ignored the dispatch lock.' );
        qa_check( $qaCallbackRead() === $qaCallbackBefore, 'A blocked callback modified the practice.' );
    } finally { trb_release_process_unlock( $qaCallbackLock ); }
    // A previous read may have cached active state before cancellation in another request.
    get_post_meta( $qaCallbackRelease );
    $wpdb->insert( $wpdb->postmeta, array( 'post_id' => $qaCallbackRelease, 'meta_key' => '_trb_owner_cancelled_at', 'meta_value' => 'qa-cancelled' ) );
    $qaCallbackResult = trb_release_bridge_apply_callback( $qaCallbackPayload );
    qa_check( is_wp_error( $qaCallbackResult ) && $qaCallbackResult->get_error_code() === 'release_cancelled', 'A stale cached callback revived a cancelled practice.' );
    delete_post_meta( $qaCallbackRelease, '_trb_owner_cancelled_at' );
    foreach ( array( 'release_id', 'status', 'dossier_id', 'signed_at' ) as $qaBadField ) {
        $qaBadPayload = $qaCallbackPayload; $qaBadPayload[$qaBadField] = array( 'nested' );
        qa_check( is_wp_error( trb_release_bridge_apply_callback( $qaBadPayload ) ), 'A nested signature field was accepted: ' . $qaBadField );
    }
    $qaSent = trb_release_bridge_apply_callback( array_replace( $qaCallbackPayload, array( 'status' => 'contract_sent' ) ) );
    qa_check( $qaSent['state'] === 'contract_sent' && get_post_meta( $qaCallbackRelease, '_trb_otp_dossier_id', true ) === $qaCallbackPayload['dossier_id'], 'Native sent callback lost the dossier.' );
    $qaBeforeMismatch = $qaCallbackRead();
    $qaMismatch = trb_release_bridge_apply_callback( array_replace( $qaCallbackPayload, array( 'dossier_id' => 'wrong-dossier' ) ) );
    qa_check( is_wp_error( $qaMismatch ) && $qaMismatch->get_error_code() === 'dossier_mismatch' && $qaCallbackRead() === $qaBeforeMismatch, 'A different dossier changed the contract.' );
    $qaSigned = trb_release_bridge_apply_callback( $qaCallbackPayload );
    qa_check( $qaSigned['state'] === 'signed' && ! $qaSigned['idempotent'] && ! $qaSigned['spreadsheet_synced'], 'Spreadsheet interruption erased or concealed the durable first signature.' );
    qa_check( get_post_meta( $qaCallbackRelease, '_trb_contract_signed_at', true ) === $qaCallbackPayload['signed_at'] && $qaCallbackSheetCalls === 1, 'The first signature did not persist its timestamp or reach the intercepted sheet.' );
    qa_check( (bool) wp_next_scheduled( 'trb_owner_dashboard_send_signed_summary', array( $qaCallbackRelease ) ), 'A committed signature lost its owner summary event.' );
    $qaCallbackSheetFails = false;
    $qaRepeated = trb_release_bridge_apply_callback( array_replace( $qaCallbackPayload, array( 'signed_at' => '2030-01-01T00:00:00+00:00' ) ) );
    qa_check( $qaRepeated['idempotent'] && $qaRepeated['spreadsheet_synced'] && $qaCallbackSheetCalls === 2, 'The identical signature could not retry spreadsheet synchronization.' );
    qa_check( get_post_meta( $qaCallbackRelease, '_trb_contract_signed_at', true ) === $qaCallbackPayload['signed_at'], 'A repeated signature replaced the original timestamp.' );
    $qaLate = trb_release_bridge_apply_callback( array_replace( $qaCallbackPayload, array( 'status' => 'contract_sent' ) ) );
    qa_check( $qaLate['state'] === 'signed' && $qaCallbackSheetCalls === 2, 'A late sent callback downgraded a signature or repeated synchronization.' );
    $qaPublicPayload = array_replace( $qaCallbackPayload, array( 'action' => 'trb_release_contract_callback', 'secret[0]' => 'nested' ) );
    qa_check( qa_http( $qaPublicPayload, true )['status'] === 403, 'The public callback accepted a nested secret.' );
    unset( $qaPublicPayload['secret[0]'], $qaPublicPayload['dossier_id'] );
    $qaPublicPayload['secret'] = str_repeat( 'c', 64 ); $qaPublicPayload['dossier_id[0]'] = 'nested';
    qa_check( qa_http( $qaPublicPayload, true )['status'] === 400, 'An authenticated HTTP callback accepted a nested dossier.' );
    $qaPublicValid = qa_http( array_replace( $qaCallbackPayload, array( 'action' => 'trb_release_contract_callback', 'secret' => str_repeat( 'c', 64 ), 'status' => 'contract_sent' ) ), true );
    qa_check( $qaPublicValid['status'] === 200 && $qaPublicValid['data']['success'] && $qaPublicValid['data']['data']['state'] === 'signed', 'A valid public HTTP callback failed or downgraded the signature.' );
    trb_release_bridge_rest_routes();
    $qaRest = new WP_REST_Request( 'POST', '/trb/v1/release-contract-callback' );
    $qaRest->set_body_params( array_replace( $qaCallbackPayload, array( 'secret' => array( 'nested' ) ) ) );
    qa_check( rest_do_request( $qaRest )->get_status() === 403, 'The REST callback accepted a nested secret.' );
    $qaRest->set_body_params( array_replace( $qaCallbackPayload, array( 'secret' => str_repeat( 'c', 64 ), 'signed_at' => array( 'nested' ) ) ) );
    qa_check( rest_do_request( $qaRest )->get_status() === 400, 'An authenticated REST callback accepted a nested timestamp.' );
    $qaRest->set_body_params( array_replace( $qaCallbackPayload, array( 'secret' => str_repeat( 'c', 64 ), 'status' => 'contract_sent' ) ) );
    $qaRestValid = rest_do_request( $qaRest );
    qa_check( $qaRestValid->get_status() === 200 && $qaRestValid->get_data()['state'] === 'signed', 'A valid REST callback failed or downgraded the signature.' );
    echo "Native signature callback rollback, failed commit, cancellation, concurrency, retry and HTTP/REST shape assertions passed.\n";
} finally {
    $wpdb->query( 'DROP TRIGGER IF EXISTS qa_reject_signature' );
    $wpdb->suppress_errors( $qaCallbackPriorErrors );
    remove_filter( 'pre_http_request', $qaCallbackTransport, PHP_INT_MAX );
    wp_clear_scheduled_hook( 'trb_owner_dashboard_send_signed_summary', array( $qaCallbackRelease ) );
    if ( false === $qaCallbackOptions ) delete_option( TRB_RELEASE_BRIDGE_OPTION ); else update_option( TRB_RELEASE_BRIDGE_OPTION, $qaCallbackOptions );
    wp_delete_post( $qaCallbackRelease, true );
}
