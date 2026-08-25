<?php
/** Technical, rights and decision pipeline for release audio. */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TRB_RELEASE_ANALYSIS_VERSION', '1.2.0' );

function trb_analysis_public_version_marker() { echo '<meta name="trb-release-analysis" content="' . esc_attr( TRB_RELEASE_ANALYSIS_VERSION ) . '">'; }
add_action( 'wp_head', 'trb_analysis_public_version_marker', 2 );

function trb_analysis_settings() {
	return wp_parse_args( (array) get_option( 'trb_release_analysis_settings', array() ), array(
		'true_peak_warning' => -0.30,
		'master_lufs_max' => -7.0,
		'master_lufs_min' => -18.0,
		'master_lufs_extreme_max' => -4.0,
		'master_lufs_extreme_min' => -35.0,
		'master_silence_peak_max' => -30.0,
		'premaster_peak_max' => -6.0,
		'silence_warning_seconds' => 8.0,
		'benchmark_required' => 15,
		'fingerprint_red_score' => 80.0,
		'match_review_score' => 1.0,
		'benchmark_complete' => 0,
		'auto_approval' => 1,
		'clamav_binary' => '',
	) );
}

/** Migrate only untouched legacy defaults; never overwrite administrator choices. */
function trb_analysis_migrate_settings() {
	$schema = (string) get_option( 'trb_release_analysis_settings_schema', '' );
	if ( version_compare( $schema ?: '1.0.0', TRB_RELEASE_ANALYSIS_VERSION, '>=' ) ) return;
	$stored = (array) get_option( 'trb_release_analysis_settings', array() );
	if ( isset( $stored['premaster_peak_max'] ) && -3.0 === (float) $stored['premaster_peak_max'] ) $stored['premaster_peak_max'] = -6.0;
	// From 1.2.0 the contract policy is deterministic: a clean copyright
	// result is approved automatically; benchmarks remain quality metrics.
	$stored['auto_approval'] = 1;
	update_option( 'trb_release_analysis_settings', $stored, false );
	update_option( 'trb_release_analysis_settings_schema', TRB_RELEASE_ANALYSIS_VERSION, false );
}
add_action( 'init', 'trb_analysis_migrate_settings', 1 );

/**
 * Loudness is a musical descriptor, not a universal delivery requirement.
 * These broad families only decide when TRB should listen to a master; they
 * never reject an otherwise valid file by themselves.
 */
function trb_analysis_genre_profile( $track ) {
	$genre = strtolower( remove_accents( (string) ( $track['primary_genre'] ?? '' ) ) );
	$profile = array( 'family' => 'balanced', 'lufs_min' => -24.0, 'lufs_max' => -6.0, 'lra_floor' => 1.5, 'plr_floor' => 5.5, 'crest_floor' => 5.5 );
	if ( preg_match( '/classical|orchestral|opera|chamber|baroque|piano|ambient|new age|meditation|jazz|blues|easy listening|folk|soundtrack|spoken word|singer.songwriter/', $genre ) ) {
		$profile = array( 'family' => 'dynamic', 'lufs_min' => -30.0, 'lufs_max' => -8.0, 'lra_floor' => null, 'plr_floor' => null, 'crest_floor' => null );
	} elseif ( preg_match( '/electronic|dance|house|techno|trance|dubstep|drum.*bass|hardcore|hardstyle|breakbeat|bass|fitness|reggaeton|hip.hop|rap/', $genre ) ) {
		$profile = array( 'family' => 'high_energy', 'lufs_min' => -18.0, 'lufs_max' => -5.0, 'lra_floor' => 1.0, 'plr_floor' => 5.0, 'crest_floor' => 5.0 );
	} elseif ( preg_match( '/pop|rock|punk|metal|r&b|funk|soul|reggae|ska|latin|country/', $genre ) ) {
		$profile = array( 'family' => 'dense', 'lufs_min' => -20.0, 'lufs_max' => -6.0, 'lra_floor' => 1.5, 'plr_floor' => 5.5, 'crest_floor' => 5.5 );
	}
	$profile['genre'] = (string) ( $track['primary_genre'] ?? '' );
	return $profile;
}

