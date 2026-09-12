<?php
/** Durable receipt for confirmed submissions, independent of file acquisition. */
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/trb-release-integrity.php';

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
	// Lock the artist, not the token: two tabs can hold different tokens.
	$lock = trb_release_process_lock( 'intake-user:' . absint( $user_id ) );
	if ( ! $lock ) return new WP_Error( 'intake_busy', 'Registrazione della ricevuta in corso. Attendi e riprova con lo stesso modulo.' );
	try {
		$existing = trb_intake_find( $user_id, $token );
		if ( $existing ) {
			if ( trb_release_is_inactive( $existing ) ) return new WP_Error( 'release_cancelled', 'Questa pratica è stata annullata. Apri una nuova bozza.' );
			if ( 'complete' === get_post_meta( $existing, '_trb_release_intake_phase', true ) ) {
				$previous = array( 'trb_release_title' => get_post($existing)->post_title, 'trb_release_type' => get_post_meta($existing,'_trb_release_type',true), 'trb_tracks' => get_post_meta($existing,'_trb_release_tracks',true) );
				if ( trb_intake_project_identity($post) !== trb_intake_project_identity($previous) ) return new WP_Error( 'receipt_completed', 'Questa ricevuta appartiene a una pratica già inviata. Per un altro progetto apri una nuova bozza: i nuovi dati non sono stati inviati.', $existing );
			}
			return $existing;
		}
		if ( ! trb_intake_project_identity( $post ) ) return new WP_Error( 'invalid', 'Inserisci il titolo della release, la tipologia e il titolo di ciascun brano prima di inviare.' );
		$conflict = trb_intake_project_conflict( $user_id, $post );
		if ( $conflict ) return new WP_Error( 'existing_release', 'Esiste già la pratica #' . $conflict . ' con questo titolo e questi brani. Aprila da «Il tuo catalogo» e usa «Riprendi il caricamento di questa pratica» se incompleta. Nessuna nuova pratica è stata creata. Se intendi pubblicare una nuova edizione distinta, apri una segnalazione.', $conflict );
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
		if ( trb_portal_is_release_qa_account( get_userdata( $user_id ) ) ) $meta['_trb_release_qa_mode'] = '1';
		$id = wp_insert_post( array( 'post_type' => 'trb_release', 'post_status' => 'private', 'post_title' => $title ?: 'Release da completare', 'post_author' => absint( $user_id ), 'meta_input' => $meta ), true );
		if ( ! is_wp_error( $id ) && $id ) trb_intake_sync( $id );
		return $id ?: new WP_Error( 'intake_failed', 'Impossibile registrare la ricevuta.' );
	} finally {
		trb_release_process_unlock( $lock );
	}
}

/** Keep retries recoverable from the latest confirmed metadata. */
function trb_intake_refresh_draft($id, $post) {
 if (!in_array(get_post_meta($id,'_trb_release_intake_phase',true), array('awaiting_upload','validation_failed'),true)) return;
 $pairs=json_decode((string)($post['trb_release_payload_json']??''),true);
 if (is_array($pairs) && function_exists('trb_portal_normalize_release_draft_pairs')) update_post_meta($id,'_trb_release_intake_draft',trb_portal_normalize_release_draft_pairs($pairs));
 $title=sanitize_text_field($post['trb_release_title']??'');
 if ($title!=='') wp_update_post(array('ID'=>$id,'post_title'=>$title));
 foreach(array('type','state','date','original_date') as $field) update_post_meta($id,'_trb_release_'.$field,sanitize_text_field($post['trb_release_'.$field]??''));
 if (isset($post['trb_tracks']) && is_array($post['trb_tracks'])) update_post_meta($id,'_trb_release_tracks',trb_portal_sanitize_release_tracks(array_slice($post['trb_tracks'],0,24,true)));
}

