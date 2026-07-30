<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get theme option
 *
 * @param string $option The option key.
 * @param mixed  $default The default value.
 * @param string $arr_item Access to the array item of a theme option.
 *
 * @return mixed|string
 */
function docy_opt( string $option = '', $default = '', $arr_item = '' ) {
	static $options = null;

	if ( null === $options ) {
		$options = get_option( 'docy_opt', [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}
	}

	$single_opt = $options[ $option ] ?? null;

	// Fall back to the registered default (then the provided default) when the
	// option has never been saved or was emptied by a Theme Settings reset.
	// A reset stores an empty string for every field without a defined default,
	// so an empty value is treated the same as a missing one.
	if ( null === $single_opt || '' === $single_opt ) {
		$defaults   = docy_opt_defaults();
		$single_opt = $defaults[ $option ] ?? $default;
	}

	if ( is_array( $single_opt ) && '' !== $arr_item && null !== $arr_item ) {
		return $single_opt[ $arr_item ] ?? $default;
	}

	return $single_opt;
}

/**
 * Build a map of Theme Settings field defaults registered with CSF.
 *
 * Lets unsaved options resolve to the default defined in the options files,
 * even when CSF has not persisted them to the database yet (e.g. new fields
 * added after the options were first saved).
 *
 * @return array Associative array of field_id => default value.
 */
function docy_opt_defaults(): array {
	static $defaults = null;

	// Recompute until the Theme Settings sections have been registered with CSF.
	if ( null === $defaults && class_exists( 'CSF' ) && ! empty( CSF::$args['sections']['docy_opt'] ) ) {

		$defaults = [];

		foreach ( CSF::$args['sections']['docy_opt'] as $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {
				if ( ! empty( $field['id'] ) && isset( $field['default'] ) ) {
					$defaults[ $field['id'] ] = $field['default'];
				}
			}
		}
	}

	return is_array( $defaults ) ? $defaults : [];
}

/**
 * Get post-meta
 *
 * @param string $meta_id
 * @param string $default
 *
 * @return mixed|string
 */
function docy_meta(string $meta_id = '', string $default = '' ): mixed {
    $meta_value = get_post_meta( get_the_ID(), $meta_id, true );
    return !empty($meta_value) ? $meta_value : $default;
}

/**
 * Get post-meta value or theme option value.
 *
 * This function first attempts to retrieve a post-meta value. If the post meta
 * is not set or is empty, it falls back to the theme option value.
 *
 * @param string $option_id
 * @param string|null $default The default value to return if both meta and option are not set.
 * @return mixed The post meta value, theme option value, or default value.
 */
function docy_meta_apply(string $option_id, null|string $default = '' ): mixed {
    // Get post meta and theme option values
    $post_id 	  = get_the_ID();
    $option_value = docy_opt($option_id, $default);

    // If option_value is not set, use the default value.
    if ( ! isset( $option_value ) || $option_value === '' ) {
        $option_value = $default;
    }

    // If no valid post ID (e.g., BBPress author profile pages), use theme option
    if ( empty( $post_id ) || 0 === $post_id ) {
        return $option_value;
    }

    $meta_value = get_post_meta( $post_id, $option_id, true );

    // Check if meta value is an array and empty
    $is_meta_arr_empty = is_array($meta_value) && empty(array_filter($meta_value));

    if ( 'default' === $meta_value || '' === $meta_value || null === $meta_value || $is_meta_arr_empty ) {
        return $option_value;
    }

    // Return meta if it's a valid non-empty value
    return $meta_value;
}

/**
 * Render posts based on the selected category or default query.
 *
 * @return void
 */
function docy_extracted_cat_posts(): void {
	// Optimization: This function used to run a separate WP_Query with posts_per_page => -1.
	// We now filter the main query via pre_get_posts (in filter_actions.php) to include the
	// category filter and pagination support.

    // We rewind posts because the main loop might have been iterated previously (e.g. for sticky posts in index.php)
    rewind_posts();

	// Default query loop if no category is selected.
    while ( have_posts() ) : the_post();
        get_template_part('template-parts/contents/content-grid');
    endwhile;

	// Output a closing container div for layout structure.
	echo '<div class="col-lg-12"></div>';
}

/**
 * Get the homepage IDs by Title
 */
function docy_homepage_ids() {
    $page_ids = get_transient( 'docy_homepage_ids_cache' );

    if ( false === $page_ids ) {
        global $wpdb;
        // Array of page titles you want to retrieve IDs for
        $page_titles = [
            'Focused Helpdesk',
            'Home Book Chapters / Tutorials',
            'Home Classic',
            'Home Cool',
            'Home Creative',
            'Home Help Desk',
            'Home Light',
            'Home Cool',
            'Home Multi Helpdesk',
            'User Manuals',
            'Support Forum',
            'Instructor',
            'Documentation'
        ];

        // Prepare placeholders for the IN clause
        $placeholders = implode( ', ', array_fill( 0, count( $page_titles ), '%s' ) );

        // Single query to fetch all IDs
        $query = "SELECT ID FROM $wpdb->posts WHERE post_title IN ( $placeholders ) AND post_type = 'page' AND post_status = 'publish'";

        // Prepare the query safely
        $prepared_sql = $wpdb->prepare( $query, $page_titles );

        $page_ids = $wpdb->get_col( $prepared_sql );

        // Cache the result for a week
        set_transient( 'docy_homepage_ids_cache', $page_ids, WEEK_IN_SECONDS );
    }

    return $page_ids;
}

/**
 * Custom functions that act independently of the theme templates
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package docy
 */

// Search form
function docy_search_form( $is_button = true ) {
	?>
    <div class="docy-search">
        <form class="form-wrapper" action="<?php echo esc_url( home_url( '/' ) ); ?>" _lpchecked="1">
            <input type="text" id="search" placeholder="<?php esc_attr_e( 'Search ...', 'docy' ); ?>" name="s">
            <button type="submit" class="btn"><i class="fa fa-search"></i></button>
        </form>
		<?php if ( $is_button ) { ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"
               class="home_btn"> <?php esc_html_e( 'Back to home Page', 'docy' ); ?> </a>
		<?php } ?>
    </div>
	<?php
}

/**
 * Get comment count text
 *
 * @param $post_id
 *
 * @return void
 */
function docy_comment_count( $post_id ) {
	echo esc_html( docy_get_comment_count_text( $post_id ) );
}

/**
 * Build the human-readable comment count label for a post.
 *
 * Extracted so the same wording can be reused by the AJAX handler when it
 * refreshes the count after a comment is posted.
 *
 * @param int $post_id Post ID.
 * @return string Localized count label, e.g. "No Comments", "1 Comment", "5 Comments".
 */
function docy_get_comment_count_text( $post_id ): string {
	$comments_number = (int) get_comments_number( $post_id );

	if ( 0 === $comments_number ) {
		return esc_html__( 'No Comments', 'docy' );
	}

	if ( 1 === $comments_number ) {
		return esc_html__( '1 Comment', 'docy' );
	}

	/* translators: %s: number of comments. */
	return sprintf( esc_html__( '%s Comments', 'docy' ), number_format_i18n( $comments_number ) );
}

/**
 * Get author role
 *
 * @return string
 */
function docy_get_author_role() {
	global $authordata;
	$author_roles = $authordata->roles;
	$author_role  = array_shift( $author_roles );

	return esc_html( $author_role );
}

/**
 * Check If the Page is Forum user profile page
 */
function docy_forum_user_profile() {
	if ( in_array( 'bbp-user-page', get_body_class() ) || in_array( 'bbp-user-edit', get_body_class() ) ) {
		return true;
	}
}


/**
 * Post title array
 *
 * @param $postType
 *
 * @return array
 */
function docy_get_postTitleArray( $postType = 'post' ) {
	$post_type_query = new WP_Query(
		[
			'post_type'      => $postType,
			'posts_per_page' => - 1
		]
	);
	// we need the array of posts
	$posts_array = $post_type_query->posts;
	// the key equals the ID, the value is the post_title
	if ( is_array( $posts_array ) ) {
		$post_title_array = wp_list_pluck( $posts_array, 'post_title', 'ID' );
	} else {
		$post_title_array['default'] = esc_html__( 'Default', 'docy' );
	}

	return $post_title_array;
}

/**
 * Get a specific html tag from content
 *
 * @return a specific HTML tag from the loaded content
 */
function docy_get_html_tag( $tag = 'blockquote', $content = '' ) {
	$dom = new DOMDocument();
	$dom->loadHTML( $content );
	$divs = $dom->getElementsByTagName( $tag );
	$i    = 0;
	foreach ( $divs as $div ) {
		if ( 1 === $i ) {
			break;
		}
		echo '<h4 class="c_head">' . esc_html( $div->nodeValue ) . '</h4>';
		++ $i;
	}
}

/**
 * Get the page id by page template
 *
 * @param string $template
 *
 * @return int
 */
function docy_get_page_template_id( $template = 'page-job-apply-form.php' ) {
	$pages = get_pages( [
		'meta_key'   => '_wp_page_template',
		'meta_value' => $template
	] );
	foreach ( $pages as $page ) {
		$page_id = $page->ID;
	}

	return $page_id;
}

/**
 * Arrow icon left right position
 */
function docy_arrow_left_right() {
	$arrow_icon = is_rtl() ? 'arrow_left' : 'arrow_right';
	echo esc_attr( $arrow_icon );
}

/**
 * Search results page's active tab
 */
function docy_is_search_tab_active( $post_type ): string
{

    // Check if the post_type is set in the query parameters
	$current_post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';

	if ( $current_post_type === $post_type ) {
        return 'active'; // Return active for matching post-types
    }

    // If post_type is not set and the current type is 'all', mark it as active
	if ( '' === $current_post_type && 'all' === $post_type ) {
        return 'active';
    }

    return ''; // Return an empty string if no conditions are meet

}

/**
 * Docy post breadcrumbs
 */
function docy_post_breadcrumbs(): void {
	global $post;

    $opt = get_option( 'docy_opt' );
	$breadcrumb_home = $opt['breadcrumb_home'] ?? esc_html__( 'Home', 'docy' );

	if ( is_home() ) {
		$title = ! empty( $opt['blog_title'] ) ? $opt['blog_title'] : esc_html__( 'Blog', 'docy' );
	} else {
		$title = get_the_title();
	}

	if ( is_singular('docs') || in_array( 'single-docs', get_post_class() ) ) {
		eazydocs_breadcrumbs();
	} elseif ( in_array( 'bbpress', get_body_class() ) || in_array( 'forumax-body', get_body_class() ) || in_array( 'type-topic', get_post_class() ) ) {
		bbp_breadcrumb( [
			'before'         => '<ol class="breadcrumb"> <li class="breadcrumb-item">',
			'sep_before'     => '',
			'sep'            => '</li><li class="breadcrumb-item">',
			'sep_after'      => '',
			'current_before' => '',
			'current_after'  => '',
			'after'          => '</li></ol>',
			'home_text'      => $breadcrumb_home
		] );
	} else {
		?>
		<ol class="breadcrumb <?php echo esc_attr( sanitize_html_class( get_post_type( get_the_ID() ) ) ); ?>">
            <li class="breadcrumb-item">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"> <?php echo esc_html( $breadcrumb_home ); ?> </a>
            </li>
            <?php
            // if the page has a parent page
            if ( !empty($post->post_parent) ) {
                ?>
                <li class="breadcrumb-item">
					<a href="<?php echo esc_url( get_permalink( $post->post_parent ) ); ?>">
						<?php echo esc_html( get_the_title( $post->post_parent ) ); ?>
                    </a>
                </li>
                <?php
            }
            // Is Search Result page
            if ( is_search() ) {
                ?>
                <li class="breadcrumb-item">
					<a href="<?php echo esc_url( get_post_type_archive_link( get_post_type( get_the_ID() ) ) ); ?>">
						<?php 
						$post_type = get_post_type( get_the_ID() );
						if ( 'docs' === $post_type && function_exists( 'docy_get_docs_slug' ) ) {
							echo esc_html( docy_get_docs_slug() );
						} else {
							echo esc_html( ucwords( str_replace( [ '-', '_' ], ' ', $post_type ) ) );
						}
						?>
					</a>
				</li>

                <?php
                if ( 'docs' === $post->post_type && $post->post_parent ) {
                    $ancestors = array_reverse( get_post_ancestors( $post->ID ) ); ;
                    foreach ( $ancestors as $ancestor_id ) {
                        $ancestor = get_post( $ancestor_id );
                        ?>
                        <li class="breadcrumb-item">
							<a href="<?php echo esc_url( get_the_permalink( $ancestor_id ) ); ?>">
								<?php echo esc_html( get_the_title( $ancestor ) ); ?>
                            </a>
                        </li>
                        <?php
                    }
                }
            }
            ?>

            <!-- wooCommerce Pages -->
	        <?php if ( in_array( 'woocommerce-cart', get_body_class() ) || in_array( 'woocommerce-checkout', get_body_class() ) ) : ?>
                <li class="breadcrumb-item">
					<a href="<?php echo esc_url( get_permalink( wc_get_page_id('shop') ) ); ?>">
				        <?php echo esc_html( get_the_title( wc_get_page_id('shop') ) ); ?>
                    </a>
                </li>
	        <?php endif; ?>

            <?php if ( in_array( 'woocommerce-checkout', get_body_class() ) ) : ?>
                <li class="breadcrumb-item">
					<a href="<?php echo esc_url( wc_get_cart_url() ); ?>">
			            <?php echo esc_html( get_the_title( wc_get_page_id('cart') ) ); ?>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Active page -->
			<?php if ( is_archive() && ! is_home() ) : ?>
                <li class="breadcrumb-item active">
					<?php
					if ( is_category() ) {
						echo esc_html( single_cat_title( '', false ) );
					} elseif ( is_tag() ) {
						echo esc_html( single_tag_title( '', false ) );
					} elseif ( is_tax() ) {
						echo esc_html( single_term_title( '', false ) );
					} elseif ( is_author() ) {
						echo esc_html( get_the_author() );
					} elseif ( is_date() ) {
						echo esc_html( wp_strip_all_tags( get_the_archive_title() ) );
					} else {
						echo esc_html( docy_get_modified_slug_by_post_type( get_post_type() ) );
					}
					?>
                </li>
			<?php endif; ?>
			<?php if ( is_home() ) : ?>
                <li class="breadcrumb-item active">
					<?php esc_html_e( 'Blog', 'docy' ); ?>
                </li>
			<?php endif; ?>
			<?php if ( is_single() || is_page() ) : ?>
                <li class="breadcrumb-item active" aria-current="page">
					<?php echo esc_html( wp_strip_all_tags( $title ) ); ?>
                </li>
			<?php endif; ?>
        </ol>
		<?php
	}
}

/**
 * Sanitize a link target attribute.
 *
 * @param string $target Raw target value.
 *
 * @return string
 */
function docy_get_link_target( string $target = '_self' ): string {
	$allowed_targets = [ '_self', '_blank', '_parent', '_top' ];
	$target          = strtolower( trim( $target ) );

	return in_array( $target, $allowed_targets, true ) ? $target : '_self';
}

/**
 * Get the rel attribute for a link target.
 *
 * @param string $target Link target.
 *
 * @return string
 */
function docy_get_link_rel( string $target = '_self' ): string {
	return '_blank' === docy_get_link_target( $target ) ? 'noopener noreferrer' : '';
}

/**
 * Has scrollspy
 */
function docy_has_scrollspy() {
	if ( '1' === (string) docy_toc( 'post' ) || '1' === (string) docy_toc( 'page' ) ) {
		echo 'data-bs-spy="scroll" data-bs-target="#docy-toc" data-bs-scroll-animation="true"';
	}
}

/**
 * No Titlebar Condition
 */
function docy_no_titlebar() {
	if ( is_bbp_core_active() ) {
		if ( is_post_type_archive( [
				'forum',
				'topic'
			] )
		     || bbp_is_search_results()
		     || in_array( 'bbp-view-popular', get_body_class() )
		     || in_array( 'bbp-view-no-replies', get_body_class() )
		) {
			return true;
		}
	}

	if ( is_singular( 'docs' ) || is_404() || is_home() || is_single() || is_singular( 'topic' ) || is_search() ) {
		return true;
	}

	// WooCommerce pages: suppress by default, but respect per-page banner meta.
	if ( in_array( 'woocommerce', get_body_class() ) ) {
		return '1' !== docy_meta( 'is_banner' );
	}
}


/**
 * Decode Docy
 */
function docy_decode_du( $str ) {
	$str = str_replace( 'cZ5^9o#!', 'wordpress-theme.spider-themes.net', $str );
	$str = str_replace( 'aI7!8B4H', 'resources', $str );
	$str = str_replace( '^93|3d@', 'https', $str );
	$str = str_replace( 't7Cg*^n0', 'docy', $str );
	$str = str_replace( '3O7%jfGc', '.zip', $str );

	return urldecode( $str );
}

/**
 * Navbar Position
 */
function docy_navbar_position() {
	$opt           = get_option( 'docy_opt' );
	$position_page = docy_meta('navbar_position');
	$position_opt  = $opt['navbar_position'] ?? 'absolute';

	return ! empty( $position_page ) && 'default' !== $position_page ? $position_page : $position_opt;
}

/**
 * Navbar class
 **/
function docy_navbar_class() {
	$is_static = 'static' === docy_navbar_position() && ! is_singular( 'post' ) ? ' position-static' : '';
	?>
    class="navbar navbar-expand-lg menu_one sticky-nav display_none <?php Docy_helper()->navbar_type();
	echo esc_attr( $is_static ) . '"';
}

/**
 * Navbar container
 **/
function docy_nav_container( $class = '' ) {
	// Build base classes depending on page width.
	$classes = ( 'full-width' === Docy_helper()->page_width() )
		? array( 'container-fluid', 'pl-60', 'pr-60' )
		: array( 'container' );

	// Allow additional classes as string or array.
	if ( ! empty( $class ) ) {
		if ( is_string( $class ) ) {
			$extra_classes = preg_split( '/\s+/', $class );
		} elseif ( is_array( $class ) ) {
			$extra_classes = $class;
		} else {
			$extra_classes = array();
		}

		foreach ( (array) $extra_classes as $extra_class ) {
			if ( ! empty( $extra_class ) ) {
				$classes[] = $extra_class;
			}
		}
	}

	// Sanitize each class token and output as an escaped attribute value.
	$sanitized_classes = array();
	foreach ( $classes as $cls ) {
		$sanitized = sanitize_html_class( $cls );
		if ( '' !== $sanitized ) {
			$sanitized_classes[] = $sanitized;
		}
	}

	echo esc_attr( implode( ' ', $sanitized_classes ) );
}

/**
 * Get menu alignment CSS classes based on theme options.
 *
 * Centralizes the menu alignment logic used by layout templates.
 *
 * @return array {
 *     @type string $menu_class   CSS class for the navbar-collapse wrapper.
 *     @type string $center_class CSS class for centering the nav menu.
 * }
 */
function docy_get_menu_alignment_classes(): array {
	$menu_align   = docy_opt( 'menu_align', 'right' );
	$menu_class   = '';
	$center_class = '';

	if ( 'left' === $menu_align ) {
		$menu_class = 'justify-content-lg-between ms-5';
	} elseif ( 'center' === $menu_align ) {
		$center_class = 'm-auto';
	}

	return [
		'menu_class'   => $menu_class,
		'center_class' => $center_class,
	];
}

/**
 * Render the main navigation menu.
 *
 * Centralizes the wp_nav_menu() call used across layout and mobile templates.
 *
 * @param string $extra_classes Additional CSS classes for the menu UL.
 * @param string $walker_class  Walker class name to use.
 *
 * @return void
 */
function docy_render_main_menu( string $extra_classes = '', string $walker_class = 'Docy_Nav_Walker' ): void {
	if ( ! has_nav_menu( 'main_menu' ) ) {
		return;
	}

	wp_nav_menu( [
		'menu'           => 'main_menu',
		'theme_location' => 'main_menu',
		'container'      => null,
		'menu_class'     => 'navbar-nav menu ml-auto ' . $extra_classes,
		'walker'         => new $walker_class(),
		'depth'          => 4,
	] );
}

/**
 * Get the available submenu column choices for menu items.
 *
 * @return array<string, string>
 */
function docy_get_menu_item_column_options(): array {
	return [
		'' => esc_html__( 'Default', 'docy' ),
		'2' => esc_html__( '2 Columns', 'docy' ),
		'3' => esc_html__( '3 Columns', 'docy' ),
		'4' => esc_html__( '4 Columns', 'docy' ),
	];
}

/**
 * Render the menu item columns selector in the menu editor.
 *
 * @param int    $menu_item_id Menu item ID.
 * @param object $item         Menu item object.
 * @param int    $depth        Menu item depth.
 * @param object $args         Menu item arguments.
 *
 * @return void
 */
function docy_render_menu_item_columns_field( $menu_item_id, $item, $depth, $args ): void {
	if ( 0 !== (int) $depth ) {
		return;
	}

	$selected_columns = get_post_meta( $menu_item_id, '_menu_item_columns', true );
	$options          = docy_get_menu_item_column_options();

	echo '<p class="description description-wide docy-menu-item-columns">';
	echo '<label for="edit-menu-item-columns-' . esc_attr( $menu_item_id ) . '">';
	echo esc_html__( 'Menu Columns', 'docy' ) . '<br>';
	echo '<select id="edit-menu-item-columns-' . esc_attr( $menu_item_id ) . '" class="widefat code" name="docy_menu_item_columns[' . esc_attr( $menu_item_id ) . ']">';

	foreach ( $options as $value => $label ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( $selected_columns, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}

	echo '</select>';
	echo '</label>';
	echo '</p>';
}
add_action( 'wp_nav_menu_item_custom_fields', 'docy_render_menu_item_columns_field', 20, 4 );

/**
 * Save the menu item columns setting.
 *
 * @param int   $menu_id         Menu ID.
 * @param int   $menu_item_db_id Menu item ID.
 * @param array $menu_item_args  Menu item arguments.
 *
 * @return void
 */
function docy_save_menu_item_columns( $menu_id, $menu_item_db_id, $menu_item_args ): void {
	$nonce_key = 'update-nav-menu-nonce';
	$nonce     = isset( $_POST[ $nonce_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ) : '';

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! wp_verify_nonce( $nonce, 'update-nav_menu' ) ) {
		return;
	}

	$columns = isset( $_POST['docy_menu_item_columns'][ $menu_item_db_id ] )
		? sanitize_key( wp_unslash( $_POST['docy_menu_item_columns'][ $menu_item_db_id ] ) )
		: '';

	$allowed_columns = array_map( 'strval', array_keys( docy_get_menu_item_column_options() ) );

	if ( ! in_array( $columns, $allowed_columns, true ) || '' === $columns ) {
		delete_post_meta( $menu_item_db_id, '_menu_item_columns' );
		return;
	}

	update_post_meta( $menu_item_db_id, '_menu_item_columns', $columns );
}
add_action( 'wp_update_nav_menu_item', 'docy_save_menu_item_columns', 20, 3 );

/**
 * Add a menu-columns class to items that have a column setting.
 *
 * @param array  $classes Menu item CSS classes.
 * @param object $item    Menu item object.
 * @param object $args    Menu item arguments.
 *
 * @return array
 */
function docy_menu_item_columns_class( array $classes, $item, $args ): array {
	if ( empty( $args->has_children ) || empty( $item->ID ) ) {
		return $classes;
	}

	$columns = get_post_meta( $item->ID, '_menu_item_columns', true );
	$column_classes = [
		'2' => 'two-col',
		'3' => 'three-col',
		'4' => 'four-col',
	];

	if ( isset( $column_classes[ $columns ] ) ) {
		$classes[] = $column_classes[ $columns ];
	}

	return $classes;
}
add_filter( 'nav_menu_css_class', 'docy_menu_item_columns_class', 10, 3 );

/**
 *
 * Is Navbar Sticky
 */
function docy_sticky_navbar( $class = 'wrapper', $stick_on = 'desktop' ) {
	$is_sticky_nav     = docy_opt( 'is_sticky_header', '' );
	$sticky_appearance = docy_opt( 'sticky_appearance', 'stick_up' );
	if ( '1' === $is_sticky_nav ) {
		$get_nav = isset( $_GET['navbar'] ) ? sanitize_key( $_GET['navbar'] ) : '';
		if ( 'desktop' === $stick_on ) {
			if ( 'wrapper' === $class ) {
				echo 'stick_all' === $sticky_appearance || 'stick-all' === $get_nav ? 'sticky_menu' : '';
			} else {
				echo 'stick_all' === $sticky_appearance || 'stick-all' === $get_nav ? 'stickyTwo' : 'sticky';
			}
		} elseif ( 'mobile' === $stick_on ) {
			echo 'stick_all' === $sticky_appearance || 'stick-all' === $get_nav ? 'mobile-stickyTwo' : 'mobile-sticky';
		}
	}
}

/**
 * Page TOC
 */
function docy_toc( $post_type ) {

	$is_toc = docy_meta('is_toc');

	// Check if 'is_toc' exists and if its value is 'default'
	if ( isset( $is_toc ) && 'default' === $is_toc ) {
		$is_toc = docy_opt( "is_" . $post_type . "_toc" );
	} else {
		$is_toc = ! empty( $is_toc ) ? $is_toc : '';
	}

	return $is_toc;
}

/**
 * Body wrapper css classes
 */
function docy_body_wrapper_classes() {
	$class = '';
	if ( 'color' !== docy_opt('search_banner_bg') ) {
		$class .= ' sbnr-gradient';
	}
	if ( '1' === docy_opt('is_top_header') ) {
		$class .= ' has_top_header';
	}
	if ( '1' === docy_toc('post') || '1' === docy_toc( 'page' ) ) {
		$class .= ' no-overflow';
	}

	echo docy_sticky_navbar() . $class;
}

/**
 * Render the search/hero banner for the current page.
 */
if ( ! function_exists( 'docy_render_banner' ) ) {
	function docy_render_banner() {
		if ( is_singular( 'post' ) || is_404() ) {
			return;
		}

		$meta_value = docy_meta_apply( 'is_banner', '1' );

		if ( $meta_value != '1' ) {
			return;
		}

		$is_banner_meta = docy_meta( 'is_banner' );
		$homepage_ids   = docy_homepage_ids();

		if ( isset( $is_banner_meta ) && $is_banner_meta != '1' && in_array( get_the_ID(), $homepage_ids ) ) {
			return;
		}

		get_template_part( 'template-parts/header-elements/search-banner/sbnr', docy_search_banner() );
	}
}

/**
 * Search banner
 */
function docy_search_banner() {
	$opt = get_option( 'docy_opt' );
	if ( 'default' === docy_opt( 'search_banner_layout', 'default' ) ) {
		$search_banner = ! empty( $opt['select_search_banner'] ) ? $opt['select_search_banner'] : 'light';
	} else {
		$search_banner = 'el-template';
	}

	return $search_banner;
}

/**
 * Is aesthetic banner default
 */
function docy_is_aesthetic_default() {
	$opt               = get_option( 'docy_opt' );
	// Per-post meta (header type, banner preset) is only meaningful on singular
	// views. On archives the global $post is the first post in the loop, so
	// reading its meta would let one post's banner leak onto the whole archive —
	// fall back to the global banner option there.
	$is_singular       = is_singular();
	// docy_meta() returns the default when no meta is saved, so default to
	// 'default' — otherwise the empty string would never match below.
	$header_type       = $is_singular ? docy_meta( 'docy_header_type', 'default' ) : 'default';
	$banner_preset     = $is_singular ? docy_meta( 'banner_preset' ) : '';
	$search_banner_opt = $opt['select_search_banner'] ?? 'light';
	$search_banner     = ! empty( $banner_preset ) && 'default' !== $banner_preset ? $banner_preset : $search_banner_opt;

	return 'aesthetic' === $search_banner && 'default' === $header_type;
}

/**
 * Get titlebar excerpt
 */
function docy_excerpt() {
	if ( ! is_search() ) {
		if ( is_tag() ) {
			echo wpautop( tag_description( get_queried_object()->term_id ) );
		} elseif ( is_category() ) {
			echo wpautop( category_description( get_queried_object()->term_id ) );
		} else {
			echo has_excerpt() ? wpautop( get_the_excerpt() ) : '';
		}
	}
}

/**
 * Modified Date
 */
function docy_modified_date() {
	if ( is_home() ) {
		$recent_posts = get_posts( [
			'numberposts'            => 1, // Number of recent posts thumbnails to display
			'post_status'            => 'publish', // Show only the published posts
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
		] );

		if ( ! empty( $recent_posts ) ) {
			$modified_date = get_the_modified_time( get_option( 'date_format' ), $recent_posts[0] );
			echo esc_html( $modified_date );
		}
	} else {
		the_modified_date( get_option( 'date_format' ) );
	}
}

/**
 * Estimated reading time
 **/
function docy_reading_time( $post ) {
	$content     = get_post_field( 'post_content', $post );
	$word_count  = str_word_count( strip_tags( $content ) );
	$readingtime = (int) ceil( $word_count / 200 );
	if ( 1 === $readingtime ) {
		$timer = esc_html__( " minute", 'docy' );
	} else {
		$timer = esc_html__( " minutes", 'docy' );
	}
	$totalreadingtime = $readingtime . $timer;
	echo esc_html( $totalreadingtime );
}

/**
 * Allowed HTML for wp_kses function
 *
 * @return array
 */
function docy_allowed_html() {
	return [
		'a' => [
			'class'  => [],
			'href'   => true,
			'rel'    => true,
			'rev'    => true,
			'name'   => true,
			'target' => true,
		],

		'br' => [],

		'p' => [
			'class' => [],
		],

		'strong' => [],
		'div'    => [
			'style' => [],
			'class' => []
		],

		'img' => [
			'class'  => [],
			'src'    => [],
			'srcset' => [],
			'alt'    => [],
		],
	];
}

/**
 * Banner preset background styles
 *
 * @return array[]|string[]
 */
function docy_banner_bg_style() {
	return [
		'color'           => DOCY_DIR_IMG . '/options/color.png',
		'faded-sun'       => DOCY_DIR_IMG . '/options/faded-sun.jpg',
		'happy-journey'   => DOCY_DIR_IMG . '/options/happy-journey.jpg',
		'apparent-circle' => DOCY_DIR_IMG . '/options/apparent-circle.jpg',
		'soft-weather'    => DOCY_DIR_IMG . '/options/soft-weather.jpg',
		'romantic-sun'    => DOCY_DIR_IMG . '/options/romantic-sun.jpg',
		'teal-eclipse'    => DOCY_DIR_IMG . '/options/teal-eclipse.jpg',
	];
}

/**
 * Get elementor templates
 *
 * @return array[]
 */
function docy_elementor_template() {
	$cache_key                 = 'docy_elementor_template_cache';
	$elementor_templates_array = get_transient( $cache_key );

	if ( false === $elementor_templates_array ) {
		// Bolt: Optimized to fetch only ID and Title via direct SQL, avoiding full object hydration.
		global $wpdb;
		$query = $wpdb->prepare(
			"SELECT ID, post_title FROM $wpdb->posts WHERE post_type = %s AND post_status = %s ORDER BY post_date DESC LIMIT %d",
			'elementor_library',
			'publish',
			500
		);
		$elementor_templates = $wpdb->get_results( $query );

		$elementor_templates_array = [];
		if ( ! empty( $elementor_templates ) ) {
			foreach ( $elementor_templates as $elementor_template ) {
				$elementor_templates_array[ $elementor_template->ID ] = $elementor_template->post_title;
			}
		}

		set_transient( $cache_key, $elementor_templates_array, WEEK_IN_SECONDS );
	}

	return $elementor_templates_array;
}

function docy_get_page_title( $page_title_name = '' ) {
	global $wpdb;

	// Use direct SQL to avoid loading all matching pages into memory (performance/memory concern)
	// Order by post_date ASC to match legacy behavior (returns oldest page if duplicates exist)
	$page_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'page' AND post_status = 'publish' ORDER BY post_date ASC LIMIT 1",
		$page_title_name
	) );

	return $page_id ? (int) $page_id : '';
}

