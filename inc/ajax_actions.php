<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Docy search result item markup
 */
function docy_search_result_html($post_type, $id){
    if ( 'product' === $post_type ) :
        ?>
        <a class="search-result-item shop-search-result-item" href="<?php echo esc_url( get_the_permalink($id) ); ?>">
            <div class="shop-search-thumbnail-wrap">
                <?php
                if ( docy_opt('is_search_result_thumbnail') ) :
                    if ( has_post_thumbnail() ) :
                        the_post_thumbnail('docy_60x60');
					else:
                        ?>
                        <svg width="16px" aria-labelledby="title" viewBox="0 0 17 17" fill="currentColor" class="block h-full" role="img"><title id="title"><?php the_title(); ?></title>
                            <path d="M14.72,0H2.28A2.28,2.28,0,0,0,0,2.28V14.72A2.28,2.28,0,0,0,2.28,17H14.72A2.28,2.28,0,0,0,17,14.72V2.28A2.28,2.28,0,0,0,14.72,0ZM2.28,1H14.72A1.28,1.28,0,0,1,16,2.28V5.33H1V2.28A1.28,1.28,0,0,1,2.28,1ZM1,14.72V6.33H5.33V16H2.28A1.28,1.28,0,0,1,1,14.72ZM14.72,16H6.33V6.33H16v8.39A1.28,1.28,0,0,1,14.72,16Z"></path>
                        </svg>
                        <?php
                    endif;
                endif;
                ?>
            </div>
            <div class="shop-search-content-wrap">
                <h6 class="title">
                    <span class="topic-section"><?php the_title(); ?></span>
                    <svg viewBox="0 0 24 24" fill="none" color="white" stroke="white" width="16px" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="block h-auto w-16">
                        <polyline points="9 10 4 15 9 20"></polyline>
                        <path d="M20 4v7a4 4 0 0 1-4 4H4"></path>
                    </svg>
                </h6>
                <div class="price">
                    <?php
                    global $product;
                    if ( $product ) {
                        echo wp_kses_post($product->get_price_html());
                    }
                    ?>
                </div>
            </div>
        </a>
        <?php
     else :
        ?>
		<div class="search-result-item" data-result-url="<?php echo esc_url( get_permalink( $id ) ); ?>" role="link" tabindex="0">
            <a href="<?php echo esc_url( get_permalink( $id ) ); ?>" class="title">
                <svg width="16px" aria-labelledby="title" viewBox="0 0 17 17" fill="currentColor" class="block h-full w-auto" role="img"><title id="title"><?php the_title(); ?></title>
                    <path d="M14.72,0H2.28A2.28,2.28,0,0,0,0,2.28V14.72A2.28,2.28,0,0,0,2.28,17H14.72A2.28,2.28,0,0,0,17,14.72V2.28A2.28,2.28,0,0,0,14.72,0ZM2.28,1H14.72A1.28,1.28,0,0,1,16,2.28V5.33H1V2.28A1.28,1.28,0,0,1,2.28,1ZM1,14.72V6.33H5.33V16H2.28A1.28,1.28,0,0,1,1,14.72ZM14.72,16H6.33V6.33H16v8.39A1.28,1.28,0,0,1,14.72,16Z"></path>
                </svg>
                <span class="doc-section"><?php the_title(); ?></span>
                <svg viewBox="0 0 24 24" fill="none" color="white" stroke="white" width="16px" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="block h-auto w-16">
                    <polyline points="9 10 4 15 9 20"></polyline>
                    <path d="M20 4v7a4 4 0 0 1-4 4H4"></path>
                </svg>
            </a>

			<?php
			if ( docy_opt('is_search_result_breadcrumb') ) :
				setup_postdata( get_post($id) );
				?>
				<ol class="breadcrumb">
					<?php docy_ajax_search_breadcrumb(); ?>
				</ol>
				<?php 
				wp_reset_postdata();
			endif;
			?>
			
        </div>
    <?php
    endif;
}

