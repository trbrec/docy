<?php
/**
 * Plugin Name: TRB CRM Sync
 * Description: Sincronizzazione firmata fra CRM e Portale Artisti.
 * Version: 2026.08.30.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/trb-crm-sync-storage.php';

function trb_crm_sync_secret() {
	if ( defined( 'TRB_CRM_SYNC_SECRET' ) && TRB_CRM_SYNC_SECRET ) return (string) TRB_CRM_SYNC_SECRET;
	return (string) getenv( 'TRB_CRM_SYNC_SECRET' );
}

function trb_crm_sync_verify_request( WP_REST_Request $request ) {
	$secret    = trb_crm_sync_secret();
	$timestamp = (string) $request->get_header( 'x-trb-timestamp' );
	$signature = (string) $request->get_header( 'x-trb-signature' );
	$body      = (string) $request->get_body();
	if ( '' === $secret || ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > 300 ) return false;
	$expected = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	return '' !== $signature && hash_equals( $expected, $signature );
}

function trb_crm_sync_profile_key( $group ) {
	$map = array( 'DDS' => 'dds', 'DDB12' => 'ddb12', 'DDB' => 'ddb', 'DDB-TRB' => 'ddb_trb', 'TRB' => 'trb' );
	$group = strtoupper( trim( (string) $group ) );
	return isset( $map[ $group ] ) ? $map[ $group ] : '';
}

function trb_crm_sync_pending_key( $email ) {
	return 'trb_crm_pending_' . hash( 'sha256', strtolower( trim( (string) $email ) ) );
}

function trb_crm_sync_sanitize_releases( $items ) {
	$clean = array();
	foreach ( (array) $items as $item ) {
		if ( ! is_array( $item ) || in_array( sanitize_key( $item['status'] ?? '' ), array( 'taken_down', 'take_down' ), true ) ) continue;
		$status = sanitize_key( $item['status'] ?? '' );
		if ( ! in_array( $status, array( 'published', 'scheduled' ), true ) ) continue;
		$clean[] = array(
			'catalog_number' => sanitize_text_field( $item['catalog_number'] ?? '' ),
			'upc'            => sanitize_text_field( $item['upc'] ?? '' ),
			'title'          => sanitize_text_field( $item['title'] ?? '' ),
			'artist_credit'  => sanitize_text_field( $item['artist_credit'] ?? '' ),
			'release_date'   => sanitize_text_field( $item['release_date'] ?? '' ),
			'status'         => $status,
		);
	}
	return $clean;
}

function trb_crm_sync_apply_entitlement( $user_id, array $payload ) {
    $result = trb_crm_sync_atomic( 'user:' . absint( $user_id ), array( 'user' => array( absint( $user_id ) ) ), static function() use ( $user_id, $payload ) {
        return trb_crm_sync_persist_entitlement( $user_id, $payload );
    } );
    if ( is_wp_error( $result ) ) return $result;
    // Approval can send mail: run it only after the durable profile transaction.
    try {
        if ( function_exists( 'pw_new_user_approve' ) ) {
            $approval = pw_new_user_approve();
            if ( 'pending' === $approval->get_user_status( $user_id ) ) $approval->update_user_status( $user_id, 'approve' );
            if ( 'approved' !== $approval->get_user_status( $user_id ) ) return new WP_Error( 'trb_crm_approval_failed', 'Approvazione non confermata. Riprova sulla stessa pratica.', array( 'status' => 503 ) );
        }
    } catch ( Throwable $error ) { return new WP_Error( 'trb_crm_approval_failed', 'Approvazione non confermata. Riprova sulla stessa pratica.', array( 'status' => 503 ) ); }
    return $result;
}

function trb_crm_sync_persist_entitlement( $user_id, array $payload ) {
	$user = get_userdata( absint( $user_id ) );
	if ( ! $user ) return new WP_Error( 'trb_crm_user_missing', 'Account artista non trovato.', array( 'status' => 404 ) );
	if ( $user->has_cap( 'manage_options' ) ) return new WP_Error( 'trb_crm_admin_account', 'Account amministrativo non modificabile.', array( 'status' => 409 ) );
	$group       = strtoupper( sanitize_text_field( $payload['entitlement']['group_code'] ?? '' ) );
	$profile_key = trb_crm_sync_profile_key( $group );
	if ( ! $profile_key || ! function_exists( 'trb_portal_profiles' ) ) return new WP_Error( 'trb_crm_profile_missing', 'Profilo contrattuale del portale non disponibile.', array( 'status' => 503 ) );
	$profiles = (array) trb_portal_profiles();
	$profile  = isset( $profiles[ $profile_key ] ) ? (array) $profiles[ $profile_key ] : array();
	$role     = sanitize_key( $profile['role'] ?? '' );
	if ( ! $role || ! get_role( $role ) ) return new WP_Error( 'trb_crm_role_missing', 'Ruolo contrattuale non configurato.', array( 'status' => 503 ) );

	if ( function_exists( 'trb_portal_user_profile' ) ) {
		$current = trb_portal_user_profile( $user );
		if ( $current && $current !== $profile_key ) return new WP_Error( 'trb_crm_group_conflict', 'L’account possiede già un gruppo contrattuale differente.', array( 'status' => 409 ) );
	}

	$user->set_role( $role );
	global $wpdb;
	$stored_roles = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key=%s", $user->ID, $wpdb->get_blog_prefix() . 'capabilities' ) );
	if ( maybe_serialize( array( $role => true ) ) !== $stored_roles ) throw new RuntimeException( 'Role persistence failed.' );
	trb_crm_sync_write_meta( 'user', $user->ID, '_trb_crm_public_id', sanitize_text_field( $payload['practice']['public_id'] ?? '' ) );
	trb_crm_sync_write_meta( 'user', $user->ID, '_trb_crm_submission_id', absint( $payload['practice']['submission_id'] ?? 0 ) );
	trb_crm_sync_write_meta( 'user', $user->ID, '_trb_crm_contract_id', absint( $payload['practice']['contract_id'] ?? 0 ) );
	trb_crm_sync_write_meta( 'user', $user->ID, '_trb_crm_contract_number', sanitize_text_field( $payload['practice']['contract_number'] ?? '' ) );
	trb_crm_sync_write_meta( 'user', $user->ID, '_trb_crm_group_code', $group );
	trb_crm_sync_write_meta( 'user', $user->ID, '_trb_crm_activation_date', sanitize_text_field( $payload['entitlement']['activation_date'] ?? '' ) );
	trb_crm_sync_write_meta( 'user', $user->ID, '_trb_crm_historical_releases', trb_crm_sync_sanitize_releases( $payload['releases'] ?? array() ) );
	trb_crm_sync_write_meta( 'user', $user->ID, '_trb_crm_synced_at', time() );
	return array( 'user_id' => $user->ID, 'profile' => $profile_key, 'role' => $role );
}

function trb_crm_sync_receive_entitlement( WP_REST_Request $request ) {
	$payload = $request->get_json_params();
	if ( ! is_array( $payload ) ) return new WP_Error( 'trb_crm_invalid_json', 'Payload non valido.', array( 'status' => 422 ) );
	$email       = sanitize_email( $payload['artist']['email'] ?? '' );
	$eligible    = true === ( $payload['entitlement']['eligible'] ?? false );
	$portal      = sanitize_key( $payload['entitlement']['portal_status'] ?? '' );
	$contract    = sanitize_key( $payload['entitlement']['contract_status'] ?? '' );
	$profile_key = trb_crm_sync_profile_key( $payload['entitlement']['group_code'] ?? '' );
	if ( ! is_email( $email ) || ! $eligible || 'pronto' !== $portal || 'accettato' !== $contract || ! $profile_key ) {
		return new WP_Error( 'trb_crm_gate_rejected', 'Il semaforo contrattuale non consente l’attivazione.', array( 'status' => 422 ) );
	}
	$payload_hash = hash( 'sha256', (string) $request->get_body() );
	$idempotency  = sanitize_text_field( $request->get_header( 'idempotency-key' ) );
	if ( $idempotency && ! hash_equals( $payload_hash, $idempotency ) ) return new WP_Error( 'trb_crm_idempotency_mismatch', 'Chiave di idempotenza non coerente.', array( 'status' => 422 ) );
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
        $response = array( 'ok' => true, 'activated' => false, 'status' => 'pending_registration', 'reference' => 'pending:' . substr( $payload_hash, 0, 16 ) );
        $saved = trb_crm_sync_atomic( 'pending:' . strtolower( $email ), array(), static function() use ( $email, $payload, $payload_hash, $response ) {
            trb_crm_sync_write_option( trb_crm_sync_pending_key( $email ), array( 'payload' => $payload, 'payload_hash' => $payload_hash, 'received_at' => time() ) );
            trb_crm_sync_write_option( 'trb_crm_sync_' . $payload_hash, $response );
            return $response;
        } );
        if ( is_wp_error( $saved ) ) return $saved;
        // Registration can finish while the pending transaction is committing.
        $registered = get_user_by( 'email', $email );
        if ( $registered ) {
            trb_crm_sync_activate_registered_user( $registered->ID );
            $saved = get_option( 'trb_crm_sync_' . $payload_hash, $saved );
        }
        return rest_ensure_response( $saved );
	}
	$result = trb_crm_sync_apply_entitlement( $user->ID, $payload );
	if ( is_wp_error( $result ) ) return $result;
	$response = array( 'ok' => true, 'activated' => true, 'status' => 'active', 'reference' => 'user:' . absint( $user->ID ), 'profile' => $result['profile'] );
	$saved = trb_crm_sync_atomic( 'receipt:' . $payload_hash, array(), static function() use ( $payload_hash, $response ) { trb_crm_sync_write_option( 'trb_crm_sync_' . $payload_hash, $response ); return $response; } );
	return is_wp_error( $saved ) ? $saved : rest_ensure_response( $saved );
}

function trb_crm_sync_register_routes() {
	register_rest_route( 'trb-crm/v1', '/entitlement', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'trb_crm_sync_receive_entitlement',
		'permission_callback' => 'trb_crm_sync_verify_request',
	) );
}
add_action( 'rest_api_init', 'trb_crm_sync_register_routes' );

function trb_crm_sync_activate_registered_user( $user_id ) {
	$user = get_userdata( absint( $user_id ) );
	if ( ! $user || ! is_email( $user->user_email ) ) return;
	$key = trb_crm_sync_pending_key( $user->user_email );
	$pending = get_option( $key );
	if ( ! is_array( $pending ) || ! is_array( $pending['payload'] ?? null ) ) return;
	$result = trb_crm_sync_apply_entitlement( $user->ID, $pending['payload'] );
	if ( is_wp_error( $result ) ) {
		update_user_meta( $user->ID, '_trb_crm_activation_error', $result->get_error_code() );
		return;
	}
	$active_response = array( 'ok' => true, 'activated' => true, 'status' => 'active', 'reference' => 'user:' . absint( $user->ID ), 'profile' => $result['profile'] );
    $saved = trb_crm_sync_atomic( 'pending:' . strtolower( $user->user_email ), array( 'user' => array( $user->ID ) ), static function() use ( $pending, $active_response, $key, $user ) {
        if ( ! empty( $pending['payload_hash'] ) ) trb_crm_sync_write_option( 'trb_crm_sync_' . sanitize_text_field( $pending['payload_hash'] ), $active_response );
        trb_crm_sync_write_option( $key, null, true );
        trb_crm_sync_write_meta( 'user', $user->ID, '_trb_crm_activation_error', null, true );
        return true;
    } );
    if ( is_wp_error( $saved ) ) return;
	$ack = array(
		'practice_public_id' => sanitize_text_field( $pending['payload']['practice']['public_id'] ?? '' ),
		'contract_id'       => absint( $pending['payload']['practice']['contract_id'] ?? 0 ),
		'status'            => 'active',
		'reference'         => 'user:' . absint( $user->ID ),
	);
	if ( is_wp_error( trb_crm_sync_post_to_crm( '/webhooks/artist-portal/entitlement-ack', $ack ) ) ) {
		wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'trb_crm_sync_retry_ack', array( $ack, 1 ) );
	}
}
add_action( 'user_register', 'trb_crm_sync_activate_registered_user', 900 );

function trb_crm_sync_post_to_crm( $path, array $payload ) {
	$secret = trb_crm_sync_secret();
	$base   = defined( 'TRB_CRM_BASE_URL' ) ? (string) TRB_CRM_BASE_URL : ( (string) getenv( 'TRB_CRM_BASE_URL' ) ?: 'https://crm.trbrec.com' );
	$url    = untrailingslashit( $base ) . '/' . ltrim( (string) $path, '/' );
	$parts  = wp_parse_url( $url );
	if ( '' === $secret || 'https' !== ( $parts['scheme'] ?? '' ) || 'crm.trbrec.com' !== strtolower( $parts['host'] ?? '' ) ) return new WP_Error( 'trb_crm_endpoint_invalid', 'Endpoint CRM non configurato.' );
	$body      = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$timestamp = (string) time();
	$response  = wp_remote_post( $url, array(
		'timeout'     => 20,
		'redirection' => 0,
		'headers'     => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-TRB-Timestamp' => $timestamp, 'X-TRB-Signature' => 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret ) ),
		'body'        => $body,
	) );
	if ( is_wp_error( $response ) ) return $response;
	$code = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 || ! is_array( $data ) || true !== ( $data['ok'] ?? false ) ) return new WP_Error( 'trb_crm_remote_rejected', 'Il CRM non ha confermato la sincronizzazione.', array( 'status' => $code ) );
	return $data;
}

function trb_crm_sync_retry_ack( $payload, $attempt = 1 ) {
	if ( ! is_array( $payload ) ) return;
	$result = trb_crm_sync_post_to_crm( '/webhooks/artist-portal/entitlement-ack', $payload );
	$attempt = absint( $attempt );
	if ( is_wp_error( $result ) && $attempt < 5 ) wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS * ( 2 ** $attempt ) ), 'trb_crm_sync_retry_ack', array( $payload, $attempt + 1 ) );
}
add_action( 'trb_crm_sync_retry_ack', 'trb_crm_sync_retry_ack', 10, 2 );

function trb_crm_sync_release_workflow_status( $release_id ) {
	$pipeline = sanitize_key( get_post_meta( $release_id, '_trb_release_pipeline_status', true ) );
	$status   = sanitize_key( get_post_meta( $release_id, '_trb_release_status', true ) );
	if ( in_array( $pipeline, array( 'cancelled', 'rejected', 'security_rejected' ), true ) || in_array( $status, array( 'cancelled', 'rejected' ), true ) ) return 'cancelled';
	if ( in_array( $pipeline, array( 'published', 'online', 'live' ), true ) || 'published' === $status ) return 'published';
	if ( in_array( $pipeline, array( 'scheduled', 'delivery_scheduled' ), true ) || 'scheduled' === $status ) return 'scheduled';
	if ( in_array( $pipeline, array( 'ready', 'approved', 'delivery_ready' ), true ) ) return 'ready';
	if ( in_array( $pipeline, array( '', 'draft', 'artist_action' ), true ) && in_array( $status, array( '', 'draft', 'artist_action' ), true ) ) return 'draft';
	return 'in_progress';
}

function trb_crm_sync_decode_meta_value( $value ) {
	if ( ! is_string( $value ) ) return $value;
	$value = trim( $value );
	if ( '' === $value ) return '';
	$decoded = trb_crm_sync_decode_serialized( $value );
	if ( $decoded !== $value ) return $decoded;
	if ( in_array( substr( $value, 0, 1 ), array( '{', '[' ), true ) ) {
		$json = json_decode( $value, true );
		if ( JSON_ERROR_NONE === json_last_error() ) return $json;
	}
	return $value;
}

function trb_crm_sync_safe_meta_map( $rows ) {
	$clean = array();
	foreach ( (array) $rows as $key => $values ) {
		$key = (string) $key;
		if ( '' === $key || preg_match( '/secret|token|password|nonce|signature|session/i', $key ) ) continue;
		$decoded = array_map( 'trb_crm_sync_decode_meta_value', (array) $values );
		$clean[ $key ] = 1 === count( $decoded ) ? reset( $decoded ) : array_values( $decoded );
	}
	return $clean;
}

function trb_crm_sync_user_profile( $user_id ) {
	$meta = trb_crm_sync_safe_meta_map( get_user_meta( absint( $user_id ) ) );
	$pick = static function ( array $keys ) use ( $meta ) {
		foreach ( $keys as $key ) if ( array_key_exists( $key, $meta ) ) return $meta[ $key ];
		return null;
	};
	return array_filter( array(
		'phone'           => $pick( array( '_trb_artist_phone', 'phone', 'billing_phone' ) ),
		'birth_date'      => $pick( array( '_trb_artist_birth_date', 'birth_date' ) ),
		'birth_place'     => $pick( array( '_trb_artist_birth_place', 'birth_place' ) ),
		'birth_province'  => $pick( array( '_trb_artist_birth_province', 'birth_province' ) ),
		'tax_code'        => $pick( array( '_trb_artist_tax_code', 'tax_code', 'codice_fiscale' ) ),
		'street'          => $pick( array( '_trb_artist_street', 'billing_address_1', 'street' ) ),
		'street_number'   => $pick( array( '_trb_artist_street_number', 'street_number' ) ),
		'postal_code'     => $pick( array( '_trb_artist_postal_code', 'billing_postcode', 'postal_code' ) ),
		'city'            => $pick( array( '_trb_artist_city', 'billing_city', 'city' ) ),
		'province'        => $pick( array( '_trb_artist_province', 'billing_state', 'province' ) ),
		'country'         => $pick( array( '_trb_artist_country', 'billing_country', 'country' ) ),
		'document_number' => $pick( array( '_trb_artist_document_number', 'document_number' ) ),
		'document_expiry' => $pick( array( '_trb_artist_document_expiry', 'document_expiry' ) ),
		'live_fee'        => $pick( array( '_trb_artist_live_fee', 'live_fee' ) ),
		'spotify_url'     => $pick( array( '_trb_artist_spotify_url', 'spotify_url' ) ),
		'youtube_url'     => $pick( array( '_trb_artist_youtube_url', 'youtube_url' ) ),
		'instagram_url'   => $pick( array( '_trb_artist_instagram_url', 'instagram_url' ) ),
		'facebook_url'    => $pick( array( '_trb_artist_facebook_url', 'facebook_url' ) ),
	), static fn( $value ) => null !== $value && '' !== $value );
}

function trb_crm_sync_send_release( $release_id, $attempt = 0 ) {
	$release = get_post( absint( $release_id ) );
	if ( ! $release || 'trb_release' !== $release->post_type ) return;
	if ( 'trash' === $release->post_status ) return;
	$workflow = trb_crm_sync_release_workflow_status( $release->ID );
	$pipeline = sanitize_key( get_post_meta( $release->ID, '_trb_release_pipeline_status', true ) );
	$status   = sanitize_key( get_post_meta( $release->ID, '_trb_release_status', true ) );
	if ( in_array( $pipeline, array( 'take_down', 'taken_down' ), true ) || in_array( $status, array( 'take_down', 'taken_down' ), true ) ) return;
	$user = get_userdata( $release->post_author );
	if ( ! $user || ! is_email( $user->user_email ) ) return;
	$credit = function_exists( 'trb_portal_artist_profile_value' ) ? trb_portal_artist_profile_value( 'artist_name', $user->ID ) : '';
	if ( ! $credit ) $credit = $user->display_name;
	$date = sanitize_text_field( get_post_meta( $release->ID, '_trb_release_date', true ) );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) $date = null;
	$post_meta = trb_crm_sync_safe_meta_map( get_post_meta( $release->ID ) );
	$user_meta = trb_crm_sync_safe_meta_map( get_user_meta( $user->ID ) );
	$payload = array(
		'portal_release_id'   => 'artist:' . (string) $release->ID,
		'practice_public_id'  => sanitize_text_field( get_user_meta( $user->ID, '_trb_crm_public_id', true ) ),
		'email'               => strtolower( $user->user_email ),
		'release_title'       => sanitize_text_field( $release->post_title ),
		'artist_credit'       => sanitize_text_field( $credit ),
		'workflow_status'     => $workflow,
		'planned_release_date'=> $date,
		'catalog_number'      => sanitize_text_field( get_post_meta( $release->ID, '_trb_release_catalog_number', true ) ),
		'upc'                 => sanitize_text_field( get_post_meta( $release->ID, '_trb_release_upc', true ) ),
		'portal_updated_at'   => get_post_modified_time( 'Y-m-d H:i:s', true, $release ),
		'metadata'            => array(
			'source'             => 'artist_portal_signed_connector',
			'portal_post_id'     => $release->ID,
			'pipeline_status'    => $pipeline,
			'portal_release_status' => $status,
			'post_status'        => $release->post_status,
			'release_type'       => trb_crm_sync_decode_meta_value( get_post_meta( $release->ID, '_trb_release_type', true ) ),
			'artist_profile'     => trb_crm_sync_user_profile( $user->ID ),
			'tracks'             => trb_crm_sync_decode_meta_value( get_post_meta( $release->ID, '_trb_release_tracks', true ) ),
			'files'              => trb_crm_sync_decode_meta_value( get_post_meta( $release->ID, '_trb_release_files', true ) ),
			'pcloud_archive'     => trb_crm_sync_decode_meta_value( get_post_meta( $release->ID, '_trb_release_pcloud_archive', true ) ),
			'contract'           => trb_crm_sync_decode_meta_value( get_post_meta( $release->ID, '_trb_release_contract', true ) ),
			'rights_declarations'=> trb_crm_sync_decode_meta_value( get_post_meta( $release->ID, '_trb_release_rights_declarations', true ) ),
			'analysis_decision'  => trb_crm_sync_decode_meta_value( get_post_meta( $release->ID, '_trb_release_analysis_decision', true ) ),
			'technical_analysis' => trb_crm_sync_decode_meta_value( get_post_meta( $release->ID, '_trb_release_technical_analysis', true ) ),
			'portal_post_meta'   => $post_meta,
			'portal_user_meta'   => $user_meta,
			'automatic_actions_started' => false,
		),
	);
	$result = trb_crm_sync_post_to_crm( '/webhooks/artist-portal/release', $payload );
	if ( is_wp_error( $result ) ) {
		update_post_meta( $release->ID, '_trb_crm_last_sync_error', $result->get_error_code() );
		$attempt = absint( $attempt );
		if ( $attempt < 5 ) wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS * ( 2 ** $attempt ) ), 'trb_crm_sync_release', array( $release->ID, $attempt + 1 ) );
		return;
	}
	delete_post_meta( $release->ID, '_trb_crm_last_sync_error' );
	update_post_meta( $release->ID, '_trb_crm_last_synced_at', time() );
}
add_action( 'trb_crm_sync_release', 'trb_crm_sync_send_release', 10, 2 );

function trb_crm_sync_schedule_release( $release_id ) {
	$release_id = absint( $release_id );
	if ( ! $release_id || wp_next_scheduled( 'trb_crm_sync_release', array( $release_id, 0 ) ) ) return;
	wp_schedule_single_event( time() + 10, 'trb_crm_sync_release', array( $release_id, 0 ) );
}
add_action( 'save_post_trb_release', 'trb_crm_sync_schedule_release', 100 );

function trb_crm_sync_release_meta_changed( $meta_id, $object_id, $meta_key ) {
	if ( in_array( $meta_key, array( '_trb_release_pipeline_status', '_trb_release_status', '_trb_release_date', '_trb_release_catalog_number', '_trb_release_upc' ), true ) ) trb_crm_sync_schedule_release( $object_id );
}
add_action( 'added_post_meta', 'trb_crm_sync_release_meta_changed', 100, 3 );
add_action( 'updated_post_meta', 'trb_crm_sync_release_meta_changed', 100, 3 );

function trb_crm_sync_all_current_releases() {
	$ids = get_posts( array( 'post_type' => 'trb_release', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
	foreach ( $ids as $release_id ) trb_crm_sync_send_release( absint( $release_id ), 0 );
}
add_action( 'trb_crm_sync_hourly_releases', 'trb_crm_sync_all_current_releases' );

function trb_crm_sync_activate() {
	if ( ! wp_next_scheduled( 'trb_crm_sync_hourly_releases' ) ) wp_schedule_event( time() + 60, 'hourly', 'trb_crm_sync_hourly_releases' );
}
register_activation_hook( __FILE__, 'trb_crm_sync_activate' );
add_action( 'init', 'trb_crm_sync_activate' );

function trb_crm_sync_deactivate() {
	$timestamp = wp_next_scheduled( 'trb_crm_sync_hourly_releases' );
	if ( $timestamp ) wp_unschedule_event( $timestamp, 'trb_crm_sync_hourly_releases' );
}
register_deactivation_hook( __FILE__, 'trb_crm_sync_deactivate' );
