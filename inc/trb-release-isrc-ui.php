<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function trb_release_isrc_ui_enqueue() {
    $post = get_post();
    if ( ! is_page() || ! $post || ! has_shortcode( $post->post_content, 'trb_artist_portal' ) ) return;
    $path = get_template_directory() . '/assets/js/trb-release-isrc.js';
    wp_enqueue_script( 'trb-release-isrc', get_template_directory_uri() . '/assets/js/trb-release-isrc.js', array( 'trb-release-upload' ), file_exists( $path ) ? (string) filemtime( $path ) : null, true );
}
add_action( 'wp_enqueue_scripts', 'trb_release_isrc_ui_enqueue', 40 );
