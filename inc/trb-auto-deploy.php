<?php
/**
 * Automatic deployment bridge for the public trbrec/docy repository.
 *
 * It keeps the Deployer for Git secret inside WordPress and asks the installed
 * plugin to update the active theme whenever GitHub main changes.
 *
 * @package docy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TRB_DOCY_REPOSITORY_API = 'https://api.github.com/repos/trbrec/docy/commits/main';
const TRB_DOCY_DEPLOYED_SHA_OPTION = 'trb_docy_auto_deployed_sha';
const TRB_DOCY_DEPLOY_STATUS_OPTION = 'trb_docy_auto_deploy_status';

/** Five-minute internal safety net when SiteGround blocks the GitHub request. */
function trb_docy_add_deploy_interval( $schedules ) {
	$schedules['trb_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Ogni cinque minuti' );
	return $schedules;
}
add_filter( 'cron_schedules', 'trb_docy_add_deploy_interval' );

function trb_docy_schedule_deploy_safety_net() {
	if ( ! wp_next_scheduled( 'trb_docy_auto_deploy' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'trb_five_minutes', 'trb_docy_auto_deploy' );
	}
}
add_action( 'init', 'trb_docy_schedule_deploy_safety_net', 30 );

function trb_docy_run_deploy_safety_net() {
	$sha = trb_docy_get_github_main_sha();
	if ( is_wp_error( $sha ) || hash_equals( (string) get_option( TRB_DOCY_DEPLOYED_SHA_OPTION, '' ), (string) $sha ) ) {
		return;
	}
	trb_docy_deploy_verified_sha( $sha );
}
add_action( 'trb_docy_auto_deploy', 'trb_docy_run_deploy_safety_net' );

/** Remove the one-time verification artifact left by overwrite-style deploys. */
function trb_docy_remove_push_verification_artifact() {
	$file = trailingslashit( get_template_directory() ) . 'trb-push-deploy-check.txt';
	if ( is_file( $file ) ) {
		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}
}
add_action( 'init', 'trb_docy_remove_push_verification_artifact', 1 );

/** Store a compact, non-sensitive deployment result for diagnostics. */
function trb_docy_store_deploy_status( $state, $message, $sha = '' ) {
	update_option(
		TRB_DOCY_DEPLOY_STATUS_OPTION,
		array(
			'state'   => sanitize_key( $state ),
			'message' => sanitize_text_field( $message ),
			'sha'     => sanitize_text_field( $sha ),
			'time'    => time(),
		),
		false
	);
}

