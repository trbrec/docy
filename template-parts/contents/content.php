<?php
/**
 * Template part for displaying posts in classic list layout.
 *
 * @package docy
 */

defined( 'ABSPATH' ) || exit;

$opt                = get_option( 'docy_opt' );
$thumb_size         = is_active_sidebar( 'sidebar_widgets' ) ? 'docy_844x400' : 'full';
$blog_continue_read = ! empty( $opt['blog_continue_read'] ) ? $opt['blog_continue_read'] : esc_html__( 'Continue Reading', 'docy' );

// Post Meta Options
$is_post_meta = [
	'meta'         => $opt['is_post_meta'] ?? '1',
	'date'         => $opt['is_post_date'] ?? '1',
	'reading_time' => $opt['is_post_reading_time'] ?? '1',
	'author'       => $opt['is_post_author'] ?? '1'
];

$is_post_cat = $opt['is_post_cat'] ?? '1';

$post_author_id = get_post_field( 'post_author', get_the_ID() );
$categories     = get_the_category();
$first_category = ! empty( $categories ) ? $categories[0] : null;
$badge_link     = $first_category ? get_category_link( $first_category->term_id ) : '';

$show_card_header   = '1' === $is_post_meta['meta'] && ( '1' === $is_post_meta['date'] || '1' === $is_post_meta['reading_time'] || ( '1' === $is_post_cat && $first_category ) );
$has_category_badge = '1' === $is_post_cat && $first_category;
?>

<div <?php post_class( 'blog_classic_item wow fadeInUp' ); ?>>

	<?php if ( $show_card_header ) : ?>
		<div class="blog-card-header d-flex justify-content-between align-items-center">
			<div class="blog-card-meta-left">
				<?php if ( $has_category_badge ) : ?>
					<a class="blog-card-badge" href="<?php echo esc_url( $badge_link ); ?>">
						<?php echo esc_html( $first_category->name ); ?>
					</a>
				<?php endif; ?>
			</div>
			<div class="blog-card-meta">
				<?php if ( '1' === $is_post_meta['date'] ) : ?>
					<span class="meta-date"><?php the_time( get_option( 'date_format' ) ); ?></span>
				<?php endif; ?>
				<?php if ( '1' === $is_post_meta['reading_time'] && '1' === $is_post_meta['date'] ) : ?>
					<span class="meta-sep">|</span>
				<?php endif; ?>
				<?php if ( '1' === $is_post_meta['reading_time'] ) : ?>
					<span class="meta-read-time"><?php docy_reading_time( get_the_ID() ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Featured Image Frame -->
	<?php if ( has_post_thumbnail() && ! is_search() ) : ?>
		<div class="blog-card-image-wrap">
			<a href="<?php the_permalink(); ?>">
				<?php the_post_thumbnail( $thumb_size ); ?>
			</a>
		</div>
	<?php endif; ?>

	<!-- Card Body Content -->
	<div class="blog-card-body b_top_post_content">
		<!-- Title -->
		<div class="post_icon">
			<?php if ( docy_opt( 'is_post_format_icon' ) == '1' ) : ?>
				<i class="<?php echo esc_attr( $opt['b_standard_icon'] ) ?>"></i>
			<?php endif; ?>
			<a href="<?php the_permalink(); ?>" class="title-link">
				<h3 class="title">
					<?php the_title(); ?>
				</h3>
			</a>
		</div>

		<!-- Excerpt -->
		<div class="blog-card-excerpt">
			<?php echo strip_shortcodes( Docy_helper()->excerpt( 'blog_excerpt', false ) ); ?>
		</div>

		<!-- Footer / Author info -->
		<div class="d-flex justify-content-between p_bottom align-items-center">
			<a href="<?php the_permalink(); ?>" class="learn_btn">
				<?php echo esc_html( $blog_continue_read ); ?>
				<i class="<?php docy_arrow_left_right(); ?>"></i>
			</a>
			<?php if ( $is_post_meta['author'] == '1' ) : ?>
				<div class="blog-card-footer">
					<div class="blog-card-author d-flex align-items-center">
						<div class="author-avatar round_img">
							<?php Docy_helper()->post_author_avatar(); ?>
						</div>
						<span class="author-name">
							<a href="<?php echo esc_url( get_author_posts_url( $post_author_id ) ); ?>">
								<?php echo esc_html( get_the_author_meta( 'display_name' ) ); ?>
							</a>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>