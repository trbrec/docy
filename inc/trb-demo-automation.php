<?php
/**
 * Automated demo evaluation pipeline.
 *
 * Secrets live in the private WordPress option trb_demo_automation_settings
 * and are never committed with the public theme.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function trb_demo_settings() {
	$settings = get_option( 'trb_demo_automation_settings', array() );
	return is_array( $settings ) ? $settings : array();
}

function trb_demo_local_path( $file ) {
	if ( empty( $file['path'] ) ) return '';
	$uploads = wp_upload_dir();
	$base = realpath( trailingslashit( $uploads['basedir'] ) . 'trb-demo-private' );
	$path = realpath( trailingslashit( $uploads['basedir'] ) . ltrim( $file['path'], '/' ) );
	return $base && $path && 0 === strpos( $path, $base . DIRECTORY_SEPARATOR ) && is_file( $path ) ? $path : '';
}

function trb_demo_extract_text( $file ) {
	$path = trb_demo_local_path( $file );
	if ( ! $path ) return '';
	$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	if ( 'txt' === $extension ) {
		$text = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	} elseif ( 'docx' === $extension && class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) return '';
		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();
		if ( false === $xml ) return '';
		$xml = str_replace( array( '</w:p>', '</w:tr>', '<w:tab/>' ), array( "\n", "\n", "\t" ), $xml );
		$text = wp_strip_all_tags( $xml );
	} else {
		return '';
	}
	$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	$text = preg_replace( "/[ \t]+/u", ' ', $text );
	$text = preg_replace( "/\n{3,}/u", "\n\n", $text );
	$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 30000 ) : substr( $text, 0, 30000 );
	return trim( $text );
}

function trb_demo_remote_url( $endpoint, $relative_path ) {
	$segments = array_filter( explode( '/', str_replace( '\\', '/', $relative_path ) ), 'strlen' );
	return untrailingslashit( $endpoint ) . '/' . implode( '/', array_map( 'rawurlencode', $segments ) );
}

function trb_demo_webdav_request( $method, $relative_path, $body = null, $headers = array() ) {
	$settings = trb_demo_settings();
	if ( empty( $settings['webdav_endpoint'] ) || empty( $settings['pcloud_user'] ) || empty( $settings['pcloud_pass'] ) ) return new WP_Error( 'missing_webdav_settings' );
	if ( 'PUT' === strtoupper( $method ) && function_exists( 'trb_resource_pcloud_guard' ) ) {
		$guard = trb_resource_pcloud_guard( is_string( $body ) ? strlen( $body ) : 0 );
		if ( is_wp_error( $guard ) ) return $guard;
	}
	$headers['Authorization'] = 'Basic ' . base64_encode( $settings['pcloud_user'] . ':' . $settings['pcloud_pass'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	$args = array( 'method' => $method, 'headers' => $headers, 'timeout' => 90, 'redirection' => 0 );
	if ( null !== $body ) $args['body'] = $body;
	return wp_remote_request( trb_demo_remote_url( $settings['webdav_endpoint'], $relative_path ), $args );
}

function trb_demo_ensure_remote_folder( $relative_path ) {
	$built = '';
	foreach ( array_filter( explode( '/', trim( $relative_path, '/' ) ), 'strlen' ) as $segment ) {
		$built .= '/' . $segment;
		$response = trb_demo_webdav_request( 'MKCOL', $built );
		if ( is_wp_error( $response ) ) return $response;
		$code = wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $code, array( 201, 301, 405 ), true ) ) return new WP_Error( 'webdav_mkdir_failed', 'WebDAV MKCOL ' . $code );
	}
	return true;
}

function trb_demo_upload_to_pcloud( $payload ) {
	$folder_name = sanitize_file_name( trim( implode( ' ', array_filter( array( $payload['first_name'], $payload['last_name'], $payload['artist_name'], $payload['title'] ) ) ) ) );
	if ( '' === $folder_name ) $folder_name = $payload['uuid'];
	$folder = '/Upload files - TRB rec/Audio/Demo files/' . $folder_name;
	$ready = trb_demo_ensure_remote_folder( $folder );
	if ( is_wp_error( $ready ) ) return $ready;
	$remote_files = array();
	foreach ( array( 'text_file', 'audio_file' ) as $key ) {
		if ( empty( $payload[ $key ] ) ) continue;
		$local = trb_demo_local_path( $payload[ $key ] );
		if ( ! $local ) return new WP_Error( 'missing_local_file' );
		$remote = $folder . '/' . sanitize_file_name( $payload[ $key ]['name'] );
		$response = trb_demo_webdav_request( 'PUT', $remote, file_get_contents( $local ), array( 'Content-Type' => $payload[ $key ]['type'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( is_wp_error( $response ) || ! in_array( wp_remote_retrieve_response_code( $response ), array( 200, 201, 204 ), true ) ) return new WP_Error( 'webdav_upload_failed' );
		$remote_files[ $key ] = $remote;
	}
	return array( 'folder' => $folder, 'files' => $remote_files );
}

function trb_demo_review_prompt( $payload, $has_audio, $has_text ) {
	$scope = $has_audio && $has_text ? 'audio e testo autoriale' : ( $has_audio ? 'solo audio' : 'solo testo autoriale' );
	$profile = strtoupper( str_replace( '_', '-', (string) $payload['profile'] ) );
	$prompt = "Agisci come A&R, producer e consulente editoriale senior di TRB rec - Music Publishing. Scrivi in italiano una valutazione utile a un artista che deve capire esattamente cosa conservare, cosa correggere e con quale priorità nel provino \"{$payload['title']}\". Materiale disponibile: {$scope}. Profilo contrattuale: {$profile}.\n\n";
	$prompt .= "REGOLE INDEROGABILI:\n";
	$prompt .= "- Ogni osservazione deve derivare da un elemento realmente udibile o leggibile. Non indovinare strumenti, tecniche, tonalità, effetti, intenzioni o dettagli di produzione; non usare formule come «presumibilmente», «sembra», «potrebbe esserci» per riempire lacune. Se un dato non è verificabile, omettilo.\n";
	$prompt .= "- Riduci al minimo introduzioni, parafrasi e complimenti generici. Massimo 750 parole. Niente voti numerici, promesse, diagnosi, giudizi sulla persona o tabelle.\n";
	$prompt .= "- Per ogni criticità indica: il problema concreto, l'effetto sull'ascolto o sulla comprensione e un intervento preciso da provare. Quando l'audio lo consente, localizza il passaggio con sezione o intervallo temporale; non inventare timestamp.\n";
	$prompt .= "- Distingui ciò che richiede una scelta artistica da ciò che costituisce una correzione tecnica. Ordina gli interventi per impatto e chiudi con non più di 4 priorità, formulate come azioni eseguibili.\n";
	if ( $has_audio ) $prompt .= "- Valuta soltanto quando chiaramente percepibili: efficacia di struttura e transizioni, sviluppo dell'arrangiamento, intonazione e timing, intelligibilità e dinamica vocale, bilanciamento, mascheramenti, eccessi o carenze timbriche, transienti, ambiente, distorsioni e tenuta complessiva del mix. Evita consigli vaghi come «dare più dinamismo»: specifica cosa cambiare, dove e perché.\n";
	if ( $has_text && $has_audio ) $prompt .= "- Valuta testo, metrica, accenti prosodici, immagini, coerenza narrativa, cantabilità, originalità e sviluppo. Cita solo brevi parole o frammenti necessari a identificare il punto e proponi correzioni mirate senza riscrivere integralmente il testo.\n";
	if ( $has_text && ! $has_audio ) {
		$prompt .= "- Poiché non è disponibile l'audio, non affermare che un verso sia o non sia cantabile, fuori tempo, accentato male rispetto alla musica o adatto a un genere: melodia, BPM, scansione e interpretazione non sono verificabili. Puoi svolgere soltanto una verifica metrica e prosodica preliminare sulla lingua scritta, segnalando dove versi molto diversi per lunghezza o accenti potrebbero richiedere adattamento musicale.\n";
		$prompt .= "- Evita una recensione scolastica divisa in paragrafi generici. Organizza l'analisi in questi blocchi interni: «Nucleo e sviluppo del racconto», «Passaggi riusciti», «Passaggi da rivedere», «Revisione linguistica e metrica preliminare». Non ripetere lo stesso rilievo in blocchi diversi.\n";
		$prompt .= "- Individua da 2 a 4 passaggi realmente riusciti e spiega in una frase perché funzionano. Individua da 3 a 6 passaggi deboli citando il frammento esatto; per ciascuno indica il problema e proponi una o due alternative brevi che conservino significato, tono e identità dell'autore. Non riscrivere l'intero testo e non trasformarlo in uno stile estraneo.\n";
		$prompt .= "- Separa refusi ed errori grammaticali oggettivi dalle scelte artistiche. Per immagini, metafore o parole ambigue, spiega le possibili letture e formula una domanda utile all'autore prima di suggerire una correzione definitiva. Valuta anche se titolo, apertura, ritornello e chiusura hanno una funzione chiara e se il racconto evolve davvero.\n";
		$prompt .= "- Se il materiale è poesia, prosa o appunto ancora lontano da una canzone, indica i passaggi concreti per trasformarlo in una struttura autoriale completa.\n";
	}
	if ( ! $has_text ) $prompt .= "Non formulare osservazioni sul testo autoriale. ";
	if ( ! $has_audio ) $prompt .= "Non formulare osservazioni su musica, arrangiamento, interpretazione, registrazione o missaggio. ";
	if ( in_array( $payload['profile'], array( 'ddb', 'ddb12', 'ddb_trb' ), true ) ) $prompt .= "Soltanto se emerge un bisogno concreto, inserisci nei prossimi passaggi una breve indicazione sui servizi TRB pertinenti (strumentale, intonazione, registrazione o missaggio), senza tono commerciale aggressivo. ";
	if ( $has_audio ) $prompt .= "Apri con il titolo esatto «1. Analisi compositiva e dell’arrangiamento». ";
	if ( $has_text ) $prompt .= "Inserisci poi il titolo numerato «" . ( $has_audio ? "2" : "1" ) . ". Analisi autoriale». ";
	if ( $has_audio ) $prompt .= "Inserisci poi il titolo numerato «" . ( $has_text ? "3" : "2" ) . ". Analisi interpretativa e tecnica». ";
	$prompt .= "Chiudi con il titolo numerato «" . ( $has_audio && $has_text ? "4" : ( $has_audio || $has_text ? "2" : "1" ) ) . ". Prossimi passaggi». Ogni titolo deve essere su una riga autonoma; sotto usa paragrafi brevi ed eventuali elenchi puntati.";
	return $prompt;
}

function trb_demo_model_rates( $model ) {
	$rates = array(
		'gpt-audio-mini' => array( 'text_input' => 0.60, 'text_output' => 2.40, 'audio_input' => 10.00, 'audio_output' => 20.00 ),
		'gpt-4.1-mini'   => array( 'text_input' => 0.40, 'text_output' => 1.60, 'audio_input' => 0.00, 'audio_output' => 0.00 ),
	);
	foreach ( $rates as $model_name => $model_rates ) {
		if ( $model === $model_name || 0 === strpos( $model, $model_name . '-' ) ) return $model_rates;
	}
	return array( 'text_input' => 0.00, 'text_output' => 0.00, 'audio_input' => 0.00, 'audio_output' => 0.00 );
}

function trb_demo_usage_and_cost( $model, $usage ) {
	$usage = is_array( $usage ) ? $usage : array();
	$prompt_tokens = isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0;
	$completion_tokens = isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : 0;
	$audio_input = isset( $usage['prompt_tokens_details']['audio_tokens'] ) ? (int) $usage['prompt_tokens_details']['audio_tokens'] : 0;
	$audio_output = isset( $usage['completion_tokens_details']['audio_tokens'] ) ? (int) $usage['completion_tokens_details']['audio_tokens'] : 0;
	$text_input = max( 0, $prompt_tokens - $audio_input );
	$text_output = max( 0, $completion_tokens - $audio_output );
	$rates = trb_demo_model_rates( $model );
	$cost = ( $text_input * $rates['text_input'] + $text_output * $rates['text_output'] + $audio_input * $rates['audio_input'] + $audio_output * $rates['audio_output'] ) / 1000000;
	return array(
		'model' => $model,
		'prompt_tokens' => $prompt_tokens,
		'completion_tokens' => $completion_tokens,
		'total_tokens' => isset( $usage['total_tokens'] ) ? (int) $usage['total_tokens'] : $prompt_tokens + $completion_tokens,
		'text_input_tokens' => $text_input,
		'text_output_tokens' => $text_output,
		'audio_input_tokens' => $audio_input,
		'audio_output_tokens' => $audio_output,
		'rates_per_million_usd' => $rates,
		'estimated_cost_usd' => round( $cost, 8 ),
		'raw_usage' => $usage,
		'recorded_at' => gmdate( 'c' ),
	);
}

function trb_demo_openai_review( $payload ) {
	$settings = trb_demo_settings();
	if ( empty( $settings['openai_key'] ) ) return new WP_Error( 'missing_openai_key' );
	$text = ! empty( $payload['text_file'] ) ? trb_demo_extract_text( $payload['text_file'] ) : '';
	$audio_path = ! empty( $payload['audio_file'] ) ? trb_demo_local_path( $payload['audio_file'] ) : '';
	if ( empty( $text ) && ! $audio_path ) return new WP_Error( 'empty_demo' );
	$prompt = trb_demo_review_prompt( $payload, (bool) $audio_path, (bool) $text );
	if ( $text ) $prompt .= "\n\nTESTO AUTORIALE:\n" . $text;
	$content = array( array( 'type' => 'text', 'text' => $prompt ) );
	$model = ! empty( $settings['text_model'] ) ? $settings['text_model'] : 'gpt-4.1-mini';
	if ( $audio_path ) {
		$model = ! empty( $settings['audio_model'] ) ? $settings['audio_model'] : 'gpt-audio-mini';
		$content[] = array( 'type' => 'input_audio', 'input_audio' => array( 'data' => base64_encode( file_get_contents( $audio_path ) ), 'format' => 'mp3' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	$body = array( 'model' => $model, 'modalities' => array( 'text' ), 'messages' => array( array( 'role' => 'user', 'content' => $content ) ), 'max_tokens' => 3200, 'temperature' => 0.25 );
	$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array( 'timeout' => 180, 'headers' => array( 'Authorization' => 'Bearer ' . $settings['openai_key'], 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
	if ( is_wp_error( $response ) ) return $response;
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( wp_remote_retrieve_response_code( $response ) >= 300 || empty( $data['choices'][0]['message']['content'] ) ) return new WP_Error( 'openai_failed', isset( $data['error']['message'] ) ? sanitize_text_field( $data['error']['message'] ) : 'OpenAI error' );
	if ( 'length' === ( $data['choices'][0]['finish_reason'] ?? '' ) ) return new WP_Error( 'openai_truncated', 'La valutazione OpenAI è stata troncata e non verrà inviata.' );
	return array(
		'review' => trim( wp_kses_post( $data['choices'][0]['message']['content'] ) ),
		'usage' => trb_demo_usage_and_cost( $model, isset( $data['usage'] ) ? $data['usage'] : array() ),
	);
}

function trb_demo_post_sheet_webhook( $url, $envelope ) {
	$response = wp_remote_post( $url, array(
		'timeout' => 30,
		'redirection' => 0,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body' => wp_json_encode( $envelope ),
	) );
	if ( is_wp_error( $response ) ) return $response;
	$code = wp_remote_retrieve_response_code( $response );
	if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
		$location = wp_remote_retrieve_header( $response, 'location' );
		$host = $location ? strtolower( (string) wp_parse_url( $location, PHP_URL_HOST ) ) : '';
		if ( ! $location || ( 'script.googleusercontent.com' !== $host && ! str_ends_with( $host, '.googleusercontent.com' ) ) ) {
			return new WP_Error( 'invalid_sheet_redirect', 'Redirect Google Sheets non valido.' );
		}
		$response = wp_remote_get( $location, array( 'timeout' => 30, 'redirection' => 2 ) );
	}
	return $response;
}

function trb_demo_sheet_row( $request_id, $payload, $remote ) {
	$row = array(
		'informazioni_cronologiche' => wp_date( 'd/m/Y H:i', strtotime( $payload['submitted_at'] ) ),
		'nome' => $payload['first_name'], 'cognome' => $payload['last_name'], 'nome_arte' => $payload['artist_name'],
		'email' => $payload['email'], 'titolo' => $payload['title'],
		'link_provino' => trb_demo_remote_url( trb_demo_settings()['webdav_endpoint'], $remote['folder'] ), 'request_id' => $request_id,
	);
	update_post_meta( $request_id, '_trb_demo_sheet_row', $row );
	$settings = trb_demo_settings();
	if ( empty( $settings['sheet_webhook_url'] ) ) return false;
	$row_json = wp_json_encode( $row );
	$envelope = array( 'payload_base64' => base64_encode( $row_json ), 'signature' => hash_hmac( 'sha256', $row_json, $settings['sheet_webhook_secret'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	$response = trb_demo_post_sheet_webhook( $settings['sheet_webhook_url'], $envelope );
	$response_data = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
	$ok = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 300 && ! empty( $response_data['success'] );
	update_post_meta( $request_id, '_trb_demo_sheet_synced', $ok ? time() : 0 );
	return $ok;
}

function trb_demo_is_test_payload( $payload ) {
	$email = is_array( $payload ) && ! empty( $payload['email'] ) ? strtolower( (string) $payload['email'] ) : '';
	return in_array( $email, array( 'spotify2@trbrec.com', 'spotify3@trbrec.com', 'spotify4@trbrec.com' ), true );
}

function trb_demo_process_request( $request_id ) {
	$payload = get_post_meta( $request_id, '_trb_demo_payload', true );
	if ( ! is_array( $payload ) || ! in_array( $payload['status'], array( 'queued', 'retry' ), true ) ) return;
	$delete_after = (int) get_post_meta( $request_id, '_trb_demo_delete_after', true );
	if ( $delete_after && ! wp_next_scheduled( 'trb_portal_cleanup_demo', array( $request_id ) ) ) wp_schedule_single_event( $delete_after, 'trb_portal_cleanup_demo', array( $request_id ) );
	$remote = trb_demo_upload_to_pcloud( $payload );
	if ( ! is_wp_error( $remote ) ) update_post_meta( $request_id, '_trb_demo_remote', $remote );
	$review_result = trb_demo_openai_review( $payload );
	if ( is_wp_error( $remote ) || is_wp_error( $review_result ) ) {
		$attempts = (int) get_post_meta( $request_id, '_trb_demo_attempts', true ) + 1;
		update_post_meta( $request_id, '_trb_demo_attempts', $attempts );
		update_post_meta( $request_id, '_trb_demo_last_error', is_wp_error( $remote ) ? $remote->get_error_message() : $review_result->get_error_message() );
		if ( $attempts < 3 ) { $payload['status'] = 'retry'; update_post_meta( $request_id, '_trb_demo_payload', $payload ); wp_schedule_single_event( time() + ( trb_demo_is_test_payload( $payload ) ? MINUTE_IN_SECONDS : HOUR_IN_SECONDS ), 'trb_portal_process_demo', array( $request_id ) ); }
		else { $payload['status'] = 'manual_review'; update_post_meta( $request_id, '_trb_demo_payload', $payload ); wp_mail( 'info@trbrec.com', 'Provino da verificare manualmente: ' . $payload['title'], 'La procedura automatica non è riuscita dopo tre tentativi. Richiesta #' . $request_id ); }
		return;
	}
	$review = $review_result['review'];
	$usage = $review_result['usage'];
	update_post_meta( $request_id, '_trb_demo_review', $review );
	update_post_meta( $request_id, '_trb_demo_openai_usage', $usage );
	update_post_meta( $request_id, '_trb_demo_cost_usd', (float) $usage['estimated_cost_usd'] );
	trb_demo_sheet_row( $request_id, $payload, $remote );
	$payload['status'] = 'ready';
	update_post_meta( $request_id, '_trb_demo_payload', $payload );
	$send_at = max( time() + 30, (int) get_post_meta( $request_id, '_trb_demo_earliest_delivery', true ) );
	wp_schedule_single_event( $send_at, 'trb_portal_send_demo_review', array( $request_id ) );
}
add_action( 'trb_portal_process_demo', 'trb_demo_process_request' );

function trb_demo_review_html( $review ) {
	$safe = esc_html( trim( (string) $review ) );
	$safe = preg_replace( '/^(\\d+\\. [^\\n]+)$/mu', '<h2 style="margin:30px 0 12px;color:#101936;font-size:21px;line-height:1.3;">$1</h2>', $safe );
	$safe = preg_replace( '/^[\\-•]\\s+(.+)$/mu', '<div style="margin:7px 0 7px 18px;">• $1</div>', $safe );
	return wpautop( $safe );
}

function trb_demo_send_review( $request_id ) {
	$payload = get_post_meta( $request_id, '_trb_demo_payload', true );
	$review = get_post_meta( $request_id, '_trb_demo_review', true );
	if ( ! is_array( $payload ) || 'ready' !== $payload['status'] || ! $review || empty( $payload['email'] ) ) return;
	$name = $payload['first_name'] ?: ( $payload['artist_name'] ?: 'Artista' );
	$artist_name = ! empty( $payload['artist_name'] ) ? $payload['artist_name'] : trim( $payload['first_name'] . ' ' . $payload['last_name'] );
	$affiliation = function_exists( 'trb_portal_profile_affiliation' ) ? trb_portal_profile_affiliation( $payload['profile'] ) : ( 'trb' === $payload['profile'] ? 'TRB rec - Music Publishing' : 'Digital Distribution Bundle' );
	$review_html = trb_demo_review_html( $review );
	$service_note = '';
	if ( in_array( $payload['profile'], array( 'ddb', 'ddb12', 'ddb_trb' ), true ) ) {
		$service_note = '<div style="margin-top:28px;padding:18px 20px;background:#f4f5ff;border-left:4px solid #514cff;border-radius:8px;"><strong>Approfondimenti e interventi tecnici</strong><p style="margin:8px 0 0;">Quando la valutazione evidenzia una necessità concreta, puoi consultare i servizi riservati su <a style="color:#4038e8;" href="https://store.trbrec.com/">store.trbrec.com</a>. Le eventuali condizioni dedicate vengono applicate attraverso il codice comunicato da TRB rec.</p></div>';
	}
	$body = '<!doctype html><html><body style="margin:0;background:#f3f5f9;font-family:Arial,Helvetica,sans-serif;color:#20263b;">'
		. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f5f9;padding:24px 12px;"><tr><td align="center">'
		. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 35px rgba(20,28,60,.10);">'
		. '<tr><td style="padding:28px 34px;background:linear-gradient(135deg,#091b3c,#303e9f);color:#ffffff;"><div style="font-size:12px;letter-spacing:1.5px;font-weight:700;">TRB REC - MUSIC PUBLISHING</div><h1 style="margin:10px 0 0;font-size:28px;line-height:1.2;">Valutazione del provino</h1></td></tr>'
		. '<tr><td style="padding:34px;"><p style="margin:0 0 16px;font-size:17px;">Ciao <strong>' . esc_html( $name ) . '</strong>,</p>'
		. '<p style="margin:0 0 24px;line-height:1.65;">abbiamo completato la valutazione del provino che ci hai inviato. Di seguito trovi gli elementi più rilevanti e gli interventi consigliati in ordine di priorità.</p>'
		. '<div style="padding:18px 20px;background:#f7f8fc;border:1px solid #e4e7f0;border-radius:10px;"><div style="font-size:12px;color:#66708a;text-transform:uppercase;letter-spacing:1px;">Provino analizzato</div><strong style="display:block;margin-top:5px;font-size:20px;color:#101936;">' . esc_html( $payload['title'] ) . '</strong><span style="display:block;margin-top:9px;color:#66708a;"><strong style="color:#39415a;">Artista:</strong> ' . esc_html( $artist_name ) . '</span><span style="display:block;margin-top:5px;color:#66708a;"><strong style="color:#39415a;">Etichetta:</strong> ' . esc_html( $affiliation ) . '</span></div>'
		. '<div style="margin-top:28px;line-height:1.7;font-size:16px;">' . $review_html . '</div>' . $service_note
		. '<div style="margin-top:34px;padding-top:22px;border-top:1px solid #e4e7f0;"><p style="margin:0 0 6px;">Un saluto,</p><strong style="font-size:16px;color:#101936;">TRB rec - Music Publishing</strong><p style="margin:7px 0 0;color:#4c5670;line-height:1.55;">A&amp;R Management<br><a href="https://artist.trbrec.com/" style="color:#4038e8;text-decoration:none;">artist.trbrec.com</a></p></div>'
		. '<div style="margin-top:24px;padding-top:18px;border-top:1px solid #e4e7f0;color:#7a8296;font-size:10px;line-height:1.45;"><strong>Nota di riservatezza:</strong> Questo documento è destinato esclusivamente al destinatario. Tutte le informazioni contenute, compresi eventuali allegati, sono confidenziali e riservate ai sensi del D.Lgs. 196/2003 e del Regolamento europeo 679/2016 (GDPR). Ne è vietato qualsiasi utilizzo, divulgazione o distribuzione non autorizzati. Se avete ricevuto questo messaggio per errore, vi preghiamo di contattare immediatamente il mittente e cancellare l’e-mail.<br><br><strong>Confidentiality notice:</strong> This e-mail, including any attachments, is intended solely for the named recipient and may contain confidential and privileged information pursuant to Italian Legislative Decree 196/2003 and European Regulation 679/2016 (GDPR). Any unauthorized review, use, disclosure or distribution is prohibited. If you are not the intended recipient, please notify the sender by reply e-mail and delete all copies of the original message.</div>'
		. '</td></tr></table></td></tr></table></body></html>';
	$subject = 'Valutazione del provino “' . $payload['title'] . '” | TRB rec';
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: TRB rec - Music Publishing <info@trbrec.com>',
		'Reply-To: TRB rec - Music Publishing <info@trbrec.com>',
	);
	$sent = wp_mail( $payload['email'], $subject, $body, $headers );
	if ( $sent ) { $payload['status'] = 'sent'; $payload['sent_at'] = gmdate( 'c' ); update_post_meta( $request_id, '_trb_demo_payload', $payload ); }
	else wp_schedule_single_event( time() + ( trb_demo_is_test_payload( $payload ) ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS ), 'trb_portal_send_demo_review', array( $request_id ) );
}
add_action( 'trb_portal_send_demo_review', 'trb_demo_send_review' );

function trb_demo_cleanup_request( $request_id ) {
	$payload = get_post_meta( $request_id, '_trb_demo_payload', true );
	if ( is_array( $payload ) ) foreach ( array( 'text_file', 'audio_file' ) as $key ) { $path = ! empty( $payload[ $key ] ) ? trb_demo_local_path( $payload[ $key ] ) : ''; if ( $path ) wp_delete_file( $path ); }
	$remote = get_post_meta( $request_id, '_trb_demo_remote', true );
	if ( ! empty( $remote['folder'] ) ) trb_demo_webdav_request( 'DELETE', $remote['folder'] );
	delete_post_meta( $request_id, '_trb_demo_review' );
	delete_post_meta( $request_id, '_trb_demo_remote' );
	update_post_meta( $request_id, '_trb_demo_cleaned_at', time() );
}
add_action( 'trb_portal_cleanup_demo', 'trb_demo_cleanup_request' );

/** Non-sensitive readiness endpoint used by deployment monitoring. */
function trb_demo_health_payload() {
	$settings = trb_demo_settings();
	$payload = array(
		'ready' => ! empty( $settings['webdav_endpoint'] ) && ! empty( $settings['pcloud_user'] ) && ! empty( $settings['pcloud_pass'] ) && ! empty( $settings['openai_key'] ),
	);
	if ( current_user_can( 'manage_options' ) ) {
		$payload['pcloud_configured'] = ! empty( $settings['webdav_endpoint'] ) && ! empty( $settings['pcloud_user'] ) && ! empty( $settings['pcloud_pass'] );
		$payload['openai_configured'] = ! empty( $settings['openai_key'] );
		$payload['spreadsheet_configured'] = ! empty( $settings['spreadsheet_id'] ) && ! empty( $settings['spreadsheet_tab'] ) && ! empty( $settings['sheet_webhook_url'] ) && ! empty( $settings['sheet_webhook_secret'] );
		$payload['processor_registered'] = has_action( 'trb_portal_process_demo', 'trb_demo_process_request' ) > 0;
		$payload['cleanup_registered'] = has_action( 'trb_portal_cleanup_demo', 'trb_demo_cleanup_request' ) > 0;
	}
	return $payload;
}

