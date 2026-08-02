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

/** Add a five-minute WordPress cron interval. */
function trb_docy_auto_deploy_interval( $schedules ) {
	$schedules['trb_five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => 'Every five minutes (TRB deploy)',
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'trb_docy_auto_deploy_interval' );

/** Schedule the checker once and keep one recurring event. */
function trb_docy_schedule_auto_deploy() {
	if ( ! wp_next_scheduled( 'trb_docy_auto_deploy' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'trb_five_minutes', 'trb_docy_auto_deploy' );
	}
}
add_action( 'init', 'trb_docy_schedule_auto_deploy', 30 );

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

/** Check GitHub main and delegate the actual installation to Deployer for Git. */
function trb_docy_run_auto_deploy() {
	if ( get_transient( 'trb_docy_auto_deploy_lock' ) ) {
		return;
	}
	set_transient( 'trb_docy_auto_deploy_lock', 1, 4 * MINUTE_IN_SECONDS );

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
		trb_docy_store_deploy_status( 'error', 'Impossibile controllare il repository GitHub.' );
		delete_transient( 'trb_docy_auto_deploy_lock' );
		return;
	}

	$data = json_decode( wp_remote_retrieve_body( $github ), true );
	$sha  = isset( $data['sha'] ) ? sanitize_text_field( $data['sha'] ) : '';
	if ( ! preg_match( '/^[a-f0-9]{40}$/', $sha ) ) {
		trb_docy_store_deploy_status( 'error', 'GitHub non ha restituito un commit valido.' );
		delete_transient( 'trb_docy_auto_deploy_lock' );
		return;
	}

	if ( hash_equals( (string) get_option( TRB_DOCY_DEPLOYED_SHA_OPTION, '' ), $sha ) ) {
		trb_docy_store_deploy_status( 'current', 'Il tema è già aggiornato.', $sha );
		delete_transient( 'trb_docy_auto_deploy_lock' );
		return;
	}

	if ( ! class_exists( '\\DeployerForGit\\ApiRequests\\PackageUpdate' ) ) {
		trb_docy_store_deploy_status( 'error', 'Deployer for Git non è attivo.', $sha );
		delete_transient( 'trb_docy_auto_deploy_lock' );
		return;
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
		return;
	}

	update_option( TRB_DOCY_DEPLOYED_SHA_OPTION, $sha, false );
	trb_docy_store_deploy_status( 'success', 'Tema aggiornato automaticamente.', $sha );
	delete_transient( 'trb_docy_auto_deploy_lock' );
}
add_action( 'trb_docy_auto_deploy', 'trb_docy_run_auto_deploy' );

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
