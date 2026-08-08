<?php
/**
 * Bridge between artist.trbrec.com and the existing DDB/TRB Apps Script factories.
 * Contract generation and OTPService remain exclusively inside Apps Script.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const TRB_RELEASE_BRIDGE_OPTION = 'trb_release_bridge_settings';

/** Recover the highest portal-assigned sequence if an annual counter is missing. */
function trb_release_bridge_recover_isrc_seed( $year, $is_trb, $fallback ) {
    global $wpdb;

    $prefix = 'ITV24' . sprintf( '%02d', absint( $year ) );
    $values = $wpdb->get_col( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_trb_release_tracks' AND meta_value LIKE %s",
        '%' . $wpdb->esc_like( $prefix ) . '%'
    ) );
    $highest = absint( $fallback );
    foreach ( $values as $value ) {
        $tracks = maybe_unserialize( $value );
        if ( ! is_array( $tracks ) ) continue;
        foreach ( $tracks as $track ) {
            $code = isset( $track['isrc'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $track['isrc'] ) ) : '';
            if ( ! preg_match( '/^' . preg_quote( $prefix, '/' ) . '([0-9]{5})$/', $code, $match ) ) continue;
            $sequence = absint( $match[1] );
            if ( ( $is_trb && $sequence >= 1 && $sequence <= 9999 ) || ( ! $is_trb && $sequence >= 10000 && $sequence <= 99999 ) ) $highest = max( $highest, $sequence );
        }
    }
    return $highest;
}

/**
 * Reserve a consecutive block of TRB rec ISRCs with one atomic database write.
 * TRB releases use 00001–09999 (2026 starts at 00005); DDS, DDB12, DDB and
 * DDB-TRB use 10000–99999. Each year has independent counters and receives the
 * current two-digit year in the ISRC itself.
 */
function trb_release_bridge_allocate_isrcs( $quantity, $profile = 'trb' ) {
    global $wpdb;

    $quantity = absint( $quantity );
    if ( $quantity < 1 || $quantity > 60 ) return new WP_Error( 'invalid_isrc_quantity', 'Numero di ISRC da assegnare non valido.' );

    $profile = sanitize_key( $profile );
    if ( ! in_array( $profile, array( 'dds', 'ddb12', 'ddb', 'ddb_trb', 'trb' ), true ) ) return new WP_Error( 'invalid_isrc_profile', 'Gruppo contrattuale non valido per l’assegnazione ISRC.' );

    $year = absint( wp_date( 'y' ) );
    $is_trb = 'trb' === $profile;
    $seed = $is_trb ? ( 26 === $year ? 4 : 0 ) : 9999;
    $range_start = $is_trb ? 1 : 10000;
    $range_end = $is_trb ? 9999 : 99999;
    // Preserve the existing TRB counter name so already allocated codes remain reserved.
    $option_name = $is_trb ? 'trb_release_isrc_sequence_' . sprintf( '%02d', $year ) : 'trb_release_isrc_distribution_sequence_' . sprintf( '%02d', $year );
    $counter_exists = null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option_name ) );
    if ( ! $counter_exists ) $seed = trb_release_bridge_recover_isrc_seed( $year, $is_trb, $seed );
    // Create the annual counter at its seed, then increment it in a dedicated
    // atomic UPDATE. Keeping LAST_INSERT_ID() out of the INSERT avoids MySQL
    // returning wp_options.option_id during the very first allocation.
    $created = $wpdb->query( $wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no')",
        $option_name,
        $seed
    ) );
    if ( false === $created ) return new WP_Error( 'isrc_reservation_failed', 'Il sistema non è riuscito a inizializzare il registro ISRC.' );

    $updated = $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->options}
         SET option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + %d)
         WHERE option_name = %s AND CAST(option_value AS UNSIGNED) BETWEEN %d AND %d",
        $quantity,
        $option_name,
        $seed,
        $range_end - $quantity
    ) );
    if ( 1 !== $updated ) return new WP_Error( 'isrc_sequence_exhausted', 'La sequenza ISRC disponibile non è valida o risulta esaurita.' );

    $end = absint( $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ) );
    $start = $end - $quantity + 1;
    if ( $start < $range_start || $end > $range_end ) return new WP_Error( 'isrc_sequence_exhausted', 'La sequenza ISRC disponibile non è valida o risulta esaurita.' );

    wp_cache_delete( $option_name, 'options' );
    $prefix = 'ITV24' . sprintf( '%02d', $year );
    $codes = array();
    for ( $sequence = $start; $sequence <= $end; $sequence++ ) {
        $codes[] = $prefix . sprintf( '%05d', $sequence );
    }
    return $codes;
}

function trb_release_bridge_settings() {
    $defaults = array('ddb_webapp_url'=>'','trb_webapp_url'=>'','shared_secret'=>'');
    $saved = get_option( TRB_RELEASE_BRIDGE_OPTION, array() );
    $saved = is_array( $saved ) ? $saved : array();
    if ( defined( 'TRB_DDB_WEBAPP_URL' ) && empty( $saved['ddb_webapp_url'] ) ) $saved['ddb_webapp_url'] = TRB_DDB_WEBAPP_URL;
    if ( defined( 'TRB_TRB_WEBAPP_URL' ) && empty( $saved['trb_webapp_url'] ) ) $saved['trb_webapp_url'] = TRB_TRB_WEBAPP_URL;
    if ( defined( 'TRB_RELEASE_BRIDGE_SECRET' ) && empty( $saved['shared_secret'] ) ) $saved['shared_secret'] = TRB_RELEASE_BRIDGE_SECRET;
    return wp_parse_args( $saved, $defaults );
}

function trb_release_bridge_admin_menu() {
    add_options_page('Collegamento contratti release','Contratti release','manage_options','trb-release-bridge','trb_release_bridge_settings_page');
}
add_action( 'admin_menu', 'trb_release_bridge_admin_menu' );