/**
 * Remove px || em || % from array
 *
 * @param $array
 *
 */
function docy_dimension_exclude( $array ) {
	$result = [];

	foreach ( $array as $value ) {
		$valueWithoutPx = str_replace( 'px', '', $value );
		$valueWithoutPx = str_replace( 'em', '', $valueWithoutPx );
		$valueWithoutPx = str_replace( '%', '', $valueWithoutPx );
		$result[]       = $valueWithoutPx;
	}

	return $result;
}

function docy_theme_option_dimension( $key, $mode ) {

	$top    = docy_opt( $key )[ $mode . '-top' ] ?? '';
	$right  = docy_opt( $key )[ $mode . '-right' ] ?? '';
	$left   = docy_opt( $key )[ $mode . '-left' ] ?? '';
	$bottom = docy_opt( $key )[ $mode . '-bottom' ] ?? '';
	$modePx = $top . $right . $left . $bottom;

	if ( preg_match( '/px|em|%/', $modePx ) ) {

		$top          = docy_opt( $key )[ $mode . '-top' ] ?? '';
		$right        = docy_opt( $key )[ $mode . '-right' ] ?? '';
		$left         = docy_opt( $key )[ $mode . '-left' ] ?? '';
		$bottom       = docy_opt( $key )[ $mode . '-bottom' ] ?? '';
		$padding_unit = docy_opt( $key )['units'] ?? '';

		$top    = docy_dimension_exclude( [ $top ] )[0];
		$right  = docy_dimension_exclude( [ $right ] )[0];
		$left   = docy_dimension_exclude( [ $left ] )[0];
		$bottom = docy_dimension_exclude( [ $bottom ] )[0];

		$key = [
			'top'    => $top,
			'right'  => $right,
			'left'   => $left,
			'bottom' => $bottom,
			'units'  => $padding_unit
		];
	} else {
		$key = docy_opt( $key );
	}

	return $key;
}

