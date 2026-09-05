<?php
/** Durable receipt for confirmed submissions, independent of file acquisition. */
if ( ! defined( 'ABSPATH' ) ) exit;

function trb_intake_find( $user_id, $token ) {
	if ( ! preg_match( '/^[a-f0-9-]{36}$/i', $token ) ) return 0;
	$ids = get_posts( array( 'post_type' => 'trb_release', 'post_status' => array( 'publish', 'private', 'pending', 'draft' ), 'author' => absint( $user_id ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_trb_release_submission_token', 'meta_value' => $token ) );
	return $ids ? absint( $ids[0] ) : 0;
}

function trb_intake_sync( $release_id ) {
	// The companion keeps its existing signed transport and periodic reconciliation.
	if ( function_exists( 'trb_crm_complete_sync_send_release' ) ) {
		$args = array( absint( $release_id ), 0 );
		if ( ! wp_next_scheduled( 'trb_crm_complete_sync_retry_release', $args ) ) wp_schedule_single_event( time() + 1, 'trb_crm_complete_sync_retry_release', $args );
	}
}

function trb_intake_record( $user_id, $token, $post ) {
	if ( ! preg_match( '/^[a-f0-9-]{36}$/i', $token ) ) return new WP_Error( 'invalid_token', 'Identificativo invio non valido.' );
	$existing = trb_intake_find( $user_id, $token );
	if ( $existing ) return $existing;
	// An option has a database-unique key, unlike user/post metadata.
	$lock = 'trb_intake_' . hash( 'sha256', $user_id . ':' . $token );
	if ( ! add_option( $lock, time(), '', false ) ) return new WP_Error( 'intake_busy', 'Registrazione della ricevuta in corso. Attendi e riprova con lo stesso modulo.' );
	try {
		$existing = trb_intake_find( $user_id, $token );
		if ( $existing ) return $existing;
		$title = sanitize_text_field( $post['trb_release_title'] ?? '' );
		$raw_tracks = isset( $post['trb_tracks'] ) && is_array( $post['trb_tracks'] ) ? array_slice( $post['trb_tracks'], 0, 24, true ) : array();
		$tracks = trb_portal_sanitize_release_tracks( $raw_tracks );
		$meta = array(
			'_trb_release_submission_token' => $token,
			'_trb_release_intake_phase' => 'awaiting_upload',
			'_trb_release_pipeline_status' => 'upload_incomplete',
			'_trb_release_status' => 'attention',
			'_trb_contract_state' => 'waiting_upload',
			'_trb_release_received_at' => time(),
			'_trb_release_type' => sanitize_key( $post['trb_release_type'] ?? '' ),
			'_trb_release_state' => sanitize_key( $post['trb_release_state'] ?? '' ),
			'_trb_release_date' => sanitize_text_field( $post['trb_release_date'] ?? '' ),
			'_trb_release_original_date' => sanitize_text_field( $post['trb_release_original_date'] ?? '' ),
			'_trb_release_tracks' => $tracks,
			'_trb_release_expected_tracks' => count( $raw_tracks ),
			'_trb_release_intake_error' => 'Invio ricevuto; acquisizione e validazione dei file non ancora completate.',
		);
		$pairs = json_decode( (string) ( $post['trb_release_payload_json'] ?? '' ), true );
		if ( is_array( $pairs ) && function_exists( 'trb_portal_normalize_release_draft_pairs' ) ) $meta['_trb_release_intake_draft'] = trb_portal_normalize_release_draft_pairs( $pairs );
		if ( trb_portal_is_release_qa_account() ) $meta['_trb_release_qa_mode'] = '1';
		$id = wp_insert_post( array( 'post_type' => 'trb_release', 'post_status' => 'private', 'post_title' => $title ?: 'Release da completare', 'post_author' => absint( $user_id ), 'meta_input' => $meta ), true );
		if ( ! is_wp_error( $id ) && $id ) trb_intake_sync( $id );
		return $id ?: new WP_Error( 'intake_failed', 'Impossibile registrare la ricevuta.' );
	} finally {
		delete_option( $lock );
	}
}

function trb_intake_failure( $status, $message ) {
	if ( ! is_user_logged_in() ) return 0;
	$token = sanitize_text_field( wp_unslash( $_POST['trb_release_submission_token'] ?? '' ) );
	$id = trb_intake_find( get_current_user_id(), $token );
	if ( ! $id || ! in_array( get_post_meta( $id, '_trb_release_intake_phase', true ), array( 'awaiting_upload', 'validation_failed' ), true ) ) return $id;
	update_post_meta( $id, '_trb_release_intake_phase', 'validation_failed' );
	update_post_meta( $id, '_trb_release_intake_error', sanitize_text_field( $message ) );
	update_post_meta( $id, '_trb_release_intake_error_code', sanitize_key( $status ) );
	wp_update_post( array( 'ID' => $id ) );
	trb_intake_sync( $id );
	return $id;
}
