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
$qaCatalogEmpty = array();
$qaPlaceholderMismatches = array();
$qaPlaceholderPattern = '/%(?:[0-9]+\$)?[-+0 #]*(?:[0-9]+|\*)?(?:\.(?:[0-9]+|\*))?[bcdeEfFgGosuxX]/';
foreach ( $qaCatalog->entries as $qaMessageKey => $qaEntry ) {
    if ( ! array_filter( $qaEntry->translations ) ) $qaCatalogEmpty[] = $qaMessageKey;
    if ( array_filter( $qaEntry->translations ) && ( $qaCompiled->entries[$qaMessageKey]->translations ?? null ) !== $qaEntry->translations ) $qaCatalogMismatches[] = $qaMessageKey;
    foreach ( $qaEntry->translations as $qaIndex => $qaTranslation ) {
        preg_match_all( $qaPlaceholderPattern, $qaIndex > 0 && $qaEntry->plural ? $qaEntry->plural : $qaEntry->singular, $qaSourcePlaceholders );
        preg_match_all( $qaPlaceholderPattern, $qaTranslation, $qaTranslatedPlaceholders );
        sort( $qaSourcePlaceholders[0] ); sort( $qaTranslatedPlaceholders[0] );
        if ( $qaSourcePlaceholders[0] !== $qaTranslatedPlaceholders[0] ) $qaPlaceholderMismatches[] = $qaMessageKey;
    }
}
qa_check( ! $qaCatalogMismatches, 'The compiled Italian catalog differs from its maintained source.' );
qa_check( ! $qaCatalogEmpty, 'The maintained Italian catalog still contains untranslated entries.' );
qa_check( ! $qaPlaceholderMismatches, 'Italian format placeholders differ from the singular or plural source.' );
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
    'invalid_nonce' => 'Errore: verifica di sicurezza non valida.',
    'unsaved_changes' => 'Ci sono modifiche non salvate. Salva le modifiche!',
    'forum_login' => 'Devi effettuare l’accesso per iscriverti a questa discussione.',
    'search_focus' => 'Cerca (premi «/» per attivare la ricerca)',
    'reply_format' => 'Risposta n. 123 in Prova',
) as $qaKey => $qaExpected ) {
    qa_check( $qaExpected === $qaItalian['data']['data'][$qaKey], 'A standard Italian theme message failed native WordPress catalog/context/placeholder loading: ' . $qaKey );
}
$qaEnglish = qa_http( array( 'action' => 'qa_theme_language', 'qa_locale' => 'en_US' ), true );
qa_check( 200 === $qaEnglish['status'] && 'Search' === $qaEnglish['data']['data']['search'] && 'Registered: 2026' === $qaEnglish['data']['data']['registered'], 'The Italian catalog changed another locale.' );
echo "Nineteen native WordPress Italian catalog, completeness, context, placeholder and locale assertions passed.\n";