function trb_release_bridge_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( isset( $_POST['trb_release_bridge_save'] ) ) {
        check_admin_referer( 'trb_release_bridge_save' );
        update_option( TRB_RELEASE_BRIDGE_OPTION, array(
            'ddb_webapp_url' => esc_url_raw( wp_unslash( $_POST['ddb_webapp_url'] ?? '' ) ),
            'trb_webapp_url' => esc_url_raw( wp_unslash( $_POST['trb_webapp_url'] ?? '' ) ),
            'shared_secret'  => sanitize_text_field( wp_unslash( $_POST['shared_secret'] ?? '' ) ),
        ), false );
        echo '<div class="notice notice-success"><p>Impostazioni salvate.</p></div>';
    }
    $s = trb_release_bridge_settings();
    ?>
    <div class="wrap"><h1>Collegamento contratti release</h1>
    <p>Inserire gli URL delle Web App pubblicate dai due progetti Apps Script. OTPService resta gestito dagli script dei fogli.</p>
    <form method="post"><?php wp_nonce_field( 'trb_release_bridge_save' ); ?>
    <table class="form-table"><tbody>
    <tr><th><label for="ddb_webapp_url">Web App DDB</label></th><td><input class="large-text" id="ddb_webapp_url" name="ddb_webapp_url" value="<?php echo esc_attr( $s['ddb_webapp_url'] ); ?>"></td></tr>
    <tr><th><label for="trb_webapp_url">Web App TRB</label></th><td><input class="large-text" id="trb_webapp_url" name="trb_webapp_url" value="<?php echo esc_attr( $s['trb_webapp_url'] ); ?>"></td></tr>
    <tr><th><label for="shared_secret">Segreto condiviso</label></th><td><input class="regular-text" id="shared_secret" name="shared_secret" value="<?php echo esc_attr( $s['shared_secret'] ); ?>"><p class="description">Usare lo stesso valore nella proprietà PORTAL_SHARED_SECRET di entrambi i progetti Apps Script.</p></td></tr>
    </tbody></table><p><button class="button button-primary" name="trb_release_bridge_save" value="1">Salva</button></p></form></div>
    <?php
}

function trb_release_bridge_capture_isrc() {
    if ( ! is_user_logged_in() ) return;
    if ( empty( $_POST['trb_portal_release_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trb_portal_release_nonce'] ) ), 'trb_portal_start_release' ) ) return;
    $state = sanitize_key( wp_unslash( $_POST['trb_release_state'] ?? '' ) );
    $codes = array();
    if ( 'previously_released' === $state ) {
        foreach ( (array) wp_unslash( $_POST['trb_existing_isrc'] ?? array() ) as $index => $value ) {
            $value = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $value ) );
            if ( preg_match( '/^[A-Z]{2}[A-Z0-9]{3}[0-9]{7}$/', $value ) ) $codes[ absint( $index ) ] = $value;
        }
    }
    set_transient( 'trb_release_bridge_isrc_' . get_current_user_id(), $codes, 2 * HOUR_IN_SECONDS );
}
add_action( 'admin_post_trb_portal_start_release', 'trb_release_bridge_capture_isrc', 1 );
add_action( 'wp_ajax_trb_portal_start_release', 'trb_release_bridge_capture_isrc', 1 );

function trb_release_bridge_meta_written( $meta_id, $post_id, $meta_key, $meta_value ) {
    if ( '_trb_release_files' !== $meta_key || 'trb_release' !== get_post_type( $post_id ) ) return;
    if ( ! get_post_meta( $post_id, '_trb_contract_state', true ) ) update_post_meta( $post_id, '_trb_contract_state', 'waiting_analysis' );
}
add_action( 'added_post_meta', 'trb_release_bridge_meta_written', 10, 4 );
add_action( 'updated_post_meta', 'trb_release_bridge_meta_written', 10, 4 );

function trb_release_bridge_queue_dispatch( $release_id ) {
    $release_id = absint( $release_id );
    if ( 'trb_release' !== get_post_type( $release_id ) || 'approved' !== get_post_meta( $release_id, '_trb_release_pipeline_status', true ) ) return;
    $current = get_post_meta( $release_id, '_trb_contract_state', true );
    if ( in_array( $current, array( 'contract_sent', 'signed' ), true ) ) return;
    update_post_meta( $release_id, '_trb_contract_state', 'preparing' );
    if ( ! wp_next_scheduled( 'trb_release_bridge_dispatch', array( $release_id ) ) ) wp_schedule_single_event( time() + 10, 'trb_release_bridge_dispatch', array( $release_id ) );
}
add_action( 'trb_release_analysis_approved', 'trb_release_bridge_queue_dispatch', 10, 1 );

/** Keep contract handoff diagnostics visible on every release admin screen. */
function trb_release_bridge_add_meta_box() {
    add_meta_box( 'trb-release-contract-diagnostics', 'Stato contratto e spreadsheet', 'trb_release_bridge_render_meta_box', 'trb_release', 'normal', 'high' );
}
add_action( 'add_meta_boxes_trb_release', 'trb_release_bridge_add_meta_box' );