function trb_demo_register_health_route() {
	register_rest_route( 'trb/v1', '/demo-health', array(
		'methods' => 'GET',
		'permission_callback' => '__return_true',
		'callback' => function() {
			return rest_ensure_response( trb_demo_health_payload() );
		},
	) );
}
add_action( 'rest_api_init', 'trb_demo_register_health_route' );

function trb_demo_ajax_health() {
	wp_send_json( trb_demo_health_payload() );
}
add_action( 'wp_ajax_nopriv_trb_demo_health', 'trb_demo_ajax_health' );
add_action( 'wp_ajax_trb_demo_health', 'trb_demo_ajax_health' );

/** Private settings screen: secrets are stored in WordPress, never in Git. */
function trb_demo_register_settings_page() {
	add_management_page(
		'Automazione valutazione demo',
		'Automazione demo',
		'manage_options',
		'trb-demo-automation',
		'trb_demo_render_settings_page'
	);
}
add_action( 'admin_menu', 'trb_demo_register_settings_page' );

function trb_demo_cost_report() {
	$request_ids = get_posts( array(
		'post_type' => 'trb_request',
		'post_status' => array( 'publish', 'private', 'draft', 'pending', 'trash' ),
		'posts_per_page' => -1,
		'fields' => 'ids',
		'orderby' => 'date',
		'order' => 'DESC',
		'meta_query' => array( array( 'key' => '_trb_demo_openai_usage', 'compare' => 'EXISTS' ) ),
	) );
	$rows = array();
	$total_cost = 0.0;
	foreach ( $request_ids as $request_id ) {
		$usage = get_post_meta( $request_id, '_trb_demo_openai_usage', true );
		if ( ! is_array( $usage ) ) continue;
		$cost = isset( $usage['estimated_cost_usd'] ) ? (float) $usage['estimated_cost_usd'] : (float) get_post_meta( $request_id, '_trb_demo_cost_usd', true );
		$total_cost += $cost;
		if ( count( $rows ) < 20 ) {
			$payload = get_post_meta( $request_id, '_trb_demo_payload', true );
			$rows[] = array(
				'id' => $request_id,
				'title' => get_the_title( $request_id ),
				'date' => get_post_time( 'd/m/Y H:i', false, $request_id ),
				'status' => is_array( $payload ) && ! empty( $payload['status'] ) ? $payload['status'] : get_post_status( $request_id ),
				'usage' => $usage,
				'cost' => $cost,
			);
		}
	}
	$count = count( $request_ids );
	return array( 'count' => $count, 'total_cost' => $total_cost, 'average_cost' => $count ? $total_cost / $count : 0, 'rows' => $rows );
}

