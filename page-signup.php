<?php
/**
 * Template Name: Sign up Page
 */

get_header('empty');

// Left Column
$left_title = docy_meta('left_title');
$top_ornament = docy_meta('top_ornament');
$bottom_ornament = docy_meta('bottom_ornament');
$featured_image = docy_meta('featured_image');

// Right Column
$right_title = docy_meta('right_title');
$right_subtitle = docy_meta('right_subtitle');
$submit_btn_label = docy_meta('submit_button_label');
?>

<section class="signup_area signup_area_height">
    <div class="sign_left signup_left">
        <?php echo !empty($left_title) ? "<h2> {$left_title} </h2>" : ''; ?>
        <?php
        if ( !empty($top_ornament['id']) ) {
            echo wp_get_attachment_image($top_ornament['id'], 'full');
        } else { ?>
            <img src="<?php echo DOCY_DIR_IMG ?>/sign-up/top_ornamate.png" class="position-absolute top" alt="<?php esc_attr_e('top ornament', 'docy'); ?>">
            <?php
        }
        if ( !empty($bottom_ornament['id']) ) {
            echo wp_get_attachment_image($bottom_ornament['id'], 'full');
        } else { ?>
            <img src="<?php echo DOCY_DIR_IMG ?>/sign-up/bottom_ornamate.png" class="position-absolute bottom" alt="<?php esc_attr_e('bottom ornament', 'docy'); ?>">
            <?php
        }
        if ( !empty($featured_image['id']) ) {
            echo wp_get_attachment_image($featured_image['id'], 'full');
        } else { ?>
            <img src="<?php echo DOCY_DIR_IMG ?>/sign-up/man_image.png" class="position-absolute middle wow fadeInRight" alt="<?php esc_attr_e('man image with lock', 'docy'); ?>">
            <?php
        }
        ?>
        <div class="round wow zoomIn" data-wow-delay="0.2s"></div>
    </div>
    <div class="sign_right signup_right">
            <div class="nave_container">
                <a href="<?php echo esc_url(home_url('/')) ?>" class="btn-lg left-icon doc_border_btn m-4">
                    <i class="arrow_left"></i> <?php esc_html_e( 'Back to Home', 'docy' ) ?>
                </a>
            </div>
            <div class="sign_inner signup_inner">
                <div class="text-center">
                    <?php echo !empty($right_title) ? "<h3> {$right_title} </h3>" : ''; ?>
                    <?php echo !empty($right_subtitle) ? wpautop(wp_kses_post($right_subtitle)) : ''; ?>
                </div>

                <?php
                $social_login_shortcode = docy_meta('social_login');

                if ( !empty($social_login_shortcode) ) : ?>
                    <div class="docy-social-login">
                        <?php echo do_shortcode($social_login_shortcode); ?>
                    </div>
                <?php endif; ?>

                <div id="reg-form-validation-messages"> </div>
                <?php
                // wp register form

                ?>
                <?php dt_reg_form($submit_btn_label) ?>
            </div>
        </div>
</section>

<?php
get_footer('empty');