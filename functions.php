<?php

if ( ! function_exists( 'custom_subscription_price_text' ) ) {
	function custom_subscription_price_text( $subscription_string, $product ) {
		if ( $product->is_type( 'subscription' ) ) {
			if ( strpos( $subscription_string, 'every 4 months' ) !== false ) {
				$subscription_string = str_replace( 'every 4 months', 'ogni 4 mesi', $subscription_string );
			}
			if ( strpos( $subscription_string, 'month' ) !== false ) {
				$subscription_string = str_replace( 'month', 'al mese', $subscription_string );
			}
		}
		return $subscription_string;
	}
	add_filter( 'woocommerce_subscriptions_product_price_string', 'custom_subscription_price_text', 10, 2 );
}

if ( ! function_exists( 'trb_child_style_cache_version' ) ) {
	function trb_child_style_cache_version( $src, $handle ) {
		if ( strpos( $src, '/themes/flatsome-child/style.css' ) !== false ) {
			$style_file = get_stylesheet_directory() . '/style.css';
			$version    = file_exists( $style_file ) ? filemtime( $style_file ) . '-trb2' : '20260729-trb2';
			$src        = remove_query_arg( 'ver', $src );
			$src        = add_query_arg( 'ver', $version, $src );
		}
		return $src;
	}
	add_filter( 'style_loader_src', 'trb_child_style_cache_version', 999, 2 );
}

if ( ! function_exists( 'trb_signature_placeholder_image' ) ) {
	function trb_signature_placeholder_image( $src ) {
		$product_id = 0;

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = get_queried_object_id();
		} elseif ( isset( $GLOBALS['product'] ) && is_a( $GLOBALS['product'], 'WC_Product' ) ) {
			$product_id = $GLOBALS['product']->get_id();
		}

		if ( $product_id && has_term( 'trb-pro', 'product_cat', $product_id ) ) {
			return content_url( '/uploads/2026/07/trb-signature-artist-development-v2.png' );
		}

		return $src;
	}
	add_filter( 'woocommerce_placeholder_img_src', 'trb_signature_placeholder_image' );
}

if ( ! function_exists( 'trb_archive_semantic_heading' ) ) {
	function trb_archive_semantic_heading() {
		if ( is_shop() || is_product_category() || is_product_tag() ) {
			echo '<h1 class="screen-reader-text">' . esc_html( woocommerce_page_title( false ) ) . '</h1>';
		}
	}
	add_action( 'woocommerce_before_shop_loop', 'trb_archive_semantic_heading', 1 );
}
