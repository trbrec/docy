<?php
/** Real HTTP, capabilities and failed MySQL writes for inherited settings actions. */
defined( 'ABSPATH' ) || exit;
$qaSettingsKey = 'qa_csf_fixture';
$qaPreviousSettings = array( 'previous' => 'preserved' );
$qaSettingsRead = static function() use ( $wpdb, $qaSettingsKey ) {
    return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $qaSettingsKey ) );
};
$qaSettingsAdmin = qa_http( null, false, true )['data'];
$qaSettingsImport = array( 'action' => 'csf-import', 'unique' => $qaSettingsKey, 'nonce' => $qaSettingsAdmin['settings_nonce'], 'data' => wp_json_encode( array( 'fixture' => 'updated' ) ) );
update_option( $qaSettingsKey, $qaPreviousSettings, false );
try {
    foreach ( array( true, false ) as $qaSettingsAnonymous ) {
        $qaSettingsDenied = $qaSettingsImport;
        $qaSettingsDenied['nonce'] = qa_http( null, $qaSettingsAnonymous )['data']['settings_nonce'];
        qa_check( false === qa_http( $qaSettingsDenied, $qaSettingsAnonymous )['data']['success'], 'Anonymous or artist settings import was authorized.' );
        qa_check( $qaSettingsRead() === maybe_serialize( $qaPreviousSettings ), 'Denied settings import changed previous values.' );
    }
    $qaSettingsBadNonce = $qaSettingsImport;
    $qaSettingsBadNonce['nonce'] = 'invalid';
    qa_check( false === qa_http( $qaSettingsBadNonce, false, true )['data']['success'], 'Administrator settings import accepted an invalid nonce.' );
    $wpdb->query( "CREATE TRIGGER qa_reject_settings_update BEFORE UPDATE ON {$wpdb->options} FOR EACH ROW BEGIN IF NEW.option_name='qa_csf_fixture' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA settings update failure'; END IF; END" );
    try {
        qa_check( false === qa_http( $qaSettingsImport, false, true )['data']['success'], 'A real MySQL settings write failure produced a success response.' );
        qa_check( $qaSettingsRead() === maybe_serialize( $qaPreviousSettings ), 'Failed native settings update lost previous values.' );
    } finally { $wpdb->query( 'DROP TRIGGER qa_reject_settings_update' ); }
    qa_check( true === qa_http( $qaSettingsImport, false, true )['data']['success'], 'Settings import could not retry after database recovery.' );
    qa_check( $qaSettingsRead() === maybe_serialize( array( 'fixture' => 'updated' ) ), 'Successful settings import did not persist the exact data.' );
    qa_check( true === qa_http( $qaSettingsImport, false, true )['data']['success'], 'An identical verified settings import was incorrectly rejected.' );
    $qaSettingsReset = array( 'action' => 'csf-reset', 'unique' => $qaSettingsKey, 'nonce' => $qaSettingsAdmin['settings_nonce'] );
    $wpdb->query( "CREATE TRIGGER qa_reject_settings_delete BEFORE DELETE ON {$wpdb->options} FOR EACH ROW BEGIN IF OLD.option_name='qa_csf_fixture' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA settings delete failure'; END IF; END" );
    try {
        qa_check( false === qa_http( $qaSettingsReset, false, true )['data']['success'], 'A real MySQL settings deletion failure produced a success response.' );
        qa_check( $qaSettingsRead() === maybe_serialize( array( 'fixture' => 'updated' ) ), 'Failed native settings reset lost previous values.' );
    } finally { $wpdb->query( 'DROP TRIGGER qa_reject_settings_delete' ); }
    qa_check( true === qa_http( $qaSettingsReset, false, true )['data']['success'], 'Settings reset could not retry after database recovery.' );
    qa_check( null === $qaSettingsRead(), 'Successful settings reset retained the option.' );
    qa_check( true === qa_http( $qaSettingsReset, false, true )['data']['success'], 'An already absent settings option was incorrectly rejected.' );
    wp_cache_delete( $qaSettingsKey, 'options' );
    update_option( $qaSettingsKey, $qaPreviousSettings, false );
    $qaFrameworkPayload = array( 'csf_options_nonce' . $qaSettingsKey => $qaSettingsAdmin['settings_form_nonce'], $qaSettingsKey => array( 'fixture' => 'updated' ) );
    $qaFrameworkSave = array( 'action' => 'qa_csf_save', 'data' => wp_json_encode( $qaFrameworkPayload ) );
    foreach ( array( true, false ) as $qaSettingsAnonymous ) {
        $qaFrameworkDenied = $qaFrameworkPayload;
        $qaFrameworkDenied['csf_options_nonce' . $qaSettingsKey] = qa_http( null, $qaSettingsAnonymous )['data']['settings_form_nonce'];
        qa_check( false === qa_http( array( 'action' => 'qa_csf_save', 'data' => wp_json_encode( $qaFrameworkDenied ) ), $qaSettingsAnonymous )['data']['success'], 'Anonymous or artist framework form save was authorized.' );
        qa_check( $qaSettingsRead() === maybe_serialize( $qaPreviousSettings ), 'Denied framework save changed previous values.' );
    }
    $wpdb->query( "CREATE TRIGGER qa_reject_settings_update BEFORE UPDATE ON {$wpdb->options} FOR EACH ROW BEGIN IF NEW.option_name='qa_csf_fixture' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA settings update failure'; END IF; END" );
    try {
        qa_check( false === qa_http( $qaFrameworkSave, false, true )['data']['success'], 'Framework form confirmed a real MySQL write failure.' );
        qa_check( $qaSettingsRead() === maybe_serialize( $qaPreviousSettings ), 'Failed framework form save lost previous values.' );
    } finally { $wpdb->query( 'DROP TRIGGER qa_reject_settings_update' ); }
    qa_check( true === qa_http( $qaFrameworkSave, false, true )['data']['success'], 'Framework form save could not retry after recovery.' );
    qa_check( $qaSettingsRead() === maybe_serialize( array( 'fixture' => 'updated' ) ), 'Successful framework form save did not persist exact data.' );
    qa_check( true === qa_http( $qaFrameworkSave, false, true )['data']['success'], 'An identical verified framework form save was rejected.' );
    qa_check( false === qa_http( array( 'action' => 'qa_csf_save', 'data[0]' => 'nested' ), false, true )['data']['success'], 'Malformed framework form data was accepted or caused a fatal response.' );
} finally {
    $wpdb->query( 'DROP TRIGGER IF EXISTS qa_reject_settings_update' );
    $wpdb->query( 'DROP TRIGGER IF EXISTS qa_reject_settings_delete' );
    wp_cache_delete( $qaSettingsKey, 'options' );
    delete_option( $qaSettingsKey );
}