add_action('wp_ajax_ajax_search', 'ajax_search_handler');
add_action('wp_ajax_nopriv_ajax_search', 'ajax_search_handler');

function ajax_search_handler() {

    check_ajax_referer('ajax_search_nonce', 'security');

    // Initialize post_type safely
    $post_type 		= isset( $_POST['post_type'] ) ? wp_unslash( $_POST['post_type'] ) : '';
    if ( isset( $_POST['keyword'] ) && is_scalar( $_POST['keyword'] ) ) {
    	$search_term = sanitize_text_field( wp_unslash( $_POST['keyword'] ) );
    } else {
    	$search_term = '';
    }

	if ( '' === $search_term ) {
		wp_die();
	}

	$posts_per_page = (int) docy_opt( 'doc_result_limit', '-1' );
	$posts_per_page = $posts_per_page < -1 ? 10 : $posts_per_page;
	$posts_per_page = 0 === $posts_per_page ? 10 : $posts_per_page;

    // Bolt: Cache search results for 1 hour to prevent N+1 queries on repeated searches.
    // Only cache for guests to prevent exposing private content visible to logged-in users.
    $is_cachable = ! is_user_logged_in();
    $cache_key = 'docy_search_' . md5( serialize( [ $post_type, $search_term ] ) );

    if ( $is_cachable ) {
        $cached_output = get_transient( $cache_key );
        if ( false !== $cached_output ) {
            echo $cached_output;
            wp_die();
        }
    }

    ob_start();

    $keyword_recorded = false; // Flag to ensure we record keyword only once

	if ( is_array( $post_type ) ) {
        // Limit to max 5 post types to prevent DoS
		$post_type = array_slice( array_map( 'sanitize_key', $post_type ), 0, 5 );

		foreach ( $post_type as $type ) {

            // Security: Check if post type exists
            if ( ! post_type_exists( $type ) ) {
                continue;
            }

             $args = [
                's'                 => $search_term,
                'post_type'         => $type,
				'posts_per_page'    => $posts_per_page,
                'orderby'           => 'post_date',
                'order'             => 'DESC',
				'post_status' 		=> 'publish',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
            ];            
            $query = new WP_Query($args);
            if ($query->have_posts()) :
                if ( ! $keyword_recorded ) {
				    docy_set_search_keywords($search_term);
                    $keyword_recorded = true;
                }
                ?>
                <div class="docy-search-results-heading">
                    <?php echo esc_html( docy_get_modified_slug_by_post_type($type) ); ?>
                </div>    
                <?php
                while ( $query->have_posts() ) { $query->the_post();
					docy_search_result_html($type, get_the_ID());               
                }
                wp_reset_postdata();
            endif;            
        }        
    } else { 
		$post_type_safe = sanitize_key( (string) $post_type );

        if ( post_type_exists( $post_type_safe ) ) {
            $args = [
                's'                 => $search_term,
                'post_type'         => $post_type_safe,
				'posts_per_page'    => $posts_per_page,
                'orderby'           => 'post_date',
                'order'             => 'DESC',
				'post_status' 		=> 'publish',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
            ];
            $query = new WP_Query($args);
            if ($query->have_posts()) :
                if ( ! $keyword_recorded ) {
                    docy_set_search_keywords($search_term);
                    $keyword_recorded = true;
                }
                ?>
                <div class="docy-search-results-heading">
                    <?php echo esc_html( docy_get_modified_slug_by_post_type($post_type_safe) ); ?>
                </div>
                <?php
                while ($query->have_posts()) {
                    $query->the_post();
                    docy_search_result_html($post_type_safe, get_the_ID());
                }
                wp_reset_postdata();

                $search_url = add_query_arg( [
                    's'         => $search_term,
                    'post_type' => $post_type_safe,
                ], home_url( '/' ) );
                ?>
                <a href="<?php echo esc_url( $search_url ); ?>" class="view-more-btn">
                    <?php esc_html_e( 'Show More Results', 'docy' ); ?>
                </a>
                <?php
            endif;
        }
    }

    $output = ob_get_clean();

    if ( $is_cachable ) {
        set_transient( $cache_key, $output, HOUR_IN_SECONDS );
    }
    echo $output;

    wp_die();
}

