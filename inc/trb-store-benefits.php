<?php
/** Private, purpose-bound Store entitlement bridge. No public artist directory. */
if ( ! defined( 'ABSPATH' ) ) exit;

function trb_store_benefits_live() {
    return (bool) get_option( 'trb_store_benefits_live', false ) && strlen( trb_portal_dds_store_secret() ) >= 32;
}
function trb_store_benefits_eligible( $user ) {
    if ( ! $user || ! $user->exists() || ! in_array( trb_portal_user_profile( $user ), array( 'dds', 'ddb12', 'ddb', 'ddb_trb' ), true ) ) return false;
    if ( function_exists( 'pw_new_user_approve' ) && 'approved' !== pw_new_user_approve()->get_user_status( $user->ID ) ) return false;
    if ( function_exists( 'trb_release_bridge_is_contract_access_expired' ) && trb_release_bridge_is_contract_access_expired( $user->ID ) ) return false;
    return true;
}
function trb_store_benefits_permission( $request ) {
    $secret = trb_portal_dds_store_secret();
    $timestamp = (string) $request->get_header( 'x-trb-timestamp' );
    $signature = (string) $request->get_header( 'x-trb-signature' );
    $nonce = (string) $request->get_header( 'x-trb-nonce' );
    if ( strlen( $secret ) < 32 || ! preg_match( '/^[0-9]+$/D', $timestamp ) || abs( time() - (int) $timestamp ) > 120 || ! preg_match( '/^[a-f0-9]{32}$/D', $nonce ) || ! preg_match( '/^[a-f0-9]{64}$/D', $signature ) ) return new WP_Error( 'benefit_auth', 'Richiesta non autorizzata.', array( 'status' => 401 ) );
    $expected = hash_hmac( 'sha256', 'artist-benefits-v1.' . $timestamp . '.' . $nonce . '.' . $request->get_body(), $secret );
    if ( ! hash_equals( $expected, $signature ) ) return new WP_Error( 'benefit_auth', 'Richiesta non autorizzata.', array( 'status' => 401 ) );
    // Atomic insertion prevents concurrent replay; scheduled cleanup bounds storage.
    if ( ! add_option( 'trb_benefit_nonce_' . $nonce, time(), '', false ) ) return new WP_Error( 'benefit_replay', 'Richiesta già utilizzata.', array( 'status' => 409 ) );
    if ( ! wp_next_scheduled( 'trb_benefit_nonce_cleanup' ) ) wp_schedule_single_event( time() + 300, 'trb_benefit_nonce_cleanup' );
    return true;
}
add_action( 'trb_benefit_nonce_cleanup', function() {
    global $wpdb;
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d", $wpdb->esc_like( 'trb_benefit_nonce_' ) . '%', time() - 240 ) );
} );
function trb_store_benefits_endpoint( $request ) {
    $data = $request->get_json_params();
    if ( ! is_array( $data ) ) return new WP_Error( 'benefit_body', 'Richiesta non valida.', array( 'status' => 400 ) );
    if ( 'activate' === ( $data['action'] ?? '' ) ) update_option( 'trb_store_benefits_live', true, false );
    if ( 'deactivate' === ( $data['action'] ?? '' ) ) update_option( 'trb_store_benefits_live', false, false );
    $email = strtolower( trim( (string) ( $data['email'] ?? '' ) ) );
    $user = is_email( $email ) ? get_user_by( 'email', $email ) : false;
    $response = new WP_REST_Response( array(
        'protocol' => 'artist-benefits-v1', 'email_hash' => hash( 'sha256', $email ),
        'eligible' => trb_store_benefits_eligible( $user ),
        'included' => $user && 'trb' === trb_portal_user_profile( $user ),
        'discount_percent' => trb_store_benefits_eligible( $user ) ? 50 : 0,
        'live' => trb_store_benefits_live(),
    ) );
    $response->header( 'Cache-Control', 'no-store, private' );
    return $response;
}
add_action( 'rest_api_init', function() {
    register_rest_route( 'trb/v1', '/artist-benefits', array( 'methods' => 'POST', 'permission_callback' => 'trb_store_benefits_permission', 'callback' => 'trb_store_benefits_endpoint' ) );
} );
add_filter( 'rest_authentication_errors', function( $result ) {
    $path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
    return '/wp-json/trb/v1/artist-benefits' === untrailingslashit( (string) $path ) ? null : $result;
}, PHP_INT_MAX );
function trb_store_benefits_panel( $user ) {
    if ( ! trb_store_benefits_live() || ! trb_store_benefits_eligible( $user ) ) return;
    echo '<aside class="trb-portal__store-benefits" aria-labelledby="trb-benefits-title"><div><h2 id="trb-benefits-title">Le tue condizioni riservate</h2><p>Come nostro artista hai diritto al <strong>50% di sconto su qualsiasi servizio dello Store</strong>. Registrati o accedi con <strong>' . esc_html( $user->user_email ) . '</strong> e conferma l’indirizzo email: lo sconto si applica automaticamente al carrello, senza codici.</p></div><a href="https://store.trbrec.com/?trb_artist_account=1">Accedi allo Store →</a></aside>';
}
