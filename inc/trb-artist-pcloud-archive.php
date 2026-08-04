<?php
/**
 * Private pCloud archive for artist identity data and documents.
 *
 * Uses the WebDAV credentials already stored by the demo automation module.
 * No secret or personal data is committed to the theme repository.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function trb_artist_archive_base_folder() {
	return "/Private/Documenti d'identità ID/Artisti e Clienti";
}

function trb_artist_archive_safe_segment( $value, $fallback = 'Artista' ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );
	$value = preg_replace( '/[\\\\\/:*?"<>|]+/u', '-', $value );
	$value = preg_replace( '/\s+/u', ' ', $value );
	$value = trim( $value, " .-\t\n\r\0\x0B" );
	return '' !== $value ? $value : $fallback;
}

function trb_artist_archive_folder( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) return new WP_Error( 'artist_not_found' );
	$artist_name = trb_portal_artist_profile_value( 'artist_name', $user_id );
	$full_name = trim( $user->first_name . ' ' . $user->last_name );
	$name = trb_artist_archive_safe_segment( $artist_name );
	if ( '' !== $full_name ) $name .= ' - ' . trb_artist_archive_safe_segment( $full_name );
	return trb_artist_archive_base_folder() . '/' . $name;
}

function trb_artist_archive_put( $remote, $body, $type = 'application/octet-stream' ) {
	$response = trb_demo_webdav_request( 'PUT', $remote, $body, array( 'Content-Type' => $type ) );
	if ( is_wp_error( $response ) ) return $response;
	$code = wp_remote_retrieve_response_code( $response );
	return in_array( $code, array( 200, 201, 204 ), true ) ? true : new WP_Error( 'pcloud_put_failed', 'WebDAV PUT ' . $code );
}

function trb_artist_archive_delete( $remote ) {
	$response = trb_demo_webdav_request( 'DELETE', $remote );
	if ( is_wp_error( $response ) ) return $response;
	$code = wp_remote_retrieve_response_code( $response );
	return in_array( $code, array( 200, 204, 404 ), true ) ? true : new WP_Error( 'pcloud_delete_failed', 'WebDAV DELETE ' . $code );
}

function trb_artist_archive_local_document( $file ) {
	if ( empty( $file['path'] ) ) return '';
	$uploads = wp_upload_dir();
	$base = realpath( trailingslashit( $uploads['basedir'] ) . 'trb-artist-private' );
	$path = realpath( trailingslashit( $uploads['basedir'] ) . ltrim( $file['path'], '/' ) );
	return $base && $path && 0 === strpos( $path, $base . DIRECTORY_SEPARATOR ) && is_file( $path ) ? $path : '';
}

function trb_artist_archive_profile_text( $user_id ) {
	$user = get_userdata( $user_id );
	$lines = array(
		'ANAGRAFICA ARTISTA',
		'Ultimo aggiornamento archivio: ' . wp_date( 'd/m/Y H:i:s' ),
		'',
		'Nome d’arte: ' . trb_portal_artist_profile_value( 'artist_name', $user_id ),
		'Nome: ' . $user->first_name,
		'Cognome: ' . $user->last_name,
		'E-mail: ' . $user->user_email,
	);
	foreach ( trb_portal_artist_profile_fields() as $key => $label ) {
		if ( 'artist_name' === $key ) continue;
		$value = trb_portal_artist_profile_value( $key, $user_id );
		if ( '' !== trim( $value ) ) $lines[] = $label . ': ' . $value;
	}
	return implode( "\r\n", $lines ) . "\r\n";
}

function trb_artist_archive_document_slots() {
	return array(
		'Carta d’identità — fronte' => 'Carta identita - fronte',
		'Carta d’identità — retro' => 'Carta identita - retro',
		'Codice fiscale o tessera sanitaria — fronte' => 'Codice fiscale o tessera sanitaria - fronte',
		'Codice fiscale o tessera sanitaria — retro' => 'Codice fiscale o tessera sanitaria - retro',
	);
}

function trb_artist_archive_sync( $user_id ) {
	if ( ! function_exists( 'trb_demo_webdav_request' ) ) return new WP_Error( 'pcloud_module_unavailable' );
	if ( function_exists( 'trb_portal_deduplicate_private_profile_files' ) ) trb_portal_deduplicate_private_profile_files( $user_id );
	$folder = trb_artist_archive_folder( $user_id );
	if ( is_wp_error( $folder ) ) return $folder;
	$ready = trb_demo_ensure_remote_folder( $folder );
	if ( is_wp_error( $ready ) ) return $ready;

	$result = trb_artist_archive_put( $folder . '/Dati anagrafici.txt', trb_artist_archive_profile_text( $user_id ), 'text/plain; charset=utf-8' );
	if ( is_wp_error( $result ) ) return $result;

	$documents = array();
	foreach ( trb_portal_private_profile_files( $user_id ) as $file ) {
		if ( empty( $file['label'] ) || ! isset( trb_artist_archive_document_slots()[ $file['label'] ] ) ) continue;
		$documents[ $file['label'] ] = $file;
	}
	foreach ( trb_artist_archive_document_slots() as $label => $remote_name ) {
		foreach ( array( 'pdf', 'jpg', 'jpeg', 'png' ) as $extension ) {
			$deleted = trb_artist_archive_delete( $folder . '/' . $remote_name . '.' . $extension );
			if ( is_wp_error( $deleted ) ) return $deleted;
		}
		if ( empty( $documents[ $label ] ) ) continue;
		$local = trb_artist_archive_local_document( $documents[ $label ] );
		if ( ! $local ) return new WP_Error( 'private_document_missing', $label );
		$extension = strtolower( pathinfo( $local, PATHINFO_EXTENSION ) );
		$type = ! empty( $documents[ $label ]['type'] ) ? $documents[ $label ]['type'] : 'application/octet-stream';
		$uploaded = trb_artist_archive_put( $folder . '/' . $remote_name . '.' . $extension, file_get_contents( $local ), $type ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( is_wp_error( $uploaded ) ) return $uploaded;
	}
	update_user_meta( $user_id, '_trb_artist_pcloud_archive', array( 'status' => 'synced', 'time' => time(), 'folder' => $folder ) );
	return array( 'folder' => $folder, 'documents' => count( $documents ) );
}

function trb_artist_archive_after_profile_save( $user_id ) {
	$result = trb_artist_archive_sync( $user_id );
	if ( is_wp_error( $result ) ) {
		update_user_meta( $user_id, '_trb_artist_pcloud_archive', array( 'status' => 'error', 'time' => time(), 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );
		error_log( 'TRB pCloud artist archive: user ' . absint( $user_id ) . ' - ' . $result->get_error_code() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
add_action( 'trb_portal_artist_profile_saved', 'trb_artist_archive_after_profile_save', 10, 1 );

function trb_artist_archive_admin_page() {
	add_management_page( 'Archivio artisti pCloud', 'Archivio artisti pCloud', 'manage_options', 'trb-artist-pcloud-archive', 'trb_artist_archive_render_admin_page' );
}
add_action( 'admin_menu', 'trb_artist_archive_admin_page' );

function trb_artist_archive_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$result = null;
	if ( isset( $_POST['trb_artist_archive_user'] ) ) {
		check_admin_referer( 'trb_artist_archive_sync' );
		$email = sanitize_email( wp_unslash( $_POST['trb_artist_archive_user'] ) );
		$user = get_user_by( 'email', $email );
		$result = $user ? trb_artist_archive_sync( $user->ID ) : new WP_Error( 'artist_not_found', 'Account non trovato.' );
		if ( $user && ! is_wp_error( $result ) && function_exists( 'trb_artist_promo_sync' ) ) {
			$promo_result = trb_artist_promo_sync( $user->ID );
			if ( is_wp_error( $promo_result ) ) $result = $promo_result;
			else $result['promo_folder'] = $promo_result['folder'];
		}
	}
	?>
	<div class="wrap"><h1>Archivio artisti pCloud</h1><p>Sincronizza anagrafica e documenti riservati già presenti nel portale.</p>
	<?php if ( is_wp_error( $result ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( $result->get_error_code() . ': ' . $result->get_error_message() ); ?></p></div><?php elseif ( is_array( $result ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( 'Sincronizzazione completata: ' . $result['folder'] . ' (' . $result['documents'] . ' documenti).' . ( ! empty( $result['promo_folder'] ) ? ' PROMO: ' . $result['promo_folder'] . '.' : '' ) ); ?></p></div><?php endif; ?>
	<form method="post"><?php wp_nonce_field( 'trb_artist_archive_sync' ); ?><label for="trb-artist-archive-user"><strong>E-mail account artista</strong></label><br><input id="trb-artist-archive-user" name="trb_artist_archive_user" type="email" class="regular-text" required><p><button class="button button-primary">Sincronizza ora</button></p></form></div>
	<?php
}