/**
 * Set searched keywords
 * Optimized to prevent option bloat by storing counts and limiting size.
 */
function docy_set_search_keywords( $keyword = '' ) {
	if ( empty( $keyword ) && isset( $_POST['keyword'] ) ) {
		$keyword = sanitize_text_field( wp_unslash( $_POST['keyword'] ) );
	}

	if ( empty( $keyword ) ) {
		return;
	}

	$keyword = sanitize_text_field( $keyword );
	$keyword = strtolower( trim( $keyword ) );

	if ( empty( $keyword ) ) {
		return;
	}

	// Cap keyword length to prevent option bloat / DoS via excessively long strings.
	$max_length = 191; // Reasonable limit for search term length.
	if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
		if ( mb_strlen( $keyword, 'UTF-8' ) > $max_length ) {
			$keyword = mb_substr( $keyword, 0, $max_length, 'UTF-8' );
		}
	} else {
		if ( strlen( $keyword ) > $max_length ) {
			$keyword = substr( $keyword, 0, $max_length );
		}
	}
	$keywords = get_option( 'docy_search_keyword', [] );

	if ( ! is_array( $keywords ) ) {
		$keywords = [];
	}

	// Migration: Convert legacy flat array (numeric list) to associative array (keyword => count)
	if ( ! empty( $keywords ) && array_values( $keywords ) === $keywords ) {
		$keywords = array_count_values( $keywords );
	}
	if ( isset( $keywords[ $keyword ] ) ) {
		$keywords[ $keyword ] ++;
	} else {
		$keywords[ $keyword ] = 1;
	}

	// Sort by count descending
	arsort( $keywords );

	// Limit to top 100 to prevent bloat (DoS protection)
	if ( count( $keywords ) > 100 ) {
		$keywords = array_slice( $keywords, 0, 100, true );
	}

	update_option( 'docy_search_keyword', $keywords );
}

/**
 * Render a shared bbPress topic card for AJAX responses.
 *
 * @param int $parent_post_id Optional parent/forum post ID.
 *
 * @return void
 */
function docy_render_forum_topic_card( int $parent_post_id = 0 ) {
	global $post;

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$topic_id        = get_the_ID();
	$author_id       = (int) $post->post_author;
	$author_class    = sanitize_html_class( get_the_author_meta( 'user_nicename', $author_id ) ?: 'topic-author' );
	$favoriters      = get_post_meta( $topic_id, '_bbp_favorite', true );
	$favorite_count  = is_array( $favoriters ) ? count( array_filter( $favoriters ) ) : ( ! empty( $favoriters ) ? 1 : 0 );
	$reply_count     = (int) get_post_meta( $topic_id, '_bbp_reply_count', true );
	$forum_id        = (int) bbp_get_topic_forum_id( $topic_id );
	$forum_id        = $forum_id ?: $parent_post_id;
	$forum_link      = $forum_id ? get_permalink( $forum_id ) : '';
	$forum_title     = $forum_id ? get_the_title( $forum_id ) : '';
	$forum_thumbnail = $forum_id ? get_the_post_thumbnail( $forum_id, [ 40, 40 ] ) : '';
	$author_link     = bbp_get_topic_author_link(
		[
			'post_id' => $topic_id,
			'type'    => 'avatar',
			'size'    => 40,
		]
	);
	?>
	<div class="community-post style-two <?php echo esc_attr( $author_class ); ?>">
		<div class="post-content">
			<div class="author-avatar">
				<?php echo wp_kses_post( $author_link ); ?>
			</div>
			<div class="entry-content">
				<a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark"><h3 class="post-title"><?php echo esc_html( get_the_title() ); ?></h3></a>
				<ul class="meta">
					<li>
						<?php echo wp_kses_post( $forum_thumbnail ); ?>
						<?php if ( $forum_link && $forum_title ) : ?>
							<a href="<?php echo esc_url( $forum_link ); ?>"><?php echo esc_html( $forum_title ); ?></a>
						<?php endif; ?>
					</li>
					<li><i class="icon_clock_alt"></i> <?php bbp_topic_post_date( $topic_id ); ?> </li>
				</ul>
			</div>
		</div>
		<div class="post-meta-wrapper">
			<ul class="post-meta-info">
				<li><a href="#"><i class="icon_chat_alt"></i><?php echo esc_html( $reply_count ); ?></a></li>
				<li><a href="#"><i class="icon_star"></i><?php echo esc_html( $favorite_count ); ?></a></li>
			</ul>
		</div>
	</div>
	<?php
}

