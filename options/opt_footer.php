<?php
// Footer settings
CSF::createSection($prefix, array(
	'title'     => esc_html__( 'Footer', 'docy' ),
	'id'        => 'docy_footer_opt',
	'icon'      => 'dashicons dashicons-arrow-down',
));

// Footer Options
CSF::createSection($prefix, array(
	'parent'    => 'docy_footer_opt',
	'title'     => esc_html__( 'Footer Options', 'docy' ),
	'id'        => 'docy_footer_options',
	'fields'    => array(

		array(
			'title'     => esc_html__('Footer', 'docy'),
			'type'      => 'heading',
		),

		array(
			'title'         => esc_html__( 'Footer Style', 'docy' ),
			'id'            => 'footer_style',
			'type'          => 'select',
			'options'       => array(
				'normal'    => esc_html__( 'Default', 'docy' ),
				'simple'    => esc_html__( 'Simple', 'docy' ),
				'elementor'    => esc_html__( 'Elementor Template', 'docy' ),
			),
			'default'       => 'normal',
		),

		array(
			'id'            => 'is_footer_columns_preset',
			'type'          => 'switcher',
			'title'         => esc_html__( 'Preset Columns', 'docy' ),
			'subtitle'      => esc_html__( 'If you enable this switcher, the Footer Widget columns will set as preset (33.33% + 22.22% + 22.22% + 22.22%) on the demo of Docy.', 'docy' ),
			'text_on'       => esc_html__( 'Yes', 'docy' ),
			'text_off'      => esc_html__( 'No', 'docy' ),
			'default'       => true,
			'dependency'    => array( 'footer_style', '==', 'normal' )
		),

		array(
			'title'     => esc_html__('Footer Column', 'docy'),
			'id'        => 'footer_column',
			'type'      => 'select',
			'default'   => '3',
			'options'   => array(
				'6' => esc_html__('Two Column', 'docy'),
				'4' => esc_html__('Three Column', 'docy'),
				'3' => esc_html__('Four Column', 'docy'),
			),
			'dependency' => array(
				array(
					'footer_style', '==', 'normal'
				),
				array(
					'is_footer_columns_preset', '==', ''
				),
			)
		),

		array(
			'title'             => esc_html__( 'Padding', 'docy' ),
			'subtitle'          => esc_html__( 'Padding around the footer columns (Top Right Bottom Left)', 'docy' ),
			'id'                => 'footer_column_padding',
			'type'              => 'spacing',
			'output'            => array( '.doc_footer_area .f_widget' ),
			'mode'              => 'padding',
			'units'             => array( 'em', 'px', '%' ),      // You can specify a unit value. Possible: px, em, %
			'units_extended'    => 'true',
			'dependency'        => array( 'footer_style', '==', 'normal' )
		),

		array(
			'title'         => esc_html__( 'Ornament Illustration', 'docy' ),
			'subtitle'      => esc_html__( 'This is for beautiful design purpose. You can replace the default illustration or delete it from here.', 'docy' ),
			'id'            => 'fs_illustration',
			'type'          => 'media',
			'preview'       => true,
			'default'       => array(
				'url'       => DOCY_DIR_IMG.'/footer/leaf_footter.png'
			),
			'dependency'    => array( 'footer_style', '==', 'simple' )
		),

		// Elementor Footer Template
		array(
			'title'         => esc_html__( 'Elementor Template', 'docy' ),
			'id'            => 'footer_el_template',
			'type'          => 'select',
			'options'       => docy_get_post_options('docy_footer'),
			'dependency'    => array( 'footer_style', '==', 'elementor' ),
			'desc'          => sprintf(
				esc_html__('To create a new footer template, click %shere%s. Make sure Elementor plugin is activated and the template is published.', 'docy'),
				'<a href="'.esc_url(admin_url('edit.php?post_type=docy_footer')).'" target="_blank" rel="noopener noreferrer" style="color:#2271b1;text-decoration:underline;font-weight:bold;">',
				'</a>'
			),
		),
	)
));