function docy_theme_option_typo( $key = '' ) {

	$font_size   = docy_opt( $key )['font-size'] ?? '';
	$line_height = docy_opt( $key )['line-height'] ?? '';

	$typoPx = $font_size . $line_height;

	if ( preg_match( '/px|em|%/', $typoPx ) ) {

		$font_size   = docy_dimension_exclude( [ $font_size ] )[0];
		$line_height = docy_dimension_exclude( [ $line_height ] )[0];

		$typo = [
			'font-family' => docy_opt( $key )['font-family'] ?? '',
			'font-size'   => $font_size,
			'font-weight' => docy_opt( $key )['font-weight'] ?? '',
			'subset'      => docy_opt( $key )['subsets'] ?? '',
			'line-height' => $line_height,
			'color'       => docy_opt( $key )['color'] ?? '',
			'text-align'  => docy_opt( $key )['text-align'] ?? '',
		];

	} else {
		$typo = docy_opt( $key );
	}

	return $typo;
}


/*
 * Set post views count using post meta
 */
function docy_post_views( $post_ID ) {
	// Check for bots to prevent DB writes and inflated views
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
	$bot_pattern = '/bot|crawl|slurp|spider|mediapartners|facebookexternalhit|whatsapp|google|bing|yahoo|duckduckgo|baidu|yandex/i';

	if ( preg_match( $bot_pattern, $user_agent ) ) {
		return;
	}

	$countKey = 'docy_post_views_count';
	$count    = get_post_meta( $post_ID, $countKey, true );
	if ( '' === $count ) {
		delete_post_meta( $post_ID, $countKey );
		add_post_meta( $post_ID, $countKey, '1' );
	} else {
		$count ++;
		update_post_meta( $post_ID, $countKey, $count );
	}
}

