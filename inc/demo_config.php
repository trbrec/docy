<?php
// Disable regenerating images while importing media
add_filter( 'pt-ocdi/regenerate_thumbnails_in_content_import', '__return_false' );
add_filter( 'pt-ocdi/disable_pt_branding', '__return_true' );

// Change some options for the jQuery modal window
function docy_ocdi_confirmation_dialog_options( $options ) {
	return array_merge( $options, array(
		'width'       => 400,
		'dialogClass' => 'wp-dialog',
		'resizable'   => false,
		'height'      => 'auto',
		'modal'       => true,
	) );
}

add_filter( 'pt-ocdi/confirmation_dialog_options', 'docy_ocdi_confirmation_dialog_options', 10, 1 );

function docy_ocdi_intro_text( $default_text ) {
	$default_text .= '<div class="ocdi_custom-intro-text notice notice-info inline">';
	$default_text .= sprintf(
		'%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a> %4$s',
		esc_html__( 'Install and activate all ', 'docy' ),
		get_admin_url( null, 'themes.php?page=tgmpa-install-plugins' ),
		esc_html__( 'required plugins', 'docy' ),
		esc_html__( 'before you click on the "Import" button.', 'docy' )
	);
	$default_text .= sprintf(
		'<br> %1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a> %4$s',
		esc_html__( 'After importing a demo, you will find all the pages in ', 'docy' ),
		get_admin_url( null, 'edit.php?post_type=page' ),
		esc_html__( 'Pages.', 'docy' ),
		esc_html__( 'Other pages will be imported along with the main Homepage demo.', 'docy' )
	);
	$default_text .= '<br>';
	$default_text .= sprintf(
		'%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
		esc_html__( 'If you fail to import the demo data, follow the alternative way', 'docy' ),
		'https://is.gd/bk8F5p',
		esc_html__( 'here.', 'docy' )
	);
	$default_text .= '</div>';

	return $default_text;
}

add_filter( 'pt-ocdi/plugin_intro_text', 'docy_ocdi_intro_text' );

// OneClick Demo Importer
add_filter( 'pt-ocdi/import_files', 'docy_import_files' );
function docy_import_files() {
	return array(
		array(
			'import_file_name'         => esc_html__( 'Creative Helpdesk', 'docy' ),
			'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/default.xml',
			'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
			'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/creative.jpg',
			'preview_url'              => 'https://wordpress-theme.spider-themes.net/docy/',
			'local_import_cs'          => array(
				array(
					'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/settings.json',
					'option_name' => 'docy_opt',
				),
			),
		),
		array(
			'import_file_name'         => esc_html__( 'Cool Knowledge Base', 'docy' ),
			'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/default.xml',
			'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
			'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/cool.jpg',
			'preview_url'              => 'https://wordpress-theme.spider-themes.net/docy/home-cool/',
			'local_import_cs'          => array(
				array(
					'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/settings.json',
					'option_name' => 'docy_opt',
				),
			),
		),
		array(
			'import_file_name'         => esc_html__( 'Classic Helpdesk', 'docy' ),
			'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/default.xml',
			'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
			'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/classic.jpg',
			'preview_url'              => 'https://wordpress-theme.spider-themes.net/docy/home-classic/',
			'local_import_cs'          => array(
				array(
					'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/settings.json',
					'option_name' => 'docy_opt',
				),
			),
		),
		array(
			'import_file_name'         => esc_html__( 'Light Knowledge Base', 'docy' ),
			'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/default.xml',
			'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
			'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/light.jpeg',
			'preview_url'              => 'https://wordpress-theme.spider-themes.net/docy/home-light/',
			'local_import_cs'          => array(
				array(
					'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/settings.json',
					'option_name' => 'docy_opt',
				),
			),
		),
		array(
			'import_file_name'         => esc_html__( 'Support Forum', 'docy' ),
			'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/default.xml',
			'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
			'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/forum.jpeg',
			'preview_url'              => 'https://wordpress-theme.spider-themes.net/docy/support-forum/',
			'local_import_cs'          => array(
				array(
					'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/settings.json',
					'option_name' => 'docy_opt',
				),
			),
		),
		array(
			'import_file_name'         => esc_html__( 'Multi Helpdesk', 'docy' ),
			'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/default.xml',
			'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
			'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/multi.jpeg',
			'preview_url'              => 'https://wordpress-theme.spider-themes.net/docy/support-forum/',
			'local_import_cs'          => array(
				array(
					'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/settings.json',
					'option_name' => 'docy_opt',
				),
			),
		),
		array(
			'import_file_name'         => esc_html__( 'Book Chapters / Tutorials', 'docy' ),
			'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/default.xml',
			'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
			'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/book-chapters.jpeg',
			'preview_url'              => 'https://wordpress-theme.spider-themes.net/docy/support-forum/',
			'local_import_cs'          => array(
				array(
					'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/settings.json',
					'option_name' => 'docy_opt',
				),
			),
		),
		array(
			'import_file_name'         => esc_html__( 'User Manuals', 'docy' ),
			'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/default.xml',
			'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
			'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/user-manuals.jpg',
			'preview_url'              => 'https://wordpress-theme.spider-themes.net/docy/user-manuals/',
			'local_import_cs'          => array(
				array(
					'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/settings.json',
					'option_name' => 'docy_opt',
				),
			),
		),
		array(
			'import_file_name'         => esc_html__( 'Focused Helpdesk', 'docy' ),
			'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/default.xml',
			'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
			'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/focused-helpdesk.jpg',
			'preview_url'              => 'https://wordpress-theme.spider-themes.net/docy/focused-helpdesk/',
			'local_import_cs'          => array(
				array(
					'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/settings.json',
					'option_name' => 'docy_opt',
				),
			),
		),
		array(
			'import_file_name'         => esc_html__( 'LMS', 'docy' ),
			'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/lms.xml',
			'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
			'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/lms.jpg',
			'preview_url'              => 'https://wordpress-theme.spider-themes.net/lms-docy',
			'local_import_cs'          => array(
				array(
					'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/lms-settings.json',
					'option_name' => 'docy_opt',
				),
			),
		),
        array(
            'import_file_name'         => esc_html__( 'Docy Career', 'docy' ),
            'import_file_url'          => 'https://wordpress-theme.spider-themes.net/resources/docy/docy-career.xml',
            'local_import_widget_file' => trailingslashit( get_template_directory() ) . 'inc/demo/widgets.wie',
            'import_preview_image_url' => trailingslashit( get_template_directory_uri() ) . 'inc/demo/images/docy-career.png',
            'preview_url'              => 'https://wordpress-theme.spider-themes.net/docy/career',
            'local_import_cs'          => array(
                array(
                    'file_path'   => trailingslashit( get_template_directory() ) . 'inc/demo/career-settings.json',
                    'option_name' => 'docy_opt',
                ),
            ),
        ),
	);
}

