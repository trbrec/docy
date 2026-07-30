<?php
/**
 * Template Name: Docy Full Width
 *
 * @package docy
 */

get_header();
docy_render_banner();

while ( have_posts() ) : the_post(); ?>
    <div class="full-width-page">
        <?php the_content(); ?>
    </div>
<?php endwhile;

get_footer();
