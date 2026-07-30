<?php
global $wc_loop_i;
global $product;

use Automattic\WooCommerce\Blocks\BlockTypes\AbstractProductGrid;

$opt    = get_option( 'docy_opt' );
$column = wc_get_loop_prop( 'columns' );

switch ( $column ) {

	case '3':
		$image_size = 'docy_370x360';
		break;

	case '2':
		$image_size = 'full';
		break;

	default:
		$image_size = 'docy_300x320';
		break;
}

?>
<li class="single_product_item">
    <a class="product_img" href="<?php the_permalink() ?>">
		<?php the_post_thumbnail( $image_size, array( 'class' => 'img-fluid' ) ) ?>
    </a>
    <div class="single_pr_details">
        <a href="<?php the_permalink() ?>" class="title" title="<?php the_title_attribute() ?>">
			<?php docy_limit_letter( get_the_title(), 48 ) ?>
        </a>
		<?php woocommerce_template_loop_price(); ?>
		<?php woocommerce_template_single_rating() ?>
		<?php echo docy_get_add_to_cart( $product ); ?>
    </div>
</li>