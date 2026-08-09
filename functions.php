<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Automatic GitHub main deployment through the installed Deployer plugin. */
require_once get_template_directory() . '/inc/trb-auto-deploy.php';

/**
 * docy functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package docy
 */


/**
 * Error handling configuration
 */
error_reporting( E_ALL & ~E_WARNING );
if ( ! headers_sent() ) {
	@ini_set( 'display_errors', 0 );
}


if ( ! function_exists( 'docy_fs' ) ) {
    // Create a helper function for easy SDK access.
    function docy_fs() {
        global $docy_fs;

        if ( ! isset( $docy_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

            $docy_fs = fs_dynamic_init( array(
                'id'                  => '32760',
                'slug'                => 'docy',
                'premium_slug'        => 'docy',
                'type'                => 'theme',
                'public_key'          => 'pk_506fc3db2df24ddab2ba8794b2afa',
                'is_premium'          => true,
                'premium_suffix'      => 'Pro',
                // If your theme is a serviceware, set this option to false.
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'is_org_compliant'    => false,
                // Never auto-show the Freemius opt-in/activation screen. License
                // activation is handled on the unified Register/Verify page (and the
                // Freemius Account page), so the theme stays usable without forcing
                // the connect screen — including for ThemeForest-licensed installs.
                'anonymous_mode'      => true,
                'menu'                => array(
                    'slug'           => 'docy_template',
                    'first-path'     => 'admin.php?page=docy_template',
                    'support'        => false,
                    // Licensing lives on the unified Register/Verify page; keep only
                    // the Freemius Account submenu and hide Pricing/Contact clutter.
                    'pricing'        => false,
                    'contact'        => false,
                ),
            ) );


/** Area Artisti TRB rec */
require get_template_directory() . '/inc/trb-artist-portal.php';
require get_template_directory() . '/inc/trb-demo-automation.php';
require get_template_directory() . '/inc/trb-artist-pcloud-archive.php';
require get_template_directory() . '/inc/trb-artist-promo-archive.php';
require get_template_directory() . '/inc/trb-release-pcloud-archive.php';
require get_template_directory() . '/inc/trb-release-analysis.php';
require get_template_directory() . '/inc/trb-resource-monitor.php';
require get_template_directory() . '/inc/trb-portal-launch-campaign.php';

/** Canonical portal favicon (also covers admin and login screens). */
function trb_portal_favicon() {
	echo '<link rel="icon" href="' . esc_url( home_url( '/favicon.ico' ) ) . '" sizes="any">';
}
add_action( 'wp_head', 'trb_portal_favicon', 1 );
add_action( 'admin_head', 'trb_portal_favicon', 1 );
add_action( 'login_head', 'trb_portal_favicon', 1 );

        }

        return $docy_fs;
    }

    // Init Freemius.
    docy_fs();
    // Signal that SDK was initiated.
    do_action( 'docy_fs_loaded' );
}

// Handle null post object errors
add_action(
	'init',
	function () {
		add_filter(
			'display_post_states',
			function ( $post_states, $post ) {
				if ( ! is_object( $post ) || empty( $post ) ) {
					return [];
				}
				return $post_states;
			},
			1,
			2
		);
	}
);


/**
 * Admin notice for Forumax Core plugin version check
 * Shows when the plugin is active but outdated
 */
if ( ! function_exists( 'docy_forumax_core_version_check' ) ) {
	function docy_forumax_core_version_check() {
		$plugin_path = 'bbp-core/forumax.php';

		// Check if the plugin is active
		if ( is_plugin_active( $plugin_path ) ) {
			$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_path;

			// Check if plugin file exists
			if ( ! file_exists( $plugin_file ) ) {
				return;
			}

			// Get plugin data
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_data       = get_plugin_data( $plugin_file );
			$current_plugin_v  = $plugin_data['Version'];
			$required_plugin_v = '2.1.0';

			// Compare versions
			if ( version_compare( $current_plugin_v, $required_plugin_v, '<' ) ) {
				?>
				<div class="notice notice-error" style="border-left-color: #dc3232; padding: 15px;">
					<div style="display: flex; align-items: flex-start; gap: 15px;">
						<span style="font-size: 40px; line-height: 1;">⚠️</span>
						<div style="flex: 1;">
							<h2 style="margin: 0 0 10px 0; color: #dc3232; font-size: 18px;">
								<?php esc_html_e( '🚨 CRITICAL: Immediate Action Required!', 'docy' ); ?>
							</h2>
							<p style="font-size: 15px; margin: 0 0 10px 0; line-height: 1.6;">
								<strong><?php esc_html_e( 'Your site may break or malfunction!', 'docy' ); ?></strong>
							</p>
							<p style="font-size: 14px; margin: 0 0 15px 0; line-height: 1.6;">
								<?php
								printf(
									/* translators: 1: Current version, 2: Required version */
									esc_html__( 'Your %1$s is outdated (version %2$s). Please update to version %3$s or higher to avoid compatibility issues. We have made significant changes to the plugin architecture, and using an outdated version will cause compatibility issues and may break your website functionality.', 'docy' ),
									'<strong>' . esc_html__( 'Forumax Plugin', 'docy' ) . '</strong>',
									'<code style="background: #fff3cd; padding: 2px 6px; border-radius: 3px; color: #856404;">' . esc_html( $current_plugin_v ) . '</code>',
									'<code style="background: #d4edda; padding: 2px 6px; border-radius: 3px; color: #155724; font-weight: bold;">' . esc_html( $required_plugin_v ) . '</code>'
								);
								?>
							</p>
							<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px;">
								<p style="margin: 0; font-size: 13px; color: #856404;">
									<strong><?php esc_html_e( '⚡ What has changed:', 'docy' ); ?></strong><br>
									<?php esc_html_e( 'We have completely restructured the plugin with new features, improved performance, enhanced security, and better compatibility. The old version is no longer compatible with the latest theme updates.', 'docy' ); ?>
								</p>
							</div>
							<p style="margin: 0;">
								<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" class="button button-primary" style="background: #dc3232; border-color: #dc3232; font-size: 14px; height: auto; padding: 8px 20px;">
									<?php esc_html_e( '🔄 Update Forumax Plugin Now', 'docy' ); ?>
								</a>
								<span style="margin-left: 15px; color: #666; font-size: 13px;">
									<?php esc_html_e( 'This update is mandatory for your site to function properly.', 'docy' ); ?>
								</span>
							</p>
						</div>
					</div>
				</div>
				<style>
					.notice.notice-error h2 {
						font-size: 18px !important;
					}
				</style>
				<?php
			}
		}
	}
}


if ( ! function_exists( 'docy_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 */
	function docy_setup() {
		/*
		 * Make theme available for translation.
		 * Translations can be filed in the /languages/ directory.
		 * If you're building a theme based on gull, use a find and replace
		 * to change 'gull' to the name of your theme in all the template files.
		 */
		load_theme_textdomain( 'docy', get_template_directory() . '/languages' );

		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		// Enable excerpt support for page
		add_post_type_support( 'page', 'excerpt' );

		/*
		 * Let WordPress manage the document title.
		 * By adding theme support, we declare that this theme does not use a
		 * hard-coded <title> tag in the document head, and expect WordPress to
		 * provide it for us.
		 */
		add_theme_support( 'title-tag' );
		add_theme_support( 'woocommerce' );

		/*
		 * Enable support for Post Thumbnails on posts and pages.
		 *
		 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		 */
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'post-formats', [ 'video', 'quote', 'link' ] );

		// This theme uses wp_nav_menu() in one location.
		register_nav_menus(
			[
				'main_menu' => esc_html__( 'Main Menu', 'docy' ),
			]
		);

		/*
		 * Switch default core markup for search form, comment form, and comments
		 * to output valid HTML5.
		 */
		add_theme_support(
			'html5',
			[
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
			]
		);

		add_theme_support( 'align-wide' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'style-editor.css' );
		add_theme_support( 'responsive-embeds' );

		// Disable Sidebar widgets block editor
		if ( 'classic' === docy_opt( 'is_sidebar_editor' ) ) {
			add_filter( 'use_widgets_block_editor', '__return_false' );
		}

		/**
		 * Theme settings
		 */
		// Include CSF
		require_once get_template_directory() . '/inc/csf/classes/setup.class.php';
		if ( class_exists( 'CSF' ) ) {

			// Theme settings
			require_once get_template_directory() . '/inc/admin/settings-options.php';

			// Metaboxes
			require get_template_directory() . '/inc/meta/all-meta-boxes.php';
			require get_template_directory() . '/inc/meta/meta-register-login.php';
			require get_template_directory() . '/inc/meta/meta-post-format.php';
			require get_template_directory() . '/inc/meta/remove-meta.php';

		}
	}
endif;
add_action( 'after_setup_theme', 'docy_setup' );


/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function docy_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'docy_content_width', 1270 );
}

