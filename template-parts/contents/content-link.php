<?php
$allowed_html = array(
    'a' => array(
        'href' => array(),
        'title' => array()
    ),
    'br' => array(),
    'em' => array(),
    'strong' => array(),
    'iframe' => array(
        'src' => array(),
    )
);
?>

<div <?php post_class( 'blog_link_post bs-sm h:bs-lg' ); ?>>
    <a href="<?php the_permalink(); ?>">
        <p><?php the_title() ?></p>
    </a>
</div>
