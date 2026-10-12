<?php
/** Read-only hosting proof. Outputs counts and booleans, never credentials or identities. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
set_exception_handler( static function() { fwrite( STDERR, "Production release readback unconfirmed.\n" ); exit( 1 ); } );
$revision = $argv[1] ?? '';
$mode = $argv[2] ?? 'verify';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || ! in_array( $mode, array( 'verify', 'preflight' ), true ) || isset( $argv[3] ) ) exit( 2 );
$site = '/home/customer/www/artist.trbrec.com';
$theme = $site . '/public_html/wp-content/themes/docy';
$release = $site . '/private/portal-release-' . $revision;
$candidate = $release . '/candidate';
$readJson = static function( $path ) {
    if ( is_link( $path ) || ! is_file( $path ) || filesize( $path ) > 4194304 ) throw new RuntimeException( 'Receipt unavailable.' );
    $value = json_decode( file_get_contents( $path ), true, 64, JSON_THROW_ON_ERROR );
    if ( ! is_array( $value ) ) throw new RuntimeException( 'Invalid receipt.' );
    return $value;
};
foreach ( array( $site, $theme, $release, $candidate ) as $directory ) if ( is_link( $directory ) || realpath( $directory ) !== $directory ) throw new RuntimeException( 'Unexpected release directory.' );
if ( 'preflight' === $mode ) {
    $checks = array( 'read_only' => true, 'personal_data_exported' => false, 'diagnostic_only' => true, 'revision' => $revision );
    $checks['complete_receipt_present'] = is_file( $release . '/complete.json' );
    $checks['snapshot_manifest_present'] = is_file( $release . '/theme.previous/manifest.json' );
    $installed = trim( (string) @file_get_contents( $theme . '/.trb-deployed-sha' ) );
    $checks['installed_revision'] = preg_match( '/^[a-f0-9]{40}$/D', $installed ) ? $installed : null;
    require $candidate . '/tools/portal-release-backup.php';
    foreach ( array( 'installed_theme_inventory' => $theme, 'candidate_inventory' => $candidate ) as $key => $directory ) {
        try { $checks[$key] = count( trb_portal_release_files( $directory ) ); }
        catch ( RuntimeException $error ) { $checks[$key] = false; $checks[$key . '_error'] = $error->getMessage(); }
    }
    if ( $checks['snapshot_manifest_present'] ) {
        $snapshot = $readJson( $release . '/theme.previous/manifest.json' );
        $checks['rollback_theme_bytes_verified'] = ( $snapshot['revision'] ?? '' ) === $revision;
        foreach ( $snapshot['previous'] ?? array() as $path => $spec ) {
            if ( ! trb_portal_release_path_is_safe( $path ) ) throw new RuntimeException( 'Unsafe snapshot diagnostic path.' );
            foreach ( array( $theme . '/' . $path, $release . '/theme.previous/files/' . $path ) as $file ) if ( is_link( $file ) || ! is_file( $file ) || ! hash_equals( $spec['sha256'], hash_file( 'sha256', $file ) ) ) $checks['rollback_theme_bytes_verified'] = false;
        }
        foreach ( array_diff_key( $snapshot['next'] ?? array(), $snapshot['previous'] ?? array() ) as $path => $spec ) if ( file_exists( $theme . '/' . $path ) || is_link( $theme . '/' . $path ) ) $checks['rollback_theme_bytes_verified'] = false;
        $checks['rollback_theme_files_checked'] = count( $snapshot['previous'] ?? array() );
    }
    require_once $candidate . '/tools/release-file-transaction.php';
    foreach ( array( 'rollback_store_bytes_verified' => array( $site . '/private/onboarding-' . $revision . '/store-files', '/home/customer/www/store.trbrec.com/public_html/wp-content/themes' ), 'rollback_integration_bytes_verified' => array( $site . '/private/portal-audit-' . $revision . '/files', $site . '/public_html/wp-content' ) ) as $key => $location ) {
        $checks[$key] = true; $checks[$key . '_files_checked'] = 0;
        if ( ! is_file( $location[0] . '/manifest.json' ) ) continue;
        foreach ( $readJson( $location[0] . '/manifest.json' ) as $item ) {
            trb_release_file_check_path( $item['path'], $location[1] );
            $current = is_file( $item['path'] ) ? hash_file( 'sha256', $item['path'] ) : null;
            if ( $current !== $item['previous'] || null === $item['previous'] && file_exists( $item['path'] ) ) $checks[$key] = false;
            $checks[$key . '_files_checked']++;
        }
    }
    foreach ( array( 'canonical_preflight' => array( 'preflight-portal-release.php' ), 'support_health' => array( 'deploy-giulia-support.php', $revision ), 'integration_preflight' => array( 'deploy-portal-integrations.php', 'preflight', $revision ), 'readiness' => array( 'onboarding-readiness.php', $revision ) ) as $key => $arguments ) {
        $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $candidate . '/tools/' . array_shift( $arguments ) );
        foreach ( $arguments as $argument ) $command .= ' ' . escapeshellarg( $argument );
        $output = array(); exec( $command . ' 2>/dev/null', $output, $code );
        $checks[$key] = 0 === $code;
        if ( 'readiness' === $key ) $checks['readiness_flags'] = json_decode( implode( "\n", $output ), true );
    }
    $canonical = $readJson( '/home/customer/www/crm.trbrec.com/private/canonical-release.json' );
    require $candidate . '/tools/crm-module-source-guard.php';
    $required = array( 'app/GiuliaSupport.php', 'assets/onboarding.css' );
    foreach ( glob( $candidate . '/integrations/onboarding/crm/*.php' ) as $file ) $required[] = 'app/' . basename( $file );
    try { trb_crm_module_source_guard( '/home/customer/www/crm.trbrec.com/public_html', '/home/customer/www/crm.trbrec.com/private/canonical-release.json', $required ); }
    catch ( RuntimeException $error ) { $checks['canonical_guard_error'] = $error->getMessage(); }
    $checks['canonical_required_modules_absent'] = array_values( array_diff( $required, array_column( $canonical['files'] ?? array(), 'path' ) ) );
    $checks['canonical_drift_paths'] = array();
    foreach ( $canonical['files'] ?? array() as $entry ) {
        $path = $entry['path'] ?? '';
        if ( ! preg_match( '#^[a-zA-Z0-9_./-]+$#D', $path ) || str_starts_with( $path, '/' ) || preg_match( '#(^|/)\.\.?(/|$)#', $path ) ) throw new RuntimeException( 'Unexpected CRM diagnostic path.' );
        $file = '/home/customer/www/crm.trbrec.com/public_html/' . $path;
        if ( is_link( $file ) || ! is_file( $file ) || ! hash_equals( $entry['sha256'], hash_file( 'sha256', $file ) ) ) $checks['canonical_drift_paths'][] = $path;
    }
    echo json_encode( $checks, JSON_THROW_ON_ERROR ) . "\n"; exit;
}
$receipt = $readJson( $release . '/complete.json' );
if ( ( $receipt['verified'] ?? false ) !== true || ( $receipt['revision'] ?? '' ) !== $revision || trim( (string) file_get_contents( $theme . '/.trb-deployed-sha' ) ) !== $revision ) throw new RuntimeException( 'Publication not acknowledged.' );
require $candidate . '/tools/portal-release-backup.php';
$files = trb_portal_release_files( $candidate );
foreach ( $files as $path => $spec ) if ( is_link( $theme . '/' . $path ) || ! is_file( $theme . '/' . $path ) || ! hash_equals( $spec['sha256'], hash_file( 'sha256', $theme . '/' . $path ) ) ) throw new RuntimeException( 'Published file drift.' );
if ( count( $files ) !== ( $receipt['theme_files'] ?? 0 ) ) throw new RuntimeException( 'Publication count mismatch.' );
$backup = $release . '/theme.previous';
$manifest = $readJson( $backup . '/manifest.json' );
if ( ( $manifest['revision'] ?? '' ) !== $revision || empty( $manifest['previous']['functions.php'] ) ) throw new RuntimeException( 'Rollback manifest unavailable.' );
$backupFiles = trb_portal_release_files( $backup . '/files' );
if ( array_keys( $backupFiles ) !== array_keys( $manifest['previous'] ) ) throw new RuntimeException( 'Rollback file inventory changed.' );
foreach ( $backupFiles as $path => $spec ) if ( ! hash_equals( $manifest['previous'][$path]['sha256'], $spec['sha256'] ) ) throw new RuntimeException( 'Rollback bytes changed.' );
$integrations = $readJson( $site . '/private/portal-audit-' . $revision . '/verified-sources.json' );
foreach ( $integrations as $entry ) if ( ! str_starts_with( $entry['target'] ?? '', $site . '/public_html/wp-content/' ) || is_link( $entry['target'] ) || ! is_file( $entry['target'] ) || ! hash_equals( $entry['next_sha256'], hash_file( 'sha256', $entry['target'] ) ) ) throw new RuntimeException( 'Integration drift.' );
if ( count( $integrations ) !== ( $receipt['integration_files'] ?? -1 ) ) throw new RuntimeException( 'Integration count mismatch.' );
if ( file_exists( $release . '/artist-communications.json' ) || is_link( $release . '/artist-communications.json' ) ) throw new RuntimeException( 'Premature artist communications qualification.' );
foreach ( array( 'trb-launch-release-guard.php', 'trb-legacy-field-preservation.php' ) as $name ) {
    $file = $site . '/public_html/wp-content/mu-plugins/' . $name;
    if ( is_link( $file ) || ! is_file( $file ) || ! hash_equals( hash_file( 'sha256', $candidate . '/integrations/portal-mu-plugins/' . $name ), hash_file( 'sha256', $file ) ) ) throw new RuntimeException( 'Independent guard drift.' );
}
$qaWorkspaces = array_merge( glob( '/tmp/trb-portal-stack.*', GLOB_ONLYDIR ), glob( $site . '/private/portal-stack-qa.*', GLOB_ONLYDIR ) );
$qaEndpoints = array_merge( glob( $site . '/public_html/trb-audit-http-*' ), glob( $site . '/public_html/trb-audit-bootstrap-*' ) );
if ( $qaWorkspaces || $qaEndpoints ) throw new RuntimeException( 'Temporary QA paths remain.' );
$crm = '/home/customer/www/crm.trbrec.com';
$canonical = $readJson( $crm . '/private/canonical-release.json' );
if ( ( $canonical['verified'] ?? false ) !== true || ( $canonical['revision'] ?? '' ) !== 'b45aa1d0b868235ed7098c97149145d674ec5c6b' || count( $canonical['files'] ?? array() ) !== 53 ) throw new RuntimeException( 'Canonical CRM mismatch.' );
foreach ( $canonical['files'] as $entry ) {
    $path = $entry['path'] ?? '';
    if ( ! preg_match( '#^[a-zA-Z0-9_./-]+$#D', $path ) || str_starts_with( $path, '/' ) || preg_match( '#(^|/)\.\.?(/|$)#', $path ) ) throw new RuntimeException( 'Unexpected CRM path.' );
    $file = $crm . '/public_html/' . $path;
    if ( is_link( $file ) || ! is_file( $file ) || ! hash_equals( $entry['sha256'], hash_file( 'sha256', $file ) ) ) throw new RuntimeException( 'Canonical CRM drift.' );
}
require $crm . '/public_html/app/Core.php';
\TrbCrm\Env::load( $crm . '/public_html/.env' );
require $crm . '/public_html/app/OnboardingTransport.php';
require $crm . '/public_html/app/OnboardingContractCatalog.php';
$health = \TrbCrm\OnboardingTransport::script( (string) \TrbCrm\Env::get( 'CONTRACT_APPS_SCRIPT_URL', '' ), (string) \TrbCrm\Env::get( 'CONTRACT_APPS_SCRIPT_SECRET', '' ), array( 'action' => 'crm_onboarding_health' ) );
if ( ( $health['version'] ?? '' ) !== '2026.2' || ( $health['historical_sources']['verified'] ?? false ) !== true || ( $health['historical_sources']['versions'] ?? 0 ) !== 2 || ( $health['otp_configured'] ?? false ) !== true ) throw new RuntimeException( 'Published Google generator unconfirmed.' );
$models = \TrbCrm\OnboardingContractCatalog::all();
foreach ( $models as $model ) {
    $actual = $health['models'][$model['template_key']] ?? array();
    if ( ( $actual['id'] ?? '' ) !== $model['template_document_id'] || ( $actual['anchors'] ?? false ) !== true || ! hash_equals( $model['source_sha256'], (string) ( $actual['sha256'] ?? '' ) ) ) throw new RuntimeException( 'Contract source drift.' );
}
define( 'SHORTINIT', true ); define( 'DISABLE_WP_CRON', true );
ob_start(); require $site . '/public_html/wp-load.php'; ob_end_clean();
global $wpdb;
$qaTables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( 'trbqa_' ) . '%' ) );
if ( ! is_array( $qaTables ) || $qaTables || $wpdb->last_error ) throw new RuntimeException( 'Temporary QA tables remain or could not be checked.' );
echo json_encode( array( 'read_only' => true, 'personal_data_exported' => false, 'revision' => $revision, 'published_theme_files_verified' => count( $files ), 'rollback_backup_files_verified' => count( $backupFiles ), 'integration_files_verified' => count( $integrations ), 'independent_guard_files_verified' => 2, 'temporary_qa_workspaces_remaining' => 0, 'temporary_qa_endpoints_remaining' => 0, 'temporary_qa_tables_remaining' => 0, 'artist_communications_waiting_for_full_audit' => true, 'canonical_crm_revision' => $canonical['revision'], 'canonical_crm_files_verified' => count( $canonical['files'] ), 'google_generator_authenticated_health_verified' => true, 'current_contract_sources_verified' => count( $models ), 'historical_contract_sources_verified' => 2, 'checked_at_utc' => gmdate( 'c' ) ), JSON_THROW_ON_ERROR ) . "\n";
