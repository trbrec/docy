<?php
/**
 * Template part for displaying the article rating component on blog single pages.
 *
 * Inspired by Tidio's blog rating feature.
 * Includes Schema.org structured data for SEO (AggregateRating).
 *
 * @package docy
 */

// Bail if not a single post.
if ( ! is_singular( 'post' ) ) {
	return;
}

// Check if article rating is enabled in settings.
$is_rating_enabled = docy_opt( 'is_article_rating', '1' );
if ( '1' !== $is_rating_enabled ) {
	return;
}

$post_id = get_the_ID();

// Get current rating data from post meta.
$rating_data  = get_post_meta( $post_id, '_docy_article_rating', true );
$total_votes  = ! empty( $rating_data['votes'] ) ? intval( $rating_data['votes'] ) : 0;
$total_rating = ! empty( $rating_data['total'] ) ? floatval( $rating_data['total'] ) : 0;
$avg_rating   = $total_votes > 0 ? round( $total_rating / $total_votes, 1 ) : 0;

// Check if the user has already rated via cookie.
$cookie_name = 'docy_article_rated_' . $post_id;
$has_rated   = isset( $_COOKIE[ $cookie_name ] );

// Get settings for customizable text.
$rating_title      = docy_opt( 'article_rating_title', esc_html__( 'Rate the article', 'docy' ) );
$thank_you_message = docy_opt( 'article_rating_thank_you', esc_html__( 'Thank you for rating this article!', 'docy' ) );
$show_average      = docy_opt( 'is_article_rating_average', '1' );
$enable_schema     = docy_opt( 'is_article_rating_schema', '1' );

// Prepare data for structured data.
$post_title       = get_the_title( $post_id );
$post_url         = get_permalink( $post_id );
$post_description = get_the_excerpt( $post_id );
$post_date        = get_the_date( 'c', $post_id );
$post_modified    = get_the_modified_date( 'c', $post_id );
$author_name      = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
$author_url       = get_author_posts_url( get_post_field( 'post_author', $post_id ) );

// Get featured image for schema.
$featured_image = '';
if ( has_post_thumbnail( $post_id ) ) {
	$featured_image = get_the_post_thumbnail_url( $post_id, 'full' );
}

// Get site name.
$site_name = get_bloginfo( 'name' );
$site_url  = home_url();
?>

<?php
/**
 * Schema.org Structured Data for Article with AggregateRating.
 *
 * This helps search engines display rich snippets with star ratings.
 * Only output if there are votes (Google requires at least 1 rating) and schema is enabled.
 */
if ( $total_votes > 0 && '1' === $enable_schema ) :
?>
<script type="application/ld+json">
{
	"@context": "https://schema.org",
	"@type": "Article",
	"mainEntityOfPage": {
		"@type": "WebPage",
		"@id": "<?php echo esc_url( $post_url ); ?>"
	},
	"headline": "<?php echo esc_js( $post_title ); ?>",
	"description": "<?php echo esc_js( wp_strip_all_tags( $post_description ) ); ?>",
	<?php if ( $featured_image ) : ?>
	"image": "<?php echo esc_url( $featured_image ); ?>",
	<?php endif; ?>
	"datePublished": "<?php echo esc_attr( $post_date ); ?>",
	"dateModified": "<?php echo esc_attr( $post_modified ); ?>",
	"author": {
		"@type": "Person",
		"name": "<?php echo esc_js( $author_name ); ?>",
		"url": "<?php echo esc_url( $author_url ); ?>"
	},
	"publisher": {
		"@type": "Organization",
		"name": "<?php echo esc_js( $site_name ); ?>",
		"url": "<?php echo esc_url( $site_url ); ?>"
	},
	"aggregateRating": {
		"@type": "AggregateRating",
		"ratingValue": "<?php echo esc_attr( $avg_rating ); ?>",
		"bestRating": "5",
		"worstRating": "1",
		"ratingCount": "<?php echo esc_attr( $total_votes ); ?>"
	}
}
</script>
<?php endif; ?>

<div class="docy-article-rating" id="docy-article-rating" data-post-id="<?php echo esc_attr( $post_id ); ?>" itemscope itemtype="https://schema.org/Article">
	<?php if ( $has_rated ) : ?>
		<!-- Already Rated State -->
		<div class="docy-rating-thankyou">
			<div class="docy-rating-thankyou-icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32" aria-hidden="true">
					<path d="M7.493 18.5c-.425 0-.82-.236-.975-.632A7.48 7.48 0 0 1 6 15.125c0-1.75.599-3.358 1.602-4.634.151-.192.373-.309.6-.397.473-.183.89-.514 1.212-.924a9.042 9.042 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23h-.777ZM2.331 10.727a11.969 11.969 0 0 0-.831 4.398 12 12 0 0 0 .52 3.507c.26.85 1.084 1.368 1.973 1.368H4.9c.445 0 .72-.498.523-.898a8.963 8.963 0 0 1-.924-3.977c0-1.708.476-3.305 1.302-4.666.245-.403-.028-.959-.5-.959H4.25c-.832 0-1.612.453-1.918 1.227Z" />
				</svg>
			</div>
			<p class="docy-rating-thankyou-text"><?php echo esc_html( $thank_you_message ); ?></p>
		</div>
	<?php else : ?>
		<!-- Rating Input State -->
		<div class="docy-rating-input">
			<div class="docy-rating-label">
				<span class="docy-rating-title" id="docy-rating-label"><?php echo esc_html( $rating_title ); ?></span>
				<?php if ( $total_votes > 0 && '1' === $show_average ) : ?>
					<span class="docy-rating-average" itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">
						<meta itemprop="ratingValue" content="<?php echo esc_attr( $avg_rating ); ?>">
						<meta itemprop="bestRating" content="5">
						<meta itemprop="worstRating" content="1">
						<meta itemprop="ratingCount" content="<?php echo esc_attr( $total_votes ); ?>">
						<?php echo esc_html( number_format( $avg_rating, 1 ) ); ?>
					</span>
				<?php endif; ?>
			</div>
			<div class="docy-rating-stars" role="radiogroup" aria-labelledby="docy-rating-label">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<button type="button"
						class="docy-rating-star"
						role="radio"
						aria-checked="false"
						data-rating="<?php echo esc_attr( $i ); ?>"
						aria-label="<?php printf( esc_attr__( 'Rate %d out of 5 stars', 'docy' ), $i ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
						</svg>
					</button>
				<?php endfor; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php
	/**
	 * Hidden microdata for accessibility and SEO when displayed inline.
	 * This provides additional context for screen readers and crawlers.
	 */
	if ( $total_votes > 0 ) :
	?>
	<div class="screen-reader-text" aria-hidden="true">
		<?php
		printf(
			/* translators: 1: Average rating, 2: Total votes */
			esc_html__( 'Average rating: %1$s out of 5, based on %2$s votes.', 'docy' ),
			esc_html( number_format( $avg_rating, 1 ) ),
			esc_html( number_format_i18n( $total_votes ) )
		);
		?>
	</div>
	<?php endif; ?>
</div>