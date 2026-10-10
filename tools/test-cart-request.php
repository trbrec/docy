<?php
/** Request-boundary regressions; no actual cart, customer, order or payment is created. */
define( 'ABSPATH', __DIR__ . '/' );
function get_option( ...$args ) { return array(); }
function add_action( ...$args ) {} function add_filter( ...$args ) {} function remove_action( ...$args ) {} function add_theme_support( ...$args ) {}
function esc_html__( $text, $domain = '' ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_verify_nonce( $value, $action ) { return 'fixture-valid' === $value && 'docy-buy-now-nonce' === $action; }
function wc_clean( $value ) { return $value; }
function WC() { return $GLOBALS['fixture_wc']; }
class CartFixtureResponse extends RuntimeException { public function __construct( public bool $success ) { parent::__construct( 'Synthetic response' ); } }
function wp_send_json_error( ...$args ) { throw new CartFixtureResponse( false ); }
function wp_send_json_success( ...$args ) { throw new CartFixtureResponse( true ); }
class CartFixture {
    public function add_to_cart( ...$args ) { $GLOBALS['fixture_cart_calls'][] = $args; return $GLOBALS['fixture_cart_result']; }
}
require dirname( __DIR__ ) . '/inc/woo_config.php';
set_error_handler( static function( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$base = array( 'nonce' => 'fixture-valid', 'product_id' => '123' );
$GLOBALS['fixture_wc'] = (object) array( 'cart' => new CartFixture() );
$GLOBALS['fixture_cart_result'] = 'cart-key';
$checks = 0;
$send = static function( $post ) {
    $_POST = $post;
    try { docy_buy_now_add_to_cart(); } catch ( CartFixtureResponse $response ) { return $response->success; }
    throw new RuntimeException( 'Missing cart response.' );
};
foreach ( array( array( 'nonce' => array() ), array( 'nonce' => 'invalid' ), array( 'product_id' => array( '123' ) ), array( 'product_id' => '0' ), array( 'quantity' => '-2' ), array( 'quantity' => '0' ), array( 'quantity' => '1.5' ), array( 'quantity' => array() ), array( 'variation_id' => '-3' ), array( 'variation_id' => array() ), array( 'variation' => 'not-an-array' ), array( 'variation' => array( 'attribute_size' => array() ) ), array( 'variation' => array( 'M' ) ), array( 'quantity' => str_repeat( '9', 40 ) ) ) as $invalid ) {
    $GLOBALS['fixture_cart_calls'] = array();
    if ( $send( array_replace( $base, $invalid ) ) || $GLOBALS['fixture_cart_calls'] ) throw new RuntimeException( 'Malformed cart input reached the cart service.' );
    ++$checks;
}
foreach ( array( null, (object) array(), (object) array( 'cart' => new stdClass() ) ) as $unavailable ) {
    $GLOBALS['fixture_wc'] = $unavailable;
    if ( $send( $base ) ) throw new RuntimeException( 'Unavailable cart reported success.' );
    ++$checks;
}
$GLOBALS['fixture_wc'] = (object) array( 'cart' => new CartFixture() );
foreach ( array( false, 0, array( 'invalid receipt' ), new stdClass(), 'verified-cart-key' ) as $receipt ) {
    $GLOBALS['fixture_cart_result'] = $receipt;
    $GLOBALS['fixture_cart_calls'] = array();
    if ( ( 'verified-cart-key' === $receipt ) !== $send( $base ) || array( array( 123, 1 ) ) !== $GLOBALS['fixture_cart_calls'] ) throw new RuntimeException( 'Cart service failure or default arguments were misreported.' );
    ++$checks;
}
$GLOBALS['fixture_cart_result'] = 'verified-variation-key';
$GLOBALS['fixture_cart_calls'] = array();
if ( ! $send( $base + array( 'quantity' => '2', 'variation_id' => '456', 'variation' => array( 'attribute_size' => 'M' ) ) ) || array( array( 123, 2, 456, array( 'attribute_size' => 'M' ) ) ) !== $GLOBALS['fixture_cart_calls'] ) throw new RuntimeException( 'A valid variation request changed its arguments.' );
++ $checks;
restore_error_handler();
echo "Cart request boundary: {$checks} malformed-input, availability, service-receipt and variation assertions passed. No real checkout or payment exercised.\n";