function trb_release_bridge_render_meta_box( $post ) {
	$technical = (array) get_post_meta( $post->ID, '_trb_release_technical_analysis', true );
	$decision  = (array) get_post_meta( $post->ID, '_trb_release_analysis_decision', true );
	$technical_summary = isset( $technical['status'] ) ? (string) $technical['status'] : '';
	if ( ! empty( $technical['errors'] ) ) $technical_summary .= ' · errori: ' . implode( ', ', array_map( 'sanitize_text_field', (array) $technical['errors'] ) );
	if ( ! empty( $technical['warnings'] ) ) $technical_summary .= ' · avvisi: ' . implode( ', ', array_map( 'sanitize_text_field', (array) $technical['warnings'] ) );
	$decision_summary = isset( $decision['semaphore'] ) ? (string) $decision['semaphore'] : '';
	if ( ! empty( $decision['state'] ) ) $decision_summary .= ' · ' . (string) $decision['state'];
	$limitations = array();
	foreach ( (array) ( $decision['limitations'] ?? array() ) as $track => $codes ) foreach ( (array) $codes as $code ) $limitations[] = 'brano ' . ( absint( $track ) + 1 ) . ': ' . sanitize_text_field( $code );
    $sheet_error = (string) get_post_meta( $post->ID, '_trb_contract_spreadsheet_error', true );
    $sheet_synced_at = (string) get_post_meta( $post->ID, '_trb_contract_spreadsheet_synced_at', true );
    $sheet_status = $sheet_error ?: ( $sheet_synced_at ? 'Completata il ' . $sheet_synced_at : ( 'signed' === get_post_meta( $post->ID, '_trb_contract_state', true ) ? 'Da sincronizzare' : '—' ) );
    $rows = array(
        'Pipeline release'  => get_post_meta( $post->ID, '_trb_release_pipeline_status', true ),
		'Analisi tecnica'   => $technical_summary,
		'Decisione audio'   => $decision_summary,
		'Limiti analisi'    => implode( ', ', $limitations ),
        'Stato contratto'   => get_post_meta( $post->ID, '_trb_contract_state', true ),
        'Errore contratto'  => get_post_meta( $post->ID, '_trb_contract_error', true ),
        'Numero contratto'  => get_post_meta( $post->ID, '_trb_contract_number', true ),
        'Dossier OTP'       => get_post_meta( $post->ID, '_trb_otp_dossier_id', true ),
        'Contratto inviato' => get_post_meta( $post->ID, '_trb_contract_sent_at', true ),
        'Contratto firmato' => get_post_meta( $post->ID, '_trb_contract_signed_at', true ),
        'Sincronizzazione foglio' => $sheet_status,
    );
    echo '<table class="widefat striped"><tbody>';
    foreach ( $rows as $label => $value ) echo '<tr><th style="width:190px">' . esc_html( $label ) . '</th><td>' . esc_html( '' !== (string) $value ? $value : '—' ) . '</td></tr>';
    echo '</tbody></table>';
	if ( current_user_can( 'manage_options' ) && function_exists( 'trb_resource_tables' ) ) {
		global $wpdb;
		$usage_table = trb_resource_tables()['usage'];
		$usage_rows = $wpdb->get_results( $wpdb->prepare( "SELECT service,track_index,status,attempts,last_error,provider_reference,payload,updated_at FROM $usage_table WHERE release_id=%d ORDER BY id", $post->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $usage_rows ) {
			echo '<details style="margin-top:12px"><summary><strong>Diagnostica analisi audio</strong></summary><table class="widefat striped" style="margin-top:8px"><thead><tr><th>Servizio</th><th>Brano</th><th>Stato</th><th>Tentativi</th><th>Errore</th><th>Riferimento</th><th>Aggiornato</th></tr></thead><tbody>';
			foreach ( $usage_rows as $usage ) echo '<tr><td>' . esc_html( $usage['service'] ) . '</td><td>' . esc_html( absint( $usage['track_index'] ) + 1 ) . '</td><td>' . esc_html( $usage['status'] ) . '</td><td>' . esc_html( absint( $usage['attempts'] ) ) . '</td><td>' . esc_html( $usage['last_error'] ?: '—' ) . '</td><td>' . esc_html( $usage['provider_reference'] ?: '—' ) . '</td><td>' . esc_html( $usage['updated_at'] ) . '</td></tr>';
			echo '</tbody></table><details style="margin-top:8px"><summary>Risultati grezzi del provider</summary><pre style="max-height:420px;overflow:auto;white-space:pre-wrap">' . esc_html( wp_json_encode( $usage_rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre></details></details>';
			$has_acr_error = false;
			foreach ( $usage_rows as $usage ) if ( 'fingerprinting' === $usage['service'] && 'error' === $usage['status'] ) { $has_acr_error = true; break; }
			if ( $has_acr_error ) echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_release_analysis_retry&release_id=' . absint( $post->ID ) ), 'trb_release_analysis_retry_' . absint( $post->ID ) ) ) . '">Rielabora risposta ACR senza nuovo caricamento</a></p>';
		}
	}
    if ( 'approved' === get_post_meta( $post->ID, '_trb_release_pipeline_status', true ) && ! in_array( get_post_meta( $post->ID, '_trb_contract_state', true ), array( 'contract_sent', 'signed' ), true ) ) {
        echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_release_bridge_retry&release_id=' . absint( $post->ID ) ), 'trb_release_bridge_retry_' . absint( $post->ID ) ) ) . '">Riprova invio contratto</a></p>';
    }
    if ( current_user_can( 'manage_options' ) && 'contract_sent' === get_post_meta( $post->ID, '_trb_contract_state', true ) && get_post_meta( $post->ID, '_trb_otp_dossier_id', true ) ) {
        echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_release_bridge_confirm_signed&release_id=' . absint( $post->ID ) ), 'trb_release_bridge_confirm_signed_' . absint( $post->ID ) ) ) . '" onclick="return confirm(\'Usa questo recupero soltanto dopo aver verificato su OTPService che il dossier risulta completato e che il PDF firmato e stato archiviato. Confermi?\');">Firma verificata: sincronizza la pratica</a></p>';
    }
    if ( current_user_can( 'manage_options' ) && 'signed' === get_post_meta( $post->ID, '_trb_contract_state', true ) ) {
        echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_release_bridge_sync_spreadsheet&release_id=' . absint( $post->ID ) ), 'trb_release_bridge_sync_spreadsheet_' . absint( $post->ID ) ) ) . '">Sincronizza riga verde nel foglio</a></p>';
    }
}

function trb_release_analysis_retry_dispatch() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Non autorizzato.' );
	$release_id = isset( $_GET['release_id'] ) ? absint( $_GET['release_id'] ) : 0;
	check_admin_referer( 'trb_release_analysis_retry_' . $release_id );
	if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) || ! function_exists( 'trb_resource_start_release_analysis' ) ) wp_die( 'Pratica non valida.' );
	update_post_meta( $release_id, '_trb_release_pipeline_status', 'analysis_in_progress' );
	trb_resource_start_release_analysis( $release_id );
	wp_safe_redirect( get_edit_post_link( $release_id, 'url' ) );
	exit;
}
add_action( 'admin_post_trb_release_analysis_retry', 'trb_release_analysis_retry_dispatch' );

function trb_release_bridge_retry_dispatch() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Non autorizzato.' );
    $release_id = isset( $_GET['release_id'] ) ? absint( $_GET['release_id'] ) : 0;
    check_admin_referer( 'trb_release_bridge_retry_' . $release_id );
    if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) ) wp_die( 'Pratica non valida.' );
    update_post_meta( $release_id, '_trb_contract_state', 'preparing' );
    delete_post_meta( $release_id, '_trb_contract_error' );
    trb_release_bridge_dispatch( $release_id );
    wp_safe_redirect( get_edit_post_link( $release_id, 'url' ) );
    exit;
}
add_action( 'admin_post_trb_release_bridge_retry', 'trb_release_bridge_retry_dispatch' );

