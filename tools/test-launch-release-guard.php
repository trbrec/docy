<?php
/** The independent guard also protects a legacy theme without receipt support. */
define( 'ABSPATH', __DIR__ );
function add_action( $hook, $callback, ...$args ) { $GLOBALS['hooks'][$hook] = $callback; }
function remove_action( $hook, $callback, $priority = 10 ) { $GLOBALS['removed'][$hook] = array( $callback, $priority ); }
require __DIR__ . '/../integrations/portal-mu-plugins/trb-launch-release-guard.php';
function launch_hooks_blocked() {
    $GLOBALS['removed'] = array(); $GLOBALS['hooks']['after_setup_theme']();
    if ( ( $GLOBALS['removed']['init'] ?? null ) !== array( 'trb_portal_schedule_launch_campaign', 45 ) || ( $GLOBALS['removed']['trb_portal_send_launch_campaign_batch'] ?? null ) !== array( 'trb_portal_send_launch_campaign_batch', 10 ) ) throw new RuntimeException( 'Unverified launch announcement remains active.' );
}
launch_hooks_blocked();
if ( true ) { function trb_docy_has_completed_release() { return $GLOBALS['ready']; } }
$GLOBALS['ready'] = false; launch_hooks_blocked();
$GLOBALS['ready'] = true; $GLOBALS['removed'] = array(); $GLOBALS['hooks']['after_setup_theme']();
if ( $GLOBALS['removed'] ) throw new RuntimeException( 'Verified launch announcement was disabled.' );
echo "Independent launch guard protects legacy and pending themes, and preserves a verified release.\n";
