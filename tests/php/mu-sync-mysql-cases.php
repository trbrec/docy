<?php
/** Native signed REST requests, SQL rejection and cross-connection locking. */
function qa_mu_request( $payload, $route = '/entitlement', $signed = true, $method = 'POST' ) {
    return qa_http( array( 'action' => 'qa_mu_sync', 'qa_route' => $route, 'qa_body' => wp_json_encode( $payload ), 'qa_signed' => $signed ? '1' : '0', 'qa_method' => $method ) );
}
$qaEntitlement = array( 'artist' => array( 'email' => 'qa-tunisia@example.invalid' ), 'practice' => array( 'public_id' => 'QA-TN-ONLY', 'submission_id' => 1, 'contract_id' => 1, 'contract_number' => 'QA-NONVALIDO' ), 'entitlement' => array( 'eligible' => true, 'portal_status' => 'pronto', 'contract_status' => 'accettato', 'group_code' => 'TRB', 'activation_date' => '2026-10-10' ) );
qa_check( qa_mu_request( $qaEntitlement, '/entitlement', false )['status'] >= 400, 'An unsigned entitlement was accepted.' );
$qaEntitlementKeys = array( '_trb_crm_public_id', '_trb_crm_submission_id', '_trb_crm_contract_id', '_trb_crm_contract_number', '_trb_crm_group_code', '_trb_crm_activation_date', '_trb_crm_historical_releases', '_trb_crm_synced_at' );
clean_user_cache( $qaUser ); wp_cache_delete( $qaUser, 'user_meta' );
$qaOldEntitlement = get_user_meta( $qaUser );
$wpdb->query( "CREATE TRIGGER qa_reject_entitlement BEFORE INSERT ON {$wpdb->usermeta} FOR EACH ROW BEGIN IF NEW.meta_key='_trb_crm_contract_number' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic entitlement persistence failure'; END IF; END" );
try {
    qa_check( qa_mu_request( $qaEntitlement )['status'] === 503, 'A rejected entitlement write falsely activated the account.' );
    clean_user_cache( $qaUser ); wp_cache_delete( $qaUser, 'user_meta' );
    qa_check( get_user_meta( $qaUser ) === $qaOldEntitlement, 'A rejected entitlement left partial role/profile metadata.' );
} finally { $wpdb->query( 'DROP TRIGGER qa_reject_entitlement' ); }
$qaActivated = qa_mu_request( $qaEntitlement );
qa_check( $qaActivated['status'] === 200 && true === ( $qaActivated['data']['activated'] ?? false ), 'Valid signed entitlement could not activate the fictional artist.' );
qa_check( qa_mu_request( $qaEntitlement )['data'] === $qaActivated['data'], 'Identical entitlement retry changed its receipt.' );
$qaPending = $qaEntitlement; $qaPending['artist']['email'] = 'not-registered@example.invalid';
$qaPendingKey = trb_crm_sync_pending_key( $qaPending['artist']['email'] );
$wpdb->query( "CREATE TRIGGER qa_reject_pending BEFORE INSERT ON {$wpdb->options} FOR EACH ROW BEGIN IF NEW.option_name='{$qaPendingKey}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic pending persistence failure'; END IF; END" );
try { qa_check( qa_mu_request( $qaPending )['status'] === 503, 'Pending registration was falsely acknowledged without durable storage.' ); }
finally { $wpdb->query( 'DROP TRIGGER qa_reject_pending' ); }
qa_check( qa_mu_request( $qaPending )['data']['status'] === 'pending_registration', 'Pending registration did not recover after storage was restored.' );
$qaPendingUser = wp_insert_user( array( 'user_login' => 'qa_pending_registration', 'user_email' => $qaPending['artist']['email'], 'user_pass' => wp_generate_password( 32 ) ) );
qa_check( ! is_wp_error( $qaPendingUser ) && true === ( qa_mu_request( $qaPending )['data']['activated'] ?? false ), 'A pending receipt stayed pending after artist registration.' );

