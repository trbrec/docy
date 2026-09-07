<?php
/** Curated service facts. The model may select a service, never create offers. */
if ( ! defined( 'ABSPATH' ) ) exit;
function trb_demo_service_catalog() {
    return array(
        'lyrics_revision' => array( 'name' => 'Revisione e Adattamento Autoriale del Testo', 'url' => 'https://store.trbrec.com/prodotto/revisione-e-adattamento-autoriale-del-testo/', 'scope' => 'Revisione del testo, coerenza, metrica e accenti. Per l’adattamento al canto occorrono testo e demo o base con riferimento melodico, oltre al significato da preservare.', 'audio' => false ),
        'stereo_mastering' => array( 'name' => 'Stereo Mastering Professionale', 'url' => 'https://store.trbrec.com/prodotto/stereo-mastering-professionale/', 'scope' => 'Finalizzazione di un mix stereo già pronto. Non corregge scrittura, arrangiamento o registrazioni da rifare. Richiede il premaster stereo.', 'audio' => true ),
        'production_essential' => array( 'name' => 'Produzione Musicale Essential', 'url' => 'https://store.trbrec.com/prodotto/produzione-musicale-essential/', 'scope' => 'Produzione e arrangiamento di un brano già scritto, con mix e master. Non proporre una produzione completa per un solo dettaglio da correggere.', 'audio' => true ),
    );
}
function trb_demo_service_selection_prompt() {
    $catalog = trb_demo_service_catalog();
    $text = "\nDOPO le sei sezioni della valutazione, aggiungi una sola riga tecnica nel formato TRB_SERVICE_JSON: {\"id\":\"\",\"reason\":\"\",\"evidence\":\"\"}. Verrà rimossa prima dell'invio. Scegli AL MASSIMO UN servizio solo quando risponde a una priorità concreta appena motivata; usa id vuoto se il lavoro può proseguire autonomamente, se il materiale è già adeguato o se nessun servizio è pertinente. Non creare bisogni per vendere. reason: massimo 320 caratteri, suggerimento facoltativo in seconda persona che spiega cosa risolverebbe per QUESTO progetto, senza prezzi, sconti, promesse, link o valutazioni sonore non verificate. evidence: copia letterale di una frase breve della tua valutazione definitiva che documenta il bisogno. Non attribuire all'artista la paternità di testi dichiarati altrui. Con solo testo non suggerire mastering o produzione. Per lyrics_revision esplicita che l'adattamento metrico al canto richiede un riferimento melodico; se non è disponibile, proponi di prepararlo prima dell'acquisto. Servizi ammessi:\n";
    foreach ( $catalog as $id => $item ) $text .= $id . ': ' . $item['name'] . '. ' . $item['scope'] . "\n";
    return $text;
}
function trb_demo_extract_service_selection( $raw, $has_audio ) {
    $parts = preg_split( '/\n[ \t]*(?:```(?:json)?\s*)?TRB_SERVICE_JSON:\s*/', $raw, 2 );
    $review = trim( $parts[0] );
    $selection = array();
    if ( isset( $parts[1] ) ) {
        $data = json_decode( trim( $parts[1] ), true );
        $catalog = trb_demo_service_catalog();
        $id = is_array( $data ) && is_string( $data['id'] ?? null ) ? $data['id'] : '';
        $reason = is_array( $data ) && is_string( $data['reason'] ?? null ) ? trim( $data['reason'] ) : '';
        $evidence = is_array( $data ) && is_string( $data['evidence'] ?? null ) ? trim( $data['evidence'] ) : '';
        if ( isset( $catalog[ $id ] ) && ( $has_audio || ! $catalog[ $id ]['audio'] ) && strlen( $reason ) >= 20 && strlen( $reason ) <= 1000 && strlen( $evidence ) >= 20 && strlen( $evidence ) <= 1000 && false !== strpos( $review, $evidence ) && ! preg_match( '/https?:|www\.|[<>]|\b(?:sconto|coupon)\b|[%€]/iu', $reason ) ) $selection = array( 'id' => $id, 'reason' => $reason, 'evidence' => $evidence );
    }
    return array( 'review' => $review, 'selection' => $selection );
}
function trb_demo_service_recommendation_html( $selection ) {
    $catalog = trb_demo_service_catalog();
    $id = is_array( $selection ) ? ( $selection['id'] ?? '' ) : '';
    if ( ! isset( $catalog[ $id ] ) || empty( $selection['reason'] ) ) return '';
    $item = $catalog[ $id ];
    return '<h2 style="margin:0 0 10px;font-size:18px;color:#20263b;">Un supporto possibile per questo progetto</h2><p style="line-height:1.7;margin:0 0 10px;">' . esc_html( $selection['reason'] ) . '</p><p style="line-height:1.7;margin:0 0 10px;"><a style="color:#243e63;font-weight:bold;" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a></p><p style="font-size:13px;line-height:1.6;color:#66708a;">' . esc_html( $item['scope'] ) . ' È una possibilità: puoi anche lavorare autonomamente seguendo le priorità indicate sopra.</p>';
}
