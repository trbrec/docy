<?php
/**
 * Public landing for the approval-only Artist Portal.
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
$login_url     = home_url( '/accedi/' );
?>
<main class="trb-landing">
	<header class="trb-landing__topbar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="trb-landing__brand"><img src="<?php echo esc_url( trb_portal_logo_url() ); ?>" alt="TRB rec" width="186" height="62" /></a>
		<div class="trb-landing__topbar-actions">
			<?php if ( ! is_user_logged_in() ) : ?>
				<a class="trb-landing__account trb-landing__account--register" href="<?php echo esc_url( home_url( '/registrati/' ) ); ?>">Registrati</a>
				<a class="trb-landing__account" href="<?php echo esc_url( $login_url ); ?>">Accedi</a>
			<?php endif; ?>
			<a class="trb-landing__site-link" href="<?php echo esc_url( home_url( '/segnalazione/' ) ); ?>">Apri una segnalazione</a>
		</div>
	</header>
	<section class="trb-landing__hero">
		<div class="trb-landing__hero-copy">
			<p>PORTALE ARTISTI · AREA RISERVATA</p>
			<h1>Il punto di riferimento per gli artisti TRB rec e Digital Distribution Bundle.</h1>
			<p class="trb-landing__lead">Knowledge Hub Esclusivo: linee guida, procedure, formazione e supporto tecnico per il percorso artistico dei nostri artisti.</p>
			<div class="trb-landing__actions">
				<a class="trb-button" href="<?php echo esc_url( home_url( '/registrati/' ) ); ?>">Registrati</a>
				<a class="trb-button trb-button--secondary trb-landing__login" href="<?php echo esc_url( $login_url ); ?>">Accedi</a>
			</div>
		</div>
		<aside class="trb-landing__access-note"><strong>Attenzione: accesso subordinato ad approvazione</strong><p>L'accesso è riservato agli artisti contrattualizzati o espressamente autorizzati dalla Direzione TRB rec - Music Publishing.<br><br><b>La sola registrazione non attiva i servizi né l'Area Artisti</b>, e non costituisce approvazione contrattuale. Gli account non autorizzati rimarranno inattivi e verranno eliminati automaticamente dopo 30 giorni.</p></aside>
	</section>
	<section class="trb-landing__how"><p>COME FUNZIONA</p><h2>Un portale privato, non una pagina pubblica.</h2><div><article><span>01</span><h3>Registrazione autorizzata</h3><p>E' possibile registrarsi solo dopo eventuale sottoscrizione di un accordo contrattuale o su specifica autorizzazione della Direzione.</p></article><article><span>02</span><h3>Approvazione dell’account</h3><p>La Direzione verifica e abilita personalmente il profilo artistico in base alla tipologia di accordo contrattuale raggiunto.</p></article><article><span>03</span><h3>Profilo artista</h3><p>Completamento del profilo con i dati e la documentazione necessari agli adempimenti contrattuali e alla valorizzazione dell'identità artistica.</p></article><article><span>04</span><h3>Knowledge Hub</h3><p>Risorse formative e linee guida per la preparazione di ogni pubblicazione, secondo le procedure e i servizi previsti.</p></article></div></section>
</main>
<footer class="trb-public-footer"><span>&copy; 2008-<?php echo esc_html( wp_date( 'Y' ) ); ?> TRB rec di Andrea Tognassi - Music Publishing</span><span>Tutti i diritti riservati.</span></footer>
<?php wp_footer(); ?>
</body>
</html>
