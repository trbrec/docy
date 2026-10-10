<?php
/** Native demo failures and retries, included by the isolated MySQL/HTTP harness. */
if ( ! defined( 'ABSPATH' ) || ! isset( $qaRoot, $qaUser, $qaOutbox ) || ! function_exists( 'qa_http' ) ) exit( 1 );
$qaDemoPath = $qaRoot . '/qa-demo.txt';
file_put_contents( $qaDemoPath, "Testo interamente fittizio per il collaudo.\n" );
$qaDemo = array(
    'action' => 'trb_portal_submit_demo', 'trb_demo_async' => '1',
    'trb_demo_nonce' => qa_http()['data']['demo_nonce'],
    'trb_demo_title' => 'Provino fittizio Tunisia', 'trb_demo_genre' => 'Electronic',
    'trb_demo_focus' => 'lyrics', 'trb_demo_origin_lyrics' => 'own',
    'trb_demo_submission_kind' => 'new', 'trb_demo_notes' => "L'artista usa la notazione C:\\Musica.",
    'trb_demo_text' => new CURLFile( $qaDemoPath, 'text/plain', 'provino.txt' ),
);
function qa_demo_snapshot() {
    global $wpdb, $qaUser, $qaOutbox;
    return array(
        'requests' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='trb_request' AND post_author=%d", $qaUser ) ),
        'quota' => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key,meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key IN ('_trb_demo_last_submission','_trb_demo_last_fingerprint') ORDER BY meta_key", $qaUser ), ARRAY_A ),
        'cron' => $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='cron'" ),
        'outbox' => $wpdb->get_results( "SELECT event_id,status FROM {$qaOutbox} ORDER BY id", ARRAY_A ),
        'files' => glob( wp_upload_dir()['basedir'] . '/trb-demo-private/*' ) ?: array(),
    );
}
$qaDemoBefore = qa_demo_snapshot();
qa_check( ! trb_portal_is_demo_test_account( get_userdata( $qaUser ) ), 'Demo fixture bypasses the ordinary weekly limit.' );
$qaDemoLock = trb_release_process_lock( 'demo-user:' . $qaUser );
qa_check( is_resource( $qaDemoLock ), 'Could not acquire the independent demo process lock.' );
try {
    $qaDemoConcurrent = qa_http( $qaDemo );
    qa_check( $qaDemoConcurrent['data']['status'] === 'processing', 'A concurrent demo request bypassed the process lock: ' . wp_json_encode( $qaDemoConcurrent ) );
    qa_check( qa_demo_snapshot() === $qaDemoBefore, 'A rejected concurrent request changed demo storage.' );
} finally { trb_release_process_unlock( $qaDemoLock ); }

// Fail actual MySQL INSERTs at successive points after files have been acquired.
foreach ( array(
    array( $wpdb->postmeta, '_trb_demo_payload' ),
    array( $wpdb->postmeta, '_trb_demo_earliest_delivery' ),
    array( $wpdb->postmeta, '_trb_demo_delete_after' ),
    array( $wpdb->usermeta, '_trb_demo_last_submission' ),
    array( $wpdb->usermeta, '_trb_demo_last_fingerprint' ),
) as list( $qaDemoTable, $qaDemoKey ) ) {
    $wpdb->query( $wpdb->prepare( "CREATE TRIGGER qa_reject_demo BEFORE INSERT ON {$qaDemoTable} FOR EACH ROW BEGIN IF NEW.meta_key=%s THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic demo failure'; END IF; END", $qaDemoKey ) );
    try {
        $qaDemoResult = qa_http( $qaDemo );
        qa_check( $qaDemoResult['data']['success'] === false && $qaDemoResult['data']['status'] === 'storage_error', 'Failed native demo write was reported as success: ' . $qaDemoKey );
        qa_check( qa_demo_snapshot() === $qaDemoBefore, 'Failed demo left requests, files, quota, cron or outbox changes: ' . $qaDemoKey );
    } finally { $wpdb->query( 'DROP TRIGGER qa_reject_demo' ); }
}
$qaDemoScheduleFailure = $qaDemo; $qaDemoScheduleFailure['qa_reject_demo_schedule'] = '1';
qa_check( qa_http( $qaDemoScheduleFailure )['data']['status'] === 'storage_error', 'Rejected scheduling was reported as a complete demo.' );
qa_check( qa_demo_snapshot() === $qaDemoBefore, 'Failed scheduling consumed the demo quota or left acquired data.' );