/**
 * Get searched keywords
 */
function docy_get_search_keywords() {
	$keywords = get_option( 'docy_search_keyword', [] );

	if ( empty( $keywords ) ) {
		return [];
	}

	// Handle legacy flat array
	if ( isset( $keywords[0] ) ) {
		$keywords = array_count_values( $keywords );
		arsort( $keywords );
	}

	return array_keys( $keywords );
}

/**
 * Loading Post
 *
 * @return string
 */
add_action( 'wp_ajax_docy_loading_post', 'docy_loading_post' );
add_action( 'wp_ajax_nopriv_docy_loading_post', 'docy_loading_post' );

/**
 * Loading forum posts
 */
function docy_loading_post() {
	global $wpdb;

	$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	$type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
	$post_in = isset( $_POST['a_t_id'] ) ? sanitize_text_field( wp_unslash( $_POST['a_t_id'] ) ) : '';
	$count   = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 10;
	$parent  = absint( $_POST['parent'] ?? 0 );
	if ( ! wp_verify_nonce( $nonce, 'docy-nonce' ) ) {
		die( '-1' );
	}

	// Cap count to 100 to prevent DoS via resource exhaustion
	if ( $count > 100 || $count <= 0 ) {
		$count = 100;
	}

	$paged = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;
	$q     = [
		'post_type'           => 'topic',
		'post_parent'         => $parent,
		'order'               => 'DESC',
		'orderby'             => 'post_date',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => 1,
	];
	if ( 'author' === $type ) {
		$auth_ids = [
			'author' => $post_in,
		];
		$q        = array_merge( $q, $auth_ids );
	} elseif ( 'tag' === $type ) {
		$tax_query[] = [
			'taxonomy' => 'topic-tag',
			'field'    => 'term_id',
			'terms'    => $post_in,
		];
	}
	$tax_query[] = [
		'taxonomy' => 'post_format',
		'field'    => 'slug',
		'terms'    => [ 'post-format-quote', 'post-format-link' ],
		'operator' => 'NOT IN',
	];
	if ( ! empty( $tax_query ) ) {
		$tax_query = array_merge( [ 'relation' => 'AND' ], $tax_query );
		$q         = array_merge( $q, [ 'tax_query' => $tax_query ] );
	}
	$query = new WP_Query( $q );

	if ( $query->have_posts() ):
		echo '<div class="community-posts-wrapper bb-radius">';
		while ( $query->have_posts() ): $query->the_post();
			docy_render_forum_topic_card( $parent );
		endwhile;
		wp_reset_postdata();

		echo '</div>';
	else:
		echo '<div class="community-post-error bug">';
		echo '<div class="error-content">';
		echo '<svg height="40" class="docy-error error-icon" viewBox="0 0 24 24" version="1.1" width="40" aria-hidden="true"><path d="M12 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0112 7zm1 9a1 1 0 11-2 0 1 1 0 012 0z"></path><path fill-rule="evenodd" d="M12 1C5.925 1 1 5.925 1 12s4.925 11 11 11 11-4.925 11-11S18.075 1 12 1zM2.5 12a9.5 9.5 0 1119 0 9.5 9.5 0 01-19 0z"></path></svg>';
		echo '<h3 class="error">' . esc_html__( 'Oops! No results matched your search.', 'docy' ) . '</h3>';
		echo '<p class="error">' . esc_html__( 'You could search again.', 'docy' ) . '</p>';
		echo '</div>';
		echo '</div>';
	endif;

	die;
}

