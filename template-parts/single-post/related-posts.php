<?php
$opt = get_option('docy_opt' );
if ( ! in_array( $opt['is_related_posts'] ?? null, array( '1', 1, true, 1.0 ), true ) ) {
    return;
}

$current_post_id = get_the_ID();
$cats = get_the_terms($current_post_id, 'category' );
if ( ! is_array( $cats ) || empty( $cats ) ) {
    return;
}
$cat_ids = wp_list_pluck($cats,'term_id' );
$related_post_count = !empty($opt['related_posts_count']) ? $opt['related_posts_count'] : 3;
$posts = new WP_Query( array(
    'post_type' => 'post',
    'tax_query' => array(
        array(
            'taxonomy' => 'category',
            'field' => 'id',
            'terms' => $cat_ids,
            'operator'=> 'IN' //Or 'AND' or 'NOT IN'
        )),
    'posts_per_page' => $related_post_count,
    'ignore_sticky_posts' => 1,
    'no_found_rows' => true,
    'orderby' => 'rand',
    'post__not_in' => array($current_post_id)
));

if ( $posts->have_posts() ) :
    ?>
    <div class="blog_related_post">
        <?php
        if(!empty($opt['related_posts_title'])) : ?>
            <h2 class="c_head"> <?php echo esc_html($opt['related_posts_title']) ?> </h2>
        <?php endif; ?>
        <div class="row">
            <?php
            while($posts->have_posts()) : $posts->the_post(); ?>
                <div class="col-lg-4 col-sm-6">
                    <div class="blog_grid_post wow fadeInUp" data-wow-delay="0.2s">
                        <?php the_post_thumbnail('docy_370x200'); ?>
                        <div class="grid_post_content">
                            <div class="post_tag">
                                <span class="meta">
                                    <?php docy_reading_time(get_the_ID()); ?>
                                </span>
                                <a class="cat" href="<?php Docy_helper()->first_category_link(); ?>">
                                    <?php Docy_helper()->first_category(); ?>
                                </a>
                            </div>
                            <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute() ?>">
                                <h4 class="b_title">
                                    <?php Docy_helper()->limit_latter( get_the_title(), 45 ); ?>
                                </h4>
                            </a>
                            <p> <?php Docy_helper()->limit_latter(wp_strip_all_tags(get_the_content()), 55); ?> </p>
                        </div>
                    </div>
                </div>
                <?php
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
    </div>
    <?php
endif;
