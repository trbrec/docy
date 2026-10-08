<?php
$opt = get_option( 'docy_opt' );
wp_enqueue_script( 'anchor' );
$creative_video = has_post_format( 'video' ) ? 'shadow-sm' : 'toc-creative-default';
$video_url      = docy_meta('video_url');
?>

<section class="tip_banner_area toc-wrapper">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="toc-wrapper-banner">
                    <?php
                    // Category badges
                    $categories = get_the_category();
                    if ( ! empty( $categories ) ) :
                    ?>
                    <div class="banner-category-badges">
                        <?php foreach ( $categories as $cat ) : ?>
                            <a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>">
                                <?php echo esc_html( $cat->name ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
					<?php
					the_title( '<h1 class="banner_title">', '</h1>' );
					echo strip_shortcodes( Docy_helper()->excerpt( 'blog_excerpt', false ) );
					?>
                    <div class="banner-post-meta">
                        <?php if ( docy_opt('is_single_post_date') == '1' ) : ?>
                        <span class="meta-item">
                            <i class="fa fa-calendar-alt"></i>
                            <?php echo esc_html( get_the_modified_time( get_option( 'date_format' ) ) ); ?>
                        </span>
                        <span class="meta-divider"></span>
                        <?php endif; ?>
                        <?php if ( docy_opt( 'is_single_reading_time' ) == '1' ) : ?>
                        <span class="meta-item">
                            <i class="fa fa-clock"></i>
                            <?php docy_reading_time( get_the_ID() ); ?>
                        </span>
                        <span class="meta-divider"></span>
                        <?php endif; ?>
                        <?php if ( docy_opt('is_post_author') == '1' ) : ?>
                        <span class="meta-item">
                            <i class="fa fa-user"></i>
                            <a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta('ID') ) ); ?>">
                                <?php echo esc_html( get_the_author() ); ?>
                            </a>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="toc-creative-media <?php echo esc_attr( $creative_video ); ?>">
                    <?php
                    if ( has_post_format( 'video' ) && ! empty( $video_url ) ) :
                        $thumbnail_url    = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'full' ) : '';
                        $video_post_style = $thumbnail_url ? 'background-image: url(' . esc_url( $thumbnail_url ) . '); background-size: cover;' : '';
                        wp_enqueue_style( 'magnific-popup' );
                        wp_enqueue_script( 'magnific-popup' );
                        ?>
                            <div class="video_post"<?php echo $video_post_style ? ' style="' . esc_attr( $video_post_style ) . '"' : ''; ?>>
                            <a class="popup-youtube video_icon" href="<?php echo esc_url( $video_url ) ?>">
                                <i class="arrow_triangle-right"></i>
                            </a>
                        </div>
                        <?php 
                    else :
                        if ( has_post_thumbnail() ) :
                        ?>
                        <div class="banner-featured-img-wrap">
                            <?php the_post_thumbnail('full'); ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="toc-banner-overlay">
        <img src="<?php echo esc_url( DOCY_DIR_IMG . '/banner-blog/toc-overlay.svg' ); ?>" alt="<?php esc_attr_e( 'Overlay Image', 'docy' ) ?>" class="overlay-shape-light"/>
        <img src="<?php echo esc_url( DOCY_DIR_IMG . '/banner-blog/toc-overlay-dark.svg' ); ?>" alt="<?php esc_attr_e( 'Overlay Image', 'docy' ) ?>" class="overlay-shape-dark"/>
    </div>
</section>