/**
 * Loading Post
 *
 * @return string
 */
add_action( 'wp_ajax_docy_open_post', 'docy_open_post' );
add_action( 'wp_ajax_nopriv_docy_open_post', 'docy_open_post' );

function docy_open_post() {
	global $wpdb;

	$is_queried_obj = is_singular( 'forum' ) ? get_queried_object_id() : false;
	$nonce          = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	$type           = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
	$post_in        = isset( $_POST['a_t_id'] ) ? sanitize_text_field( wp_unslash( $_POST['a_t_id'] ) ) : '';
	$count          = absint( $_POST['count'] ?? 0 );
	$parent         = absint( $_POST['parent'] ?? 0 );
	$userid         = absint( $_POST['userid'] ?? 0 );

	if ( ! wp_verify_nonce( $nonce, 'docy-nonce' ) ) {
		die( '-1' );
	}
	$paged = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;
	$q     = [
		'post_type'           => 'topic',
		'post_parent'         => $parent,
		'order'               => 'DESC',
		'orderby'             => 'post_date',
		'posts_per_page'      => get_option( '_bbp_topics_per_page', 10 ),
		'ignore_sticky_posts' => 1,
		'author'              => $userid
	];
	if ( 'open' === $type ) {
		$status = [
			'post_status' => 'publish',
		];
		$q      = array_merge( $q, $status );
	} elseif ( 'closed' === $type ) {
		$status = [
			'post_status' => 'closed',
		];
		$q      = array_merge( $q, $status );
	}
	$tax_query[] = [
		'taxonomy' => 'post_format',
		'field'    => 'slug',
		'terms'    => [ 'post-format-quote', 'post-format-link' ],
		'operator' => 'NOT IN',
	];
	if ( ! empty( $tax_query ) ) {
		$tax_query = array_merge( [ 'relation' => 'AND' ], $tax_query );
		$q         = array_merge( $q, [ 'tax_query' => $tax_query ] );
	}
	$query = new WP_Query( $q );
	if ( $query->have_posts() ):
		echo '<div class="community-posts-wrapper bb-radius">';
		while ( $query->have_posts() ): $query->the_post();
			docy_render_forum_topic_card( $parent );
		endwhile;
		wp_reset_postdata();

		echo '</div>';
	else:
		echo '<div class="community-post-error bug">';
		echo '<div class="error-content">';
		echo '<svg height="40" class="docy-error error-icon" viewBox="0 0 24 24" version="1.1" width="40" aria-hidden="true"><path d="M12 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0112 7zm1 9a1 1 0 11-2 0 1 1 0 012 0z"></path><path fill-rule="evenodd" d="M12 1C5.925 1 1 5.925 1 12s4.925 11 11 11 11-4.925 11-11S18.075 1 12 1zM2.5 12a9.5 9.5 0 1119 0 9.5 9.5 0 01-19 0z"></path></svg>';
		echo '<h3 class="error">' . esc_html__( 'Oops! No results matched your search.', 'docy' ) . '</h3>';
		echo '<p class="error">' . esc_html__( 'You could search again.', 'docy' ) . '</p>';
		echo '</div>';
		echo '</div>';
	endif;
	die;
}

add_action( 'wp_ajax_docy_loading_sort_post', 'docy_loading_sort_post' );
add_action( 'wp_ajax_nopriv_docy_loading_sort_post', 'docy_loading_sort_post' );

