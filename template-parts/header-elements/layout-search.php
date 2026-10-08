<?php
defined( 'ABSPATH' ) || exit;

$is_search_form_page    = docy_meta('is_search_form');
$is_search_form_opt     = docy_opt( 'is_search_form', '' );
$is_search_form         = ! empty( $is_search_form_page ) ? $is_search_form_page : $is_search_form_opt;
$s_value                = get_search_query() ? get_search_query() : '';

$menu_alignment         = docy_get_menu_alignment_classes();
$menu_class             = $menu_alignment['menu_class'];
$center_class           = $menu_alignment['center_class'];
?>
<div class="collapse navbar-collapse <?php echo esc_attr($menu_class) ?>" id="navbarSupportedContent">
    <?php
    if ( $is_search_form == '1' ) {
        docy_render_search_form(['class' => 'search-input show-by-default']);
    }
    
    docy_render_main_menu( $center_class );
    
    get_template_part('template-parts/header-elements/action-button' );
    ?>
</div>