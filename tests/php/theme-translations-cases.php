<?php
/** Native WordPress just-in-time catalog loading in the standard Italian locale. */
defined( 'ABSPATH' ) || exit;
require_once ABSPATH . 'wp-includes/pomo/po.php';
require_once ABSPATH . 'wp-includes/pomo/mo.php';
$qaCatalog = new PO();
$qaCompiled = new MO();
$qaLanguagePath = dirname( __DIR__, 2 ) . '/languages/it_IT';
qa_check( $qaCatalog->import_from_file( $qaLanguagePath . '.po' ) && $qaCompiled->import_from_file( $qaLanguagePath . '.mo' ) && 'it_IT' === $qaCompiled->headers['Language'], 'The standard Italian source or compiled catalog could not be read.' );
$qaCatalogMismatches = array();
foreach ( $qaCatalog->entries as $qaMessageKey => $qaEntry ) {
    if ( array_filter( $qaEntry->translations ) && ( $qaCompiled->entries[$qaMessageKey]->translations ?? null ) !== $qaEntry->translations ) $qaCatalogMismatches[] = $qaMessageKey;
}
qa_check( ! $qaCatalogMismatches, 'The compiled Italian catalog differs from its maintained source.' );
$qaItalian = qa_http( array( 'action' => 'qa_theme_language', 'qa_locale' => 'it_IT' ), true );
qa_check( 200 === $qaItalian['status'] && true === $qaItalian['data']['success'], 'The Italian translation HTTP response failed.' );
foreach ( array(
    'search' => 'Cerca',
    'billing' => 'Dettagli di fatturazione',
    'registration' => 'Creare un account?',
    'empty_search' => 'Nessun risultato corrisponde alla ricerca.',
    'search_retry' => 'Puoi provare con un’altra ricerca.',
    'dark_mode' => 'Attiva o disattiva la modalità scura',
    'registered' => 'Registrazione: 2026',
    'quantity' => 'Quantità',
) as $qaKey => $qaExpected ) {
    qa_check( $qaExpected === $qaItalian['data']['data'][$qaKey], 'A standard Italian theme message failed native WordPress catalog/context/placeholder loading: ' . $qaKey );
}
$qaEnglish = qa_http( array( 'action' => 'qa_theme_language', 'qa_locale' => 'en_US' ), true );
qa_check( 200 === $qaEnglish['status'] && 'Search' === $qaEnglish['data']['data']['search'] && 'Registered: 2026' === $qaEnglish['data']['data']['registered'], 'The Italian catalog changed another locale.' );
echo "Twelve native WordPress Italian catalog, context, placeholder and locale assertions passed.\n";
