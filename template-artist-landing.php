<?php
/**
 * Public landing for the approval-only Artist Portal.
 *
 * @package docy
 */

get_header();
$dashboard_url = home_url( '/area-artisti/' );
$login_url     = add_query_arg( 'wppb_force_wp_login', 'true', wp_login_url( $dashboard_url ) );
?>
<main class="trb-landing">
	<header class="trb-landing__topbar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="trb-landing__brand"><img src="https://faq.trbrec.com/wp-content/uploads/2023/08/Vector-TRB-rec-White.png" alt="TRB rec" width="186" height="62" /></a>
		<a class="trb-landing__site-link" href="https://trbrec.com">Visita il sito TRB rec - Music Publishing</a>
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
		<aside class="trb-landing__access-note"><strong>Attenzione: accesso subordinato ad approvazione</strong><p>L'accesso è riservato agli artisti contrattualizzati o espressamente autorizzati dalla Direzione TRB rec - Music Publishing. La sola registrazione non attiva i servizi né l'Area Artisti, e non costituisce approvazione contrattuale. Gli account non autorizzati rimarranno inattivi e verranno eliminati automaticamente dopo 30 giorni.</p></aside>
	</section>
	<section class="trb-landing__how"><p>COME FUNZIONA</p><h2>Un portale privato, non una pagina pubblica.</h2><div><article><span>01</span><h3>Registrazione autorizzata</h3><p>Puoi registrarti solo dopo la sottoscrizione dell’accordo raggiunto o una comunicazione della Direzione.</p></article><article><span>02</span><h3>Approvazione dell’account</h3><p>TRB rec verifica e abilita personalmente il profilo contrattuale corretto.</p></article><article><span>03</span><h3>Profilo personale</h3><p>Completi dati e materiali necessari per le pratiche contrattuali e per la tua identità artistica.</p></article><article><span>04</span><h3>Release e Knowledge Hub</h3><p>Prepari ogni pubblicazione con procedure, risorse e servizi previsti dal tuo contratto.</p></article></div></section>
</main>
<?php get_footer(); ?>
