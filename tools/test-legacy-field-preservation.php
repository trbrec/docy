<?php
/** Use native WordPress hooks to prove preservation without changing actual ACF data. */
define( 'ABSPATH', __DIR__ . '/' );
$wp_root = getenv( 'TRB_WP_TEST_ROOT' ) ?: dirname( __DIR__, 2 ) . '/.audit-runtime/wp/wordpress';
require $wp_root . '/wp-includes/plugin.php';
require dirname( __DIR__ ) . '/integrations/portal-mu-plugins/trb-legacy-field-preservation.php';
$GLOBALS['legacy_field_fixture'] = array( 'existing_definition' => 'preserved' );
$GLOBALS['unrelated_init_calls'] = 0;
function docy_remove_acf_fields_if_exists_in_codestar() { unset( $GLOBALS['legacy_field_fixture']['existing_definition'] ); }
add_action( 'init', static function() { ++$GLOBALS['unrelated_init_calls']; }, 20 );
do_action( 'after_setup_theme' );
if ( false !== has_action( 'init', 'docy_remove_acf_fields_if_exists_in_codestar' ) ) throw new RuntimeException( 'An absent callback was introduced.' );
add_action( 'after_setup_theme', static function() { add_action( 'init', 'docy_remove_acf_fields_if_exists_in_codestar' ); }, 10 );
foreach ( array( 1, 2 ) as $iteration ) {
    do_action( 'after_setup_theme' );
    if ( false !== has_action( 'init', 'docy_remove_acf_fields_if_exists_in_codestar' ) ) throw new RuntimeException( 'Legacy deletion callback remains registered.' );
    do_action( 'init' );
    if ( array( 'existing_definition' => 'preserved' ) !== $GLOBALS['legacy_field_fixture'] || $iteration !== $GLOBALS['unrelated_init_calls'] ) throw new RuntimeException( 'Definitions were deleted or unrelated hooks blocked.' );
}
echo "Legacy field preservation: native WordPress hook order, retained definitions, unrelated callbacks and repeated setup passed.\n";
