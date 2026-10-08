<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.

/**
 * Design parent tab.
 *
 * Groups the visual/design-related settings (Colors, Dark Mode, Typography)
 * under a single top-level tab so all appearance controls live together.
 */
CSF::createSection( $prefix, array(
	'title' => esc_html__( 'Design', 'docy' ),
	'id'    => 'design_parent',
	'icon'  => 'dashicons dashicons-art',
) );

// Colors sub-tab.
CSF::createSection( $prefix, array(
	'parent'     => 'design_parent',
	'title'      => esc_html__( 'Colors', 'docy' ),
	'id'         => 'color',
	'subsection' => true,
	'icon'       => 'dashicons dashicons-admin-appearance',
	'fields'     => array(
		array(
			'title'   => esc_html__( 'Brand Colors', 'docy' ),
			'id'      => 'color_scheme_option',
			'type'    => 'heading',
		),
		array(
			'id'      => 'color_contrast_note',
			'type'    => 'notice',
			'style'   => 'info',
			'icon'    => 'dashicons dashicons-universal-access-alt',
			'content' => esc_html__( 'Tip: Keep a strong contrast between your colors and their backgrounds. Aim for a contrast ratio of at least 4.5:1 for body text to keep your documentation readable and accessible (WCAG AA).', 'docy' ),
		),
		array(
			'id'      => 'color_dark_mode_warning',
			'type'    => 'notice',
			'style'   => 'warning',
			'icon'    => 'dashicons dashicons-warning',
			'content' => esc_html__( 'Note: Dark mode color switching may not work if you change/update the colors. It is recommended to not use dark mode if you customize the colors.', 'docy' ),
		),
		array(
			'id'         => 'color_preview_link',
			'type'       => 'content',
			'content'    => sprintf(
				'<a href="%1$s" class="button button-secondary" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( admin_url( 'customize.php?url=' ) . site_url( '/' ) . '?autofocus[panel]=docy_opt&autofocus[section]=color' ),
				esc_html__( 'Preview in Customizer', 'docy' )
			),
			// Only deep-link when the Customizer panel is enabled; otherwise the autofocus target does not exist.
			'dependency' => array( 'customizer_visibility', '==', true ),
		),
		array(
			'id'          => 'accent_solid_color_opt',
			'type'        => 'color',
			'title'       => esc_html__( 'Accent Color', 'docy' ),
			'subtitle'    => esc_html__( 'Used for links, buttons, and primary highlights across the site.', 'docy' ),
			'default'     => '#0866ff',
			'output'      => ':root',
			'output_mode' => '--brand_color',
		),
		array(
			'id'          => 'secondary_color_opt',
			'type'        => 'color',
			'title'       => esc_html__( 'Secondary Color', 'docy' ),
			'subtitle'    => esc_html__( 'Normally used in Titles, Gradient Colors', 'docy' ),
			'default'     => '#242729',
			'output'      => ':root',
			'output_mode' => '--black_800',
		),
		array(
			'id'          => 'paragraph_color_opt',
			'type'        => 'color',
			'title'       => esc_html__( 'Paragraph Color', 'docy' ),
			'subtitle'    => esc_html__( 'Normally used in meta content, paragraph, doc lists, subtitles, icon', 'docy' ),
			'default'     => '#425466',
			'output'      => ':root',
			'output_mode' => '--p_color',
		),
		array(
			'title' => esc_html__( 'Backgrounds & Effects', 'docy' ),
			'id'    => 'color_backgrounds_heading',
			'type'  => 'heading',
		),
		array(
			'id'       => 'gradient_bg_color',
			'type'     => 'color_group',
			'title'    => esc_html__( 'Background Gradient Color', 'docy' ),
			'subtitle' => esc_html__( 'This color applied to Post Single, Archive (tags, category, author) pages, Forum pages, Search Results page', 'docy' ),
			'options'  => array(
				'gradient_bg_color-from' => esc_html__( 'From', 'docy' ),
				'gradient_bg_color-to'   => esc_html__( 'To', 'docy' ),
			),
			'default'  => array(
				'gradient_bg_color-from' => '#FFFBF2',
				'gradient_bg_color-to'   => '#EDFFFD',
			),
			'output'   => ':root',
		),

		array(
			'id'         => 'is_box_shadow',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Container Box Shadow', 'docy' ),
			'subtitle'   => esc_html__( 'Adds a soft shadow around content containers on Post Single, Forum, and Search Results pages.', 'docy' ),
			'text_on'    => esc_html__( 'Show', 'docy' ),
			'text_off'   => esc_html__( 'Hide', 'docy' ),
			'text_width' => 100,
			'default'    => true,
		),
	),
) );
