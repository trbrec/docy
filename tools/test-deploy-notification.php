<?php
/** An authenticated deploy notification may never install unverified code. */
$base = sys_get_temp_dir() . '/trb-notification-test-' . bin2hex( random_bytes( 8 ) );
mkdir( $base . '/public_html/theme', 0700, true );
define( 'ABSPATH', $base . '/public_html/' );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_Error { public function __construct( public $code, ...$args ) {} }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function get_template_directory() { return ABSPATH . 'theme'; }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function untrailingslashit( $path ) { return rtrim( $path, '/' ); }
function sanitize_key( $value ) { return $value; }
function sanitize_text_field( $value ) { return $value; }
function update_option( $key, $value, ...$args ) { $GLOBALS['options'][$key] = $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_get( ...$args ) { throw new RuntimeException( 'Unverified notification reached network.' ); }
require __DIR__ . '/../inc/trb-auto-deploy.php';
$sha = str_repeat( 'a', 40 );
try {
    foreach ( array( '', '../outside', $sha ) as $revision ) {
        $result = trb_docy_deploy_verified_sha( $revision );
        if ( ! is_wp_error( $result ) || isset( $GLOBALS['options'][TRB_DOCY_DEPLOYED_SHA_OPTION] ) ) throw new RuntimeException( 'Unverified deployment acknowledged.' );
    }
    file_put_contents( ABSPATH . 'theme/.trb-deployed-sha', $sha . "\n" );
    $result = trb_docy_deploy_verified_sha( $sha );
    if ( ! is_wp_error( $result ) || $result->code !== 'trb_ssh_release_pending' ) throw new RuntimeException( 'Marker without completed bundle was accepted.' );
    echo "Invalid revisions and marker-only notifications refused without installing code or acknowledging completion.\n";
} finally { @unlink( ABSPATH . 'theme/.trb-deployed-sha' ); rmdir( ABSPATH . 'theme' ); rmdir( ABSPATH ); rmdir( $base ); }
