<?php
/** Read-only operational dashboard reserved for the TRB owner account. */

if ( ! defined( 'ABSPATH' ) ) exit;

const TRB_OWNER_DASHBOARD_CAPABILITY = 'trb_view_owner_dashboard';
const TRB_OWNER_DASHBOARD_VERSION = '1.0.0';
const TRB_OWNER_DASHBOARD_SHEET_URL = 'https://docs.google.com/spreadsheets/d/1tanb_tOnvNuFMi_mMDCDQY6MPL-P3vXjeaKDvlvQ-cw/edit';

/** Keep operational visibility separate from write/approval permissions. */
function trb_owner_dashboard_register_access() {
	$role = get_role( 'trb_owner_viewer' );
	if ( ! $role ) {
		$role = add_role( 'trb_owner_viewer', 'Direzione TRB · sola lettura', array(
			'read'                         => true,
			TRB_OWNER_DASHBOARD_CAPABILITY => true,
		) );
	}
	if ( $role && ! $role->has_cap( TRB_OWNER_DASHBOARD_CAPABILITY ) ) $role->add_cap( TRB_OWNER_DASHBOARD_CAPABILITY );
	$administrator = get_role( 'administrator' );
	if ( $administrator && ! $administrator->has_cap( TRB_OWNER_DASHBOARD_CAPABILITY ) ) $administrator->add_cap( TRB_OWNER_DASHBOARD_CAPABILITY );
}
add_action( 'init', 'trb_owner_dashboard_register_access', 5 );

function trb_owner_dashboard_admin_menu() {
	add_menu_page(
		'Cruscotto operativo TRB',
		'Cruscotto TRB',
		TRB_OWNER_DASHBOARD_CAPABILITY,
		'trb-owner-dashboard',
		'trb_owner_dashboard_render',
		'dashicons-chart-area',
		2
	);
}
add_action( 'admin_menu', 'trb_owner_dashboard_admin_menu' );

function trb_owner_dashboard_release_posts() {
	return get_posts( array(
		'post_type'      => 'trb_release',
		'post_status'    => array( 'publish', 'private', 'pending', 'draft' ),
		'posts_per_page' => 250,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	) );
}

function trb_owner_dashboard_demo_posts() {
	return get_posts( array(
		'post_type'      => 'trb_request',
		'post_status'    => array( 'publish', 'private', 'pending', 'draft' ),
		'posts_per_page' => 250,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'meta_query'     => array( array( 'key' => '_trb_demo_payload', 'compare' => 'EXISTS' ) ),
	) );
}

function trb_owner_dashboard_artist_users() {
	return get_users( array(
		'role__in' => array( 'artista_a', 'artista_b', 'artista_c', 'artista_d', 'artista_ddb12', 'artista_dds', 'artista_ddb', 'artista_ddb-trb', 'artista_trb' ),
		'orderby'  => 'display_name',
		'order'    => 'ASC',
		'number'   => 1000,
	) );
}

function trb_owner_dashboard_status_label( $value ) {
	$value = sanitize_key( (string) $value );
	$labels = array(
		'approved'                       => 'Approvata',
		'manual_review'                  => 'Verifica manuale',
		'copyright_review'               => 'Verifica diritti',
		'copyright_documents_needed'     => 'Documenti diritti richiesti',
		'analysis_in_progress'           => 'Analisi in corso',
		'analysis_waiting_configuration' => 'Analisi in attesa configurazione',
		'technical_review'               => 'Verifica tecnica',
		'technical_error'                => 'Errore tecnico',
		'cover_creation_pending'         => 'Copertina in lavorazione',
		'waiting_analysis'               => 'In attesa analisi',
		'preparing'                      => 'Contratto in preparazione',
		'dispatch_error'                 => 'Errore invio contratto',
		'contract_sent'                  => 'Contratto inviato',
		'signed'                         => 'Firmato',
		'ready'                          => 'Valutato, email programmata',
		'sent'                           => 'Valutazione inviata',
		'queued'                         => 'In coda',
		'retry'                          => 'Nuovo tentativo',
	);
	return $labels[ $value ] ?? ( $value ? ucwords( str_replace( '_', ' ', $value ) ) : '—' );
}

