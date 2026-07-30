<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
    $parent = wp_get_theme( get_template() );

    wp_enqueue_style(
        'docy-parent-style',
        get_template_directory_uri() . '/style.css',
        array(),
        $parent->get( 'Version' )
    );

    wp_enqueue_style(
        'trb-faq-style',
        get_stylesheet_uri(),
        array( 'docy-parent-style' ),
        wp_get_theme()->get( 'Version' )
    );
}, 20 );
