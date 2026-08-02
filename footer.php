<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link    https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package docy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme Options
 */
$opt             = get_option( 'docy_opt', [] );
$is_back2top_btn = $opt['is_back_to_top_btn_switcher'] ?? '1';
$bt_position     = $opt['bt_position'] ?? '';
$footer_style    = ! empty( $opt['footer_style'] ) ? $opt['footer_style'] : 'normal';

// The Artist Portal no longer depends on Elementor. Preserve a complete
// footer if an old theme option still points to an Elementor template after
// the retired builder plugins have been removed.
if ( 'elementor' === $footer_style && ! did_action( 'elementor/loaded' ) ) {
    $footer_style = 'normal';
}

/**
 * Footer is visible by default
 * - If current object has footer_visibility = '0' → hide footer
 * - Any other value or missing meta → show footer
 */
$show_footer = true;

// Get current queried object ID
$object_id = get_queried_object_id();

if ( $object_id ) {
    $footer_visibility = get_post_meta( $object_id, 'footer_visibility', true );

    // Hide ONLY if explicitly disabled
    if ( $footer_visibility === '0' ) {
        $show_footer = false;
    }
}

/**
 * Output footer
 */
if ( $show_footer ) {
    $copyright_text = ! empty( $opt['copyright_txt'] ) ? $opt['copyright_txt'] : esc_html__( '©2025 Spider Themes. All rights reserved', 'docy' );
    // Keep a configured final copyright year current without changing the
    // initial year in a range such as “2008-2026”.
    $copyright_text = preg_replace( '/20\\d{2}(?!.*20\\d{2})/', wp_date( 'Y' ), $copyright_text );
    get_template_part( 'template-parts/footers/footer', $footer_style );
}
?>

</div> <!-- Body Wrapper -->

<?php
/**
 * Back to Top button
 */
if ( $is_back2top_btn === '1' ) :
    ?>
    <a id="back-to-top" href="#" aria-label="<?php esc_attr_e( 'Back to Top', 'docy' ); ?>" title="<?php esc_attr_e( 'Back to Top', 'docy' ); ?>" class="<?php echo esc_attr( $bt_position ); ?>"></a>
    <?php
endif;

/**
 * Reading progress bar
 */
if ( is_singular( 'docs' ) || is_singular( 'post' ) ) :
    ?>
    <div id="reading-progress">
        <div id="reading-progress-fill"></div>
    </div>
    <?php 
endif;
wp_footer(); 
?>
</body>
</html>
