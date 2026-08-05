<?php
/** Definitive release confirmation, Google Sheets, contracts, OTP and ISRC flow. */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TRB_RELEASE_FLOW_VERSION', '1.0.0' );

function trb_release_flow_settings() {
    $defaults = array(
        'ddb_spreadsheet_id' => '1YCGSVeq5ame4HDb-C-Ra2ELpw_MDAezeT9MivI4zNc4',
        'trb_spreadsheet_id' => '1udfWd1HDtNZKGqMVuGMaihctSfsIeu5NF1CXatYbrfw',
        'google_service_account_json' => '',
        'sheets_webhook_url' => '',
        'sheets_webhook_secret' => '',
        'otp_endpoint' => '',
        'otp_api_key' => '',
        'otp_webhook_secret' => '',
        'otp_distribution_template_id' => '',
        'otp_trb_template_id' => '',
    );
    $saved = get_option( 'trb_release_flow_settings', array() );
    foreach ( array( 'trb_otp_service_settings', 'otp_service_settings' ) as $legacy_key ) {
        $legacy = get_option( $legacy_key, array() );
        if ( is_array( $legacy ) ) $saved = wp_parse_args( $saved, $legacy );
    }
    return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

function trb_release_flow_log( $release_id, $event, $context = array() ) {
    $log = get_post_meta( $release_id, '_trb_release_flow_log', true );
    $log = is_array( $log ) ? $log : array();
    $log[] = array( 'time' => current_time( 'mysql' ), 'event' => sanitize_key( $event ), 'context' => $context );
    update_post_meta( $release_id, '_trb_release_flow_log', array_slice( $log, -100 ) );
}

/** Capture pre-existing ISRCs before the legacy handler sanitizes the posted tracks. */
function trb_release_flow_capture_isrc() {
    if ( ! is_user_logged_in() || empty( $_POST['trb_portal_release_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trb_portal_release_nonce'] ) ), 'trb_portal_start_release' ) ) return;
    $state = isset( $_POST['trb_release_state'] ) ? sanitize_key( wp_unslash( $_POST['trb_release_state'] ) ) : '';
    $values = array();
    if ( 'previously_released' === $state && ! empty( $_POST['trb_existing_isrc'] ) && is_array( $_POST['trb_existing_isrc'] ) ) {
        foreach ( (array) wp_unslash( $_POST['trb_existing_isrc'] ) as $index => $value ) {
            $value = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $value ) );
            if ( preg_match( '/^[A-Z]{2}[A-Z0-9]{3}[0-9]{7}$/', $value ) ) $values[ absint( $index ) ] = $value;
        }
    }
    set_transient( 'trb_release_isrc_' . get_current_user_id(), $values, HOUR_IN_SECONDS );
}
add_action( 'admin_post_trb_portal_start_release', 'trb_release_flow_capture_isrc', 1 );

function trb_release_flow_schedule( $post_id, $post, $update ) {
    if ( wp_is_post_revision( $post_id ) || 'trb_release' !== $post->post_type || $update ) return;
    if ( ! wp_next_scheduled( 'trb_release_flow_process', array( $post_id ) ) ) wp_schedule_single_event( time() + 45, 'trb_release_flow_process', array( $post_id ) );
}
add_action( 'save_post_trb_release', 'trb_release_flow_schedule', 20, 3 );

function trb_release_flow_profile_data( $user_id ) {
    $user = get_userdata( $user_id );
    $get = static function( $key ) use ( $user_id ) { return function_exists( 'trb_portal_artist_profile_value' ) ? trb_portal_artist_profile_value( $key, $user_id ) : get_user_meta( $user_id, '_trb_artist_' . $key, true ); };
    return array(
        'name' => $user ? $user->first_name : '', 'surname' => $user ? $user->last_name : '', 'email' => $user ? $user->user_email : '',
        'tax_code' => $get( 'tax_code' ), 'address' => $get( 'address' ), 'street_number' => $get( 'street_number' ), 'postcode' => $get( 'postcode' ),
        'municipality' => $get( 'municipality' ), 'province' => $get( 'province' ), 'country' => $get( 'country' ) ?: 'Italia', 'phone' => $get( 'phone' ),
        'artist_name' => $get( 'artist_name' ),
    );
}

