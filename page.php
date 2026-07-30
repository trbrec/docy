<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package docy
 */

get_header();
docy_render_banner();

$opt = get_option('docy_opt');

if ( docy_toc('page') == '1' ) {
	wp_enqueue_script( 'anchor' );
	wp_enqueue_script( 'bootstrap-toc' );
}

$padding = "";

$wrap_class = 'page_wrapper';
if ( is_bbp_core_active() ) {
    $wrap_class .= is_post_type_archive('forum') || is_post_type_archive('topic') || is_singular('forum') ? ' forum-page-content' : '';
} elseif ( in_array('woocommerce', get_body_class()) || in_array('woocommerce-page', get_body_class() ) ) {
    $wrap_class = '';
}

$is_forum_pages = is_bbp_core_active() ? ( bbp_is_single_forum() ||  bbp_is_single_topic() ) : null;

while ( have_posts() ) : the_post();
    ?>
    <div class="page_pad <?php echo esc_attr($wrap_class) ?>">
        <div class="container <?php echo has_blocks() && !$is_forum_pages ? 'editor-content' : ''; ?>">

	        <?php
	        if ( docy_toc('page') == '1' ) :
                ?>
                <div id="toc_stick" class="row">
                    <div class="col-lg-3 doc_mobile_menu doc-sidebar display_none">
                        <aside class="left_sidebarlist">
                            <nav data-toggle="toc" class="nav-sidebar doc-nav" id="docy-toc"> </nav>
                        </aside>
                    </div>

                    <?php
                    // Display TOC and Share on mobile
                    get_template_part('template-parts/contents/mobile-toc-share');
                    ?>

                    <div class="col-lg-9">
                        <?php endif; // End of TOC wrapper ?>

                        <?php
                        echo docy_toc('page') == '1' ? '<div class="anchor-enabled">' : ''; // The TOC content area

                        the_content();
                        wp_link_pages(array(
                            'before'      => '<div class="page-links"><span class="page-links-title">' . esc_html__( 'Pages:', 'docy' ) . '</span>',
                            'after'       => '</div>',
                            'link_before' => '<span>',
                            'link_after'  => '</span>',
                            'pagelink'    => '<span class="screen-reader-text">' . esc_html__( 'Page', 'docy' ) . ' </span>%',
                            'separator'   => '<span class="screen-reader-text">, </span>',
                        ));

                        echo docy_toc('page') == '1' ? '</div>' : ''; // Close the TOC content area

                        // If comments are open or we have at least one comment, load up the comment template.
                        if ( comments_open() || get_comments_number() ) :
                            comments_template();
                        endif;

                        echo docy_toc('page') == '1' ? '
                    </div>
                </div>' : '';
            // Close the TOC wrapper condition ?>
        </div>
    </div>

<?php
endwhile; // End of the loop.

if ( is_post_type_archive( array('forum', 'topic') ) ) {
    if ( docy_opt('is_forum_btm_c2a') == '1' ) {
        get_template_part('template-parts/forum/c2a-bottom');
    }
}

get_footer();