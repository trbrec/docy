<?php
/** Actual WordPress workers with native locks/SQL failures; providers intercepted. */
if ( ! defined( 'ABSPATH' ) || ! isset( $qaUser, $qaDemoPayload ) ) exit( 1 );
update_option( 'trb_demo_automation_settings', array( 'webdav_endpoint' => 'https://qa-webdav.example.invalid', 'pcloud_user' => 'fixture', 'pcloud_pass' => 'fixture' ) );
function qa_demo_worker( $id, $worker, $extra = array() ) {
    return qa_http( array_merge( array( 'action' => 'qa_demo_worker', 'qa_request_id' => $id, 'qa_worker' => $worker ), $extra ) )['data'];
}
function qa_worker_fixture( $status = 'ready' ) {
    global $qaUser, $qaDemoPayload;
    $id = wp_insert_post( array( 'post_type' => 'trb_request', 'post_status' => 'publish', 'post_author' => $qaUser, 'post_title' => '[QA] Worker fictional demo' ) );
    $payload = $qaDemoPayload;
    $payload['status'] = $status;
    // Scheduling bypass only: native mail/socket transports remain disabled and
    // pre_wp_mail captures attempts before a transport can be invoked.
    $payload['owner_qa'] = true; $payload['email'] = 'andrea.tognassi@trbrec.com';
    $payload['uuid'] = wp_generate_uuid4();
    $uploads = wp_upload_dir();
    $path = 'trb-demo-private/worker-' . $id . '.txt';
    copy( $uploads['basedir'] . '/' . $qaDemoPayload['text_file']['path'], $uploads['basedir'] . '/' . $path );
    $payload['text_file']['path'] = $path;
    update_post_meta( $id, '_trb_demo_payload', wp_slash( $payload ) );
    update_post_meta( $id, '_trb_demo_review', wp_slash( "Valutiamo il testo: conserviamo l'espressione C:\\Musica." ) );
    update_post_meta( $id, '_trb_demo_openai_usage', array( 'estimated_cost_usd' => .01 ) );
    update_post_meta( $id, '_trb_demo_delete_after', time() - 10 );
    return $id;
}

$qaWorkerId = qa_worker_fixture();
$qaWorkerLock = trb_release_process_lock( 'demo-request:' . $qaWorkerId );
try {
    foreach ( array( 'process', 'send', 'cleanup' ) as $worker ) {
        $result = qa_demo_worker( $qaWorkerId, $worker );
        qa_check( $result['mail_calls'] === 0 && $result['http_calls'] === 0 && $result['text_exists'] && $result['payload']['status'] === 'ready', 'Concurrent worker bypassed the shared native lock: ' . $worker );
    }
} finally { trb_release_process_unlock( $qaWorkerLock ); }
$result = qa_demo_worker( $qaWorkerId, 'send' );
qa_check( $result['mail_calls'] === 1 && $result['payload']['status'] === 'sent', 'Native sender did not persist a successful intercepted send.' );
qa_check( qa_demo_worker( $qaWorkerId, 'send' )['mail_calls'] === 0, 'Native sender repeated a delivered message.' );

// A failed durable intent must prevent the side effect entirely.
$qaWorkerId = qa_worker_fixture();
$wpdb->query( "CREATE TRIGGER qa_worker_reject BEFORE UPDATE ON {$wpdb->postmeta} FOR EACH ROW BEGIN IF NEW.post_id={$qaWorkerId} AND NEW.meta_key='_trb_demo_payload' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic worker intent failure'; END IF; END" );
try {
    $result = qa_demo_worker( $qaWorkerId, 'send' );
    qa_check( $result['mail_calls'] === 0 && $result['payload']['status'] === 'ready', 'Sender sent mail despite rejected durable intent.' );
} finally { $wpdb->query( 'DROP TRIGGER qa_worker_reject' ); }

// If SMTP accepted mail but the final write fails, the next worker must not send again.
$wpdb->query( "CREATE TRIGGER qa_worker_reject BEFORE UPDATE ON {$wpdb->postmeta} FOR EACH ROW BEGIN IF NEW.post_id={$qaWorkerId} AND NEW.meta_key='_trb_demo_payload' AND NEW.meta_value LIKE '%\"sent\"%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic accepted-mail persistence failure'; END IF; END" );
try {
    $result = qa_demo_worker( $qaWorkerId, 'send' );
    qa_check( $result['mail_calls'] === 1 && $result['payload']['status'] === 'sending', 'Final write failure lost the durable delivery intent.' );
    qa_check( qa_demo_worker( $qaWorkerId, 'send' )['mail_calls'] === 0, 'Uncertain accepted mail was automatically sent a second time.' );
} finally { $wpdb->query( 'DROP TRIGGER qa_worker_reject' ); }
wp_cache_delete( $qaWorkerId, 'post_meta' );
$payload = get_post_meta( $qaWorkerId, '_trb_demo_payload', true );
$payload['delivery_started_at'] = gmdate( 'c', time() - HOUR_IN_SECONDS );
update_post_meta( $qaWorkerId, '_trb_demo_payload', wp_slash( $payload ) );
$result = qa_demo_worker( $qaWorkerId, 'recover' );
qa_check( $result['payload']['status'] === 'delivery_uncertain' && $result['mail_calls'] === 0, 'Recovery resent an uncertain delivery or hid it from the team.' );