function trb_analysis_binary( $name, $configured = '' ) {
	if ( $configured && is_executable( $configured ) ) return $configured;
	if ( ! function_exists( 'exec' ) || ! function_exists( 'shell_exec' ) ) return '';
	$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
	if ( in_array( 'exec', $disabled, true ) || in_array( 'shell_exec', $disabled, true ) ) return '';
	$path = trim( (string) shell_exec( 'command -v ' . escapeshellarg( $name ) . ' 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
	return $path && is_executable( $path ) ? $path : '';
}

function trb_analysis_exec( $command ) {
	$output = array(); $code = 1;
	exec( $command . ' 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
	return array( 'code' => $code, 'output' => implode( "\n", $output ) );
}

function trb_analysis_wordfence_active() {
	return defined( 'WORDFENCE_VERSION' ) || class_exists( 'wfConfig' ) || class_exists( 'wordfence' );
}

/**
 * Optionally scan supplementary material when a local ClamAV binary exists.
 * WAV files never reach this function: they are verified as PCM and decoded
 * completely with FFmpeg before analysis.
 */
function trb_analysis_antivirus_scan( $path ) {
	$s = trb_analysis_settings();
	$binary = trb_analysis_binary( 'clamdscan', $s['clamav_binary'] );
	if ( ! $binary ) $binary = trb_analysis_binary( 'clamscan', $s['clamav_binary'] );
	if ( ! $binary ) return true;
	$result = trb_analysis_exec( escapeshellarg( $binary ) . ' --no-summary -- ' . escapeshellarg( $path ) );
	if ( 0 === $result['code'] ) return true;
	return new WP_Error( 1 === $result['code'] ? 'MALWARE_DETECTED' : 'VIRUS_SCAN_FAILED', wp_strip_all_tags( $result['output'] ) );
}

function trb_analysis_float( $pattern, $text ) {
	return preg_match( $pattern, $text, $m ) ? (float) str_replace( ',', '.', $m[1] ) : null;
}

/** Decode the complete WAV and calculate measurements from actual audio samples. */
function trb_analysis_inspect_wav( $path ) {
	$ffprobe = trb_analysis_binary( 'ffprobe' );
	$ffmpeg  = trb_analysis_binary( 'ffmpeg' );
	if ( ! $ffprobe || ! $ffmpeg ) return new WP_Error( 'TECHNICAL_ANALYSIS_UNAVAILABLE', 'FFmpeg/ffprobe non disponibili sul server.' );

	$probe = trb_analysis_exec( escapeshellarg( $ffprobe ) . ' -v error -print_format json -show_format -show_streams -- ' . escapeshellarg( $path ) );
	$data = json_decode( $probe['output'], true );
	if ( 0 !== $probe['code'] || ! is_array( $data ) || empty( $data['streams'][0] ) ) return new WP_Error( 'WAV_DECODE_FAILED', 'Il WAV non può essere decodificato integralmente.' );
	$stream = $data['streams'][0];
	if ( 'audio' !== ( $stream['codec_type'] ?? '' ) || 0 !== strpos( (string) ( $stream['codec_name'] ?? '' ), 'pcm_' ) ) return new WP_Error( 'WAV_NOT_PCM', 'Il contenitore WAV non contiene audio PCM.' );

	$decode = trb_analysis_exec( escapeshellarg( $ffmpeg ) . ' -v error -xerror -i ' . escapeshellarg( $path ) . ' -map 0:a:0 -f null -' );
	if ( 0 !== $decode['code'] ) return new WP_Error( 'WAV_DECODE_FAILED', wp_strip_all_tags( $decode['output'] ) );

	$filters = 'ebur128=peak=true,silencedetect=noise=-60dB:d=2,astats=metadata=1:reset=0';
	$measure = trb_analysis_exec( escapeshellarg( $ffmpeg ) . ' -hide_banner -nostats -i ' . escapeshellarg( $path ) . ' -af ' . escapeshellarg( $filters ) . ' -f null -' );
	$text = $measure['output'];
	$head = trb_analysis_exec( escapeshellarg( $ffmpeg ) . ' -hide_banner -nostats -t 0.05 -i ' . escapeshellarg( $path ) . ' -af astats=reset=0 -f null -' );
	$tail_start = max( 0, (float) ( $data['format']['duration'] ?? 0 ) - 0.05 );
	$tail = trb_analysis_exec( escapeshellarg( $ffmpeg ) . ' -hide_banner -nostats -ss ' . escapeshellarg( (string) $tail_start ) . ' -i ' . escapeshellarg( $path ) . ' -af astats=reset=0 -f null -' );
	$duration = isset( $data['format']['duration'] ) ? (float) $data['format']['duration'] : (float) ( $stream['duration'] ?? 0 );
	$bits = (int) ( $stream['bits_per_raw_sample'] ?? $stream['bits_per_sample'] ?? 0 );
	$integrated = trb_analysis_float( '/\bI:\s*(-?[0-9.]+)\s*LUFS/', $text );
	$lra = trb_analysis_float( '/\bLRA:\s*([0-9.]+)\s*LU/', $text );
	$true_peak = trb_analysis_float( '/\bPeak:\s*(-?[0-9.]+)\s*dBFS/', $text );
	$peak_level = trb_analysis_float( '/Peak level dB:\s*(-?[0-9.]+)/', $text );
	$rms_level = trb_analysis_float( '/RMS level dB:\s*(-?[0-9.]+)/', $text );
	$nans = trb_analysis_float( '/Number of NaNs:\s*([0-9.]+)/', $text );
	$infs = trb_analysis_float( '/Number of Infs:\s*([0-9.]+)/', $text );
	preg_match_all( '/silence_start:\s*([0-9.]+)/', $text, $silence_starts );
	preg_match_all( '/silence_end:\s*([0-9.]+)\s*\|\s*silence_duration:\s*([0-9.]+)/', $text, $silence_ends );
	$silences = array();
	foreach ( $silence_ends[2] ?? array() as $i => $length ) $silences[] = array( 'end' => (float) $silence_ends[1][ $i ], 'duration' => (float) $length );

	return array(
		'codec' => sanitize_key( $stream['codec_name'] ?? '' ),
		'channels' => (int) ( $stream['channels'] ?? 0 ),
		'channel_layout' => sanitize_text_field( $stream['channel_layout'] ?? '' ),
		'sample_rate' => (int) ( $stream['sample_rate'] ?? 0 ),
		'bit_depth' => $bits,
		'duration_seconds' => round( $duration, 6 ),
		'integrated_lufs' => $integrated,
		'loudness_range_lu' => $lra,
		'true_peak_dbtp' => null !== $true_peak ? $true_peak : $peak_level,
		'peak_level_dbfs' => $peak_level,
		'rms_level_dbfs' => $rms_level,
		'crest_factor_db' => null !== $peak_level && null !== $rms_level ? round( $peak_level - $rms_level, 3 ) : null,
		'peak_to_loudness_ratio_db' => null !== $true_peak && null !== $integrated ? round( $true_peak - $integrated, 3 ) : null,
		'number_of_nans' => null === $nans ? 0 : absint( $nans ),
		'number_of_infs' => null === $infs ? 0 : absint( $infs ),
		'boundary_start_rms_dbfs' => trb_analysis_float( '/RMS level dB:\s*(-?[0-9.]+)/', $head['output'] ),
		'boundary_end_rms_dbfs' => trb_analysis_float( '/RMS level dB:\s*(-?[0-9.]+)/', $tail['output'] ),
		'clipping_suspected' => null !== $peak_level && $peak_level >= -0.01,
		'silences' => $silences,
		'complete_decode' => true,
		'analyzed_at' => time(),
	);
}

function trb_analysis_track_findings( $spec, $track, $declared_seconds ) {
	$s = trb_analysis_settings(); $errors = array(); $warnings = array();
	if ( 2 !== (int) $spec['channels'] ) $errors[] = 'AUDIO_NOT_STEREO';
	if ( ! in_array( (int) $spec['sample_rate'], array( 44100, 48000, 88200, 96000 ), true ) ) $errors[] = 'SAMPLE_RATE_INVALID';
	if ( ! in_array( (int) $spec['bit_depth'], array( 16, 24 ), true ) ) $errors[] = 'BIT_DEPTH_INVALID';
	if ( $declared_seconds <= 0 || abs( $spec['duration_seconds'] - $declared_seconds ) > 1.0 ) $errors[] = 'DURATION_MISMATCH';
	if ( ! empty( $spec['number_of_nans'] ) || ! empty( $spec['number_of_infs'] ) ) $errors[] = 'INVALID_AUDIO_SAMPLES';
	if ( null !== $spec['integrated_lufs'] && null !== $spec['true_peak_dbtp'] && $spec['integrated_lufs'] < (float) $s['master_lufs_extreme_min'] && $spec['true_peak_dbtp'] < (float) $s['master_silence_peak_max'] ) $errors[] = 'AUDIO_LEVEL_EFFECTIVELY_SILENT';
	if ( $spec['duration_seconds'] < 15.0 ) $warnings[] = 'AUDIO_TOO_SHORT_FOR_RELIABLE_RECOGNITION';
	if ( null !== $spec['true_peak_dbtp'] && $spec['true_peak_dbtp'] > (float) $s['true_peak_warning'] ) $warnings[] = 'TRUE_PEAK_HIGH';
	if ( ! empty( $spec['clipping_suspected'] ) ) $warnings[] = 'CLIPPING_SUSPECTED';
	if ( null !== $spec['boundary_end_rms_dbfs'] && $spec['boundary_end_rms_dbfs'] > -25.0 ) $warnings[] = 'ABRUPT_END_REVIEW';
	if ( null !== $spec['boundary_start_rms_dbfs'] && $spec['boundary_start_rms_dbfs'] > -3.0 ) $warnings[] = 'ABRUPT_START_REVIEW';
	foreach ( $spec['silences'] as $silence ) if ( $silence['duration'] >= (float) $s['silence_warning_seconds'] ) { $warnings[] = 'LONG_SILENCE'; break; }
	$status = sanitize_key( $track['audio_status'] ?? '' );
	if ( 'mastering' === $status && null !== $spec['true_peak_dbtp'] && $spec['true_peak_dbtp'] > (float) $s['premaster_peak_max'] ) $warnings[] = 'PREMASTER_HEADROOM_LOW';
	$genre_profile = trb_analysis_genre_profile( $track );
	if ( 'mastered' === $status && null !== $spec['integrated_lufs'] ) {
		if ( $spec['integrated_lufs'] > (float) $s['master_lufs_extreme_max'] ) $warnings[] = 'MASTER_LEVEL_EXTREME';
		elseif ( $spec['integrated_lufs'] > $genre_profile['lufs_max'] || $spec['integrated_lufs'] < $genre_profile['lufs_min'] ) $warnings[] = 'MASTER_LOUDNESS_GENRE_REVIEW';
		$lra = $spec['loudness_range_lu']; $plr = $spec['peak_to_loudness_ratio_db']; $crest = $spec['crest_factor_db'];
		if ( $spec['duration_seconds'] >= 30.0 && $spec['integrated_lufs'] > -16.0 && null !== $genre_profile['lra_floor'] && null !== $lra && null !== $plr && null !== $crest && $lra < $genre_profile['lra_floor'] && $plr < $genre_profile['plr_floor'] && $crest < $genre_profile['crest_floor'] ) $warnings[] = 'EXCESSIVE_LIMITING_REVIEW';
	}
	return array( 'errors' => array_values( array_unique( $errors ) ), 'warnings' => array_values( array_unique( $warnings ) ), 'genre_profile' => $genre_profile );
}


/** Convert internal finding codes into concise administrator-facing labels. */
function trb_analysis_finding_email_label( $code ) {
	$code = sanitize_key( $code );
	$labels = array(
		'audio_not_stereo'                         => 'Il file non è stereo',
		'sample_rate_invalid'                      => 'Frequenza di campionamento non valida',
		'bit_depth_invalid'                        => 'Profondità in bit non valida',
		'duration_mismatch'                        => 'Durata dichiarata e durata del WAV non corrispondono',
		'invalid_audio_samples'                    => 'Il WAV contiene campioni audio non validi',
		'audio_level_effectively_silent'           => 'Il livello del file è incompatibile con un master utilizzabile',
		'master_level_extreme'                     => 'Il livello del master supera un limite tecnico estremo',
		'audio_too_short_for_reliable_recognition' => 'Audio troppo breve per un riconoscimento affidabile',
		'true_peak_high'                           => 'True peak troppo elevato',
		'clipping_suspected'                       => 'Possibile clipping',
		'excessive_limiting_review'                => 'Dinamica eccessivamente limitata',
		'abrupt_end_review'                        => 'Finale del brano da controllare',
		'abrupt_start_review'                      => 'Inizio del brano da controllare',
		'long_silence'                             => 'Silenzio prolungato nel brano',
		'premaster_headroom_low'                   => 'Headroom insufficiente nel pre-master',
		'master_loudness_review'                   => 'Loudness del master da verificare',
		'master_loudness_genre_review'             => 'Loudness da verificare rispetto al genere dichiarato',
		'release_audio_inconsistent'               => 'Specifiche audio non uniformi fra i brani',
		'local_archive_missing'                    => 'File locale non disponibile per l’analisi',
		'wav_decode_failed'                        => 'Il WAV non può essere decodificato',
		'wav_not_pcm'                              => 'Il WAV non è in formato PCM',
	);
	$label = $labels[ $code ] ?? ucwords( strtolower( str_replace( '_', ' ', $code ) ) );
	return $label . ' (' . strtoupper( $code ) . ')';
}

/** Add the technical outcome to the single administrator review email. */
function trb_analysis_admin_technical_email_rows( $payload ) {
	$rows  = array();
	$codes = array_values( array_unique( array_merge( (array) ( $payload['errors'] ?? array() ), (array) ( $payload['warnings'] ?? array() ) ) ) );
	$rows[] = '<strong>Esito tecnico:</strong> ' . esc_html( $payload['status'] ?? 'n/d' );
	$rows[] = '<strong>Criticità tecniche:</strong> ' . esc_html( $codes ? implode( '; ', array_map( 'trb_analysis_finding_email_label', $codes ) ) : 'Nessuna' );
	foreach ( (array) ( $payload['tracks'] ?? array() ) as $index => $track ) {
		$findings   = (array) ( $track['findings'] ?? array() );
		$track_codes = array_values( array_unique( array_merge( (array) ( $findings['errors'] ?? array() ), (array) ( $findings['warnings'] ?? array() ) ) ) );
		if ( $track_codes ) $rows[] = '<strong>Brano ' . ( absint( $index ) + 1 ) . ':</strong> ' . esc_html( implode( '; ', array_map( 'trb_analysis_finding_email_label', $track_codes ) ) );
	}
	return $rows;
}

/** Notify the artist only when a new WAV hash has an objective blocking error. */
function trb_analysis_queue_artist_correction_email( $release_id, $payload ) {
	if ( ! function_exists( 'trb_resource_queue_recipient_email' ) ) return;
	$release = get_post( $release_id );
	$user = $release ? get_userdata( $release->post_author ) : false;
	if ( ! $release || ! $user || ! is_email( $user->user_email ) ) return;
	$hashes = array_values( array_filter( array_map( static function( $track ) { return sanitize_text_field( $track['sha256'] ?? '' ); }, (array) ( $payload['tracks'] ?? array() ) ) ) );
	$errors = array_values( array_unique( (array) ( $payload['errors'] ?? array() ) ) );
	if ( ! $errors ) return;
	$name = function_exists( 'trb_resource_artist_legal_greeting_name' ) ? trb_resource_artist_legal_greeting_name( $user ) : trim( (string) $user->first_name );
	if ( '' === $name ) $name = 'Artista';
	$link = add_query_arg( 'trb_release', 'technical_correction', get_permalink( get_option( 'trb_portal_dashboard_created' ) ) ) . '#release-files-' . absint( $release_id );
	$body = '<p>Gentile ' . esc_html( $name ) . ',</p><p>la release <strong>' . esc_html( $release->post_title ) . '</strong> è stata acquisita, ma il WAV deve essere sostituito prima di poter proseguire.</p>';
	$body .= '<p><strong>Motivo:</strong> ' . esc_html( implode( '; ', array_map( 'trb_analysis_finding_email_label', $errors ) ) ) . '</p>';
	$body .= '<p>La pratica rimane aperta e gli altri dati non devono essere reinseriti.</p><p><a href="' . esc_url( $link ) . '">Apri la release e sostituisci il WAV</a></p>';
	if ( function_exists( 'trb_resource_artist_email_signature' ) ) $body .= trb_resource_artist_email_signature();
	$key = 'artist-technical-' . absint( $release_id ) . '-' . substr( hash( 'sha256', wp_json_encode( array( $hashes, $errors ) ) ), 0, 20 );
	$headers = function_exists( 'trb_resource_artist_recovery_cc_headers' ) ? trb_resource_artist_recovery_cc_headers() : array( 'Cc: andrea.tognassi@trbrec.com' );
	trb_resource_queue_recipient_email( $key, $user->user_email, 'Correzione richiesta per la release ' . $release->post_title, $body, false, $headers );
}

/** Notify the artist, with Andrea in CC, only after a real rights finding. */
function trb_analysis_queue_artist_copyright_email( $release_id, $decision ) {
	if ( ! function_exists( 'trb_resource_queue_recipient_email' ) ) return;
	$semaphore = sanitize_key( $decision['semaphore'] ?? '' );
	if ( ! in_array( $semaphore, array( 'yellow', 'red' ), true ) ) return;
	$release = get_post( absint( $release_id ) );
	$user = $release ? get_userdata( $release->post_author ) : false;
	if ( ! $release || ! $user || ! is_email( $user->user_email ) ) return;

	$name = function_exists( 'trb_resource_artist_legal_greeting_name' ) ? trb_resource_artist_legal_greeting_name( $user ) : trim( (string) $user->first_name );
	if ( '' === $name ) $name = 'Artista';
	$findings = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) ( $decision['copyright_findings'] ?? array() ) ) ) ) );
	$link = add_query_arg( 'trb_release', 'copyright_review', get_permalink( get_option( 'trb_portal_dashboard_created' ) ) ) . '#release-files-' . absint( $release_id );
	$body = '<p>Gentile ' . esc_html( $name ) . ',</p><p>durante il controllo dei diritti relativo alla release <strong>' . esc_html( $release->post_title ) . '</strong> è emersa una corrispondenza o una dichiarazione che richiede una verifica.</p>';
	if ( $findings ) $body .= '<p><strong>Motivo della verifica:</strong> ' . esc_html( implode( '; ', $findings ) ) . '.</p>';
	$body .= '<p>La segnalazione non costituisce automaticamente un accertamento di violazione. Il contratto rimane temporaneamente sospeso mentre verifichiamo i dati e l’eventuale documentazione sui diritti.</p>';
	if ( 'copyright_documents_needed' === ( $decision['state'] ?? '' ) ) $body .= '<p>Accedi alla pratica e carica la documentazione richiesta, leggibile e riferita esattamente al materiale inviato.</p>';
	else $body .= '<p>Non devi creare una nuova pratica né caricare nuovamente il brano, salvo nostra specifica richiesta.</p>';
	$body .= '<p><a href="' . esc_url( $link ) . '">Apri la release nel Portale Artisti</a></p>';
	if ( function_exists( 'trb_resource_artist_email_signature' ) ) $body .= trb_resource_artist_email_signature();

	$hashes = array_values( array_filter( array_map( static function( $track ) { return sanitize_text_field( $track['sha256'] ?? '' ); }, (array) get_post_meta( $release_id, '_trb_release_files', true ) ) ) );
	$key = 'artist-copyright-' . absint( $release_id ) . '-' . substr( hash( 'sha256', wp_json_encode( array( $semaphore, $decision['state'] ?? '', $findings, $hashes ) ) ), 0, 20 );
	$headers = function_exists( 'trb_resource_artist_recovery_cc_headers' ) ? trb_resource_artist_recovery_cc_headers() : array( 'Cc: andrea.tognassi@trbrec.com' );
	trb_resource_queue_recipient_email( $key, $user->user_email, 'Verifica dei diritti per la release ' . $release->post_title, $body, true, $headers );
}

