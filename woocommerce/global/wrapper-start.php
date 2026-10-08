<?php
/**
 * Content wrappers
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/global/wrapper-start.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see           https://docs.woocommerce.com/document/template-structure/
 * @author        WooThemes
 * @package       WooCommerce/Templates
 * @version       3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$template = wc_get_theme_slug_for_templates();

$wrap_class = '';

if ( is_shop() || is_product_taxonomy() ) {
	$wrap_class = 'shop_grid_area';
} elseif ( is_singular( 'product' ) ) {
	$wrap_class = 'product_details_area';
} elseif ( is_checkout() ) {
	$wrap_class = 'checkout_area bg_color_gradient';
} else {
	$wrap_class = 'page_wrapper';
}
?>

<section class="sec_pad <?php echo esc_attr($wrap_class) ?>">
	<div class="container ect">