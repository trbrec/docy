<?php
/**
 * One-time launch campaign for the renewed TRB rec Artist Portal.
 *
 * @package docy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TRB_PORTAL_LAUNCH_CAMPAIGN = 'portal-launch-2026-08-10';
const TRB_PORTAL_LAUNCH_HOOK     = 'trb_portal_send_launch_campaign_batch';

/** Return the fixed launch date in the Italian timezone. */
function trb_portal_launch_campaign_datetime() {
	return new DateTimeImmutable( '2026-08-10 09:00:00', new DateTimeZone( 'Europe/Rome' ) );
}

/** Collect every artist role, including the aliases retained for legacy accounts. */
function trb_portal_launch_campaign_roles() {
	$roles = array();
	foreach ( trb_portal_profiles() as $profile ) {
		$roles[] = $profile['role'];
		$roles   = array_merge( $roles, isset( $profile['aliases'] ) ? (array) $profile['aliases'] : array() );
	}
	return array_values( array_unique( array_filter( $roles ) ) );
}

/**
 * Register the one-time campaign. The overdue fallback protects the campaign
 * if WP-Cron is cleared or the deployment completes shortly before launch.
 */
function trb_portal_schedule_launch_campaign() {
	if ( get_option( 'trb_portal_launch_campaign_completed' ) === TRB_PORTAL_LAUNCH_CAMPAIGN ) {
		return;
	}

	$send_at = trb_portal_launch_campaign_datetime()->getTimestamp();
	if ( ! wp_next_scheduled( TRB_PORTAL_LAUNCH_HOOK ) ) {
		wp_schedule_single_event( max( time() + 30, $send_at ), TRB_PORTAL_LAUNCH_HOOK );
	}
}
add_action( 'init', 'trb_portal_schedule_launch_campaign', 45 );

/** Prefer the artist name stored in the portal, with a safe account fallback. */
function trb_portal_launch_campaign_recipient_name( WP_User $user ) {
	$artist_name = trim( (string) get_user_meta( $user->ID, '_trb_artist_artist_name', true ) );
	if ( '' !== $artist_name ) {
		return $artist_name;
	}
	$name = trim( (string) $user->display_name );
	return '' !== $name ? $name : 'Artista';
}

