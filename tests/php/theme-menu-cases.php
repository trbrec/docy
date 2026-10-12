<?php
/** Native menu rendering: visible titles, tooltip attributes and balanced nesting. */
defined( 'ABSPATH' ) || exit;
require_once dirname( __DIR__, 2 ) . '/inc/classes/Docy_Nav_Walker.php';
require_once dirname( __DIR__, 2 ) . '/inc/classes/Docy_Mobile_Nav_Walker.php';
$qaMenu = wp_create_nav_menu( 'Menu fittizio di collaudo' );
if ( is_wp_error( $qaMenu ) ) throw new RuntimeException( 'Could not create the isolated menu.' );
$qaMenuParent = 0;
for ( $qaMenuDepth = 0; $qaMenuDepth < 7; $qaMenuDepth++ ) {
    $qaMenuParent = wp_update_nav_menu_item( $qaMenu, 0, array(
        'menu-item-title' => 'Voce ' . $qaMenuDepth . ' à تونس',
        'menu-item-attr-title' => 'Suggerimento ' . $qaMenuDepth,
        'menu-item-description' => 'Descrizione ' . $qaMenuDepth,
        'menu-item-url' => 'https://example.invalid/menu-' . $qaMenuDepth . '?a=1&b=2',
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
        'menu-item-parent-id' => $qaMenuParent,
    ) );
    if ( is_wp_error( $qaMenuParent ) ) throw new RuntimeException( 'Could not create an isolated menu item.' );
}
$qaMenuWarnings = array();
set_error_handler( static function( $severity, $message ) use ( &$qaMenuWarnings ) { $qaMenuWarnings[] = $message; return false; } );
try {
    foreach ( array( 'Docy_Nav_Walker', 'Docy_Mobile_Nav_Walker' ) as $qaWalkerClass ) {
        $qaMenuArgs = array( 'menu' => $qaMenu, 'echo' => false, 'container' => false, 'fallback_cb' => false, 'walker' => new $qaWalkerClass(), 'depth' => 0, 'before' => '<span class="fixture-before"></span>', 'after' => '<span class="fixture-after"></span>' );
        $qaMenuHtml = wp_nav_menu( $qaMenuArgs );
        qa_woo_check( is_string( $qaMenuHtml ) && 7 === substr_count( $qaMenuHtml, '<li ' ), $qaWalkerClass . ': native WordPress did not render all seven levels.' );
        qa_woo_check( 7 === preg_match_all( '/<ul\b/', $qaMenuHtml ) && 7 === substr_count( $qaMenuHtml, '</ul>' ), $qaWalkerClass . ': deeply nested lists are unbalanced.' );
        qa_woo_check( 7 === substr_count( $qaMenuHtml, ' title="Suggerimento ' ), $qaWalkerClass . ': tooltip attributes were lost.' );
        qa_woo_check( ! str_contains( wp_strip_all_tags( $qaMenuHtml ), 'Suggerimento' ), $qaWalkerClass . ': a tooltip leaks into the visible menu label.' );
        qa_woo_check( 7 === substr_count( $qaMenuHtml, 'class="fixture-before"' ) && 7 === substr_count( $qaMenuHtml, 'class="fixture-after"' ), $qaWalkerClass . ': native before/after arguments were lost.' );
        qa_woo_check( 7 === substr_count( wp_strip_all_tags( $qaMenuHtml ), 'à تونس' ), $qaWalkerClass . ': UTF-8 menu labels were damaged.' );
        qa_woo_check( 7 === substr_count( $qaMenuHtml, 'menu-item-description' ), $qaWalkerClass . ': descriptions were lost.' );
        qa_woo_check( substr_count( $qaMenuHtml, '<div ' ) === substr_count( $qaMenuHtml, '</div>' ), $qaWalkerClass . ': mobile item wrappers are unbalanced.' );
        $qaMenuDom = new DOMDocument();
        $qaMenuDom->loadHTML( '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $qaMenuHtml . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
        $qaMenuXpath = new DOMXPath( $qaMenuDom );
        $qaMenuLinkPath = 'Docy_Mobile_Nav_Walker' === $qaWalkerClass ? '//li/div/a' : '//li/a';
        qa_woo_check( 7 === $qaMenuXpath->query( $qaMenuLinkPath )->length, $qaWalkerClass . ': browser DOM moves a menu link into another item.' );
        qa_woo_check( 6 === $qaMenuXpath->query( '//li/ul' )->length && 0 === $qaMenuXpath->query( '//ul/ul' )->length, $qaWalkerClass . ': submenu DOM ownership is incorrect.' );
        $qaMenuArgs['depth'] = 2;
        $qaLimitedMenu = wp_nav_menu( $qaMenuArgs );
        qa_woo_check( 2 === substr_count( $qaLimitedMenu, '<li ' ) && 2 === preg_match_all( '/<ul\b/', $qaLimitedMenu ) && 2 === substr_count( $qaLimitedMenu, '</ul>' ) && substr_count( $qaLimitedMenu, '<div ' ) === substr_count( $qaLimitedMenu, '</div>' ), $qaWalkerClass . ': the native depth limit leaves incomplete markup.' );
        qa_woo_check( empty( $qaMenuWarnings ), $qaWalkerClass . ': native rendering emitted PHP warnings.' );
    }
} finally {
    restore_error_handler();
    wp_delete_nav_menu( $qaMenu );
}
echo "Twenty-four native WordPress menu title, nesting, wrapper, depth, DOM and UTF-8 assertions passed.\n";
