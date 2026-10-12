<?php
/** Keep the launch announcement paused across an incomplete release or rollback. */
if ( ! defined( 'ABSPATH' ) ) exit;
function trb_portal_guard_launch_hooks() {
    if ( function_exists( 'trb_docy_has_completed_release' ) && trb_docy_has_completed_release() ) return;
    remove_action( 'init', 'trb_portal_schedule_launch_campaign', 45 );
    remove_action( 'trb_portal_send_launch_campaign_batch', 'trb_portal_send_launch_campaign_batch' );
}
add_action( 'after_setup_theme', 'trb_portal_guard_launch_hooks', PHP_INT_MAX );
