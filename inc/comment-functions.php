<?php
/**
 * Comment functionality for the theme.
 *
 * Provides AJAX comment submission, frontend inline comment editing,
 * permission helpers, and the shared comment markup renderer used by
 * both the comment walker and the AJAX responses.
 *
 * @package docy
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether AJAX commenting is enabled.
 *
 * @return bool True when AJAX comment submission is enabled.
 */
function docy_is_ajax_comments_enabled(): bool {
	/**
	 * Filters whether AJAX commenting is enabled.
	 *
	 * @param bool $enabled Default true.
	 */
	return (bool) apply_filters( 'docy_enable_ajax_comments', true );
}

/**
 * Whether frontend comment editing is enabled.
 *
 * @return bool True when frontend comment editing is enabled.
 */
function docy_is_comment_editing_enabled(): bool {
	/**
	 * Filters whether frontend comment editing is enabled.
	 *
	 * @param bool $enabled Default true.
	 */
	return (bool) apply_filters( 'docy_enable_comment_editing', true );
}

/**
 * Time window (in seconds) during which a comment author may edit their own comment.
 *
 * Return 0 for an unlimited window. Users with the `edit_comment` capability
 * (moderators/editors) are never restricted by this window.
 *
 * @return int Number of seconds. 0 means unlimited.
 */
function docy_comment_edit_window(): int {
	/**
	 * Filters the comment self-edit time window in seconds.
	 *
	 * @param int $seconds Default 15 minutes. 0 for unlimited.
	 */
	return absint( apply_filters( 'docy_comment_edit_window', 15 * MINUTE_IN_SECONDS ) );
}

/**
 * Determine whether the current user may edit the given comment on the frontend.
 *
 * Moderators (users with the `edit_comment` capability) may always edit.
 * A logged-in comment author may edit their own comment while it is within the
 * configured edit window. Anonymous (cookie-based) commenters cannot edit.
 *
 * @param int|WP_Comment $comment Comment ID or object.
 * @return bool True when the current user may edit the comment.
 */
function docy_user_can_edit_comment( $comment ): bool {
	if ( ! docy_is_comment_editing_enabled() ) {
		return false;
	}

	$comment = get_comment( $comment );
	if ( ! $comment instanceof WP_Comment ) {
		return false;
	}

	$can_edit = false;

	// Moderators / editors can always edit.
	if ( current_user_can( 'edit_comment', $comment->comment_ID ) ) {
		$can_edit = true;
	} elseif ( is_user_logged_in() ) {
		$user_id = get_current_user_id();

		// Only registered authors editing their own comment are allowed.
		if ( $user_id > 0 && (int) $comment->user_id === $user_id ) {
			$window = docy_comment_edit_window();

			if ( 0 === $window ) {
				$can_edit = true;
			} else {
				$posted_gmt = strtotime( $comment->comment_date_gmt . ' GMT' );
				$age        = time() - (int) $posted_gmt;
				$can_edit   = ( $age >= 0 && $age <= $window );
			}
		}
	}

	/**
	 * Filters whether the current user may edit a comment on the frontend.
	 *
	 * @param bool       $can_edit Whether editing is allowed.
	 * @param WP_Comment $comment  The comment object.
	 */
	return (bool) apply_filters( 'docy_user_can_edit_comment', $can_edit, $comment );
}

/**
 * Render the markup for a single comment.
 *
 * Shared between Docy_Walker_Comment and the AJAX handlers so that the markup
 * stays identical whether a comment is rendered on page load or after being
 * posted/edited via AJAX.
 *
 * @param int|WP_Comment $comment      Comment ID or object.
 * @param array          $args         wp_list_comments() style args.
 * @param int            $depth        Current nesting depth.
 * @param bool           $has_children Whether the comment has child comments.
 * @return string The comment markup (without wrapping <ul>).
 */
