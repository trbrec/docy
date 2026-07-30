<?php
get_header();
docy_render_banner();

    while ( have_posts() ) : the_post();
        ?>
        <div class="mt-5 sec_pad">
            <div class="container">
                <?php
                the_content();
                wp_link_pages(array(
                    'before'      => '<div class="page-links"><span class="page-links-title">' . esc_html__( 'Pages:', 'docy' ) . '</span>',
                    'after'       => '</div>',
                    'link_before' => '<span>',
                    'link_after'  => '</span>',
                    'pagelink'    => '<span class="screen-reader-text">' . esc_html__( 'Page', 'docy' ) . ' </span>%',
                    'separator'   => '<span class="screen-reader-text">, </span>',
                ));

                // If comments are open or we have at least one comment, load up the comment template.
                if ( comments_open() || get_comments_number() ) :
                    comments_template();
                endif;
                ?>
            </div>
        </div>
        <?php
    endwhile; // End of the loop.

get_footer();