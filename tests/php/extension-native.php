<?php
/** Real WordPress queries/password gates; the optional Elementor renderer is a test double. */
namespace Elementor {
    class Plugin {
        public $frontend;
        public static function instance() {
            static $instance;
            if ( ! $instance ) {
                $instance = new self();
                $instance->frontend = new TemplateRendererFixture();
            }
            return $instance;
        }
    }
    class TemplateRendererFixture {
        public function get_builder_content_for_display( $id ) {
            $GLOBALS['qa_extension_render_calls'][] = $id;
            // These two checks mirror the reviewed official frontend API.
            if ( get_the_ID() === (int) $id || post_password_required( $id ) ) return '';
            return get_post( $id )->post_content;
        }
        public function get_builder_content() {
            throw new \RuntimeException( 'Theme invoked an internal Elementor rendering API.' );
        }
    }
}
namespace {
    if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }
    $qa_root = str_replace( '\\', '/', (string) getenv( 'TRB_WP_MYSQL_ROOT' ) );
    $qa_tmp = str_replace( '\\', '/', realpath( sys_get_temp_dir() ) ?: '' );
    $qa_database = (string) getenv( 'TRB_QA_MYSQL_DATABASE' );
    $qa_config_hash = (string) getenv( 'TRB_QA_CONFIG_SHA256' );
    if ( ! $qa_tmp || dirname( dirname( $qa_root ) ) !== $qa_tmp || ! preg_match( '~^trb-wp-mysql-qa-[0-9]+$~D', basename( dirname( $qa_root ) ) ) || 'wordpress' !== basename( $qa_root ) || ! is_file( $qa_root . '/wp-load.php' ) || is_link( $qa_root ) || is_link( dirname( $qa_root ) ) || ! preg_match( '/^trb_wp_qa_[0-9]+$/D', $qa_database ) || ! preg_match( '/^[a-f0-9]{64}$/D', $qa_config_hash ) || ! is_file( $qa_root . '/wp-config.php' ) || is_link( $qa_root . '/wp-config.php' ) || ! hash_equals( $qa_config_hash, hash_file( 'sha256', $qa_root . '/wp-config.php' ) ) ) throw new \RuntimeException( 'An exclusively disposable official WordPress fixture is required.' );
    $_SERVER['HTTP_HOST'] = '127.0.0.1';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SERVER_PORT'] = '80';
    $_SERVER['REQUEST_URI'] = '/';
    require $qa_root . '/wp-load.php';
    if ( DB_NAME !== $qa_database || wp_get_environment_type() !== 'local' ) throw new \RuntimeException( 'The native extension tests require the isolated local database.' );
    $qa_theme = dirname( __DIR__, 2 );
    $qa_extension_notices = [];
    set_error_handler( static function( $severity, $message, $file, $line ) use ( $qa_theme, &$qa_extension_notices ) {
        if ( 0 === ( error_reporting() & $severity ) ) return false;
        if ( str_starts_with( str_replace( '\\', '/', $file ), str_replace( '\\', '/', $qa_theme ) . '/' ) ) throw new \ErrorException( $message, 0, $severity, $file, $line );
        $qa_extension_notices[] = ['message'=>$message, 'file'=>basename($file), 'line'=>$line];
        return true;
    } );
    $qa_extension_checks = 0;
    $qa_extension_render_calls = [];
    function qa_extension_check( $condition, $message ) {
        if ( ! $condition ) throw new \RuntimeException( $message );
        $GLOBALS['qa_extension_checks']++;
    }
    function qa_extension_template( $part ) {
        ob_start();
        include $GLOBALS['qa_theme'] . '/template-parts/' . $part;
        return ob_get_clean();
    }
    register_post_type( 'elementor_library', ['public'=>false, 'show_ui'=>true, 'capability_type'=>'post'] );
    register_post_type( 'docy_footer', ['public'=>false, 'show_ui'=>true, 'capability_type'=>'post'] );
    $qa_extension_page = wp_insert_post( ['post_type'=>'page', 'post_title'=>'Pagina fittizia', 'post_content'=>'PAGE_CONTENT', 'post_status'=>'publish'], true );
    $qa_extension_template = wp_insert_post( ['post_type'=>'elementor_library', 'post_title'=>'Template fittizio', 'post_content'=>'SYNTHETIC_BUILDER_CONTENT', 'post_status'=>'publish'], true );
    qa_extension_check( ! is_wp_error( $qa_extension_page ) && ! is_wp_error( $qa_extension_template ), 'Native template fixtures could not be created.' );
    $qa_empty_template = ( $argv[1] ?? '' ) === 'empty';
    update_option( 'docy_opt', ['search_banner_el_layout'=>$qa_empty_template ? 0 : $qa_extension_template, 'footer_el_template'=>$qa_extension_template, 'is_breadcrumb'=>'0'] );
    require $qa_theme . '/inc/template-functions.php';
    do_action( 'elementor/loaded' );
    $GLOBALS['wp_query'] = new \WP_Query( ['page_id'=>$qa_extension_page] );
    $GLOBALS['wp_query']->the_post();
    if ( $qa_empty_template ) {
        $qa_banner_queries = 0;
        add_action( 'pre_get_posts', static function() use ( &$qa_banner_queries ) { $qa_banner_queries++; } );
        $qa_empty_output = qa_extension_template( 'header-elements/search-banner/sbnr-el-template.php' );
        qa_extension_check( '' === trim( $qa_empty_output ) && 0 === $qa_banner_queries && [] === $qa_extension_render_calls, 'An empty template ID queried or rendered the native Elementor library.' );
        qa_extension_check( get_the_ID() === $qa_extension_page, 'An empty template ID changed the native page context.' );
        restore_error_handler();
        echo wp_json_encode( ['passed'=>true, 'checks'=>$qa_extension_checks, 'case'=>'empty configured template ID', 'wordpress'=>get_bloginfo('version'), 'native_queries_started'=>$qa_banner_queries, 'theme_warnings'=>0, 'external_notices'=>$qa_extension_notices, 'elementor_renderer'=>'explicit test double', 'actual_elementor_plugin_loaded'=>false], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
        exit;
    }
    $qa_admin_ids = get_users( ['role'=>'administrator', 'number'=>1, 'fields'=>'ID'] );
    qa_extension_check( count( $qa_admin_ids ) === 1, 'The disposable administrator fixture is required.' );
    foreach ( [0, (int) $qa_admin_ids[0]] as $qa_viewer ) {
        wp_set_current_user( $qa_viewer );
        foreach ( ['publish', 'draft', 'private', 'pending', 'future', 'trash'] as $qa_status ) {
            $qa_updated = wp_update_post( ['ID'=>$qa_extension_template, 'post_status'=>$qa_status, 'post_date'=>'2099-01-01 12:00:00', 'post_date_gmt'=>'2099-01-01 12:00:00'], true );
            qa_extension_check( ! is_wp_error( $qa_updated ), 'Native template status could not be changed.' );
            // Future dates convert publish into future; use a past publication date.
            if ( 'publish' === $qa_status ) wp_update_post( ['ID'=>$qa_extension_template, 'post_status'=>'publish', 'post_date'=>'2020-01-01 12:00:00', 'post_date_gmt'=>'2020-01-01 12:00:00'] );
            $qa_footer = qa_extension_template( 'footers/footer-elementor.php' );
            $qa_banner = qa_extension_template( 'header-elements/search-banner/sbnr-el-template.php' );
            $qa_expected = 'publish' === $qa_status;
            qa_extension_check( $qa_expected === str_contains( $qa_footer, 'SYNTHETIC_BUILDER_CONTENT' ), 'Native footer status gate failed: ' . $qa_status );
            qa_extension_check( $qa_expected === str_contains( $qa_banner, 'SYNTHETIC_BUILDER_CONTENT' ), 'Native banner query leaked or lost a template: ' . $qa_status );
            qa_extension_check( get_the_ID() === $qa_extension_page, 'The native banner changed the page post context.' );
        }
    }
    wp_set_current_user( 0 );
    wp_update_post( ['ID'=>$qa_extension_template, 'post_status'=>'publish', 'post_date'=>'2020-01-01 12:00:00', 'post_date_gmt'=>'2020-01-01 12:00:00', 'post_password'=>'synthetic-protected'] );
    unset( $_COOKIE['wp-postpass_' . COOKIEHASH] );
    $qa_protected_footer = qa_extension_template( 'footers/footer-elementor.php' );
    $qa_protected_banner = qa_extension_template( 'header-elements/search-banner/sbnr-el-template.php' );
    qa_extension_check( ! str_contains( $qa_protected_footer, 'SYNTHETIC_BUILDER_CONTENT' ) && ! str_contains( $qa_protected_banner, 'SYNTHETIC_BUILDER_CONTENT' ) && str_contains( $qa_protected_banner, 'post-password-form' ), 'Native password protection was bypassed by template fallback.' );
    wp_update_post( ['ID'=>$qa_extension_template, 'post_password'=>''] );
    wp_update_post( ['ID'=>$qa_extension_template, 'post_type'=>'page'] );
    qa_extension_check( ! str_contains( qa_extension_template( 'footers/footer-elementor.php' ), 'SYNTHETIC_BUILDER_CONTENT' ) && ! str_contains( qa_extension_template( 'header-elements/search-banner/sbnr-el-template.php' ), 'SYNTHETIC_BUILDER_CONTENT' ), 'The native templates embedded an unrelated post type.' );
    wp_update_post( ['ID'=>$qa_extension_template, 'post_type'=>'docy_footer'] );
    qa_extension_check( str_contains( qa_extension_template( 'footers/footer-elementor.php' ), 'SYNTHETIC_BUILDER_CONTENT' ), 'A published native docy_footer stopped rendering.' );
    qa_extension_check( is_wp_error( wp_remote_get( 'https://example.invalid/' ) ), 'Outbound network isolation was not preserved.' );
    restore_error_handler();
    echo wp_json_encode( ['passed'=>true, 'checks'=>$qa_extension_checks, 'wordpress'=>get_bloginfo('version'), 'theme_warnings'=>0, 'external_notices'=>$qa_extension_notices, 'query_service'=>'real WordPress/MySQL', 'password_gate'=>'real WordPress', 'elementor_renderer'=>'explicit test double of reviewed public API boundary', 'actual_elementor_plugin_loaded'=>false, 'orders_created'=>0, 'payments_invoked'=>0, 'outbound_blocked'=>true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
}