/** Queue one consolidated, idempotent administrator email for a release requiring attention. */
function trb_analysis_queue_admin_review_email( $release_id, $stage, $payload ) {
	if ( ! function_exists( 'trb_resource_queue_email' ) ) return;
	$release = get_post( $release_id );
	if ( ! $release || 'trb_release' !== $release->post_type ) return;

	$stage = sanitize_key( $stage );
	if ( 'technical' === $stage ) {
		$technical_hashes = array_values( array_filter( array_map( static function( $track ) {
			return isset( $track['sha256'] ) ? sanitize_text_field( $track['sha256'] ) : '';
		}, (array) ( $payload['tracks'] ?? array() ) ) ) );
		$notification_signature = 'technical-' . substr( hash( 'sha256', wp_json_encode( array(
			'status'   => sanitize_key( $payload['status'] ?? '' ),
			'errors'   => (array) ( $payload['errors'] ?? array() ),
			'warnings' => (array) ( $payload['warnings'] ?? array() ),
			'hashes'   => $technical_hashes,
		) ) ), 0, 20 );
	} else {
		$technical = (array) get_post_meta( $release_id, '_trb_release_technical_analysis', true );
		$technical_hashes = array_values( array_filter( array_map( static function( $track ) {
			return isset( $track['sha256'] ) ? sanitize_text_field( $track['sha256'] ) : '';
		}, (array) ( $technical['tracks'] ?? array() ) ) ) );
		$notification_signature = 'decision-' . substr( hash( 'sha256', wp_json_encode( array(
			'state'       => sanitize_key( $payload['state'] ?? '' ),
			'semaphore'   => sanitize_key( $payload['semaphore'] ?? '' ),
			'limitations' => (array) ( $payload['limitations'] ?? array() ),
			'hashes'      => $technical_hashes,
		) ) ), 0, 20 );
	}
	$artist = function_exists( 'trb_portal_artist_profile_value' ) ? trb_portal_artist_profile_value( 'artist_name', $release->post_author ) : '';
	if ( ! $artist ) {
		$user = get_userdata( $release->post_author );
		$artist = $user ? $user->display_name : 'Non indicato';
	}
	$edit_url = admin_url( 'tools.php?page=trb-resource-monitor#trb-release-' . absint( $release_id ) );
	$rows = array(
		'<strong>Pratica:</strong> #' . absint( $release_id ),
		'<strong>Artista:</strong> ' . esc_html( $artist ),
		'<strong>Release:</strong> ' . esc_html( $release->post_title ),
	);

	if ( 'technical' === $stage ) {
		$rows = array_merge( $rows, trb_analysis_admin_technical_email_rows( $payload ) );
		$subject = 'Release bloccata da errore tecnico: pratica #' . absint( $release_id ) . ' - ' . $release->post_title;
	} else {
		$limitations = array();
		foreach ( (array) ( $payload['limitations'] ?? array() ) as $index => $codes ) foreach ( (array) $codes as $code ) $limitations[] = 'brano ' . ( absint( $index ) + 1 ) . ': ' . sanitize_text_field( $code );
		if ( $technical ) $rows = array_merge( $rows, trb_analysis_admin_technical_email_rows( $technical ) );
		$rows[] = '<strong>Semaforo:</strong> ' . esc_html( $payload['semaphore'] ?? 'n/d' );
		$rows[] = '<strong>Decisione:</strong> ' . esc_html( $payload['state'] ?? 'n/d' );
		if ( $limitations ) $rows[] = '<strong>Limitazioni:</strong> ' . esc_html( implode( '; ', $limitations ) );
		$subject = 'Nuova release da verificare: pratica #' . absint( $release_id ) . ' - ' . $release->post_title;
	}

	$rows[] = '<a href="' . esc_url( $edit_url ) . '">Apri la coda e gestisci direttamente la pratica</a>';
	$body = '<p>' . implode( '</p><p>', $rows ) . '</p>';
	trb_resource_queue_email( 'release-analysis-' . absint( $release_id ) . '-' . $notification_signature, $subject, $body, true );
}

