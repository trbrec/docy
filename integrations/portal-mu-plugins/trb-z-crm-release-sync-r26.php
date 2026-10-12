<?php
/**
 * Bidirectional release deletion, complete release-change propagation and
 * ten-minute full reconciliation with the TRB CRM.
 * Standalone companion for the TRB CRM Sync mu-plugin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/trb-crm-sync-storage.php';

function trb_crm_sync_is_release_post( $post_or_id ) {
	$post = $post_or_id instanceof WP_Post ? $post_or_id : get_post( absint( $post_or_id ) );
	if ( ! $post instanceof WP_Post ) return false;
	return 'trb_release' === $post->post_type;
}

function trb_crm_sync_clean_snapshot_value( $value ) {
	if ( is_array( $value ) ) {
		$clean = array();
		foreach ( $value as $key => $item ) $clean[ is_int( $key ) ? $key : sanitize_key( (string) $key ) ] = trb_crm_sync_clean_snapshot_value( $item );
		return $clean;
	}
	if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) return $value;
	return sanitize_text_field( (string) $value );
}

function trb_crm_sync_receive_release_update( WP_REST_Request $request ) {
	$release_id = absint( $request['id'] );
	$payload    = $request->get_json_params();
	if ( ! is_array( $payload ) || true !== ( $payload['confirm'] ?? false ) || 'update_release' !== ( $payload['operation'] ?? '' ) ) {
		return new WP_Error( 'trb_crm_update_not_confirmed', 'Conferma di aggiornamento non valida.', array( 'status' => 422 ) );
	}
	$portal_id = sanitize_text_field( $payload['portal_release_id'] ?? '' );
	$title     = sanitize_text_field( $payload['expected_title'] ?? '' );
	$post      = get_post( $release_id );
	if ( ! trb_crm_sync_is_release_post( $post ) || 'artist:' . $release_id !== $portal_id || '' === $title || ! hash_equals( (string) $post->post_title, $title ) ) {
		return new WP_Error( 'trb_crm_update_target_mismatch', 'La release remota non corrisponde alla conferma.', array( 'status' => 409 ) );
	}
	$status  = sanitize_key( $payload['workflow_status'] ?? '' );
	$allowed = array( 'draft', 'waiting', 'ready_to_process', 'processed', 'in_progress', 'ready', 'scheduled', 'published', 'cancelled' );
	if ( ! in_array( $status, $allowed, true ) ) {
		return new WP_Error( 'trb_crm_update_status_invalid', 'Stato release non valido.', array( 'status' => 422 ) );
	}
	$date    = sanitize_text_field( $payload['planned_release_date'] ?? '' );
	$catalog = sanitize_text_field( $payload['catalog_number'] ?? '' );
	$upc     = sanitize_text_field( $payload['upc'] ?? '' );
	if ( '' !== $date && ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $date_parts ) || ! checkdate( (int) $date_parts[2], (int) $date_parts[3], (int) $date_parts[1] ) ) ) {
		return new WP_Error( 'trb_crm_update_date_invalid', 'Data release non valida.', array( 'status' => 422 ) );
	}
    return trb_crm_sync_atomic( 'release:' . $release_id, array( 'post' => array( $release_id ), 'user' => array( (int) $post->post_author ) ), static function() use ( $release_id, $payload, $status, $date, $catalog, $upc, $title, $post ) {
        $current = get_post( $release_id );
        if ( ! trb_crm_sync_is_release_post( $current ) || ! hash_equals( $title, $current->post_title ) ) return new WP_Error( 'trb_crm_update_target_changed', 'Release modificata durante il salvataggio. Riprova.', array( 'status' => 409 ) );
        $previous = $GLOBALS['trb_crm_sync_update_from_crm'] ?? null;
        try {
	$GLOBALS['trb_crm_sync_update_from_crm'] = true;
	// CRM business state never authorizes the portal's technical pipeline.
	trb_crm_sync_write_meta( 'post', $release_id, '_trb_crm_workflow_status', $status );
	if ( '' === $date ) trb_crm_sync_write_meta( 'post', $release_id, '_trb_release_date', null, true ); else trb_crm_sync_write_meta( 'post', $release_id, '_trb_release_date', $date );
	if ( '' === $catalog ) trb_crm_sync_write_meta( 'post', $release_id, '_trb_release_catalog_number', null, true ); else trb_crm_sync_write_meta( 'post', $release_id, '_trb_release_catalog_number', $catalog );
	if ( '' === $upc ) trb_crm_sync_write_meta( 'post', $release_id, '_trb_release_upc', null, true ); else trb_crm_sync_write_meta( 'post', $release_id, '_trb_release_upc', $upc );
	$snapshot = isset( $payload['crm_snapshot'] ) && is_array( $payload['crm_snapshot'] ) ? trb_crm_sync_clean_snapshot_value( $payload['crm_snapshot'] ) : array();
	if ( $snapshot ) {
		trb_crm_sync_write_meta( 'post', $release_id, '_trb_crm_sync_snapshot', $snapshot );
		trb_crm_sync_write_meta( 'post', $release_id, '_trb_crm_last_synced_at', gmdate( 'c' ) );
		if ( ! empty( $snapshot['contract'] ) ) trb_crm_sync_write_meta( 'user', (int) $post->post_author, '_trb_crm_contract_snapshot', $snapshot['contract'] );
		if ( ! empty( $snapshot['practice'] ) ) trb_crm_sync_write_meta( 'user', (int) $post->post_author, '_trb_crm_practice_snapshot', $snapshot['practice'] );
	}

            return rest_ensure_response( array( 'ok' => true, 'updated' => true, 'snapshot_stored' => ! empty( $snapshot ), 'reference' => 'updated:' . $release_id ) );
        } finally {
            if ( null === $previous ) unset( $GLOBALS['trb_crm_sync_update_from_crm'] ); else $GLOBALS['trb_crm_sync_update_from_crm'] = $previous;
        }
    } );
}

function trb_crm_sync_verify_release_request( WP_REST_Request $request ) {
	$secret    = trb_crm_sync_secret();
	$timestamp = (string) $request->get_header( 'x-trb-timestamp' );
	$signature = (string) $request->get_header( 'x-trb-signature' );
	$body      = (string) $request->get_body();
	if ( '' === $secret ) return new WP_Error( 'trb_crm_release_secret_missing', 'Segreto di sincronizzazione assente nel Portale Artisti.', array( 'status' => 503 ) );
	if ( ! ctype_digit( $timestamp ) ) return new WP_Error( 'trb_crm_release_timestamp_missing', 'Timestamp firmato assente.', array( 'status' => 401 ) );
	if ( abs( time() - (int) $timestamp ) > 300 ) return new WP_Error( 'trb_crm_release_timestamp_expired', 'Timestamp firmato scaduto.', array( 'status' => 401 ) );
	if ( '' === $signature ) return new WP_Error( 'trb_crm_release_signature_missing', 'Firma di sincronizzazione assente.', array( 'status' => 401 ) );
	$expected = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	if ( ! hash_equals( $expected, $signature ) ) return new WP_Error( 'trb_crm_release_signature_mismatch', 'Firma di sincronizzazione non corrispondente.', array( 'status' => 401 ) );
	return true;
}

function trb_crm_sync_allow_signed_release_rest( $result ) {
	if ( ! is_wp_error( $result ) ) return $result;
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	if ( ! preg_match( '#/wp-json/trb-crm/v1/(?:release/\d+|reconcile|pcloud)/?$#', $path ) ) return $result;
	$secret    = trb_crm_sync_secret();
	$timestamp = (string) ( $_SERVER['HTTP_X_TRB_TIMESTAMP'] ?? '' );
	$signature = (string) ( $_SERVER['HTTP_X_TRB_SIGNATURE'] ?? '' );
	$body      = (string) file_get_contents( 'php://input' );
	if ( '' === $secret || ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > 300 || '' === $signature ) return $result;
	$expected = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	return hash_equals( $expected, $signature ) ? null : $result;
}
add_filter( 'rest_authentication_errors', 'trb_crm_sync_allow_signed_release_rest', 9999 );

function trb_crm_sync_receive_release_mutation( WP_REST_Request $request ) {
	$payload = $request->get_json_params();
	if ( is_array( $payload ) && 'delete_release' === ( $payload['operation'] ?? '' ) ) return trb_crm_sync_receive_release_delete( $request );
	return trb_crm_sync_receive_release_update( $request );
}

function trb_crm_sync_receive_release_delete( WP_REST_Request $request ) {
	$release_id = absint( $request['id'] );
	$payload    = $request->get_json_params();
	if ( ! is_array( $payload ) || true !== ( $payload['confirm'] ?? false ) || 'delete_release' !== ( $payload['operation'] ?? '' ) ) {
		return new WP_Error( 'trb_crm_delete_not_confirmed', 'Conferma di eliminazione non valida.', array( 'status' => 422 ) );
	}
	$portal_id = sanitize_text_field( $payload['portal_release_id'] ?? '' );
	$title     = sanitize_text_field( $payload['expected_title'] ?? '' );
	if ( ! $release_id || 'artist:' . $release_id !== $portal_id || '' === $title ) {
		return new WP_Error( 'trb_crm_delete_identity_mismatch', 'Identità della release non coerente.', array( 'status' => 422 ) );
	}
	$post = get_post( $release_id );
	if ( ! $post ) {
		return rest_ensure_response( array( 'ok' => true, 'deleted' => true, 'verified_absent' => true, 'already_absent' => true, 'reference' => 'absent:' . $release_id ) );
	}
	if ( ! trb_crm_sync_is_release_post( $post ) || ! hash_equals( (string) $post->post_title, $title ) ) {
		return new WP_Error( 'trb_crm_delete_target_mismatch', 'La release remota non corrisponde alla conferma (tipo: ' . sanitize_key( (string) $post->post_type ) . ').', array( 'status' => 409 ) );
	}
    return trb_crm_sync_atomic( 'release:' . $release_id, array( 'post' => array( $release_id ) ), static function() use ( $release_id, $title ) {
        $current = get_post( $release_id );
        if ( ! $current ) return rest_ensure_response( array( 'ok' => true, 'deleted' => true, 'verified_absent' => true, 'already_absent' => true, 'reference' => 'absent:' . $release_id ) );
        if ( ! trb_crm_sync_is_release_post( $current ) || ! hash_equals( $title, $current->post_title ) ) return new WP_Error( 'trb_crm_delete_target_changed', 'Release modificata durante l’eliminazione. Riprova.', array( 'status' => 409 ) );
        $previous = $GLOBALS['trb_crm_sync_delete_from_crm'] ?? null;
        $GLOBALS['trb_crm_sync_delete_from_crm'] = true;
        try {
            if ( ! wp_delete_post( $release_id, true ) ) throw new RuntimeException( 'Release deletion failed.' );
            global $wpdb;
            if ( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID=%d", $release_id ) ) || $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d LIMIT 1", $release_id ) ) ) throw new RuntimeException( 'Release deletion readback failed.' );
            wp_clear_scheduled_hook( 'trb_crm_sync_release', array( $release_id, 0 ) );
            return rest_ensure_response( array( 'ok' => true, 'deleted' => true, 'verified_absent' => true, 'already_absent' => false, 'reference' => 'deleted:' . $release_id ) );
        } finally {
            if ( null === $previous ) unset( $GLOBALS['trb_crm_sync_delete_from_crm'] ); else $GLOBALS['trb_crm_sync_delete_from_crm'] = $previous;
        }
    } );
}

function trb_crm_sync_register_release_delete_route() {
	register_rest_route( 'trb-crm/v1', '/release/(?P<id>\d+)', array(
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'trb_crm_sync_receive_release_mutation',
			'permission_callback' => 'trb_crm_sync_verify_release_request',
			'args'                => array( 'id' => array( 'validate_callback' => static fn( $value ) => absint( $value ) > 0 ) ),
		),
		array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => 'trb_crm_sync_receive_release_delete',
			'permission_callback' => 'trb_crm_sync_verify_release_request',
			'args'                => array( 'id' => array( 'validate_callback' => static fn( $value ) => absint( $value ) > 0 ) ),
		),
	) );
	register_rest_route( 'trb-crm/v1', '/pcloud', array('methods'=>WP_REST_Server::CREATABLE,'callback'=>'trb_crm_pcloud_receive','permission_callback'=>'trb_crm_sync_verify_release_request') );
	register_rest_route( 'trb-crm/v1', '/reconcile', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'trb_crm_sync_receive_reconciliation_request',
		'permission_callback' => 'trb_crm_sync_verify_release_request',
	) );
}
add_action( 'rest_api_init', 'trb_crm_sync_register_release_delete_route', 20 );

function trb_crm_sync_release_delete_payload( $post_id, $post = null ) {
	$post = $post instanceof WP_Post ? $post : get_post( absint( $post_id ) );
	if ( ! trb_crm_sync_is_release_post( $post ) ) return null;
	$user = get_userdata( $post->post_author );
	return array(
		'operation'          => 'delete_release',
		'portal_release_id'  => 'artist:' . absint( $post->ID ),
		'expected_title'     => sanitize_text_field( $post->post_title ),
		'release_title'      => sanitize_text_field( $post->post_title ),
		'artist_credit'      => $user ? sanitize_text_field( $user->display_name ) : 'Artista',
		'workflow_status'    => 'cancelled',
		'portal_updated_at'  => current_time( 'mysql', true ),
		'practice_public_id' => $user ? sanitize_text_field( get_user_meta( $user->ID, '_trb_crm_public_id', true ) ) : '',
		'email'              => $user ? strtolower( (string) $user->user_email ) : '',
	);
}

function trb_crm_sync_send_release_delete_payload( $payload, $attempt = 0 ) {
	if ( ! is_array( $payload ) ) return;
	$result  = trb_crm_sync_post_to_crm( '/webhooks/artist-portal/release', $payload );
	$attempt = absint( $attempt );
	if ( is_wp_error( $result ) && $attempt < 5 ) {
		wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS * ( 2 ** $attempt ) ), 'trb_crm_sync_retry_release_delete', array( $payload, $attempt + 1 ) );
	}
}
add_action( 'trb_crm_sync_retry_release_delete', 'trb_crm_sync_send_release_delete_payload', 10, 2 );

function trb_crm_sync_notify_release_deleted( $post_id, $post = null ) {
	if ( ! empty( $GLOBALS['trb_crm_sync_delete_from_crm'] ) ) return;
	if ( ! empty( $GLOBALS['trb_crm_sync_delete_ack'][ absint( $post_id ) ] ) ) return;
	$payload = trb_crm_sync_release_delete_payload( $post_id, $post );
	if ( $payload ) trb_crm_sync_send_release_delete_payload( $payload, 0 );
}
add_action( 'before_delete_post', 'trb_crm_sync_notify_release_deleted', 10, 2 );

function trb_crm_sync_guard_portal_release_delete( $delete, $post ) {
	if ( null !== $delete || ! trb_crm_sync_is_release_post( $post ) || ! empty( $GLOBALS['trb_crm_sync_delete_from_crm'] ) ) return $delete;
	$payload = trb_crm_sync_release_delete_payload( $post->ID, $post );
	if ( ! $payload ) return false;
	$result = trb_crm_sync_post_to_crm( '/webhooks/artist-portal/release', $payload );
	if ( is_wp_error( $result ) || ! is_array( $result ) || true !== ( $result['ok'] ?? false ) || true !== ( $result['deleted'] ?? false ) ) {
		$message = is_wp_error( $result ) ? $result->get_error_message() : 'risposta CRM non valida';
		error_log( 'TRB CRM: eliminazione portale bloccata per release ' . absint( $post->ID ) . ': ' . $message );
		return false;
	}
	if ( ! isset( $GLOBALS['trb_crm_sync_delete_ack'] ) || ! is_array( $GLOBALS['trb_crm_sync_delete_ack'] ) ) $GLOBALS['trb_crm_sync_delete_ack'] = array();
	$GLOBALS['trb_crm_sync_delete_ack'][ absint( $post->ID ) ] = true;
	return null;
}
add_filter( 'pre_delete_post', 'trb_crm_sync_guard_portal_release_delete', 10, 2 );
add_filter( 'pre_trash_post', 'trb_crm_sync_guard_portal_release_delete', 10, 2 );

function trb_crm_sync_notify_release_trashed( $post_id ) {
	trb_crm_sync_notify_release_deleted( $post_id, get_post( absint( $post_id ) ) );
}
add_action( 'trashed_post', 'trb_crm_sync_notify_release_trashed', 10, 1 );

function trb_crm_sync_any_release_meta_changed( $meta_id, $object_id, $meta_key ) {
	if ( ! empty( $GLOBALS['trb_crm_sync_update_from_crm'] ) ) return;
	$meta_key = (string) $meta_key;
	if ( str_starts_with( $meta_key, '_trb_release_' ) || str_starts_with( $meta_key, '_trb_contract_' ) || '_trb_otp_dossier_id' === $meta_key ) {
		trb_crm_sync_schedule_release( absint( $object_id ) );
	}
}
add_action( 'added_post_meta', 'trb_crm_sync_any_release_meta_changed', 110, 3 );
add_action( 'updated_post_meta', 'trb_crm_sync_any_release_meta_changed', 110, 3 );
add_action( 'deleted_post_meta', 'trb_crm_sync_any_release_meta_changed', 110, 3 );

function trb_crm_sync_schedule_user_releases( $user_id ) {
	$ids = get_posts( array( 'post_type' => 'trb_release', 'post_status' => 'any', 'author' => absint( $user_id ), 'fields' => 'ids', 'posts_per_page' => -1 ) );
	foreach ( $ids as $release_id ) trb_crm_sync_schedule_release( absint( $release_id ) );
}

function trb_crm_sync_user_release_data_changed( $meta_id, $user_id, $meta_key ) {
	$meta_key = (string) $meta_key;
	if ( str_starts_with( $meta_key, '_trb_artist_' ) || str_starts_with( $meta_key, 'billing_' ) || in_array( $meta_key, array( 'first_name', 'last_name' ), true ) ) {
		trb_crm_sync_schedule_user_releases( absint( $user_id ) );
	}
}
add_action( 'added_user_meta', 'trb_crm_sync_user_release_data_changed', 110, 3 );
add_action( 'updated_user_meta', 'trb_crm_sync_user_release_data_changed', 110, 3 );
add_action( 'deleted_user_meta', 'trb_crm_sync_user_release_data_changed', 110, 3 );
add_action( 'profile_update', 'trb_crm_sync_schedule_user_releases', 110, 1 );

function trb_crm_complete_sync_metadata( $type, $object_id ) {
	$raw    = 'user' === $type ? get_user_meta( absint( $object_id ) ) : get_post_meta( absint( $object_id ) );
	$result = array();
	foreach ( $raw as $key => $values ) {
		if ( str_starts_with( (string) $key, '_trb_crm_' ) || preg_match( '/secret|token|password|nonce|signature|session/i', (string) $key ) ) continue;
		$decoded = array_map( 'trb_crm_sync_decode_serialized', (array) $values );
		$result[ (string) $key ] = 1 === count( $decoded ) ? $decoded[0] : $decoded;
	}
	return $result;
}

function trb_crm_complete_sync_meta_value( $meta, $keys, $default = null ) {
	foreach ( (array) $keys as $key ) if ( array_key_exists( $key, $meta ) ) return $meta[ $key ];
	foreach ( $meta as $meta_key => $value ) foreach ( (array) $keys as $key ) if ( str_ends_with( (string) $meta_key, (string) $key ) ) return $value;
	return $default;
}

function trb_crm_complete_sync_workflow_status( $pipeline ) {
	$pipeline = sanitize_key( (string) $pipeline );
	if ( in_array( $pipeline, array( 'analysis_in_progress', 'analysis_waiting_configuration', 'technical_analysis_running', 'copyright_queued', 'security_scan_waiting', 'archived_pending_analysis', 'in_progress' ), true ) ) return 'in_progress';
	if ( in_array( $pipeline, array( 'approved', 'contract_ready', 'contract_sent', 'ready_for_distribution', 'ready' ), true ) ) return 'ready';
	if ( str_contains( $pipeline, 'scheduled' ) ) return 'scheduled';
	if ( str_contains( $pipeline, 'published' ) || str_contains( $pipeline, 'distributed' ) ) return 'published';
	if ( in_array( $pipeline, array( 'cancelled', 'canceled', 'rejected', 'security_rejected' ), true ) ) return 'cancelled';
	return 'draft';
}

function trb_crm_complete_sync_release_payload( $post_id ) {
	$post = get_post( absint( $post_id ) );
	if ( ! trb_crm_sync_is_release_post( $post ) || 'trash' === $post->post_status ) return null;
	$user = get_userdata( (int) $post->post_author );
	if ( ! $user ) return null;
	$post_meta = trb_crm_complete_sync_metadata( 'post', $post->ID );
	$user_meta = trb_crm_complete_sync_metadata( 'user', $user->ID );
	$pipeline  = (string) trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_pipeline_status', '_trb_release_status' ), 'draft' );
	$artist    = trim( (string) trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_artist_name' ), $user->display_name ) );
	$profile   = array(
		'phone'           => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_phone', 'phone', 'billing_phone' ) ),
		'birth_date'      => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_birth_date', 'birth_date' ) ),
		'birth_place'     => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_birth_place', 'birth_place' ) ),
		'birth_province'  => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_birth_province', 'birth_province' ) ),
		'tax_code'        => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_tax_code', 'tax_code', 'codice_fiscale' ) ),
		'street'          => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_street', 'billing_address_1', 'street' ) ),
		'street_number'   => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_street_number', 'street_number' ) ),
		'postal_code'     => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_postal_code', 'billing_postcode', 'postal_code' ) ),
		'city'            => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_city', 'billing_city', 'city' ) ),
		'province'        => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_province', 'billing_state', 'province' ) ),
		'country'         => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_country', 'billing_country', 'country' ) ),
		'document_number' => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_document_number', 'document_number' ) ),
		'document_expiry' => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_document_expiry', 'document_expiry' ) ),
		'live_fee'        => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_live_fee', 'live_fee' ) ),
		'spotify_url'     => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_spotify_url', 'spotify_url' ) ),
		'youtube_url'     => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_youtube_url', 'youtube_url' ) ),
		'instagram_url'   => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_instagram_url', 'instagram_url' ) ),
		'facebook_url'    => trb_crm_complete_sync_meta_value( $user_meta, array( '_trb_artist_facebook_url', 'facebook_url' ) ),
	);
	$metadata = array(
		'source'                 => 'artist_portal_periodic_sync',
		'portal_post_id'         => (int) $post->ID,
		'portal_post_status'     => (string) $post->post_status,
		'portal_pipeline_status' => $pipeline,
		'portal_release_status'  => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_status' ), '' ),
		'portal_release_step'    => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_step' ), '' ),
		'release_type'           => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_type' ), '' ),
		'original_release_date'  => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_original_date', '_trb_original_release_date' ) ),
		'artist_profile'         => array_filter( $profile, static fn( $value ) => null !== $value && '' !== $value ),
		'tracks'                 => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_tracks', '_trb_tracks' ), array() ),
		'files'                  => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_files', '_trb_files' ), array() ),
		'pcloud_archive'         => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_pcloud_archive', '_trb_pcloud_archive' ), array() ),
		'contract'               => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_contract', '_trb_contract' ), array() ),
		'rights_declarations'    => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_rights_declarations', '_trb_rights_declarations' ), array() ),
		'analysis_decision'      => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_analysis_decision', '_trb_analysis_decision' ), array() ),
		'technical_analysis'     => trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_technical_analysis', '_trb_technical_analysis' ), array() ),
		'portal_post_meta'       => $post_meta,
		'portal_user_meta'       => $user_meta,
		'automatic_actions_started' => false,
	);
	foreach ( array( 'local_folder', 'local_path', 'folder_path', 'windows_path', 'distribution_folder' ) as $key ) {
		$value = trb_crm_complete_sync_meta_value( $post_meta, array( $key ) );
		if ( is_string( $value ) && '' !== trim( $value ) ) { $metadata[ $key ] = trim( $value ); break; }
	}
	$date = (string) trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_date' ), '' );
	return array(
		'operation'            => 'upsert',
		'portal_release_id'    => 'artist:' . (int) $post->ID,
		'release_title'        => sanitize_text_field( $post->post_title ),
		'artist_credit'        => sanitize_text_field( $artist ?: $user->display_name ),
		'workflow_status'      => trb_crm_complete_sync_workflow_status( $pipeline ),
		'planned_release_date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : null,
		'catalog_number'       => sanitize_text_field( (string) trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_catalog_number', 'catalog_number' ), '' ) ),
		'upc'                  => sanitize_text_field( (string) trb_crm_complete_sync_meta_value( $post_meta, array( '_trb_release_upc', 'upc' ), '' ) ),
		'portal_updated_at'    => $post->post_modified_gmt ?: current_time( 'mysql', true ),
		'practice_public_id'   => sanitize_text_field( (string) get_user_meta( $user->ID, '_trb_crm_public_id', true ) ),
		'email'                => strtolower( (string) $user->user_email ),
		'metadata'             => $metadata,
	);
}

function trb_crm_complete_sync_send_release( $post_id, $retry = 0 ) {
	$payload = trb_crm_complete_sync_release_payload( $post_id );
	if ( ! $payload ) return new WP_Error( 'trb_crm_complete_sync_release_missing', 'Release non disponibile.' );
	$result = trb_crm_sync_post_to_crm( '/webhooks/artist-portal/release', $payload );
	if ( ! is_wp_error( $result ) && ( ! is_array( $result ) || true !== ( $result['ok'] ?? false ) ) ) {
		$result = new WP_Error( 'trb_crm_complete_sync_invalid_ack', 'Il CRM non ha confermato la sincronizzazione della release.' );
	}
	if ( is_wp_error( $result ) && absint( $retry ) < 3 ) {
		$args = array( absint( $post_id ), absint( $retry ) + 1 );
		if ( ! wp_next_scheduled( 'trb_crm_complete_sync_retry_release', $args ) ) wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'trb_crm_complete_sync_retry_release', $args );
	}
	return $result;
}
add_action( 'trb_crm_complete_sync_retry_release', 'trb_crm_complete_sync_send_release', 10, 2 );

function trb_crm_complete_sync_run() {
	global $wpdb;
	$lock_key = 'trb-full-sync:' . substr( hash( 'sha256', $wpdb->prefix ), 0, 32 );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock_key ) ) ) return array( 'status' => 'locked', 'completed_at' => gmdate( 'c' ), 'interval_seconds' => 600, 'release_count' => 0, 'sent' => 0, 'portal_release_ids' => array(), 'errors' => array( array( 'error' => 'sync_locked' ) ), 'duration_ms' => 0 );
	$started = microtime( true );
	try {
		$ids      = get_posts( array( 'post_type' => 'trb_release', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
		$sent     = 0;
		$errors   = array();
		$manifest = array();
		foreach ( $ids as $post_id ) {
			$payload = trb_crm_complete_sync_release_payload( absint( $post_id ) );
			if ( ! $payload ) continue;
			$manifest[] = (string) $payload['portal_release_id'];
			$result = trb_crm_complete_sync_send_release( absint( $post_id ), 0 );
			if ( is_wp_error( $result ) ) $errors[] = array( 'portal_release_id' => 'artist:' . absint( $post_id ), 'error' => $result->get_error_message() ); else $sent++;
		}
		$state = array( 'status' => $errors ? 'degraded' : 'completed', 'completed_at' => gmdate( 'c' ), 'interval_seconds' => 600, 'release_count' => count( $manifest ), 'sent' => $sent, 'portal_release_ids' => array_values( array_unique( $manifest ) ), 'errors' => $errors, 'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ) );
		update_option( 'trb_crm_complete_sync_state', $state, false );
		return $state;
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_key ) );
	}
}
add_action( 'trb_crm_complete_sync_every_ten_minutes', 'trb_crm_complete_sync_run' );

/**
 * Return the authoritative release manifest without replaying every release to
 * the CRM. The independent ten-minute cron still performs the full push; this
 * lightweight path prevents a CRM reconciliation from immediately duplicating
 * the same network traffic.
 */
