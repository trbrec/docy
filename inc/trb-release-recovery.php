<?php
/** Administrator recovery of already received files. Never advances approval. */
if ( ! defined( 'ABSPATH' ) ) exit;

function trb_recovery_file_candidates( $user_id ) {
	$root = realpath( trb_portal_release_staging_root( absint( $user_id ) ) );
	if ( ! $root ) return array();
	$paths = array_merge( glob( $root . '/*/f*.json' ) ?: array(), glob( $root . '/recovery-*/*/f*.json' ) ?: array() );
	$out = array();
	foreach ( array_slice( $paths, 0, 300 ) as $path ) {
		$resolved = realpath( $path );
		$part = realpath( substr( $path, 0, -5 ) . '.part' );
		if ( ! $resolved || ! $part || 0 !== strpos( $resolved, $root . DIRECTORY_SEPARATOR ) || 0 !== strpos( $part, $root . DIRECTORY_SEPARATOR ) || ! is_file( $part ) || filesize( $resolved ) > 16384 ) continue;
		$meta = json_decode( (string) file_get_contents( $resolved ), true );
		if ( ! is_array( $meta ) || empty( $meta['complete'] ) || empty( $meta['name'] ) || (int) ( $meta['size'] ?? 0 ) !== (int) filesize( $part ) ) continue;
		$relative = substr( $resolved, strlen( $root ) + 1 );
		$out[ hash( 'sha256', $relative ) ] = array( 'relative' => $relative, 'path' => $part, 'name' => sanitize_file_name( $meta['name'] ), 'type' => sanitize_mime_type( $meta['type'] ?? '' ), 'size' => (int) filesize( $part ) );
	}
	return $out;
}

function trb_recovery_release( $release_id ) {
	$post = get_post( absint( $release_id ) );
	if ( ! $post || 'trb_release' !== $post->post_type || ! in_array( get_post_meta( $post->ID, '_trb_release_intake_phase', true ), array( 'awaiting_upload', 'validation_failed', 'files_partial', 'recovery_review' ), true ) ) return null;
	return $post;
}