add_action( 'after_setup_theme', 'docy_content_width', 0 );

function docy_custom_login_failed_redirect( $username ) {
	if ( isset( $_SERVER['HTTP_REFERER'] ) && false !== strpos( $_SERVER['HTTP_REFERER'], 'sign-in' ) ) {
		$redirect_url = esc_url_raw( add_query_arg( 'login', 'failed', $_SERVER['HTTP_REFERER'] ) );
		// Sentinel Fix: Prevent Open Redirect by using a safe redirect
		wp_safe_redirect( $redirect_url );
		exit;
	}
}
add_action( 'wp_login_failed', 'docy_custom_login_failed_redirect' );

/**
 * Constants
 * Defining default asset paths
 */
define( 'DOCY_DIR_CSS', get_template_directory_uri() . '/assets/css' );
define( 'DOCY_DIR_JS', get_template_directory_uri() . '/assets/js' );
define( 'DOCY_DIR_VEND', get_template_directory_uri() . '/assets/vendors' );
define( 'DOCY_DIR_IMG', get_template_directory_uri() . '/assets/img' );
define( 'DOCY_DIR_FONT', get_template_directory_uri() . '/assets/fonts' );

$my_theme = wp_get_theme( 'docy' );
define( 'DOCY_VERSION', $my_theme->Version );