function trb_release_flow_payload( $release_id ) {
    $post = get_post( $release_id ); if ( ! $post ) return new WP_Error( 'release_missing' );
    $profile = trb_portal_user_profile( get_userdata( $post->post_author ) );
    $tracks = (array) get_post_meta( $release_id, '_trb_release_tracks', true );
    $state = (string) get_post_meta( $release_id, '_trb_release_state', true );
    $isrcs = (array) get_post_meta( $release_id, '_trb_release_isrcs', true );
    if ( 'previously_released' === $state && empty( $isrcs ) ) {
        $isrcs = (array) get_transient( 'trb_release_isrc_' . $post->post_author );
        delete_transient( 'trb_release_isrc_' . $post->post_author );
        $seen = array();
        foreach ( $tracks as $i => &$track ) {
            $code = isset( $isrcs[ $i ] ) ? $isrcs[ $i ] : '';
            if ( ! $code || isset( $seen[ $code ] ) ) return new WP_Error( 'isrc_missing_or_duplicate', 'Inserire un ISRC valido e univoco per ogni brano già pubblicato.' );
            $seen[ $code ] = true; $track['isrc'] = $code;
        }
        unset( $track );
        update_post_meta( $release_id, '_trb_release_tracks', $tracks );
        update_post_meta( $release_id, '_trb_release_isrcs', $isrcs );
    }
    return array(
        'release_id' => $release_id, 'profile' => $profile, 'affiliation' => trb_portal_profile_affiliation( $profile ),
        'contract_type' => 'trb' === $profile ? 'master_distribution' : 'distribution', 'title' => $post->post_title,
        'release_type' => get_post_meta( $release_id, '_trb_release_type', true ), 'release_state' => $state,
        'original_date' => get_post_meta( $release_id, '_trb_release_original_date', true ), 'tracks' => $tracks,
        'artist' => trb_release_flow_profile_data( $post->post_author ), 'confirmed_at' => get_post_time( 'c', true, $post ),
        'callback_url' => rest_url( 'trb/v1/otp-callback' ),
    );
}

function trb_release_flow_google_token() {
    $settings = trb_release_flow_settings();
    $json = trim( (string) $settings['google_service_account_json'] );
    if ( ! $json && defined( 'TRB_GOOGLE_SERVICE_ACCOUNT_JSON' ) ) $json = TRB_GOOGLE_SERVICE_ACCOUNT_JSON;
    $sa = json_decode( $json, true );
    if ( ! is_array( $sa ) || empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) return new WP_Error( 'google_credentials_missing' );
    $cached = get_transient( 'trb_google_sheets_token' ); if ( $cached ) return $cached;
    $b64 = static function( $v ) { return rtrim( strtr( base64_encode( $v ), '+/', '-_' ), '=' ); };
    $now = time(); $header = $b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
    $claim = $b64( wp_json_encode( array( 'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/spreadsheets', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3500 ) ) );
    $input = $header . '.' . $claim; $signature = '';
    if ( ! openssl_sign( $input, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256 ) ) return new WP_Error( 'google_jwt_failed' );
    $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 30, 'body' => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $input . '.' . $b64( $signature ) ) ) );
    if ( is_wp_error( $response ) ) return $response; $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $data['access_token'] ) ) return new WP_Error( 'google_token_failed', wp_remote_retrieve_body( $response ) );
    set_transient( 'trb_google_sheets_token', $data['access_token'], 50 * MINUTE_IN_SECONDS ); return $data['access_token'];
}

