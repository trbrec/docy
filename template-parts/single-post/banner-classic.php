<?php
$opt = get_option('docy_opt');
wp_enqueue_script( 'anchor' );
$sticky_toc = docy_meta('sticky_toc');
$sticky_toc = $sticky_toc ?? '1';
$post_column = $sticky_toc == '1' ? '9' : '12';
$p0 = $sticky_toc == '1' ? '' : 'p-0';
?>

<section class="tip_banner_area">
    <div class="container">
        <div class="row">
            <div class="col-lg-9 m-auto">
	            <?php
	            // Display the post title and meta information
	            get_template_part('template-parts/single-post/post-meta-top');
	            // Display the post title
	            the_title('<h1 class="banner_title">', '</h1>');
	            // Display rest of the post meta information
	            get_template_part('template-parts/single-post/post-meta-bottom');
	            ?>
            </div>
        </div>
        <div class="row" id="docy-top-toc"> </div>
    </div>
</section>