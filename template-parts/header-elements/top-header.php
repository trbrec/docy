<?php defined( 'ABSPATH' ) || exit; ?>
<div class="top_header">
	<?php
	// Extract the domain from the current URL
	if ( docy_opt( 'top_header_left_items' ) ) {
		?>
        <div class="left_contents">
            <ul class="list-unstyled">
				<?php
				foreach ( docy_opt( 'top_header_left_items' ) as $item ) {
					// Extract the domain from the link URL
					$item_url = ! empty( $item['link'] ) ? $item['link'] : '';
					$btn_target      = docy_get_link_target( $item['link_target'] ?? '_self' );
					$link_rel        = docy_get_link_rel( $btn_target );
					$item_image      = ! empty( $item['image']['id'] ) ? wp_get_attachment_image( $item['image']['id'], 'full' ) : '';

					$current_parts = wp_parse_url( home_url( '/' ) );
					$item_parts    = wp_parse_url( $item_url );
					$current_host  = strtolower( $current_parts['host'] ?? '' );
					$item_host     = strtolower( $item_parts['host'] ?? $current_host );
					$current_path  = trim( $current_parts['path'] ?? '', '/' );
					$item_path     = trim( $item_parts['path'] ?? '', '/' );
					$is_active     = docy_opt( 'is_active_left_items' ) == '1' && ! empty( $item_url ) && $current_host === $item_host && $current_path === $item_path;
					$is_active_class = $is_active ? 'is-active' : '';
					?>
                    <li class="<?php echo esc_attr( $is_active_class ) ?>">
						<a href="<?php echo esc_url( $item_url ) ?>" target="<?php echo esc_attr( $btn_target ) ?>"<?php echo $link_rel ? ' rel="' . esc_attr( $link_rel ) . '"' : ''; ?>>
							<?php echo wp_kses_post( $item_image ); ?>
							<span> <?php echo esc_html( $item['text'] ) ?> </span>
							<?php if ( '_blank' === $btn_target ) : ?>
								<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'docy' ); ?></span>
							<?php endif; ?>
                        </a>
                    </li>
					<?php
				}
				?>
            </ul>
        </div>
		<?php
	}

	if ( docy_opt( 'top_header_right_items' ) ) {
		?>
        <div class="right_contents">
            <ul class="list-unstyled">
				<?php
				foreach ( docy_opt( 'top_header_right_items' ) as $item ) {
					$item_url   = ! empty( $item['link'] ) ? $item['link'] : '';
					$btn_target = docy_get_link_target( $item['link_target'] ?? '_self' );
					$link_rel   = docy_get_link_rel( $btn_target );
					$item_icon  = ! empty( $item['image']['id'] ) ? wp_get_attachment_image( $item['image']['id'], 'full' ) : '';
					?>
                    <li>
						<a href="<?php echo esc_url( $item_url ) ?>" target="<?php echo esc_attr( $btn_target ) ?>"<?php echo $link_rel ? ' rel="' . esc_attr( $link_rel ) . '"' : ''; ?>>
							<?php echo wp_kses_post( $item_icon ); ?>
							<span> <?php echo esc_html( $item['text'] ) ?> </span>
							<?php if ( '_blank' === $btn_target ) : ?>
								<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'docy' ); ?></span>
							<?php endif; ?>
                        </a>
                    </li>
					<?php
				}
				?>
            </ul>
        </div>
		<?php
	}
	?>
</div>