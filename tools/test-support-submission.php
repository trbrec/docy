<?php
/** Exercise the actual support handler with isolated storage/mail failures. */
error_reporting( E_ALL );
set_error_handler( static function( $severity, $message ) { throw new RuntimeException( $message ); } );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_Error {}
class SupportRedirect extends RuntimeException {}
function check_admin_referer( $action, $name ) { $GLOBALS['nonce_checked'] = array( $action, $name ); }
function wp_get_current_user() { return (object) array( 'ID' => 198, 'first_name' => 'Artista', 'last_name' => 'Fittizio', 'user_email' => 'audit@example.invalid' ); }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_textarea_field( $value ) { return sanitize_text_field( $value ); }
function sanitize_email( $value ) { return sanitize_text_field( $value ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function home_url( $path ) { return 'https://portal.example.invalid' . $path; }
function add_query_arg( $name, $value, $url ) { return $url . '?' . $name . '=' . rawurlencode( $value ); }
function wp_safe_redirect( $url ) { throw new SupportRedirect( $url ); }
function get_transient( $key ) { return $GLOBALS['rate_limited']; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][] = array( $key, $value, $ttl ); }
function trb_portal_user_profile( $user ) { return 'trb'; }
function wp_insert_post( $post, $return_error = false ) { $GLOBALS['posts'][] = array( $post, $return_error ); return $GLOBALS['insert_result']; }
function wp_mail( ...$args ) { $GLOBALS['mail'][] = $args; return $GLOBALS['mail_result']; }

$tokens = token_get_all( file_get_contents( __DIR__ . '/../inc/trb-artist-portal.php' ) );
foreach ( $tokens as $i => $token ) {
    if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) continue;
    $j = $i + 1;
    while ( isset( $tokens[$j] ) && is_array( $tokens[$j] ) && T_WHITESPACE === $tokens[$j][0] ) $j++;
    if ( ! isset( $tokens[$j] ) || ! is_array( $tokens[$j] ) || 'trb_portal_submit_support_request' !== $tokens[$j][1] ) continue;
    $body = ''; $depth = 0; $opened = false;
    for ( $k = $i; $k < count( $tokens ); $k++ ) {
        $part = $tokens[$k]; $body .= is_array( $part ) ? $part[1] : $part;
        if ( '{' === $part || ( is_array( $part ) && in_array( $part[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) { $depth++; $opened = true; }
        if ( '}' === $part && --$depth === 0 && $opened ) break;
    }
    eval( $body );
    break;
}
function check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); $GLOBALS['checks']++; }
function fixture() {
    $_POST = array( 'trb_support_name' => 'Artista Fittizio', 'trb_support_email' => 'audit@example.invalid', 'trb_support_artist_name' => 'AUDIT TEST', 'trb_support_type' => 'problema', 'trb_support_subject' => 'Synthetic audit request', 'trb_support_message' => 'Synthetic content: no delivery.', 'trb_support_started' => time() - 10 );
    $_SERVER['REMOTE_ADDR'] = '192.0.2.198';
    $GLOBALS['logged_in'] = false; $GLOBALS['rate_limited'] = false; $GLOBALS['insert_result'] = 12351; $GLOBALS['mail_result'] = true;
    $GLOBALS['posts'] = array(); $GLOBALS['mail'] = array(); $GLOBALS['transients'] = array(); $GLOBALS['nonce_checked'] = array();
}
function submit() {
    try { trb_portal_submit_support_request(); } catch ( SupportRedirect $redirect ) {
        parse_str( parse_url( $redirect->getMessage(), PHP_URL_QUERY ), $query );
        return $query['trb_support'];
    }
    throw new RuntimeException( 'Support handler did not redirect' );
}
$GLOBALS['checks'] = 0;
foreach ( array( 0, new WP_Error() ) as $failure ) {
    fixture(); $GLOBALS['insert_result'] = $failure;
    check( 'storage_failed' === submit(), 'Failed storage was reported as a sent request' );
    check( ! $GLOBALS['mail'] && ! $GLOBALS['transients'], 'Failed storage sent email or prevented a retry' );
    check( true === $GLOBALS['posts'][0][1], 'WordPress storage error was not requested' );
}
fixture(); $GLOBALS['mail_result'] = false;
check( 'notification_failed' === submit(), 'Failed email notification was reported as sent' );
check( count( $GLOBALS['posts'] ) === 1 && count( $GLOBALS['transients'] ) === 1, 'Stored request was lost or allowed an immediate duplicate' );
fixture(); check( 'sent' === submit(), 'Successful submission was rejected' );
check( 'private' === $GLOBALS['posts'][0][0]['post_status'] && 0 === $GLOBALS['posts'][0][0]['post_author'], 'Guest request visibility or attribution changed' );
check( $GLOBALS['nonce_checked'] === array( 'trb_portal_submit_support', 'trb_support_nonce' ), 'Nonce check missing' );
fixture(); $GLOBALS['logged_in'] = true;
check( 'sent' === submit() && 198 === $GLOBALS['posts'][0][0]['post_author'], 'Authenticated author lost' );
foreach ( array( 'trb_support_message' => '', 'trb_support_website' => 'spam', 'trb_support_started' => time() ) as $key => $value ) {
    fixture(); $_POST[$key] = $value;
    check( 'invalid' === submit() && ! $GLOBALS['posts'] && ! $GLOBALS['mail'], 'Invalid request reached storage or mail' );
}
fixture(); $GLOBALS['rate_limited'] = true;
check( 'rate_limited' === submit() && ! $GLOBALS['posts'] && ! $GLOBALS['mail'], 'Rate-limited request reached storage or mail' );
echo $GLOBALS['checks'] . " support submission assertions passed.\n";