/**
 * Limit latter
 *
 * @param        $string
 * @param        $limit_length
 * @param string $suffix
 */
function docy_limit_letter( $string, $limit_length, $suffix = '...' ) {
	if ( strlen( $string ) > $limit_length ) {
		echo strip_shortcodes( substr( $string, 0, $limit_length ) . $suffix );
	} else {
		echo strip_shortcodes( esc_html( $string ) );
	}
}

/**
 * Retrieve an associative array of post IDs and titles for a given post type.
 *
 * @param string $post_type The post type to retrieve posts from.
 *
 * @return array Associative array of post IDs and titles.
 */
function docy_get_post_options( string $post_type ): array {
	$cache_key = 'docy_post_options_' . sanitize_key( $post_type );
	$options   = get_transient( $cache_key );

	if ( false === $options ) {
		// Initialize the options array with a default value.
		$options = [ '' => esc_html__( 'Default', 'docy' ) ];

		// Bolt: Optimized to fetch only ID and Title via direct SQL, avoiding full object hydration.
		global $wpdb;
		$query = $wpdb->prepare(
			"SELECT ID, post_title FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish' ORDER BY post_title ASC",
			$post_type
		);
		$posts = $wpdb->get_results( $query );

		// Add each post's ID and title to the options array.
		foreach ( $posts as $post ) {
			$options[ $post->ID ] = $post->post_title;
		}

		set_transient( $cache_key, $options, WEEK_IN_SECONDS );
	}

	return $options;
}