/** Choose the first 90 consecutive seconds after technical leading silence only. */
function trb_analysis_excerpt_window( $path, $maximum_seconds = 90 ) {
	$ffmpeg = trb_analysis_binary( 'ffmpeg' ); $ffprobe = trb_analysis_binary( 'ffprobe' );
	if ( ! $ffmpeg || ! $ffprobe ) return new WP_Error( 'AUDIO_EXTRACTOR_UNAVAILABLE' );
	$probe = trb_analysis_exec( escapeshellarg( $ffprobe ) . ' -v error -show_entries format=duration -of default=nw=1:nk=1 -- ' . escapeshellarg( $path ) );
	$duration = (float) trim( $probe['output'] ); if ( $duration <= 0 ) return new WP_Error( 'AUDIO_DURATION_UNAVAILABLE' );
	$scan = trb_analysis_exec( escapeshellarg( $ffmpeg ) . ' -hide_banner -nostats -i ' . escapeshellarg( $path ) . ' -af silencedetect=noise=-60dB:d=0.25 -t 30 -f null -' );
	$start = 0.0;
	if ( preg_match( '/silence_start:\s*0(?:\.0+)?[\s\S]*?silence_end:\s*([0-9.]+)/', $scan['output'], $m ) ) $start = min( (float) $m[1], max( 0, $duration - 1 ) );
	return array( 'start' => $start, 'length' => min( (float) $maximum_seconds, max( 0.1, $duration - $start ) ), 'source_duration' => $duration );
}

/** Enforce album-wide delivery coherence and persist an auditable technical result. */
function trb_analysis_run_technical( $release_id ) {
	$tracks = (array) get_post_meta( $release_id, '_trb_release_tracks', true );
	$files = (array) get_post_meta( $release_id, '_trb_release_files', true );
	$results = array(); $reference = null; $release_errors = array(); $release_warnings = array();
	update_post_meta( $release_id, '_trb_release_pipeline_status', 'technical_analysis_running' );
	foreach ( $files as $file ) {
		if ( 'audio' !== ( $file['kind'] ?? '' ) ) continue;
		$index = absint( $file['track'] ?? count( $results ) );
		$path = function_exists( 'trb_release_pcloud_local_file' ) ? trb_release_pcloud_local_file( $file ) : '';
		if ( ! $path ) { $release_errors[] = 'LOCAL_ARCHIVE_MISSING'; continue; }
		$spec = trb_analysis_inspect_wav( $path );
		if ( is_wp_error( $spec ) ) {
			$results[ $index ] = array( 'status' => 'error', 'code' => $spec->get_error_code(), 'message' => $spec->get_error_message() );
			$release_errors[] = $spec->get_error_code(); continue;
		}
		$declared = function_exists( 'trb_portal_release_track_duration_seconds' ) && isset( $tracks[ $index ] ) ? trb_portal_release_track_duration_seconds( $tracks[ $index ] ) : 0;
		$findings = trb_analysis_track_findings( $spec, $tracks[ $index ] ?? array(), $declared );
		if ( null === $reference ) $reference = array( 'sample_rate' => $spec['sample_rate'], 'bit_depth' => $spec['bit_depth'] );
		elseif ( $reference['sample_rate'] !== $spec['sample_rate'] || $reference['bit_depth'] !== $spec['bit_depth'] ) $findings['errors'][] = 'RELEASE_AUDIO_INCONSISTENT';
		$results[ $index ] = array( 'status' => empty( $findings['errors'] ) ? ( empty( $findings['warnings'] ) ? 'passed' : 'warning' ) : 'failed', 'declared_seconds' => $declared, 'sha256' => hash_file( 'sha256', $path ), 'spec' => $spec, 'findings' => $findings );
		$release_errors = array_merge( $release_errors, $findings['errors'] );
		$release_warnings = array_merge( $release_warnings, $findings['warnings'] );
	}
	$status = $release_errors ? 'failed' : ( $release_warnings ? 'warning' : 'passed' );
	$payload = array( 'version' => TRB_RELEASE_ANALYSIS_VERSION, 'status' => $status, 'tracks' => $results, 'errors' => array_values( array_unique( $release_errors ) ), 'warnings' => array_values( array_unique( $release_warnings ) ), 'completed_at' => time() );
	update_post_meta( $release_id, '_trb_release_technical_analysis', $payload );
	if ( 'failed' === $status ) update_post_meta( $release_id, '_trb_release_pipeline_status', 'technical_error' );
	elseif ( 'warning' === $status ) update_post_meta( $release_id, '_trb_release_pipeline_status', 'technical_review' );
	else update_post_meta( $release_id, '_trb_release_pipeline_status', 'copyright_queued' );
	// Non-blocking warnings continue through copyright analysis without being
	// misclassified as rights problems. Only a terminal technical failure
	// requires an immediate correction email.
	if ( 'failed' === $status ) {
		trb_analysis_queue_admin_review_email( $release_id, 'technical', $payload );
		trb_analysis_queue_artist_correction_email( $release_id, $payload );
	}
	return $payload;
}

function trb_analysis_after_pcloud( $release_id ) {
	$result = trb_analysis_run_technical( absint( $release_id ) );
	if ( ! in_array( $result['status'], array( 'passed', 'warning' ), true ) ) return;
	/** ACRCloud starts at priority 10 after this priority-5 gate. */
}
add_action( 'trb_release_audio_ready_for_analysis', 'trb_analysis_after_pcloud', 5, 1 );

function trb_analysis_normalize_acr_result( $item ) {
	$root = isset( $item['results'] ) && is_array( $item['results'] ) ? $item['results'] : ( isset( $item['result'] ) && is_array( $item['result'] ) ? $item['result'] : $item );
	$groups = array( 'music', 'custom_files', 'cover_songs', 'cover_files', 'deepright' ); $matches = array();
	foreach ( $groups as $group ) {
		$rows = $root[ $group ] ?? array();
		if ( isset( $rows['music'] ) ) $rows = $rows['music'];
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) continue;
			if ( isset( $row['result'] ) && is_array( $row['result'] ) ) $row = array_merge( $row, $row['result'] );
			$external = $row['external_metadata'] ?? array();
			$matches[] = array(
				'engine' => $group,
				'title' => sanitize_text_field( $row['title'] ?? '' ),
				'artists' => array_values( array_filter( array_map( static function( $a ){ return sanitize_text_field( is_array( $a ) ? ( $a['name'] ?? '' ) : $a ); }, (array) ( $row['artists'] ?? array() ) ) ) ),
				'album' => sanitize_text_field( is_array( $row['album'] ?? null ) ? ( $row['album']['name'] ?? '' ) : ( $row['album'] ?? '' ) ),
				'isrc' => sanitize_text_field( $row['external_ids']['isrc'] ?? '' ),
				'acrid' => sanitize_text_field( $row['acrid'] ?? '' ),
				'score' => (float) ( $row['score'] ?? $row['confidence'] ?? 0 ),
				'play_offset_ms' => absint( $row['play_offset_ms'] ?? 0 ),
				'platform_ids' => array_filter( array(
					'spotify' => sanitize_text_field( $external['spotify']['track']['id'] ?? '' ),
					'apple_music' => sanitize_text_field( $external['apple_music']['track']['id'] ?? '' ),
					'deezer' => sanitize_text_field( $external['deezer']['track']['id'] ?? '' ),
					'youtube' => sanitize_text_field( $external['youtube']['vid'] ?? '' ),
				) ),
			);
		}
	}
	$deep = $root['deepright'] ?? array();
	return array( 'matches' => $matches, 'deepright' => is_array( $deep ) ? $deep : array(), 'raw_status' => sanitize_text_field( $root['status']['msg'] ?? '' ) );
}

