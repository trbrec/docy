<?php
/** Real plugin callers and WordPress loaders, confined to the native disposable fixture. */
if ( 'cli' !== PHP_SAPI || ! isset( $qa_theme, $qa_checks, $qa_product, $qa_user ) ) throw new RuntimeException( 'Native WooCommerce fixture required.' );
$qa_contract_start = $qa_checks;
$qa_contract_failures = array();
function qa_woo_contract_check( $condition, $message ) {
    if ( ! $condition ) { $GLOBALS['qa_contract_failures'][] = $message; fwrite( STDERR, 'Template contract failed: ' . $message . "\n" ); return; }
    qa_woo_check( $condition, $message );
}
$qa_template_contract_paths = array();
$qa_observe_template = static function( $name ) use ( &$qa_template_contract_paths ) { $qa_template_contract_paths[ $name ] = true; };
add_action( 'woocommerce_before_template_part', $qa_observe_template );
function qa_woo_capture( $callback ) {
    ob_start();
    try { $callback(); return ob_get_contents(); }
    finally { ob_end_clean(); }
}
// Native get_template_part()/load_template() must load this theme's product card paths.
$qa_old_stylesheet_path = $GLOBALS['wp_stylesheet_path'];
$qa_old_template_path = $GLOBALS['wp_template_path'];
$GLOBALS['wp_stylesheet_path'] = $qa_theme;
$GLOBALS['wp_template_path'] = $qa_theme;
$qa_template_part_filter = static function( $template, $slug, $name ) use ( $qa_theme ) {
    $candidate = $qa_theme . '/woocommerce/' . $slug . ( $name ? '-' . $name : '' ) . '.php';
    return is_file( $candidate ) ? $candidate : $template;
};
add_filter( 'wc_get_template_part', $qa_template_part_filter, 10, 3 );
$qa_external = new WC_Product_External();
$qa_external->set_name( 'Fornitore fittizio' );
$qa_external->set_status( 'publish' );
$qa_external->set_regular_price( '12.00' );
$qa_external->set_product_url( 'https://example.invalid/prodotto?campagna=qa&origine=test' );
$qa_external->set_button_text( 'Visita il fornitore' );
$qa_external->save();
$GLOBALS['product'] = $qa_external;
$GLOBALS['post'] = get_post( $qa_external->get_id() );
setup_postdata( $GLOBALS['post'] );
$qa_loop_external = qa_woo_dom( qa_woo_capture( static function() { woocommerce_template_loop_add_to_cart(); } ) );
qa_woo_contract_check( 1 === $qa_loop_external->query( '//a[@aria-describedby]/following-sibling::span[@class="screen-reader-text"]' )->length, 'External loop control must preserve the native description and its associated ID.' );
$qa_list_external = qa_woo_dom( qa_woo_capture( static function() { get_template_part( 'woocommerce/wc-template-parts/content', 'list' ); } ) );
qa_woo_contract_check( 1 === $qa_list_external->query( '//div[@class="pr_button"]/a[@href="https://example.invalid/prodotto?campagna=qa&origine=test" and normalize-space(.)="Visita il fornitore"]' )->length, 'List cards must use the external product URL and its native action text.' );
$qa_external_form = qa_woo_dom( qa_woo_capture( static function() { woocommerce_external_add_to_cart(); } ) );
qa_woo_contract_check( 1 === $qa_external_form->query( '//form[@method="get" and @action="https://example.invalid/prodotto?campagna=qa&origine=test"]' )->length && 0 === $qa_external_form->query( '//button[@name="add-to-cart"]' )->length && 2 === $qa_external_form->query( '//input[@name="campagna" or @name="origine"]' )->length, 'External forms must preserve provider query fields without adding a local add-to-cart request.' );

