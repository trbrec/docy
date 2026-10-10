<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$opt = get_option( 'docy_opt' );

// Re-arrange the related products, upsell product
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
add_action( 'woocommerce_single_product_after_main_content', 'woocommerce_upsell_display', 20 );
add_action( 'woocommerce_single_product_after_main_content', 'woocommerce_output_related_products', 25 );

// Enabling the gallery in themes that declare
add_theme_support( 'wc-product-gallery-zoom' );
add_theme_support( 'wc-product-gallery-lightbox' );
add_theme_support( 'wc-product-gallery-slider' );

/**
 * Product columns
 *
 * @return int|string
 */
function docy_woocommerce_loop_columns() {
	$opt         = get_option( 'docy_opt' );
	$shop_layout = ! empty( $opt['shop_layout'] ) ? $opt['shop_layout'] : 'shop_grid';
	$view_style  = ! empty( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : '';

	// Check if current view is list layout
	$is_list_view = false;
	if ( 'shop_list' === $shop_layout && 'grid' !== $view_style ) {
		$is_list_view = true;
	} elseif ( 'list' === $view_style ) {
		$is_list_view = true;
	}

	// Only apply column setting for grid layout
	if ( $is_list_view ) {
		return 1; // Always 1 column for list view
	}

	$columns = isset( $opt['shop_grid_columns'] ) ? $opt['shop_grid_columns'] : '3';
	return $columns;
}
add_filter( 'loop_shop_columns', 'docy_woocommerce_loop_columns' );

// Product Gallery thumbnail size
add_filter( 'woocommerce_get_image_size_gallery_thumbnail', function( $size ) {
	return [
		'width'  => 120,
		'height' => 140,
		'crop'   => 1,
	];
} );

/**
 * WooCommerce review list
 *
 * @param object $comment The comment object.
 * @param array  $args    Arguments.
 * @param int    $depth   Depth.
 */
function docy_woocommerce_comments( $comment, $args, $depth ) {
	$GLOBALS['comment'] = $comment;
	?>
	<li class="post-comment" id="comment-<?php comment_ID() ?>">
		<div class="comment-content">
			<a href="#" class="avatar">
				<?php echo get_avatar( $comment, 70 ); ?>
			</a>
			<div class="post-body">
				<div class="comment-header">
					<a href="#"> <?php comment_author(); ?> </a>
					<?php echo get_comment_time( get_option( 'date_format' ) ); ?>
				</div>
				<div class="rating">
					<?php woocommerce_review_display_rating() ?>
				</div>
				<?php comment_text() ?>
				<div class="hr mt_30 mb-0"></div>
			</div>
		</div>
	</li>
	<?php
}

/**
 * Add/remove fields from checkout
 *
 * @param array $fields Checkout fields.
 * @return array
 */
function docy_disable_checkout_fields( $fields ) {
	$remove_fields = docy_opt( 'remove_checkout_fields' );
	// Unset $remove_fields with foreach loop
	if ( ! empty( $remove_fields ) ) {
		foreach ( $remove_fields as $field ) {
			unset( $fields['billing'][ 'billing_' . $field ] );
			unset( $fields['shipping'][ 'shipping_' . $field ] );
		}
	}

	// if the last name is unset then the first name will be full width
	if ( isset( $fields['billing']['billing_last_name'] ) ) {
		$fields['billing']['billing_first_name']['class']   = [ 'col-md-6' ];
		$fields['shipping']['shipping_first_name']['class'] = [ 'col-md-6' ];
	} else {
		$fields['billing']['billing_first_name']['class']   = [ 'col-md-12' ];
		$fields['shipping']['shipping_first_name']['class'] = [ 'col-md-12' ];
	}
	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'docy_disable_checkout_fields' );


// Ajax add to cart
/**
 * Get the "add to cart" button.
 *
 * @param \WC_Product $product Product.
 * @return string Rendered product output.
 */
function docy_get_add_to_cart( $product ) {
	$attributes = [
		'aria-label'       => $product->add_to_cart_description(),
		'data-quantity'    => '1',
		'data-product_id'  => $product->get_id(),
		'data-product_sku' => $product->get_sku(),
		'data-price'       => wc_get_price_to_display( $product ),
		'rel'              => 'nofollow',
		'class'            => ( function_exists( 'wc_wp_theme_get_element_class_name' ) ? wc_wp_theme_get_element_class_name( 'button' ) : '' ) . ' add_to_cart_button',
	];

	if (
		$product->supports( 'ajax_add_to_cart' ) &&
		$product->is_purchasable() &&
		( $product->is_in_stock() || $product->backorders_allowed() )
	) {
		$attributes['class'] .= ' ajax_add_to_cart';
	}

	return sprintf(
		'<a href="%s" %s>%s</a>',
		esc_url( $product->add_to_cart_url() ),
		wc_implode_html_attributes( $attributes ),
		esc_html( $product->add_to_cart_text() )
	);
}

/**
 * AJAX Handler for Buy Now functionality
 */
function docy_buy_now_add_to_cart() {
	// Verify nonce
	if ( ! isset( $_POST['nonce'] ) || ! is_string( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'docy-buy-now-nonce' ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Controllo di sicurezza fallito. Ricarica la pagina e riprova.', 'docy' ) ], 403 );
	}

	foreach ( array( 'product_id', 'quantity', 'variation_id' ) as $key ) {
		if ( isset( $_POST[ $key ] ) && ( ! is_string( $_POST[ $key ] ) || ! ctype_digit( $_POST[ $key ] ) || (float) $_POST[ $key ] >= PHP_INT_MAX ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Prodotto o quantità non validi.', 'docy' ) ], 400 );
		}
	}
	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	$quantity = isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : 1;
	$variation_id = isset( $_POST['variation_id'] ) ? (int) $_POST['variation_id'] : 0;
	$variation = $_POST['variation'] ?? array();
	if ( ! $product_id || ! $quantity || ! is_array( $variation ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Prodotto o quantità non validi.', 'docy' ) ], 400 );
	}
	foreach ( $variation as $key => $value ) {
		if ( ! is_string( $key ) || ! is_string( $value ) ) wp_send_json_error( [ 'message' => esc_html__( 'Variante del prodotto non valida.', 'docy' ) ], 400 );
	}
	$woocommerce = function_exists( 'WC' ) ? WC() : null;
	$cart = is_object( $woocommerce ) ? ( $woocommerce->cart ?? null ) : null;
	if ( ! is_object( $cart ) || ! is_callable( array( $cart, 'add_to_cart' ) ) || ! function_exists( 'wc_clean' ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Carrello non disponibile. Riprova più tardi.', 'docy' ) ], 503 );
	}
	$variation = wc_clean( wp_unslash( $variation ) );

	// Add product to cart
	if ( $variation_id ) {
		// Variable product
		$cart_item_key = $cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
	} else {
		// Simple product
		$cart_item_key = $cart->add_to_cart( $product_id, $quantity );
	}

	if ( is_string( $cart_item_key ) && '' !== $cart_item_key ) {
		wp_send_json_success( [ 'message' => esc_html__( 'Prodotto aggiunto al carrello.', 'docy' ) ] );
	} else {
		wp_send_json_error( [ 'message' => esc_html__( 'Impossibile aggiungere il prodotto al carrello. Riprova.', 'docy' ) ], 409 );
	}
}
add_action( 'wp_ajax_docy_buy_now_add_to_cart', 'docy_buy_now_add_to_cart' );
add_action( 'wp_ajax_nopriv_docy_buy_now_add_to_cart', 'docy_buy_now_add_to_cart' );
