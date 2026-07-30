<?php
/**
 * Template part for displaying posts in a grid layout.
 *
 * @package docy
 */

defined( 'ABSPATH' ) || exit;

$opt        = get_option( 'docy_opt' );
$thumb_size = is_active_sidebar( 'sidebar_widgets' ) ? 'docy_410x220' : 'full';

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
$blog_column    = docy_opt( 'blog_column', 3 );
$request_layout = isset( $_GET['blog_layout'] ) ? sanitize_key( wp_unslash( $_GET['blog_layout'] ) ) : '';

if ( 'blog_category' === $request_layout ) {
	$blog_column = '4';
}

$is_masonry   = docy_opt( 'is_blog_masonry' );
$masonry_item = $is_masonry && docy_opt( 'blog_layout' ) == 'grid' ? 'masonry-item' : 'col-lg-' . esc_attr( $blog_column ) . ' col-sm-6';
$data_cols    = $is_masonry && docy_opt( 'blog_layout' ) == 'grid' ? (string) $blog_column : '';

$show_card_header   = '1' === $is_post_meta['meta'] && ( '1' === $is_post_meta['date'] || '1' === $is_post_meta['reading_time'] || ( '1' === $is_post_cat && $first_category ) );
$has_category_badge = '1' === $is_post_cat && $first_category;
?>

<div class="<?php echo esc_attr( $masonry_item ); ?>"<?php echo '' !== $data_cols ? ' data-cols="' . esc_attr( $data_cols ) . '"' : ''; ?>>
	<div class="blog_grid_post wow fadeInUp">

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

		<!-- Featured Image Frame (if post has thumbnail) -->
		<?php if ( has_post_thumbnail() && ! is_search() ) : ?>
			<div class="blog-card-image-wrap">
				<a href="<?php the_permalink(); ?>">
					<?php the_post_thumbnail( $thumb_size ); ?>
				</a>
			</div>
		<?php endif; ?>

		<!-- Card Body Content -->
		<div class="blog-card-body grid_post_content">
			<!-- Title -->
			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" class="title-link">
				<h4 class="b_title">
					<?php Docy_helper()->limit_latter( get_the_title(), docy_opt( 'post_title_length', 55 ) ); ?>
				</h4>
			</a>

			<!-- Excerpt/Description -->
			<div class="blog-card-excerpt">
				<?php echo strip_shortcodes( Docy_helper()->excerpt( 'blog_excerpt', false ) ); ?>
			</div>

			<!-- Author Footer Section -->
			<?php if ( $is_post_meta['author'] == '1' && $is_post_meta['meta'] == '1' ) : ?>
				<div class="blog-card-footer d-flex align-items-center">
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