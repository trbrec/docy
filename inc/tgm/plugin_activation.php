<?php
/**
 * This file represents an example of the code that themes would use to register
 * the required plugins.
 *
 * It is expected that theme authors would copy and paste this code into their
 * functions.php file, and amend to suit.
 *
 * @see http://tgmpluginactivation.com/configuration/ for detailed documentation.
 *
 * @package    TGM-Plugin-Activation
 * @subpackage Example
 * @version    2.6.1
 * @author     Thomas Griffin, Gary Jones, Juliette Reinders Folmer
 * @copyright  Copyright (c) 2011, Thomas Griffin
 * @license    http://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 * @link       https://github.com/TGMPA/TGM-Plugin-Activation
 */

/**
 * Include the TGM_Plugin_Activation class.
 */
require get_template_directory() . '/inc/tgm/class-tgm-plugin-activation.php';

add_action( 'tgmpa_register', 'docy_register_required_plugins' );

/**
 * Register the required plugins for this theme.
 *
 * This function is hooked into tgmpa_init, which is fired within the
 * TGM_Plugin_Activation class constructor.
 */
function docy_register_required_plugins() {
	/*
	 * Array of plugin arrays. Required keys are name and slug.
	 * If the source is NOT from the .org repo, then source is also required.
	 */
	$plugins = array(
        array(
            'name'          => esc_html__( 'Advanced Custom Fields-pro', 'docy' ), // The plugin name.
            'slug'          => 'advanced-custom-fields-pro', // The plugin slug (typically the folder name).
            'source'        => 'https://wordpress-theme.spider-themes.net/3rd-plugins/advanced-custom-fields-pro.zip', // The plugin source.
            'required'      => true, // If false, the plugin is only 'recommended' instead of required.
        ),

		array(
			'name'          => esc_html__( 'EazyDocs', 'docy' ),
			'slug'          => 'eazydocs',
			'required'      => true,
		),

		array(
			'name'          => esc_html__( 'Jobus', 'docy' ),
			'slug'          => 'jobus',
			'required'      => true,
		),
        array(
            'name'          => esc_html__( 'Forumax', 'docy' ),
            'slug'          => 'bbp-core',
            'required'      => true,
        ),

		array(
			'name'          => esc_html__( 'Spider Elements', 'docy' ),
			'slug'          => 'spider-elements',
			'required'      => false,
		),

		array(
			'name'          => esc_html__( 'wooCommerce', 'docy' ),
			'slug'          => 'woocommerce',
			'required'      => false,
		),

        array(
            'name'          => esc_html__( 'One Click Demo Import', 'docy' ),
            'slug'          => 'one-click-demo-import',
            'required'      => false,
        ),

		array(
			'name'          => esc_html__( 'Changeloger', 'docy' ),
			'slug'          => 'changeloger',
			'required'      => false,
		),

		array(
			'name'          => esc_html__( 'Antimanual – The Ultimate AI Solution for Your Site', 'docy' ),
			'slug'          => 'antimanual',
			'required'      => false,
		),
	);

	/*
	 * Array of configuration settings. Amend each line as needed.
	 *
	 * TGMPA will start providing localized text strings soon. If you already have translations of our standard
	 * strings available, please help us make TGMPA even better by giving us access to these translations or by
	 * sending in a pull-request with .po file(s) with the translations.
	 *
	 * Only uncomment the strings in the config array if you want to customize the strings.
	 */
	$config = array(
		'id'           => 'tgmpa',                 // Unique ID for hashing notices for multiple instances of TGMPA.
		'default_path' => '',                      // Default absolute path to bundled plugins.
		'menu'         => 'tgmpa-install-plugins', // Menu slug.
		'parent_slug'  => 'themes.php',            // Parent menu slug.
		'capability'   => 'edit_theme_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
		'has_notices'  => true,                    // Show admin notices or not.
		'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
		'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
		'is_automatic' => false,                   // Automatically activate plugins after installation or not.
		'message'      => '',                      // Message to output right before the plugins table.
	);

	tgmpa( $plugins, $config );
}

