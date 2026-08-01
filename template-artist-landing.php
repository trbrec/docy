<?php
/**
 * Public landing for the approval-only Artist Portal.
 *
 * @package docy
 */

get_header();
$dashboard_url = home_url( '/area-artisti/' );
?>
<main class="trb-landing">
	<header class="trb-landing__topbar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="trb-landing__brand"><img src="https://faq.trbrec.com/wp-content/uploads/2023/08/Vector-TRB-rec-White.png" alt="TRB rec" width="186" height="62" /></a>
		<?php if ( is_user_logged_in() ) : ?><a class="trb-landing__account" href="<?php echo esc_url( $dashboard_url ); ?>">Vai alla tua area</a><?php else : ?><a class="trb-landing__account" href="<?php echo esc_url( wp_login_url( $dashboard_url ) ); ?>">Accedi</a><?php endif; ?>
	</header>
	<section class="trb-landing__hero">
		<div class="trb-landing__hero-copy">
			<p>PORTALE ARTISTI · AREA RISERVATA</p>
			<h1>Il punto di riferimento per gli artisti TRB rec e Digital Distribution Bundle.</h1>
			<p class="trb-landing__lead">Procedure per le release, formazione, documentazione e richieste: un solo spazio privato, personalizzato in base al tuo contratto.</p>
			<div class="trb-landing__actions">
				<?php if ( is_user_logged_in() ) : ?><a class="trb-button" href="<?php echo esc_url( $dashboard_url ); ?>">Entra nel Portale Artisti</a><?php else : ?><a class="trb-button" href="<?php echo esc_url( wp_login_url( $dashboard_url ) ); ?>">Accedi al Portale Artisti</a><?php endif; ?>
			</div>
		</div>
		<aside class="trb-landing__access-note"><strong>Accesso riservato</strong><p>La registrazione e l’accesso sono riservati agli artisti già approvati dalla Direzione TRB rec.</p><p>Non hai ancora ricevuto conferma? Non registrarti autonomamente: attendi le istruzioni ricevute via e-mail.</p></aside>
	</section>
	<section class="trb-landing__how"><p>COME FUNZIONA</p><h2>Un portale privato, non una pagina FAQ pubblica.</h2><div><article><span>01</span><h3>Approvazione</h3><p>La Direzione abilita l’accesso soltanto agli artisti con una collaborazione confermata.</p></article><article><span>02</span><h3>Profilo personale</h3><p>Completi dati e materiali necessari per le pratiche contrattuali e per la tua identità artistica.</p></article><article><span>03</span><h3>Release e Knowledge Hub</h3><p>Prepari ogni pubblicazione seguendo passaggi collegati, con guide e risorse previste dal tuo contratto.</p></article></div></section>
</main>
<?php get_footer(); ?>
