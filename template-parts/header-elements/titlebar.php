<?php
defined( 'ABSPATH' ) || exit;

// Theme settings options

$opt                    = get_option('docy_opt' );
$left_ornament          = docy_meta('left_ornament', '1');
$is_banner              = docy_meta('is_banner', '1');
$titlebar_align         = $opt['titlebar_align'] ?? '';

$is_ornament_meta       = docy_meta('is_banner_ornament');
$is_banner_opt          = $opt['is_banner_ornaments'] ?? '';
$is_banner_ornaments    = $is_banner_opt == '1' ? $is_ornament_meta : $is_banner_opt;

if ( docy_no_titlebar() ) {
    $is_banner = '';
}

if ( is_singular('onepage-docs') ) {
	$is_banner = '1';
}

if ( $is_banner == '1' ) :
    ?>
    <div class="titlebar bg-<?php echo esc_attr( docy_opt('banner_bg') ) ?>">
        <?php
        if ( $is_banner_ornaments == '1' ) {
            Docy_helper()->image_from_settings('banner_left_ornament', 'p_absolute one', 'leaf left');
            Docy_helper()->image_from_settings('banner_right_ornament', 'p_absolute four', 'leaf right');
        }
        ?>
        <div class="container">
            <div class="breadcrumb_text text-<?php echo esc_attr($titlebar_align) ?>">
                <h1 class="text-<?php echo esc_attr($titlebar_align) ?>">
                    <?php docy_page_title() ?>
                </h1>
                <?php docy_excerpt(); ?>
            </div>
        </div>
    </div>
    <?php
endif;