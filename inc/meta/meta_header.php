<?php
// Header
CSF::createSection($prefix, array(
    'title' => esc_html__('Header', 'docy'),
    'post_type' => ['post', 'page'],  // Applied post, page
    'fields' => array(
        array(
            'id' => 'docy_header_type',
            'type' => 'button_set',
            'title' => esc_html__('Navbar Type', 'docy'),
            'options' => array(
                'default' => esc_html__('Default', 'docy'),
                'black' => esc_html__('Black', 'docy'),
                'white' => esc_html__('White', 'docy'),
            ),
            'default' => 'default'
        ),

        array(
            'id' => 'navbar_position',
            'type' => 'button_set',
            'title' => esc_html__('Navbar Position', 'docy'),
            'options' => array(
                'default' => esc_html__('Default', 'docy'),
                'absolute' => esc_html__('Absolute', 'docy'),
                'static' => esc_html__('Static', 'docy'),
            ),
            'default' => 'default'
        ),

        array(
            'id' => 'doc_width',
            'type' => 'select',
            'title' => esc_html__('Header Width', 'docy'),

            'options' => array(
                'default' => esc_html__('Default', 'docy'),
                'boxed' => esc_html__('Boxed', 'docy'),
                'wide-container' => esc_html__('Wide', 'docy'),
                'full-width' => esc_html__('Full-Width', 'docy'),
            ),
            'default' => 'default'
        ),

        array(
            'id'    => 'is_search_form',
            'type'  => 'switcher',
            'title' => esc_html__('Search Form', 'docy'),
            'text_on'  => esc_html__('Show', 'docy'),
            'text_off' => esc_html__('Hide', 'docy'),
            'text_width' => 80,
            'default' => true
        ),

        array(
            'id' => 'menu_item_color',
            'type' => 'color',
            'title' => esc_html__('Menu Item Color', 'docy'),
            'output'  => '.navbar .menu > .nav-item > .nav-link',
        ),
    )
));