<?php
CSF::createSection( $prefix, array(
	'title' => esc_html__( 'General', 'docy' ),
	'id'    => 'general_settings',
	'icon'  => 'dashicons dashicons-admin-generic',
) );

CSF::createSection( $prefix, array(
	'parent'     => 'general_settings',
	'title'      => esc_html__( 'General Settings', 'docy' ),
	'id'         => 'sidebar_editor_opt',
	'icon'       => '',
	'subsection' => true,
	'fields'     => array(

		array(
			'id'       => 'is_sidebar_editor',
			'type'     => 'button_set',
			'title'    => esc_html__( 'Sidebar Widgets Editor', 'docy' ),
			'options'  => array(
				'classic'   => esc_html__( 'Classic', 'docy' ),
				'gutenburg' => esc_html__( 'Gutenberg', 'docy' ),
			),
			'subtitle' => esc_html__( 'If enabled classic, it will be restored the old Widgets screen and disables the block editor from managing widgets.',
				'docy' ),
			'default'  => 'classic'
		),

		array(
			'title'    => esc_html__( 'Custom Post Types', 'docy' ),
			'subtitle' => esc_html__( 'If you disable a post type, all the assets, widgets associated with that post type will be disabled.', 'docy' ),
			'id'       => 'post_type_disable',
			'type'     => 'checkbox',
			'options'  => [
				'faq'       => esc_html__( 'FAQs', 'docy' ),
				'video'     => esc_html__( 'Videos', 'docy' ),
			],
			'default'  => [
				'faq'       => true,
				'video'     => true,
			]
		),

		array(
			'id'         => 'is_page_toc',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Page TOC', 'docy' ),
			'subtitle'   => esc_html__( 'Enable/disable TOC (Table of Contents) in Page sidebar.', 'docy' ),
			'text_on'    => esc_html__( 'Enable', 'docy' ),
			'text_off'   => esc_html__( 'Disable', 'docy' ),
			'text_width' => 100,
			'default'    => false,
		),

		array(
			'id'         => 'is_post_toc',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Post TOC', 'docy' ),
			'subtitle'   => esc_html__( 'Enable/disable TOC (Table of Contents) in Post sidebar.', 'docy' ),
			'text_on'    => esc_html__( 'Enable', 'docy' ),
			'text_off'   => esc_html__( 'Disable', 'docy' ),
			'text_width' => 100,
			'default'    => false,
		),
	),
) );


/**
 * Preloader
 */
