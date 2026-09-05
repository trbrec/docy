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
function trb_release_pcloud_put_file( $remote, $local, $content_type = 'application/octet-stream' ) {
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
			CURLOPT_HTTPHEADER     => array( 'Content-Type: ' . sanitize_mime_type( $content_type ) ),
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
	return trb_artist_archive_put( $remote, file_get_contents( $local ), $content_type ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}

/** Execute the small WebDAV requests used to verify and atomically publish a WAV. */
function trb_release_pcloud_dav_request( $method, $remote, $headers = array() ) {
	$settings = trb_demo_settings();
	if ( empty( $settings['webdav_endpoint'] ) || empty( $settings['pcloud_user'] ) || empty( $settings['pcloud_pass'] ) || ! function_exists( 'curl_init' ) ) {
		return new WP_Error( 'missing_webdav_settings' );
	}
	$curl = curl_init( trb_demo_remote_url( $settings['webdav_endpoint'], $remote ) );
	curl_setopt_array( $curl, array(
		CURLOPT_CUSTOMREQUEST  => $method,
		CURLOPT_NOBODY         => 'HEAD' === $method,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => true,
		CURLOPT_USERPWD        => $settings['pcloud_user'] . ':' . $settings['pcloud_pass'],
		CURLOPT_HTTPAUTH        => CURLAUTH_BASIC,
		CURLOPT_HTTPHEADER      => $headers,
		CURLOPT_CONNECTTIMEOUT => 30,
		CURLOPT_TIMEOUT        => 120,
	) );
	$response = curl_exec( $curl );
	$code = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
	$error = curl_error( $curl );
	curl_close( $curl );
	if ( false === $response || $code < 200 || $code >= 300 ) {
		return new WP_Error( 'pcloud_webdav_request_failed', $error ? $error : $method . ' ' . $code );
	}
	return array( 'code' => $code, 'headers' => (string) $response );
}

function trb_release_pcloud_remote_size( $remote ) {
	$result = trb_release_pcloud_dav_request( 'HEAD', $remote );
	if ( is_wp_error( $result ) ) return $result;
	return preg_match( '/^Content-Length:\s*(\d+)/mi', $result['headers'], $matches ) ? (int) $matches[1] : new WP_Error( 'pcloud_size_unavailable' );
}

/** Download a remote WAV without loading it into PHP memory. */
function trb_release_pcloud_download_file( $remote, $local ) {
	$settings = trb_demo_settings();
	if ( empty( $settings['webdav_endpoint'] ) || empty( $settings['pcloud_user'] ) || empty( $settings['pcloud_pass'] ) || ! function_exists( 'curl_init' ) ) return new WP_Error( 'missing_webdav_settings' );
	$handle = fopen( $local, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $handle ) return new WP_Error( 'master_local_file_failed' );
	$curl = curl_init( trb_demo_remote_url( $settings['webdav_endpoint'], $remote ) );
	curl_setopt_array( $curl, array(
		CURLOPT_FILE           => $handle,
		CURLOPT_USERPWD        => $settings['pcloud_user'] . ':' . $settings['pcloud_pass'],
		CURLOPT_HTTPAUTH        => CURLAUTH_BASIC,
		CURLOPT_CONNECTTIMEOUT => 30,
		CURLOPT_TIMEOUT        => 0,
		CURLOPT_FAILONERROR    => false,
	) );
	curl_exec( $curl );
	$code = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
	$error = curl_error( $curl );
	curl_close( $curl );
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	if ( 200 !== $code ) {
		wp_delete_file( $local );
		return new WP_Error( 'master_download_failed', $error ? $error : 'WebDAV GET ' . $code );
	}
	return true;
}

function trb_release_pcloud_master_import_notice( $release_id, $code ) {
	$key = '_trb_master_import_notice_' . sanitize_key( $code );
	if ( get_transient( $key . '_' . absint( $release_id ) ) ) return;
	set_transient( $key . '_' . absint( $release_id ), 1, 12 * HOUR_IN_SECONDS );
	$release = get_post( $release_id );
	$subject = 'Master non importato automaticamente: pratica #' . absint( $release_id );
	$body = sprintf( '<p><strong>Release:</strong> #%d · %s</p><p><strong>Errore:</strong> %s</p><p>Il file resta su pCloud e verrà controllato nuovamente.</p>', absint( $release_id ), esc_html( $release ? $release->post_title : '' ), esc_html( $code ) );
	if ( function_exists( 'trb_resource_queue_email' ) ) trb_resource_queue_email( 'master-import-' . absint( $release_id ) . '-' . sanitize_key( $code ) . '-' . wp_date( 'YmdH' ), $subject, $body, true );
	else wp_mail( 'info@trbrec.com', '[PRIORITÀ] ' . $subject, wp_strip_all_tags( $body ) );
}

/** Import the final (MST) counterpart of every pending PRE-MST from pCloud. */
function trb_release_pcloud_import_masters() {
	if ( get_transient( 'trb_master_import_lock' ) ) return;
	set_transient( 'trb_master_import_lock', 1, 4 * MINUTE_IN_SECONDS );
	$releases = get_posts( array( 'post_type' => 'trb_release', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
	foreach ( $releases as $release ) {
		$files = (array) get_post_meta( $release->ID, '_trb_release_files', true );
		$tracks = (array) get_post_meta( $release->ID, '_trb_release_tracks', true );
		$user = get_userdata( $release->post_author );
		if ( ! $user ) continue;
		$artist_name = trb_portal_artist_profile_value( 'artist_name', $user->ID );
		$folder = trb_release_pcloud_mastering_folder( $artist_name, $release->post_title, $release->ID );
		foreach ( $files as $index => $file ) {
			if ( 'audio' !== ( $file['kind'] ?? '' ) || 'mastering' !== ( $file['audio_status'] ?? '' ) ) continue;
			$track_index = absint( $file['track'] ?? 0 );
			$track_title = $tracks[ $track_index ]['title'] ?? ( 'Brano ' . ( $track_index + 1 ) );
			$remote_name = trb_portal_release_audio_filename( $release->ID, $track_index, $track_title, 'mastered' );
			$remote = $folder . '/' . $remote_name;
			$size = trb_release_pcloud_remote_size( $remote );
			if ( is_wp_error( $size ) ) continue; // A missing MST is the normal pending state.

			/* Require the same byte count on two scans so a synchronizing file is never imported halfway. */
			$candidate_key = 'trb_mst_' . md5( $remote );
			$candidate = get_transient( $candidate_key );
			if ( ! is_array( $candidate ) || (int) ( $candidate['size'] ?? -1 ) !== (int) $size ) {
				set_transient( $candidate_key, array( 'size' => (int) $size ), HOUR_IN_SECONDS );
				continue;
			}

			$temporary = wp_tempnam( $remote_name );
			if ( ! $temporary ) { trb_release_pcloud_master_import_notice( $release->ID, 'master_local_file_failed' ); continue; }
			$result = trb_release_pcloud_download_file( $remote, $temporary );
			if ( is_wp_error( $result ) || ! is_file( $temporary ) || (int) filesize( $temporary ) !== (int) $size ) {
				wp_delete_file( $temporary );
				trb_release_pcloud_master_import_notice( $release->ID, is_wp_error( $result ) ? $result->get_error_code() : 'master_size_mismatch' );
				continue;
			}
			$spec = trb_portal_wav_spec( $temporary );
			$declared = isset( $tracks[ $track_index ] ) ? trb_portal_release_track_duration_seconds( $tracks[ $track_index ] ) : 0;
			if ( is_wp_error( $spec ) || $declared <= 0 || abs( $spec['duration_seconds'] - $declared ) > 1.0 ) {
				wp_delete_file( $temporary );
				trb_release_pcloud_master_import_notice( $release->ID, 'master_audio_invalid' );
				continue;
			}
			$upload = array( 'name' => $remote_name, 'type' => 'audio/wav', 'tmp_name' => $temporary, 'error' => UPLOAD_ERR_OK, 'size' => (int) $size, '_trb_staged' => true );
			$stored = trb_portal_store_release_upload( $release->ID, $upload, 'audio', $track_index, array( 'track_title' => $track_title, 'audio_status' => 'mastered', 'replacement' => true ) );
			if ( is_wp_error( $stored ) ) {
				wp_delete_file( $temporary );
				trb_release_pcloud_master_import_notice( $release->ID, $stored->get_error_code() );
				continue;
			}
			$stored['audio_status'] = 'mastered';
			$previous = (array) get_post_meta( $release->ID, '_trb_release_previous_files', true );
			$previous[] = $file;
			update_post_meta( $release->ID, '_trb_release_previous_files', $previous );
			$files[ $index ] = $stored;
			if ( isset( $tracks[ $track_index ] ) ) $tracks[ $track_index ]['audio_status'] = 'mastered';
			update_post_meta( $release->ID, '_trb_release_files', array_values( $files ) );
			update_post_meta( $release->ID, '_trb_release_tracks', array_values( $tracks ) );
			update_post_meta( $release->ID, '_trb_release_pipeline_status', 'master_imported_pending_archive' );
			delete_transient( $candidate_key );
			trb_release_pcloud_schedule_sync( $release->ID, true );
		}
	}
	delete_transient( 'trb_master_import_lock' );
}
add_action( 'trb_release_pcloud_import_masters', 'trb_release_pcloud_import_masters' );
add_filter( 'cron_schedules', static function( $schedules ) {
	$schedules['trb_master_scan'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Ogni 5 minuti (master pCloud)' );
	return $schedules;
} );
add_action( 'init', static function() {
	if ( ! wp_next_scheduled( 'trb_release_pcloud_import_masters' ) ) wp_schedule_event( time() + MINUTE_IN_SECONDS, 'trb_master_scan', 'trb_release_pcloud_import_masters' );
} );

/**
 * Upload to a temporary remote name, verify the complete byte count and only
 * then replace the canonical WAV. A failed transfer therefore leaves the
 * previous pCloud copy untouched.
 */
function trb_release_pcloud_publish_file( $remote, $local, $content_type = 'application/octet-stream' ) {
	$temporary = $remote . '.uploading-' . wp_generate_uuid4();
	$uploaded = trb_release_pcloud_put_file( $temporary, $local, $content_type );
	if ( is_wp_error( $uploaded ) ) return $uploaded;
	$remote_size = trb_release_pcloud_remote_size( $temporary );
	if ( is_wp_error( $remote_size ) || (int) filesize( $local ) !== $remote_size ) {
		trb_release_pcloud_dav_request( 'DELETE', $temporary );
		return new WP_Error( 'pcloud_audio_verification_failed' );
	}
	$settings = trb_demo_settings();
	$destination = trb_demo_remote_url( $settings['webdav_endpoint'], $remote );
	$moved = trb_release_pcloud_dav_request( 'MOVE', $temporary, array( 'Destination: ' . $destination, 'Overwrite: T' ) );
	if ( is_wp_error( $moved ) ) {
		trb_release_pcloud_dav_request( 'DELETE', $temporary );
		return $moved;
	}
	$published_size = trb_release_pcloud_remote_size( $remote );
	return ! is_wp_error( $published_size ) && (int) filesize( $local ) === $published_size ? true : new WP_Error( 'pcloud_audio_verification_failed' );
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
	$intake_phase = (string) get_post_meta( $release_id, '_trb_release_intake_phase', true );
	if ( '' !== $intake_phase && 'complete' !== $intake_phase ) return new WP_Error( 'release_intake_incomplete', 'La ricevuta è incompleta: acquisizione e validazione dei file non concluse.' );
	$audio_indexes = array();
	foreach ( $files as $file ) if ( is_array( $file ) && 'audio' === ( $file['kind'] ?? '' ) && isset( $file['track'] ) ) $audio_indexes[ absint( $file['track'] ) ] = true;
	if ( ! $tracks || count( $audio_indexes ) !== count( $tracks ) ) return new WP_Error( 'release_audio_incomplete', 'Il numero dei WAV acquisiti non corrisponde ai brani della release.' );
	$master_folder = trb_release_pcloud_master_folder( $release_id, $profile, $artist_name, $release->post_title );
	$mastering_folder = trb_release_pcloud_mastering_folder( $artist_name, $release->post_title, $release_id );
	$total_bytes = 0;
	foreach ( $files as $file ) if ( isset( $file['size'] ) ) $total_bytes += (int) $file['size'];
	if ( function_exists( 'trb_resource_pcloud_guard' ) ) {
		$quota_guard = trb_resource_pcloud_guard( $total_bytes );
		if ( is_wp_error( $quota_guard ) ) return $quota_guard;
	}
	$uploaded = array();
	$folders = array();
	$materials = array();
	$master_ready = trb_demo_ensure_remote_folder( $master_folder );
	if ( is_wp_error( $master_ready ) ) return $master_ready;
	foreach ( $files as $file ) {
		if ( empty( $file['kind'] ) ) continue;
		if ( 'rights_document' === $file['kind'] ) continue;
		if ( 'audio' !== $file['kind'] ) {
			if ( 'clean' !== ( $file['security_status'] ?? '' ) ) return new WP_Error( 'SECURITY_SCAN_PENDING' );
			$local = trb_release_pcloud_local_file( $file ); if ( ! $local ) return new WP_Error( 'release_material_missing' );
			$extension = strtolower( pathinfo( $file['name'] ?? $file['original_name'] ?? '', PATHINFO_EXTENSION ) );
			$track_index = absint( $file['track'] ?? 0 ); $track_title = $tracks[ $track_index ]['title'] ?? ( 'Brano ' . ( $track_index + 1 ) );
			$base = 'cover' === $file['kind'] ? '00)_Copertina' : ( 'cover_reference' === $file['kind'] ? '00)_Reference_copertina' : ( 'presentation' === $file['kind'] ? '00)_Presentazione_release' : sprintf( '%02d)_Testo_-_%s', $track_index + 1, trb_portal_release_audio_name_segment( $track_title, 'Brano' ) ) ) );
			$remote = $master_folder . '/' . sanitize_file_name( $base . ( $extension ? '.' . $extension : '' ) );
			$result = trb_release_pcloud_publish_file( $remote, $local, $file['type'] ?? 'application/octet-stream' ); if ( is_wp_error( $result ) ) return $result;
			$uploaded[] = $remote; $materials[] = $remote;
			update_post_meta( $release_id, '_trb_release_pcloud_archive', array( 'status' => 'uploading', 'time' => time(), 'files' => $uploaded, 'materials' => $materials, 'folders' => $folders, 'verified' => false ) );
			continue;
		}
		$status = 'dds' === $profile ? 'mastered' : ( isset( $file['audio_status'] ) ? sanitize_key( $file['audio_status'] ) : '' );
		if ( ! in_array( $status, array( 'mastered', 'mastering' ), true ) ) return new WP_Error( 'audio_status_missing' );
		$folder = 'mastering' === $status ? $mastering_folder : $master_folder;
		$ready = trb_demo_ensure_remote_folder( $folder );
		if ( is_wp_error( $ready ) ) return $ready;
		$track_index = isset( $file['track'] ) ? absint( $file['track'] ) : count( $uploaded );
		$track_title = isset( $tracks[ $track_index ]['title'] ) ? $tracks[ $track_index ]['title'] : 'Brano ' . ( $track_index + 1 );
		$remote_name = trb_portal_release_audio_filename( $release_id, $track_index, $track_title, $status );
		$local = trb_release_pcloud_local_file( $file );
		if ( ! $local ) return new WP_Error( 'release_audio_missing' );
		$result = trb_release_pcloud_publish_file( $folder . '/' . $remote_name, $local, 'audio/wav' );
		if ( is_wp_error( $result ) ) return $result;
		$uploaded[] = $folder . '/' . $remote_name;
		$folders[ $track_index ] = $folder;
		update_post_meta( $release_id, '_trb_release_pcloud_archive', array( 'status' => 'uploading', 'time' => time(), 'files' => $uploaded, 'materials' => $materials, 'folders' => $folders, 'verified' => false ) );
	}
	$archive = array( 'status' => 'synced', 'time' => time(), 'files' => $uploaded, 'materials' => $materials, 'folders' => $folders, 'verified' => true );
	update_post_meta( $release_id, '_trb_release_pcloud_archive', $archive );
	$rights_documents = get_post_meta( $release_id, '_trb_release_rights_documents', true );
	$rights_documents = is_array( $rights_documents ) ? array_values( array_filter( $rights_documents, static function( $document ) { return is_array( $document ) && ! empty( $document['path'] ); } ) ) : array();
	foreach ( $rights_documents as $document_index => $document ) {
		if ( 'synced' === ( $document['status'] ?? '' ) || ! function_exists( 'trb_resource_sync_rights_document' ) ) continue;
		$rights_result = trb_resource_sync_rights_document( $release_id, $document_index );
		if ( is_wp_error( $rights_result ) ) return $rights_result;
	}
	update_post_meta( $release_id, '_trb_release_pipeline_status', 'archived_pending_analysis' );
	$previous_files = (array) get_post_meta( $release_id, '_trb_release_previous_files', true );
	if ( $previous_files ) {
		trb_portal_delete_release_files( $previous_files );
		delete_post_meta( $release_id, '_trb_release_previous_files' );
	}
	/** Start copyright/technical analysis only after pCloud verification. */
	do_action( 'trb_release_audio_ready_for_analysis', $release_id, $uploaded );
	return array( 'files' => $uploaded );
}

function trb_release_pcloud_run_sync( $release_id ) {
	$result = trb_release_pcloud_sync( absint( $release_id ) );
	if ( is_wp_error( $result ) ) {
		$archive = (array) get_post_meta( $release_id, '_trb_release_pcloud_archive', true );
		$archive['status'] = 'error'; $archive['time'] = time(); $archive['code'] = $result->get_error_code(); $archive['detail'] = sanitize_text_field( $result->get_error_message() );
		update_post_meta( $release_id, '_trb_release_pcloud_archive', $archive );
		$pipeline_status = in_array( $result->get_error_code(), array( 'PCLOUD_QUOTA_LIMIT_REACHED', 'PCLOUD_QUOTA_UNVERIFIED' ), true ) ? $result->get_error_code() : 'pcloud_transfer_waiting';
		update_post_meta( $release_id, '_trb_release_pipeline_status', $pipeline_status );
		if ( ! wp_next_scheduled( 'trb_release_pcloud_retry', array( absint( $release_id ) ) ) ) wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, 'trb_release_pcloud_retry', array( absint( $release_id ) ) );
	}
}
add_action( 'trb_release_pcloud_sync', 'trb_release_pcloud_run_sync', 10, 1 );
add_action( 'trb_release_pcloud_retry', 'trb_release_pcloud_run_sync', 10, 1 );

function trb_release_pcloud_schedule_sync( $release_id, $replace = false ) {
	$release_id = absint( $release_id );
	update_post_meta( $release_id, '_trb_release_pcloud_archive', array( 'status' => 'pending', 'time' => time(), 'replacement' => (bool) $replace ) );
	update_post_meta( $release_id, '_trb_release_pipeline_status', 'pending_pcloud_transfer' );
	if ( ! wp_next_scheduled( 'trb_release_pcloud_sync', array( $release_id ) ) ) wp_schedule_single_event( time() + 5, 'trb_release_pcloud_sync', array( $release_id ) );
}
