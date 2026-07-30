<?php
CSF::createSection( 'docy_opt', array(
	'title' => esc_html__( 'Blog Pages', 'docy' ),
	'id'    => 'blog_page',
	'icon'  => 'dashicons dashicons-admin-post',
) );

/**
 * Blog archive settings
 */
CSF::createSection( 'docy_opt', array(
	'parent'     => 'blog_page',
	'title'      => esc_html__( 'Blog archive', 'docy' ),
	'id'         => 'blog_meta_opt',
	'icon'       => '',
	'subsection' => true,
	'fields'     => array(

		array(
			'title' => esc_html__( 'Blog archive', 'docy' ),
			'id'    => 'Blog_archive_opt',
			'type'  => 'heading',
		),

		array(
			'title'    => esc_html__( 'Blog page Title', 'docy' ),
			'subtitle' => esc_html__( 'Controls the title text that displays on the Blog page Titlebar/Search Banner.', 'docy' ),
			'id'       => 'blog_title',
			'type'     => 'text',
			'default'  => esc_html__( 'Blog', 'docy' )
		),

		array(
			'title'    => esc_html__( 'Blog page Subtitle', 'docy' ),
			'subtitle' => esc_html__( 'Controls the title text that displays on the Blog page Titlebar/Search Banner.".', 'docy' ),
			'id'       => 'blog_subtitle',
			'type'     => 'textarea',
		),

		array(
			'title'    => esc_html__( 'Blog Layout', 'docy' ),
			'subtitle' => esc_html__( 'The Blog layout will also apply on the blog category and tag pages.', 'docy' ),
			'id'       => 'blog_layout',
			'type'     => 'image_select',
			'options'  => array(
				'list'          => DOCY_DIR_IMG . '/layouts/list.jpg',
				'grid'          => DOCY_DIR_IMG . '/layouts/blog_grid.jpg',
				'blog_category' => DOCY_DIR_IMG . '/layouts/blog_grid_category_tab.jpg',
			),
			'default'  => 'list'
		),

        array(
            'id'       => 'is_blog_masonry',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Enable Masonry Layout', 'docy' ),
            'subtitle' => esc_html__( 'Enable masonry layout for blog grid style.', 'docy' ),
            'default'  => false,
            'dependency' => array( 'blog_layout', 'any', 'grid,blog_category' ),
        ),

		array(
			'title'      => esc_html__( 'Column', 'docy' ),
			'id'         => 'blog_column',
			'type'       => 'select',
			'options'    => [
				'6' => esc_html__( 'Two', 'docy' ),
				'4' => esc_html__( 'Three', 'docy' ),
				'3' => esc_html__( 'Four', 'docy' ),
			],
			'default'    => '6',
			'dependency' => array( 'blog_layout', 'any', 'grid,blog_category' ),
		),

		array(
			'title'         => esc_html__( 'Post Title Length', 'docy' ),
			'subtitle'      => esc_html__( 'Set the Blog post title length in character', 'docy' ),
			'id'            => 'post_title_length',
			'type'          => 'slider',
			'default'       => 50,
			"min"           => 1,
			"step"          => 1,
			"max"           => 200,
			'display_value' => 'text',
		),

		array(
			'title'         => esc_html__( 'Post Word Excerpt', 'docy' ),
			'subtitle'      => esc_html__( 'Define the number of words to display for each post on the blog page. If the post excerpt is empty, the content will be taken from the post content.',
				'docy' ),
			'id'            => 'blog_excerpt',
			'type'          => 'slider',
			'default'       => 40,
			"min"           => 1,
			"step"          => 1,
			"max"           => 100,
			'display_value' => 'text'
		),

		array(
			'title'      => esc_html__( 'Continue Reading Label', 'docy' ),
			'id'         => 'blog_continue_read',
			'type'       => 'text',
			'default'    => esc_html__( 'Continue Reading', 'docy' ),
			'dependency' => array(
				array( 'blog_layout', '==', 'list' ),
			),
		),

		array(
			'title'      => esc_html__( 'Post Meta', 'docy' ),
			'subtitle'   => esc_html__( 'Show/hide post meta on blog archive page', 'docy' ),
			'id'         => 'is_post_meta',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
		),

		array(
			'title'      => esc_html__( 'Post category', 'docy' ),
			'id'         => 'is_post_cat',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
			'dependency' => array( 'is_post_meta', '==', 1 )
		),

		array(
			'title'      => esc_html__( 'Post Date', 'docy' ),
			'id'         => 'is_post_date',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
			'dependency' => array( 'is_post_meta', '==', 1 )
		),

		array(
			'title'      => esc_html__( 'Post Reading Time', 'docy' ),
			'id'         => 'is_post_reading_time',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
			'dependency' => array( 'is_post_meta', '==', 1 )
		),

		array(
			'title'      => esc_html__( 'Author', 'docy' ),
			'id'         => 'is_post_author',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
			'dependency' => array( 'is_post_meta', '==', 1 )
		),
	)
) );


/**
 * Blog Title Icon
 */
