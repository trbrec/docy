<?php
/**
 * Area Artisti TRB rec.
 *
 * @package docy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract profiles. Keep role slugs aligned with the existing installation.
 */
function trb_portal_profiles() {
	return array(
		'dds' => array(
			'role'       => 'artista_a',
			'aliases'    => array( 'artista_dds' ),
			'label'      => 'DDS',
			'capability' => 'trb_portal_dds',
		),
		'ddb' => array(
			'role'       => 'artista_b',
			'aliases'    => array( 'artista_ddb' ),
			'label'      => 'DDB',
			'capability' => 'trb_portal_ddb',
		),
		'ddb_trb' => array(
			'role'       => 'artista_c',
			'aliases'    => array( 'artista_ddb-trb' ),
			'label'      => 'DDB-TRB',
			'capability' => 'trb_portal_ddb_trb',
		),
		'trb' => array(
			'role'       => 'artista_d',
			'aliases'    => array( 'artista_trb' ),
			'label'      => 'TRB',
			'capability' => 'trb_portal_trb',
		),
	);
}

/**
 * Read the one contractual profile assigned to a user.
 * Legacy TRB Basic remains readable until the controlled migration is run.
 */
function trb_portal_user_profile( $user = null ) {
	$user = $user ? $user : wp_get_current_user();

	if ( ! $user || ! $user->exists() ) {
		return false;
	}

	foreach ( trb_portal_profiles() as $key => $profile ) {
		$roles = array_merge( array( $profile['role'] ), isset( $profile['aliases'] ) ? (array) $profile['aliases'] : array() );
		if ( array_intersect( $roles, (array) $user->roles ) ) {
			return $key;
		}
	}

	if ( in_array( 'artisti_trb_basic', (array) $user->roles, true ) ) {
		return 'trb';
	}

	return false;
}

function trb_portal_profile_label( $profile = null ) {
	$profile = $profile ? $profile : trb_portal_user_profile();
	$profiles = trb_portal_profiles();

	return isset( $profiles[ $profile ] ) ? $profiles[ $profile ]['label'] : '';
}

function trb_portal_allowed_profiles() {
	return array_keys( trb_portal_profiles() );
}

function trb_portal_user_can_access( $profiles, $user = null ) {
	$profiles = is_array( $profiles ) ? $profiles : array( $profiles );
	$profiles = array_filter( array_map( 'sanitize_key', $profiles ) );

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	return in_array( trb_portal_user_profile( $user ), $profiles, true );
}

/**
 * Idempotently add a distinct capability to each current contractual role.
 * No artist is moved here: the TRB Basic migration has its own explicit action.
 */
function trb_portal_register_capabilities() {
	foreach ( trb_portal_profiles() as $profile ) {
		$roles = array_merge( array( $profile['role'] ), isset( $profile['aliases'] ) ? (array) $profile['aliases'] : array() );
		foreach ( $roles as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role ) {
				$role->add_cap( 'trb_portal_access' );
				$role->add_cap( $profile['capability'] );
			}
		}
	}
}
add_action( 'init', 'trb_portal_register_capabilities', 20 );

/**
 * Resource metadata: an administrator selects exactly which contracts can see
 * a document, video or download. A resource can belong to more than one group.
 */
function trb_portal_supported_resource_types() {
	return array( 'trb_guide', 'docs', 'video', 'wpdmpro' );
}

function trb_portal_resource_profiles( $post_id ) {
	$profiles = get_post_meta( $post_id, '_trb_portal_profiles', true );
	$profiles = is_array( $profiles ) ? $profiles : array();

	return array_values( array_intersect( $profiles, trb_portal_allowed_profiles() ) );
}

function trb_portal_resource_is_visible( $post_id, $user = null ) {
	$profiles = trb_portal_resource_profiles( $post_id );

	// Untagged legacy material is deliberately not exposed through the new hub.
	return ! empty( $profiles ) && trb_portal_user_can_access( $profiles, $user );
}