function trb_demo_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Non sei autorizzato ad accedere a questa pagina.', 'docy' ) );
	}

	$settings = trb_demo_settings();
	$test_results = array();
	if ( isset( $_POST['trb_demo_save_settings'] ) ) {
		check_admin_referer( 'trb_demo_save_settings' );
		$fields = array( 'webdav_endpoint', 'pcloud_user', 'pcloud_pass', 'openai_key', 'text_model', 'audio_model', 'spreadsheet_id', 'spreadsheet_tab', 'sheet_webhook_url', 'sheet_webhook_secret' );
		$secret_fields = array( 'pcloud_pass', 'openai_key', 'sheet_webhook_secret' );
		$updated = array();
		foreach ( $fields as $field ) {
			$value = isset( $_POST[ $field ] ) ? trim( wp_unslash( $_POST[ $field ] ) ) : '';
			if ( '' === $value && in_array( $field, $secret_fields, true ) && isset( $settings[ $field ] ) ) {
				$updated[ $field ] = $settings[ $field ];
			} else {
				$updated[ $field ] = in_array( $field, array( 'webdav_endpoint', 'sheet_webhook_url' ), true ) ? esc_url_raw( $value ) : sanitize_text_field( $value );
			}
		}
		update_option( 'trb_demo_automation_settings', $updated, false );
		$settings = $updated;
		echo '<div class="notice notice-success is-dismissible"><p>Configurazione salvata.</p></div>';
	}
	if ( isset( $_POST['trb_demo_test_settings'] ) ) {
		check_admin_referer( 'trb_demo_save_settings' );
		$pcloud = trb_demo_webdav_request( 'PROPFIND', '/' , null, array( 'Depth' => '0' ) );
		$test_results['pCloud'] = ! is_wp_error( $pcloud ) && in_array( wp_remote_retrieve_response_code( $pcloud ), array( 200, 207, 301 ), true );
		if ( empty( $settings['openai_key'] ) ) {
			$test_results['OpenAI'] = false;
		} else {
			$model = ! empty( $settings['text_model'] ) ? $settings['text_model'] : 'gpt-4.1-mini';
			$openai = wp_remote_get( 'https://api.openai.com/v1/models/' . rawurlencode( $model ), array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $settings['openai_key'] ) ) );
			$test_results['OpenAI'] = ! is_wp_error( $openai ) && 200 === wp_remote_retrieve_response_code( $openai );
		}
		if ( empty( $settings['sheet_webhook_url'] ) || empty( $settings['sheet_webhook_secret'] ) ) {
			$test_results['Google Sheets'] = false;
		} else {
			$test_row = array(
				'informazioni_cronologiche' => wp_date( 'd/m/Y H:i' ),
				'nome' => 'TEST', 'cognome' => 'CONFIGURAZIONE', 'nome_arte' => 'TRB AUTOMATION',
				'email' => 'info@trbrec.com', 'titolo' => 'TEST COLLEGAMENTO - eliminabile',
				'link_provino' => '', 'request_id' => 'test-' . gmdate( 'YmdHis' ),
			);
			$test_json = wp_json_encode( $test_row );
			$test_envelope = array( 'payload_base64' => base64_encode( $test_json ), 'signature' => hash_hmac( 'sha256', $test_json, $settings['sheet_webhook_secret'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$sheet = trb_demo_post_sheet_webhook( $settings['sheet_webhook_url'], $test_envelope );
			$sheet_body = is_wp_error( $sheet ) ? '' : wp_remote_retrieve_body( $sheet );
			$sheet_data = $sheet_body ? json_decode( $sheet_body, true ) : array();
			$sheet_ok = ! is_wp_error( $sheet ) && ! empty( $sheet_data['success'] );
			$sheet_detail = $sheet_ok ? '' : ( is_wp_error( $sheet ) ? $sheet->get_error_message() : ( $sheet_data['error'] ?? 'risposta non valida' ) );
			$test_results[ 'Google Sheets' . ( $sheet_detail ? ' — ' . sanitize_text_field( $sheet_detail ) : '' ) ] = $sheet_ok;
		}
	}

	$defaults = array(
		'webdav_endpoint' => 'https://webdav.pcloud.com',
		'text_model' => 'gpt-4.1-mini',
		'audio_model' => 'gpt-audio-mini',
		'spreadsheet_id' => '15-A6nUDO47zxLrMJ-8xQs4AcvnjHwwQIgpEeLwS8pa4',
		'spreadsheet_tab' => '2026 NEW',
	);
	$settings = wp_parse_args( $settings, $defaults );
	$cost_report = trb_demo_cost_report();
	$fields = array(
		'webdav_endpoint' => array( 'Endpoint WebDAV pCloud', 'url' ),
		'pcloud_user' => array( 'Utente pCloud', 'text' ),
		'pcloud_pass' => array( 'Password pCloud', 'password' ),
		'openai_key' => array( 'Chiave API OpenAI', 'password' ),
		'text_model' => array( 'Modello testo OpenAI', 'text' ),
		'audio_model' => array( 'Modello audio OpenAI', 'text' ),
		'spreadsheet_id' => array( 'ID Google Spreadsheet', 'text' ),
		'spreadsheet_tab' => array( 'Scheda Google Spreadsheet', 'text' ),
		'sheet_webhook_url' => array( 'Webhook Google Sheets', 'url' ),
		'sheet_webhook_secret' => array( 'Segreto webhook', 'password' ),
	);
	?>
	<div class="wrap">
		<h1>Automazione valutazione demo</h1>
		<p>Configurazione privata del trasferimento file, dell'analisi e della registrazione dei provini.</p>
		<?php if ( $test_results ) : ?>
			<div class="notice <?php echo ! in_array( false, $test_results, true ) ? 'notice-success' : 'notice-error'; ?>"><p>
			<?php foreach ( $test_results as $service => $ok ) : ?>
				<strong><?php echo esc_html( $service ); ?>:</strong> <?php echo $ok ? 'collegamento riuscito' : 'collegamento non riuscito'; ?>&nbsp;&nbsp;
			<?php endforeach; ?>
			</p></div>
		<?php endif; ?>
		<h2>Consumi e costi OpenAI</h2>
		<p>I costi sono stimati applicando ai token restituiti dall'API il listino associato al modello. Il dato di fatturazione definitivo resta quello dell'account OpenAI.</p>
		<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0 24px;">
			<div style="min-width:180px;padding:16px 20px;background:#fff;border:1px solid #dcdcde;border-radius:8px;"><div style="color:#646970;">Valutazioni conteggiate</div><strong style="display:block;font-size:24px;margin-top:6px;"><?php echo number_format_i18n( $cost_report['count'] ); ?></strong></div>
			<div style="min-width:180px;padding:16px 20px;background:#fff;border:1px solid #dcdcde;border-radius:8px;"><div style="color:#646970;">Costo totale stimato</div><strong style="display:block;font-size:24px;margin-top:6px;">$<?php echo esc_html( number_format( $cost_report['total_cost'], 4, '.', '' ) ); ?></strong></div>
			<div style="min-width:180px;padding:16px 20px;background:#fff;border:1px solid #dcdcde;border-radius:8px;"><div style="color:#646970;">Costo medio per provino</div><strong style="display:block;font-size:24px;margin-top:6px;">$<?php echo esc_html( number_format( $cost_report['average_cost'], 4, '.', '' ) ); ?></strong></div>
		</div>
		<?php if ( $cost_report['rows'] ) : ?>
			<table class="widefat striped" style="margin-bottom:28px;">
				<thead><tr><th>Data</th><th>Provino</th><th>Modello</th><th>Testo in/out</th><th>Audio in/out</th><th>Token totali</th><th>Costo stimato</th><th>Stato</th></tr></thead>
				<tbody>
				<?php foreach ( $cost_report['rows'] as $row ) : $usage = $row['usage']; ?>
					<tr>
						<td><?php echo esc_html( $row['date'] ); ?></td>
						<td><a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"><?php echo esc_html( $row['title'] ?: '#' . $row['id'] ); ?></a></td>
						<td><code><?php echo esc_html( $usage['model'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( $usage['text_input_tokens'] ?? 0 ) . ' / ' . number_format_i18n( $usage['text_output_tokens'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $usage['audio_input_tokens'] ?? 0 ) . ' / ' . number_format_i18n( $usage['audio_output_tokens'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $usage['total_tokens'] ?? 0 ) ); ?></td>
						<td><strong>$<?php echo esc_html( number_format( $row['cost'], 5, '.', '' ) ); ?></strong></td>
						<td><?php echo esc_html( $row['status'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="notice notice-info inline"><p>Il monitoraggio partirà dalla prossima valutazione elaborata dopo questo aggiornamento; le valutazioni precedenti non contengono il dettaglio token.</p></div>
		<?php endif; ?>
		<hr style="margin:28px 0;">
		<h2>Configurazione collegamenti</h2>
		<form method="post">
			<?php wp_nonce_field( 'trb_demo_save_settings' ); ?>
			<table class="form-table" role="presentation"><tbody>
			<?php foreach ( $fields as $name => $field ) : ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
					<td><input class="regular-text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $field[1] ); ?>" value="<?php echo 'password' === $field[1] ? '' : esc_attr( $settings[ $name ] ?? '' ); ?>" placeholder="<?php echo 'password' === $field[1] && ! empty( $settings[ $name ] ) ? 'Configurato — lascia vuoto per mantenerlo' : ''; ?>" autocomplete="new-password"></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php submit_button( 'Salva configurazione', 'primary', 'trb_demo_save_settings' ); ?>
			<?php submit_button( 'Testa tutti i collegamenti', 'secondary', 'trb_demo_test_settings', false ); ?>
		</form>
	</div>
	<?php
}
