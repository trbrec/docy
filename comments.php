<?php
/**
 * The template for displaying comments
 *
 * This is the template that displays the area of the page that contains both the current comments
 * and the comment form.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package docy
 */

/*
 * If the current post is protected by a password and
 * the visitor has not yet entered the password we will
 * return early without loading the comments.
 */
if ( post_password_required() ) {
	return;
}

$is_comments = have_comments() ? 'have_comments' : 'no_comments';

/*
 * The comments wrapper and list are always rendered (even with zero comments)
 * so AJAX-posted comments have a stable container to append to.
 */
?>
<div class="comment_inner <?php echo esc_attr( $is_comments ); ?>" id="comments">
    <h2 class="c_head"<?php echo have_comments() ? '' : ' hidden'; ?>> <?php docy_comment_count( get_the_ID() ); ?> </h2>
    <ul class="comment_box list-unstyled">
        <?php
        if ( have_comments() ) {
            wp_list_comments(
                array(
                    'style'      => 'ul',
                    'short_ping' => true,
                    'walker'     => new Docy_Walker_Comment,
                )
            );
            the_comments_navigation();
        }
        ?>
    </ul>
    <?php if ( ! have_comments() && comments_open() ) : ?>
        <p class="comment_empty"><?php esc_html_e( 'No comments yet — be the first to share your thoughts.', 'docy' ); ?></p>
    <?php endif; ?>
</div>

<div class="blog_comment_box <?php echo esc_attr($is_comments) ?>">
    <div class="docy-comment-response" role="status" aria-live="polite" hidden></div>
    <?php
    if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
        <p class="no-comments alert alert-warning"> <?php esc_html_e( 'Comments are closed.', 'docy' ); ?> </p>
        <?php
    endif;

    $commenter       = wp_get_current_commenter();
    $req             = get_option( 'require_name_email' );
    $aria_req        = ( $req ? " aria-required='true'" : '' );
    $url_placeholder = esc_attr__( 'Website (Optional)', 'docy' );

    /*
     * Each field shows its placeholder at rest; the matching <label> floats in
     * once the field gains content/focus. Required markers live on the label.
     */
    $fields = array(
        'author' => '<div class="col-md-6 form-group"> <input type="text" class="form-control" placeholder="'.esc_attr__('Full Name', 'docy').'" name="author" id="name" value="'.esc_attr($commenter['comment_author']).'" '.$aria_req.'><label for="name" class="floating-label">'.esc_html__('Full Name *', 'docy').'</label> </div>',
        'email'	=> '<div class="col-md-6 form-group"> <input type="email" class="form-control" placeholder="'.esc_attr__('Email', 'docy').'" name="email" id="email" value="'.esc_attr($commenter['comment_author_email']).'" '.$aria_req.'><label for="email" class="floating-label">'.esc_html__('Email *', 'docy').'</label> </div>',
        'url'	=> '<div class="col-md-12 form-group"><input type="url" class="form-control" placeholder="'.$url_placeholder.'" name="url" id="url" value="'.esc_attr($commenter['comment_author_url']).'"><label for="url" class="floating-label">'. $url_placeholder . '</label> </div>',
    );
    $comments_args = array(
        'fields'                => apply_filters( 'comment_form_default_fields', $fields ),
        'class_form'            => 'get_quote_form row',
        'class_submit'          => 'fill-brand',
        'title_reply_before'    => '<h2 class="c_head">',
        'title_reply'           => esc_html__( 'Leave a Comment', 'docy' ),
        'title_reply_after'     => '</h2>',
        'comment_notes_before'  => '',
        'comment_field'         => '<div class="col-md-12 form-group"><textarea name="comment" id="comment" placeholder="'.esc_attr__('Comment', 'docy').'" class="form-control message" aria-required="true"></textarea> <label for="comment" class="floating-label">'.esc_html__('Comment *', 'docy').'</label></div>',
        'comment_notes_after'   => '',
    );
    comment_form($comments_args);
    ?>
</div>