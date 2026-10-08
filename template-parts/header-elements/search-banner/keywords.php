<?php
/**
 * Ajax Search Results
 */

$is_keywords = docy_opt( 'is_keywords', 1 );
if ( $is_keywords == '1' ) : ?>
    <div class="header_search_keyword">
		<?php
		if ( ! empty( $opt['keywords_label'] ) ) :
			?>
            <span class="header-search-form__keywords-label">
                <?php echo esc_html( $opt['keywords_label'] ) ?>
            </span>
		<?php
		endif;

		?>
        <ul class="list-unstyled">
			<?php
			if ( docy_opt( 'keywords_by' ) == 'static' ) :
				if ( ! empty( $opt['doc_keywords'] ) ) :
					foreach ( $opt['doc_keywords'] as $keyword ) :
						?>
                        <li class="wow fadeInUp" data-wow-delay="0.2s">
                            <a href="#"> <?php echo esc_html( $keyword['doc_keyword'] ); ?> </a>
                        </li>
					<?php
					endforeach;
				endif;
			else:

				$displayed_keywords = [];
				$counter = 0;

				foreach ( docy_get_search_keywords() as $word ) :
					?>
                    <li class="wow fadeInUp" data-wow-delay="0.2s">
                        <a href="#"> <?php echo esc_html( $word ); ?> </a>
                    </li>
					<?php
					$counter ++;

					// Check if the counter is 2
					if ( $counter >= docy_opt( 'keywords_limit' ) ) {
						break; // Exit the loop
					}
				endforeach;
			endif;
			?>
        </ul>

    </div>
<?php
endif;