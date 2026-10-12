<?php
/** Preserve legacy field definitions while the inherited theme is being replaced. */
defined( 'ABSPATH' ) || exit;
add_action( 'after_setup_theme', static function() {
    remove_action( 'init', 'docy_remove_acf_fields_if_exists_in_codestar' );
}, PHP_INT_MAX );
