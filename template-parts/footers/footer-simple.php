<?php
$opt = get_option('docy_opt' );
?>
<footer class="simple_footer">
    <?php if ( !empty($opt['fs_illustration']['url']) ) : ?>
        <img class="leaf_right" src="<?php echo esc_url($opt['fs_illustration']['url']) ?>" alt="<?php esc_attr_e('Leaf Illustration', 'docy'); ?>">
    <?php endif; ?>
    <div class="container custom_container">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <?php
                $copyright_text = !empty($opt['copyright_txt']) ? $opt['copyright_txt'] : esc_html__('© 2025 Spider-Themes. All rights reserved', 'docy');
                echo wp_kses(wpautop($copyright_text), docy_allowed_html());
                ?>
            </div>
            <div class="col-sm-6 text-right">
                <ul class="list-unstyled f_social_icon">
                    <?php Docy_helper()->social_links() ?>
                </ul>
            </div>
        </div>
    </div>
</footer>