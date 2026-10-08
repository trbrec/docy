<?php
$admin_url = rtrim(admin_url(), '/');

// Header Section
CSF::createSection( $prefix, array(
	'title'            => esc_html__( 'Header', 'docy' ),
	'id'               => 'header_sec',
	'customizer_width' => '400px',
	'icon'             => 'dashicons dashicons-arrow-up',
) );


// Header Layout
CSF::createSection( $prefix, array(
	'parent'           => 'header_sec',
	'title'            => esc_html__( 'Layout & Settings', 'docy' ),
	'id'               => 'header_layout',
	'customizer_width' => '400px',
	'subsection'       => true,
	'icon'             => '',
	'fields'           => array(
        array(
			'id'    => 'layot_section_header',
			'title' => esc_html__( 'Layout & Settings', 'docy' ),
			'type'  => 'heading',
		),

        array(
            'title'         => esc_html__( 'Header Style', 'docy' ),
            'id'            => 'header_style',
            'type'          => 'select',
            'options'       => array(
                'normal'    => esc_html__( 'Default', 'docy' ),
                'elementor'    => esc_html__( 'Elementor Template', 'docy' ),
            ),
            'default'       => 'normal'
        ),

        array(
			'title'    => esc_html__( 'Header Width', 'docy' ),
			'subtitle' => esc_html__( 'Set the default Header width here. It will apply on the theme\'s Header globally.', 'docy' ),
			'id'       => 'header_width',
			'type'     => 'select',
			'options'  => array(
				'boxed'          => esc_html__( 'Boxed', 'docy' ),
				'wide-container' => esc_html__( 'Wide', 'docy' ),
				'full-width'     => esc_html__( 'Full Width', 'docy' ),
			),
			'default'  => 'boxed',
            'dependency'    => array( 'header_style', '==', 'normal' )
		),

		array(
			'title'   => esc_html__( 'Sticky Navbar', 'docy' ),
			'id'      => 'is_sticky_header',
			'type'    => 'switcher',
			'default' => true,
            'dependency'    => array( 'header_style', '==', 'normal' )
		),

		array(
			'title'      => esc_html__( 'Sticky Appearance', 'docy' ),
			'subtitle'   => esc_html__( 'Set the sticky appearance based on scrolling states.', 'docy' ),
			'id'         => 'sticky_appearance',
			'type'       => 'select',
			'options'    => array(
				'stick_all' => esc_html__( 'Stick all Time', 'docy' ),
				'stick_up'  => esc_html__( 'Stick on Up Scrolling', 'docy' ),
			),
			'default'    => 'stick_up',
            'dependency'    => array( 'header_style', '==', 'normal' )
		),

		array(
			'title'   => esc_html__( 'Header Layout', 'docy' ),
			'id'      => 'header_layout',
			'type'    => 'image_select',
			'options' => array(
				'default' => DOCY_DIR_IMG . '/headers/default.jpg',
				'search'  => DOCY_DIR_IMG . '/headers/search-form.jpg',
			),
			'default' => 'default',
            'dependency'    => array( 'header_style', '==', 'normal' )
		),

		array(
			'title'    => esc_html__( 'Navbar Position', 'docy' ),
			'subtitle' => esc_html__( 'The Navbar Position will apply globally except the blog single page.', 'docy' ),
			'desc'     => esc_html__( 'The Static position recommended with disabling the Search Banner', 'docy' ),
			'id'       => 'navbar_position',
			'type'     => 'button_set',
			'options'  => array(
				'absolute' => esc_html__( 'Absolute', 'docy' ),
				'static'   => esc_html__( 'Static', 'docy' ),
			),
			'default'  => 'absolute',
            'dependency'    => array( 'header_style', '==', 'normal' )
		),

		array(
			'title'    => esc_html__( 'Navbar Color', 'docy' ),
			'subtitle' => esc_html__( 'Select the default color for the selected Navbar style.', 'docy' ),
			'desc'     => esc_html__( 'The selected style would apply main navbar area\'s elements (logo, menu items, action button)', 'docy' ),
			'id'       => 'navbar_color',
			'type'     => 'button_set',
			'options'  => array(
				'default' => esc_html__( 'Default', 'docy' ),
				'white'   => esc_html__( 'White', 'docy' ),
				'black'   => esc_html__( 'Black', 'docy' ),
			),
			'default'  => 'default',
            'dependency'    => array( 'header_style', '==', 'normal' )
		),

		( is_plugin_active( 'eazydocs/eazydocs.php' ) ? array(
			'title'      => esc_html__( 'Docs Page Navbar Color', 'docy' ),
			'subtitle'   => esc_html__( 'Select a navbar color for single Docs pages.', 'docy' ),
			'id'         => 'docs_navbar_color',
			'type'       => 'button_set',
			'options'    => array(
				'white'   => esc_html__( 'White', 'docy' ),
				'black'   => esc_html__( 'Black', 'docy' ),
			),
			'default'    => 'white',
			'dependency' => array( 'header_style', '==', 'normal' ),
		) : array() ),

		array(
			'title'      => esc_html__( 'Search Form', 'docy' ),
			'id'         => 'is_search_form',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => true,
            'dependency'    => array( 'header_style', '==', 'normal' )
		),

		array(
			'title'       => esc_html__( 'Search Form Dimension', 'docy' ),
			'id'          => 'search_form_width',
			'type'        => 'slider',
			'min'         => 0,
			'max'         => 100,
			'step'        => 1,
			'unit'        => '%',
			'output'      => '.navbar .search-input, .navbar .search-input.show-by-default',
			'output_mode' => 'width',
			'dependency'  => array(
                array( 'is_search_form', '==', 'true' ),
                array( 'header_style', '==', 'normal' ),
            ),
		),

        // Elementor Header Template
        array(
            'title'         => esc_html__( 'Elementor Template', 'docy' ),
            'id'            => 'header_el_template',
            'type'          => 'select',
            'options'       => docy_get_post_options('docy_header'),
            'dependency'    => array( 'header_style', '==', 'elementor' )
        ),

	)
) );