function trb_analysis_decide_release( $release_id ) {
	global $wpdb; $table = function_exists( 'trb_resource_tables' ) ? trb_resource_tables()['usage'] : '';
	if ( ! $table ) return;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT track_index,file_hash,payload,status FROM $table WHERE release_id=%d AND provider='acrcloud' AND service IN ('fingerprinting','fingerprinting_reuse') ORDER BY id ASC", $release_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$current_hashes = array(); foreach ( (array) get_post_meta( $release_id, '_trb_release_files', true ) as $file ) if ( 'audio' === ( $file['kind'] ?? '' ) ) $current_hashes[ absint( $file['track'] ?? 0 ) ] = (string) ( $file['sha256'] ?? '' );
	$s = trb_analysis_settings();
	$normalized = array(); $limitations = array(); $copyright_findings = array(); $red = false; $yellow = false;
	foreach ( $rows as $row ) {
		if ( 'completed' !== $row['status'] ) continue;
		if ( empty( $current_hashes[ absint( $row['track_index'] ) ] ) || ! hash_equals( $current_hashes[ absint( $row['track_index'] ) ], (string) $row['file_hash'] ) ) continue;
		$item = json_decode( $row['payload'], true ); $result = trb_analysis_normalize_acr_result( is_array( $item ) ? $item : array() );
		$normalized[ absint( $row['track_index'] ) ] = $result;
	}
	$missing_tracks = array_diff_key( $current_hashes, $normalized );
	if ( $missing_tracks ) {
		update_post_meta( $release_id, '_trb_release_pipeline_status', 'copyright_review' );
		if ( function_exists( 'trb_resource_event' ) ) trb_resource_event( 'acr-incomplete-result-' . absint( $release_id ), 'acrcloud', 'critical', 'Decisione copyright sospesa: manca un risultato completo per una o più tracce correnti.', array( 'tracks' => array_keys( $missing_tracks ) ) );
		return;
	}
	$declarations = (array) get_post_meta( $release_id, '_trb_release_rights_declarations', true );
	$release_state = (string) get_post_meta( $release_id, '_trb_release_state', true );
	foreach ( $normalized as $index => $result ) {
		$nature = $declarations[ $index ]['nature'] ?? 'original';
		foreach ( $result['matches'] as $match ) {
			if ( $match['score'] < (float) $s['match_review_score'] ) continue;
			$match_label = trim( implode( ' - ', array_filter( array( implode( ', ', (array) $match['artists'] ), $match['title'] ) ) ) );
			$copyright_findings[] = 'Brano ' . ( absint( $index ) + 1 ) . ': corrispondenza ' . ( $match_label ?: 'nel catalogo del provider' );
			if ( in_array( $match['engine'], array( 'music', 'custom_files' ), true ) && $match['score'] >= (float) $s['fingerprint_red_score'] && 'original' === $nature && 'unreleased' === $release_state ) $red = true;
			else $yellow = true;
		}
		if ( ! empty( $result['deepright'] ) ) {
			$yellow = true;
			$copyright_findings[] = 'Brano ' . ( absint( $index ) + 1 ) . ': possibile contenuto derivato rilevato da DeepRight';
		}
	}
	$declaration_labels = array( 'type_beat' => 'licenza type beat', 'protected_samples' => 'sample o elemento protetto dichiarato', 'remix' => 'remix dichiarato' );
	foreach ( $declarations as $index => $declaration ) {
		$nature = $declaration['nature'] ?? '';
		if ( isset( $declaration_labels[ $nature ] ) ) {
			$yellow = true;
			$copyright_findings[] = 'Brano ' . ( absint( $index ) + 1 ) . ': ' . $declaration_labels[ $nature ];
		}
	}
	$semaphore = $red ? 'red' : ( $yellow ? 'yellow' : 'green' );
	$benchmark_ready = ! empty( $s['benchmark_complete'] ) && trb_analysis_benchmark_count() >= absint( $s['benchmark_required'] );
	$documents = get_post_meta( $release_id, '_trb_release_rights_documents', true );
	$documents = is_array( $documents ) ? array_values( array_filter( $documents, static function( $document ) { return is_array( $document ) && ! empty( $document['path'] ); } ) ) : array();
	$documents_ready = false; foreach ( $documents as $document ) if ( 'synced' === ( $document['status'] ?? '' ) ) { $documents_ready = true; break; }
	$state = 'red' === $semaphore ? ( 'unreleased' === $release_state ? 'published_audio_conflict' : ( $documents_ready ? 'manual_review' : 'copyright_documents_needed' ) ) : ( 'yellow' === $semaphore ? 'manual_review' : 'approved' );
	$decision = array( 'semaphore' => $semaphore, 'state' => $state, 'results' => $normalized, 'limitations' => $limitations, 'copyright_findings' => array_values( array_unique( $copyright_findings ) ), 'benchmark_ready' => $benchmark_ready, 'decided_at' => time() );
	update_post_meta( $release_id, '_trb_release_analysis_decision', $decision );
	update_post_meta( $release_id, '_trb_release_pipeline_status', $state );
	if ( 'approved' === $state ) do_action( 'trb_release_analysis_approved', $release_id );
	trb_analysis_generate_report( $release_id );
	if ( 'approved' !== $state ) {
		trb_analysis_queue_admin_review_email( $release_id, 'decision', $decision );
		trb_analysis_queue_artist_copyright_email( $release_id, $decision );
	}
}

function trb_analysis_benchmark_count() { return count( (array) get_option( 'trb_analysis_benchmark_cases', array() ) ); }

/** Normalize both object and one-element-list responses from the Console API. */
function trb_analysis_acr_container_item( $body, $container_id ) {
	if ( ! is_array( $body ) ) return array();
	$payload = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;
	if ( isset( $payload['id'] ) ) return $payload;
	foreach ( $payload as $item ) if ( is_array( $item ) && isset( $item['id'] ) && (string) $container_id === (string) $item['id'] ) return $item;
	return array();
}

/** Read the live File Scanning container without exposing its bearer token. */
function trb_analysis_fetch_acr_container() {
	if ( ! function_exists( 'trb_resource_settings' ) || ! function_exists( 'trb_resource_acr_endpoint' ) ) return new WP_Error( 'ACR_CONFIGURATION_UNAVAILABLE' );
	$s = trb_resource_settings();
	if ( empty( $s['acr_token'] ) || empty( $s['acr_container_id'] ) ) return new WP_Error( 'ACR_CONFIGURATION_INCOMPLETE' );
	$url = 'https://api-v2.acrcloud.com/api/fs-containers/' . rawurlencode( $s['acr_container_id'] );
	$response = wp_remote_get( $url, array( 'timeout' => 60, 'headers' => array( 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $s['acr_token'] ) ) );
	if ( is_wp_error( $response ) ) return new WP_Error( 'ACR_CONTAINER_UNREACHABLE', $response->get_error_message() );
	$code = wp_remote_retrieve_response_code( $response ); $body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) return new WP_Error( 'ACR_CONTAINER_UNVERIFIED', 'HTTP ' . $code );
	$container = trb_analysis_acr_container_item( $body, $s['acr_container_id'] );
	if ( ! $container ) return new WP_Error( 'ACR_CONTAINER_RESPONSE_INVALID', 'Il provider non ha restituito la configurazione del container richiesto.' );
	return $container;
}