function trb_release_flow_sheet_map( $payload ) {
    $a = $payload['artist']; $map = array(
        'Informazioni cronologiche' => wp_date( 'd/m/Y H.i.s' ), 'Nome' => $a['name'], 'Cognome' => $a['surname'], 'Codice fiscale' => $a['tax_code'],
        'Indirizzo di residenza' => $a['address'], 'Numero civico' => $a['street_number'], 'CAP' => $a['postcode'], 'Comune' => $a['municipality'], 'Provincia' => $a['province'],
        'Nazione' => $a['country'], 'Telefono cellulare con prefisso internazionale' => $a['phone'], 'Indirizzo e-mail' => $a['email'], 'Nome d\'arte' => $a['artist_name'],
        'Titolo del progetto discografico' => $payload['title'], 'Data di pubblicazione originale (solo se già precedentemente rilasciato)' => $payload['original_date'],
        'Tipologia di progetto discografico' => $payload['release_type'],
    );
    foreach ( $payload['tracks'] as $i => $track ) { $n = $i + 1; $map["Brano $n: Titolo"] = $track['title'] ?? ''; $map["Brano $n: Featuring"] = $track['featuring'] ?? ''; $map["Brano $n: Durata"] = $track['duration'] ?? ''; $map["Brano $n: Parental Advisory"] = $track['advisory'] ?? ''; $map["Brano $n: ISRC"] = $track['isrc'] ?? ''; }
    return $map;
}

function trb_release_flow_sync_sheet( $payload ) {
    $settings = trb_release_flow_settings();
    if ( ! empty( $settings['sheets_webhook_url'] ) ) {
        $body = wp_json_encode( array( 'action' => 'append_release', 'payload' => $payload ) );
        return wp_remote_post( $settings['sheets_webhook_url'], array( 'timeout' => 45, 'headers' => array( 'Content-Type' => 'application/json', 'X-TRB-Signature' => hash_hmac( 'sha256', $body, $settings['sheets_webhook_secret'] ) ), 'body' => $body ) );
    }
    $token = trb_release_flow_google_token(); if ( is_wp_error( $token ) ) return $token;
    $spreadsheet = 'trb' === $payload['profile'] ? $settings['trb_spreadsheet_id'] : $settings['ddb_spreadsheet_id']; $sheet = wp_date( 'Y' );
    $headers_response = wp_remote_get( 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode( $spreadsheet ) . '/values/' . rawurlencode( "'$sheet'!1:1" ), array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
    if ( is_wp_error( $headers_response ) ) return $headers_response; $headers_data = json_decode( wp_remote_retrieve_body( $headers_response ), true );
    if ( empty( $headers_data['values'][0] ) ) return new WP_Error( 'sheet_headers_missing' );
    $map = trb_release_flow_sheet_map( $payload ); $row = array(); foreach ( $headers_data['values'][0] as $header ) $row[] = $map[ $header ] ?? '';
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode( $spreadsheet ) . '/values/' . rawurlencode( "'$sheet'!A:ZZ" ) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
    $response = wp_remote_post( $url, array( 'timeout' => 45, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'values' => array( $row ) ) ) ) );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 300 ) return is_wp_error( $response ) ? $response : new WP_Error( 'sheet_append_failed', wp_remote_retrieve_body( $response ) );
    return json_decode( wp_remote_retrieve_body( $response ), true );
}