// Logo
CSF::createSection( $prefix, array(
	'parent'     => 'header_sec',
	'title'      => esc_html__( 'Logo', 'docy' ),
	'id'         => 'logo_opt',
	'subsection' => true,
	'icon'       => '',
	'fields'     => array(
		array(
			'id'    => 'header_logo_opt',
			'title' => esc_html__( 'Logo', 'docy' ),
			'subtitle' => esc_html__( 'The logo will display in the website’s navigation bar. If you’re uploading an SVG logo, there’s no need to upload a separate Retina logo, as SVG files maintain high resolution on all screens.', 'docy' ),
			'type'  => 'heading',
		),
		array(
			'title'    => esc_html__( 'Black Logo', 'docy' ),
			'subtitle' => esc_html__( 'Upload here the black version of your logo.', 'docy' ),
			'id'       => 'main_logo',
			'type'     => 'media',
			'compiler' => true,
			'default'  => array(
				'url' => DOCY_DIR_IMG . '/logo.png'
			),
			'library'  => 'image'
		),


		array(
			'title'    => esc_html__( 'White Logo', 'docy' ),
			'subtitle' => esc_html__( 'Upload here the white version of your logo. This logo is useful for the Dark mode.', 'docy' ),
			'id'       => 'sticky_logo',
			'type'     => 'media',
			'compiler' => true,
			'default'  => array(
				'url' => DOCY_DIR_IMG . '/logo-w.png'
			)
		),

		array(
			'title'    => esc_html__( 'Black Retina Logo', 'docy' ),
			'subtitle' => esc_html__( 'The retina logo should be double (2x) of your original logo size.', 'docy' ),
			'id'       => 'retina_logo',
			'type'     => 'media',
			'compiler' => true,
			'default'  => array(
				'url' => DOCY_DIR_IMG . '/logo-2x.png'
			)
		),

		array(
			'title'    => esc_html__( 'White Retina Logo', 'docy' ),
			'subtitle' => esc_html__( 'The retina logo should be double (2x) of your original logo size.', 'docy' ),
			'id'       => 'retina_sticky_logo',
			'type'     => 'media',
			'compiler' => true,
			'default'  => array(
				'url' => DOCY_DIR_IMG . '/logo-w2x.png'
			)
		),

		array(
			'title'    => esc_html__( 'Logo dimensions', 'docy' ),
			'subtitle' => esc_html__( 'Set a custom height width for your uploaded logo.', 'docy' ),
			'id'       => 'logo_dimensions',
			'type'     => 'dimensions',
			'width'    => true,
			'height'   => true,
			'units'    => array( 'em', 'px', '%' ),
			'default'  => array(
				'unit'   => 'px',
			),
			'output'   => '.navbar-brand>img',
			'output_prefix' => 'max'
		),

		array(
			'title'            => esc_html__( 'Padding', 'docy' ),
			'subtitle'         => esc_html__( 'Padding around the logo. Input the padding as clockwise (Top Right Bottom Left)', 'docy' ),
			'id'               => 'logo_padding',
			'type'             => 'spacing',
			'output'           => array( '.navbar-brand' ),
			'output_mode'      => 'padding',
			'units'            => array( 'em', 'px', '%' ),      // You can specify a unit value. Possible: px, em, %
			'units_extended'   => true,
			'output_important' => true,
		),

		// Multi Logo Feature
		array(
			'id'       => 'multi_logo_heading',
			'title'    => esc_html__( 'Multi Logo', 'docy' ),
			'subtitle' => esc_html__( 'Create a dropdown menu of multiple site logos with individual URLs. Perfect for network sites or multi-brand navigation.', 'docy' ),
			'type'     => 'heading',
		),

		array(
			'title'      => esc_html__( 'Enable Multi Logo', 'docy' ),
			'subtitle'   => esc_html__( 'Enable to display a dropdown with multiple site logos beside the main logo.', 'docy' ),
			'id'         => 'is_multi_logo',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Enabled', 'docy' ),
			'text_off'   => esc_html__( 'Disabled', 'docy' ),
			'text_width' => 100,
			'default'    => false,
		),

		array(
			'title'      => esc_html__( 'See All Link', 'docy' ),
			'subtitle'   => esc_html__( 'Optional "See All" link text and URL at the bottom of the dropdown.', 'docy' ),
			'id'         => 'multi_logo_see_all_fieldset',
			'type'       => 'fieldset',
			'dependency' => array( 'is_multi_logo', '==', 'true' ),
			'fields'     => array(
				array(
					'title'   => esc_html__( 'Link Text', 'docy' ),
					'id'      => 'text',
					'type'    => 'text',
					'default' => esc_html__( 'See All Sites', 'docy' ),
				),
				array(
					'title' => esc_html__( 'Link URL', 'docy' ),
					'id'    => 'url',
					'type'  => 'text',
				),
				array(
					'title'   => esc_html__( 'Menu URL Target', 'docy' ),
					'id'      => 'link_target',
					'type'    => 'select',
					'options' => array(
						'_blank' => esc_html__( 'Blank (Open in new tab)', 'docy' ),
						'_self'  => esc_html__( 'Self (Open in the same tab)', 'docy' ),
					),
					'default' => '_self'
				),
			),
		),

		array(
			'id'           => 'multi_logo_sites',
			'type'         => 'repeater',
			'title'        => esc_html__( 'Site Logos', 'docy' ),
			'subtitle'     => esc_html__( 'Add multiple sites with their logos and URLs. First item will be highlighted as the current site.', 'docy' ),
			'button_title' => esc_html__( 'Add Site', 'docy' ),
			'dependency'   => array( 'is_multi_logo', '==', 'true' ),
			'fields'       => array(
				array(
					'title' => esc_html__( 'Site Name', 'docy' ),
					'id'    => 'name',
					'type'  => 'text',
				),
				array(
					'title' => esc_html__( 'Description', 'docy' ),
					'id'    => 'description',
					'type'  => 'text',
				),
				array(
					'title'   => esc_html__( 'Site Logo', 'docy' ),
					'id'      => 'logo',
					'type'    => 'media',
					'library' => 'image',
				),
				array(
					'title' => esc_html__( 'Site URL', 'docy' ),
					'id'    => 'url',
					'type'  => 'text',
				),
				array(
					'title'   => esc_html__( 'Menu URL Target', 'docy' ),
					'id'      => 'link_target',
					'type'    => 'select',
					'options' => array(
						'_blank' => esc_html__( 'Blank (Open in new tab)', 'docy' ),
						'_self'  => esc_html__( 'Self (Open in the same tab)', 'docy' ),
					),
					'default' => '_self'
				),
				array(
					'title'   => esc_html__( 'Is Active', 'docy' ),
					'id'      => 'is_current',
					'type'    => 'switcher',
					'default' => false,
				),
			),
			'default' => array(
				array(
					'name'       => esc_html__( 'Home', 'docy' ),
					'url'        => '#',
					'is_current' => true,
				),
			),
		),
	)
) );

