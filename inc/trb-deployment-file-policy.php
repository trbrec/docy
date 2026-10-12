<?php
/** Repository tests remain available to CI and private release tooling, outside the public theme. */
function trb_portal_release_private_patterns() {
    return array(
        '/tools/test-*',
        '/tests/',
        '/integrations/site-studio/test-*',
        '/tools/fixtures-onboarding-entry-mail.php',
        '/tools/site-preview.test.cjs',
        '/integrations/site-studio/artist-cards.test.cjs',
        '/integrations/site-studio/editor-browser.test.cjs',
    );
}
function trb_portal_release_path_is_public( $path ) {
    foreach ( trb_portal_release_private_patterns() as $pattern ) {
        $pattern = substr( $pattern, 1 );
        if ( str_ends_with( $pattern, '/' ) ) {
            if ( str_starts_with( $path, $pattern ) ) return false;
        } elseif ( preg_match( '#^' . str_replace( '\\*', '[^/]*', preg_quote( $pattern, '#' ) ) . '(?:/.*)?$#D', $path ) ) {
            return false;
        }
    }
    return true;
}
