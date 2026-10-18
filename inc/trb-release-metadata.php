<?php
/** Declared distribution metadata. No migration or writes to existing releases. */
function trb_release_metadata_options() {
	static $options;
	if (null === $options) $options = json_decode(file_get_contents(__DIR__ . '/../assets/data/symphonic-metadata-options.json'), true);
	return $options;
}

function trb_release_metadata_code($value, $domain) {
	if (!is_string($value)) return '';
	$code = 'countries' === $domain ? strtoupper(trim($value)) : strtolower(trim($value));
	return isset(trb_release_metadata_options()[$domain][$code]) ? $code : '';
}

/** Explicit style wins; advisory remains a compatibility fallback for old receipts. */
function trb_release_audio_style($track) {
	$style = $track['audio_style'] ?? '';
	if (in_array($style, array('Vocal', 'Instrumental'), true)) return $style;
	return 'no_lyrics' === ($track['advisory'] ?? '') ? 'Instrumental' : 'Vocal';
}

/** Called only on a confirmed new submission or explicit completion of an incomplete receipt. */
function trb_release_metadata_errors($post) {
	$errors = array();
	if (!trb_release_metadata_code($post['trb_release_metadata_language'] ?? '', 'languages')) $errors[] = 'Seleziona la lingua dei titoli e dei metadati della release.';
	foreach ((array)($post['trb_tracks'] ?? array()) as $index => $track) {
		$prefix = 'Brano ' . ($index + 1) . ': ';
		if (!is_array($track)) { $errors[] = $prefix . 'dati non validi.'; continue; }
		if (!in_array($track['audio_style'] ?? '', array('Vocal', 'Instrumental'), true)) $errors[] = $prefix . 'indica se il brano contiene voce o è strumentale.';
		if ('Vocal' === trb_release_audio_style($track) && !trb_release_metadata_code($track['vocal_language'] ?? '', 'languages')) $errors[] = $prefix . 'seleziona la lingua del cantato.';
		$year = $track['recording_year'] ?? '';
		if (!is_scalar($year) || !preg_match('/^\d{4}$/', (string)$year) || (int)$year < 1900 || (int)$year > (int)wp_date('Y')) $errors[] = $prefix . 'inserisci l’anno effettivo della registrazione, non futuro.';
		if (!trb_release_metadata_code($track['recording_country'] ?? '', 'countries')) $errors[] = $prefix . 'seleziona il paese della registrazione.';
	}
	return $errors;
}

function trb_release_metadata_render_options($domain) {
	foreach (trb_release_metadata_options()[$domain] as $code => $entry) echo '<option value="' . esc_attr($code) . '">' . esc_html($entry['label_it']) . '</option>';
}

/** New values are written only while the artist explicitly submits this receipt. */
function trb_release_metadata_store($id, $post) {
	update_post_meta($id, '_trb_release_metadata_language', trb_release_metadata_code($post['trb_release_metadata_language'] ?? '', 'languages'));
	update_post_meta($id, '_trb_release_version', sanitize_text_field($post['trb_release_version'] ?? ''));
	update_post_meta($id, '_trb_release_metadata_schema', 'symphonic-2026-10');
}

/** Keep a recorded specific AI declaration on retry; the owner default cannot erase it. */
function trb_release_ai_declaration_conflicts($tracks, $previous) {
	foreach ($tracks as $track) {
		if (!is_array($track)) continue;
		$matches = array_filter($previous, static function($row) use ($track) { return is_array($row) && trim((string)($row['title'] ?? '')) === trim((string)($track['title'] ?? '')) && trim((string)($row['version'] ?? '')) === trim((string)($track['version'] ?? '')); });
		$current = array_filter($tracks, static function($row) use ($track) { return is_array($row) && trim((string)($row['title'] ?? '')) === trim((string)($track['title'] ?? '')) && trim((string)($row['version'] ?? '')) === trim((string)($track['version'] ?? '')); });
		if ((count($matches) > 1 || count($current) > 1) && array_filter($matches, static function($row) { return in_array($row['ai_usage'] ?? '', array('Some', 'All'), true); })) return array('Due brani della pratica hanno lo stesso titolo e la stessa versione, con dichiarazioni precedenti da conservare. Apri una segnalazione per verificare l’associazione: i dati ricevuti non sono stati modificati.');
	}
	return array();
}

function trb_release_preserve_ai_declarations($tracks, $previous) {
	foreach ($tracks as $index => &$track) {
		if (!is_array($track)) continue;
		$identity = static function($row) { return array(trim((string)($row['title'] ?? '')), trim((string)($row['version'] ?? ''))); };
		if ('' === $identity($track)[0]) continue;
		$matches = array_filter($previous, static function($row) use ($track, $identity) { return is_array($row) && $identity($row) === $identity($track); });
		// An unfinished receipt without assets can reorder or replace its tracks.
		// Copy a disclosure only when title + version identify one previous track.
		if (1 === count($matches)) {
			$source = reset($matches);
			if (!in_array($source['ai_usage'] ?? '', array('Some', 'All'), true)) continue;
			$track['ai_usage'] = $source['ai_usage'];
			$track['ai_usage_provenance'] = $source['ai_usage_provenance'] ?? 'artist_existing_declaration';
		}
	}
	unset($track);
	return $tracks;
}
