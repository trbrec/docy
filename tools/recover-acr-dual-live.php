<?php
/** Resume existing jobs affected by the former pending/engine error. No uploads. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$revision = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || trim( (string) @file_get_contents( dirname(__DIR__) . '/.trb-deployed-sha' ) ) !== $revision ) exit(2);
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
define( 'DISABLE_WP_CRON', true );
require dirname(__DIR__, 4) . '/wp-load.php';
if ( rtrim( home_url(), '/' ) !== 'https://artist.trbrec.com' ) throw new RuntimeException( 'Unexpected site' );
global $wpdb;
$table = trb_resource_tables()['usage'];
$jobs = $wpdb->get_results( "SELECT id,release_id FROM $table WHERE provider='acrcloud' AND provider_reference<>'' AND service IN ('cover_song_scan','fingerprinting_exact') AND ((status='error' AND last_error IN ('ACR_DUAL_ENGINE_MISMATCH_2_EXPECTED_2','ACR_DUAL_ENGINE_MISMATCH_1_EXPECTED_1')) OR (status='processing' AND last_error='ACR_PROVIDER_PROCESSING' AND attempts>=30))" );
$releases = array();
foreach ( $jobs as $job ) {
    if ( trb_release_is_inactive( (int) $job->release_id ) ) continue;
    trb_resource_poll_dual_acr_job( (int) $job->id );
    $releases[(int) $job->release_id] = true;
}
$summary = array( 'checked' => count($releases), 'analysis_pending' => 0, 'review_required' => 0, 'contract_sent' => 0, 'contract_error' => 0 );
foreach ( array_keys($releases) as $id ) {
    $pipeline = get_post_meta( $id, '_trb_release_pipeline_status', true );
    // Normal dispatch rechecks technical analysis, approval, intake and duplicate guards.
    if ( 'approved' === $pipeline ) trb_release_bridge_dispatch( $id );
    $contract = get_post_meta( $id, '_trb_contract_state', true );
    if ( in_array( $contract, array('contract_sent','signed'), true ) ) $summary['contract_sent']++;
    elseif ( 'analysis_in_progress' === $pipeline ) $summary['analysis_pending']++;
    elseif ( 'approved' === $pipeline ) $summary['contract_error']++;
    else $summary['review_required']++;
}
// Public deployment logs contain aggregate outcomes only, never customer records or tokens.
echo 'ACR_RECOVERY ' . wp_json_encode( $summary ) . "\n";
