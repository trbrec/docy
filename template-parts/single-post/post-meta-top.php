<?php
if ( docy_opt('is_single_cats') == '1' || docy_opt('is_single_post_date') == '1' ) :
	?>
	<div class="post_tag post-meta-top mb-10">
        <?php if ( docy_opt('is_single_cats') == '1' ) : ?>
            <!-- Display the post categories -->
            <div class="cats meta-item bs-sm" title="<?php esc_attr_e( 'Categories', 'docy' ) ?>">
                <i class="fa fa-tags"></i>
                <span class="category-links">
                <?php
                $post_categories = get_the_category();

                if ( ! empty( $post_categories ) ) {
					$category_links = [];

					foreach ( $post_categories as $category ) {
						$category_links[] = sprintf(
							'<a href="%1$s">%2$s</a>',
							esc_url( get_category_link( $category->term_id ) ),
							esc_html( trim( $category->name ) )
						);
					}

					echo wp_kses_post( implode( ', ', $category_links ) );
				}
                ?>
                </span>
            </div>
        <?php endif; ?>

        <?php
        if ( docy_opt('is_single_post_date') == '1' ) :
	        ?>
            <!-- Display the post date -->
            <a href="<?php Docy_helper()->day_link(); ?>" class="meta-item date bs-sm" title="<?php esc_attr_e( 'First published on ', 'docy'); the_time(get_option('date_format')); ?>">
                <i class="fa fa-calendar"></i>
		        <?php esc_html_e( 'Updated on ', 'docy' ); the_modified_time( get_option( 'date_format' ) ); ?>
            </a>
            <?php
        endif;
        ?>
	</div>
	<?php
endif;