function docy_after_import_setup( $selected_import ): void {

	// Assign menus to their locations.
	$main_menu = get_term_by( 'name', 'Main Menu', 'nav_menu' );

	set_theme_mod( 'nav_menu_locations', array(
			'main_menu' => $main_menu->term_id,
		)
	);

	// Disable Elementor's Default Colors and Default Fonts
	update_option( 'elementor_disable_color_schemes', 'yes' );
	update_option( 'elementor_disable_typography_schemes', 'yes' );
	update_option( 'elementor_global_image_lightbox', '' );

	$front_page_id = docy_get_page_title( 'Home Creative' );

	// Assign front page and posts page (blog page).
	if ( 'Creative Helpdesk' == $selected_import['import_file_name'] ) {
		$front_page_id = docy_get_page_title( 'Home Creative' );
	}

	if ( 'Cool Knowledge Base' == $selected_import['import_file_name'] ) {
		$front_page_id = docy_get_page_title( 'Home Cool' );
	}

	if ( 'Classic Helpdesk' == $selected_import['import_file_name'] ) {
		$front_page_id = docy_get_page_title( 'Home Classic' );
	}

	if ( 'Light Knowledge Base' == $selected_import['import_file_name'] ) {
		$front_page_id = docy_get_page_title( 'Home Light' );
	}

	if ( 'Support Forum' == $selected_import['import_file_name'] ) {
		$front_page_id = docy_get_page_title( 'Support Forum' );
	}

	if ( 'Multi Helpdesk' == $selected_import['import_file_name'] ) {
		$front_page_id = docy_get_page_title( 'Home Multi Helpdesk' );
	}

	if ( 'Book Chapters / Tutorials' == $selected_import['import_file_name'] ) {
		$front_page_id = docy_get_page_title( 'Home Book Chapters / Tutorials' );
	}

	if ( 'User Manuals' == $selected_import['import_file_name'] ) {
		$front_page_id = docy_get_page_title( 'User Manuals' );
	}

	if ( 'Focused Helpdesk' == $selected_import['import_file_name'] ) {
		$front_page_id = docy_get_page_title( 'Focused Helpdesk' );
	}

	$blog_page_id = docy_get_page_title( 'Blog' );

	// Set the home page and blog page
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $front_page_id );
	update_option( 'page_for_posts', $blog_page_id );

	// Flag the import as complete so the welcome-page Setup Checklist can mark
	// the "Import Demo Content" step as done.
	update_option( 'docy_demo_imported', true );
}

add_action( 'pt-ocdi/after_import', 'docy_after_import_setup' );