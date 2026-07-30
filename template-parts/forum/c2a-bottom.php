<?php
defined( 'ABSPATH' ) || exit;

$opt              = get_option( 'docy_opt' );
$bg_image         = ! empty( $opt['forum_btm_c2a_bg']['url'] ) ? esc_url( $opt['forum_btm_c2a_bg']['url'] ) : '';
$button_title     = $opt['forum_btm_c2a_btn_title'] ?? ( $opt['forum_top_c2a_btn_title'] ?? '' );
$button_url       = $opt['forum_top_c2a_btn_url'] ?? '';
$background_style = $bg_image ? 'background-image: url(' . $bg_image . ');' : '';
?>
<div class="call-to-action">
    <div class="overlay-bg"<?php echo $background_style ? ' style="' . esc_attr( $background_style ) . '"' : ''; ?>></div>
    <div class="container">
        <div class="action-content-wrapper">
            <div class="action-title-wrap title-img">
				<?php if ( ! empty( $opt['forum_btm_c2a_logo']['url'] ) ) : ?>
                    <img src="<?php echo esc_url( $opt['forum_btm_c2a_logo']['url'] ) ?>" alt="<?php echo esc_attr( $opt['forum_btm_c2a_title'] ) ?>">
				<?php endif; ?>
				<?php if ( ! empty( $opt['forum_btm_c2a_title'] ) ) : ?>
                    <h2 class="action-title"><?php echo wp_kses( $opt['forum_btm_c2a_title'], docy_allowed_html() ) ?></h2>
				<?php endif; ?>
            </div>
			<?php if ( ! empty( $button_title ) ) : ?>
                <a href="<?php echo esc_url( $button_url ?: '#' ) ?>" class="action_btn">
					<?php echo esc_html( $button_title ) ?> <i class="<?php docy_arrow_left_right() ?>"></i>
                </a>
			<?php endif; ?>
        </div>
        <!-- /.action-content-wrapper -->
    </div>
    <!-- /.container -->
</div>