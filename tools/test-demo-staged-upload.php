<?php
/** Check the demo-specific staged manifest and server-side file limit. */
$source = file_get_contents( dirname( __DIR__ ) . '/inc/trb-artist-portal.php' );
$start = strpos( $source, 'function trb_portal_demo_upload_item(' );
$end = strpos( $source, 'function trb_portal_demo_finish(', $start );
if ( false === $start || false === $end ) throw new RuntimeException( 'Demo upload functions missing.' );
$guardStart = strpos( $source, 'function trb_portal_prepare_private_directory(' );
$guardEnd = strpos( $source, "\n}\n", $guardStart ) + 3;
eval( substr( $source, $guardStart, $guardEnd - $guardStart ) );
eval( substr( $source, $start, $end - $start ) );
define( 'MB_IN_BYTES', 1048576 );
define( 'ABSPATH', sys_get_temp_dir() . '/trb-demo-staged-test-' . getmypid() . '/' );
if ( ! is_dir( ABSPATH . 'wp-admin/includes' ) ) mkdir( ABSPATH . 'wp-admin/includes', 0700, true );
file_put_contents( ABSPATH . 'wp-admin/includes/file.php', '<?php' );
class WP_Error { public function __construct( public $code, public $message = '' ) {} }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_unslash( $value ) { return $value; }
function sanitize_file_name( $value ) { return basename( $value ); }
function trb_portal_staged_release_upload_item( $name ) { $path = ABSPATH . 'staged/part'; clearstatcache( true, $path ); return array( 'name' => $name === 'trb_demo_audio' ? 'demo.mp3' : 'words.txt', 'type' => $name === 'trb_demo_audio' ? 'audio/mpeg' : 'text/plain', 'tmp_name' => $path, 'size' => filesize( $path ), 'error' => 0, '_trb_staged' => true ); }
function trb_portal_release_is_staged_path( $path ) { return ABSPATH . 'staged/part' === $path; }
function wp_upload_dir() { return array( 'basedir' => sys_get_temp_dir() . '/trb-demo-staged-test-' . getmypid() ); }
function wp_mkdir_p( $directory ) { return is_dir( $directory ) || mkdir( $directory, 0700, true ); }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function add_filter(...$args) {}
function remove_filter(...$args) {}
function wp_generate_uuid4() { return 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'; }
function wp_delete_file( $path ) { unlink( $path ); }
function wp_handle_sideload( $file, $options ) { $GLOBALS['sideload_called'] = is_file( $file['tmp_name'] ) && str_ends_with( $file['tmp_name'], '.mp3' ); $GLOBALS['sideload_options'] = $options; return array( 'file' => wp_upload_dir()['basedir'] . '/trb-demo-private/demo.mp3', 'type' => $file['type'] ); }
function wp_handle_upload( $file, $options ) { throw new RuntimeException( 'Staged file entered ordinary upload path.' ); }
function check_demo_upload( $ok, $reason ) { if ( ! $ok ) throw new RuntimeException( $reason ); }
register_shutdown_function( function() { $dir = wp_upload_dir()['basedir'] . '/trb-demo-private'; @unlink( $dir . '/.htaccess' ); @rmdir( $dir ); @unlink( ABSPATH . 'staged/part' ); @rmdir( ABSPATH . 'staged' ); @unlink( ABSPATH . 'wp-admin/includes/file.php' ); @rmdir( ABSPATH . 'wp-admin/includes' ); @rmdir( ABSPATH . 'wp-admin' ); @rmdir( ABSPATH ); } );
mkdir( ABSPATH . 'staged', 0700, true );
file_put_contents( ABSPATH . 'staged/part', str_repeat( 'A', 3 * MB_IN_BYTES ) );
$_POST['trb_staged_uploads_json'] = json_encode( array( 'trb_demo_audio' => array( 'key' => 'f2000' ) ) );
check_demo_upload( ! trb_portal_demo_upload_item( 'trb_demo_audio' ), 'Swapped slot was accepted.' );
$_POST['trb_staged_uploads_json'] = json_encode( array( 'trb_demo_audio' => array( 'key' => 'f2001' ) ) );
$item = trb_portal_demo_upload_item( 'trb_demo_audio' );
check_demo_upload( $item['name'] === 'demo.mp3', 'Audio manifest was not recovered.' );
check_demo_upload( is_wp_error( trb_portal_store_demo_file( 'trb_demo_audio', array( 'mp3' => 'audio/mpeg' ), 2 * MB_IN_BYTES, $item ) ), 'Oversized staged file was accepted.' );
$saved = trb_portal_store_demo_file( 'trb_demo_audio', array( 'mp3' => 'audio/mpeg' ), 25 * MB_IN_BYTES, $item );
check_demo_upload( is_array( $saved ) && $saved['size'] === 3 * MB_IN_BYTES && ! empty( $GLOBALS['sideload_called'] ), 'Staged MP3 did not use the safe server-side upload handler.' );
$GLOBALS['sideload_called'] = false;
$rulesPath = wp_upload_dir()['basedir'] . '/trb-demo-private/.htaccess';
$privateRules = file_get_contents( $rulesPath );
file_put_contents( $rulesPath, "Require all granted\n" );
check_demo_upload( is_wp_error( trb_portal_store_demo_file( 'trb_demo_audio', array( 'mp3' => 'audio/mpeg' ), 25 * MB_IN_BYTES, $item ) ) && ! $GLOBALS['sideload_called'], 'Demo storage proceeded without private access protection.' );
file_put_contents( $rulesPath, $privateRules );
// A real text fixture, rather than reusing three megabytes of one MP3 placeholder byte.
file_put_contents( ABSPATH . 'staged/part', str_repeat( "Testo sintetico del demo.\n", 10000 ) );
$_POST['trb_staged_uploads_json'] = json_encode( array( 'trb_demo_text' => array( 'key' => 'f2000' ) ) );
$text = trb_portal_demo_upload_item( 'trb_demo_text' );
$stored_text = trb_portal_store_demo_file( 'trb_demo_text', array( 'txt' => 'text/plain' ), 3 * MB_IN_BYTES, $text );
check_demo_upload( is_array( $stored_text ) && ( $GLOBALS['sideload_options']['test_type'] ?? true ) === false, 'Staged TXT did not validate its real MIME before the private sideload.' );
echo "PASS demo staged manifest, size validation and sideload\n";
