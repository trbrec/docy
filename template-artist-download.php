<?php
/**
 * Quiet library resource view for the Area Artisti.
 *
 * @package docy
 */

get_header();

while ( have_posts() ) :
	the_post();
	$profiles = trb_portal_resource_profiles( get_the_ID() );
	?>
	<header class="trb-portal-topbar">
		<div class="trb-portal-topbar__inner">
			<a class="trb-portal-topbar__brand" href="<?php echo esc_url( home_url( '/area-artisti/' ) ); ?>" aria-label="Torna all'Area Artisti TRB rec"><img src="https://faq.trbrec.com/wp-content/uploads/2023/08/Vector-TRB-rec-White.png" alt="TRB rec" width="186" height="62" /></a>
			<div class="trb-portal-topbar__actions"><a class="trb-portal-topbar__support" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Apri una segnalazione</a><a class="trb-portal-topbar__back" href="<?php echo esc_url( home_url( '/area-artisti/#download' ) ); ?>">← Torna alla Library</a></div>
		</div>
	</header>
	<main class="trb-download-page">
		<article class="trb-download-resource">
			<p class="trb-portal__eyebrow">LIBRARY E DOWNLOAD</p>
			<h1><?php echo esc_html( preg_replace( '/^\[[^\]]+\]\s*/', '', get_the_title() ) ); ?></h1>
			<p class="trb-download-resource__intro"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 38 ) ); ?></p>
			<div class="trb-download-resource__actions"><?php echo do_shortcode( '[wpdm_package id="' . absint( get_the_ID() ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php if ( ! empty( $profiles ) ) : ?><p class="trb-download-resource__access">Materiale riservato al tuo profilo contrattuale.</p><?php endif; ?>
		</article>
	</main>
	<?php
endwhile;

get_footer();
