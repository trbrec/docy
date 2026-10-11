<?php
/** Actual installed document/membership APIs against exclusively synthetic rows. */
if ( PHP_SAPI !== 'cli' || ! defined( 'ABSPATH' ) ) exit;
function trb_qa_installed_document_access( $artist_id ) {
    global $wpdb, $wp_query, $post;
    if ( wp_get_environment_type() !== 'local' || ! preg_match( '/^trbqa_[a-f0-9]{16}_$/D', $wpdb->prefix ) ) throw new RuntimeException( 'Document qualification requires the isolated database.' );
    if ( ! function_exists( 'ezd_internal_doc_security' ) || ! function_exists( 'ezd_get_opt' ) || ! post_type_exists( 'docs' ) ) throw new RuntimeException( 'Actual installed document APIs unavailable.' );
    $artist = get_userdata( $artist_id );
    if ( ! $artist || ! str_ends_with( $artist->user_email, '@example.invalid' ) || $artist->has_cap( 'manage_options' ) ) throw new RuntimeException( 'An ordinary fictional artist is required.' );
    $prior_options = get_option( 'eazydocs_settings', null );
    $prior_user = get_current_user_id(); $prior_query = $wp_query; $prior_post = $post ?? null;
    $canary = 'QA_PRIVATE_DOC_' . bin2hex( random_bytes( 12 ) );
    $doc = wp_insert_post( array( 'post_type' => 'docs', 'post_status' => 'private', 'post_title' => 'Documento riservato fittizio', 'post_content' => $canary, 'post_author' => $artist_id ), true );
    if ( is_wp_error( $doc ) || ! $doc ) throw new RuntimeException( 'Fictional private document unavailable.' );
    $result = array( 'completed' => false, 'checks' => 0, 'engine' => 'actual installed EazyDocs and WordPress/MySQL', 'premium_plugin_class_loaded' => class_exists( 'EZD_EazyDocsPro' ), 'premium_license_active_in_isolation' => (bool) ezd_is_premium(), 'premium_locked_renderer_available' => is_callable( array( '\eazyDocsPro\Frontend\Login_Popup', 'locked_block_html' ) ), 'artist_messages' => 0 );
    $check = static function( $condition, $message ) use ( &$result ) { if ( ! $condition ) throw new RuntimeException( $message ); ++$result['checks']; };
    try {
        $admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
        $check( count( $admin ) === 1, 'Fictional administrator unavailable.' );
        // Load one known synthetic document as administrator, then exercise the
        // actual plugin boundary under each viewer. Query access is tested separately.
        wp_set_current_user( (int) $admin[0] );
        $context = new WP_Query( array( 'post_type' => 'docs', 'p' => $doc, 'post_status' => 'private' ) );
        $check( $context->have_posts(), 'The synthetic document context is unavailable.' );
        $role = $artist->roles[0];
        foreach ( array(
            array( array( 'private_doc_access_type' => 'all_users' ), array( false, true, true ) ),
            array( array( 'private_doc_access_type' => 'specific_roles', 'private_doc_allowed_roles' => array( 'editor' ) ), array( false, false, true ) ),
            array( array( 'private_doc_access_type' => 'specific_roles', 'private_doc_allowed_roles' => array( $role ) ), array( false, true, true ) ),
            array( array( 'private_doc_user_restriction' => array( 'private_doc_all_user' => '1' ) ), array( false, true, true ) ),
            array( array( 'private_doc_user_restriction' => array( 'private_doc_roles' => array( $role ) ) ), array( false, true, true ) ),
        ) as list( $options, $expected ) ) {
            update_option( 'eazydocs_settings', $options, false );
            $check( get_option( 'eazydocs_settings' ) === $options, 'Synthetic document settings did not persist.' );
            foreach ( array( 0, $artist_id, (int) $admin[0] ) as $index => $viewer ) {
                wp_set_current_user( $viewer );
                $wp_query = clone $context;
                $wp_query->rewind_posts();
                $check( $wp_query->have_posts(), 'The explicit synthetic private document query failed.' );
                $wp_query->the_post();
                ob_start();
                try { $allowed = ezd_internal_doc_security( $doc ); } finally { $html = ob_get_clean(); }
                $check( ( true === $allowed ) === $expected[$index], 'Installed document role boundary failed.' );
                $check( ! str_contains( $html, $canary ) && ( $expected[$index] || '' !== trim( $html ) ), 'The installed denial renderer is missing or exposed private content.' );
            }
        }
        wp_set_current_user( 0 );
        $public_query = new WP_Query( array( 'post_type' => 'docs', 'p' => $doc ) );
        $check( ! $public_query->have_posts(), 'Native anonymous queries exposed the private document.' );
        wp_set_current_user( $artist_id );
        $check( ! current_user_can( 'manage_options' ), 'Document qualification changed artist privileges.' );
        $result['completed'] = true;
    } finally {
        null === $prior_options ? delete_option( 'eazydocs_settings' ) : update_option( 'eazydocs_settings', $prior_options, false );
        wp_delete_post( $doc, true );
        wp_set_current_user( $prior_user ); $wp_query = $prior_query; $post = $prior_post;
        $check( get_option( 'eazydocs_settings', null ) === $prior_options && ! get_post( $doc ), 'Synthetic document/settings cleanup failed.' );
    }
    $result['synthetic_rows_removed'] = true;
    $result['full_premium_membership_paths_qualified'] = false;
    return $result;
}
