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
    $text = "\nMETADATI SEPARATI OBBLIGATORI: dopo le sei sezioni scrivi una sola riga TRB_SERVICE_JSON: {\"id\":\"\",\"reason\":\"\",\"evidence\":\"\"}. Non sono una settima sezione della valutazione. La riga viene rimossa e validata dal sistema. Valuta il supporto professionale più pertinente rispetto alle priorità osservate: AL MASSIMO UN servizio. La possibilità di lavorare autonomamente non esclude un supporto facoltativo del team se risponde a un bisogno concreto. Non creare problemi per vendere. Se nessun servizio è utile o il materiale è già adeguato, id deve essere vuoto, ma reason deve spiegare specificamente perché non lo proponiamo per questo progetto. reason è SEMPRE obbligatoria, 20–320 caratteri: voce del team al plurale o impersonale, tu al destinatario; spiega quale intervento aiuterebbe e su quale passaggio, senza prezzi, sconti, promesse, link o giudizi sonori non verificati. evidence è SEMPRE obbligatoria: copia letterale di una frase breve dalla valutazione definitiva (almeno 20 caratteri), che sostiene la scelta oppure l’assenza di proposta. Non attribuire al mittente opere dichiarate altrui. Con solo testo non suggerire mastering o produzione; una revisione autoriale può essere pertinente alle ambiguità del testo, ma l’adattamento al canto richiede demo o base con riferimento melodico: non promettere un adattamento al canto dal solo testo. Il portale mostra separatamente quali materiali preparare prima dell’acquisto. Le citazioni originali e le alternative restano alla loro persona grammaticale; la nostra spiegazione resta al plurale. Catalogo ammesso:\n";
    $text .= "TONO CONSULENZIALE: scrivi una motivazione professionale e concreta, senza infantilizzare il linguaggio. Collega la priorità emersa nella valutazione al beneficio specifico del servizio. Usa brano o testo senza ripetere titolo, nomi di oggetti o citazioni già analizzate. Spiega su quali aspetti lavoreremmo (per esempio coerenza delle immagini e chiarezza dei passaggi narrativi), senza promettere risultati garantiti. Non riscrivere il catalogo e non ripetere materiali o istruzioni di acquisto. Non usare formule vaghe come possiamo aiutarti a rendere più chiare le frasi.\n";
    foreach ( $catalog as $id => $item ) $text .= $id . ': ' . $item['name'] . '. ' . $item['scope'] . "\n";
    return $text;
}
function trb_demo_extract_service_selection( $raw, $has_audio ) {
    $parts = preg_split( '/\n[ \t]*TRB_SERVICE_JSON:\s*/', str_replace("\r\n", "\n", $raw), 2 );
    $review = trim(preg_replace('/\n```(?:json)?\s*$/u', '', $parts[0]));
    $result = array( 'review' => $review, 'selection' => array(), 'status' => 'missing', 'diagnostic' => 'Decisione sui servizi mancante.', 'candidate' => '' );
    if ( ! isset( $parts[1] ) ) return $result;
    $json = trim( $parts[1] );
    $json = preg_replace( '/^```(?:json)?\s*|\s*```$/u', '', $json );
    $result['candidate'] = substr($json,0,5000);
    $data = json_decode( $json, true );
    $result['status'] = 'invalid';
    $result['diagnostic'] = 'JSON non valido o campi id/reason/evidence mancanti.';
    if ( ! is_array( $data ) || ! is_string( $data['id'] ?? null ) || ! is_string( $data['reason'] ?? null ) || ! is_string( $data['evidence'] ?? null ) ) return $result;
    $id = $data['id']; $reason = trim( $data['reason'] ); $evidence = trim( $data['evidence'] );
    $catalog = trb_demo_service_catalog();
    if ( '' !== $id && ( ! isset( $catalog[$id] ) || ( ! $has_audio && $catalog[$id]['audio'] ) ) ) { $result['diagnostic']='Servizio sconosciuto o incompatibile con i materiali.'; return $result; }
    if ( preg_match_all('/./us',$reason)<20 || preg_match_all('/./us',$reason)>320 || strlen($evidence)<20 || strlen($evidence)>1280 ) { $result['diagnostic']='Motivazione o evidenza fuori dai limiti richiesti.'; return $result; }
    if ( false===strpos($review,$evidence) ) { $result['diagnostic']='Il frammento di prova non compare letteralmente nella valutazione.'; return $result; }
    if ( preg_match('/https?:|www\.|[<>]|\b(?:sconto|coupon)\b|[%€]/iu',$reason) ) { $result['diagnostic']='La motivazione contiene condizioni economiche o contenuti non consentiti.'; return $result; }
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
    $paragraph = 'margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.65;color:#20263b;';
    if ( '' === $id ) return '<p style="' . $paragraph . '">' . esc_html($selection['reason']) . '</p>';
    if ( ! isset( $catalog[ $id ] ) ) return '';
    $item = $catalog[ $id ];
    $title = 'Il nostro consiglio professionale';
    $materials = array('lyrics_revision'=>'Per la revisione serve il testo completo; per l’adattamento alla melodia occorre anche una demo cantata.', 'stereo_mastering'=>'Materiale richiesto: mix stereo definitivo, prima del mastering.', 'production_essential'=>'Materiale richiesto: brano già scritto e demo di riferimento.');
    return '<h2 style="margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:18px;line-height:1.4;color:#20263b;">' . esc_html($title) . '</h2><p style="' . $paragraph . '">' . esc_html($selection['reason']) . '</p><p style="' . $paragraph . '">' . esc_html($materials[$id]) . '</p><p style="' . $paragraph . '"><a style="color:#243e63;text-decoration:underline;font-weight:600;font-size:16px;" href="' . esc_url($item['url']) . '">' . esc_html($item['name']) . '</a></p>';
}
