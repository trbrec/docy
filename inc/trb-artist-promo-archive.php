<?php
/** Synchronize the artistic profile to the canonical pCloud discography. */

if ( ! defined( 'ABSPATH' ) ) exit;

function trb_artist_promo_root( $profile ) {
	return 'trb' === $profile ? '/Discografia - TRB rec' : '/Discografia - DDB';
}

function trb_artist_promo_folder_segment( $artist_name ) {
	$artist_name = trim( wp_strip_all_tags( (string) $artist_name ) );
	return str_replace( array( '/', '\\' ), array( '／', '＼' ), $artist_name );
}

function trb_artist_promo_profile_text( $user_id ) {
	$profile = trb_portal_user_profile( get_userdata( $user_id ) );
	$lines = array(
		'PROFILO ARTISTA',
		'Ultimo aggiornamento: ' . wp_date( 'd/m/Y H:i:s' ),
		'',
		'Nome d’arte: ' . trb_portal_artist_profile_value( 'artist_name', $user_id ),
		'Appartenenza: ' . trb_portal_profile_affiliation( $profile ),
		'',
		'BIOGRAFIA',
		trim( (string) get_user_meta( $user_id, '_trb_artist_bio', true ) ),
		'',
		'PROFILI MUSICALI UFFICIALI',
	);
	foreach ( array( 'spotify_url' => 'Spotify', 'apple_music_url' => 'Apple Music', 'youtube_url' => 'YouTube', 'soundcloud_url' => 'SoundCloud' ) as $key => $label ) {
		$value = trb_portal_artist_profile_value( $key, $user_id );
		if ( '' !== $value ) $lines[] = $label . ': ' . $value;
	}
	$lines[] = '';
	$lines[] = 'SOCIAL E CONTATTI PUBBLICI';
	foreach ( array( 'facebook_url' => 'Facebook', 'instagram_url' => 'Instagram', 'linkedin_url' => 'LinkedIn', 'tiktok_url' => 'TikTok', 'discord_url' => 'Discord', 'twitch_url' => 'Twitch', 'x_url' => 'X', 'snapchat_url' => 'Snapchat', 'threads_url' => 'Threads' ) as $key => $label ) {
		$value = trb_portal_artist_profile_value( $key, $user_id );
		if ( '' !== $value ) $lines[] = $label . ': ' . $value;
	}
	$live_fee = trb_portal_artist_profile_value( 'live_fee', $user_id );
	if ( '' !== $live_fee ) {
		$lines[] = '';
		$lines[] = 'LIVE / DJ SET';
		$lines[] = 'Cachet e condizioni indicative: ' . $live_fee;
	}
	return implode( "\r\n", $lines ) . "\r\n";
}

function trb_artist_promo_local_photo( $file ) {
	if ( empty( $file['path'] ) ) return '';
	$uploads = wp_upload_dir();
	$base = realpath( trailingslashit( $uploads['basedir'] ) . 'trb-artist-private' );
	$path = realpath( trailingslashit( $uploads['basedir'] ) . ltrim( $file['path'], '/' ) );
	return $base && $path && 0 === strpos( $path, $base . DIRECTORY_SEPARATOR ) && is_file( $path ) ? $path : '';
}

