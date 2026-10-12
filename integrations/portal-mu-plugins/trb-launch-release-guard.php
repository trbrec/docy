<?php
/** Keep announcements paused until the full audit, independently of deployment. */
if ( ! defined( 'ABSPATH' ) ) exit;
function trb_portal_guard_launch_hooks() {
    if ( function_exists( 'trb_portal_launch_campaign_is_ready' ) && trb_portal_launch_campaign_is_ready() ) return;
    remove_action( 'init', 'trb_portal_schedule_launch_campaign', 45 );
    remove_action( 'trb_portal_send_launch_campaign_batch', 'trb_portal_send_launch_campaign_batch' );
}
add_action( 'after_setup_theme', 'trb_portal_guard_launch_hooks', PHP_INT_MAX );
