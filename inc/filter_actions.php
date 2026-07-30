<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Image sizes
add_image_size( 'docy_60x60', 60, 60, true ); // Forum Thumbnail
add_image_size( 'docy_270x320', 270, 320, true ); // Product Thumbnail
add_image_size( 'docy_300x320', 300, 320, true ); // Product Thumbnail
add_image_size( 'docy_370x360', 370, 360, true ); // Posts carousel thumbnail
add_image_size( 'docy_844x400', 844, 400, true ); // Blog post list format
add_image_size( 'docy_410x220', 410, 220, true ); // Blog post grid format
add_image_size( 'docy_370x200', 370, 200, true ); // Related post-thumbnail
add_image_size( 'docy_670x450', 670, 450, true ); // Blog Category Page Sticky post thumbnail
add_image_size( 'docy_18x18', 18, 18, true ); // Forum post topic category thumbnail
add_image_size( 'docy_20x20', 20, 20, true ); // Forum post topic category thumbnail

/**
 * Add category nicknames in body and post class
 *
 * @param array $classes Post classes.
 * @return array
 */
function docy_post_class( $classes ) {
	if ( ! has_post_thumbnail() ) {
		$classes[] = 'no-post-thumbnail';
	}
	if ( is_sticky() && ! in_array( 'sticky', $classes, true ) ) {
		$classes[] = 'sticky';
	}
	return $classes;
}
add_filter( 'post_class', 'docy_post_class' );

/**
 * Get custom icon [Elegant Icons]
 *
 * @param array $icons Existing icons.
 * @return array
 */
function docy_csf_elegant_icons( $icons = [] ) {
	static $static_icons_arr = null;

	if ( null !== $static_icons_arr ) {
		$icons_arr = $static_icons_arr;
	} else {
		$cache_key = 'docy_elegant_icons_cache';
		$icons_arr = get_transient( $cache_key );

		if ( false === $icons_arr ) {
			// Bolt: Use file path instead of URL to avoid expensive HTTP request.
			$icons_path = get_template_directory() . '/assets/vendors/elegant-icon/icons.json';

			if ( ! file_exists( $icons_path ) || ! is_readable( $icons_path ) ) {
				return array_reverse( $icons );
			}

			// Get the icons json object.
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_get_contents_file_get_contents
			$icons_json = file_get_contents( $icons_path );
			if ( false === $icons_json || '' === $icons_json ) {
				return array_reverse( $icons );
			}

			// Decode the json object.
			$icons_arr = json_decode( $icons_json, true );

			if ( is_array( $icons_arr ) ) {
				set_transient( $cache_key, $icons_arr, WEEK_IN_SECONDS );
			} else {
				return array_reverse( $icons );
			}
		}

		$static_icons_arr = $icons_arr;
	}

	// Adding new icons
	$icons[] = [
		'title' => esc_html__( 'Elegant Icons', 'docy' ),
		'icons' => $icons_arr,
	];

	return array_reverse( $icons );
}

add_filter( 'csf_field_icon_add_icons', 'docy_csf_elegant_icons' );


/**
 * Body classes
 */
add_filter( 'body_class', function( $classes ) {

	$page_doc_width = docy_meta( 'doc_width', 'default' );

	if ( 'default' === $page_doc_width || '' === $page_doc_width ) {
		$header_width = docy_opt( 'header_width', 'boxed' );
	} else {
		$header_width = $page_doc_width;
	}

	$is_doc_sticky = docy_meta( 'is_sticky_header' );
	$has_menu      = has_nav_menu( 'main_menu' ) ? '' : 'has_not_menu';
	$classes[]     = $has_menu;

	if ( 'left_sidebar' === Docy_helper()->doc_layout() && is_singular( 'docs' ) ) {
		$classes[] = 'no_right_sidebar';
	}

	if ( is_singular( 'docs' ) ) {
		$classes[] = 'doc';
		if ( '1' === $is_doc_sticky ) {
			$classes[] = 'sticky-nav-doc';
		}
	}

	if ( '1' === docy_opt( 'is_dark_default' ) ) {
		wp_enqueue_style( 'docy-dark-mode' );
		$classes[] = 'body_dark';
	}

	if ( 'static' === docy_navbar_position() ) {
		$classes[] = 'static-navbar';
	}

	$classes[] = $header_width;
	$classes[] = docy_search_banner();

	return $classes;
} );

