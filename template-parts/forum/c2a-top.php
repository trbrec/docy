<?php
defined( 'ABSPATH' ) || exit;

$opt = get_option('docy_opt');
?>
<div class="answer-action shadow">
    <div class="action-content">
        <?php if ( !empty($opt['forum_top_c2a_logo']['url']) ) : ?>
            <div class="image-wrap">
                <img src="<?php echo esc_url($opt['forum_top_c2a_logo']['url']) ?>" alt="<?php echo esc_attr($opt['forum_top_c2a_title']) ?>">
            </div>
        <?php endif; ?>
        <div class="content">
            <?php if ( !empty($opt['forum_top_c2a_title']) ) : ?>
                <h2 class="ans-title"> <?php echo wp_kses_post($opt['forum_top_c2a_title']) ?> </h2>
            <?php endif; ?>
            <?php echo !empty($opt['forum_top_c2a_subtitle']) ? wp_kses_post( wpautop( $opt['forum_top_c2a_subtitle'] ) ) : ''; ?>
        </div>
    </div>
    <!-- /.action-content -->
    <?php if ( !empty($opt['forum_top_c2a_btn_title']) ) : ?>
        <div class="action-button-container">
            <a href="<?php echo esc_url($opt['forum_top_c2a_btn_url']) ?>" class="action_btn btn-ans">
                <?php echo esc_html($opt['forum_top_c2a_btn_title']) ?>
            </a>
        </div>
    <?php endif; ?>
</div>