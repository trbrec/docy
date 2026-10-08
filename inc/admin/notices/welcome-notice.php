<?php
/**
 * Docy Theme Welcome Notice
 *
 * Displays a welcome notice on the themes page after theme activation.
 * Includes setup steps, quick actions, and helpful resources.
 *
 * @package Docy
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show the welcome notice on themes.php page if not dismissed.
 *
 * @return void
 */
function docy_show_congratulations_notice() {
	global $pagenow;

	// Check if DEVELOPER_MODE is enabled for debugging
	$developer_mode = defined( 'DEVELOPER_MODE' ) && DEVELOPER_MODE === true;

	// Show only on themes.php and if the user hasn't dismissed the notice (unless in developer mode)
	if ( 'themes.php' !== $pagenow || ( ! $developer_mode && get_user_meta( get_current_user_id(), 'docy_congrats_notice_dismissed', true ) ) ) {
		return;
	}

	// Get theme data.
	$theme         = wp_get_theme( 'docy' );
	$theme_version = $theme->get( 'Version' );
	$wp_version    = get_bloginfo( 'version' );
	$php_version   = phpversion();

	// Check setup status.
	// Step 1: Check if theme is licensed via ThemeForest (Envato) or spider-themes.net (Freemius).
	$is_verified = function_exists( 'docy_is_theme_licensed' )
		? docy_is_theme_licensed()
		: ( 'valid' === trim( get_option( 'docy_purchase_code_status', '' ) ) );

	// Step 2: Check if required plugins are installed and active.
	$plugins_active = docy_check_required_plugins_status();

	// Step 3: Check if demo content has been imported.
	$demo_imported = docy_check_demo_imported();

	// Step 4: Check if theme settings have been configured.
	$theme_configured = docy_check_theme_configured();

	// Calculate progress.
	$completed_steps = 0;
	if ( $is_verified ) {
		$completed_steps++;
	}
	if ( $plugins_active ) {
		$completed_steps++;
	}
	if ( $demo_imported ) {
		$completed_steps++;
	}
	if ( $theme_configured ) {
		$completed_steps++;
	}
	$total_steps = 4;
	$progress    = ( $completed_steps / $total_steps ) * 100;

	?>
	<div class="docy-welcome-notice updated notice ti-about-notice" id="docy-welcome-notice">
		<button type="button" class="docy-notice-dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'docy' ); ?>">
			<span class="dashicons dashicons-no-alt"></span>
		</button>

		<div class="docy-welcome-wrapper">
			<!-- Header Section -->
			<div class="docy-welcome-header">
				<div class="docy-welcome-header-content">
					<div class="docy-welcome-badge">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Theme Activated', 'docy' ); ?></span>
					</div>
					<h2 class="docy-welcome-title">
						<?php esc_html_e( 'Welcome to Docy!', 'docy' ); ?>
						<span class="docy-version"><?php echo esc_html( 'v' . $theme_version ); ?></span>
					</h2>
					<p class="docy-welcome-description">
						<?php esc_html_e( 'Thank you for choosing Docy – the perfect documentation theme for WordPress. Follow the setup steps below to get your site ready.', 'docy' ); ?>
					</p>
				</div>
				<div class="docy-welcome-stats">
					<div class="docy-stat-item">
						<span class="docy-stat-icon dashicons dashicons-wordpress"></span>
						<span class="docy-stat-label"><?php esc_html_e( 'WordPress', 'docy' ); ?></span>
						<span class="docy-stat-value"><?php echo esc_html( $wp_version ); ?></span>
					</div>
					<div class="docy-stat-item">
						<span class="docy-stat-icon dashicons dashicons-editor-code"></span>
						<span class="docy-stat-label"><?php esc_html_e( 'PHP', 'docy' ); ?></span>
						<span class="docy-stat-value"><?php echo esc_html( $php_version ); ?></span>
					</div>
					<div class="docy-stat-item">
						<span class="docy-stat-icon dashicons dashicons-admin-appearance"></span>
						<span class="docy-stat-label"><?php esc_html_e( 'Theme', 'docy' ); ?></span>
						<span class="docy-stat-value"><?php echo esc_html( $theme_version ); ?></span>
					</div>
				</div>
			</div>

			<!-- Progress Bar -->
			<div class="docy-progress-section">
				<div class="docy-progress-header">
					<span class="docy-progress-label"><?php esc_html_e( 'Setup Progress', 'docy' ); ?></span>
					<span class="docy-progress-percent"><?php echo esc_html( $completed_steps . '/' . $total_steps ); ?> <?php esc_html_e( 'completed', 'docy' ); ?></span>
				</div>
				<div class="docy-progress-bar">
					<div class="docy-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
				</div>
			</div>

			<!-- Main Content Grid -->
			<div class="docy-welcome-grid">
				<!-- Setup Steps Column -->
				<div class="docy-welcome-column docy-setup-steps">
					<div class="docy-column-header">
						<span class="docy-column-icon dashicons dashicons-flag"></span>
						<h3><?php esc_html_e( 'Quick Setup Guide', 'docy' ); ?></h3>
					</div>

					<div class="docy-steps-list">
						<!-- Step 1: Register/Verify -->
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=docy_verify' ) ); ?>" class="docy-step-item <?php echo $is_verified ? 'completed' : ''; ?>">
							<div class="docy-step-number">
								<?php if ( $is_verified ) : ?>
									<span class="dashicons dashicons-yes"></span>
								<?php else : ?>
									<span>01</span>
								<?php endif; ?>
							</div>
							<div class="docy-step-content">
								<h4><?php esc_html_e( 'Register & Verify', 'docy' ); ?></h4>
								<p><?php esc_html_e( 'Activate your license to unlock all features', 'docy' ); ?></p>
							</div>
							<span class="docy-step-arrow dashicons dashicons-arrow-right-alt2"></span>
						</a>

						<!-- Step 2: Install Plugins -->
						<a href="<?php echo esc_url( admin_url( 'themes.php?page=tgmpa-install-plugins&plugin_status=activate' ) ); ?>" class="docy-step-item <?php echo $plugins_active ? 'completed' : ''; ?>">
							<div class="docy-step-number">
								<?php if ( $plugins_active ) : ?>
									<span class="dashicons dashicons-yes"></span>
								<?php else : ?>
									<span>02</span>
								<?php endif; ?>
							</div>
							<div class="docy-step-content">
								<h4><?php esc_html_e( 'Install Required Plugins', 'docy' ); ?></h4>
								<p><?php esc_html_e( 'Install and activate recommended plugins', 'docy' ); ?></p>
							</div>
							<span class="docy-step-arrow dashicons dashicons-arrow-right-alt2"></span>
						</a>

						<!-- Step 3: Import Demo -->
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=one-click-demo-import' ) ); ?>" class="docy-step-item <?php echo $demo_imported ? 'completed' : ''; ?>">
							<div class="docy-step-number">
								<?php if ( $demo_imported ) : ?>
									<span class="dashicons dashicons-yes"></span>
								<?php else : ?>
									<span>03</span>
								<?php endif; ?>
							</div>
							<div class="docy-step-content">
								<h4><?php esc_html_e( 'Import Demo Content', 'docy' ); ?></h4>
								<p><?php esc_html_e( 'Get started with ready-made templates', 'docy' ); ?></p>
							</div>
							<span class="docy-step-arrow dashicons dashicons-arrow-right-alt2"></span>
						</a>

						<!-- Step 4: Configure Theme -->
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=docy-options' ) ); ?>" class="docy-step-item <?php echo $theme_configured ? 'completed' : ''; ?>">
							<div class="docy-step-number">
								<?php if ( $theme_configured ) : ?>
									<span class="dashicons dashicons-yes"></span>
								<?php else : ?>
									<span>04</span>
								<?php endif; ?>
							</div>
							<div class="docy-step-content">
								<h4><?php esc_html_e( 'Configure Theme Settings', 'docy' ); ?></h4>
								<p><?php esc_html_e( 'Customize theme options to match your brand', 'docy' ); ?></p>
							</div>
							<span class="docy-step-arrow dashicons dashicons-arrow-right-alt2"></span>
						</a>
					</div>
				</div>

				<!-- Quick Actions Column -->
				<div class="docy-welcome-column docy-quick-actions">
					<div class="docy-column-header">
						<span class="docy-column-icon dashicons dashicons-admin-generic"></span>
						<h3><?php esc_html_e( 'Quick Actions', 'docy' ); ?></h3>
					</div>

					<div class="docy-actions-grid">
						<a href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>" class="docy-action-card">
							<span class="docy-action-icon dashicons dashicons-admin-customizer"></span>
							<span class="docy-action-label"><?php esc_html_e( 'Customizer', 'docy' ); ?></span>
						</a>
						<a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>" class="docy-action-card">
							<span class="docy-action-icon dashicons dashicons-menu"></span>
							<span class="docy-action-label"><?php esc_html_e( 'Menus', 'docy' ); ?></span>
						</a>
						<a href="<?php echo esc_url( admin_url( 'widgets.php' ) ); ?>" class="docy-action-card">
							<span class="docy-action-icon dashicons dashicons-screenoptions"></span>
							<span class="docy-action-label"><?php esc_html_e( 'Widgets', 'docy' ); ?></span>
						</a>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer" class="docy-action-card">
							<span class="docy-action-icon dashicons dashicons-visibility"></span>
							<span class="docy-action-label"><?php esc_html_e( 'View Site', 'docy' ); ?></span>
						</a>
					</div>
				</div>

				<!-- Resources Column -->
				<div class="docy-welcome-column docy-resources">
					<div class="docy-column-header">
						<span class="docy-column-icon dashicons dashicons-book"></span>
						<h3><?php esc_html_e( 'Resources & Support', 'docy' ); ?></h3>
					</div>

					<div class="docy-resources-list">
						<a href="https://helpdesk.spider-themes.net/docs/docy-wordpress-theme/" target="_blank" rel="noopener noreferrer" class="docy-resource-item">
							<span class="docy-resource-icon dashicons dashicons-media-document"></span>
							<div class="docy-resource-content">
								<h4><?php esc_html_e( 'Documentation', 'docy' ); ?></h4>
								<p><?php esc_html_e( 'Read full theme documentation', 'docy' ); ?></p>
							</div>
							<span class="docy-external-icon dashicons dashicons-external"></span>
						</a>

						<a href="https://www.youtube.com/@spiderthemes" target="_blank" rel="noopener noreferrer" class="docy-resource-item">
							<span class="docy-resource-icon dashicons dashicons-format-video"></span>
							<div class="docy-resource-content">
								<h4><?php esc_html_e( 'Video Tutorials', 'docy' ); ?></h4>
								<p><?php esc_html_e( 'Watch step-by-step guides', 'docy' ); ?></p>
							</div>
							<span class="docy-external-icon dashicons dashicons-external"></span>
						</a>

						<a href="https://helpdesk.spider-themes.net/forums/forum/docy-wordpress/" target="_blank" rel="noopener noreferrer" class="docy-resource-item">
							<span class="docy-resource-icon dashicons dashicons-sos"></span>
							<div class="docy-resource-content">
								<h4><?php esc_html_e( 'Get Support', 'docy' ); ?></h4>
								<p><?php esc_html_e( 'Submit a support ticket', 'docy' ); ?></p>
							</div>
							<span class="docy-external-icon dashicons dashicons-external"></span>
						</a>

						<a href="https://spider-themes.net/docy-changelog/" target="_blank" rel="noopener noreferrer" class="docy-resource-item">
							<span class="docy-resource-icon dashicons dashicons-backup"></span>
							<div class="docy-resource-content">
								<h4><?php esc_html_e( 'Changelog', 'docy' ); ?></h4>
								<p><?php esc_html_e( 'See what\'s new in this version', 'docy' ); ?></p>
							</div>
							<span class="docy-external-icon dashicons dashicons-external"></span>
						</a>
					</div>
				</div>
			</div>

			<!-- Footer Section -->
			<div class="docy-welcome-footer">
				<div class="docy-footer-left">
					<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" class="docy-btn docy-btn-secondary">
						<span class="dashicons dashicons-arrow-left-alt"></span>
						<?php esc_html_e( 'Return to Dashboard', 'docy' ); ?>
					</a>
				</div>
				<div class="docy-footer-right">
					<label class="docy-dismiss-checkbox">
						<input type="checkbox" id="docy-dismiss-permanently" />
						<span><?php esc_html_e( 'Don\'t show this notice again', 'docy' ); ?></span>
					</label>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=docy_verify' ) ); ?>" class="docy-btn docy-btn-primary">
						<?php esc_html_e( 'Start Setup', 'docy' ); ?>
						<span class="dashicons dashicons-arrow-right-alt"></span>
					</a>
				</div>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		(function ($) {
			'use strict';

			/**
			 * Dismiss the welcome notice via AJAX.
			 */
			function dismissDocyNotice() {
				var data = {
					action: 'dismiss_docy_notice',
					security: '<?php echo esc_js( wp_create_nonce( 'dismiss_docy_notice' ) ); ?>'
				};

				$.post(ajaxurl, data, function (response) {
					if (response === 'success') {
						$('#docy-welcome-notice').addClass('docy-notice-hiding');
						setTimeout(function() {
							$('#docy-welcome-notice').slideUp(300, function() {
								$(this).remove();
							});
						}, 200);
					}
				});
			}

			$(document).ready(function() {
				// Dismiss button click.
				$('.docy-notice-dismiss').on('click', function (e) {
					e.preventDefault();
					dismissDocyNotice();
				});

				// Dismiss checkbox change.
				$('#docy-dismiss-permanently').on('change', function() {
					if ($(this).is(':checked')) {
						dismissDocyNotice();
					}
				});

				// Add entrance animation.
				setTimeout(function() {
					$('#docy-welcome-notice').addClass('docy-notice-visible');
				}, 100);

				// Animate progress bar.
				setTimeout(function() {
					$('.docy-progress-fill').addClass('animated');
				}, 500);
			});
		})(jQuery);
	</script>
	<?php
}
add_action( 'admin_notices', 'docy_show_congratulations_notice' );

