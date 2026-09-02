<?php
/** Independent TRB monitoring for provider budgets, quotas and release jobs. */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TRB_RESOURCE_MONITOR_VERSION', '1.2.1' );

function trb_resource_settings() {
	$defaults = array(
		'admin_email' => 'info@trbrec.com',
		'acr_enabled' => 0, 'acr_paid_confirmed' => 0, 'acr_token' => '', 'acr_container_id' => '', 'acr_fingerprint_container_id' => '', 'acr_region' => 'eu-west-1',
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
		headers longtext NULL,
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

/** Close a current diagnostic without deleting its history or occurrence count. */
function trb_resource_resolve_event( $key, $resource ) {
	global $wpdb;
	$table = trb_resource_tables()['events'];
	$key = sanitize_key( $resource ) . ':' . sanitize_text_field( $key );
	return false !== $wpdb->update( $table, array( 'status' => 'resolved' ), array( 'event_key' => $key, 'status' => 'open' ) );
}

/** Close every recoverable ACR diagnostic for a track after a complete result. */
function trb_resource_resolve_acr_track_events( $release_id, $track ) {
	$release_id = absint( $release_id );
	$track = absint( $track );
	foreach ( array(
		'acr-engine-' . $release_id . '-' . $track,
		'rescan-' . $release_id . '-' . $track,
		'acr-engine-persistent-' . $release_id . '-' . $track,
		'submit-' . $release_id . '-' . $track,
		'acr-dual-' . $release_id . '-' . $track . '-1',
		'acr-dual-' . $release_id . '-' . $track . '-2',
	) as $event_key ) {
		trb_resource_resolve_event( $event_key, 'acrcloud' );
	}
	trb_resource_resolve_event( 'acr-incomplete-result-' . $release_id, 'acrcloud' );
}

function trb_resource_queue_recipient_email( $event_key, $recipient, $subject, $body, $priority = false, $headers = array() ) {
	global $wpdb;
	$table = trb_resource_tables()['notifications'];
	$recipient = sanitize_email( $recipient );
	$headers = is_array( $headers ) ? array_values( array_filter( array_map( 'sanitize_text_field', $headers ) ) ) : array();
	if ( in_array( strtolower( $recipient ), array( 'spotify4@trbrec.com', 'spotify9@trbrec.com' ), true ) ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO $table (event_key,recipient,subject,body,headers,status,attempts,last_error,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,'cancelled_qa',0,'qa_recipient_suppressed',%s,%s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			sanitize_text_field( $event_key ), $recipient, ( $priority ? '[PRIORITÀ] ' : '' ) . sanitize_text_field( $subject ), wp_kses_post( $body ), wp_json_encode( $headers ), trb_resource_now(), trb_resource_now()
		) );
		return;
	}
	$wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO $table (event_key,recipient,subject,body,headers,status,attempts,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,'pending',0,%s,%s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		sanitize_text_field( $event_key ), $recipient, ( $priority ? '[PRIORITÀ] ' : '' ) . sanitize_text_field( $subject ), wp_kses_post( $body ), wp_json_encode( $headers ), trb_resource_now(), trb_resource_now()
	) );
	if ( ! wp_next_scheduled( 'trb_resource_process_notifications' ) ) wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'trb_resource_process_notifications' );
}

function trb_resource_queue_email( $event_key, $subject, $body, $priority = false ) {
	$settings = trb_resource_settings();
	trb_resource_queue_recipient_email( $event_key, $settings['admin_email'], $subject, $body, $priority );
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
		$mail_headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$stored_headers = json_decode( (string) ( $row->headers ?? '' ), true );
		foreach ( is_array( $stored_headers ) ? $stored_headers : array() as $stored_header ) {
			if ( ! preg_match( '/^(Cc|Reply-To):\s*(.+)$/i', (string) $stored_header, $matches ) ) continue;
			$email = sanitize_email( trim( $matches[2] ) );
			if ( is_email( $email ) ) $mail_headers[] = ucfirst( strtolower( $matches[1] ) ) . ': ' . $email;
		}
		$sent = wp_mail( $row->recipient, $row->subject, $row->body, $mail_headers );
		$wpdb->update( $table, array( 'status' => $sent ? 'sent' : 'retry', 'last_error' => $sent ? '' : 'wp_mail_failed', 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
		if ( 0 === strpos( (string) $row->event_key, 'artist-pipeline-recovered-' ) ) {
			$receipts = get_option( 'trb_resource_recovery_mail_receipts', array() );
			$receipts = is_array( $receipts ) ? $receipts : array();
			$receipts[ md5( (string) $row->event_key ) ] = array(
				'role'       => in_array( 'Cc: andrea.tognassi@trbrec.com', $mail_headers, true ) ? 'artist_cc_admin' : 'artist',
				'status'     => $sent ? 'sent' : 'retry',
				'attempts'   => (int) $row->attempts + 1,
				'updated_at' => time(),
			);
			uasort( $receipts, static function( $a, $b ) { return (int) ( $b['updated_at'] ?? 0 ) <=> (int) ( $a['updated_at'] ?? 0 ); } );
			update_option( 'trb_resource_recovery_mail_receipts', array_slice( $receipts, 0, 12, true ), false );
		}
		if ( $sent ) { $sent_today++; update_option( 'trb_resource_email_sent_' . wp_date( 'Ymd' ), $sent_today, false ); }
	}
	$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status IN ('pending','retry') AND attempts<5" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $remaining && $sent_today < (int) $settings['email_daily_limit'] && ! wp_next_scheduled( 'trb_resource_process_notifications' ) ) {
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'trb_resource_process_notifications' );
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
		trb_resource_event( 'capacity', 'storage', 'critical', 'Spazio hosting insufficiente per lo staging temporaneo.', $snapshot + array( 'required' => $required ) );
		trb_resource_queue_email( 'storage-critical-' . wp_date( 'YmdH' ), 'Spazio hosting quasi esaurito', 'I nuovi WAV sono stati fermati prima del caricamento. Verificare lo spazio del filesystem e le pratiche in attesa.', true );
		return new WP_Error( 'TEMP_STORAGE_LIMIT_REACHED' );
	}
	trb_resource_resolve_event( 'capacity', 'storage' );
	if ( $snapshot['used_percent'] >= (float) $settings['temp_warning_1'] ) trb_resource_event( 'capacity-warning', 'storage', 'warning', 'Spazio hosting oltre la prima soglia.', $snapshot );
	else trb_resource_resolve_event( 'capacity-warning', 'storage' );
	if ( $snapshot['used_percent'] >= (float) $settings['temp_warning_2'] ) trb_resource_queue_email( 'storage-85-' . wp_date( 'Ym' ), 'Spazio hosting oltre la soglia di attenzione', 'Utilizzo corrente del filesystem: ' . number_format_i18n( $snapshot['used_percent'], 1 ) . '%.' );
	return true;
}

/**
 * Read quota through the same WebDAV account used by every archive pipeline.
 * pCloud WebDAV credentials are not interchangeable with API credentials.
 */
function trb_resource_pcloud_webdav_userinfo() {
	$legacy = function_exists( 'trb_demo_settings' ) ? trb_demo_settings() : array();
	if ( empty( $legacy['webdav_endpoint'] ) || empty( $legacy['pcloud_user'] ) || empty( $legacy['pcloud_pass'] ) || ! function_exists( 'curl_init' ) ) {
		return new WP_Error( 'PCLOUD_WEBDAV_AUTH_MISSING' );
	}
	$body = '<?xml version="1.0" encoding="UTF-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:quota-available-bytes/><d:quota-used-bytes/></d:prop></d:propfind>';
	$curl = curl_init( untrailingslashit( $legacy['webdav_endpoint'] ) . '/' );
	curl_setopt_array( $curl, array(
		CURLOPT_CUSTOMREQUEST  => 'PROPFIND',
		CURLOPT_POSTFIELDS     => $body,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERPWD        => $legacy['pcloud_user'] . ':' . $legacy['pcloud_pass'],
		CURLOPT_HTTPAUTH        => CURLAUTH_BASIC,
		CURLOPT_HTTPHEADER      => array( 'Depth: 0', 'Content-Type: application/xml; charset=UTF-8' ),
		CURLOPT_CONNECTTIMEOUT => 30,
		CURLOPT_TIMEOUT        => 60,
	) );
	$response = curl_exec( $curl );
	$code = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
	$error = curl_error( $curl );
	curl_close( $curl );
	if ( false === $response || ! in_array( $code, array( 200, 207 ), true ) ) {
		return new WP_Error( 'PCLOUD_WEBDAV_QUOTA_FAILED', $error ? $error : 'WebDAV PROPFIND ' . $code );
	}
	$available_match = array();
	$used_match = array();
	$has_available = preg_match( '/<(?:[^:>]+:)?quota-available-bytes\b[^>]*>\s*(\d+)/i', (string) $response, $available_match );
	$has_used = preg_match( '/<(?:[^:>]+:)?quota-used-bytes\b[^>]*>\s*(\d+)/i', (string) $response, $used_match );
	if ( ! $has_available || ! $has_used ) return new WP_Error( 'PCLOUD_WEBDAV_QUOTA_UNAVAILABLE' );
	$free = (float) $available_match[1];
	$used = (float) $used_match[1];
	$quota = $free + $used;
	if ( $quota <= 0 ) return new WP_Error( 'PCLOUD_WEBDAV_QUOTA_INVALID' );
	return array(
		'quota'        => $quota,
		'usedquota'    => $used,
		'free'         => $free,
		'used_percent' => ( $used / $quota ) * 100,
		'source'       => 'webdav',
	);
}

