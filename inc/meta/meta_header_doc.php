<?php
// Header Settings
CSF::createSection($prefix, array(
    'title' => esc_html__('Header Settings', 'docy'),
    'post_type' => ['docs'],  // Applied docs
    'fields' => array(

        array(
            'id'         => 'docy_header_type',
            'type'       => 'button_set',
            'title'      => esc_html__('Header Type', 'docy'),
            'subtitle' => esc_html__('The header type will on the header elements based on the chooses type','docy'),
            'options'    => array(
                'default'  => esc_html__('Default', 'docy'),
                'black' => esc_html__('Black', 'docy'),
                'white' => esc_html__('White', 'docy'),
            ),
            'default'    => 'default'
        ),

        array(
            'id'         => 'is_search_banner',
            'type'       => 'button_set',
            'title'      => esc_html__('Search Banner', 'docy'),
            'subtitle' => esc_html__('The default option comes from Theme Settings > Header > Search Banner.','docy'),
            'options'    => array(
                'default'  => esc_html__('Default', 'docy'),
                '1' => esc_html__('Show', 'docy'),
                '0' => esc_html__('Hide', 'docy'),
            ),
            'default'    => 'default'
        ),

        array(
            'id'    => 'banner_background_color',
            'type'  => 'color',
            'title' => esc_html__('Background Color','docy'),
            'subtitle' => esc_html__('The header background-color', 'docy'),
            'output' => '.doc_banner_area',
            'output_mode' => 'background',
        ),

        array(
            'id' => 'menu_item_color',
            'type' => 'color',
            'title' => esc_html__('Menu Item Color', 'docy'),
            'subtitle' => esc_html__('Menu items font color','docy'),
            'output'  => '.navbar .menu > .nav-item > .nav-link',
        ),
    )
));