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

$portal_page_url = get_permalink();
?>
<header class="trb-portal-topbar">
	<div class="trb-portal-topbar__inner">
		<a class="trb-portal-topbar__brand" href="<?php echo esc_url( $portal_page_url ); ?>" aria-label="Area Artisti TRB rec">
			<img src="https://faq.trbrec.com/wp-content/uploads/2023/08/Vector-TRB-rec-White.png" alt="TRB rec" width="186" height="62" />
		</a>
		<?php if ( is_user_logged_in() ) : ?>
			<div class="trb-portal-topbar__actions">
				<a class="trb-portal-topbar__support" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Apri una segnalazione</a>
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