function docy_loading_sort_post() {
	global $wpdb;

	$nonce  = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	$sort   = isset( $_POST['sort'] ) ? sanitize_key( wp_unslash( $_POST['sort'] ) ) : '';
	$parent = absint( $_POST['parent'] ?? 0 );

	if ( ! wp_verify_nonce( $nonce, 'docy-nonce' ) ) {
		die( '-1' );
	}

	$paged = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;
	$q     = [
		'post_type'           => 'topic',
		'post_parent'         => $parent,
		'post_status'         => 'publish',
		'posts_per_page'      => get_option( '_bbp_topics_per_page', 10 ),
		'ignore_sticky_posts' => 1,
	];
	if ( 'newest_posts' === $sort ) {
		$newest_posts = [
			'order' => 'DESC',
		];
		$q            = array_merge( $q, $newest_posts );
	} elseif ( 'oldest_posts' === $sort ) {
		$oldest_posts = [
			'order' => 'ASC',
		];
		$q            = array_merge( $q, $oldest_posts );
	} elseif ( 'comment_count' === $sort ) {
		$comment_count = [
			'meta_key' => '_bbp_reply_count',
			'orderby'  => 'meta_value_num',
			'order'    => 'DESC',
		];
		$q             = array_merge( $q, $comment_count );
	} elseif ( 'comment_date' === $sort ) {
		$comment_count = [
			'meta_key'  => '_bbp_reply_count',
			'meta_type' => 'NUMERIC',
			'orderby'   => 'meta_value_num',
			'order'     => 'ASC',
		];
		$q             = array_merge( $q, $comment_count );
	} elseif ( 'recent_updated_post' === $sort ) {
		$post_date = [
			'orderby' => 'post_modified',
			'order'   => 'DESC',
		];
		$q         = array_merge( $q, $post_date );
	} elseif ( 'last_recent_updated_post' === $sort ) {
		$post_modified = [
			'orderby' => 'post_modified',
			'order'   => 'ASC',
		];
		$q             = array_merge( $q, $post_modified );
	}
	$tax_query[] = [
		'taxonomy' => 'post_format',
		'field'    => 'slug',
		'terms'    => [ 'post-format-quote', 'post-format-link' ],
		'operator' => 'NOT IN',
	];
	if ( ! empty( $tax_query ) ) {
		$tax_query = array_merge( [ 'relation' => 'AND' ], $tax_query );
		$q         = array_merge( $q, [ 'tax_query' => $tax_query ] );
	}
	$query = new WP_Query( $q );
	if ( $query->have_posts() ):
		echo '<div class="community-posts-wrapper bb-radius">';
		while ( $query->have_posts() ): $query->the_post();
			docy_render_forum_topic_card( $parent );
		endwhile;
		wp_reset_postdata();

		echo '</div>';
	else:
		echo '<div class="community-post-error bug">';
		echo '<div class="error-content">';
		echo '<svg height="40" class="docy-error error-icon" viewBox="0 0 24 24" version="1.1" width="40" aria-hidden="true"><path d="M12 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0112 7zm1 9a1 1 0 11-2 0 1 1 0 012 0z"></path><path fill-rule="evenodd" d="M12 1C5.925 1 1 5.925 1 12s4.925 11 11 11 11-4.925 11-11S18.075 1 12 1zM2.5 12a9.5 9.5 0 1119 0 9.5 9.5 0 01-19 0z"></path></svg>';
		echo '<h3 class="error">' . esc_html__( 'Oops! No results matched your search.', 'docy' ) . '</h3>';
		echo '<p class="error">' . esc_html__( 'You could search again.', 'docy' ) . '</p>';
		echo '</div>';
		echo '</div>';
	endif;

	die;
}

add_action( 'wp_ajax_docy_loading_pagination', 'docy_loading_pagination' );
add_action( 'wp_ajax_nopriv_docy_loading_pagination', 'docy_loading_pagination' );