function trb_recovery_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$id = absint( $_GET['release_id'] ?? 0 );
	echo '<div class="wrap"><h1>Recupero materiali release</h1><form method="get"><input type="hidden" name="page" value="trb-release-recovery"><label>Numero pratica <input type="number" name="release_id" min="1" value="' . $id . '"></label> <button class="button">Apri materiali ricevuti</button></form>';
	$post = trb_recovery_release( $id );
	if ( ! $post ) { echo '<p>Seleziona una pratica incompleta. Le release già completate non vengono modificate da questo strumento.</p></div>'; return; }
	if ( 'recovery_review' === get_post_meta( $id, '_trb_release_intake_phase', true ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="trb_recovery_resume"><input type="hidden" name="release_id" value="' . $id . '">';
		wp_nonce_field( 'trb_recovery_resume_' . $id );
		echo '<p>La verifica usa i dati salvati e tutti i controlli ordinari. Se validi, avvia archiviazione e analisi; i contratti seguono le regole della pipeline.</p><button class="button button-primary">Riprendi la verifica standard della pratica</button></form>';
	}
	$tracks = (array) get_post_meta( $id, '_trb_release_tracks', true );
	$files = trb_recovery_file_candidates( $post->post_author );
	echo '<h2>' . esc_html( $post->post_title ) . ' · #' . $id . '</h2><p>Associa soltanto i materiali corretti. Le copie originali restano conservate; il recupero non avvia contratti, analisi o distribuzione.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="trb_recovery_attach"><input type="hidden" name="release_id" value="' . $id . '">';
	wp_nonce_field( 'trb_recovery_attach_' . $id );
	$associated = get_post_meta( $id, '_trb_release_files', true );
	if ( is_array( $associated ) && $associated ) {
		echo '<h3>Materiali già recuperati</h3><ul>';
		foreach ( $associated as $file_index => $stored ) echo '<li><a href="' . esc_url( trb_portal_release_file_url( $id, $file_index ) ) . '">' . esc_html( $stored['original_name'] ?? $stored['name'] ?? '' ) . '</a> · SHA-256 ' . esc_html( $stored['sha256'] ?? '' ) . '</li>';
		echo '</ul>';
	}

	echo '<table class="widefat striped"><thead><tr><th>File ricevuto</th><th>Verifica</th><th>Associazione</th></tr></thead><tbody>';
	foreach ( $files as $key => $file ) {
		$details = size_format( $file['size'] );
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'wav' === $extension ) {
			$spec = trb_portal_wav_spec( $file['path'] );
			$details .= is_wp_error( $spec ) ? ' · WAV non valido' : ' · ' . round( $spec['duration_seconds'], 2 ) . ' s · ' . $spec['sample_rate'] . ' Hz · ' . $spec['bit_depth'] . ' bit';
		} elseif ( in_array( $extension, array( 'png', 'jpg', 'jpeg' ), true ) ) {
			$image = @getimagesize( $file['path'] );
			if ( $image ) $details .= ' · ' . $image[0] . ' × ' . $image[1];
		}
		$preview = in_array( $extension, array( 'png', 'jpg', 'jpeg' ), true ) ? '<br><img alt="Anteprima ' . esc_attr( $file['name'] ) . '" style="max-width:260px;height:auto" src="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'trb_recovery_preview', 'release_id' => $id, 'file_key' => $key ), admin_url( 'admin-post.php' ) ), 'trb_recovery_preview_' . $id ) ) . '">' : '';
		echo '<tr><td><strong>' . esc_html( $file['name'] ) . '</strong><br><small>' . esc_html( $file['relative'] ) . '</small>' . $preview . '</td><td>' . esc_html( $details ) . '</td><td><select aria-label="Associazione ' . esc_attr( $file['relative'] ) . '" name="files[' . esc_attr( $key ) . ']"><option value="">Non utilizzare</option><option value="cover">Copertina</option><option value="presentation">Presentazione</option>';
		foreach ( $tracks as $index => $track ) {
			if ( ! is_array( $track ) ) continue;
			echo '<option value="audio:' . absint( $index ) . '">Audio: ' . esc_html( $track['title'] ?? '' ) . '</option><option value="lyrics:' . absint( $index ) . '">Testo: ' . esc_html( $track['title'] ?? '' ) . '</option>';
		}
		echo '</select></td></tr>';
	}
	echo '</tbody></table><p><button class="button button-primary">Recupera e verifica i file selezionati</button></p></form></div>';
}
add_action( 'admin_menu', static function() { add_management_page( 'Recupero materiali release', 'Recupero materiali release', 'manage_options', 'trb-release-recovery', 'trb_recovery_page' ); } );

