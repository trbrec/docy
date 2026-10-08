<div <?php post_class('search-post-item'); ?>>
    <div class="b_top_post_content">
        <?php
        if ( is_sticky() ) {
            echo '<p class="sticky-label">'.esc_html__( 'Featured', 'docy' ).'</p>';
        }
        docy_post_breadcrumbs();
        ?>
        <h5 class="title">
            <a href="<?php the_permalink(); ?>">
                <?php the_title() ?>
            </a>
        </h5>
        <?php echo strip_shortcodes(Docy_helper()->excerpt( 'blog_excerpt', false)); ?>
    </div>
</div>