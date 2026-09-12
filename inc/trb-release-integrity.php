<?php
/** Shared release identity, process locks and durable acquisition checkpoints. */
if ( ! defined( 'ABSPATH' ) ) exit;

/** File locks are released by PHP even after exit/fatal errors; never unlink them. */
function trb_release_process_lock( $scope ) {
	$uploads = wp_upload_dir();
	$directory = rtrim( $uploads['basedir'], '/\\' ) . '/trb-release-locks';
	if ( ! empty( $uploads['error'] ) || ! wp_mkdir_p( $directory ) ) return false;
	$handle = fopen( $directory . '/' . hash( 'sha256', $scope ) . '.lock', 'c' );
	if ( ! $handle ) return false;
	if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) { fclose( $handle ); return false; }
	// A pre-lock read can be stale in WordPress's per-request object cache.
	if ( 0 === strpos($scope,'release:') && function_exists('wp_cache_delete') ) {
		$id = absint(substr($scope,8));
		wp_cache_delete($id,'posts');
		wp_cache_delete($id,'post_meta');
	}
	return $handle;
}
function trb_release_process_unlock( $handle ) {
	if ( is_resource( $handle ) ) { flock( $handle, LOCK_UN ); fclose( $handle ); }
}
function trb_release_is_inactive( $id ) {
	$post = get_post( $id );
	return ! $post || 'trb_release' !== $post->post_type || 'trash' === $post->post_status || (bool) get_post_meta( $id, '_trb_owner_cancelled_at', true );
}
function trb_release_current_audio_hash( $id, $track, $hash ) {
	if ( trb_release_is_inactive( $id ) || ! $hash ) return false;
	foreach ( (array) get_post_meta( $id, '_trb_release_files', true ) as $file ) {
		if ( is_array($file) && 'audio' === ($file['kind'] ?? '') && (int)($file['track'] ?? -1) === (int)$track ) return hash_equals( (string)($file['sha256'] ?? ''), (string)$hash );
	}
	return false;
}
/** Technical approval must describe every current audio file and declared track. */
function trb_release_technical_is_current( $id ) {
	$technical = get_post_meta($id, '_trb_release_technical_analysis', true);
	if (!is_array($technical) || !in_array($technical['status'] ?? '', array('passed','warning'), true)) return false;
	$tracks = get_post_meta($id, '_trb_release_tracks', true);
	if (!is_array($tracks) || !$tracks) return false;
	$seen = array();
	foreach ((array)get_post_meta($id, '_trb_release_files', true) as $file) {
		if (!is_array($file) || 'audio' !== ($file['kind'] ?? '')) continue;
		$i = $file['track'] ?? -1;
		if (!isset($tracks[$i]) || isset($seen[$i]) || empty($file['sha256'])) return false;
		$measured = $technical['tracks'][$i] ?? array();
		if (!in_array($measured['status'] ?? '', array('passed','warning'), true) || !hash_equals((string)$file['sha256'], (string)($measured['sha256'] ?? ''))) return false;
		$seen[$i] = true;
	}
	return count($seen) === count($tracks);
}
function trb_intake_identity_text( $value ) {
	$value = preg_replace( '/\s+/u', ' ', trim( sanitize_text_field( (string) $value ) ) );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
}
/** Identifies a project, not a particular form revision or audio byte sequence. */
function trb_intake_project_identity( $post ) {
	$title = trb_intake_identity_text( $post['trb_release_title'] ?? '' );
	$type = sanitize_key( $post['trb_release_type'] ?? '' );
	$tracks = $post['trb_tracks'] ?? array();
	if ( ! $title || ! $type || ! is_array( $tracks ) || ! $tracks || count( $tracks ) > 24 ) return '';
	$ordered = array();
	foreach ( $tracks as $track ) {
		if ( ! is_array( $track ) || '' === trb_intake_identity_text( $track['title'] ?? '' ) ) return '';
		$ordered[] = array( trb_intake_identity_text( $track['title'] ), trb_intake_identity_text( $track['version'] ?? '' ) );
	}
	return hash( 'sha256', wp_json_encode( array( $title, $type, $ordered ) ) );
}
/** Return a conflict for explicit recovery; never silently merge projects. */
function trb_intake_project_conflict( $user_id, $post, $exclude_id = 0 ) {
	$identity = trb_intake_project_identity( $post );
	if ( ! $identity ) return 0;
	$candidates = get_posts( array( 'post_type' => 'trb_release', 'post_status' => array( 'publish', 'private', 'pending', 'draft' ), 'author' => absint( $user_id ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'DESC', 'cache_results' => false ) );
	foreach ( $candidates as $candidate ) {
		if ((int)$candidate->ID === (int)$exclude_id) continue;
		if (function_exists('wp_cache_delete')) wp_cache_delete($candidate->ID,'post_meta');
		if ( get_post_meta( $candidate->ID, '_trb_owner_cancelled_at', true ) ) continue;
		$previous = array( 'trb_release_title' => $candidate->post_title, 'trb_release_type' => get_post_meta( $candidate->ID, '_trb_release_type', true ), 'trb_tracks' => get_post_meta( $candidate->ID, '_trb_release_tracks', true ) );
		if ( hash_equals( $identity, trb_intake_project_identity( $previous ) ) ) return (int) $candidate->ID;
	}
	return 0;
}
function trb_intake_checkpoint_file( $id, $file ) {
	if ( 'acquiring_files' !== get_post_meta( $id, '_trb_release_intake_phase', true ) ) return;
	$files = get_post_meta( $id, '_trb_release_acquired_files', true );
	$files = is_array( $files ) ? $files : array();
	$files[ $file['kind'] . ':' . (string) $file['track'] ] = $file;
	update_post_meta( $id, '_trb_release_acquired_files', $files );
}
