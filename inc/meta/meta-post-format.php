<?php
// Control core classes for avoid errors
if ( class_exists( 'CSF' ) ) {

    $prefix = 'my_post_options';

    // Option: Video
    CSF::createMetabox( $prefix, array(
        'title'              => esc_html__('Video','docy'),
        'post_type'          => 'post',
        'context'            => 'side',
        'priority'           => 'default',
        'post_formats'       => 'video',
        'data_type'          => 'unserialize',
    ) );

    // Video
    CSF::createSection( $prefix, array(
        'title'  => '',
        'fields' => array(
            array(
                'id'    => 'video_url',
                'type'  => 'text',
                'title' => esc_html__('Video URL','docy'),
            ),
        )
    ) );
}