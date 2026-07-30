<?php
if ( ! empty( $summary_title ) || ! empty( $summary_desc ) )  :
    ?>
    <div class="toc-summery-wrapper">
        <?php if ( ! empty( $summary_title ) )  : ?>
            <h2 class="toc-summery-title"> <?php echo esc_html( $summary_title ); ?></h2>
        <?php endif;

        if ( ! empty( $summary_desc ) )  : ?>
            <p class="toc-summery-info"> <?php echo esc_html( $summary_desc ); ?></p>
        <?php endif; ?>

        <div class="toc-content row" id="docy-top-toc"></div>
    </div>
    <?php
endif;