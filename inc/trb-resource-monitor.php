<?php
/** Independent TRB monitoring for provider budgets, quotas and release jobs. */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TRB_RESOURCE_MONITOR_VERSION', '1.0.0' );

function trb_resource_settings() {
	$defaults = array(
		'admin_email' => 'info@trbrec.com',
		'acr_enabled' => 0, 'acr_paid_confirmed' => 0, 'acr_token' => '', 'acr_container_id' => '', 'acr_region' => 'eu-west-1',
		'acr_monthly_budget' => 5.00, 'acr_fingerprint_max' => 0.05, 'acr_deepright_minute_max' => 0.001,
		'acr_cover_minute_max' => 0.001, 'acr_metadata_call_max' => 0.01, 'acr_engine' => 3, 'acr_deepright' => 1,
		'acr_excerpt_seconds' => 90, 'acr_excerpt_offset' => 30,
		'pcloud_api_host' => 'https://eapi.pcloud.com', 'pcloud_auth_token' => '', 'pcloud_safety_bytes' => 1073741824,
		'pcloud_warning_1' => 70, 'pcloud_warning_2' => 85, 'pcloud_warning_3' => 95, 'pcloud_block' => 98,
		'temp_warning_1' => 70, 'temp_warning_2' => 85, 'temp_block' => 95, 'temp_file_multiplier' => 2.5,
		'email_daily_limit' => 200,
	);
	$saved = get_option( 'trb_resource_monitor_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

function trb_resource_tables() {
	global $wpdb;
	return array(
		'usage' => $wpdb->prefix . 'trb_usage_ledger',
		'events' => $wpdb->prefix . 'trb_resource_events',
		'notifications' => $wpdb->prefix . 'trb_notification_queue',
	);
}

function trb_resource_install() {
	global $wpdb;
	$tables = trb_resource_tables();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( "CREATE TABLE {$tables['usage']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		provider varchar(32) NOT NULL,
		service varchar(64) NOT NULL,
		period_key varchar(10) NOT NULL,
		idempotency_key varchar(191) NOT NULL,
		release_id bigint(20) unsigned NOT NULL DEFAULT 0,
		track_index int unsigned NOT NULL DEFAULT 0,
		file_hash char(64) NOT NULL DEFAULT '',
		units decimal(18,6) NOT NULL DEFAULT 0,
		cost_max decimal(18,6) NOT NULL DEFAULT 0,
		cost_estimated decimal(18,6) NOT NULL DEFAULT 0,
		cost_actual decimal(18,6) NULL,
		status varchar(40) NOT NULL,
		provider_reference varchar(191) NOT NULL DEFAULT '',
		attempts int unsigned NOT NULL DEFAULT 0,
		last_error text NULL,
		payload longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY (id), UNIQUE KEY idempotency_key (idempotency_key), KEY provider_period (provider,period_key), KEY release_id (release_id)
	) $charset;" );
	dbDelta( "CREATE TABLE {$tables['events']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_key varchar(191) NOT NULL,
		resource varchar(40) NOT NULL,
		severity varchar(20) NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'open',
		message text NOT NULL,
		context longtext NULL,
		first_seen datetime NOT NULL,
		last_seen datetime NOT NULL,
		occurrences int unsigned NOT NULL DEFAULT 1,
		PRIMARY KEY (id), UNIQUE KEY event_key (event_key), KEY status (status), KEY resource (resource)
	) $charset;" );
	dbDelta( "CREATE TABLE {$tables['notifications']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_key varchar(191) NOT NULL,
		recipient varchar(191) NOT NULL,
		subject text NOT NULL,
		body longtext NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		attempts int unsigned NOT NULL DEFAULT 0,
		last_error text NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY (id), UNIQUE KEY event_key (event_key), KEY status (status)
	) $charset;" );
	update_option( 'trb_resource_monitor_db_version', TRB_RESOURCE_MONITOR_VERSION, false );
}
add_action( 'after_switch_theme', 'trb_resource_install' );
add_action( 'init', function() {
	if ( TRB_RESOURCE_MONITOR_VERSION !== get_option( 'trb_resource_monitor_db_version' ) ) trb_resource_install();
} );

function trb_resource_period_key() { return wp_date( 'Y-m' ); }
function trb_resource_now() { return current_time( 'mysql', true ); }

function trb_resource_event( $key, $resource, $severity, $message, $context = array() ) {
	global $wpdb;
	$table = trb_resource_tables()['events'];
	$key = sanitize_key( $resource ) . ':' . sanitize_text_field( $key );
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,occurrences FROM $table WHERE event_key=%s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$data = array( 'resource' => sanitize_key( $resource ), 'severity' => sanitize_key( $severity ), 'status' => 'open', 'message' => sanitize_text_field( $message ), 'context' => wp_json_encode( $context ), 'last_seen' => trb_resource_now() );
	if ( $existing ) {
		$data['occurrences'] = (int) $existing->occurrences + 1;
		$wpdb->update( $table, $data, array( 'id' => $existing->id ) );
		return (int) $existing->id;
	}
	$data['event_key'] = $key; $data['first_seen'] = trb_resource_now();
	$wpdb->insert( $table, $data );
	return (int) $wpdb->insert_id;
}

function trb_resource_queue_email( $event_key, $subject, $body, $priority = false ) {
	global $wpdb;
	$settings = trb_resource_settings();
	$table = trb_resource_tables()['notifications'];
	$wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO $table (event_key,recipient,subject,body,status,attempts,created_at,updated_at) VALUES (%s,%s,%s,%s,'pending',0,%s,%s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		sanitize_text_field( $event_key ), sanitize_email( $settings['admin_email'] ), ( $priority ? '[PRIORITÀ] ' : '' ) . sanitize_text_field( $subject ), wp_kses_post( $body ), trb_resource_now(), trb_resource_now()
	) );
	if ( ! wp_next_scheduled( 'trb_resource_process_notifications' ) ) wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'trb_resource_process_notifications' );
}

