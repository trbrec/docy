<?php
// Shop page
CSF::createSection( $prefix, array(
	'title' => esc_html__( 'Shop/wooCommerce', 'docy' ),
	'id'    => 'shop_opt',
	'icon'  => 'dashicons dashicons-cart',
) );


// Product Single Options
CSF::createSection( $prefix, array(
	'parent' => 'shop_opt',
	'title'  => esc_html__( 'Product Archive', 'docy' ),
	'id'     => 'shop_header_options',
	'fields' => array(
		array(
			'id'    => 'shop-header',
			'title' => esc_html__( 'Shop', 'docy' ),
			'type'  => 'heading',
		),
		array(
			'title'    => esc_html__( 'Page title', 'docy' ),
			'subtitle' => esc_html__( 'Give here the shop page title', 'docy' ),
			'desc'     => esc_html__( 'This text will show on the shop page banner', 'docy' ),
			'id'       => 'shop_title',
			'type'     => 'text',
			'default'  => esc_html__( 'Shop', 'docy' ),
		),

        array(
            'title'     => esc_html__( 'Sidebar', 'docy' ),
            'subtitle'  => esc_html__( 'Select the sidebar position of Shop page', 'docy' ),
            'id'        => 'shop_sidebar',
            'type'      => 'image_select',
            'options'   => array(
                'left'  =>  DOCY_DIR_IMG.'/layouts/sidebar_left.jpg',
                'right' => DOCY_DIR_IMG.'/layouts/sidebar_right.jpg',
                'full'  =>  DOCY_DIR_IMG.'/layouts/fullwidth.jpg',
            ),
            'default'   => 'left'
        ),

        array(
            'title'    => esc_html__( 'Shop Layout', 'docy' ),
            'subtitle' => esc_html__( 'Select the default layout for shop products', 'docy' ),
            'id'       => 'shop_layout',
            'type'     => 'radio',
            'options'  => array(
                'shop_grid' => esc_html__( 'Grid', 'docy' ),
                'shop_list' => esc_html__( 'List', 'docy' ),
            ),
            'default'  => 'shop_grid'
        ),

        array(
            'title'      => esc_html__( 'Product Columns (Grid Layout)', 'docy' ),
            'subtitle'   => esc_html__( 'Number of columns for grid layout', 'docy' ),
            'id'         => 'shop_grid_columns',
            'type'       => 'select',
            'options'    => array(
                '2' => esc_html__( '2 Columns', 'docy' ),
                '3' => esc_html__( '3 Columns', 'docy' ),
                '4' => esc_html__( '4 Columns', 'docy' ),
            ),
            'default'    => '3',
            'dependency' => array( 'shop_layout', '==', 'shop_grid' ),
        ),
    ),
));

// Product Single Options
CSF::createSection( $prefix, array(
	'parent'     => 'shop_opt',
	'title'      => esc_html__( 'Product Single', 'docy' ),
	'id'         => 'product_single_opt',
	'subsection' => true,
	'icon'       => '',
	'fields'     => array(
		array(
			'id'    => 'shop-single',
			'title' => esc_html__( 'Product Single', 'docy' ),
			'type'  => 'heading',
		),
		array(
			'title'   => esc_html__( 'Related Products Title', 'docy' ),
			'id'      => 'related_products_title',
			'type'    => 'text',
			'default' => esc_html__( 'Related products', 'docy' ),
		),
		array(
			'title' => esc_html__( 'Related Products Subtitle', 'docy' ),
			'id'    => 'related_products_subtitle',
			'type'  => 'textarea',
		),
	)
) );


// Gutenberg Blocks
CSF::createSection( $prefix, array(
	'parent'     => 'shop_opt',
	'title'      => esc_html__( 'Gutenberg Blocks', 'docy' ),
	'id'         => 'wc_gutenberg_opt',
	'subsection' => true,
	'icon'       => '',
	'fields'     => array(
		array(
			'id'    => 'shop-blocks',
			'title' => esc_html__( 'Blocks', 'docy' ),
			'type'  => 'heading',
		),
		array(
			'title'      => esc_html__( 'Unload WC Gutenberg Assets', 'docy' ),
			'subtitle'   => esc_html__( "WC gutenberg assets loads globally. If you don't use wooCommerce gutenberg blocks, it's recommended to unload the unnecessary assets.",
				'docy' ),
			'id'         => 'is_wc_block',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Yes', 'docy' ),
			'text_off'   => esc_html__( 'No', 'docy' ),
			'text_width' => 80,
			'default'    => '',
		),
	)
) );