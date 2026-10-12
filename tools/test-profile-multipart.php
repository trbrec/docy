<?php
/** Real PHP multipart reception and WordPress MIME/move handling, isolated from production. */
if ( PHP_SAPI === 'cli-server' ) {
    if ( ( $_SERVER['REMOTE_ADDR'] ?? '' ) !== '127.0.0.1' || ! getenv( 'TRB_PROFILE_HTTP_ROOT' ) ) { http_response_code( 404 ); exit; }
    define( 'ABSPATH', rtrim( getenv( 'TRB_WP_TEST_ROOT' ), '/' ) . '/' );
    define( 'WPINC', 'wp-includes' );
    define( 'WP_CONTENT_DIR', getenv( 'TRB_PROFILE_HTTP_ROOT' ) . '/uploads' );
    define( 'WP_CONTENT_URL', 'http://localhost/uploads' );
    define( 'MB_IN_BYTES', 1048576 );
    define( 'WP_DEBUG', true );
    require ABSPATH . 'wp-includes/compat.php';
    if ( is_file( ABSPATH . 'wp-includes/compat-utf8.php' ) ) require ABSPATH . 'wp-includes/compat-utf8.php';
    if ( is_file( ABSPATH . 'wp-includes/utf8.php' ) ) require ABSPATH . 'wp-includes/utf8.php';
    require ABSPATH . 'wp-includes/plugin.php';
    require ABSPATH . 'wp-includes/class-wp-error.php';
    require ABSPATH . 'wp-includes/formatting.php';
    require ABSPATH . 'wp-includes/shortcodes.php';
    require ABSPATH . 'wp-includes/media.php';
    $core_tokens = token_get_all( file_get_contents( ABSPATH . 'wp-includes/functions.php' ) );
    $core_wanted = array( 'wp_check_filetype_and_ext', 'wp_check_filetype', 'wp_get_mime_types', 'get_allowed_mime_types', 'wp_unique_filename', '_wp_check_existing_file_names', '_wp_check_alternate_file_names', 'wp_get_image_mime', 'wp_is_stream', 'wp_mkdir_p', 'wp_delete_file', 'wp_generate_uuid4', 'wp_is_json_request' );
    foreach ( $core_tokens as $i => $token ) {
        if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) continue;
        $j = $i + 1;
        while ( isset( $core_tokens[$j] ) && is_array( $core_tokens[$j] ) && T_WHITESPACE === $core_tokens[$j][0] ) $j++;
        if ( ! isset( $core_tokens[$j] ) || ! is_array( $core_tokens[$j] ) || ! in_array( $core_tokens[$j][1], $core_wanted, true ) ) continue;
        $body = ''; $depth = 0; $opened = false;
        for ( $k = $i; $k < count( $core_tokens ); $k++ ) {
            $part = $core_tokens[$k]; $body .= is_array( $part ) ? $part[1] : $part;
            if ( '{' === $part ) { $depth++; $opened = true; }
            if ( is_array( $part ) && in_array( $part[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) $depth++;
            if ( '}' === $part && --$depth === 0 && $opened ) break;
        }
        eval( $body );
    }
    function __( $value ) { return $value; }
    function is_wp_error( $value ) { return $value instanceof WP_Error; }
    function wp_convert_hr_to_bytes( $value ) { return 16 * MB_IN_BYTES; }
    function _x( $value, $context ) { return $value; }
    function get_option( $key, $default = false ) {
        return array( 'upload_path' => getenv( 'TRB_PROFILE_HTTP_ROOT' ) . '/uploads', 'upload_url_path' => 'http://localhost/uploads', 'uploads_use_yearmonth_folders' => 0, 'blog_charset' => 'UTF-8', 'siteurl' => 'http://localhost' )[ $key ] ?? $default;
    }
    function is_multisite() { return false; }
    function get_current_blog_id() { return 1; }
    function current_time( $type ) { return '2026-10-10 12:00:00'; }
    function current_user_can( ...$args ) { return false; }
    function get_current_user_id() { return 198; }
    function wp_rand( $min = 0, $max = 0 ) { return random_int( $min, $max ?: PHP_INT_MAX ); }
    function wp_upload_dir( $time = null ) {
        $base = getenv( 'TRB_PROFILE_HTTP_ROOT' ) . '/uploads';
        return apply_filters( 'upload_dir', array( 'basedir' => $base, 'baseurl' => 'http://localhost/uploads', 'path' => $base, 'url' => 'http://localhost/uploads', 'subdir' => '', 'error' => false ) );
    }
    function wp_get_upload_dir() { return wp_upload_dir(); }
    function get_user_meta( $id, $key, $single ) { return json_decode( file_get_contents( getenv( 'TRB_PROFILE_HTTP_ROOT' ) . '/metadata.json' ), true ); }
    function update_user_meta( $id, $key, $value, $previous = null ) {
        if ( $previous !== get_user_meta( $id, $key, true ) ) return false;
        return file_put_contents( getenv( 'TRB_PROFILE_HTTP_ROOT' ) . '/metadata.json', json_encode( $value ) );
    }
    $tokens = token_get_all( file_get_contents( __DIR__ . '/../inc/trb-artist-portal.php' ) );
    $wanted = array( 'trb_portal_prepare_private_directory', 'trb_portal_private_upload_dir', 'trb_portal_private_profile_files', 'trb_portal_private_profile_file_path', 'trb_portal_private_upload_items', 'trb_portal_delete_retired_profile_files', 'trb_portal_handle_private_profile_uploads' );
    foreach ( $tokens as $i => $token ) {
        if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) continue;
        $j = $i + 1;
        while ( isset( $tokens[$j] ) && is_array( $tokens[$j] ) && T_WHITESPACE === $tokens[$j][0] ) $j++;
        if ( ! isset( $tokens[$j] ) || ! is_array( $tokens[$j] ) || ! in_array( $tokens[$j][1], $wanted, true ) ) continue;
        $body = ''; $depth = 0; $opened = false;
        for ( $k = $i; $k < count( $tokens ); $k++ ) {
            $part = $tokens[$k]; $body .= is_array( $part ) ? $part[1] : $part;
            if ( '{' === $part ) { $depth++; $opened = true; }
            if ( is_array( $part ) && in_array( $part[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) $depth++;
            if ( '}' === $part && --$depth === 0 && $opened ) break;
        }
        eval( $body );
    }
    header( 'Content-Type: application/json' );
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) { echo json_encode( array( 'ready' => true ) ); exit; }
    $received = array();
    foreach ( $_FILES as $field => $file ) $received[$field] = is_uploaded_file( $file['tmp_name'] );
    $moved = array();
    $checked_types = array();
    add_filter( 'wp_handle_upload', static function( $upload ) use ( &$moved ) {
        $moved[] = basename( $upload['file'] );
        return $upload;
    } );
    add_filter( 'wp_check_filetype_and_ext', static function( $checked, $path, $name ) use ( &$checked_types ) {
        $checked_types[ $name ] = $checked['type'];
        return $checked;
    }, 10, 3 );
    $result = trb_portal_handle_private_profile_uploads( 198 );
    echo json_encode( array( 'ok' => ! is_wp_error( $result ), 'error' => is_wp_error( $result ) ? $result->get_error_code() : '', 'received_as_http_upload' => $received, 'moved' => $moved, 'checked_types' => $checked_types, 'files' => get_user_meta( 198, '_trb_artist_private_files', true ) ) );
    exit;
}
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$core = getenv( 'TRB_WP_TEST_ROOT' );
if ( ! $core || ! is_file( $core . '/wp-admin/includes/file.php' ) ) throw new RuntimeException( 'Set TRB_WP_TEST_ROOT to an official WordPress test core.' );
$root = sys_get_temp_dir() . '/trb-profile-http-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/uploads/trb-artist-private', 0700, true );
$port_socket = stream_socket_server( 'tcp://127.0.0.1:0' );
$address = stream_socket_get_name( $port_socket, false ); fclose( $port_socket );
$env = getenv(); $env['TRB_PROFILE_HTTP_ROOT'] = $root;
$command = array( PHP_BINARY );
// Preserve the current CLI extension setup in the child HTTP process.
if ( ! php_ini_loaded_file() ) {
    $command = array_merge( $command, array( '-n', '-d', 'extension_dir=' . ini_get( 'extension_dir' ) ) );
    foreach ( array( 'tokenizer', 'ctype', 'mbstring', 'fileinfo', 'iconv' ) as $extension ) $command = array_merge( $command, array( '-d', 'extension=' . $extension ) );
}
$command = array_merge( $command, array( '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'upload_max_filesize=16M', '-d', 'post_max_size=20M', '-S', $address, __FILE__ ) );
$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $root . '/server.log', 'a' ), 2 => array( 'file', $root . '/server.log', 'a' ) ), $pipes, __DIR__, $env );
if ( ! is_resource( $process ) ) throw new RuntimeException( 'Could not launch the isolated HTTP fixture.' );
function http_upload( $fields = array() ) {
    $curl = curl_init( 'http://' . $GLOBALS['address'] . '/' );
    curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10 ) );
    if ( $fields ) curl_setopt_array( $curl, array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields ) );
    $response = curl_exec( $curl ); $error = curl_error( $curl ); curl_close( $curl );
    $decoded = is_string( $response ) ? json_decode( $response, true ) : null;
    if ( ! is_array( $decoded ) ) throw new RuntimeException( 'Invalid fixture response: ' . $error . ' ' . $response . '\n' . file_get_contents( $GLOBALS['root'] . '/server.log' ) );
    return $decoded;
}
function http_check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
try {
    for ( $attempt = 0; $attempt < 30; $attempt++ ) {
        $socket = @stream_socket_client( 'tcp://' . $address, $errno, $error, 0.1 );
        if ( $socket ) { fclose( $socket ); break; }
        usleep( 50000 );
    }
    http_check( http_upload()['ready'] === true, 'HTTP fixture not ready' );
    file_put_contents( $root . '/uploads/trb-artist-private/old.txt', 'Biography of fictional test artist' );
    $original = array( array( 'id' => 'original', 'group' => 'biography', 'label' => 'Biografia artistica', 'path' => 'trb-artist-private/old.txt', 'name' => 'old.txt' ) );
    file_put_contents( $root . '/metadata.json', json_encode( $original ) );
    file_put_contents( $root . '/new.txt', 'Replacement biography for synthetic Tunisia artist' );
    file_put_contents( $root . '/forged.png', 'This is plain text, not an identity image.' );
    $failed = http_upload( array( 'trb_artist_bio_file' => new CURLFile( $root . '/new.txt', 'text/plain', 'new.txt' ), 'trb_artist_id_front' => new CURLFile( $root . '/forged.png', 'image/png', 'identity.png' ) ) );
    http_check( $failed['received_as_http_upload'] === array( 'trb_artist_bio_file' => true, 'trb_artist_id_front' => true ), 'Files did not pass through real PHP multipart reception' );
    http_check( $failed['moved'] === array( 'new.txt' ), 'Rollback case did not actually move the first file before the second file failed' );
    http_check( $failed['checked_types'] === array( 'new.txt' => 'text/plain', 'identity.png' => false ), 'Second file was not rejected by the real WordPress MIME check' );
    http_check( ! $failed['ok'] && $failed['files'] === $original, 'WordPress MIME rejection did not preserve previous metadata' );
    http_check( file_get_contents( $root . '/uploads/trb-artist-private/old.txt' ) === 'Biography of fictional test artist', 'MIME failure removed previous biography' );
    http_check( count( glob( $root . '/uploads/trb-artist-private/*' ) ) === 1, 'MIME failure left new files on disk' );
    $saved = http_upload( array( 'trb_artist_identity_section' => '1', 'trb_artist_bio_file' => new CURLFile( $root . '/new.txt', 'text/plain', 'new.txt' ) ) );
    http_check( $saved['ok'] && count( $saved['files'] ) === 1, 'Real WordPress biography upload failed: ' . json_encode( $saved ) . '\n' . file_get_contents( $root . '/server.log' ) );
    http_check( ! is_file( $root . '/uploads/trb-artist-private/old.txt' ), 'Successful multipart replacement retained obsolete biography' );
    $retry = http_upload( array( 'trb_artist_bio_file' => new CURLFile( $root . '/new.txt', 'text/plain', 'new.txt' ) ) );
    http_check( $retry['ok'] && $retry['files'] === $saved['files'], 'Multipart retry duplicated the file' );
    echo "Real PHP multipart and WordPress MIME/move: forged image rejection, batch rollback, successful biography replacement and retry passed.\n";
} finally {
    proc_terminate( $process ); fclose( $pipes[0] ); proc_close( $process );
    $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $iterator as $file ) { if ( $file->isDir() ) rmdir( $file->getPathname() ); else unlink( $file->getPathname() ); }
    rmdir( $root );
}
