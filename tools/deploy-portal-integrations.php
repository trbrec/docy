<?php
/** Reviewed MU receivers and vendor compatibility sources, with durable recovery. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
set_exception_handler( static function() { fwrite( STDERR, "Portal integration deployment or recovery unconfirmed.\n" ); exit( 1 ); } );
require __DIR__ . '/signature-compatibility.php';
require __DIR__ . '/integration-syntax-preflight.php';
require __DIR__ . '/release-file-transaction.php';
$mode = $argv[1] ?? ''; $revision = $argv[2] ?? ''; $bundle = dirname( __DIR__ );
if ( ! in_array( $mode, array( 'preflight', 'apply', 'rollback' ), true ) || ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || isset( $argv[3] ) ) exit( 2 );
$content = '/home/customer/www/artist.trbrec.com/public_html/wp-content';
$backup = dirname( $content, 2 ) . '/private/portal-audit-' . $revision . '/files';
if ( trim( (string) @file_get_contents( $bundle . '/.trb-deployed-sha' ) ) !== $revision ) throw new RuntimeException( 'Deployment revision mismatch.' );
$lock = null;
if ( 'preflight' !== $mode ) {
    $lock = fopen( dirname( $content, 2 ) . '/private/portal-integrations.lock', 'c' );
    if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) throw new RuntimeException( 'Integration deployment busy.' );
}
if ( 'rollback' === $mode ) {
    trb_release_file_rollback( $backup, $content );
    echo "Portal integration recovery verified; no database restored.\n";
    exit;
}
$baselines = array( 'trb-crm-sync.php' => 'cf824a04f552f058260f63aa34c3df4c75d5cf964b0dcfa727f334819ca254fe', 'trb-login-cache-guard.php' => '7359820b2cf6c93f262f67b81d1018869b01d0df63c71257658380541708b202', 'trb-z-crm-release-sync-r26.php' => '449a7bc2db0835e3faa392399c76e9129ffe6f233ec7eb27a0ab4bfd7c5d381c', 'trb-crm-sync-storage.php' => null );
$changes = array(); $sources = array();
// Installed independently by QA: retain this guard even if a release is reverted.
$guard = $content . '/mu-plugins/trb-release-deploy-guard.php';
$guard_source = $bundle . '/integrations/portal-mu-plugins/trb-release-deploy-guard.php';
if ( is_link( $guard ) || ! is_file( $guard ) || ! hash_equals( hash_file( 'sha256', $guard_source ), hash_file( 'sha256', $guard ) ) ) throw new RuntimeException( 'Independent release guard unconfirmed.' );
$sources[$guard] = file_get_contents( $guard_source );
foreach ( $baselines as $name => $baseline ) {
    $target = $content . '/mu-plugins/' . $name; $candidate = $bundle . '/integrations/portal-mu-plugins/' . $name;
    trb_release_file_check_path( $target, $content );
    if ( ! is_file( $candidate ) || is_link( $candidate ) ) throw new RuntimeException( 'Unsafe integration source.' );
    $next = file_get_contents( $candidate ); $original = is_file( $target ) ? file_get_contents( $target ) : null;
    if ( $original !== $next && ( null === $baseline ? null !== $original : null === $original || ! hash_equals( $baseline, hash( 'sha256', $original ) ) ) ) throw new RuntimeException( 'Installed MU source diverged.' );
    $sources[$target] = $next;
    if ( $original !== $next ) $changes[$target] = compact( 'target', 'next', 'original' ) + array( 'path' => $target );
}
foreach ( trb_signature_compatibility_manifest() as $name => $spec ) {
    $target = $content . '/plugins/' . $name;
    trb_release_file_check_path( $target, $content );
    if ( ! is_file( $target ) ) throw new RuntimeException( 'Signature model unavailable.' );
    $original = file_get_contents( $target ); $next = trb_signature_verified_property_patch( $original, $spec );
    $sources[$target] = $next;
    if ( $original !== $next ) $changes[$target] = compact( 'target', 'next', 'original' ) + array( 'path' => $target );
}
trb_integration_syntax_preflight( $sources );
if ( 'preflight' === $mode ) { echo "Portal integrations preflight passed without persistent rollback copies.\n"; exit; }
// Install the common storage helper first; recovery restores receivers before it.
uksort( $changes, static fn( $a, $b ) => ( str_ends_with( $a, '/trb-crm-sync-storage.php' ) ? -1 : 0 ) <=> ( str_ends_with( $b, '/trb-crm-sync-storage.php' ) ? -1 : 0 ) );
trb_release_file_install( array_values( $changes ), $backup, $content );
$verification_directory = dirname( $backup );
if ( ! is_dir( $verification_directory ) && ! mkdir( $verification_directory, 0700, true ) ) throw new RuntimeException( 'Integration verification storage unavailable.' );
if ( is_link( $verification_directory ) ) throw new RuntimeException( 'Unsafe integration verification storage.' );
$verification = array();
foreach ( $sources as $target => $source ) $verification[] = array( 'target' => $target, 'next_sha256' => hash( 'sha256', $source ) );
trb_release_file_replace( $verification_directory . '/verified-sources.json', json_encode( $verification, JSON_THROW_ON_ERROR ), 0600 );
echo "Portal integrations installed with verified bytes and durable private recovery.\n";