$qaWorkerId = qa_worker_fixture();
$result = qa_demo_worker( $qaWorkerId, 'send', array( 'qa_mail_mode' => 'throw' ) );
qa_check( $result['interrupted'] && $result['payload']['status'] === 'sending', 'Interrupted transport lost its persisted delivery intent.' );
qa_check( qa_demo_worker( $qaWorkerId, 'send' )['mail_calls'] === 0, 'Interrupted transport was automatically retried as a new message.' );
$qaWorkerId = qa_worker_fixture();
$result = qa_demo_worker( $qaWorkerId, 'send', array( 'qa_mail_mode' => 'false' ) );
qa_check( $result['payload']['status'] === 'ready' && $result['send_scheduled'], 'Explicit transport rejection did not retain a retryable review.' );
qa_check( qa_demo_worker( $qaWorkerId, 'send' )['payload']['status'] === 'sent', 'Explicitly rejected transport could not retry.' );

$qaWorkerId = qa_worker_fixture( 'queued' );
update_post_meta( $qaWorkerId, '_trb_demo_analysis_pending', gmdate( 'c' ) );
$result = qa_demo_worker( $qaWorkerId, 'process' );
qa_check( $result['http_calls'] === 0 && $result['payload']['status'] === 'manual_review' && $result['error_code'] === 'analysis_result_uncertain', 'Interrupted paid analysis was charged again automatically.' );

$qaWorkerId = qa_worker_fixture( 'queued' );
$result = qa_demo_worker( $qaWorkerId, 'process' );
qa_check( $result['payload']['status'] === 'ready' && $result['http_calls'] > 0 && $result['mail_calls'] === 0, 'Native worker failed to reuse an already saved review through verified archive upload.' );
wp_cache_delete( $qaWorkerId, 'post_meta' );
qa_check( get_post_meta( $qaWorkerId, '_trb_demo_review', true ) === "Valutiamo il testo: conserviamo l'espressione C:\\Musica.", 'Worker metadata writes changed backslashes or apostrophes.' );

update_post_meta( $qaWorkerId, '_trb_demo_delete_after', time() + HOUR_IN_SECONDS );
$result = qa_demo_worker( $qaWorkerId, 'cleanup' );
qa_check( $result['text_exists'] && $result['http_calls'] === 0 && ! $result['cleaned_at'], 'Cleanup removed material before its retention deadline.' );
update_post_meta( $qaWorkerId, '_trb_demo_delete_after', time() - 10 );
$result = qa_demo_worker( $qaWorkerId, 'cleanup', array( 'qa_delete_code' => 500 ) );
qa_check( ! empty( $result['remote']['folder'] ) && ! $result['cleaned_at'] && $result['cleanup_scheduled'], 'Failed remote deletion discarded the evidence needed for retry.' );
$result = qa_demo_worker( $qaWorkerId, 'cleanup', array( 'qa_delete_code' => 404 ) );
qa_check( empty( $result['remote'] ) && $result['cleaned_at'] && ! $result['text_exists'], 'Confirmed absent archive was not marked as cleaned.' );
// More than one page of completed history cannot hide a new unscheduled request.
for ( $historical = 0; $historical < 105; $historical++ ) {
    $id = wp_insert_post( array( 'post_type' => 'trb_request', 'post_status' => 'publish', 'post_author' => $qaUser, 'post_title' => '[QA] Completed history', 'post_date' => '2020-01-01 00:00:00', 'post_modified' => '2020-01-01 00:00:00' ) );
    update_post_meta( $id, '_trb_demo_payload', array( 'status' => 'sent' ) );
}
$qaWorkerId = qa_worker_fixture( 'queued' );
$result = qa_demo_worker( $qaWorkerId, 'recover' );
qa_check( $result['process_scheduled'] && $result['mail_calls'] === 0 && $result['http_calls'] === 0, 'Completed history starved a newer pending worker.' );
echo "Native demo worker locks, delivery uncertainty, paid-analysis interruption and retention assertions passed.\n";
