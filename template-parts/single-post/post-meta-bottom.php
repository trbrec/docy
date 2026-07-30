<?php
global $post;
if ( docy_opt( 'is_single_post_meta' ) == '1' ) :
	?>
    <div class="single_post_author d-flex justify-content-center">
        <div class="text post_tag">
			<?php
			// Display the post author
			if ( docy_opt('is_post_author') == '1' ) :
				?>
                <div class="author meta-item" title="<?php esc_attr_e( 'Post Author ', 'docy' ) ?>">
                    <i class="fa fa-user"></i>
                    <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>">
                        <?php echo esc_html(get_the_author()); ?>
                    </a>
                </div>
			<?php
			endif;

			if ( docy_opt( 'is_single_reading_time' ) == '1' ) :
				?>
                <div class="meta-item read-time" title="<?php docy_reading_time( get_the_ID() );
				esc_html_e( ' to read this post', 'docy' ); ?>">
                    <i class="fa fa-clock"></i>
					<?php docy_reading_time( get_the_ID() ); ?>
                </div>
			    <?php
			endif;
			docy_post_views( get_the_ID() );
			?>
            <div class="views meta-item">
                <i class="fa fa-eye"></i>
                <span> <?php echo get_post_meta( get_the_ID(), 'docy_post_views_count', true ) . esc_html__( ' Views', 'docy' ); ?> </span>
            </div>
            <?php
            if ( function_exists( 'docy_post_share' ) ) {
	            docy_post_share();
            }
            ?>
        </div>
    </div>
    <?php
endif;