function trb_crm_complete_sync_manifest_state() {
	$started  = microtime( true );
	$ids      = get_posts( array( 'post_type' => 'trb_release', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
	$manifest = array();
	$excluded = 0;
	foreach ( $ids as $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! trb_crm_sync_is_release_post( $post ) || 'trash' === $post->post_status ) continue;
		$pipeline = (string) get_post_meta( $post->ID, '_trb_release_pipeline_status', true );
		$status   = (string) get_post_meta( $post->ID, '_trb_release_status', true );
		$haystack = strtolower( implode( '_', array( $pipeline, $status ) ) );
		$haystack = preg_replace( '/[^a-z0-9]+/', '_', $haystack );
		if ( str_contains( (string) $haystack, 'take_down' ) || str_contains( (string) $haystack, 'taken_down' ) ) { $excluded++; continue; }
		$manifest[] = 'artist:' . (int) $post->ID;
	}
	return array(
		'status' => 'completed', 'completed_at' => gmdate( 'c' ), 'interval_seconds' => 600,
		'release_count' => count( $manifest ), 'sent' => 0, 'delivery_mode' => 'manifest_only',
		'excluded_take_down' => $excluded, 'portal_release_ids' => array_values( array_unique( $manifest ) ),
		'errors' => array(), 'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
	);
}

function trb_crm_sync_receive_reconciliation_request( WP_REST_Request $request ) {
	$payload = $request->get_json_params();
	if ( ! is_array( $payload ) || true !== ( $payload['confirm'] ?? false ) || 'reconcile_portal' !== ( $payload['operation'] ?? '' ) ) {
		return new WP_Error( 'trb_crm_reconcile_not_confirmed', 'Richiesta di riconciliazione non valida.', array( 'status' => 422 ) );
	}
	$delivery_mode = sanitize_key( (string) ( $payload['delivery_mode'] ?? 'full_push' ) );
	$state = 'manifest_only' === $delivery_mode ? trb_crm_complete_sync_manifest_state() : trb_crm_complete_sync_run();
	$ok    = 'completed' === ( $state['status'] ?? '' ) && empty( $state['errors'] );
	return rest_ensure_response( array( 'ok' => $ok, 'completed' => $ok, 'state' => $state, 'reference' => 'reconciled:' . gmdate( 'YmdHis' ) ) );
}

function trb_crm_complete_sync_schedule( $schedules ) {
	$schedules['trb_every_ten_minutes'] = array( 'interval' => 10 * MINUTE_IN_SECONDS, 'display' => 'TRB ogni 10 minuti' );
	return $schedules;
}
add_filter( 'cron_schedules', 'trb_crm_complete_sync_schedule' );

function trb_crm_complete_sync_ensure_schedule() {
	if ( ! wp_next_scheduled( 'trb_crm_complete_sync_every_ten_minutes' ) ) wp_schedule_event( time() + MINUTE_IN_SECONDS, 'trb_every_ten_minutes', 'trb_crm_complete_sync_every_ten_minutes' );
}
add_action( 'init', 'trb_crm_complete_sync_ensure_schedule', 30 );

/** Private CRM-to-pCloud bridge. Uses the existing HMAC permission callback. */

/** Private CRM-to-pCloud bridge. Uses the existing HMAC permission callback. */
function trb_crm_pcloud_path($path) {
    if(!is_string($path)||strlen($path)>2048||!str_starts_with($path,'/')||preg_match('/[\x00-\x1f\\\\]/',$path))throw new RuntimeException('PCLOUD_INVALID_PATH');
    if($path!=='/')foreach(explode('/',substr($path,1)) as $part)if($part===''||$part==='.'||$part==='..')throw new RuntimeException('PCLOUD_INVALID_PATH');
    return $path;
}
function trb_crm_pcloud_transport($method,$path,$local=null,$limit=null,$sink=null) {
    if(!in_array($method,array('HEAD','GET','PUT','MKCOL'),true))throw new RuntimeException('PCLOUD_INVALID_METHOD');
    $settings=function_exists('trb_demo_settings')?trb_demo_settings():array();
    $endpoint=rtrim((string)($settings['webdav_endpoint']??''),'/');$parts=parse_url($endpoint);
    if(($parts['scheme']??'')!=='https'||!in_array(strtolower((string)($parts['host']??'')),array('webdav.pcloud.com','ewebdav.pcloud.com'),true)||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||!in_array($parts['port']??443,array(443),true)||!empty($parts['path']))throw new RuntimeException('PCLOUD_CONFIGURATION_INVALID');
    if(empty($settings['pcloud_user'])||empty($settings['pcloud_pass']))throw new RuntimeException('PCLOUD_CONFIGURATION_MISSING');
    trb_crm_pcloud_path($path);$url=$endpoint.'/'.implode('/',array_map('rawurlencode',explode('/',substr($path,1))));
    $ch=curl_init($url);if(!$ch)throw new RuntimeException('PCLOUD_TRANSPORT_UNAVAILABLE');
    $hash=hash_init('sha256');$bytes=0;$size=null;$stream=null;
    curl_setopt_array($ch,array(CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_USERPWD=>$settings['pcloud_user'].':'.$settings['pcloud_pass'],CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>180,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_RETURNTRANSFER=>false,
        CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$size){if(preg_match('/^Content-Length:\s*(\d+)/i',$line,$m))$size=(int)$m[1];return strlen($line);},
        CURLOPT_WRITEFUNCTION=>static function($ch,$data)use(&$bytes,$hash,$limit,$sink){$length=strlen($data);if($limit!==null&&$bytes+$length>$limit)return 0;if(is_resource($sink)&&fwrite($sink,$data)!==$length)return 0;$bytes+=$length;hash_update($hash,$data);return $length;}
    ));
    if($method==='HEAD')curl_setopt_array($ch,array(CURLOPT_NOBODY=>true));
    if($method==='PUT'){
        $stream=fopen($local,'rb');if(!$stream){curl_close($ch);throw new RuntimeException('PCLOUD_LOCAL_UNREADABLE');}
        curl_setopt_array($ch,array(CURLOPT_UPLOAD=>true,CURLOPT_INFILE=>$stream,CURLOPT_INFILESIZE=>filesize($local),CURLOPT_HTTPHEADER=>array('Content-Type: application/octet-stream','If-None-Match: *','Expect:')));
    }
    try{$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if($ok===false)throw new RuntimeException('PCLOUD_TRANSPORT_FAILED');return array('status'=>$status,'size'=>$size,'bytes'=>$bytes,'sha256'=>hash_final($hash));}
    finally{curl_close($ch);if(is_resource($stream))fclose($stream);}
}
function trb_crm_pcloud_verify_binary($path,$size,$sha) {
    $result=trb_crm_pcloud_transport('GET',$path,null,$size);
    if($result['status']!==200||$result['bytes']!==$size||!hash_equals($sha,$result['sha256']))throw new RuntimeException('PCLOUD_REMOTE_INTEGRITY_FAILED');
    return array('path'=>$path,'size'=>$size,'sha256'=>$sha,'verified_at'=>gmdate('c'));
}
function trb_crm_pcloud_archive($payload) {
    $sha=(string)($payload['sha256']??'');$size=$payload['size']??0;$relative=(string)($payload['local_relative']??'');
    if(!preg_match('/^[a-f0-9]{64}$/',$sha)||!is_int($size)||$size<1||$size>1073741824||!preg_match('#^[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]{64}\.[a-z0-9]{1,8}$#',$relative))throw new RuntimeException('PCLOUD_INVALID_SOURCE');
    if(!str_starts_with($relative,substr($sha,0,2).'/'.substr($sha,2,2).'/'.$sha.'.'))throw new RuntimeException('PCLOUD_INVALID_SOURCE');
    $local=false;
    foreach(array('private-storage/materials','private/materials') as $storage){
        $root=realpath(dirname(rtrim(ABSPATH,'/'),2).'/crm.trbrec.com/'.$storage);$candidate=$root?realpath($root.'/'.$relative):false;
        if($candidate&&str_starts_with($candidate,$root.'/')&&is_file($candidate)&&filesize($candidate)===$size&&hash_equals($sha,hash_file('sha256',$candidate))){$local=$candidate;break;}
    }
    if(!$local)throw new RuntimeException('PCLOUD_SOURCE_UNAVAILABLE_OR_CHANGED');
    $folder=trb_crm_pcloud_path((string)($payload['folder']??''));
    if(!str_starts_with($folder,'/Candidature/'))throw new RuntimeException('PCLOUD_ARCHIVE_FOLDER_NOT_ALLOWED');
    $filename=(string)($payload['filename']??'');
    if($filename===''||strlen($filename)>220||basename($filename)!==$filename||preg_match('/[\x00-\x1f\\\\]/',$filename))throw new RuntimeException('PCLOUD_INVALID_FILENAME');
    $remote=$folder.'/'.$sha.'-'.$filename;
    $probe=trb_crm_pcloud_transport('HEAD',$remote);
    if($probe['status']===200)return trb_crm_pcloud_verify_binary($remote,$size,$sha);
    if($probe['status']!==404)throw new RuntimeException('PCLOUD_DESTINATION_UNREACHABLE');
    if(function_exists('trb_resource_pcloud_guard')){$guard=trb_resource_pcloud_guard($size);if(is_wp_error($guard))throw new RuntimeException('PCLOUD_CAPACITY_GUARD');}
    $parent='';foreach(explode('/',substr($folder,1)) as $segment){$parent.='/'.$segment;$made=trb_crm_pcloud_transport('MKCOL',$parent);if(!in_array($made['status'],array(200,201,204,301,405),true))throw new RuntimeException('PCLOUD_FOLDER_CREATION_FAILED_HTTP_'.$made['status']);}
    $put=trb_crm_pcloud_transport('PUT',$remote,$local);
    if(!in_array($put['status'],array(200,201,204,412),true))throw new RuntimeException('PCLOUD_UPLOAD_FAILED');
    return trb_crm_pcloud_verify_binary($remote,$size,$sha);
}
function trb_crm_pcloud_legacy_record($id,$verify=true) {
    if(!is_int($id)||$id<1)throw new RuntimeException('LEGACY_INVALID_ID');
    $registry=get_option('trb_legacy_demo_migration_20260904',array());$record=is_array($registry)?($registry[$id]??null):null;
    if(!is_array($record)||empty($record['verified'])||empty($record['deleted'])||!preg_match('/^[a-f0-9]{64}$/',(string)($record['sha256']??''))||(int)($record['size']??0)<1)throw new RuntimeException('LEGACY_RECORD_NOT_VERIFIED');
    $name=(string)($record['name']??'');$remote=(string)($record['remote']??'');
    if($name===''||basename($name)!==$name||$remote!=='/Upload files - TRB rec/Audio/Demo files/Archivio-trbrec-demo-20260904/'.$name)throw new RuntimeException('LEGACY_RECORD_INVALID_PATH');
    if($verify){$head=trb_crm_pcloud_transport('HEAD',$remote);if($head['status']!==200||(int)$head['size']!==(int)$record['size'])throw new RuntimeException('LEGACY_REMOTE_NOT_AVAILABLE');}
    return array('source_id'=>$id,'name'=>$name,'path'=>$remote,'sha256'=>$record['sha256'],'size'=>(int)$record['size'],'verified_at'=>$record['verified'],'original_deleted_at'=>$record['deleted'],'checked_at'=>$verify?gmdate('c'):null);
}
function trb_crm_pcloud_legacy_restore($payload) {
    $record=trb_crm_pcloud_legacy_record($payload['source_id']??null,false);$sha=$record['sha256'];
    $storage=$payload['cache_scope']??'';if(!in_array($storage,array('private/legacy-cache','private-storage/legacy-cache'),true))throw new RuntimeException('LEGACY_CACHE_NOT_ALLOWED');
    $root=realpath(dirname(rtrim(ABSPATH,'/'),2).'/crm.trbrec.com/'.$storage);if(!$root||!is_dir($root))throw new RuntimeException('LEGACY_CACHE_NOT_AVAILABLE');
    $extension=strtolower(pathinfo($record['name'],PATHINFO_EXTENSION));if(!preg_match('/^[a-z0-9]{1,8}$/',$extension))$extension='bin';
    $relative=substr($sha,0,2).'/'.$sha.'.'.$extension;$directory=$root.'/'.substr($sha,0,2);
    if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('LEGACY_CACHE_NOT_WRITABLE');
    if(realpath($directory)!==$directory)throw new RuntimeException('LEGACY_CACHE_INVALID_DIRECTORY');
    $target=$root.'/'.$relative;
    if(is_file($target)&&!is_link($target)&&filesize($target)===$record['size']&&hash_equals($sha,hash_file('sha256',$target)))return array_merge($record,array('local_relative'=>$relative,'cache_hit'=>true));
    if(file_exists($target)||is_link($target))throw new RuntimeException('LEGACY_CACHE_INTEGRITY_CONFLICT');
    $temporary=tempnam($directory,'download-');if(!$temporary)throw new RuntimeException('LEGACY_CACHE_NOT_WRITABLE');$stream=fopen($temporary,'wb');
    if(!$stream){unlink($temporary);throw new RuntimeException('LEGACY_CACHE_NOT_WRITABLE');}
    try{
        $get=trb_crm_pcloud_transport('GET',$record['path'],null,$record['size'],$stream);fclose($stream);$stream=null;
        if($get['status']!==200||$get['bytes']!==$record['size']||!hash_equals($sha,$get['sha256']))throw new RuntimeException('LEGACY_REMOTE_INTEGRITY_FAILED');
        chmod($temporary,0600);if(!rename($temporary,$target))throw new RuntimeException('LEGACY_CACHE_FINALIZE_FAILED');
        return array_merge($record,array('local_relative'=>$relative,'cache_hit'=>false,'readback_verified_at'=>gmdate('c')));
    }finally{if(is_resource($stream))fclose($stream);if(is_file($temporary))unlink($temporary);}
}
function trb_crm_pcloud_demo_reference($remote) {
        trb_crm_pcloud_path($remote);
        if(!str_starts_with($remote,'/Upload files - TRB rec/Audio/Demo files/')||str_contains($remote,'/Archivio-trbrec-demo-20260904/'))throw new RuntimeException('DEMO_FILE_NOT_REGISTERED');
        global $wpdb;
        $ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key='_trb_demo_remote' AND meta_value LIKE %s LIMIT 101",'%'.$wpdb->esc_like($remote).'%'));
        if(count($ids)>100)throw new RuntimeException('DEMO_FILE_REFERENCE_AMBIGUOUS');
        $matches=array();foreach($ids as $candidate){$meta=get_post_meta((int)$candidate,'_trb_demo_remote',true);if(is_array($meta)&&in_array($remote,$meta['files']??array(),true))$matches[]=(int)$candidate;}
        if(count($matches)!==1)throw new RuntimeException(count($matches)?'DEMO_FILE_REFERENCE_AMBIGUOUS':'DEMO_FILE_NOT_REGISTERED');
        return $matches[0];
}
function trb_crm_pcloud_restore_demo($payload) {
    $id=$payload['request_id']??0;$remote=(string)($payload['remote_path']??'');
    if(!is_int($id)||$id<0)throw new RuntimeException('DEMO_INVALID_ID');
    if($id===0)$id=trb_crm_pcloud_demo_reference($remote);
    $archive=get_post_meta($id,'_trb_demo_remote',true);$files=is_array($archive)?($archive['files']??array()):array();$key=array_search($remote,$files,true);
    if($key===false||!str_starts_with($remote,'/Upload files - TRB rec/Audio/Demo files/')||str_contains($remote,'/Archivio-trbrec-demo-20260904/'))throw new RuntimeException('DEMO_FILE_NOT_REGISTERED');
    trb_crm_pcloud_path($remote);$head=trb_crm_pcloud_transport('HEAD',$remote);$size=(int)($head['size']??0);
    if($head['status']!==200||$size<1||$size>1073741824)throw new RuntimeException('DEMO_REMOTE_NOT_AVAILABLE');
    $proof=$archive['verification'][$key]??array();$originalSha=(string)($proof['sha256']??'');$hasProof=!empty($proof);if($hasProof&&(!preg_match('/^[a-f0-9]{64}$/',$originalSha)||empty($proof['verified_at'])||(int)($proof['size']??0)!==$size))throw new RuntimeException('DEMO_REMOTE_INTEGRITY_FAILED');
    $scope=$payload['storage_scope']??'';if(!in_array($scope,array('private/materials','private-storage/materials'),true))throw new RuntimeException('DEMO_STORAGE_NOT_ALLOWED');
    $root=realpath(dirname(rtrim(ABSPATH,'/'),2).'/crm.trbrec.com/'.$scope);if(!$root||!is_dir($root))throw new RuntimeException('DEMO_STORAGE_NOT_AVAILABLE');
    $temporary=tempnam($root,'portal-read-');if(!$temporary)throw new RuntimeException('DEMO_STORAGE_NOT_WRITABLE');$stream=fopen($temporary,'wb');
    if(!$stream){unlink($temporary);throw new RuntimeException('DEMO_STORAGE_NOT_WRITABLE');}
    try{
        $get=trb_crm_pcloud_transport('GET',$remote,null,$size,$stream);fclose($stream);$stream=null;
        if($get['status']!==200||$get['bytes']!==$size||($hasProof&&!hash_equals($originalSha,$get['sha256'])))throw new RuntimeException('DEMO_REMOTE_INTEGRITY_FAILED');
        $sha=$get['sha256'];$name=basename($remote);$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!preg_match('/^[a-z0-9]{1,8}$/',$extension))$extension='bin';
        $relative=substr($sha,0,2).'/'.substr($sha,2,2).'/'.$sha.'.'.$extension;$directory=dirname($root.'/'.$relative);
        if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('DEMO_STORAGE_NOT_WRITABLE');
        if(realpath($directory)!==$directory)throw new RuntimeException('DEMO_STORAGE_INVALID_DIRECTORY');
        $target=$root.'/'.$relative;
        if(file_exists($target)||is_link($target)){if(is_link($target)||!is_file($target)||filesize($target)!==$size||!hash_equals($sha,hash_file('sha256',$target)))throw new RuntimeException('DEMO_STORAGE_INTEGRITY_CONFLICT');}
        else{chmod($temporary,0600);if(!rename($temporary,$target))throw new RuntimeException('DEMO_STORAGE_FINALIZE_FAILED');}
        return array('path'=>$remote,'request_id'=>$id,'filename'=>$name,'local_relative'=>$relative,'size'=>$size,'sha256'=>$sha,'original_verified'=>(bool)$hasProof,'readback_verified_at'=>gmdate('c'));
    }finally{if(is_resource($stream))fclose($stream);if(is_file($temporary))unlink($temporary);}
}
function trb_crm_pcloud_restore_candidate($payload) {
    $remote=trb_crm_pcloud_path((string)($payload['remote_path']??''));$size=(int)($payload['size']??0);$originalSha=(string)($payload['sha256']??'');$hasProof=true;$id=0;
    if(!preg_match('/^[a-f0-9]{64}$/',$originalSha)||$size<1||$size>1073741824||!preg_match('#^/(?:Demo TRB|Candidature)/[0-9]{4}/[0-9]{2}/[^/]+/'.$originalSha.'-#',$remote))throw new RuntimeException('CANDIDATE_ARCHIVE_REFERENCE_INVALID');
    $scope=$payload['storage_scope']??'';if(!in_array($scope,array('private/materials','private-storage/materials'),true))throw new RuntimeException('DEMO_STORAGE_NOT_ALLOWED');
    $root=realpath(dirname(rtrim(ABSPATH,'/'),2).'/crm.trbrec.com/'.$scope);if(!$root||!is_dir($root))throw new RuntimeException('DEMO_STORAGE_NOT_AVAILABLE');
    $temporary=tempnam($root,'portal-read-');if(!$temporary)throw new RuntimeException('DEMO_STORAGE_NOT_WRITABLE');$stream=fopen($temporary,'wb');
    if(!$stream){unlink($temporary);throw new RuntimeException('DEMO_STORAGE_NOT_WRITABLE');}
    try{
        $get=trb_crm_pcloud_transport('GET',$remote,null,$size,$stream);fclose($stream);$stream=null;
        if($get['status']!==200||$get['bytes']!==$size||($hasProof&&!hash_equals($originalSha,$get['sha256'])))throw new RuntimeException('DEMO_REMOTE_INTEGRITY_FAILED');
        $sha=$get['sha256'];$name=basename($remote);$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!preg_match('/^[a-z0-9]{1,8}$/',$extension))$extension='bin';
        $relative=substr($sha,0,2).'/'.substr($sha,2,2).'/'.$sha.'.'.$extension;$directory=dirname($root.'/'.$relative);
        if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('DEMO_STORAGE_NOT_WRITABLE');
        if(realpath($directory)!==$directory)throw new RuntimeException('DEMO_STORAGE_INVALID_DIRECTORY');
        $target=$root.'/'.$relative;
        if(file_exists($target)||is_link($target)){if(is_link($target)||!is_file($target)||filesize($target)!==$size||!hash_equals($sha,hash_file('sha256',$target)))throw new RuntimeException('DEMO_STORAGE_INTEGRITY_CONFLICT');}
        else{chmod($temporary,0600);if(!rename($temporary,$target))throw new RuntimeException('DEMO_STORAGE_FINALIZE_FAILED');}
        return array('path'=>$remote,'request_id'=>$id,'filename'=>$name,'local_relative'=>$relative,'size'=>$size,'sha256'=>$sha,'original_verified'=>(bool)$hasProof,'readback_verified_at'=>gmdate('c'));
    }finally{if(is_resource($stream))fclose($stream);if(is_file($temporary))unlink($temporary);}
}
function trb_crm_pcloud_receive(WP_REST_Request $request) {
    $payload=$request->get_json_params();
    if(!is_array($payload))return new WP_Error('trb_pcloud_invalid','Richiesta pCloud non valida.',array('status'=>422));
    try{
        if(($payload['operation']??'')==='demo_references'){
            $paths=$payload['paths']??null;if(!is_array($paths)||count($paths)>10)throw new RuntimeException('PCLOUD_INVALID_BATCH');$references=array();
            foreach($paths as $path){try{$references[]=array('path'=>$path,'request_id'=>trb_crm_pcloud_demo_reference($path),'registered'=>true);}catch(Throwable $e){$references[]=array('path'=>$path,'registered'=>false,'error'=>$e->getMessage());}}
            return rest_ensure_response(array('ok'=>true,'references'=>$references));
        }
        if(($payload['operation']??'')==='material_inventory'){
            $registry=get_option('trb_legacy_demo_migration_20260904',array());$files=array();
            foreach(is_array($registry)?$registry:array() as $id=>$unused){try{$files[]=trb_crm_pcloud_legacy_record((int)$id,false);}catch(Throwable $e){continue;}}
            return rest_ensure_response(array('ok'=>true,'files'=>$files));
        }
        if(($payload['operation']??'')==='legacy_record')return rest_ensure_response(array('ok'=>true,'file'=>trb_crm_pcloud_legacy_record($payload['source_id']??null)));
        if(($payload['operation']??'')==='legacy_restore')return rest_ensure_response(array('ok'=>true,'file'=>trb_crm_pcloud_legacy_restore($payload)));
        if(($payload['operation']??'')==='restore_candidate_file')return rest_ensure_response(array('ok'=>true,'file'=>trb_crm_pcloud_restore_candidate($payload)));
        if(($payload['operation']??'')==='restore_demo_file')return rest_ensure_response(array('ok'=>true,'file'=>trb_crm_pcloud_restore_demo($payload)));
        if(($payload['operation']??'')==='verify'){
            $paths=$payload['paths']??null;if(!is_array($paths)||count($paths)>20)throw new RuntimeException('PCLOUD_INVALID_BATCH');$checks=array();
            foreach($paths as $index=>$path){
                try{$result=trb_crm_pcloud_transport('HEAD',trb_crm_pcloud_path($path));$verified=$result['status']===200&&is_int($result['size'])&&$result['size']>0;$checks[$index]=array('path'=>$path,'verified'=>$verified,'status'=>$verified?'verified':'missing_or_unreachable','size'=>$result['size'],'http_status'=>$result['status'],'method'=>'portal_webdav_head','checked_at'=>gmdate('c'));}
                catch(Throwable $e){$checks[$index]=array('path'=>is_string($path)?$path:'','verified'=>false,'status'=>'unavailable','detail'=>$e->getMessage());}
            }
            return rest_ensure_response(array('ok'=>true,'checks'=>$checks));
        }
        if(($payload['operation']??'')==='archive'&&($payload['confirm']??false)===true)return rest_ensure_response(array('ok'=>true,'verification'=>trb_crm_pcloud_archive($payload)));
        return new WP_Error('trb_pcloud_operation','Operazione pCloud non consentita.',array('status'=>422));
    }catch(Throwable $e){return new WP_Error('trb_pcloud_failed',$e->getMessage(),array('status'=>502));}
}
