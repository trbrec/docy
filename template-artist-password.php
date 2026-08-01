<?php
/**
 * Artist Portal password recovery page.
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
?>
<main class="trb-login-page trb-password-page">
	<header class="trb-landing__topbar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="trb-landing__brand"><img src="https://faq.trbrec.com/wp-content/uploads/2023/08/Vector-TRB-rec-White.png" alt="TRB rec" width="186" height="62" /></a>
		<a class="trb-landing__site-link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Apri una segnalazione</a>
	</header>
	<section class="trb-login">
		<div class="trb-login__intro"><p>PORTALE ARTISTI &middot; RECUPERO ACCESSO</p><h1>Recupera la tua password.</h1><p>Inserisci lâe-mail associata al tuo account artista. Riceverai le istruzioni per scegliere una nuova password.</p><a href="<?php echo esc_url( $login_url ); ?>">Torna allâaccesso</a></div>
		<div class="trb-login__form"><h2>Password dimenticata?</h2><p>Ti invieremo un collegamento per reimpostarla.</p>
			<form name="lostpasswordform" id="lostpasswordform" action="<?php echo esc_url( wp_lostpassword_url() ); ?>" method="post">
				<p><label for="user_login">E-mail o nome utente</label><input type="text" name="user_login" id="user_login" autocomplete="username" required /></p>
				<p class="login-submit"><input type="submit" name="wp-submit" value="Invia il collegamento" /><input type="hidden" name="redirect_to" value="<?php echo esc_url( $login_url ); ?>" /></p>
			</form>
		</div>
	</section>
</main>
<footer class="trb-public-footer">&copy; 2008-<?php echo esc_html( wp_date( 'Y' ) ); ?> Tutti i diritti riservati | TRB rec di Andrea Tognassi - Music Publishing</footer>
<?php wp_footer(); ?>
</body>
</html>
