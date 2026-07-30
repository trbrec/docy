<?php
// Action Button
CSF::createSection( $prefix, array(
    'title'  => esc_html__('Action Button','docy'),
    'post_type' => ['post', 'page', 'docs' ],  // Applied post, page, docs
    'fields' => array(

        array(
            'id'       => 'is_menu_btn',
            'type'     => 'button_set',
            'title'      => esc_html__('Button Visibility', 'docy'),
            'text_on'  => esc_html__('Show', 'docy'),
            'text_off' => esc_html__('Hide', 'docy'),
            'options'    => array(
                'default'   => esc_html__('Default', 'docy'),
                '1'         => esc_html__('Show', 'docy'),
                '0'      => esc_html__('Hide', 'docy'),
            ),
            'default'    => 'default'
        ),

        array(
            'id'    => 'action_btn_link',
            'type'  => 'link',
            'title' => esc_html__('Link', 'docy'),
            'dependency' => array( 'is_menu_btn', '==', 'true' ),
            'output_mode' => 'nav_btn tp_btn',
        ),

        array(
            'id'    => 'action_btn_border_radius',
            'type'  => 'spacing',
            'title' => esc_html__('Border Radius', 'docy'),
            'output'      => '.right-nav .nav_btn',
            'output_mode' => 'border-radius',
            'dependency' => array( 'is_menu_btn', '==', 'true' ),
        ),

        array(
            'id'    => 'btn_background_color',
            'type'  => 'color',
            'title' => esc_html__('Background Color', 'docy'),
            'dependency' => array( 'is_menu_btn', '==', 'true' ),
            'output'      => '.right-nav .nav_btn.tp_btn',
            'output_mode' => 'background-color'
        ),

        array(
            'id'    => 'btn_text_color',
            'type'  => 'color',
            'title' => esc_html__('Text Color', 'docy'),
            'dependency' => array( 'is_menu_btn', '==', 'true' ),
            'output'      => '.dark_menu .right-nav .nav_btn',
            'output_important' => true
        ),

        array(
            'id'    => 'btn_border_color',
            'type'  => 'color',
            'title' => esc_html__('Border Color', 'docy'),
            'dependency' => array( 'is_menu_btn', '==', 'true' ),
            'output'      => '.right-nav .nav_btn.tp_btn',
            'output_mode' => 'border-color',
            'output_important' => true
        ),

        array(
            'id'    => 'hover_btn_background_color',
            'type'  => 'color',
            'title' => esc_html__('Hover Background Color', 'docy'),
            'dependency' => array( 'is_menu_btn', '==', 'true' ),
            'output'      => '.right-nav .nav_btn.tp_btn:hover',
            'output_mode' => 'background-color',
            'output_important' => true
        ),

        array(
            'id'    => 'hover_btn_text_color',
            'type'  => 'color',
            'title' => esc_html__('Hover Text Color', 'docy'),
            'dependency' => array( 'is_menu_btn', '==', 'true' ),
            'output'      => '.dark_menu .right-nav .nav_btn:hover',
            'output_important' => true
        ),

        array(
            'id'    => 'hover_btn_border_color',
            'type'  => 'color',
            'title' => esc_html__('Hover Border Color', 'docy'),
            'dependency' => array( 'is_menu_btn', '==', 'true' ),
            'output'      => '.right-nav .nav_btn.tp_btn:hover',
            'output_mode'   => 'border-color',
            'output_important' => true
        ),
    )
) );