CSF::createSection( $prefix, array(
	'parent'     => 'general_settings',
	'title'      => esc_html__( 'Preloader', 'docy' ),
	'id'         => 'preloader_opt',
	'icon'       => '',
	'subsection' => true,
	'fields'     => array(

		array(
			'title' => esc_html__( 'Preloader', 'docy' ),
			'id'    => 'preloader_options',
			'type'  => 'heading',
		),

		array(
			'id'         => 'is_preloader',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Pre-loader', 'docy' ),
			'text_on'    => esc_html__( 'Enable', 'docy' ),
			'text_off'   => esc_html__( 'Disable', 'docy' ),
			'text_width' => 100,
			'default'    => true,
		),

		array(
			'title'      => esc_html__( 'Enable Pre-loader on', 'docy' ),
			'id'         => 'preloader_pages',
			'type'       => 'select',
			'options'    => [
				'all'            => esc_html__( 'All Pages', 'docy' ),
				'specific_pages' => esc_html__( 'Specific Pages', 'docy' ),
			],
			'default'    => 'all',
			'dependency' => array( 'is_preloader', '==', '1' ),

		),

		array(
			'title'      => esc_html__( 'Page IDs', 'docy' ),
			'subtitle'   => esc_html__( "Input the multiple page IDs in comma separated format.", 'docy' ),
			'desc'       => sprintf( esc_html__( '%s How to find page ID %s', 'docy' ), '<a href="https://is.gd/xM75oQ" target="_blank" rel="noopener noreferrer">', '</a>' ),
			'id'         => 'preloader_page_ids',
			'type'       => 'text',
			'dependency' => array( 'is_preloader|preloader_pages', '==|==', '1|specific_pages' )
		),

		/**
		 * Preloader Logo
		 */
		array(
			'id'         => 'preloader_logo',
			'type'       => 'media',
			'title'      => esc_html__( 'Pre-loader Logo', 'docy' ),
			'preview'    => true,
			'compiler'   => true,
			'dependency' => array( 'is_preloader', '==', '1' ),
			'default'    => array(
				'url' => DOCY_DIR_IMG . '/spinner_logo.png',
			)
		),
		array(
			'dependency' => array( 'is_preloader', '==', '1' ),
			'id'         => 'logo_title',
			'type'       => 'text',
			'title'      => esc_html__( 'Logo Title', 'docy' ),
			'default'    => get_bloginfo( 'name' ),
		),
		array(
			'dependency'  => array( 'is_preloader', '==', '1' ),
			'title'       => esc_html__( 'Logo Title Color', 'docy' ),
			'id'          => 'preloader_logo_title_color',
			'type'        => 'color',
			'output'      => '#preloader .round_spinner h4',
			'output_mode' => 'color',
		),

		/**
		 * Preloader Title
		 */
		array(
			'dependency' => array( 'is_preloader', '==', '1' ),
			'id'         => 'preloader_title',
			'type'       => 'text',
			'title'      => esc_html__( 'Preloader Title', 'docy' ),
			'default'    => 'Did You Know?'
		),
		array(
			'dependency'  => array( 'is_preloader', '==', '1' ),
			'title'       => esc_html__( 'Preloader Title Color', 'docy' ),
			'id'          => 'preloader_title_color',
			'type'        => 'color',
			'output'      => '#preloader .head',
			'output_mode' => 'color',
		),

		/**
		 * Preloader Quotes
		 */
		array(
			'dependency' => array( 'is_preloader', '==', '1' ),
			'id'         => 'preloader_quotes',
			'type'       => 'repeater',
			'title'      => esc_html__( 'Quotes', 'docy' ),
			'subtitle'   => esc_html__( 'The quotes will display randomly under the title.', 'docy' ),
			'fields'     => array(
				array(
					'id'    => 'pre-quote',
					'type'  => 'text',
					'title' => esc_html__( 'Quotes', 'docy' ),
				),

			),

			'default' => array(
				array(
					'pre-quote' => esc_html__( 'We design Docy for the readers, optimizing not for page views or engagement', 'docy' ),
				),

				array(
					'pre-quote' => esc_html__( 'Docy turns out that context is a key part of learning.', 'docy' ),
				),

				array(
					'pre-quote' => esc_html__( 'You can create any type of product documentation with Docy', 'docy' ),
				),

				array(
					'pre-quote' => esc_html__( 'Advanced visual search system powered by Ajax', 'docy' ),
				),

			)
		),

		array(
			'dependency'  => array( 'is_preloader', '==', '1' ),
			'title'       => esc_html__( 'Preloader Quotes Color', 'docy' ),
			'id'          => 'preloader_quotes_color',
			'type'        => 'color',
			'output'      => '#preloader p',
			'output_mode' => 'color',
		),
	),
) );


/**
 * Back to Top settings
 */
