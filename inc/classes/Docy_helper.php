<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Docy theme helper functions and resources
 */

class Docy_Helper_Class {
	/**
	 * Hold an instance of Docy_Helper_Class class.
	 *
	 * @var Docy_Helper_Class
	 */
	protected static $instance = null;

	/**
	 * Main Docy_Helper_Class instance.
	 *
	 * @return Docy_Helper_Class - Main instance.
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new Docy_Helper_Class();
		}

		return self::$instance;
	}

	/**
	 * Website Logo
	 *
	 * @return void
	 */
	public function logo() {
		$opt = get_option( 'docy_opt' );

		// Main Logo
		$main_logo   = $opt['main_logo']['url'] ?? '';
		$retina_logo = ! empty( $opt['retina_logo']['url'] ) ? 'srcset="' . esc_url( $opt['retina_logo']['url'] ) . ' 2x"' : '';

		// Sticky Logo
		$sticky_logo        = $opt['sticky_logo']['url'] ?? '';
		$retina_sticky_logo = ! empty( $opt['retina_sticky_logo']['url'] ) ? 'srcset="' . esc_url( $opt['retina_sticky_logo']['url'] ) . ' 2x"' : '';

		// Multi Logo Feature
		$is_multi_logo = isset( $opt['is_multi_logo'] ) && '1' === $opt['is_multi_logo'];
		?>
        <div class="docy-logo-wrapper<?php echo $is_multi_logo ? ' has-multi-logo' : ''; ?>">
            <a class="navbar-brand header_logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php
				if ( ! empty( $main_logo ) ) :
					?>
                    <img class="first_logo sticky_logo" src="<?php echo esc_url( $main_logo ); ?>" alt="<?php bloginfo( 'name' ); ?>" <?php echo $retina_logo; ?>>
					<?php if ( ! empty( $sticky_logo ) ) : ?>
                    <img class="white_logo" src="<?php echo esc_url( $sticky_logo ); ?>" alt="<?php bloginfo( 'name' ); ?>" <?php echo $retina_sticky_logo; ?>>
				<?php endif; ?>
					<?php
				else :
					?>
                    <h3><?php echo get_bloginfo( 'name' ); ?></h3>
					<?php
				endif;
				?>
            </a>
			<?php
			// Render Multi Logo Dropdown
			if ( $is_multi_logo ) {
				$this->multi_logo_dropdown( $opt );
			}
			?>
        </div>
		<?php
	}

	/**
	 * Multi Logo Dropdown
	 *
	 * Renders a dropdown menu with multiple site logos
	 *
	 * @param array $opt Theme options array.
	 *
	 * @return void
	 */
	public function multi_logo_dropdown( $opt ) {
		$sites   = $opt['multi_logo_sites'] ?? [];
		$see_all = $opt['multi_logo_see_all_fieldset'] ?? [];

		if ( empty( $sites ) ) {
			return;
		}

		// Include the template file.
		include get_template_directory() . '/template-parts/header/multi-logo-dropdown.php';
	}

	/**
	 * Render the Navbar classes based on conditions
	 *
	 * @return void
	 */
	public function navbar_type() {
		// Page-level header meta is only meaningful on singular views. On archives
		// (category, tag, blog) the global $post is set to the first post in the
		// loop, so reading post meta there would wrongly inherit that post's
		// header type — always treat archives as the default page header.
		$header_type_page   = is_singular() ? docy_meta( 'docy_header_type', 'default' ) : 'default';
		$search_banner_type = docy_opt( 'select_search_banner', 'light' );
		$header_type        = 'default' !== $header_type_page ? $header_type_page : '';

		// An explicit Navbar Color choice (set in Theme Settings) only applies on
		// pages that use the default page-level header.
		$explicit_navbar_color = 'default' === $header_type_page ? docy_opt( 'navbar_color' ) : 'default';

		if ( ! isset( $header_type ) || ( 'light' === $search_banner_type && 'white' !== $header_type_page ) ) {
			$header_type = 'black';
		}

		if ( 'default' !== $explicit_navbar_color ) {
			$header_type = $explicit_navbar_color;
		}

		if ( is_singular( 'docs' ) && 'default' === $header_type_page ) {
			$header_type = docy_opt( 'docs_navbar_color', 'white' );
		}

		// The aesthetic banner preset defaults to the white (light-text) navbar,
		// but a Navbar Color explicitly chosen in Theme Settings must win —
		// otherwise dark menu text gets forced to white over a light aesthetic
		// banner (e.g. blog/category archives).
		if ( docy_is_aesthetic_default() && 'default' === $explicit_navbar_color ) {
			$header_type = 'white';
		}

		if ( is_singular( 'post' ) ) {
			$header_type = 'white';
		}

		// 404 pages always use the dark navbar, while blog/category/tag archives
		// should still respect an explicitly selected white navbar.
		if ( is_404() ) {
			$header_type = 'black';
		}

		if ( ( is_home() || is_category() || is_tag() ) && 'white' !== $header_type ) {
			$header_type = 'black';
		}

		$nav_classes = 'white' === $header_type ? ' light_menu' : ' dark_menu';

		echo esc_attr( $nav_classes );
	}

