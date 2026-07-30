<?php
// Control core classes to avoid errors
if ( class_exists( 'CSF' ) ) {

	$prefix = 'docy';

	// Meta > Options:: Page
	CSF::createMetabox( $prefix, array(
		'title'        => esc_html__( 'Docy:: Meta Options', 'docy' ),
		'post_type'    => [ 'post', 'page', 'video', 'docs' ],
		'priority'     => 'high',
		'data_type'    => 'unserialize',
		'output_css'   => true,
		'show_restore' => true,
	) );

	// Banner-> Post
    require get_template_directory() . '/inc/meta/meta_post_banner.php';

    // Banner-> Page
    require get_template_directory() . '/inc/meta/meta_banner.php';

	// Header-> [Post, Page]
	require get_template_directory() . '/inc/meta/meta_header.php';

    // Header-> Docs
    require get_template_directory() . '/inc/meta/meta_header_doc.php';

    // Page Settings-> [Page]
    require get_template_directory() . '/inc/meta/meta_page.php';

	// Action Button-> [Post, Page, Docs]
	require get_template_directory() . '/inc/meta/meta_action_button.php';

	// Footer-> [Post, Page]
	require get_template_directory() . '/inc/meta/meta_footer.php';

	// Sidebar-> [Post, Page]
	require get_template_directory() . '/inc/meta/meta_sidebar.php';

    // Video-> [ Video Post ]
	require get_template_directory() . '/inc/meta/meta_video.php';
}