/** Administrative fallback for dossiers completed by OTPService without a delivered callback. */
function trb_release_bridge_confirm_signed_dispatch() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Non autorizzato.' );
    $release_id = isset( $_GET['release_id'] ) ? absint( $_GET['release_id'] ) : 0;
    check_admin_referer( 'trb_release_bridge_confirm_signed_' . $release_id );
    if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) ) wp_die( 'Pratica non valida.' );
    $dossier_id = sanitize_text_field( (string) get_post_meta( $release_id, '_trb_otp_dossier_id', true ) );
    if ( ! $dossier_id ) wp_die( 'Dossier OTP mancante.' );
    $result = trb_release_bridge_apply_callback( array(
        'release_id' => $release_id,
        'dossier_id' => $dossier_id,
        'status'     => 'completed',
        'signed_at'  => gmdate( DATE_ATOM ),
    ) );
    if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
    wp_safe_redirect( add_query_arg( 'trb_contract_reconciled', '1', get_edit_post_link( $release_id, 'url' ) ) );
    exit;
}
add_action( 'admin_post_trb_release_bridge_confirm_signed', 'trb_release_bridge_confirm_signed_dispatch' );

/** Retry only the signed-row formatting without touching contract state. */
function trb_release_bridge_sync_spreadsheet_dispatch() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Non autorizzato.' );
    $release_id = isset( $_GET['release_id'] ) ? absint( $_GET['release_id'] ) : 0;
    check_admin_referer( 'trb_release_bridge_sync_spreadsheet_' . $release_id );
    if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) || 'signed' !== get_post_meta( $release_id, '_trb_contract_state', true ) ) wp_die( 'Pratica firmata non valida.' );
    $result = trb_release_bridge_notify_spreadsheet_signed( $release_id );
    $status = is_wp_error( $result ) ? 'error' : '1';
    wp_safe_redirect( add_query_arg( 'trb_contract_sheet_synced', $status, get_edit_post_link( $release_id, 'url' ) ) );
    exit;
}
add_action( 'admin_post_trb_release_bridge_sync_spreadsheet', 'trb_release_bridge_sync_spreadsheet_dispatch' );