/**
 * Action button
 */
CSF::createSection( $prefix, array(
	'parent'     => 'header_sec',
	'title'      => esc_html__( 'Action Button', 'docy' ),
	'id'         => 'menu_action_btn_opt',
	'subsection' => true,
	'icon'       => '',
	'fields'     => array(

		array(
			'id'    => 'action_btn_header',
			'title' => esc_html__( 'Action Button', 'docy' ),
			'type'  => 'heading',
		),

		array(
			'title'      => esc_html__( 'Button Visibility', 'docy' ),
			'id'         => 'is_menu_btn',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => true,
		),

		array(
			'title'      => esc_html__( 'Button Label', 'docy' ),
			'subtitle'   => esc_html__( 'Leave the button label field empty to hide the menu action button.', 'docy' ),
			'id'         => 'menu_btn_label',
			'type'       => 'text',
			'default'    => esc_html__( 'Get Started', 'docy' ),
			'dependency' => array( 'is_menu_btn', '==', '1' ),
		),

		array(
			'title'      => esc_html__( 'Button Label on Logged in', 'docy' ),
			'subtitle'   => esc_html__( 'Button label in login mode', 'docy' ),
			'id'         => 'menu_btn_label_logged',
			'type'       => 'text',
			'dependency' => array(
				'is_menu_btn', '==', '1',
				'menu_btn_label', '!=', ''
			),
		),

		array(
			'title'      => esc_html__( 'Button URL', 'docy' ),
			'id'         => 'menu_btn_url',
			'type'       => 'text',
			'default'    => '#',
			'dependency' => array( 'is_menu_btn', '==', '1' )
		),

		array(
			'title'      => esc_html__( 'Button URL Target', 'docy' ),
			'id'         => 'menu_btn_target',
			'type'       => 'select',
			'options'    => array(
				'_blank' => esc_html__( 'Blank (Open in new tab)', 'docy' ),
				'_self'  => esc_html__( 'Self (Open in the same tab)', 'docy' ),
			),
			'default'    => '_self',
			'dependency' => array( 'is_menu_btn', '==', '1' )
		),

		array(
			'title'          	=> esc_html__( 'Button padding', 'docy' ),
			'subtitle'      	=> esc_html__( 'Padding around the menu action button.', 'docy' ),
			'id'             	=> 'menu_btn_padding',
			'type'           	=> 'spacing',
			'output'        	=> array( '.nav_btn' ),
			'output_important'	=> true,
			'mode'           	=> 'padding',
			'units'          	=> array( 'em', 'px', '%' ),      // You can specify a unit value. Possible: px, em, %
			'units_extended' 	=> 'true',
			'dependency'     	=> array( 'is_menu_btn', '==', '1' )
		),

		// Button icon
		array(
			'title'      => esc_html__( 'Button Icon', 'docy' ),
			'subtitle'   => esc_html__( 'Button icon properties', 'docy' ),
			'id'         => 'button_icon',
			'type'       => 'subheading',
			'indent'     => true,
			'dependency' => array( 'is_menu_btn', '==', '1' )
		),

		array(
			'title'      => esc_html__( 'Icon', 'docy' ),
			'subtitle'   => esc_html__( 'Select an icon for the menu action button.', 'docy' ),
			'id'         => 'menu_btn_icon',
			'type'       => 'icon',
			'default'    => 'fas fa-arrow-right',
			'dependency' => array( 'is_menu_btn', '==', '1' )
		),

		array(
			'title'      => esc_html__( 'Icon Position', 'docy' ),
			'subtitle'   => esc_html__( 'Select the icon position for the menu action button.', 'docy' ),
			'id'         => 'menu_btn_icon_position',
			'type'       => 'button_set',
			'options'    => array(
				'before' => esc_html__( 'Before', 'docy' ),
				'after'  => esc_html__( 'After', 'docy' ),
			),
			'default'    => 'after',
			'dependency' => array( 'is_menu_btn', '==', '1' )
		),

		/**
		 * Button colors
		 * Style will apply on the Non-sticky mode and sticky mode of the header
		 */
		array(
			'title'      => esc_html__( 'Button Colors', 'docy' ),
			'subtitle'   => esc_html__( 'Button style attributes on normal (non sticky) mode.', 'docy' ),
			'id'         => 'button_colors',
			'type'       => 'subheading',
			'indent'     => true,
			'dependency' => array( 'is_menu_btn', '==', '1' )
		),

		array(
			'title'       		=> esc_html__( 'Font color', 'docy' ),
			'id'          		=> 'menu_btn_font_color',
			'type'        		=> 'color',
			'output'     		=> '.right-nav .nav_btn',
			'output_mode' 		=> 'color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_menu_btn', '==', '1' )
		),

		array(
			'title'       => esc_html__( 'Border Color', 'docy' ),
			'id'          => 'menu_btn_border_color',
			'type'        => 'color',
			'mode'        => 'border-color',
			'output'      => '.right-nav .nav_btn, .dark_menu .right-nav .nav_btn',
			'output_mode' => 'border-color',
			'dependency'  => array( 'is_menu_btn', '==', '1' )
		),

		array(
			'title'       => esc_html__( 'Background Color', 'docy' ),
			'id'          => 'menu_btn_bg_color',
			'type'        => 'color',
			'mode'        => 'background',
			'output'      => array( '.right-nav .nav_btn' ),
			'output_mode' => 'background-color',
			'dependency'  => array( 'is_menu_btn', '==', '1' )
		),

		// Button color on hover stats
		array(
			'title'       		=> esc_html__( 'Hover Font Color', 'docy' ),
			'subtitle'    		=> esc_html__( 'Font color on hover state.', 'docy' ),
			'id'          		=> 'menu_btn_hover_font_color',
			'type'        		=> 'color',
			'output'      		=> array( '.right-nav .nav_btn:hover' ),
			'output_mode' 		=> 'color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_menu_btn', '==', '1' )
		),
		array(
			'title'       		=> esc_html__( 'Hover Border Color', 'docy' ),
			'subtitle'    		=> esc_html__( 'Border color on hover state.', 'docy' ),
			'id'          		=> 'menu_btn_hover_border_color',
			'type'        		=> 'color',
			'mode'        		=> 'border-color',
			'output'      		=> array( '.right-nav .nav_btn:hover' ),
			'output_mode' 		=> 'border-color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_menu_btn', '==', '1' )
		),
		array(
			'title'       => esc_html__( 'Hover background color', 'docy' ),
			'subtitle'    => esc_html__( 'Background color on hover state.', 'docy' ),
			'id'          => 'menu_btn_hover_bg_color',
			'type'        => 'color',
			'output'      => array(
				'background'   => '.right-nav .nav_btn:hover',
				'border-color' => '.navbar_fixed .navbar .nav_btn:hover'
			),
			'output_mode' 		=> 'background-color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_menu_btn', '==', '1' )
		),
		/*
		 * Button colors on sticky mode
		 */
		array(
			'title'      => esc_html__( 'Sticky Button Style', 'docy' ),
			'subtitle'   => esc_html__( 'Button colors on sticky mode.', 'docy' ),
			'id'         => 'button_colors_sticky',
			'type'       => 'subheading',
			'indent'     => true,
			'dependency' => array( 'is_menu_btn', '==', '1' ),
		),
		array(
			'title'       		=> esc_html__( 'Border color', 'docy' ),
			'subtitle'    		=> esc_html__( 'Border color on header sticky mode.', 'docy' ),
			'id'          		=> 'menu_btn_border_color_sticky',
			'type'        		=> 'color',
			'mode'        		=> 'border-color',
			'output'      		=> array( '.navbar.navbar_fixed .right-nav .nav_btn' ),
			'output_mode' 		=> 'border-color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_menu_btn', '==', '1' )
		),
		array(
			'title'       		=> esc_html__( 'Font color', 'docy' ),
			'subtitle'    		=> esc_html__( 'Font color on header sticky mode.', 'docy' ),
			'id'				=> 'menu_btn_font_color_sticky',
			'type'        		=> 'color',
			'output'      		=> array( '.navbar_fixed.navbar .right-nav .nav_btn' ),
			'output_mode' 		=> 'color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_menu_btn', '==', '1' )
		),
		array(
			'title'       		=> esc_html__( 'Background color', 'docy' ),
			'subtitle'    		=> esc_html__( 'Background color on header sticky mode.', 'docy' ),
			'id'          		=> 'menu_btn_bg_color_sticky',
			'type'        		=> 'color',
			'mode'        		=> 'background',
			'output'      		=> array( '.navbar_fixed.navbar .right-nav .nav_btn' ),
			'output_mode' 		=> 'background-color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_menu_btn', '==', '1' )
		),

		// Button color on hover stats
		array(
			'title'       		=> esc_html__( 'Hover font color', 'docy' ),
			'subtitle'    		=> esc_html__( 'Font hover color on Header sticky mode.', 'docy' ),
			'id'          		=> 'menu_btn_hover_font_color_sticky',
			'type'        		=> 'color',
			'output'      		=> array( '.navbar.navbar_fixed .right-nav .nav_btn:hover' ),
			'output_mode' 		=> 'color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_menu_btn', '==', '1' )
		),
		array(
			'title'       => esc_html__( 'Hover background color', 'docy' ),
			'subtitle'    => esc_html__( 'Background hover color on Header sticky mode.', 'docy' ),
			'id'          => 'menu_btn_hover_bg_color_sticky',
			'type'        => 'color',
			'output'      => array(
				'background' => '.navbar.navbar_fixed .right-nav .nav_btn:hover',
			),
			'output_mode' 		=> 'background-color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_menu_btn', '==', '1' )
		),
		array(
			'title'       => esc_html__( 'Hover border color', 'docy' ),
			'subtitle'    => esc_html__( 'Background hover color on Header sticky mode.', 'docy' ),
			'id'          => 'menu_btn_hover_border_color_sticky',
			'type'        => 'color',
			'output'      => array(
				'border-color' => '.navbar.navbar_fixed .right-nav .nav_btn:hover',
			),
			'output_mode' 		=> 'border-color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_menu_btn', '==', '1' )
		),

		array(
			'id'     => 'button_colors-sticky-end',
			'type'   => 'subheading',
			'indent' => false,
		),
	)
) );