/** Deploy one already verified commit through Deployer for Git. */
function trb_docy_deploy_verified_sha( $sha ) {
	if ( get_transient( 'trb_docy_auto_deploy_lock' ) ) {
		return new WP_Error( 'trb_deploy_locked', 'Un altro deploy è già in corso.', array( 'status' => 409 ) );
	}
	set_transient( 'trb_docy_auto_deploy_lock', 1, 4 * MINUTE_IN_SECONDS );

	if ( hash_equals( (string) get_option( TRB_DOCY_DEPLOYED_SHA_OPTION, '' ), $sha ) ) {
		trb_docy_store_deploy_status( 'current', 'Il tema è già aggiornato.', $sha );
		delete_transient( 'trb_docy_auto_deploy_lock' );
		return array( 'success' => true, 'state' => 'current', 'sha' => $sha );
	}

	if ( ! class_exists( '\\DeployerForGit\\ApiRequests\\PackageUpdate' ) ) {
		trb_docy_store_deploy_status( 'error', 'Deployer for Git non è attivo.', $sha );
		delete_transient( 'trb_docy_auto_deploy_lock' );
		return new WP_Error( 'trb_deployer_missing', 'Deployer for Git non è attivo.', array( 'status' => 503 ) );
	}

	$request = new WP_REST_Request( 'POST', '/dfg/v1/package_update/' );
	$request->set_param( 'secret', \DeployerForGit\Helper::get_api_secret() );
	$request->set_param( 'type', 'theme' );
	$request->set_param( 'package', 'docy' );
	$updater = new \DeployerForGit\ApiRequests\PackageUpdate();
	$payload = $updater->update_package_callback( $request );

	if ( empty( $payload['success'] ) ) {
		$message = isset( $payload['message'] ) ? $payload['message'] : 'Deploy non riuscito.';
		trb_docy_store_deploy_status( 'error', $message, $sha );
		delete_transient( 'trb_docy_auto_deploy_lock' );
		return new WP_Error( 'trb_deploy_failed', $message, array( 'status' => 500 ) );
	}

	// Deployer for Git can report success before its GitHub archive cache has
	// caught up with main. Verify a commit-specific source file before marking
	// the workflow green, so GitHub Actions retries instead of accepting a
	// stale theme as deployed.
	$verified = true;
	foreach ( array( 'functions.php', 'inc/trb-auto-deploy.php', 'inc/trb-artist-portal.php', 'inc/trb-demo-automation.php' ) as $marker ) {
		$remote_marker = wp_remote_get(
			'https://raw.githubusercontent.com/trbrec/docy/' . rawurlencode( $sha ) . '/' . $marker,
			array( 'timeout' => 30, 'headers' => array( 'User-Agent' => 'TRB-rec-WordPress-Auto-Deploy' ) )
		);
		$local_marker = trailingslashit( get_template_directory() ) . $marker;
		$verified = $verified
			&& ! is_wp_error( $remote_marker )
			&& 200 === wp_remote_retrieve_response_code( $remote_marker )
			&& is_file( $local_marker )
			&& hash_equals( hash( 'sha256', wp_remote_retrieve_body( $remote_marker ) ), hash_file( 'sha256', $local_marker ) );
		if ( ! $verified ) break;
	}
	if ( ! $verified ) {
		$message = 'Deployer ha restituito successo, ma i file locali non corrispondono ancora al commit richiesto.';
		trb_docy_store_deploy_status( 'error', $message, $sha );
		delete_transient( 'trb_docy_auto_deploy_lock' );
		return new WP_Error( 'trb_deploy_stale', $message, array( 'status' => 503 ) );
	}

	update_option( TRB_DOCY_DEPLOYED_SHA_OPTION, $sha, false );
	wp_cache_flush();
	// Some SiteGround PHP workers keep executing the previous opcode after an
	// overwrite-style theme deploy. Reset it only after source verification, so
	// the next request cannot serve stale PHP while GitHub already reports green.
	if ( function_exists( 'opcache_reset' ) ) {
		@opcache_reset(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache();
	}
	trb_docy_store_deploy_status( 'success', 'Tema aggiornato automaticamente.', $sha );
	delete_transient( 'trb_docy_auto_deploy_lock' );
	return array( 'success' => true, 'state' => 'deployed', 'sha' => $sha );
}

/** Read and validate the current GitHub main SHA. */
function trb_docy_get_github_main_sha() {

	$github = wp_remote_get(
		TRB_DOCY_REPOSITORY_API,
		array(
			'timeout' => 20,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'TRB-rec-WordPress-Auto-Deploy',
			),
		)
	);

	if ( is_wp_error( $github ) || 200 !== wp_remote_retrieve_response_code( $github ) ) {
		return new WP_Error( 'trb_github_unavailable', 'Impossibile controllare il repository GitHub.', array( 'status' => 503 ) );
	}

	$data = json_decode( wp_remote_retrieve_body( $github ), true );
	$sha  = isset( $data['sha'] ) ? sanitize_text_field( $data['sha'] ) : '';
	if ( ! preg_match( '/^[a-f0-9]{40}$/', $sha ) ) {
		return new WP_Error( 'trb_github_invalid_sha', 'GitHub non ha restituito un commit valido.', array( 'status' => 503 ) );
	}
	return $sha;
}

/** Accept push notifications only for the exact current commit on official main. */
function trb_docy_receive_push_deploy( WP_REST_Request $request ) {
	$requested_sha = sanitize_text_field( (string) $request->get_param( 'sha' ) );
	if ( ! preg_match( '/^[a-f0-9]{40}$/', $requested_sha ) ) {
		return new WP_Error( 'trb_invalid_sha', 'SHA non valido.', array( 'status' => 400 ) );
	}

	$current_sha = trb_docy_get_github_main_sha();
	if ( is_wp_error( $current_sha ) ) {
		return $current_sha;
	}
	if ( ! hash_equals( $current_sha, $requested_sha ) ) {
		return new WP_Error( 'trb_sha_mismatch', 'Il commit richiesto non coincide con GitHub main.', array( 'status' => 409 ) );
	}

	$result = trb_docy_deploy_verified_sha( $requested_sha );
	return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
}