function trb_release_bridge_reconciled_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! empty( $_GET['trb_contract_reconciled'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Firma sincronizzata: la pratica risulta ora contrattualmente completata.</p></div>';
    if ( ! empty( $_GET['trb_contract_sheet_synced'] ) ) {
        $ok = '1' === sanitize_key( wp_unslash( $_GET['trb_contract_sheet_synced'] ) );
        echo '<div class="notice ' . ( $ok ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>' . esc_html( $ok ? 'Riga del contratto sincronizzata in verde.' : 'Sincronizzazione del foglio non riuscita: consulta la diagnostica della pratica.' ) . '</p></div>';
    }
}
add_action( 'admin_notices', 'trb_release_bridge_reconciled_notice' );

function trb_release_bridge_profile_value( $user_id, $key, $fallback = '' ) {
    if ( function_exists( 'trb_portal_artist_profile_value' ) ) {
        $value = trb_portal_artist_profile_value( $key, $user_id );
        if ( '' !== (string) $value ) return $value;
    }
    $value = get_user_meta( $user_id, '_trb_artist_' . $key, true );
    return '' !== (string) $value ? $value : $fallback;
}

function trb_release_bridge_payload( $release_id ) {
    $post = get_post( $release_id );
    if ( ! $post || 'trb_release' !== $post->post_type ) return new WP_Error( 'release_missing' );
    $user = get_userdata( $post->post_author );
    if ( ! $user ) return new WP_Error( 'artist_missing' );
    $profile = trb_portal_user_profile( $user );
    $tracks  = (array) get_post_meta( $release_id, '_trb_release_tracks', true );
    $state   = (string) get_post_meta( $release_id, '_trb_release_state', true );
    if ( ! in_array( $profile, array( 'dds', 'ddb12', 'ddb', 'ddb_trb', 'trb' ), true ) ) return new WP_Error( 'profile_invalid', 'Profilo contrattuale non riconosciuto.' );
    if ( 'unreleased' === $state ) {
        $missing_indexes = array();
        foreach ( $tracks as $index => $track ) {
            $code = isset( $track['isrc'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $track['isrc'] ) ) : '';
            if ( ! $code ) $missing_indexes[] = $index;
        }
        if ( $missing_indexes ) {
            $lock_key = '_trb_release_isrc_assignment_lock';
            $lock_time = absint( get_post_meta( $release_id, $lock_key, true ) );
            if ( $lock_time && time() - $lock_time > 10 * MINUTE_IN_SECONDS ) delete_post_meta( $release_id, $lock_key );
            if ( ! add_post_meta( $release_id, $lock_key, time(), true ) ) return new WP_Error( 'isrc_assignment_in_progress', 'L’assegnazione ISRC della pratica è già in corso.' );

            // Re-read after acquiring the lock: another dispatch may have
            // completed the assignment while this request was waiting.
            $tracks = (array) get_post_meta( $release_id, '_trb_release_tracks', true );
            $missing_indexes = array();
            foreach ( $tracks as $index => $track ) {
                $code = isset( $track['isrc'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $track['isrc'] ) ) : '';
                if ( ! $code ) $missing_indexes[] = $index;
            }
        }
        if ( $missing_indexes ) {
            $reserved = trb_release_bridge_allocate_isrcs( count( $missing_indexes ), $profile );
            if ( is_wp_error( $reserved ) || count( $reserved ) !== count( $missing_indexes ) ) {
                delete_post_meta( $release_id, $lock_key );
                return new WP_Error( 'isrc_assignment_failed', 'Il sistema non è riuscito ad assegnare gli ISRC mancanti.' );
            }
            foreach ( $missing_indexes as $position => $track_index ) $tracks[ $track_index ]['isrc'] = $reserved[ $position ];
            if ( false === update_post_meta( $release_id, '_trb_release_tracks', $tracks ) ) {
                delete_post_meta( $release_id, $lock_key );
                return new WP_Error( 'isrc_assignment_failed', 'Il sistema non è riuscito a salvare gli ISRC assegnati.' );
            }
            update_post_meta( $release_id, '_trb_release_isrc_allocation', array( 'pool' => 'trb' === $profile ? 'trb' : 'distribution', 'year' => wp_date( 'y' ), 'codes' => $reserved, 'assigned_at' => time(), 'source' => 'contract_backfill' ) );
        }
        if ( isset( $lock_key ) ) delete_post_meta( $release_id, $lock_key );
    }
    if ( in_array( $state, array( 'previously_released', 'unreleased' ), true ) ) {
        $codes = (array) get_transient( 'trb_release_bridge_isrc_' . $post->post_author );
        delete_transient( 'trb_release_bridge_isrc_' . $post->post_author );
        $seen = array();
        foreach ( $tracks as $index => &$track ) {
            $code = isset( $track['isrc'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $track['isrc'] ) ) : '';
            if ( 'previously_released' === $state && ! $code ) $code = $codes[ $index ] ?? ''; // Compatibility with practices created before persistent ISRC storage.
            if ( ! $code || isset( $seen[ $code ] ) ) return new WP_Error( 'invalid_isrc', 'ISRC mancante o duplicato.' );
            $track['isrc'] = $code;
            $seen[ $code ] = true;
        }
        unset( $track );
        update_post_meta( $release_id, '_trb_release_tracks', $tracks );
    }
    $files = array();
    foreach ( (array) get_post_meta( $release_id, '_trb_release_files', true ) as $file ) {
        if ( ! is_array( $file ) ) continue;
        $files[] = array('kind'=>sanitize_key($file['kind']??''),'name'=>sanitize_file_name($file['name']??''),'track'=>isset($file['track'])?absint($file['track']):null);
    }
    $artist = array(
        'user_id'=>(int)$post->post_author,'name'=>$user->first_name,'surname'=>$user->last_name,'email'=>$user->user_email,
        'phone'=>trb_release_bridge_profile_value($post->post_author,'phone'),'artist_name'=>trb_release_bridge_profile_value($post->post_author,'artist_name'),
        'tax_code'=>trb_release_bridge_profile_value($post->post_author,'tax_code'),'birth_date'=>trb_release_bridge_profile_value($post->post_author,'birth_date'),
        'birth_place'=>trb_release_bridge_profile_value($post->post_author,'birth_place'),'birth_province'=>trb_release_bridge_profile_value($post->post_author,'birth_province'),
        'document_type'=>trb_release_bridge_profile_value($post->post_author,'document_type','Carta d’identità'),
        'document_number'=>trb_release_bridge_profile_value($post->post_author,'document_number'),'address'=>trb_release_bridge_profile_value($post->post_author,'street'),
        'street_number'=>trb_release_bridge_profile_value($post->post_author,'street_number'),'postcode'=>trb_release_bridge_profile_value($post->post_author,'postal_code'),
        'municipality'=>trb_release_bridge_profile_value($post->post_author,'city'),'province'=>trb_release_bridge_profile_value($post->post_author,'province'),
        'country'=>trb_release_bridge_profile_value($post->post_author,'country','Italia'),
    );
    foreach ( array( 'name','surname','email','phone','artist_name','tax_code','birth_date','birth_place','birth_province','document_type','document_number','address','street_number','postcode','municipality','province','country' ) as $required ) {
        if ( '' === trim( (string) $artist[ $required ] ) ) return new WP_Error( 'artist_data_missing', 'Dato contrattuale mancante: ' . $required . '.' );
    }
    if ( empty( $tracks ) ) return new WP_Error( 'tracks_missing', 'Nessun brano disponibile per il contratto.' );
    return array('action'=>'portal_release','release_id'=>(int)$release_id,'profile'=>$profile,'title'=>$post->post_title,
        'release_type'=>(string)get_post_meta($release_id,'_trb_release_type',true),'release_state'=>$state,
        'release_date'=>(string)get_post_meta($release_id,'_trb_release_date',true),
        'original_date'=>(string)get_post_meta($release_id,'_trb_release_original_date',true),'confirmed_at'=>get_post_time(DATE_ATOM,true,$post),
        'confirmation_accepted'=>true,'artist'=>$artist,'tracks'=>$tracks,'files'=>$files,'portal_callback_url'=>trb_release_bridge_callback_url());
}

/**
 * Build the same positional row consumed by the existing TRB/DDB contract
 * factories. Those webapps receive a batch of spreadsheet rows, not a batch
 * of rich portal objects. Keep the rich payload at the top level for the
 * callback and future integrations, while rows remains a real 2-D array.
 */
function trb_release_bridge_spreadsheet_row( $payload ) {
    $artist = isset( $payload['artist'] ) && is_array( $payload['artist'] ) ? $payload['artist'] : array();
    $tracks = isset( $payload['tracks'] ) && is_array( $payload['tracks'] ) ? $payload['tracks'] : array();
    $format_date = static function ( $value ) {
        $value = (string) $value;
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) return $value;
        return $parts[3] . '/' . $parts[2] . '/' . $parts[1];
    };
    $credit_names = static function ( $entries ) {
        if ( ! is_array( $entries ) ) return '';
        return implode( "\n", array_filter( array_map( static function ( $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['name'] ) ) return '';
            $suffix = ! empty( $entry['role'] ) ? ' — ' . $entry['role'] : '';
            return $entry['name'] . $suffix;
        }, $entries ) ) );
    };

    $row = array(
        wp_date( 'd/m/Y H:i', strtotime( (string) ( $payload['confirmed_at'] ?? 'now' ) ) ),
        '', '', '', '',
        (string) ( $artist['name'] ?? '' ),
        (string) ( $artist['surname'] ?? '' ),
        $format_date( $artist['birth_date'] ?? '' ),
        (string) ( $artist['birth_place'] ?? '' ),
        (string) ( $artist['tax_code'] ?? '' ),
        (string) ( $artist['document_type'] ?? '' ),
        (string) ( $artist['document_number'] ?? '' ),
        (string) ( $artist['address'] ?? '' ),
        (string) ( $artist['street_number'] ?? '' ),
        (string) ( $artist['postcode'] ?? '' ),
        (string) ( $artist['municipality'] ?? '' ),
        (string) ( $artist['province'] ?? '' ),
        (string) ( $artist['country'] ?? '' ),
        (string) ( $artist['phone'] ?? '' ),
        (string) ( $artist['email'] ?? '' ),
        (string) ( $artist['artist_name'] ?? '' ),
        (string) ( $payload['title'] ?? '' ),
        $format_date( $payload['release_date'] ?? '' ),
        $format_date( $payload['original_date'] ?? '' ),
        'previously_released' === ( $payload['release_state'] ?? '' ) ? 'Già pubblicata' : 'Inedita',
        count( $tracks ),
        isset( $tracks[0]['primary_genre'] ) ? (string) $tracks[0]['primary_genre'] : '',
    );

    foreach ( $tracks as $track ) {
        $track = is_array( $track ) ? $track : array();
        $credits = isset( $track['credits'] ) && is_array( $track['credits'] ) ? $track['credits'] : array();
        $row[] = (string) ( $track['title'] ?? '' );
        $row[] = (string) ( $track['version'] ?? '' );
        $row[] = (string) ( $track['featuring'] ?? '' );
        $row[] = (string) ( $credits['authors'] ?? '' );
        $row[] = (string) ( $credits['composers'] ?? '' );
        $row[] = (string) ( $track['duration'] ?? '' );
        $row[] = (string) ( $track['advisory'] ?? '' );
        $row[] = (string) ( $track['primary_genre'] ?? '' );
        $row[] = (string) ( $track['secondary_genre'] ?? '' );
        $row[] = (string) ( $track['audio_status'] ?? '' );
        $row[] = (string) ( $track['isrc'] ?? '' );
        $row[] = $credit_names( $credits['credits'] ?? array() );
        $row[] = (string) ( $track['content_nature'] ?? '' );
        $row[] = (string) ( $track['rights_basis'] ?? '' );
        $row[] = (string) ( $track['rights_reference'] ?? '' );
        $row[] = (string) ( $track['rights_document'] ?? '' );
    }
    return $row;
}

