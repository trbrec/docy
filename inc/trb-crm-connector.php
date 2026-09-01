<?php
/**
 * General, incremental Artist Portal -> CRM extension connector.
 *
 * The established release synchronizer remains the only owner of release
 * reconciliation. This extension covers the missing artist-account and demo
 * entities, sends versioned snapshots and never moves or deletes source files.
 * Delivery is at-least-once; the CRM acknowledges every event and de-duplicates
 * it by immutable event id and payload hash.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const TRB_CRM_CONNECTOR_SCHEMA = '2026-09-01.1';
const TRB_CRM_CONNECTOR_SOURCE = 'artist.trbrec.com';

function trb_crm_connector_settings() {
	$stored = (array) get_option( 'trb_crm_connector_settings', array() );
	$endpoint = defined( 'TRB_CRM_SYNC_URL' ) ? TRB_CRM_SYNC_URL : getenv( 'TRB_CRM_SYNC_URL' );
	$secret = defined( 'TRB_CRM_SYNC_SECRET' ) ? TRB_CRM_SYNC_SECRET : getenv( 'TRB_CRM_SYNC_SECRET' );
	if ( ! $secret && function_exists( 'trb_crm_sync_secret' ) ) $secret = trb_crm_sync_secret();
	if ( ! $endpoint && function_exists( 'trb_crm_sync_base_url' ) ) $endpoint = rtrim( (string) trb_crm_sync_base_url(), '/' ) . '/webhooks/artist-portal/sync';
	if ( ! $endpoint ) $endpoint = isset( $stored['endpoint'] ) ? $stored['endpoint'] : 'https://crm.trbrec.com/webhooks/artist-portal/sync';
	if ( ! $secret ) $secret = isset( $stored['secret'] ) ? $stored['secret'] : get_option( 'trb_crm_sync_secret', '' );
	return array(
		'endpoint' => esc_url_raw( trim( (string) $endpoint ) ),
		'secret'   => trim( (string) $secret ),
		'enabled'  => ! isset( $stored['enabled'] ) || (bool) $stored['enabled'],
	);
}

function trb_crm_connector_table() {
	global $wpdb;
	return $wpdb->prefix . 'trb_crm_sync_outbox';
}

function trb_crm_connector_install() {
	global $wpdb;
	if ( TRB_CRM_CONNECTOR_SCHEMA === get_option( 'trb_crm_connector_schema' ) ) return;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = trb_crm_connector_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_id char(64) NOT NULL,
		entity_type varchar(32) NOT NULL,
		external_id varchar(120) NOT NULL,
		operation varchar(16) NOT NULL DEFAULT 'upsert',
		entity_version bigint(20) unsigned NOT NULL,
		payload longtext NOT NULL,
		payload_hash char(64) NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'queued',
		attempts smallint(5) unsigned NOT NULL DEFAULT 0,
		next_attempt_at datetime NOT NULL,
		last_http_code smallint(5) unsigned DEFAULT NULL,
		last_error varchar(1000) DEFAULT NULL,
		created_at datetime NOT NULL,
		acknowledged_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY event_id (event_id),
		KEY delivery_queue (status,next_attempt_at,id),
		KEY entity_lookup (entity_type,external_id,id)
	) {$charset};" );
	update_option( 'trb_crm_connector_schema', TRB_CRM_CONNECTOR_SCHEMA, false );
	if ( false === get_option( 'trb_crm_bootstrap_state', false ) ) {
		update_option( 'trb_crm_bootstrap_state', array( 'phase' => 'artists', 'artist_page' => 1, 'demo_page' => 1, 'complete' => false ), false );
	}
}
add_action( 'init', 'trb_crm_connector_install', 2 );

function trb_crm_connector_schedules( $schedules ) {
	$schedules['trb_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Ogni cinque minuti' );
	return $schedules;
}
add_filter( 'cron_schedules', 'trb_crm_connector_schedules' );

function trb_crm_connector_schedule() {
	if ( ! wp_next_scheduled( 'trb_crm_connector_tick' ) ) wp_schedule_event( time() + MINUTE_IN_SECONDS, 'trb_five_minutes', 'trb_crm_connector_tick' );
}
add_action( 'init', 'trb_crm_connector_schedule', 20 );

function trb_crm_connector_version() {
	global $wpdb;
	$micros = (int) floor( microtime( true ) * 1000000 );
	return max( $micros, (int) get_option( 'trb_crm_last_entity_version', 0 ) + 1 );
}

function trb_crm_connector_clean( $value, $depth = 0 ) {
	if ( $depth > 8 ) return null;
	if ( is_object( $value ) ) $value = (array) $value;
	if ( is_array( $value ) ) {
		$clean = array();
		foreach ( $value as $key => $item ) {
			$name = (string) $key;
			if ( preg_match( '/secret|token|password|nonce|session|private.?key|api.?key|local.?path|tmp.?name|^(?:path|file_path|filesystem_path|staging_path|directory)$/i', $name ) ) continue;
			$clean[ $key ] = trb_crm_connector_clean( $item, $depth + 1 );
		}
		return $clean;
	}
	if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) return $value;
	return wp_check_invalid_utf8( (string) $value, true );
}

function trb_crm_connector_profile_payload( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) return null;
	$profile = array();
	$fields = function_exists( 'trb_portal_artist_profile_fields' ) ? array_keys( trb_portal_artist_profile_fields() ) : array();
	foreach ( $fields as $field ) $profile[ $field ] = (string) get_user_meta( $user_id, '_trb_artist_' . $field, true );
	foreach ( array( 'spotify_new', 'youtube_none', 'soundcloud_none', 'invoice_requested' ) as $choice ) $profile[ $choice ] = '1' === get_user_meta( $user_id, '_trb_artist_' . $choice, true );
	$profile['contract_profile'] = function_exists( 'trb_portal_user_profile' ) ? trb_portal_user_profile( $user ) : (string) get_user_meta( $user_id, '_trb_artist_contract_profile', true );
	$profile['preliminary_contract'] = (string) get_user_meta( $user_id, '_trb_artist_preliminary_contract', true );
	$profile['contract_term'] = (string) get_user_meta( $user_id, '_trb_artist_contract_term', true );
	$profile['profile_complete'] = function_exists( 'trb_portal_artist_profile_is_complete' ) ? trb_portal_artist_profile_is_complete( $user_id ) : null;
	return trb_crm_connector_clean( array(
		'user_id' => (int) $user_id,
		'email' => strtolower( (string) $user->user_email ),
		'display_name' => (string) $user->display_name,
		'first_name' => (string) get_user_meta( $user_id, 'first_name', true ),
		'last_name' => (string) get_user_meta( $user_id, 'last_name', true ),
		'registered_at' => get_date_from_gmt( $user->user_registered, 'Y-m-d H:i:s' ),
		'roles' => array_values( (array) $user->roles ),
		'profile' => $profile,
	) );
}

function trb_crm_connector_demo_payload( $request_id ) {
	$post = get_post( $request_id );
	$payload = get_post_meta( $request_id, '_trb_demo_payload', true );
	if ( ! $post || ! is_array( $payload ) ) return null;
	$remote = (array) get_post_meta( $request_id, '_trb_demo_remote', true );
	return trb_crm_connector_clean( array(
		'request_id' => (int) $request_id,
		'user_id' => (int) $post->post_author,
		'title' => isset( $payload['title'] ) ? $payload['title'] : $post->post_title,
		'genre' => isset( $payload['genre'] ) ? $payload['genre'] : '',
		'artist_name' => isset( $payload['artist_name'] ) ? $payload['artist_name'] : '',
		'email' => isset( $payload['email'] ) ? strtolower( $payload['email'] ) : '',
		'status' => isset( $payload['status'] ) ? $payload['status'] : 'queued',
		'has_text' => ! empty( $payload['text_file'] ),
		'has_audio' => ! empty( $payload['audio_file'] ),
		'submitted_at' => isset( $payload['submitted_at'] ) ? (int) $payload['submitted_at'] : strtotime( $post->post_date_gmt . ' UTC' ),
		'earliest_delivery' => (int) get_post_meta( $request_id, '_trb_demo_earliest_delivery', true ),
		'pcloud' => array_intersect_key( $remote, array_flip( array( 'folder', 'files', 'manifest', 'uploaded_at' ) ) ),
		'sheet_synced' => (bool) get_post_meta( $request_id, '_trb_demo_sheet_synced', true ),
		'review_ready' => (bool) get_post_meta( $request_id, '_trb_demo_review', true ),
		'email_sent' => 'sent' === ( isset( $payload['status'] ) ? $payload['status'] : '' ),
		'updated_at' => (string) $post->post_modified_gmt,
	) );
}

function trb_crm_connector_queue( $entity_type, $external_id, $payload = null, $operation = 'upsert' ) {
	global $wpdb;
	trb_crm_connector_install();
	$entity_type = sanitize_key( $entity_type );
	$external_id = sanitize_text_field( (string) $external_id );
	if ( ! in_array( $entity_type, array( 'artist', 'demo' ), true ) || '' === $external_id ) return false;
	if ( null === $payload && 'delete' !== $operation ) {
		if ( 'artist' === $entity_type ) $payload = trb_crm_connector_profile_payload( absint( $external_id ) );
		else $payload = trb_crm_connector_demo_payload( absint( $external_id ) );
	}
	if ( null === $payload ) return false;
	$version = trb_crm_connector_version();
	update_option( 'trb_crm_last_entity_version', $version, false );
	$payload_json = wp_json_encode( trb_crm_connector_clean( $payload ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$hash = hash( 'sha256', (string) $payload_json );
	$event_id = hash( 'sha256', implode( '|', array( TRB_CRM_CONNECTOR_SOURCE, $entity_type, $external_id, $operation, $version, $hash ) ) );
	// Keep the audit trail but deliver only the newest unsent snapshot for an
	// entity. A demo processing step can update many post-meta rows in one request.
	$wpdb->query( $wpdb->prepare( "UPDATE " . trb_crm_connector_table() . " SET status='superseded' WHERE entity_type=%s AND external_id=%s AND status IN ('queued','retry')", $entity_type, $external_id ) );
	$inserted = $wpdb->insert( trb_crm_connector_table(), array(
		'event_id' => $event_id, 'entity_type' => $entity_type, 'external_id' => $external_id,
		'operation' => $operation, 'entity_version' => $version, 'payload' => $payload_json,
		'payload_hash' => $hash, 'status' => 'queued', 'attempts' => 0,
		'next_attempt_at' => current_time( 'mysql', true ), 'created_at' => current_time( 'mysql', true ),
	), array( '%s','%s','%s','%s','%d','%s','%s','%s','%d','%s','%s' ) );
	return false !== $inserted;
}

function trb_crm_connector_profile_saved( $user_id ) { trb_crm_connector_queue( 'artist', $user_id ); }
add_action( 'trb_portal_artist_profile_saved', 'trb_crm_connector_profile_saved', 50 );
add_action( 'user_register', 'trb_crm_connector_profile_saved', 50 );
add_action( 'profile_update', 'trb_crm_connector_profile_saved', 50 );

function trb_crm_connector_post_change( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) ) return;
	if ( 'trb_request' === $post->post_type && get_post_meta( $post_id, '_trb_demo_payload', true ) ) trb_crm_connector_queue( 'demo', $post_id );
}
add_action( 'save_post', 'trb_crm_connector_post_change', 100, 2 );

function trb_crm_connector_meta_change( $meta_id, $object_id, $meta_key ) {
	if ( 0 === strpos( (string) $meta_key, '_trb_demo_' ) ) trb_crm_connector_queue( 'demo', $object_id );
}
add_action( 'added_post_meta', 'trb_crm_connector_meta_change', 100, 3 );
add_action( 'updated_post_meta', 'trb_crm_connector_meta_change', 100, 3 );
add_action( 'deleted_post_meta', 'trb_crm_connector_meta_change', 100, 3 );

function trb_crm_connector_before_delete( $post_id, $post ) {
	if ( ! $post ) return;
	if ( 'trb_request' === $post->post_type && get_post_meta( $post_id, '_trb_demo_payload', true ) ) trb_crm_connector_queue( 'demo', $post_id, array( 'request_id' => (int) $post_id, 'title' => $post->post_title, 'updated_at' => current_time( 'mysql', true ) ), 'delete' );
}
add_action( 'before_delete_post', 'trb_crm_connector_before_delete', 10, 2 );

function trb_crm_connector_bootstrap( $limit = 50 ) {
	$state = (array) get_option( 'trb_crm_bootstrap_state', array() );
	if ( ! empty( $state['complete'] ) ) return $state;
	$phase = isset( $state['phase'] ) ? $state['phase'] : 'artists';
	if ( 'artists' === $phase ) {
		$page = max( 1, absint( isset( $state['artist_page'] ) ? $state['artist_page'] : 1 ) );
		$users = get_users( array( 'number' => $limit, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
		foreach ( $users as $user_id ) trb_crm_connector_queue( 'artist', $user_id );
		if ( count( $users ) < $limit ) { $state['phase'] = 'demos'; $state['demo_page'] = 1; } else $state['artist_page'] = $page + 1;
	} else {
		$page = max( 1, absint( isset( $state['demo_page'] ) ? $state['demo_page'] : 1 ) );
		$ids = get_posts( array( 'post_type' => 'trb_request', 'post_status' => 'any', 'posts_per_page' => $limit, 'paged' => $page, 'fields' => 'ids', 'meta_key' => '_trb_demo_payload', 'orderby' => 'ID', 'order' => 'ASC' ) );
		foreach ( $ids as $id ) trb_crm_connector_queue( 'demo', $id );
		if ( count( $ids ) < $limit ) { $state['complete'] = true; $state['phase'] = 'complete'; $state['completed_at'] = current_time( 'mysql', true ); } else $state['demo_page'] = $page + 1;
	}
	update_option( 'trb_crm_bootstrap_state', $state, false );
	return $state;
}

function trb_crm_connector_deliver( $limit = 50 ) {
	global $wpdb;
	$settings = trb_crm_connector_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['endpoint'] ) || strlen( $settings['secret'] ) < 32 ) return array( 'ok' => false, 'code' => 'not_configured' );
	$parts = wp_parse_url( $settings['endpoint'] );
	if ( 'https' !== ( isset( $parts['scheme'] ) ? $parts['scheme'] : '' ) || 'crm.trbrec.com' !== strtolower( isset( $parts['host'] ) ? $parts['host'] : '' ) ) return array( 'ok' => false, 'code' => 'invalid_endpoint' );
	$table = trb_crm_connector_table();
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status IN ('queued','retry') AND next_attempt_at<=UTC_TIMESTAMP() ORDER BY id ASC LIMIT %d", max( 1, min( 100, $limit ) ) ), ARRAY_A );
	if ( ! $rows ) return array( 'ok' => true, 'sent' => 0, 'pending' => 0 );
	$events = array();
	foreach ( $rows as $row ) $events[] = array( 'event_id' => $row['event_id'], 'entity_type' => $row['entity_type'], 'external_id' => $row['external_id'], 'operation' => $row['operation'], 'version' => (int) $row['entity_version'], 'payload_hash' => $row['payload_hash'], 'payload' => json_decode( $row['payload'], true ) );
	$batch_id = wp_generate_uuid4();
	$body = wp_json_encode( array( 'schema_version' => TRB_CRM_CONNECTOR_SCHEMA, 'source' => TRB_CRM_CONNECTOR_SOURCE, 'batch_id' => $batch_id, 'sent_at' => current_time( 'mysql', true ), 'events' => $events ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$timestamp = (string) time();
	$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, $settings['secret'] );
	$response = wp_remote_post( $settings['endpoint'], array( 'timeout' => 45, 'redirection' => 0, 'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-TRB-Timestamp' => $timestamp, 'X-TRB-Signature' => 'sha256=' . $signature, 'Idempotency-Key' => hash( 'sha256', $batch_id . '|' . $body ) ), 'body' => $body ) );
	$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$decoded = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
	$ack = array();
	if ( is_array( $decoded ) && ! empty( $decoded['results'] ) ) foreach ( $decoded['results'] as $result ) if ( ! empty( $result['event_id'] ) && in_array( $result['status'], array( 'applied','duplicate','stale' ), true ) ) $ack[ $result['event_id'] ] = true;
	$sent = 0;
	foreach ( $rows as $row ) {
		if ( isset( $ack[ $row['event_id'] ] ) ) {
			$wpdb->update( $table, array( 'status' => 'acknowledged', 'attempts' => (int) $row['attempts'] + 1, 'last_http_code' => $code, 'last_error' => null, 'acknowledged_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );
			$sent++;
		} else {
			$attempts = (int) $row['attempts'] + 1;
			$delay = min( 6 * HOUR_IN_SECONDS, 5 * MINUTE_IN_SECONDS * ( 2 ** min( 6, $attempts - 1 ) ) );
			$error = is_wp_error( $response ) ? $response->get_error_message() : ( is_array( $decoded ) && ! empty( $decoded['error'] ) ? $decoded['error'] : 'HTTP ' . $code . ': conferma evento mancante' );
			$wpdb->update( $table, array( 'status' => 'retry', 'attempts' => $attempts, 'last_http_code' => $code, 'last_error' => mb_substr( $error, 0, 1000 ), 'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ) ), array( 'id' => (int) $row['id'] ) );
		}
	}
	update_option( 'trb_crm_connector_last_run', array( 'at' => current_time( 'mysql', true ), 'http_code' => $code, 'batch_id' => $batch_id, 'selected' => count( $rows ), 'acknowledged' => $sent ), false );
	return array( 'ok' => $sent === count( $rows ), 'sent' => $sent, 'selected' => count( $rows ), 'http_code' => $code );
}

function trb_crm_connector_tick() {
	if ( get_transient( 'trb_crm_connector_lock' ) ) return;
	set_transient( 'trb_crm_connector_lock', 1, 4 * MINUTE_IN_SECONDS );
	try { trb_crm_connector_bootstrap( 50 ); trb_crm_connector_deliver( 50 ); }
	finally { delete_transient( 'trb_crm_connector_lock' ); }
}
add_action( 'trb_crm_connector_tick', 'trb_crm_connector_tick' );

function trb_crm_connector_health() {
	global $wpdb;
	$settings = trb_crm_connector_settings();
	$table = trb_crm_connector_table();
	$counts = array( 'queued' => 0, 'retry' => 0, 'acknowledged' => 0 );
	if ( trb_crm_connector_table_exists() ) foreach ( $wpdb->get_results( "SELECT status,COUNT(*) total FROM {$table} GROUP BY status", ARRAY_A ) as $row ) $counts[ $row['status'] ] = (int) $row['total'];
	$next = wp_next_scheduled( 'trb_crm_connector_tick' );
	return array( 'ready' => ! empty( $settings['enabled'] ) && ! empty( $settings['endpoint'] ) && strlen( $settings['secret'] ) >= 32 && (bool) $next, 'schema_version' => TRB_CRM_CONNECTOR_SCHEMA, 'source' => TRB_CRM_CONNECTOR_SOURCE, 'scope' => array( 'artist', 'demo' ), 'release_sync_owner' => 'trb-z-crm-release-sync-r26.php', 'endpoint_host' => wp_parse_url( $settings['endpoint'], PHP_URL_HOST ), 'secret_configured' => strlen( $settings['secret'] ) >= 32, 'schedule_seconds' => 300, 'next_run_at' => $next ? gmdate( 'c', $next ) : null, 'queue' => $counts, 'bootstrap' => get_option( 'trb_crm_bootstrap_state', array() ), 'last_run' => get_option( 'trb_crm_connector_last_run', array() ) );
}

function trb_crm_connector_table_exists() {
	global $wpdb;
	$table = trb_crm_connector_table();
	return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
}

function trb_crm_connector_health_route() {
	register_rest_route( 'trb/v1', '/crm-sync-health', array( 'methods' => WP_REST_Server::READABLE, 'callback' => function() { return rest_ensure_response( trb_crm_connector_health() ); }, 'permission_callback' => '__return_true' ) );
}
add_action( 'rest_api_init', 'trb_crm_connector_health_route' );
