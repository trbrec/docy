<?php
/** Definitive cover storage uses real multipart bytes and deliberate MySQL faults. */
defined( 'ABSPATH' ) || exit;
$qaCoverRelease = wp_insert_post( array( 'post_type' => 'trb_release', 'post_status' => 'publish', 'post_author' => $qaUser, 'post_title' => 'Synthetic definitive cover' ) );
$qaCoverRequest = wp_insert_post( array( 'post_type' => 'trb_cover_request', 'post_status' => 'private', 'post_title' => 'Synthetic linked cover request' ) );
update_post_meta( $qaCoverRelease, '_trb_release_cover_mode', 'request' );
update_post_meta( $qaCoverRelease, '_trb_release_cover_request_id', $qaCoverRequest );
update_post_meta( $qaCoverRelease, '_trb_release_pipeline_status', 'cover_creation_pending' );
update_post_meta( $qaCoverRequest, '_trb_cover_request_status', 'pending' );
$qaCoverPriorUser = get_current_user_id();
wp_set_current_user( $qaUser );
$qaCoverNonce = wp_create_nonce( 'trb_portal_attach_release_cover_' . $qaCoverRelease );
wp_set_current_user( $qaCoverPriorUser );
$qaCoverImage = $qaRoot . '/qa-definitive-cover.png';
$qaPngChunk = static function( $type, $data ) { return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) ); };
file_put_contents( $qaCoverImage, "\x89PNG\r\n\x1a\n" . $qaPngChunk( 'IHDR', pack( 'NNCCCCC', 1254, 1254, 8, 2, 0, 0, 0 ) ) . $qaPngChunk( 'IDAT', gzcompress( str_repeat( "\x00" . str_repeat( "\x80", 1254 * 3 ), 1254 ), 9 ) ) . $qaPngChunk( 'IEND', '' ) );
$qaCoverForm = array( 'action' => 'trb_portal_attach_release_cover', 'trb_release_id' => $qaCoverRelease, 'trb_release_cover_nonce' => $qaCoverNonce, 'trb_release_cover_300dpi' => '1', 'trb_release_cover' => new CURLFile( $qaCoverImage, 'image/png', 'cover.png' ) );
$qaCoverRead = static function( $id ) { wp_cache_delete( $id, 'post_meta' ); return get_post_meta( $id ); };
$qaCoverBefore = $qaCoverRead( $qaCoverRelease );
$qaCoverRequestBefore = $qaCoverRead( $qaCoverRequest );
$qaCoverDirectory = wp_upload_dir()['basedir'] . '/trb-release-private/' . $qaCoverRelease;
try {
    qa_check( qa_http( $qaCoverForm, true )['status'] === 302 && str_contains( qa_http( $qaCoverForm, true )['location'], 'wp-login.php' ), 'Anonymous definitive-cover upload was accepted.' );
    qa_check( qa_http( array_replace( $qaCoverForm, array( 'trb_release_cover_nonce' => 'invalid' ) ) )['status'] === 403, 'Forged definitive-cover nonce was accepted.' );
    $qaCoverLock = trb_release_process_lock( 'release:' . $qaCoverRelease );
    try { qa_check( str_contains( qa_http( $qaCoverForm )['location'], 'file_invalid' ), 'Concurrent cover mutation was not blocked.' ); }
    finally { trb_release_process_unlock( $qaCoverLock ); }
    qa_check( $qaCoverRead( $qaCoverRelease ) === $qaCoverBefore && count( glob( $qaCoverDirectory . '/*' ) ) === 0, 'Concurrent cover attempt changed stored materials.' );
    foreach ( array( array( 'INSERT', $qaCoverRelease, '_trb_release_files' ), array( 'INSERT', $qaCoverRelease, '_trb_release_cover_status' ), array( 'UPDATE', $qaCoverRequest, '_trb_cover_request_status' ), array( 'UPDATE', $qaCoverRelease, '_trb_release_pipeline_status' ) ) as [ $qaCoverOperation, $qaCoverPost, $qaCoverKey ] ) {
    $wpdb->query( "CREATE TRIGGER qa_reject_cover BEFORE {$qaCoverOperation} ON {$wpdb->postmeta} FOR EACH ROW BEGIN IF NEW.post_id={$qaCoverPost} AND NEW.meta_key='{$qaCoverKey}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA cover write failure'; END IF; END" );
    try {
        $qaCoverFailed = qa_http( $qaCoverForm );
        qa_check( str_contains( $qaCoverFailed['location'], 'file_invalid' ), 'A real definitive-cover MySQL failure was reported as upload success.' );
        qa_check( $qaCoverRead( $qaCoverRelease ) === $qaCoverBefore && $qaCoverRead( $qaCoverRequest ) === $qaCoverRequestBefore, 'Failed definitive cover changed release/request metadata.' );
        qa_check( count( glob( $qaCoverDirectory . '/*' ) ) === 0, 'Failed definitive cover left unreferenced uploaded bytes.' );
    } finally { $wpdb->query( 'DROP TRIGGER IF EXISTS qa_reject_cover' ); }
    }
    qa_check( str_contains( qa_http( array_replace( $qaCoverForm, array( 'qa_cover_commit_failure' => '1' ) ) )['location'], 'file_invalid' ), 'Failed definitive-cover COMMIT was acknowledged.' );
    qa_check( $qaCoverRead( $qaCoverRelease ) === $qaCoverBefore && $qaCoverRead( $qaCoverRequest ) === $qaCoverRequestBefore, 'Failed cover COMMIT left partial metadata.' );
    qa_check( count( glob( $qaCoverDirectory . '/*' ) ) === 0, 'Failed cover COMMIT left new bytes.' );
    qa_check( str_contains( qa_http( $qaCoverForm )['location'], 'cover_uploaded' ), 'Definitive cover retry failed after storage recovered.' );
    $qaCoverSaved = $qaCoverRead( $qaCoverRelease );
    $qaCoverFiles = get_post_meta( $qaCoverRelease, '_trb_release_files', true );
    qa_check( count( $qaCoverFiles ) === 1 && trb_file_retry_verified( $qaCoverRelease, $qaCoverFiles[0] ), 'Definitive cover receipt does not match stored bytes.' );
    $qaCoverRead( $qaCoverRequest );
    qa_check( get_post_meta( $qaCoverRequest, '_trb_cover_request_status', true ) === 'completed', 'Linked cover request was not durably completed.' );
    qa_check( str_contains( qa_http( $qaCoverForm )['location'], 'trb_release=invalid' ) && $qaCoverRead( $qaCoverRelease ) === $qaCoverSaved, 'Repeated cover upload duplicated or changed definitive materials.' );
    echo "Native definitive-cover failure, metadata/file rollback and retry assertions passed.\n";
} finally {
    $wpdb->query( 'DROP TRIGGER IF EXISTS qa_reject_cover' );
    trb_portal_delete_release_files( (array) get_post_meta( $qaCoverRelease, '_trb_release_files', true ) );
    wp_delete_post( $qaCoverRequest, true );
    wp_delete_post( $qaCoverRelease, true );
}
