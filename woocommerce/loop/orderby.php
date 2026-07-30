<?php
/**
 * Show options for ordering
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/orderby.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @package 	WooCommerce/Templates
 * @version     9.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$opt =  get_option( 'docy_opt' );
$layout = !empty($opt['shop_layout']) ? $opt['shop_layout'] : '';

$view_style = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : sanitize_key( $layout );

if ($view_style == 'grid' ) {
    $is_grid_active = 'active';
}elseif ($view_style == 'shop_grid' ) {
    $is_grid_active = 'active';
}else {
    $is_grid_active = '';
}

if ($view_style == 'list' ) {
    $is_list_active = 'active';
}elseif ($view_style == 'shop_list' ) {
    $is_list_active = 'active';
}else {
    $is_list_active = '';
}
$list_view_url = add_query_arg( 'view', 'list', remove_query_arg( 'paged' ) );
$grid_view_url = add_query_arg( 'view', 'grid', remove_query_arg( 'paged' ) );
$permalink = get_option( 'permalink_structure' );
?>
<div class="col-lg-6 col-sm-7">
    <div class="shop_menu_right d-flex">
        <h5 class="mr-2"> <?php esc_html_e( 'Sort by', 'docy' ) ?> </h5>
        <form class="woocommerce-ordering docy_select" method="get">
            <select name="orderby" class="orderby custom-select">
                <?php foreach ( $catalog_orderby_options as $id => $name ) : ?>
                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $orderby, $id ); ?>><?php echo esc_html( $name ); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="paged" value="1" />
            <?php wc_query_string_form_fields( null, array( 'orderby', 'submit', 'paged', 'product-page' ) ); ?>
        </form>
        <?php if ($permalink != '/%plain%/' ) : ?>
            <div class="view-style <?php echo esc_attr($layout) ?>">
                <div class="list-style <?php echo esc_attr($is_list_active); ?>">
                    <a href="<?php echo esc_url( $list_view_url ); ?>"><i class="ti-menu-alt"></i></a>
                </div>
                <div class="grid-style <?php echo esc_attr($is_grid_active); ?>">
                    <a href="<?php echo esc_url( $grid_view_url ); ?>"><i class="ti-layout-grid2"></i></a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>