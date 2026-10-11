<?php
/** Native WooCommerce contracts on an exclusively owned, disposable WordPress database. */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }
$qa_root = str_replace( '\\', '/', (string) getenv( 'TRB_WP_MYSQL_ROOT' ) );
$qa_tmp = str_replace( '\\', '/', realpath( sys_get_temp_dir() ) ?: '' );
$qa_database = (string) getenv( 'TRB_QA_MYSQL_DATABASE' );
if ( ! $qa_tmp || dirname( dirname( $qa_root ) ) !== $qa_tmp || ! preg_match( '~^trb-wp-mysql-qa-[0-9]+$~D', basename( dirname( $qa_root ) ) ) || 'wordpress' !== basename( $qa_root ) || ! is_file( $qa_root . '/wp-load.php' ) || is_link( $qa_root ) || is_link( dirname( $qa_root ) ) || ! preg_match( '/^trb_wp_qa_[0-9]+$/D', $qa_database ) ) {
    throw new RuntimeException( 'An exclusively disposable official WordPress fixture is required.' );
}
$qa_install = ( $argv[1] ?? '' ) === 'install';
if ( ! $qa_install ) {
    $qa_config_hash = (string) getenv( 'TRB_QA_CONFIG_SHA256' );
    if ( ! preg_match( '/^[a-f0-9]{64}$/D', $qa_config_hash ) || ! is_file( $qa_root . '/wp-config.php' ) || is_link( $qa_root . '/wp-config.php' ) || ! hash_equals( $qa_config_hash, hash_file( 'sha256', $qa_root . '/wp-config.php' ) ) ) throw new RuntimeException( 'Only the configuration created by this fixture runner may be loaded.' );
}
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/';
if ( $qa_install ) {
    if ( is_file( $qa_root . '/wp-config.php' ) ) throw new RuntimeException( 'Refusing an existing WordPress configuration.' );
    $qa_config = "<?php\ndefine('DB_NAME','" . $qa_database . "');\ndefine('DB_USER','root');\ndefine('DB_PASSWORD','qa_ci');\ndefine('DB_HOST','127.0.0.1:3307');\ndefine('DB_CHARSET','utf8mb4');\ndefine('DB_COLLATE','');\ndefine('DISABLE_WP_CRON',true);\ndefine('DISALLOW_FILE_MODS',true);\ndefine('AUTOMATIC_UPDATER_DISABLED',true);\ndefine('WP_DEBUG',true);\ndefine('WP_DEBUG_DISPLAY',false);\ndefine('WP_DISABLE_FATAL_ERROR_HANDLER',true);\ndefine('WP_ENVIRONMENT_TYPE','local');\n\$table_prefix='qa_';\n";
    foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $qa_key ) $qa_config .= "define('" . $qa_key . "','" . bin2hex( random_bytes( 32 ) ) . "');\n";
    $qa_config .= "if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');\nrequire_once ABSPATH.'wp-settings.php';\n";
    file_put_contents( $qa_root . '/wp-config.php', $qa_config );
    mkdir( $qa_root . '/wp-content/mu-plugins', 0700, true );
    file_put_contents( $qa_root . '/wp-content/mu-plugins/qa-isolation.php', '<?php add_filter("pre_wp_mail",static function(){return true;},PHP_INT_MAX); add_filter("pre_http_request",static function(){return new WP_Error("qa_network_blocked","External HTTP is disabled in this isolated fixture.");},PHP_INT_MAX);' );
    define( 'WP_INSTALLING', true );
}
require $qa_root . '/wp-load.php';
if ( $qa_install ) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    wp_install( 'Isolated WooCommerce QA', 'qa_admin', 'qa-admin@example.invalid', false, '', wp_generate_password( 32 ), 'en_US' );
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $qa_activation = activate_plugin( 'woocommerce/woocommerce.php', '', false, false );
    if ( is_wp_error( $qa_activation ) ) throw new RuntimeException( 'Native WooCommerce activation failed.' );
    echo "Native WordPress and WooCommerce activation completed.\n";
    exit;
}
if ( ! class_exists( 'WooCommerce' ) || ! did_action( 'woocommerce_init' ) ) throw new RuntimeException( 'The real WooCommerce plugin must complete native initialization.' );
$qa_theme = dirname( __DIR__, 2 );
add_filter( 'woocommerce_locate_template', static function( $template, $name ) use ( $qa_theme ) {
    $candidate = $qa_theme . '/woocommerce/' . $name;
    return is_file( $candidate ) ? $candidate : $template;
}, 10, 2 );
add_filter( 'pre_determine_locale', static function() { return 'it_IT'; } );
load_theme_textdomain( 'docy', $qa_theme . '/languages' );
// Exercise a supported setting that removes both names. Do not substitute theme functions.
update_option( 'docy_opt', array( 'remove_checkout_fields' => array( 'first_name', 'last_name' ) ) );
require $qa_theme . '/inc/template-functions.php';
require $qa_theme . '/inc/woo_config.php';
$qa_errors = array();
set_error_handler( static function( $severity, $message, $file, $line ) use ( &$qa_errors, $qa_theme ) {
    if ( 0 === ( error_reporting() & $severity ) ) return false;
    if ( str_starts_with( str_replace( '\\', '/', $file ), str_replace( '\\', '/', $qa_theme ) . '/' ) ) throw new ErrorException( $message, 0, $severity, $file, $line );
    $qa_errors[] = array( 'message' => $message, 'file' => basename( $file ), 'line' => $line );
    return true;
} );
$qa_checks = 0;
function qa_woo_check( $condition, $message ) {
    if ( ! $condition ) throw new RuntimeException( $message );
    $GLOBALS['qa_checks']++;
}
function qa_woo_dom( $html ) {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors( true );
    $dom->loadHTML( '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );
    return new DOMXPath( $dom );
}
function qa_woo_template( $name, $args = array() ) {
    ob_start();
    try { wc_get_template( $name, $args ); return ob_get_contents(); }
    finally { ob_end_clean(); }
}

update_option( 'woocommerce_default_country', 'IT' );
update_option( 'woocommerce_currency', 'EUR' );
update_option( 'woocommerce_calc_taxes', 'no' );
update_option( 'woocommerce_allowed_countries', 'all' );
update_option( 'woocommerce_ship_to_countries', '' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_enable_checkout_login_reminder', 'no' );
$qa_user = wp_insert_user( array( 'user_login' => 'qa_woo_tunisia', 'user_pass' => wp_generate_password( 32 ), 'user_email' => 'qa-woo@example.invalid', 'display_name' => 'Artista Fittizio Tunisia', 'role' => 'customer' ) );
qa_woo_check( is_int( $qa_user ) && $qa_user > 0, 'Native fictional customer could not be created.' );
wp_set_current_user( $qa_user );
wc_load_cart();
WC()->customer = new WC_Customer( $qa_user, true );
WC()->customer->set_billing_country( 'TN' );
WC()->customer->set_billing_state( 'TN-11' );
WC()->customer->set_billing_city( 'Tunisi' );
WC()->customer->set_billing_postcode( '1001' );
WC()->customer->set_billing_address_1( 'Indirizzo sintetico di prova 1' );
WC()->customer->set_billing_email( 'qa-woo@example.invalid' );
WC()->customer->save();
$qa_product = new WC_Product_Simple();
$qa_product->set_name( 'Prodotto fittizio — quantità limitata' );
$qa_product->set_regular_price( '10.00' );
$qa_product->set_status( 'publish' );
$qa_product->set_virtual( true );
$qa_product->set_manage_stock( true );
$qa_product->set_stock_quantity( 4 );
$qa_product_id = $qa_product->save();
$GLOBALS['product'] = $qa_product;
$qa_cart_key = WC()->cart->add_to_cart( $qa_product_id, 2 );
WC()->cart->calculate_totals();
qa_woo_check( is_string( $qa_cart_key ) && 2 === WC()->cart->get_cart_item( $qa_cart_key )['quantity'] && 20.0 === (float) WC()->cart->get_total( 'edit' ), 'Real cart quantity or total is wrong.' );
qa_woo_check( false === WC()->cart->add_to_cart( $qa_product_id, 5 ) && 2 === WC()->cart->get_cart_item( $qa_cart_key )['quantity'], 'Insufficient stock must preserve the previous real cart.' );
wc_clear_notices();
$qa_quantity = woocommerce_quantity_input( array( 'input_id' => 'qa_quantity', 'input_value' => 2 ), $qa_product, false );
$qa_dom = qa_woo_dom( $qa_quantity );
qa_woo_check( 1 === $qa_dom->query( '//input[@id="qa_quantity" and @min="1" and @max="4" and @step="1"]' )->length, 'Product stock and purchase limits must reach the native quantity template.' );
qa_woo_check( 1 === $qa_dom->query( '//label[@for="qa_quantity" and normalize-space(.)!=""]' )->length, 'Quantity input requires a real associated label.' );
qa_woo_check( 2 === $qa_dom->query( '//button[@type="button" and string-length(@aria-label)>0]' )->length, 'Quantity controls require accessible names.' );
$qa_readonly = qa_woo_dom( woocommerce_quantity_input( array( 'input_id' => 'qa_readonly', 'input_value' => 2, 'readonly' => true ), $qa_product, false ) );
qa_woo_check( 1 === $qa_readonly->query( '//input[@id="qa_readonly" and @readonly and @type="text"]' )->length && 0 === $qa_readonly->query( '//button[not(@disabled)]' )->length, 'Native readonly quantity must remain non-editable.' );
$qa_hidden = qa_woo_dom( woocommerce_quantity_input( array( 'input_id' => 'qa_fixed', 'min_value' => 3, 'max_value' => 3, 'input_value' => 3 ), $qa_product, false ) );
qa_woo_check( 1 === $qa_hidden->query( '//input[@id="qa_fixed" and @type="hidden" and @value="3"]' )->length && 0 === $qa_hidden->query( '//button' )->length, 'Fixed quantities must retain the native hidden control.' );
$qa_events = array();
$qa_before = static function() use ( &$qa_events ) { $qa_events[] = 'before'; };
$qa_after = static function() use ( &$qa_events ) { $qa_events[] = 'after'; };
add_action( 'woocommerce_before_quantity_input_field', $qa_before );
add_action( 'woocommerce_after_quantity_input_field', $qa_after );
woocommerce_quantity_input( array(), $qa_product, false );
remove_action( 'woocommerce_before_quantity_input_field', $qa_before );
remove_action( 'woocommerce_after_quantity_input_field', $qa_after );
qa_woo_check( array( 'before', 'after' ) === $qa_events, 'Native quantity extension hooks must each fire once.' );
$qa_cart_actions = 0;
add_action( 'woocommerce_cart_actions', static function() use ( &$qa_cart_actions ) { $qa_cart_actions++; } );
$qa_cart = qa_woo_dom( qa_woo_template( 'cart/cart.php' ) );
qa_woo_check( 1 === $qa_cart->query( '//input[@name="cart[' . $qa_cart_key . '][qty]" and @value="2" and @max="4" and @min="0"]' )->length, 'The real cart must allow removal and retain stock bounds.' );
qa_woo_check( 1 === $qa_cart->query( '//input[@name="woocommerce-cart-nonce"]' )->length, 'The cart must contain one unambiguous native security nonce.' );
qa_woo_check( 1 === $qa_cart_actions, 'The native cart action extension point must not run twice.' );
$qa_checkout = WC()->checkout();
$qa_fields = $qa_checkout->get_checkout_fields();
qa_woo_check( ! isset( $qa_fields['billing']['billing_first_name'], $qa_fields['billing']['billing_last_name'] ) && ! array_key_exists( 'billing_first_name', $qa_fields['billing'] ) && ! array_key_exists( 'shipping_first_name', $qa_fields['shipping'] ), 'Removed name fields must not be recreated as partial fields.' );
qa_woo_check( isset( $qa_fields['billing']['billing_country'], $qa_fields['billing']['billing_postcode'], $qa_fields['billing']['billing_email'] ) && 'TN' === $qa_checkout->get_value( 'billing_country' ), 'Native checkout must retain international fields and the Tunisia country.' );
$qa_checkout_dom = qa_woo_dom( qa_woo_template( 'checkout/form-checkout.php', array( 'checkout' => $qa_checkout ) ) );
qa_woo_check( 1 === $qa_checkout_dom->query( '//select[@name="billing_country"]/option[@value="TN" and @selected]' )->length && 1 === $qa_checkout_dom->query( '//input[@name="billing_postcode" and @value="1001"]' )->length, 'Rendered checkout must retain the fictional Tunisia address.' );
qa_woo_check( 0 === $qa_checkout_dom->query( '//input[@name="billing_first_name" or @name="shipping_first_name"]' )->length, 'Checkout must not render removed name fields.' );
qa_woo_check( 1 === $qa_checkout_dom->query( '//input[@name="woocommerce-process-checkout-nonce"]' )->length && 1 === $qa_checkout_dom->query( '//button[@id="place_order"]' )->length, 'Checkout must render its native security nonce and submit control.' );
$qa_order = new WC_Order(); // In-memory rendering only: never save or process an order.
$qa_order->set_billing_country( 'TN' );
$qa_order->set_billing_city( 'Tunisi' );
$qa_order->set_billing_address_1( 'Indirizzo fittizio 1' );
$qa_order->set_billing_email( 'qa-woo@example.invalid' );
$qa_order->set_billing_phone( '+21620000000' );
$qa_details = qa_woo_template( 'order/order-details-customer.php', array( 'order' => $qa_order ) );
qa_woo_check( str_contains( $qa_details, '+21620000000' ) && str_contains( $qa_details, 'Indirizzo fittizio 1' ) && str_contains( $qa_details, 'qa-woo@example.invalid' ), 'Native customer details must retain the international address and phone.' );
$qa_attributes = qa_woo_template( 'single-product/product-attributes.php', array( 'product_attributes' => array( 'qa' => array( 'label' => 'Materiale', 'value' => '<p>Prova <strong>sicura</strong><script>alert(1)</script></p>' ) ) ) );
qa_woo_check( str_contains( $qa_attributes, '<strong>sicura</strong>' ) && ! str_contains( $qa_attributes, '<script>' ), 'Prepared native attributes must retain permitted HTML and remove scripts.' );
qa_woo_check( 0 === count( wc_get_orders( array( 'limit' => 1, 'return' => 'ids' ) ) ) && 0 === $qa_order->get_id(), 'This qualification must not create an order or invoke a payment.' );
qa_woo_check( is_wp_error( wp_remote_get( 'https://example.invalid/' ) ) && true === wp_mail( 'qa@example.invalid', 'Blocked', 'Blocked' ), 'Outbound HTTP and email must remain blocked.' );
restore_error_handler();
echo wp_json_encode( array( 'passed' => true, 'checks' => $qa_checks, 'woocommerce' => WC_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'theme_warnings' => 0, 'external_notices' => $qa_errors, 'orders_created' => 0, 'payments_invoked' => 0, 'outbound_blocked' => true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