/**
 * Required plugins activation
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 *
 */
require get_template_directory() . '/inc/enqueue.php';

/**
 * Theme's helper functions
 */
require get_template_directory() . '/inc/classes/Docy_helper.php';

// ACF widgets abd blocks
require get_template_directory() . '/inc/acf/widgets-blocks.php';

/**
 * Theme's filters and actions
 */
require get_template_directory() . '/inc/filter_actions.php';
require get_template_directory() . '/inc/woo_config.php';
require get_template_directory() . '/inc/ajax_actions.php';
require get_template_directory() . '/inc/comment-functions.php';
require get_template_directory() . '/inc/reg_process.php';

/**
 * Classes
 */
require get_template_directory() . '/inc/classes/Docy_Mobile_Nav_Walker.php';
require get_template_directory() . '/inc/classes/Docy_Walker_Comment.php';

// updater
require get_template_directory() . '/inc/classes/Docy_base.php';
require get_template_directory() . '/inc/classes/Docy_register_theme.php';
require get_template_directory() . '/inc/classes/Docy_update_checker.php';
require get_template_directory() . '/inc/verify/class.verify-purchase.php';

/**
 * Admin notices
 */
if ( is_admin() ) {
	require_once get_template_directory() . '/inc/admin/notices/welcome-notice.php';
	require_once get_template_directory() . '/inc/admin/notices/pro-elements-conflict.php';
}


/**
 * Configure one click demo
 */
require get_template_directory() . '/inc/demo_config.php';

/**
 * Required plugins activation
 */
require get_template_directory() . '/inc/tgm/plugin_activation.php';

/**
 * Bootstrap Nav Walker
 */
require get_template_directory() . '/inc/classes/Docy_Nav_Walker.php';

/**
 * Register Sidebar Areas
 */
require get_template_directory() . '/inc/sidebars.php';

/**
 * Admin Page
 */
require get_template_directory() . '/inc/Admin.php';
