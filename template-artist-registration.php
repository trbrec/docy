<?php
/**
 * Approved-registration page for the Artist Portal.
 *
 * @package docy
 */
get_header();
$login_url = home_url( '/accedi/' );
$register_form = do_shortcode( '[wppb-register]' );
$register_form = preg_replace( '#</form>\s*$#', trb_portal_registration_captcha_markup() . '</form>', $register_form, 1 );
?>
<main class="trb-registration-page">
	<header class="trb-landing__topbar"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="trb-landing__brand"><img src="https://faq.trbrec.com/wp-content/uploads/2023/08/Vector-TRB-rec-White.png" alt="TRB rec" width="186" height="62" /></a><a class="trb-landing__site-link" href="https://trbrec.com" target="_blank" rel="noopener noreferrer">Visita il sito TRB rec - Music Publishing</a></header>
	<section class="trb-registration"><p>PORTALE ARTISTI &middot; REGISTRAZIONE</p><h1>Richiedi lâaccesso al tuo spazio artista.</h1><p class="trb-registration__lead">La registrazione Ã¨ il primo passaggio tecnico: lâaccesso al Portale Artisti resta inattivo fino alla verifica della Direzione.</p><div class="trb-registration__notice"><strong>Registrazione riservata</strong><p>Puoi procedere solo dopo aver sottoscritto un accordo con TRB rec oppure dopo aver ricevuto una specifica autorizzazione dalla Direzione.</p><p>Gli account non autorizzati non accedono ai servizi e vengono rimossi secondo i termini comunicati nella pagina principale.</p></div><?php if ( isset( $_GET['trb_registration_error'] ) ) : ?><div class="trb-registration__error">Verifica di sicurezza non valida o scaduta. Ricarica la pagina e riprova.</div><?php endif; ?><div class="trb-registration__form"><h2>I tuoi dati di accesso</h2><?php echo $register_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><p class="trb-registration__login">Hai giÃ  un account abilitato? <a href="<?php echo esc_url( $login_url ); ?>">Accedi al Portale Artisti</a>.</p></section>
</main>
<?php get_footer(); ?>
