<?php if ( ! defined( 'ABSPATH' )  ) { die; } // Cannot access directly.

// Set a unique slug-like ID
$prefix = 'docy_opt';

/**
 * Get theme version
 * Create options
 * @return string
 */
CSF::createOptions( $prefix, array(
    'menu_title' => esc_html__('Theme Settings', 'docy'),
    'menu_slug' => 'docy-options',
    'framework_title' => esc_html__('Docy', 'docy') . '<span> v' . DOCY_VERSION . '</span>',
    'menu_type' => 'submenu',
    'show_in_customizer' => docy_opt( 'customizer_visibility' ),
    'menu_hidden' => false,
    'menu_parent' => 'docy_template',
    'menu_capability' => 'manage_options',
));

require get_template_directory() . '/options/opt_general.php';
// Design tab: parent created in opt_color_scheme, sub-tabs ordered by require order below.
require get_template_directory() . '/options/opt_color_scheme.php'; // Design > Colors (+ Design parent)
require get_template_directory() . '/options/opt_typo.php';         // Design > Body & Headers Typography
require get_template_directory() . '/options/opt_dark.php';         // Design > Dark Mode (mode variant last)
require get_template_directory() . '/options/opt_customizer.php';
require get_template_directory() . '/options/opt_header.php';
require get_template_directory() . '/options/opt_footer.php';
require get_template_directory() . '/options/opt_menu.php';
require get_template_directory() . '/options/opt_blog.php';
require get_template_directory() . '/options/opt_shop.php';
require get_template_directory() . '/options/opt_forum.php';
require get_template_directory() . '/options/opt_custom_code.php';
require get_template_directory() . '/options/opt_social_links.php';
require get_template_directory() . '/options/opt_404.php';
require get_template_directory() . '/options/opt_backup.php';