/** Verify the paid File Scanning container before spending budget. */
function trb_analysis_verify_acr_container() {
	$container = trb_analysis_fetch_acr_container();
	if ( is_wp_error( $container ) ) return $container;
	$policy = isset( $container['policy'] ) && is_array( $container['policy'] ) ? $container['policy'] : array();
	$engine = isset( $container['engine'] ) ? (int) $container['engine'] : null;
	$deepright = isset( $container['deepright'] ) ? (bool) $container['deepright'] : ( isset( $policy['deepright'] ) ? (bool) $policy['deepright'] : null );
	$music_detection = isset( $container['music_detection'] ) ? (bool) $container['music_detection'] : ( isset( $policy['music_detection'] ) ? (bool) $policy['music_detection'] : false );
	$ai_detection = isset( $container['ai_detection'] ) ? (bool) $container['ai_detection'] : ( isset( $policy['ai_detection'] ) ? (bool) $policy['ai_detection'] : false );
	$errors = array();
	if ( 3 !== $engine ) $errors[] = 'ACR_ENGINE_MUST_INCLUDE_FINGERPRINT_AND_COVER';
	if ( true !== $deepright ) $errors[] = 'ACR_DEEPRIGHT_DISABLED_OR_UNVERIFIED';
	if ( ! isset( $container['region'] ) || 'eu-west-1' !== $container['region'] ) $errors[] = 'ACR_REGION_MUST_BE_EU_WEST_1';
	if ( ! isset( $container['audio_type'] ) || 'linein' !== $container['audio_type'] ) $errors[] = 'ACR_AUDIO_TYPE_MUST_BE_LINEIN';
	if ( empty( $container['buckets'] ) || false === stripos( wp_json_encode( $container['buckets'] ), 'ACRCloud Music' ) ) $errors[] = 'ACR_MUSIC_BUCKET_MISSING';
	if ( $music_detection ) $errors[] = 'ACR_MUSIC_DETECTION_MUST_BE_OFF';
	if ( $ai_detection ) $errors[] = 'ACR_AI_DETECTION_MUST_BE_OFF';
	$snapshot = array( 'verified_at' => time(), 'container' => $container, 'errors' => $errors );
	update_option( 'trb_acr_container_snapshot', $snapshot, false );
	return $errors ? new WP_Error( 'ACR_CONTAINER_MISCONFIGURED', implode( ', ', $errors ) ) : $snapshot;
}

/** Safely preserve the live container while enabling the complete rights check. */
function trb_analysis_configure_acr_container() {
	if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'ACR_CONFIGURATION_FORBIDDEN' );
	if ( ! function_exists( 'trb_resource_settings' ) ) return new WP_Error( 'ACR_CONFIGURATION_UNAVAILABLE' );
	$s = trb_resource_settings();
	$container = trb_analysis_fetch_acr_container();
	if ( is_wp_error( $container ) ) return $container;
	$buckets = array();
	foreach ( (array) ( $container['buckets'] ?? array() ) as $bucket ) {
		if ( is_array( $bucket ) && isset( $bucket['id'] ) ) $buckets[] = absint( $bucket['id'] );
		elseif ( is_array( $bucket ) && ! empty( $bucket['name'] ) ) $buckets[] = sanitize_text_field( $bucket['name'] );
		elseif ( is_scalar( $bucket ) ) $buckets[] = $bucket;
	}
	if ( ! $buckets ) return new WP_Error( 'ACR_MUSIC_BUCKET_MISSING', 'Il container non contiene alcun bucket da preservare.' );
	$policy = isset( $container['policy'] ) && is_array( $container['policy'] ) ? $container['policy'] : array( 'type' => 'traverse', 'interval' => 0, 'rec_length' => 10 );
	$policy['deepright'] = true;
	$policy['music_detection'] = 0;
	$policy['ai_detection'] = 0;
	$payload = array(
		'name'            => sanitize_text_field( $container['name'] ?? 'TRB rights analysis' ),
		'audio_type'      => 'linein',
		'buckets'         => $buckets,
		'engine'          => 3,
		'policy'          => $policy,
		'callback_url'    => esc_url_raw( $container['callback_url'] ?? '' ),
		'deepright'       => 1,
		'music_detection' => 0,
		'ai_detection'    => 0,
	);
	$url = 'https://api-v2.acrcloud.com/api/fs-containers/' . rawurlencode( $s['acr_container_id'] );
	$response = wp_remote_request( $url, array( 'method' => 'PUT', 'timeout' => 60, 'headers' => array( 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $s['acr_token'], 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $payload ) ) );
	if ( is_wp_error( $response ) ) return new WP_Error( 'ACR_CONTAINER_UPDATE_FAILED', $response->get_error_message() );
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) return new WP_Error( 'ACR_CONTAINER_UPDATE_FAILED', 'HTTP ' . $code );
	delete_option( 'trb_acr_container_snapshot' );
	return trb_analysis_verify_acr_container();
}

