<?php
defined( 'ABSPATH' ) || exit;

$meta_btn_link    = docy_meta('action_btn_link');
$is_menu_btn      = docy_meta_apply('is_menu_btn');

// Button Title
$btn_title 			= '';
if ( ! empty( $meta_btn_link['text'] ) ) {
	$btn_title 		= $meta_btn_link['text'];
} else {
	$btn_title 		= docy_opt('menu_btn_label');
}

if ( is_user_logged_in() ) {
    $btn_title = docy_opt('menu_btn_label_logged') ? docy_opt('menu_btn_label_logged') : $btn_title;
}

// Button URL
$btn_url 			= '';
if ( ! empty( $meta_btn_link['url'] ) ) {
	$btn_url 		= $meta_btn_link['url'];
} else {
	$btn_url 		= docy_opt('menu_btn_url');
}

// Button Target
$btn_target 		= '';
if ( ! empty( $meta_btn_link['target'] ) ) {
	$btn_target 	= $meta_btn_link['target'];
} else {
	$btn_target 	= docy_opt('menu_btn_target', '_self');
}

$btn_target = docy_get_link_target( $btn_target );
$link_rel   = docy_get_link_rel( $btn_target );

$two_button 		= $is_menu_btn == '1' && docy_opt('is_dark_switcher') == '1' ? ' two-button' : '';

if ( ( $is_menu_btn == '1' ) || ( docy_opt('is_dark_switcher') == '1' ) || ( docy_opt('is_search_form') == '1' ) ) :
	?>
    <div class="right-nav<?php echo esc_attr($two_button); ?>">
		<?php
        if ( $is_menu_btn == '1' && ! empty( $btn_title ) ) {
			?>
			<a class="nav_btn tp_btn" href="<?php echo esc_url( $btn_url ) ?>" target="<?php echo esc_attr( $btn_target ) ?>"<?php echo $link_rel ? ' rel="' . esc_attr( $link_rel ) . '"' : ''; ?>>
				<?php
                if ( docy_opt('menu_btn_icon') && docy_opt('menu_btn_icon_position') == 'before' ) {
	                $icon = docy_opt('menu_btn_icon');
                    echo '<i class="' . esc_attr( $icon ) . '"></i>';
                }

                echo esc_html( $btn_title );

				if ( docy_opt('menu_btn_icon') && docy_opt('menu_btn_icon_position') == 'after' ) {
					$icon = docy_opt('menu_btn_icon');
					echo '<i class="' . esc_attr( $icon ) . '"></i>';
				}

				if ( '_blank' === $btn_target ) {
					echo '<span class="screen-reader-text">' . esc_html__( '(opens in a new tab)', 'docy' ) . '</span>';
				}
                ?>
            </a>
		    <?php
        }

		get_template_part( 'template-parts/header-elements/dark-switcher' );

        // Search Form
		if ( docy_opt('is_search_form') == '1' && docy_opt('header_layout', 'default') == 'default' ) {
			?>
            <button type="button" class="search-icon" aria-label="<?php esc_attr_e( 'Toggle search', 'docy' ); ?>" aria-expanded="false">
                <i class="close-outline icon_close" aria-hidden="true"></i>
                <i class="search-outline icon_search" aria-hidden="true"></i>
            </button>
		    <?php
        }
		?>
    </div>
	<?php
endif;