function trb_owner_dashboard_badge( $value ) {
	$key = sanitize_key( (string) $value );
	$tone = in_array( $key, array( 'approved', 'signed', 'sent', 'contract_sent' ), true ) ? 'ok' : ( in_array( $key, array( 'dispatch_error', 'technical_error', 'security_rejected', 'upload_failed' ), true ) ? 'bad' : 'wait' );
	return '<span class="trb-owner-badge trb-owner-badge--' . esc_attr( $tone ) . '">' . esc_html( trb_owner_dashboard_status_label( $key ) ) . '</span>';
}

function trb_owner_dashboard_demo_summary( $review ) {
	if ( ! is_array( $review ) ) return '—';
	foreach ( array( 'summary', 'evaluation', 'verdict', 'feedback', 'technical_notes' ) as $key ) {
		if ( ! empty( $review[ $key ] ) && is_scalar( $review[ $key ] ) ) return wp_trim_words( wp_strip_all_tags( (string) $review[ $key ] ), 30 );
	}
	return 'Analisi disponibile';
}

/** Schedule one owner summary only after the contract state reaches signed. */
function trb_owner_dashboard_watch_contract_state( $meta_id, $object_id, $meta_key, $meta_value ) {
	if ( '_trb_contract_state' !== $meta_key || 'signed' !== sanitize_key( (string) $meta_value ) || 'trb_release' !== get_post_type( $object_id ) ) return;
	if ( ! wp_next_scheduled( 'trb_owner_dashboard_send_signed_summary', array( absint( $object_id ) ) ) ) {
		wp_schedule_single_event( time() + 30, 'trb_owner_dashboard_send_signed_summary', array( absint( $object_id ) ) );
	}
}
add_action( 'added_post_meta', 'trb_owner_dashboard_watch_contract_state', 10, 4 );
add_action( 'updated_post_meta', 'trb_owner_dashboard_watch_contract_state', 10, 4 );

