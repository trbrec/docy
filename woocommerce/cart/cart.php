<?php
/**
 * Cart Page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.8.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' ); ?>

    <form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">

        <?php do_action( 'woocommerce_before_cart_table' ); ?>

        <!-- Left 8 columns, right 4 columns -->
        <div class="row">
            <div class="col-lg-8 table-responsive">
                <table class="table cart cart_table mb-0 shop_table_responsive woocommerce-cart-form__contents">
                    <thead>
                    <tr>
                        <th class="product-name" colspan="2"> <?php esc_html_e( 'PRODUCT', 'docy' ) ?> </th>
                        <th class="product-price"> <?php esc_html_e( 'PRICE', 'docy' ) ?> </th>
                        <th class="product-quantity"> <?php esc_html_e( 'QUANTITY', 'docy' ) ?> </th>
                        <th class="product-subtotal"> <?php esc_html_e( 'TOTAL', 'docy' ) ?> </th>
                        <th class="product-remove">&nbsp;</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    do_action( 'woocommerce_before_cart_contents' );

                    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                        $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                        $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

                        if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                            $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
                            ?>
                            <tr>
                                <td class="product-thumbnail <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>" data-title="<?php esc_attr_e( 'PRODUCT', 'docy' ) ?>">
                                    <?php
                                    $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image('docy_60x60'), $cart_item, $cart_item_key );

                                    if ( ! $product_permalink ) {
                                        echo apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image('docy_60x60'), $cart_item, $cart_item_key );
                                    } else {
                                        printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail );
                                    }
                                    ?>
                                </td>
                                <td class="product-name <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>" data-title="<?php esc_attr_e( 'PRODUCT', 'docy' ) ?>">

                                    <?php
                                    if ( ! $product_permalink ) {
                                        echo apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;';
                                    } else {
                                        echo apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key );
                                    }

                                    do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );

                                    // Meta data.
                                    echo wc_get_formatted_cart_item_data( $cart_item ); // PHPCS: XSS ok.

                                    // Backorder notification.
                                    if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
                                        echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'docy' ) . '</p>' ) );
                                    }
                                    ?>
                                </td>
                                <td class="crat-price " data-title="<?php esc_attr_e( 'PRICE', 'docy' ) ?>">
                                    <div class="total"> <?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); ?> </div>
                                </td>
                                <td class="cart-quantity" data-title="<?php esc_attr_e( 'QUANTITY', 'docy' ) ?>">
                                    <div class="quantity">
                                        <div class="product-qty">
                                            <?php
                                            if ( $_product->is_sold_individually() ) {
                                                $product_quantity = sprintf( '1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key );
                                            } else {
                                                $product_quantity = woocommerce_quantity_input( array(
                                                    'input_name'   => "cart[{$cart_item_key}][qty]",
                                                    'input_value'  => $cart_item['quantity'],
                                                    'max_value'    => $_product->get_max_purchase_quantity(),
                                                    'min_value'    => '0',
                                                    'product_name' => $_product->get_name(),
                                                ), $_product, false );
                                            }
                                            echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // PHPCS: XSS ok.
                                            ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="cart-total " data-title="<?php esc_attr_e( 'TOTAL', 'docy' ) ?>">
                                    <span class="total">
                                        <?php
                                        echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // PHPCS: XSS ok.
                                        ?>
                                    </span>
                                </td>
                                <td class="del-item product-remove">
                                    <?php
                                    // @codingStandardsIgnoreLine
                                    echo apply_filters( 'woocommerce_cart_item_remove_link', sprintf(
                                        '<a href="%s" class="cart_remove" aria-label="%s" data-product_id="%s" data-product_sku="%s"> <i class="icon_close"></i> </a>',
                                        esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                        esc_html__( 'Remove this item', 'docy' ),
                                        esc_attr( $product_id ),
                                        esc_attr( $_product->get_sku() )
                                    ), $cart_item_key );
                                    ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    do_action( 'woocommerce_cart_contents' ); ?>
                    </tbody>
                </table>
                <div class="actions mt-4">
                    <div class="update-cart-area">
                        <a href="<?php echo get_permalink( wc_get_page_id( 'shop' ) ) ?>" class="cart_btn fill-brand"> <?php esc_html_e( 'Continue Shopping', 'docy' ) ?> </a>
                        <button type="submit" class="cart_btn cart_btn_two update-cart btn-outline-gray" name="update_cart" value="Update cart">
                            <?php esc_html_e( 'Update cart', 'docy' ) ?>
                        </button>
                        <?php do_action( 'woocommerce_cart_actions' );
                        wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
                    </div>
                    <h5 class="mt-5">
                        <?php esc_html_e( 'Discount Code:', 'docy' ); ?>
                    </h5>
                    <?php if ( wc_coupons_enabled() ) { ?>
                        <div class="coupon">
                            <input type="text" name="coupon_code" class="input_text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Enter your coupon code', 'docy' ); ?>" />
                            <button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'docy' ); ?>"><?php esc_html_e( 'Apply', 'docy' ); ?></button>
                            <?php do_action( 'woocommerce_cart_coupon' ); ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
            <div class="col-lg-4 col-md-6">
                <div class="cart_box bs-sm position-sticky">
                    <?php
                    /**
                     * Cart collaterals hook.
                     *
                     * @hooked woocommerce_cross_sell_display
                     * @hooked woocommerce_cart_totals - 10
                     */
                    do_action( 'woocommerce_cart_collaterals' );
                    ?>
                </div>
            </div>
        </div>

        <?php do_action( 'woocommerce_after_cart_contents' ); ?>
        <?php do_action( 'woocommerce_after_cart_table' ); ?>
    </form>

<?php
do_action( 'woocommerce_after_cart' );
