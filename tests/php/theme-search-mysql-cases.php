<?php
/** Real public search excludes internal content and honors changed visibility. */
defined( 'ABSPATH' ) || exit;
register_post_type( 'qa_internal', array( 'public' => false, 'publicly_queryable' => false ) );
register_post_type( 'qa_public', array( 'public' => true, 'label' => 'Contenuti di prova' ) );
$qaSearchNeedle = 'QAVisibilitySentinel';
$qaSearchInternal = wp_insert_post( array( 'post_type' => 'qa_internal', 'post_status' => 'publish', 'post_title' => $qaSearchNeedle . ' contenuto riservato' ) );
$qaSearchPublic = wp_insert_post( array( 'post_type' => 'qa_public', 'post_status' => 'publish', 'post_title' => $qaSearchNeedle . ' contenuto pubblico' ) );
qa_check( $qaSearchInternal > 0 && $qaSearchPublic > 0, 'Native search fixtures could not be created.' );
$qaSearchOldUser = get_current_user_id();
wp_set_current_user( 0 );
$qaSearchNonce = wp_create_nonce( 'ajax_search_nonce' );
wp_set_current_user( $qaSearchOldUser );
$qaSearchRequest = array( 'action' => 'qa_public_search', 'security' => $qaSearchNonce, 'keyword' => $qaSearchNeedle );
$qaSearchOldCache = 'docy_search_' . md5( serialize( array( 'qa_internal', $qaSearchNeedle ) ) );
set_transient( $qaSearchOldCache, 'Risposta precedente riservata', HOUR_IN_SECONDS );
try {
    $qaSearchDenied = qa_http( $qaSearchRequest + array( 'post_type' => 'qa_internal' ), true );
    qa_check( 200 === $qaSearchDenied['status'] && '' === trim( $qaSearchDenied['body'] ), 'An internal post type or its stale cached result was exposed by public search.' );
    $qaSearchAllowed = qa_http( $qaSearchRequest + array( 'post_type' => 'qa_public' ), true );
    qa_check( 200 === $qaSearchAllowed['status'] && str_contains( $qaSearchAllowed['body'], $qaSearchNeedle . ' contenuto pubblico' ) && ! str_contains( $qaSearchAllowed['body'], 'contenuto riservato' ), 'Public search either hid public content or exposed internal content.' );
    qa_check( str_contains( $qaSearchAllowed['body'], 'Mostra altri risultati' ) && ! str_contains( $qaSearchAllowed['body'], 'Show More Results' ), 'Public search action was not rendered in Italian.' );
    $qaSearchMixed = qa_http( $qaSearchRequest + array( 'post_type[0]' => 'qa_internal', 'post_type[1]' => 'qa_public', 'post_type[2][0]' => 'nested' ), true );
    qa_check( 200 === $qaSearchMixed['status'] && str_contains( $qaSearchMixed['body'], $qaSearchNeedle . ' contenuto pubblico' ) && ! str_contains( $qaSearchMixed['body'], 'contenuto riservato' ), 'A mixed or nested type list bypassed public search visibility.' );
    set_transient( 'docy_search_' . md5( serialize( array( 'qa_public', $qaSearchNeedle ) ) ), $qaSearchAllowed['body'], HOUR_IN_SECONDS );
    wp_update_post( array( 'ID' => $qaSearchPublic, 'post_status' => 'draft' ) );
    $qaSearchUnpublished = qa_http( $qaSearchRequest + array( 'post_type' => 'qa_public' ), true );
    qa_check( 200 === $qaSearchUnpublished['status'] && ! str_contains( $qaSearchUnpublished['body'], $qaSearchNeedle ), 'A previously cached public result remained visible after becoming a draft.' );
    echo "Native public search visibility, mixed input and draft invalidation assertions passed.\n";
} finally {
    delete_transient( $qaSearchOldCache );
    delete_transient( 'docy_search_' . md5( serialize( array( 'qa_public', $qaSearchNeedle ) ) ) );
    wp_delete_post( $qaSearchInternal, true );
    wp_delete_post( $qaSearchPublic, true );
    unregister_post_type( 'qa_internal' );
    unregister_post_type( 'qa_public' );
}
