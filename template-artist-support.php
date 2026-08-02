<?php
/**
 * Public support page for Artist Portal members and access assistance.
 *
 * @package docy
 */

$user    = wp_get_current_user();
$sent    = isset( $_GET['trb_support'] ) && 'sent' === sanitize_key( wp_unslash( $_GET['trb_support'] ) );
$invalid = isset( $_GET['trb_support'] ) && 'invalid' === sanitize_key( wp_unslash( $_GET['trb_support'] ) );
$logged_in = is_user_logged_in();
$profile = $logged_in ? trb_portal_user_profile( $user ) : '';
$full_name = $logged_in ? trim( $user->first_name . ' ' . $user->last_name ) : '';
$email = $logged_in ? $user->user_email : '';
$artist_name = $logged_in ? trb_portal_artist_profile_value( 'artist_name', $user->ID ) : '';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'trb-artist-public-shell' ); ?>>
<?php wp_body_open(); ?>
<main class="trb-support-page">
	<header class="trb-portal-topbar">
		<div class="trb-portal-topbar__inner">
			<a class="trb-portal-topbar__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Torna al Portale Artisti TRB rec"><img src="<?php echo esc_url( trb_portal_logo_url() ); ?>" alt="TRB rec" width="186" height="62" /></a>
			<div class="trb-portal-topbar__actions"><a class="trb-portal-topbar__back" href="<?php echo esc_url( $logged_in ? home_url( '/area-artisti/' ) : home_url( '/' ) ); ?>">← <?php echo esc_html( $logged_in ? 'Area Artisti' : 'Portale Artisti' ); ?></a></div>
		</div>
	</header>
	<section class="trb-support">
		<p class="trb-support__eyebrow">AREA ARTISTI · ASSISTENZA</p>
		<h1>Apri una segnalazione</h1>
		<p class="trb-support__lead">Segnala un problema di registrazione o accesso, richiedi assistenza oppure proponi una call di 30 minuti. La richiesta sarà inviata direttamente alla Direzione TRB rec.</p>
		<?php if ( $sent ) : ?><div class="trb-support__message">Segnalazione inviata. Riceverai riscontro via e-mail.</div><?php endif; ?>
		<?php if ( $invalid ) : ?><div class="trb-support__message trb-support__message--error">Non è stato possibile inviare la richiesta. Verifica tutti i campi e riprova.</div><?php endif; ?>
		<div class="trb-support__layout">
		<form class="trb-support__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<div class="trb-support__form-heading"><p>NUOVA SEGNALAZIONE</p><h2>Come possiamo aiutarti?</h2><span>I campi contrassegnati con * sono obbligatori.</span></div>
			<?php wp_nonce_field( 'trb_portal_submit_support', 'trb_support_nonce' ); ?>
			<input type="hidden" name="action" value="trb_portal_submit_support" />
			<input type="hidden" name="trb_support_profile" value="<?php echo esc_attr( $profile ); ?>" />
			<input type="hidden" name="trb_support_started" value="<?php echo esc_attr( time() ); ?>" />
			<div class="trb-support__website" aria-hidden="true"><label>Lascia vuoto questo campo<input type="text" name="trb_support_website" value="" tabindex="-1" autocomplete="off" /></label></div>
			<div class="trb-support__field-grid">
				<label><span class="trb-support__label-text">Nome e cognome <b aria-hidden="true">*</b></span><input type="text" name="trb_support_name" value="<?php echo esc_attr( $full_name ); ?>" <?php echo $logged_in ? 'readonly' : ''; ?> autocomplete="name" required /></label>
				<label><span class="trb-support__label-text">Nome d’arte <b aria-hidden="true">*</b></span><input type="text" name="trb_support_artist_name" value="<?php echo esc_attr( $artist_name ); ?>" autocomplete="organization-title" required /></label>
				<label class="trb-support__field-wide"><span class="trb-support__label-text">E-mail <b aria-hidden="true">*</b></span><input type="email" name="trb_support_email" value="<?php echo esc_attr( $email ); ?>" <?php echo $logged_in ? 'readonly' : ''; ?> autocomplete="email" required /></label>
			</div>
			<label><span class="trb-support__label-text">Tipo di richiesta <b aria-hidden="true">*</b></span>
				<select name="trb_support_type" required><option value="supporto">Richiesta di supporto via e-mail</option><option value="call">Richiesta call di 30 minuti</option><option value="dati">Modifica dati anagrafici o contatti</option><option value="problema">Problema tecnico del portale</option></select>
			</label>
			<label><span class="trb-support__label-text">Oggetto <b aria-hidden="true">*</b></span><input type="text" name="trb_support_subject" maxlength="160" placeholder="Descrivi brevemente il motivo della richiesta" required /></label>
			<label><span class="trb-support__label-text">Messaggio <b aria-hidden="true">*</b></span><textarea name="trb_support_message" rows="7" placeholder="Spiega con precisione cosa è successo o di quale assistenza hai bisogno." required></textarea></label>
			<button class="trb-button" type="submit">Invia la segnalazione</button>
		</form>
		<aside class="trb-support__aside"><p>ASSISTENZA DIRETTA</p><h2>La richiesta giusta, al reparto giusto.</h2><ul><li><strong>Problemi di accesso</strong><span>Registrazione, approvazione, password e login.</span></li><li><strong>Dati dell’artista</strong><span>Modifiche anagrafiche, referente ed e-mail.</span></li><li><strong>Supporto tecnico</strong><span>Problemi del portale, procedure e servizi.</span></li><li><strong>Call di 30 minuti</strong><span>Indica nel messaggio motivazione e disponibilità.</span></li></ul><small>Le richieste vengono recapitate a <strong>info@trbrec.com</strong>.</small></aside>
		</div>
	</section>
</main>
<footer class="trb-public-footer"><span>&copy; 2008-<?php echo esc_html( wp_date( 'Y' ) ); ?> TRB rec di Andrea Tognassi - Music Publishing</span><span>Tutti i diritti riservati.</span></footer>
<?php wp_footer(); ?>
</body>
</html>