/** Release only interrupted acquisition to manual recovery, never to approval. */
function trb_intake_recover_stalled($id) {
 if (trb_release_is_inactive($id)) return false;
 if (get_post_meta($id,'_trb_release_intake_phase',true)!=='acquiring_files') return false;
 $isrc=get_post_meta($id,'_trb_release_pipeline_status',true)==='isrc_assignment_failed';
 $started=(int)get_post_meta($id,'_trb_release_acquisition_started_at',true);
 if (!$isrc && (!$started || time()-$started<=30*MINUTE_IN_SECONDS)) return false;
 $lock=trb_release_process_lock('release:'.absint($id));
 if (!$lock) return false;
 try {
 $checkpoint=get_post_meta($id,'_trb_release_acquired_files',true);
 $files=get_post_meta($id,'_trb_release_files',true);
 $merged=array();
 foreach(array_merge(is_array($files)?$files:array(),is_array($checkpoint)?$checkpoint:array()) as $file) if(is_array($file)&&!empty($file['path'])) $merged[$file['kind'].':'.(string)$file['track']]=$file;
 if($merged) update_post_meta($id,'_trb_release_files',array_values($merged));
 update_post_meta($id,'_trb_release_intake_phase','files_partial');
 update_post_meta($id,'_trb_release_intake_error','Elaborazione interrotta. Dati e file conservati: la Direzione può riprendere la verifica dalla pratica esistente.');
 if (!$isrc) update_post_meta($id,'_trb_release_pipeline_status','upload_failed');
 trb_intake_sync($id);
 return true;
 } finally { trb_release_process_unlock($lock); }
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

/** Recover a historical failed submission as incomplete; never approve or dispatch it. */
function trb_intake_recover_draft() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accesso non consentito.' );
	$user_id = absint( $_POST['artist_id'] ?? 0 );
	check_admin_referer( 'trb_intake_recover_' . $user_id );
	$draft = get_user_meta( $user_id, '_trb_release_form_draft', true );
	$error = get_user_meta( $user_id, '_trb_release_last_submission_error', true );
	if ( ! is_array( $draft ) || empty( $draft['pairs'] ) || ! is_array( $error ) || empty( $error['at'] ) ) wp_die( 'Bozza e diagnosi di invio non disponibili.' );
	$pairs = trb_portal_normalize_release_draft_pairs( $draft['pairs'] );
	$original_post = $_POST;
	$_POST = array();
	foreach ( $pairs as $pair ) trb_portal_set_nested_post_value( $pair[0], $pair[1] );
	$payload = wp_unslash( $_POST );
	$_POST = $original_post;
	$payload['trb_release_payload_json'] = wp_json_encode( $pairs );
	$hash = hash( 'sha256', 'recovery:' . $user_id . ':' . absint( $error['at'] ) );
	$token = substr( $hash, 0, 8 ) . '-' . substr( $hash, 8, 4 ) . '-' . substr( $hash, 12, 4 ) . '-' . substr( $hash, 16, 4 ) . '-' . substr( $hash, 20, 12 );
	$existing = trb_intake_find( $user_id, $token );
	$id = trb_intake_record( $user_id, $token, $payload );
	if ( is_wp_error( $id ) ) wp_die( esc_html( $id->get_error_message() ) );
	if ( ! $existing ) {
		update_post_meta( $id, '_trb_release_intake_phase', 'validation_failed' );
		update_post_meta( $id, '_trb_release_intake_error', 'Recupero da bozza successivo a invio fallito. Dati e associazione dei file da verificare. ' . sanitize_text_field( $error['message'] ?? '' ) );
		update_post_meta( $id, '_trb_release_intake_error_code', sanitize_key( $error['code'] ?? 'historical_failure' ) );
		update_post_meta( $id, '_trb_release_historical_failure_at', absint( $error['at'] ) );
		trb_intake_sync( $id );
	}
	wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	exit;
}
add_action( 'admin_post_trb_intake_recover_draft', 'trb_intake_recover_draft' );

function trb_intake_recovery_notice() {
	if ( ! current_user_can( 'manage_options' ) || 'trb-owner-dashboard' !== ( $_GET['page'] ?? '' ) || 'artists' !== ( $_GET['view'] ?? '' ) ) return;
	$id = absint( $_GET['artist_id'] ?? 0 );
	if ( ! $id || ! get_user_meta( $id, '_trb_release_last_submission_error', true ) || ! get_user_meta( $id, '_trb_release_form_draft', true ) ) return;
	echo '<div class="notice notice-warning"><p>È presente un invio fallito con bozza salvata. Il recupero crea una pratica incompleta da verificare, senza avviare contratti o distribuzione.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="trb_intake_recover_draft"><input type="hidden" name="artist_id" value="' . $id . '">';
	wp_nonce_field( 'trb_intake_recover_' . $id );
	echo '<p><button class="button" type="submit">Recupera invio fallito come pratica incompleta</button></p></form></div>';
}
add_action( 'admin_notices', 'trb_intake_recovery_notice' );

/** Business status received from the CRM must not approve technical processing. */
function trb_intake_protect_pipeline( $check, $post_id, $key, $value ) {
	if ( '_trb_release_pipeline_status' !== $key ) return $check;
	if ( ! empty( $GLOBALS['trb_crm_sync_update_from_crm'] ) ) return true;
	$phase = (string) get_post_meta( $post_id, '_trb_release_intake_phase', true );
	if ( '' !== $phase && 'complete' !== $phase && in_array( $value, array( 'approved', 'analysis_in_progress', 'technical_analysis_running', 'archived_pending_analysis' ), true ) ) return true;
	return $check;
}
add_filter( 'update_post_metadata', 'trb_intake_protect_pipeline', 10, 4 );
add_filter( 'add_post_metadata', 'trb_intake_protect_pipeline', 10, 4 );

require_once __DIR__ . '/trb-release-recovery.php';

/** Completing intake ends the upload wait, without authorizing any contract. */
function trb_intake_complete_contract_status( $meta_id, $post_id, $key, $value ) {
	if ( '_trb_release_intake_phase' !== $key || 'complete' !== $value ) return;
	if ( 'waiting_upload' === get_post_meta( $post_id, '_trb_contract_state', true ) ) update_post_meta( $post_id, '_trb_contract_state', 'waiting_analysis' );
}
add_action( 'updated_post_meta', 'trb_intake_complete_contract_status', 10, 4 );
add_action( 'added_post_meta', 'trb_intake_complete_contract_status', 10, 4 );
/** Repair the same obsolete label when an administrator opens a completed receipt. */
add_action( 'load-post.php', static function() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$id = absint( $_GET['post'] ?? 0 );
	if ( $id && 'trb_release' === get_post_type( $id ) && 'complete' === get_post_meta( $id, '_trb_release_intake_phase', true ) ) {
		trb_intake_complete_contract_status( 0, $id, '_trb_release_intake_phase', 'complete' );
	}
} );
