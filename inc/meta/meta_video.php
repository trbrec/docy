<?php
// Videos Settings
CSF::createSection( $prefix, array(
    'title'     => esc_html__( 'Video Options', 'docy' ),
    'post_type' => 'video',  // Applied only to video posts
    'fields'    => array(

        array(
            'id'        => 'video',
            'type'      => 'upload',
            'title'     => esc_html__('Upload Video', 'docy'),
            'library'   => 'video',
        ),

    )
) );
