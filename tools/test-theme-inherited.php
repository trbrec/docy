<?php
/** Regression cases for inherited theme failures. No users, mail or providers are created. */
define( 'ABSPATH', __DIR__ . '/' );
$wp_root = getenv( 'TRB_WP_TEST_ROOT' ) ?: dirname( __DIR__, 2 ) . '/.audit-runtime/wp/wordpress';
require $wp_root . '/wp-includes/class-wp-error.php';
require $wp_root . '/wp-includes/class-wp-list-util.php';
function add_action( ...$args ) { $GLOBALS['fixture_actions'][] = $args; } function add_filter( ...$args ) {} function do_action( ...$args ) { $GLOBALS['fixture_fired_hooks'][] = $args[0]; }
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
function get_the_ID() { return 777; }
function get_the_terms( $post_id, $taxonomy ) { ++$GLOBALS['fixture_term_reads']; return $GLOBALS['fixture_terms']; }
function wp_list_pluck( $values, $field ) { return ( new WP_List_Util( $values ) )->pluck( $field ); }
class WP_Query {
    public function __construct( $args ) { $GLOBALS['fixture_queries'][] = $args; }
    public function have_posts() { return false; }
}
class ThemeJsonResponse extends RuntimeException {
    public function __construct( public bool $success, public array $data ) { parent::__construct( 'Fixture response' ); }
}
function wp_send_json_error( $data ) { throw new ThemeJsonResponse( false, $data ); }
function wp_send_json_success( $data = [] ) { throw new ThemeJsonResponse( true, $data ); }
function wp_kses_post_deep( $value ) { ++$GLOBALS['fixture_decode_calls']; return $value; }
function wp_kses_post( $value ) { return strip_tags( $value ); }
function current_user_can( ...$args ) { return $GLOBALS['fixture_capability']; }
function update_option( $key, $value ) {
    $GLOBALS['fixture_writes'][ $key ] = $value;
    if ( ! ( $GLOBALS['fixture_write_ok'] ?? true ) ) return false;
    $GLOBALS['fixture_options'][ $key ] = $value;
    return true;
}
function delete_option( $key ) {
    $GLOBALS['fixture_deletes'][] = $key;
    if ( ! ( $GLOBALS['fixture_delete_ok'] ?? true ) ) return false;
    unset( $GLOBALS['fixture_options'][ $key ] );
    return true;
}
function set_transient( $key, $value, $expiration = 0 ) { return update_option( 'transient:' . $key, $value ); }
function get_transient( $key ) { return get_option( 'transient:' . $key ); }
function set_theme_mod( $key, $value ) { update_option( 'theme_mod:' . $key, $value ); }
function get_theme_mod( $key ) { return get_option( 'theme_mod:' . $key ); }
function update_site_option( $key, $value ) { return update_option( 'network:' . $key, $value ); }
function get_site_option( $key ) { return get_option( 'network:' . $key ); }
function absint( $value ) { return abs( (int) $value ); }
function get_comment( $value ) { return $value instanceof WP_Comment ? $value : $GLOBALS['fixture_comment']; }
class CSF_Abstract {}
class CSF_Fields {
    public function __construct( public $field, public $value = '', public $unique = '', public $where = '', public $parent = '' ) {}
    public function field_before() { return ''; }
    public function field_after() { return ''; }
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
require dirname( __DIR__ ) . '/inc/csf/classes/admin-options.class.php';
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
foreach ( [ [ 'id' => 'empty-group' ], [ 'id' => 'empty-group', 'fields' => [] ] ] as $definition ) {
    $field = new CSF_Field_group( $definition, '', 'fixture-root' );
    ob_start();
    $field->render();
    $html = ob_get_clean();
    inherited_check( str_contains( $html, 'csf-cloneable-add' ), 'The default empty field list must render a usable group without undefined keys.' );
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

foreach ( [ false, true ] as $same_value ) {
    $GLOBALS['fixture_write_ok'] = false;
    $GLOBALS['fixture_options']['fixture-only'] = $same_value ? [ 'fixture' => 'value' ] : [ 'previous' => 'preserved' ];
    $_POST = [ 'nonce' => 'valid-fixture-nonce', 'unique' => 'fixture-only', 'data' => '{"fixture":"value"}' ];
    $response = inherited_response( 'csf_import_ajax' );
    inherited_check( $same_value === $response->success, 'Failed settings writes must report an error; a verified existing identical value remains successful.' );
    inherited_check( $same_value || [ 'previous' => 'preserved' ] === get_option( 'fixture-only' ), 'A failed settings write must preserve previous data.' );
}
$GLOBALS['fixture_write_ok'] = true;
foreach ( [ [ true, 'fixture-only' ], [ false, 'fixture-only' ], [ true, '' ], [ true, 'missing-fixture' ] ] as [ $delete_ok, $unique ] ) {
    $GLOBALS['fixture_delete_ok'] = $delete_ok;
    $GLOBALS['fixture_options']['fixture-only'] = [ 'previous' => 'preserved' ];
    $GLOBALS['fixture_deletes'] = [];
    $_POST = [ 'nonce' => 'valid-fixture-nonce', 'unique' => $unique ];
    $response = inherited_response( 'csf_reset_ajax' );
    inherited_check( ( $delete_ok && '' !== $unique ) === $response->success, 'Settings reset must verify removal and reject an empty key.' );
    inherited_check( $delete_ok || [ 'previous' => 'preserved' ] === get_option( 'fixture-only' ), 'Failed resets must preserve previous settings.' );
    if ( '' === $unique ) inherited_check( [] === $GLOBALS['fixture_deletes'], 'An empty reset key must not reach the database.' );
}
foreach ( [ '0', '1' ] as $enabled ) {
    foreach ( [ false, new WP_Error( 'fixture_terms', 'Unavailable taxonomy' ), [], [ (object) [ 'term_id' => 17 ] ] ] as $terms ) {
        $GLOBALS['fixture_options']['docy_opt'] = [ 'is_related_posts' => $enabled ];
        $GLOBALS['fixture_terms'] = $terms;
        $GLOBALS['fixture_term_reads'] = 0;
        $GLOBALS['fixture_queries'] = [];
        ob_start();
        require dirname( __DIR__ ) . '/template-parts/single-post/related-posts.php';
        $html = ob_get_clean();
        $should_query = '1' === $enabled && is_array( $terms ) && [] !== $terms;
        inherited_check( $should_query === ( 1 === count( $GLOBALS['fixture_queries'] ) ), 'Disabled or unavailable related posts must not query or raise taxonomy warnings.' );
        if ( '0' === $enabled ) inherited_check( 0 === $GLOBALS['fixture_term_reads'], 'Disabled related posts must not look up terms.' );
        if ( $should_query ) {
            $query = $GLOBALS['fixture_queries'][0];
            inherited_check( [ 777 ] === $query['post__not_in'] && [ 17 ] === $query['tax_query'][0]['terms'], 'Related posts must exclude the current post and use its category IDs.' );
            inherited_check( true === $query['no_found_rows'], 'Unpaginated related posts must not count unused result totals.' );
        }
    }
}
foreach ( [ '', '   ', null, [], '<p>No quote</p>', '<blockquote>Perché &lt;script&gt; — تونس</blockquote>' ] as $content ) {
    ob_start();
    docy_get_html_tag( 'blockquote', $content );
    $html = ob_get_clean();
    inherited_check( ( is_string( $content ) && str_contains( $content, '<blockquote>' ) ) === ( '' !== $html ), 'Empty, malformed or absent quote content must not cause a DOM fatal error or an invented quote.' );
    if ( '' !== $html ) inherited_check( str_contains( $html, 'Perché &lt;script&gt; — تونس' ) && ! str_contains( $html, '<script>' ), 'Quote text must retain UTF-8 and escape HTML-looking content.' );
}
$settings = ( new ReflectionClass( CSF_Options::class ) )->newInstanceWithoutConstructor();
$settings->unique = 'fixture-framework';
$settings->pre_fields = [ [ 'id' => 'fixture' ] ];
$settings->pre_sections = [ [ 'fields' => $settings->pre_fields ] ];
foreach ( [ '' => '', 'transient' => 'transient:', 'theme_mod' => 'theme_mod:', 'network' => 'network:' ] as $database => $prefix ) {
    $settings->args['database'] = $database;
    foreach ( [ false, true ] as $write_ok ) {
        $GLOBALS['fixture_write_ok'] = $write_ok;
        $GLOBALS['fixture_options'][ $prefix . $settings->unique ] = [ 'previous' => 'preserved' ];
        $GLOBALS['fixture_fired_hooks'] = [];
        inherited_check( $write_ok === $settings->save_options( [ 'fixture' => 'value' ] ), 'Every framework storage backend must verify its saved data.' );
        inherited_check( $write_ok === in_array( 'csf_fixture-framework_saved', $GLOBALS['fixture_fired_hooks'], true ), 'The saved hook must only fire after a verified write.' );
    }
}
$settings->args['database'] = '';
$framework_data = [ 'csf_options_noncefixture-framework' => 'valid-fixture-nonce', 'fixture-framework' => [ 'fixture' => 'value' ] ];
foreach ( [ false, true ] as $write_ok ) {
    $GLOBALS['fixture_write_ok'] = $write_ok;
    $settings->options = [ 'previous' => 'preserved' ];
    $GLOBALS['fixture_options'][ $settings->unique ] = $settings->options;
    $_POST = [ 'data' => json_encode( $framework_data ) ];
    inherited_check( $write_ok === $settings->set_options( true ), 'Framework form saves must propagate persistence failures.' );
    inherited_check( $write_ok ? [ 'fixture' => 'value' ] === $settings->options : [ 'previous' => 'preserved' ] === $settings->options, 'A failed form save must retain the previous in-memory settings.' );
    inherited_check( $write_ok !== $settings->save_failed, 'A failed synchronous save must be presented as an error.' );
    inherited_check( ! $write_ok || 'Settings saved.' === $settings->notice, 'A recovered save must clear the previous error notice.' );
}
$GLOBALS['fixture_write_ok'] = true;
foreach ( [ [], 'not-json', json_encode( array_merge( $framework_data, [ 'csf_options_noncefixture-framework' => [] ] ) ), json_encode( array_merge( $framework_data, [ 'csf_transient' => 'invalid' ] ) ), json_encode( array_merge( $framework_data, [ 'fixture-framework' => 'invalid' ] ) ), json_encode( array_merge( $framework_data, [ 'csf_transient' => [ 'reset_section' => '1', 'section' => '1x' ] ] ) ) ] as $payload ) {
    $_POST = [ 'data' => $payload ];
    $GLOBALS['fixture_writes'] = [];
    inherited_check( false === $settings->set_options( true ) && [] === $GLOBALS['fixture_writes'], 'Malformed framework data and reset indices must not reach storage.' );
}
$GLOBALS['fixture_capability'] = false;
$_POST = [ 'data' => [] ];
inherited_check( false === $settings->set_options( true ), 'Framework authorization must precede decoding malformed data.' );
$GLOBALS['fixture_capability'] = true;
foreach ( [ [ 'fixture-framework' => '' ], [ 'fixture-framework' => null ], [ 'csf_transient' => '' ], [ 'csf_transient' => null ], [ 'csf_transient' => [ 'reset_section' => '1', 'section' => [] ] ], [ 'csf_transient' => [ 'reset_section' => '1', 'section' => '0' ] ], [ 'csf_transient' => [ 'reset_section' => '1' ] ] ] as $replacement ) {
    $_POST = [ 'data' => json_encode( array_merge( $framework_data, $replacement ) ) ];
    $GLOBALS['fixture_writes'] = [];
    inherited_check( false === $settings->set_options( true ) && [] === $GLOBALS['fixture_writes'], 'Empty malformed framework fields and missing reset indices must not erase previous settings.' );
}
restore_error_handler();
echo "Inherited theme: {$checks} registration, empty-page, license, shortcode, nested-field, order, admin-page, settings, comment and related-post assertions passed.\n";
