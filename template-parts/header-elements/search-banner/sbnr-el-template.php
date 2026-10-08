<?php
$query = new \WP_Query( [
    'post_type' => 'elementor_library',
    'p'         => docy_opt('search_banner_el_layout'),
] );

if ( $query->have_posts() ) {
    while ( $query->have_posts() ) {
        $query->the_post();

        if ( did_action( 'elementor/loaded' ) ) {
            $parent_content = \Elementor\Plugin::instance()->frontend->get_builder_content(get_the_ID());
            echo !empty($parent_content) ? $parent_content : apply_filters('the_content', get_the_content());
        }
    }
}
wp_reset_postdata();

/**
 * Breadcrumb
*/
include('breadcrumb.php');