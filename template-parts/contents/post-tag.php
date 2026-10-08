<?php
$opt = get_option('docy_opt');
$is_post_meta = $opt['is_post_meta'] ?? '1';
$is_post_date = $opt['is_post_date'] ?? '1';
$is_post_reading_time = $opt['is_post_reading_time'] ?? '1';
$is_post_cat = $opt['is_post_cat'] ?? '1';
?>

<?php if ( $is_post_meta == '1' ) : ?>
    <div class="post_tag">
        <?php
        if ( $is_post_date == '1' ) {
            ?>
            <a href="<?php Docy_helper()->day_link(); ?>" class="meta-item">
                <i class="fa fa-calendar"></i>
                <?php the_time(get_option('date_format')); ?>
            </a>
            <?php
        }

        if ( $is_post_reading_time == '1' ) {
            if ( $is_post_meta == '1' ) { ?>
                <div class="meta-item">
                    <i class="fa fa-clock"></i>
                    <?php docy_reading_time(get_the_ID()); ?>
                </div>
                <?php
            }
        }

        if ( $is_post_cat == '1' && has_category() ) {
            ?>
            <a href="<?php Docy_helper()->first_category_link(); ?>" class="meta-item">
                <i class="fa fa-tags"></i>
                <?php Docy_helper()->first_category(); ?>
            </a>
            <?php
        }
        ?>
    </div>
    <?php
endif;