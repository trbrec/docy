<?php
$opt = get_option('docy_opt');
$footer_el_template = $opt['footer_el_template'] ?? '';

$elementor_active = did_action( 'elementor/loaded' );

if ( $elementor_active && ! empty( $footer_el_template ) ) {

    // Get the template post
    $footer_post = get_post( $footer_el_template );

    // Embedded templates must still be published. Elementor's display API
    // renders a document by ID and does not enforce its publication status.
    if ( $footer_post && 'publish' === $footer_post->post_status && in_array( $footer_post->post_type, ['docy_footer', 'elementor_library'], true ) ) {
        ?>
        <footer id="docy-footer" class="docy-footer">
            <?php
            echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $footer_el_template );
            ?>
        </footer>
        <?php
    }
}