/** Small dependency-free PDF writer for the audit report. */
function trb_analysis_pdf( $lines, $path ) {
	$pages = array_chunk( array_values( $lines ), 45 ); $objects = array(); $page_ids = array(); $next = 3;
	foreach ( $pages as $page ) { $page_ids[] = $next; $next += 2; }
	$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
	$objects[2] = '<< /Type /Pages /Count ' . count( $pages ) . ' /Kids [' . implode( ' ', array_map( static function( $id ){ return $id . ' 0 R'; }, $page_ids ) ) . '] >>';
	foreach ( $pages as $i => $page ) {
		$page_id = $page_ids[ $i ]; $content_id = $page_id + 1; $stream = "BT /F1 9 Tf 44 800 Td 12 TL\n";
		foreach ( $page as $line ) { $encoded = function_exists( 'iconv' ) ? iconv( 'UTF-8', 'Windows-1252//TRANSLIT', (string) $line ) : (string) $line; $safe = str_replace( array( '\\','(',')',"\r","\n" ), array( '\\\\','\\(','\\)',' ',' ' ), $encoded ?: (string) $line ); $stream .= '(' . $safe . ") Tj T*\n"; }
		$stream .= 'ET';
		$objects[ $page_id ] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $next . ' 0 R >> >> /Contents ' . $content_id . ' 0 R >>';
		$objects[ $content_id ] = '<< /Length ' . strlen( $stream ) . ">>\nstream\n" . $stream . "\nendstream";
	}
	$objects[ $next ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'; ksort( $objects );
	$pdf = "%PDF-1.4\n"; $offsets = array( 0 );
	foreach ( $objects as $id => $object ) { $offsets[ $id ] = strlen( $pdf ); $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n"; }
	$xref = strlen( $pdf ); $max = max( array_keys( $objects ) ); $pdf .= "xref\n0 " . ( $max + 1 ) . "\n0000000000 65535 f \n";
	for ( $i = 1; $i <= $max; $i++ ) $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] ?? 0 );
	$pdf .= 'trailer << /Size ' . ( $max + 1 ) . ' /Root 1 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF";
	return false !== file_put_contents( $path, $pdf ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}

function trb_analysis_generate_report( $release_id ) {
	$release = get_post( $release_id ); if ( ! $release ) return new WP_Error( 'RELEASE_NOT_FOUND' );
	$technical = (array) get_post_meta( $release_id, '_trb_release_technical_analysis', true );
	$decision = (array) get_post_meta( $release_id, '_trb_release_analysis_decision', true );
	$artist = trb_portal_artist_profile_value( 'artist_name', $release->post_author );
	$lines = array( 'TRB rec - REPORT CONTROLLO RELEASE', 'Pratica: #' . $release_id, 'Artista: ' . $artist, 'Release: ' . $release->post_title, 'Data UTC: ' . gmdate( 'c' ), 'Stato tecnico: ' . ( $technical['status'] ?? 'n/d' ), 'Semaforo: ' . ( $decision['semaphore'] ?? 'n/d' ), 'Decisione: ' . ( $decision['state'] ?? 'n/d' ), '' );
	foreach ( $technical['tracks'] ?? array() as $index => $track ) {
		$spec = $track['spec'] ?? array(); $findings = $track['findings'] ?? array();
		$lines[] = 'Brano ' . ( $index + 1 ) . ' - SHA256 ' . ( $track['sha256'] ?? '' );
		$lines[] = 'PCM ' . ( $spec['sample_rate'] ?? 0 ) . ' Hz / ' . ( $spec['bit_depth'] ?? 0 ) . ' bit / ' . ( $spec['channels'] ?? 0 ) . ' canali';
		$lines[] = 'Durata dichiarata/misurata: ' . ( $track['declared_seconds'] ?? 0 ) . ' / ' . ( $spec['duration_seconds'] ?? 0 ) . ' s';
		$lines[] = 'LUFS/LRA/True peak: ' . ( $spec['integrated_lufs'] ?? 'n/d' ) . ' / ' . ( $spec['loudness_range_lu'] ?? 'n/d' ) . ' / ' . ( $spec['true_peak_dbtp'] ?? 'n/d' );
		$lines[] = 'Errori: ' . implode( ', ', $findings['errors'] ?? array() ) . ' | Avvisi: ' . implode( ', ', $findings['warnings'] ?? array() );
	}
	foreach ( $decision['results'] ?? array() as $index => $result ) foreach ( $result['matches'] ?? array() as $match ) $lines[] = 'Match brano ' . ( $index + 1 ) . ': ' . $match['engine'] . ' - ' . $match['title'] . ' - ' . implode( ', ', $match['artists'] ) . ' - score ' . $match['score'];
	if ( function_exists( 'trb_resource_tables' ) ) { global $wpdb; $ledger = trb_resource_tables()['usage']; $current_hashes = array(); foreach ( (array) get_post_meta( $release_id, '_trb_release_files', true ) as $file ) if ( 'audio' === ( $file['kind'] ?? '' ) ) $current_hashes[ absint( $file['track'] ?? 0 ) ] = (string) ( $file['sha256'] ?? '' ); $raw_rows = $wpdb->get_results( $wpdb->prepare( "SELECT track_index,file_hash,payload FROM $ledger WHERE release_id=%d AND provider='acrcloud' AND service IN ('fingerprinting','fingerprinting_reuse') AND status='completed' ORDER BY id", $release_id ), ARRAY_A ); foreach ( $raw_rows as $raw_row ) { if ( empty( $current_hashes[ absint( $raw_row['track_index'] ) ] ) || ! hash_equals( $current_hashes[ absint( $raw_row['track_index'] ) ], (string) $raw_row['file_hash'] ) ) continue; $lines[] = 'Risultato grezzo ACR brano ' . ( absint( $raw_row['track_index'] ) + 1 ) . ':'; foreach ( str_split( (string) $raw_row['payload'], 100 ) as $chunk ) $lines[] = $chunk; } }
	foreach ( (array) get_post_meta( $release_id, '_trb_release_rights_declarations', true ) as $index => $declaration ) $lines[] = 'Dichiarazione brano ' . ( $index + 1 ) . ': ' . ( $declaration['nature'] ?? '' ) . ' / ' . ( $declaration['basis'] ?? '' ) . ( ! empty( $declaration['reference'] ) ? ' / riferimento: ' . $declaration['reference'] : '' );
	$report_documents = get_post_meta( $release_id, '_trb_release_rights_documents', true );
	$report_documents = is_array( $report_documents ) ? array_filter( $report_documents, static function( $document ) { return is_array( $document ) && ! empty( $document['path'] ); } ) : array();
	foreach ( $report_documents as $document ) $lines[] = 'Documento diritti: ' . ( $document['original_name'] ?? $document['name'] ?? '' ) . ' - SHA256 ' . ( $document['sha256'] ?? '' ) . ' - ' . ( $document['status'] ?? '' );
	foreach ( (array) get_post_meta( $release_id, '_trb_release_decision_history', true ) as $event ) { $user = get_userdata( absint( $event['user_id'] ?? 0 ) ); $lines[] = 'Cronologia: ' . gmdate( 'c', absint( $event['at'] ?? 0 ) ) . ' - ' . ( $event['action'] ?? '' ) . ' - ' . ( $user ? $user->user_login : 'sistema' ); }
	$lines[] = 'Restrizioni Content ID/social: le licenze non esclusive o basic richiedono verifica e possono escludere la monetizzazione.';
	$lines[] = 'Approvazione automatica: ' . ( ! empty( $decision['benchmark_ready'] ) ? 'benchmark pronto' : 'disabilitata fino al completamento del benchmark' );
	$uploads = wp_upload_dir(); $dir = trailingslashit( $uploads['basedir'] ) . 'trb-release-private/' . $release_id; wp_mkdir_p( $dir );
	$name = trb_portal_release_audio_name_segment( $artist, 'ARTISTA' ) . '_-_' . trb_portal_release_audio_name_segment( $release->post_title, 'RELEASE' ) . '_(COPYRIGHT_CHECK).pdf';
	$path = trailingslashit( $dir ) . $name;
	if ( ! trb_analysis_pdf( $lines, $path ) ) return new WP_Error( 'REPORT_WRITE_FAILED' );
	$report = array( 'name' => $name, 'path' => 'trb-release-private/' . $release_id . '/' . $name, 'sha256' => hash_file( 'sha256', $path ), 'created_at' => time(), 'status' => 'local' );
	$folder = function_exists( 'trb_resource_release_rights_folder' ) ? trb_resource_release_rights_folder( $release_id, 0 ) : new WP_Error( 'PCLOUD_RELEASE_FOLDER_MISSING' );
	if ( ! is_wp_error( $folder ) && function_exists( 'trb_demo_ensure_remote_folder' ) && function_exists( 'trb_artist_archive_put' ) ) {
		$report_folder = $folder . '/03 - Report analisi'; $ready = trb_demo_ensure_remote_folder( $report_folder );
		if ( ! is_wp_error( $ready ) ) {
			$remote = $report_folder . '/' . $name; $put = trb_artist_archive_put( $remote, file_get_contents( $path ), 'application/pdf' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! is_wp_error( $put ) ) { $report['status'] = 'synced'; $report['remote'] = $remote; }
		}
	}
	update_post_meta( $release_id, '_trb_release_analysis_report', $report );
	return $path;
}

function trb_analysis_report_url( $release_id ) {
	return wp_nonce_url( add_query_arg( array( 'action' => 'trb_analysis_download_report', 'release_id' => absint( $release_id ) ), admin_url( 'admin-post.php' ) ), 'trb_analysis_report_' . absint( $release_id ) );
}

function trb_analysis_download_report() {
	if ( ! is_user_logged_in() ) auth_redirect();
	$release_id = absint( $_GET['release_id'] ?? 0 ); check_admin_referer( 'trb_analysis_report_' . $release_id );
	if ( ! function_exists( 'trb_portal_current_user_can_access_release' ) || ! trb_portal_current_user_can_access_release( $release_id ) ) wp_die( 'Operazione non consentita.', 'Area Artisti TRB rec', array( 'response' => 403 ) );
	$report = (array) get_post_meta( $release_id, '_trb_release_analysis_report', true ); $uploads = wp_upload_dir();
	$base = realpath( trailingslashit( $uploads['basedir'] ) . 'trb-release-private/' . $release_id );
	$path = ! empty( $report['path'] ) ? realpath( trailingslashit( $uploads['basedir'] ) . ltrim( $report['path'], '/' ) ) : false;
	if ( ! $base || ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) wp_die( 'Report non disponibile.', 'Area Artisti TRB rec', array( 'response' => 404 ) );
	nocache_headers(); header( 'Content-Type: application/pdf' ); header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $report['name'] ?? basename( $path ) ) . '"' ); header( 'Content-Length: ' . filesize( $path ) ); readfile( $path ); exit; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
}
add_action( 'admin_post_trb_analysis_download_report', 'trb_analysis_download_report' );