function trb_portal_add_resource_metaboxes() {
	foreach ( trb_portal_supported_resource_types() as $post_type ) {
		add_meta_box(
			'trb-portal-audience',
			'Disponibile per profilo contrattuale',
			'trb_portal_render_resource_metabox',
			$post_type,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'trb_portal_add_resource_metaboxes' );

function trb_portal_render_resource_metabox( $post ) {
	$selected = trb_portal_resource_profiles( $post->ID );
	wp_nonce_field( 'trb_portal_resource_audience', 'trb_portal_resource_audience_nonce' );
	?>
	<p>Il materiale compare solo nelle dashboard selezionate. Lasciare tutto vuoto lo mantiene fuori dalla nuova Area Artisti.</p>
	<?php foreach ( trb_portal_profiles() as $key => $profile ) : ?>
		<p>
			<label>
				<input type="checkbox" name="trb_portal_profiles[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected, true ) ); ?> />
				<?php echo esc_html( $profile['label'] ); ?>
			</label>
		</p>
	<?php endforeach; ?>
	<?php
}

function trb_portal_save_resource_audience( $post_id ) {
	if ( ! isset( $_POST['trb_portal_resource_audience_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trb_portal_resource_audience_nonce'] ) ), 'trb_portal_resource_audience' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$profiles = isset( $_POST['trb_portal_profiles'] ) ? (array) wp_unslash( $_POST['trb_portal_profiles'] ) : array();
	$profiles = array_values( array_intersect( array_map( 'sanitize_key', $profiles ), trb_portal_allowed_profiles() ) );
	update_post_meta( $post_id, '_trb_portal_profiles', $profiles );
}
add_action( 'save_post_docs', 'trb_portal_save_resource_audience' );
add_action( 'save_post_video', 'trb_portal_save_resource_audience' );
add_action( 'save_post_wpdmpro', 'trb_portal_save_resource_audience' );
add_action( 'save_post_trb_guide', 'trb_portal_save_resource_audience' );

/**
 * Direct links to tagged content respect the same audience policy as the hub.
 */
function trb_portal_protect_tagged_resource() {
	if ( ! is_singular( trb_portal_supported_resource_types() ) || current_user_can( 'manage_options' ) ) {
		return;
	}

	$post_id  = get_queried_object_id();
	$profiles = trb_portal_resource_profiles( $post_id );

	if ( empty( $profiles ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}

	if ( ! trb_portal_user_can_access( $profiles ) ) {
		wp_die( 'Questo contenuto non è previsto dal tuo profilo contrattuale.', 'Area Artisti TRB rec', array( 'response' => 403 ) );
	}
}
add_action( 'template_redirect', 'trb_portal_protect_tagged_resource', 1 );

/**
 * Small request centre. Individual forms are introduced one by one, based on
 * the services included in each agreement. Cover requests are intentionally
 * unavailable to DDS and DDB.
 */
function trb_portal_register_request_type() {
	register_post_type(
		'trb_request',
		array(
			'labels' => array(
				'name'          => 'Richieste Artisti',
				'singular_name' => 'Richiesta Artista',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'supports'            => array( 'title', 'editor', 'author' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'exclude_from_search' => true,
		)
	);
}
add_action( 'init', 'trb_portal_register_request_type', 5 );

/**
 * A release is the single unit of work. It prevents audio, covers and
 * promotion requests from being submitted as unrelated tickets.
 */
function trb_portal_register_release_type() {
	register_post_type(
		'trb_release',
		array(
			'labels' => array( 'name' => 'Release Artisti', 'singular_name' => 'Release Artista' ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'supports'            => array( 'title', 'author' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'exclude_from_search' => true,
		)
	);
}
add_action( 'init', 'trb_portal_register_release_type', 5 );

function trb_portal_release_types() {
	return array(
		'single'       => array( 'label' => 'Singolo', 'range' => '1 brano', 'min' => 1, 'max' => 1 ),
		'ep'           => array( 'label' => 'EP', 'range' => '4–8 brani', 'min' => 4, 'max' => 8 ),
		'album'        => array( 'label' => 'Album', 'range' => '9–15 brani', 'min' => 9, 'max' => 15 ),
		'double_album' => array( 'label' => 'Doppio album', 'range' => '16–30 brani', 'min' => 16, 'max' => 30 ),
		'compilation'  => array( 'label' => 'Compilation', 'range' => '16–24 brani', 'min' => 16, 'max' => 24 ),
		'collection'   => array( 'label' => 'Collection', 'range' => '20–40 brani', 'min' => 20, 'max' => 40 ),
		'catalogue'    => array( 'label' => 'Catalogo / repertorio edito', 'range' => 'fino a 60 brani', 'min' => 1, 'max' => 60, 'catalogue' => true ),
	);
}

function trb_portal_genres() {
	return array( 'Alternative', 'Anime', 'Arabic', 'Audiobooks', 'Blues', 'Brazilian', "Children's Music", 'Chinese', 'Christian & Gospel', 'Classical', 'Comedy', 'Country', 'Dance', 'Disney', 'Easy Listening', 'Electronic', 'Enka', 'Fitness & Workout', 'Folk', 'French Pop', 'Funk', 'German Folk', 'German Pop', 'Heavy Metal', 'Hip Hop/Rap', 'Holiday', 'Indian', 'Inspirational', 'Instrumental', 'J-Pop', 'Jazz', 'Karaoke', 'Kayokyoku', 'Korean', 'Latin', 'Marching Bands', 'New Age', 'Other', 'Pop', 'Punk', 'R&B/Soul', 'Reggae', 'Rock', 'Singer/Songwriter', 'Soundtrack', 'Spoken Word', 'Vocal', 'World' );
}

function trb_portal_artist_profile_fields() {
	return array(
		'artist_name' => 'Nome d’arte',
		'legal_name'  => 'Nome e cognome anagrafici',
		'email'       => 'E-mail di riferimento',
		'phone'       => 'Telefono',
		'birth_date'  => 'Data di nascita',
		'birth_place' => 'Luogo di nascita',
		'tax_code'    => 'Codice fiscale',
		'address'     => 'Indirizzo di residenza',
		'city'        => 'Città',
		'postal_code' => 'CAP',
		'province'    => 'Provincia',
		'country'     => 'Nazione',
		'vat_number'  => 'Partita IVA (solo se presente)',
	);
}

function trb_portal_artist_profile_value( $key, $user_id = 0 ) {
	return (string) get_user_meta( $user_id ? $user_id : get_current_user_id(), '_trb_artist_' . $key, true );
}

function trb_portal_artist_profile_is_complete( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$required = array( 'artist_name', 'legal_name', 'email', 'phone', 'birth_date', 'tax_code', 'address', 'city', 'postal_code', 'province', 'country' );
	foreach ( $required as $field ) {
		if ( '' === trb_portal_artist_profile_value( $field, $user_id ) ) {
			return false;
		}
	}
	return '' !== (string) get_user_meta( $user_id, '_trb_artist_bio', true );
}

function trb_portal_handle_artist_profile() {
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	check_admin_referer( 'trb_portal_save_artist_profile', 'trb_portal_profile_nonce' );
	$user_id = get_current_user_id();
	foreach ( trb_portal_artist_profile_fields() as $key => $label ) {
		$value = isset( $_POST[ 'trb_artist_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'trb_artist_' . $key ] ) ) : '';
		if ( 'email' === $key ) {
			$value = sanitize_email( $value );
		}
		update_user_meta( $user_id, '_trb_artist_' . $key, $value );
	}
	$bio = isset( $_POST['trb_artist_bio'] ) ? wp_kses_post( wp_unslash( $_POST['trb_artist_bio'] ) ) : '';
	update_user_meta( $user_id, '_trb_artist_bio', $bio );
	wp_safe_redirect( add_query_arg( 'trb_profile', 'saved', get_permalink( get_option( 'trb_portal_dashboard_created' ) ) ) . '#profilo' );
	exit;
}
add_action( 'admin_post_trb_portal_save_artist_profile', 'trb_portal_handle_artist_profile' );

function trb_portal_user_releases() {
	return get_posts(
		array(
			'post_type'      => 'trb_release',
			'post_status'    => 'publish',
			'author'         => get_current_user_id(),
			'posts_per_page' => 30,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
}

function trb_portal_start_release() {
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}

	check_admin_referer( 'trb_portal_start_release', 'trb_portal_release_nonce' );
	if ( ! trb_portal_user_profile() && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Il tuo profilo non è ancora configurato.', 'Area Artisti TRB rec', array( 'response' => 403 ) );
	}

	if ( ! trb_portal_artist_profile_is_complete() && ! current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( add_query_arg( 'trb_release', 'profile_required', get_permalink( get_option( 'trb_portal_dashboard_created' ) ) ) . '#profilo' );
		exit;
	}
	$title = isset( $_POST['trb_release_title'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_release_title'] ) ) : '';
	$type  = isset( $_POST['trb_release_type'] ) ? sanitize_key( wp_unslash( $_POST['trb_release_type'] ) ) : '';
	$types = trb_portal_release_types();
	$is_catalogue = isset( $types[ $type ]['catalogue'] ) && $types[ $type ]['catalogue'];
	$release_state = isset( $_POST['trb_release_state'] ) ? sanitize_key( wp_unslash( $_POST['trb_release_state'] ) ) : 'unreleased';
	$original_date = isset( $_POST['trb_release_original_date'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_release_original_date'] ) ) : '';
	$tracks = isset( $_POST['trb_tracks'] ) && is_array( $_POST['trb_tracks'] ) ? (array) wp_unslash( $_POST['trb_tracks'] ) : array();
	$tracks = trb_portal_sanitize_release_tracks( $tracks );
	if ( ( ! $is_catalogue && '' === $title ) || ! isset( $types[ $type ] ) || empty( $tracks ) || count( $tracks ) < $types[ $type ]['min'] || ! in_array( $release_state, array( 'unreleased', 'previously_released' ), true ) || ( 'previously_released' === $release_state && '' === $original_date ) || count( $tracks ) > $types[ $type ]['max'] ) {
		wp_safe_redirect( add_query_arg( 'trb_release', 'invalid', get_permalink( get_option( 'trb_portal_dashboard_created' ) ) ) . '#release' );
		exit;
	}
	if ( $is_catalogue && '' === $title ) {
		$title = 'Catalogo / repertorio edito';
	}

	$release_id = wp_insert_post(
		array(
			'post_type'   => 'trb_release',
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_author' => get_current_user_id(),
		)
	);
	if ( ! is_wp_error( $release_id ) ) {
		update_post_meta( $release_id, '_trb_release_type', $type );
		update_post_meta( $release_id, '_trb_release_step', 'contract' );
		update_post_meta( $release_id, '_trb_release_status', 'artist_action' );
		update_post_meta( $release_id, '_trb_release_state', $release_state );
		update_post_meta( $release_id, '_trb_release_original_date', $original_date );
		update_post_meta( $release_id, '_trb_release_tracks', $tracks );
		wp_safe_redirect( add_query_arg( 'trb_release', 'created', get_permalink( get_option( 'trb_portal_dashboard_created' ) ) ) . '#release' );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'trb_release', 'error', get_permalink( get_option( 'trb_portal_dashboard_created' ) ) ) . '#release' );
	exit;
}
add_action( 'admin_post_trb_portal_start_release', 'trb_portal_start_release' );

function trb_portal_sanitize_release_tracks( $tracks ) {
	$genres = trb_portal_genres();
	$clean = array();
	foreach ( $tracks as $track ) {
		$title = isset( $track['title'] ) ? sanitize_text_field( $track['title'] ) : '';
		if ( '' === $title ) {
			continue;
		}
		$credits = isset( $track['credits'] ) && is_array( $track['credits'] ) ? $track['credits'] : array();
		$clean[] = array(
			'title' => $title,
			'featuring' => isset( $track['featuring'] ) ? sanitize_text_field( $track['featuring'] ) : '',
			'duration' => isset( $track['duration'] ) ? sanitize_text_field( $track['duration'] ) : '',
			'advisory' => isset( $track['advisory'] ) && in_array( $track['advisory'], array( 'none', 'clean', 'explicit' ), true ) ? $track['advisory'] : 'none',
			'primary_genre' => isset( $track['primary_genre'] ) && in_array( $track['primary_genre'], $genres, true ) ? $track['primary_genre'] : '',
			'secondary_genre' => isset( $track['secondary_genre'] ) && in_array( $track['secondary_genre'], $genres, true ) ? $track['secondary_genre'] : '',
			'credits' => array(
				'authors' => isset( $credits['authors'] ) ? sanitize_textarea_field( $credits['authors'] ) : '',
				'composers' => isset( $credits['composers'] ) ? sanitize_textarea_field( $credits['composers'] ) : '',
				'performers' => isset( $credits['performers'] ) ? sanitize_textarea_field( $credits['performers'] ) : '',
				'producers' => isset( $credits['producers'] ) ? sanitize_textarea_field( $credits['producers'] ) : '',
				'musicians' => isset( $credits['musicians'] ) ? sanitize_textarea_field( $credits['musicians'] ) : '',
			),
		);
	}
	return $clean;
}

/**
 * Canonical answers are maintained once here, rather than duplicated across
 * the old contract-specific FAQ homes.
 */
function trb_portal_register_guide_type() {
	register_post_type(
		'trb_guide',
		array(
			'labels' => array( 'name' => 'Guide Area Artisti', 'singular_name' => 'Guida Area Artisti' ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=trb_request',
			'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			// The guides remain non-public, but the private dashboard must search them.
			'exclude_from_search' => false,
		)
	);
}
add_action( 'init', 'trb_portal_register_guide_type', 6 );

function trb_portal_seed_guides() {
	return array(
		'nuova-release' => array(
			'title' => 'Come avviare e completare una nuova release',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'La sequenza corretta per singoli, EP e album: una pratica per ogni pubblicazione.',
			'content' => '<p>Ogni pubblicazione ha una pratica distinta. Non inviare file, brief o dati di release diverse nella stessa richiesta.</p><ol><li><strong>Compila i dati contrattuali di distribuzione.</strong> È il primo passaggio obbligatorio.</li><li><strong>Invia il materiale audio.</strong> DDS consegna il master pronto; gli altri profili il pre-master quando è prevista la lavorazione audio.</li><li><strong>Gestisci la copertina.</strong> Carica l’asset definitivo oppure, quando previsto, completa il brief grafico.</li><li><strong>Completa i dati editoriali e promozionali.</strong> Titolo, autori, featuring, testi e informazioni utili alla release.</li><li><strong>Attendi la verifica TRB.</strong> Solo una pratica completa può essere programmata.</li></ol><p>La valutazione demo è facoltativa e separata dalla pratica di pubblicazione.</p>',
		),
		'formati-audio' => array(
			'title' => 'Quale formato audio devo consegnare?',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'Requisiti aggiornati per master e pre-master destinati alla distribuzione.',
			'content' => '<p>Consegna file <strong>WAV o AIFF stereo a 48.000 Hz / 24 bit</strong>. È il formato di riferimento per pre-master e master destinati alla distribuzione sulle attuali piattaforme ad alta qualità.</p><ul><li>Non inviare MP3, M4A, file WhatsApp, screen recording o conversioni da streaming.</li><li>Non applicare normalizzazione, limiter aggiuntivi o conversioni dopo il master approvato.</li><li>Esporta il brano dall’inizio esatto, senza silenzi accidentali o code tagliate.</li><li>Per EP e album usa la stessa frequenza di campionamento e profondità bit su tutte le tracce.</li></ul><p>Quando il contratto include la lavorazione audio, consegna il <strong>pre-master</strong> nel medesimo formato e senza un master bus eccessivamente limitato.</p>',
		),
		'tempistiche-release' => array(
			'title' => 'Quanto tempo serve per pubblicare una release?',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'Tempi corretti per lavorazione, verifica e distribuzione.',
			'content' => '<p>Per fissare una data di uscita servono normalmente <strong>tre settimane dalla consegna completa</strong> del master e di tutti i materiali richiesti. La data non può essere confermata quando la pratica è incompleta.</p><ul><li>Il mastering, quando previsto, richiede normalmente <strong>2–3 giorni tecnici</strong>.</li><li>Ad agosto/Ferragosto e nel periodo di fine anno la finestra di distribuzione è di <strong>quattro settimane</strong>.</li><li>Correzioni tardive a audio, copertina, metadati, featuring o testi possono spostare la programmazione.</li></ul>',
		),
		'metadati-e-diritti' => array(
			'title' => 'Metadati, autori, featuring e titolarità: cosa verificare',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'I dati inviati per la distribuzione devono essere completi, coerenti e verificati.',
			'content' => '<p>Prima dell’invio verifica che titolo, nome d’arte, autori, featuring e titolari dei diritti siano corretti e definitivi.</p><ul><li>Indica tutti gli autori e gli aventi diritto coinvolti.</li><li>Un featuring deve essere concordato e scritto esattamente come verrà pubblicato.</li><li>Per sample, beat, basi o contenuti di terzi servono i diritti necessari prima della consegna.</li><li>Non modificare titolo, artista principale o crediti dopo l’avvio senza comunicarlo.</li></ul>',
		),
		'copertine' => array(
			'title' => 'Copertina: requisiti tecnici e brief grafico',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'Come preparare l’asset già pronto o il brief per la grafica inclusa.',
			'content' => '<p>La copertina deve essere quadrata, in <strong>RGB, 3.000 × 3.000 px, 300 DPI</strong>, senza elementi sfocati, bordi involontari o loghi di piattaforme. Titolo e nome d’arte devono coincidere con i metadati della release.</p><p>Per <strong>DDS e DDB</strong> viene richiesto l’upload della copertina definitiva conforme. Per <strong>DDB‑TRB e TRB</strong> viene richiesto il brief grafico collegato alla release: concept, riferimenti, atmosfera, testi e vincoli utili.</p>',
		),
		'spotify-apple' => array(
			'title' => 'Spotify e Apple Music: profili e pitching editoriale',
			'profiles' => array( 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'Informazioni necessarie per ottimizzazione e pitching editoriale.',
			'content' => '<p>DDB, DDB‑TRB e TRB includono l’ottimizzazione del profilo e la strategia di pitching editoriale su Spotify e Apple Music.</p><ul><li>Invia link corretti a profili artista, social e catalogo.</li><li>Spiega in modo concreto storia del brano, contesto, pubblico ed elementi distintivi.</li><li>Consegna i materiali in tempo: il pitching richiede una data programmata con anticipo sufficiente.</li></ul><p>Il pitching è una candidatura editoriale, non una promessa di inserimento in playlist o risultati specifici.</p>',
		),
		'knowledge-hub-avanzata' => array(
			'title' => 'Knowledge Hub avanzata: guide ed e-book',
			'profiles' => array( 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'Approfondimenti riservati ai percorsi DDB, DDB‑TRB e TRB.',
			'content' => '<p>La Knowledge Hub avanzata raccoglie guide, e-book, checklist e template su lancio, immagine, promozione, organizzazione del progetto e presenza digitale. È riservata ai profili DDB, DDB‑TRB e TRB.</p><p>Questi materiali non sostituiscono i passaggi obbligatori della release: servono a prepararsi meglio e a lavorare in modo più autonomo.</p>',
		),
	);
}

function trb_portal_maybe_seed_guides() {
	if ( get_option( 'trb_portal_guides_seeded_v1' ) ) {
		return;
	}

	foreach ( trb_portal_seed_guides() as $key => $guide ) {
		$guide_id = wp_insert_post( array( 'post_type' => 'trb_guide', 'post_status' => 'publish', 'post_title' => $guide['title'], 'post_excerpt' => $guide['excerpt'], 'post_content' => $guide['content'] ) );
		if ( ! is_wp_error( $guide_id ) ) {
			update_post_meta( $guide_id, '_trb_guide_key', $key );
			update_post_meta( $guide_id, '_trb_portal_profiles', $guide['profiles'] );
		}
	}

	update_option( 'trb_portal_guides_seeded_v1', time(), false );
}
add_action( 'init', 'trb_portal_maybe_seed_guides', 35 );

function trb_portal_request_catalogue() {
	return array(
		'cover' => array(
			'label'    => 'Richiedi la copertina ufficiale',
			'profiles' => array( 'ddb_trb', 'trb' ),
			'copy'     => 'Invia il brief creativo, le reference e le indicazioni utili per la copertina della release.',
		),
		'profile' => array(
			'label'    => 'Aggiorna i profili artista',
			'profiles' => array( 'ddb', 'ddb_trb', 'trb' ),
			'copy'     => 'Segnala biografia, immagini, link e materiali da aggiornare su Spotify e Apple Music.',
		),
		'demo' => array(
			'label'    => 'Invia un demo per valutazione',
			'profiles' => array( 'ddb', 'ddb_trb', 'trb' ),
			'copy'     => 'Richiedi una valutazione artistica e tecnica prima di avviare la release.',
		),
	);
}

/**
 * The single dynamic dashboard. Its content is generated from the user role,
 * so there are no duplicate homes or menus to maintain.
 */
function trb_portal_dashboard_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '<section class="trb-portal-login"><h2>Area Artisti TRB rec</h2><p>Accedi per consultare i materiali riservati al tuo profilo.</p><p><a class="trb-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Accedi all’area riservata</a></p></section>';
	}

	$profile = trb_portal_user_profile();
	if ( ! $profile && ! current_user_can( 'manage_options' ) ) {
		return '<section class="trb-portal-notice"><h2>Profilo in attivazione</h2><p>Il tuo accesso è in fase di configurazione. Riceverai conferma via e-mail appena il profilo sarà attivo.</p></section>';
	}

	$user      = wp_get_current_user();
	$profile   = $profile ? $profile : 'trb';
	$resources = trb_portal_get_resources( $profile );
	$requests  = trb_portal_request_catalogue();

	ob_start();
	?>
	<div class="trb-portal" data-profile="<?php echo esc_attr( $profile ); ?>">
		<header class="trb-portal__hero">
			<div>
				<p class="trb-portal__eyebrow">AREA ARTISTI TRB REC</p>
				<h1>Ciao <?php echo esc_html( $user->display_name ); ?>.</h1>
				<p>Il tuo spazio riservato per seguire la collaborazione, preparare le release e trovare ciò che ti serve.</p>
			</div>
			<div class="trb-portal__profile"><span>Il tuo profilo</span><strong><?php echo esc_html( trb_portal_profile_label( $profile ) ); ?></strong></div>
		</header>

		<nav class="trb-portal__nav" aria-label="Sezioni Area Artisti">
			<a href="#profilo">Profilo artista</a>
			<a href="#release">Le tue release</a>
			<a href="#demo">Valuta un demo</a>
			<a href="#risposte">Risposte rapide</a>
			<a href="#documenti">Procedure</a>
		</nav>

		<form class="trb-portal__search" method="get" action="<?php echo esc_url( get_permalink() ); ?>">
			<label class="screen-reader-text" for="trb-portal-search">Cerca nella Knowledge Hub</label>
			<input id="trb-portal-search" type="search" name="trb_search" value="<?php echo esc_attr( trb_portal_current_search() ); ?>" placeholder="Cerca procedure, requisiti e guide" />
			<button type="submit">Cerca</button>
		</form>

		<?php trb_portal_render_artist_profile_section(); ?>

		<section id="inizia" class="trb-portal__section trb-portal__start">
			<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">IL TUO PERCORSO</p><h2>Da dove iniziare</h2></div>
			<div class="trb-portal__steps">
				<article><span>01</span><h3>Aggiorna il profilo artista</h3><p>È il primo passaggio obbligatorio prima della prima pubblicazione.</p></article>
				<article><span>02</span><h3>Apri una pratica release</h3><p>Inserisci i dati della pubblicazione e di tutti i brani, una volta sola.</p></article>
				<article><span>03</span><h3>Completa la lavorazione</h3><p>Audio, copertina, materiali editoriali e verifica rimangono collegati alla stessa release.</p></article>
			</div>
		</section>

		<?php trb_portal_render_demo_section(); ?>
		<?php trb_portal_render_release_section(); ?>

		<?php trb_portal_render_resource_section( 'risposte', trb_portal_current_search() ? 'Risultati della ricerca' : 'Risposte rapide', trb_portal_current_search() ? 'Le risposte disponibili per il tuo profilo, direttamente in questa pagina.' : 'Le guide essenziali per preparare una release senza passaggi inutili.', $resources['trb_guide'] ); ?>
		<?php trb_portal_render_resource_section( 'documenti', 'Documenti e procedure', 'Le indicazioni aggiornate per gestire ogni fase della collaborazione.', $resources['docs'] ); ?>
		<?php trb_portal_render_resource_section( 'video', 'Video e formazione', 'Percorsi video selezionati per il tuo profilo.', $resources['video'] ); ?>
		<?php trb_portal_render_resource_section( 'download', 'Library e download', 'Manuali, e-book e materiali da conservare.', $resources['wpdmpro'] ); ?>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'trb_artist_portal', 'trb_portal_dashboard_shortcode' );

function trb_portal_render_artist_profile_section() {
	$fields = trb_portal_artist_profile_fields();
	$saved = isset( $_GET['trb_profile'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['trb_profile'] ) );
	$complete = trb_portal_artist_profile_is_complete();
	?>
	<section id="profilo" class="trb-portal__section trb-portal__profile-section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">PRIMO PASSAGGIO OBBLIGATORIO</p><h2>Aggiorna il profilo artista</h2><p>Compila questi dati prima di richiedere la prima pubblicazione. Li riuseremo per preparare correttamente le pratiche e, in futuro, i contratti.</p></div>
		<?php if ( $saved ) : ?><div class="trb-portal__message trb-portal__message--success">Profilo artista aggiornato.</div><?php endif; ?>
		<?php if ( ! $complete ) : ?><div class="trb-portal__message trb-portal__message--error">Prima di avviare la tua prima release completa tutti i campi obbligatori qui sotto, inclusa la biografia.</div><?php endif; ?>
		<form class="trb-portal__request-form trb-portal__profile-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="trb_portal_save_artist_profile" />
			<?php wp_nonce_field( 'trb_portal_save_artist_profile', 'trb_portal_profile_nonce' ); ?>
			<div class="trb-portal__field-grid">
				<?php foreach ( $fields as $key => $label ) : ?>
					<label><?php echo esc_html( $label ); ?><?php if ( ! in_array( $key, array( 'birth_place', 'vat_number' ), true ) ) : ?> <span aria-hidden="true">*</span><?php endif; ?>
						<input type="<?php echo 'email' === $key ? 'email' : ( 'birth_date' === $key ? 'date' : 'text' ); ?>" name="trb_artist_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( trb_portal_artist_profile_value( $key ) ); ?>" <?php echo ! in_array( $key, array( 'birth_place', 'vat_number' ), true ) ? 'required' : ''; ?> />
					</label>
				<?php endforeach; ?>
			</div>
			<label>Biografia artistica aggiornata <span aria-hidden="true">*</span><textarea name="trb_artist_bio" rows="9" required placeholder="Incolla qui la biografia aggiornata: non caricare un file."><?php echo esc_textarea( trb_portal_artist_profile_value( 'bio' ) ); ?></textarea><small>Inserisci testo copiato e incollato, pronto per essere usato nei materiali editoriali.</small></label>
			<div class="trb-portal__private-documents"><strong>Foto e documenti</strong><p>Le foto ad alta qualità (massimo 5) e i documenti anagrafici saranno caricati nel passaggio protetto della scheda profilo: non vengono pubblicati né usati come materiale pubblico.</p></div>
			<button class="trb-button" type="submit">Salva il profilo artista</button>
		</form>
	</section>
	<?php
}

function trb_portal_render_demo_section() {
	?>
	<section id="demo" class="trb-portal__section">
		<div class="trb-portal__demo"><p class="trb-portal__eyebrow">PRIMA DELLA RELEASE</p><h2>Vuoi una valutazione del demo?</h2><p>È un percorso facoltativo e resta sempre separato da una pratica di pubblicazione: puoi richiederlo in qualunque momento.</p><p><a class="trb-button trb-button--secondary" href="https://trbrec.com/form-valutazione" target="_blank" rel="noopener">Richiedi una valutazione demo</a></p></div>
	</section>
	<?php
}

function trb_portal_render_release_section() {
	$releases = trb_portal_user_releases();
	$types    = trb_portal_release_types();
	$genres   = trb_portal_genres();
	$status   = isset( $_GET['trb_release'] ) ? sanitize_key( wp_unslash( $_GET['trb_release'] ) ) : '';
	$complete = trb_portal_artist_profile_is_complete();
	?>
	<section id="release" class="trb-portal__section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">PUBBLICAZIONI</p><h2>Le tue release</h2><p>Una pratica contiene tutti i dati della pubblicazione. Prima aggiorni il profilo artista, poi inserisci metadati e brani: audio, copertina e promozione arriveranno solo all’interno della stessa pratica.</p></div>
		<?php if ( 'created' === $status ) : ?><div class="trb-portal__message trb-portal__message--success">Pratica creata. Ora puoi iniziare dai dati contrattuali della release.</div><?php endif; ?>
		<?php if ( 'profile_required' === $status ) : ?><div class="trb-portal__message trb-portal__message--error">Prima completa il tuo profilo artista. È obbligatorio prima della prima pratica.</div><?php endif; ?>
		<?php if ( 'invalid' === $status || 'error' === $status ) : ?><div class="trb-portal__message trb-portal__message--error">Controlla titolo, tipo di release, stato di pubblicazione e dati del primo brano, poi riprova.</div><?php endif; ?>
		<?php if ( ! $complete ) : ?>
			<div class="trb-portal__release-gate"><strong>Prima completa “Aggiorna il profilo artista”.</strong><p>Fino ad allora non puoi aprire la tua prima pratica di release.</p><a class="trb-button" href="#profilo">Completa il profilo artista</a></div>
		<?php else : ?>
		<div class="trb-portal__request-grid trb-portal__request-grid--release">
			<form class="trb-portal__request-form trb-portal__release-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-release-form>
				<input type="hidden" name="action" value="trb_portal_start_release" />
				<?php wp_nonce_field( 'trb_portal_start_release', 'trb_portal_release_nonce' ); ?>
				<fieldset><legend>1. Che tipo di pubblicazione stai preparando?</legend><div class="trb-portal__release-types"><?php foreach ( $types as $key => $type ) : ?><label><input type="radio" name="trb_release_type" value="<?php echo esc_attr( $key ); ?>" required data-catalogue="<?php echo ! empty( $type['catalogue'] ) ? '1' : '0'; ?>" data-min="<?php echo esc_attr( $type['min'] ); ?>" data-max="<?php echo esc_attr( $type['max'] ); ?>" /><span><strong><?php echo esc_html( $type['label'] ); ?></strong><small><?php echo esc_html( $type['range'] ); ?></small></span></label><?php endforeach; ?></div></fieldset>
				<fieldset><legend>2. La release è nuova o era già stata pubblicata?</legend><div class="trb-portal__radios"><label><input type="radio" name="trb_release_state" value="unreleased" required checked /> Inedita: non è mai stata distribuita prima</label><label><input type="radio" name="trb_release_state" value="previously_released" required /> Già precedentemente rilasciata</label></div><label class="trb-portal__original-date" hidden>Data di pubblicazione originale <small>solo se la release è edita e già precedentemente rilasciata</small><input type="date" name="trb_release_original_date" /></label></fieldset>
				<label class="trb-portal__release-title">Titolo della release principale <span aria-hidden="true">*</span><input type="text" name="trb_release_title" maxlength="160" required placeholder="Es. Titolo dell’EP o dell’album" /></label>
				<fieldset class="trb-portal__tracks"><legend>3. Aggiungi i brani della release</legend><p class="trb-portal__field-help">Inserisci subito il primo brano. Puoi poi aggiungere gli altri con il pulsante “+”. I nomi di artista principale e featuring devono essere persone/interpreti reali: etichette e società vanno nei crediti, non nel campo artista.</p><div data-tracks></div><button type="button" class="trb-button trb-button--secondary trb-portal__add-track" data-add-track>+ Aggiungi un altro brano</button></fieldset>
				<button class="trb-button" type="submit">Avvia nuova release</button>
			</form>
			<aside class="trb-portal__request-help"><h3>Prima di inviare</h3><div><strong>Dati completi, una volta sola</strong><p>Questi campi preparano la pratica e in seguito i dati contrattuali. Non inviare i singoli servizi separatamente.</p></div><div><strong>Crediti</strong><p>Elenca tutte le persone coinvolte. Se un nome o un ruolo non è chiaro, fermati e verificalo prima di inviare.</p></div><div><strong>Numero di brani</strong><p>La tipologia scelta mostra il limite corretto. Il controllo finale avviene prima della distribuzione.</p></div></aside>
		</div>
		<?php endif; ?>
		<?php if ( ! empty( $releases ) ) : ?>
			<div class="trb-portal__request-history"><h3>Le tue pratiche</h3><ul><?php foreach ( $releases as $release ) : $release_type = get_post_meta( $release->ID, '_trb_release_type', true ); ?><li><strong><?php echo esc_html( $release->post_title ); ?></strong><span><?php echo esc_html( isset( $types[ $release_type ] ) ? $types[ $release_type ]['label'] : 'Release' ); ?> · Dati contrattuali da completare</span></li><?php endforeach; ?></ul></div>
		<?php endif; ?>
	</section>
	<template id="trb-portal-track-template"><article class="trb-portal__track" data-track><header><strong>Brano <span data-track-number></span></strong><button type="button" class="trb-portal__remove-track" data-remove-track aria-label="Rimuovi brano">Rimuovi</button></header><div class="trb-portal__field-grid"><label>Titolo del brano <span aria-hidden="true">*</span><input type="text" name="trb_tracks[__INDEX__][title]" required maxlength="160" /></label><label>Featuring <small>facoltativo, solo se presente</small><input type="text" name="trb_tracks[__INDEX__][featuring]" maxlength="160" /></label><label>Durata <span aria-hidden="true">*</span><input type="text" name="trb_tracks[__INDEX__][duration]" required placeholder="es. 03:42" pattern="[0-9]{1,2}:[0-5][0-9]" /></label><label>Parental Advisory <span aria-hidden="true">*</span><select name="trb_tracks[__INDEX__][advisory]" required><option value="none">Nessuno</option><option value="clean">Clean</option><option value="explicit">Explicit</option></select></label><label>Genere musicale primario <span aria-hidden="true">*</span><select name="trb_tracks[__INDEX__][primary_genre]" required><option value="">Seleziona genere</option><?php foreach ( $genres as $genre ) : ?><option value="<?php echo esc_attr( $genre ); ?>"><?php echo esc_html( $genre ); ?></option><?php endforeach; ?></select></label><label>Genere musicale secondario <small>facoltativo</small><select name="trb_tracks[__INDEX__][secondary_genre]"><option value="">Nessuno</option><?php foreach ( $genres as $genre ) : ?><option value="<?php echo esc_attr( $genre ); ?>"><?php echo esc_html( $genre ); ?></option><?php endforeach; ?></select></label></div><fieldset class="trb-portal__credits"><legend>Crediti completi <button type="button" class="trb-portal__info" aria-label="Come compilare i crediti" data-credit-info>i</button></legend><p class="trb-portal__credit-info" hidden>Indica nome e cognome o nome d’arte di ogni persona e il suo ruolo. <strong>Autori</strong>: chi scrive testo o opera. <strong>Compositori</strong>: chi compone la musica. <strong>Interpreti</strong>: chi esegue/vocalizza. <strong>Produttori</strong>: chi cura la produzione. <strong>Musicisti</strong>: ogni strumentista partecipante. Non inserire etichette, studi o società come artisti.</p><div class="trb-portal__field-grid"><label>Autori<textarea name="trb_tracks[__INDEX__][credits][authors]" rows="3" required></textarea></label><label>Compositori<textarea name="trb_tracks[__INDEX__][credits][composers]" rows="3" required></textarea></label><label>Interpreti<textarea name="trb_tracks[__INDEX__][credits][performers]" rows="3" required></textarea></label><label>Produttori<textarea name="trb_tracks[__INDEX__][credits][producers]" rows="3" required></textarea></label><label>Singoli musicisti partecipanti<textarea name="trb_tracks[__INDEX__][credits][musicians]" rows="3" placeholder="Es. Mario Rossi — chitarra"></textarea></label></div></fieldset></article></template>
	<script>
	(function(){var form=document.querySelector('[data-release-form]');if(!form)return;var wrap=form.querySelector('[data-tracks]'),template=document.getElementById('trb-portal-track-template'),add=form.querySelector('[data-add-track]'),title=form.querySelector('.trb-portal__release-title'),date=form.querySelector('.trb-portal__original-date'),dateInput=date.querySelector('input'),typeInputs=form.querySelectorAll('input[name="trb_release_type"]'),stateInputs=form.querySelectorAll('input[name="trb_release_state"]');function renumber(){var tracks=wrap.querySelectorAll('[data-track]');tracks.forEach(function(track,index){track.querySelector('[data-track-number]').textContent=index+1;track.querySelectorAll('[name]').forEach(function(field){field.name=field.name.replace(/trb_tracks\\[\\d+\\]/,'trb_tracks['+index+']');});track.querySelector('[data-remove-track]').hidden=tracks.length===1;});var selected=form.querySelector('input[name="trb_release_type"]:checked');if(selected){var max=Number(selected.dataset.max||60);add.disabled=tracks.length>=max;add.textContent=tracks.length>=max?'Limite raggiunto':'+ Aggiungi un altro brano';}}function addTrack(){var index=wrap.querySelectorAll('[data-track]').length,html=template.innerHTML.replace(/__INDEX__/g,index);wrap.insertAdjacentHTML('beforeend',html);renumber();}function updateType(){var selected=form.querySelector('input[name="trb_release_type"]:checked'),catalogue=selected&&selected.dataset.catalogue==='1';title.hidden=!!catalogue;title.querySelector('input').required=!catalogue;if(catalogue){title.querySelector('input').value='';var old=form.querySelector('input[value="previously_released"]');old.checked=true;}renumber();}function updateState(){var old=form.querySelector('input[name="trb_release_state"]:checked').value==='previously_released';date.hidden=!old;dateInput.required=old;}add.addEventListener('click',addTrack);wrap.addEventListener('click',function(e){if(e.target.matches('[data-remove-track]')){e.target.closest('[data-track]').remove();renumber();}if(e.target.matches('[data-credit-info]')){var info=e.target.closest('.trb-portal__credits').querySelector('.trb-portal__credit-info');info.hidden=!info.hidden;}});typeInputs.forEach(function(input){input.addEventListener('change',updateType);});stateInputs.forEach(function(input){input.addEventListener('change',updateState);});addTrack();updateState();}());
	</script>
	<?php
}

/**
 * Every contractual artist enters the new dashboard after authentication.
 * This takes precedence over the legacy LoginWP DDS rule, which still points
 * to an old contract-specific home page.
 */
function trb_portal_redirect_artist_after_login( $redirect_to, $requested_redirect_to, $user ) {
	if ( $user instanceof WP_User && trb_portal_user_profile( $user ) ) {
		return home_url( '/area-artisti/' );
	}

	return $redirect_to;
}
add_filter( 'login_redirect', 'trb_portal_redirect_artist_after_login', 9999, 3 );

/**
 * LoginWP can replace the WordPress login_redirect value with an old
 * contract-specific destination. This runs after authentication and ends the
 * browser request for contractual artists, so the legacy rule cannot win.
 */
function trb_portal_force_artist_dashboard_after_login( $user_login, $user ) {
	if ( ! ( $user instanceof WP_User ) || ! trb_portal_user_profile( $user ) || wp_doing_ajax() || is_admin() ) {
		return;
	}

	wp_safe_redirect( home_url( '/area-artisti/' ) );
	exit;
}
add_action( 'wp_login', 'trb_portal_force_artist_dashboard_after_login', 9999, 2 );

function trb_portal_get_resources( $profile ) {
	$resources = array();
	$search    = trb_portal_current_search();
	foreach ( trb_portal_supported_resource_types() as $post_type ) {
		$args = array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $search ? 100 : 7,
				'meta_query'     => array(
					array(
						'key'     => '_trb_portal_profiles',
						'value'   => '"' . $profile . '"',
						'compare' => 'LIKE',
					),
				),
			);
		$query = new WP_Query( $args );
		$posts = $query->posts;
		if ( $search ) {
			$posts = array_values(
				array_filter(
					$posts,
					function( $post ) use ( $search ) {
						$haystack = wp_strip_all_tags( $post->post_title . ' ' . $post->post_excerpt . ' ' . $post->post_content );
						return false !== stripos( $haystack, $search );
					}
				)
			);
		}
		$resources[ $post_type ] = $posts;
	}

	return $resources;
}

function trb_portal_render_resource_section( $id, $title, $description, $posts ) {
	?>
	<section id="<?php echo esc_attr( $id ); ?>" class="trb-portal__section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">KNOWLEDGE HUB</p><h2><?php echo esc_html( $title ); ?></h2><p><?php echo esc_html( $description ); ?></p></div>
		<?php if ( empty( $posts ) ) : ?>
			<div class="trb-portal__empty"><p>Stiamo aggiornando questa sezione con nuovi contenuti riservati al tuo profilo.</p></div>
		<?php else : ?>
			<div class="trb-portal__cards">
				<?php foreach ( $posts as $post ) : ?>
					<?php if ( 'trb_guide' === $post->post_type ) : ?>
						<details class="trb-portal__card"><summary><p class="trb-portal__type">Guida Area Artisti</p><h3><?php echo esc_html( get_the_title( $post ) ); ?></h3><p><?php echo esc_html( $post->post_excerpt ); ?></p><span class="trb-portal__link">Leggi la risposta <span aria-hidden="true">↓</span></span></summary><div class="trb-portal__answer"><?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></details>
					<?php else : ?>
						<article class="trb-portal__card"><p class="trb-portal__type"><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></p><h3><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 22 ) ); ?></p><a class="trb-portal__link" href="<?php echo esc_url( get_permalink( $post ) ); ?>">Apri contenuto <span aria-hidden="true">→</span></a></article>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

function trb_portal_current_search() {
	return isset( $_GET['trb_search'] ) ? sanitize_text_field( wp_unslash( $_GET['trb_search'] ) ) : '';
}

function trb_portal_enqueue_assets() {
	if ( ! is_page() ) {
		return;
	}

	$post = get_post();
	if ( $post && has_shortcode( $post->post_content, 'trb_artist_portal' ) ) {
		$style_path    = get_template_directory() . '/assets/css/trb-artist-portal.css';
		$style_version = file_exists( $style_path ) ? (string) filemtime( $style_path ) : DOCY_VERSION;
		wp_enqueue_style( 'trb-artist-portal', get_template_directory_uri() . '/assets/css/trb-artist-portal.css', array(), $style_version );
	}
}
add_action( 'wp_enqueue_scripts', 'trb_portal_enqueue_assets', 30 );

/**
 * The artist dashboard uses its own quiet shell. The public Docy header and
 * its search are intentionally excluded from this one private page.
 */
function trb_portal_force_dashboard_template( $template ) {
	if ( ! is_page() ) {
		return $template;
	}

	$page = get_queried_object();
	if ( $page instanceof WP_Post && has_shortcode( $page->post_content, 'trb_artist_portal' ) ) {
		$portal_template = locate_template( 'template-artist-portal.php' );
		return $portal_template ? $portal_template : $template;
	}

	return $template;
}
add_filter( 'template_include', 'trb_portal_force_dashboard_template', 99 );

function trb_portal_body_class( $classes ) {
	if ( is_page() ) {
		$page = get_queried_object();
		if ( $page instanceof WP_Post && has_shortcode( $page->post_content, 'trb_artist_portal' ) ) {
			$classes[] = 'trb-artist-portal-shell';
		}
	}

	return $classes;
}
add_filter( 'body_class', 'trb_portal_body_class' );

/**
 * Create the dashboard page only once, preserving all legacy pages until the
 * migration is verified. It is intentionally not set as the site front page.
 */
function trb_portal_maybe_create_dashboard_page() {
	if ( get_option( 'trb_portal_dashboard_created' ) ) {
		return;
	}

	$existing = get_page_by_path( 'area-artisti' );
	if ( $existing ) {
		update_option( 'trb_portal_dashboard_created', (int) $existing->ID, false );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Area Artisti TRB rec',
			'post_name'    => 'area-artisti',
			'post_content' => '[trb_artist_portal]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);

	if ( ! is_wp_error( $page_id ) ) {
		update_option( 'trb_portal_dashboard_created', (int) $page_id, false );
	}
}
add_action( 'init', 'trb_portal_maybe_create_dashboard_page', 30 );

function trb_portal_noindex_private_area() {
	if ( is_page( get_option( 'trb_portal_dashboard_created' ) ) || is_singular( trb_portal_supported_resource_types() ) ) {
		echo "<meta name=\"robots\" content=\"noindex,nofollow\" />\n";
	}
}
add_action( 'wp_head', 'trb_portal_noindex_private_area', 1 );
