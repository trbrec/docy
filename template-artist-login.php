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
<?php
$dashboard_url = home_url( '/area-artisti/' );
?>
<main class="trb-login-page">
	<header class="trb-landing__topbar"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="trb-landing__brand"><img src="https://faq.trbrec.com/wp-content/uploads/2023/08/Vector-TRB-rec-White.png" alt="TRB rec" width="186" height="62" /></a><a class="trb-landing__site-link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Apri una segnalazione</a></header>
	<section class="trb-login">
		<div class="trb-login__intro"><p>PORTALE ARTISTI &middot; ACCESSO</p><h1>Accedi al tuo spazio riservato.</h1><p>Procedure, Knowledge Hub e pratiche di release sono disponibili esclusivamente per gli artisti abilitati dalla Direzione TRB rec.</p><a href="<?php echo esc_url( home_url( '/registrati/' ) ); ?>">Non hai ancora un account? Registrati solo se autorizzato.</a></div>
		<div class="trb-login__form"><h2>Accedi</h2><p>Usa le credenziali ricevute o create durante la registrazione.</p>
			<form name="loginform" id="loginform" action="<?php echo esc_url( add_query_arg( 'wppb_force_wp_login', 'true', wp_login_url() ) ); ?>" method="post">
				<p class="login-username"><label for="user_login">E-mail o nome utente</label><input type="text" name="log" id="user_login" autocomplete="username" required /></p>
				<p class="login-password"><label for="user_pass">Password</label><input type="password" name="pwd" id="user_pass" autocomplete="current-password" required /></p>
				<p class="login-remember"><label><input name="rememberme" type="checkbox" value="forever" /> Ricordami</label></p>
				<p class="login-submit"><input type="submit" name="wp-submit" value="Accedi al Portale Artisti" /><input type="hidden" name="redirect_to" value="<?php echo esc_url( $dashboard_url ); ?>" /><input type="hidden" name="testcookie" value="1" /></p>
			</form>
			<p class="trb-login__lost"><a href="<?php echo esc_url( home_url( '/recupera-password/' ) ); ?>">Password dimenticata?</a></p>
		</div>
	</section>
</main>
<footer class="trb-public-footer"><span>&copy; 2008-<?php echo esc_html( wp_date( 'Y' ) ); ?> TRB rec di Andrea Tognassi - Music Publishing</span><span>Tutti i diritti riservati.</span></footer>
<?php wp_footer(); ?>
</body>
</html>
