<?php
/**
 * Approved-registration page for the Artist Portal.
 *
 * @package docy
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'trb-artist-public-shell' ); ?>>
<?php wp_body_open(); ?>
<?php
$login_url = home_url( '/accedi/' );
$register_form = do_shortcode( '[wppb-register]' );
foreach ( array( 'username', 'first_name', 'last_name', 'email', 'passw1', 'passw2' ) as $required_field ) {
	$register_form = str_replace(
		'name="' . $required_field . '"',
		'name="' . $required_field . '" required aria-required="true"',
		$register_form
	);
}
$register_form = str_replace( 'autocomplete="off"', 'autocomplete="new-password"', $register_form );
$register_form = str_replace( '</ul></ul>', '</ul>', $register_form );
$captcha_markup = trb_portal_registration_captcha_markup();
$register_form  = str_replace( '>Username<', '>Nome utente<', $register_form );
$register_form  = preg_replace( '#<p class="form-submit"#', $captcha_markup . '<p class="form-submit"', $register_form, 1, $captcha_inserted );
if ( ! $captcha_inserted ) {
	$register_form = preg_replace( '#</form>\s*$#', $captcha_markup . '</form>', $register_form, 1 );
}
?>
<main class="trb-registration-page">
	<header class="trb-landing__topbar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="trb-landing__brand"><img src="<?php echo esc_url( trb_portal_logo_url() ); ?>" alt="TRB rec" width="186" height="62" /></a>
		<div class="trb-landing__topbar-actions"><a class="trb-landing__account trb-landing__account--register" href="<?php echo esc_url( home_url( '/registrati/' ) ); ?>" aria-current="page">Registrati</a><a class="trb-landing__account trb-landing__account--login" href="<?php echo esc_url( $login_url ); ?>">Accedi</a><a class="trb-landing__site-link" href="<?php echo esc_url( home_url( '/segnalazione/' ) ); ?>"><span class="trb-action-label--full">Apri una segnalazione</span><span class="trb-action-label--short">Segnalazione</span></a></div>
	</header>
	<section class="trb-registration">
		<div class="trb-registration__intro"><p class="trb-registration__eyebrow">PORTALE ARTISTI &middot; REGISTRAZIONE</p><h1>Richiedi l’accesso al tuo spazio artista.</h1><p class="trb-registration__lead">Crea le credenziali personali che userai nel portale. L’account resterà inattivo fino alla verifica della Direzione.</p><div class="trb-registration__notice"><strong>Prima di registrarti</strong><p>Procedi soltanto se hai sottoscritto un accordo con TRB rec o hai ricevuto una specifica autorizzazione.</p><p>Gli account non autorizzati non accedono ai servizi e vengono rimossi entro 30 giorni.</p></div><p class="trb-registration__login">Hai già un account abilitato? <a href="<?php echo esc_url( $login_url ); ?>">Accedi al Portale Artisti</a>.</p></div>
		<div class="trb-registration__panel"><?php if ( isset( $_GET['trb_registration_error'] ) ) : ?><div class="trb-registration__error">Verifica di sicurezza non valida o scaduta. Ricarica la pagina e riprova.</div><?php endif; ?><div class="trb-registration__form"><p class="trb-registration__step">ACCESSO PERSONALE</p><h2>I tuoi dati di accesso</h2><p class="trb-registration__form-lead">Tutti i campi sono obbligatori. Usa un’e-mail che controlli regolarmente.</p><?php echo $register_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
	</section>
</main>
<footer class="trb-public-footer"><span>&copy; 2008-<?php echo esc_html( wp_date( 'Y' ) ); ?> TRB rec di Andrea Tognassi - Music Publishing</span><span>Tutti i diritti riservati.</span></footer>
<?php wp_footer(); ?>
</body>
</html>
