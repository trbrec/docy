<?php
/** Campaign release gate and real file-lock contention; no actual mail or network. */
$root = sys_get_temp_dir() . '/trb-launch-qa-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/public_html/theme', 0700, true );
define( 'ABSPATH', $root . '/public_html/' );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_User { public function __construct( public $ID, public $user_email ) {} }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function get_template_directory() { return ABSPATH . 'theme'; }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function untrailingslashit( $value ) { return rtrim( $value, '/\\' ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $value, ...$args ) { $GLOBALS['options'][$key] = $value; }
function get_user_meta( $id, $key, ...$args ) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['meta'][$id][$key] = $value; }
function wp_next_scheduled( $hook ) { return $GLOBALS['events'][$hook] ?? false; }
function wp_schedule_single_event( $at, $hook ) { $GLOBALS['events'][$hook] = $at; }
function get_users( $args ) { $GLOBALS['queries'][] = $args; return $GLOBALS['users']; }
function trb_portal_profiles() { return array( array( 'role' => 'qa_artist', 'aliases' => array( 'legacy_qa_artist' ) ) ); }
function trb_resource_artist_legal_greeting_name( $user ) { return 'Artista Fittizio'; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return $value; }
function home_url( $path ) { return 'https://example.invalid' . $path; }
function wp_date( $format ) { return date( $format ); }
function current_time( $format ) { return '2026-10-10 20:00:00'; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['root'] . '/uploads' ); }
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0700, true ); }
function wp_mail( $to, $subject, $body, $headers ) {
    $GLOBALS['mail'][] = compact( 'to', 'subject', 'body', 'headers' );
    if ( $GLOBALS['during_mail'] ) ( $GLOBALS['during_mail'] )();
    return $GLOBALS['mail_result'];
}
require __DIR__ . '/../inc/trb-auto-deploy.php';
require __DIR__ . '/../inc/trb-release-integrity.php';
require __DIR__ . '/../inc/trb-portal-launch-campaign.php';
function launch_check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function launch_pending() {
    trb_portal_schedule_launch_campaign(); trb_portal_send_launch_campaign_batch();
    launch_check( ! $GLOBALS['events'] && ! $GLOBALS['mail'] && ! $GLOBALS['meta'] && ! $GLOBALS['queries'] && ! $GLOBALS['options'], 'Pending release changed campaign state.' );
}
$GLOBALS['options'] = $GLOBALS['events'] = $GLOBALS['mail'] = $GLOBALS['meta'] = $GLOBALS['queries'] = array();
$GLOBALS['mail_result'] = true; $GLOBALS['during_mail'] = null;
$GLOBALS['users'] = array( new WP_User( 1, 'qa-one@example.invalid' ), new WP_User( 2, 'qa-two@example.invalid' ) );
$sha = str_repeat( 'a', 40 ); $marker = ABSPATH . 'theme/.trb-deployed-sha';
$directory = $root . '/private/portal-release-' . $sha; mkdir( $directory, 0700, true );
$receipt = $directory . '/complete.json';
$verified = json_encode( array( 'revision' => $sha, 'verified' => true ) );
try {
    launch_pending();
    file_put_contents( $marker, $sha ); launch_pending();
    foreach ( array( '{broken', json_encode( array( 'revision' => $sha, 'verified' => 1 ) ), json_encode( array( 'revision' => str_repeat( 'b', 40 ), 'verified' => true ) ), str_repeat( ' ', 16385 ) ) as $bytes ) {
        file_put_contents( $receipt, $bytes ); clearstatcache(); launch_pending();
    }
    file_put_contents( $receipt, $verified ); file_put_contents( $marker, '../outside' ); clearstatcache(); launch_pending();
    file_put_contents( $marker, $sha ); clearstatcache();
    launch_check( trb_docy_has_completed_release( $sha ) && ! trb_docy_has_completed_release( str_repeat( 'b', 40 ) ), 'Receipt did not match the installed revision.' );
    if ( PHP_OS_FAMILY !== 'Windows' ) {
        rename( $receipt, $receipt . '.owned' ); symlink( $receipt . '.owned', $receipt ); clearstatcache(); launch_pending();
        unlink( $receipt ); rename( $receipt . '.owned', $receipt );
        rename( $marker, $marker . '.owned' ); symlink( $marker . '.owned', $marker ); clearstatcache(); launch_pending();
        unlink( $marker ); rename( $marker . '.owned', $marker ); clearstatcache();
    }
    trb_portal_schedule_launch_campaign(); $event = $GLOBALS['events']; trb_portal_schedule_launch_campaign();
    launch_check( $event === $GLOBALS['events'] && isset( $event[TRB_PORTAL_LAUNCH_HOOK] ), 'Campaign scheduling duplicated.' );
    $GLOBALS['events'] = array();
    $lock = trb_release_process_lock( 'portal-launch:' . TRB_PORTAL_LAUNCH_CAMPAIGN );
    try { trb_portal_send_launch_campaign_batch(); launch_check( ! $GLOBALS['mail'] && ! $GLOBALS['meta'], 'Concurrent worker sent mail.' ); }
    finally { trb_release_process_unlock( $lock ); }
    $GLOBALS['during_mail'] = static function() { unlink( $GLOBALS['receipt'] ); clearstatcache(); };
    trb_portal_send_launch_campaign_batch();
    launch_check( count( $GLOBALS['mail'] ) === 1 && ! isset( $GLOBALS['meta'][2] ), 'Campaign continued after its release receipt disappeared.' );
    file_put_contents( $receipt, $verified ); clearstatcache();
    $GLOBALS['during_mail'] = static function() { trb_portal_send_launch_campaign_batch(); };
    trb_portal_send_launch_campaign_batch(); $GLOBALS['during_mail'] = null;
    launch_check( count( $GLOBALS['mail'] ) === 2 && ( $GLOBALS['meta'][2]['_trb_portal_launch_campaign_attempts'] ?? 0 ) === 1, 'Reentrant worker duplicated delivery or attempts.' );
    launch_check( $GLOBALS['queries'][0]['role__in'] === array( 'qa_artist', 'legacy_qa_artist' ), 'Legacy artist role omitted.' );
    trb_portal_send_launch_campaign_batch();
    launch_check( get_option( 'trb_portal_launch_campaign_completed' ) === TRB_PORTAL_LAUNCH_CAMPAIGN && count( $GLOBALS['mail'] ) === 2, 'Delivered recipients were sent again.' );
    $GLOBALS['options'] = $GLOBALS['meta'] = array(); $GLOBALS['users'] = array( new WP_User( 3, 'qa-three@example.invalid' ) ); $GLOBALS['mail_result'] = false;
    trb_portal_send_launch_campaign_batch();
    launch_check( ( $GLOBALS['meta'][3]['_trb_portal_launch_campaign_attempts'] ?? 0 ) === 1 && ! isset( $GLOBALS['meta'][3]['_trb_portal_launch_campaign_sent'] ), 'Failed mail recorded as delivered.' );
    launch_check( isset( $GLOBALS['events'][TRB_PORTAL_LAUNCH_HOOK] ), 'Failed recipient lost its retry.' );
    echo "Campaign waits for the installed verified release; real lock contention, mid-batch rollback, idempotency and mail failure passed.\n";
} finally {
    if ( dirname( realpath( $root ) ?: '' ) !== realpath( sys_get_temp_dir() ) || ! preg_match( '/^trb-launch-qa-[a-f0-9]{16}$/D', basename( $root ) ) ) throw new RuntimeException( 'Fixture cleanup path mismatch.' );
    $remove = static function( $path ) use ( &$remove ) { if ( is_dir( $path ) && ! is_link( $path ) ) { foreach ( scandir( $path ) as $name ) if ( $name !== '.' && $name !== '..' ) $remove( $path . '/' . $name ); rmdir( $path ); } elseif ( file_exists( $path ) || is_link( $path ) ) unlink( $path ); };
    $remove( $root );
}
