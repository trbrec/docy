<?php
$banner_template_id = absint( docy_opt( 'search_banner_el_layout' ) );

// An unset ID must not turn into an unrestricted query for library templates.
if ( $banner_template_id && did_action( 'elementor/loaded' ) ) {
    $query = new \WP_Query( [
        'post_type'      => 'elementor_library',
        'post_status'    => 'publish',
        'p'              => $banner_template_id,
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ] );

    if ( $query->have_posts() ) {
        // Render before changing the global post: Elementor's public display
        // method rejects rendering the same ID as the current global post.
        $parent_content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $banner_template_id );
        $query->the_post();
        echo ! empty( $parent_content ) ? $parent_content : apply_filters( 'the_content', get_the_content() );
    }
    wp_reset_postdata();
}

/**
 * Breadcrumb
*/
include('breadcrumb.php');
