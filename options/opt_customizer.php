<?php

$customizer_url = admin_url( 'customize.php?url=' ) . site_url( '/' ) . '?autofocus[panel]=docy_opt&autofocus[section]=color';

CSF::createSection( $prefix, array(
	'id'     => 'customizer_settings',
	'title'  => esc_html__( 'Customizer', 'docy' ),
	'icon'   => 'dashicons dashicons-admin-customizer',
	'fields' => [
		
		array(
			'id'         => 'customizer_visibility',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Options Visibility on Customizer', 'docy' ),
			'text_on'    => esc_html__( 'Enabled', 'docy' ),
			'text_off'   => esc_html__( 'Disabled', 'docy' ),
			'text_width' => 100,
		),

		array(
			'type'       => 'content',
			'content'    => sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer" id="docy_customizer_opt">%2$s</a>', esc_url( $customizer_url ), esc_html__( 'Customizer', 'docy' ) ),
			'dependency' => array(
				array( 'customizer_visibility', '==', true ),
			),
		)

	]
) );