/**
 * Check if all required plugins are installed and active.
 *
 * Uses TGMPA's built-in method to check if all required plugins are complete.
 *
 * @return bool True if all required plugins are installed and active.
 */
function docy_check_required_plugins_status() {
	// Use TGMPA's built-in check if available.
	if ( class_exists( 'TGM_Plugin_Activation' ) ) {
		$tgmpa = TGM_Plugin_Activation::get_instance();
		if ( method_exists( $tgmpa, 'is_tgmpa_complete' ) ) {
			return $tgmpa->is_tgmpa_complete();
		}
	}

	// Fallback: Check for core required plugins manually.
	$required_plugins = array(
		'eazydocs/eazydocs.php',
	);

	foreach ( $required_plugins as $plugin ) {
		if ( ! is_plugin_active( $plugin ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Check if demo content has been imported.
 *
 * Checks for indicators that demo content was imported such as:
 * - Presence of Elementor library templates
 * - Custom page_on_front setting
 * - Imported menus
 *
 * @return bool True if demo content appears to have been imported.
 */
function docy_check_demo_imported() {
	// Check if a front page has been set (common after demo import).
	$front_page_id = get_option( 'page_on_front', 0 );
	if ( empty( $front_page_id ) ) {
		return false;
	}

	// Check if it's showing a page on front.
	$show_on_front = get_option( 'show_on_front', 'posts' );
	if ( 'page' !== $show_on_front ) {
		return false;
	}

	// Check for Elementor library templates (created during demo import).
	$elementor_templates = get_posts(
		array(
			'post_type'      => 'elementor_library',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		)
	);

	if ( ! empty( $elementor_templates ) ) {
		return true;
	}

	// Check for navigation menus (created during demo import).
	$menus = wp_get_nav_menus();
	if ( ! empty( $menus ) && count( $menus ) >= 2 ) {
		return true;
	}

	// Check if there are multiple published pages (indicating demo import).
	$page_count = wp_count_posts( 'page' );
	if ( isset( $page_count->publish ) && $page_count->publish >= 5 ) {
		return true;
	}

	return false;
}

/**
 * Check if theme settings have been configured.
 *
 * Checks if the Redux framework options have been saved with custom values.
 *
 * @return bool True if theme settings appear to have been configured.
 */
function docy_check_theme_configured() {
	// Check for Redux options.
	$docy_options = get_option( 'docy_opt', array() );

	// If options exist and have some custom values, consider it configured.
	if ( ! empty( $docy_options ) && is_array( $docy_options ) ) {
		// Check for common customized options.
		$customized_keys = array(
			'logo',
			'logo_light',
			'footer_logo',
			'header_style',
			'footer_style',
			'primary_color',
		);

		foreach ( $customized_keys as $key ) {
			if ( isset( $docy_options[ $key ] ) && ! empty( $docy_options[ $key ] ) ) {
				return true;
			}
		}

		// If options array has significant customization.
		if ( count( $docy_options ) >= 10 ) {
			return true;
		}
	}

	// Check if customizer has been used.
	$custom_logo = get_theme_mod( 'custom_logo' );
	if ( ! empty( $custom_logo ) ) {
		return true;
	}

	return false;
}

/**
 * AJAX handler to dismiss the notice and store in user meta.
 *
 * @return void
 */
function docy_dismiss_notice() {
	check_ajax_referer( 'dismiss_docy_notice', 'security' );
	update_user_meta( get_current_user_id(), 'docy_congrats_notice_dismissed', 1 );
	echo 'success';
	wp_die();
}
add_action( 'wp_ajax_dismiss_docy_notice', 'docy_dismiss_notice' );