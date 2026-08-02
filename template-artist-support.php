<?php
/**
 * Authenticated support page for Artist Portal members.
 *
 * @package docy
 */
if ( ! is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/accedi/' ) );
	exit;
}
$user = wp_get_current_user();
$sent = isset( $_GET['trb_support'] ) && 'sent' === sanitize_key( wp_unslash( $_GET['trb_support'] ) );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>" /><meta name="viewport" content="width=device-width, initial-scale=1" /><?php wp_head(); ?></head>
<body <?php body_class( 'trb-artist-public-shell' ); ?>><?php wp_body_open(); ?>
<main class="trb-support-page">
<header class="trb-portal-topbar"><div class="trb-portal-topbar__inner"><a class="trb-portal-topbar__brand" href="<?php echo esc_url( home_url( '/area-artisti/' ) ); ?>" aria-label="Torna all'Area Artisti TRB rec"><img src="<?php echo esc_url( trb_portal_logo_url() ); ?>" alt="TRB rec" width="186" height="62" /></a><div class="trb-portal-topbar__actions"><a class="trb-portal-topbar__back" href="<?php echo esc_url( home_url( '/area-artisti/' ) ); ?>">← Area Artisti</a></div></div></header>
<section class="trb-support">
<p class="trb-support__eyebrow">AREA ARTISTI · ASSISTENZA</p><h1>Apri una segnalazione</h1>
<p class="trb-support__lead">Invia una richiesta di supporto oppure chiedi una call di 30 minuti. La Direzione TRB rec riceverà la segnalazione all’indirizzo <strong>info@trbrec.com</strong>.</p>
<?php if ( $sent ) : ?><div class="trb-support__message">Segnalazione inviata. Riceverai riscontro via e-mail.</div><?php endif; ?>
<form class="trb-support__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
<?php wp_nonce_field( 'trb_portal_submit_support', 'trb_support_nonce' ); ?><input type="hidden" name="action" value="trb_portal_submit_support" />
<div class="trb-support__field-grid"><label>Nome e cognome<input type="text" value="<?php echo esc_attr( trim( $user->first_name . ' ' . $user->last_name ) ); ?>" readonly /></label><label>E-mail<input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly /></label></div>
<label>Come possiamo aiutarti?<select name="trb_support_type" required><option value="supporto">Richiesta di supporto via e-mail</option><option value="call">Richiesta call di 30 minuti</option><option value="dati">Modifica dati anagrafici o contatti</option><option value="problema">Problema tecnico del portale</option></select></label>
<label>Oggetto<input type="text" name="trb_support_subject" maxlength="160" required /></label><label>Spiega la richiesta<textarea name="trb_support_message" rows="7" required></textarea></label><button class="trb-button" type="submit">Invia la segnalazione</button>
</form></section></main>
<footer class="trb-public-footer"><span>&copy; 2008-<?php echo esc_html( wp_date( 'Y' ) ); ?> TRB rec di Andrea Tognassi - Music Publishing</span><span>Tutti i diritti riservati.</span></footer><?php wp_footer(); ?></body></html>