function trb_resource_process_notifications() {
	global $wpdb;
	$table = trb_resource_tables()['notifications'];
	$settings = trb_resource_settings();
	$sent_today = (int) get_option( 'trb_resource_email_sent_' . wp_date( 'Ymd' ), 0 );
	$rows = $wpdb->get_results( "SELECT * FROM $table WHERE status IN ('pending','retry') AND attempts<5 ORDER BY id ASC LIMIT 20" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( $rows as $row ) {
		if ( $sent_today >= (int) $settings['email_daily_limit'] ) {
			trb_resource_event( 'daily-limit-' . wp_date( 'Ymd' ), 'email', 'warning', 'Raggiunto il limite giornaliero interno del provider email.' );
			break;
		}
		$wpdb->update( $table, array( 'status' => 'sending', 'attempts' => (int) $row->attempts + 1, 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
		$sent = wp_mail( $row->recipient, $row->subject, $row->body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		$wpdb->update( $table, array( 'status' => $sent ? 'sent' : 'retry', 'last_error' => $sent ? '' : 'wp_mail_failed', 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
		if ( $sent ) { $sent_today++; update_option( 'trb_resource_email_sent_' . wp_date( 'Ymd' ), $sent_today, false ); }
	}
}
add_action( 'trb_resource_process_notifications', 'trb_resource_process_notifications' );

function trb_resource_mail_failed( $error ) {
	$message = is_wp_error( $error ) ? $error->get_error_message() : 'Errore email non specificato';
	trb_resource_event( 'smtp-' . wp_date( 'YmdH' ), 'email', 'warning', 'Invio email non riuscito.', array( 'message' => $message ) );
}
add_action( 'wp_mail_failed', 'trb_resource_mail_failed', 10, 1 );

function trb_resource_mail_succeeded() {
	$key = 'trb_resource_all_email_sent_' . wp_date( 'Ymd' );
	update_option( $key, (int) get_option( $key, 0 ) + 1, false );
}
add_action( 'wp_mail_succeeded', 'trb_resource_mail_succeeded' );

function trb_resource_storage_snapshot() {
	$uploads = wp_upload_dir();
	$path = ! empty( $uploads['basedir'] ) ? $uploads['basedir'] : ABSPATH;
	$free = @disk_free_space( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$total = @disk_total_space( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	return array( 'free' => false === $free ? null : (float) $free, 'total' => false === $total ? null : (float) $total, 'used_percent' => $total ? ( ( $total - $free ) / $total ) * 100 : null );
}

function trb_resource_acr_thresholds( $current, $budget ) {
	if ( $budget <= 0 ) return;
	$percent = (float) $current / (float) $budget * 100;
	$period = trb_resource_period_key();
	if ( $percent >= 50 ) trb_resource_event( 'budget-50-' . $period, 'acrcloud', 'info', 'Budget ACRCloud oltre il 50%.', compact( 'percent', 'current', 'budget' ) );
	if ( $percent >= 75 ) trb_resource_queue_email( 'acr-budget-75-' . $period, 'Budget ACRCloud oltre il 75%', 'Il registro prudenziale ha raggiunto il ' . number_format_i18n( $percent, 1 ) . '% del budget mensile.' );
	if ( $percent >= 90 ) trb_resource_queue_email( 'acr-budget-90-' . $period, 'Budget ACRCloud oltre il 90%', 'Il registro prudenziale ha raggiunto il ' . number_format_i18n( $percent, 1 ) . '%. Verificare il pannello prima delle prossime analisi.', true );
}

function trb_resource_temp_storage_guard( $incoming_bytes ) {
	$settings = trb_resource_settings();
	$snapshot = trb_resource_storage_snapshot();
	$required = (float) $incoming_bytes * max( 1, (float) $settings['temp_file_multiplier'] );
	if ( null === $snapshot['free'] || null === $snapshot['used_percent'] ) return new WP_Error( 'TEMP_STORAGE_UNVERIFIED' );
	if ( $snapshot['used_percent'] >= (float) $settings['temp_block'] || $snapshot['free'] < $required ) {
		trb_resource_event( 'capacity', 'storage', 'critical', 'Storage temporaneo insufficiente.', $snapshot + array( 'required' => $required ) );
		trb_resource_queue_email( 'storage-critical-' . wp_date( 'YmdH' ), 'Storage temporaneo quasi esaurito', 'I nuovi WAV sono stati fermati prima del caricamento. Verificare spazio e pratiche in attesa.', true );
		return new WP_Error( 'TEMP_STORAGE_LIMIT_REACHED' );
	}
	if ( $snapshot['used_percent'] >= (float) $settings['temp_warning_1'] ) trb_resource_event( 'capacity-warning', 'storage', 'warning', 'Storage temporaneo oltre la prima soglia.', $snapshot );
	if ( $snapshot['used_percent'] >= (float) $settings['temp_warning_2'] ) trb_resource_queue_email( 'storage-85-' . wp_date( 'Ym' ), 'Storage temporaneo oltre la soglia di attenzione', 'Utilizzo corrente: ' . number_format_i18n( $snapshot['used_percent'], 1 ) . '%.' );
	return true;
}

function trb_resource_pcloud_userinfo() {
	$settings = trb_resource_settings();
	$host = in_array( untrailingslashit( $settings['pcloud_api_host'] ), array( 'https://api.pcloud.com', 'https://eapi.pcloud.com' ), true ) ? untrailingslashit( $settings['pcloud_api_host'] ) : 'https://eapi.pcloud.com';
	$body = array();
	if ( ! empty( $settings['pcloud_auth_token'] ) ) $body['auth'] = $settings['pcloud_auth_token'];
	else {
		$legacy = function_exists( 'trb_demo_settings' ) ? trb_demo_settings() : array();
		if ( empty( $legacy['pcloud_user'] ) || empty( $legacy['pcloud_pass'] ) ) return new WP_Error( 'PCLOUD_AUTH_MISSING' );
		$body['username'] = $legacy['pcloud_user']; $body['password'] = $legacy['pcloud_pass'];
	}
	$response = wp_remote_post( $host . '/userinfo', array( 'timeout' => 30, 'body' => $body ) );
	if ( is_wp_error( $response ) ) return $response;
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || ! empty( $data['result'] ) || empty( $data['quota'] ) || ! isset( $data['usedquota'] ) ) return new WP_Error( 'PCLOUD_USERINFO_FAILED' );
	$data['used_percent'] = ( (float) $data['usedquota'] / (float) $data['quota'] ) * 100;
	$data['free'] = (float) $data['quota'] - (float) $data['usedquota'];
	update_option( 'trb_resource_pcloud_snapshot', array( 'time' => time(), 'data' => $data ), false );
	if ( $data['used_percent'] >= (float) trb_resource_settings()['pcloud_warning_1'] ) trb_resource_event( 'quota-warning', 'pcloud', 'warning', 'Utilizzo pCloud oltre la prima soglia.', array( 'used_percent' => $data['used_percent'] ) );
	if ( $data['used_percent'] >= (float) trb_resource_settings()['pcloud_warning_2'] ) trb_resource_queue_email( 'pcloud-85-' . wp_date( 'Ym' ), 'Quota pCloud oltre la soglia di attenzione', 'Spazio utilizzato: ' . number_format_i18n( $data['used_percent'], 1 ) . '%.' );
	if ( $data['used_percent'] >= (float) trb_resource_settings()['pcloud_warning_3'] ) trb_resource_queue_email( 'pcloud-95-' . wp_date( 'Ym' ), 'Quota pCloud quasi esaurita', 'Spazio utilizzato: ' . number_format_i18n( $data['used_percent'], 1 ) . '%.', true );
	return $data;
}

function trb_resource_pcloud_guard( $incoming_bytes ) {
	$settings = trb_resource_settings();
	$data = trb_resource_pcloud_userinfo();
	if ( is_wp_error( $data ) ) {
		trb_resource_event( 'userinfo', 'pcloud', 'critical', 'Quota pCloud non verificabile.', array( 'code' => $data->get_error_code() ) );
		return new WP_Error( 'PCLOUD_QUOTA_UNVERIFIED' );
	}
	$required = (float) $incoming_bytes + (float) $settings['pcloud_safety_bytes'];
	if ( $data['used_percent'] >= (float) $settings['pcloud_block'] || $data['free'] < $required ) {
		trb_resource_event( 'quota', 'pcloud', 'critical', 'Quota pCloud insufficiente.', array( 'used_percent' => $data['used_percent'], 'free' => $data['free'], 'required' => $required ) );
		trb_resource_queue_email( 'pcloud-block-' . wp_date( 'YmdH' ), 'Trasferimenti pCloud bloccati preventivamente', 'La quota disponibile non consente un trasferimento sicuro. I WAV restano nello storage temporaneo.', true );
		return new WP_Error( 'PCLOUD_QUOTA_LIMIT_REACHED' );
	}
	return $data;
}

function trb_resource_acr_stats( $period = '' ) {
	global $wpdb;
	$table = trb_resource_tables()['usage'];
	$period = $period ? $period : trb_resource_period_key();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(CASE WHEN service='fingerprinting' THEN 1 ELSE 0 END) requests,COUNT(DISTINCT CASE WHEN service='fingerprinting' THEN file_hash ELSE NULL END) tracks,SUM(cost_max) cost_max,SUM(cost_estimated) cost_estimated,SUM(COALESCE(cost_actual,0)) cost_actual,SUM(CASE WHEN service='deepright' THEN units ELSE 0 END) deepright_minutes,SUM(CASE WHEN service='cover_song' THEN units ELSE 0 END) cover_minutes,SUM(CASE WHEN service='metadata' THEN units ELSE 0 END) metadata_calls,SUM(CASE WHEN service='fingerprinting' THEN attempts ELSE 0 END) attempts,SUM(CASE WHEN status='error' THEN 1 ELSE 0 END) errors FROM $table WHERE provider='acrcloud' AND period_key=%s", $period ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $row ? (array) $row : array();
}

function trb_resource_set_acr_actual_cost( $period, $amount ) {
	global $wpdb;
	$table = trb_resource_tables()['usage'];
	$key = 'acr-reconciliation-' . $period;
	$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE idempotency_key=%s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$data = array( 'cost_actual' => max( 0, (float) $amount ), 'updated_at' => trb_resource_now(), 'status' => 'reconciled' );
	if ( $existing ) return false !== $wpdb->update( $table, $data, array( 'id' => $existing ) );
	return false !== $wpdb->insert( $table, array(
		'provider' => 'acrcloud', 'service' => 'reconciliation', 'period_key' => $period, 'idempotency_key' => $key,
		'release_id' => 0, 'track_index' => 0, 'file_hash' => '', 'units' => 0, 'cost_max' => 0,
		'cost_estimated' => 0, 'cost_actual' => max( 0, (float) $amount ), 'status' => 'reconciled',
		'provider_reference' => '', 'attempts' => 0, 'last_error' => '', 'payload' => '',
		'created_at' => trb_resource_now(), 'updated_at' => trb_resource_now(),
	) );
}

function trb_resource_acr_max_cost( $duration_seconds ) {
	$s = trb_resource_settings();
	$minutes = max( 1, ceil( (float) $duration_seconds / 60 ) );
	return round( (float) $s['acr_fingerprint_max'] + $minutes * ( (float) $s['acr_deepright_minute_max'] + (float) $s['acr_cover_minute_max'] ) + (float) $s['acr_metadata_call_max'], 6 );
}

function trb_resource_acr_budget_guard( $maximum, $release_id = 0 ) {
	$s = trb_resource_settings();
	$stats = trb_resource_acr_stats();
	$current = isset( $stats['cost_max'] ) ? (float) $stats['cost_max'] : 0;
	$budget = (float) $s['acr_monthly_budget'];
	if ( $budget <= 0 || $current + (float) $maximum > $budget ) {
		if ( $release_id && get_post_meta( $release_id, '_trb_acr_budget_override', true ) ) return true;
		trb_resource_event( 'budget-' . trb_resource_period_key(), 'acrcloud', 'critical', 'Budget mensile ACRCloud raggiunto.', compact( 'current', 'maximum', 'budget', 'release_id' ) );
		trb_resource_queue_email( 'acr-budget-block-' . trb_resource_period_key(), 'Budget ACRCloud raggiunto', 'Nuove analisi bloccate prima della chiamata. Nessun WAV è stato eliminato.', true );
		return new WP_Error( 'ACR_BUDGET_LIMIT_REACHED' );
	}
	return true;
}

function trb_resource_usage_reserve( $data ) {
	global $wpdb;
	$table = trb_resource_tables()['usage'];
	$defaults = array( 'provider' => 'acrcloud', 'service' => 'fingerprinting', 'release_id' => 0, 'track_index' => 0, 'file_hash' => '', 'units' => 1, 'cost_max' => 0, 'cost_estimated' => 0, 'status' => 'reserved', 'provider_reference' => '', 'attempts' => 0, 'payload' => '' );
	$data = wp_parse_args( $data, $defaults );
	$data['period_key'] = trb_resource_period_key(); $data['created_at'] = trb_resource_now(); $data['updated_at'] = trb_resource_now();
	$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $table (provider,service,period_key,idempotency_key,release_id,track_index,file_hash,units,cost_max,cost_estimated,status,provider_reference,attempts,payload,created_at,updated_at) VALUES (%s,%s,%s,%s,%d,%d,%s,%f,%f,%f,%s,%s,%d,%s,%s,%s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data['provider'], $data['service'], $data['period_key'], $data['idempotency_key'], $data['release_id'], $data['track_index'], $data['file_hash'], $data['units'], $data['cost_max'], $data['cost_estimated'], $data['status'], $data['provider_reference'], $data['attempts'], $data['payload'], $data['created_at'], $data['updated_at'] ) );
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE idempotency_key=%s", $data['idempotency_key'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( 'acrcloud' === $data['provider'] ) {
		$stats = trb_resource_acr_stats();
		trb_resource_acr_thresholds( isset( $stats['cost_max'] ) ? (float) $stats['cost_max'] : 0, (float) trb_resource_settings()['acr_monthly_budget'] );
	}
	return $id;
}

function trb_resource_acr_endpoint() {
	$s = trb_resource_settings();
	$map = array( 'eu-west-1' => 'https://api-eu-west-1.acrcloud.com', 'us-west-2' => 'https://api-us-west-2.acrcloud.com', 'ap-southeast-1' => 'https://api-ap-southeast-1.acrcloud.com' );
	return isset( $map[ $s['acr_region'] ] ) ? $map[ $s['acr_region'] ] : $map['eu-west-1'];
}

function trb_resource_create_excerpt( $source, $release_id, $track_index ) {
	$s = trb_resource_settings();
	if ( ! function_exists( 'exec' ) || ! function_exists( 'shell_exec' ) ) return new WP_Error( 'AUDIO_EXTRACTOR_UNAVAILABLE' );
	$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
	if ( in_array( 'exec', $disabled, true ) ) return new WP_Error( 'AUDIO_EXTRACTOR_UNAVAILABLE' );
	$binary = trim( (string) shell_exec( 'command -v ffmpeg 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
	if ( ! $binary || ! is_executable( $binary ) ) return new WP_Error( 'AUDIO_EXTRACTOR_UNAVAILABLE' );
	$window = function_exists( 'trb_analysis_excerpt_window' ) ? trb_analysis_excerpt_window( $source, absint( $s['acr_excerpt_seconds'] ) ) : array( 'start' => 0, 'length' => absint( $s['acr_excerpt_seconds'] ) );
	if ( is_wp_error( $window ) ) return $window;
	$target = trailingslashit( dirname( $source ) ) . '.acr-' . absint( $release_id ) . '-' . absint( $track_index ) . '-' . wp_generate_uuid4() . '.wav';
	$command = escapeshellarg( $binary ) . ' -v error -y -i ' . escapeshellarg( $source ) . ' -ss ' . escapeshellarg( (string) $window['start'] ) . ' -t ' . escapeshellarg( (string) $window['length'] ) . ' -map 0:a:0 -vn -c:a copy ' . escapeshellarg( $target ) . ' 2>&1';
	$output = array(); $code = 1; exec( $command, $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
	return 0 === $code && is_file( $target ) ? $target : new WP_Error( 'AUDIO_EXCERPT_FAILED', implode( ' ', array_slice( $output, -3 ) ) );
}

function trb_resource_submit_acr_file( $path, $name ) {
	$s = trb_resource_settings();
	if ( ! function_exists( 'curl_init' ) || ! class_exists( 'CURLFile' ) ) return new WP_Error( 'ACR_TRANSPORT_UNAVAILABLE' );
	$url = trb_resource_acr_endpoint() . '/api/fs-containers/' . rawurlencode( $s['acr_container_id'] ) . '/files';
	$curl = curl_init( $url );
	curl_setopt_array( $curl, array( CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 300, CURLOPT_HTTPHEADER => array( 'Accept: application/json', 'Authorization: Bearer ' . $s['acr_token'] ), CURLOPT_POSTFIELDS => array( 'file' => new CURLFile( $path, 'audio/wav', $name ), 'data_type' => 'audio', 'name' => $name ) ) );
	$body = curl_exec( $curl ); $code = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ); $error = curl_error( $curl ); curl_close( $curl );
	$data = json_decode( (string) $body, true );
	return $code >= 200 && $code < 300 && ! empty( $data['data']['id'] ) ? $data['data'] : new WP_Error( 'ACR_SUBMIT_FAILED', $error ? $error : 'HTTP ' . $code );
}

/** Recover a provider job after an ambiguous timeout before allowing a retry. */
function trb_resource_find_acr_file( $name ) {
	$s = trb_resource_settings();
	$url = add_query_arg( array( 'page' => 1, 'per_page' => 20, 'search' => $name ), trb_resource_acr_endpoint() . '/api/fs-containers/' . rawurlencode( $s['acr_container_id'] ) . '/files' );
	$response = wp_remote_get( $url, array( 'timeout' => 60, 'headers' => array( 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $s['acr_token'] ) ) );
	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 ) return new WP_Error( 'ACR_LOOKUP_FAILED' );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	foreach ( isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array() as $item ) {
		if ( ! empty( $item['id'] ) && isset( $item['name'] ) && hash_equals( (string) $item['name'], (string) $name ) ) return $item;
	}
	return new WP_Error( 'ACR_FILE_NOT_FOUND' );
}

function trb_resource_start_release_analysis( $release_id ) {
	$s = trb_resource_settings();
	$technical = (array) get_post_meta( $release_id, '_trb_release_technical_analysis', true );
	if ( ! in_array( $technical['status'] ?? '', array( 'passed', 'warning' ), true ) ) return;
	if ( empty( $s['acr_enabled'] ) || empty( $s['acr_paid_confirmed'] ) || empty( $s['acr_token'] ) || empty( $s['acr_container_id'] ) ) {
		update_post_meta( $release_id, '_trb_release_pipeline_status', 'analysis_waiting_configuration' );
		return;
	}
	if ( function_exists( 'trb_analysis_verify_acr_container' ) ) {
		$container = trb_analysis_verify_acr_container();
		if ( is_wp_error( $container ) ) {
			update_post_meta( $release_id, '_trb_release_pipeline_status', 'analysis_waiting_configuration' );
			trb_resource_event( 'container-' . trb_resource_period_key(), 'acrcloud', 'critical', 'Container ACRCloud non verificato o non conforme.', array( 'code' => $container->get_error_code(), 'message' => $container->get_error_message() ) );
			return;
		}
	}
	$files = (array) get_post_meta( $release_id, '_trb_release_files', true );
	update_post_meta( $release_id, '_trb_release_pipeline_status', 'analysis_in_progress' );
	$waiting = false;
	foreach ( $files as $file ) {
		if ( empty( $file['kind'] ) || 'audio' !== $file['kind'] ) continue;
		$hash = isset( $file['sha256'] ) ? $file['sha256'] : '';
		$track = isset( $file['track'] ) ? absint( $file['track'] ) : 0;
		$local = function_exists( 'trb_release_pcloud_local_file' ) ? trb_release_pcloud_local_file( $file ) : '';
		if ( ! $local ) { update_post_meta( $release_id, '_trb_release_pipeline_status', 'manual_review' ); return; }
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) $hash = hash_file( 'sha256', $local );
		$key = hash( 'sha256', 'acrcloud|' . $hash . '|engine:' . absint( $s['acr_engine'] ) . '|deepright:' . absint( $s['acr_deepright'] ) );
		global $wpdb; $table = trb_resource_tables()['usage'];
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE idempotency_key=%s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $existing && in_array( $existing->status, array( 'submitted', 'processing', 'completed' ), true ) ) {
			if ( (int) $existing->release_id !== (int) $release_id ) {
				$alias_id = trb_resource_usage_reserve( array(
					'service' => 'fingerprinting_reuse', 'idempotency_key' => $key . ':release:' . absint( $release_id ) . ':' . $track,
					'release_id' => $release_id, 'track_index' => $track, 'file_hash' => $hash, 'units' => 0,
					'cost_max' => 0, 'cost_estimated' => 0, 'status' => $existing->status,
					'provider_reference' => $existing->provider_reference, 'payload' => $existing->payload,
				) );
				if ( 'completed' !== $existing->status ) {
					$waiting = true;
					if ( ! wp_next_scheduled( 'trb_resource_poll_acr_job', array( $alias_id ) ) ) wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'trb_resource_poll_acr_job', array( $alias_id ) );
				}
			} elseif ( 'completed' !== $existing->status ) $waiting = true;
			continue;
		}
		$duration = ! empty( $file['audio_spec']['duration_seconds'] ) ? (float) $file['audio_spec']['duration_seconds'] : 0;
		$maximum = trb_resource_acr_max_cost( $duration );
		$guard = trb_resource_acr_budget_guard( $maximum, $release_id );
		if ( is_wp_error( $guard ) ) { update_post_meta( $release_id, '_trb_release_pipeline_status', 'ACR_BUDGET_LIMIT_REACHED' ); return; }
		$excerpt = trb_resource_create_excerpt( $local, $release_id, $track );
		if ( is_wp_error( $excerpt ) ) { trb_resource_event( 'extractor-' . $release_id, 'acrcloud', 'critical', 'Impossibile creare l’estratto audio per ACRCloud.', array( 'code' => $excerpt->get_error_code() ) ); update_post_meta( $release_id, '_trb_release_pipeline_status', 'manual_review' ); return; }
		$minutes = max( 1, ceil( $duration / 60 ) );
		$ledger_id = trb_resource_usage_reserve( array( 'idempotency_key' => $key, 'release_id' => $release_id, 'track_index' => $track, 'file_hash' => $hash, 'cost_max' => (float) $s['acr_fingerprint_max'], 'cost_estimated' => (float) $s['acr_fingerprint_max'], 'status' => 'reserved' ) );
		trb_resource_usage_reserve( array( 'service' => 'deepright', 'idempotency_key' => $key . ':deepright', 'release_id' => $release_id, 'track_index' => $track, 'file_hash' => $hash, 'units' => $minutes, 'cost_max' => $minutes * (float) $s['acr_deepright_minute_max'], 'cost_estimated' => $minutes * (float) $s['acr_deepright_minute_max'], 'status' => 'estimated' ) );
		trb_resource_usage_reserve( array( 'service' => 'cover_song', 'idempotency_key' => $key . ':cover', 'release_id' => $release_id, 'track_index' => $track, 'file_hash' => $hash, 'units' => $minutes, 'cost_max' => $minutes * (float) $s['acr_cover_minute_max'], 'cost_estimated' => $minutes * (float) $s['acr_cover_minute_max'], 'status' => 'estimated' ) );
		trb_resource_usage_reserve( array( 'service' => 'metadata', 'idempotency_key' => $key . ':metadata', 'release_id' => $release_id, 'track_index' => $track, 'file_hash' => $hash, 'units' => 1, 'cost_max' => (float) $s['acr_metadata_call_max'], 'cost_estimated' => (float) $s['acr_metadata_call_max'], 'status' => 'estimated' ) );
		$provider_name = 'trb-' . $hash . '.wav';
		$result = 'error' === ( $existing ? $existing->status : '' ) ? trb_resource_find_acr_file( $provider_name ) : new WP_Error( 'ACR_FILE_NOT_FOUND' );
		if ( is_wp_error( $result ) && 'ACR_LOOKUP_FAILED' === $result->get_error_code() ) {
			wp_delete_file( $excerpt );
			update_post_meta( $release_id, '_trb_release_pipeline_status', 'manual_review' );
			return;
		}
		if ( is_wp_error( $result ) ) $result = trb_resource_submit_acr_file( $excerpt, $provider_name );
		wp_delete_file( $excerpt );
		if ( is_wp_error( $result ) ) {
			$wpdb->update( $table, array( 'status' => 'error', 'attempts' => 1, 'last_error' => $result->get_error_code(), 'updated_at' => trb_resource_now() ), array( 'id' => $ledger_id ) );
			update_post_meta( $release_id, '_trb_release_pipeline_status', 'manual_review' );
			trb_resource_event( 'submit-' . $release_id . '-' . $track, 'acrcloud', 'critical', 'Invio ACRCloud non completato.', array( 'code' => $result->get_error_code() ) );
			return;
		}
		$wpdb->update( $table, array( 'status' => 'submitted', 'provider_reference' => sanitize_text_field( $result['id'] ), 'attempts' => 1, 'payload' => wp_json_encode( $result ), 'updated_at' => trb_resource_now() ), array( 'id' => $ledger_id ) );
		$waiting = true;
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'trb_resource_poll_acr_job', array( $ledger_id ) );
	}
	if ( ! $waiting ) {
		update_post_meta( $release_id, '_trb_release_pipeline_status', 'copyright_review' );
		if ( function_exists( 'trb_analysis_decide_release' ) ) trb_analysis_decide_release( absint( $release_id ) );
	}
}
add_action( 'trb_release_audio_ready_for_analysis', 'trb_resource_start_release_analysis', 10, 1 );
add_action( 'trb_resource_start_release_analysis_manual', 'trb_resource_start_release_analysis', 10, 1 );

function trb_resource_poll_acr_job( $ledger_id ) {
	global $wpdb; $table = trb_resource_tables()['usage']; $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", absint( $ledger_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $row || ! $row->provider_reference ) return;
	$s = trb_resource_settings();
	$url = trb_resource_acr_endpoint() . '/api/fs-containers/' . rawurlencode( $s['acr_container_id'] ) . '/files/' . rawurlencode( $row->provider_reference );
	$response = wp_remote_get( $url, array( 'timeout' => 60, 'headers' => array( 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $s['acr_token'] ) ) );
	$data = ! is_wp_error( $response ) ? json_decode( wp_remote_retrieve_body( $response ), true ) : array();
	$item = isset( $data['data'] ) ? $data['data'] : array();
	$state = isset( $item['state'] ) ? (int) $item['state'] : 0;
	if ( 0 === $state && (int) $row->attempts < 30 ) {
		$wpdb->update( $table, array( 'status' => 'processing', 'attempts' => (int) $row->attempts + 1, 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'trb_resource_poll_acr_job', array( (int) $row->id ) ); return;
	}
	$status = 1 === $state || -1 === $state ? 'completed' : 'error';
	$wpdb->update( $table, array( 'status' => $status, 'payload' => wp_json_encode( $item ), 'last_error' => 'error' === $status ? 'ACR_STATE_' . $state : '', 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
	if ( 'completed' === $status ) {
		$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE release_id=%d AND status IN ('reserved','submitted','processing')", $row->release_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 0 === $pending ) {
			update_post_meta( $row->release_id, '_trb_release_pipeline_status', 'copyright_review' );
			if ( function_exists( 'trb_analysis_decide_release' ) ) trb_analysis_decide_release( absint( $row->release_id ) );
		}
	} else update_post_meta( $row->release_id, '_trb_release_pipeline_status', 'manual_review' );
}
add_action( 'trb_resource_poll_acr_job', 'trb_resource_poll_acr_job' );

function trb_resource_release_rights_folder( $release_id, $track_index ) {
	$archive = (array) get_post_meta( $release_id, '_trb_release_pcloud_archive', true );
	return isset( $archive['folders'][ absint( $track_index ) ] ) ? $archive['folders'][ absint( $track_index ) ] : new WP_Error( 'PCLOUD_RELEASE_FOLDER_MISSING' );
}

function trb_resource_sync_rights_document( $release_id, $document_index ) {
	$documents = (array) get_post_meta( $release_id, '_trb_release_rights_documents', true );
	if ( ! isset( $documents[ $document_index ] ) ) return new WP_Error( 'RIGHTS_DOCUMENT_MISSING' );
	$document = $documents[ $document_index ];
	$folder = trb_resource_release_rights_folder( $release_id, isset( $document['track'] ) ? $document['track'] : 0 );
	if ( is_wp_error( $folder ) ) return $folder;
	$local = function_exists( 'trb_release_pcloud_local_file' ) ? trb_release_pcloud_local_file( $document ) : '';
	if ( ! $local ) return new WP_Error( 'RIGHTS_DOCUMENT_LOCAL_MISSING' );
	if ( function_exists( 'trb_analysis_antivirus_scan' ) ) {
		$scan = trb_analysis_antivirus_scan( $local );
		if ( is_wp_error( $scan ) ) {
			$documents[ $document_index ]['status'] = 'quarantine'; $documents[ $document_index ]['security_status'] = $scan->get_error_code();
			update_post_meta( $release_id, '_trb_release_rights_documents', $documents );
			update_post_meta( $release_id, '_trb_release_pipeline_status', 'security_scan_waiting' );
			return $scan;
		}
		$documents[ $document_index ]['security_status'] = 'clean';
	}
	$remote = $folder . '/Documentazione diritti - ' . sanitize_file_name( $document['name'] );
	$result = trb_artist_archive_put( $remote, file_get_contents( $local ), ! empty( $document['type'] ) ? $document['type'] : 'application/octet-stream' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( is_wp_error( $result ) ) return $result;
	$documents[ $document_index ]['status'] = 'synced'; $documents[ $document_index ]['remote'] = $remote; $documents[ $document_index ]['synced_at'] = time();
	update_post_meta( $release_id, '_trb_release_rights_documents', $documents );
	update_post_meta( $release_id, '_trb_release_pipeline_status', 'copyright_review' );
	if ( function_exists( 'trb_analysis_decide_release' ) ) trb_analysis_decide_release( absint( $release_id ) );
	return true;
}

function trb_resource_retry_rights_document( $release_id, $document_index ) {
	$result = trb_resource_sync_rights_document( absint( $release_id ), absint( $document_index ) );
	if ( is_wp_error( $result ) && ! wp_next_scheduled( 'trb_resource_retry_rights_document', array( absint( $release_id ), absint( $document_index ) ) ) ) wp_schedule_single_event( time() + 30 * MINUTE_IN_SECONDS, 'trb_resource_retry_rights_document', array( absint( $release_id ), absint( $document_index ) ) );
}
add_action( 'trb_resource_retry_rights_document', 'trb_resource_retry_rights_document', 10, 2 );

function trb_resource_upload_rights_document() {
	if ( ! is_user_logged_in() ) auth_redirect();
	$release_id = isset( $_POST['release_id'] ) ? absint( $_POST['release_id'] ) : 0;
	check_admin_referer( 'trb_resource_rights_' . $release_id );
	if ( ! function_exists( 'trb_portal_current_user_can_access_release' ) || ! trb_portal_current_user_can_access_release( $release_id ) ) wp_die( 'Operazione non consentita.', 'Area Artisti TRB rec', array( 'response' => 403 ) );
	$file = ! empty( $_FILES['rights_document'] ) ? $_FILES['rights_document'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$track = isset( $_POST['track_index'] ) ? absint( $_POST['track_index'] ) : 0;
	$name = ! empty( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
	$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$valid = $name && UPLOAD_ERR_OK === (int) $file['error'] && is_uploaded_file( $file['tmp_name'] ) && (int) $file['size'] <= 20 * MB_IN_BYTES && in_array( $extension, array( 'pdf','jpg','jpeg','png','docx' ), true );
	if ( $valid ) { $guard = trb_resource_temp_storage_guard( (int) $file['size'] ); $valid = ! is_wp_error( $guard ); }
	$dashboard = get_permalink( get_option( 'trb_portal_dashboard_created' ) ); $anchor = '#release-files-' . $release_id;
	if ( ! $valid ) { wp_safe_redirect( add_query_arg( 'trb_release', 'rights_invalid', $dashboard ) . $anchor ); exit; }
	$uploads = wp_upload_dir(); $relative = 'trb-release-private/' . $release_id; $directory = trailingslashit( $uploads['basedir'] ) . $relative;
	if ( ! wp_mkdir_p( $directory ) ) { wp_safe_redirect( add_query_arg( 'trb_release', 'rights_error', $dashboard ) . $anchor ); exit; }
	$stored_name = wp_unique_filename( $directory, 'Diritti - ' . $name ); $target = trailingslashit( $directory ) . $stored_name;
	if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) { wp_safe_redirect( add_query_arg( 'trb_release', 'rights_error', $dashboard ) . $anchor ); exit; }
	$documents = (array) get_post_meta( $release_id, '_trb_release_rights_documents', true );
	$documents[] = array( 'kind' => 'rights', 'track' => $track, 'name' => $stored_name, 'original_name' => $name, 'path' => $relative . '/' . $stored_name, 'type' => sanitize_mime_type( $file['type'] ), 'size' => filesize( $target ), 'sha256' => hash_file( 'sha256', $target ), 'status' => 'pending', 'uploaded_at' => time() );
	update_post_meta( $release_id, '_trb_release_rights_documents', $documents ); $index = count( $documents ) - 1;
	$result = trb_resource_sync_rights_document( $release_id, $index );
	if ( is_wp_error( $result ) ) { wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, 'trb_resource_retry_rights_document', array( $release_id, $index ) ); update_post_meta( $release_id, '_trb_release_pipeline_status', 'pcloud_transfer_waiting' ); }
	wp_safe_redirect( add_query_arg( 'trb_release', is_wp_error( $result ) ? 'rights_waiting' : 'rights_uploaded', $dashboard ) . $anchor ); exit;
}
add_action( 'admin_post_trb_resource_upload_rights', 'trb_resource_upload_rights_document' );

function trb_resource_render_rights_box( $release_id ) {
	$status = (string) get_post_meta( $release_id, '_trb_release_pipeline_status', true );
	$documents = (array) get_post_meta( $release_id, '_trb_release_rights_documents', true );
	if ( ! in_array( $status, array( 'copyright_documents_needed','copyright_review','pcloud_transfer_waiting' ), true ) && ! $documents ) return;
	$tracks = (array) get_post_meta( $release_id, '_trb_release_tracks', true );
	?><div class="trb-portal__message"><strong>Documentazione sui diritti</strong><p>Allega licenze, autorizzazioni o attestazioni relative al brano. I documenti vengono archiviati nella stessa cartella pCloud del WAV e sottoposti a verifica.</p><?php if ( $documents ) : ?><ul><?php foreach ( $documents as $document ) : ?><li><?php echo esc_html( $document['original_name'] . ' · ' . ( 'synced' === $document['status'] ? 'archiviato' : 'trasferimento in attesa' ) ); ?></li><?php endforeach; ?></ul><?php endif; ?><form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="trb_resource_upload_rights"><?php wp_nonce_field( 'trb_resource_rights_' . $release_id ); ?><input type="hidden" name="release_id" value="<?php echo esc_attr( $release_id ); ?>"><label>Brano <select name="track_index"><?php foreach ( $tracks as $index => $track ) : ?><option value="<?php echo esc_attr( $index ); ?>"><?php echo esc_html( ( $index + 1 ) . '. ' . $track['title'] ); ?></option><?php endforeach; ?></select></label><input type="file" name="rights_document" accept=".pdf,.jpg,.jpeg,.png,.docx" required><button class="trb-button trb-button--compact">Allega documento</button></form></div><?php
}

function trb_resource_daily_health() {
	$anomalies = array();
	$pcloud = trb_resource_pcloud_userinfo();
	if ( is_wp_error( $pcloud ) ) $anomalies[] = 'Quota pCloud non verificabile: ' . $pcloud->get_error_code();
	else {
		$s = trb_resource_settings();
		if ( $pcloud['used_percent'] >= (float) $s['pcloud_warning_2'] ) $anomalies[] = 'pCloud utilizzato al ' . number_format_i18n( $pcloud['used_percent'], 1 ) . '%.';
	}
	$storage = trb_resource_storage_snapshot();
	$s = trb_resource_settings();
	if ( null === $storage['used_percent'] ) $anomalies[] = 'Storage temporaneo non verificabile.';
	elseif ( $storage['used_percent'] >= (float) $s['temp_warning_2'] ) $anomalies[] = 'Storage temporaneo utilizzato al ' . number_format_i18n( $storage['used_percent'], 1 ) . '%.';
	global $wpdb; $tables = trb_resource_tables();
	$stuck = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_trb_release_pipeline_status' AND meta_value IN ('pcloud_transfer_waiting','analysis_in_progress','manual_review','ACR_BUDGET_LIMIT_REACHED','PCLOUD_QUOTA_LIMIT_REACHED')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$failed_mail = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['notifications']} WHERE status IN ('pending','retry')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $stuck ) $anomalies[] = $stuck . ' pratiche richiedono attenzione.';
	if ( $failed_mail ) $anomalies[] = $failed_mail . ' notifiche email sono in coda.';
	if ( $anomalies ) trb_resource_queue_email( 'daily-digest-' . wp_date( 'Ymd' ), 'Riepilogo giornaliero anomalie Portale Artisti', '<p>' . implode( '</p><p>', array_map( 'esc_html', $anomalies ) ) . '</p>' );
	trb_resource_process_notifications();
}
add_action( 'trb_resource_daily_health', 'trb_resource_daily_health' );
add_action( 'init', function() {
	if ( ! wp_next_scheduled( 'trb_resource_daily_health' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'trb_resource_daily_health' );
} );

function trb_resource_admin_menu() {
	add_management_page( 'Monitoraggio risorse TRB', 'Monitoraggio risorse TRB', 'manage_options', 'trb-resource-monitor', 'trb_resource_render_admin' );
}
add_action( 'admin_menu', 'trb_resource_admin_menu' );

function trb_resource_render_admin() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$settings = trb_resource_settings();
	if ( isset( $_POST['trb_resource_reconcile'] ) ) {
		check_admin_referer( 'trb_resource_reconcile' );
		$actual = isset( $_POST['acr_actual_cost'] ) ? (float) wp_unslash( $_POST['acr_actual_cost'] ) : 0;
		trb_resource_set_acr_actual_cost( trb_resource_period_key(), $actual );
		echo '<div class="notice notice-success"><p>Spesa effettiva del periodo registrata.</p></div>';
	}
	if ( isset( $_POST['trb_resource_release_action'] ) ) {
		check_admin_referer( 'trb_resource_release_action' );
		$release_id = isset( $_POST['release_id'] ) ? absint( $_POST['release_id'] ) : 0;
		$action = sanitize_key( wp_unslash( $_POST['trb_resource_release_action'] ) );
		if ( $release_id && 'trb_release' === get_post_type( $release_id ) ) {
			if ( 'override_budget' === $action ) { update_post_meta( $release_id, '_trb_acr_budget_override', 1 ); update_post_meta( $release_id, '_trb_release_pipeline_status', 'analysis_in_progress' ); wp_schedule_single_event( time() + 5, 'trb_resource_start_release_analysis_manual', array( $release_id ) ); }
			if ( 'request_documents' === $action ) update_post_meta( $release_id, '_trb_release_pipeline_status', 'copyright_documents_needed' );
			if ( 'manual_review' === $action ) update_post_meta( $release_id, '_trb_release_pipeline_status', 'manual_review' );
			if ( 'approve' === $action ) update_post_meta( $release_id, '_trb_release_pipeline_status', 'approved' );
			$history = (array) get_post_meta( $release_id, '_trb_release_decision_history', true );
			$history[] = array( 'action' => $action, 'user_id' => get_current_user_id(), 'at' => time() );
			update_post_meta( $release_id, '_trb_release_decision_history', array_slice( $history, -100 ) );
			if ( function_exists( 'trb_analysis_generate_report' ) ) trb_analysis_generate_report( $release_id );
			echo '<div class="notice notice-success"><p>Stato della pratica aggiornato.</p></div>';
		}
	}
	if ( isset( $_POST['trb_resource_save'] ) ) {
		check_admin_referer( 'trb_resource_save' );
		$boolean = array( 'acr_enabled', 'acr_paid_confirmed', 'acr_deepright' );
		$numeric = array( 'acr_monthly_budget','acr_fingerprint_max','acr_deepright_minute_max','acr_cover_minute_max','acr_metadata_call_max','acr_engine','acr_excerpt_seconds','acr_excerpt_offset','pcloud_safety_bytes','pcloud_warning_1','pcloud_warning_2','pcloud_warning_3','pcloud_block','temp_warning_1','temp_warning_2','temp_block','temp_file_multiplier','email_daily_limit' );
		$updated = $settings;
		foreach ( $boolean as $field ) $updated[ $field ] = isset( $_POST[ $field ] ) ? 1 : 0;
		foreach ( $numeric as $field ) if ( isset( $_POST[ $field ] ) ) $updated[ $field ] = (float) wp_unslash( $_POST[ $field ] );
		foreach ( array( 'admin_email','acr_container_id','acr_region','pcloud_api_host' ) as $field ) if ( isset( $_POST[ $field ] ) ) $updated[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
		foreach ( array( 'acr_token','pcloud_auth_token' ) as $field ) { $value = isset( $_POST[ $field ] ) ? trim( wp_unslash( $_POST[ $field ] ) ) : ''; if ( '' !== $value ) $updated[ $field ] = $value; }
		$updated['admin_email'] = sanitize_email( $updated['admin_email'] );
		$updated['pcloud_api_host'] = esc_url_raw( $updated['pcloud_api_host'] );
		update_option( 'trb_resource_monitor_settings', $updated, false ); $settings = $updated;
		echo '<div class="notice notice-success"><p>Configurazione salvata.</p></div>';
	}
	$stats = trb_resource_acr_stats(); $budget = (float) $settings['acr_monthly_budget']; $spent = isset( $stats['cost_max'] ) ? (float) $stats['cost_max'] : 0; $percent = $budget > 0 ? min( 100, $spent / $budget * 100 ) : 100;
	$day = (int) wp_date( 'j' ); $days = (int) wp_date( 't' ); $projection = $day > 0 ? $spent / $day * $days : $spent; $average = ! empty( $stats['tracks'] ) ? $spent / (int) $stats['tracks'] : 0; $reset = wp_date( 'd/m/Y', ( new DateTimeImmutable( 'first day of next month 00:00:00', wp_timezone() ) )->getTimestamp() );
	$pcloud_snapshot = get_option( 'trb_resource_pcloud_snapshot', array() ); $storage = trb_resource_storage_snapshot(); global $wpdb; $tables = trb_resource_tables();
	$events = $wpdb->get_results( "SELECT * FROM {$tables['events']} WHERE status='open' ORDER BY last_seen DESC LIMIT 20" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$queue = get_posts( array( 'post_type' => 'trb_release', 'post_status' => 'publish', 'posts_per_page' => 50, 'meta_query' => array( array( 'key' => '_trb_release_pipeline_status', 'value' => array( 'approved' ), 'compare' => 'NOT IN' ) ) ) );
	?>
	<div class="wrap"><h1>Monitoraggio risorse TRB</h1><p>Sistema indipendente di prevenzione per costi, crediti, spazio e notifiche.</p>
	<?php if ( $percent >= 90 ) : ?><div class="notice notice-error"><p><strong>Budget ACRCloud oltre il 90%.</strong> Le nuove analisi saranno bloccate prima di superare il limite.</p></div><?php elseif ( $percent >= 75 ) : ?><div class="notice notice-warning"><p>Budget ACRCloud oltre il 75%.</p></div><?php elseif ( $percent >= 50 ) : ?><div class="notice notice-info"><p>Budget ACRCloud oltre il 50%.</p></div><?php endif; ?>
	<h2>Quadro corrente</h2><table class="widefat striped"><tbody>
	<tr><th>Budget ACRCloud</th><td><?php echo esc_html( number_format_i18n( $spent, 4 ) . ' / ' . number_format_i18n( $budget, 2 ) . ' USD (' . number_format_i18n( $percent, 1 ) . '%)' ); ?></td></tr>
	<tr><th>Tracce / richieste</th><td><?php echo esc_html( absint( isset( $stats['tracks'] ) ? $stats['tracks'] : 0 ) . ' / ' . absint( isset( $stats['requests'] ) ? $stats['requests'] : 0 ) ); ?></td></tr>
	<tr><th>Costo medio / proiezione fine mese</th><td><?php echo esc_html( number_format_i18n( $average, 4 ) . ' USD / ' . number_format_i18n( $projection, 4 ) . ' USD' ); ?></td></tr>
	<tr><th>Spesa stimata / effettiva</th><td><?php echo esc_html( number_format_i18n( isset( $stats['cost_estimated'] ) ? $stats['cost_estimated'] : 0, 4 ) . ' USD / ' . number_format_i18n( isset( $stats['cost_actual'] ) ? $stats['cost_actual'] : 0, 4 ) . ' USD' ); ?></td></tr>
	<tr><th>Errori / retry / rinnovo periodo</th><td><?php echo esc_html( absint( isset( $stats['errors'] ) ? $stats['errors'] : 0 ) . ' / ' . absint( isset( $stats['attempts'] ) ? $stats['attempts'] : 0 ) . ' / ' . $reset ); ?></td></tr>
	<tr><th>DeepRight / Cover Song / Metadata</th><td><?php echo esc_html( (float) ( isset( $stats['deepright_minutes'] ) ? $stats['deepright_minutes'] : 0 ) . ' min / ' . (float) ( isset( $stats['cover_minutes'] ) ? $stats['cover_minutes'] : 0 ) . ' min / ' . absint( isset( $stats['metadata_calls'] ) ? $stats['metadata_calls'] : 0 ) . ' chiamate' ); ?></td></tr>
	<tr><th>pCloud</th><td><?php echo esc_html( ! empty( $pcloud_snapshot['data']['used_percent'] ) ? number_format_i18n( $pcloud_snapshot['data']['used_percent'], 1 ) . '% utilizzato' : 'Da verificare' ); ?></td></tr>
	<tr><th>Storage temporaneo</th><td><?php echo esc_html( null !== $storage['used_percent'] ? number_format_i18n( $storage['used_percent'], 1 ) . '% utilizzato' : 'Non verificabile' ); ?></td></tr>
	</tbody></table><form method="post" style="margin:12px 0 24px"><?php wp_nonce_field( 'trb_resource_reconcile' ); ?><label><strong>Spesa effettiva ACRCloud del mese (USD)</strong> <input type="number" min="0" step="0.000001" name="acr_actual_cost" value="<?php echo esc_attr( isset( $stats['cost_actual'] ) ? $stats['cost_actual'] : 0 ); ?>"></label> <button class="button" name="trb_resource_reconcile" value="1">Registra riconciliazione</button></form>
	<h2>Anomalie aperte</h2><?php if ( ! $events ) : ?><p>Nessuna anomalia registrata.</p><?php else : ?><table class="widefat striped"><thead><tr><th>Ultimo evento</th><th>Risorsa</th><th>Gravità</th><th>Dettaglio</th><th>Occorrenze</th></tr></thead><tbody><?php foreach ( $events as $event ) : ?><tr><td><?php echo esc_html( $event->last_seen ); ?></td><td><?php echo esc_html( $event->resource ); ?></td><td><?php echo esc_html( $event->severity ); ?></td><td><?php echo esc_html( $event->message ); ?></td><td><?php echo esc_html( $event->occurrences ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
	<h2>Coda pratiche</h2><?php if ( ! $queue ) : ?><p>Nessuna pratica in attesa.</p><?php else : ?><table class="widefat striped"><thead><tr><th>Pratica</th><th>Artista</th><th>Stato</th><th>Decisione</th></tr></thead><tbody><?php foreach ( $queue as $release ) : $state = get_post_meta( $release->ID, '_trb_release_pipeline_status', true ); $artist = get_userdata( $release->post_author ); ?><tr><td>#<?php echo esc_html( $release->ID . ' · ' . $release->post_title ); ?></td><td><?php echo esc_html( $artist ? $artist->display_name : '' ); ?></td><td><?php echo esc_html( $state ); ?></td><td><form method="post"><?php wp_nonce_field( 'trb_resource_release_action' ); ?><input type="hidden" name="release_id" value="<?php echo esc_attr( $release->ID ); ?>"><button class="button" name="trb_resource_release_action" value="request_documents">Richiedi documenti</button> <button class="button" name="trb_resource_release_action" value="manual_review">Verifica manuale</button> <button class="button" name="trb_resource_release_action" value="override_budget">Autorizza analisi</button> <button class="button button-primary" name="trb_resource_release_action" value="approve">Approva</button></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
	<h2>Configurazione</h2><form method="post"><?php wp_nonce_field( 'trb_resource_save' ); ?><table class="form-table"><tbody>
	<tr><th>Email amministrativa</th><td><input type="email" class="regular-text" name="admin_email" value="<?php echo esc_attr( $settings['admin_email'] ); ?>"></td></tr>
	<tr><th>ACRCloud</th><td><label><input type="checkbox" name="acr_enabled" <?php checked( $settings['acr_enabled'] ); ?>> Abilita analisi reali</label><br><label><input type="checkbox" name="acr_paid_confirmed" <?php checked( $settings['acr_paid_confirmed'] ); ?>> Confermo piano Premium/pay-per-use e pagamento verificato</label><br><label><input type="checkbox" name="acr_deepright" <?php checked( $settings['acr_deepright'] ); ?>> DeepRight abilitato</label><p><input type="password" class="regular-text" name="acr_token" placeholder="Token invariato se vuoto"> <input name="acr_container_id" value="<?php echo esc_attr( $settings['acr_container_id'] ); ?>" placeholder="Container ID"> <select name="acr_region"><option value="eu-west-1" <?php selected( $settings['acr_region'], 'eu-west-1' ); ?>>EU</option><option value="us-west-2" <?php selected( $settings['acr_region'], 'us-west-2' ); ?>>US</option><option value="ap-southeast-1" <?php selected( $settings['acr_region'], 'ap-southeast-1' ); ?>>AP</option></select></p><p>Motore <select name="acr_engine"><option value="1" <?php selected( $settings['acr_engine'], 1 ); ?>>Fingerprinting</option><option value="2" <?php selected( $settings['acr_engine'], 2 ); ?>>Cover Song</option><option value="3" <?php selected( $settings['acr_engine'], 3 ); ?>>Entrambi</option></select> Estratto massimo <input name="acr_excerpt_seconds" value="<?php echo esc_attr( $settings['acr_excerpt_seconds'] ); ?>" size="4"> secondi consecutivi dopo il solo silenzio tecnico iniziale, senza ricampionamento o elaborazioni.</p></td></tr>
	<tr><th>Budget e costi massimi USD</th><td>Budget <input type="number" step="0.01" name="acr_monthly_budget" value="<?php echo esc_attr( $settings['acr_monthly_budget'] ); ?>"> Fingerprint <input type="number" step="0.000001" name="acr_fingerprint_max" value="<?php echo esc_attr( $settings['acr_fingerprint_max'] ); ?>"> DeepRight/min <input type="number" step="0.000001" name="acr_deepright_minute_max" value="<?php echo esc_attr( $settings['acr_deepright_minute_max'] ); ?>"> Cover/min <input type="number" step="0.000001" name="acr_cover_minute_max" value="<?php echo esc_attr( $settings['acr_cover_minute_max'] ); ?>"> Metadata <input type="number" step="0.000001" name="acr_metadata_call_max" value="<?php echo esc_attr( $settings['acr_metadata_call_max'] ); ?>"></td></tr>
	<tr><th>pCloud API</th><td><input class="regular-text" name="pcloud_api_host" value="<?php echo esc_attr( $settings['pcloud_api_host'] ); ?>"><br><input type="password" class="regular-text" name="pcloud_auth_token" placeholder="Token invariato se vuoto"><p>Il sistema usa in alternativa le credenziali WebDAV già configurate.</p></td></tr>
	<tr><th>Soglie pCloud %</th><td><input name="pcloud_warning_1" value="<?php echo esc_attr( $settings['pcloud_warning_1'] ); ?>" size="4"> / <input name="pcloud_warning_2" value="<?php echo esc_attr( $settings['pcloud_warning_2'] ); ?>" size="4"> / <input name="pcloud_warning_3" value="<?php echo esc_attr( $settings['pcloud_warning_3'] ); ?>" size="4"> / blocco <input name="pcloud_block" value="<?php echo esc_attr( $settings['pcloud_block'] ); ?>" size="4"> Margine byte <input name="pcloud_safety_bytes" value="<?php echo esc_attr( $settings['pcloud_safety_bytes'] ); ?>"></td></tr>
	<tr><th>Soglie storage %</th><td><input name="temp_warning_1" value="<?php echo esc_attr( $settings['temp_warning_1'] ); ?>" size="4"> / <input name="temp_warning_2" value="<?php echo esc_attr( $settings['temp_warning_2'] ); ?>" size="4"> / blocco <input name="temp_block" value="<?php echo esc_attr( $settings['temp_block'] ); ?>" size="4"> Moltiplicatore spazio <input name="temp_file_multiplier" value="<?php echo esc_attr( $settings['temp_file_multiplier'] ); ?>" size="5"></td></tr>
	<tr><th>Limite email giornaliero</th><td><input name="email_daily_limit" value="<?php echo esc_attr( $settings['email_daily_limit'] ); ?>"></td></tr>
	</tbody></table><p><button class="button button-primary" name="trb_resource_save" value="1">Salva configurazione</button></p></form></div>
	<?php
}