CSF::createSection( 'docy_opt', array(
	'parent'     => 'blog_page',
	'title'      => esc_html__( 'Post Format Icon', 'docy' ),
	'id'         => 'blog_post_format_icon_opt',
	'icon'       => '',
	'subsection' => true,
	'fields'     => array(

		array(
			'title'      => esc_html__( 'Post Icon', 'docy' ),
			'subtitle'   => esc_html__( 'Post Icon show', 'docy' ),
			'id'         => 'is_post_format_icon',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => false,
		),

		array(
			'id'         => 'b_standard_icon',
			'type'       => 'icon',
			'title'      => esc_html__( 'Post Standard', 'docy' ),
			'default'    => 'icon_chat_alt',
			'dependency' => array( 'is_post_format_icon', '==', '1' )
		),
		array(
			'id'         => 'b_video_icon',
			'type'       => 'icon',
			'title'      => esc_html__( 'Post Video', 'docy' ),
			'default'    => 'social_youtube',
			'dependency' => array( 'is_post_format_icon', '==', '1' )
		),

		array(
			'id'          => 'b_icon_size',
			'type'        => 'slider',
			'title'       => esc_html__( 'Icon Size', 'docy' ),
			'unit'        => 'px',
			'output'      => '.blog_classic_item .b_top_post_content .post_icon i',
			'output_mode' => 'font-size',
			'min'         => 10,
			'max'         => 100,
			'step'        => 1,
			'dependency'  => array( 'is_post_format_icon', '==', '1' )
		),

	)
) );


/**
 * Post single
 */
