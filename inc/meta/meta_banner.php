<?php
// Page > Banner
CSF::createSection( $prefix, array(
    'title'     => esc_html__( 'Banner', 'docy' ),
    'post_type' => 'page',  // Applied only to pages
    'fields'    => array(

        array(
            'id'         => 'is_banner',
            'type'       => 'button_set',
            'title'      => esc_html__( 'Banner', 'docy' ),
            'subtitle'   => esc_html__( 'The Default option value is getting from the Theme Settings page.', 'docy' ),
            'options' => array(
	            'default' => esc_html__('Default', 'docy'),
	            '1' => esc_html__('Show', 'docy'),
	            '0' => esc_html__('Hide', 'docy'),
            ),
            'default'    => 'default', // or false
        ),

        array(
            'id'         => 'is_breadcrumb',
            'type'       => 'button_set',
            'title'      => esc_html__( 'Breadcrumb', 'docy' ),
            'subtitle'   => esc_html__( 'The Default option value is getting from the Theme Settings page.', 'docy' ),
            'options' => array(
	            'default' => esc_html__('Default', 'docy'),
	            '1' => esc_html__('Show', 'docy'),
	            '0' => esc_html__('Hide', 'docy'),
            ),
            'text_width' => 80,
            'default'    => true, // or false
            'dependency'  => array( 'is_banner', '==', 'true' ),
        ),

        array(
            'id'     => 'banner_text_color',
            'type'   => 'color',
            'title'  => esc_html__( 'Text Color', 'docy' ),
            'output' => '.doc_banner_area h1, .search-banner-light .doc_banner_content p',
            'dependency'  => array( 'is_banner', '==', 'true' ),
        ),

        array(
            'id'          => 'banner_background_color',
            'type'        => 'color',
            'title'       => esc_html__( 'Background Color', 'docy' ),
            'output'      => '.titlebar',
            'output_mode' => 'background-color',
            'dependency'  => array( 'is_banner', '==', 'true' ),
        ),

        array(
            'id'         => 'featured_video',
            'type'       => 'fieldset',
            'title'      => esc_html__( 'Featured Video', 'docy' ),
            'dependency' => array( 'is_banner', '==', 'true' ),
            'fields'    => array(
                array(
                    'id'         => 'video_url',
                    'type'       => 'text',
                    'title'      => esc_html__( 'YouTube Video URL', 'docy' ),
                    'desc'       => esc_html__( 'Only YouTube video link supported.', 'docy' ),
                ),
                array(
                    'id'         => 'video_thumbnail',
                    'type'       => 'media',
                    'title'      => esc_html__( 'Video Thumbnail', 'docy' ),
                ),
            )
        ),

    )
) );