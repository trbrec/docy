<?php
// Footer Visibility
CSF::createSection( $prefix, array(
    'title'  => esc_html__('Footer','docy'),
    'post_type' => ['post', 'page'],  // Applied post, page
    'fields' => array(
        array(
            'id'       => 'footer_visibility',
            'type'     => 'switcher',
            'title'      => esc_html__('Footer Visibility', 'docy'),
            'text_on'  => esc_html__('Shown', 'docy'),
            'text_off' => esc_html__('Hidden', 'docy'),
            'text_width' => 80,
            'default' => true
        ),

        array(
            'id'    => 'footer_background_color',
            'type'  => 'color',
            'title' => esc_html__('Background Color', 'docy'),
            'dependency' => array( 'footer_visibility', '==', 'true' ),
            'output_mode'   => 'background-color',
            'output'  => '.doc_footer_top',
        ),

        array(
            'id'    => 'footer_padding',
            'type'  => 'spacing',
            'title' => esc_html__('Padding', 'docy'),
            'dependency' => array( 'footer_visibility', '==', 'true' ),
            'output'  => '.doc_footer_top',
            'output_mode' => 'padding',
        ),
    )
) );