/** Build the responsive HTML message sent to each artist. */
function trb_portal_launch_campaign_message( WP_User $user ) {
	$name      = esc_html( trb_portal_launch_campaign_recipient_name( $user ) );
	$login_url = esc_url( home_url( '/accedi/' ) );
	$year      = esc_html( wp_date( 'Y' ) );

	return '<!doctype html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>' .
	'<body style="margin:0;padding:0;background:#f3f5f7;color:#1d2733;font-family:Arial,Helvetica,sans-serif;">' .
	'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f5f7;padding:28px 12px;"><tr><td align="center">' .
	'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 8px 28px rgba(20,31,45,.08);">' .
	'<tr><td style="background:#101820;padding:30px 42px;color:#ffffff;"><div style="font-size:14px;letter-spacing:1.7px;text-transform:uppercase;color:#e33139;font-weight:700;">TRB rec – Music Publishing</div><h1 style="font-size:30px;line-height:1.2;margin:12px 0 0;color:#ffffff;">Il nuovo portale artisti è online</h1></td></tr>' .
	'<tr><td style="padding:38px 42px;font-size:16px;line-height:1.7;">' .
	'<p style="margin:0 0 22px;">Ciao <strong>' . $name . '</strong>,</p>' .
	'<p style="margin:0 0 22px;">siamo lieti di annunciarti l’inaugurazione del nuovo portale artisti TRB rec, completamente riprogettato per accompagnarti nella gestione delle pubblicazioni e nel tuo percorso con noi.</p>' .
	'<p style="margin:0 0 22px;">Non si tratta di un semplice aggiornamento grafico. Abbiamo ricostruito l’intera area riservata con l’obiettivo di riunire in un unico spazio tutto ciò che riguarda la tua attività artistica: identità, materiali, pubblicazioni, servizi, documenti, assistenza e comunicazioni operative.</p>' .
	'<p style="margin:0 0 30px;">Il portale riconosce automaticamente il tuo percorso con TRB rec e ti mostra esclusivamente le informazioni, le procedure e i servizi pertinenti. In questo modo potrai lavorare con indicazioni più precise, evitando passaggi superflui o informazioni non applicabili alla tua situazione.</p>' .
	'<h2 style="font-size:20px;line-height:1.3;margin:0 0 14px;color:#101820;">Un unico spazio per il tuo progetto artistico</h2>' .
	'<p style="margin:0 0 18px;">Accedendo alla tua area personale potrai completare e aggiornare la tua identità artistica, verificando biografia, fotografie, collegamenti ufficiali, recapiti e documenti.</p>' .
	'<p style="margin:0 0 13px;">Troverai inoltre il tuo catalogo e potrai preparare le prossime pubblicazioni attraverso una procedura guidata, pensata per raccogliere correttamente:</p>' .
	'<ul style="margin:0 0 30px;padding-left:22px;"><li style="margin-bottom:7px;">titolo e caratteristiche della release;</li><li style="margin-bottom:7px;">artisti principali, featuring e collaboratori;</li><li style="margin-bottom:7px;">autori, compositori e crediti;</li><li style="margin-bottom:7px;">genere musicale e informazioni editoriali;</li><li style="margin-bottom:7px;">testo e presentazione del brano;</li><li style="margin-bottom:7px;">copertina e materiali promozionali;</li><li>file audio e relative caratteristiche tecniche.</li></ul>' .
	'<p style="margin:0 0 30px;">Il sistema ti segnalerà eventuali dati mancanti o materiali da correggere prima dell’invio, riducendo gli scambi successivi e permettendoci di lavorare con maggiore rapidità e precisione.</p>' .
	'<h2 style="font-size:20px;line-height:1.3;margin:0 0 14px;color:#101820;">Le tue pubblicazioni, sempre sotto controllo</h2>' .
	'<p style="margin:0 0 30px;">Ogni release avrà uno spazio dedicato dal quale potrai verificare i materiali consegnati, seguire l’avanzamento della lavorazione e conoscere le attività ancora necessarie. Avrai così una visione più chiara dell’intero percorso, dalla preparazione iniziale fino alla pubblicazione, senza dover ricostruire le informazioni attraverso email e conversazioni separate.</p>' .
	'<h2 style="font-size:20px;line-height:1.3;margin:0 0 14px;color:#101820;">Risposte più precise, quando ti servono</h2>' .
	'<p style="margin:0 0 30px;">Abbiamo realizzato una nuova area di assistenza con guide approfondite e un motore di ricerca interno. Potrai cercare argomenti come copertine, file audio, mastering, autori e compositori, royalties, Content ID, Smartlink, playlist, tempistiche, correzioni e stato delle pubblicazioni. Le risposte saranno coerenti con i servizi previsti per il tuo percorso con TRB rec.</p>' .
	'<h2 style="font-size:20px;line-height:1.3;margin:0 0 14px;color:#101820;">Più autonomia, senza perdere il rapporto diretto</h2>' .
	'<p style="margin:0 0 30px;">Il portale non sostituisce il confronto umano con TRB rec. Nasce per semplificare la gestione operativa e permetterci di dedicare maggiore attenzione alla musica, alle pubblicazioni e allo sviluppo dei progetti artistici. Le comunicazioni più importanti continueranno ad arrivarti anche tramite email.</p>' .
	'<h2 style="font-size:20px;line-height:1.3;margin:0 0 14px;color:#101820;">Accedi e controlla il tuo profilo</h2>' .
	'<p style="margin:0 0 13px;">Ti invitiamo a effettuare il primo accesso e a verificare attentamente:</p>' .
	'<ul style="margin:0 0 30px;padding-left:22px;"><li style="margin-bottom:7px;">dati personali e recapiti;</li><li style="margin-bottom:7px;">nome d’arte e profili ufficiali;</li><li style="margin-bottom:7px;">biografia e fotografie;</li><li style="margin-bottom:7px;">documenti eventualmente richiesti;</li><li style="margin-bottom:7px;">pubblicazioni presenti nel catalogo;</li><li>attività segnalate come incomplete.</li></ul>' .
	'<table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 30px;"><tr><td bgcolor="#e33139" style="border-radius:8px;"><a href="' . $login_url . '" style="display:inline-block;padding:15px 26px;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;">ACCEDI AL NUOVO PORTALE ARTISTI</a></td></tr></table>' .
	'<p style="margin:0 0 22px;color:#4d5a66;font-size:14px;">Se non ricordi la password, potrai richiederne una nuova direttamente dalla pagina di accesso utilizzando l’indirizzo email con cui sei registrato.</p>' .
	'<p style="margin:0 0 22px;">Il nuovo portale rappresenta un passaggio importante nell’evoluzione di TRB rec – Music Publishing: uno strumento più completo, trasparente e organizzato, costruito per accompagnare concretamente il lavoro di ogni artista.</p>' .
	'<p style="margin:0 0 28px;">Ti aspettiamo nel tuo nuovo spazio personale.</p>' .
	'<p style="margin:0;"><strong>Andrea Tognassi</strong><br>Owner &amp; A&amp;R Manager<br>TRB rec – Music Publishing · <a href="https://trbrec.com" style="color:#e33139;text-decoration:none;">trbrec.com</a></p>' .
	'</td></tr><tr><td style="background:#101820;color:#aeb8c2;padding:20px 42px;text-align:center;font-size:12px;line-height:1.5;">© 2008–' . $year . ' TRB rec – Music Publishing<br>Ricevi questa comunicazione perché disponi di un account sul Portale Artisti TRB rec.</td></tr>' .
	'</table></td></tr></table></body></html>';
}

