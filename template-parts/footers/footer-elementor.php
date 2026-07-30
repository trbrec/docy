<?php
$opt = get_option('docy_opt');
$footer_el_template = $opt['footer_el_template'] ?? '';

$elementor_active = did_action( 'elementor/loaded' );

if ( $elementor_active && ! empty( $footer_el_template ) ) {

    // Get the template post
    $footer_post = get_post( $footer_el_template );

    // Check valid post type (Theme Builder/Elementor template)
    if ( $footer_post && in_array( $footer_post->post_type, ['docy_footer', 'elementor_library'] ) ) {
        ?>
        <footer id="docy-footer" class="docy-footer">
            <?php
            echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $footer_el_template );
            ?>
        </footer>
        <?php
    }
}