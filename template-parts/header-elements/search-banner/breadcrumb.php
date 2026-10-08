<?php
$update_txt = docy_opt('breadcrumb_update_text', esc_html__('Updated on', 'docy'));
$breadcrumb_home = docy_opt('breadcrumb_home',esc_html__('Home', 'docy'));

$doc_container = '';
$breadcrumb_container = 'container';
if ( is_singular('docs') || is_post_type_archive('docs') ) {
    $breadcrumb_container = function_exists('ezd_container') ? ezd_container() : 'container';
    $docs_page_width = function_exists('ezd_get_opt') ? ezd_get_opt('docs_page_width') : '';
    $doc_container = $docs_page_width == 'full-width' ? 'ezd-breadcrumb-fluid' : '';
}

$is_breadcrumb = docy_meta_apply('is_breadcrumb');

// Skip Docy theme breadcrumb on single docs pages — EazyDocs controls its own breadcrumb independently
if ( is_singular('docs') ) {
    return;
}

if ( $is_breadcrumb == '1') :
    ?>
    <section class="page_breadcrumb <?php echo esc_attr($doc_container); ?>">
        <div class="<?php echo esc_attr($breadcrumb_container) ?>">
            <div class="row">
                <div class="col-lg-9 col-md-8">
                    <nav aria-label="breadcrumb">
                        <?php
                        if ( is_search() ) {
                            ?>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="<?php echo esc_url(home_url('/')) ?>">
                                        <?php echo esc_html($breadcrumb_home) ?>
                                    </a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page">
                                    <?php echo esc_html__( 'Search results for "', 'docy' );
                                    echo esc_html( get_search_query() ) . '"'; ?>
                                </li>
                            </ol>
                            <?php
                        } else {
                            docy_post_breadcrumbs();
                        }
                        ?>
                    </nav>
                </div>
                <div class="col-lg-3 col-md-4">
                    <span class="date" title="<?php echo esc_attr( sprintf( esc_html__( 'Published on: %s', 'docy' ), get_the_time( get_option( 'date_format' ) ) ) ); ?>">
                        <i class="<?php echo esc_attr( is_rtl() ? 'icon_quotations' : 'icon_clock_alt' ); ?>"></i>
                        <?php echo esc_html($update_txt); ?>
                        <?php docy_modified_date(); ?>
                    </span>
                </div>
            </div>
        </div>
    </section>
    <?php
endif;