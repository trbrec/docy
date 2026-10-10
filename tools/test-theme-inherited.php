<?php
/** Regression cases for inherited theme failures. No users, mail or providers are created. */
define( 'ABSPATH', __DIR__ . '/' );
$wp_root = getenv( 'TRB_WP_TEST_ROOT' ) ?: dirname( __DIR__, 2 ) . '/.audit-runtime/wp/wordpress';
require $wp_root . '/wp-includes/class-wp-error.php';
function add_action( ...$args ) { $GLOBALS['fixture_actions'][] = $args; } function add_filter( ...$args ) {} function do_action( ...$args ) {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function esc_html__( $value, $domain = '' ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $value, $domain = '' ) { echo esc_html__( $value, $domain ); }
function esc_attr( $value ) { return esc_html__( $value ); }
function esc_html( $value ) { return esc_html__( $value ); }
function __( $value, $domain = '' ) { return $value; }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value ); }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_user( $value ) { return sanitize_text_field( $value ); }
function sanitize_email( $value ) { return sanitize_text_field( $value ); }
function wp_parse_str( $value, &$result ) { parse_str( $value, $result ); }
function wp_parse_args( $value, $defaults ) { return array_merge( $defaults, $value ); }
function wp_verify_nonce( $value, $action ) { return 'valid-fixture-nonce' === $value; }
function username_exists( $value ) { return 'existing' === $value; }
function email_exists( $value ) { return 'existing@example.invalid' === $value; }
function validate_username( $value ) { return (bool) preg_match( '/^[a-zA-Z0-9_]+$/', $value ); }
function is_email( $value ) { return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_insert_user( $value ) { $GLOBALS['insert_calls'][] = $value; return $GLOBALS['insert_result']; }
function get_pages( $query ) { return $GLOBALS['fixture_pages']; }
function get_option( $key, $default = false ) { return $GLOBALS['fixture_options'][ $key ] ?? $default; }
class ThemeJsonResponse extends RuntimeException {
    public function __construct( public bool $success, public array $data ) { parent::__construct( 'Fixture response' ); }
}
function wp_send_json_error( $data ) { throw new ThemeJsonResponse( false, $data ); }
function wp_send_json_success( $data = [] ) { throw new ThemeJsonResponse( true, $data ); }
function wp_kses_post_deep( $value ) { ++$GLOBALS['fixture_decode_calls']; return $value; }
function current_user_can( ...$args ) { return $GLOBALS['fixture_capability']; }
function update_option( $key, $value ) { $GLOBALS['fixture_writes'][ $key ] = $value; }
function absint( $value ) { return abs( (int) $value ); }
function get_comment( $value ) { return $value instanceof WP_Comment ? $value : $GLOBALS['fixture_comment']; }
class CSF_Abstract {}
class CSF_Fields {
    public function __construct( public $field, public $value = '', public $unique = '', public $where = '', public $parent = '' ) {}
}
class CSF {
    public static function field( $field, $default, $shortcode, $context ) { echo '<input data-field="' . esc_attr( $field['id'] ) . '">'; }
}
class Docy_register_theme { public function messages() {} public function form() {} }
class WC_Order { public function get_order_number() { return 'fixture-<script>unsafe</script>'; } }
class WP_Comment { public $comment_ID = 123; }
require dirname( __DIR__ ) . '/inc/reg_process.php';
require dirname( __DIR__ ) . '/inc/template-functions.php';
require dirname( __DIR__ ) . '/inc/csf/classes/shortcode-options.class.php';
require dirname( __DIR__ ) . '/inc/csf/fields/group/group.php';
require dirname( __DIR__ ) . '/inc/csf/fields/repeater/repeater.php';
require dirname( __DIR__ ) . '/inc/csf/functions/actions.php';
require dirname( __DIR__ ) . '/inc/comment-functions.php';
require dirname( __DIR__ ) . '/inc/classes/Docy_base.php';
require dirname( __DIR__ ) . '/inc/classes/Docy_admin_page.php';
class FixtureAdminPage extends Docy_admin_page { public $id = 'fixture-admin'; public function save() {} }
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$checks = 0;
function inherited_check( $condition, $message ) { ++$GLOBALS['checks']; if ( ! $condition ) throw new RuntimeException( $message ); }
function inherited_response( $callback ) {
    try { $callback(); } catch ( ThemeJsonResponse $response ) { return $response; }
    throw new RuntimeException( 'Expected a terminating JSON response.' );
}
inherited_check( '' === dt_registration_validation( 'fixture_artist', 'fixture-password', 'fixture@example.invalid' ), 'Valid registration must not read an undefined message.' );
$errors = dt_registration_validation( 'a!', 'x', 'invalid' );
inherited_check( 4 === substr_count( $errors, '<div class="error">' ), 'All validation errors must remain visible.' );
$valid_data = [ 'username' => 'fixture_artist', 'password' => 'fixture-password', 'email' => 'fixture@example.invalid', 'submit_et_form' => 'valid-fixture-nonce' ];
foreach ( [ new WP_Error( 'db_insert_error', 'Synthetic insertion failure' ), 0, 123 ] as $result ) {
    $GLOBALS['insert_result'] = $result;
    $GLOBALS['insert_calls'] = [];
    $_POST = [ 'data' => http_build_query( $valid_data ) ];
    $response = inherited_response( 'dt_custom_registration_form' );
    inherited_check( ( 123 === $result ) === $response->success, 'Only an actual inserted user ID can produce success.' );
    inherited_check( 1 === count( $GLOBALS['insert_calls'] ), 'The handler must insert only once.' );
    inherited_check( $valid_data['password'] === $GLOBALS['insert_calls'][0]['user_pass'], 'Password bytes must reach WordPress unchanged.' );
}
foreach ( [ [ 'data' => [] ], [ 'data' => http_build_query( array_merge( $valid_data, [ 'password' => [ 'nested' ] ] ) ) ], [ 'data' => http_build_query( array_merge( $valid_data, [ 'submit_et_form' => 'invalid' ] ) ) ], [ 'data' => http_build_query( array_merge( $valid_data, [ 'username' => 'a!', 'password' => 'x' ] ) ) ] ] as $post ) {
    $GLOBALS['insert_calls'] = [];
    $_POST = $post;
    $response = inherited_response( 'dt_custom_registration_form' );
    inherited_check( ! $response->success && [] === $GLOBALS['insert_calls'], 'Malformed, unauthenticated or invalid registration must not insert a user.' );
}
$GLOBALS['fixture_pages'] = [];
inherited_check( 0 === docy_get_page_template_id(), 'A site without a matching page must return zero without a warning.' );
$GLOBALS['fixture_pages'] = [ (object) [ 'ID' => 11 ], (object) [ 'ID' => 22 ] ];
inherited_check( 22 === docy_get_page_template_id(), 'Existing page selection behavior must remain intact.' );
foreach ( [ 'valid' => 'success', 'invalid' => 'failed', 'expired' => '', '' => '' ] as $status => $class ) {
    $GLOBALS['fixture_options']['docy_purchase_code_status'] = $status;
    ob_start();
    require dirname( __DIR__ ) . '/inc/admin/registration.php';
    $html = ob_get_clean();
    inherited_check( str_contains( $html, 'class="st-box-head ' . $class . '"' ), 'Every license status must render without undefined variables.' );
}
$shortcoder = ( new ReflectionClass( CSF_Shortcoder::class ) )->newInstanceWithoutConstructor();
$shortcoder->pre_sections = [ [ 'shortcode' => 'fixture', 'fields' => [ [ 'type' => 'text', 'id' => 'fixture_field' ] ] ] ];
foreach ( [ 'abc', '1x', '0', '-1', '2', '999999999999999999999999999', [ '1' ] ] as $key ) {
    $_POST = [ 'nonce' => 'valid-fixture-nonce', 'shortcode_key' => $key ];
    $response = inherited_response( [ $shortcoder, 'get_shortcode' ] );
    inherited_check( str_contains( $response->data['content'], 'csf-error-text' ), 'Invalid shortcode indices must fail without PHP arithmetic or array warnings.' );
}
$_POST = [ 'nonce' => 'valid-fixture-nonce', 'shortcode_key' => '1' ];
$response = inherited_response( [ $shortcoder, 'get_shortcode' ] );
inherited_check( str_contains( $response->data['content'], 'data-field="fixture_field"' ), 'A valid shortcode index must continue to render its field.' );
$_POST['nonce'] = 'invalid';
$response = inherited_response( [ $shortcoder, 'get_shortcode' ] );
inherited_check( str_contains( $response->data['content'], 'csf-error-text' ), 'Valid indices must still require nonce verification.' );
foreach ( [ [ CSF_Field_group::class, [ [ 'id' => 'child' ] ] ], [ CSF_Field_repeater::class, [ [ 'id' => 'child' ] ] ], [ CSF_Field_group::class, [] ], [ CSF_Field_group::class, [ [ 'type' => 'notice' ] ] ] ] as [ $field_class, $fields ] ) {
    $field = new $field_class( [ 'id' => 'path/segment', 'fields' => $fields ], '', 'root[path/segment]' );
    ob_start();
    $field->render();
    $html = ob_get_clean();
    inherited_check( str_contains( $html, 'Error: Field ID conflict.' ), 'Slash-delimited IDs and empty or untitled field groups must return the controlled conflict notice without warnings.' );
}
foreach ( [ false, new WC_Order() ] as $order ) {
    ob_start();
    require dirname( __DIR__ ) . '/woocommerce/checkout/order-received.php';
    $html = ob_get_clean();
    inherited_check( (bool) $order === str_contains( $html, 'class="order-num"' ), 'An unavailable order must not cause a fatal error or display an invented order number.' );
    inherited_check( ! str_contains( $html, '<script>' ), 'Order numbers supplied by plugins must be escaped.' );
}
foreach ( [ null, 'another-page', [ 'fixture-admin' ], 'fixture-admin' ] as $page ) {
    $_GET = null === $page ? [] : [ 'page' => $page ];
    $GLOBALS['fixture_actions'] = [];
    new FixtureAdminPage();
    $save_hooks = array_filter( $GLOBALS['fixture_actions'], static fn( $hook ) => 'admin_init' === $hook[0] );
    inherited_check( ( 'fixture-admin' === $page ) === ( 1 === count( $save_hooks ) ), 'Admin save handlers must only register on their own exact page.' );
}
foreach ( [ [ 'nonce' => 'invalid', 'allowed' => true, 'data' => [] ], [ 'nonce' => 'valid-fixture-nonce', 'allowed' => false, 'data' => [] ], [ 'nonce' => 'valid-fixture-nonce', 'allowed' => true, 'data' => [] ], [ 'nonce' => 'valid-fixture-nonce', 'allowed' => true, 'data' => 'not-json' ], [ 'nonce' => 'valid-fixture-nonce', 'allowed' => true, 'data' => '{"fixture":"value"}' ] ] as $case ) {
    $GLOBALS['fixture_capability'] = $case['allowed'];
    $GLOBALS['fixture_writes'] = [];
    $GLOBALS['fixture_decode_calls'] = 0;
    $_POST = [ 'nonce' => $case['nonce'], 'unique' => 'fixture-only', 'data' => $case['data'] ];
    $response = inherited_response( 'csf_import_ajax' );
    $valid = 'valid-fixture-nonce' === $case['nonce'] && $case['allowed'] && '{"fixture":"value"}' === $case['data'];
    inherited_check( $valid === $response->success && $valid === isset( $GLOBALS['fixture_writes']['fixture-only'] ), 'Only authorized, valid JSON may update theme options.' );
    if ( 'invalid' === $case['nonce'] || ! $case['allowed'] ) inherited_check( 0 === $GLOBALS['fixture_decode_calls'], 'Authorization must precede parsing imported settings.' );
}
$GLOBALS['fixture_capability'] = true;
$GLOBALS['fixture_comment'] = new WP_Comment();
$_POST = [ 'nonce' => 'valid-fixture-nonce', 'comment_id' => 123, 'comment_content' => [ 'invalid' ] ];
$response = inherited_response( 'docy_ajax_edit_comment' );
inherited_check( ! $response->success, 'Malformed comment text must return a controlled error without calling trim on an array.' );
restore_error_handler();
echo "Inherited theme: {$checks} registration, empty-page, license, shortcode, nested-field, order, admin-page, settings and comment assertions passed.\n";