function trb_recovery_attach() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accesso non consentito.' );
	$id = absint( $_POST['release_id'] ?? 0 );
	check_admin_referer( 'trb_recovery_attach_' . $id );
	$post = trb_recovery_release( $id );
	if ( ! $post ) wp_die( 'La pratica non è recuperabile con questa azione.' );
	$candidates = trb_recovery_file_candidates( $post->post_author );
	$tracks = (array) get_post_meta( $id, '_trb_release_tracks', true );
	$existing = get_post_meta( $id, '_trb_release_files', true );
	$existing = is_array( $existing ) ? $existing : array();
	$selection = isset( $_POST['files'] ) && is_array( $_POST['files'] ) ? wp_unslash( $_POST['files'] ) : array();
	$chosen = array();
	foreach ( array_slice( $selection, 0, 300, true ) as $key => $slot ) {
		if ( ! is_string( $slot ) || '' === $slot ) continue;
		if ( ! isset( $candidates[ $key ] ) || ! preg_match( '/^(cover|presentation|audio:[0-9]{1,2}|lyrics:[0-9]{1,2})$/', $slot ) || isset( $chosen[ $slot ] ) ) wp_die( 'Selezione duplicata o non valida.' );
		$parts = explode( ':', $slot );
		if ( isset( $parts[1] ) && ! isset( $tracks[ (int) $parts[1] ] ) ) wp_die( 'Brano non presente nella pratica.' );
		$chosen[ $slot ] = $candidates[ $key ];
	}
	if ( ! $chosen ) wp_die( 'Nessun file selezionato.' );
	$lock = 'trb_recovery_release_' . $id;
	if ( ! add_option( $lock, time(), '', false ) ) wp_die( 'Un recupero è già in corso.' );
	$session = wp_generate_uuid4();
	try {
		$temp = trb_portal_release_staging_session_dir( $session, true );
		if ( ! $temp ) throw new RuntimeException( 'Storage temporaneo non disponibile.' );
		foreach ( $chosen as $slot => $file ) {
			$parts = explode( ':', $slot ); $kind = $parts[0]; $index = isset( $parts[1] ) ? (int) $parts[1] : null;
			$hash = hash_file( 'sha256', $file['path'] );
			$already = false;
			foreach ( $existing as $stored ) if ( ( $stored['kind'] ?? '' ) === $kind && ( $stored['track'] ?? null ) === $index ) {
				if ( ! hash_equals( (string) ( $stored['sha256'] ?? '' ), (string) $hash ) ) throw new RuntimeException( 'Un file diverso è già associato a ' . $slot . '. Nessuna sovrascrittura eseguita.' );
				$already = true;
			}
			if ( $already ) continue;
			$temporary = trailingslashit( $temp ) . 'recovery.part';
			if ( ! copy( $file['path'], $temporary ) || ! hash_equals( (string) $hash, (string) hash_file( 'sha256', $temporary ) ) ) throw new RuntimeException( 'Copia non verificata: ' . $file['name'] );
			$upload = array( 'name' => $file['name'], 'type' => $file['type'], 'size' => $file['size'], 'tmp_name' => $temporary, 'error' => UPLOAD_ERR_OK, '_trb_staged' => true );
			$status = $tracks[ $index ]['audio_status'] ?? 'mastered';
			if ( 'audio' === $kind ) {
				$spec = trb_portal_wav_spec( $temporary );
				$seconds = trb_portal_release_track_duration_seconds( $tracks[ $index ] );
				if ( is_wp_error( $spec ) || ! $seconds || abs( $seconds - $spec['duration_seconds'] ) > 1.0 ) throw new RuntimeException( 'Durata non corrispondente: ' . $file['name'] );
			}
			$stored = trb_portal_store_release_upload( $id, $upload, $kind, $index, array( 'track_title' => $tracks[ $index ]['title'] ?? '', 'audio_status' => $status ) );
			if ( is_wp_error( $stored ) ) throw new RuntimeException( $file['name'] . ': ' . trb_portal_release_upload_error_message( $stored->get_error_code() ) );
			if ( ! hash_equals( (string) $hash, (string) $stored['sha256'] ) ) throw new RuntimeException( 'Integrità del file archiviato non confermata.' );
			if ( 'audio' === $kind ) $stored['audio_status'] = $status; else $stored['security_status'] = 'pending';
			$stored['recovered_from'] = $file['relative'];
			$existing[] = $stored;
			update_post_meta( $id, '_trb_release_files', $existing );
		}
		update_post_meta( $id, '_trb_release_intake_phase', 'recovery_review' );
		update_post_meta( $id, '_trb_release_intake_error', 'Materiali recuperati e integrità verificata. Revisione finale della pratica e scansione dei documenti ancora da completare.' );
		update_post_meta( $id, '_trb_release_pipeline_status', 'upload_incomplete' );
		trb_intake_sync( $id );
	} catch ( Throwable $error ) {
		update_post_meta( $id, '_trb_release_intake_error', sanitize_text_field( $error->getMessage() ) );
		trb_intake_sync( $id );
		delete_option( $lock );
		trb_portal_cleanup_release_staging_session( $session );
		wp_die( esc_html( $error->getMessage() ) );
	} finally {
		delete_option( $lock );
	}
	trb_portal_cleanup_release_staging_session( $session );
	wp_safe_redirect( admin_url( 'tools.php?page=trb-release-recovery&release_id=' . $id ) );
	exit;
}
add_action( 'admin_post_trb_recovery_attach', 'trb_recovery_attach' );

