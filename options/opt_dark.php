<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.

// Dark Mode sub-tab (grouped under the Design parent).
CSF::createSection( $prefix, array(
	'parent'     => 'design_parent',
	'title'      => esc_html__( 'Dark Mode', 'docy' ),
	'id'         => 'dark_mode_opt',
	'subsection' => true,
	'icon'       => 'dashicons dashicons-star-half',
	'fields'     => array(
		array(
			'title' => esc_html__( 'Dark Mode', 'docy' ),
			'id'    => 'dark_mode_option',
			'type'  => 'heading',
		),
		array(
			'id'      => 'dark_mode_color_warning',
			'type'    => 'notice',
			'style'   => 'warning',
			'icon'    => 'dashicons dashicons-warning',
			'content' => esc_html__( 'Note: Dark mode color switching may not work if you change/update the colors. It is recommended to not use dark mode if you customize the colors.', 'docy' ),
		),
		array(
			'title'      => esc_html__( 'Dark Mode Switcher', 'docy' ),
			'subtitle'   => esc_html__( 'Show/Hide the Dark Mode Switcher on the Header navigation bar.', 'docy' ),
			'id'         => 'is_dark_switcher',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 100,
			'default'    => true,
		),
		array(
			'title'      => esc_html__( 'Active Dark Mode', 'docy' ),
			'subtitle'   => esc_html__( 'Activate the Dark Mode by default.', 'docy' ),
			'id'         => 'is_dark_default',
			'type'       => 'switcher',
			'text_on'    => esc_html__( 'Enable', 'docy' ),
			'text_off'   => esc_html__( 'Disable', 'docy' ),
			'text_width' => 100,
			'default'    => '',
		),
		array(
			'id'         => 'dark_mode_reachability_note',
			'type'       => 'notice',
			'style'      => 'warning',
			'icon'       => 'dashicons dashicons-info',
			'content'    => esc_html__( 'The Dark Mode Switcher is hidden, so visitors cannot toggle it themselves. Enable "Active Dark Mode" above to serve a dark theme by default — otherwise the site stays in light mode for everyone.', 'docy' ),
			// Only surface this when the user has hidden the switcher (the dead-end state).
			'dependency' => array( 'is_dark_switcher', '!=', '1' ),
		),
		array(
			'id'          => 'brand_color_dark',
			'type'        => 'color',
			'title'       => esc_html__( 'Accent Color', 'docy' ),
			'subtitle'    => esc_html__( 'Accent Color for dark mode', 'docy' ),
			'output'      => ':root',
			'output_mode' => '--brand_color_dark',
			// Always available within the Dark Mode tab so the dark accent can be set
			// whether dark mode is reached via the switcher or forced on by default.
		),
	),
) );