function docy_loading_pagination() {
	global $wpdb;
	$nonce  = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	$list   = isset( $_POST['list'] ) ? sanitize_key( wp_unslash( $_POST['list'] ) ) : '';
	$parent = absint( $_POST['parent'] ?? 0 );
	if ( ! wp_verify_nonce( $nonce, 'docy-nonce' ) ) {
		die( '-1' );
	}
	$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
	$q     = [
		'post_type'           => 'topic',
		'post_parent'         => $parent,
		'order'               => 'DESC',
		'orderby'             => 'post_date',
		'posts_per_page'      => get_option( '_bbp_topics_per_page', 10 ),
		'ignore_sticky_posts' => 1,
		'paged'               => absint( $_POST['paged'] ?? 1 ),
		'page'                => absint( $_POST['paged'] ?? 1 ),

	];

	$query = new WP_Query( $q );
	if ( $query->have_posts() ):
		echo '<div class="community-posts-wrapper bb-radius">';
		while ( $query->have_posts() ): $query->the_post();
			docy_render_forum_topic_card( $parent );
		endwhile;
		wp_reset_postdata();
		echo '</div>';

	else:
		echo '<div class="community-post-error bug">';
		echo '<div class="error-content">';
		echo '<svg height="40" class="docy-error error-icon" viewBox="0 0 24 24" version="1.1" width="40" aria-hidden="true"><path d="M12 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0112 7zm1 9a1 1 0 11-2 0 1 1 0 012 0z"></path><path fill-rule="evenodd" d="M12 1C5.925 1 1 5.925 1 12s4.925 11 11 11 11-4.925 11-11S18.075 1 12 1zM2.5 12a9.5 9.5 0 1119 0 9.5 9.5 0 01-19 0z"></path></svg>';
		echo '<h3 class="error">' . esc_html__( 'Oops! No results matched your search.', 'docy' ) . '</h3>';
		echo '<p class="error">' . esc_html__( 'You could search again.', 'docy' ) . '</p>';
		echo '</div>';
		echo '</div>';
	endif;

	die;
}

/**
 * Article Rating Feature
 *
 * Handles AJAX request to submit article ratings on blog single pages.
 *
 * @since 4.4.0
 */
add_action( 'wp_ajax_docy_submit_article_rating', 'docy_submit_article_rating' );
add_action( 'wp_ajax_nopriv_docy_submit_article_rating', 'docy_submit_article_rating' );

/**
 * Handle article rating submission.
 *
 * Validates the request, sanitizes input, stores the rating in post meta,
 * and sets a cookie to prevent duplicate ratings.
 *
 * @return void
 */
function docy_submit_article_rating() {
	// Verify nonce for security.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'docy_article_rating_nonce' ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Security verification failed.', 'docy' ) ] );
	}

	// Validate and sanitize post ID.
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id || ! get_post( $post_id ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Invalid post ID.', 'docy' ) ] );
	}

	// Validate and sanitize rating value (1-5).
	$rating = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
	if ( $rating < 1 || $rating > 5 ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Invalid rating value.', 'docy' ) ] );
	}

	// Get existing rating data.
	$rating_data = get_post_meta( $post_id, '_docy_article_rating', true );
	if ( empty( $rating_data ) || ! is_array( $rating_data ) ) {
		$rating_data = [
			'votes' => 0,
			'total' => 0,
		];
	}

	// Update rating data.
	$rating_data['votes'] = intval( $rating_data['votes'] ) + 1;
	$rating_data['total'] = floatval( $rating_data['total'] ) + $rating;

	// Save updated rating data.
	update_post_meta( $post_id, '_docy_article_rating', $rating_data );

	// Set cookie to prevent duplicate ratings (expires in 30 days).
	$cookie_name   = 'docy_article_rated_' . $post_id;
	$cookie_expiry = time() + ( 30 * DAY_IN_SECONDS );
	setcookie( $cookie_name, '1', $cookie_expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

	// Calculate new average rating.
	$avg_rating = $rating_data['votes'] > 0 ? round( $rating_data['total'] / $rating_data['votes'], 1 ) : 0;

	wp_send_json_success(
		[
			'message'    => esc_html__( 'Thank you for rating this article!', 'docy' ),
			'avg_rating' => $avg_rating,
			'votes'      => $rating_data['votes'],
		]
	);
}