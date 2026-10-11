<?php
/** Native option cache and atomic replacement, exclusively with synthetic keys. */
defined( 'ABSPATH' ) || exit;
define( 'TRB_DEMO_KEY_LIBRARY_ONLY', true ); require dirname( __DIR__, 2 ) . '/tools/demo-key-alignment.php';
$qa_demo_option = 'trb_demo_automation_settings';
$qa_demo_initial = array( 'sheet_webhook_secret' => str_repeat( 'a', 64 ), 'sheet_webhook_url' => 'https://script.google.com/macros/s/fixture/exec', 'other_service' => 'preserve synthetic service' );
$qa_demo_plan = array( 'previous' => $qa_demo_initial['sheet_webhook_secret'], 'next' => str_repeat( 'b', 64 ), 'url' => $qa_demo_initial['sheet_webhook_url'] );
try {
    add_option( $qa_demo_option, $qa_demo_initial, '', true ); get_option( $qa_demo_option ); trb_demo_key_cas( $qa_demo_plan );
    qa_woo_check( get_option( $qa_demo_option ) === array_replace( $qa_demo_initial, array( 'sheet_webhook_secret' => $qa_demo_plan['next'] ) ), 'Key replacement must invalidate the autoloaded cache and preserve all other settings.' );
    trb_demo_key_cas( $qa_demo_plan ); qa_woo_check( get_option( $qa_demo_option )['sheet_webhook_secret'] === $qa_demo_plan['next'], 'Repeated key replacement must be idempotent.' );
    $qa_demo_concurrent = get_option( $qa_demo_option ); $qa_demo_concurrent['other_service'] = 'changed independently'; update_option( $qa_demo_option, $qa_demo_concurrent ); trb_demo_key_cas( $qa_demo_plan, true );
    qa_woo_check( get_option( $qa_demo_option ) === array_replace( $qa_demo_initial, array( 'other_service' => 'changed independently' ) ), 'Rollback must retain concurrent unrelated settings.' );
    $qa_demo_concurrent['sheet_webhook_secret'] = str_repeat( 'c', 64 ); update_option( $qa_demo_option, $qa_demo_concurrent );
    try { trb_demo_key_cas( $qa_demo_plan ); throw new LogicException( 'Concurrent credential replaced.' ); } catch ( RuntimeException $expected ) {}
    qa_woo_check( get_option( $qa_demo_option ) === $qa_demo_concurrent, 'Another credential change must remain untouched.' );
    update_option( $qa_demo_option, $qa_demo_initial );
    $wpdb->query( "CREATE TRIGGER qa_demo_key_reject BEFORE UPDATE ON {$wpdb->options} FOR EACH ROW BEGIN IF OLD.option_name='trb_demo_automation_settings' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic key update failure'; END IF; END" );
    $qa_demo_errors = $wpdb->suppress_errors( true );
    try { try { trb_demo_key_cas( $qa_demo_plan ); throw new LogicException( 'Failed key update confirmed.' ); } catch ( RuntimeException $expected ) {} }
    finally { $wpdb->suppress_errors( $qa_demo_errors ); $wpdb->query( 'DROP TRIGGER qa_demo_key_reject' ); }
    qa_woo_check( get_option( $qa_demo_option ) === $qa_demo_initial, 'Failed native update must preserve the prior credential and settings.' );
} finally { delete_option( $qa_demo_option ); }
