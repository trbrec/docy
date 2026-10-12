<?php
/** Native WordPress title excerpts: UTF-8, safe text and inert shortcodes. */
defined( 'ABSPATH' ) || exit;
require_once dirname( __DIR__, 2 ) . '/inc/classes/Docy_helper.php';
$qaThemeText = static function( $text, $limit, $suffix = '...' ) {
    ob_start();
    Docy_helper()->limit_latter( $text, $limit, $suffix );
    return ob_get_clean();
};
foreach ( array(
    array( 'Artista italiano', 30, 'Artista italiano' ),
    array( 'Artista italiano', 7, 'Artista...' ),
    array( 'Gìulìa artista', 5, 'Gìulì...' ),
    array( 'تونس فنان', 3, 'تون...' ),
    array( '🎵🎶🎤 artista', 2, '🎵🎶...' ),
    array( '<img src=x onerror=alert(1)>Titolo lungo<script>alert(1)</script>', 6, 'Titolo...' ),
    array( 'A &amp; B', 4, 'A...' ),
) as [ $qaText, $qaLimit, $qaExpected ] ) {
    qa_check( $qaExpected === $qaThemeText( $qaText, $qaLimit ), 'A native theme title excerpt corrupted characters, escaped text or its requested limit.' );
}
qa_check( 'Titolo&lt;img src=x onerror=alert(1)&gt;' === $qaThemeText( 'Titolo lungo', 6, '<img src=x onerror=alert(1)>' ), 'An excerpt suffix injected HTML.' );
add_shortcode( 'qa_excerpt_inert', static function() { throw new RuntimeException( 'An excerpt executed a shortcode.' ); } );
try {
    qa_check( 'Artista' === $qaThemeText( 'Artista[qa_excerpt_inert]Segreto[/qa_excerpt_inert]', 20 ), 'Registered shortcode content leaked or executed in a theme excerpt.' );
} finally { remove_shortcode( 'qa_excerpt_inert' ); }
echo "Nine native WordPress safe UTF-8 title excerpt assertions passed.\n";
