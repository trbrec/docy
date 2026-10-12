<?php
/** Keep an old theme from bypassing the reviewed SSH release or its rollback. */
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'after_setup_theme', static function() {
    if ( ! function_exists( 'trb_docy_deployment_is_ssh_only' ) ) remove_action( 'trb_docy_auto_deploy', 'trb_docy_run_deploy_safety_net' );
}, PHP_INT_MAX );
add_filter( 'rest_pre_dispatch', static function( $result, $server, $request ) {
    if ( $request->get_route() === '/trb/v1/deploy' && ! function_exists( 'trb_docy_deployment_is_ssh_only' ) ) return new WP_Error( 'trb_ssh_release_required', 'La pubblicazione richiede il rilascio verificato dal server.', array( 'status' => 409 ) );
    return $result;
}, -PHP_INT_MAX, 3 );
