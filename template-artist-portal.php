<?php
/**
 * Template Name: Area Artisti TRB rec
 *
 * A deliberately quiet page shell for the member dashboard. The standard
 * Docy banner/search header belongs to a public knowledge base; the artist
 * portal needs to open directly on the member experience instead.
 *
 * @package docy
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<main id="main-content" class="trb-artist-portal-page">
		<?php the_content(); ?>
	</main>
	<?php
endwhile;

get_footer();
