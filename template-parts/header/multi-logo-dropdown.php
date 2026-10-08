<?php
/**
 * Multi Logo Dropdown Template
 *
 * Displays a dropdown menu with multiple site logos for site switching.
 *
 * @package Docy
 * @since 1.0.0
 *
 * @var array $sites    Array of site data (name, logo, url, is_current).
 * @var array $see_all  See All link configuration (text, url).
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bail if no sites.
if ( empty( $sites ) ) {
	return;
}
?>
<button class="multi-logo-toggle" type="button" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle site switcher', 'docy' ); ?>">
	<i class="arrow_carrot-down toggle-icon"></i>
</button>

<div class="multi-logo-dropdown">
	<div class="multi-logo-dropdown-inner">
		<ul class="multi-logo-sites">
			<?php
			foreach ( $sites as $site ) :
				$name        = $site['name'] ?? '';
				$description = $site['description'] ?? '';
				$logo_url    = $site['logo']['url'] ?? '';
				$url         = $site['url'] ?? '#';
				$link_target = docy_get_link_target( $site['link_target'] ?? '_self' );
				$link_rel    = docy_get_link_rel( $link_target );
				$is_current  = isset( $site['is_current'] ) && $site['is_current'] == '1';
				?>
				<li class="site-item<?php echo $is_current ? ' current-site' : ''; ?>">
					<a href="<?php echo esc_url( $url ); ?>" class="site-link" target="<?php echo esc_attr( $link_target ); ?>"<?php echo $link_rel ? ' rel="' . esc_attr( $link_rel ) . '"' : ''; ?>>
						<span class="site-logo">
							<?php if ( ! empty( $logo_url ) ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $name ); ?>">
							<?php endif; ?>
						</span>
						<span class="site-info">
							<span class="site-name"><?php echo esc_html( $name ); ?></span>
							<?php if ( ! empty( $description ) ) : ?>
								<span class="site-description"><?php echo esc_html( $description ); ?></span>
							<?php endif; ?>
						</span>
						<span class="site-arrow">
							<i class="arrow_carrot-right"></i>
						</span>
						<?php if ( '_blank' === $link_target ) : ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'docy' ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php
		$see_all_text   = $see_all['text'] ?? '';
		$see_all_url    = $see_all['url'] ?? '';
		$see_all_target = docy_get_link_target( $see_all['link_target'] ?? '_self' );
		$see_all_rel    = docy_get_link_rel( $see_all_target );

		if ( ! empty( $see_all_text ) && ! empty( $see_all_url ) ) :
			?>
			<div class="see-all-link">
				<a href="<?php echo esc_url( $see_all_url ); ?>" target="<?php echo esc_attr( $see_all_target ); ?>"<?php echo $see_all_rel ? ' rel="' . esc_attr( $see_all_rel ) . '"' : ''; ?>>
					<?php echo esc_html( $see_all_text ); ?>
					<i class="arrow_right"></i>
					<?php if ( '_blank' === $see_all_target ) : ?>
						<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'docy' ); ?></span>
					<?php endif; ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>
