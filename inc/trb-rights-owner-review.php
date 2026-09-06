<?php
/** Explicit CRM review of individual tracks, using the existing signed connector. */
function trb_rights_review_snapshot( $id ) {
	$decision = (array) get_post_meta( $id, '_trb_release_analysis_decision', true );
	$files = (array) get_post_meta( $id, '_trb_release_files', true );
	$version = hash( 'sha256', wp_json_encode( array( $decision, array_column( $files, 'sha256' ) ) ) );
	$tracks = array();
	foreach ( (array) get_post_meta( $id, '_trb_release_tracks', true ) as $index => $track ) {
		$findings = array_values( array_filter( (array) ( $decision['copyright_findings'] ?? array() ), static function( $finding ) use ( $index ) { return strpos( $finding, 'Brano ' . ( (int) $index + 1 ) . ':' ) === 0; } ) );
		$tracks[] = array( 'index' => (int) $index, 'title' => sanitize_text_field( $track['title'] ?? '' ), 'findings' => $findings, 'matches' => (array) ( $decision['results'][$index]['matches'] ?? array() ), 'deepright' => ! empty( $decision['results'][$index]['deepright'] ) );
	}
	$review = (array) get_post_meta( $id, '_trb_release_owner_rights_review', true );
	return array( 'ok' => true, 'version' => $version, 'tracks' => $tracks, 'state' => $decision['state'] ?? 'pending', 'review' => array_intersect_key( $review, array_flip( array( 'selected_tracks', 'reviewed_at', 'status', 'version' ) ) ) );
}

function trb_rights_selected_findings( $snapshot, $selected ) {
	if ( ! is_array( $selected ) || ! $selected ) throw new RuntimeException( 'Seleziona almeno un brano da approfondire.' );
	$findings = array(); $indexes = array();
	foreach ( $selected as $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) throw new RuntimeException( 'Indice brano non valido.' );
		$index = (int) $value; $found = false;
		foreach ( $snapshot['tracks'] as $track ) if ( $track['index'] === $index && $track['findings'] ) { $findings = array_merge( $findings, $track['findings'] ); $found = true; break; }
		if ( ! $found ) throw new RuntimeException( 'Brano senza rilievi correnti: aggiorna la scheda.' );
		$indexes[] = $index;
	}
	$indexes = array_values( array_unique( $indexes ) ); sort( $indexes );
	return array( 'indexes' => $indexes, 'findings' => array_values( array_unique( $findings ) ) );
}

function trb_rights_review_receive( $result, $server, $request ) {
	if ( $result !== null || $request->get_method() !== 'POST' || ! preg_match( '#^/trb-crm/v1/release/(\d+)/?$#', $request->get_route(), $m ) ) return $result;
	$payload = $request->get_json_params(); $operation = $payload['operation'] ?? '';
	if ( ! in_array( $operation, array( 'copyright_review_snapshot', 'copyright_review_send' ), true ) ) return $result;
	if ( ! function_exists( 'trb_crm_sync_verify_release_request' ) ) return new WP_Error( 'review_unavailable', 'Connettore firmato non disponibile.', array( 'status' => 503 ) );
	$auth = trb_crm_sync_verify_release_request( $request ); if ( is_wp_error( $auth ) ) return $auth;
	$id = (int) $m[1];
	if ( get_post_type( $id ) !== 'trb_release' || get_post_status( $id ) === 'trash' ) return new WP_Error( 'release_missing', 'Release non disponibile.', array( 'status' => 404 ) );
	$snapshot = trb_rights_review_snapshot( $id );
	if ( $operation === 'copyright_review_snapshot' ) return rest_ensure_response( $snapshot );
	if ( ! hash_equals( $snapshot['version'], (string) ( $payload['version'] ?? '' ) ) ) return new WP_Error( 'review_stale', 'Analisi o file cambiati: riapri la scheda prima di inviare.', array( 'status' => 409 ) );
	try { $selected = trb_rights_selected_findings( $snapshot, $payload['selected_tracks'] ?? null ); }
	catch ( RuntimeException $e ) { return new WP_Error( 'review_invalid', $e->getMessage(), array( 'status' => 422 ) ); }
	if ( ! in_array( $snapshot['state'], array( 'manual_review', 'published_audio_conflict', 'copyright_documents_needed' ), true ) ) return new WP_Error( 'review_state', 'La pratica non richiede una verifica dei diritti.', array( 'status' => 409 ) );
	$token = hash( 'sha256', $snapshot['version'] . ':' . implode( ',', $selected['indexes'] ) );
	$review = array( 'version' => $snapshot['version'], 'token' => $token, 'selected_tracks' => $selected['indexes'], 'reviewed_at' => time(), 'reviewer' => absint( $payload['reviewer'] ?? 0 ), 'status' => 'queued' );
	if ( ! $review['reviewer'] ) return new WP_Error( 'reviewer_missing', 'Revisore non identificato.', array( 'status' => 422 ) );
	update_post_meta( $id, '_trb_release_owner_rights_review', $review );
	$decision = (array) get_post_meta( $id, '_trb_release_analysis_decision', true ); $decision['copyright_findings'] = $selected['findings'];
	trb_analysis_queue_artist_copyright_email( $id, $decision, $token );
	return rest_ensure_response( array( 'ok' => true, 'status' => function_exists( 'trb_portal_release_is_qa' ) && trb_portal_release_is_qa( $id ) ? 'qa_suppressed' : 'queued', 'selected_tracks' => $selected['indexes'] ) );
}
add_filter( 'rest_pre_dispatch', 'trb_rights_review_receive', 10, 3 );
