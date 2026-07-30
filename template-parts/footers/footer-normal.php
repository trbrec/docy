<?php

$copyright_text          = docy_opt( 'copyright_txt', esc_html__( '© 2021 Spider-Themes. All rights reserved', 'docy' ) );
$footer_background_color = docy_meta( 'footer_background_color' );
$has_bg                  = ! empty( $footer_background_color ) ? 'has_bg_color' : '';
$is_preset_footer        = '1' === docy_opt( 'is_footer_columns_preset' ) ? 'preset_footer' : '';
$footer_links            = docy_opt( 'footer_btm_links', [] );
?>
<footer class="doc_footer_area <?php echo !is_active_sidebar('footer_widgets') ? 'no_footer_widgets' : ''; ?>">
    <?php if ( is_active_sidebar('footer_widgets') ) : ?>
        <div class="doc_footer_top <?php echo esc_attr($has_bg) ?>">
            <div class="container">
                <div class="row doc_service_list_widget <?php echo esc_attr($is_preset_footer) ?>">
                    <?php dynamic_sidebar('footer_widgets') ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="doc_footer_bottom">
        <div class="container d-flex justify-content-between">
            <?php 
            if ( is_array( $footer_links ) ) :
                ?>
                <ul class="doc_footer_menu list-unstyled wow fadeInUp" data-wow-delay="0.2s">
                    <?php
                        foreach ( $footer_links as $footer_btm_link ) {
							$url   = $footer_btm_link['url'] ?? '';
							$title = $footer_btm_link['title'] ?? '';

							if ( empty( $url ) || empty( $title ) ) {
								continue;
							}

							echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></li>';
                        }
                    ?>
                </ul>
                <?php
            endif; 

            echo wp_kses( wpautop( $copyright_text ), docy_allowed_html() );
            ?>
        </div>
    </div>
</footer>