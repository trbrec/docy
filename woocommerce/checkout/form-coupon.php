<?php
/**
 * Checkout coupon form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-coupon.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.8.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! wc_coupons_enabled() ) { // @codingStandardsIgnoreLine.
	return;
}

?>

<div class="row" id="checkout_top_forms">
    <div class="col-lg-5">
        <div class="checkout_content coupon_form">
            <!-- Coupon code apply form -->
            <div class="return_customer">
                <i class="icon_error-circle_alt"></i>
                <?php echo apply_filters( 'woocommerce_checkout_coupon_message', esc_html__( 'Have a coupon?', 'docy' ) ); ?>
                <a data-bs-toggle="collapse" href="#coupon_two" aria-expanded="false" class="collapsed">
                    <?php esc_html_e( 'Click here to enter your code', 'docy' ); ?>
                </a>
            </div>

            <div CLASS="collapse tab_content" id="coupon_two">
                <p class="f_p f_300"> <?php esc_html_e( 'If you have a coupon code, please apply it below.', 'docy' ) ?> </p>
                <form class="login_form coupon_form" method="post">
                    <!--<p><?php /*esc_html_e( 'If you have a coupon code, please apply it below.', 'docy' ); */?></p>-->
                    <input type="text" name="coupon_code" class="form-control" placeholder="<?php esc_attr_e( 'Coupon code', 'docy' ); ?>" id="coupon_code" value="" />
                    <button type="submit" class="login_btn fill-brand" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'docy' ); ?>">
                        <?php esc_html_e( 'Apply', 'docy' ); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>