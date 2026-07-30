<?php
defined( 'ABSPATH' ) || exit;

if ( docy_opt('is_dark_switcher') == '1' ) :
	wp_enqueue_style( 'docy-dark-mode' );
	wp_enqueue_script( 'docy-dark-mode' );
    ?>
    <div class="px-2 darkmode-btn" title="<?php esc_attr_e( 'Toggle dark mode', 'docy' ); ?>">
        <label for="dark-mode-toggle" class="tab-btn tab-btns dm-moon-icon" aria-hidden="true">
            <i class="fas fa-moon"></i>
        </label>
        <label for="dark-mode-toggle" class="tab-btn dm-sun-icon" aria-hidden="true">
            <i class="fas fa-sun"></i>
        </label>
        <label id="dark-mode-ball" class="ball" for="dark-mode-toggle" aria-hidden="true"></label>
        <input type="checkbox" name="dark_mode" id="dark-mode-toggle" class="dark_mode_switcher something"
               role="switch" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'docy' ); ?>">
    </div>
    <?php
endif;