/**
 * Page title
 *
 * @return string
 */
function docy_page_title() {
	$opt = get_option( 'docy_opt' );

	if ( is_home() ) {
		$blog_title = ! empty( $opt['blog_title'] ) ? $opt['blog_title'] : esc_html__( 'Blog', 'docy' );
		echo esc_html( $blog_title );
	} elseif ( class_exists( 'WooCommerce' ) && is_shop() ) {
		$shop_title = ! empty( $opt['shop_title'] ) ? $opt['shop_title'] : esc_html__( 'Shop', 'docy' );
		echo esc_html( $shop_title );
	} elseif ( is_category() ) {
		single_cat_title();
	} elseif ( is_tag() ) {
		single_tag_title();
	} elseif ( is_tax() ) {
		single_term_title();
	} elseif ( is_author() ) {
		echo esc_html( get_the_author() );
	} elseif ( is_archive() ) {
		the_archive_title();
	} elseif ( ( function_exists( 'is_bbpress' ) && is_bbpress() ) || in_array( 'bbpress', get_body_class() ) ) {
		echo esc_html( get_the_title() );
	} elseif ( is_page() || is_single() ) {
		echo esc_html( get_the_title() );
	} elseif ( is_search() ) {
		echo esc_html__( 'Search result for: “', 'docy' );
		echo esc_html( get_search_query() ) . esc_html__( '”', 'docy' );
	} else {
		echo esc_html( get_the_title() );
	}
}

