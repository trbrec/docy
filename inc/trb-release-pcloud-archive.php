<?php
/** Route release WAV files to their canonical pCloud folders. */

if ( ! defined( 'ABSPATH' ) ) exit;

function trb_release_pcloud_local_file( $file ) {
	if ( empty( $file['path'] ) ) return '';
	$uploads = wp_upload_dir();
	$base = realpath( trailingslashit( $uploads['basedir'] ) . 'trb-release-private' );
	$path = realpath( trailingslashit( $uploads['basedir'] ) . ltrim( $file['path'], '/' ) );
	return $base && $path && 0 === strpos( $path, $base . DIRECTORY_SEPARATOR ) && is_file( $path ) ? $path : '';
}

function trb_release_pcloud_master_folder( $release_id, $profile, $artist_name, $release_title ) {
	$root = 'trb' === $profile ? '/Discografia - TRB rec' : '/Discografia - DDB';
	return $root . '/' . trb_artist_promo_folder_segment( $artist_name ) . '/' . trb_artist_archive_safe_segment( $release_title, 'Release ' . absint( $release_id ) );
}

function trb_release_pcloud_mastering_folder( $artist_name, $release_title, $release_id ) {
	$folder = trb_artist_archive_safe_segment( $artist_name ) . ' - ' . trb_artist_archive_safe_segment( $release_title, 'Release ' . absint( $release_id ) );
	return '/Upload files - TRB rec/Audio/Pre-master stem files/' . $folder;
}

/** Stream large audio files so the PHP memory limit is not tied to WAV size. */
function trb_release_pcloud_put_file( $remote, $local ) {
	$settings = trb_demo_settings();
	if ( empty( $settings['webdav_endpoint'] ) || empty( $settings['pcloud_user'] ) || empty( $settings['pcloud_pass'] ) ) return new WP_Error( 'missing_webdav_settings' );
	if ( function_exists( 'curl_init' ) ) {
		$handle = fopen( $local, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) return new WP_Error( 'release_audio_missing' );
		$curl = curl_init( trb_demo_remote_url( $settings['webdav_endpoint'], $remote ) );
		curl_setopt_array( $curl, array(
			CURLOPT_UPLOAD        => true,
			CURLOPT_INFILE        => $handle,
			CURLOPT_INFILESIZE     => filesize( $local ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERPWD        => $settings['pcloud_user'] . ':' . $settings['pcloud_pass'],
			CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
			CURLOPT_HTTPHEADER     => array( 'Content-Type: audio/wav' ),
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_TIMEOUT        => 0,
		) );
		curl_exec( $curl );
		$code = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
		$error = curl_error( $curl );
		curl_close( $curl );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return in_array( $code, array( 200, 201, 204 ), true ) ? true : new WP_Error( 'pcloud_audio_upload_failed', $error ? $error : 'WebDAV PUT ' . $code );
	}
	if ( filesize( $local ) > 128 * MB_IN_BYTES ) return new WP_Error( 'streaming_upload_unavailable' );
	return trb_artist_archive_put( $remote, file_get_contents( $local ), 'audio/wav' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}

function trb_release_pcloud_sync( $release_id ) {
	$release = get_post( $release_id );
	if ( ! $release || 'trb_release' !== $release->post_type ) return new WP_Error( 'release_not_found' );
	$user = get_userdata( $release->post_author );
	if ( ! $user ) return new WP_Error( 'artist_not_found' );
	$profile = trb_portal_user_profile( $user );
	if ( ! in_array( $profile, array( 'dds', 'ddb12', 'ddb', 'ddb_trb', 'trb' ), true ) ) return new WP_Error( 'artist_profile_missing' );
	$artist_name = trb_portal_artist_profile_value( 'artist_name', $user->ID );
	if ( '' === trim( $artist_name ) ) return new WP_Error( 'artist_name_missing' );
	$tracks = (array) get_post_meta( $release_id, '_trb_release_tracks', true );
	$files = (array) get_post_meta( $release_id, '_trb_release_files', true );
	$master_folder = trb_release_pcloud_master_folder( $release_id, $profile, $artist_name, $release->post_title );
	$mastering_folder = trb_release_pcloud_mastering_folder( $artist_name, $release->post_title, $release_id );
	$uploaded = array();
	foreach ( $files as $file ) {
		if ( empty( $file['kind'] ) || 'audio' !== $file['kind'] ) continue;
		$status = 'dds' === $profile ? 'mastered' : ( isset( $file['audio_status'] ) ? sanitize_key( $file['audio_status'] ) : '' );
		if ( ! in_array( $status, array( 'mastered', 'mastering' ), true ) ) return new WP_Error( 'audio_status_missing' );
		$folder = 'mastering' === $status ? $mastering_folder : $master_folder;
		$ready = trb_demo_ensure_remote_folder( $folder );
		if ( is_wp_error( $ready ) ) return $ready;
		$track_index = isset( $file['track'] ) ? absint( $file['track'] ) : count( $uploaded );
		$track_title = isset( $tracks[ $track_index ]['title'] ) ? $tracks[ $track_index ]['title'] : 'Brano ' . ( $track_index + 1 );
		$remote_name = ! empty( $file['name'] ) ? basename( $file['name'] ) : trb_portal_release_audio_filename( $release_id, $track_index, $track_title, $status );
		$local = trb_release_pcloud_local_file( $file );
		if ( ! $local ) return new WP_Error( 'release_audio_missing' );
		$result = trb_release_pcloud_put_file( $folder . '/' . $remote_name, $local );
		if ( is_wp_error( $result ) ) return $result;
		$uploaded[] = $folder . '/' . $remote_name;
	}
	update_post_meta( $release_id, '_trb_release_pcloud_archive', array( 'status' => 'synced', 'time' => time(), 'files' => $uploaded ) );
	return array( 'files' => $uploaded );
}

function trb_release_pcloud_run_sync( $release_id ) {
	$result = trb_release_pcloud_sync( absint( $release_id ) );
	if ( is_wp_error( $result ) ) {
		update_post_meta( $release_id, '_trb_release_pcloud_archive', array( 'status' => 'error', 'time' => time(), 'code' => $result->get_error_code() ) );
		if ( ! wp_next_scheduled( 'trb_release_pcloud_retry', array( absint( $release_id ) ) ) ) wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, 'trb_release_pcloud_retry', array( absint( $release_id ) ) );
	}
}
add_action( 'trb_release_pcloud_sync', 'trb_release_pcloud_run_sync', 10, 1 );
add_action( 'trb_release_pcloud_retry', 'trb_release_pcloud_run_sync', 10, 1 );

function trb_release_pcloud_schedule_sync( $release_id, $replace = false ) {
	$release_id = absint( $release_id );
	update_post_meta( $release_id, '_trb_release_pcloud_archive', array( 'status' => 'pending', 'time' => time(), 'replacement' => (bool) $replace ) );
	if ( ! wp_next_scheduled( 'trb_release_pcloud_sync', array( $release_id ) ) ) wp_schedule_single_event( time() + 5, 'trb_release_pcloud_sync', array( $release_id ) );
}
