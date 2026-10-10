<?php
/** Native WordPress sanitization and the prepared WooCommerce attribute contract. */
define( 'ABSPATH', __DIR__ . '/' );
function apply_filters( $hook, $value, ...$args ) { return $value; }
function wp_allowed_protocols() { return [ 'http', 'https', 'mailto' ]; }
$wp_root = getenv( 'TRB_WP_TEST_ROOT' ) ?: dirname( __DIR__, 2 ) . '/.audit-runtime/wp/wordpress';
require $wp_root . '/wp-includes/compat.php';
require $wp_root . '/wp-includes/html-api/class-wp-html-tag-processor.php';
foreach ( glob( $wp_root . '/wp-includes/html-api/class-wp-html-*.php' ) as $dependency ) require_once $dependency;
require $wp_root . '/wp-includes/kses.php';
set_error_handler( static function( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
function attribute_render( $product_attributes ) {
    ob_start();
    require dirname( __DIR__ ) . '/woocommerce/single-product/product-attributes.php';
    return ob_get_clean();
}
function attribute_check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
attribute_check( '' === attribute_render( [] ), 'No attributes must render no empty table.' );
$html = attribute_render( [
    'weight' => [ 'label' => 'Peso', 'value' => '2 kg' ],
    'dimensions' => [ 'label' => 'Dimensioni', 'value' => '10 × 20 × 30 cm' ],
    'colour' => [ 'label' => '<strong>Colore</strong>', 'value' => '<p><a href="https://example.invalid/blue">Blu</a></p>' ],
    'unsafe' => [ 'label' => '<script>alert(1)</script>Etichetta', 'value' => '<img src="https://example.invalid/image.png" onerror="alert(1)"><a href="javascript:alert(1)">Link</a>' ],
] );
attribute_check( 4 === substr_count( $html, '<tr>' ), 'Each prepared attribute, including dimensions and weight, must appear exactly once.' );
attribute_check( str_contains( $html, '2 kg' ) && str_contains( $html, '10 × 20 × 30 cm' ), 'Prepared weight and dimensions must be preserved without legacy product variables.' );
attribute_check( str_contains( $html, '<strong>Colore</strong>' ) && str_contains( $html, '<a href="https://example.invalid/blue">Blu</a>' ), 'Permitted formatting and taxonomy links must remain intact.' );
attribute_check( ! str_contains( $html, '<script' ) && ! str_contains( $html, 'onerror' ) && ! str_contains( $html, 'javascript:' ), 'Actual WordPress HTML filtering must remove scripts, event handlers and unsafe URLs.' );
restore_error_handler();
echo "Product attributes: prepared WooCommerce fields, Unicode, empty results and native WordPress HTML filtering passed.\n";
