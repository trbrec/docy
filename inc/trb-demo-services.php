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
    $text = "\nMETADATI SEPARATI OBBLIGATORI: dopo le sei sezioni scrivi una sola riga TRB_SERVICE_JSON: {\"id\":\"\",\"reason\":\"\",\"evidence\":\"\"}. Non sono una settima sezione della valutazione. La riga viene rimossa e validata dal sistema. Valuta il supporto professionale più pertinente rispetto alle priorità osservate: AL MASSIMO UN servizio. La possibilità di lavorare autonomamente non esclude un supporto facoltativo del team se risponde a un bisogno concreto. Non creare problemi per vendere. Se nessun servizio è utile o il materiale è già adeguato, id deve essere vuoto, ma reason deve spiegare specificamente perché non lo proponiamo per questo progetto. reason è SEMPRE obbligatoria, 20–320 caratteri: voce del team al plurale o impersonale, tu al destinatario; spiega quale intervento aiuterebbe e su quale passaggio, senza prezzi, sconti, promesse, link o giudizi sonori non verificati. evidence è SEMPRE obbligatoria: copia letterale di una frase breve dalla valutazione definitiva (almeno 20 caratteri), che sostiene la scelta oppure l’assenza di proposta. Non attribuire al mittente opere dichiarate altrui. Con solo testo non suggerire mastering o produzione; una revisione autoriale può essere pertinente alle ambiguità del testo, ma l’adattamento al canto richiede demo o base con riferimento melodico: se manca, invita a prepararlo prima dell’acquisto. Le citazioni originali e le alternative restano alla loro persona grammaticale; la nostra spiegazione resta al plurale. Catalogo ammesso:\n";
    foreach ( $catalog as $id => $item ) $text .= $id . ': ' . $item['name'] . '. ' . $item['scope'] . "\n";
    return $text;
}
function trb_demo_extract_service_selection( $raw, $has_audio ) {
    $parts = preg_split( '/\n[ \t]*TRB_SERVICE_JSON:\s*/', str_replace("\r\n", "\n", $raw), 2 );
    $review = trim( $parts[0] );
    $result = array( 'review' => $review, 'selection' => array(), 'status' => 'missing', 'diagnostic' => 'Decisione sui servizi mancante.' );
    if ( ! isset( $parts[1] ) ) return $result;
    $json = trim( $parts[1] );
    $json = preg_replace( '/^```(?:json)?\s*|\s*```$/u', '', $json );
    $data = json_decode( $json, true );
    $result['status'] = 'invalid';
    $result['diagnostic'] = 'Decisione sui servizi non valida o non sostenuta dalla valutazione.';
    if ( ! is_array( $data ) || ! is_string( $data['id'] ?? null ) || ! is_string( $data['reason'] ?? null ) || ! is_string( $data['evidence'] ?? null ) ) return $result;
    $id = $data['id']; $reason = trim( $data['reason'] ); $evidence = trim( $data['evidence'] );
    $catalog = trb_demo_service_catalog();
    if ( '' !== $id && ( ! isset( $catalog[$id] ) || ( ! $has_audio && $catalog[$id]['audio'] ) ) ) return $result;
    if ( strlen($reason) < 20 || strlen($reason) > 1280 || strlen($evidence) < 20 || strlen($evidence) > 1280 || false === strpos($review,$evidence) || preg_match('/https?:|www\.|[<>]|\b(?:sconto|coupon)\b|[%€]/iu',$reason) ) return $result;
    if ( function_exists('trb_demo_team_voice_valid') && ! trb_demo_team_voice_valid($reason) ) { $result['diagnostic'] = 'La proposta di supporto non usa la voce del team.'; return $result; }
    $result['selection'] = array( 'id' => $id, 'reason' => $reason, 'evidence' => $evidence );
    $result['status'] = '' === $id ? 'none' : 'selected';
    $result['diagnostic'] = '';
    return $result;
}
function trb_demo_service_recommendation_html( $selection ) {
    $catalog = trb_demo_service_catalog();
    $id = is_array( $selection ) ? ( $selection['id'] ?? '' ) : '';
    if ( empty( $selection['reason'] ) ) return '';
    if ( '' === $id ) return '<h2 style="margin:0 0 10px;font-size:18px;color:#20263b;">Come proseguire con questo progetto</h2><p style="line-height:1.7;margin:0 0 10px;">' . esc_html($selection['reason']) . '</p>';
    if ( ! isset( $catalog[ $id ] ) ) return '';
    $item = $catalog[ $id ];
    return '<h2 style="margin:0 0 10px;font-size:18px;color:#20263b;">Un supporto possibile per questo progetto</h2><p style="line-height:1.7;margin:0 0 10px;">' . esc_html( $selection['reason'] ) . '</p><p style="line-height:1.7;margin:0 0 10px;"><a style="color:#243e63;font-weight:bold;" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a></p><p style="font-size:13px;line-height:1.6;color:#66708a;">' . esc_html( $item['scope'] ) . ' È una possibilità: puoi anche lavorare autonomamente seguendo le priorità indicate sopra.</p>';
}
