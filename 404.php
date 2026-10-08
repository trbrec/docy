<?php
/**
 * The template for displaying 404 pages (not found)
 */

get_header();

$opt            = get_option( 'docy_opt' );
$bg_shape       = !empty( $opt['error_bg_shape_image'] ) ? $opt['error_bg_shape_image']['url'] : DOCY_DIR_IMG . '/404/404_bg.png';
$error_shape_1  = docy_opt('error_shape_1', DOCY_DIR_IMG . '/404/shape_1.png' );
$error_shape_2  = docy_opt('error_shape_2', DOCY_DIR_IMG . '/404/shape_2.png' );
$error_shape_3  = docy_opt('error_shape_3', DOCY_DIR_IMG . '/404/shape_3.png' );
?>

<section class="error_area bg_color">
    <div class="error_dot one"></div>
    <div class="error_dot two"></div>
    <div class="error_dot three"></div>
    <div class="error_dot four"></div>
    <div class="container">
        <div class="error_content_two text-center">
            <div class="error_img">
                <?php
                if ( !empty( $bg_shape ) ) {
                    ?>
                    <img class="p_absolute error_shap" src="<?php echo esc_url( $bg_shape ) ?>" alt="<?php esc_attr_e( '404 page background shape.', 'docy' ); ?>">
                    <?php
                }
                if ( !empty( $error_shape_1['url'] ) ) {
                    ?>
                    <div class="one wow clipInDown" data-wow-delay="1s">
                        <img class="img_one" src="<?php echo esc_url($error_shape_1['url']) ?>" alt="<?php esc_attr_e('4 illustration', 'docy' ); ?>">
                    </div>
                    <?php
                }
                if ( !empty( $error_shape_2['url'] ) ) {
                    ?>
                    <div class="two wow clipInDown" data-wow-delay="1.5s">
                        <img class="img_two" src="<?php echo esc_url($error_shape_2['url']) ?>" alt="<?php esc_attr_e( '0 illustration', 'docy' ); ?>">
                    </div>
                    <?php
                }
                if ( !empty( $error_shape_3['url'] ) ) {
                    ?>
                    <div class="three wow clipInDown" data-wow-delay="1.8s">
                        <img class="img_three" src="<?php echo esc_url($error_shape_3['url']) ?>" alt="<?php esc_attr_e( '4 illustration', 'docy' ); ?>">
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
            echo sprintf("<h1>%s</h1>", docy_opt('error_heading',  __( "Error! We can't find the page you're looking for.", 'docy' )  ));
            echo wp_kses_post( docy_opt( 'error_subtitle',  __( "We can't seem to find the page you're looking for", 'docy' ) ) );
            ?>

            <form action="<?php echo esc_url( home_url( '/' ) ) ?>" class="error_search">
                <input type="text" name="s" class="form-control" placeholder="<?php esc_attr_e( 'Search', 'docy' ); ?>">
            </form>

            <a href="<?php echo esc_url( home_url( '/' ) ) ?>" class="action_btn box_shadow_none">
                <i class="arrow_left"></i> <?php echo esc_html( docy_opt( 'error_home_btn_label',  __( "Go Back to home Page", 'docy' ) ) ); ?>
            </a>
        </div>
    </div>
</section>

<?php
get_footer( 'empty' );