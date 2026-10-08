<?php

// Sidebar
CSF::createSection( $prefix, array(
	'title'     => esc_html__( 'Sidebar', 'docy' ),
	'post_type' => [ 'post', 'page' ],  // Applied post, page
	'fields'    => array(
		array(
			'id'       => 'is_toc',
			'type'     => 'button_set',
			'title'    => esc_html__( 'TOC', 'docy' ),
			'subtitle' => esc_html__( 'TOC will work on the default page template only.', 'docy' ),
			'options'  => array(
				'default' => 'Default',
				'1'       => 'Enable',
				'0'       => 'Disable',
			),
			'output'   => '.nav-sidebar doc-nav',
			'default'  => 'default'
		),
	)
) );