/**
 * Top Header Options
 */
CSF::createSection( $prefix, array(
	'parent'     => 'header_sec',
	'title'      => esc_html__( 'Top Header', 'docy' ),
	'id'         => 'top_header_opt',
	'subsection' => true,
	'icon'       => '',
	'fields'     => array(

		array(
			'id'         => 'is_top_header',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Top Header', 'docy' ),
			'subtitle'   => esc_html__( 'Toggle this switch to Show/Hide the Top Header.', 'docy' ),
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => false, // or true
		),

		array(
			'id'         => 'is_active_left_items',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Active Current Site', 'docy' ),
			'subtitle'   => esc_html__( 'Show the current website menu item URL activated.', 'docy' ),
			'text_on'    => esc_html__( 'Enabled', 'docy' ),
			'text_off'   => esc_html__( 'Disabled', 'docy' ),
			'text_width' => 100,
			'default'    => true, // or false
			'dependency' => array( 'is_top_header', '==', 'true' )
		),

		array(
			'id'           => 'top_header_left_items',
			'type'         => 'repeater',
			'title'        => esc_html__( 'Left Items', 'docy' ),
			'subtitle'     => esc_html__( 'Add your menu items for the left side Top Header.', 'docy' ),
			'fields'       => array(

				array(
					'id'    => 'image',
					'type'  => 'media',
					'title' => esc_html__( 'Icon', 'docy' ),
				),

				array(
					'id'    => 'text',
					'type'  => 'text',
					'title' => esc_html__( 'Menu Item', 'docy' ),
				),

				array(
					'id'    => 'link',
					'type'  => 'text',
					'title' => esc_html__( 'Menu URL', 'docy' ),
				),

				array(
					'title'   => esc_html__( 'Menu URL Target', 'docy' ),
					'id'      => 'link_target',
					'type'    => 'select',
					'options' => array(
						'_blank' => esc_html__( 'Blank (Open in new tab)', 'docy' ),
						'_self'  => esc_html__( 'Self (Open in the same tab)', 'docy' ),
					),
					'default' => '_self'
				),
			),
			'default'      => array(
				array(
					'text' => __( 'Menu Item 01', 'docy' ),
					'link' => '#',
				),
			),
			'button_title' => esc_html__( 'Add Item', 'docy' ),
			'dependency'   => array( 'is_top_header', '==', 'true' )
		),

		array(
			'id'           => 'top_header_right_items',
			'type'         => 'repeater',
			'title'        => __( 'Right Items', 'docy' ),
			'subtitle'     => __( 'Add your menu items for the right side Top Header.', 'docy' ),
			'fields'       => array(

				array(
					'id'    => 'image',
					'type'  => 'media',
					'title' => esc_html__( 'Icon', 'docy' ),
				),

				array(
					'id'    => 'text',
					'type'  => 'text',
					'title' => esc_html__( 'Menu Item', 'docy' ),
				),

				array(
					'id'    => 'link',
					'type'  => 'text',
					'title' => esc_html__( 'Menu URL', 'docy' ),
				),

				array(
					'title'   => esc_html__( 'Menu URL Target', 'docy' ),
					'id'      => 'link_target',
					'type'    => 'select',
					'options' => array(
						'_blank' => esc_html__( 'Blank (Open in new tab)', 'docy' ),
						'_self'  => esc_html__( 'Self (Open in the same tab)', 'docy' ),
					),
					'default' => '_self'
				),

			),
			'button_title' => esc_html__( 'Add Item', 'docy' ),
			'dependency'   => array( 'is_top_header', '==', 'true' )
		),

	)
) );

