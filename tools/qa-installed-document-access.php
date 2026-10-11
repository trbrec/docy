<?php
/** Actual installed document/membership APIs against exclusively synthetic rows. */
if ( PHP_SAPI !== 'cli' || ! defined( 'ABSPATH' ) ) exit;
function trb_qa_installed_document_access( $artist_id, $work ) {
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
        // Each complete template needs a fresh WordPress process: get_header()
        // and get_footer() intentionally require their files only once.
        $result['full_template_cases'] = array();
        foreach ( array( 'allowed-role', 'other-role' ) as $rule ) {
            foreach ( array( 'anonymous', 'artist', 'administrator' ) as $viewer ) {
                $command = array( PHP_BINARY, '-d', 'display_errors=0', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=mail,curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client', __DIR__ . '/qa-installed-stack.php', 'document-template', $work, (string) $doc, $rule, $viewer );
                $process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'file', $work . '/document-template-private.log', 'a' ) ), $pipes );
                if ( ! is_resource( $process ) ) throw new RuntimeException( 'Document template process unavailable.' );
                fclose( $pipes[0] ); $reply = stream_get_contents( $pipes[1] ); fclose( $pipes[1] ); $status = proc_close( $process );
                $render = json_decode( $reply, true );
                if ( 0 !== $status || ! is_array( $render ) || true !== ( $render['completed'] ?? false ) || isset( $render['fatal'] ) ) $GLOBALS['qa_stack_result']['document_template_failure'] = array( 'rule' => $rule, 'viewer' => $viewer, 'status' => $status, 'diagnostics' => $render['diagnostics'] ?? array(), 'fatal' => $render['fatal'] ?? null, 'stage' => $render['document_template_stage'] ?? 'bootstrap' );
                $check( 0 === $status && is_array( $render ) && true === ( $render['completed'] ?? false ) && ! isset( $render['fatal'] ), 'The complete document template failed.' );
                $result['full_template_cases'][] = array( 'rule' => $rule, 'viewer' => $viewer, 'checks' => $render['document_template']['checks'], 'content_visible' => $render['document_template']['content_visible'], 'theme_diagnostics' => $render['document_template']['theme_diagnostics'], 'diagnostics' => $render['diagnostics'], 'diagnostic_frames' => $render['diagnostic_frames'] ?? array() );
                $GLOBALS['qa_stack_result']['document_template_cases'] = $result['full_template_cases'];
            }
        }
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

/** One complete render in the exclusively synthetic database, with actual plugins. */
function trb_qa_installed_document_template( $artist_id, $doc_id, $rule, $viewer ) {
    global $wpdb, $wp_query, $post;
    if ( wp_get_environment_type() !== 'local' || ! preg_match( '/^trbqa_[a-f0-9]{16}_$/D', $wpdb->prefix ) || ! in_array( $rule, array( 'allowed-role', 'other-role' ), true ) || ! in_array( $viewer, array( 'anonymous', 'artist', 'administrator' ), true ) ) throw new RuntimeException( 'Unsafe document render context.' );
    $artist = get_userdata( $artist_id ); $document = get_post( $doc_id );
    if ( ! $artist || ! str_ends_with( $artist->user_email, '@example.invalid' ) || $artist->has_cap( 'manage_options' ) || ! $document || $document->post_type !== 'docs' || $document->post_status !== 'private' || (int) $document->post_author !== (int) $artist_id || ! preg_match( '/^QA_PRIVATE_DOC_[a-f0-9]{24}$/D', $document->post_content ) ) throw new RuntimeException( 'Owned synthetic document required.' );
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    if ( count( $admins ) !== 1 ) throw new RuntimeException( 'Synthetic administrator required.' );
    $roles = $rule === 'allowed-role' ? array( $artist->roles[0] ) : array( 'editor' );
    $options = array( 'private_doc_access_type' => 'specific_roles', 'private_doc_allowed_roles' => $roles );
    $prior_options = get_option( 'eazydocs_settings', null );
    $option_filter = static function() use ( $options ) { return $options; };
    add_filter( 'pre_option_eazydocs_settings', $option_filter, PHP_INT_MAX );
    wp_set_current_user( (int) $admins[0] );
    $wp_query = new WP_Query( array( 'post_type' => 'docs', 'p' => $doc_id, 'post_status' => 'private' ) );
    if ( ! $wp_query->have_posts() ) throw new RuntimeException( 'Synthetic document query failed.' );
    wp_set_current_user( $viewer === 'administrator' ? (int) $admins[0] : ( $viewer === 'artist' ? $artist_id : 0 ) );
    $diagnostics_before = $GLOBALS['qa_stack_result']['diagnostics'] ?? array();
    ob_start();
    try { load_template( get_template_directory() . '/eazydocs/single-docs.php', false ); } finally { $html = ob_get_clean(); remove_filter( 'pre_option_eazydocs_settings', $option_filter, PHP_INT_MAX ); }
    $expected = $viewer === 'administrator' || ( $viewer === 'artist' && $rule === 'allowed-role' );
    $checks = 0;
    $check = static function( $condition ) use ( &$checks ) { $GLOBALS['qa_stack_result']['document_template_stage'] = 'render_assertion_' . ( $checks + 1 ); if ( ! $condition ) throw new RuntimeException( 'Complete document render assertion failed.' ); ++$checks; };
    $check( str_contains( $html, 'id="sticky_doc"' ) && str_contains( $html, '</html>' ) );
    $check( str_contains( $html, $document->post_content ) === $expected );
    $check( substr_count( $html, '<main ' ) === 1 && substr_count( $html, '</main>' ) === 1 );
    $site_footer = strpos( $html, '<footer class="doc_footer_area ' );
    $check( false !== $site_footer && strpos( $html, '</main>' ) < $site_footer );
    $check( get_option( 'eazydocs_settings', null ) === $prior_options );
    $check( str_contains( $html, 'Realizzato con' ) && ! str_contains( $html, 'Powered By' ) );
    $theme_diagnostics = 0;
    foreach ( $GLOBALS['qa_stack_result']['diagnostics'] ?? array() as $key => $count ) {
        if ( $count <= ( $diagnostics_before[$key] ?? 0 ) ) continue;
        $origin = explode( ':', $key )[1] ?? '';
        if ( in_array( $origin, array( 'single-docs.php', 'header.php', 'footer.php', 'Docy_helper.php' ), true ) ) ++$theme_diagnostics;
    }
    return array( 'checks' => $checks, 'content_visible' => $expected, 'theme_diagnostics' => $theme_diagnostics, 'native_plugins' => true, 'license_scope' => 'isolated installation; native licensed APIs qualified separately' );
}
