<?php
/** Actual provider requests with synthetic bytes; no artist message or release. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
$run = $argv[1] ?? '';
$mode = $argv[2] ?? 'full';
if ( ! preg_match( '/^[a-f0-9]{16}$/D', $run ) || ! in_array( $mode, array( 'full', 'no-ai' ), true ) ) exit( 2 );
$result = array( 'pcloud' => false, 'pcloud_cleanup' => false, 'evaluation' => false, 'sheets' => false, 'artist_messages' => 0 );
$buffer = ob_get_level(); ob_start();
register_shutdown_function( static function() use ( &$result, $buffer ) { while ( ob_get_level() > $buffer ) ob_end_clean(); echo json_encode( $result, JSON_UNESCAPED_SLASHES ) . "\n"; } );
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
if ( ! function_exists( 'trb_webdav_request' ) ) require dirname( __DIR__ ) . '/inc/trb-webdav.php';
$settings = trb_demo_settings();
preg_match( '~^https://script\.google\.com/macros/s/([a-zA-Z0-9_-]+)/exec$~D', (string) ( $settings['sheet_webhook_url'] ?? '' ), $deployment );
$result['sheets_deployment_id'] = $deployment[1] ?? null;
$folder = '/Upload files - TRB rec/Audio/Demo files/QA-AUDIT-' . $run;
$remote = $folder . '/testo-sintetico.txt';
$result['pcloud_fixture'] = 'QA-AUDIT-' . $run;
$body = "TEST TECNICO FITTIZIO, nessun artista reale.\nUna luce sul mare, un passo nella sera.\nCerco una strada nuova, ritorno alla mia terra.\nIl vento porta voci, la notte le raccoglie.\nDomani cambio passo e apro altre soglie.\n";
$made = false; $local = '';
try {
    require __DIR__ . '/qa-provider-cleanup.php';
    $result['pcloud_stale_folders_removed'] = trb_qa_provider_cleanup_stale( $body );
    $response = trb_webdav_request( trb_demo_settings(), 'MKCOL', $folder );
    $made = ! is_wp_error( $response ) && in_array( (int) wp_remote_retrieve_response_code( $response ), array( 200, 201, 204 ), true );
    if ( ! $made ) throw new RuntimeException( 'Archive test folder unavailable.' );
    $put = trb_webdav_request( trb_demo_settings(), 'PUT', $remote, $body, array( 'Content-Type' => 'text/plain; charset=utf-8', 'If-None-Match' => '*' ) );
    if ( is_wp_error( $put ) || ! in_array( (int) wp_remote_retrieve_response_code( $put ), array( 200, 201, 204 ), true ) ) throw new RuntimeException( 'Archive upload unconfirmed.' );
    $get = trb_webdav_request( trb_demo_settings(), 'GET', $remote );
    $result['pcloud'] = ! is_wp_error( $get ) && 200 === (int) wp_remote_retrieve_response_code( $get ) && hash_equals( hash( 'sha256', $body ), hash( 'sha256', wp_remote_retrieve_body( $get ) ) );
    $result['pcloud_bytes'] = strlen( $body );
    if ( ! $result['pcloud'] ) throw new RuntimeException( 'Archive readback mismatch.' );
    $uploads = wp_upload_dir(); $directory = $uploads['basedir'] . '/trb-demo-private';
    if ( ! is_dir( $directory ) || is_link( $directory ) ) throw new RuntimeException( 'Private synthetic storage unavailable.' );
    $local = $directory . '/qa-audit-' . $run . '.txt';
    if ( file_exists( $local ) || file_put_contents( $local, $body, LOCK_EX ) !== strlen( $body ) ) throw new RuntimeException( 'Synthetic fixture collision.' );
    chmod( $local, 0600 );
    $payload = array( 'uuid' => $run, 'title' => 'QA AUDIT FITTIZIO ' . $run, 'first_name' => 'Artista', 'last_name' => 'Fittizio', 'artist_name' => 'Artista Fittizio Tunisia', 'email' => 'qa-audit@example.invalid', 'profile' => 'trb', 'submitted_at' => gmdate( 'c' ), 'genre' => 'Pop', 'text_file' => array( 'path' => 'trb-demo-private/' . basename( $local ), 'name' => basename( $local ), 'original_name' => basename( $local ), 'size' => strlen( $body ), 'sha256' => hash( 'sha256', $body ) ), 'review_context' => array( 'focus' => 'lyrics' ) );
    if ( 'full' === $mode ) {
        $evaluation = trb_demo_openai_review( $payload );
        $result['evaluation'] = ! is_wp_error( $evaluation ) && is_array( $evaluation ) && ! empty( $evaluation['review'] );
        if ( is_wp_error( $evaluation ) ) $result['evaluation_error_code'] = sanitize_key( $evaluation->get_error_code() );
    } else { $result['evaluation'] = null; $result['evaluation_skipped'] = true; }
    // No undocumented POST action: it could append a row to the live sheet.
    $sheet = wp_remote_get( (string) ( $settings['sheet_webhook_url'] ?? '' ), array( 'timeout' => 30, 'redirection' => 3 ) );
    $result['sheets_http_status'] = is_wp_error( $sheet ) ? 0 : (int) wp_remote_retrieve_response_code( $sheet );
    preg_match( '~^https://script\.google\.com/macros/s/([a-zA-Z0-9_-]+)/exec$~D', (string) ( $settings['sheet_webhook_url'] ?? '' ), $deployment );
    $result['sheets_deployment_id'] = $deployment[1] ?? null;
    $result['sheets_roundtrip_scope'] = 'read_only_endpoint_probe; test_tab_not_yet_verified';
    $health = is_wp_error( $sheet ) ? null : json_decode( wp_remote_retrieve_body( $sheet ), true );
    // Old deployments ignore unknown fields. Send QA bytes only to this exact
    // isolated protocol after its read-only endpoint has identified itself.
    if ( is_array( $health ) && ( $health['protocol'] ?? '' ) === 'trb-demo-sheet-v2' && ( $health['isolated_qa'] ?? false ) === true ) {
        $row = array( 'informazioni_cronologiche' => gmdate( 'd/m/Y H:i' ), 'nome' => 'Artista', 'cognome' => 'Fittizio', 'nome_arte' => 'Artista Fittizio Tunisia', 'email' => 'qa-' . $run . '@example.invalid', 'titolo' => '=Collaudo l’onda تونس', 'link_provino' => 'https://example.invalid/qa/' . $run, 'request_id' => 'QA-AUDIT-' . $run, 'qa_run' => $run );
        $call_sheet = static function( $action, $valid = true ) use ( $row, $settings, &$result ) {
            $json = wp_json_encode( array_merge( $row, array( 'qa_action' => $action ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            $envelope = array( 'payload_base64' => base64_encode( $json ), 'signature' => $valid ? hash_hmac( 'sha256', $json, $settings['sheet_webhook_secret'] ) : str_repeat( '0', 64 ) );
            $response = trb_demo_post_sheet_webhook( $settings['sheet_webhook_url'], $envelope );
            $decoded = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
            $key = $action . ( $valid ? '' : '_invalid_signature' );
            $error = is_array( $decoded ) && isset( $decoded['error'] ) ? (string) $decoded['error'] : '';
            $result['sheets_diagnostics'][$key] = array( 'http_status' => is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response ), 'json' => is_array( $decoded ), 'success' => ( $decoded['success'] ?? false ) === true, 'unauthorized' => $error === 'unauthorized', 'headers_mismatch' => str_contains( $error, 'Intestazioni' ), 'readback_unconfirmed' => str_contains( $error, 'Rilettura' ), 'values_count' => isset( $decoded['values'] ) && is_array( $decoded['values'] ) ? count( $decoded['values'] ) : 0 );
            return $decoded;
        };
        try {
            $invalid = $call_sheet( 'write', false );
            $write = $call_sheet( 'write' ); $duplicate = $call_sheet( 'write' ); $read = $call_sheet( 'read' );
            $expected = array_values( array_slice( $row, 0, 8, true ) );
            $result['sheets'] = ( $invalid['success'] ?? true ) === false && ( $write['success'] ?? false ) === true && ( $write['request_id'] ?? '' ) === $row['request_id'] && ( $duplicate['duplicate'] ?? false ) === true && ( $read['values'] ?? null ) === $expected;
            $result['sheets_roundtrip_scope'] = 'signed_write_duplicate_unicode_literal_readback_in_isolated_tab';
        } finally {
            $cleanup = $call_sheet( 'cleanup' );
            $result['sheets_cleanup'] = ( $cleanup['success'] ?? false ) === true && ( $cleanup['cleaned'] ?? false ) === true && ( $cleanup['qa_run'] ?? '' ) === $run;
        }
    }
} catch ( Throwable $error ) {
    $result['failure'] = array( 'class' => get_class( $error ), 'line' => $error->getLine(), 'code' => (int) $error->getCode() );
} finally {
    if ( $local && is_file( $local ) ) unlink( $local );
    if ( $made ) {
        $file_deleted = trb_webdav_request( trb_demo_settings(), 'DELETE', $remote );
        $folder_deleted = trb_webdav_request( trb_demo_settings(), 'DELETE', $folder . '/' );
        $absent = trb_webdav_request( trb_demo_settings(), 'GET', $remote, null, array( 'Cache-Control' => 'no-cache' ) );
        $folder_absent = trb_webdav_request( trb_demo_settings(), 'GET', $folder . '/', null, array( 'Cache-Control' => 'no-cache' ) );
        $result['pcloud_cleanup_http'] = array( is_wp_error( $file_deleted ) ? 0 : (int) wp_remote_retrieve_response_code( $file_deleted ), is_wp_error( $folder_deleted ) ? 0 : (int) wp_remote_retrieve_response_code( $folder_deleted ), is_wp_error( $absent ) ? 0 : (int) wp_remote_retrieve_response_code( $absent ) );
        $result['pcloud_folder_absence_http'] = is_wp_error( $folder_absent ) ? 0 : (int) wp_remote_retrieve_response_code( $folder_absent );
        $result['pcloud_cleanup'] = in_array( $result['pcloud_cleanup_http'][0], array( 200, 204, 404, 410 ), true ) && in_array( $result['pcloud_cleanup_http'][1], array( 200, 204, 404, 410 ), true ) && in_array( $result['pcloud_cleanup_http'][2], array( 404, 410 ), true ) && in_array( $result['pcloud_folder_absence_http'], array( 404, 410 ), true );
    }
}