$qa_variable = new WC_Product_Variable();
$qa_variable->set_name( 'Formato fittizio' );
$qa_variable->set_status( 'publish' );
$qa_attribute = new WC_Product_Attribute();
$qa_attribute->set_name( 'Formato' );
$qa_attribute->set_options( array( 'Digitale & speciale' ) );
$qa_attribute->set_visible( true );
$qa_attribute->set_variation( true );
$qa_variable->set_attributes( array( $qa_attribute ) );
$qa_variable->save();
$qa_variation = new WC_Product_Variation();
$qa_variation->set_parent_id( $qa_variable->get_id() );
$qa_variation->set_attributes( array( 'formato' => 'Digitale & speciale' ) );
$qa_variation->set_regular_price( '15.00' );
$qa_variation->set_status( 'publish' );
$qa_variation->save();
WC_Product_Variable::sync( $qa_variable->get_id() );
$GLOBALS['product'] = wc_get_product( $qa_variable->get_id() );
$GLOBALS['post'] = get_post( $qa_variable->get_id() );
setup_postdata( $GLOBALS['post'] );
$qa_variable_dom = qa_woo_dom( qa_woo_capture( static function() { woocommerce_variable_add_to_cart(); } ) );
$qa_variation_data = json_decode( $qa_variable_dom->query( '//form[@data-product_variations]' )->item( 0 )->getAttribute( 'data-product_variations' ), true, 512, JSON_THROW_ON_ERROR );
qa_woo_contract_check( 1 === count( $qa_variation_data ) && $qa_variation->get_id() === $qa_variation_data[0]['variation_id'] && 'Digitale & speciale' === $qa_variation_data[0]['attributes']['attribute_formato'], 'Native variation JSON must survive HTML attribute escaping unchanged.' );
qa_woo_contract_check( 1 === $qa_variable_dom->query( '//select[@name="attribute_formato"]/option[@value="Digitale & speciale"]' )->length, 'Native variable attributes must reach the selection control.' );
$qa_variable_list = qa_woo_dom( qa_woo_capture( static function() { get_template_part( 'woocommerce/wc-template-parts/content', 'list' ); } ) );
qa_woo_contract_check( 1 === $qa_variable_list->query( '//div[@class="pr_button"]/a[@href="' . $qa_variable->add_to_cart_url() . '"]' )->length, 'Variable list cards must navigate to variation selection using the native product URL.' );

$qa_grouped = new WC_Product_Grouped();
$qa_grouped->set_name( 'Gruppo fittizio' );
$qa_grouped->set_status( 'publish' );
$qa_grouped->set_children( array( $qa_product->get_id() ) );
$qa_grouped->save();
$GLOBALS['product'] = $qa_grouped;
$GLOBALS['post'] = get_post( $qa_grouped->get_id() );
setup_postdata( $GLOBALS['post'] );
$qa_grouped_dom = qa_woo_dom( qa_woo_capture( static function() { woocommerce_grouped_add_to_cart(); } ) );
qa_woo_contract_check( 1 === $qa_grouped_dom->query( '//input[@name="quantity[' . $qa_product->get_id() . ']" and @min="0" and @max="4"]' )->length && 1 === $qa_grouped_dom->query( '//input[@name="add-to-cart" and @value="' . $qa_grouped->get_id() . '"]' )->length, 'Native grouped child quantities must retain purchase bounds and their parent product.' );

