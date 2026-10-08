<section class="banner_shape">
    <div class="gradient_banner_area">
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
        </div>
    </div>
</section>