function trb_release_flow_send_contract( $payload ) {
    $filtered = apply_filters( 'trb_otp_service_create_contract', null, $payload ); if ( null !== $filtered ) return $filtered;
    $settings = trb_release_flow_settings();
    if ( empty( $settings['otp_endpoint'] ) ) return new WP_Error( 'otp_not_configured' );
    $template = 'master_distribution' === $payload['contract_type'] ? $settings['otp_trb_template_id'] : $settings['otp_distribution_template_id'];
    $request = array( 'template_id' => $template, 'external_id' => 'TRB-RELEASE-' . $payload['release_id'], 'signer' => array( 'name' => trim( $payload['artist']['name'] . ' ' . $payload['artist']['surname'] ), 'email' => $payload['artist']['email'], 'phone' => $payload['artist']['phone'] ), 'fields' => $payload, 'callback_url' => $payload['callback_url'] );
    $response = wp_remote_post( $settings['otp_endpoint'], array( 'timeout' => 60, 'headers' => array( 'Authorization' => 'Bearer ' . $settings['otp_api_key'], 'Content-Type' => 'application/json', 'Idempotency-Key' => 'release-' . $payload['release_id'] ), 'body' => wp_json_encode( $request ) ) );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 300 ) return is_wp_error( $response ) ? $response : new WP_Error( 'otp_request_failed', wp_remote_retrieve_body( $response ) );
    return json_decode( wp_remote_retrieve_body( $response ), true );
}

function trb_release_flow_process( $release_id ) {
    if ( get_post_meta( $release_id, '_trb_release_contract_dispatched', true ) ) return;
    $payload = trb_release_flow_payload( $release_id );
    if ( is_wp_error( $payload ) ) { update_post_meta( $release_id, '_trb_release_pipeline_status', 'data_correction_required' ); trb_release_flow_log( $release_id, 'payload_error', array( 'code' => $payload->get_error_code(), 'message' => $payload->get_error_message() ) ); return; }
    update_post_meta( $release_id, '_trb_release_confirmed_at', $payload['confirmed_at'] ); update_post_meta( $release_id, '_trb_release_pipeline_status', 'confirmed' );
    $sheet = trb_release_flow_sync_sheet( $payload );
    if ( is_wp_error( $sheet ) ) { update_post_meta( $release_id, '_trb_release_pipeline_status', 'sheet_sync_waiting' ); trb_release_flow_log( $release_id, 'sheet_error', array( 'code' => $sheet->get_error_code() ) ); wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, 'trb_release_flow_process', array( $release_id ) ); return; }
    update_post_meta( $release_id, '_trb_release_sheet_synced_at', current_time( 'mysql' ) );
    $contract = trb_release_flow_send_contract( $payload );
    if ( is_wp_error( $contract ) ) { update_post_meta( $release_id, '_trb_release_pipeline_status', 'contract_configuration_required' ); trb_release_flow_log( $release_id, 'contract_error', array( 'code' => $contract->get_error_code(), 'message' => $contract->get_error_message() ) ); if ( function_exists( 'trb_resource_queue_email' ) ) trb_resource_queue_email( 'release-contract-' . $release_id, 'Contratto release non inviato', 'La release #' . $release_id . ' è stata salvata nel foglio ma il contratto non è partito: ' . esc_html( $contract->get_error_message() ), true ); return; }
    update_post_meta( $release_id, '_trb_release_contract_dispatched', 1 ); update_post_meta( $release_id, '_trb_release_otp_response', $contract ); update_post_meta( $release_id, '_trb_release_pipeline_status', 'awaiting_signature' ); trb_release_flow_log( $release_id, 'contract_sent' );
}
add_action( 'trb_release_flow_process', 'trb_release_flow_process', 10, 1 );

