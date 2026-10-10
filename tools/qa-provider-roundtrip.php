<?php
/** Actual provider requests with synthetic bytes; no artist message or release. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
$run = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{16}$/D', $run ) ) exit( 2 );
$result = array( 'pcloud' => false, 'pcloud_cleanup' => false, 'evaluation' => false, 'sheets' => false, 'artist_messages' => 0 );
$buffer = ob_get_level(); ob_start();
register_shutdown_function( static function() use ( &$result, $buffer ) { while ( ob_get_level() > $buffer ) ob_end_clean(); echo json_encode( $result, JSON_UNESCAPED_SLASHES ) . "\n"; } );
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
$settings = trb_demo_settings();
$folder = '/Upload files - TRB rec/Audio/Demo files/QA-AUDIT-' . $run;
$remote = $folder . '/testo-sintetico.txt';
$result['pcloud_fixture'] = 'QA-AUDIT-' . $run;
$body = "TEST TECNICO FITTIZIO, nessun artista reale.\nUna luce sul mare, un passo nella sera.\nCerco una strada nuova, ritorno alla mia terra.\nIl vento porta voci, la notte le raccoglie.\nDomani cambio passo e apro altre soglie.\n";
$made = false; $local = '';
try {
    require __DIR__ . '/qa-provider-cleanup.php';
    $result['pcloud_stale_folders_removed'] = trb_qa_provider_cleanup_stale( $body );
    $response = trb_demo_webdav_request( 'MKCOL', $folder );
    $made = ! is_wp_error( $response ) && in_array( (int) wp_remote_retrieve_response_code( $response ), array( 200, 201, 204 ), true );
    if ( ! $made ) throw new RuntimeException( 'Archive test folder unavailable.' );
    $put = trb_demo_webdav_request( 'PUT', $remote, $body, array( 'Content-Type' => 'text/plain; charset=utf-8', 'If-None-Match' => '*' ) );
    if ( is_wp_error( $put ) || ! in_array( (int) wp_remote_retrieve_response_code( $put ), array( 200, 201, 204 ), true ) ) throw new RuntimeException( 'Archive upload unconfirmed.' );
    $get = trb_demo_webdav_request( 'GET', $remote );
    $result['pcloud'] = ! is_wp_error( $get ) && 200 === (int) wp_remote_retrieve_response_code( $get ) && hash_equals( hash( 'sha256', $body ), hash( 'sha256', wp_remote_retrieve_body( $get ) ) );
    $result['pcloud_bytes'] = strlen( $body );
    if ( ! $result['pcloud'] ) throw new RuntimeException( 'Archive readback mismatch.' );
    $uploads = wp_upload_dir(); $directory = $uploads['basedir'] . '/trb-demo-private';
    if ( ! is_dir( $directory ) || is_link( $directory ) ) throw new RuntimeException( 'Private synthetic storage unavailable.' );
    $local = $directory . '/qa-audit-' . $run . '.txt';
    if ( file_exists( $local ) || file_put_contents( $local, $body, LOCK_EX ) !== strlen( $body ) ) throw new RuntimeException( 'Synthetic fixture collision.' );
    chmod( $local, 0600 );
    $payload = array( 'uuid' => $run, 'title' => 'QA AUDIT FITTIZIO ' . $run, 'first_name' => 'Artista', 'last_name' => 'Fittizio', 'artist_name' => 'Artista Fittizio Tunisia', 'email' => 'qa-audit@example.invalid', 'profile' => 'trb', 'submitted_at' => gmdate( 'c' ), 'genre' => 'Pop', 'text_file' => array( 'path' => 'trb-demo-private/' . basename( $local ), 'name' => basename( $local ), 'original_name' => basename( $local ), 'size' => strlen( $body ), 'sha256' => hash( 'sha256', $body ) ), 'review_context' => array( 'focus' => 'lyrics' ) );
    $evaluation = trb_demo_openai_review( $payload );
    $result['evaluation'] = ! is_wp_error( $evaluation ) && is_array( $evaluation ) && ! empty( $evaluation['review'] );
    if ( is_wp_error( $evaluation ) ) $result['evaluation_error_code'] = sanitize_key( $evaluation->get_error_code() );
    // No undocumented POST action: it could append a row to the live sheet.
    $sheet = wp_remote_get( (string) ( $settings['sheet_webhook_url'] ?? '' ), array( 'timeout' => 30, 'redirection' => 3 ) );
    $result['sheets_http_status'] = is_wp_error( $sheet ) ? 0 : (int) wp_remote_retrieve_response_code( $sheet );
    preg_match( '~^https://script\.google\.com/macros/s/([a-zA-Z0-9_-]+)/exec$~D', (string) ( $settings['sheet_webhook_url'] ?? '' ), $deployment );
    $result['sheets_deployment_id'] = $deployment[1] ?? null;
    $result['sheets_roundtrip_scope'] = 'read_only_endpoint_probe; test_tab_not_yet_verified';
} catch ( Throwable $error ) {
    $result['failure'] = array( 'class' => get_class( $error ), 'line' => $error->getLine() );
} finally {
    if ( $local && is_file( $local ) ) unlink( $local );
    if ( $made ) {
        $file_deleted = trb_demo_webdav_request( 'DELETE', $remote );
        $folder_deleted = trb_demo_webdav_request( 'DELETE', $folder . '/' );
        $absent = trb_demo_webdav_request( 'GET', $remote, null, array( 'Cache-Control' => 'no-cache' ) );
        $folder_absent = trb_demo_webdav_request( 'GET', $folder . '/', null, array( 'Cache-Control' => 'no-cache' ) );
        $result['pcloud_cleanup_http'] = array( is_wp_error( $file_deleted ) ? 0 : (int) wp_remote_retrieve_response_code( $file_deleted ), is_wp_error( $folder_deleted ) ? 0 : (int) wp_remote_retrieve_response_code( $folder_deleted ), is_wp_error( $absent ) ? 0 : (int) wp_remote_retrieve_response_code( $absent ) );
        $result['pcloud_folder_absence_http'] = is_wp_error( $folder_absent ) ? 0 : (int) wp_remote_retrieve_response_code( $folder_absent );
        $result['pcloud_cleanup'] = in_array( $result['pcloud_cleanup_http'][0], array( 200, 204, 404, 410 ), true ) && in_array( $result['pcloud_cleanup_http'][1], array( 200, 204, 404, 410 ), true ) && in_array( $result['pcloud_cleanup_http'][2], array( 404, 410 ), true ) && in_array( $result['pcloud_folder_absence_http'], array( 404, 410 ), true );
    }
}
