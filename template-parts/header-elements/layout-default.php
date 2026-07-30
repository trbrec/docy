<?php
defined( 'ABSPATH' ) || exit;

$s_value        = get_search_query() ? get_search_query() : '';
$menu_alignment = docy_get_menu_alignment_classes();
$menu_class     = $menu_alignment['menu_class'];
$center_class   = $menu_alignment['center_class'];
?>

<div class="collapse navbar-collapse <?php echo esc_attr($menu_class) ?>" id="navbarSupportedContent">
    <?php
    docy_render_search_form(['class' => 'search-input toggle']);
    docy_render_main_menu( $center_class );
    ?>
    <?php get_template_part('template-parts/header-elements/action-button' ); ?>
</div>