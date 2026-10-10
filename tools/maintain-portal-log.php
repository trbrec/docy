<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$root = '/home/customer/www/artist.trbrec.com/public_html';
require __DIR__ . '/release-file-transaction.php';
if ( is_link( $root ) || realpath( $root ) !== $root ) throw new RuntimeException( 'Unsafe portal log root.' );
$revision = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || trim( (string) @file_get_contents( dirname( __DIR__ ) . '/.trb-deployed-sha' ) ) !== $revision ) exit( 2 );
$config = $root . '/.htaccess';
if ( ! is_file( $config ) || is_link( $config ) ) throw new RuntimeException( 'Root access rules unavailable.' );
$current = file_get_contents( $config );
$marker = '# BEGIN TRB private error log';
if ( ! str_contains( $current, $marker ) ) {
    $private = dirname( $root ) . '/private/error-log-archive';
    if ( ! is_dir( $private ) && ! mkdir( $private, 0700, true ) ) throw new RuntimeException( 'Private log storage unavailable.' );
    if ( is_link( $private ) || realpath( $private ) !== $private ) throw new RuntimeException( 'Unsafe private log storage.' );
    $backup = $private . '/root-htaccess-' . hash( 'sha256', $current ) . '.previous';
    if ( is_link( $backup ) ) throw new RuntimeException( 'Unsafe access rules backup.' );
    if ( ! is_file( $backup ) ) trb_release_file_replace( $backup, $current, 0600 );
    if ( ! hash_equals( hash( 'sha256', $current ), hash_file( 'sha256', $backup ) ) || file_get_contents( $config ) !== $current ) throw new RuntimeException( 'Access rules rollback unavailable or configuration changed.' );
    $next = $current . "\n" . $marker . "\n<FilesMatch \"^php_errorlog(?:[.].*)?$\">\nRequire all denied\n</FilesMatch>\n# END TRB private error log\n";
    trb_release_file_replace( $config, $next, fileperms( $config ) & 0777 );
}
define( 'ABSPATH', $root . '/' ); define( 'DAY_IN_SECONDS', 86400 );
function add_action() {}
require dirname( __DIR__ ) . '/inc/trb-log-maintenance.php';
$result = trb_portal_maintain_error_log();
echo json_encode( $result, JSON_THROW_ON_ERROR ) . "\n";
if ( true !== ( $result['completed'] ?? false ) ) exit( 1 );
