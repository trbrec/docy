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
$action    = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
$key       = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$login     = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ), true ) : '';
$is_reset  = 'rp' === $action && '' !== $key && '' !== $login;
$status    = isset( $_GET['password_status'] ) ? sanitize_key( wp_unslash( $_GET['password_status'] ) ) : '';
$error     = isset( $_GET['password_error'] ) ? sanitize_key( wp_unslash( $_GET['password_error'] ) ) : '';
if ( $is_reset && is_wp_error( check_password_reset_key( $key, $login ) ) ) {
	$is_reset = false;
	$error = 'invalid_key';
}
?>
<main class="trb-login-page trb-password-page">
	<header class="trb-landing__topbar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="trb-landing__brand"><img src="<?php echo esc_url( trb_portal_logo_url() ); ?>" alt="TRB rec" width="186" height="62" /></a>
		<a class="trb-landing__site-link" href="<?php echo esc_url( home_url( '/segnalazione/' ) ); ?>">Apri una segnalazione</a>
	</header>
	<section class="trb-login">
		<div class="trb-login__intro"><p>PORTALE ARTISTI &middot; RECUPERO ACCESSO</p><h1><?php echo $is_reset ? 'Scegli una nuova password.' : 'Recupera la tua password.'; ?></h1><p><?php echo $is_reset ? 'Inserisci e conferma la nuova password che utilizzerai per accedere al Portale Artisti.' : 'Inserisci l’e-mail associata al tuo account artista. Riceverai le istruzioni per scegliere una nuova password.'; ?></p><a href="<?php echo esc_url( $login_url ); ?>">Torna all’accesso</a></div>
		<div class="trb-login__form">
			<?php if ( 'sent' === $status ) : ?><div class="trb-portal__message"><strong>Controlla la tua e-mail</strong><p>Se i dati inseriti corrispondono a un account, riceverai a breve il collegamento per scegliere una nuova password. Controlla anche la cartella Spam.</p></div><?php endif; ?>
			<?php if ( 'missing' === $status ) : ?><div class="trb-portal__message trb-portal__message--error"><strong>Dato mancante</strong><p>Inserisci l’e-mail o il nome utente associato al tuo account.</p></div><?php endif; ?>
			<?php if ( 'invalid_key' === $error ) : ?><div class="trb-portal__message trb-portal__message--error"><strong>Collegamento non valido o scaduto</strong><p>Richiedi un nuovo collegamento di reimpostazione. Per sicurezza ogni collegamento può essere utilizzato una sola volta.</p></div><?php endif; ?>
			<?php if ( 'mismatch' === $error ) : ?><div class="trb-portal__message trb-portal__message--error"><strong>Le password non coincidono</strong><p>Inserisci la stessa nuova password in entrambi i campi.</p></div><?php endif; ?>
			<?php if ( 'too_short' === $error ) : ?><div class="trb-portal__message trb-portal__message--error"><strong>Password troppo corta</strong><p>La nuova password deve contenere almeno 10 caratteri.</p></div><?php endif; ?>
			<?php if ( 'session' === $error ) : ?><div class="trb-portal__message trb-portal__message--error"><strong>Pagina scaduta</strong><p>Aggiorna la pagina e ripeti l’operazione.</p></div><?php endif; ?>
			<?php if ( $is_reset ) : ?>
				<h2>Imposta la nuova password</h2><p>Il collegamento è personale e può essere utilizzato una sola volta.</p>
				<form action="<?php echo esc_url( home_url( '/recupera-password/' ) ); ?>" method="post">
					<?php wp_nonce_field( 'trb_portal_password_recovery', 'trb_password_nonce' ); ?>
					<input type="hidden" name="trb_portal_action" value="reset_password" /><input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>" /><input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>" />
					<p><label for="pass1">Nuova password</label><input type="password" name="pass1" id="pass1" autocomplete="new-password" minlength="10" required /></p>
					<p><label for="pass2">Conferma la nuova password</label><input type="password" name="pass2" id="pass2" autocomplete="new-password" minlength="10" required /></p>
					<p class="login-submit"><input type="submit" value="Salva la nuova password" /></p>
				</form>
			<?php else : ?>
				<h2>Password dimenticata?</h2><p>Ti invieremo un collegamento sicuro per reimpostarla.</p>
				<form name="lostpasswordform" id="lostpasswordform" action="<?php echo esc_url( home_url( '/recupera-password/' ) ); ?>" method="post">
					<?php wp_nonce_field( 'trb_portal_password_recovery', 'trb_password_nonce' ); ?>
					<input type="hidden" name="trb_portal_action" value="request_password" />
					<p><label for="user_login">E-mail o nome utente</label><input type="text" name="user_login" id="user_login" autocomplete="username" required /></p>
					<p class="login-submit"><input type="submit" value="Invia il collegamento" /></p>
				</form>
			<?php endif; ?>
		</div>
	</section>
</main>
<footer class="trb-public-footer"><span>&copy; 2008-<?php echo esc_html( wp_date( 'Y' ) ); ?> TRB rec di Andrea Tognassi - Music Publishing</span><span>Tutti i diritti riservati.</span></footer>
<?php wp_footer(); ?>
</body>
</html>
