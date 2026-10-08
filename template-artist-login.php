<?php
/**
 * Artist Portal login page.
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
<main class="trb-login-page">
	<header class="trb-landing__topbar"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="trb-landing__brand"><img src="<?php echo esc_url( trb_portal_logo_url() ); ?>" alt="TRB rec" width="186" height="62" /></a><div class="trb-landing__topbar-actions"><a class="trb-landing__account trb-landing__account--register" href="<?php echo esc_url( home_url( '/registrati/' ) ); ?>">Registrati</a><a class="trb-landing__account trb-landing__account--login" href="<?php echo esc_url( home_url( '/accedi/' ) ); ?>" aria-current="page">Accedi</a><a class="trb-landing__site-link" href="<?php echo esc_url( home_url( '/segnalazione/' ) ); ?>"><span class="trb-action-label--full">Apri una segnalazione</span><span class="trb-action-label--short">Segnalazione</span></a></div></header>
	<section class="trb-login">
		<div class="trb-login__intro"><p>PORTALE ARTISTI &middot; ACCESSO</p><h1>Accedi al tuo spazio riservato.</h1><p>Procedure, Knowledge Hub e pratiche di release sono disponibili esclusivamente per gli artisti abilitati dalla Direzione TRB rec.</p><a href="<?php echo esc_url( home_url( '/registrati/' ) ); ?>">Non hai ancora un account? Registrati solo se autorizzato.</a></div>
		<div class="trb-login__form"><h2>Accedi</h2><p>Usa le credenziali ricevute o create durante la registrazione.</p>
			<?php $trb_login_reason = isset( $_GET['trb_login'] ) ? sanitize_key( wp_unslash( $_GET['trb_login'] ) ) : ''; ?>
			<?php $trb_password_reset = isset( $_GET['password_reset'] ) ? sanitize_key( wp_unslash( $_GET['password_reset'] ) ) : ''; ?>
			<?php if ( 'success' === $trb_password_reset ) : ?><div class="trb-portal__message" role="status"><strong>Password aggiornata</strong><p>La nuova password è stata salvata. Ora puoi accedere al Portale Artisti.</p></div><?php endif; ?>
			<?php if ( 'session_expired' === $trb_login_reason ) : ?><div class="trb-portal__message trb-portal__message--error" role="alert"><strong>Sessione scaduta</strong><p>Accedi nuovamente per continuare. I dati testuali salvati nella bozza della release restano disponibili.</p></div><?php endif; ?>
			<?php if ( 'contract_expired' === $trb_login_reason ) : ?>
				<div class="trb-portal__message trb-portal__message--error trb-login__contract-expired" role="alert">
					<strong>Il tuo contratto artistico è giunto alla scadenza.</strong>
					<p>Il periodo contrattuale associato al profilo si è concluso e l’accesso alle funzioni riservate del Portale Artisti è stato temporaneamente sospeso.</p>
					<p>Il percorso condiviso ha rappresentato una fase importante del tuo progetto. Siamo felici del lavoro costruito insieme e ci farebbe piacere confrontarci su come proseguire la tua crescita artistica, programmare le prossime pubblicazioni e dare continuità a quanto realizzato.</p>
					<p>Contattaci per valutare insieme l’eventuale rinnovo, le condizioni aggiornate e il percorso più adatto alla nuova fase del progetto. Il catalogo e lo storico già presenti nel portale restano conservati: torneranno disponibili dopo il rinnovo e la riattivazione da parte della Direzione.</p>
					<p><a class="trb-button trb-button--compact" href="<?php echo esc_url( home_url( '/segnalazione/' ) ); ?>">Contattaci per il rinnovo</a></p>
				</div>
			<?php elseif ( 'failed' === $trb_login_reason ) : ?><div class="trb-portal__message trb-portal__message--error">Accesso non riuscito. Controlla e-mail e password oppure verifica che l’account sia stato approvato.</div><?php endif; ?>
			<form name="loginform" id="loginform" action="<?php echo esc_url( home_url( '/accedi/' ) ); ?>" method="post">
				<input type="hidden" name="trb_portal_action" value="login" />
				<?php wp_nonce_field( 'trb_portal_login', 'trb_portal_login_nonce' ); ?>
				<p class="login-username"><label for="user_login">E-mail o nome utente</label><input type="text" name="log" id="user_login" autocomplete="username" required /></p>
				<p class="login-password"><label for="user_pass">Password</label><input type="password" name="pwd" id="user_pass" autocomplete="current-password" required /></p>
				<p class="login-remember"><label><input name="rememberme" type="checkbox" value="forever" /> Ricordami</label></p>
				<p class="login-submit"><input type="submit" name="wp-submit" value="Accedi al Portale Artisti" /></p>
			</form>
			<p class="trb-login__lost"><a href="<?php echo esc_url( home_url( '/recupera-password/' ) ); ?>">Password dimenticata?</a></p>
		</div>
	</section>
</main>
<footer class="trb-public-footer"><span>&copy; 2008-<?php echo esc_html( wp_date( 'Y' ) ); ?> TRB rec di Andrea Tognassi - Music Publishing</span><span>Tutti i diritti riservati.</span></footer>
<?php wp_footer(); ?>
</body>
</html>