function trb_owner_dashboard_send_signed_summary( $release_id ) {
	$release = get_post( absint( $release_id ) );
	if ( ! $release || 'trb_release' !== $release->post_type || 'signed' !== get_post_meta( $release_id, '_trb_contract_state', true ) ) return;
	$user = get_userdata( $release->post_author );
	$artist = function_exists( 'trb_portal_artist_profile_value' ) ? trb_portal_artist_profile_value( 'artist_name', $release->post_author ) : '';
	$contract = sanitize_text_field( (string) get_post_meta( $release_id, '_trb_contract_number', true ) );
	$dossier = sanitize_text_field( (string) get_post_meta( $release_id, '_trb_otp_dossier_id', true ) );
	$signed_at = sanitize_text_field( (string) get_post_meta( $release_id, '_trb_contract_signed_at', true ) );
	$sheet_synced = sanitize_text_field( (string) get_post_meta( $release_id, '_trb_contract_spreadsheet_synced_at', true ) );
	$sheet_error = sanitize_text_field( (string) get_post_meta( $release_id, '_trb_contract_spreadsheet_error', true ) );
	$key = 'owner-contract-signed-' . absint( $release_id ) . '-' . sanitize_key( $dossier ?: $contract );
	$subject = 'Contratto release firmato: #' . absint( $release_id ) . ' - ' . $release->post_title;
	$body = '<p>Il contratto della release è stato firmato da entrambe le parti.</p><table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse">';
	$rows = array(
		'Pratica' => '#' . absint( $release_id ),
		'Artista' => $artist ?: ( $user ? $user->display_name : '—' ),
		'Release' => $release->post_title,
		'Contratto' => $contract ?: '—',
		'Dossier OTP' => $dossier ?: '—',
		'Firma completata' => $signed_at ?: '—',
		'Data di uscita' => (string) ( get_post_meta( $release_id, '_trb_release_date', true ) ?: get_post_meta( $release_id, '_trb_release_original_date', true ) ),
		'Sincronizzazione foglio' => $sheet_error ? 'ERRORE: ' . $sheet_error : ( $sheet_synced ? 'Completata il ' . $sheet_synced : 'In attesa' ),
	);
	foreach ( $rows as $label => $value ) $body .= '<tr><th align="left">' . esc_html( $label ) . '</th><td>' . esc_html( $value ?: '—' ) . '</td></tr>';
	$body .= '</table><p><a href="' . esc_url( get_edit_post_link( $release_id, 'url' ) ) . '">Apri la pratica completa</a> · <a href="' . esc_url( admin_url( 'admin.php?page=trb-owner-dashboard&artist_id=' . absint( $release->post_author ) ) ) . '">Apri il cruscotto dell’artista</a> · <a href="' . esc_url( TRB_OWNER_DASHBOARD_SHEET_URL ) . '">Apri il foglio operativo</a></p>';
	$body .= function_exists( 'trb_resource_artist_email_signature' ) ? trb_resource_artist_email_signature() : '';
	if ( function_exists( 'trb_resource_queue_recipient_email' ) ) {
		trb_resource_queue_recipient_email( $key, 'andrea.tognassi@trbrec.com', $subject, $body, false );
	} else {
		wp_mail( 'andrea.tognassi@trbrec.com', $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
	update_post_meta( $release_id, '_trb_owner_signed_summary_queued_at', time() );
}
add_action( 'trb_owner_dashboard_send_signed_summary', 'trb_owner_dashboard_send_signed_summary', 10, 1 );

/** Backfill only the already completed Ruggia practice requested by the owner. */
function trb_owner_dashboard_backfill_ruggia_summary() {
	$release_id = 12283;
	if ( 'trb_release' !== get_post_type( $release_id ) || 'signed' !== get_post_meta( $release_id, '_trb_contract_state', true ) ) return;
	if ( get_post_meta( $release_id, '_trb_owner_signed_summary_queued_at', true ) ) return;
	if ( ! wp_next_scheduled( 'trb_owner_dashboard_send_signed_summary', array( $release_id ) ) ) {
		wp_schedule_single_event( time() + 30, 'trb_owner_dashboard_send_signed_summary', array( $release_id ) );
	}
}
add_action( 'init', 'trb_owner_dashboard_backfill_ruggia_summary', 30 );

/**
 * Finish the two live practices exactly once after this repair is deployed.
 * The guards make the recovery harmless when an earlier browser request or a
 * provider callback has already completed the same transition.
 */
function trb_owner_dashboard_schedule_live_practice_recovery() {
	$feel_id = 12275;
	$feel_state = sanitize_key( (string) get_post_meta( $feel_id, '_trb_contract_state', true ) );
	if ( 'trb_release' === get_post_type( $feel_id ) && 'approved' === get_post_meta( $feel_id, '_trb_release_pipeline_status', true ) && ! in_array( $feel_state, array( 'contract_sent', 'signed' ), true ) && ! get_post_meta( $feel_id, '_trb_owner_live_recovery_20260825', true ) ) {
		if ( ! wp_next_scheduled( 'trb_owner_dashboard_retry_feel_contract', array( $feel_id ) ) ) wp_schedule_single_event( time() + 20, 'trb_owner_dashboard_retry_feel_contract', array( $feel_id ) );
	}
	$ruggia_id = 12283;
	$ruggia_state = sanitize_key( (string) get_post_meta( $ruggia_id, '_trb_contract_state', true ) );
	if ( 'trb_release' === get_post_type( $ruggia_id ) && in_array( $ruggia_state, array( 'contract_sent', 'signed' ), true ) && ! get_post_meta( $ruggia_id, '_trb_owner_live_recovery_20260825', true ) ) {
		if ( ! wp_next_scheduled( 'trb_owner_dashboard_reconcile_ruggia_contract', array( $ruggia_id ) ) ) wp_schedule_single_event( time() + 20, 'trb_owner_dashboard_reconcile_ruggia_contract', array( $ruggia_id ) );
	}
}
add_action( 'init', 'trb_owner_dashboard_schedule_live_practice_recovery', 31 );

function trb_owner_dashboard_retry_feel_contract( $release_id ) {
	$release_id = absint( $release_id );
	if ( 12275 !== $release_id || ! add_post_meta( $release_id, '_trb_owner_live_recovery_20260825', time(), true ) ) return;
	if ( 'approved' !== get_post_meta( $release_id, '_trb_release_pipeline_status', true ) || in_array( get_post_meta( $release_id, '_trb_contract_state', true ), array( 'contract_sent', 'signed' ), true ) || ! function_exists( 'trb_release_bridge_dispatch' ) ) return;
	update_post_meta( $release_id, '_trb_contract_state', 'preparing' );
	delete_post_meta( $release_id, '_trb_contract_error' );
	trb_release_bridge_dispatch( $release_id );
}
add_action( 'trb_owner_dashboard_retry_feel_contract', 'trb_owner_dashboard_retry_feel_contract', 10, 1 );

function trb_owner_dashboard_reconcile_ruggia_contract( $release_id ) {
	$release_id = absint( $release_id );
	if ( 12283 !== $release_id || ! add_post_meta( $release_id, '_trb_owner_live_recovery_20260825', time(), true ) ) return;
	if ( 'DDB20260031' !== (string) get_post_meta( $release_id, '_trb_contract_number', true ) || '1061056' !== (string) get_post_meta( $release_id, '_trb_otp_dossier_id', true ) ) return;
	if ( 'signed' === get_post_meta( $release_id, '_trb_contract_state', true ) ) {
		if ( function_exists( 'trb_release_bridge_notify_spreadsheet_signed' ) && ( ! get_post_meta( $release_id, '_trb_contract_spreadsheet_synced_at', true ) || get_post_meta( $release_id, '_trb_contract_spreadsheet_error', true ) ) ) trb_release_bridge_notify_spreadsheet_signed( $release_id );
		return;
	}
	if ( 'contract_sent' !== get_post_meta( $release_id, '_trb_contract_state', true ) || ! function_exists( 'trb_release_bridge_apply_callback' ) ) return;
	trb_release_bridge_apply_callback( array(
		'release_id' => $release_id,
		'dossier_id' => '1061056',
		'status'     => 'completed',
		'signed_at'  => gmdate( DATE_ATOM ),
	) );
}
add_action( 'trb_owner_dashboard_reconcile_ruggia_contract', 'trb_owner_dashboard_reconcile_ruggia_contract', 10, 1 );

function trb_owner_dashboard_render() {
	global $wpdb;
	if ( ! current_user_can( TRB_OWNER_DASHBOARD_CAPABILITY ) ) wp_die( 'Accesso non consentito.' );
	$releases = trb_owner_dashboard_release_posts();
	$demos = trb_owner_dashboard_demo_posts();
	$artists = trb_owner_dashboard_artist_users();
	$selected_artist_id = isset( $_GET['artist_id'] ) ? absint( $_GET['artist_id'] ) : 0;
	$selected_artist = $selected_artist_id ? get_userdata( $selected_artist_id ) : false;
	$release_counts = array( 'total' => count( $releases ), 'approved' => 0, 'attention' => 0, 'signed' => 0 );
	foreach ( $releases as $release ) {
		$pipeline = sanitize_key( (string) get_post_meta( $release->ID, '_trb_release_pipeline_status', true ) );
		$contract = sanitize_key( (string) get_post_meta( $release->ID, '_trb_contract_state', true ) );
		if ( 'approved' === $pipeline ) $release_counts['approved']++;
		if ( 'approved' !== $pipeline || in_array( $contract, array( 'dispatch_error', 'preparing' ), true ) ) $release_counts['attention']++;
		if ( 'signed' === $contract ) $release_counts['signed']++;
	}
	$demo_counts = array();
	foreach ( $demos as $demo ) {
		$payload = (array) get_post_meta( $demo->ID, '_trb_demo_payload', true );
		$status = sanitize_key( (string) ( $payload['status'] ?? 'unknown' ) );
		$demo_counts[ $status ] = isset( $demo_counts[ $status ] ) ? $demo_counts[ $status ] + 1 : 1;
	}
	$resource_events = array();
	$notification_rows = array();
	if ( function_exists( 'trb_resource_tables' ) ) {
		$tables = trb_resource_tables();
		$resource_events = $wpdb->get_results( "SELECT resource,severity,message,occurrences,last_seen FROM {$tables['events']} WHERE status='open' ORDER BY FIELD(severity,'critical','warning','info'),last_seen DESC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$notification_rows = $wpdb->get_results( "SELECT recipient,subject,status,attempts,last_error,updated_at FROM {$tables['notifications']} ORDER BY id DESC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	?>
	<div class="wrap trb-owner-dashboard">
		<h1>Cruscotto operativo TRB</h1>
		<p>Panoramica in sola lettura · aggiornata <?php echo esc_html( wp_date( 'd/m/Y H:i:s' ) ); ?> · versione <?php echo esc_html( TRB_OWNER_DASHBOARD_VERSION ); ?> · <a href="<?php echo esc_url( TRB_OWNER_DASHBOARD_SHEET_URL ); ?>">Apri il foglio operativo</a></p>
		<style>
		.trb-owner-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:18px 0}.trb-owner-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}.trb-owner-card strong{display:block;font-size:28px;margin-top:4px}.trb-owner-section{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;margin:18px 0;overflow:auto}.trb-owner-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-weight:600;white-space:nowrap}.trb-owner-badge--ok{background:#d7f5df;color:#0b5d25}.trb-owner-badge--wait{background:#fff2c7;color:#6b4b00}.trb-owner-badge--bad{background:#ffd7d7;color:#8a1616}.trb-owner-dashboard table{width:100%;border-collapse:collapse}.trb-owner-dashboard th,.trb-owner-dashboard td{padding:8px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}.trb-owner-dashboard th{white-space:nowrap}.trb-owner-filter{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.trb-owner-filter label{display:grid;gap:4px}.trb-owner-muted{color:#646970}.trb-owner-error{color:#b32d2e;font-weight:600}.trb-owner-dashboard details{margin:8px 0}
		</style>
		<div class="trb-owner-grid">
			<div class="trb-owner-card">Artisti<strong><?php echo esc_html( count( $artists ) ); ?></strong></div>
			<div class="trb-owner-card">Release caricate<strong><?php echo esc_html( $release_counts['total'] ); ?></strong></div>
			<div class="trb-owner-card">Release da seguire<strong><?php echo esc_html( $release_counts['attention'] ); ?></strong></div>
			<div class="trb-owner-card">Contratti firmati<strong><?php echo esc_html( $release_counts['signed'] ); ?></strong></div>
			<div class="trb-owner-card">Provini ricevuti<strong><?php echo esc_html( count( $demos ) ); ?></strong></div>
			<div class="trb-owner-card">Provini processati<strong><?php echo esc_html( ( $demo_counts['sent'] ?? 0 ) + ( $demo_counts['ready'] ?? 0 ) ); ?></strong></div>
		</div>

		<div class="trb-owner-section">
			<h2>Artista</h2>
			<form method="get" class="trb-owner-filter"><input type="hidden" name="page" value="trb-owner-dashboard"><label>Seleziona artista<select name="artist_id"><option value="0">Tutti gli artisti</option><?php foreach ( $artists as $artist ) : $stage = function_exists( 'trb_portal_artist_profile_value' ) ? trb_portal_artist_profile_value( 'artist_name', $artist->ID ) : ''; ?><option value="<?php echo esc_attr( $artist->ID ); ?>" <?php selected( $selected_artist_id, $artist->ID ); ?>><?php echo esc_html( ( $stage ?: $artist->display_name ) . ' · ' . $artist->first_name . ' ' . $artist->last_name ); ?></option><?php endforeach; ?></select></label><button class="button button-primary">Apri panoramica</button></form>
			<?php if ( $selected_artist ) : ?>
				<h3><?php echo esc_html( ( function_exists( 'trb_portal_artist_profile_value' ) ? trb_portal_artist_profile_value( 'artist_name', $selected_artist->ID ) : '' ) ?: $selected_artist->display_name ); ?></h3>
				<table><tbody><tr><th>ID account</th><td><?php echo esc_html( $selected_artist->ID ); ?></td><th>Profilo</th><td><?php echo esc_html( function_exists( 'trb_portal_user_profile' ) ? trb_portal_user_profile( $selected_artist ) : implode( ', ', $selected_artist->roles ) ); ?></td></tr><tr><th>Nome e cognome</th><td><?php echo esc_html( trim( $selected_artist->first_name . ' ' . $selected_artist->last_name ) ); ?></td><th>Email</th><td><?php echo esc_html( $selected_artist->user_email ); ?></td></tr><tr><th>Contratto preliminare</th><td><?php echo esc_html( get_user_meta( $selected_artist->ID, '_trb_artist_preliminary_contract', true ) ?: '—' ); ?></td><th>Validità</th><td><?php echo esc_html( get_user_meta( $selected_artist->ID, '_trb_artist_contract_term', true ) ?: '—' ); ?></td></tr></tbody></table>
				<details><summary><strong>Dati anagrafici, fiscali, documento e contatti</strong></summary><table><tbody><?php foreach ( function_exists( 'trb_portal_artist_profile_fields' ) ? trb_portal_artist_profile_fields() : array() as $key => $label ) : $value = trb_portal_artist_profile_value( $key, $selected_artist->ID ); ?><tr><th><?php echo esc_html( $label ); ?></th><td><?php echo $value ? esc_html( $value ) : '<span class="trb-owner-muted">—</span>'; ?></td></tr><?php endforeach; ?></tbody></table></details>
			<?php endif; ?>
		</div>

		<div class="trb-owner-section"><h2>Release e contratti</h2><table><thead><tr><th>Pratica</th><th>Artista</th><th>Release</th><th>Pipeline</th><th>Contratto</th><th>Numero / dossier</th><th>Aggiornata</th><th></th></tr></thead><tbody>
		<?php foreach ( $releases as $release ) : if ( $selected_artist_id && (int) $release->post_author !== $selected_artist_id ) continue; $user = get_userdata( $release->post_author ); $pipeline = get_post_meta( $release->ID, '_trb_release_pipeline_status', true ); $contract = get_post_meta( $release->ID, '_trb_contract_state', true ); $contract_error = get_post_meta( $release->ID, '_trb_contract_error', true ); ?>
		<tr><td>#<?php echo esc_html( $release->ID ); ?></td><td><?php echo esc_html( $user ? ( trb_portal_artist_profile_value( 'artist_name', $user->ID ) ?: $user->display_name ) : '—' ); ?></td><td><strong><?php echo esc_html( $release->post_title ); ?></strong><br><span class="trb-owner-muted"><?php echo esc_html( get_post_meta( $release->ID, '_trb_release_state', true ) ); ?></span></td><td><?php echo wp_kses_post( trb_owner_dashboard_badge( $pipeline ) ); ?></td><td><?php echo wp_kses_post( trb_owner_dashboard_badge( $contract ) ); ?><?php if ( $contract_error ) : ?><br><span class="trb-owner-error"><?php echo esc_html( $contract_error ); ?></span><?php endif; ?></td><td><?php echo esc_html( get_post_meta( $release->ID, '_trb_contract_number', true ) ?: '—' ); ?><br><?php echo esc_html( get_post_meta( $release->ID, '_trb_otp_dossier_id', true ) ?: '—' ); ?></td><td><?php echo esc_html( get_post_modified_time( 'd/m/Y H:i', false, $release ) ); ?></td><td><a class="button" href="<?php echo esc_url( get_edit_post_link( $release->ID, 'url' ) ); ?>">Apri</a></td></tr>
		<?php endforeach; ?></tbody></table></div>

		<div class="trb-owner-section"><h2>Provini e valutazioni</h2><p><?php foreach ( $demo_counts as $status => $count ) echo wp_kses_post( trb_owner_dashboard_badge( $status ) ) . ' ' . esc_html( $count ) . ' &nbsp; '; ?></p><table><thead><tr><th>Richiesta</th><th>Artista</th><th>Brano</th><th>Stato</th><th>Analisi</th><th>Foglio</th><th>Errore</th></tr></thead><tbody>
		<?php foreach ( $demos as $demo ) : if ( $selected_artist_id && (int) $demo->post_author !== $selected_artist_id ) continue; $payload = (array) get_post_meta( $demo->ID, '_trb_demo_payload', true ); $review = get_post_meta( $demo->ID, '_trb_demo_review', true ); ?>
		<tr><td>#<?php echo esc_html( $demo->ID ); ?><br><span class="trb-owner-muted"><?php echo esc_html( ! empty( $payload['submitted_at'] ) ? wp_date( 'd/m/Y H:i', absint( $payload['submitted_at'] ) ) : get_post_time( 'd/m/Y H:i', false, $demo ) ); ?></span></td><td><?php echo esc_html( $payload['artist_name'] ?? '—' ); ?></td><td><?php echo esc_html( $payload['title'] ?? $demo->post_title ); ?></td><td><?php echo wp_kses_post( trb_owner_dashboard_badge( $payload['status'] ?? '' ) ); ?></td><td><?php echo esc_html( trb_owner_dashboard_demo_summary( $review ) ); ?></td><td><?php echo get_post_meta( $demo->ID, '_trb_demo_sheet_synced', true ) ? 'Sincronizzato' : 'In attesa'; ?></td><td><?php echo esc_html( get_post_meta( $demo->ID, '_trb_demo_last_error', true ) ?: '—' ); ?></td></tr>
		<?php endforeach; ?></tbody></table></div>

		<div class="trb-owner-section"><h2>Attività, anomalie e comunicazioni</h2><h3>Anomalie aperte</h3><table><thead><tr><th>Risorsa</th><th>Gravità</th><th>Messaggio</th><th>Occorrenze</th><th>Ultima rilevazione</th></tr></thead><tbody><?php if ( ! $resource_events ) : ?><tr><td colspan="5">Nessuna anomalia aperta.</td></tr><?php endif; ?><?php foreach ( $resource_events as $event ) : ?><tr><td><?php echo esc_html( $event['resource'] ); ?></td><td><?php echo esc_html( strtoupper( $event['severity'] ) ); ?></td><td><?php echo esc_html( $event['message'] ); ?></td><td><?php echo esc_html( $event['occurrences'] ); ?></td><td><?php echo esc_html( $event['last_seen'] ); ?></td></tr><?php endforeach; ?></tbody></table><h3>Ultime comunicazioni automatiche</h3><table><thead><tr><th>Destinatario</th><th>Oggetto</th><th>Stato</th><th>Tentativi</th><th>Errore</th><th>Aggiornata</th></tr></thead><tbody><?php if ( ! $notification_rows ) : ?><tr><td colspan="6">Nessuna comunicazione registrata.</td></tr><?php endif; ?><?php foreach ( $notification_rows as $notification ) : ?><tr><td><?php echo esc_html( $notification['recipient'] ); ?></td><td><?php echo esc_html( $notification['subject'] ); ?></td><td><?php echo wp_kses_post( trb_owner_dashboard_badge( $notification['status'] ) ); ?></td><td><?php echo esc_html( $notification['attempts'] ); ?></td><td><?php echo esc_html( $notification['last_error'] ?: '—' ); ?></td><td><?php echo esc_html( $notification['updated_at'] ); ?></td></tr><?php endforeach; ?></tbody></table></div>
	</div>
	<?php
}
