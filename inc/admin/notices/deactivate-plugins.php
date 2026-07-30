<?php
defined( 'ABSPATH' ) || exit;

/**
 * Notice
 * Deactivate the ACF plugin
 *
 * @return void
 */
function docy_get_plugin_deactivation_url( $plugin_slug ) {
	return wp_nonce_url(
		add_query_arg(
			'docy-deactivate-plugin',
			sanitize_key( $plugin_slug ),
			admin_url( 'themes.php' )
		),
		'docy_deactivate_plugin_' . sanitize_key( $plugin_slug )
	);
}

add_action( 'admin_notices', function () {
	if ( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) :
		?>
		<div class="notice notice-warning eaz-notice">
			<p>
				<?php esc_html_e( 'We replaced the ACF metaboxes with the Theme Settings options framework to make the theme more lightweight and dependency free.', 'docy' ); ?> <br>
				<?php esc_html_e( 'Deactivate ACF Pro plugin to avoid conflict with the new metabox fields.', 'docy' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( docy_get_plugin_deactivation_url( 'advanced-custom-fields-pro' ) ); ?>" class="button-primary button-large">
					<?php esc_html_e( 'Deactivate ACF Pro', 'docy' ); ?>
				</a>
			</p>
		</div>
	    <?php
	endif;
} );

/**
 * Deactivate plugins action
 */
function docy_deactivate_other_plugin() {
	if ( empty( $_GET['docy-deactivate-plugin'] ) || empty( $_GET['_wpnonce'] ) ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$plugin = sanitize_key( wp_unslash( $_GET['docy-deactivate-plugin'] ) );
	$nonce  = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

	if ( 'advanced-custom-fields-pro' !== $plugin ) {
		return;
	}

	if ( ! wp_verify_nonce( $nonce, 'docy_deactivate_plugin_' . $plugin ) ) {
		return;
	}

	deactivate_plugins( 'advanced-custom-fields-pro/acf.php' );
	wp_safe_redirect( admin_url( 'plugins.php' ) );
	exit;
}
add_action( 'admin_init', 'docy_deactivate_other_plugin' );