/**
 * The contract webapps process a JSON batch (`JSON.parse(...).map(...)`).
 * Authentication is read from the Apps Script query parameters. Sending the
 * rich release object as the JSON root made the factory call `.map()` on an
 * object, which is the exact `rows.map is not a function` failure returned by
 * both deployments.
 */
function trb_release_bridge_post_webapp( $url, $payload, $secret ) {
    $request_url = add_query_arg( 'secret', (string) $secret, $url );
    $response = wp_remote_post( $request_url, array(
        'timeout'     => 120,
        'redirection' => 0,
        'headers'     => array( 'Content-Type' => 'application/json' ),
        'body'        => wp_json_encode( array( $payload ) ),
    ) );
    if ( is_wp_error( $response ) ) return $response;
    $code = wp_remote_retrieve_response_code( $response );
    if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
        $location = wp_remote_retrieve_header( $response, 'location' );
        $host = $location ? strtolower( (string) wp_parse_url( $location, PHP_URL_HOST ) ) : '';
        if ( ! $location || ( 'script.googleusercontent.com' !== $host && ! str_ends_with( $host, '.googleusercontent.com' ) ) ) {
            return new WP_Error( 'invalid_contract_redirect', 'Redirect Apps Script non valido.' );
        }
        $response = wp_remote_get( $location, array( 'timeout' => 120, 'redirection' => 2 ) );
    }
    return $response;
}

