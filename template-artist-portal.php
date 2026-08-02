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

$portal_page_url = get_permalink();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'trb-artist-portal-shell' ); ?>>
<?php wp_body_open(); ?>
<header class="trb-portal-topbar">
	<div class="trb-portal-topbar__inner">
		<a class="trb-portal-topbar__brand" href="<?php echo esc_url( $portal_page_url ); ?>" aria-label="Area Artisti TRB rec">
			<img src="<?php echo esc_url( trb_portal_logo_url() ); ?>" alt="TRB rec" width="186" height="62" />
		</a>
		<?php if ( is_user_logged_in() ) : ?>
			<div class="trb-portal-topbar__actions">
				<a class="trb-portal-topbar__support" href="<?php echo esc_url( home_url( '/segnalazione/' ) ); ?>">Apri una segnalazione</a>
				<a class="trb-portal-topbar__account" href="<?php echo esc_url( $portal_page_url . '#profilo' ); ?>">Profilo artista</a>
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
?>
<footer class="trb-public-footer"><span>&copy; 2008-<?php echo esc_html( wp_date( 'Y' ) ); ?> TRB rec di Andrea Tognassi - Music Publishing</span><span>Tutti i diritti riservati.</span></footer>
<?php wp_footer(); ?>
</body>
</html>
