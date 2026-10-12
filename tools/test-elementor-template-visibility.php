<?php
/** Theme boundary tests; Elementor and query services are explicit test doubles. */
namespace Elementor {
    class Plugin {
        public $frontend;
        public static function instance() {
            static $instance;
            if ( ! $instance ) {
                $instance = new self();
                $instance->frontend = new FrontendFixture();
            }
            return $instance;
        }
    }
    class FrontendFixture {
        public function get_builder_content_for_display( $id ) {
            $GLOBALS['elementor_display_calls'][] = $id;
            // Official public API refuses recursion against the current post.
            if ( get_the_ID() === (int) $id || post_password_required( $id ) ) return '';
            return $GLOBALS['elementor_posts'][$id]->builder_content;
        }
        public function get_builder_content() {
            throw new \RuntimeException( 'The banner invoked an internal Elementor method.' );
        }
    }
}
namespace {
    if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }
    $GLOBALS['elementor_posts'] = [];
    $GLOBALS['elementor_options'] = [];
    $GLOBALS['elementor_loaded'] = true;
    $GLOBALS['elementor_current_id'] = 99;
    $GLOBALS['elementor_query_args'] = [];
    $GLOBALS['elementor_display_calls'] = [];
    function get_option( $name ) { return $GLOBALS['elementor_options']; }
    function docy_opt( $name, $default = '' ) { return $GLOBALS['elementor_options'][$name] ?? $default; }
    function docy_meta_apply() { return '0'; }
    function esc_html__( $value ) { return $value; }
    function did_action() { return $GLOBALS['elementor_loaded'] ? 1 : 0; }
    function absint( $value ) { return abs( (int) $value ); }
    function get_post( $id ) { return $GLOBALS['elementor_posts'][$id] ?? null; }
    function get_the_ID() { return $GLOBALS['elementor_current_id']; }
    function post_password_required( $id ) { return ! empty( get_post( $id )->post_password ); }
    function get_the_content() { return post_password_required( get_the_ID() ) ? 'PASSWORD_FORM' : get_post( get_the_ID() )->post_content; }
    function apply_filters( $name, $value ) { return $value; }
    function wp_reset_postdata() { $GLOBALS['elementor_current_id'] = 99; }
    function is_singular() { return false; }
    function is_post_type_archive() { return false; }
    class WP_Query {
        private $fixture_post;
        private $used = false;
        public function __construct( $args ) {
            $GLOBALS['elementor_query_args'][] = $args;
            $candidate = get_post( $args['p'] );
            if ( $candidate && $candidate->post_type === $args['post_type'] && $candidate->post_status === $args['post_status'] ) $this->fixture_post = $candidate;
        }
        public function have_posts() { return ! $this->used && (bool) $this->fixture_post; }
        public function the_post() { $this->used = true; $GLOBALS['elementor_current_id'] = $this->fixture_post->ID; }
    }
    $checks = 0;
    function elementor_check( $condition, $message ) {
        if ( ! $condition ) throw new \RuntimeException( $message );
        $GLOBALS['checks']++;
    }
    function elementor_render( $part ) {
        ob_start();
        include dirname( __DIR__ ) . '/template-parts/' . $part;
        return ob_get_clean();
    }
    foreach ( ['publish', 'draft', 'private', 'pending', 'future', 'trash'] as $index => $status ) {
        $id = $index + 1;
        $GLOBALS['elementor_posts'][$id] = (object) ['ID'=>$id, 'post_type'=>'elementor_library', 'post_status'=>$status, 'post_password'=>'', 'post_content'=>'PLAIN_' . $status, 'builder_content'=>'BUILDER_' . $status];
        $GLOBALS['elementor_options'] = ['footer_el_template'=>$id, 'search_banner_el_layout'=>$id];
        $footer = elementor_render( 'footers/footer-elementor.php' );
        $banner = elementor_render( 'header-elements/search-banner/sbnr-el-template.php' );
        $published = 'publish' === $status;
        elementor_check( $published === str_contains( $footer, 'BUILDER_' . $status ), 'Footer publication boundary failed: ' . $status );
        elementor_check( $published === str_contains( $banner, 'BUILDER_' . $status ), 'Banner publication boundary failed: ' . $status );
        elementor_check( 99 === get_the_ID(), 'The banner changed the current page post.' );
    }
    foreach ( ['', 0, null] as $empty ) {
        $GLOBALS['elementor_options']['search_banner_el_layout'] = $empty;
        $before = count( $GLOBALS['elementor_query_args'] );
        elementor_check( '' === trim( elementor_render( 'header-elements/search-banner/sbnr-el-template.php' ) ) && $before === count( $GLOBALS['elementor_query_args'] ), 'An unset template queried the library.' );
    }
    $GLOBALS['elementor_options'] = ['footer_el_template'=>1, 'search_banner_el_layout'=>1];
    $GLOBALS['elementor_loaded'] = false;
    $before = count( $GLOBALS['elementor_display_calls'] );
    elementor_check( '' === trim( elementor_render( 'footers/footer-elementor.php' ) ) && '' === trim( elementor_render( 'header-elements/search-banner/sbnr-el-template.php' ) ) && $before === count( $GLOBALS['elementor_display_calls'] ), 'Inactive Elementor rendered template content.' );
    $GLOBALS['elementor_loaded'] = true;
    $GLOBALS['elementor_posts'][1]->post_password = 'synthetic-protected';
    elementor_check( ! str_contains( elementor_render( 'footers/footer-elementor.php' ), 'BUILDER_' ), 'Password protected footer leaked content.' );
    elementor_check( str_contains( elementor_render( 'header-elements/search-banner/sbnr-el-template.php' ), 'PASSWORD_FORM' ), 'Banner fallback bypassed the password gate.' );
    $GLOBALS['elementor_posts'][1]->post_password = '';
    $GLOBALS['elementor_posts'][1]->builder_content = '';
    elementor_check( str_contains( elementor_render( 'header-elements/search-banner/sbnr-el-template.php' ), 'PLAIN_publish' ), 'Published plain template fallback was lost.' );
    $GLOBALS['elementor_posts'][1]->post_type = 'page';
    elementor_check( '' === trim( elementor_render( 'footers/footer-elementor.php' ) ) && '' === trim( elementor_render( 'header-elements/search-banner/sbnr-el-template.php' ) ), 'An unrelated post type was embedded.' );
    foreach ( $GLOBALS['elementor_query_args'] as $args ) {
        elementor_check( 'publish' === $args['post_status'] && 1 === $args['posts_per_page'] && true === $args['no_found_rows'], 'Banner query broadened publication or omitted its bound.' );
    }
    echo "$checks Elementor template boundary assertions passed; query and renderer services are test doubles.\n";
}
