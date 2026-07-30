<?php
/**
 * Conflict prevention: PRO Elements vs Elementor Pro
 *
 * If Elementor Pro (official) is installed or active, PRO Elements must be
 * hidden from all admin UIs and deactivated automatically. This file is the
 * only place that implements this logic — nothing else in the theme or plugin
 * is touched.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Helpers
// -------------------------------------------------------------------------

/**
 * True when Elementor Pro is installed on disk (regardless of active state).
 * Using file_exists rather than is_plugin_active so we can hide PRO Elements
 * even before Elementor Pro has been network-activated or fully loaded.
 */
function docy_elementor_pro_installed() {
	return file_exists( WP_PLUGIN_DIR . '/elementor-pro/elementor-pro.php' );
}

/**
 * True when Elementor Pro is currently active.
 */
function docy_elementor_pro_active() {
	return is_plugin_active( 'elementor-pro/elementor-pro.php' );
}

// -------------------------------------------------------------------------
// Auto-deactivate on plugin activation
// Fires when the admin activates Elementor Pro through any standard WP path.
// -------------------------------------------------------------------------

add_action( 'activated_plugin', 'docy_deactivate_pro_elements_on_elementor_pro', 10, 1 );

function docy_deactivate_pro_elements_on_elementor_pro( $plugin ) {
	if ( $plugin !== 'elementor-pro/elementor-pro.php' ) {
		return;
	}

	if ( ! is_plugin_active( 'pro-elements/pro-elements.php' ) ) {
		return;
	}

	deactivate_plugins( 'pro-elements/pro-elements.php' );

	// Store a transient so the notice can survive the redirect.
	set_transient( 'docy_pro_elements_auto_deactivated', true, 60 );
}

// -------------------------------------------------------------------------
// Safety-net enforcement on every admin load.
// Catches edge cases where both plugins end up active simultaneously via
// WP-CLI, Multisite bulk-activation, or direct database manipulation.
// -------------------------------------------------------------------------

add_action( 'admin_init', 'docy_enforce_pro_elements_conflict_guard' );

function docy_enforce_pro_elements_conflict_guard() {
	if ( ! docy_elementor_pro_active() ) {
		return;
	}

	if ( ! is_plugin_active( 'pro-elements/pro-elements.php' ) ) {
		return;
	}

	// Both are active — deactivate PRO Elements silently.
	deactivate_plugins( 'pro-elements/pro-elements.php' );
	set_transient( 'docy_pro_elements_auto_deactivated', true, 60 );
}

// -------------------------------------------------------------------------
// Admin notice after auto-deactivation
// -------------------------------------------------------------------------

add_action( 'admin_notices', 'docy_pro_elements_deactivated_notice' );

function docy_pro_elements_deactivated_notice() {
	if ( ! get_transient( 'docy_pro_elements_auto_deactivated' ) ) {
		return;
	}

	// Only show to users who can manage plugins.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	delete_transient( 'docy_pro_elements_auto_deactivated' );

	echo '<div class="notice notice-success is-dismissible"><p>';
	esc_html_e(
		'PRO Elements has been automatically deactivated because Elementor Pro is now active. Your site design and content are fully intact.',
		'docy'
	);
	echo '</p></div>';
}

// -------------------------------------------------------------------------
// Hide PRO Elements from the OCDI plugin installer list.
// Runs at priority 20, after docy_ocdi_register_plugins (default priority 10),
// so it cleanly strips the entry without touching the original function.
// -------------------------------------------------------------------------

add_filter( 'ocdi/register_plugins', 'docy_hide_pro_elements_from_ocdi', 20 );

function docy_hide_pro_elements_from_ocdi( $plugins ) {
	if ( ! docy_elementor_pro_installed() ) {
		return $plugins;
	}

	return array_values(
		array_filter( $plugins, function ( $plugin ) {
			return ! isset( $plugin['slug'] ) || $plugin['slug'] !== 'pro-elements';
		} )
	);
}
