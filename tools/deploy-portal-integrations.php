<?php
/** CLI deployment of reviewed MU receivers and a narrow vendor compatibility fix. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/signature-compatibility.php';
$mode = $argv[1] ?? ''; $revision = $argv[2] ?? ''; $bundle = dirname( __DIR__ );
if ( ! in_array( $mode, array( 'preflight', 'apply', 'rollback' ), true ) || ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) ) exit( 2 );
$content = '/home/customer/www/artist.trbrec.com/public_html/wp-content';
$private = dirname( $content, 2 ) . '/private/portal-audit-' . $revision;
$baselines = array( 'trb-crm-sync.php' => 'cf824a04f552f058260f63aa34c3df4c75d5cf964b0dcfa727f334819ca254fe', 'trb-login-cache-guard.php' => '7359820b2cf6c93f262f67b81d1018869b01d0df63c71257658380541708b202', 'trb-z-crm-release-sync-r26.php' => '449a7bc2db0835e3faa392399c76e9129ffe6f233ec7eb27a0ab4bfd7c5d381c', 'trb-crm-sync-storage.php' => null );
$changes = array();
foreach ( $baselines as $name => $baseline ) {
    $target = $content . '/mu-plugins/' . $name; $candidate = $bundle . '/integrations/portal-mu-plugins/' . $name;
    if ( is_link( $target ) || ! is_file( $candidate ) || is_link( $candidate ) ) throw new RuntimeException( 'Unsafe integration target.' );
    $next = file_get_contents( $candidate ); $current = is_file( $target ) ? file_get_contents( $target ) : null;
    if ( $current !== $next && ( null === $baseline ? null !== $current : null === $current || ! hash_equals( $baseline, hash( 'sha256', $current ) ) ) ) throw new RuntimeException( 'Installed MU source diverged.' );
    $changes[$target] = $next;
}
foreach ( trb_signature_compatibility_manifest() as $name => $spec ) {
    $target = $content . '/plugins/' . $name;
    if ( ! is_file( $target ) || is_link( $target ) ) throw new RuntimeException( 'Signature model unavailable.' );
    $current = file_get_contents( $target ); $next = trb_signature_property_patch( $current, $spec['class'], $spec['properties'] );
    if ( $current !== $next && ! hash_equals( $spec['baseline'], hash( 'sha256', $current ) ) ) throw new RuntimeException( 'Signature source diverged.' );
    $changes[$target] = $next;
}
if ( ! is_dir( $private ) && ! mkdir( $private, 0700, true ) ) throw new RuntimeException( 'Private rollback storage unavailable.' );
if ( is_link( $private ) ) throw new RuntimeException( 'Unsafe rollback storage.' );
$lock = fopen( dirname( $private ) . '/portal-integrations.lock', 'c' );
if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) throw new RuntimeException( 'Integration deployment busy.' );
$manifest = array();
foreach ( $changes as $target => $next ) {
    $key = hash( 'sha256', $target ); $temporary = $private . '/' . $key . '.next';
    if ( file_put_contents( $temporary, $next, LOCK_EX ) !== strlen( $next ) ) throw new RuntimeException( 'Integration staging failed.' );
    exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $temporary ) . ' 2>&1', $output, $status );
    if ( 0 !== $status ) throw new RuntimeException( 'Integration lint failed.' );
    $manifest[$key] = array( 'target' => $target, 'exists' => is_file( $target ), 'next_sha256' => hash( 'sha256', $next ) );
    if ( is_file( $target ) && ! is_file( $private . '/' . $key . '.previous' ) && ! copy( $target, $private . '/' . $key . '.previous' ) ) throw new RuntimeException( 'Rollback copy failed.' );
}
$manifest_path = $private . '/manifest.json';
if ( ! is_file( $manifest_path ) && false === file_put_contents( $manifest_path, json_encode( $manifest, JSON_THROW_ON_ERROR ), LOCK_EX ) ) throw new RuntimeException( 'Rollback manifest failed.' );
if ( 'preflight' === $mode ) { echo "Portal integrations preflight passed.\n"; exit; }
if ( trim( (string) @file_get_contents( $bundle . '/.trb-deployed-sha' ) ) !== $revision ) throw new RuntimeException( 'Deployment revision mismatch.' );
$original = json_decode( file_get_contents( $manifest_path ), true, 32, JSON_THROW_ON_ERROR );
$restore = static function() use ( $original, $private ) {
    foreach ( $original as $key => $entry ) {
        if ( ! $entry['exists'] ) { if ( is_file( $entry['target'] ) ) unlink( $entry['target'] ); continue; }
        $temp = $entry['target'] . '.audit-restore';
        if ( ! copy( $private . '/' . $key . '.previous', $temp ) || ! rename( $temp, $entry['target'] ) ) throw new RuntimeException( 'Integration restore failed.' );
    }
};
if ( 'rollback' === $mode ) { $restore(); echo "Portal integrations restored.\n"; exit; }
// Install the shared helper before either receiver can require it.
uksort( $changes, static fn( $a, $b ) => ( str_ends_with( $a, '/trb-crm-sync-storage.php' ) ? -1 : 0 ) <=> ( str_ends_with( $b, '/trb-crm-sync-storage.php' ) ? -1 : 0 ) );
try {
    foreach ( $changes as $target => $next ) {
        $temp = $target . '.audit-next';
        if ( file_put_contents( $temp, $next, LOCK_EX ) !== strlen( $next ) || ! rename( $temp, $target ) || ! hash_equals( hash( 'sha256', $next ), hash_file( 'sha256', $target ) ) ) throw new RuntimeException( 'Integration install failed.' );
        if ( function_exists( 'opcache_invalidate' ) ) opcache_invalidate( $target, true );
    }
} catch ( Throwable $error ) { $restore(); throw $error; }
echo "Portal integrations installed with verified hashes and private rollback copies.\n";