	/**
	 * Social Links
	 *
	 * @return void
	 */
	public function social_links() {
		$opt             = get_option( 'docy_opt' );
		$social_networks = [
			'facebook'  => 'social_facebook',
			'twitter'   => 'fa-brands fa-x-twitter',
			'instagram' => 'social_instagram',
			'linkedin'  => 'social_linkedin',
			'youtube'   => 'social_youtube',
			'github'    => 'fa fa-github',
			'dribbble'  => 'social_dribbble'
		];

		foreach ( $social_networks as $network => $icon ) {
			if ( ! empty( $opt[ $network ] ) ) {
				printf(
					'<li><a href="%s"><i class="%s" aria-hidden="true"></i></a></li>',
					esc_url( $opt[ $network ] ),
					esc_attr( $icon )
				);
			}
		}
	}

	/**
	 * Convert hexdec color string to rgb(a) string
	 *
	 * @param string          $color   Hex color string.
	 * @param float|int|false $opacity Opacity value.
	 *
	 * @return string
	 */
	public function hex2rgba( $color, $opacity = false ) {
		$default = 'rgb(0,0,0)';

		// Return default if no color provided
		if ( empty( $color ) ) {
			return $default;
		}

		// Sanitize $color if "#" is provided
		if ( '#' === $color[0] ) {
			$color = substr( $color, 1 );
		}

		// Check if color has 6 or 3 characters and get values
		if ( 6 === strlen( $color ) ) {
			$hex = [ $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] ];
		} elseif ( 3 === strlen( $color ) ) {
			$hex = [ $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] ];
		} else {
			return $default;
		}

		// Convert hexadec to rgb
		$rgb = array_map( 'hexdec', $hex );

		// Check if opacity is set(rgba or rgb)
		if ( $opacity ) {
			if ( abs( $opacity ) > 1 ) {
				$opacity = 1.0;
			}
			$output = 'rgba(' . implode( ',', $rgb ) . ',' . $opacity . ')';
		} else {
			$output = implode( ',', $rgb );
		}

		// Return rgb(a) color string
		return $output;
	}

	/**
	 * Render Meta CSS value
	 *
	 * @param string $handle    Script handle.
	 * @param array  $css_items CSS items to render.
	 *
	 * @return void
	 */
	public function dynamic_css_render( $handle, $css_items ) {
		$dynamic_css = '';
		$opt         = get_option( 'docy_opt' );

		// Banner
		$banner_background_color = docy_meta( 'banner_background_color' );

		$gradient_bg = docy_opt( 'gradient_bg_color' );
		if ( ! is_array( $gradient_bg ) ) {
			$gradient_bg = [];
		}
		$gradient_bg1 = $gradient_bg['gradient_bg_color-from'] ?? '#FFFBF2';
		$gradient_bg2 = $gradient_bg['gradient_bg_color-to'] ?? '#EDFFFD';

		if ( ! empty( $gradient_bg1 ) || ! empty( $gradient_bg2 ) ) {
			$dynamic_css .= "body:is(.blog, .topic, .search, .tag, .category, .bbpress, .single-post, .woocommerce-checkout, .woocommerce-cart) {background: linear-gradient(45deg, {$gradient_bg1}, {$gradient_bg2});}";
			$dynamic_css .= "body .bg_color_gradient{background: linear-gradient(45deg, {$gradient_bg1}, {$gradient_bg2});}";
		}

		if ( ! empty( $banner_background_color ) ) {
			$dynamic_css .= ".doc_banner_area { background: $banner_background_color !important;}";
		}

		if ( ! empty( $opt['custom_css'] ) ) {
			$dynamic_css .= $opt['custom_css'];
		}

		$toc_titles = docy_meta( 'titles' );
		// TOC post Title colors
		if ( ! empty( $toc_titles ) && is_array( $toc_titles ) ) {
			foreach ( $toc_titles as $ti => $toc_title ) {
				$ti = $ti + 1;
				if ( ! empty( $toc_title['color'] ) ) {
					$dynamic_css .= ".tip_banner_area .col-lg-4:nth-child($ti) .tip_title {color: {$toc_title['color']} !important;}";
				}
			}
		}

		// Post Banner Overlay Color
		$banner_overlay_color = docy_meta( 'banner_overlay_color' );
		if ( ! empty( $banner_overlay_color ) ) {
			$dynamic_css .= "section.tip_banner_area::before { background-color: $banner_overlay_color; }";
		}

		if ( ! empty( $opt['accent_solid_color_opt'] ) ) {
			$brand_color_rgb  = $this->hex2rgba( $opt['accent_solid_color_opt'] );
			$brand_color_dark = ! empty( $opt['brand_color_dark'] ) ? $this->hex2rgba( $opt['brand_color_dark'] ) : '';

			// If bbPress active
			$bbp_rgba_1 = '';
			$bbp_rgba_3 = '';
			if ( is_bbp_core_active() ) {
				$bbp_rgba_1 = '.bbp-your-profile > fieldset.bbp-form #password .button.wp-generate-pw, .author-badge.badge.moderator, .author-badge.badge.keymaster';
				$bbp_rgba_3 = '.bbp-your-profile > fieldset.bbp-form #password .button.wp-generate-pw:hover';
			}

			$dynamic_css .= ":root { --brand_color_rgb: $brand_color_rgb; }";
			$dynamic_css .= ! empty( $opt['brand_color_dark'] ) ? "body.body_dark { --brand_color_rgb: $brand_color_dark; }" : '';

			// background 0.1
			$dynamic_css .= "$bbp_rgba_1, .pagination .page-numbers:hover:not(.current), .woocommerce-cart .update-cart:hover:not(:disabled) { background: rgba(var(--brand_color_rgb), 0.1); }";

			// background 0.2
			$dynamic_css .= ".pagination-wrapper .page-numbers:not(.current):hover, #bbpress-forums .bbp-single-user-details #bbp-user-navigation li:not(.current) a:hover, .more a:hover, .tip_doc_area .left_sidebarlist .nav-sidebar::before, .doc_tag .nav-item .nav-link:not(.active):hover { background: rgba(var(--brand_color_rgb), 0.2) !important; }";

			// background 0.3
			$dynamic_css .= "$bbp_rgba_3 { background: rgba(var(--brand_color_rgb), 0.3); }";

			// background 0.6
			$dynamic_css .= ".direction_step { background: rgba(var(--brand_color_rgb), 0.6); }";

			// background 0.7
			$dynamic_css .= ".single_post_tags.post-tags a:hover{ background: rgba(var(--brand_color_rgb), 0.7); }";

			// background 0.8
			$dynamic_css .= ".header_search_keyword ul li a.has-bg:hover, .fill-brand, input#wp-submit, .woocommerce form.lost_reset_password button.woocommerce-Button { background: rgba(var(--brand_color_rgb), 0.8); }";

			// background 0.9
			$dynamic_css .= ".woocommerce .product-type-subscription .cart .button, .pr_details .cart_button .cart_btn { background: rgba(var(--brand_color_rgb), 0.9); }";

			// color 0.6
			$dynamic_css .= ".direction_step + .direction_step:before{ color: rgba(var(--brand_color_rgb), 0.6); }";

			// Border Color 0.1
			$dynamic_css .= "$bbp_rgba_1 { border-color: rgba(var(--brand_color_rgb), 0.1); }";
			// Border Color 0.2
			$dynamic_css .= ".search-banner-light .header_search_keyword ul li a, .doc_tag .nav-item .nav-link { border-color: rgba(var(--brand_color_rgb), 0.2); }";
			// Border Color 0.3
			$dynamic_css .= "$bbp_rgba_3, .navbar_fixed.menu_one .nav_btn, .pagination .page-numbers { border-color: rgba(var(--brand_color_rgb), 0.3); }";
			// Border Color 0.4
			$dynamic_css .= ".editor-content a, .forum-post-content .content a { text-decoration-color: rgba(var(--brand_color_rgb), 0.4);}";
		}

		$is_box_shadow = $opt['is_box_shadow'] ?? '';
		if ( '1' !== $is_box_shadow ) {
			$dynamic_css .= "
            #bbpress-forums #new-post > fieldset.bbp-form,
            .main-post, .all-answers, .bbp-reply-form, .search-main, #comments, .blog_comment_box, .bb-radius, .doc_subscribe_inner {
                 box-shadow: none;
            }";
		}

		$dynamic_css .= ".fa-x-twitter:before { content: '\\e61b'; font-family: 'Font Awesome 6 Brands' !important; font-weight: 400; }";

		wp_add_inline_style( $handle, $dynamic_css );
	}

	/**
	 * Pagination
	 *
	 * @return void
	 */
	public function pagination() {
		the_posts_pagination( [
			'screen_reader_text' => ' ',
			'prev_text'          => '<i class="arrow_carrot-left"></i>',
			'next_text'          => '<i class="arrow_carrot-right"></i>'
		] );
	}

	/**
	 * Day link to archive page
	 *
	 * @return void
	 */
	public function day_link() {
		$archive_year  = get_the_time( 'Y' );
		$archive_month = get_the_time( 'm' );
		$archive_day   = get_the_time( 'd' );
		echo get_day_link( $archive_year, $archive_month, $archive_day );
	}

	/**
	 * Post's excerpt text
	 *
	 * @param string $settings_key Settings key.
	 * @param bool   $echo         Whether to echo or return.
	 *
	 * @return string|void
	 */
	public function excerpt( $settings_key, $echo = true ) {
		$opt            = get_option( 'docy_opt' );
		$excerpt_limit  = $opt[ $settings_key ] ?? 40;
		$post_excerpt   = get_the_excerpt();
		$excerpt        = ! empty( trim( $post_excerpt ) ) ? wp_trim_words( $post_excerpt, $excerpt_limit ) : wp_trim_words( get_the_content(), $excerpt_limit );
		$excerpt_output = wp_kses_post( wpautop( $excerpt ) );
		if ( $echo ) {
			echo $excerpt_output;
		} else {
			return $excerpt_output;
		}
	}

	/**
	 * Post author avatar
	 *
	 * @param int    $size    Avatar size.
	 * @param string $default Default avatar.
	 * @param string $alt     Alt text.
	 * @param array  $args    Arguments.
	 *
	 * @return void
	 */
	public function post_author_avatar( $size = 30, $default = '', $alt = '', $args = null ) {
		$post_author_id = get_post_field( 'post_author', get_the_ID() );
		echo get_avatar( $post_author_id, $size, $default, $alt, $args );
	}

	/**
	 * Get the first category name
	 *
	 * @param string $term Term name.
	 *
	 * @return void
	 */
	public function first_category( $term = 'category' ) {
		$cats = get_the_terms( get_the_ID(), $term );
		$cat  = is_array( $cats ) ? $cats[0]->name : '';
		echo esc_html( $cat );
	}

	/**
	 * Get the first category link
	 *
	 * @param string $term Term name.
	 *
	 * @return void
	 */
	public function first_category_link( $term = 'category' ) {
		$cats = get_the_terms( get_the_ID(), $term );
		$cat  = is_array( $cats ) ? get_category_link( $cats[0]->term_id ) : '';
		echo esc_url( $cat );
	}

	/**
	 * Limit letter
	 *
	 * Note: The method name contains a typo (latter vs letter) but is preserved for backward compatibility.
	 *
	 * @param string $string       The string.
	 * @param int    $limit_length Limit length.
	 * @param string $suffix       Suffix.
	 *
	 * @return void
	 */
	public function limit_latter( $string, $limit_length, $suffix = '...' ) {
		if ( strlen( $string ) > $limit_length ) {
			echo strip_shortcodes( substr( $string, 0, $limit_length ) . $suffix );
		} else {
			echo strip_shortcodes( esc_html( $string ) );
		}
	}

	/**
	 * Doc Layout
	 *
	 * @return mixed|string
	 */
	public function doc_layout() {
		$opt             = get_option( 'docy_opt' );
		$page_doc_layout = docy_meta( 'doc_layout', 'default' );
		if ( 'default' === $page_doc_layout || '' === $page_doc_layout ) {
			$doc_layout = ! empty( $opt['doc_layout'] ) ? $opt['doc_layout'] : 'both_sidebar';
		} else {
			$doc_layout = $page_doc_layout;
		}

		return $doc_layout;
	}

	/**
	 * Page width
	 *
	 * @return string
	 */
	public function page_width() {
		$page_doc_width = docy_meta( 'doc_width', 'default' );

		if ( 'default' === $page_doc_width || '' === $page_doc_width ) {
			$header_width = docy_opt( 'header_width', 'boxed' );
		} else {
			$header_width = $page_doc_width;
		}

		return $header_width;
	}

	/**
	 * Image from Theme Settings
	 *
	 * @param string $option_id Option ID.
	 * @param string $class     CSS class.
	 * @param string $alt       Alt text.
	 *
	 * @return void
	 */
	public function image_from_settings( string $option_id = '', string $class = '', string $alt = '' ): void {

		$image_meta = docy_meta( $option_id ) ?? '';
		$image_opt  = docy_opt( $option_id ) ?? '';

		// Check if meta image contains a valid 'id' or 'url'
		$meta_is_valid = ! empty( $image_meta['id'] ) || ! empty( $image_meta['url'] );
		$image         = $meta_is_valid ? $image_meta : $image_opt;

		// Check if the image has an 'id' or a 'url' and display accordingly
		if ( ! empty( $image['id'] ) ) {
			echo wp_get_attachment_image( $image['id'], 'full', '', [ 'class' => $class ] );
		} elseif ( ! empty( $image['url'] ) ) {
			$attr_class = ! empty( $class ) ? ' class="' . esc_attr( $class ) . '"' : '';
			echo '<img src="' . esc_url( $image['url'] ) . '"' . $attr_class . ' alt="' . esc_attr( $alt ) . '">';
		}
	}
}


