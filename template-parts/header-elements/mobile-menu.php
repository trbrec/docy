<?php
defined( 'ABSPATH' ) || exit;

$opt = get_option( 'docy_opt' );
?>
<div class="mobile_main_menu <?php Docy_helper()->navbar_type(); ?>" id="<?php docy_sticky_navbar('id', 'mobile') ?>">
    <div class="container">
        <div class="mobile_menu_left">
            <?php Docy_helper()->logo(); ?>
        </div>
        <div class="mobile_menu_right">
           <?php get_template_part( 'template-parts/header-elements/action-button' ); ?>
            <button type="button" class="navbar-toggler mobile_menu_btn" aria-label="<?php esc_attr_e( 'Toggle navigation', 'docy' ); ?>" aria-controls="side-menu" aria-expanded="false">
                <span class="menu_toggle ">
                    <span class="hamburger">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </span>
            </button>
        </div>
    </div>
</div>

<div class="side_menu dark_menu" id="side-menu" role="dialog" aria-modal="true" aria-hidden="true" aria-label="<?php esc_attr_e( 'Mobile menu', 'docy' ); ?>">
    <div class="mobile_menu_header">
        <button type="button" class="close_nav" aria-label="<?php esc_attr_e( 'Close navigation', 'docy' ); ?>" aria-controls="side-menu">
            <i class="icon_close" aria-hidden="true"></i>
        </button>
        <?php 
        if ( !empty( $opt['main_logo']['url'] ) ) :
            ?>
            <div class="mobile_logo">
	            <?php
	            if ( docy_opt('is_dark_switcher') != '1' ) {
		            Docy_helper()->logo();
	            }
                get_template_part( 'template-parts/header-elements/dark-switcher' );
                ?>
            </div>
            <?php 
        endif;
        ?>
    </div>

    <div class="mobile_nav_wrapper">
        <nav class="mobile_nav_bottom" aria-labelledby="mobile-menu-title">
            <h2 id="mobile-menu-title" class="screen-reader-text"><?php esc_html_e( 'Mobile Navigation', 'docy' ); ?></h2>
            <?php
            docy_render_main_menu( '', 'Docy_Mobile_Nav_Walker' );
            ?>
        </nav>
    </div>
</div>