<?php
/**
 * Public support page for Artist Portal members and access assistance.
 *
 * @package docy
 */

$user    = wp_get_current_user();
$sent    = isset( $_GET['trb_support'] ) && 'sent' === sanitize_key( wp_unslash( $_GET['trb_support'] ) );
$logged_in = is_user_logged_in();
$profile = $logged_in ? trb_portal_user_profile( $user ) : '';
$full_name = $logged_in ? trim( $user->first_name . ' ' . $user->last_name ) : '';
$email = $logged_in ? $user->user_email : '';
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
		<p class="trb-support__lead">Invia una richiesta di supporto oppure chiedi una call di 30 minuti. La Direzione TRB rec riceverà la segnalazione all’indirizzo <strong>info@trbrec.com</strong>.</p>
		<?php if ( $sent ) : ?><div class="trb-support__message">Segnalazione inviata. Riceverai riscontro via e-mail.</div><?php endif; ?>
		<form class="trb-support__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'trb_portal_submit_support', 'trb_support_nonce' ); ?>
			<input type="hidden" name="action" value="trb_portal_submit_support" />
			<input type="hidden" name="trb_support_profile" value="<?php echo esc_attr( $profile ); ?>" />
			<input type="hidden" name="trb_support_started" value="<?php echo esc_attr( time() ); ?>" />
			<div class="trb-support__website" aria-hidden="true"><label>Lascia vuoto questo campo<input type="text" name="trb_support_website" value="" tabindex="-1" autocomplete="off" /></label></div>
			<div class="trb-support__field-grid">
				<label>Nome e cognome <span aria-hidden="true">*</span><input type="text" name="trb_support_name" value="<?php echo esc_attr( $full_name ); ?>" <?php echo $logged_in ? 'readonly' : ''; ?> autocomplete="name" required /></label>
				<label>E-mail <span aria-hidden="true">*</span><input type="email" name="trb_support_email" value="<?php echo esc_attr( $email ); ?>" <?php echo $logged_in ? 'readonly' : ''; ?> autocomplete="email" required /></label>
			</div>
			<label>Come possiamo aiutarti?
				<select name="trb_support_type" required><option value="supporto">Richiesta di supporto via e-mail</option><option value="call">Richiesta call di 30 minuti</option><option value="dati">Modifica dati anagrafici o contatti</option><option value="problema">Problema tecnico del portale</option></select>
			</label>
			<label>Oggetto<input type="text" name="trb_support_subject" maxlength="160" required /></label>
			<label>Spiega la richiesta<textarea name="trb_support_message" rows="7" required></textarea></label>
			<button class="trb-button" type="submit">Invia la segnalazione</button>
		</form>
	</section>
</main>
<footer class="trb-public-footer"><span>&copy; 2008-<?php echo esc_html( wp_date( 'Y' ) ); ?> TRB rec di Andrea Tognassi - Music Publishing</span><span>Tutti i diritti riservati.</span></footer>
<?php wp_footer(); ?>
</body>
</html>
