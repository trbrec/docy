<div class="docy-mobile-toc">
	<?php
	$current_permalink = get_permalink();
	$mail_subject      = rawurlencode( wp_strip_all_tags( get_the_title() ) );
	$mail_body         = rawurlencode( sprintf( '%s %s', esc_html__( 'Check out this page', 'docy' ), $current_permalink ) );
	$facebook_share    = 'https://www.facebook.com/share.php?u=' . rawurlencode( $current_permalink );
	$linkedin_share    = 'https://www.linkedin.com/shareArticle?mini=true&url=' . rawurlencode( $current_permalink );
	$twitter_share     = 'https://x.com/intent/tweet?text=' . rawurlencode( $current_permalink );
	?>
	<div class="mobile-toc">
		<div class="overlay" id="toc-overlay"></div>
		<!-- Button to toggle the Table of Contents -->
		<button class="fqmceZ table_content" aria-expanded="false" aria-controls="docy-toc">
			<?php esc_html_e('Table of Contents', 'docy'); ?>
		</button>

		<!-- Hidden Table of Contents, will appear above the button -->
		<aside class="bottom_table_content" id="docy-tocs" aria-hidden="true">
			<button class="close-toc">
				<img src="<?php echo esc_url( DOCY_DIR_IMG . '/icons/cross-icon.svg' ); ?>" alt="<?php esc_attr_e( 'Cross icon', 'docy') ?>">
			</button>
			<nav data-toggle="toc" class="nav-sidebar doc-nav">
				<!-- You can dynamically generate your TOC items here -->
			</nav>
		</aside>

		<!-- Button to show Share modal -->
		<button class="fqmceZ table_share_btn">
			<img src="<?php echo esc_url( DOCY_DIR_IMG . '/icons/share-icon.svg' ); ?>" alt="<?php esc_attr_e( 'Share icon', 'docy') ?>">
			<?php esc_html_e('Share', 'docy'); ?>
		</button>

		<!-- Hidden Share Modal -->
		<div class="docy-modal-content" id="share-modal" aria-hidden="true">

			<button class="close docy-close" aria-label="<?php esc_attr_e( 'Close share modal', 'docy' ); ?>">
				<img src="<?php echo esc_url( DOCY_DIR_IMG . '/icons/cross-icon.svg' ); ?>" alt="<?php esc_attr_e( 'Cross icon', 'docy'); ?>">
			</button>

			<div class="docy-share-wrap">
				<div class="social-links">
					<a href="mailto:?subject=<?php echo esc_attr( $mail_subject ); ?>&amp;body=<?php echo esc_attr( $mail_body ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Share by email', 'docy' ); ?>">
						<i class="icon_mail"></i>
						<span class="screen-reader-text"><?php esc_html_e( 'Share by email (opens in a new tab)', 'docy' ); ?></span>
					</a>
					<a href="<?php echo esc_url( $facebook_share ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Share on Facebook', 'docy' ); ?>">
						<i class="social_facebook_circle"></i>
						<span class="screen-reader-text"><?php esc_html_e( 'Share on Facebook (opens in a new tab)', 'docy' ); ?></span>
					</a>
					<a href="<?php echo esc_url( $linkedin_share ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Share on LinkedIn', 'docy' ); ?>">
						<i class="social_linkedin_square"></i>
						<span class="screen-reader-text"><?php esc_html_e( 'Share on LinkedIn (opens in a new tab)', 'docy' ); ?></span>
					</a>
					<a class="twitter" title="<?php esc_attr_e( 'Twitter (X)', 'docy' ); ?>" href="<?php echo esc_url( $twitter_share ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Share on Twitter (X)', 'docy' ); ?>"><i class="fa-brands fa-x-twitter"> </i><span class="screen-reader-text"><?php esc_html_e( 'Share on Twitter (X) (opens in a new tab)', 'docy' ); ?></span></a>
				</div>
				<p><?php esc_html_e( 'Copy link', 'docy' ); ?></p>
				<div class="docy-copy-url-wrap">
					<div class="share-this-docs">
						<input readonly type="text" value="<?php echo esc_url( $current_permalink ); ?>" class="word-wrap">
						<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/clone.svg' ); ?>" alt="<?php esc_attr_e( 'Copy link icon', 'docy' ); ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
</div>