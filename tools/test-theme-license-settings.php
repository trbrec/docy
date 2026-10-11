<?php
/** Native WordPress settings registration; theme metadata only is a test double. */
$wp_root = getenv( 'TRB_WP_TEST_ROOT' ) ?: dirname( __DIR__, 2 ) . '/.audit-runtime/wp/wordpress';
define( 'ABSPATH', rtrim( $wp_root, '/\\' ) . '/' );
define( 'WPINC', 'wp-includes' );
require ABSPATH . WPINC . '/compat-utf8.php';
require ABSPATH . WPINC . '/compat.php';
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/functions.php';
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/utf8.php';
function wp_get_theme( $slug = '' ) { return new class { public function get( $name ) { return 'fixture-version'; } }; }
add_filter( 'pre_option_blog_charset', static function() { return 'UTF-8'; } );
require dirname( __DIR__ ) . '/inc/classes/Docy_register_theme.php';
set_error_handler( static function( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
do_action( 'admin_init' );
$callback = $GLOBALS['wp_registered_settings']['docy_purchase_code']['sanitize_callback'];
if ( ! is_callable( $callback ) ) throw new RuntimeException( 'The registered license sanitizer must be callable.' );
$checks = 1;
foreach ( array(
    array( '  QA-ONLY-1234  ', 'QA-ONLY-1234' ),
    array( '<b>QA-ONLY-1234</b>', 'QA-ONLY-1234' ),
    array( "QA-ONLY-1234\n", 'QA-ONLY-1234' ),
    array( array( 'nested' ), '' ),
    array( '', '' ),
) as list( $input, $expected ) ) {
    if ( sanitize_option( 'docy_purchase_code', $input ) !== $expected ) throw new RuntimeException( 'Native settings sanitization failed.' );
    ++$checks;
}
// Exercise the real HTTP-argument filter, including input shaped by other filters.
add_filter( 'pre_option_template', static function() { return 'docy'; } );
add_filter( 'pre_option_stylesheet', static function() { return 'docy-child'; } );
$url = 'https://api.wordpress.org/themes/update-check/1.1/';
foreach ( array( array(), array( 'body' => 'serialized-body' ), array( 'body' => array() ), array( 'body' => array( 'themes' => array() ) ), array( 'body' => array( 'themes' => 'invalid-json' ) ), array( 'body' => array( 'themes' => '{"themes":[]}' ) ) ) as $request ) {
    if ( apply_filters( 'http_request_args', $request, $url ) !== $request ) throw new RuntimeException( 'Malformed or unsupported update metadata must remain untouched.' );
    ++$checks;
}
$request = array( 'body' => array( 'themes' => '{"themes":{"docy":{"Version":"1"},"docy-child":{"Version":"1"},"other":{"Version":"2"}},"active":"docy-child"}', 'locale' => 'it_IT' ), 'timeout' => 10 );
$filtered = apply_filters( 'http_request_args', $request, $url );
$metadata = json_decode( $filtered['body']['themes'] );
if ( isset( $metadata->themes->docy ) || isset( $metadata->themes->{'docy-child'} ) || $metadata->themes->other->Version !== '2' || $metadata->active !== 'docy-child' || $filtered['body']['locale'] !== 'it_IT' || $filtered['timeout'] !== 10 ) throw new RuntimeException( 'Valid update filtering changed unrelated metadata or request arguments.' );
++$checks;
if ( apply_filters( 'http_request_args', $request, 'https://example.invalid/unrelated' ) !== $request ) throw new RuntimeException( 'Unrelated requests must remain untouched.' );
++$checks;
restore_error_handler();
echo "Theme license settings: {$checks} native WordPress registration and sanitization assertions passed; no license activation or network requests.\n";
