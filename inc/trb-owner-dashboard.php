<?php
/** Read-only operational dashboard reserved for the TRB owner account. */

if ( ! defined( 'ABSPATH' ) ) exit;

const TRB_OWNER_DASHBOARD_CAPABILITY = 'trb_view_owner_dashboard';
const TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY = 'trb_manage_owner_dashboard';
const TRB_OWNER_DASHBOARD_VERSION = '1.1.0';
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
	if ( $administrator && ! $administrator->has_cap( TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY ) ) $administrator->add_cap( TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY );
	$manager = get_role( 'trb_owner_manager' );
	if ( ! $manager ) {
		$manager = add_role( 'trb_owner_manager', 'Direzione TRB · operativa', array(
			'read'                                => true,
			TRB_OWNER_DASHBOARD_CAPABILITY        => true,
			TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY => true,
		) );
	}
	if ( $manager && ! $manager->has_cap( TRB_OWNER_DASHBOARD_CAPABILITY ) ) $manager->add_cap( TRB_OWNER_DASHBOARD_CAPABILITY );
	if ( $manager && ! $manager->has_cap( TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY ) ) $manager->add_cap( TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY );
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

function trb_owner_dashboard_release_posts( $include_trash = false ) {
	return get_posts( array(
		'post_type'      => 'trb_release',
		'post_status'    => $include_trash ? array( 'publish', 'private', 'pending', 'draft', 'trash' ) : array( 'publish', 'private', 'pending', 'draft' ),
		'posts_per_page' => 1000,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	) );
}

function trb_owner_dashboard_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'trb-owner-dashboard' ), $args ), admin_url( 'admin.php' ) );
}

function trb_owner_dashboard_release_attention( $release ) {
	$pipeline = sanitize_key( (string) get_post_meta( $release->ID, '_trb_release_pipeline_status', true ) );
	$contract = sanitize_key( (string) get_post_meta( $release->ID, '_trb_contract_state', true ) );
	return 'approved' !== $pipeline || in_array( $contract, array( 'dispatch_error', 'data_error', 'configuration_required', 'preparing' ), true ) || get_post_meta( $release->ID, '_trb_contract_spreadsheet_error', true );
}

function trb_owner_dashboard_release_search_text( $release ) {
	$user = get_userdata( $release->post_author );
	$tracks = (array) get_post_meta( $release->ID, '_trb_release_tracks', true );
	$parts = array( $release->ID, $release->post_title, $user ? $user->display_name : '', $user ? $user->user_email : '', get_post_meta( $release->ID, '_trb_contract_number', true ), get_post_meta( $release->ID, '_trb_otp_dossier_id', true ) );
	foreach ( $tracks as $track ) $parts[] = ( $track['title'] ?? '' ) . ' ' . ( $track['isrc'] ?? '' );
	return strtolower( remove_accents( implode( ' ', array_map( 'strval', $parts ) ) ) );
}

function trb_owner_dashboard_filter_releases( $releases, $artist_id, $query, $status ) {
	$query = strtolower( remove_accents( trim( (string) $query ) ) );
	return array_values( array_filter( $releases, static function( $release ) use ( $artist_id, $query, $status ) {
		if ( $artist_id && (int) $release->post_author !== (int) $artist_id ) return false;
		if ( $query && false === strpos( trb_owner_dashboard_release_search_text( $release ), $query ) ) return false;
		$pipeline = sanitize_key( (string) get_post_meta( $release->ID, '_trb_release_pipeline_status', true ) );
		$contract = sanitize_key( (string) get_post_meta( $release->ID, '_trb_contract_state', true ) );
		if ( 'attention' === $status && ! trb_owner_dashboard_release_attention( $release ) ) return false;
		if ( 'signed' === $status && 'signed' !== $contract ) return false;
		if ( 'waiting_signature' === $status && 'contract_sent' !== $contract ) return false;
		if ( 'approved' === $status && 'approved' !== $pipeline ) return false;
		if ( 'trash' === $status && 'trash' !== $release->post_status ) return false;
		return true;
	} ) );
}

