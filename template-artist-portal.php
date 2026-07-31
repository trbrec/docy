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

get_header( 'empty' );

$portal_page_url = get_permalink();
?>
<header class="trb-portal-topbar">
	<div class="trb-portal-topbar__inner">
		<a class="trb-portal-topbar__brand" href="<?php echo esc_url( $portal_page_url ); ?>" aria-label="Area Artisti TRB rec">
			<span>TRB</span><small>rec</small>
		</a>
		<?php if ( is_user_logged_in() ) : ?>
			<div class="trb-portal-topbar__actions">
				<a href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>">Account</a>
				<a class="trb-portal-topbar__logout" href="<?php echo esc_url( wp_logout_url( $portal_page_url ) ); ?>">Esci</a>
			</div>
		<?php endif; ?>
	</div>
</header>
<?php

while ( have_posts() ) :
	the_post();
	?>
	<main id="main-content" class="trb-artist-portal-page">
		<?php the_content(); ?>
	</main>
	<?php
endwhile;

get_footer();
	</main>
	<?php
endwhile;

get_footer();