/**
 * Instance of Docy_Helper_Class class
 */
function Docy_helper() {
	return Docy_Helper_Class::instance();
}


if ( ! function_exists( 'docy_is_theme_licensed' ) ) {
	/**
	 * Unified license check.
	 *
	 * The theme is sold on both ThemeForest (Envato purchase code) and
	 * spider-themes.net (Freemius). This returns true when the install is
	 * licensed through either platform, so feature gating stays in one place.
	 *
	 * @return bool True if licensed via Envato or a Freemius paid license.
	 */
	function docy_is_theme_licensed() {
		// ThemeForest / Envato purchase code.
		if ( 'valid' === get_option( 'docy_purchase_code_status' ) ) {
			return true;
		}

		// spider-themes.net / Freemius paid license.
		if ( function_exists( 'docy_fs' ) && ( docy_fs()->is_paying() || docy_fs()->can_use_premium_code() ) ) {
			return true;
		}

		return false;
	}
}


if ( ! function_exists( 'docy_license_source' ) ) {
	/**
	 * Identify which platform licensed this install.
	 *
	 * @return string 'envato' | 'freemius' | '' (empty when not licensed).
	 */
	function docy_license_source() {
		if ( 'valid' === get_option( 'docy_purchase_code_status' ) ) {
			return 'envato';
		}

		if ( function_exists( 'docy_fs' ) && ( docy_fs()->is_paying() || docy_fs()->can_use_premium_code() ) ) {
			return 'freemius';
		}

		return '';
	}
}