$GLOBALS['product'] = $qa_product;
$GLOBALS['post'] = get_post( $qa_product->get_id() );
setup_postdata( $GLOBALS['post'] );
$qa_item_data_filter = static function( $data ) { $data[] = array( 'key' => 'Prova', 'value' => 'Dato sintetico' ); return $data; };
add_filter( 'woocommerce_get_item_data', $qa_item_data_filter );
$qa_item_data = wc_get_formatted_cart_item_data( WC()->cart->get_cart_item( $qa_cart_key ) );
remove_filter( 'woocommerce_get_item_data', $qa_item_data_filter );
qa_woo_contract_check( str_contains( $qa_item_data, 'Dato sintetico' ) && str_contains( $qa_item_data, '<dl' ), 'Native cart metadata must reach its template.' );
qa_woo_contract_check( str_contains( wc_get_stock_html( $qa_product ), 'in-stock' ), 'Native stock availability and CSS class must reach their template.' );
$qa_global_stock_product = new WC_Product_Simple();
$qa_global_stock_product->set_stock_status( 'outofstock' );
$GLOBALS['product'] = $qa_global_stock_product;
$qa_child_stock = qa_woo_dom( wc_get_stock_html( $qa_product ) );
qa_woo_contract_check( 1 === $qa_child_stock->query( '//span[contains(@class,"in-stock")]/i' )->length, 'Stock rendering must use the product passed by the native caller, independently of the global product.' );
$GLOBALS['product'] = $qa_product;
$qa_product->set_upsell_ids( array( $qa_external->get_id() ) );
$qa_product->set_cross_sell_ids( array( $qa_external->get_id() ) );
$qa_product->save();
qa_woo_contract_check( str_contains( qa_woo_capture( static function() { woocommerce_upsell_display(); } ), 'Fornitore fittizio' ), 'Native upsell objects must render through WordPress product card loading.' );
WC()->cart->get_cart_contents()[ $qa_cart_key ]['data']->set_cross_sell_ids( array( $qa_external->get_id() ) );
qa_woo_contract_check( str_contains( qa_woo_capture( static function() { woocommerce_cross_sell_display(); } ), 'Fornitore fittizio' ), 'Native cart cross-sell objects must reach the theme template.' );
$qa_related_filter = static function() use ( $qa_external ) { return array( $qa_external->get_id() ); };
add_filter( 'woocommerce_related_products', $qa_related_filter );
qa_woo_contract_check( str_contains( qa_woo_capture( static function() { woocommerce_related_products(); } ), 'Fornitore fittizio' ), 'Native related-product objects must render through the custom grid.' );
remove_filter( 'woocommerce_related_products', $qa_related_filter );
$qa_category = wp_insert_term( 'Categoria fittizia', 'product_cat' );
wp_set_object_terms( $qa_product->get_id(), $qa_category['term_id'], 'product_cat' );
$qa_categories = qa_woo_capture( static function() use ( $qa_category ) { echo do_shortcode( '[product_categories hide_empty="0" ids="' . $qa_category['term_id'] . '"]' ); } );
qa_woo_contract_check( str_contains( $qa_categories, 'Categoria fittizia' ), 'Native product category shortcode must provide the category term object.' );
$qa_widget = new WC_Widget_Products();
$qa_widget_html = qa_woo_capture( static function() use ( $qa_widget ) { $qa_widget->widget( array( 'before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => '', 'widget_id' => 'qa-products' ), array( 'number' => 10, 'show' => '', 'orderby' => 'date', 'order' => 'desc', 'hide_free' => false, 'show_hidden' => false ) ); } );
qa_woo_contract_check( str_contains( $qa_widget_html, 'Fornitore fittizio' ), 'Real product widget must supply its native arguments and product object.' );
wc_set_loop_prop( 'total', 21 );
wc_set_loop_prop( 'per_page', 10 );
wc_set_loop_prop( 'current_page', 2 );
$qa_result_count = html_entity_decode( strip_tags( qa_woo_capture( static function() { woocommerce_result_count(); } ) ), ENT_QUOTES, 'UTF-8' );
qa_woo_contract_check( str_contains( $qa_result_count, '11' ) && str_contains( $qa_result_count, '20' ) && str_contains( $qa_result_count, '21' ), 'Native pagination arguments must retain the displayed result interval.' );
qa_woo_contract_check( 1 === qa_woo_dom( qa_woo_capture( static function() { woocommerce_catalog_ordering(); } ) )->query( '//select[@name="orderby"]/option[@selected]' )->length, 'Native catalog ordering must render one selected sorting option.' );

wp_set_current_user( 0 );
$qa_login = qa_woo_dom( qa_woo_capture( static function() { woocommerce_login_form( array( 'hidden' => true, 'redirect' => 'https://example.invalid/ritorno', 'message' => 'Messaggio fittizio' ) ); } ) );
qa_woo_contract_check( 1 === $qa_login->query( '//form[contains(@style,"display:none")]//input[@name="redirect" and @value="https://example.invalid/ritorno"]' )->length, 'Native login defaults and supplied visibility/redirect arguments must reach the template.' );
wp_set_current_user( $qa_user );
$qa_reset = qa_woo_dom( qa_woo_template( 'myaccount/form-reset-password.php', array( 'key' => 'synthetic-render-marker', 'login' => 'qa_woo_tunisia' ) ) );
qa_woo_contract_check( 1 === $qa_reset->query( '//input[@name="reset_key" and @value="synthetic-render-marker"]' )->length && 1 === $qa_reset->query( '//input[@name="reset_login" and @value="qa_woo_tunisia"]' )->length, 'Reset template must preserve its caller arguments without submitting a password change.' );
$qa_comment_id = wp_insert_comment( array( 'comment_post_ID' => $qa_product->get_id(), 'comment_author' => 'Recensore fittizio', 'comment_author_email' => 'qa-review@example.invalid', 'comment_content' => 'Recensione sintetica', 'comment_approved' => 1, 'comment_type' => 'review' ) );
$qa_review = qa_woo_dom( qa_woo_capture( static function() use ( $qa_comment_id ) { woocommerce_comments( get_comment( $qa_comment_id ), array(), 1 ); } ) );
qa_woo_contract_check( 1 === $qa_review->query( '//*[@id="comment-' . $qa_comment_id . '"]' )->length && 1 === $qa_review->query( '//li[@id="li-comment-' . $qa_comment_id . '"]' )->length, 'Native review anchors must have unique IDs in their DOM.' );

$GLOBALS['product'] = $qa_product;
$qa_product->set_virtual( false );
$qa_product->save();
WC()->cart->get_cart_contents()[ $qa_cart_key ]['data']->set_virtual( false );
WC()->customer->set_shipping_country( 'TN' );
WC()->customer->set_shipping_city( 'Tunisi' );
WC()->customer->set_shipping_postcode( '1001' );
WC()->customer->set_shipping_address_1( 'Indirizzo spedizione sintetico' );
WC()->customer->save();
$qa_shipping_zone = new WC_Shipping_Zone( 0 );
$qa_shipping_zone->add_shipping_method( 'flat_rate' );
WC()->cart->calculate_totals();
qa_woo_contract_check( WC()->cart->needs_shipping_address(), 'A physical-product cart with a real local flat-rate method must require a shipping address.' );
$qa_shipping_previous_post = $_POST;
// Use the native posted-value branch: WC_Checkout intentionally caches its customer within a request.
$_POST = array_merge( $_POST, array( 'shipping_country' => 'TN', 'shipping_postcode' => '1001', 'shipping_city' => 'Tunisi', 'shipping_address_1' => 'Indirizzo spedizione sintetico' ) );
$qa_shipping = qa_woo_dom( qa_woo_capture( static function() { WC()->checkout()->checkout_form_shipping(); } ) );
$_POST = $qa_shipping_previous_post;
qa_woo_contract_check( 1 === $qa_shipping->query( '//select[@name="shipping_country"]/option[@value="TN" and @selected]' )->length && 1 === $qa_shipping->query( '//input[@name="shipping_postcode" and @value="1001"]' )->length, 'Physical-product checkout must preserve its separate Tunisia shipping address.' );
qa_woo_contract_check( 0 === count( wc_get_orders( array( 'limit' => 1, 'return' => 'ids' ) ) ), 'Template qualification must not create orders or invoke payment processing.' );
remove_action( 'woocommerce_before_template_part', $qa_observe_template );
remove_filter( 'wc_get_template_part', $qa_template_part_filter );
$GLOBALS['wp_stylesheet_path'] = $qa_old_stylesheet_path;
$GLOBALS['wp_template_path'] = $qa_old_template_path;
$qa_template_contract_paths = array_keys( $qa_template_contract_paths );
sort( $qa_template_contract_paths );
$qa_template_contract_checks = $qa_checks - $qa_contract_start;
if ( $qa_contract_failures ) throw new RuntimeException( implode( "\n", $qa_contract_failures ) );