function trb_resource_pcloud_userinfo() {
	$settings = trb_resource_settings();
	$host = in_array( untrailingslashit( $settings['pcloud_api_host'] ), array( 'https://api.pcloud.com', 'https://eapi.pcloud.com' ), true ) ? untrailingslashit( $settings['pcloud_api_host'] ) : 'https://eapi.pcloud.com';
	$data = array();
	if ( ! empty( $settings['pcloud_auth_token'] ) ) {
		$response = wp_remote_post( $host . '/userinfo', array( 'timeout' => 30, 'body' => array( 'auth' => $settings['pcloud_auth_token'] ) ) );
		if ( ! is_wp_error( $response ) ) $data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! empty( $data['result'] ) || empty( $data['quota'] ) || ! isset( $data['usedquota'] ) ) return new WP_Error( 'PCLOUD_USERINFO_FAILED' );
		$data['used_percent'] = ( (float) $data['usedquota'] / (float) $data['quota'] ) * 100;
		$data['free'] = (float) $data['quota'] - (float) $data['usedquota'];
		$data['source'] = 'api';
	} else {
		$legacy = function_exists( 'trb_demo_settings' ) ? trb_demo_settings() : array();
		if ( empty( $legacy['pcloud_user'] ) || empty( $legacy['pcloud_pass'] ) ) return new WP_Error( 'PCLOUD_AUTH_MISSING' );
		$legacy_host = ! empty( $legacy['webdav_endpoint'] ) && false !== stripos( $legacy['webdav_endpoint'], 'ewebdav.pcloud.com' ) ? 'https://eapi.pcloud.com' : 'https://api.pcloud.com';
		$response = wp_remote_post( $legacy_host . '/userinfo', array(
			'timeout' => 30,
			'body'    => array( 'username' => $legacy['pcloud_user'], 'password' => $legacy['pcloud_pass'] ),
		) );
		if ( ! is_wp_error( $response ) ) $data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! empty( $data['result'] ) || empty( $data['quota'] ) || ! isset( $data['usedquota'] ) ) {
			$data = trb_resource_pcloud_webdav_userinfo();
			if ( is_wp_error( $data ) ) return $data;
		} else {
			$data['used_percent'] = ( (float) $data['usedquota'] / (float) $data['quota'] ) * 100;
			$data['free'] = (float) $data['quota'] - (float) $data['usedquota'];
			$data['source'] = 'api-' . ( 'https://eapi.pcloud.com' === $legacy_host ? 'eu' : 'us' );
		}
	}
	update_option( 'trb_resource_pcloud_snapshot', array( 'time' => time(), 'data' => $data ), false );
	$pcloud_settings = trb_resource_settings();
	trb_resource_resolve_event( 'userinfo', 'pcloud' );
	if ( $data['used_percent'] < (float) $pcloud_settings['pcloud_block'] && $data['free'] >= (float) $pcloud_settings['pcloud_safety_bytes'] ) trb_resource_resolve_event( 'quota', 'pcloud' );
	if ( $data['used_percent'] >= (float) $pcloud_settings['pcloud_warning_1'] ) trb_resource_event( 'quota-warning', 'pcloud', 'warning', 'Utilizzo pCloud oltre la prima soglia.', array( 'used_percent' => $data['used_percent'] ) );
	else trb_resource_resolve_event( 'quota-warning', 'pcloud' );
	if ( $data['used_percent'] >= (float) $pcloud_settings['pcloud_warning_2'] ) trb_resource_queue_email( 'pcloud-85-' . wp_date( 'Ym' ), 'Quota pCloud oltre la soglia di attenzione', 'Spazio utilizzato: ' . number_format_i18n( $data['used_percent'], 1 ) . '%.' );
	if ( $data['used_percent'] >= (float) $pcloud_settings['pcloud_warning_3'] ) trb_resource_queue_email( 'pcloud-95-' . wp_date( 'Ym' ), 'Quota pCloud quasi esaurita', 'Spazio utilizzato: ' . number_format_i18n( $data['used_percent'], 1 ) . '%.', true );
	return $data;
}

