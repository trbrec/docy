<?php
/**
 * The header for our theme
 *
 * This is the template that displays all the <head> section
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package docy
 */

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
    <head>
        <!-- Theme Version -->
        <meta name="docy-version" content="<?php echo DOCY_VERSION ?>">
        <!-- Charset Meta -->
        <meta charset="<?php bloginfo('charset' ); ?>">
        <!-- For IE -->
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <!-- For Responsive Device -->
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <?php wp_head(); ?>
    </head>

    <body <?php body_class(); docy_has_scrollspy() ?> >
        <?php
        if ( function_exists('wp_body_open') ) {
            wp_body_open();
        }
        ?>
        <a class="skip-link screen-reader-text" href="#main-content">
            <?php esc_html_e( 'Skip to content', 'docy' ); ?>
        </a>

        <?php
        /**
         * Preloader
         */
        if ( docy_opt('is_preloader') == '1' ) {
            get_template_part('template-parts/header-elements/preloader');
        }
        ?>

        <div class="body_wrapper <?php docy_body_wrapper_classes() ?>">
            <div class="click_capture"></div>

            <?php
            if ( docy_opt('header_style') == 'elementor' && class_exists( '\\Elementor\\Plugin' ) ) {
                $template_id = absint( docy_opt('header_el_template') );
                if ( $template_id > 0 ) :
                ?>
                <header id="docy-header" class="docy-header">
                    <?php echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id ); ?>
                </header>
                <?php
                endif;
            } else {
                ?>
                <header class="header">
                    <?php
                    if ( docy_opt('is_top_header') == '1' ) {
                        get_template_part( 'template-parts/header-elements/top-header' );
                    }
                    ?>
                    <nav <?php docy_navbar_class() ?> id="<?php docy_sticky_navbar('id') ?>">
                        <div class="<?php docy_nav_container() ?>">
                            <?php Docy_helper()->logo(); ?>
                            <button class="navbar-toggler collapsed" type="button" data-bs-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                                    aria-expanded="false" aria-label="<?php esc_attr_e('Toggle navigation', 'docy'); ?>">
                                <span class="menu_toggle">
                                    <span class="hamburger">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </span>
                                    <span class="hamburger-cross">
                                        <span></span>
                                        <span></span>
                                    </span>
                                </span>
                            </button>
                            <?php get_template_part( 'template-parts/header-elements/layout', docy_opt('header_layout', 'default') ); ?>
                        </div>
                    </nav>
                </header>

                <?php
                /**
                 * Mobile menu
                 */
                get_template_part( 'template-parts/header-elements/mobile-menu' );
            }
            ?>
            
            <main id="main-content" tabindex="-1">
