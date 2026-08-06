<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function trb_release_isrc_ui_enqueue() {
    $post = get_post();
    if ( ! is_page() || ! $post || ! has_shortcode( $post->post_content, 'trb_artist_portal' ) ) return;

    $isrc_path = get_template_directory() . '/assets/js/trb-release-isrc.js';
    wp_enqueue_script(
        'trb-release-isrc',
        get_template_directory_uri() . '/assets/js/trb-release-isrc.js',
        array( 'trb-release-upload' ),
        file_exists( $isrc_path ) ? (string) filemtime( $isrc_path ) : null,
        true
    );

    $ux_path = get_template_directory() . '/assets/js/trb-release-form-ux.js';
    wp_enqueue_script(
        'trb-release-form-ux',
        get_template_directory_uri() . '/assets/js/trb-release-form-ux.js',
        array( 'trb-release-upload', 'trb-release-isrc' ),
        file_exists( $ux_path ) ? (string) filemtime( $ux_path ) : null,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'trb_release_isrc_ui_enqueue', 40 );
