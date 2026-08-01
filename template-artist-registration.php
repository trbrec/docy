<?php
/**
 * Approved-registration page for the Artist Portal.
 *
 * @package docy
 */
get_header();
$login_url = add_query_arg( 'wppb_force_wp_login', 'true', wp_login_url( home_url( '/area-artisti/' ) ) );
?>
<main class="trb-registration-page">
	<header class="trb-landing__topbar"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="trb-landing__brand"><img src="https://faq.trbrec.com/wp-content/uploads/2023/08/Vector-TRB-rec-White.png" alt="TRB rec" width="186" height="62" /></a><a class="trb-landing__site-link" href="https://trbrec.com">Visita il sito TRB rec - Music Publishing</a></header>
	<section class="trb-registration"><p>PORTALE ARTISTI · REGISTRAZIONE</p><h1>Completa la registrazione del tuo account.</h1><div class="trb-registration__notice"><strong>Prima di continuare</strong><p>Questa pagina è riservata agli artisti che hanno già sottoscritto un accordo con TRB rec o ricevuto istruzioni dalla Direzione.</p><p>La registrazione non attiva autonomamente l’accesso: ogni account viene verificato e abilitato dalla Direzione prima di poter consultare il Portale Artisti.</p></div><div class="trb-registration__form"><?php echo do_shortcode( '[wppb-register]' ); ?></div><p class="trb-registration__login">Hai già ricevuto l’abilitazione? <a href="<?php echo esc_url( $login_url ); ?>">Accedi al Portale Artisti</a>.</p></section>
</main>
<?php get_footer(); ?>