/**
 * Page subtitle
 *
 * @return string
 */
function docy_page_subtitle() {
	$opt      = get_option( 'docy_opt' );
	$subtitle = ''; // Initialize an empty variable for the subtitle.

	if ( '1' === docy_opt( 'sbnr_subtitle_fieldset', '', 'is_page_subtitle' ) ) {
		if ( is_home() ) {
			$subtitle = ! empty( $opt['blog_subtitle'] ) ? esc_html( $opt['blog_subtitle'] ) : '';
		} elseif ( is_page() || is_single() ) {
			$subtitle = has_excerpt() && !is_singular('product') ? get_the_excerpt() : '';
		} elseif ( is_category() ) {
			$subtitle = category_description();
		} elseif ( is_tag() ) {
			$subtitle = tag_description();
		} elseif ( is_tax() ) {
			$subtitle = term_description();
		}

		// Echo the subtitle wrapped with wpautop.
		echo wpautop( $subtitle );
	}
}

/**
 * Extracts the YouTube video ID from a given URL.
 *
 * This function uses a regular expression to find the 'v' parameter
 * in a YouTube URL, which contains the video ID.
 *
 * @param string $url The YouTube video URL to extract the ID from.
 *
 * @return string The extracted YouTube video ID, or an empty string if not found.
 */