// Footer settings
CSF::createSection($prefix, array(
	'parent'        => 'docy_footer_opt',
	'title'         => esc_html__( 'Font colors', 'docy' ),
	'id'            => 'docy_footer_font_colors',
	'icon'          => '',
	'subsection'    => true,
	'fields'        => array(
		array(
			'id'        => 'footer-font_color',
			'title'     => esc_html__('Font Color', 'docy'),
			'type'      => 'heading',
		),
		array(
			'title'     => esc_html__( 'Widget Title Color', 'docy' ),
			'id'        => 'widget_title_color',
			'type'      => 'color',
			'output'    => array('.f_widget .f_title, .f_widget h2' )
		),
		array(
			'title'     => esc_html__( 'Normal Font color', 'docy' ),
			'id'        => 'footer_top_normal_font_color',
			'type'      => 'color',
			'rgba'      => true,
			'output'    => array('.doc_service_list_widget ul li a' )
		),
		array(
			'title'     => esc_html__( 'Hover Font color', 'docy' ),
			'id'        => 'footer_top_hover_font_color',
			'type'      => 'color',
			'rgba'      => true,
			'output'    => array('.footer_top .f_widget ul li a:hover, .footer_top .widget.widget_nav_menu ul li a:hover' )
		),
	)
));

// Footer background
CSF::createSection($prefix, array(
	'parent'        => 'docy_footer_opt',
	'title'         => esc_html__( 'Background', 'docy' ),
	'id'            => 'docy_footer_background',
	'icon'          => '',
	'subsection'    => true,
	'fields'        => array(
		array(
			'id'        => 'footer_background_color',
			'title'     => esc_html__('Background', 'docy'),
			'type'      => 'heading',
		),

		array(
			'title'            => esc_html__( 'Background Color', 'docy' ),
			'id'               => 'footer_top_bg_color',
			'type'             => 'color',
			'output'           => array('.doc_footer_top, .simple_footer' ),
			'output_mode'      => 'background-color'
		),

		array(
			'title'            => esc_html__( 'Bottom Background Color', 'docy' ),
			'subtitle'         => esc_html__( 'Footer bottom background color', 'docy' ),
			'id'               => 'footer_btm_bg_color',
			'type'             => 'color',
			'output'        => array('.doc_footer_bottom' ),
			'output_mode'   => 'background-color'
		),
	)
));

// Footer settings
CSF::createSection($prefix, array(
	'parent'        => 'docy_footer_opt',
	'title'         => esc_html__( 'Footer Bottom', 'docy' ),
	'id'            => 'docy_footer_btm',
	'icon'          => '',
	'subsection'    => true,
	'fields'        => array(
		array(
			'id'        => 'footer-bottom',
			'title'     => esc_html__('Footer Bottom', 'docy'),
			'type'      => 'heading',
		),
		array(
			'title'     => esc_html__( 'Copyright Text', 'docy' ),
			'id'        => 'copyright_txt',
			'type'      => 'wp_editor',
			'default'   => __('© 2025 All Rights Reserved by Spider-Themes', 'docy'),
			'args'      => array (
				'wpautop'           => true,
				'media_buttons'     => false,
				'textarea_rows'     => 10,
				'tinymce'           => false,
				'quicktags'         => false,
			)
		),
		array(
			'id'     => 'footer_btm_links',
			'type'   => 'repeater',
			'title'  => esc_html__( 'Useful Links', 'docy' ),
			'fields' => array(

				array(
					'id'    => 'title',
					'type'  => 'text',
					'title' => esc_html__( 'Title', 'docy' ),
				),
				array(
					'id'    => 'url',
					'type'  => 'text',
					'title' => esc_html__( 'URL', 'docy' ),
				)
			),
			'button_title' => esc_html__( 'Add New', 'docy' ),
		)
	)
));