<?php
$shape1 = docy_meta_apply('banner_shape_01');
$shape2 = docy_meta_apply('banner_shape_02');
?>
<section class="doc_banner_area single_breadcrumb">
    <ul class="list-unstyled banner_shap_img">
        <li>
            <?php
            if ( !empty($shape1['id'])) {
                echo wp_get_attachment_image($shape1['id'], 'full');
            }
            ?>
        </li>
        <li><img src="<?php echo DOCY_DIR_IMG . '/banner-blog/banner_shape_4.png' ?>" alt="<?php esc_attr_e('banner shape', 'docy') ?>"></li>
        <li><img src="<?php echo DOCY_DIR_IMG . '/banner-blog/banner_shape_3.png' ?>" alt="<?php esc_attr_e('banner shape', 'docy') ?>"></li>
        <li>
            <?php
            if ( !empty($shape2['id']) ) {
                echo wp_get_attachment_image($shape2['id'], 'full');
            }
            ?>
        </li>
        <li><img data-parallax='{"x": -180, "y": 80, "rotateY":2000}' src="<?php echo DOCY_DIR_IMG . '/banner-blog/plus1.png' ?>" alt="<?php esc_attr_e('plus icon', 'docy') ?>"></li>
        <li><img data-parallax='{"x": -50, "y": -160, "rotateZ":200}' src="<?php echo DOCY_DIR_IMG . '/banner-blog/plus2.png' ?>" alt="<?php esc_attr_e('plus icon', 'docy') ?>"></li>
        <li></li>
        <li></li>
        <li></li>
    </ul>

    <div class="container">
        <div class="doc_banner_content">
            <?php
            // Display the post title and meta information
            get_template_part('template-parts/single-post/post-meta-top');
            // Display the post title
            the_title('<h1 class="title">', '</h1>');
            // Display rest of the post meta information
            get_template_part('template-parts/single-post/post-meta-bottom');
            ?>
        </div>
    </div>
</section>