function trb_resource_pcloud_guard( $incoming_bytes ) {
	$settings = trb_resource_settings();
	$data = trb_resource_pcloud_userinfo();
	if ( is_wp_error( $data ) ) {
		trb_resource_event( 'userinfo', 'pcloud', 'warning', 'Quota pCloud non verificabile; il trasferimento atomico resta protetto dalla risposta WebDAV.', array( 'code' => $data->get_error_code() ) );
		return array( 'quota_verified' => false, 'source' => 'webdav-transfer-guard' );
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
	return trb_resource_submit_acr_file_to_container( $path, $name, $s['acr_container_id'] );
}

function trb_resource_submit_acr_file_to_container( $path, $name, $container_id ) {
	$s = trb_resource_settings();
	if ( ! function_exists( 'curl_init' ) || ! class_exists( 'CURLFile' ) ) return new WP_Error( 'ACR_TRANSPORT_UNAVAILABLE' );
	$url = trb_resource_acr_endpoint() . '/api/fs-containers/' . rawurlencode( $container_id ) . '/files';
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

/**
 * Normalize ACRCloud File Scanning responses.
 *
 * The file-detail endpoint can return data either as the file object itself or
 * as a one-element list. Treating the latter as an object leaves state unset
 * and causes completed jobs to be polled until they fail as ACR_STATE_0.
 */
function trb_resource_acr_response_item( $data, $provider_reference = '' ) {
	if ( ! is_array( $data ) ) return array();
	$payload = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : $data;
	if ( isset( $payload['id'] ) || isset( $payload['state'] ) ) return $payload;
	foreach ( $payload as $item ) {
		if ( ! is_array( $item ) ) continue;
		if ( $provider_reference && isset( $item['id'] ) && hash_equals( (string) $provider_reference, (string) $item['id'] ) ) return $item;
	}
	foreach ( $payload as $item ) if ( is_array( $item ) ) return $item;
	return array();
}

function trb_resource_settle_acr_companion_usage( $row, $status, $last_error = '' ) {
	global $wpdb;
	$table = trb_resource_tables()['usage'];
	$wpdb->query( $wpdb->prepare(
		"UPDATE $table SET status=%s,last_error=%s,updated_at=%s WHERE release_id=%d AND track_index=%d AND file_hash=%s AND provider='acrcloud' AND service IN ('deepright','cover_song','metadata') AND status='estimated'",
		$status, $last_error, trb_resource_now(), (int) $row->release_id, (int) $row->track_index, (string) $row->file_hash
	) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/** Re-run an existing provider file after the container policy was corrected. */
function trb_resource_rescan_acr_file( $provider_reference ) {
	$s = trb_resource_settings();
	if ( empty( $provider_reference ) || empty( $s['acr_token'] ) || empty( $s['acr_container_id'] ) ) return new WP_Error( 'ACR_RESCAN_CONFIGURATION_MISSING' );
	$url = trb_resource_acr_endpoint() . '/api/fs-containers/' . rawurlencode( $s['acr_container_id'] ) . '/files/' . rawurlencode( $provider_reference ) . '/rescan';
	$response = wp_remote_request( $url, array( 'method' => 'PUT', 'timeout' => 60, 'headers' => array( 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $s['acr_token'] ) ) );
	if ( is_wp_error( $response ) ) return $response;
	$code = (int) wp_remote_retrieve_response_code( $response );
	return in_array( $code, array( 200, 201, 202, 204 ), true ) ? true : new WP_Error( 'ACR_RESCAN_FAILED', 'HTTP ' . $code );
}

/** Track the bounded recovery stage for a provider object created with an old engine. */
function trb_resource_acr_configuration_generation() {
	return sanitize_key( (string) get_option( 'trb_acr_configuration_generation', '' ) );
}

function trb_resource_acr_engine_recovery_stage( $release_id, $file_hash ) {
	$stages = get_post_meta( absint( $release_id ), '_trb_acr_engine_recovery_stages', true );
	$stages = is_array( $stages ) ? $stages : array();
	if ( ! isset( $stages[ $file_hash ] ) ) return 0;
	$entry = $stages[ $file_hash ];
	$generation = trb_resource_acr_configuration_generation();
	if ( is_array( $entry ) ) return (string) ( $entry['generation'] ?? '' ) === $generation ? absint( $entry['stage'] ?? 0 ) : 0;
	return '' === $generation ? absint( $entry ) : 0;
}

function trb_resource_set_acr_engine_recovery_stage( $release_id, $file_hash, $stage ) {
	$release_id = absint( $release_id );
	$stages = get_post_meta( $release_id, '_trb_acr_engine_recovery_stages', true );
	$stages = is_array( $stages ) ? $stages : array();
	if ( $stage > 0 ) $stages[ $file_hash ] = array( 'stage' => absint( $stage ), 'generation' => trb_resource_acr_configuration_generation() );
	else unset( $stages[ $file_hash ] );
	if ( $stages ) update_post_meta( $release_id, '_trb_acr_engine_recovery_stages', $stages );
	else delete_post_meta( $release_id, '_trb_acr_engine_recovery_stages' );
}

/** Retry transient container/configuration failures without leaving a release silent. */
function trb_resource_schedule_analysis_configuration_retry( $release_id, $error_code ) {
	$release_id = absint( $release_id );
	$attempts = absint( get_post_meta( $release_id, '_trb_acr_configuration_retry_attempts', true ) ) + 1;
	update_post_meta( $release_id, '_trb_acr_configuration_retry_attempts', $attempts );
	update_post_meta( $release_id, '_trb_acr_configuration_last_error', sanitize_key( $error_code ) );
	if ( ! wp_next_scheduled( 'trb_resource_start_release_analysis_manual', array( $release_id ) ) ) wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'trb_resource_start_release_analysis_manual', array( $release_id ) );
	if ( $attempts >= 5 && function_exists( 'trb_resource_queue_email' ) ) {
		$release = get_post( $release_id );
		$body = '<p>La pratica #' . $release_id . ' (' . esc_html( $release ? $release->post_title : '' ) . ') non ha ancora superato la verifica live del container ACRCloud dopo ' . $attempts . ' tentativi.</p><p>Ultimo codice: <strong>' . esc_html( $error_code ) . '</strong>.</p>';
		trb_resource_queue_email( 'acr-configuration-retry-' . $release_id . '-' . wp_date( 'Ymd' ), 'Verifica ACRCloud ancora in attesa', $body, true );
	}
}

/** Finish one track only after independent exact and cover scans are complete. */
function trb_resource_finalize_dual_acr_track( $release_id, $track, $hash ) {
	global $wpdb; $table = trb_resource_tables()['usage'];
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT service,status,payload FROM $table WHERE release_id=%d AND track_index=%d AND file_hash=%s AND provider='acrcloud' AND service IN ('fingerprinting_exact','cover_song_scan') ORDER BY id DESC",
		absint( $release_id ), absint( $track ), (string) $hash
	), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$parts = array();
	foreach ( $rows as $row ) if ( 'completed' === $row['status'] && ! isset( $parts[ $row['service'] ] ) ) $parts[ $row['service'] ] = json_decode( $row['payload'], true );
	if ( empty( $parts['fingerprinting_exact'] ) || empty( $parts['cover_song_scan'] ) ) return false;
	$exact = is_array( $parts['fingerprinting_exact'] ) ? $parts['fingerprinting_exact'] : array();
	$cover = is_array( $parts['cover_song_scan'] ) ? $parts['cover_song_scan'] : array();
	$merged_results = array();
	foreach ( array( $exact['results'] ?? array(), $cover['results'] ?? array() ) as $result_set ) {
		if ( ! is_array( $result_set ) ) continue;
		foreach ( $result_set as $group => $items ) {
			if ( ! is_array( $items ) ) continue;
			$merged_results[ $group ] = array_merge( $merged_results[ $group ] ?? array(), $items );
		}
	}
	$merged = $exact;
	$merged['engine'] = 3;
	$merged['state'] = ( ! empty( $merged_results ) || 1 === (int) ( $exact['state'] ?? -1 ) || 1 === (int) ( $cover['state'] ?? -1 ) ) ? 1 : -1;
	$merged['results'] = $merged_results ?: null;
	$merged['deepright'] = ! empty( $cover['deepright'] );
	$merged['trb_dual_engine'] = true;
	$key = hash( 'sha256', 'acrcloud-dual-merged|' . $hash . '|release:' . absint( $release_id ) . '|track:' . absint( $track ) );
	$ledger_id = trb_resource_usage_reserve( array(
		'service' => 'fingerprinting', 'idempotency_key' => $key, 'release_id' => $release_id,
		'track_index' => $track, 'file_hash' => $hash, 'units' => 0, 'cost_max' => 0,
		'cost_estimated' => 0, 'status' => 'completed', 'payload' => wp_json_encode( $merged ),
	) );
	$wpdb->update( $table, array( 'status' => 'completed', 'payload' => wp_json_encode( $merged ), 'last_error' => '', 'updated_at' => trb_resource_now() ), array( 'id' => $ledger_id ) );
	trb_resource_resolve_acr_track_events( $release_id, $track );
	return true;
}

/** Reconcile historical open events for tracks already completed by the dual scan. */
function trb_resource_reconcile_completed_dual_acr_events() {
	if ( '20260825.1' === get_option( 'trb_resource_event_reconciliation_version' ) ) return;
	global $wpdb;
	$table = trb_resource_tables()['usage'];
	$payload_marker = '%' . $wpdb->esc_like( '"trb_dual_engine"' ) . '%';
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT DISTINCT release_id,track_index FROM $table WHERE provider='acrcloud' AND service='fingerprinting' AND status='completed' AND payload LIKE %s",
		$payload_marker
	), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( (array) $rows as $row ) trb_resource_resolve_acr_track_events( $row['release_id'], $row['track_index'] );
	update_option( 'trb_resource_event_reconciliation_version', '20260825.1', false );
}
add_action( 'init', 'trb_resource_reconcile_completed_dual_acr_events', 31 );

function trb_resource_poll_dual_acr_job( $ledger_id ) {
	global $wpdb; $table = trb_resource_tables()['usage'];
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", absint( $ledger_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $row || ! $row->provider_reference || 'cancelled' === $row->status || 'trash' === get_post_status( $row->release_id ) ) return;
	$envelope = json_decode( (string) $row->payload, true );
	$container_id = absint( $envelope['trb_container_id'] ?? 0 );
	$expected_engine = absint( $envelope['trb_expected_engine'] ?? 0 );
	if ( ! $container_id || ! in_array( $expected_engine, array( 1, 2 ), true ) ) return;
	$s = trb_resource_settings();
	$url = trb_resource_acr_endpoint() . '/api/fs-containers/' . rawurlencode( $container_id ) . '/files/' . rawurlencode( $row->provider_reference );
	$response = wp_remote_get( $url, array( 'timeout' => 60, 'headers' => array( 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $s['acr_token'] ) ) );
	$http_code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$data = ! is_wp_error( $response ) ? json_decode( wp_remote_retrieve_body( $response ), true ) : array();
	$item = trb_resource_acr_response_item( $data, $row->provider_reference );
	$state = isset( $item['state'] ) ? (int) $item['state'] : 0;
	if ( ( is_wp_error( $response ) || $http_code < 200 || $http_code >= 300 || ! $item || 0 === $state ) && (int) $row->attempts < 30 ) {
		$error = is_wp_error( $response ) ? $response->get_error_code() : ( $http_code && ! $item ? 'ACR_RESPONSE_INVALID' : '' );
		$wpdb->update( $table, array( 'status' => 'processing', 'attempts' => (int) $row->attempts + 1, 'last_error' => $error, 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'trb_resource_poll_dual_acr_job', array( (int) $row->id ) );
		return;
	}
	$reported_engine = absint( $item['engine'] ?? 0 );
	if ( ! in_array( $state, array( 1, -1 ), true ) || $reported_engine !== $expected_engine ) {
		$error = 'ACR_DUAL_ENGINE_MISMATCH_' . $reported_engine . '_EXPECTED_' . $expected_engine;
		$wpdb->update( $table, array( 'status' => 'error', 'payload' => wp_json_encode( $item ), 'last_error' => $error, 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
		update_post_meta( $row->release_id, '_trb_release_pipeline_status', 'manual_review' );
		trb_resource_event( 'acr-dual-' . $row->release_id . '-' . $row->track_index . '-' . $expected_engine, 'acrcloud', 'critical', 'Una delle due analisi copyright indipendenti non ha usato il motore previsto.', array( 'reported_engine' => $reported_engine, 'expected_engine' => $expected_engine ) );
		return;
	}
	$wpdb->update( $table, array( 'status' => 'completed', 'payload' => wp_json_encode( $item ), 'last_error' => '', 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
	trb_resource_finalize_dual_acr_track( $row->release_id, $row->track_index, (string) $row->file_hash );
	$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE release_id=%d AND service IN ('fingerprinting_exact','cover_song_scan') AND status IN ('reserved','submitted','processing')", $row->release_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( 0 === $pending ) {
		update_post_meta( $row->release_id, '_trb_release_pipeline_status', 'copyright_review' );
		if ( function_exists( 'trb_analysis_decide_release' ) ) trb_analysis_decide_release( absint( $row->release_id ) );
	}
}
add_action( 'trb_resource_poll_dual_acr_job', 'trb_resource_poll_dual_acr_job' );

function trb_resource_start_dual_acr_analysis( $release_id ) {
	if ( 'trash' === get_post_status( $release_id ) || get_post_meta( $release_id, '_trb_owner_cancelled_at', true ) ) return new WP_Error( 'TRB_RELEASE_CANCELLED' );
	$s = trb_resource_settings();
	$containers = array( 'fingerprinting_exact' => absint( $s['acr_fingerprint_container_id'] ?? 0 ), 'cover_song_scan' => absint( $s['acr_container_id'] ?? 0 ) );
	if ( ! $containers['fingerprinting_exact'] || ! $containers['cover_song_scan'] ) return new WP_Error( 'ACR_DUAL_CONFIGURATION_INCOMPLETE' );
	$files = (array) get_post_meta( $release_id, '_trb_release_files', true );
	update_post_meta( $release_id, '_trb_release_pipeline_status', 'analysis_in_progress' );
	global $wpdb; $table = trb_resource_tables()['usage'];
	foreach ( $files as $file ) {
		if ( 'audio' !== ( $file['kind'] ?? '' ) ) continue;
		$hash = (string) ( $file['sha256'] ?? '' ); $track = absint( $file['track'] ?? 0 );
		$local = function_exists( 'trb_release_pcloud_local_file' ) ? trb_release_pcloud_local_file( $file ) : '';
		if ( ! $local ) { update_post_meta( $release_id, '_trb_release_pipeline_status', 'manual_review' ); return new WP_Error( 'ACR_LOCAL_FILE_MISSING' ); }
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) $hash = hash_file( 'sha256', $local );
		$duration = ! empty( $file['audio_spec']['duration_seconds'] ) ? (float) $file['audio_spec']['duration_seconds'] : 0;
		$minutes = max( 1, ceil( $duration / 60 ) );
		$maximum = (float) $s['acr_fingerprint_max'] + $minutes * (float) $s['acr_cover_minute_max'];
		$guard = trb_resource_acr_budget_guard( $maximum, $release_id );
		if ( is_wp_error( $guard ) ) { update_post_meta( $release_id, '_trb_release_pipeline_status', 'ACR_BUDGET_LIMIT_REACHED' ); return $guard; }
		$excerpt = trb_resource_create_excerpt( $local, $release_id, $track );
		if ( is_wp_error( $excerpt ) ) { update_post_meta( $release_id, '_trb_release_pipeline_status', 'manual_review' ); return $excerpt; }
		foreach ( $containers as $service => $container_id ) {
			$expected_engine = 'fingerprinting_exact' === $service ? 1 : 2;
			$key = hash( 'sha256', 'acrcloud-dual|' . $service . '|' . $container_id . '|' . $hash . '|release:' . absint( $release_id ) . '|track:' . $track );
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE idempotency_key=%s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $existing && in_array( $existing->status, array( 'submitted', 'processing' ), true ) ) {
				if ( ! wp_next_scheduled( 'trb_resource_poll_dual_acr_job', array( (int) $existing->id ) ) ) wp_schedule_single_event( time() + 5, 'trb_resource_poll_dual_acr_job', array( (int) $existing->id ) );
				continue;
			}
			if ( $existing && 'completed' === $existing->status ) { trb_resource_finalize_dual_acr_track( $release_id, $track, $hash ); continue; }
			$job_cost = 'fingerprinting_exact' === $service ? (float) $s['acr_fingerprint_max'] : $minutes * (float) $s['acr_cover_minute_max'];
			$ledger_id = trb_resource_usage_reserve( array( 'service' => $service, 'idempotency_key' => $key, 'release_id' => $release_id, 'track_index' => $track, 'file_hash' => $hash, 'cost_max' => $job_cost, 'cost_estimated' => $job_cost, 'status' => 'reserved' ) );
			$name = 'trb-' . $hash . '-' . $service . '-r' . absint( $release_id ) . '-t' . $track . '.wav';
			$result = trb_resource_submit_acr_file_to_container( $excerpt, $name, $container_id );
			if ( is_wp_error( $result ) ) {
				$wpdb->update( $table, array( 'status' => 'error', 'last_error' => $result->get_error_code(), 'updated_at' => trb_resource_now() ), array( 'id' => $ledger_id ) );
				wp_delete_file( $excerpt ); update_post_meta( $release_id, '_trb_release_pipeline_status', 'manual_review' ); return $result;
			}
			$envelope = array( 'trb_container_id' => $container_id, 'trb_expected_engine' => $expected_engine, 'trb_provider' => $result );
			$wpdb->update( $table, array( 'status' => 'submitted', 'provider_reference' => sanitize_text_field( $result['id'] ), 'attempts' => 1, 'last_error' => '', 'payload' => wp_json_encode( $envelope ), 'updated_at' => trb_resource_now() ), array( 'id' => $ledger_id ) );
			wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'trb_resource_poll_dual_acr_job', array( $ledger_id ) );
		}
		wp_delete_file( $excerpt );
	}
	return true;
}

function trb_resource_start_release_analysis( $release_id ) {
	if ( 'trash' === get_post_status( $release_id ) || get_post_meta( $release_id, '_trb_owner_cancelled_at', true ) ) return;
	$s = trb_resource_settings();
	$technical = (array) get_post_meta( $release_id, '_trb_release_technical_analysis', true );
	if ( ! in_array( $technical['status'] ?? '', array( 'passed', 'warning' ), true ) ) return;
	if ( empty( $s['acr_enabled'] ) || empty( $s['acr_paid_confirmed'] ) || empty( $s['acr_token'] ) || empty( $s['acr_container_id'] ) ) {
		update_post_meta( $release_id, '_trb_release_pipeline_status', 'analysis_waiting_configuration' );
		trb_resource_schedule_analysis_configuration_retry( $release_id, 'ACR_CONFIGURATION_INCOMPLETE' );
		return;
	}
	if ( ! empty( $s['acr_fingerprint_container_id'] ) ) {
		trb_resource_start_dual_acr_analysis( $release_id );
		return;
	}
	if ( function_exists( 'trb_analysis_verify_acr_container' ) ) {
		$container = trb_analysis_verify_acr_container();
		if ( is_wp_error( $container ) ) {
			update_post_meta( $release_id, '_trb_release_pipeline_status', 'analysis_waiting_configuration' );
			trb_resource_event( 'container-' . trb_resource_period_key(), 'acrcloud', 'critical', 'Container ACRCloud non verificato o non conforme.', array( 'code' => $container->get_error_code(), 'message' => $container->get_error_message() ) );
			trb_resource_schedule_analysis_configuration_retry( $release_id, $container->get_error_code() );
			return;
		}
	}
	delete_post_meta( $release_id, '_trb_acr_configuration_retry_attempts' );
	delete_post_meta( $release_id, '_trb_acr_configuration_last_error' );
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
		$provider_name_suffix = '';
		$engine_replacement_stage = 0;
		// A failed row belongs to the release that created it. Reusing that row
		// for another release would make the provider callback complete the old
		// practice and leave the new one permanently in progress.
		if ( $existing && 'error' === $existing->status && (int) $existing->release_id !== (int) $release_id ) {
			$key = hash( 'sha256', $key . '|retry-release:' . absint( $release_id ) . '|track:' . $track );
			$provider_name_suffix = '-r' . absint( $release_id ) . '-' . $track;
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE idempotency_key=%s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
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
			} elseif ( 'completed' !== $existing->status ) {
				$waiting = true;
				if ( ! wp_next_scheduled( 'trb_resource_poll_acr_job', array( (int) $existing->id ) ) ) {
					wp_schedule_single_event( time() + 5, 'trb_resource_poll_acr_job', array( (int) $existing->id ) );
				}
			}
				continue;
			}
			if ( $existing && 'error' === $existing->status && ( 0 === strpos( (string) $existing->last_error, 'ACR_ENGINE_MISMATCH_' ) || 'ACR_FILE_NOT_FOUND_IN_CONTAINER' === (string) $existing->last_error ) && ! empty( $existing->provider_reference ) ) {
				$engine_recovery_stage = trb_resource_acr_engine_recovery_stage( $release_id, $hash );
				$rescan = $engine_recovery_stage < 1 && 'ACR_FILE_NOT_FOUND_IN_CONTAINER' !== (string) $existing->last_error ? trb_resource_rescan_acr_file( $existing->provider_reference ) : new WP_Error( 'ACR_RESCAN_ALREADY_ATTEMPTED' );
				if ( ! is_wp_error( $rescan ) ) {
					trb_resource_set_acr_engine_recovery_stage( $release_id, $hash, 1 );
					$wpdb->update( $table, array( 'status' => 'submitted', 'attempts' => 1, 'last_error' => '', 'updated_at' => trb_resource_now() ), array( 'id' => (int) $existing->id ) );
					$wpdb->query( $wpdb->prepare(
						"UPDATE $table SET status='estimated',last_error='',updated_at=%s WHERE release_id=%d AND track_index=%d AND file_hash=%s AND provider='acrcloud' AND service IN ('deepright','cover_song','metadata') AND status='error'",
						trb_resource_now(), (int) $existing->release_id, (int) $existing->track_index, (string) $existing->file_hash
					) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$waiting = true;
					if ( ! wp_next_scheduled( 'trb_resource_poll_acr_job', array( (int) $existing->id ) ) ) wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'trb_resource_poll_acr_job', array( (int) $existing->id ) );
					continue;
				}
				if ( $engine_recovery_stage < 1 ) trb_resource_event( 'rescan-' . $release_id . '-' . $track, 'acrcloud', 'critical', 'Nuova scansione ACRCloud non avviata.', array( 'code' => $rescan->get_error_code() ) );
				if ( $engine_recovery_stage < 2 ) {
					// The old object keeps the engine used when it was created. Use a
					// deterministic replacement name so the current engine-3 policy is
					// applied without repeatedly purchasing new scans on later retries.
					$generation = trb_resource_acr_configuration_generation();
					$provider_name_suffix .= '-engine3' . ( $generation ? '-g' . $generation : '' ) . '-r' . absint( $release_id ) . '-t' . $track . '-v2';
					$engine_replacement_stage = 2;
				} else {
					update_post_meta( $release_id, '_trb_release_pipeline_status', 'manual_review' );
					trb_resource_event( 'acr-engine-persistent-' . $release_id . '-' . $track, 'acrcloud', 'critical', 'Il file sostitutivo ACRCloud non ha applicato il motore combinato.', array( 'expected_engine' => 3, 'file_hash' => $hash ) );
					$release = get_post( $release_id );
					$body = '<p>La pratica #' . absint( $release_id ) . ' (' . esc_html( $release ? $release->post_title : '' ) . ') richiede verifica manuale: anche il file sostitutivo ACRCloud non ha applicato il motore combinato fingerprinting + Cover Song.</p><p>Nessun contratto è stato inviato automaticamente.</p>';
					trb_resource_queue_email( 'acr-engine-persistent-' . $release_id . '-' . $track, 'Analisi copyright da verificare manualmente', $body, true );
					$waiting = true;
					continue;
				}
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
		$provider_name = 'trb-' . $hash . $provider_name_suffix . '.wav';
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
		$wpdb->update( $table, array( 'status' => 'submitted', 'provider_reference' => sanitize_text_field( $result['id'] ), 'attempts' => 1, 'last_error' => '', 'payload' => wp_json_encode( $result ), 'updated_at' => trb_resource_now() ), array( 'id' => $ledger_id ) );
		if ( $engine_replacement_stage ) {
			trb_resource_set_acr_engine_recovery_stage( $release_id, $hash, $engine_replacement_stage );
			$wpdb->query( $wpdb->prepare(
				"UPDATE $table SET status='estimated',last_error='',updated_at=%s WHERE release_id=%d AND track_index=%d AND file_hash=%s AND provider='acrcloud' AND service IN ('deepright','cover_song','metadata') AND status='error'",
				trb_resource_now(), $release_id, $track, $hash
			) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
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
	if ( ! $row || ! $row->provider_reference || 'cancelled' === $row->status || 'trash' === get_post_status( $row->release_id ) ) return;
	$s = trb_resource_settings();
	$url = trb_resource_acr_endpoint() . '/api/fs-containers/' . rawurlencode( $s['acr_container_id'] ) . '/files/' . rawurlencode( $row->provider_reference );
	$response = wp_remote_get( $url, array( 'timeout' => 60, 'headers' => array( 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $s['acr_token'] ) ) );
	$http_code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$data = ! is_wp_error( $response ) ? json_decode( wp_remote_retrieve_body( $response ), true ) : array();
	$item = trb_resource_acr_response_item( $data, $row->provider_reference );
	$state = isset( $item['state'] ) ? (int) $item['state'] : 0;
	$transport_error = is_wp_error( $response ) || $http_code < 200 || $http_code >= 300 || ! $item;
	// After an atomic container switch an old provider reference is not present
	// in the new regional container. A successful empty response is terminal for
	// that reference: recreate the file once instead of polling it 30 times.
	if ( ! is_wp_error( $response ) && $http_code >= 200 && $http_code < 300 && ! $item ) {
		$missing_code = 'ACR_FILE_NOT_FOUND_IN_CONTAINER';
		$wpdb->update( $table, array( 'status' => 'error', 'last_error' => $missing_code, 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
		trb_resource_settle_acr_companion_usage( $row, 'error', $missing_code );
		trb_resource_set_acr_engine_recovery_stage( $row->release_id, (string) $row->file_hash, 1 );
		update_post_meta( $row->release_id, '_trb_release_pipeline_status', 'analysis_waiting_configuration' );
		if ( ! wp_next_scheduled( 'trb_resource_start_release_analysis_manual', array( absint( $row->release_id ) ) ) ) wp_schedule_single_event( time() + 5, 'trb_resource_start_release_analysis_manual', array( absint( $row->release_id ) ) );
		return;
	}
	if ( $transport_error && (int) $row->attempts < 30 ) {
		$error = is_wp_error( $response ) ? $response->get_error_code() : ( $http_code ? 'ACR_HTTP_' . $http_code : 'ACR_RESPONSE_INVALID' );
		$wpdb->update( $table, array( 'status' => 'processing', 'attempts' => (int) $row->attempts + 1, 'last_error' => $error, 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'trb_resource_poll_acr_job', array( (int) $row->id ) ); return;
	}
	if ( 0 === $state && (int) $row->attempts < 30 ) {
		$wpdb->update( $table, array( 'status' => 'processing', 'attempts' => (int) $row->attempts + 1, 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'trb_resource_poll_acr_job', array( (int) $row->id ) ); return;
	}
	$reported_engine = isset( $item['engine'] ) ? (int) $item['engine'] : 0;
	if ( ! $transport_error && in_array( $state, array( 1, -1 ), true ) && 3 !== $reported_engine ) {
		$wpdb->update( $table, array( 'status' => 'error', 'payload' => wp_json_encode( $item ), 'last_error' => 'ACR_ENGINE_MISMATCH_' . $reported_engine, 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
		trb_resource_settle_acr_companion_usage( $row, 'error', 'ACR_ENGINE_MISMATCH_' . $reported_engine );
		update_post_meta( $row->release_id, '_trb_release_pipeline_status', 'analysis_waiting_configuration' );
		trb_resource_event( 'acr-engine-' . $row->release_id . '-' . $row->track_index, 'acrcloud', 'critical', 'Il provider non ha eseguito fingerprinting e cover detection insieme.', array( 'reported_engine' => $reported_engine, 'expected_engine' => 3 ) );
		return;
	}
	$status = ! $transport_error && ( 1 === $state || -1 === $state ) ? 'completed' : 'error';
	$last_error = 'error' === $status ? ( $transport_error ? ( $http_code ? 'ACR_HTTP_' . $http_code : 'ACR_RESPONSE_INVALID' ) : 'ACR_STATE_' . $state ) : '';
	$wpdb->update( $table, array( 'status' => $status, 'payload' => wp_json_encode( $item ), 'last_error' => $last_error, 'updated_at' => trb_resource_now() ), array( 'id' => $row->id ) );
	trb_resource_settle_acr_companion_usage( $row, $status, $last_error );
	if ( 'completed' === $status ) {
		trb_resource_set_acr_engine_recovery_stage( $row->release_id, (string) $row->file_hash, 0 );
		$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE release_id=%d AND status IN ('reserved','submitted','processing')", $row->release_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 0 === $pending ) {
			update_post_meta( $row->release_id, '_trb_release_pipeline_status', 'copyright_review' );
			if ( function_exists( 'trb_analysis_decide_release' ) ) trb_analysis_decide_release( absint( $row->release_id ) );
		}
	} else update_post_meta( $row->release_id, '_trb_release_pipeline_status', 'manual_review' );
}
add_action( 'trb_resource_poll_acr_job', 'trb_resource_poll_acr_job' );

/**
 * Resume release jobs whose one-shot cron event was lost or whose status was
 * written by an older pipeline revision. Provider calls remain idempotent by
 * release audio hash, so recovery never purchases the same analysis twice.
 */
function trb_resource_artist_legal_greeting_name( $user ) {
	if ( ! $user instanceof WP_User ) return 'Artista';
	$name = trim( (string) $user->first_name );
	if ( '' === $name && function_exists( 'trb_portal_artist_profile_value' ) ) {
		$name = trim( (string) trb_portal_artist_profile_value( 'legal_name', $user->ID ) );
	}
	return '' !== $name ? $name : 'Artista';
}

function trb_resource_artist_email_signature() {
	return '<p>Sezione Contratti e Distribuzione<br><strong>TRB rec – Music Publishing</strong> · <a href="https://trbrec.com">trbrec.com</a><br>P. IVA 02846170989 · REA BS-483571 · SDI 095EI9R</p><p><em>Privacy notice — This email and its contents are confidential and intended solely for the recipients. If you received it in error, please delete it and notify the sender.</em></p>';
}

function trb_resource_artist_recovery_cc_headers() {
	return array( 'Cc: andrea.tognassi@trbrec.com' );
}

function trb_resource_notify_artist_pipeline_recovery( $release_id, $previous_status, $event_suffix = '' ) {
	/*
	 * Artist-facing recovery messages are reserved for a confirmed incident.
	 * The watchdog also performs harmless/idempotent retries during normal
	 * processing; those retries must never be presented as a portal outage.
	 */
	$event_suffix = sanitize_key( $event_suffix );
	$confirmed_incident = 0 === strpos( $event_suffix, 'confirmed-incident-' ) || 0 === strpos( $event_suffix, 'manual-resend-' );
	if ( ! $confirmed_incident || ! function_exists( 'trb_resource_queue_recipient_email' ) ) return false;
	$release = get_post( absint( $release_id ) );
	$user = $release ? get_userdata( $release->post_author ) : false;
	if ( ! $release || ! $user || ! is_email( $user->user_email ) ) return false;
	$name = trb_resource_artist_legal_greeting_name( $user );
	$link = get_permalink( get_option( 'trb_portal_dashboard_created' ) ) . '#release-files-' . absint( $release_id );
	$subject = 'Aggiornamento sulla lavorazione della release ' . $release->post_title;
	$body = '<p>Gentile ' . esc_html( $name ) . ',</p><p>ti informiamo che il Portale Artisti ha rilevato un’interruzione temporanea durante l’elaborazione della release “<strong>' . esc_html( $release->post_title ) . '</strong>”.</p>';
	$body .= '<p>La problematica tecnica è stata risolta e la lavorazione è ripresa regolarmente. Non è richiesta alcuna azione da parte tua: <strong>non caricare nuovamente i materiali e non creare una pratica duplicata</strong>.</p>';
	$body .= '<p>Puoi verificare in qualsiasi momento lo stato aggiornato della release accedendo alla tua area riservata:</p><p><a href="' . esc_url( $link ) . '">Apri la release nel Portale Artisti</a></p>';
	$body .= '<p>Qualora durante i controlli emergesse la necessità di modificare o sostituire uno dei materiali inviati, riceverai una comunicazione specifica contenente tutte le indicazioni necessarie.</p><p>Ci scusiamo per il temporaneo inconveniente e ti ringraziamo per la collaborazione.</p>' . trb_resource_artist_email_signature();
	$key = 'artist-pipeline-recovered-' . absint( $release_id ) . '-' . sanitize_key( $previous_status ) . '-' . $event_suffix;
	update_post_meta( $release_id, '_trb_pipeline_recovery_notice_at', time() );
	trb_resource_queue_recipient_email( $key, $user->user_email, $subject, $body, false, trb_resource_artist_recovery_cc_headers() );
	return true;
}

function trb_resource_notify_artist_recovery_without_release( $user_id, $event_suffix ) {
	$event_suffix = sanitize_key( $event_suffix );
	$confirmed_incident = 0 === strpos( $event_suffix, 'confirmed-incident-' ) || 0 === strpos( $event_suffix, 'manual-resend-' );
	if ( ! $confirmed_incident ) return false;
	$user = get_userdata( absint( $user_id ) );
	if ( ! $user || ! is_email( $user->user_email ) ) return false;
	$name = trb_resource_artist_legal_greeting_name( $user );
	$link = get_permalink( get_option( 'trb_portal_dashboard_created' ) );
	$subject = 'Aggiornamento sul caricamento della tua release';
	$body = '<p>Gentile ' . esc_html( $name ) . ',</p><p>ti informiamo che il Portale Artisti ha rilevato un’interruzione temporanea durante il recente caricamento della tua release.</p>';
	$body .= '<p>La problematica tecnica è stata risolta. Accedi alla tua area riservata per verificare lo stato: se la pratica è visibile, <strong>non caricare nuovamente i materiali e non creare una pratica duplicata</strong>; se invece non compare alcuna pratica, puoi ripetere ora l’invio.</p>';
	$body .= '<p>Qualora fosse necessario modificare o sostituire uno dei materiali, riceverai una comunicazione specifica contenente tutte le indicazioni necessarie.</p><p><a href="' . esc_url( $link ) . '">Apri il Portale Artisti</a></p><p>Ci scusiamo per il temporaneo inconveniente e ti ringraziamo per la collaborazione.</p>' . trb_resource_artist_email_signature();
	$key = 'artist-pipeline-recovered-user-' . absint( $user->ID ) . '-' . $event_suffix;
	trb_resource_queue_recipient_email( $key, $user->user_email, $subject, $body, false, trb_resource_artist_recovery_cc_headers() );
	return true;
}

function trb_resource_recover_release_pipeline() {
	$release_ids = get_posts( array(
		'post_type'      => 'trb_release',
		'post_status'    => array( 'publish', 'private', 'pending' ),
		'posts_per_page' => 20,
		'fields'         => 'ids',
		'orderby'        => 'modified',
		'order'          => 'ASC',
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => '_trb_release_pipeline_status', 'value' => array( 'pending_pcloud_transfer', 'pcloud_transfer_waiting' ), 'compare' => 'IN' ),
			array( 'key' => '_trb_release_pipeline_status', 'value' => array( 'archived_pending_analysis', 'technical_review', 'copyright_queued', 'analysis_in_progress', 'analysis_waiting_configuration', 'copyright_review' ), 'compare' => 'IN' ),
		),
	) );

	foreach ( $release_ids as $release_id ) {
		$status  = sanitize_key( get_post_meta( $release_id, '_trb_release_pipeline_status', true ) );
		$archive = (array) get_post_meta( $release_id, '_trb_release_pcloud_archive', true );
		$last_recovery = absint( get_post_meta( $release_id, '_trb_pipeline_last_recovery_at', true ) );
		$recovery_cooldown = 'analysis_waiting_configuration' === $status ? 2 * MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS;
		if ( $last_recovery && $last_recovery > time() - $recovery_cooldown ) continue;
		$previous_status = sanitize_key( get_post_meta( $release_id, '_trb_pipeline_last_recovery_status', true ) );
		$attempts = $previous_status === $status ? absint( get_post_meta( $release_id, '_trb_pipeline_recovery_attempts', true ) ) + 1 : 1;
		update_post_meta( $release_id, '_trb_pipeline_last_recovery_at', time() );
		update_post_meta( $release_id, '_trb_pipeline_last_recovery_status', $status );
		update_post_meta( $release_id, '_trb_pipeline_recovery_attempts', $attempts );
		$recovered = false;
		if ( in_array( $status, array( 'pending_pcloud_transfer', 'pcloud_transfer_waiting' ), true ) && empty( $archive['verified'] ) ) {
			if ( ! wp_next_scheduled( 'trb_release_pcloud_sync', array( $release_id ) ) && ! wp_next_scheduled( 'trb_release_pcloud_retry', array( $release_id ) ) ) {
				wp_schedule_single_event( time() + 5, 'trb_release_pcloud_retry', array( $release_id ) );
				$recovered = true;
			}
		} elseif ( ! empty( $archive['verified'] ) && in_array( $status, array( 'archived_pending_analysis', 'technical_review', 'copyright_queued' ), true ) ) {
			do_action( 'trb_release_audio_ready_for_analysis', $release_id, (array) ( $archive['files'] ?? array() ) );
			$recovered = true;
		} elseif ( ! empty( $archive['verified'] ) && in_array( $status, array( 'analysis_in_progress', 'analysis_waiting_configuration', 'copyright_review' ), true ) && function_exists( 'trb_resource_start_release_analysis' ) ) {
			trb_resource_start_release_analysis( $release_id );
			$recovered = 'analysis_waiting_configuration' !== sanitize_key( get_post_meta( $release_id, '_trb_release_pipeline_status', true ) );
		}
		if ( $recovered ) {
			update_post_meta( $release_id, '_trb_pipeline_last_recovered_at', time() );
		}
		$current_status = sanitize_key( get_post_meta( $release_id, '_trb_release_pipeline_status', true ) );
		if ( $attempts >= 3 && $current_status === $status && function_exists( 'trb_resource_queue_email' ) ) {
			$release = get_post( $release_id );
			$artist = $release && function_exists( 'trb_portal_artist_profile_value' ) ? trb_portal_artist_profile_value( 'artist_name', $release->post_author ) : '';
			$body = '<p>La pratica #' . absint( $release_id ) . ' (' . esc_html( $release ? $release->post_title : '' ) . ') è rimasta nello stato <strong>' . esc_html( $status ) . '</strong> dopo ' . absint( $attempts ) . ' tentativi automatici.</p><p>Artista: ' . esc_html( $artist ?: 'non indicato' ) . '.</p>';
			trb_resource_queue_email( 'pipeline-stalled-' . absint( $release_id ) . '-' . $status . '-' . wp_date( 'Ymd' ), 'Release ancora bloccata dopo il recupero automatico', $body, true );
		}
	}
}
add_action( 'trb_resource_recover_release_pipeline', 'trb_resource_recover_release_pipeline' );

/**
 * Cancel messages queued by the former broad watchdog notification rule.
 * Confirmed, explicitly keyed incident messages (such as the Ruggia backfill)
 * are preserved. Sent messages are immutable and are left in the audit trail.
 */
function trb_resource_cancel_unconfirmed_recovery_notifications() {
	if ( '20260825.1' === get_option( 'trb_resource_recovery_notification_policy_version' ) ) return;
	global $wpdb;
	$table = trb_resource_tables()['notifications'];
	$automatic_pattern = $wpdb->esc_like( 'artist-pipeline-recovered-' ) . '%';
	$manual_pattern = '%' . $wpdb->esc_like( '-manual-resend-' ) . '%';
	$confirmed_pattern = '%' . $wpdb->esc_like( '-confirmed-incident-' ) . '%';
	$wpdb->query( $wpdb->prepare(
		"UPDATE $table SET status='cancelled',last_error='cancelled_unconfirmed_recovery_notice',updated_at=%s WHERE status IN ('pending','retry') AND event_key LIKE %s AND event_key NOT LIKE %s AND event_key NOT LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		trb_resource_now(), $automatic_pattern, $manual_pattern, $confirmed_pattern
	) );
	update_option( 'trb_resource_recovery_notification_policy_version', '20260825.1', false );
}
add_action( 'init', 'trb_resource_cancel_unconfirmed_recovery_notifications', 24 );

/**
 * The first Ruggia recovery ran before artist-facing recovery notifications
 * existed. Locate the account defensively across the artist metadata and core
 * user fields, then issue a uniquely traceable one-time communication.
 */
function trb_resource_notify_ruggia_recovery_backfill() {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( 'Ruggia' ) . '%';
	$artist_user_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT u.ID
		FROM {$wpdb->users} u
		LEFT JOIN {$wpdb->usermeta} um ON um.user_id=u.ID
		WHERE u.user_login LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s OR um.meta_value LIKE %s
		ORDER BY CASE WHEN LOWER(TRIM(u.user_login))='ruggia' OR LOWER(TRIM(u.display_name))='ruggia' OR LOWER(TRIM(um.meta_value))='ruggia' THEN 0 ELSE 1 END,u.ID DESC
		LIMIT 1",
		$like, $like, $like, $like
	) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$release_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT p.ID
		FROM {$wpdb->posts} p
		WHERE p.post_type='trb_release'
		AND p.post_status NOT IN ('trash','auto-draft')
		AND (p.post_author=%d OR p.post_title LIKE %s OR p.post_content LIKE %s OR p.post_excerpt LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id=p.ID AND pm.meta_value LIKE %s))
		ORDER BY p.post_modified_gmt DESC,p.ID DESC
		LIMIT 1",
		$artist_user_id, $like, $like, $like, $like
	) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $release_id ) {
		if ( $artist_user_id ) {
			update_option( 'trb_resource_recovery_mail_discovery', array( 'status' => 'artist_found_without_release', 'updated_at' => time() ), false );
			trb_resource_notify_artist_recovery_without_release( $artist_user_id, 'manual-resend-20260824-2' );
			trb_resource_process_notifications();
			return;
		}
		update_option( 'trb_resource_recovery_mail_discovery', array( 'status' => 'ruggia_account_not_found', 'updated_at' => time() ), false );
		return;
	}

	update_option( 'trb_resource_recovery_mail_discovery', array( 'status' => 'release_found', 'updated_at' => time() ), false );
	$status = sanitize_key( get_post_meta( $release_id, '_trb_pipeline_last_recovery_status', true ) );
	if ( ! $status ) $status = sanitize_key( get_post_meta( $release_id, '_trb_release_pipeline_status', true ) );
	trb_resource_notify_artist_pipeline_recovery( $release_id, $status ?: 'pipeline', 'manual-resend-20260824-2' );
	trb_resource_process_notifications();
}
add_action( 'trb_resource_notify_ruggia_recovery_backfill', 'trb_resource_notify_ruggia_recovery_backfill' );

add_action( 'init', function() {
	if ( ! wp_next_scheduled( 'trb_resource_recover_release_pipeline' ) ) wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'trb_resource_recover_release_pipeline' );
	if ( '20260824.9' !== get_option( 'trb_resource_pipeline_recovery_version' ) ) {
		update_option( 'trb_resource_pipeline_recovery_version', '20260824.9', false );
		wp_schedule_single_event( time() + 5, 'trb_resource_recover_release_pipeline' );
		wp_schedule_single_event( time() + 10, 'trb_resource_notify_ruggia_recovery_backfill' );
	}
}, 25 );

function trb_resource_release_rights_folder( $release_id, $track_index ) {
	$archive = (array) get_post_meta( $release_id, '_trb_release_pcloud_archive', true );
	return isset( $archive['folders'][ absint( $track_index ) ] ) ? $archive['folders'][ absint( $track_index ) ] : new WP_Error( 'PCLOUD_RELEASE_FOLDER_MISSING' );
}

function trb_resource_sync_rights_document( $release_id, $document_index ) {
	$documents = get_post_meta( $release_id, '_trb_release_rights_documents', true );
	$documents = is_array( $documents ) ? array_values( array_filter( $documents, static function( $document ) { return is_array( $document ) && ! empty( $document['path'] ); } ) ) : array();
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
	if ( $valid && in_array( $extension, array( 'jpg', 'jpeg', 'png' ), true ) ) {
		$image = wp_getimagesize( $file['tmp_name'] );
		$valid = is_array( $image ) && in_array( $image['mime'] ?? '', array( 'image/jpeg', 'image/png' ), true );
	} elseif ( $valid && 'pdf' === $extension ) {
		$handle = fopen( $file['tmp_name'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$valid = $handle && '%PDF-' === fread( $handle, 5 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		if ( $handle ) fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	} elseif ( $valid && 'docx' === $extension ) {
		$valid = function_exists( 'trb_portal_validate_release_document' ) && trb_portal_validate_release_document( $file['tmp_name'], 'docx' );
	}
	if ( $valid ) { $guard = trb_resource_temp_storage_guard( (int) $file['size'] ); $valid = ! is_wp_error( $guard ); }
	$dashboard = get_permalink( get_option( 'trb_portal_dashboard_created' ) ); $anchor = '#release-files-' . $release_id;
	if ( ! $valid ) { wp_safe_redirect( add_query_arg( 'trb_release', 'rights_invalid', $dashboard ) . $anchor ); exit; }
	$uploads = wp_upload_dir(); $relative = 'trb-release-private/' . $release_id; $directory = trailingslashit( $uploads['basedir'] ) . $relative;
	if ( ! wp_mkdir_p( $directory ) ) { wp_safe_redirect( add_query_arg( 'trb_release', 'rights_error', $dashboard ) . $anchor ); exit; }
	$stored_name = wp_unique_filename( $directory, 'Diritti - ' . $name ); $target = trailingslashit( $directory ) . $stored_name;
	if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) { wp_safe_redirect( add_query_arg( 'trb_release', 'rights_error', $dashboard ) . $anchor ); exit; }
	$documents = get_post_meta( $release_id, '_trb_release_rights_documents', true );
	$documents = is_array( $documents ) ? array_values( array_filter( $documents, static function( $document ) { return is_array( $document ) && ! empty( $document['path'] ); } ) ) : array();
	$documents[] = array( 'kind' => 'rights', 'track' => $track, 'name' => $stored_name, 'original_name' => $name, 'path' => $relative . '/' . $stored_name, 'type' => sanitize_mime_type( $file['type'] ), 'size' => filesize( $target ), 'sha256' => hash_file( 'sha256', $target ), 'status' => 'pending', 'uploaded_at' => time() );
	update_post_meta( $release_id, '_trb_release_rights_documents', $documents ); $index = count( $documents ) - 1;
	$result = trb_resource_sync_rights_document( $release_id, $index );
	if ( is_wp_error( $result ) ) { wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, 'trb_resource_retry_rights_document', array( $release_id, $index ) ); update_post_meta( $release_id, '_trb_release_pipeline_status', 'pcloud_transfer_waiting' ); }
	wp_safe_redirect( add_query_arg( 'trb_release', is_wp_error( $result ) ? 'rights_waiting' : 'rights_uploaded', $dashboard ) . $anchor ); exit;
}
add_action( 'admin_post_trb_resource_upload_rights', 'trb_resource_upload_rights_document' );

function trb_resource_render_rights_box( $release_id ) {
	$status = (string) get_post_meta( $release_id, '_trb_release_pipeline_status', true );
	$documents = get_post_meta( $release_id, '_trb_release_rights_documents', true );
	$documents = is_array( $documents ) ? array_values( array_filter( $documents, static function( $document ) { return is_array( $document ) && ! empty( $document['path'] ); } ) ) : array();
	if ( ! in_array( $status, array( 'copyright_documents_needed','copyright_review','pcloud_transfer_waiting' ), true ) && ! $documents ) return;
	$tracks = (array) get_post_meta( $release_id, '_trb_release_tracks', true );
	?><div class="trb-portal__message"><strong>Documentazione sui diritti</strong><p>Allega licenze, autorizzazioni o attestazioni relative al brano. I documenti vengono archiviati nella stessa cartella pCloud del WAV e sottoposti a verifica.</p><?php if ( $documents ) : ?><ul><?php foreach ( $documents as $document ) : ?><li><?php echo esc_html( $document['original_name'] . ' · ' . ( 'synced' === $document['status'] ? 'archiviato' : 'trasferimento in attesa' ) ); ?></li><?php endforeach; ?></ul><?php endif; ?><form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="trb_resource_upload_rights"><?php wp_nonce_field( 'trb_resource_rights_' . $release_id ); ?><input type="hidden" name="release_id" value="<?php echo esc_attr( $release_id ); ?>"><label>Brano <select name="track_index"><?php foreach ( $tracks as $index => $track ) : ?><option value="<?php echo esc_attr( $index ); ?>"><?php echo esc_html( ( $index + 1 ) . '. ' . $track['title'] ); ?></option><?php endforeach; ?></select></label><input type="file" name="rights_document" accept=".pdf,.jpg,.jpeg,.png,.docx" required><button class="trb-button trb-button--compact">Allega documento</button></form></div><?php
}

function trb_resource_daily_health() {
	$anomalies = array();
	$pcloud = trb_resource_pcloud_userinfo();
	// WebDAV often omits quota properties even while file transfers work. Keep
	// that diagnostic in the monitor without turning it into an email alert.
	if ( is_wp_error( $pcloud ) ) trb_resource_event( 'quota-check-' . wp_date( 'Ymd' ), 'pcloud', 'info', 'Quota pCloud non verificabile.', array( 'code' => $pcloud->get_error_code() ) );
	else {
		$s = trb_resource_settings();
		if ( $pcloud['used_percent'] >= (float) $s['pcloud_warning_2'] ) $anomalies[] = 'pCloud utilizzato al ' . number_format_i18n( $pcloud['used_percent'], 1 ) . '%.';
	}
	$storage = trb_resource_storage_snapshot();
	$s = trb_resource_settings();
	if ( null === $storage['used_percent'] ) trb_resource_event( 'storage-check-' . wp_date( 'Ymd' ), 'storage', 'info', 'Spazio hosting non verificabile.' );
	else {
		if ( $storage['used_percent'] < (float) $s['temp_block'] ) trb_resource_resolve_event( 'capacity', 'storage' );
		if ( $storage['used_percent'] < (float) $s['temp_warning_1'] ) trb_resource_resolve_event( 'capacity-warning', 'storage' );
		if ( $storage['used_percent'] >= (float) $s['temp_warning_2'] ) $anomalies[] = 'Filesystem hosting utilizzato al ' . number_format_i18n( $storage['used_percent'], 1 ) . '%.';
	}
	global $wpdb; $tables = trb_resource_tables();
	// Manual review and active processing are expected workflow states. Notify
	// Andrea only for states that indicate an actual block or rejected content.
	$blocked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_trb_release_pipeline_status' AND meta_value IN ('technical_error','security_rejected','upload_failed','isrc_assignment_failed','analysis_waiting_configuration','ACR_BUDGET_LIMIT_REACHED','PCLOUD_QUOTA_LIMIT_REACHED')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$failed_mail = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['notifications']} WHERE status='retry' AND attempts>0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $blocked ) $anomalies[] = $blocked . ' pratiche risultano realmente bloccate.';
	if ( $failed_mail ) $anomalies[] = $failed_mail . ' notifiche email non sono state recapitate.';
	if ( function_exists( 'trb_portal_profiles' ) && function_exists( 'trb_portal_user_profile_candidates' ) ) {
		$artist_roles = array();
		$missing_roles = array();
		foreach ( trb_portal_profiles() as $key => $profile ) {
			$canonical = sanitize_key( (string) ( $profile['role'] ?? '' ) );
			if ( ! $canonical || ! get_role( $canonical ) ) $missing_roles[] = $key;
			$artist_roles = array_merge( $artist_roles, array( $canonical ), (array) ( $profile['aliases'] ?? array() ) );
		}
		$artist_roles = array_values( array_unique( array_filter( array_map( 'sanitize_key', $artist_roles ) ) ) );
		$ambiguous = 0;
		foreach ( get_users( array( 'fields' => 'ids', 'role__in' => $artist_roles, 'number' => -1 ) ) as $artist_user_id ) {
			$artist_user = get_userdata( $artist_user_id );
			$candidates = trb_portal_user_profile_candidates( $artist_user );
			$profiles_found = array_unique( array_merge( array_keys( $candidates['canonical'] ), array_keys( $candidates['legacy'] ) ) );
			if ( count( $profiles_found ) > 1 ) $ambiguous++;
		}
		if ( $missing_roles ) $anomalies[] = 'Ruoli contrattuali mancanti: ' . implode( ', ', $missing_roles ) . '.';
		if ( $ambiguous ) $anomalies[] = $ambiguous . ' account hanno più gruppi contrattuali assegnati e richiedono verifica amministrativa.';
	}
	if ( $anomalies ) trb_resource_queue_email( 'daily-digest-' . wp_date( 'Ymd' ), 'Intervento richiesto sul Portale Artisti', '<p>' . implode( '</p><p>', array_map( 'esc_html', $anomalies ) ) . '</p>', true );
	trb_resource_process_notifications();
}
add_action( 'trb_resource_daily_health', 'trb_resource_daily_health' );
add_action( 'init', function() {
	if ( ! wp_next_scheduled( 'trb_resource_daily_health' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'trb_resource_daily_health' );
} );

/** Run and mail one deployment-time audit against the real production data. */
function trb_resource_run_portal_audit() {
	global $wpdb;
	$issues = array();
	$profile_counts = array_fill_keys( array( 'dds', 'ddb12', 'ddb', 'ddb_trb', 'trb' ), 0 );
	$ambiguous = 0;
	$artist_roles = array();
	if ( function_exists( 'trb_portal_profiles' ) ) {
		foreach ( trb_portal_profiles() as $key => $profile ) {
			$canonical = sanitize_key( (string) ( $profile['role'] ?? '' ) );
			if ( ! $canonical || ! get_role( $canonical ) ) $issues[] = 'Ruolo canonico mancante: ' . $key;
			elseif ( ! get_role( $canonical )->has_cap( 'trb_portal_access' ) || empty( $profile['capability'] ) || ! get_role( $canonical )->has_cap( $profile['capability'] ) ) $issues[] = 'Permesso portale o profilo mancante sul ruolo: ' . $key;
			$artist_roles = array_merge( $artist_roles, array( $canonical ), (array) ( $profile['aliases'] ?? array() ) );
		}
		$artist_roles = array_values( array_unique( array_filter( array_map( 'sanitize_key', $artist_roles ) ) ) );
		foreach ( get_users( array( 'fields' => 'ids', 'role__in' => $artist_roles, 'number' => -1 ) ) as $artist_user_id ) {
			$artist_user = get_userdata( $artist_user_id );
			$profile = $artist_user && function_exists( 'trb_portal_user_profile' ) ? trb_portal_user_profile( $artist_user ) : false;
			if ( isset( $profile_counts[ $profile ] ) ) $profile_counts[ $profile ]++;
			$candidates = $artist_user && function_exists( 'trb_portal_user_profile_candidates' ) ? trb_portal_user_profile_candidates( $artist_user ) : array( 'canonical' => array(), 'legacy' => array() );
			$found = array_unique( array_merge( array_keys( $candidates['canonical'] ), array_keys( $candidates['legacy'] ) ) );
			if ( count( $found ) > 1 ) $ambiguous++;
		}
	}
	if ( $ambiguous ) $issues[] = $ambiguous . ' account con più gruppi contrattuali';
	$release_qa = function_exists( 'trb_portal_release_qa_health_payload' ) ? trb_portal_release_qa_health_payload() : array( 'account_found' => false, 'form_available' => false );
	if ( empty( $release_qa['account_found'] ) ) $issues[] = 'Account QA spotify4 non trovato';
	elseif ( empty( $release_qa['form_available'] ) ) $issues[] = 'Modulo nuova release non disponibile per spotify4';
	$release_matrix = function_exists( 'trb_portal_release_group_health_payload' ) ? trb_portal_release_group_health_payload() : array( 'healthy' => false, 'groups' => array() );
	foreach ( array( 'dds', 'ddb12' ) as $limited_profile ) {
		if ( 'signed_contracts_only' !== ( $release_matrix['groups'][ $limited_profile ]['counter_policy'] ?? '' ) ) $issues[] = 'Contatore release non limitato ai contratti firmati per il gruppo ' . strtoupper( $limited_profile );
	}
	if ( empty( $release_matrix['healthy'] ) ) {
		foreach ( (array) ( $release_matrix['groups'] ?? array() ) as $qa_profile => $qa_state ) {
			if ( empty( $qa_state['policy_consistent'] ) ) $issues[] = 'Gate release QA non coerente per il gruppo ' . strtoupper( str_replace( '_', '-', sanitize_key( $qa_profile ) ) );
		}
		if ( empty( $release_matrix['groups'] ) ) $issues[] = 'Matrice QA dei gruppi release non disponibile';
	}
	$cover_workflow = function_exists( 'trb_portal_cover_workflow_health_payload' ) ? trb_portal_cover_workflow_health_payload() : array( 'healthy' => false, 'profiles' => array() );
	if ( empty( $cover_workflow['healthy'] ) ) $issues[] = 'Flusso richiesta o caricamento copertina non coerente';
	if ( function_exists( 'trb_portal_service_status' ) ) {
		$entitlement_expectations = array(
			'editorial_pitching' => array( 'dds' => 'unavailable', 'ddb12' => 'included', 'ddb' => 'included', 'ddb_trb' => 'included', 'trb' => 'included' ),
			'owned_playlists'    => array( 'dds' => 'unavailable', 'ddb12' => 'included', 'ddb' => 'included', 'ddb_trb' => 'included', 'trb' => 'included' ),
			'training'           => array( 'dds' => 'unavailable', 'ddb12' => 'included', 'ddb' => 'included', 'ddb_trb' => 'included', 'trb' => 'unavailable' ),
			'certificate'        => array( 'dds' => 'unavailable', 'ddb12' => 'included', 'ddb' => 'included', 'ddb_trb' => 'included', 'trb' => 'unavailable' ),
			'press_release'      => array( 'dds' => 'store_50', 'ddb12' => 'store_50', 'ddb' => 'store_50', 'ddb_trb' => 'included', 'trb' => 'included' ),
			'radio_date'         => array( 'dds' => 'store_50', 'ddb12' => 'store_50', 'ddb' => 'store_50', 'ddb_trb' => 'included', 'trb' => 'included' ),
			'cover_artwork'      => array( 'dds' => 'store_50', 'ddb12' => 'store_50', 'ddb' => 'store_50', 'ddb_trb' => 'included', 'trb' => 'included' ),
		);
		foreach ( $entitlement_expectations as $service => $profiles ) foreach ( $profiles as $profile => $expected ) {
			if ( $expected !== trb_portal_service_status( $service, $profile ) ) $issues[] = 'Permesso servizio incoerente: ' . $service . '/' . $profile;
		}
	}

	$pipeline_rows = $wpdb->get_results( "SELECT pm.meta_value AS pipeline_status,COUNT(*) AS total FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='_trb_release_pipeline_status' AND p.post_type='trb_release' AND p.post_status IN ('publish','private','pending') GROUP BY pm.meta_value ORDER BY total DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$pipeline_counts = array();
	foreach ( (array) $pipeline_rows as $row ) {
		$pipeline_key = sanitize_key( $row['pipeline_status'] );
		$pipeline_counts[ $pipeline_key ] = ( $pipeline_counts[ $pipeline_key ] ?? 0 ) + absint( $row['total'] );
	}
	foreach ( array( 'technical_error','security_rejected','upload_failed','isrc_assignment_failed','analysis_waiting_configuration','acr_budget_limit_reached','pcloud_quota_limit_reached' ) as $blocked_status ) {
		if ( ! empty( $pipeline_counts[ $blocked_status ] ) ) $issues[] = $pipeline_counts[ $blocked_status ] . ' pratiche in ' . $blocked_status;
	}

	$required_events = array( 'trb_resource_recover_release_pipeline', 'trb_resource_daily_health', 'trb_analysis_security_retry', 'trb_release_pcloud_import_masters', 'trb_demo_recover_stalled_requests', 'trb_portal_cleanup_pending_accounts', 'trb_portal_check_identity_expirations', 'trb_release_bridge_transition_expired_ddb_trb' );
	foreach ( $required_events as $event ) if ( ! wp_next_scheduled( $event ) ) $issues[] = 'Evento automatico non pianificato: ' . $event;
	$page_status = array();
	foreach ( array( 'accedi', 'registrati', 'recupera-password', 'segnalazione' ) as $page_slug ) {
		$page = get_page_by_path( $page_slug );
		$page_status[ $page_slug ] = $page && 'publish' === $page->post_status;
		if ( ! $page_status[ $page_slug ] ) $issues[] = 'Pagina pubblica mancante o non pubblicata: ' . $page_slug;
	}
	$dashboard_id = absint( get_option( 'trb_portal_dashboard_created' ) );
	$page_status['area-artisti'] = $dashboard_id && 'publish' === get_post_status( $dashboard_id );
	if ( ! $page_status['area-artisti'] ) $issues[] = 'Dashboard artisti mancante o non pubblicata';

	$demo_counts = array_fill_keys( array( 'queued', 'retry', 'ready', 'sent', 'manual_review', 'email_failed' ), 0 );
	$demo_problems = array_fill_keys( array( 'stalled', 'missing_files', 'missing_remote', 'sheet_unsynced' ), 0 );
	$demo_request_ids = get_posts( array(
		'post_type'      => 'trb_request',
		'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_trb_demo_payload', 'compare' => 'EXISTS' ) ),
	) );
	foreach ( $demo_request_ids as $demo_request_id ) {
		$demo_payload = get_post_meta( $demo_request_id, '_trb_demo_payload', true );
		if ( ! is_array( $demo_payload ) ) continue;
		$demo_status = sanitize_key( (string) ( $demo_payload['status'] ?? '' ) );
		if ( isset( $demo_counts[ $demo_status ] ) ) $demo_counts[ $demo_status ]++;
		$submitted_at = ! empty( $demo_payload['submitted_at'] ) ? strtotime( $demo_payload['submitted_at'] ) : 0;
		if ( in_array( $demo_status, array( 'queued', 'retry' ), true ) && $submitted_at && $submitted_at < time() - 2 * HOUR_IN_SECONDS ) $demo_problems['stalled']++;
		if ( in_array( $demo_status, array( 'queued', 'retry' ), true ) && function_exists( 'trb_demo_local_path' ) ) {
			$has_expected_file = false;
			foreach ( array( 'text_file', 'audio_file' ) as $file_key ) {
				if ( empty( $demo_payload[ $file_key ] ) ) continue;
				$has_expected_file = true;
				if ( ! trb_demo_local_path( $demo_payload[ $file_key ] ) ) $demo_problems['missing_files']++;
			}
			if ( ! $has_expected_file ) $demo_problems['missing_files']++;
		}
		$demo_remote = get_post_meta( $demo_request_id, '_trb_demo_remote', true );
		if ( in_array( $demo_status, array( 'ready', 'sent' ), true ) && empty( $demo_remote['folder'] ) ) $demo_problems['missing_remote']++;
		if ( in_array( $demo_status, array( 'ready', 'sent' ), true ) && ! get_post_meta( $demo_request_id, '_trb_demo_sheet_synced', true ) ) $demo_problems['sheet_unsynced']++;
	}
	$demo_settings = function_exists( 'trb_demo_settings' ) ? trb_demo_settings() : array();
	foreach ( array( 'webdav_endpoint', 'pcloud_user', 'pcloud_pass', 'openai_key', 'sheet_webhook_url', 'sheet_webhook_secret' ) as $demo_setting ) {
		if ( empty( $demo_settings[ $demo_setting ] ) ) $issues[] = 'Configurazione demo mancante: ' . $demo_setting;
	}
	foreach ( array( 'trb_portal_process_demo' => 'trb_demo_process_request', 'trb_portal_send_demo_review' => 'trb_demo_send_review', 'trb_portal_sync_demo_sheet' => 'trb_demo_sync_sheet_retry', 'trb_demo_recover_stalled_requests' => 'trb_demo_recover_stalled_requests' ) as $hook => $callback ) {
		if ( ! has_action( $hook, $callback ) ) $issues[] = 'Handler demo non registrato: ' . $hook;
	}
	foreach ( $demo_problems as $problem => $count ) if ( $count ) $issues[] = $count . ' valutazioni demo con anomalia ' . $problem;
	if ( $demo_counts['manual_review'] ) $issues[] = $demo_counts['manual_review'] . ' valutazioni demo in verifica manuale';
	if ( $demo_counts['email_failed'] ) $issues[] = $demo_counts['email_failed'] . ' valutazioni demo con consegna email non riuscita';
	$settings = trb_resource_settings();
	if ( empty( $settings['acr_enabled'] ) || empty( $settings['acr_paid_confirmed'] ) || empty( $settings['acr_token'] ) || empty( $settings['acr_container_id'] ) ) $issues[] = 'Configurazione ACRCloud incompleta o disattivata';
	if ( function_exists( 'trb_analysis_verify_acr_container' ) ) {
		$container = trb_analysis_verify_acr_container();
		if ( is_wp_error( $container ) ) $issues[] = 'Container ACRCloud non verificato: ' . $container->get_error_code();
	}
	$failed_mail = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . trb_resource_tables()['notifications'] . " WHERE status='retry' AND attempts>0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( $failed_mail ) $issues[] = $failed_mail . ' notifiche email in retry';
	$open_resource_events = $wpdb->get_results( "SELECT resource,severity,message,SUM(occurrences) AS occurrences FROM " . trb_resource_tables()['events'] . " WHERE status='open' AND severity IN ('warning','critical') GROUP BY resource,severity,message ORDER BY severity,resource,message", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	foreach ( (array) $open_resource_events as $open_event ) {
		$issues[] = absint( $open_event['occurrences'] ) . ' occorrenze aperte ' . sanitize_key( $open_event['resource'] ) . '/' . sanitize_key( $open_event['severity'] ) . ': ' . sanitize_text_field( $open_event['message'] );
	}

	$snapshot = array( 'checked_at' => time(), 'profiles' => $profile_counts, 'pipelines' => $pipeline_counts, 'demos' => $demo_counts, 'demo_problems' => $demo_problems, 'release_qa' => $release_qa, 'release_matrix' => $release_matrix, 'cover_workflow' => $cover_workflow, 'pages' => $page_status, 'resource_events' => $open_resource_events, 'issues' => $issues );
	update_option( 'trb_resource_last_portal_audit', $snapshot, false );
	$profile_rows = array();
	foreach ( $profile_counts as $profile => $count ) $profile_rows[] = strtoupper( str_replace( '_', '-', $profile ) ) . ': ' . absint( $count );
	$pipeline_summary = array();
	foreach ( $pipeline_counts as $status => $count ) $pipeline_summary[] = esc_html( $status ) . ': ' . absint( $count );
	$demo_summary = array();
	foreach ( $demo_counts as $status => $count ) $demo_summary[] = esc_html( $status ) . ': ' . absint( $count );
	$body = '<p><strong>Gruppi:</strong> ' . esc_html( implode( ' · ', $profile_rows ) ) . '</p><p><strong>Pipeline release:</strong> ' . ( $pipeline_summary ? implode( ' · ', $pipeline_summary ) : 'nessuna pratica attiva' ) . '</p><p><strong>Valutazioni demo:</strong> ' . esc_html( implode( ' · ', $demo_summary ) ) . '</p>';
	$body .= $issues ? '<p><strong>Interventi richiesti:</strong></p><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $issues ) ) . '</li></ul>' : '<p><strong>Esito:</strong> nessuna anomalia rilevata in pagine, gruppi, permessi, valutazioni demo, release, eventi automatici, coda email e configurazione copyright.</p>';
	trb_resource_queue_email( 'portal-audit-20260825.1', 'Audit completo Portale Artisti completato', $body, (bool) $issues );
	trb_resource_process_notifications();
}
add_action( 'trb_resource_run_portal_audit', 'trb_resource_run_portal_audit' );
add_action( 'init', function() {
	if ( '20260825.1' === get_option( 'trb_resource_portal_audit_version' ) ) return;
	update_option( 'trb_resource_portal_audit_version', '20260825.1', false );
	if ( ! wp_next_scheduled( 'trb_resource_run_portal_audit' ) ) wp_schedule_single_event( time() + 30, 'trb_resource_run_portal_audit' );
}, 30 );

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
			if ( 'retry_pcloud' === $action && function_exists( 'trb_release_pcloud_schedule_sync' ) ) trb_release_pcloud_schedule_sync( $release_id );
			if ( 'retry_acr' === $action ) {
				update_post_meta( $release_id, '_trb_release_pipeline_status', 'analysis_in_progress' );
				trb_resource_start_release_analysis( $release_id );
			}
			if ( 'qa_reassign' === $action && false !== stripos( get_the_title( $release_id ), 'NON PUBBLICARE' ) ) {
				$qa_email = isset( $_POST['qa_artist_email'] ) ? sanitize_email( wp_unslash( $_POST['qa_artist_email'] ) ) : '';
				$qa_user  = $qa_email ? get_user_by( 'email', $qa_email ) : false;
				$qa_profile = $qa_user && function_exists( 'trb_portal_user_profile' ) ? trb_portal_user_profile( $qa_user ) : false;
				if ( $qa_user && $qa_profile ) {
					wp_update_post( array( 'ID' => $release_id, 'post_author' => $qa_user->ID ) );
					if ( function_exists( 'trb_release_pcloud_schedule_sync' ) ) trb_release_pcloud_schedule_sync( $release_id );
				}
			}
			if ( 'request_documents' === $action ) update_post_meta( $release_id, '_trb_release_pipeline_status', 'copyright_documents_needed' );
			if ( 'manual_review' === $action ) update_post_meta( $release_id, '_trb_release_pipeline_status', 'manual_review' );
			if ( 'upload_cover' === $action && function_exists( 'trb_portal_store_final_release_cover' ) ) {
				$cover = isset( $_FILES['trb_release_cover'] ) && is_array( $_FILES['trb_release_cover'] ) ? $_FILES['trb_release_cover'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$result = trb_portal_store_final_release_cover( $release_id, $cover, ! empty( $_POST['trb_release_cover_300dpi'] ) );
				if ( is_wp_error( $result ) ) $action = 'upload_cover_failed_' . sanitize_key( $result->get_error_code() );
			}
			if ( 'approve' === $action ) {
				$cover_missing = 'request' === get_post_meta( $release_id, '_trb_release_cover_mode', true ) && function_exists( 'trb_portal_release_has_final_cover' ) && ! trb_portal_release_has_final_cover( $release_id );
				if ( $cover_missing ) {
					update_post_meta( $release_id, '_trb_release_pipeline_status', 'cover_creation_pending' );
					$action = 'approve_blocked_cover';
				} else {
					update_post_meta( $release_id, '_trb_release_pipeline_status', 'approved' );
					do_action( 'trb_release_analysis_approved', $release_id );
				}
			}
			$history = (array) get_post_meta( $release_id, '_trb_release_decision_history', true );
			$history[] = array( 'action' => $action, 'user_id' => get_current_user_id(), 'at' => time() );
			update_post_meta( $release_id, '_trb_release_decision_history', array_slice( $history, -100 ) );
			if ( function_exists( 'trb_analysis_generate_report' ) ) trb_analysis_generate_report( $release_id );
			if ( 'approve_blocked_cover' === $action ) echo '<div class="notice notice-error"><p>Pratica non approvata: manca la copertina definitiva.</p></div>';
			elseif ( 0 === strpos( $action, 'upload_cover_failed_' ) ) echo '<div class="notice notice-error"><p>Copertina non acquisita: controlla formato, dimensioni e conferma 300 DPI.</p></div>';
			else echo '<div class="notice notice-success"><p>Stato della pratica aggiornato.</p></div>';
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
	<tr><th>pCloud</th><td><?php echo esc_html( isset( $pcloud_snapshot['data']['used_percent'] ) ? number_format_i18n( $pcloud_snapshot['data']['used_percent'], 1 ) . '% utilizzato (' . ( $pcloud_snapshot['data']['source'] ?? 'origine non indicata' ) . ')' : 'Da verificare' ); ?></td></tr>
	<tr><th>Spazio hosting (filesystem condiviso)</th><td><?php echo esc_html( null !== $storage['used_percent'] ? number_format_i18n( $storage['used_percent'], 1 ) . '% utilizzato' : 'Non verificabile' ); ?></td></tr>
	</tbody></table><form method="post" style="margin:12px 0 24px"><?php wp_nonce_field( 'trb_resource_reconcile' ); ?><label><strong>Spesa effettiva ACRCloud del mese (USD)</strong> <input type="number" min="0" step="0.000001" name="acr_actual_cost" value="<?php echo esc_attr( isset( $stats['cost_actual'] ) ? $stats['cost_actual'] : 0 ); ?>"></label> <button class="button" name="trb_resource_reconcile" value="1">Registra riconciliazione</button></form>
	<h2>Anomalie aperte</h2><?php if ( ! $events ) : ?><p>Nessuna anomalia registrata.</p><?php else : ?><table class="widefat striped"><thead><tr><th>Ultimo evento</th><th>Risorsa</th><th>Gravità</th><th>Dettaglio</th><th>Occorrenze</th></tr></thead><tbody><?php foreach ( $events as $event ) : ?><tr><td><?php echo esc_html( $event->last_seen ); ?></td><td><?php echo esc_html( $event->resource ); ?></td><td><?php echo esc_html( $event->severity ); ?></td><td><?php echo esc_html( $event->message ); ?></td><td><?php echo esc_html( $event->occurrences ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
	<h2>Coda pratiche</h2><?php if ( ! $queue ) : ?><p>Nessuna pratica in attesa.</p><?php else : ?><table class="widefat striped"><thead><tr><th>Pratica</th><th>Artista</th><th>Stato</th><th>Decisione</th></tr></thead><tbody><?php foreach ( $queue as $release ) : $state = get_post_meta( $release->ID, '_trb_release_pipeline_status', true ); $archive = (array) get_post_meta( $release->ID, '_trb_release_pcloud_archive', true ); $artist = get_userdata( $release->post_author ); $cover_requested = 'request' === get_post_meta( $release->ID, '_trb_release_cover_mode', true ); $cover_missing = $cover_requested && function_exists( 'trb_portal_release_has_final_cover' ) && ! trb_portal_release_has_final_cover( $release->ID ); ?><tr id="trb-release-<?php echo esc_attr( $release->ID ); ?>"><td>#<?php echo esc_html( $release->ID . ' · ' . $release->post_title ); ?></td><td><?php echo esc_html( $artist ? $artist->display_name : '' ); ?></td><td><?php echo esc_html( $state . ( $cover_missing ? ' · copertina definitiva mancante' : '' ) . ( ! empty( $archive['code'] ) ? ' · ' . $archive['code'] : '' ) . ( ! empty( $archive['detail'] ) ? ' · ' . $archive['detail'] : '' ) ); ?></td><td><form method="post" enctype="multipart/form-data"><?php wp_nonce_field( 'trb_resource_release_action' ); ?><input type="hidden" name="release_id" value="<?php echo esc_attr( $release->ID ); ?>"><button class="button" name="trb_resource_release_action" value="retry_pcloud">Riprova pCloud</button> <button class="button" name="trb_resource_release_action" value="retry_acr">Rielabora risposta ACR</button> <button class="button" name="trb_resource_release_action" value="request_documents">Richiedi documenti</button> <button class="button" name="trb_resource_release_action" value="manual_review">Verifica manuale</button> <button class="button" name="trb_resource_release_action" value="override_budget">Autorizza analisi</button> <button class="button button-primary" name="trb_resource_release_action" value="approve">Approva</button><?php if ( $cover_missing ) : ?><br><label><strong>Copertina definitiva:</strong> <input type="file" name="trb_release_cover" accept="image/jpeg,image/png,.jpg,.jpeg,.png"></label> <label><input type="checkbox" name="trb_release_cover_300dpi" value="1"> Confermo 300 DPI</label> <button class="button" name="trb_resource_release_action" value="upload_cover">Collega copertina</button><?php endif; ?><?php if ( false !== stripos( $release->post_title, 'NON PUBBLICARE' ) ) : ?><br><input type="email" name="qa_artist_email" placeholder="E-mail account collaudo" style="margin-top:6px"> <button class="button" name="trb_resource_release_action" value="qa_reassign">Riassegna test e ritenta</button><?php endif; ?></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
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