/**
 * Removes admin notices on the docy's pages.
 *
 * @return void
 */
add_action( 'admin_head', function () {
	$page = ! empty( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	// Check if the current screen is for your plugin page
	if ( in_array( $page, [ 'docy_verify', 'docy-options', 'docy_template' ], true ) ) {
		// Remove admin notices
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
	}
} );

/**
 * Show post excerpt by default
 *
 * @param string  $user_login User login.
 * @param WP_User $user       User object.
 */
function docy_show_post_excerpt( $user_login, $user ) {
	$unchecked = get_user_meta( $user->ID, 'metaboxhidden_post', true );
	$key       = is_array( $unchecked ) ? array_search( 'postexcerpt', $unchecked ) : false;
	if ( false !== $key ) {
		array_splice( $unchecked, $key, 1 );
		update_user_meta( $user->ID, 'metaboxhidden_post', $unchecked );
	}
}
add_action( 'wp_login', 'docy_show_post_excerpt', 10, 2 );

// Filter to replace class on reply link
add_filter( 'comment_reply_link', function( $class ) {
	return str_replace( "class='comment-reply-link", "class='comment_reply", $class );
} );

/**
 * Add a pingback url auto-discovery header for singularly identifiable articles.
 */
function docy_pingback_header() {
	if ( is_singular() && pings_open() ) {
		echo '<link rel="pingback" href="', esc_url( get_bloginfo( 'pingback_url' ) ), '">';
	}
}
add_action( 'wp_head', 'docy_pingback_header' );

// Move the comment field to bottom
add_filter( 'comment_form_fields', function ( $fields ) {
	$comment_field = $fields['comment'];
	unset( $fields['comment'] );
	$fields['comment'] = $comment_field;
	return $fields;
} );

// Remove WordPress admin bar default CSS
add_action( 'get_header', function() {
	remove_action( 'wp_head', '_admin_bar_bump_cb' );
} );


/**
 * Render the banner on Elementor Full Width pages.
 *
 * The "Elementor Full Width" template (elementor_header_footer) loads its own
 * template file and never passes through page.php, so docy_render_banner() must
 * be hooked here to restore banner output for existing pages using that template.
 */
add_action( 'elementor/page_templates/header-footer/before_content', 'docy_render_banner' );

/**
 * Elementor post type support
 *
 * @return void
 */
function docy_add_cpt_support() {
	// If exists, assign to $cpt_support var
	$cpt_support = get_option( 'elementor_cpt_support' );

	// Check if option DOESN'T exist in db or is not a valid array
	if ( ! is_array( $cpt_support ) || empty( $cpt_support ) ) {
		$cpt_support = [ 'page', 'post', 'docs' ]; // Create array of our default supported post types
		update_option( 'elementor_cpt_support', $cpt_support ); // Write it to the database
	}

	// If it DOES exist as an array, but 'docs' is NOT defined
	elseif ( ! in_array( 'docs', $cpt_support, true ) ) {
		$cpt_support[] = 'docs'; // Append to array
		update_option( 'elementor_cpt_support', $cpt_support ); // Update database
	}
}
add_action( 'after_switch_theme', 'docy_add_cpt_support' );


/**
 * Filter main query for Blog Category layout
 *
 * Optimizes the 'blog_category' layout by filtering the main query instead of
 * running a secondary unpaginated query.
 *
 * @param WP_Query $query The query instance.
 */
add_action( 'pre_get_posts', function( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// Get theme options for fallback
	$opt    = get_option( 'docy_opt' );
	$layout = ! empty( $opt['blog_layout'] ) ? $opt['blog_layout'] : 'list';

	// Check for override via GET parameter
	if ( isset( $_GET['blog_layout'] ) ) {
		$layout = sanitize_text_field( wp_unslash( $_GET['blog_layout'] ) );
	}

	// If the layout is blog_category and a category is specified, filter the main query
	if ( 'blog_category' === $layout && isset( $_GET['category'] ) ) {
		$category = sanitize_text_field( wp_unslash( $_GET['category'] ) );
		if ( ! empty( $category ) ) {
			$query->set( 'category_name', $category );
			$query->set( 'ignore_sticky_posts', true );
		}
	}
} );