function docy_get_youtube_video_id( $url ): string {
	preg_match('/[\\?\\&]v=([^\\?\\&]+)/', $url, $matches);
	return $matches[1] ?? '';
}

/**
 * Docy ajax search breadcrumb.
 *
 * @return void
 */
function docy_ajax_search_breadcrumb() {

    global $post; 
    $breadcrumb 	= [];
    $current_post 	= $post; // Get the global post object

    // Loop through parent hierarchy
    while ( $current_post->post_parent ) {
        $parent_post 	= get_post( $current_post->post_parent );
		$breadcrumb[] 	= '<li class="breadcrumb-item"><a href="' . esc_url( get_permalink( $parent_post->ID ) ) . '">' . esc_html( $parent_post->post_title ) . '</a></li>';
        $current_post 	= $parent_post;
    }

    // Reverse to maintain order from root to current post
    $breadcrumb = array_reverse( $breadcrumb );

    // Add the current post title (not a link)
    $breadcrumb[] = '<li class="breadcrumb-item active">' . esc_html( get_the_title( $post->ID ) ) . '</li>';

    // Output breadcrumb list items
    echo implode( "\n", $breadcrumb );
}

/**
 * Get docs slug
 */
function docy_get_docs_slug(){
    $post_type_object = get_post_type_object('docs');
    if ( $post_type_object && ! is_wp_error( $post_type_object ) ) {
        return ucfirst( $post_type_object->rewrite['slug'] ?? '' );
    }
}

