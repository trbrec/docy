<?php
/**
 * Product quantity inputs
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/global/quantity-input.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.1.0
 */

defined( 'ABSPATH' ) || exit;

/* translators: %s: Product name. */
$label = ! empty( $args['product_name'] ) ? sprintf( __( '%s quantity', 'docy' ), wp_strip_all_tags( $args['product_name'] ) ) : __( 'Quantità', 'docy' );
$adjustable = 'hidden' !== $type && ! $readonly;
?>
	<div class="product-qty quantity<?php echo 'hidden' === $type ? ' hidden' : ''; ?>">
        <?php do_action( 'woocommerce_before_quantity_input_field' ); ?>
        <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $label ); ?></label>
        <?php if ( $adjustable ) : ?>
        <button class="ar_top" type="button" aria-label="<?php esc_attr_e( 'Aumenta la quantità', 'docy' ); ?>"><i class="arrow_carrot-up" aria-hidden="true"></i></button>
        <?php endif; ?>
        <input
			type="<?php echo esc_attr( $type ); ?>"
			<?php echo $readonly ? 'readonly="readonly"' : ''; ?>
			id="<?php echo esc_attr( $input_id ); ?>"
			class="<?php echo esc_attr( implode( ' ', array_unique( array_merge( (array) $classes, array( 'qty', 'manual-adjust' ) ) ) ) ); ?>"
			step="<?php echo esc_attr( $step ); ?>"
			min="<?php echo esc_attr( $min_value ); ?>"
			max="<?php echo esc_attr( 0 < $max_value ? $max_value : '' ); ?>"
			name="<?php echo esc_attr( $input_name ); ?>"
			value="<?php echo esc_attr( $input_value ); ?>"
			title="<?php echo esc_attr_x( 'Qty', 'Product quantity input tooltip', 'docy' ); ?>"
			pattern="<?php echo esc_attr( $pattern ); ?>"
			inputmode="<?php echo esc_attr( $inputmode ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			autocomplete="<?php echo esc_attr( $autocomplete ?? 'off' ); ?>" />
        <?php if ( $adjustable ) : ?>
        <button class="ar_down" type="button" aria-label="<?php esc_attr_e( 'Diminuisci la quantità', 'docy' ); ?>"><i class="arrow_carrot-down" aria-hidden="true"></i></button>
        <?php endif; ?>
        <?php do_action( 'woocommerce_after_quantity_input_field' ); ?>
	</div>
