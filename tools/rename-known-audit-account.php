<?php
/** Italian display labels for the one explicitly identified fictional account. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
$buffer = ob_get_level(); ob_start();
$result = array( 'completed' => false, 'artist_messages' => 0, 'accounts_changed' => 0 );
register_shutdown_function( static function() use ( &$result, $buffer ) { while ( ob_get_level() > $buffer ) ob_end_clean(); echo json_encode( $result ) . "\n"; } );
set_exception_handler( static function() { exit( 1 ); } );
$revision = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || isset( $argv[2] ) || trim( (string) @file_get_contents( dirname( __DIR__ ) . '/.trb-deployed-sha' ) ) !== $revision ) exit( 2 );
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
$user = get_user_by( 'login', 'trb_audit_20261010' );
if ( ! $user ) { $result['completed'] = true; $result['account_present'] = false; exit; }
if ( $user->user_login !== 'trb_audit_20261010' || ! str_starts_with( $user->user_registered, '2026-10-10 ' ) || user_can( $user, 'manage_options' ) ) throw new RuntimeException( 'Fictional account guard.' );
// Only this display correction: do not send mail or enqueue profile sync jobs.
foreach ( array( 'profile_update', 'added_user_meta', 'updated_user_meta', 'deleted_user_meta' ) as $hook ) remove_all_actions( $hook );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
if ( ! function_exists( 'trb_crm_sync_atomic' ) ) require dirname( __DIR__ ) . '/integrations/portal-mu-plugins/trb-crm-sync-storage.php';
$saved = trb_crm_sync_atomic( 'audit-display-' . $user->ID, array( 'user' => array( $user->ID ) ), static function() use ( $user ) {
    global $wpdb;
    $name = 'Artista Fittizio Tunisia';
    $updated = wp_update_user( array( 'ID' => $user->ID, 'display_name' => $name ) );
    if ( is_wp_error( $updated ) || $wpdb->get_var( $wpdb->prepare( "SELECT display_name FROM {$wpdb->users} WHERE ID=%d", $user->ID ) ) !== $name ) throw new RuntimeException( 'Fictional display readback failed.' );
    foreach ( array( 'first_name' => 'Artista', 'last_name' => 'Fittizio', 'nickname' => $name, '_trb_artist_first_name' => 'Artista', '_trb_artist_last_name' => 'Fittizio', '_trb_artist_artist_name' => $name ) as $key => $value ) trb_crm_sync_write_meta( 'user', $user->ID, $key, $value );
    foreach ( array( '_trb_artist_city', '_trb_artist_birth_place' ) as $key ) if ( in_array( get_user_meta( $user->ID, $key, true ), array( 'Tunis', 'تونس' ), true ) ) trb_crm_sync_write_meta( 'user', $user->ID, $key, 'Tunisi' );
    return true;
} );
if ( is_wp_error( $saved ) || $saved !== true ) throw new RuntimeException( 'Fictional display update unconfirmed.' );
$result['completed'] = true; $result['account_present'] = true; $result['accounts_changed'] = 1;
