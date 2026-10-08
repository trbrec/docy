<?php
// Page parent show as badge
if ( has_post_parent( get_the_ID() ) && is_page() ) {
	$current_parent_id = wp_get_post_parent_id( get_the_ID() );
	echo '<div class="page-parent mb-2">';
	echo '<a class="badge-14 badge" href="' . get_the_permalink( $current_parent_id ) . '">' . get_the_title( $current_parent_id ) . '</a>';
	echo '</div>';
}

if ( is_singular('forum') ) {
    ?>
    <div class="forum-title-area <?php echo !get_the_post_thumbnail( get_the_ID() ) ? 'no-thumb' : ''; ?>">
        <?php if ( get_the_post_thumbnail( get_the_ID() ) ) : ?>
            <div class="thumbnail">
                <?php echo get_the_post_thumbnail( get_the_ID(), 'full' ); ?>
            </div>
        <?php endif; ?>
        <div class="content">
            <h1 class="title"> <?php the_title() ?> </h1>
            <?php if ( get_the_content() ) : ?>
            <p class="forum-description"> <?php echo bbp_get_forum_content(); ?> </p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

$is_profile_page = function_exists( 'ezdpro_is_profile_page' ) && ezdpro_is_profile_page();

// Page title
if ( docy_opt( 'sbnr_title_fieldset', '1', 'is_page_title' ) == '1' && !is_singular([ 'docs', 'topic', 'product', 'forum' ]) && !$is_profile_page ) {
	?>
	<h1 class="title text-center"><?php docy_page_title() ?></h1>
	<?php
}

// Page Subtitle/Excerpt
if ( !is_singular([ 'docs', 'topic', 'product', 'forum' ]) ) {
	docy_page_subtitle();
}