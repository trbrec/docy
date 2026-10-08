<?php
CSF::createSection( $prefix, array(
    'title'            => esc_html__( 'Privacy', 'docy' ),
    'id'               => 'privacy_opt',
    'icon'             => 'dashicons dashicons-businessperson',
    'fields'           => array(
        array(
            'id'            => 'is_privacy_bar',
            'type'          => 'switcher',
            'title'         => esc_html__( 'Privacy Bar', 'docy' ),
            'subtitle'      => esc_html__( 'Turn on to enable a privacy bar at the bottom of the page.', 'docy' ),
            'text_on'            => esc_html__( 'On', 'docy' ),
            'text_off'           => esc_html__( 'Off', 'docy' ),
            'text_width'        => 80
            // 'default'       => '',
        ),

        /**
         * Privacy Bar Styling
         */
	    array(
		    'required'      => array( 'is_privacy_bar', '=', '1' ),
		    'title'         => esc_html__( 'Privacy Bar Text', 'docy' ),
		    'id'            => 'privacy_bar_txt',
		    'type'          => 'wp_editor',
		    'default'       => esc_html__( 'By using this website, you automatically accept that we use cookies.', 'docy' ),
		    'args'          => array (
			    'wpautop'       => true,
			    'media_buttons' => false,
			    'textarea_rows' => 10,
			    'teeny'         => false,
			    'quicktags'     => false,
		    )
	    ),
	    array(
		    'dependency'      => array( 'is_privacy_bar', '==', '1' ),
		    'title'         => esc_html__( 'Privacy Bar Button Text', 'docy' ),
		    'id'            => 'privacy_bar_btn_txt',
		    'type'          => 'text',
		    'default'       => esc_html__( 'Understood', 'docy' ),
	    ),
        array(
            'dependency'      => array( 'is_privacy_bar', '==', '1' ),
            'id'            => 'privacy_bar_padding',
            'title'         => esc_html__( 'Privacy Bar Padding', 'docy' ),
            'type'          => 'spacing',
            'output'        => array( '.cookieAcceptBar' ),
            'mode'          => 'padding',
            'units'         => array( 'em', 'px', '%' ),      // You can specify a unit value. Possible: px, em, %
            'units_extended' => 'true',
        ),
        array(
            'dependency'      => array( 'is_privacy_bar', '==', '1' ),
            'id'            => 'privacy_bar_bg',
            'title'         => esc_html__( 'Privacy Bar Background Color', 'docy' ),
            'type'          => 'color',
            'output'        => array( '.cookieAcceptBar' ),
            'output_mode'          => 'background-color',
            'output_important'      => true,
        ),
    )
));