function docy_get_comment_markup( $comment, array $args = [], int $depth = 1, bool $has_children = false ): string {
	$comment = get_comment( $comment );
	if ( ! $comment instanceof WP_Comment ) {
		return '';
	}

	$args = wp_parse_args(
		$args,
		[
			'style'     => 'ul',
			'max_depth' => (int) get_option( 'thread_comments_depth', 5 ),
			'reply_text' => esc_html__( 'Reply', 'docy' ),
		]
	);

	$arrow_icon = is_rtl() ? 'arrow_left' : 'arrow_right';
	$tag        = ( 'div' === $args['style'] ) ? 'div' : 'li';
	$can_edit   = docy_user_can_edit_comment( $comment );

	ob_start();
	?>
	<<?php echo esc_html( $tag ); ?> id="comment-<?php echo esc_attr( $comment->comment_ID ); ?>" <?php comment_class( $has_children ? 'post_comment has_children' : 'post_comment', $comment ); ?> data-comment-id="<?php echo esc_attr( $comment->comment_ID ); ?>">
		<div class="media d-flex comment_author">
			<?php
			if ( get_avatar( $comment ) ) {
				echo get_avatar( $comment, 40, null, null, [ 'class' => 'img_rounded' ] );
			}
			?>
			<div class="media-body">
				<div class="comment_info dot-sep">
					<span class="author-name"> <?php echo esc_html( get_comment_author( $comment ) ); ?> </span>
					<div class="comment_date meta sep"> <?php echo esc_html( get_comment_date( get_option( 'date_format' ), $comment ) ); ?> </div>
				</div>
				<?php if ( '0' === $comment->comment_approved ) : ?>
					<em class="comment-awaiting-moderation"> <?php esc_html_e( 'Your comment is awaiting moderation.', 'docy' ); ?></em><br />
				<?php endif; ?>
				<div class="comment-txt editor-content">
					<?php comment_text( $comment ); ?>
				</div>
				<div class="comment_actions">
					<?php
					comment_reply_link(
						array_merge(
							$args,
							[
								'reply_text' => esc_html__( 'Reply', 'docy' ) . '<i class="' . esc_attr( $arrow_icon ) . '"></i>',
								'depth'      => $depth,
								'max_depth'  => $args['max_depth'],
							]
						),
						$comment
					);

					if ( $can_edit ) :
						?>
						<button type="button" class="docy-comment-edit-link" data-comment-id="<?php echo esc_attr( $comment->comment_ID ); ?>">
							<i class="icon_pencil-edit" aria-hidden="true"></i><?php esc_html_e( 'Edit', 'docy' ); ?>
						</button>
						<textarea class="docy-comment-edit-source" hidden><?php echo esc_textarea( $comment->comment_content ); ?></textarea>
						<?php
					endif;
					?>
				</div>
			</div>
		</div>
	<?php
	// Close the wrapper tag. Child comments (nested <ul>) are appended by the
	// walker between this content and the closing tag, so mirror the walker by
	// leaving it open here only when rendering via the walker. For AJAX we close it.
	echo '</' . esc_html( $tag ) . '>';

	return (string) ob_get_clean();
}

/**
 * Output the opening portion of a comment's markup (without the closing tag).
 *
 * Used by the walker so WordPress can nest child comment lists before the
 * closing tag is emitted by Walker_Comment::end_el().
 *
 * @param WP_Comment $comment      Comment object.
 * @param int        $depth        Current nesting depth.
 * @param array      $args         wp_list_comments() style args.
 * @param bool       $has_children Whether the comment has children.
 * @return void
 */
