<?php
CSF::createSection($prefix, array(
    'parent'     => 'design_parent',
    'title'      => esc_html__( 'Body Typography', 'docy' ),
    'id'         => 'docy_typo_opt_header',
    'subsection' => true,
    'icon'       => 'dashicons dashicons-editor-textcolor',
    'fields'     => array(
        array(
            'id'        => 'typo_header',
            'title'     => esc_html__( 'Body Typography', 'docy' ),
            'type'      => 'heading',
        ),
        array(
            'id'        => 'typo_note',
            'type'      => 'notice',
            'style'     => 'info',
            'title'     => esc_html__( 'Important Note:', 'docy' ),
            'icon'      => 'dashicons dashicons-info',
            'content'   => esc_html__( 'This tab contains general typography options. Additional typography options for specific areas can be found within other tabs. Example: For menu typography options go to the Menu Settings tab.', 'docy' )
        ),

        array(
            'id'                => 'body_typo',
            'type'              => 'typography',
            'title'             => esc_html__( 'Default Body Font', 'docy' ),
            'subtitle'          => esc_html__( 'These settings control the typography for the body text globally.', 'docy' ),
            'font_family'       => true,
            'font_style'        => true,
            'font_size'         => true,
            'text_align'        => true,
            'text_transform'    => true,
            'line_height'       => true,
            'letter_spacing'    => true,
            'output'            => 'body, .f_p'
        ),
    )
));


/*** Headers Typography ***/
CSF::createSection($prefix, array(
    'parent'        => 'design_parent',
    'title'         => esc_html__( 'Headers Typography', 'docy' ),
    'id'            => 'headers_typo_opt',
    'icon'          => 'dashicons dashicons-heading',
    'subsection'    => true,
    'fields'        => array(
        array(
            'id'        => 'header_typo',
            'title'     => esc_html__( 'Headers Typography', 'docy' ),
            'type'      => 'heading',
        ),

        array(
            'id'                => 'h1_typo',
            'type'              => 'typography',
            'title'             => esc_html__( 'H1 Headers Typography', 'docy' ),
            'subtitle'          => esc_html__( 'These settings control the typography for all H1 Headers.', 'docy' ),
            'font_family'       => true,
            'font_style'        => true,
            'font_size'         => true,
            'text_align'        => true,
            'text_transform'    => true,
            'line_height'       => true,
            'letter_spacing'    => true,
            'output'            => 'h1, h1.f_p, .breadcrumb_content h1'
        ),

        array(
            'id'                => 'h2_typo',
            'type'              => 'typography',
            'title'             => esc_html__( 'H2 Headers Typography', 'docy' ),
            'subtitle'          => esc_html__( 'These settings control the typography for all H2 Headers. The h2 title tag used in the most section title.', 'docy' ),
            'font_family'       => true,
            'font_style'        => true,
            'font_size'         => true,
            'text_align'        => true,
            'text_transform'    => true,
            'line_height'       => true,
            'letter_spacing'    => true,
            'output'            => 'h2, h2.f_p'
        ),
        
        array(
            'id'                => 'h3_typo',
            'type'              => 'typography',
            'title'             => esc_html__( 'H3 Headers Typography', 'docy' ),
            'subtitle'          => esc_html__( 'These settings control the typography for all H3 Headers.', 'docy' ),
            'font_family'       => true,
            'font_style'        => true,
            'font_size'         => true,
            'text_align'        => true,
            'text_transform'    => true,
            'line_height'       => true,
            'letter_spacing'    => true,
            'output'            => 'h3, h3.f_p, .job_details_area h3'
        ),

        array(
            'id'                => 'h4_typo',
            'type'              => 'typography',
            'title'             => esc_html__( 'H4 Headers Typography', 'docy' ),
            'subtitle'          => esc_html__( 'These settings control the typography for all H4 Headers.', 'docy' ),
            'font_family'       => true,
            'font_style'        => true,
            'font_size'         => true,
            'text_align'        => true,
            'text_transform'    => true,
            'line_height'       => true,
            'letter_spacing'    => true,
            'output'            => 'h4, h4.f_p'
        ),

        array(
            'id'                => 'h5_typo',
            'type'              => 'typography',
            'title'             => esc_html__( 'H5 Headers Typography', 'docy' ),
            'font_family'       => true,
            'font_style'        => true,
            'font_size'         => true,
            'text_align'        => true,
            'text_transform'    => true,
            'line_height'       => true,
            'letter_spacing'    => true,
            'subtitle'          => esc_html__( 'These settings control the typography for all H5 Headers.', 'docy' ),
            'output'            => 'h5, h5.f_p'
        ),

        array(
            'id'                => 'h6_typo',
            'type'              => 'typography',
            'font_family'       => true,
            'font_style'        => true,
            'font_size'         => true,
            'text_align'        => true,
            'text_transform'    => true,
            'line_height'       => true,
            'letter_spacing'    => true,
            'title'             => esc_html__( 'H6 Headers Typography', 'docy' ),
            'subtitle'          => esc_html__( 'These settings control the typography for all H6 Headers.', 'docy' ),
            'output'            => 'h6, h6.f_p, .job_info .info_item h6'
        ),
    )
));