function trb_release_flow_allocate_isrcs( $release_id ) {
    $post = get_post( $release_id ); if ( ! $post || 'trb' !== trb_portal_user_profile( get_userdata( $post->post_author ) ) || 'unreleased' !== get_post_meta( $release_id, '_trb_release_state', true ) ) return;
    $tracks = (array) get_post_meta( $release_id, '_trb_release_tracks', true ); if ( ! empty( get_post_meta( $release_id, '_trb_release_isrcs', true ) ) ) return;
    $year = (int) wp_date( 'y' ); $key = 'trb_isrc_next_' . $year; $lock = 'trb_isrc_lock_' . $year;
    if ( ! add_option( $lock, time(), '', 'no' ) ) { wp_schedule_single_event( time() + 30, 'trb_release_flow_allocate_isrcs', array( $release_id ) ); return; }
    $next = (int) get_option( $key, 26 === $year ? 5 : 1 ); $codes = array();
    foreach ( $tracks as $i => &$track ) { $code = 'ITV24' . sprintf( '%02d%05d', $year, $next++ ); $track['isrc'] = $code; $codes[ $i ] = $code; }
    unset( $track ); update_option( $key, $next, false ); delete_option( $lock ); update_post_meta( $release_id, '_trb_release_tracks', $tracks ); update_post_meta( $release_id, '_trb_release_isrcs', $codes ); update_post_meta( $release_id, '_trb_release_pipeline_status', 'signed_isrc_assigned' ); trb_release_flow_log( $release_id, 'isrc_assigned', $codes );
}
add_action( 'trb_release_flow_allocate_isrcs', 'trb_release_flow_allocate_isrcs', 10, 1 );

function trb_release_flow_register_rest() {
    register_rest_route( 'trb/v1', '/otp-callback', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'trb_release_flow_otp_callback' ) );
}
add_action( 'rest_api_init', 'trb_release_flow_register_rest' );
function trb_release_flow_otp_callback( WP_REST_Request $request ) {
    $raw = $request->get_body(); $settings = trb_release_flow_settings(); $signature = (string) $request->get_header( 'x-trb-signature' );
    if ( ! empty( $settings['otp_webhook_secret'] ) && ! hash_equals( hash_hmac( 'sha256', $raw, $settings['otp_webhook_secret'] ), $signature ) ) return new WP_Error( 'invalid_signature', 'Firma callback non valida.', array( 'status' => 403 ) );
    $data = json_decode( $raw, true ); $external = $data['external_id'] ?? ''; $release_id = preg_match( '/TRB-RELEASE-(\d+)/', $external, $m ) ? absint( $m[1] ) : absint( $data['release_id'] ?? 0 );
    if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) ) return new WP_Error( 'release_not_found', 'Release non trovata.', array( 'status' => 404 ) );
    $status = sanitize_key( $data['status'] ?? '' ); update_post_meta( $release_id, '_trb_release_otp_status', $status ); update_post_meta( $release_id, '_trb_release_otp_callback', $data );
    if ( in_array( $status, array( 'signed', 'completed', 'success' ), true ) ) { update_post_meta( $release_id, '_trb_release_signed_at', current_time( 'mysql' ) ); update_post_meta( $release_id, '_trb_release_pipeline_status', 'signed' ); wp_schedule_single_event( time() + 5, 'trb_release_flow_allocate_isrcs', array( $release_id ) ); }
    elseif ( in_array( $status, array( 'declined', 'expired', 'failed' ), true ) ) update_post_meta( $release_id, '_trb_release_pipeline_status', 'signature_' . $status );
    trb_release_flow_log( $release_id, 'otp_' . $status ); return rest_ensure_response( array( 'success' => true, 'release_id' => $release_id ) );
}

function trb_release_flow_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return; $s = trb_release_flow_settings(); $missing = array();
    if ( empty( $s['google_service_account_json'] ) && empty( $s['sheets_webhook_url'] ) && ! defined( 'TRB_GOOGLE_SERVICE_ACCOUNT_JSON' ) ) $missing[] = 'credenziali Google Sheets';
    if ( empty( $s['otp_endpoint'] ) && ! has_filter( 'trb_otp_service_create_contract' ) ) $missing[] = 'adattatore OTP Service';
    if ( $missing ) echo '<div class="notice notice-warning"><p><strong>Flusso contratti release:</strong> configurazione necessaria per ' . esc_html( implode( ' e ', $missing ) ) . '. Le pratiche restano salvate e vengono ritentate senza duplicazioni.</p></div>';
}
add_action( 'admin_notices', 'trb_release_flow_admin_notice' );
