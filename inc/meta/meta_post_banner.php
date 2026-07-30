<?php
// Post-> Banner
CSF::createSection( $prefix, array(
    'title'     => esc_html__( 'Banner', 'docy' ),
    'post_type' => 'post',  // Applied only to posts
    'fields'    => array(

        array(
            'id'      => 'banner_type',
            'type'    => 'button_set',
            'title'   => esc_html__( 'Banner Type', 'docy' ),
            'options' => array(
                'default'  => esc_html__( 'Default', 'docy' ),
                'colorful' => esc_html__( 'Creative Colorful', 'docy' ),
                'classic'  => esc_html__( 'Classic Titlebar', 'docy' ),
                'curved'   => esc_html__( 'Curved Shape', 'docy' ),
                'gradient'   => esc_html__( 'Gradient Banner', 'docy' ),
            ),
            'default' => 'default',
        ),

        array(
            'id'         => 'banner_shape_01',
            'type'       => 'media',
            'title'      => esc_html__( 'Shape 01', 'docy' ),
            'dependency' => array( 'banner_type', '==', 'colorful' ),
        ),

        array(
            'id'         => 'banner_shape_02',
            'type'       => 'media',
            'title'      => esc_html__( 'Shape 02', 'docy' ),
            'dependency' => array( 'banner_type', '==', 'colorful' ),
        ),

        array(
            'id'                    => 'blog_single_banner_bg',
            'type'                  => 'background',
            'title'                 => esc_html__( 'Background', 'docy' ),
            'background_gradient'   => true,
            'output'                => array('.doc_banner_area, .tip_banner_area, .banner_shape .gradient_banner_area'),
            'default'               => false,
            'dependency'            => array( 'banner_type', 'any', 'colorful,classic,curved,gradient' ),
            'output_important'      => true
        ),

        array(
            'id'          => 'banner_overlay_color',
            'type'        => 'color',
            'title'       => esc_html__( 'Overlay Color', 'docy' ),
            'output'      => '.tip_banner_area::before',
            'output_mode' => 'background-color',
            'dependency'  => array( 'banner_type', 'any', 'classic,curved,gradient' ),
            'output_important'      => true
        ),

        array(
            'title'             => esc_html__( 'Title Color', 'docy' ),
            'id'                => 'blog_single_banner_title_color',
            'output'            => array( '.doc_banner_content .title, .tip_banner_area .banner_title, .banner_shape .gradient_banner_area .banner_title' ),
            'type'              => 'color',
            'output_important'  => true
        ),

    )
) );