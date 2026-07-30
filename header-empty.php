<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset' ); ?>">
	<!-- For IE -->
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<!-- For Resposive Device -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> data-scroll-animation="true">
<?php
if ( function_exists('wp_body_open') ) {
	wp_body_open();
}

/**
 * Preloader
 */
$is_preloader = $opt['is_preloader'] ?? '';
if ( $is_preloader == '1' ) {
	get_template_part('template-parts/header-elements/preloader');
}
?>

<div class="body_wrapper">