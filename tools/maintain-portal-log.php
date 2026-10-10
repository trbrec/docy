<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$root = '/home/customer/www/artist.trbrec.com/public_html';
$revision = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || trim( (string) @file_get_contents( dirname( __DIR__ ) . '/.trb-deployed-sha' ) ) !== $revision ) exit( 2 );
$config = $root . '/.htaccess';
if ( ! is_file( $config ) || is_link( $config ) ) throw new RuntimeException( 'Root access rules unavailable.' );
$current = file_get_contents( $config );
$marker = '# BEGIN TRB private error log';
if ( ! str_contains( $current, $marker ) ) {
    $private = dirname( $root ) . '/private/error-log-archive';
    if ( ! is_dir( $private ) && ! mkdir( $private, 0700, true ) ) throw new RuntimeException( 'Private log storage unavailable.' );
    $backup = $private . '/root-htaccess-' . hash( 'sha256', $current ) . '.previous';
    if ( ! is_file( $backup ) && file_put_contents( $backup, $current, LOCK_EX ) !== strlen( $current ) ) throw new RuntimeException( 'Access rules rollback unavailable.' );
    $next = $current . "\n" . $marker . "\n<FilesMatch \"^php_errorlog(?:[.].*)?$\">\nRequire all denied\n</FilesMatch>\n# END TRB private error log\n";
    if ( file_put_contents( $config . '.log-next', $next, LOCK_EX ) !== strlen( $next ) || ! rename( $config . '.log-next', $config ) ) throw new RuntimeException( 'Log access guard failed.' );
}
define( 'ABSPATH', $root . '/' ); define( 'DAY_IN_SECONDS', 86400 );
function add_action() {}
require dirname( __DIR__ ) . '/inc/trb-log-maintenance.php';
$result = trb_portal_maintain_error_log();
echo json_encode( $result, JSON_THROW_ON_ERROR ) . "\n";
if ( true !== ( $result['completed'] ?? false ) ) exit( 1 );