function trb_owner_dashboard_trash_release() {
	if ( ! current_user_can( TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY ) ) wp_die( 'Non autorizzato.' );
	$release_id = absint( $_GET['release_id'] ?? 0 );
	check_admin_referer( 'trb_owner_dashboard_trash_' . $release_id );
	if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) ) wp_die( 'Pratica non valida.' );
	if ( ! wp_trash_post( $release_id ) ) wp_die( 'Impossibile spostare la pratica nel cestino.' );
	if ( function_exists( 'trb_resource_event' ) ) trb_resource_event( 'owner-trash-release-' . $release_id, 'portal', 'info', 'Pratica spostata nel cestino dalla Direzione TRB.', array( 'release_id' => $release_id, 'user_id' => get_current_user_id() ) );
	wp_safe_redirect( trb_owner_dashboard_url( array( 'view' => 'releases', 'trb_notice' => 'trashed' ) ) );
	exit;
}
add_action( 'admin_post_trb_owner_dashboard_trash_release', 'trb_owner_dashboard_trash_release' );

function trb_owner_dashboard_restore_release() {
	if ( ! current_user_can( TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY ) ) wp_die( 'Non autorizzato.' );
	$release_id = absint( $_GET['release_id'] ?? 0 );
	check_admin_referer( 'trb_owner_dashboard_restore_' . $release_id );
	if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) || 'trash' !== get_post_status( $release_id ) ) wp_die( 'Pratica non valida.' );
	if ( ! wp_untrash_post( $release_id ) ) wp_die( 'Impossibile ripristinare la pratica.' );
	if ( function_exists( 'trb_resource_event' ) ) trb_resource_event( 'owner-restore-release-' . $release_id, 'portal', 'info', 'Pratica ripristinata dal cestino dalla Direzione TRB.', array( 'release_id' => $release_id, 'user_id' => get_current_user_id() ) );
	wp_safe_redirect( trb_owner_dashboard_url( array( 'view' => 'releases', 'trb_notice' => 'restored' ) ) );
	exit;
}
add_action( 'admin_post_trb_owner_dashboard_restore_release', 'trb_owner_dashboard_restore_release' );

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
	$view = sanitize_key( (string) ( $_GET['view'] ?? 'overview' ) );
	if ( ! in_array( $view, array( 'overview', 'releases', 'artists', 'demos', 'activity' ), true ) ) $view = 'overview';
	$query = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
	$status_filter = sanitize_key( (string) ( $_GET['release_status'] ?? '' ) );
	$all_releases = trb_owner_dashboard_release_posts( true );
	$active_releases = array_values( array_filter( $all_releases, static function( $release ) { return 'trash' !== $release->post_status; } ) );
	$demos = trb_owner_dashboard_demo_posts();
	$artists = trb_owner_dashboard_artist_users();
	$selected_artist_id = isset( $_GET['artist_id'] ) ? absint( $_GET['artist_id'] ) : 0;
	$selected_artist = $selected_artist_id ? get_userdata( $selected_artist_id ) : false;
	$selected_release_id = isset( $_GET['release_id'] ) ? absint( $_GET['release_id'] ) : 0;
	$selected_release = $selected_release_id ? get_post( $selected_release_id ) : false;
	if ( $selected_release && 'trb_release' !== $selected_release->post_type ) $selected_release = false;
	$releases = trb_owner_dashboard_filter_releases( $status_filter === 'trash' ? $all_releases : $active_releases, $selected_artist_id, $query, $status_filter );
	$release_counts = array( 'total' => count( $active_releases ), 'approved' => 0, 'attention' => 0, 'signed' => 0, 'trash' => count( $all_releases ) - count( $active_releases ) );
	foreach ( $active_releases as $release ) {
		$pipeline = sanitize_key( (string) get_post_meta( $release->ID, '_trb_release_pipeline_status', true ) );
		$contract = sanitize_key( (string) get_post_meta( $release->ID, '_trb_contract_state', true ) );
		if ( 'approved' === $pipeline ) $release_counts['approved']++;
		if ( trb_owner_dashboard_release_attention( $release ) ) $release_counts['attention']++;
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
		<div class="trb-owner-heading"><div><h1>TRB Control Room</h1><p>Operatività aggiornata <?php echo esc_html( wp_date( 'd/m/Y H:i:s' ) ); ?> · versione <?php echo esc_html( TRB_OWNER_DASHBOARD_VERSION ); ?></p></div><div><a class="button" href="<?php echo esc_url( TRB_OWNER_DASHBOARD_SHEET_URL ); ?>">Foglio operativo</a> <a class="button" href="<?php echo esc_url( trb_owner_dashboard_url() ); ?>">Aggiorna dati</a></div></div>
		<?php if ( 'trashed' === ( $_GET['trb_notice'] ?? '' ) ) : ?><div class="notice notice-success"><p>Release spostata nel cestino. Puoi ripristinarla dalla vista Cestino.</p></div><?php endif; ?>
		<?php if ( 'restored' === ( $_GET['trb_notice'] ?? '' ) ) : ?><div class="notice notice-success"><p>Release ripristinata correttamente.</p></div><?php endif; ?>
		<style>
		.trb-owner-dashboard{max-width:1600px}.trb-owner-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:18px 0}.trb-owner-heading h1{font-size:30px;margin:0}.trb-owner-heading p{margin:6px 0 0;color:#646970}.trb-owner-nav{display:flex;gap:6px;flex-wrap:wrap;padding:6px;background:#fff;border:1px solid #dcdcde;border-radius:10px;position:sticky;top:32px;z-index:20}.trb-owner-nav a{padding:9px 14px;border-radius:7px;text-decoration:none;font-weight:600;color:#1d2327}.trb-owner-nav a.is-active{background:#1d2327;color:#fff}.trb-owner-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px;margin:18px 0}.trb-owner-card{display:block;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px;text-decoration:none;color:#1d2327;box-shadow:0 1px 2px rgba(0,0,0,.04)}.trb-owner-card:hover{border-color:#2271b1}.trb-owner-card strong{display:block;font-size:30px;margin-top:4px}.trb-owner-section{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;margin:18px 0;overflow:auto}.trb-owner-section h2{margin-top:0}.trb-owner-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-weight:600;white-space:nowrap}.trb-owner-badge--ok{background:#d7f5df;color:#0b5d25}.trb-owner-badge--wait{background:#fff2c7;color:#6b4b00}.trb-owner-badge--bad{background:#ffd7d7;color:#8a1616}.trb-owner-dashboard table{width:100%;border-collapse:collapse}.trb-owner-dashboard th,.trb-owner-dashboard td{padding:9px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}.trb-owner-dashboard th{white-space:nowrap}.trb-owner-dashboard tbody tr:hover{background:#f6f7f7}.trb-owner-filter{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin:12px 0 18px;padding:12px;background:#f6f7f7;border-radius:8px}.trb-owner-filter label{display:grid;gap:4px;min-width:160px}.trb-owner-filter input[type=search]{min-width:280px}.trb-owner-muted{color:#646970}.trb-owner-error{color:#b32d2e;font-weight:600}.trb-owner-dashboard details{margin:8px 0}.trb-owner-actions{display:flex;gap:6px;flex-wrap:wrap}.trb-owner-detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}.trb-owner-detail{padding:14px;background:#f6f7f7;border-radius:8px}.trb-owner-detail strong{display:block;margin-bottom:5px}.trb-owner-priority{border-left:4px solid #d63638}.trb-owner-empty{text-align:center;padding:30px;color:#646970}@media(max-width:782px){.trb-owner-heading{align-items:flex-start;flex-direction:column}.trb-owner-nav{top:46px}.trb-owner-filter input[type=search]{min-width:100%}}
		</style>
		<nav class="trb-owner-nav" aria-label="Sezioni cruscotto"><?php foreach ( array( 'overview' => 'Panoramica', 'releases' => 'Release', 'artists' => 'Artisti', 'demos' => 'Provini', 'activity' => 'Attività ed email' ) as $nav_key => $nav_label ) : ?><a class="<?php echo $view === $nav_key ? 'is-active' : ''; ?>" href="<?php echo esc_url( trb_owner_dashboard_url( array( 'view' => $nav_key ) ) ); ?>"><?php echo esc_html( $nav_label ); ?></a><?php endforeach; ?></nav>
		<div class="trb-owner-grid">
			<a class="trb-owner-card" href="<?php echo esc_url( trb_owner_dashboard_url( array( 'view' => 'artists' ) ) ); ?>">Artisti<strong><?php echo esc_html( count( $artists ) ); ?></strong></a>
			<a class="trb-owner-card" href="<?php echo esc_url( trb_owner_dashboard_url( array( 'view' => 'releases' ) ) ); ?>">Release attive<strong><?php echo esc_html( $release_counts['total'] ); ?></strong></a>
			<a class="trb-owner-card trb-owner-priority" href="<?php echo esc_url( trb_owner_dashboard_url( array( 'view' => 'releases', 'release_status' => 'attention' ) ) ); ?>">Da seguire ora<strong><?php echo esc_html( $release_counts['attention'] ); ?></strong></a>
			<a class="trb-owner-card" href="<?php echo esc_url( trb_owner_dashboard_url( array( 'view' => 'releases', 'release_status' => 'signed' ) ) ); ?>">Contratti firmati<strong><?php echo esc_html( $release_counts['signed'] ); ?></strong></a>
			<a class="trb-owner-card" href="<?php echo esc_url( trb_owner_dashboard_url( array( 'view' => 'demos' ) ) ); ?>">Provini ricevuti<strong><?php echo esc_html( count( $demos ) ); ?></strong></a>
			<a class="trb-owner-card" href="<?php echo esc_url( trb_owner_dashboard_url( array( 'view' => 'releases', 'release_status' => 'trash' ) ) ); ?>">Cestino release<strong><?php echo esc_html( $release_counts['trash'] ); ?></strong></a>
		</div>

		<?php if ( in_array( $view, array( 'overview', 'artists' ), true ) || $selected_artist ) : ?><div class="trb-owner-section">
			<h2>Artista</h2>
			<form method="get" class="trb-owner-filter"><input type="hidden" name="page" value="trb-owner-dashboard"><input type="hidden" name="view" value="artists"><label>Seleziona artista<select name="artist_id"><option value="0">Tutti gli artisti</option><?php foreach ( $artists as $artist ) : $stage = function_exists( 'trb_portal_artist_profile_value' ) ? trb_portal_artist_profile_value( 'artist_name', $artist->ID ) : ''; ?><option value="<?php echo esc_attr( $artist->ID ); ?>" <?php selected( $selected_artist_id, $artist->ID ); ?>><?php echo esc_html( ( $stage ?: $artist->display_name ) . ' · ' . $artist->first_name . ' ' . $artist->last_name ); ?></option><?php endforeach; ?></select></label><button class="button button-primary">Apri panoramica</button></form>
			<?php if ( $selected_artist ) : ?>
				<h3><?php echo esc_html( ( function_exists( 'trb_portal_artist_profile_value' ) ? trb_portal_artist_profile_value( 'artist_name', $selected_artist->ID ) : '' ) ?: $selected_artist->display_name ); ?></h3>
				<table><tbody><tr><th>ID account</th><td><?php echo esc_html( $selected_artist->ID ); ?></td><th>Profilo</th><td><?php echo esc_html( function_exists( 'trb_portal_user_profile' ) ? trb_portal_user_profile( $selected_artist ) : implode( ', ', $selected_artist->roles ) ); ?></td></tr><tr><th>Nome e cognome</th><td><?php echo esc_html( trim( $selected_artist->first_name . ' ' . $selected_artist->last_name ) ); ?></td><th>Email</th><td><?php echo esc_html( $selected_artist->user_email ); ?></td></tr><tr><th>Contratto preliminare</th><td><?php echo esc_html( get_user_meta( $selected_artist->ID, '_trb_artist_preliminary_contract', true ) ?: '—' ); ?></td><th>Validità</th><td><?php echo esc_html( get_user_meta( $selected_artist->ID, '_trb_artist_contract_term', true ) ?: '—' ); ?></td></tr></tbody></table>
				<details><summary><strong>Dati anagrafici, fiscali, documento e contatti</strong></summary><table><tbody><?php foreach ( function_exists( 'trb_portal_artist_profile_fields' ) ? trb_portal_artist_profile_fields() : array() as $key => $label ) : $value = trb_portal_artist_profile_value( $key, $selected_artist->ID ); ?><tr><th><?php echo esc_html( $label ); ?></th><td><?php echo $value ? esc_html( $value ) : '<span class="trb-owner-muted">—</span>'; ?></td></tr><?php endforeach; ?></tbody></table></details>
			<?php endif; ?>
		</div><?php endif; ?>

		<?php if ( in_array( $view, array( 'overview', 'releases' ), true ) || $selected_release ) : ?><div class="trb-owner-section"><h2><?php echo 'overview' === $view ? 'Release prioritarie e recenti' : 'Release e contratti'; ?></h2>
		<form method="get" class="trb-owner-filter"><input type="hidden" name="page" value="trb-owner-dashboard"><input type="hidden" name="view" value="releases"><label>Cerca pratica, artista, ISRC, contratto<input type="search" name="q" value="<?php echo esc_attr( $query ); ?>" placeholder="Es. Ruggia, 12283, ITV…"></label><label>Stato<select name="release_status"><option value="">Tutte le release</option><option value="attention" <?php selected( $status_filter, 'attention' ); ?>>Da seguire</option><option value="approved" <?php selected( $status_filter, 'approved' ); ?>>Copyright approvato</option><option value="waiting_signature" <?php selected( $status_filter, 'waiting_signature' ); ?>>Firma in attesa</option><option value="signed" <?php selected( $status_filter, 'signed' ); ?>>Firmate</option><option value="trash" <?php selected( $status_filter, 'trash' ); ?>>Cestino</option></select></label><label>Artista<select name="artist_id"><option value="0">Tutti</option><?php foreach ( $artists as $artist ) : ?><option value="<?php echo esc_attr( $artist->ID ); ?>" <?php selected( $selected_artist_id, $artist->ID ); ?>><?php echo esc_html( trb_portal_artist_profile_value( 'artist_name', $artist->ID ) ?: $artist->display_name ); ?></option><?php endforeach; ?></select></label><button class="button button-primary">Applica filtri</button><a class="button" href="<?php echo esc_url( trb_owner_dashboard_url( array( 'view' => 'releases' ) ) ); ?>">Azzera</a></form>
		<?php if ( $selected_release ) : $detail_user = get_userdata( $selected_release->post_author ); $detail_tracks = (array) get_post_meta( $selected_release->ID, '_trb_release_tracks', true ); $detail_decision = (array) get_post_meta( $selected_release->ID, '_trb_release_analysis_decision', true ); $detail_technical = (array) get_post_meta( $selected_release->ID, '_trb_release_technical_analysis', true ); $detail_files = (array) get_post_meta( $selected_release->ID, '_trb_release_files', true ); ?>
		<div class="trb-owner-detail-grid"><div class="trb-owner-detail"><strong>Pratica e artista</strong>#<?php echo esc_html( $selected_release->ID ); ?> · <?php echo esc_html( $selected_release->post_title ); ?><br><?php echo esc_html( $detail_user ? ( trb_portal_artist_profile_value( 'artist_name', $detail_user->ID ) ?: $detail_user->display_name ) : '—' ); ?></div><div class="trb-owner-detail"><strong>Pubblicazione</strong><?php echo esc_html( trb_owner_dashboard_status_label( get_post_meta( $selected_release->ID, '_trb_release_pipeline_status', true ) ) ); ?><br><?php echo esc_html( get_post_meta( $selected_release->ID, '_trb_release_date', true ) ?: get_post_meta( $selected_release->ID, '_trb_release_original_date', true ) ?: 'Data non disponibile' ); ?></div><div class="trb-owner-detail"><strong>Copyright e tecnica</strong><?php echo esc_html( strtoupper( (string) ( $detail_decision['semaphore'] ?? '—' ) ) ); ?> · <?php echo esc_html( (string) ( $detail_decision['state'] ?? '—' ) ); ?><br><?php echo esc_html( (string) ( $detail_technical['status'] ?? '—' ) ); ?></div><div class="trb-owner-detail"><strong>Contratto</strong><?php echo esc_html( get_post_meta( $selected_release->ID, '_trb_contract_number', true ) ?: '—' ); ?> · dossier <?php echo esc_html( get_post_meta( $selected_release->ID, '_trb_otp_dossier_id', true ) ?: '—' ); ?><br><?php echo esc_html( trb_owner_dashboard_status_label( get_post_meta( $selected_release->ID, '_trb_contract_state', true ) ) ); ?></div></div>
		<h3>Brani, ISRC e analisi</h3><table><thead><tr><th>#</th><th>Titolo</th><th>Versione</th><th>ISRC</th><th>Durata</th><th>Diritti</th></tr></thead><tbody><?php foreach ( $detail_tracks as $track_index => $track ) : ?><tr><td><?php echo esc_html( $track_index + 1 ); ?></td><td><?php echo esc_html( $track['title'] ?? '—' ); ?></td><td><?php echo esc_html( $track['version'] ?? '—' ); ?></td><td><?php echo esc_html( $track['isrc'] ?? '—' ); ?></td><td><?php echo esc_html( $track['duration'] ?? '—' ); ?></td><td><?php echo esc_html( $track['content_nature'] ?? $track['rights_basis'] ?? '—' ); ?></td></tr><?php endforeach; ?></tbody></table>
		<details><summary><strong>Dettagli tecnici, copyright e file (<?php echo esc_html( count( $detail_files ) ); ?>)</strong></summary><pre><?php echo esc_html( wp_json_encode( array( 'decisione' => $detail_decision, 'tecnica' => $detail_technical, 'file' => $detail_files ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></details>
		<?php endif; ?>
		<table><thead><tr><th>Pratica</th><th>Artista / release</th><th>Uscita</th><th>Copyright</th><th>Pipeline</th><th>Contratto</th><th>Foglio</th><th>Azioni</th></tr></thead><tbody>
		<?php if ( ! $releases ) : ?><tr><td colspan="8" class="trb-owner-empty">Nessuna release corrisponde ai filtri.</td></tr><?php endif; ?>
		<?php foreach ( ( 'overview' === $view && ! $query && ! $status_filter ? array_slice( $releases, 0, 15 ) : $releases ) as $release ) : $user = get_userdata( $release->post_author ); $pipeline = get_post_meta( $release->ID, '_trb_release_pipeline_status', true ); $contract = get_post_meta( $release->ID, '_trb_contract_state', true ); $contract_error = get_post_meta( $release->ID, '_trb_contract_error', true ); $decision = (array) get_post_meta( $release->ID, '_trb_release_analysis_decision', true ); $sheet_error = get_post_meta( $release->ID, '_trb_contract_spreadsheet_error', true ); $sheet_at = get_post_meta( $release->ID, '_trb_contract_spreadsheet_synced_at', true ); ?>
		<tr><td>#<?php echo esc_html( $release->ID ); ?><br><span class="trb-owner-muted"><?php echo esc_html( get_post_modified_time( 'd/m/Y H:i', false, $release ) ); ?></span></td><td><?php echo esc_html( $user ? ( trb_portal_artist_profile_value( 'artist_name', $user->ID ) ?: $user->display_name ) : '—' ); ?><br><strong><?php echo esc_html( $release->post_title ); ?></strong></td><td><?php echo esc_html( get_post_meta( $release->ID, '_trb_release_date', true ) ?: get_post_meta( $release->ID, '_trb_release_original_date', true ) ?: '—' ); ?></td><td><?php echo esc_html( strtoupper( (string) ( $decision['semaphore'] ?? '—' ) ) ); ?><br><span class="trb-owner-muted"><?php echo esc_html( $decision['state'] ?? '—' ); ?></span></td><td><?php echo wp_kses_post( trb_owner_dashboard_badge( $pipeline ) ); ?></td><td><?php echo wp_kses_post( trb_owner_dashboard_badge( $contract ) ); ?><br><?php echo esc_html( get_post_meta( $release->ID, '_trb_contract_number', true ) ?: '—' ); ?><?php if ( $contract_error ) : ?><br><span class="trb-owner-error"><?php echo esc_html( $contract_error ); ?></span><?php endif; ?></td><td><?php if ( $sheet_error ) : ?><span class="trb-owner-error"><?php echo esc_html( $sheet_error ); ?></span><?php elseif ( $sheet_at ) : ?>Sincronizzato<br><span class="trb-owner-muted"><?php echo esc_html( $sheet_at ); ?></span><?php else : ?>In attesa<?php endif; ?></td><td><div class="trb-owner-actions"><a class="button" href="<?php echo esc_url( trb_owner_dashboard_url( array( 'view' => 'releases', 'release_id' => $release->ID ) ) ); ?>">Dettagli</a><?php if ( current_user_can( 'manage_options' ) && get_edit_post_link( $release->ID, 'url' ) ) : ?><a class="button" href="<?php echo esc_url( get_edit_post_link( $release->ID, 'url' ) ); ?>">WordPress</a><?php endif; ?><?php if ( current_user_can( TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY ) && 'trash' !== $release->post_status ) : ?><a class="button" style="color:#b32d2e" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_owner_dashboard_trash_release&release_id=' . $release->ID ), 'trb_owner_dashboard_trash_' . $release->ID ) ); ?>" onclick="return confirm('Spostare nel cestino la pratica #<?php echo esc_js( $release->ID ); ?> — <?php echo esc_js( $release->post_title ); ?>? Potrai ripristinarla.')">Cestino</a><?php elseif ( current_user_can( TRB_OWNER_DASHBOARD_MANAGE_CAPABILITY ) ) : ?><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_owner_dashboard_restore_release&release_id=' . $release->ID ), 'trb_owner_dashboard_restore_' . $release->ID ) ); ?>">Ripristina</a><?php endif; ?></div></td></tr>
		<?php endforeach; ?></tbody></table></div><?php endif; ?>

		<?php if ( in_array( $view, array( 'overview', 'demos' ), true ) ) : ?><div class="trb-owner-section"><h2>Provini e valutazioni</h2><p><?php foreach ( $demo_counts as $status => $count ) echo wp_kses_post( trb_owner_dashboard_badge( $status ) ) . ' ' . esc_html( $count ) . ' &nbsp; '; ?></p><table><thead><tr><th>Richiesta</th><th>Artista</th><th>Brano</th><th>Stato</th><th>Analisi</th><th>Foglio</th><th>Errore</th></tr></thead><tbody>
		<?php foreach ( $demos as $demo ) : if ( $selected_artist_id && (int) $demo->post_author !== $selected_artist_id ) continue; $payload = (array) get_post_meta( $demo->ID, '_trb_demo_payload', true ); $review = get_post_meta( $demo->ID, '_trb_demo_review', true ); ?>
		<tr><td>#<?php echo esc_html( $demo->ID ); ?><br><span class="trb-owner-muted"><?php echo esc_html( ! empty( $payload['submitted_at'] ) ? wp_date( 'd/m/Y H:i', absint( $payload['submitted_at'] ) ) : get_post_time( 'd/m/Y H:i', false, $demo ) ); ?></span></td><td><?php echo esc_html( $payload['artist_name'] ?? '—' ); ?></td><td><?php echo esc_html( $payload['title'] ?? $demo->post_title ); ?></td><td><?php echo wp_kses_post( trb_owner_dashboard_badge( $payload['status'] ?? '' ) ); ?></td><td><?php echo esc_html( trb_owner_dashboard_demo_summary( $review ) ); ?></td><td><?php echo get_post_meta( $demo->ID, '_trb_demo_sheet_synced', true ) ? 'Sincronizzato' : 'In attesa'; ?></td><td><?php echo esc_html( get_post_meta( $demo->ID, '_trb_demo_last_error', true ) ?: '—' ); ?></td></tr>
		<?php endforeach; ?></tbody></table></div><?php endif; ?>

		<?php if ( in_array( $view, array( 'overview', 'activity' ), true ) ) : ?><div class="trb-owner-section"><h2>Attività, anomalie e comunicazioni</h2><h3>Anomalie aperte</h3><table><thead><tr><th>Risorsa</th><th>Gravità</th><th>Messaggio</th><th>Occorrenze</th><th>Ultima rilevazione</th></tr></thead><tbody><?php if ( ! $resource_events ) : ?><tr><td colspan="5">Nessuna anomalia aperta.</td></tr><?php endif; ?><?php foreach ( $resource_events as $event ) : ?><tr><td><?php echo esc_html( $event['resource'] ); ?></td><td><?php echo esc_html( strtoupper( $event['severity'] ) ); ?></td><td><?php echo esc_html( $event['message'] ); ?></td><td><?php echo esc_html( $event['occurrences'] ); ?></td><td><?php echo esc_html( $event['last_seen'] ); ?></td></tr><?php endforeach; ?></tbody></table><h3>Ultime comunicazioni automatiche</h3><table><thead><tr><th>Destinatario</th><th>Oggetto</th><th>Stato</th><th>Tentativi</th><th>Errore</th><th>Aggiornata</th></tr></thead><tbody><?php if ( ! $notification_rows ) : ?><tr><td colspan="6">Nessuna comunicazione registrata.</td></tr><?php endif; ?><?php foreach ( $notification_rows as $notification ) : ?><tr><td><?php echo esc_html( $notification['recipient'] ); ?></td><td><?php echo esc_html( $notification['subject'] ); ?></td><td><?php echo wp_kses_post( trb_owner_dashboard_badge( $notification['status'] ) ); ?></td><td><?php echo esc_html( $notification['attempts'] ); ?></td><td><?php echo esc_html( $notification['last_error'] ?: '—' ); ?></td><td><?php echo esc_html( $notification['updated_at'] ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
	</div>
	<?php
}