CSF::createSection( $prefix, array(
	'parent'     => 'general_settings',
	'title'      => esc_html__( 'Back to Top', 'docy' ),
	'id'         => 'back_to_top_btn',
	'icon'       => '',
	'subsection' => true,
	'fields'     => array(
		array(
			'title' => esc_html__( 'Back to Top', 'docy' ),
			'id'    => 'backtotop_options',
			'type'  => 'heading',
		),

		array(
			'title'      => esc_html__( 'Back to Top Button', 'docy' ),
			'subtitle'   => esc_html__( 'Show/hide back to top button globally settings', 'docy' ),
			'id'         => 'is_back_to_top_btn_switcher',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 100,
			'default'    => '1',
		),

		array(
			'title'      => esc_html__( 'Position', 'docy' ),
			'id'         => 'bt_position',
			'type'       => 'button_set',
			'options'    => array(
				'left'   => esc_html__( 'Left', 'docy' ),
				'right'  => esc_html__( 'Right', 'docy' ),
			),
			'default'    => 'right',
			'dependency' => array( 'is_back_to_top_btn_switcher', '==', 1 )
		),

		// Horizontal Position
		array(
			'id'               => 'bt_custom_position',
			'type'             => 'spacing',
			'title'            => esc_html__( 'Margin', 'docy' ),
			'desc'             => esc_html__( 'Set the margin to adjust the button in pixel.', 'docy' ),
			'output'           => array( '#back-to-top' ),
			'output_important' => true,
			'output_mode'      => 'margin',
			'units'            => array( 'px' ),
		),


		/**
		 * Button Normal Colors
		 */
		array(
			'id'         => 'normal_color_start',
			'type'       => 'subheading',
			'title'      => esc_html__( 'Normal Color', 'docy' ),
			'indent'     => true,
			'dependency' => array( 'is_back_to_top_btn_switcher', '==', 1 )
		),

		array(
			'title'      => esc_html__( 'Icon Color', 'docy' ),
			'id'         => 'back_to_top_btn_icon_color',
			'type'       => 'color',
			'dependency' => array( 'is_back_to_top_btn_switcher', '==', 1 ),
			'output'     => array(
				'color' => '#back-to-top::after'
			),
		),

		array(
			'title'       => esc_html__( 'Background Color', 'docy' ),
			'id'          => 'back_to_top_btn_bg_color',
			'type'        => 'color',
			'rgba'        => true,
			'output'      => '#back-to-top',
			'output_mode' => 'background-color',
			'dependency'  => array( 'is_back_to_top_btn_switcher', '==', 1 )

		),

		/**
		 * Button Hover Colors
		 */
		array(
			'id'         => 'hover_color_start',
			'type'       => 'subheading',
			'title'      => esc_html__( 'Hover Color', 'docy' ),
			'indent'     => true,
			'dependency' => array( 'is_back_to_top_btn_switcher', '==', 1 )
		),

		array(
			'title'      => esc_html__( 'Icon Color', 'docy' ),
			'id'         => 'back_to_top_btn_icon_hover_color',
			'type'       => 'color',
			'output'     => array(
				'color' => '#back-to-top:hover::after'
			),
			'dependency' => array( 'is_back_to_top_btn_switcher', '==', 1 )
		),

		array(
			'title'       => esc_html__( 'Background Color', 'docy' ),
			'id'          => 'back_to_top_btn_bg_hover_color',
			'type'        => 'color',
			'output_mode' => 'background-color',
			'rgba'        => true,
			'output'      => array(
				'background-color' => '#back-to-top:hover'
			),
			'dependency'  => array( 'is_back_to_top_btn_switcher', '==', 1 )
		)
	)
) );


/**
 * Search result settings
 */
CSF::createSection( $prefix, array(
	'parent'     => 'general_settings',
	'title'      => esc_html__( 'Search Results', 'docy' ),
	'id'         => 'search_results_opt',
	'icon'       => '',
	'subsection' => true,
	'fields'     => array(

		array(
			'title' => esc_html__( 'Search Result', 'docy' ),
			'id'    => 'ajax_options',
			'type'  => 'heading',
		),

		array(
			'title'    => esc_html__( 'Search Result Limit', 'docy' ),
			'subtitle' => esc_html__( 'This will limit the doc sections and articles in Ajax live search results. Input -1 to show all results.', 'docy' ),
			'id'       => 'doc_result_limit',
			'type'     => 'number',
			'min'      => -1,
			'step'     => 1,
			'default'  => '-1',
		),

		array(
			'id'         => 'is_ajax_search_tab',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Tab Filters', 'docy' ),
			'subtitle'   => esc_html__( 'If you disable it, the search filter tabs will be removed.', 'docy' ),
			'text_on'    => esc_html__( 'Enable', 'docy' ),
			'text_off'   => esc_html__( 'Disable', 'docy' ),
			'text_width' => 100,
			'default'    => true,
		),

		array(
			'id'         => 'is_search_result_breadcrumb',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Breadcrumb', 'docy' ),
			'subtitle'   => esc_html__( 'Show / Hide the breadcrumbs in search results', 'docy' ),
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'default'    => true,
			'text_width' => 100,
		),

		array(
			'id'         => 'is_search_result_thumbnail',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Thumbnail', 'docy' ),
			'subtitle'   => esc_html__( 'Show / Hide the thumbnail in search results', 'docy' ),
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'default'    => true,
			'text_width' => 100,
		),

		array(
			'id'          => 'sbnr_post_types',
			'type'        => 'select',
			'title'       => esc_html__( 'Select Post Types to Include', 'docy' ),
			'subtitle'    => esc_html__( 'Choose one or more post types (e.g., Pages, Posts, Products) to filter results.', 'docy' ),
			'chosen'      => true,
			'ajax'        => true,
			'multiple'	  => true,
			'options'     => 'post_type',
			'query_args'  => array(
				'post_type' => 'any'
			),
			'default' => is_plugin_active('eazydocs/eazydocs.php') ? ['page','post', 'docs'] : ['post','page'],
		),
	)
) );