/** Authenticated, image-only preview; filenames never become arbitrary paths. */
function trb_recovery_preview() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accesso non consentito.' );
	$id = absint( $_GET['release_id'] ?? 0 );
	check_admin_referer( 'trb_recovery_preview_' . $id );
	$post = trb_recovery_release( $id );
	if ( ! $post ) wp_die( 'Pratica non disponibile.' );
	$key = sanitize_key( $_GET['file_key'] ?? '' );
	$files = trb_recovery_file_candidates( $post->post_author );
	if ( ! isset( $files[ $key ] ) ) wp_die( 'File non disponibile.' );
	$file = $files[ $key ];
	$image = @getimagesize( $file['path'] );
	if ( ! $image || ! in_array( (int) $image[2], array( IMAGETYPE_JPEG, IMAGETYPE_PNG ), true ) ) wp_die( 'Anteprima immagine non disponibile.' );
	nocache_headers();
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Type: ' . ( IMAGETYPE_PNG === (int) $image[2] ? 'image/png' : 'image/jpeg' ) );
	header( 'Content-Length: ' . $file['size'] );
	readfile( $file['path'] );
	exit;
}
add_action( 'admin_post_trb_recovery_preview', 'trb_recovery_preview' );

/** Resume a recovered submission through the ordinary artist validator. */
function trb_recovery_resume() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accesso non consentito.' );
	$id = absint( $_POST['release_id'] ?? 0 );
	check_admin_referer( 'trb_recovery_resume_' . $id );
	$post = trb_recovery_release( $id );
	if ( ! $post || 'recovery_review' !== get_post_meta( $id, '_trb_release_intake_phase', true ) ) wp_die( 'Pratica non pronta per la verifica.' );
	$pairs = get_post_meta( $id, '_trb_release_intake_draft', true );
	$files = get_post_meta( $id, '_trb_release_files', true );
	$token = (string) get_post_meta( $id, '_trb_release_submission_token', true );
	if ( ! is_array( $pairs ) || ! $pairs || ! is_array( $files ) || ! $files || ! preg_match( '/^[a-f0-9-]{36}$/i', $token ) ) wp_die( 'Dati di recupero insufficienti.' );
	$lock = 'trb_recovery_release_' . $id;
	if ( ! add_option( $lock, time(), '', false ) ) wp_die( 'Recupero già in corso.' );
	$admin = get_current_user_id();
	$GLOBALS['trb_recovery_resume_context'] = array( 'id' => $id, 'admin' => $admin, 'lock' => $lock, 'token' => $token, 'files' => $files );
	register_shutdown_function( static function() use ( $lock ) { delete_option( $lock ); } );
	try {
		// The administrator authorizes recovery, while normal profile/contract checks run as the owner.
		wp_set_current_user( $post->post_author );
		$_POST = array(); $_FILES = array();
		foreach ( trb_portal_normalize_release_draft_pairs( $pairs ) as $pair ) trb_portal_set_nested_post_value( $pair[0], $pair[1] );
		$_POST['trb_release_title'] = $post->post_title;
		$_POST['trb_release_submission_token'] = $token;
		unset( $_POST['trb_release_intake_only'], $_POST['trb_release_ajax'] );
		$_POST['trb_portal_release_nonce'] = wp_create_nonce( 'trb_portal_start_release' );
		$directory = trb_portal_release_staging_session_dir( $token, true );
		if ( ! $directory ) throw new RuntimeException( 'Storage temporaneo non disponibile.' );
		$map = array();
		foreach ( $files as $i => $file ) {
			$kind = $file['kind'] ?? '';
			$fields = array( 'cover' => 'trb_release_cover', 'cover_reference' => 'trb_release_cover_reference', 'presentation' => 'trb_release_presentation', 'audio' => 'trb_track_audio', 'lyrics' => 'trb_track_lyrics', 'rights_document' => 'trb_track_rights_document' );
			if ( ! isset( $fields[ $kind ] ) ) throw new RuntimeException( 'Tipo allegato non riconosciuto.' );
			$field = $fields[ $kind ];
			if ( in_array( $kind, array( 'audio', 'lyrics', 'rights_document' ), true ) ) $field .= '[' . absint( $file['track'] ) . ']';
			if ( isset( $map[ $field ] ) ) throw new RuntimeException( 'Allegati duplicati per lo stesso campo.' );
			$local = trb_release_pcloud_local_file( $file );
			$root = realpath( trailingslashit( wp_upload_dir()['basedir'] ) . 'trb-release-private/' . $id );
			$real = $local ? realpath( $local ) : false;
			if ( ! $root || ! $real || 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) || ! is_file( $real ) || empty( $file['sha256'] ) || ! hash_equals( $file['sha256'], hash_file( 'sha256', $real ) ) ) throw new RuntimeException( 'Integrità degli allegati non confermata.' );
			$key = 'f' . ( 9000 + $i );
			$part = trailingslashit( $directory ) . $key . '.part';
			if ( ! copy( $real, $part ) || ! hash_equals( $file['sha256'], hash_file( 'sha256', $part ) ) ) throw new RuntimeException( 'Copia di verifica non riuscita.' );
			$meta = array( 'complete' => true, 'size' => filesize( $part ), 'name' => $file['original_name'] ?? $file['name'], 'type' => $file['mime'] ?? '' );
			if ( false === file_put_contents( trailingslashit( $directory ) . $key . '.json', wp_json_encode( $meta ), LOCK_EX ) ) throw new RuntimeException( 'Manifest di verifica non salvato.' );
			$map[ $field ] = array( 'session' => $token, 'key' => $key );
		}
		$_POST['trb_staged_uploads_json'] = wp_json_encode( $map );
		$_POST = wp_slash( $_POST );
		update_post_meta( $id, '_trb_release_recovery_resumed_by', $admin );
		update_post_meta( $id, '_trb_release_recovery_resumed_at', time() );
		update_post_meta( $id, '_trb_release_intake_phase', 'validation_failed' );
		trb_portal_start_release();
	} catch ( Throwable $error ) {
		trb_recovery_resume_response( 'error', $error->getMessage() );
	}
}
add_action( 'admin_post_trb_recovery_resume', 'trb_recovery_resume' );