$qaMuRelease = wp_insert_post( array( 'post_type' => 'trb_release', 'post_status' => 'private', 'post_author' => $qaUser, 'post_title' => 'Take Down — QA sintetico' ) );
update_post_meta( $qaMuRelease, '_trb_release_pipeline_status', 'draft' );
update_post_meta( $qaMuRelease, '_trb_release_upc', 'OLD' );
$qaMutation = array( 'confirm' => true, 'operation' => 'update_release', 'portal_release_id' => 'artist:' . $qaMuRelease, 'expected_title' => 'Take Down — QA sintetico', 'workflow_status' => 'scheduled', 'planned_release_date' => '2026-12-01', 'catalog_number' => 'QA-ONLY', 'upc' => 'NEW', 'crm_snapshot' => array( 'contract' => array( 'number' => 'QA-NONVALIDO' ) ) );
$qaMuRoute = '/release/' . $qaMuRelease;
$qaBeforeMutation = get_post_meta( $qaMuRelease );
$wpdb->query( "CREATE TRIGGER qa_reject_release BEFORE UPDATE ON {$wpdb->postmeta} FOR EACH ROW BEGIN IF NEW.meta_key='_trb_release_upc' AND NEW.post_id={$qaMuRelease} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic release write rejection'; END IF; END" );
try {
    qa_check( qa_mu_request( $qaMutation, $qaMuRoute )['status'] === 503, 'Partially rejected release update was acknowledged.' );
    clean_post_cache( $qaMuRelease );
    qa_check( get_post_meta( $qaMuRelease ) === $qaBeforeMutation, 'Rejected release update left partial metadata.' );
} finally { $wpdb->query( 'DROP TRIGGER qa_reject_release' ); }
$qaRecoveredMutation = qa_mu_request( $qaMutation, $qaMuRoute );
qa_check( ( $qaRecoveredMutation['data']['updated'] ?? false ) === true, 'Release update could not be retried after failure: ' . wp_json_encode( $qaRecoveredMutation ) );
clean_post_cache( $qaMuRelease );
qa_check( get_post_meta( $qaMuRelease, '_trb_release_pipeline_status', true ) === 'draft' && get_post_meta( $qaMuRelease, '_trb_crm_workflow_status', true ) === 'scheduled', 'CRM business status bypassed the technical pipeline.' );
$qaInvalidDate = $qaMutation; $qaInvalidDate['planned_release_date'] = '2026-02-30';
qa_check( qa_mu_request( $qaInvalidDate, $qaMuRoute )['status'] === 422, 'An impossible calendar date was accepted.' );
$qaStorageLock = 'trb-sync:' . substr( hash( 'sha256', $wpdb->prefix . ':release:' . $qaMuRelease ), 0, 48 );
qa_check( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $qaStorageLock ) ) === '1', 'Native lock fixture could not acquire a lock.' );
try { qa_check( qa_mu_request( $qaMutation, $qaMuRoute )['status'] === 409, 'A second connection updated a locked release.' ); }
finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $qaStorageLock ) ); }
$qaOtherPost = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'private', 'post_title' => 'Contenuto ordinario QA' ) );
update_post_meta( $qaOtherPost, '_trb_release_status', 'draft' );
$qaDelete = array( 'confirm' => true, 'operation' => 'delete_release', 'portal_release_id' => 'artist:' . $qaOtherPost, 'expected_title' => 'Contenuto ordinario QA' );
qa_check( qa_mu_request( $qaDelete, '/release/' . $qaOtherPost )['status'] === 409 && get_post( $qaOtherPost ) instanceof WP_Post, 'A normal post with release-like metadata was deleted.' );
class QaSerializedObject { public function __wakeup() { throw new RuntimeException( 'Object construction during metadata decoding.' ); } }
qa_check( null === trb_crm_sync_decode_serialized( serialize( new QaSerializedObject() ) ), 'Serialized PHP objects escaped the safe metadata decoder.' );
qa_check( array( 'x' => 'تونس' ) === trb_crm_sync_decode_serialized( serialize( array( 'x' => 'تونس' ) ) ), 'Safe Unicode metadata could not round-trip.' );
qa_check( in_array( 'artist:' . $qaMuRelease, trb_crm_complete_sync_manifest_state()['portal_release_ids'], true ), 'A release was excluded solely because its title contained Take Down.' );
$qaDelete['portal_release_id'] = 'artist:' . $qaMuRelease; $qaDelete['expected_title'] = 'Take Down — QA sintetico';
$qaBeforeDelete = get_post_meta( $qaMuRelease );
$wpdb->query( "CREATE TRIGGER qa_reject_release_delete BEFORE DELETE ON {$wpdb->posts} FOR EACH ROW BEGIN IF OLD.ID={$qaMuRelease} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic post deletion failure'; END IF; END" );
try {
    qa_check( qa_mu_request( $qaDelete, $qaMuRoute )['status'] === 503, 'Failed post deletion was acknowledged.' );
    clean_post_cache( $qaMuRelease );
    qa_check( get_post( $qaMuRelease ) instanceof WP_Post && get_post_meta( $qaMuRelease ) === $qaBeforeDelete, 'Failed deletion lost the release or its metadata.' );
} finally { $wpdb->query( 'DROP TRIGGER qa_reject_release_delete' ); }
qa_check( true === ( qa_mu_request( $qaDelete, $qaMuRoute )['data']['deleted'] ?? false ), 'Deletion retry did not recover.' );
qa_check( true === ( qa_mu_request( $qaDelete, $qaMuRoute )['data']['already_absent'] ?? false ), 'Identical deletion retry was not idempotent.' );
