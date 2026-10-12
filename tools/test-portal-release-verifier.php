<?php
/** Execute the real Linux hosting verifier across the WordPress global-state boundary. */
if ( PHP_OS_FAMILY === 'Windows' ) { echo "Hosting verifier fixture runs on Linux in PHP 8.2/8.3 CI.\n"; exit; }
$fixture = sys_get_temp_dir() . '/trb-verifier-' . bin2hex( random_bytes( 8 ) );
$sha = str_repeat( 'a', 40 ); $release = $fixture . '/private/portal-release-' . $sha;
$candidate = $release . '/candidate'; $theme = $fixture . '/public_html/wp-content/themes/docy';
mkdir( $candidate . '/tools', 0700, true ); mkdir( $theme . '/tools', 0700, true );
mkdir( $fixture . '/private/portal-audit-' . $sha, 0700, true );
$source = str_replace( "'/home/customer/www/artist.trbrec.com'", var_export( $fixture, true ), file_get_contents( __DIR__ . '/verify-portal-release.php' ) );
file_put_contents( $candidate . '/tools/verify-portal-release.php', $source );
copy( __DIR__ . '/portal-release-backup.php', $candidate . '/tools/portal-release-backup.php' );
file_put_contents( $candidate . '/functions.php', '<?php /* fictional theme */' );
file_put_contents( $candidate . '/.trb-deployed-sha', $sha . "\n" );
foreach ( array( 'functions.php', '.trb-deployed-sha', 'tools/verify-portal-release.php', 'tools/portal-release-backup.php' ) as $path ) copy( $candidate . '/' . $path, $theme . '/' . $path );
file_put_contents( $fixture . '/private/portal-audit-' . $sha . '/verified-sources.json', '[]' );
$bootstrap = <<<'PHP'
<?php
define( 'ABSPATH', __DIR__ . '/' );
// wp-settings.php loads themes with $theme, then unsets it; plugins also share this scope.
$theme = '/wordpress/theme-loop'; $files = array( 'plugin-local-file' ); $revision = 'plugin-local-revision';
unset( $theme, $wp_theme );
function get_template_directory() { return ABSPATH . 'wp-content/themes/docy'; }
function trb_docy_deployment_is_ssh_only() { return true; }
function trb_portal_launch_campaign_is_ready() { return false; }
function has_action( $hook, $callback ) { return $hook === 'trb_portal_daily_log_maintenance' ? 10 : false; }
function wp_get_scheduled_event( $hook ) { return (object) array( 'schedule' => 'daily' ); }
function trb_webdav_request() {}
function trb_portal_handle_artist_profile() {}
function trb_crm_sync_atomic() {}
function trb_portal_maintain_error_log() {}
$wpdb = new class {
    public $users = 'users', $usermeta = 'usermeta', $posts = 'posts', $postmeta = 'postmeta', $options = 'options';
    public function prepare( $query, $table ) { return $table; }
    public function get_var( $table ) { return $table === 'users' && is_file( ABSPATH . 'nonatomic.fixture' ) ? 'MyISAM' : 'InnoDB'; }
};
PHP;
file_put_contents( $fixture . '/public_html/wp-load.php', $bootstrap );
$run = static function() use ( $candidate, $sha ) {
    $lines = array(); exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $candidate . '/tools/verify-portal-release.php' ) . ' ' . escapeshellarg( $sha ), $lines, $code );
    return array( $code, implode( "\n", $lines ) );
};
try {
    list( $code, $output ) = $run();
    $receipt = json_decode( (string) @file_get_contents( $release . '/complete.json' ), true );
    if ( 0 !== $code || ( $receipt['verified'] ?? false ) !== true || ( $receipt['revision'] ?? '' ) !== $sha || ( $receipt['theme_files'] ?? 0 ) !== 4 ) throw new RuntimeException( 'WordPress bootstrap corrupted the release state or receipt.' );
    unlink( $release . '/complete.json' );
    file_put_contents( $fixture . '/public_html/nonatomic.fixture', 'synthetic unsupported engine' );
    list( $code, $output ) = $run(); $failure = json_decode( $output, true );
    if ( 1 !== $code || ( $failure['stage'] ?? '' ) !== 'atomic-storage' || ( $failure['table_label'] ?? '' ) !== 'users' || is_file( $release . '/complete.json' ) ) throw new RuntimeException( 'Unsupported storage was certified or lost its safe diagnostic.' );
    unlink( $fixture . '/public_html/nonatomic.fixture' );
    file_put_contents( $theme . '/functions.php', '<?php /* concurrent change */' );
    list( $code, $output ) = $run(); $failure = json_decode( $output, true );
    if ( 1 !== $code || ( $failure['stage'] ?? '' ) !== 'theme-bytes' || ( $failure['path_fingerprint'] ?? '' ) !== substr( hash( 'sha256', 'functions.php' ), 0, 12 ) || is_file( $release . '/complete.json' ) || str_contains( $output, $fixture ) ) throw new RuntimeException( 'Changed source was certified or a private path leaked.' );
    echo "Real verifier preserves state across WordPress globals, refuses source drift and nontransactional storage, and emits bounded diagnostics.\n";
} finally {
    if ( dirname( realpath( $fixture ) ?: '' ) !== realpath( sys_get_temp_dir() ) || ! preg_match( '/^trb-verifier-[a-f0-9]{16}$/D', basename( $fixture ) ) ) throw new RuntimeException( 'Verifier fixture cleanup path mismatch.' );
    $entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $entries as $entry ) $entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
    rmdir( $fixture );
}
