<?php
global $wc_loop_i;
$short_description = apply_filters( 'woocommerce_short_description', $post->post_excerpt );
$opt = get_option( 'docy_opt' );
global $product;
?>
<li <?php wc_product_class( 'row shop_list_item' ); ?>>
    <div class="col-md-5 col-lg-4">
        <div class="shop_list_img">
            <a href="<?php the_permalink() ?>">
                <?php the_post_thumbnail( 'docy_450x420', array( 'class' => 'img-fluid')) ?>
            </a>
        </div>
    </div>
    <div class="col-md-7 col-lg-8 single_product_item mt-0">
        <div class="single_pr_details text-left">
            <a href="<?php the_permalink() ?>" class="s_list_title">
                <h3 class="f_p f_500 f_size_22"> <?php the_title(); ?> </h3>
            </a>

            <?php woocommerce_template_single_rating() ?>

            <?php woocommerce_template_loop_price(); ?>

            <p class="f_p f_300 f_size_15"> <?php echo wp_trim_words( get_the_excerpt(), 20, '...' ) ?> </p>
            
            <div class="pr_button">
                <a href="?add-to-cart=<?php echo get_the_ID() ?>" title="<?php echo esc_attr($product->single_add_to_cart_text()) ?>" class="cart_btn">
                    <?php esc_html_e( 'Add to cart', 'docy' ) ?>
                </a>
                <?php echo shortcode_exists( 'ti_wishlists_addtowishlist' ) ? do_shortcode( '[ti_wishlists_addtowishlist]' ) : ''; ?>
            </div>
        </div>
    </div>
</li>