/** Mark the matching contract row as signed in the correct DDB/TRB spreadsheet. */
function trb_release_bridge_notify_spreadsheet_signed( $release_id ) {
    $release = get_post( $release_id );
    if ( ! $release || 'trb_release' !== $release->post_type ) return new WP_Error( 'release_missing', 'Release non trovata.' );
    $user = get_userdata( $release->post_author );
    $profile = $user && function_exists( 'trb_portal_user_profile' ) ? sanitize_key( trb_portal_user_profile( $user ) ) : '';
    if ( ! in_array( $profile, array( 'dds', 'ddb12', 'ddb', 'ddb_trb', 'trb' ), true ) ) {
        $error = new WP_Error( 'profile_invalid', 'Profilo contrattuale non riconosciuto per la sincronizzazione del foglio.' );
        update_post_meta( $release_id, '_trb_contract_spreadsheet_error', $error->get_error_message() );
        return $error;
    }

    $settings = trb_release_bridge_settings();
    $url = 'trb' === $profile ? $settings['trb_webapp_url'] : $settings['ddb_webapp_url'];
    if ( ! $url || ! $settings['shared_secret'] ) {
        $error = new WP_Error( 'spreadsheet_configuration_required', 'Collegamento al foglio non configurato.' );
        update_post_meta( $release_id, '_trb_contract_spreadsheet_error', $error->get_error_message() );
        return $error;
    }

    $payload = array(
        'action'          => 'portal_contract_signed',
        'release_id'      => absint( $release_id ),
        'profile'         => $profile,
        'contract_number' => sanitize_text_field( (string) get_post_meta( $release_id, '_trb_contract_number', true ) ),
        'dossier_id'      => sanitize_text_field( (string) get_post_meta( $release_id, '_trb_otp_dossier_id', true ) ),
        'secret'          => $settings['shared_secret'],
    );
    $response = trb_release_bridge_post_webapp( $url, $payload, $settings['shared_secret'] );
    if ( is_wp_error( $response ) ) {
        update_post_meta( $release_id, '_trb_contract_spreadsheet_error', $response->get_error_message() );
        return $response;
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( wp_remote_retrieve_response_code( $response ) >= 300 || empty( $body['success'] ) ) {
        $message = sanitize_text_field( (string) ( $body['error'] ?? wp_remote_retrieve_body( $response ) ) );
        $error = new WP_Error( 'spreadsheet_sync_failed', $message ?: 'Il foglio non ha confermato la sincronizzazione.' );
        update_post_meta( $release_id, '_trb_contract_spreadsheet_error', $error->get_error_message() );
        return $error;
    }
    delete_post_meta( $release_id, '_trb_contract_spreadsheet_error' );
    update_post_meta( $release_id, '_trb_contract_spreadsheet_synced_at', current_time( 'mysql', true ) );
    return true;
}

function trb_release_bridge_dispatch( $release_id ) {
    $current = get_post_meta( $release_id, '_trb_contract_state', true );
    if ( in_array( $current, array( 'contract_sent', 'signed' ), true ) ) return;
    if ( 'approved' !== get_post_meta( $release_id, '_trb_release_pipeline_status', true ) ) { update_post_meta($release_id,'_trb_contract_state','waiting_analysis'); return; }
    $payload = trb_release_bridge_payload( $release_id );
    if ( is_wp_error( $payload ) ) { update_post_meta($release_id,'_trb_contract_state','data_error'); update_post_meta($release_id,'_trb_contract_error',$payload->get_error_message()); return; }
    $s = trb_release_bridge_settings();
    $url = 'trb' === $payload['profile'] ? $s['trb_webapp_url'] : $s['ddb_webapp_url'];
    if ( ! $url || ! $s['shared_secret'] ) { update_post_meta($release_id,'_trb_contract_state','configuration_required'); return; }
    $payload['rows'] = array( trb_release_bridge_spreadsheet_row( $payload ) );
    $payload['secret'] = $s['shared_secret'];
    $response = trb_release_bridge_post_webapp( $url, $payload, $s['shared_secret'] );
    if ( is_wp_error( $response ) ) { update_post_meta($release_id,'_trb_contract_state','dispatch_error'); update_post_meta($release_id,'_trb_contract_error',$response->get_error_message()); wp_schedule_single_event(time()+15*MINUTE_IN_SECONDS,'trb_release_bridge_dispatch',array($release_id)); return; }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( wp_remote_retrieve_response_code( $response ) >= 300 || empty( $body['success'] ) ) { update_post_meta($release_id,'_trb_contract_state','dispatch_error'); update_post_meta($release_id,'_trb_contract_error',sanitize_text_field($body['error']??wp_remote_retrieve_body($response))); return; }
    update_post_meta($release_id,'_trb_contract_state','contract_sent'); update_post_meta($release_id,'_trb_contract_sent_at',current_time('mysql',true));
    if ( ! empty($body['contract_number']) ) update_post_meta($release_id,'_trb_contract_number',sanitize_text_field($body['contract_number']));
    if ( ! empty($body['dossier_id']) ) update_post_meta($release_id,'_trb_otp_dossier_id',sanitize_text_field($body['dossier_id']));
}
add_action( 'trb_release_bridge_dispatch', 'trb_release_bridge_dispatch', 10, 1 );

/**
 * Use admin-post for provider callbacks because this installation protects the
 * whole REST API with a login requirement before route permissions are checked.
 */
function trb_release_bridge_callback_url() {
    return admin_url( 'admin-post.php?action=trb_release_contract_callback' );
}

/** Apply a verified contract callback once; repeated completed callbacks are harmless. */
function trb_release_bridge_apply_callback( $payload ) {
    $release_id = absint( $payload['release_id'] ?? 0 );
    if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) ) return new WP_Error( 'not_found', 'Release non trovata.', array( 'status' => 404 ) );

    $dossier_id = sanitize_text_field( (string) ( $payload['dossier_id'] ?? '' ) );
    if ( ! $dossier_id ) return new WP_Error( 'dossier_missing', 'Dossier OTP mancante.', array( 'status' => 400 ) );
    $stored_dossier = sanitize_text_field( (string) get_post_meta( $release_id, '_trb_otp_dossier_id', true ) );
    if ( $stored_dossier && ! hash_equals( $stored_dossier, $dossier_id ) ) return new WP_Error( 'dossier_mismatch', 'Il dossier OTP non corrisponde alla pratica.', array( 'status' => 409 ) );
    if ( ! $stored_dossier ) update_post_meta( $release_id, '_trb_otp_dossier_id', $dossier_id );

    $status = sanitize_key( (string) ( $payload['status'] ?? '' ) );
    if ( ! in_array( $status, array( 'completed', 'contract_sent' ), true ) ) return new WP_Error( 'status_invalid', 'Stato firma non valido.', array( 'status' => 400 ) );
    if ( 'completed' !== $status ) {
        if ( 'signed' !== get_post_meta( $release_id, '_trb_contract_state', true ) ) update_post_meta( $release_id, '_trb_contract_state', 'contract_sent' );
        return array( 'success' => true, 'state' => get_post_meta( $release_id, '_trb_contract_state', true ) );
    }

    $already_signed = 'signed' === get_post_meta( $release_id, '_trb_contract_state', true );
    if ( ! $already_signed ) {
        $signed_at = sanitize_text_field( (string) ( $payload['signed_at'] ?? '' ) );
        update_post_meta( $release_id, '_trb_contract_state', 'signed' );
        update_post_meta( $release_id, '_trb_contract_signed_at', $signed_at ?: gmdate( DATE_ATOM ) );
        delete_post_meta( $release_id, '_trb_contract_error' );
    }
    $spreadsheet_result = trb_release_bridge_notify_spreadsheet_signed( $release_id );
    return array(
        'success'      => true,
        'state'        => 'signed',
        'idempotent'   => $already_signed,
        'spreadsheet_synced' => ! is_wp_error( $spreadsheet_result ),
        'release_date' => (string) get_post_meta( $release_id, '_trb_release_date', true ),
    );
}

