<?php
/** Production file batch logic against a disposable filesystem, with fault injection. */
error_reporting( E_ALL );
set_error_handler( static function( $severity, $message ) { throw new RuntimeException( $message ); } );
$root = sys_get_temp_dir() . '/trb-profile-files-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/wp-admin/includes', 0700, true );
mkdir( $root . '/uploads/trb-artist-private', 0700, true );
file_put_contents( $root . '/wp-admin/includes/file.php', '<?php' );
define( 'ABSPATH', $root . '/' );
define( 'MB_IN_BYTES', 1048576 );
class WP_Error { public function __construct( public $code ) {} public function get_error_code() { return $this->code; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_current_user_id() { return 198; }
function get_user_meta( $id, $key, $single ) { return $GLOBALS['metadata']; }
function update_user_meta( $id, $key, $value, $previous = null ) {
    if ( $GLOBALS['fail_write'] || $previous !== $GLOBALS['metadata'] ) return false;
    $GLOBALS['metadata'] = $value; return true;
}
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['root'] . '/uploads' ); }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function wp_max_upload_size() { return 16 * MB_IN_BYTES; }
function sanitize_file_name( $name ) { return basename( $name ); }
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0700, true ); }
function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }
function wp_delete_file( $path ) { if ( file_exists( $path ) ) unlink( $path ); }
function add_filter( ...$args ) { $GLOBALS['filter_active'] = true; }
function remove_filter( ...$args ) { $GLOBALS['filter_active'] = false; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function size_format( $value, $decimals = 0 ) { return $value . ' B'; }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function trb_portal_private_file_url( $id, $preview = false ) { return '/private-file?id=' . rawurlencode( $id ); }
function wp_handle_upload( $file, $options ) {
    if ( ++$GLOBALS['upload_count'] === $GLOBALS['fail_upload'] ) return array( 'error' => 'Injected disk failure' );
    $target = $GLOBALS['root'] . '/uploads/trb-artist-private/new-' . $GLOBALS['upload_count'] . '-' . $file['name'];
    copy( $file['tmp_name'], $target );
    return array( 'file' => $target, 'type' => $file['type'] );
}
$source = file_get_contents( __DIR__ . '/../inc/trb-artist-portal.php' );
$wanted = array( 'trb_portal_prepare_private_directory', 'trb_portal_private_profile_files', 'trb_portal_private_profile_file_path', 'trb_portal_private_upload_items', 'trb_portal_delete_retired_profile_files', 'trb_portal_handle_private_profile_uploads', 'trb_portal_render_private_files' );
$tokens = token_get_all( $source );
foreach ( $tokens as $i => $token ) {
    if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) continue;
    $j = $i + 1;
    while ( isset( $tokens[$j] ) && is_array( $tokens[$j] ) && T_WHITESPACE === $tokens[$j][0] ) $j++;
    if ( ! isset( $tokens[$j] ) || ! is_array( $tokens[$j] ) || ! in_array( $tokens[$j][1], $wanted, true ) ) continue;
    $body = ''; $depth = 0; $opened = false;
    for ( $k = $i; $k < count( $tokens ); $k++ ) {
        $part = $tokens[$k]; $body .= is_array( $part ) ? $part[1] : $part;
        if ( '{' === $part ) { $depth++; $opened = true; }
        if ( '}' === $part && --$depth === 0 && $opened ) break;
    }
    eval( $body );
}
function check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); $GLOBALS['checks']++; }
function fixture( $input, $content, $error = UPLOAD_ERR_OK ) {
    $path = $GLOBALS['root'] . '/' . $input . '.tmp'; file_put_contents( $path, $content );
    $_FILES[$input] = array( 'name' => $input . '.txt', 'type' => 'text/plain', 'tmp_name' => $path, 'error' => $error, 'size' => strlen( $content ) );
}
function reset_fixture() {
    foreach ( glob( $GLOBALS['root'] . '/uploads/trb-artist-private/new-*' ) as $path ) unlink( $path );
    file_put_contents( $GLOBALS['root'] . '/uploads/trb-artist-private/old.txt', 'Original biography' );
    $GLOBALS['metadata'] = array( array( 'id' => 'old', 'group' => 'biography', 'label' => 'Biografia artistica', 'name' => 'old.txt', 'path' => 'trb-artist-private/old.txt' ) );
    $GLOBALS['fail_write'] = false; $GLOBALS['fail_upload'] = 0; $GLOBALS['upload_count'] = 0;
    $_FILES = array(); $_POST = array();
}
function assert_preserved( $before ) {
    check( $GLOBALS['metadata'] === $before, 'Failure changed previous metadata' );
    check( file_get_contents( $GLOBALS['root'] . '/uploads/trb-artist-private/old.txt' ) === 'Original biography', 'Failure lost original bytes' );
    check( ! glob( $GLOBALS['root'] . '/uploads/trb-artist-private/new-*' ), 'Failure left newly uploaded files' );
}
$GLOBALS['root'] = $root; $GLOBALS['checks'] = 0;
try {
    foreach ( $wanted as $name ) check( function_exists( $name ), 'Missing production function: ' . $name );
    reset_fixture(); $before = $GLOBALS['metadata'];
    fixture( 'trb_artist_bio_file', 'Replacement biography' ); fixture( 'trb_artist_id_front', 'Synthetic document' );
    $GLOBALS['fail_upload'] = 2;
    check( is_wp_error( trb_portal_handle_private_profile_uploads( 198 ) ), 'Second upload failure reported as success' );
    assert_preserved( $before ); check( ! $GLOBALS['filter_active'], 'Upload directory filter leaked' );

    reset_fixture(); $before = $GLOBALS['metadata']; fixture( 'trb_artist_bio_file', 'Replacement biography' );
    $GLOBALS['fail_write'] = true;
    check( is_wp_error( trb_portal_handle_private_profile_uploads( 198 ) ), 'Metadata write failure reported as success' ); assert_preserved( $before );

    foreach ( array( UPLOAD_ERR_PARTIAL, UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_CANT_WRITE ) as $error ) {
        reset_fixture(); $before = $GLOBALS['metadata']; fixture( 'trb_artist_id_front', 'Incomplete document', $error );
        $_POST['trb_artist_remove_files'] = array( 'old' );
        check( is_wp_error( trb_portal_handle_private_profile_uploads( 198 ) ), 'PHP upload failure ignored' ); assert_preserved( $before );
    }
    reset_fixture(); $before = $GLOBALS['metadata'];
    $_FILES['trb_artist_photos'] = array( 'name' => array( array( 'nested.png' ) ) );
    check( is_wp_error( trb_portal_handle_private_profile_uploads( 198 ) ), 'Malformed multipart accepted' ); assert_preserved( $before );

    reset_fixture(); $before = $GLOBALS['metadata']; fixture( 'trb_artist_bio_file', 'Replacement biography' );
    $_FILES['trb_artist_bio_file']['size']++;
    check( is_wp_error( trb_portal_handle_private_profile_uploads( 198 ) ), 'Truncated file accepted' ); assert_preserved( $before );

    reset_fixture(); $before = $GLOBALS['metadata']; $_POST = array( 'trb_artist_identity_section' => '1', 'trb_artist_remove_files' => array( 'old' ) );
    check( is_wp_error( trb_portal_handle_private_profile_uploads( 198 ) ), 'Last biography could be removed from identity form' ); assert_preserved( $before );

    reset_fixture(); fixture( 'trb_artist_bio_file', 'Replacement biography' );
    check( true === trb_portal_handle_private_profile_uploads( 198 ), 'Successful replacement rejected' );
    check( count( $GLOBALS['metadata'] ) === 1, 'Replacement left duplicate metadata' );
    check( ! file_exists( $root . '/uploads/trb-artist-private/old.txt' ), 'Retired biography was retained' );
    check( file_get_contents( trb_portal_private_profile_file_path( $GLOBALS['metadata'][0] ) ) === 'Replacement biography', 'Replacement bytes not retained' );
    fixture( 'trb_artist_bio_file', 'Replacement biography' ); $count = $GLOBALS['upload_count'];
    check( true === trb_portal_handle_private_profile_uploads( 198 ) && $count === $GLOBALS['upload_count'], 'Identical retry created another physical file' );

    reset_fixture(); $GLOBALS['metadata'][0]['label'] = 'Old biography label';
    fixture( 'trb_artist_bio_file', 'Replacement biography' );
    check( true === trb_portal_handle_private_profile_uploads( 198 ), 'Legacy biography replacement rejected' );
    check( count( $GLOBALS['metadata'] ) === 1 && ! file_exists( $root . '/uploads/trb-artist-private/old.txt' ), 'Legacy label retained an obsolete biography' );

    reset_fixture(); $duplicate = $GLOBALS['metadata'][0]; $duplicate['id'] = 'duplicate'; $GLOBALS['metadata'][] = $duplicate;
    $_POST['trb_artist_remove_files'] = array( 'duplicate' );
    check( true === trb_portal_handle_private_profile_uploads( 198 ), 'Duplicate reference removal failed' );
    check( file_exists( $root . '/uploads/trb-artist-private/old.txt' ), 'Removing duplicate reference deleted shared bytes' );

    reset_fixture(); unset( $GLOBALS['metadata'][0]['id'] ); $before = $GLOBALS['metadata'];
    check( ! empty( trb_portal_private_profile_files( 198 )[0]['id'] ), 'Legacy file ID not normalized' );
    check( $before === $GLOBALS['metadata'], 'Reading file list wrote user metadata' );
    check( false === trb_portal_private_profile_file_path( array( 'path' => '../wp-admin/includes/file.php' ) ), 'Path escaped private directory' );

    reset_fixture(); $missing = $GLOBALS['metadata'][0];
    $missing['id'] = 'missing'; $missing['path'] = 'trb-artist-private/missing.txt'; $missing['size'] = 500;
    $GLOBALS['metadata'][] = $missing;
    ob_start(); trb_portal_render_private_files( 'biography' ); $html = ob_get_clean();
    $document = new DOMDocument(); $document->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
    check( $document->getElementsByTagName( 'a' )->length === 1, 'Missing file advertised a download despite stale size metadata' );
    check( str_contains( $document->textContent, 'File non disponibile: carica una nuova copia.' ), 'Missing file displayed a successful upload status' );
    $missing['group'] = 'photo'; $GLOBALS['metadata'] = array( $missing );
    ob_start(); trb_portal_render_private_files( 'photo' ); $html = ob_get_clean();
    check( ! str_contains( $html, '<img' ) && ! str_contains( $html, '<a ' ), 'Missing photo displayed a broken preview or download' );
    echo $GLOBALS['checks'] . " profile file transaction assertions passed.\n";
} finally {
    $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $iterator as $file ) { if ( $file->isDir() ) rmdir( $file->getPathname() ); else unlink( $file->getPathname() ); }
    rmdir( $root );
}