/**
 * Search Banner
 */
CSF::createSection( $prefix, array(
	'parent'     => 'header_sec',
	'title'      => esc_html__( 'Search Banner', 'docy' ),
	'id'         => 'search_banner_header_opt',
	'subsection' => true,
	'icon'       => '',
	'fields'     => array(
		array(
			'id'    => 'search_header_opt',
			'type'  => 'heading',
			'title' => esc_html__( 'Search Banner', 'docy' )
		),
		array(
			'id'      => 'search_banner_note',
			'type'    => 'notice',
			'style'   => 'success',
			'title'   => esc_html__( 'Important Note:', 'docy' ),
			'icon'    => 'dashicons dashicons-info',
			'content' => esc_html__( 'By default, the Search Banner is displays on all pages. You can hide Search Banner from a specific page from Options :: Page > Banner', 'docy' )
		),

		array(
			'title'      => esc_html__( 'Search Banner', 'docy' ),
			'id'         => 'is_banner',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => true
		),

		array(
			'title'    => esc_html__( 'Documentation Banner', 'docy' ),
			'subtitle' => esc_html__( 'Choose which banner to show on single doc pages when EazyDocs plugin is active. EazyDocs Banner is the default.', 'docy' ),
			'id'       => 'docs_banner_source',
			'type'     => 'button_set',
			'options'  => array(
				'eazydocs' => esc_html__( 'EazyDocs Banner', 'docy' ),
				'docy'     => esc_html__( 'Docy Banner', 'docy' ),
			),
			'default'  => 'eazydocs',
			'dependency' => array( 'is_banner', '==', '1' ),
		),

		array(
			'id'     => 'sbnr_title_fieldset',
			'type'   => 'fieldset',
			'title'  =>  esc_html__( 'Banner Title', 'docy' ),
			'dependency' => array( 'is_banner', '==', '1' ),
			'fields' => array(
				array(
					'id'         => 'is_page_title',
					'type'       => 'switcher',
					'title'      => esc_html__( 'Page Title', 'docy' ),
					'subtitle'   => esc_html__( 'Show / Hide the page title', 'docy' ),
					'text_on'    => esc_html__( 'Show', 'docy' ),
					'text_off'   => esc_html__( 'Hide', 'docy' ),
					'text_width' => 80,
					'default'    => true,
				),
				array(
					'title'       		=> esc_html__( 'Title color', 'docy' ),
					'id'          		=> 'sbnr_title_color',
					'type'        		=> 'color',
					'output'     		=> '.sbnr-global .doc_banner_content > .title',
					'output_mode' 		=> 'color',
					'output_important'	=> true,
				),
			),
		),

		array(
			'id'     => 'sbnr_subtitle_fieldset',
			'type'   => 'fieldset',
			'title'  =>  esc_html__( 'Banner Subtitle', 'docy' ),
			'dependency' => array( 'is_banner', '==', '1' ),
			'fields' => array(
				array(
					'id'         => 'is_page_subtitle',
					'type'       => 'switcher',
					'title'      => esc_html__( 'Page Subtitle', 'docy' ),
					'subtitle'   => esc_html__( 'Page subtitle will get from the page excerpt.', 'docy' ),
					'text_on'    => esc_html__( 'Show', 'docy' ),
					'text_off'   => esc_html__( 'Hide', 'docy' ),
					'text_width' => 80,
					'default'    => true,
				),
				array(
					'title'       		=> esc_html__( 'Subtitle color', 'docy' ),
					'id'          		=> 'sbnr_subtitle_color',
					'type'        		=> 'color',
					'output'     		=> '.search-banner-light .doc_banner_content p',
					'output_mode' 		=> 'color',
				),
			),
		),

		array(
			'id'     => 'sbnr_search_fieldset',
			'type'   => 'fieldset',
			'title'  =>  esc_html__( 'Banner Search Form', 'docy' ),
			'dependency' => array( 'is_banner', '==', '1' ),
			'fields' => array(
				array(
					'id'         => 'is_sbnr_search',
					'type'       => 'switcher',
					'title'      => esc_html__( 'Search Form', 'docy' ),
					'subtitle'   => esc_html__( 'Enable/Disable the Search Form on the banner area.', 'docy' ),
					'text_on'    => esc_html__( 'Enabled', 'docy' ),
					'text_off'   => esc_html__( 'Disabled', 'docy' ),
					'text_width' => 92,
					'default'    => true,
				),
				array(
					'title'       		=> esc_html__( 'Placeholder color', 'docy' ),
					'id'          		=> 'sbnr_placeholder_color',
					'type'        		=> 'color',
					'output'     		=> '.search-banner-light .header_search_form .header_search_form_info .form-group .input-wrapper input::placeholder',
					'output_mode' 		=> 'color',
					'output_important'	=> true,
				),
				array(
					'title'       		=> esc_html__( 'Background color', 'docy' ),
					'id'          		=> 'sbnr_search_bg_color',
					'type'        		=> 'color',
					'output'     		=> '.header_search_form .input-wrapper input',
					'output_mode' 		=> 'background',
					'output_important'	=> true,
				),
			),
		),

		array(
			'title'      => esc_html__( 'Search Banner Layout', 'docy' ),
			'id'         => 'search_banner_layout',
			'type'       => 'select',
			'options'    => array(
				'default'      => esc_html__( 'Default', 'docy' ),
				'el-templates' => esc_html__( 'Elementor Template', 'docy' ),
			),
			'default'    => 'default',
			'dependency' => array( 'is_banner', '==', '1' )
		),

		array(
			'title'      => esc_html__( 'Select Elementor Layout', 'docy' ),
			'id'         => 'search_banner_el_layout',
			'type'       => 'select',
			'options'    => docy_elementor_template(),
			'default'    => 'default',
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'el-templates' )
			),
		),

		array(
			'title'      => esc_html__( 'Search Banner Design', 'docy' ),
			'id'         => 'select_search_banner',
			'type'       => 'image_select',
			'options'    => array(
				'light'     => DOCY_DIR_IMG . '/search-banners/simple-light.jpg',
				'aesthetic' => DOCY_DIR_IMG . '/search-banners/gradient.jpg',
			),
			'default'    => 'light',
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		array(
			'title'          => esc_html__( 'Padding', 'docy' ),
			'subtitle'       => esc_html__( 'Padding around the Search Banner. Input the padding as clockwise (Top Right Bottom Left)', 'docy' ),
			'id'             => 'sbnr_padding',
			'type'           => 'spacing',
			'output'         => array( '.doc_banner_area.sbnr-global' ),
			'mode'           => 'padding',
			'units'          => array( 'em', 'px', '%' ),      // You can specify a unit value. Possible: px, em, %
			'units_extended' => 'true',
			'dependency'     => array(
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		// ---- Background Images -----
		array(
			'id'    => 'sbnr_bg_styling',
			'title' => esc_html__( 'Background Styling', 'docy' ),
			'type'  => 'heading',
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
			)
		),

		array(
			'title'      => esc_html__( 'Banner Background Style', 'docy' ),
			'subtitle'   => esc_html__( 'Select a pre-designed banner background style.', 'docy' ),
			'id'         => 'search_banner_bg',
			'type'       => 'image_select',
			'default'    => 'color',
			'options'    => docy_banner_bg_style(),
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		array(
			'title'      => esc_html__( 'Background', 'docy' ),
			'id'         => 'sbnr_light_bg',
			'type'       => 'background',
			'default'    => array(
				'background-image' => DOCY_DIR_IMG . '/banner-bg.png'
			),
			'output'     => '.doc_banner_area.search-banner-light',
			'dependency' => array(
				array( 'select_search_banner', '==', 'light' ),
				array( 'search_banner_layout', '==', 'default' ),
				array( 'is_banner', '==', '1' )
			),
		),

		array(
			'title'      => esc_html__( 'Background Image', 'docy' ),
			'id'         => 'sbnr_bg_image2',
			'type'       => 'media',
			'default'    => array(
				'url' => DOCY_DIR_IMG . '/search-banners/banner-bg.jpeg'
			),
			'dependency' => array(
				array( 'select_search_banner', '==', 'aesthetic' ),
				array( 'search_banner_layout', '==', 'default' ),
				array( 'is_banner', '==', '1' )
			),
		),

		array(
			'title'      => esc_html__( 'Left Leaf Image', 'docy' ),
			'id'         => 'sbanner_left_image',
			'type'       => 'media',
			'default'    => array(
				'url' => DOCY_DIR_IMG . '/v.svg'
			),
			'dependency' => array(
				array( 'select_search_banner', '==', 'aesthetic' ),
				array( 'search_banner_layout', '==', 'default' ),
				array( 'is_banner', '==', '1' )
			),
		),

		array(
			'title'      => esc_html__( 'Right Leaf Image', 'docy' ),
			'id'         => 'sbanner_right_image',
			'type'       => 'media',
			'default'    => array(
				'url' => DOCY_DIR_IMG . '/home_one/b_leaf.svg'
			),
			'dependency' => array(
				array( 'select_search_banner', '==', 'aesthetic' ),
				array( 'search_banner_layout', '==', 'default' ),
				array( 'is_banner', '==', '1' )
			),
		),

		array(
			'title'      => esc_html__( 'Man Image', 'docy' ),
			'id'         => 'sbanner_man_image',
			'type'       => 'media',
			'default'    => array(
				'url' => DOCY_DIR_IMG . '/home_one/b_man_two.png'
			),
			'dependency' => array(
				array( 'select_search_banner', '==', 'aesthetic' ),
				array( 'search_banner_layout', '==', 'default' ),
				array( 'is_banner', '==', '1' )
			),
		),

		array(
			'title'      => esc_html__( 'Flower Image', 'docy' ),
			'id'         => 'sbanner_flower_image',
			'type'       => 'media',
			'default'    => array(
				'url' => DOCY_DIR_IMG . '/home_one/flower.png'
			),
			'dependency' => array(
				array( 'select_search_banner', '==', 'aesthetic' ),
				array( 'search_banner_layout', '==', 'default' ),
				array( 'is_banner', '==', '1' )
			),
		),

		array(
			'title'      => esc_html__( 'Background Shape Image', 'docy' ),
			'subtitle'   => esc_html__( 'We used here a transparent image that are containing stars. So you can use here similar transparent image or any other image.',
				'docy' ),
			'id'         => 'sbanner_bg_image',
			'type'       => 'media',
			'default'    => array(
				'url' => DOCY_DIR_IMG . '/home_one/banner_bg.png'
			),
			'dependency' => array(
				array( 'select_search_banner', '==', 'aesthetic' ),
				array( 'search_banner_layout', '==', 'default' ),
				array( 'is_banner', '==', '1' )
			),
		),

		array(
			'title'      => esc_html__( 'Wave Shape 01', 'docy' ),
			'subtitle'   => esc_html__( 'We used here a transparent wave shape image. You can use here similar transparent shape image or any other image.',
				'docy' ),
			'id'         => 'sbanner_shape1',
			'type'       => 'media',
			'default'    => array(
				'url' => DOCY_DIR_IMG . '/shap_01.png'
			),
			'dependency' => array(
				array( 'select_search_banner', '==', 'aesthetic' ),
				array( 'search_banner_layout', '==', 'default' ),
				array( 'is_banner', '==', '1' )
			),
		),

		array(
			'title'      => esc_html__( 'Wave Shape 02', 'docy' ),
			'subtitle'   => esc_html__( 'We used here a transparent wave shape image. You can use here similar transparent shape image or any other image.',
				'docy' ),
			'id'         => 'sbanner_shape2',
			'type'       => 'media',
			'default'    => array(
				'url' => DOCY_DIR_IMG . '/shap_02.png'
			),
			'dependency' => array(
				array( 'select_search_banner', '==', 'aesthetic' ),
				array( 'search_banner_layout', '==', 'default' ),
				array( 'is_banner', '==', '1' )
			),
		),

		// ---- Overlay Color -----
		array(
			'id'    => 'sbnr_overlay',
			'title' => esc_html__( 'Banner Overlay', 'docy' ),
			'type'  => 'heading',
			'dependency' => array(
				array( 'is_banner', '==', '1' )
			),
		),

		array(
			'id'         => 'is_banner_overlay',
			'type'       => 'switcher',
			'title'      => __( 'Overlay', 'docy' ),
			'subtitle'   => __( 'Show/hide banner overlay color', 'docy' ),
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => false,
			'dependency' => array(
				array( 'is_banner', '==', '1' )
			),
		),

		array(
			'id'           => 'sbnr_overlay_color',
			'type'         => 'color_group',
			'title'        => esc_html__( 'Overlay Gradient Color', 'docy' ),
			'subtitle'     => esc_html__( 'Use transparent colors to see the blur effect.', 'docy' ),
			'options'      => array(
				'gradient_bg_color-from' => esc_html__( 'From', 'docy' ),
				'gradient_bg_color-to'   => esc_html__( 'To', 'docy' ),
			),
			'output'       => ':root',
			'dependency'   => array(
				array( 'is_banner_overlay', '==', '1' ),
			),
		),

		array(
			'id'         => 'is_sbnr_blur',
			'type'       => 'switcher',
			'title'      => __( 'Overlay Blur', 'docy' ),
			'subtitle'   => __( 'Enable/disable background overlay blur effect.', 'docy' ),
			'text_on'    => esc_html__( 'Yes', 'docy' ),
			'text_off'   => esc_html__( 'No', 'docy' ),
			'default'    => false,
			'dependency' => array(
				array( 'is_banner_overlay', '==', '1' ),
			)
		),

		array(
			'id'               => 'sbnr_blur_density',
			'type'             => 'slider',
			'title'            => esc_html__( 'Blur Density', 'docy' ),
			'subtitle'         => esc_html__( 'Adjust the blur intensity for Post Single, Forum pages, Search Results page', 'docy' ),
			'default'          => 10,
			'min'              => 0,
			'max'              => 50,
			'step'             => 1,
			'dependency' => array(
				array( 'is_sbnr_blur', '==', '1' ),
				array( 'is_banner_overlay', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),


		// ---- Search Form -----
		array(
			'id'    => 'sbnr_search_form_heading',
			'title' => esc_html__( 'Search Form', 'docy' ),
			'type'  => 'heading',
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
			)
		),

		array(
			'id'         => 'banner_search_placeholder',
			'type'       => 'text',
			'title'      => esc_html__( 'Search Placeholder', 'docy' ),
			'default'    => esc_html__( 'Search ("/" to focus)', 'docy' ),
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		array(
			'title'      => esc_html__( 'Focus Search', 'docy' ),
			'subtitle'   => esc_html__( 'When you enter the cursor in the search field, a transparent overlay color will appear on other elements of the page.',
				'docy' ),
			'id'         => 'is_focus_search',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Yes', 'docy' ),
			'text_off'   => esc_html__( 'No', 'docy' ),
			'text_width' => 80,
			'default'    => 1,
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		array(
			'title'      => esc_html__( 'Focus Search by /', 'docy' ),
			'subtitle'   => esc_html__( 'If you enable this setting, your website visitors can focus (enter the mouse cursor) on the search form by pressing the "/" key of the keyboard.',
				'docy' ),
			'id'         => 'is_focus_by_slash',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Yes', 'docy' ),
			'text_off'   => esc_html__( 'No', 'docy' ),
			'text_width' => 80,
			'default'    => 1,
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		// ---- Search Keywords -----
		array(
			'id'    => 'sbnr_search_keywords_heading',
			'title' => esc_html__( 'Search Keywords', 'docy' ),
			'type'  => 'heading',
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
			)
		),

		array(
			'title'      => esc_html__( 'Search Keywords', 'docy' ),
			'id'         => 'is_keywords',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Yes', 'docy' ),
			'text_off'   => esc_html__( 'No', 'docy' ),
			'text_width' => 80,
			'default'    => 1,
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		array(
			'title'      => esc_html__( 'Keywords Label', 'docy' ),
			'id'         => 'keywords_label',
			'type'       => 'text',
			'default'    => esc_html__( 'Popular Searches', 'docy' ),
			'dependency' => array(
				array( 'is_keywords', '==', '1' ),
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		array(
			'title'       		=> esc_html__( 'Label color', 'docy' ),
			'id'          		=> 'sbnr_label_color',
			'type'        		=> 'color',
			'output'     		=> '.search-banner-light .header_search_keyword .header-search-form__keywords-label',
			'output_mode' 		=> 'color',
			'output_important'	=> true,
			'dependency'        => array(
				array( 'is_keywords', '==', '1' ),
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		// keyword by dynamic || static select
		array(
			'id'         => 'keywords_by',
			'type'       => 'select',
			'title'      => esc_html__( 'Keywords By', 'docy' ),
			'subtitle'   => esc_html__( 'Select your preferred keywords type.', 'docy' ),
			'desc'       => esc_html__( 'Static keywords are predefined, while dynamic keywords are generated by queries from website visitors', 'docy' ),
			'options'    => array(
				'static'  => esc_html__( 'Static', 'docy' ),
				'dynamic' => esc_html__( 'Dynamic (Sort by popular)', 'docy' ),
			),
			'default'    => 'static',
			'dependency' => array(
				array( 'is_keywords', '==', '1' ),
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			)
		),

		array(
			'id'         => 'keywords_limit',
			'type'       => 'slider',
			'title'      => esc_html__( 'Keywords Limit', 'docy' ),
			'subtitle'   => esc_html__( 'Set the number of keywords to show.', 'docy' ),
			'default'    => 6,
			'min'        => 1,
			'max'        => 30,
			'step'       => 1,
			'dependency' => array(
				array( 'is_keywords', '==', '1' ),
				array( 'is_banner', '==', '1' ),
				array( 'keywords_by', '==', 'dynamic' ),
				array( 'search_banner_layout', '==', 'default' )
			)
		),

		array(
			'title'      => esc_html__( 'Keywords', 'docy' ),
			'id'         => 'doc_keywords',
			'type'       => 'repeater',
			'fields'     => array(

				array(
					'id'    => 'doc_keyword',
					'type'  => 'text',
					'title' => esc_html__( 'Text', 'docy' ),
				),

			),
			'add_text'   => esc_html__( 'Add Keyword', 'docy' ),
			'dependency' => array(
				array( 'is_keywords', '==', '1' ),
				array( 'is_banner', '==', '1' ),
				array( 'keywords_by', '==', 'static' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		array(
			'title'       		=> esc_html__( 'Keywords color', 'docy' ),
			'id'          		=> 'sbnr_keywords_color',
			'type'        		=> 'color',
			'output'     		=> '.search-banner-light .header_search_keyword ul li a',
			'output_mode' 		=> 'color',
			'output_important'	=> true,
			'dependency'        => array(
				array( 'is_keywords', '==', '1' ),
				array( 'is_banner', '==', '1' ),
				array( 'search_banner_layout', '==', 'default' )
			),
		),

		// ---- Ajax Search Results -----
		array(
			'id'    => 'sbnr_search_results_heading',
			'title' => esc_html__( 'Ajax Search Results', 'docy' ),
			'type'  => 'heading',
			'dependency' => array(
				array( 'is_banner', '==', '1' ),
			)
		),

		// Info
		array(
			'id'      => 'sbnr_search_results_note',
			'type'    => 'notice',
			'style'   => 'info',
			'title'   => esc_html__( 'Note:', 'docy' ),
			'icon'    => 'dashicons dashicons-info',
			'content' => sprintf(
			    esc_html__( 'Get the Ajax Search results related settings in %1$s Theme Settings > General > Search Results%2$s Settings page', 'docy' ),
			    '<a href="' . esc_url( $admin_url . '/admin.php?page=docy-options#tab=general/search-results' ) . '">',
			    '</a>'
			)
		),
	)
) );


/**
 * Breadcrumbs Options
 */
CSF::createSection( $prefix, array(
	'parent'     => 'header_sec',
	'title'      => esc_html__( 'Breadcrumbs', 'docy' ),
	'id'         => 'breadcrumbs_opt',
	'subsection' => true,
	'icon'       => '',
	'fields'     => array(

		// ---- Breadcrumbs -----
		array(
			'id'    => 'breadcrumbs_header',
			'type'  => 'heading',
			'title' => esc_html__( 'Breadcrumbs', 'docy' ),
			'subtitle' => esc_html__( 'Breadcrumbs are a great way to help your visitors navigate your website. The breadcrumbs displays below to the Search Banner.', 'docy' )
		),

		array(
			'id'         => 'is_breadcrumb',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Show/Hide Breadcrumb', 'docy' ),
			'subtitle'   => esc_html__( 'Toggle this switch to Show/Hide the Breadcrumb bar.', 'docy' ),
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => true, // or false
		),

		array(
			'id'         => 'breadcrumb_home',
			'type'       => 'text',
			'title'      => esc_html__( 'Frontpage Name', 'docy' ),
			'default'    => esc_html__( 'Home', 'docy' ),
			'dependency' => array( 'is_breadcrumb', '==', 'true' ),
		),

		array(
			'id'         => 'breadcrumb_update_text',
			'type'       => 'text',
			'title'      => esc_html__( 'Updated Text', 'docy' ),
			'default'    => esc_html__( 'Updated on', 'docy' ),
			'dependency' => array( 'is_breadcrumb', '==', 'true' )
		),

		// ---- Breadcrumb background color -----
		array(
			'title'       		=> esc_html__( 'Background color', 'docy' ),
			'id'          		=> 'breadcrumb_bg_color',
			'type'        		=> 'color',
			'output'     		=> '.page_breadcrumb',
			'output_mode' 		=> 'background-color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_breadcrumb', '==', '1' )
		),

		array(
			'title'       		=> esc_html__( 'Text color', 'docy' ),
			'id'          		=> 'breadcrumb_text_color',
			'type'        		=> 'color',
			'output'     		=> '.breadcrumb .breadcrumb-item a, .page_breadcrumb .date, .bbpress a.bbp-breadcrumb-home, .breadcrumb .breadcrumb-item, .breadcrumb .breadcrumb-item + .breadcrumb-item:before',
			'output_mode' 		=> 'color',
			'output_important'	=> true,
			'dependency'  		=> array( 'is_breadcrumb', '==', '1' )
		),
	)
) );