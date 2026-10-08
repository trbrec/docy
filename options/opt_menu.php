<?php

// Navbar styling
CSF::createSection( $prefix, array(
    'title'     => esc_html__( 'Menu', 'docy' ),
    'id'        => 'menu_opt',
    'icon'      => 'dashicons dashicons-menu',
));

/**
 * Main Menu styling
 */
CSF::createSection( $prefix, array(
    'parent'        => 'menu_opt',
    'title'         => esc_html__( 'Main Menu', 'docy' ),
    'id'            => 'main_menu_opt',
    'icon'          => '',
    'subsection'    => true,
    'fields'        => array(
        array(
            'title' => esc_html__( 'Main Menu', 'docy'),
            'id'    => 'Main_menu_option',
            'type'  => 'heading',
        ),

        array(
            'title'     => esc_html__( 'Navbar Menu Alignment', 'docy' ),
            'id'        => 'menu_align',
            'type'      => 'button_set',
            'options'   => array(
                'left'      => esc_html__( 'Left', 'docy' ),
                'center'    => esc_html__( 'Center', 'docy' ),
                'right'     => esc_html__( 'Right', 'docy' ),
            ),
            'default'   => 'right'
        ),

        // Normal menu settings section
        array(
            'id'        => 'normal_menu_start',
            'type'      => 'subheading',
            'title'     => esc_html__( 'Normal menu colors', 'docy' ),
            'subtitle'  => esc_html__( 'The following settings will only apply on the normal header mode..', 'docy' ),
            'indent'    => true
        ),

        array(
            'title'     => esc_html__( 'Item Color', 'docy' ),
            'subtitle'  => esc_html__( 'This is the menu item font color.', 'docy' ),
            'id'        => 'menu_normal_font_color',
            'type'      => 'color',
            'output'    => array (
                'color' => '.menu > .nav-item > .nav-link, .dark_menu .menu > .nav-item > .nav-link',
            ),
            'output_mode'       => 'color',
        ),

        array(
            'title'     => esc_html__( 'Item Hover Color', 'docy' ),
            'subtitle'  => esc_html__( 'Menu item\'s font color on hover stats.', 'docy' ),
            'id'        => 'menu_normal_hover_font_color',
            'output'    => array(
                'color' => '
                    .navbar .menu > .nav-item:hover .nav-link, 
                    .navbar .menu > .nav-item.submenu .dropdown-menu .nav-item:hover > .nav-link, 
                    .navbar .menu > .nav-item.submenu .dropdown-menu .nav-item:hover > .nav-link h5',
            ),
            'type'              => 'color',
            'output_mode'       => 'color'
        ),

        array(
            'title'     => esc_html__( 'Item Active Color', 'docy' ),
            'subtitle'  => esc_html__( 'Menu item\'s font color on active stats.', 'docy' ),
            'id'        => 'menu_normal_item_active_color',
            'output'    => array(
                'color'             => '.navbar .menu > .nav-item.active .nav-link, .navbar .menu > .nav-item.submenu .dropdown-menu .nav-item:focus > .nav-link, .navbar .menu > .nav-item.submenu .dropdown-menu .nav-item.active > .nav-link',
                'background-color'  => '.navbar .menu > .nav-item.submenu .dropdown-menu .nav-item .nav-link:before',
            ),
            'type'              => 'color',
            'output_mode'       => 'color'
        ),

        // Sticky menu settings section
        array(
            'id'        => 'sticky_menu_start',
            'type'      => 'subheading',
            'title'     => esc_html__( 'Sticky menu colors', 'docy' ),
            'subtitle'  => esc_html__( 'The following settings will only apply on the sticky header mode..', 'docy' ),
            'indent'    => true
        ),

        array(
            'title'         => esc_html__( 'Menu Color', 'docy' ),
            'subtitle'      => esc_html__( 'Menu item font color on sticky menu mode.', 'docy' ),
            'id'            => 'sticky_menu_font_color',
            'output'        => array (
                'color'     => '.navbar_fixed.menu_one .menu > .nav-item > .nav-link',
            ),
            'type'              => 'color',
            'output_mode'       => 'color'
        ),

        array(
            'title'     => esc_html__( 'Menu Hover Color', 'docy' ),
            'subtitle'  => esc_html__( 'Menu item hover font color on header sticky mode', 'docy' ),
            'id'        => 'menu_sticky_hover_font_color',
            'output'    => array(
                'color' => '.navbar_fixed.menu_one .menu > .nav-item:hover > .nav-link',
            ),
            'type'              => 'color',
            'output_mode'       => 'color'
        ),

        array(
            'title'     => esc_html__( 'Menu Active Color', 'docy' ),
            'subtitle'  => esc_html__( 'Menu item active font color on header sticky mode', 'docy' ),
            'id'        => 'menu_sticky_active_font_color',
            'output'    => array(
                'color' => '.navbar_fixed.menu_one .menu > .nav-item.active > .nav-link',
            ),
            'type'              => 'color',
            'output_mode'       => 'color'
        ),

        // Dropdown menu settings section
        array(
            'id'        => 'dropdown_menu_start',
            'type'      => 'subheading',
            'title'     => esc_html__( 'Dropdown menu colors', 'docy' ),
            'subtitle'  => esc_html__( 'The following settings will only apply on the dropdown header mode..', 'docy' ),
            'indent'    => true
        ),

        array(
            'title'         => esc_html__( 'Menu Color', 'docy' ),
            'id'            => 'dropdown_menu_font_color',
            'output'        => array (
                'color'     => '.menu > .nav-item.submenu .dropdown-menu .nav-item .nav-link',
            ),
            'type'              => 'color',
            'output_mode'       => 'color'
        ),

        array(
            'title'     => esc_html__( 'Menu Hover/Active Color', 'docy' ),
            'id'        => 'dropdown_menu_hover_font_color',
            'output'    => array(
                'color'             => '.menu > .nav-item.submenu .dropdown-menu .nav-item:hover > .nav-link, .menu > .nav-item.submenu .dropdown-menu .nav-item:focus > .nav-link, .menu > .nav-item.submenu .dropdown-menu .nav-item.active > .nav-link',
                'background-color'  => '.menu > .nav-item.submenu .dropdown-menu .nav-item .nav-link:before',
            ),
            'type'              => 'color',
            'output_mode'       => 'color'
        ),

        array(
            'title'     => esc_html__( 'Background Color', 'docy' ),
            'id'        => 'dropdown_menu_bg_color',
            'output'    => array(
                'background-color'  => '.menu > .nav-item.submenu .dropdown-menu, .menu > .nav-item.submenu .dropdown-menu:before',
            ),
            'type'              => 'color',
            'output_mode'       => 'background-color'
        ),

        array(
            'title'     => esc_html__( 'Border Color', 'docy' ),
            'id'        => 'dropdown_menu_border_color',
            'output'    => array(
                'border-color'  => '.menu > .nav-item.submenu .dropdown-menu, .menu > .nav-item.submenu .dropdown-menu:before',
            ),
            'type'              => 'color',
            'output_mode'       => 'border-color'
        ),

        array(
            'id'     => 'dropdown_menu_end',
            'type'   => 'subheading',
            'indent' => false,
        ),

        // Menu item padding and margin options
        array(
            'title'             => esc_html__( 'Menu item padding', 'docy' ),
            'subtitle'          => esc_html__( 'Padding around menu item. Default is 37px 0px 37px 0px. You can reduce the top and bottom padding to make the menu bar height smaller.', 'docy' ),
            'id'                => 'menu_item_padding',
            'type'              => 'spacing',
            'output'            => array( '.navbar_fixed.menu_one .menu > .nav-item' ),
            'mode'              => 'padding',
            'units'             => array( 'em', 'px', '%' ),      // You can specify a unit value. Possible: px, em, %
            'units_extended'    => 'true',
        ),

        array(
            'title'             => esc_html__( 'Menu item margin', 'docy' ),
            'subtitle'          => esc_html__( 'Margin around menu item.', 'docy' ),
            'id'                => 'menu_item_margin',
            'type'              => 'spacing',
            'output'            => array( '.menu > .nav-item + .nav-item' ),
            'mode'              => 'margin',
            'units'             => array( 'em', 'px', '%' ),      // You can specify a unit value. Possible: px, em, %
            'units_extended'    => 'true',
        ),
    )
));