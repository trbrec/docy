<?php
/** Public bundle policy and commit verification, with synthetic GitHub responses only. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require_once dirname( __DIR__ ) . '/inc/trb-deployment-file-policy.php';
$private = array(
    'tools/test-synthetic.php', 'tools/test-synthetic.py', 'tools/test-embedded/nested.php',
    'tests/fixture.php', 'tests/php/fixture.php', 'integrations/site-studio/test-synthetic.php',
    'tools/fixtures-onboarding-entry-mail.php', 'tools/site-preview.test.cjs',
    'integrations/site-studio/artist-cards.test.cjs', 'integrations/site-studio/editor-browser.test.cjs',
);
foreach ( $private as $path ) if ( trb_portal_release_path_is_public( $path ) ) throw new RuntimeException( 'A development fixture is public.' );
foreach ( array( 'functions.php', 'tools/run-site-studio-cron.php', 'tools/qa-installed-stack.php', 'tools/.htaccess', 'assets/tests/synthetic.js', 'inc/test-example.php' ) as $path ) {
    if ( ! trb_portal_release_path_is_public( $path ) ) throw new RuntimeException( 'Runtime or hosting tooling was excluded.' );
}
$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/deploy.yml' );
if ( ! preg_match( '/^.*rsync -a --checksum .*$/m', $workflow, $copy ) ) throw new RuntimeException( 'Public install command missing.' );
preg_match_all( "/--exclude='([^']+)'/", $copy[0], $exclusions );
$actual = array_values( array_diff( $exclusions[1], array( '.trb-deployed-sha' ) ) );
if ( $actual !== trb_portal_release_private_patterns() ) throw new RuntimeException( 'Public copy and manifest policies differ.' );
if ( ! preg_match( '/^.*rsync -az --checksum .*$/m', $workflow, $upload ) || str_contains( $upload[0], '/tools/test-' ) || str_contains( $upload[0], '/tests/' ) ) throw new RuntimeException( 'Private candidate must retain the complete test suite.' );

$base = sys_get_temp_dir() . '/trb-policy-' . bin2hex( random_bytes( 8 ) );
mkdir( $base, 0700 );
define( 'ABSPATH', $base . '/' );
class WP_Error { public function __construct( public $code, ...$args ) {} }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function get_template_directory() { return ABSPATH; }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function sanitize_key( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_get( ...$args ) { return array( 'body' => json_encode( $GLOBALS['synthetic_commit'], JSON_THROW_ON_ERROR ) ); }
function wp_remote_retrieve_response_code( $response ) { return 200; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
require dirname( __DIR__ ) . '/inc/trb-auto-deploy.php';
$sha = str_repeat( 'a', 40 );
try {
    file_put_contents( $base . '/functions.php', '<?php /* synthetic runtime */' );
    $files = array( array( 'filename' => 'functions.php', 'status' => 'modified', 'sha' => trb_docy_local_git_blob_sha( $base . '/functions.php' ) ) );
    foreach ( array_merge( $private, array( '.github/workflows/example.yml' ) ) as $path ) $files[] = array( 'filename' => $path, 'status' => 'modified', 'sha' => str_repeat( 'b', 40 ) );
    $GLOBALS['synthetic_commit'] = array( 'sha' => $sha, 'files' => $files );
    $result = trb_docy_verify_deployed_commit( $sha );
    if ( $result !== array( 'verified' => true, 'checked' => 1 ) ) throw new RuntimeException( 'Private test changes required public copies.' );
    file_put_contents( $base . '/functions.php', '<?php /* unexpected runtime */' );
    $result = trb_docy_verify_deployed_commit( $sha );
    if ( ! is_wp_error( $result ) || $result->code !== 'trb_deploy_stale' ) throw new RuntimeException( 'Runtime drift was ignored with private test changes.' );
    $GLOBALS['synthetic_commit']['files'] = array( array( 'filename' => 'tools/test-synthetic/../../outside.php', 'status' => 'modified' ) );
    $result = trb_docy_verify_deployed_commit( $sha );
    if ( ! is_wp_error( $result ) || $result->code !== 'trb_commit_manifest_invalid' ) throw new RuntimeException( 'Excluded paths bypassed manifest path validation.' );
    echo "Private test policy matches the public copy; commit checks preserve runtime hashes and reject unsafe paths.\n";
} finally {
    unlink( $base . '/functions.php' );
    rmdir( $base );
}