function trb_artist_promo_sync( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) return new WP_Error( 'artist_not_found' );
	$artist_name = trb_portal_artist_profile_value( 'artist_name', $user_id );
	if ( '' === trim( $artist_name ) ) return new WP_Error( 'artist_name_missing' );
	$profile = trb_portal_user_profile( $user );
	if ( ! in_array( $profile, array( 'dds', 'ddb', 'ddb12', 'ddb_trb', 'trb' ), true ) ) return new WP_Error( 'artist_profile_missing' );

	$artist_folder = trb_artist_promo_root( $profile ) . '/' . trb_artist_promo_folder_segment( $artist_name );
	$promo_folder = $artist_folder . '/PROMO';
	$photos_folder = $promo_folder . '/FOTO UFFICIALI';
	$ready = trb_demo_ensure_remote_folder( $photos_folder );
	if ( is_wp_error( $ready ) ) return $ready;
	$bio = trb_artist_archive_put( $promo_folder . '/Profilo artista.txt', trb_artist_promo_profile_text( $user_id ), 'text/plain; charset=utf-8' );
	if ( is_wp_error( $bio ) ) return $bio;
	$biography = trb_portal_private_profile_file_by_group( 'biography', $user_id );
	if ( ! empty( $biography ) ) {
		foreach ( array( 'txt', 'docx', 'odt', 'rtf' ) as $extension ) {
			$deleted = trb_artist_archive_delete( $promo_folder . '/Biografia artista.' . $extension );
			if ( is_wp_error( $deleted ) ) return $deleted;
		}
		$local_biography = trb_artist_promo_local_photo( $biography );
		if ( ! $local_biography ) return new WP_Error( 'artist_biography_missing' );
		$bio_extension = strtolower( pathinfo( $local_biography, PATHINFO_EXTENSION ) );
		$uploaded_biography = trb_artist_archive_put( $promo_folder . '/Biografia artista.' . $bio_extension, file_get_contents( $local_biography ), ! empty( $biography['type'] ) ? $biography['type'] : 'application/octet-stream' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( is_wp_error( $uploaded_biography ) ) return $uploaded_biography;
	}

	foreach ( range( 1, 6 ) as $index ) {
		foreach ( array( 'jpg', 'jpeg', 'png', 'webp' ) as $extension ) {
			$deleted = trb_artist_archive_delete( $photos_folder . '/Foto artista ' . sprintf( '%02d', $index ) . '.' . $extension );
			if ( is_wp_error( $deleted ) ) return $deleted;
		}
	}
	$photos = array_values( array_filter( trb_portal_private_profile_files( $user_id ), function( $file ) { return isset( $file['group'] ) && 'photo' === $file['group']; } ) );
	foreach ( array_slice( $photos, 0, 6 ) as $index => $file ) {
		$local = trb_artist_promo_local_photo( $file );
		if ( ! $local ) return new WP_Error( 'artist_photo_missing' );
		$extension = strtolower( pathinfo( $local, PATHINFO_EXTENSION ) );
		$type = ! empty( $file['type'] ) ? $file['type'] : 'application/octet-stream';
		$remote = $photos_folder . '/Foto artista ' . sprintf( '%02d', $index + 1 ) . '.' . $extension;
		$uploaded = trb_artist_archive_put( $remote, file_get_contents( $local ), $type ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( is_wp_error( $uploaded ) ) return $uploaded;
	}
	update_user_meta( $user_id, '_trb_artist_promo_archive', array( 'status' => 'synced', 'time' => time(), 'folder' => $promo_folder, 'photos' => count( $photos ) ) );
	return array( 'folder' => $promo_folder, 'photos' => count( $photos ) );
}

function trb_artist_promo_after_profile_save( $user_id ) {
	$result = trb_artist_promo_sync( $user_id );
	if ( is_wp_error( $result ) ) {
		update_user_meta( $user_id, '_trb_artist_promo_archive', array( 'status' => 'error', 'time' => time(), 'code' => $result->get_error_code() ) );
		if ( ! wp_next_scheduled( 'trb_artist_promo_retry', array( $user_id ) ) ) wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, 'trb_artist_promo_retry', array( $user_id ) );
	}
}
add_action( 'trb_portal_artist_profile_saved', 'trb_artist_promo_after_profile_save', 20, 1 );
add_action( 'trb_artist_promo_retry', 'trb_artist_promo_after_profile_save', 10, 1 );

/** Release ISRC fields belong to the portal; contracts remain in Apps Script. */
require_once get_template_directory() . '/inc/trb-release-isrc-ui.php';
