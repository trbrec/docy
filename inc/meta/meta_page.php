<?php
// Page Settings
CSF::createSection( $prefix, array(
    'title'     => 'Page Settings',
    'post_type' => 'page',  // Applied only to pages
    'fields'    => array(
        array(
            'id'          => 'page_padding',
            'type'        => 'spacing',
            'title'       => esc_html__( 'Padding', 'docy' ),
            'output'      => '.page_wrapper',
            'output_mode' => 'padding',
        )
    )
) );
