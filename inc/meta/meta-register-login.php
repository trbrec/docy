<?php
// Control core classes to avoid errors
if ( class_exists( 'CSF' ) ) {

	$prefix = 'docy_singup';

	// Meta > Options:: Page
	CSF::createMetabox( $prefix, array(
		'title'          => esc_html__( 'Docy:: Sign up / Sign in Page Options', 'docy' ),
		'post_type'      => [ 'page' ],
		'page_templates' => [ 'page-signup.php', 'page-signin.php' ],
		'data_type'      => 'unserialize',
		'output_css'     => true,
		'show_restore'   => false,
	) );

	// Left Column
	CSF::createSection( $prefix, array(
		'title'  => esc_html__( 'Left Column', 'docy' ),
		'fields' => array(

			array(
				'id'      => 'left_title',
				'type'    => 'text',
				'title'   => esc_html__( 'Title', 'docy' ),
				'default' => esc_html__( 'We are design changers do what matters.', 'docy' ),
			),

			array(
				'id'    => 'top_ornament',
				'type'  => 'media',
				'title' => esc_html__( 'Top Ornament Image', 'docy' ),
			),

			array(
				'id'    => 'bottom_ornament',
				'type'  => 'media',
				'title' => esc_html__( 'Bottom Ornament Image', 'docy' ),
			),

			array(
				'id'    => 'featured_image',
				'type'  => 'media',
				'title' => esc_html__( 'Featured Image', 'docy' ),
			),
		)
	) );

	// Right Column
	CSF::createSection( $prefix, array(
		'title'  => esc_html__( 'Right Column', 'docy' ),
		'fields' => array(
			array(
				'id'      => 'right_title',
				'type'    => 'text',
				'title'   => esc_html__( 'Right Title', 'docy' ),
				'default' => esc_html__( 'Create your Account', 'docy' ),
			),

			array(
				'id'      => 'right_subtitle',
				'type'    => 'wp_editor',
				'title'   => esc_html__( 'Subtitle', 'docy' ),
				'default' => esc_html__( 'Already have an account? Sign in', 'docy' ),
			),

			array(
				'id'       => 'social_login',
				'type'     => 'text',
				'title'    => esc_html__( 'Shortcode', 'docy' ),
				'subtitle' => esc_html__( 'The Shortcode will be rendered between the Login form and Title area.', 'docy' ),
				'desc'     => esc_html__( 'You can place here any shortcode as like social login shortcode or ther content', 'docy' ),
			),

			array(
				'id'      => 'submit_button_label',
				'type'    => 'text',
				'title'   => esc_html__( 'Submit Button Label', 'docy' ),
				'default' => esc_html__( 'Create an account', 'docy' ),
			),
		)
	) );

	// Agree Checkbox
	CSF::createSection( $prefix, array(
		'title'  => esc_html__( 'Agree Checkbox', 'docy' ),
		'fields' => array(

			array(
				'id'         => 'agree_checkbox',
				'type'       => 'switcher',
				'title'      => esc_html__( 'Agree Checkbox', 'docy' ),
				'text_on'    => esc_html__( 'Yes', 'docy' ),
				'text_off'   => esc_html__( 'No', 'docy' ),
				'text_width' => 80,
				'subtitle'   => 'This is only for the Signup page.',
				'default'    => true
			),

			array(
				'id'         => 'agreement_label',
				'type'       => 'wp_editor',
				'title'      => esc_html__( 'Agreement Label', 'docy' ),
				'default'    => esc_html__( 'I accept the politic of confidentiality', 'docy' ),
				'dependency' => array( 'agree_checkbox', '==', '1' ),
			),

			array(
				'id'         => 'alert_message',
				'type'       => 'textarea',
				'title'      => esc_html__( 'Alert Message', 'docy' ),
				'subtitle'   => esc_html__( 'The alert message will throw if the checkbox is not checked before submitting the form.', 'docy' ),
				'default'    => esc_html__( 'Please indicate that you have read and agree to the Terms and Conditions and Privacy Policy', 'docy' ),
				'dependency' => array( 'agree_checkbox', '==', '1' ),
			),
		)
	) );
}