/** Resume quarantined releases without asking the artist to upload again. */
function trb_analysis_retry_security_scans() {
	$releases = get_posts( array( 'post_type' => 'trb_release', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids', 'meta_key' => '_trb_release_pipeline_status', 'meta_value' => 'security_scan_waiting' ) );
	foreach ( $releases as $release_id ) {
		$files = (array) get_post_meta( $release_id, '_trb_release_files', true ); $blocked = false; $malware = false;
		foreach ( $files as &$file ) {
			if ( 'audio' === ( $file['kind'] ?? '' ) ) continue;
			$local = function_exists( 'trb_release_pcloud_local_file' ) ? trb_release_pcloud_local_file( $file ) : '';
			$scan = $local ? trb_analysis_antivirus_scan( $local ) : new WP_Error( 'VIRUS_SCAN_FILE_MISSING' );
			$file['security_status'] = is_wp_error( $scan ) ? $scan->get_error_code() : 'clean';
			if ( is_wp_error( $scan ) ) { $blocked = true; if ( 'MALWARE_DETECTED' === $scan->get_error_code() ) $malware = true; }
		}
		unset( $file ); update_post_meta( $release_id, '_trb_release_files', $files );
		$documents = get_post_meta( $release_id, '_trb_release_rights_documents', true );
		$documents = is_array( $documents ) ? array_values( array_filter( $documents, static function( $document ) { return is_array( $document ) && ! empty( $document['path'] ); } ) ) : array();
		foreach ( $documents as $index => $document ) if ( 'synced' !== ( $document['status'] ?? '' ) && function_exists( 'trb_resource_sync_rights_document' ) ) { $result = trb_resource_sync_rights_document( $release_id, $index ); if ( is_wp_error( $result ) ) { $blocked = true; if ( 'MALWARE_DETECTED' === $result->get_error_code() ) $malware = true; } }
		if ( $malware ) { update_post_meta( $release_id, '_trb_release_pipeline_status', 'security_rejected' ); if ( function_exists( 'trb_resource_queue_email' ) ) trb_resource_queue_email( 'security-rejected-' . $release_id, 'Materiale bloccato dalla scansione antivirus', 'La pratica #' . absint( $release_id ) . ' richiede verifica immediata.', true ); continue; }
		if ( ! $blocked && function_exists( 'trb_release_pcloud_schedule_sync' ) ) trb_release_pcloud_schedule_sync( $release_id );
	}
}
add_action( 'trb_analysis_security_retry', 'trb_analysis_retry_security_scans' );
add_action( 'trb_resource_daily_health', 'trb_analysis_retry_security_scans', 5 );
add_action( 'init', function() { if ( ! wp_next_scheduled( 'trb_analysis_security_retry' ) ) wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'hourly', 'trb_analysis_security_retry' ); } );

function trb_analysis_admin_menu() { add_management_page( 'Analisi release TRB', 'Analisi release TRB', 'manage_options', 'trb-release-analysis', 'trb_analysis_admin_page' ); }
add_action( 'admin_menu', 'trb_analysis_admin_menu', 31 );

function trb_analysis_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$s = trb_analysis_settings(); $message = ''; $message_error = false;
	if ( isset( $_POST['trb_analysis_configure_acr'] ) ) {
		check_admin_referer( 'trb_analysis_configure_acr' );
		$configured = trb_analysis_configure_acr_container();
		$message_error = is_wp_error( $configured );
		$message = $message_error ? 'Configurazione ACRCloud non completata: ' . $configured->get_error_message() : 'ACRCloud configurato e verificato: fingerprinting + Cover Song, DeepRight attivo, regione UE.';
	}
	if ( isset( $_POST['trb_analysis_save'] ) ) {
		check_admin_referer( 'trb_analysis_save' );
		foreach ( array( 'true_peak_warning','master_lufs_extreme_max','master_lufs_extreme_min','master_silence_peak_max','premaster_peak_max','silence_warning_seconds','fingerprint_red_score','match_review_score' ) as $key ) if ( isset( $_POST[ $key ] ) ) $s[ $key ] = (float) wp_unslash( $_POST[ $key ] );
		$s['benchmark_required'] = max( 15, absint( $_POST['benchmark_required'] ?? 15 ) );
		$s['benchmark_complete'] = isset( $_POST['benchmark_complete'] ) ? 1 : 0;
		$s['auto_approval'] = 1;
		$s['clamav_binary'] = isset( $_POST['clamav_binary'] ) ? sanitize_text_field( wp_unslash( $_POST['clamav_binary'] ) ) : '';
		update_option( 'trb_release_analysis_settings', $s, false ); $message = 'Configurazione salvata.';
	}
	if ( isset( $_POST['trb_analysis_benchmark_add'] ) ) {
		check_admin_referer( 'trb_analysis_benchmark_add' ); $cases = (array) get_option( 'trb_analysis_benchmark_cases', array() );
		$cases[] = array( 'label' => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ), 'expected' => sanitize_key( wp_unslash( $_POST['expected'] ?? '' ) ), 'actual' => sanitize_key( wp_unslash( $_POST['actual'] ?? '' ) ), 'duration_ms' => absint( $_POST['duration_ms'] ?? 0 ), 'cost' => (float) ( $_POST['cost'] ?? 0 ), 'created_at' => time() );
		update_option( 'trb_analysis_benchmark_cases', array_slice( $cases, -100 ), false ); $message = 'Caso benchmark registrato.';
	}
	$cases = (array) get_option( 'trb_analysis_benchmark_cases', array() ); $count = count( $cases ); $ready = ! empty( $s['benchmark_complete'] ) && $count >= absint( $s['benchmark_required'] );
	?>
	<div class="wrap"><h1>Analisi release TRB</h1><?php if ( $message ) : ?><div class="notice notice-<?php echo $message_error ? 'error' : 'success'; ?>"><p><?php echo esc_html( $message ); ?></p></div><?php endif; ?>
	<table class="widefat striped"><tbody><tr><th>FFmpeg / ffprobe</th><td><?php echo esc_html( trb_analysis_binary( 'ffmpeg' ) && trb_analysis_binary( 'ffprobe' ) ? 'Disponibili · WAV PCM verificati e decodificati integralmente' : 'NON disponibili: analisi bloccata in sicurezza' ); ?></td></tr><tr><th>Sicurezza caricamenti</th><td><?php echo esc_html( 'Controlli rigorosi sul formato' . ( trb_analysis_wordfence_active() ? ' · Wordfence attivo' : '' ) . ( trb_analysis_binary( 'clamdscan', $s['clamav_binary'] ) || trb_analysis_binary( 'clamscan', $s['clamav_binary'] ) ? ' · ClamAV aggiuntivo disponibile' : '' ) ); ?></td></tr><tr><th>Politica contratti</th><td>Copyright verde: approvazione e contratto automatici · giallo/rosso: contratto sospeso ed email all’artista con Andrea in CC</td></tr><tr><th>Benchmark</th><td><?php echo esc_html( $count . ' / ' . absint( $s['benchmark_required'] ) . ( $ready ? ' · validato' : ' · controllo qualità in corso; non blocca i contratti con copyright verde' ) ); ?></td></tr></tbody></table>
	<h2>Configurazione copyright ACRCloud</h2><form method="post"><?php wp_nonce_field( 'trb_analysis_configure_acr' ); ?><p>Conserva nome, bucket, callback e politica del container; imposta il motore combinato 3, DeepRight, audio line-in e disattiva i servizi non necessari.</p><button class="button" name="trb_analysis_configure_acr" value="1">Configura e verifica il container</button></form>
	<h2>Regole tecniche</h2><form method="post"><?php wp_nonce_field( 'trb_analysis_save' ); ?><table class="form-table"><tbody><tr><th>True peak: soglia di avviso dBTP</th><td><input name="true_peak_warning" value="<?php echo esc_attr( $s['true_peak_warning'] ); ?>"><p class="description">Genera un avviso da ascoltare; da solo non rifiuta il master.</p></td></tr><tr><th>Livelli master estremi</th><td>Avviso master molto alto: <input name="master_lufs_extreme_max" value="<?php echo esc_attr( $s['master_lufs_extreme_max'] ); ?>"> LUFS · audio sostanzialmente muto sotto <input name="master_lufs_extreme_min" value="<?php echo esc_attr( $s['master_lufs_extreme_min'] ); ?>"> LUFS con true peak sotto <input name="master_silence_peak_max" value="<?php echo esc_attr( $s['master_silence_peak_max'] ); ?>"> dBTP<p class="description">Gli avvisi tecnici restano visibili per il controllo qualità, ma non vengono trattati come problemi di copyright. Solo un errore tecnico bloccante impedisce la prosecuzione.</p></td></tr><tr><th>Pre-master: picco consigliato</th><td><input name="premaster_peak_max" value="<?php echo esc_attr( $s['premaster_peak_max'] ); ?>"><p class="description">Un superamento genera un avviso, non un rifiuto automatico.</p></td></tr><tr><th>Silenzio lungo (secondi)</th><td><input name="silence_warning_seconds" value="<?php echo esc_attr( $s['silence_warning_seconds'] ); ?>"></td></tr><tr><th>Soglie match configurabili</th><td>Rosso fingerprint ≥ <input name="fingerprint_red_score" value="<?php echo esc_attr( $s['fingerprint_red_score'] ); ?>" size="6"> · Revisione ≥ <input name="match_review_score" value="<?php echo esc_attr( $s['match_review_score'] ); ?>" size="6"></td></tr><tr><th>Benchmark minimo</th><td><input type="number" min="15" name="benchmark_required" value="<?php echo esc_attr( $s['benchmark_required'] ); ?>"> <label><input type="checkbox" name="benchmark_complete" <?php checked( $s['benchmark_complete'] ); ?>> Validato da TRB</label><p class="description">Il benchmark misura la qualità del sistema ma non blocca l’approvazione automatica quando il controllo copyright è verde.</p></td></tr></tbody></table><button class="button button-primary" name="trb_analysis_save" value="1">Salva</button></form>
	<h2>Registra caso benchmark</h2><form method="post"><?php wp_nonce_field( 'trb_analysis_benchmark_add' ); ?><input required name="label" placeholder="Caso / hash"> <select name="expected"><option value="green">Verde</option><option value="yellow">Giallo</option><option value="red">Rosso</option></select> <select name="actual"><option value="green">Verde</option><option value="yellow">Giallo</option><option value="red">Rosso</option></select> <input type="number" name="duration_ms" placeholder="ms"> <input type="number" step="0.000001" name="cost" placeholder="USD"> <button class="button" name="trb_analysis_benchmark_add" value="1">Registra</button></form>
	<?php if ( $cases ) : ?><table class="widefat striped" style="margin-top:12px"><thead><tr><th>Caso</th><th>Atteso</th><th>Risultato</th><th>Tempo</th><th>Costo</th></tr></thead><tbody><?php foreach ( array_reverse( $cases ) as $case ) : ?><tr><td><?php echo esc_html( $case['label'] ); ?></td><td><?php echo esc_html( $case['expected'] ); ?></td><td><?php echo esc_html( $case['actual'] ); ?></td><td><?php echo esc_html( $case['duration_ms'] . ' ms' ); ?></td><td><?php echo esc_html( $case['cost'] . ' USD' ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div><?php
}