function docy_render_comment_open( $comment, int $depth, array $args, bool $has_children ): void {
	$markup = docy_get_comment_markup( $comment, $args, $depth, $has_children );

	// Strip the final closing tag so the walker can inject nested children.
	$tag    = ( isset( $args['style'] ) && 'div' === $args['style'] ) ? 'div' : 'li';
	$markup = preg_replace( '#</' . preg_quote( $tag, '#' ) . '>\s*$#', '', $markup );

	// Markup is assembled from escaped values inside docy_get_comment_markup().
	echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Handle AJAX submission of a new comment.
 *
 * Verifies the nonce, delegates validation/spam/duplicate handling to
 * wp_handle_comment_submission(), sets the commenter cookies, and returns the
 * rendered comment markup for insertion into the DOM.
 *
 * @return void
 */
function docy_ajax_post_comment(): void {
	if ( ! docy_is_ajax_comments_enabled() ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Comments are not available right now.', 'docy' ) ], 400 );
	}

	// Verify nonce for security.
	if ( ! isset( $_POST['docy_comment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['docy_comment_nonce'] ) ), 'docy_ajax_comment' ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Security check failed. Please refresh the page and try again.', 'docy' ) ], 403 );
	}

	// wp_handle_comment_submission() expects unslashed data and performs all
	// core validation: comments open, required fields, login requirement,
	// duplicate detection, flood control and spam filtering.
	$comment_data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized within core.
	$comment      = wp_handle_comment_submission( $comment_data );

	if ( is_wp_error( $comment ) ) {
		$error_message = $comment->get_error_message();
		if ( '' === $error_message ) {
			$error_message = esc_html__( 'An error occurred while posting your comment.', 'docy' );
		}

		// Never leak internal codes/traces; return the safe, human-readable message only.
		wp_send_json_error( [ 'message' => wp_strip_all_tags( $error_message ) ], 400 );
	}

	// Replicate core behaviour: persist the commenter cookies.
	$user            = wp_get_current_user();
	$cookies_consent = isset( $_POST['wp-comment-cookies-consent'] );

	/** This action is documented in wp-includes/comment.php */
	do_action( 'set_comment_cookies', $comment, $user, $cookies_consent );

	// Establish post context so the reply link and comment filters resolve
	// exactly as they do during a normal page render.
	global $post;
	$post = get_post( $comment->comment_post_ID ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	if ( $post instanceof WP_Post ) {
		setup_postdata( $post );
	}

	$comment_html = docy_get_comment_markup( $comment );
	$approved     = (string) $comment->comment_approved;

	// Refresh the public comment count so the heading can update in place.
	// Only approved comments are counted, matching what get_comments_number()
	// reports on a normal page load.
	$post_id     = (int) $comment->comment_post_ID;
	$count       = ( $post instanceof WP_Post ) ? (int) get_comments_number( $post_id ) : 0;
	$count_text  = function_exists( 'docy_get_comment_count_text' )
		? docy_get_comment_count_text( $post_id )
		: '';

	wp_send_json_success(
		[
			'comment_id'   => (int) $comment->comment_ID,
			'parent'       => (int) $comment->comment_parent,
			'approved'     => ( '1' === $approved ) ? 1 : 0,
			'comment_html' => $comment_html,
			'count'        => $count,
			'count_text'   => $count_text,
			'message'      => ( '1' === $approved )
				? esc_html__( 'Your comment has been posted.', 'docy' )
				: esc_html__( 'Thanks! Your comment is awaiting moderation.', 'docy' ),
		]
	);
}
add_action( 'wp_ajax_docy_post_comment', 'docy_ajax_post_comment' );
add_action( 'wp_ajax_nopriv_docy_post_comment', 'docy_ajax_post_comment' );

/**
 * Handle AJAX editing of an existing comment from the frontend.
 *
 * Verifies the nonce and edit permission, sanitises the content with the same
 * allowed-HTML rules WordPress applies to comments, persists the change and
 * returns the re-rendered comment text.
 *
 * @return void
 */
function docy_ajax_edit_comment(): void {
	// Verify nonce for security.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'docy_edit_comment' ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Security check failed. Please refresh the page and try again.', 'docy' ) ], 403 );
	}

	$comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
	$comment    = $comment_id ? get_comment( $comment_id ) : null;

	if ( ! $comment instanceof WP_Comment ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Comment not found.', 'docy' ) ], 404 );
	}

	// Capability / ownership check.
	if ( ! docy_user_can_edit_comment( $comment ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'You are not allowed to edit this comment.', 'docy' ) ], 403 );
	}

	$raw_content = isset( $_POST['comment_content'] ) ? trim( wp_unslash( $_POST['comment_content'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized below via wp_kses.

	if ( '' === $raw_content ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Comment cannot be empty.', 'docy' ) ], 400 );
	}

	// Apply the same allowed-HTML restrictions WordPress uses for comments,
	// unless the user is allowed to post unfiltered HTML.
	if ( current_user_can( 'unfiltered_html' ) ) {
		$content = $raw_content;
	} else {
		global $allowedtags;
		$allowed = ! empty( $allowedtags ) ? $allowedtags : wp_kses_allowed_html( 'data' );
		$content = wp_kses( $raw_content, $allowed );
	}

	$content = trim( $content );
	if ( '' === $content ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Comment cannot be empty.', 'docy' ) ], 400 );
	}

	// wp_update_comment() expects slashed data.
	$result = wp_update_comment(
		[
			'comment_ID'      => $comment->comment_ID,
			'comment_content' => wp_slash( $content ),
		],
		true
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'The comment could not be updated. Please try again.', 'docy' ) ], 500 );
	}

	$updated = get_comment( $comment->comment_ID );

	// Render the content exactly as comment_text() would.
	$content_html = get_comment_text( $updated );
	/** This filter is documented in wp-includes/comment-template.php */
	$content_html = apply_filters( 'comment_text', $content_html, $updated, [] );

	wp_send_json_success(
		[
			'comment_id'   => (int) $updated->comment_ID,
			'content_html' => $content_html,
			'source'       => $updated->comment_content,
			'message'      => esc_html__( 'Comment updated.', 'docy' ),
		]
	);
}
add_action( 'wp_ajax_docy_edit_comment', 'docy_ajax_edit_comment' );
// Editing requires a logged-in user, so no nopriv handler is registered.

/**
 * Output the AJAX comment nonce field inside the comment form.
 *
 * Scoped to the blog/docs comment form; WooCommerce product reviews use a
 * separate template and are intentionally left untouched.
 *
 * @return void
 */
function docy_comment_form_nonce_field(): void {
	if ( ! docy_is_ajax_comments_enabled() || is_singular( 'product' ) ) {
		return;
	}

	wp_nonce_field( 'docy_ajax_comment', 'docy_comment_nonce' );
}
add_action( 'comment_form', 'docy_comment_form_nonce_field' );
