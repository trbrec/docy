<?php
/**
 * Theme register Framework
 * The Docy_register_theme initiate the theme engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class Docy_register_theme {

	/**
	 * Variables required for the theme updater
	 *
	 * @since 1.0.0
	 * @type string
	 */
	protected $remote_api_url = null;
	protected $theme_slug = null;
	protected $version = null;
	protected $renew_url = null;
	protected $strings = null;
	private $author;

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	function __construct( $config = array(), $strings = array() ) {

		$config = wp_parse_args( $config, array(
			'remote_api_url' => '',
			'theme_slug'     => 'docy',
			'version'        => '',
			'author'         => 'Spider-Themes',
			'renew_url'      => ''
		) );

		// Set config arguments
		$this->remote_api_url = $config['remote_api_url'];
		$this->theme_slug     = sanitize_key( $config['theme_slug'] );
		$this->version        = $config['version'];
		$this->author         = $config['author'];
		$this->renew_url      = $config['renew_url'];

		// Populate version fallback
		if ( '' == $config['version'] ) {
			$theme         = wp_get_theme( $this->theme_slug );
			$this->version = $theme->get( 'Version' );
		}

		// Strings passed in from the updater config
		$this->strings = $strings;

		add_action( 'after_setup_theme', array( $this, 'init_hooks' ) );
		add_action( 'admin_init', array( $this, 'register_option' ) );
		add_filter( 'http_request_args', array( $this, 'disable_wporg_request' ), 5, 2 );
	}

	/**
	 * [init_hooks description]
	 * @method init_hooks
	 *
	 * @return [type]     [description]
	 */
	public function init_hooks() {

		$is_licensed = function_exists( 'docy_is_theme_licensed' )
			? docy_is_theme_licensed()
			: ( 'valid' === get_option( $this->theme_slug . '_purchase_code_status', false ) );

		if ( ! $is_licensed ) {

			if ( ( ! isset( $_GET['page'] ) || 'docy' != $_GET['page'] ) ) {
				add_action( 'admin_notices', array( $this, 'admin_error' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'admin_notice' ) );

			}
		}
	}

	function admin_error() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$out = '<div class="notice notice-error is-dismissible docy-purchase-notice"><p>'
		       . sprintf( wp_kses_post( __( 'The %s theme needs to be registered. %sRegister Now%s', 'docy' ) ), 'Docy',
				'<a href="' . admin_url( 'admin.php?page=docy_verify' ) . '">', '</a>' ) . '</p></div>';
		if ( get_option( 'notice_dismissed' ) ) {
			return;
		}
		echo wp_kses_post( $out );
	}

	function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$out = '<div class="notice is-dismissible docy-purchase-notice"><p>' .
		       sprintf( wp_kses_post( __( 'Purchase code is invalid. Need a license? %sPurchase Now%s', 'docy' ) ),
			       '<a target="_blank" rel="noopener noreferrer" href="https://themeforest.net/item/docy-documentation-and-forum-wordpress-theme/31370838">', '</a>' ) .
		       '</p></div>';
		if ( get_option( 'notice_dismissed' ) ) {
			return;
		}
		echo wp_kses_post( $out );
	}

	function messages() {
		$license = trim( get_option( $this->theme_slug . '_purchase_code' ) );
		$status  = get_option( $this->theme_slug . '_purchase_code_status', false );
		if ( $status != '' ) {
			$license_icon = ( $status == 'valid' ) ? '<i class="icon_check_alt2"></i>' : '<i class="icon_error-circle_alt"></i>';
			$title        = ( $status == 'valid' ) ? esc_html__( 'Purchase Verified', 'docy' ) : esc_html__( 'Purchase Code Invalid', 'docy' );
		} else {
			$license_icon = '';
			$title        = esc_html__( 'Verify by . . .', 'docy' );
		}
		// Checks license status to display under license key
		$message = '<h4>' . $license_icon . $title . '</h4>';
		echo wp_kses_post( $message );
	}

	/**
	 * Outputs the markup used on the theme license page
	 * since 1.0.0
	 */
	function form() {
		$purchase_code_status = trim( get_option( 'docy_purchase_code_status' ) );
		$strings              = $this->strings;
		if ( $purchase_code_status == 'valid' ) {
			$type = 'password';
		} else {
			$type = 'text';
		}
		$license = trim( get_option( $this->theme_slug . '_purchase_code' ) );
		$email   = get_option( $this->theme_slug . '_register_email', false );
		$status  = get_option( $this->theme_slug . '_purchase_code_status', false );
		$purchase_code_status = get_option('docy_purchase_code_status');
		$license = get_option('docy_purchase_code');
		$button_text = ($purchase_code_status === 'valid') ? 'Reset License' : 'Verify License';
		?>	    
		<form id="verify-docy-license-form" class="st-theme-register-form">
			<input type="text" name="docy_purchase_code" value="<?php echo esc_attr($license); ?>" placeholder="Enter your purchase code" required>
			<input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('docy_verify_nonce')); ?>">
			<button type="submit" id="toggle-license-button"><?php echo esc_html($button_text); ?>
			<span class="docy-verify-preloader"></span>
		</button>
		<div id="show-result"></div>
		</form>
		<?php
	}

	/**
	 * Registers the option used to store the license key in the options table.
	 *
	 * since 1.0.0
	 */
	function register_option() {
		register_setting(
			$this->theme_slug . '-license',
			$this->theme_slug . '_purchase_code',
			array( $this, 'sanitize_license' )
		);
		register_setting(
			$this->theme_slug . '-license',
			$this->theme_slug . '_register_email'
		);
	}

	/**
	 * Disable requests to wp.org repository for this theme.
	 *
	 * @since 1.0.0
	 */
	function disable_wporg_request( $r, $url ) {
		// If it's not a theme update request, bail.
		if ( 0 !== strpos( $url, 'https://api.wordpress.org/themes/update-check/1.1/' ) ) {
			return $r;
		}

		// Decode the JSON response
		$themes = json_decode( $r['body']['themes'] );
		// Remove the active parent and child themes from the check
		$parent = get_option( 'template' );
		$child  = get_option( 'stylesheet' );
		unset( $themes->themes->$parent );
		unset( $themes->themes->$child );
		// Encode the updated JSON response
		$r['body']['themes'] = json_encode( $themes );

		return $r;
	}
}

new Docy_register_theme;