CSF::createSection( 'docy_opt', array(
	'parent'     => 'blog_page',
	'title'      => esc_html__( 'Blog single', 'docy' ),
	'id'         => 'blog_single_opt',
	'icon'       => '',
	'subsection' => true,
	'fields'     => array(

		//=== Title-bar
		array(
			'title' => esc_html__( 'Title Bar', 'docy' ),
			'type'  => 'heading',
		),

		array(
			'id'      => 'banner_type',
			'type'    => 'image_select',
			'title'   => esc_html__( 'Banner Layout', 'docy' ),
			'desc'    => esc_html__( 'Select the default banner layout for blog post single page.', 'docy' ),
			'options' => array(
				'colorful' => DOCY_DIR_IMG . '/layouts/banner_single_colorful.jpg',
				'classic'  => DOCY_DIR_IMG . '/layouts/banner_single_classic.jpg',
				'curved'   => DOCY_DIR_IMG . '/layouts/banner_single_curved.jpg',
                'gradient' => DOCY_DIR_IMG . '/layouts/banner_single_gradient.png',
			),
			'class'   => 'docy_blog_single_banner',
			'default' => 'colorful'
		),


		// Media Field id name shape1, shape2, shape3,  etc.
		array(
			'id'         => 'banner_shape_01',
			'type'       => 'media',
			'title'      => esc_html__( 'Shape 1', 'docy' ),
			'dependency' => array( 'banner_type', '==', 'colorful' ),
			'default'    => [
				'url' => DOCY_DIR_IMG . '/banner-blog/banner_shape_1.png',
			]
		),

		array(
			'id'         => 'banner_shape_02',
			'type'       => 'media',
			'title'      => esc_html__( 'Shape 2', 'docy' ),
			'dependency' => array( 'banner_type', '==', 'colorful' ),
			'default'    => [
				'url' => DOCY_DIR_IMG . '/banner-blog/banner_shape_2.png',
			]
		),

		array(
			'id'                    => 'blog_single_banner_bg_color',
			'type'                  => 'background',
			'title'                 => esc_html__( 'Background', 'docy' ),
			'background_gradient'   => true,
			'background_origin'     => true,
			'background_clip'       => true,
			'background_blend_mode' => true,
			'output'                => '.single-post .doc_banner_area, .single-post .tip_banner_area, .banner_shape .gradient_banner_area',
			'default'               => false,
			'dependency'            => array( 'banner_type', '||', 'colorful', 'classic' ),
		),

		array(
			'title'  => esc_html__( 'Title Color', 'docy' ),
			'id'     => 'blog_single_banner_title_color',
			'output' => array( '.doc_banner_content .title, .tip_banner_area .banner_title, .banner_shape .gradient_banner_area .banner_title' ),
			'type'   => 'color',
		),

		// Post Metas
		array(
			'title' => esc_html__( 'Post Meta', 'docy' ),
			'type'  => 'heading',
		),

		array(
			'title'      => esc_html__( 'Meta', 'docy' ),
			'subtitle'   => esc_html__( 'Post meta includes Date, Reading Time and Categories.', 'docy' ),
			'id'         => 'is_single_post_meta',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
		),
		array(
			'title'      => esc_html__( 'Date', 'docy' ),
			'id'         => 'is_single_post_date',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
			'dependency' => array( 'is_single_post_meta', '==', 1 )
		),
		array(
			'title'      => esc_html__( 'Reading Time', 'docy' ),
			'id'         => 'is_single_reading_time',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
			'dependency' => array( 'is_single_post_meta', '==', 1 )
		),
		array(
			'title'      => esc_html__( 'Categories', 'docy' ),
			'id'         => 'is_single_cats',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
			'dependency' => array( 'is_single_post_meta', '==', 1 )
		), //End Post Metas


		//==== Post Contents
		array(
			'title' => esc_html__( 'Post Contents', 'docy' ),
			'type'  => 'heading',
		),

		// Content horizontal padding
		array(
			'title'      => esc_html__( 'Content Padding', 'docy' ),
			'subtitle'   => esc_html__( 'Apply horizontal padding to reduce text width and enhance readability.', 'docy' ),
			'desc'       => esc_html__( 'Horizontal padding to the text post content except images, videos, tables etc.', 'docy' ),
			'type'       => 'switcher',
			'id'         => 'is_post_content_padding',
			'text_on'    => esc_html__( 'Enable', 'docy' ),
			'text_off'   => esc_html__( 'Disable', 'docy' ),
			'text_width' => 85,
		),

		array(
			'title'      => esc_html__( 'Content Padding', 'docy' ),
			'id'         => 'blog_content_padding',
			'type'       => 'spacing',
			'units'      => array( '%', 'px' ),
			'top'        => false,
			'bottom'     => false,
			'output'     => ':root',
			'output_mode' => '--blog_content_padding',
			'default'    => array(
				'right' => '10',
				'left'  => '10',
				'unit'  => '%',
			),
			'dependency' => array( 'is_post_content_padding', '==', '1' ),
		),

		array(
			'title'      => esc_html__( 'Featured Image', 'docy' ),
			'subtitle'   => esc_html__( 'Show or hide the featured image in the main post content area.', 'docy' ),
			'id'         => 'is_single_featured_image',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
		),

		// Tags
		array(
			'title'      => esc_html__( 'Tags', 'docy' ),
			'subtitle'   => esc_html__( 'The Post Tags shows at the bottom of the post content.', 'docy' ),
			'id'         => 'is_single_post_tag',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1'
		),

		// Article Rating Feature
		array(
			'title' => esc_html__( 'Article Rating', 'docy' ),
			'type'  => 'heading',
		),

		array(
			'title'      => esc_html__( 'Article Rating', 'docy' ),
			'subtitle'   => esc_html__( 'Allow visitors to rate your blog articles with a 5-star rating system.', 'docy' ),
			'id'         => 'is_article_rating',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Enable', 'docy' ),
			'text_off'   => esc_html__( 'Disable', 'docy' ),
			'text_width' => 85,
			'default'    => '1',
		),

		array(
			'title'      => esc_html__( 'Rating Box Title', 'docy' ),
			'subtitle'   => esc_html__( 'The title text displayed in the rating box.', 'docy' ),
			'id'         => 'article_rating_title',
			'type'       => 'text',
			'default'    => esc_html__( 'Rate the article', 'docy' ),
			'dependency' => array( 'is_article_rating', '==', '1' ),
		),

		array(
			'title'      => esc_html__( 'Thank You Message', 'docy' ),
			'subtitle'   => esc_html__( 'The message displayed after a user submits their rating.', 'docy' ),
			'id'         => 'article_rating_thank_you',
			'type'       => 'text',
			'default'    => esc_html__( 'Thank you for rating this article!', 'docy' ),
			'dependency' => array( 'is_article_rating', '==', '1' ),
		),

		array(
			'title'      => esc_html__( 'Show Average Rating', 'docy' ),
			'subtitle'   => esc_html__( 'Display the average rating score next to the title.', 'docy' ),
			'id'         => 'is_article_rating_average',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
			'default'    => '1',
			'dependency' => array( 'is_article_rating', '==', '1' ),
		),

		array(
			'title'      => esc_html__( 'SEO Schema Markup', 'docy' ),
			'subtitle'   => esc_html__( 'Enable Schema.org structured data for rich snippets in search results.', 'docy' ),
			'desc'       => esc_html__( 'When enabled, search engines may display star ratings alongside your articles in search results.', 'docy' ),
			'id'         => 'is_article_rating_schema',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Enable', 'docy' ),
			'text_off'   => esc_html__( 'Disable', 'docy' ),
			'text_width' => 85,
			'default'    => '1',
			'dependency' => array( 'is_article_rating', '==', '1' ),
		),

		// Related Posts
		array(
			'title' => esc_html__( 'Related posts', 'docy' ),
			'type'  => 'heading',
		),

		array(
			'title'      => esc_html__( 'Related posts ', 'docy' ),
			'id'         => 'is_related_posts',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 80,
		),

		array(
			'title'      => esc_html__( 'Related Posts Title', 'docy' ),
			'id'         => 'related_posts_title',
			'type'       => 'text',
			'default'    => esc_html__( 'Related Post', 'docy' ),
			'dependency' => array( 'is_related_posts', '==', '1' )
		),

		array(
			'title'         => esc_html__( 'Related Posts Count', 'docy' ),
			'id'            => 'related_posts_count',
			'type'          => 'slider',
			'default'       => 3,
			'min'           => 3,
			'step'          => 1,
			'max'           => 20,
			'display_value' => 'label',
			'dependency'    => array( 'is_related_posts', '==', '1' )
		),

	)
) );