/**
 * Check if the required plugin is active or not. If not, it will install and activate the plugin automatically.
 * @param $plugins
 * @return array
 */
function docy_ocdi_register_plugins( $plugins ) {
    $step   = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
    $import = isset( $_GET['import'] ) ? sanitize_text_field( wp_unslash( $_GET['import'] ) ) : '';

    $theme_plugins = [
        [
            'name'          => esc_html__( 'Advanced Custom Fields-pro', 'docy' ), // The plugin name.
            'description'   => esc_html__( 'Advanced Custom Fields Pro is a premium plugin that allows you to add custom fields to your WordPress edit screens.', 'docy' ),
            'slug'          => 'advanced-custom-fields-pro', // The plugin slug (typically the folder name).
            'source'        => 'https://wordpress-theme.spider-themes.net/3rd-plugins/advanced-custom-fields-pro.zip', // The plugin source.
            'required'      => true, // If false, the plugin is only 'recommended' instead of required.
        ],
        [
            'name'          => esc_html__( 'Pro Elements', 'docy' ), // The plugin name.
            'description'   => esc_html__( 'Free WordPress plugin that enables PRO features in the Elementor page builder. If you have Elementor Pro, no need to install this plugin.', 'docy' ),
            'slug'          => 'pro-elements', // The plugin slug (typically the folder name).
            'source'        => 'https://github.com/proelements/proelements/releases/download/v3.27.0/pro-elements.zip', // The plugin source.
            'required'      => true, // If false, the plugin is only 'recommended' instead of required.
        ],
        [
            'name'          => esc_html__( 'EazyDocs', 'docy' ),
            'description'   => esc_html__( 'EazyDocs is a free WordPress plugin that allows you to create a knowledge base, documentation, or wiki for your website.', 'docy' ),
            'slug'          => 'eazydocs',
            'required'      => true,
        ],
        [
            'name'          => esc_html__( 'Jobus', 'docy' ),
            'description'   => esc_html__( 'A powerful recruitment and job listing plugin that seamlessly connects jobseekers with employers, enabling businesses to find the best talent quickly and efficiently.', 'docy' ),
            'slug'          => 'jobus',
            'required'      => true,
        ],
        [
            'name'          => esc_html__('wooCommerce', 'docy'),
            'description'   => esc_html__( 'WooCommerce is a free eCommerce plugin that allows you to sell anything. If you need to sell product, you have to install it. Otherwise, you can skip this plugin.', 'docy' ),
            'slug'          => 'woocommerce',
            'required'      => false,
        ],        
        [
            'name'          => esc_html__( 'Forumax ', 'docy'),
            'description'   => esc_html__( 'Forumax is a required plugin for forums management. If you don\'t want the Forum feature, you can skip installing this plugin.', 'docy' ),
            'slug'          => 'bbp-core',
            'required'      => false,
        ],
	    [
		    'name'          => esc_html__( 'Changeloger', 'docy' ),
		    'description'   => esc_html__( 'Install this plugin if you want to publish changelog of products', 'docy'),
		    'slug'          => 'changeloger',
		    'required'      => false,
	    ],
	    [
		    'name'          => esc_html__( 'Antimanual – The Ultimate AI Solution for Your Site', 'docy' ),
		    'slug'          => 'antimanual',
		    'required'      => false,
	    ],
    ];

    // The LMS demo required plugins
    if ( 'import' === $step && '' !== $import ) {
        // List of all plugins only used by second demo import [overwrite the list] ('import' number = 1).
        if ( '9' === $import ) {
            $theme_plugins[] = [
                'name'     => 'Tutor LMS',
                'slug'     => 'tutor',
                'required' => true,
            ];
            $theme_plugins[] = [
                'name'     => 'Tutor LMS Elementor Addons',
                'slug'     => 'tutor-lms-elementor-addons',
                'required' => true,
            ];
        }
    }

  return array_merge( $plugins, $theme_plugins );
}
add_filter( 'ocdi/register_plugins', 'docy_ocdi_register_plugins' );