/**
 * Retrieve specific post type slug and it's modified slug
 *
 * @param string $post_type The post type key.
 */
function docy_get_modified_slug_by_post_type( $post_type ) {
    $slug = $post_type;

    if ( $obj = get_post_type_object( $post_type ) ) {
        $slug = $obj->labels->name ?? $post_type;
    }

    // Clean and format: remove unwanted characters, replace -/_ with space, and capitalize
    $slug = preg_replace( '/[^a-zA-Z0-9\s_-]/', ' ', $slug );     // Remove special chars except - and _
    $slug = preg_replace( '/[-_]+/', ' ', $slug );               // Replace -/_ with space
    return ucwords( strtolower( trim( $slug ) ) );               // Capitalize each word
}

/**
 * Retrieve all the post types slugs and their modified slugs.
 *
 * @param bool $modified If true, returns modified slugs from the post type rewrite rules.
 * If false, returns the original post type slugs.
 *
 */
function docy_get_modified_post_type_slugs( $modified = false ) {
    $sbnr_post_types = docy_opt( 'sbnr_post_types' );
    $results         = [];

    if ( ! empty( $sbnr_post_types ) ) {
        $all_post_types = get_post_types( [], 'objects' );

        foreach ( $sbnr_post_types as $type ) {
            if ( isset( $all_post_types[ $type ] ) ) {
                $slug = $modified && ! empty( $all_post_types[ $type ]->labels->name )
                    ? $all_post_types[ $type ]->labels->name
                    : $type;

                $results[ $type ] = $slug;
            }
        }
    }

    return $results;
}

/**
 * Check if bbPress || BBP Core is active
 */
function is_bbp_core_active() {
    return class_exists( 'bbPress' ) || class_exists( 'BBP_Core' );
}

/**
 * Clear cache for post options when a post is saved or deleted.
 *
 * @param int $post_id The post ID.
 */
function docy_clear_post_options_cache( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	$post_type = get_post_type( $post_id );
	if ( empty( $post_type ) ) {
		return;
	}

	if ( 'elementor_library' === $post_type ) {
		delete_transient( 'docy_elementor_template_cache' );
	}

	// `docy_get_post_options()` is generic; clear cache for any post type.
	delete_transient( 'docy_post_options_' . sanitize_key( $post_type ) );
}
add_action( 'save_post', 'docy_clear_post_options_cache' );
add_action( 'before_delete_post', 'docy_clear_post_options_cache' );

/**
 * Render standard search form for header layouts.
 *
 * Centralizes the search form markup, resolving duplication between
 * layout-default.php and layout-search.php.
 *
 * @param array $args Custom arguments for the search form.
 * @return void
 */
function docy_render_search_form( array $args = [] ): void {
	$defaults = [
		'class'       => 'search-input',
		'method'      => 'get',
		'action'      => home_url( '/' ),
		'placeholder' => esc_attr__( 'Search...', 'docy' ),
		'value'       => get_search_query(),
		'show_button' => true,
	];

	$parsed_args = wp_parse_args( $args, $defaults );

	?>
	<form action="<?php echo esc_url( $parsed_args['action'] ); ?>" class="<?php echo esc_attr( $parsed_args['class'] ); ?>" method="<?php echo esc_attr( $parsed_args['method'] ); ?>">
		<input type="search" placeholder="<?php echo esc_attr( $parsed_args['placeholder'] ); ?>" name="s" value="<?php echo esc_attr( $parsed_args['value'] ); ?>" aria-label="<?php esc_attr_e( 'Search', 'docy' ); ?>">
		<?php if ( $parsed_args['show_button'] ) : ?>
			<button type="submit" class="search-icon" aria-label="<?php esc_attr_e( 'Search', 'docy' ); ?>">
				<i class="icon_search" aria-hidden="true"></i>
			</button>
		<?php endif; ?>
		<div class="search-spinner spinner-border spinner-border-sm text-primary" role="status">
			<span class="visually-hidden"><?php esc_html_e( 'Loading...', 'docy' ); ?></span>
		</div>
	</form>
	<?php
}