/** Public provider endpoint authenticated with the same shared bridge secret. */
function trb_release_bridge_public_callback() {
    if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
        status_header( 405 );
        wp_send_json_error( array( 'message' => 'Metodo non consentito.' ), 405 );
    }
    $raw = file_get_contents( 'php://input' );
    $payload = json_decode( (string) $raw, true );
    if ( ! is_array( $payload ) ) $payload = wp_unslash( $_POST );
    $settings = trb_release_bridge_settings();
    $header_secret = isset( $_SERVER['HTTP_X_TRB_PORTAL_SECRET'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TRB_PORTAL_SECRET'] ) ) : '';
    $secret = $header_secret ?: sanitize_text_field( (string) ( $payload['secret'] ?? '' ) );
    if ( ! $settings['shared_secret'] || ! $secret || ! hash_equals( (string) $settings['shared_secret'], $secret ) ) wp_send_json_error( array( 'message' => 'Secret non valido.' ), 403 );
    $result = trb_release_bridge_apply_callback( $payload );
    if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data()['status'] ?? 400 ) );
    wp_send_json_success( $result );
}
add_action( 'admin_post_nopriv_trb_release_contract_callback', 'trb_release_bridge_public_callback' );
add_action( 'admin_post_trb_release_contract_callback', 'trb_release_bridge_public_callback' );

function trb_release_bridge_rest_routes() {
    register_rest_route('trb/v1','/release-contract-status',array('methods'=>'GET','permission_callback'=>function(){return is_user_logged_in();},'callback'=>function(){
        $releases=get_posts(array('post_type'=>'trb_release','post_status'=>array('publish','private','pending','draft'),'author'=>get_current_user_id(),'numberposts'=>100)); $out=array();
        foreach($releases as $release){
            $summary=function_exists('trb_portal_release_status_summary')?trb_portal_release_status_summary($release->ID):array();
            $out[]=array_merge(array('release_id'=>$release->ID,'state'=>get_post_meta($release->ID,'_trb_contract_state',true)?:'waiting_analysis','pipeline_state'=>get_post_meta($release->ID,'_trb_release_pipeline_status',true),'state_label'=>function_exists('trb_portal_release_current_state_label')?trb_portal_release_current_state_label($release->ID):'Controllo del brano in corso','release_date'=>get_post_meta($release->ID,'_trb_release_date',true)),$summary);
        }
        return rest_ensure_response($out);
    }));
    register_rest_route('trb/v1','/release-contract-callback',array('methods'=>'POST','permission_callback'=>'__return_true','callback'=>function(WP_REST_Request $request){
        $s=trb_release_bridge_settings(); $secret=(string)($request->get_header('x-trb-portal-secret')?:$request->get_param('secret'));
        if(!$s['shared_secret']||!hash_equals($s['shared_secret'],$secret))return new WP_Error('forbidden','Secret non valido.',array('status'=>403));
        $result=trb_release_bridge_apply_callback($request->get_params());
        return is_wp_error($result)?$result:rest_ensure_response($result);
    }));
}
add_action('rest_api_init','trb_release_bridge_rest_routes');

function trb_release_bridge_enqueue() {
    $post=get_post(); if(!is_page()||!$post||!has_shortcode($post->post_content,'trb_artist_portal'))return;
    $path=get_template_directory().'/assets/js/trb-release-contract-status.js'; wp_enqueue_script('trb-release-contract-status',get_template_directory_uri().'/assets/js/trb-release-contract-status.js',array(),file_exists($path)?filemtime($path):null,true);
    wp_localize_script('trb-release-contract-status','trbReleaseContract',array('statusUrl'=>rest_url('trb/v1/release-contract-status'),'nonce'=>wp_create_nonce('wp_rest'),'created'=>isset($_GET['trb_release'])&&'created'===sanitize_key(wp_unslash($_GET['trb_release']))));
}
add_action('wp_enqueue_scripts','trb_release_bridge_enqueue',45);