function trb_docy_register_push_deploy_route() {
	register_rest_route(
		'trb/v1',
		'/deploy',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'trb_docy_receive_push_deploy',
			'permission_callback' => 'trb_docy_verify_github_oidc_request',
			'args'                => array( 'sha' => array( 'required' => true, 'type' => 'string' ) ),
		)
	);
	register_rest_route(
		'trb/v1',
		'/deploy-status',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function() {
				$status = get_option( TRB_DOCY_DEPLOY_STATUS_OPTION, array() );
				$status = is_array( $status ) ? $status : array();
				$discovery = get_option( 'trb_resource_recovery_mail_discovery', array() );
				$discovery = is_array( $discovery ) ? $discovery : array();
				$receipts = get_option( 'trb_resource_recovery_mail_receipts', array() );
				$receipt_status = array();
				foreach ( is_array( $receipts ) ? $receipts : array() as $receipt ) {
					$receipt_status[] = array(
						'role'       => sanitize_key( (string) ( $receipt['role'] ?? '' ) ),
						'status'     => sanitize_key( (string) ( $receipt['status'] ?? '' ) ),
						'attempts'   => absint( $receipt['attempts'] ?? 0 ),
						'updated_at' => absint( $receipt['updated_at'] ?? 0 ),
					);
				}
				$audit = get_option( 'trb_resource_last_portal_audit', array() );
				$audit = is_array( $audit ) ? $audit : array();
				$audit_maps = array();
				foreach ( array( 'profiles', 'pipelines', 'demos', 'demo_problems' ) as $audit_key ) {
					$audit_maps[ $audit_key ] = array();
					foreach ( is_array( $audit[ $audit_key ] ?? null ) ? $audit[ $audit_key ] : array() as $key => $value ) $audit_maps[ $audit_key ][ sanitize_key( (string) $key ) ] = absint( $value );
				}
				$audit_pages = array();
				foreach ( is_array( $audit['pages'] ?? null ) ? $audit['pages'] : array() as $key => $value ) $audit_pages[ sanitize_key( (string) $key ) ] = (bool) $value;
				$release_qa = function_exists( 'trb_portal_release_qa_health_payload' ) ? trb_portal_release_qa_health_payload() : array( 'account_found' => false, 'form_available' => false );
				return rest_ensure_response(
					array(
						'sha'        => sanitize_text_field( (string) get_option( TRB_DOCY_DEPLOYED_SHA_OPTION, '' ) ),
						'state'      => sanitize_key( (string) ( $status['state'] ?? '' ) ),
						'updated_at' => isset( $status['time'] ) ? absint( $status['time'] ) : 0,
						'release_qa' => $release_qa,
						'recovery_mail' => array(
							'discovery' => sanitize_key( (string) ( $discovery['status'] ?? '' ) ),
							'checked_at' => absint( $discovery['updated_at'] ?? 0 ),
							'receipts' => array_slice( $receipt_status, 0, 6 ),
						),
						'portal_audit' => array(
							'checked_at'    => absint( $audit['checked_at'] ?? 0 ),
							'issue_count'   => is_array( $audit['issues'] ?? null ) ? count( $audit['issues'] ) : 0,
							'profiles'      => $audit_maps['profiles'],
							'pipelines'     => $audit_maps['pipelines'],
							'demos'         => $audit_maps['demos'],
							'demo_problems' => $audit_maps['demo_problems'],
							'pages'         => $audit_pages,
						),
					)
				);
			},
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'trb_docy_register_push_deploy_route' );