$qaDemoSession = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
$qaDemoChunk = array( 'action' => 'trb_portal_stage_release_chunk', 'trb_release_stage_nonce' => qa_http()['data']['stage_nonce'], 'session' => $qaDemoSession, 'file_key' => 'f2000', 'file_name' => 'provino.txt', 'file_type' => 'text/plain', 'file_size' => (string) filesize( $qaDemoPath ), 'last_modified' => '1', 'chunk_index' => '0', 'chunk_total' => '1', 'upload_id' => 'qa-demo-staged', 'trb_release_chunk' => new CURLFile( $qaDemoPath, 'text/plain', 'chunk.txt' ) );
$qaDemoChunk['field_name'] = 'trb_demo_text';
qa_check( qa_http( $qaDemoChunk )['data']['data']['complete'] === true, 'Real staged demo upload did not complete.' );
$qaStagedDemo = $qaDemo; unset( $qaStagedDemo['trb_demo_text'] );
$qaStagedDemo['trb_release_submission_token'] = $qaDemoSession;
$qaStagedDemo['trb_staged_uploads_json'] = wp_json_encode( array( 'trb_demo_text' => array( 'key' => 'f2000', 'upload_id' => 'qa-demo-staged', 'session' => $qaDemoSession ) ) );
$qaDemoPart = trb_portal_release_staging_base() . '/' . $qaUser . '/' . $qaDemoSession . '/f2000.part';
$wpdb->query( "CREATE TRIGGER qa_reject_demo BEFORE INSERT ON {$wpdb->postmeta} FOR EACH ROW BEGIN IF NEW.meta_key='_trb_demo_payload' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic staged demo failure'; END IF; END" );
try {
    $qaStagedFailure = qa_http( $qaStagedDemo );
    qa_check( $qaStagedFailure['data']['status'] === 'storage_error', 'Failed staged demo returned an unexpected result: ' . wp_json_encode( $qaStagedFailure ) );
    qa_check( is_file( $qaDemoPart ) && file_get_contents( $qaDemoPart ) === file_get_contents( $qaDemoPath ), 'Failed persistence destroyed the staged original needed for retry.' );
    qa_check( qa_demo_snapshot() === $qaDemoBefore, 'Failed staged demo left acquired data or consumed quota.' );
} finally { $wpdb->query( 'DROP TRIGGER qa_reject_demo' ); }
$qaDemoResult = qa_http( $qaStagedDemo );
qa_check( $qaDemoResult['data']['success'] === true && $qaDemoResult['data']['status'] === 'sent', 'An ordinary artist could not retry after a storage failure.' );
clearstatcache( true, $qaDemoPart );
qa_check( ! is_file( $qaDemoPart ), 'Confirmed acquisition retained completed staging bytes.' );
$qaDemoSaved = qa_demo_snapshot();
qa_check( $qaDemoSaved['requests'] === $qaDemoBefore['requests'] + 1 && count( $qaDemoSaved['files'] ) === count( $qaDemoBefore['files'] ) + 1, 'Successful demo did not persist exactly one request and one file.' );
$qaDemoId = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type='trb_request' AND post_author=%d", $qaUser ) );
wp_cache_delete( $qaDemoId, 'post_meta' );
$qaDemoPayload = get_post_meta( $qaDemoId, '_trb_demo_payload', true );
qa_check( $qaDemoPayload['review_context']['notes'] === $qaDemo['trb_demo_notes'], 'Persistence changed apostrophes or backslashes in demo notes.' );
qa_check( file_get_contents( wp_upload_dir()['basedir'] . '/' . $qaDemoPayload['text_file']['path'] ) === file_get_contents( $qaDemoPath ), 'The recorded demo file differs from the uploaded bytes.' );
wp_cache_delete( 'cron', 'options' ); wp_cache_delete( 'alloptions', 'options' );
qa_check( (bool) wp_next_scheduled( 'trb_portal_process_demo', array( $qaDemoId ) ), 'Successful demo has no processing event.' );
qa_check( qa_http( $qaDemo )['data']['status'] === 'duplicate', 'An identical retry created another demo.' );
qa_check( qa_demo_snapshot() === $qaDemoSaved, 'An identical retry changed persisted demo data.' );
$qaDemoOriginal = file_get_contents( $qaDemoPath );
// Keep the byte length equal; equal filenames and sizes must not imply equality.
file_put_contents( $qaDemoPath, str_repeat( 'X', strlen( $qaDemoOriginal ) ) );
qa_check( qa_http( $qaDemo )['data']['status'] === 'weekly_limit', 'Different bytes with equal filename and size were falsely confirmed as a duplicate.' );
qa_check( qa_demo_snapshot() === $qaDemoSaved, 'A weekly-limit rejection changed the acquired demo.' );
echo "Native demo rollback, retry, concurrency, content identity and weekly-limit assertions passed.\n";
