<?php
/** Licensed document API qualification; one owned private canary, no option/license changes. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
$mode = $argv[1] ?? ''; $run = $argv[2] ?? ''; $revision = $argv[3] ?? '';
if ( ! in_array( $mode, array( 'verify', 'cleanup' ), true ) || ! preg_match( '/^[a-f0-9]{16}$/D', $run ) || ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || isset( $argv[4] ) || trim( (string) @file_get_contents( dirname( __DIR__ ) . '/.trb-deployed-sha' ) ) !== $revision ) exit( 2 );
$result = array( 'completed' => false, 'checks' => 0, 'license_modified' => false, 'settings_modified' => false, 'artist_messages' => 0 );
$buffer = ob_get_level(); ob_start(); $reserve = str_repeat( 'x', 65536 ); $cleanup = null;
register_shutdown_function( static function() use ( &$result, &$cleanup, &$reserve, $buffer ) {
    $reserve = null;
    try { if ( $cleanup ) $cleanup(); } catch ( Throwable $ignored ) { $result['cleanup_confirmed'] = false; $result['completed'] = false; }
    while ( ob_get_level() > $buffer ) ob_end_clean();
    echo json_encode( $result ) . "\n";
} );
set_exception_handler( static function( $error ) use ( &$result ) {
    $result['failure'] = array( 'class' => get_class( $error ), 'file' => basename( $error->getFile() ), 'line' => $error->getLine() );
    exit( 1 );
} );
define( 'DISABLE_WP_CRON', true ); define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
// WordPress supports preinitialized hooks: block delivery before plugin bootstrap.
$GLOBALS['wp_filter']['pre_wp_mail'][PHP_INT_MAX][] = array( 'function' => static function() { return true; }, 'accepted_args' => 1 );
$GLOBALS['wp_filter']['pre_http_request'][PHP_INT_MAX][] = array( 'function' => static function() { return new WP_Error( 'qa_network_blocked', 'External HTTP disabled.' ); }, 'accepted_args' => 1 );
require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
$artist = get_user_by( 'login', 'trb_audit_20261010' );
if ( ! $artist || ! str_starts_with( $artist->user_registered, '2026-10-10 ' ) || user_can( $artist, 'manage_options' ) || empty( $artist->roles ) ) throw new RuntimeException( 'Known ordinary fictional artist required.' );
if ( ! function_exists( 'ezd_is_premium' ) || ! ezd_is_premium() || ! function_exists( 'ezd_internal_doc_security' ) || ! is_callable( array( '\eazyDocsPro\Frontend\Login_Popup', 'locked_block_html' ) ) ) throw new RuntimeException( 'Native licensed document APIs required.' );
$result['native_premium_license_active'] = true;
$slug = 'trb-audit-doc-' . $run; $canary = 'TRB_PRIVATE_CANARY_' . $run;
$cleanup = static function() use ( $slug, $canary, $artist, &$result ) {
    global $wpdb;
    // Cleanup must also find the private canary while the current viewer is anonymous.
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_author, post_content, post_status FROM {$wpdb->posts} WHERE post_type='docs' AND post_name=%s", $slug ) );
    foreach ( $rows as $row ) {
        if ( (int) $row->post_author !== (int) $artist->ID || $row->post_content !== $canary || ! in_array( $row->post_status, array( 'private', 'trash' ), true ) ) throw new RuntimeException( 'Unexpected document ownership.' );
        if ( ! wp_delete_post( $row->ID, true ) || get_post( $row->ID ) ) throw new RuntimeException( 'Owned document removal failed.' );
    }
    $result['cleanup_confirmed'] = 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='docs' AND post_name=%s", $slug ) );
    if ( ! $result['cleanup_confirmed'] ) throw new RuntimeException( 'Document cleanup unconfirmed.' );
};
if ( 'cleanup' === $mode ) { $cleanup(); $result['completed'] = true; exit; }
$cleanup();
$prior_options = get_option( 'eazydocs_settings', null );
$check = static function( $condition ) use ( &$result ) { if ( ! $condition ) throw new RuntimeException( 'Licensed document assertion failed.' ); ++$result['checks']; };
wp_set_current_user( $artist->ID );
$result['stage'] = 'create_owned_document';
$doc = wp_insert_post( array( 'post_type' => 'docs', 'post_status' => 'private', 'post_name' => $slug, 'post_title' => 'Collaudo riservato temporaneo', 'post_content' => $canary, 'post_author' => $artist->ID, 'comment_status' => 'closed', 'ping_status' => 'closed' ), true );
if ( is_wp_error( $doc ) ) $result['insert_error_code'] = sanitize_key( $doc->get_error_code() );
$check( ! is_wp_error( $doc ) && $doc > 0 );
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$check( count( $admins ) === 1 );
wp_set_current_user( (int) $admins[0] );
$context = new WP_Query( array( 'post_type' => 'docs', 'p' => $doc, 'post_status' => 'private' ) );
$check( $context->have_posts() );
$options = array();
$option_filter = static function() use ( &$options ) { return $options; };
add_filter( 'pre_option_eazydocs_settings', $option_filter, PHP_INT_MAX );
try {
    $result['stage'] = 'licensed_role_matrix';
    $role = $artist->roles[0];
    foreach ( array(
        array( array( 'private_doc_access_type' => 'all_users' ), array( false, true, true ) ),
        array( array( 'private_doc_access_type' => 'specific_roles', 'private_doc_allowed_roles' => array( 'editor' ) ), array( false, false, true ) ),
        array( array( 'private_doc_access_type' => 'specific_roles', 'private_doc_allowed_roles' => array( $role ) ), array( false, true, true ) ),
        array( array( 'private_doc_user_restriction' => array( 'private_doc_all_user' => '1' ) ), array( false, true, true ) ),
        array( array( 'private_doc_user_restriction' => array( 'private_doc_roles' => array( $role ) ) ), array( false, true, true ) ),
    ) as list( $options, $expected ) ) {
        $check( get_option( 'eazydocs_settings' ) === $options );
        foreach ( array( 0, $artist->ID, (int) $admins[0] ) as $index => $viewer ) {
            wp_set_current_user( $viewer ); $GLOBALS['wp_query'] = clone $context; $GLOBALS['wp_query']->rewind_posts();
            $check( $GLOBALS['wp_query']->have_posts() ); $GLOBALS['wp_query']->the_post();
            ob_start(); try { $allowed = ezd_internal_doc_security( $doc ); } finally { $html = ob_get_clean(); }
            $check( ( true === $allowed ) === $expected[$index] );
            $check( ! str_contains( $html, $canary ) && ( $expected[$index] || '' !== trim( $html ) ) );
        }
    }
    wp_set_current_user( 0 ); $public_query = new WP_Query( array( 'post_type' => 'docs', 'p' => $doc ) );
    $check( ! $public_query->have_posts() );
    // Exercise premium-only password form customization with synthetic labels.
    $result['stage'] = 'premium_password_form';
    $options = array( 'protected_doc_form' => 'eazydocs-form', 'protected_form_title' => 'Accesso riservato di prova', 'protected_form_subtitle' => 'Documento temporaneo di collaudo' );
    $form = ezd_password_form( '', get_post( $doc ) );
    $check( str_contains( $form, 'Accesso riservato di prova' ) && str_contains( $form, 'Documento temporaneo di collaudo' ) && ! str_contains( $form, $canary ) );
    wp_set_current_user( $artist->ID ); $GLOBALS['wp_query'] = clone $context; $GLOBALS['wp_query']->rewind_posts(); $GLOBALS['wp_query']->the_post();
    $options = array( 'private_doc_mode' => 'login' );
    $result['stage'] = 'premium_private_gate';
    $check( ! current_user_can( 'read_private_docs' ) );
    ob_start(); try { ezd_private_docs_access(); } finally { $gate = ob_get_clean(); }
    $check( ! str_contains( $gate, $canary ) );
    $check( ezd_is_premium() && ! current_user_can( 'manage_options' ) );
} finally {
    remove_filter( 'pre_option_eazydocs_settings', $option_filter, PHP_INT_MAX );
    $check( get_option( 'eazydocs_settings', null ) === $prior_options );
    $cleanup();
}
$result['completed'] = true;
$result['stage'] = 'complete';
$result['scope'] = 'Native licensed APIs, five role configurations, denial renderer, premium password form and login gate. Synthetic settings are process-local filters; no persistent settings or license changes.';