function trb_docy_base64url_decode( $value ) {
	$padding = strlen( $value ) % 4;
	if ( $padding ) {
		$value .= str_repeat( '=', 4 - $padding );
	}
	return base64_decode( strtr( $value, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
}

function trb_docy_asn1_length( $length ) {
	if ( $length < 128 ) {
		return chr( $length );
	}
	$encoded = '';
	while ( $length > 0 ) {
		$encoded = chr( $length & 0xff ) . $encoded;
		$length >>= 8;
	}
	return chr( 0x80 | strlen( $encoded ) ) . $encoded;
}

function trb_docy_asn1_integer( $value ) {
	$value = ltrim( $value, "\x00" );
	if ( '' === $value || ord( $value[0] ) > 0x7f ) {
		$value = "\x00" . $value;
	}
	return "\x02" . trb_docy_asn1_length( strlen( $value ) ) . $value;
}

function trb_docy_jwk_to_pem( $jwk ) {
	if ( empty( $jwk['n'] ) || empty( $jwk['e'] ) ) {
		return false;
	}
	$modulus = trb_docy_base64url_decode( $jwk['n'] );
	$exponent = trb_docy_base64url_decode( $jwk['e'] );
	if ( false === $modulus || false === $exponent ) {
		return false;
	}
	$rsa = trb_docy_asn1_integer( $modulus ) . trb_docy_asn1_integer( $exponent );
	$rsa = "\x30" . trb_docy_asn1_length( strlen( $rsa ) ) . $rsa;
	$algorithm = hex2bin( '300d06092a864886f70d0101010500' );
	$bit_string = "\x03" . trb_docy_asn1_length( strlen( $rsa ) + 1 ) . "\x00" . $rsa;
	$der = "\x30" . trb_docy_asn1_length( strlen( $algorithm . $bit_string ) ) . $algorithm . $bit_string;
	return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
}

/** Authenticate the short-lived GitHub Actions OIDC token. */
function trb_docy_verify_github_oidc_request( WP_REST_Request $request ) {
	$authorization = (string) $request->get_header( 'authorization' );
	if ( 0 !== stripos( $authorization, 'Bearer ' ) || ! function_exists( 'openssl_verify' ) ) {
		return new WP_Error( 'trb_oidc_missing', 'Autenticazione GitHub mancante.', array( 'status' => 401 ) );
	}
	$jwt = trim( substr( $authorization, 7 ) );
	$parts = explode( '.', $jwt );
	if ( 3 !== count( $parts ) ) {
		return new WP_Error( 'trb_oidc_invalid', 'Token GitHub non valido.', array( 'status' => 401 ) );
	}
	$header = json_decode( trb_docy_base64url_decode( $parts[0] ), true );
	$claims = json_decode( trb_docy_base64url_decode( $parts[1] ), true );
	$signature = trb_docy_base64url_decode( $parts[2] );
	if ( ! is_array( $header ) || ! is_array( $claims ) || false === $signature || 'RS256' !== ( $header['alg'] ?? '' ) || empty( $header['kid'] ) ) {
		return new WP_Error( 'trb_oidc_invalid', 'Token GitHub non valido.', array( 'status' => 401 ) );
	}

	$keys = get_transient( 'trb_github_oidc_jwks' );
	if ( ! is_array( $keys ) ) {
		$response = wp_remote_get( 'https://token.actions.githubusercontent.com/.well-known/jwks', array( 'timeout' => 15 ) );
		$keys = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ? json_decode( wp_remote_retrieve_body( $response ), true ) : array();
		if ( is_array( $keys ) ) {
			set_transient( 'trb_github_oidc_jwks', $keys, 12 * HOUR_IN_SECONDS );
		}
	}
	$jwk = null;
	foreach ( (array) ( $keys['keys'] ?? array() ) as $candidate ) {
		if ( isset( $candidate['kid'] ) && hash_equals( (string) $candidate['kid'], (string) $header['kid'] ) ) {
			$jwk = $candidate;
			break;
		}
	}
	$pem = $jwk ? trb_docy_jwk_to_pem( $jwk ) : false;
	if ( ! $pem || 1 !== openssl_verify( $parts[0] . '.' . $parts[1], $signature, $pem, OPENSSL_ALGO_SHA256 ) ) {
		return new WP_Error( 'trb_oidc_signature', 'Firma GitHub non valida.', array( 'status' => 401 ) );
	}

	$now = time();
	$allowed_audiences = array(
		'https://artisti.trbrec.com/wp-json/trb/v1/deploy',
		'https://artist.trbrec.com/wp-json/trb/v1/deploy',
	);
	$audiences = (array) ( $claims['aud'] ?? array() );
	$valid = 'https://token.actions.githubusercontent.com' === ( $claims['iss'] ?? '' )
		&& array_intersect( $allowed_audiences, $audiences )
		&& 'trbrec/docy' === ( $claims['repository'] ?? '' )
		&& 'refs/heads/main' === ( $claims['ref'] ?? '' )
		&& 'push' === ( $claims['event_name'] ?? '' )
		&& $now < (int) ( $claims['exp'] ?? 0 )
		&& $now >= (int) ( $claims['nbf'] ?? 0 ) - 30
		&& hash_equals( (string) ( $claims['sha'] ?? '' ), (string) $request->get_param( 'sha' ) );
	return $valid ? true : new WP_Error( 'trb_oidc_claims', 'Autorizzazione GitHub non valida.', array( 'status' => 403 ) );
}

/** Exempt verified machine callbacks and non-sensitive health probes. */
function trb_docy_allow_push_deploy_authentication( $result ) {
	$route  = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? untrailingslashit( (string) $GLOBALS['wp']->query_vars['rest_route'] ) : '';
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	if ( ( '/trb/v1/deploy' === $route && 'POST' === $method ) || ( in_array( $route, array( '/trb/v1/deploy-status', '/trb/v1/demo-health' ), true ) && 'GET' === $method ) ) {
		return null;
	}
	return $result;
}
add_filter( 'rest_authentication_errors', 'trb_docy_allow_push_deploy_authentication', PHP_INT_MAX );

/** Schedule a one-time cleanup of every standard plugin that is not active. */
function trb_docy_schedule_inactive_plugin_cleanup() {
	if ( get_option( 'trb_docy_inactive_plugins_cleaned_v1' ) || wp_next_scheduled( 'trb_docy_cleanup_inactive_plugins' ) ) {
		return;
	}
	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'trb_docy_cleanup_inactive_plugins' );
}
add_action( 'init', 'trb_docy_schedule_inactive_plugin_cleanup', 31 );

/** Delete inactive plugins and expose a short-lived audit report for verification. */
function trb_docy_cleanup_inactive_plugins() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active = array_unique( array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) ) );
	}

	$installed = get_plugins();
	$inactive  = array_values( array_diff( array_keys( $installed ), $active ) );
	$report    = array(
		'time'    => wp_date( 'c' ),
		'removed' => array(),
		'errors'  => array(),
	);

	foreach ( $inactive as $plugin_file ) {
		$name   = isset( $installed[ $plugin_file ]['Name'] ) ? $installed[ $plugin_file ]['Name'] : $plugin_file;
		$result = delete_plugins( array( $plugin_file ) );
		if ( is_wp_error( $result ) ) {
			$report['errors'][] = array( 'file' => $plugin_file, 'name' => $name, 'message' => $result->get_error_message() );
		} else {
			$report['removed'][] = array( 'file' => $plugin_file, 'name' => $name );
		}
	}

	update_option( 'trb_docy_inactive_plugins_cleaned_v1', $report, false );
	$uploads = wp_upload_dir();
	if ( empty( $uploads['error'] ) ) {
		$directory = trailingslashit( $uploads['basedir'] ) . 'trb-audit';
		if ( wp_mkdir_p( $directory ) ) {
			$file = trailingslashit( $directory ) . 'plugin-cleanup-20260802.json';
			file_put_contents( $file, wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			wp_schedule_single_event( time() + DAY_IN_SECONDS, 'trb_docy_remove_plugin_cleanup_report', array( $file ) );
		}
	}
}
add_action( 'trb_docy_cleanup_inactive_plugins', 'trb_docy_cleanup_inactive_plugins' );

/** Remove only the generated cleanup report from the uploads directory. */
function trb_docy_remove_plugin_cleanup_report( $file ) {
	$uploads = wp_upload_dir();
	$base    = realpath( $uploads['basedir'] );
	$target  = realpath( $file );
	if ( $base && $target && 0 === strpos( $target, $base . DIRECTORY_SEPARATOR ) && is_file( $target ) ) {
		unlink( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}
}
add_action( 'trb_docy_remove_plugin_cleanup_report', 'trb_docy_remove_plugin_cleanup_report' );
