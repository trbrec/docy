<?php
/**
 * Single Product Meta
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/meta.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     9.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

$sku_v = ( $sku = $product->get_sku() ) ? $sku : esc_html__( 'N/A', 'docy' );
$product_url      = get_permalink();
$facebook_share   = 'https://facebook.com/sharer/sharer.php?u=' . rawurlencode( $product_url );
$twitter_share    = 'https://x.com/intent/tweet?text=' . rawurlencode( $product_url );
$pinterest_share  = 'https://www.pinterest.com/pin/create/button/?url=' . rawurlencode( $product_url );
$linkedin_share   = 'https://www.linkedin.com/shareArticle?mini=true&url=' . rawurlencode( $product_url );

?>
<div class="pr_footer mt_40 mb-30">

    <ul class="product_meta list-unstyled">

        <?php do_action( 'woocommerce_product_meta_start' ); ?>

        <?php if ( wc_product_sku_enabled() && ( $product->get_sku() || $product->is_type( 'variable' ) ) ) : ?>

            <li class="sku_wrapper"> <span> <?php esc_html_e( 'SKU:', 'docy' ); ?> </span> <?php echo wp_kses_post($sku_v) ?></li>

        <?php endif; ?>

        <?php echo wc_get_product_category_list( $product->get_id(), ', ', '<li class="posted_in"> <span>' . _n( 'Category:', 'Categories:', count( $product->get_category_ids() ), 'docy' ) .'</span>' , '</li>' ); ?>

        <?php echo wc_get_product_tag_list( $product->get_id(), ', ', '<li class="tagged_as"><span>' . _n( 'Tag:', 'Tags: ', count( $product->get_tag_ids() ), 'docy' ) . '</span>', '</li>' ); ?>

        <?php do_action( 'woocommerce_product_meta_end' ); ?>

    </ul>

    <div class="share-link">
        <label> <?php esc_html_e( 'Share ON:', 'docy' ) ?> </label>
        <ul class="social-icon list-unstyled">
            <li><a class="facebook" title="<?php esc_attr_e( 'Facebook', 'docy' ); ?>" href="<?php echo esc_url( $facebook_share ); ?>" target="_blank" rel="noopener noreferrer"><i class="social_facebook"> </i><span class="screen-reader-text"><?php esc_html_e( 'Share on Facebook (opens in a new tab)', 'docy' ); ?></span></a></li>
            <li><a class="twitter" title="<?php esc_attr_e( 'Twitter (X)', 'docy' ); ?>" href="<?php echo esc_url( $twitter_share ); ?>" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-x-twitter"> </i><span class="screen-reader-text"><?php esc_html_e( 'Share on Twitter (X) (opens in a new tab)', 'docy' ); ?></span></a></li>
            <li><a class="pinterest" title="<?php esc_attr_e( 'Pinterest', 'docy' ); ?>" href="<?php echo esc_url( $pinterest_share ); ?>" target="_blank" rel="noopener noreferrer"><i class="social_pinterest"> </i><span class="screen-reader-text"><?php esc_html_e( 'Share on Pinterest (opens in a new tab)', 'docy' ); ?></span></a></li>
            <li><a class="linkedin" title="<?php esc_attr_e( 'LinkedIn', 'docy' ); ?>" href="<?php echo esc_url( $linkedin_share ); ?>" target="_blank" rel="noopener noreferrer"><i class="social_linkedin"> </i><span class="screen-reader-text"><?php esc_html_e( 'Share on LinkedIn (opens in a new tab)', 'docy' ); ?></span></a></li>
        </ul>
    </div>
</div>