<?php
/** Inspect the visible payment-method notice without depending on one obsolete sentence. */
function trb_onboarding_instant_payment_copy_present( $html ) {
    if ( ! is_string( $html ) || '' === $html ) return false;
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors( true );
    try {
        if ( ! $document->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET ) ) return false;
        $notice = $document->getElementById( 'payment-methods' );
        if ( ! $notice ) return false;
        $text = preg_replace( '/\s+/u', ' ', $notice->textContent );
        foreach ( array( 'PayPal', 'carta', 'credito', 'debito', 'pagamenti online' ) as $term ) if ( ! str_contains( $text, $term ) ) return false;
        return ! str_contains( strtolower( $text ), 'bonifico' ) && ! str_contains( $html, 'Per il bonifico' );
    } finally { libxml_clear_errors(); libxml_use_internal_errors( $previous ); }
}
