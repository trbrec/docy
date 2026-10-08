<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Widget areas
 */
add_action( 'widgets_init', function () {
	$opt = get_option( 'docy_opt' );
	register_sidebar( [
		'name'          => esc_html__( 'Primary Sidebar', 'docy' ),
		'description'   => esc_html__( 'Place widgets in sidebar widgets area.', 'docy' ),
		'id'            => 'sidebar_widgets',
		'before_widget' => '<div id="%1$s" class="widget sidebar_widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3 class="c_head">',
		'after_title'   => '</h3>'
	] );

	if ( class_exists( 'WooCommerce' ) ) {
		register_sidebar( [
			'name'          => esc_html__( 'Shop sidebar', 'docy' ),
			'description'   => esc_html__( 'Place widgets here for WooCommerce shop page.', 'docy' ),
			'id'            => 'shop_sidebar',
			'before_widget' => '<div id="%1$s" class="widget sidebar_widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="c_head">',
			'after_title'   => '</h3>'
		] );
	}

	$footer_column = ! empty( $opt['footer_column'] ) ? $opt['footer_column'] : '3';
	register_sidebar( [
		'name'          => esc_html__( 'Footer Widgets', 'docy' ),
		'description'   => esc_html__( 'Add widgets here for Footer widgets area', 'docy' ),
		'id'            => 'footer_widgets',
		'before_widget' => '<div id="%1$s" class="footer_widget col-lg-' . $footer_column . ' col-md-6">
                            <div class="widget f_widget %2$s">',
		'after_widget'  => '</div></div>',
		'before_title'  => '<h3 class="f_title">',
		'after_title'   => '</h3>'
	] );
} );
