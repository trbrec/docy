<?php
/**
 * The template for displaying search results pages.
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package docy
 */

wp_enqueue_style( 'nice-select' );
wp_enqueue_script( 'nice-select' );
get_header();
docy_render_banner();

$opt              = get_option( 'docy_opt', [] );
$blog_column      = is_active_sidebar( 'sidebar_widgets' ) ? '8' : '12';
$show_tab_filters = '1' === docy_opt( 'is_ajax_search_tab', '1' );

// Post types for search tabs 
$sbnr_post_types = ! empty( $opt['sbnr_post_types'] ) && is_array( $opt['sbnr_post_types'] ) ? $opt['sbnr_post_types'] : [ 'post', 'page' ];

// Search parameters
$search_term = get_search_query();
$post_type   = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'all';
$paged       = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );

// Query args
$args = [
    's'              => $search_term,
    'post_type'      => ( $post_type === 'all' ) ? $sbnr_post_types : $post_type,
    'posts_per_page' => get_option( 'posts_per_page' ),
    'paged'          => $paged,
];

// Sorting
$allowed_order_by = [ 'relevance', 'latest', 'oldest' ];
$order_by         = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'relevance';
$order_by         = in_array( $order_by, $allowed_order_by, true ) ? $order_by : 'relevance';
$tab_query_args   = [ 's' => $search_term ];

if ( 'relevance' !== $order_by ) {
	$tab_query_args['orderby'] = $order_by;
}

if ( $order_by === 'latest' ) {
    $args['orderby'] = 'date';
    $args['order']   = 'DESC';
} elseif ( $order_by === 'oldest' ) {
    $args['orderby'] = 'date';
    $args['order']   = 'ASC';
}

// Run custom query
$search_query = new WP_Query( $args );
?>

<section class="doc_blog_classic_area sec_pad">
    <div class="container">
        <div class="row">
            <div class="col-lg-<?php echo esc_attr( $blog_column ); ?> pe-4">
                <div class="search-main">

                    <!-- Results count -->
                    <div class="search-results-count mb-4 black-500">
                        <?php
                        printf(
                            esc_html__( 'Found %1$s results for "%2$s"', 'docy' ),
                            $search_query->found_posts,
                            '<span>' . esc_html( $search_term ) . '</span>'
                        );
                        ?>
                    </div>

                    <!-- Tabs -->
                    <div class="searchbar-tabs mb-4">
                        <div>
                            <?php if ( $show_tab_filters ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( $tab_query_args, home_url( '/' ) ) ); ?>" class="tab-item <?php echo esc_attr( docy_is_search_tab_active( 'all' ) ); ?>">
                                    <?php esc_html_e( 'All', 'docy' ); ?>
                                </a>
                                <?php foreach ( $sbnr_post_types as $sbnr_post_type ) : ?>
                                    <a href="<?php echo esc_url( add_query_arg( array_merge( $tab_query_args, [ 'post_type' => $sbnr_post_type ] ), home_url( '/' ) ) ); ?>"
                                       class="tab-item <?php echo esc_attr( docy_is_search_tab_active( $sbnr_post_type ) ); ?>">
                                        <?php echo esc_html( docy_get_modified_slug_by_post_type( $sbnr_post_type ) ); ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Sort dropdown -->
                        <form class="search_result_dropdown" method="get">
                            <label for="sort-select" class="me-2"> <?php esc_html_e( 'Sort by:', 'docy' ); ?> </label>
                            <select id="sort-select" name="orderby" class="dropdown_select" onchange="this.form.submit()">
                                <option value="relevance" <?php selected( $order_by, 'relevance' ); ?>> <?php esc_html_e( 'Relevance', 'docy' ); ?> </option>
                                <option value="latest" <?php selected( $order_by, 'latest' ); ?>> <?php esc_html_e( 'Latest', 'docy' ); ?> </option>
                                <option value="oldest" <?php selected( $order_by, 'oldest' ); ?>> <?php esc_html_e( 'Oldest', 'docy' ); ?> </option>
                            </select>

                            <?php
							if ( ! empty( $search_term ) ) : 
								?>
                                <input type="hidden" name="s" value="<?php echo esc_attr( $search_term ); ?>">
                            	<?php 
							endif;

							if ( $post_type !== 'all' ) : ?>
                                <input type="hidden" name="post_type" value="<?php echo esc_attr( $post_type ); ?>">
								<?php 
							endif; 
							?>

                        </form>
                    </div>

                    <!-- Results Loop -->
                    <?php 
					if ( $search_query->have_posts() ) :

						while ( $search_query->have_posts() ) : $search_query->the_post();
							get_template_part( 'template-parts/contents/content', 'search' );
						endwhile;

						Docy_helper()->pagination( $search_query );
						wp_reset_postdata();

						else :
						get_template_part( 'template-parts/contents/content', 'none' );

					endif; 
					?>

                </div>
            </div>
            <?php get_sidebar(); ?>
        </div>
    </div>
</section>

<?php 
get_footer();