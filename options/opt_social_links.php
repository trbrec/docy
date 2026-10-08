<?php
CSF::createSection($prefix, array(
    'title'     => esc_html__( 'Social links', 'docy' ),
    'id'        => 'opt_social_links',
    'icon'      => 'dashicons dashicons-share',
    'fields'    => array(

        array(
            'id'    => 'social_link_opt',
            'type'  => 'Heading',
            'title' => esc_html__( 'Social links', 'docy' ),
        ),
        array(
            'id'        => 'facebook',
            'type'      => 'text',
            'title'     => esc_html__( 'Facebook', 'docy' ),
            'default'   => '#'
        ),

        array(
            'id'        => 'twitter',
            'type'      => 'text',
            'title'     => esc_html__( 'Twitter (X)', 'docy' ),
            'default'   => '#'
        ),

        array(
            'id'    => 'instagram',
            'type'  => 'text',
            'title' => esc_html__( 'Instagram', 'docy' ),
        ),

        array(
            'id'        => 'linkedin',
            'type'      => 'text',
            'title'     => esc_html__( 'LinkedIn', 'docy' ),
            'default'   => '#'
        ),

        array(
            'id'    => 'youtube',
            'type'  => 'text',
            'title' => esc_html__( 'Youtube', 'docy' ),
        ),

        array(
            'id'    => 'dribbble',
            'type'  => 'text',
            'title' => esc_html__( 'Dribbble', 'docy' ),
        ),

        array(
            'id'    => 'github',
            'type'  => 'text',
            'title' => esc_html__( 'GitHub', 'docy' ),
        ),

    ),
));