<?php
/** Theme boundary tests; the bbPress permission service is a test double. */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', __DIR__ . '/' );
function add_action() {}
require dirname( __DIR__ ) . '/inc/ajax_actions.php';
$checks = 0;
function forum_check( $condition, $message ) {
    if ( ! $condition ) throw new RuntimeException( $message );
    $GLOBALS['checks']++;
}
foreach ( array( 'forum', 'topic', 'reply' ) as $type ) forum_check( ! docy_forum_result_is_readable( $type, 10 ), 'Forum content was exposed without its permission service.' );
forum_check( docy_forum_result_is_readable( 'post', 10 ), 'An ordinary post incorrectly required bbPress.' );
function forum_enable_permission_fixture() {
    function bbp_get_forum_post_type() { return 'custom_forum'; }
    function bbp_get_topic_post_type() { return 'custom_topic'; }
    function bbp_get_reply_post_type() { return 'custom_reply'; }
    function bbp_get_topic_forum_id( $id ) { return array( 11 => 1, 12 => 2 )[$id] ?? 0; }
    function bbp_get_reply_forum_id( $id ) { return array( 21 => 1, 22 => 2 )[$id] ?? 0; }
    function bbp_user_can_view_forum( $args ) {
        $GLOBALS['permission_requests'][] = $args;
        return 1 === $args['forum_id'] || ! empty( $GLOBALS['fixture_membership'] );
    }
}
forum_enable_permission_fixture();
foreach ( array( array( 'custom_forum', 1, true ), array( 'custom_forum', 2, false ), array( 'custom_topic', 11, true ), array( 'custom_topic', 12, false ), array( 'custom_reply', 21, true ), array( 'custom_reply', 22, false ), array( 'custom_topic', 99, false ), array( 'custom_reply', 99, false ), array( 'post', 10, true ) ) as [ $type, $id, $allowed ] ) {
    forum_check( $allowed === docy_forum_result_is_readable( $type, $id ), 'Forum membership or a missing parent was ignored.' );
}
foreach ( $GLOBALS['permission_requests'] as $request ) forum_check( true === $request['check_ancestors'], 'Forum ancestor checks were disabled.' );
ob_start();
docy_search_result_html( 'custom_topic', 12 );
forum_check( '' === ob_get_clean(), 'A denied topic leaked search markup.' );
class WP_Post { public $ID = 12; public $post_type = 'custom_topic'; }
$GLOBALS['post'] = new WP_Post();
ob_start();
docy_render_forum_topic_card();
forum_check( '' === ob_get_clean(), 'A denied topic leaked its card or queried display data.' );
$GLOBALS['fixture_membership'] = true;
forum_check( docy_forum_result_is_readable( 'custom_topic', 12 ), 'Theme code ignored an allowed member returned by bbPress.' );
echo "$checks forum visibility boundary assertions passed; no real bbPress integration is claimed.\n";