/** Reuse only the exact verified private file; validation still runs before this. */
function trb_recovery_reuse_file( $id, $file, $kind, $index ) {
	$context = $GLOBALS['trb_recovery_resume_context'] ?? array();
	if ( empty( $context ) || (int) $context['id'] !== (int) $id ) return null;
	foreach ( $context['files'] as $stored ) {
		if ( ( $stored['kind'] ?? '' ) !== $kind || ( $stored['track'] ?? null ) !== $index ) continue;
		$local = trb_release_pcloud_local_file( $stored );
		if ( ! $local || ! is_file( $local ) || ! hash_equals( $stored['sha256'], hash_file( 'sha256', $local ) ) || ! hash_equals( $stored['sha256'], hash_file( 'sha256', $file['tmp_name'] ) ) ) return new WP_Error( 'recovery_integrity_failed' );
		return $stored;
	}
	return new WP_Error( 'recovery_file_missing' );
}

function trb_recovery_resume_response( $status, $message ) {
	$context = $GLOBALS['trb_recovery_resume_context'] ?? array();
	if ( ! $context ) return;
	$id = $context['id'];
	if ( 'created' !== $status ) {
		update_post_meta( $id, '_trb_release_intake_phase', 'recovery_review' );
		update_post_meta( $id, '_trb_release_intake_error', sanitize_text_field( $message ) );
		update_post_meta( $id, '_trb_release_pipeline_status', 'upload_incomplete' );
	}
	update_post_meta( $id, '_trb_release_recovery_result', array( 'status' => sanitize_key( $status ), 'message' => sanitize_text_field( $message ), 'time' => time() ) );
	trb_intake_sync( $id );
	trb_portal_cleanup_release_staging_session( $context['token'] );
	delete_option( $context['lock'] );
	wp_set_current_user( $context['admin'] );
	wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	exit;
}