/** Send a small batch, recording every successful delivery to prevent duplicates. */
function trb_portal_send_launch_campaign_batch() {
	if ( get_option( 'trb_portal_launch_campaign_completed' ) === TRB_PORTAL_LAUNCH_CAMPAIGN ) {
		return;
	}

	$users  = get_users(
		array(
			'role__in' => trb_portal_launch_campaign_roles(),
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'number'   => -1,
		)
	);
	$users = array_values(
		array_filter(
			$users,
			static function( $user ) {
				$sent     = get_user_meta( $user->ID, '_trb_portal_launch_campaign_sent', true );
				$attempts = (int) get_user_meta( $user->ID, '_trb_portal_launch_campaign_attempts', true );
				return TRB_PORTAL_LAUNCH_CAMPAIGN !== $sent && $attempts < 3;
			}
		)
	);
	$users = array_slice( $users, 0, 20 );

	if ( empty( $users ) ) {
		update_option( 'trb_portal_launch_campaign_completed', TRB_PORTAL_LAUNCH_CAMPAIGN, false );
		update_option( 'trb_portal_launch_campaign_completed_at', current_time( 'mysql' ), false );
		return;
	}

	$subject = 'Inaugurazione del nuovo portale artisti TRB rec';
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: TRB rec - Music Publishing <info@trbrec.com>',
		'Reply-To: TRB rec - Music Publishing <info@trbrec.com>',
	);

	foreach ( $users as $user ) {
		$attempts = (int) get_user_meta( $user->ID, '_trb_portal_launch_campaign_attempts', true ) + 1;
		update_user_meta( $user->ID, '_trb_portal_launch_campaign_attempts', $attempts );
		if ( wp_mail( $user->user_email, $subject, trb_portal_launch_campaign_message( $user ), $headers ) ) {
			update_user_meta( $user->ID, '_trb_portal_launch_campaign_sent', TRB_PORTAL_LAUNCH_CAMPAIGN );
			update_user_meta( $user->ID, '_trb_portal_launch_campaign_sent_at', current_time( 'mysql' ) );
		}
	}

	if ( ! wp_next_scheduled( TRB_PORTAL_LAUNCH_HOOK ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, TRB_PORTAL_LAUNCH_HOOK );
	}
}
add_action( TRB_PORTAL_LAUNCH_HOOK, 'trb_portal_send_launch_campaign_batch' );

/** Expose aggregate delivery state for operational verification (no personal data). */
function trb_portal_register_launch_campaign_status() {
	register_rest_route(
		'trb/v1',
		'/portal-launch-status',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'callback'            => static function() {
				$users   = get_users( array( 'role__in' => trb_portal_launch_campaign_roles(), 'fields' => 'ids', 'number' => -1 ) );
				$sent    = 0;
				$failed  = 0;
				foreach ( $users as $user_id ) {
					if ( TRB_PORTAL_LAUNCH_CAMPAIGN === get_user_meta( $user_id, '_trb_portal_launch_campaign_sent', true ) ) {
						$sent++;
					} elseif ( (int) get_user_meta( $user_id, '_trb_portal_launch_campaign_attempts', true ) >= 3 ) {
						$failed++;
					}
				}
				$next = wp_next_scheduled( TRB_PORTAL_LAUNCH_HOOK );
				return rest_ensure_response(
					array(
						'campaign'    => TRB_PORTAL_LAUNCH_CAMPAIGN,
						'total'       => count( $users ),
						'sent'        => $sent,
						'pending'     => max( 0, count( $users ) - $sent - $failed ),
						'failed'      => $failed,
						'completed'   => get_option( 'trb_portal_launch_campaign_completed' ) === TRB_PORTAL_LAUNCH_CAMPAIGN,
						'next_run_at' => $next ? wp_date( DATE_ATOM, $next, new DateTimeZone( 'Europe/Rome' ) ) : null,
					)
				);
			},
		)
	);
}
add_action( 'rest_api_init', 'trb_portal_register_launch_campaign_status' );
