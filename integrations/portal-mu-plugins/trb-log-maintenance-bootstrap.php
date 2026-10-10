<?php
/** Keep private log retention available independently of the full theme release. */
if ( ! defined( 'ABSPATH' ) ) exit;
$trb_log_helper = WP_CONTENT_DIR . '/themes/docy/inc/trb-log-maintenance.php';
if ( ! is_link( $trb_log_helper ) && is_file( $trb_log_helper ) ) require_once $trb_log_helper;
unset( $trb_log_helper );
