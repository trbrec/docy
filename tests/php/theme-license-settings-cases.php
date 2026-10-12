<?php
/** Real WordPress/MySQL option writes through the theme's registered sanitizer. */
defined( 'ABSPATH' ) || exit;
qa_check( false === get_option( 'docy_purchase_code' ), 'The isolated fixture must not contain an existing license.' );
require_once dirname( __DIR__, 2 ) . '/inc/classes/Docy_register_theme.php';
$qaLicenseSettings = new Docy_register_theme( array( 'version' => 'qa-only' ) );
$qaLicenseSettings->register_option();
try {
    qa_check( is_callable( $GLOBALS['wp_registered_settings']['docy_purchase_code']['sanitize_callback'] ), 'WordPress silently omitted the theme license sanitizer.' );
    foreach ( array( '  QA-ONLY-1234  ', '<b>QA-ONLY-1234</b>', "QA-ONLY-1234\n" ) as $qaLicenseInput ) {
        update_option( 'docy_purchase_code', $qaLicenseInput, false );
        wp_cache_delete( 'docy_purchase_code', 'options' );
        qa_check( 'QA-ONLY-1234' === get_option( 'docy_purchase_code' ), 'Native option readback retained unsanitized license input.' );
        qa_check( 'QA-ONLY-1234' === $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'docy_purchase_code' ) ), 'The actual MySQL license value was not sanitized.' );
    }
} finally {
    delete_option( 'docy_purchase_code' );
    unregister_setting( 'docy-license', 'docy_purchase_code' );
    unregister_setting( 'docy-license', 'docy_register_email' );
}
qa_check( false === get_option( 'docy_purchase_code' ), 'The synthetic license fixture was not removed.' );
