<?php
defined( 'ABSPATH' ) || exit;

$featured_video   = docy_meta( 'featured_video' );
$video_thumb_url  = ! empty( $featured_video['video_thumbnail']['url'] ) ? $featured_video['video_thumbnail']['url'] : '';
$video_url        = ! empty( $featured_video['video_url'] ) ? $featured_video['video_url'] : '';
$video_id         = $video_url ? docy_get_youtube_video_id( $video_url ) : '';
$copy_link_label  = esc_html__( 'Copy link', 'docy' );
$copied_link_text = esc_html__( 'Link copied to clipboard!', 'docy' );

if ( ! empty( $video_id ) ) : ?>
	<div class="banner-video-container">
		<div class="video-wrapper">
			<?php if ( $video_thumb_url ): ?>
				<div class="video-thumbnail">
					<img src="<?php echo esc_url( $video_thumb_url ); ?>" alt="<?php esc_attr_e( 'Video Thumbnail', 'docy' ); ?>">
				</div>
			<?php endif; ?>
			<iframe class="banner-video-iframe" src="https://www.youtube.com/embed/<?php echo esc_attr( $video_id ); ?>?rel=0&enablejsapi=1"
			        title="<?php esc_attr_e( 'Featured Video', 'docy' ); ?>"
			        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
			        loading="lazy"
			        allowfullscreen
			></iframe>

			<button type="button" class="copy-link-btn" data-video-link="<?php echo esc_url( $video_url ); ?>" data-label-default="<?php echo esc_attr( $copy_link_label ); ?>" data-label-copied="<?php echo esc_attr( $copied_link_text ); ?>">
				<?php echo esc_html( $copy_link_label ); ?>
			</button>

			<div class="play-overlay">
				<button type="button" class="play-button">
					<img src="<?php echo esc_url( DOCY_DIR_IMG . '/play.svg' ); ?>" alt="<?php esc_attr_e( 'Play Icon', 'docy' ); ?>">
					<span class="paragraph-medium video-button_initial-text">
						<?php esc_html_e( 'Click to play', 'docy' ); ?>
                    </span>
				</button>
			</div>
		</div>
	</div>
<?php endif; ?>