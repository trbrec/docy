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
			// WordPress stores these as artista_a/b/c/d. The longer names
			// shown in the users screen are display labels, not role slugs.
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

	if ( in_array( trb_portal_legacy_basic_role(), (array) $user->roles, true ) ) {
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

function trb_portal_legacy_basic_role() {
	return 'artisti_trb_basic';
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
		$role = get_role( $profile['role'] );
		if ( $role ) {
			$role->add_cap( 'trb_portal_access' );
			$role->add_cap( $profile['capability'] );
		}
	}
}
add_action( 'init', 'trb_portal_register_capabilities', 20 );

/**
 * The old TRB Basic profile is absorbed into TRB only through this protected
 * admin action. It never runs as a side-effect of deploying the theme.
 */
function trb_portal_add_transition_page() {
	add_submenu_page(
		'edit.php?post_type=trb_release',
		'Transizione TRB Basic',
		'Transizione TRB Basic',
		'manage_options',
		'trb-portal-transition',
		'trb_portal_render_transition_page'
	);
}
add_action( 'admin_menu', 'trb_portal_add_transition_page' );

function trb_portal_render_transition_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$users    = get_users( array( 'role' => trb_portal_legacy_basic_role(), 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
	$migrated = isset( $_GET['trb_migrated'] ) ? absint( $_GET['trb_migrated'] ) : 0;
	?>
	<div class="wrap"><h1>Transizione TRB Basic → TRB</h1>
		<p>Il vecchio profilo TRB Basic non verrà più usato. Questa azione sposta gli utenti elencati nel profilo TRB, mantenendo invariati account, password, contenuti e pratiche.</p>
		<?php if ( $migrated ) : ?><div class="notice notice-success"><p><?php echo esc_html( $migrated ); ?> utenti trasferiti in TRB.</p></div><?php endif; ?>
		<?php if ( empty( $users ) ) : ?>
			<p><strong>Nessun utente TRB Basic da trasferire.</strong></p>
		<?php else : ?>
			<table class="widefat striped"><thead><tr><th>Artista</th><th>E-mail</th></tr></thead><tbody><?php foreach ( $users as $user ) : ?><tr><td><?php echo esc_html( $user->display_name ); ?></td><td><?php echo esc_html( $user->user_email ); ?></td></tr><?php endforeach; ?></tbody></table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
				<input type="hidden" name="action" value="trb_portal_migrate_basic" />
				<?php wp_nonce_field( 'trb_portal_migrate_basic', 'trb_portal_transition_nonce' ); ?>
				<?php submit_button( 'Trasferisci ' . count( $users ) . ' utenti in TRB', 'primary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

function trb_portal_handle_basic_migration() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Non disponi dei permessi necessari.' );
	}

	check_admin_referer( 'trb_portal_migrate_basic', 'trb_portal_transition_nonce' );
	$users = get_users( array( 'role' => trb_portal_legacy_basic_role(), 'fields' => 'all' ) );
	$count = 0;

	foreach ( $users as $user ) {
		$user->set_role( trb_portal_profiles()['trb']['role'] );
		$count++;
	}

	wp_safe_redirect( add_query_arg( 'trb_migrated', $count, admin_url( 'edit.php?post_type=trb_release&page=trb-portal-transition' ) ) );
	exit;
}
add_action( 'admin_post_trb_portal_migrate_basic', 'trb_portal_handle_basic_migration' );

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

	// Every document, video and download belongs to the reserved portal. During
	// migration an untagged legacy item remains reachable to authenticated
	// artists, but is no longer exposed to the public internet.
	if ( empty( $profiles ) ) {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
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
 * A release is the central unit of the Area Artisti. Services are never
 * requested in isolation: each action belongs to a named release and is shown
 * only when its preceding step has been completed.
 */
function trb_portal_register_release_type() {
	register_post_type(
		'trb_release',
		array(
			'labels' => array(
				'name'          => 'Release Artisti',
				'singular_name' => 'Release Artista',
			),
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

/**
 * Canonical answers live in WordPress, not in copied FAQ pages. They are
 * editable from the dashboard and the same answer can serve many contracts.
 */
function trb_portal_register_guide_type() {
	register_post_type(
		'trb_guide',
		array(
			'labels' => array(
				'name'          => 'Guide Area Artisti',
				'singular_name' => 'Guida Area Artisti',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=trb_release',
			'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'exclude_from_search' => true,
		)
	);
}
add_action( 'init', 'trb_portal_register_guide_type', 6 );

function trb_portal_seed_guides() {
	return array(
		'nuova-release' => array(
			'title'    => 'Come avviare e completare una nuova release',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt'  => 'Una pratica per ogni singolo, EP o album: cosa fare, nell’ordine corretto.',
			'content'  => '<p>Ogni pubblicazione ha una pratica distinta. Non inviare file, brief o dati di release diverse nella stessa richiesta: il modo più rapido per evitare errori è aprire una pratica per ciascun singolo, EP o album.</p><ol><li><strong>Compila i dati contrattuali di distribuzione.</strong> È il primo passaggio obbligatorio per quella release.</li><li><strong>Invia il materiale audio.</strong> DDS consegna il master pronto; gli altri profili consegnano il pre-master quando è prevista la lavorazione audio.</li><li><strong>Gestisci la copertina.</strong> Carica l’asset definitivo oppure, quando previsto dal contratto, completa il brief grafico.</li><li><strong>Completa dati editoriali e promozionali.</strong> Titolo, autori, featuring, testi, profili e informazioni utili alla distribuzione.</li><li><strong>Attendi la verifica TRB.</strong> Solo dopo la verifica della pratica si procede alla programmazione.</li></ol><p>La valutazione di un demo è facoltativa e resta separata: non sostituisce la pratica di pubblicazione.</p>',
		),
		'formati-audio' => array(
			'title'    => 'Quale formato audio devo consegnare?',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt'  => 'Requisiti aggiornati per master e pre-master destinati alla distribuzione.',
			'content'  => '<p>Consegna file <strong>WAV o AIFF stereo a 48.000 Hz / 24 bit</strong>. Questo è il formato di riferimento per i pre-master e per i master destinati alla distribuzione, in linea con le attuali piattaforme ad alta qualità.</p><ul><li>Non inviare MP3, M4A, file WhatsApp, screen recording o conversioni da streaming.</li><li>Non applicare normalizzazione, limiter aggiuntivi o conversioni dopo il master approvato.</li><li>Esporta il brano dall’inizio esatto, senza silenzi accidentali o code tagliate.</li><li>Per un EP o album, usa la stessa frequenza di campionamento e profondità bit su tutte le tracce.</li></ul><p>Se il tuo contratto include la lavorazione audio, consegna il <strong>pre-master</strong> nel medesimo formato e lascia un margine tecnico adeguato: non inviare un file già schiacciato da limiter sul master bus.</p>',
		),
		'tempistiche-release' => array(
			'title'    => 'Quanto tempo serve per pubblicare una release?',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt'  => 'Tempi corretti per lavorazione, verifica e distribuzione.',
			'content'  => '<p>Per fissare una data di uscita servono normalmente <strong>tre settimane dalla consegna completa del master e di tutti i materiali richiesti</strong>. La data non può essere confermata quando la pratica è ancora incompleta.</p><ul><li>Il mastering, quando previsto, richiede normalmente <strong>2–3 giorni tecnici</strong>.</li><li>Ad agosto/Ferragosto e nel periodo di fine anno la finestra di distribuzione è di <strong>quattro settimane</strong>.</li><li>Correzioni tardive a audio, copertina, metadati, featuring o testi possono spostare la programmazione.</li></ul><p>Per evitare rinvii, apri la pratica prima di fissare comunicazioni, campagne o date pubbliche con terzi.</p>',
		),
		'metadati-e-diritti' => array(
			'title'    => 'Metadati, autori, featuring e titolarità: cosa verificare',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt'  => 'I dati inviati per la distribuzione devono essere completi, coerenti e verificati.',
			'content'  => '<p>Prima dell’invio verifica che il titolo, il nome d’arte, gli autori, gli eventuali featuring e i titolari dei diritti siano corretti e definitivi. Le piattaforme possono applicare regole proprie di formattazione, ma non possono correggere informazioni mancanti o dichiarazioni inesatte.</p><ul><li>Indica tutti gli autori e gli aventi diritto coinvolti, senza omissioni.</li><li>Un featuring o una collaborazione deve essere concordato e scritto esattamente come verrà pubblicato.</li><li>Se usi sample, beat, basi o contenuti di terzi, devi avere i diritti necessari prima della consegna.</li><li>Non cambiare titolo, artista principale o crediti dopo l’avvio senza comunicarlo: può richiedere una nuova verifica.</li></ul>',
		),
		'copertine' => array(
			'title'    => 'Copertina: requisiti tecnici e brief grafico',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt'  => 'Come preparare l’asset già pronto o il brief per la grafica inclusa.',
			'content'  => '<p>La copertina destinata alla distribuzione deve essere quadrata, in <strong>RGB, 3.000 × 3.000 px, 300 DPI</strong>, senza elementi sfocati, bordi involontari o loghi di piattaforme. Il titolo e il nome d’arte devono corrispondere esattamente ai metadati della release.</p><p>Per <strong>DDS e DDB</strong>, la pratica richiederà il caricamento della copertina definitiva conforme ai requisiti. Per <strong>DDB‑TRB e TRB</strong>, la pratica mostrerà invece il brief grafico collegato alla release: concept, riferimenti, atmosfera, testi da inserire e qualunque vincolo utile.</p><p>Non usare immagini, marchi o fotografie di terzi senza una licenza adeguata. Una copertina non conforme blocca la verifica della pratica.</p>',
		),
		'spotify-apple' => array(
			'title'    => 'Spotify e Apple Music: profili e pitching editoriale',
			'profiles' => array( 'ddb', 'ddb_trb', 'trb' ),
			'excerpt'  => 'Come preparare le informazioni necessarie per ottimizzazione e pitching.',
			'content'  => '<p>DDB, DDB‑TRB e TRB includono l’ottimizzazione del profilo e la strategia di pitching editoriale su Spotify e Apple Music. Per lavorare correttamente servono informazioni chiare sulla release, sul progetto artistico e sul piano di comunicazione.</p><ul><li>Invia link corretti a profili artista, social e catalogo già pubblicato.</li><li>Spiega in modo concreto storia del brano, contesto, pubblico e elementi distintivi.</li><li>Consegna i materiali entro i tempi della pratica: il pitching richiede che la release sia programmata con anticipo sufficiente.</li></ul><p>Il pitching è una candidatura editoriale, non una promessa di inserimento in playlist o risultati specifici.</p>',
		),
		'materiali-promozionali' => array(
			'title'    => 'Materiali promozionali: cosa preparare per tempo',
			'profiles' => array( 'ddb', 'ddb_trb', 'trb' ),
			'excerpt'  => 'Le informazioni utili per presentazione, profili e comunicazione della release.',
			'content'  => '<p>Raccogli i materiali quando avvii la release, non all’ultimo momento: biografia aggiornata, fotografie, link ai profili, testi, crediti, eventuali video e una descrizione chiara del progetto. Ogni asset deve essere coerente con nome d’arte, titolo e data programmata.</p><p>La pratica ti mostrerà soltanto i materiali previsti per il tuo contratto. Se una parte richiede valutazione editoriale o artistica, verrà indicata come tale e non come un diritto automatico.</p>',
		),
		'knowledge-hub-avanzata' => array(
			'title'    => 'Knowledge Hub avanzata: come usare guide ed e-book',
			'profiles' => array( 'ddb', 'ddb_trb', 'trb' ),
			'excerpt'  => 'Materiali di approfondimento riservati ai percorsi DDB, DDB‑TRB e TRB.',
			'content'  => '<p>La Knowledge Hub avanzata raccoglie e-book, checklist, template e guide di approfondimento su lancio, immagine, promozione, organizzazione del progetto e gestione della presenza digitale. È una biblioteca riservata ai profili DDB, DDB‑TRB e TRB.</p><p>Questi materiali non sostituiscono i passaggi obbligatori della pratica release: servono a prepararsi meglio e a lavorare in modo più autonomo. Ogni risorsa riporterà tema, ultimo aggiornamento e pubblico a cui è destinata.</p>',
		),
	);
}

function trb_portal_maybe_seed_guides() {
	if ( get_option( 'trb_portal_guides_seeded_v1' ) ) {
		return;
	}

	foreach ( trb_portal_seed_guides() as $key => $guide ) {
		$existing = get_posts(
			array(
				'post_type'      => 'trb_guide',
				'posts_per_page' => 1,
				'meta_key'       => '_trb_guide_key',
				'meta_value'     => $key,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			continue;
		}

		$guide_id = wp_insert_post(
			array(
				'post_type'    => 'trb_guide',
				'post_status'  => 'publish',
				'post_title'   => $guide['title'],
				'post_excerpt' => $guide['excerpt'],
				'post_content' => $guide['content'],
			)
		);

		if ( ! is_wp_error( $guide_id ) ) {
			update_post_meta( $guide_id, '_trb_guide_key', $key );
			update_post_meta( $guide_id, '_trb_portal_profiles', $guide['profiles'] );
		}
	}

	update_option( 'trb_portal_guides_seeded_v1', time(), false );
}
add_action( 'init', 'trb_portal_maybe_seed_guides', 35 );

function trb_portal_release_types() {
	return array(
		'single' => 'Singolo',
		'ep'     => 'EP',
		'album'  => 'Album',
	);
}

function trb_portal_release_steps( $profile ) {
	$audio = array(
		'title' => 'File audio',
		'copy'  => 'Invia il master definitivo nel formato richiesto per la distribuzione.',
	);

	if ( in_array( $profile, array( 'ddb', 'ddb_trb', 'trb' ), true ) ) {
		$audio = array(
			'title' => 'Pre-master e lavorazione audio',
			'copy'  => 'Invia il pre-master nel formato richiesto: la lavorazione prevista dal tuo contratto sarà associata a questa release.',
		);
	}

	$cover = array(
		'title' => 'Copertina della release',
		'copy'  => 'Carica la copertina definitiva rispettando i requisiti tecnici richiesti per la distribuzione.',
	);

	if ( in_array( $profile, array( 'ddb_trb', 'trb' ), true ) ) {
		$cover = array(
			'title' => 'Brief grafico e copertina',
			'copy'  => 'Completa il brief della release: la richiesta della copertina sarà collegata direttamente a questa pratica.',
		);
	}

	return array(
		'contract' => array(
			'title' => 'Dati contrattuali di distribuzione',
			'copy'  => 'Primo passaggio obbligatorio: compila i dati contrattuali relativi a questa specifica release.',
		),
		'audio' => $audio,
		'cover' => $cover,
		'promo' => array(
			'title' => 'Dati editoriali e promozionali',
			'copy'  => 'Raccogli le informazioni utili alla distribuzione, alla presentazione e alla promozione della release.',
		),
		'review' => array(
			'title' => 'Verifica finale',
			'copy'  => 'TRB verifica la completezza della pratica prima della programmazione della pubblicazione.',
		),
	);
}

function trb_portal_release_statuses() {
	return array(
		'artist_action' => 'In attesa dell’artista',
		'trb_review'    => 'In verifica TRB',
		'programmed'     => 'Programmata',
		'complete'       => 'Completata',
	);
}

function trb_portal_add_release_metaboxes() {
	add_meta_box(
		'trb-portal-release-status',
		'Stato pratica Area Artisti',
		'trb_portal_render_release_metabox',
		'trb_release',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_trb_release', 'trb_portal_add_release_metaboxes' );

function trb_portal_render_release_metabox( $post ) {
	$profile = get_post_meta( $post->ID, '_trb_release_profile', true );
	$step    = get_post_meta( $post->ID, '_trb_release_step', true );
	$status  = get_post_meta( $post->ID, '_trb_release_status', true );
	$steps   = trb_portal_release_steps( $profile ? $profile : 'dds' );
	$states  = trb_portal_release_statuses();

	wp_nonce_field( 'trb_portal_release_status', 'trb_portal_release_status_nonce' );
	?>
	<p><strong>Profilo:</strong> <?php echo esc_html( trb_portal_profile_label( $profile ) ); ?></p>
	<p><label for="trb-release-step"><strong>Passaggio corrente</strong></label><br />
	<select class="widefat" id="trb-release-step" name="trb_release_step">
		<?php foreach ( $steps as $key => $item ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $step, $key ); ?>><?php echo esc_html( $item['title'] ); ?></option>
		<?php endforeach; ?>
	</select></p>
	<p><label for="trb-release-status"><strong>Stato visibile all’artista</strong></label><br />
	<select class="widefat" id="trb-release-status" name="trb_release_status">
		<?php foreach ( $states as $key => $label ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status ? $status : 'artist_action', $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select></p>
	<p class="description">Aggiorna qui lo stato soltanto dopo avere verificato il materiale ricevuto.</p>
	<?php
}

function trb_portal_save_release_status( $post_id ) {
	if ( ! isset( $_POST['trb_portal_release_status_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trb_portal_release_status_nonce'] ) ), 'trb_portal_release_status' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$profile = get_post_meta( $post_id, '_trb_release_profile', true );
	$steps   = trb_portal_release_steps( $profile ? $profile : 'dds' );
	$states  = trb_portal_release_statuses();
	$step    = isset( $_POST['trb_release_step'] ) ? sanitize_key( wp_unslash( $_POST['trb_release_step'] ) ) : '';
	$status  = isset( $_POST['trb_release_status'] ) ? sanitize_key( wp_unslash( $_POST['trb_release_status'] ) ) : '';

	if ( isset( $steps[ $step ] ) ) {
		update_post_meta( $post_id, '_trb_release_step', $step );
	}
	if ( isset( $states[ $status ] ) ) {
		update_post_meta( $post_id, '_trb_release_status', $status );
	}
}
add_action( 'save_post_trb_release', 'trb_portal_save_release_status' );

function trb_portal_user_releases( $limit = 12 ) {
	return get_posts(
		array(
			'post_type'      => 'trb_release',
			'post_status'    => array( 'publish', 'draft' ),
			'author'         => get_current_user_id(),
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
}

function trb_portal_handle_release_start() {
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}

	check_admin_referer( 'trb_portal_start_release', 'trb_portal_release_nonce' );

	$title = isset( $_POST['trb_release_title'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_release_title'] ) ) : '';
	$type  = isset( $_POST['trb_release_type'] ) ? sanitize_key( wp_unslash( $_POST['trb_release_type'] ) ) : '';
	$types = trb_portal_release_types();
	$back  = wp_get_referer() ? wp_get_referer() : home_url( '/area-artisti/' );

	$profile = trb_portal_user_profile();
	if ( ! $profile && current_user_can( 'manage_options' ) ) {
		$profile = 'trb';
	}

	if ( empty( $title ) || empty( $types[ $type ] ) || ! $profile ) {
		wp_safe_redirect( add_query_arg( 'trb_release', 'invalid', $back ) . '#release' );
		exit;
	}

	$release_id = wp_insert_post(
		array(
			'post_type'    => 'trb_release',
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
			'post_title'   => $title,
		)
	);

	if ( ! is_wp_error( $release_id ) ) {
		update_post_meta( $release_id, '_trb_release_type', $type );
		update_post_meta( $release_id, '_trb_release_step', 'contract' );
		update_post_meta( $release_id, '_trb_release_profile', $profile );
		update_post_meta( $release_id, '_trb_release_status', 'artist_action' );
	}

	$redirect = add_query_arg( 'trb_release', is_wp_error( $release_id ) ? 'error' : 'created', $back );
	if ( ! is_wp_error( $release_id ) ) {
		$redirect = add_query_arg( 'trb_release_id', (int) $release_id, $redirect );
	}
	wp_safe_redirect( $redirect . '#release' );
	exit;
}
add_action( 'admin_post_trb_portal_start_release', 'trb_portal_handle_release_start' );

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

		<form class="trb-portal__search" method="get" action="<?php echo esc_url( get_permalink() ); ?>">
			<label for="trb-portal-search" class="screen-reader-text">Cerca nella tua Area Artisti</label>
			<input id="trb-portal-search" type="search" name="trb_search" value="<?php echo esc_attr( trb_portal_current_search() ); ?>" placeholder="Cerca procedure, guide e materiali riservati" />
			<button type="submit">Cerca</button>
		</form>

		<section id="inizia" class="trb-portal__section trb-portal__start">
			<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">IL TUO PERCORSO</p><h2>Ogni pubblicazione segue una pratica</h2><p>Prima i dati contrattuali della release, poi audio, copertina, materiali editoriali e verifica finale.</p></div>
			<div class="trb-portal__steps">
				<article><span>01</span><h3>Apri la pratica</h3><p>Indica titolo e formato della nuova release.</p></article>
				<article><span>02</span><h3>Completa i passaggi</h3><p>Ogni azione si sblocca al momento corretto.</p></article>
				<article><span>03</span><h3>Segui lo stato</h3><p>Trova sempre ciò che manca e il punto in cui si trova TRB.</p></article>
			</div>
		</section>

		<?php trb_portal_render_release_section( $profile ); ?>
		<?php trb_portal_render_profile_services( $profile ); ?>
		<?php trb_portal_render_demo_section(); ?>
		<?php if ( trb_portal_current_search() ) : ?>
			<?php trb_portal_render_search_results( $resources ); ?>
		<?php else : ?>
			<?php trb_portal_render_resource_section( 'risposte', 'Guide e procedure', 'Risposte aggiornate, chiare e riservate al tuo profilo.', $resources['trb_guide'] ); ?>
			<?php if ( ! empty( $resources['docs'] ) ) : ?>
				<?php trb_portal_render_resource_section( 'documenti', 'Documenti e procedure', 'Le procedure di dettaglio riservate al tuo profilo.', $resources['docs'] ); ?>
			<?php endif; ?>
			<?php if ( ! empty( $resources['video'] ) ) : ?>
				<?php trb_portal_render_resource_section( 'video', 'Video e formazione', 'Percorsi video selezionati per il tuo profilo.', $resources['video'] ); ?>
			<?php endif; ?>
			<?php if ( ! empty( $resources['wpdmpro'] ) ) : ?>
				<?php trb_portal_render_resource_section( 'download', 'Library e download', 'Manuali, e-book e materiali da conservare.', $resources['wpdmpro'] ); ?>
			<?php endif; ?>
		<?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
}

function trb_portal_profile_services( $profile ) {
	$catalogue = array(
		'dds' => array(
			array( 'title' => 'Distribuzione e codici', 'copy' => 'La pratica raccoglie i dati necessari a distribuzione, metadati e assegnazione dei codici della release.' ),
			array( 'title' => 'Network editoriale', 'copy' => 'Ogni pubblicazione idonea viene presa in carico nel network editoriale previsto dal tuo percorso.' ),
			array( 'title' => 'Knowledge Hub essenziale', 'copy' => 'Procedure, requisiti tecnici e indicazioni indispensabili per preparare correttamente le release.' ),
		),
		'ddb' => array(
			array( 'title' => 'Mastering e post-produzione', 'copy' => 'La pratica raccoglie il pre-master e i materiali necessari alla lavorazione audio prevista.' ),
			array( 'title' => 'Spotify e Apple Music', 'copy' => 'Profilo, pitch editoriale e informazioni strategiche vengono gestiti insieme ai dati della release.' ),
			array( 'title' => 'Asset e lancio', 'copy' => 'Social Media Kit, Promo Cards, Smartlink e Digital Press Kit si collegano alla release verificata.' ),
		),
		'ddb_trb' => array(
			array( 'title' => 'Audio e copertina inclusa', 'copy' => 'Pre-master, lavorazione prevista e brief grafico entrano nella stessa pratica di release.' ),
			array( 'title' => 'Promozione avanzata', 'copy' => 'I materiali completi alimentano le attività editoriali, promozionali e mediatiche previste per il progetto.' ),
			array( 'title' => 'Percorso verso il roster', 'copy' => 'Il percorso biennale tiene traccia dello sviluppo fino all’inserimento garantito nel roster TRB rec.' ),
		),
		'trb' => array(
			array( 'title' => 'Gestione release completa', 'copy' => 'Audio, copertina, dati editoriali e materiali promozionali sono coordinati all’interno della pratica.' ),
			array( 'title' => 'Sviluppo e promozione', 'copy' => 'La dashboard raccoglie i materiali utili alle attività editoriali, promozionali e mediatiche previste.' ),
			array( 'title' => 'Roster TRB rec', 'copy' => 'Il tuo spazio accompagna una collaborazione discografica ed editoriale a tempo indeterminato.' ),
		),
	);

	return isset( $catalogue[ $profile ] ) ? $catalogue[ $profile ] : array();
}

function trb_portal_render_profile_services( $profile ) {
	$services = trb_portal_profile_services( $profile );
	?>
	<section id="profilo" class="trb-portal__section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">IL TUO PERCORSO <?php echo esc_html( trb_portal_profile_label( $profile ) ); ?></p><h2>Cosa accompagna le tue release</h2><p>Questi elementi sono parte del tuo percorso e vengono gestiti all’interno della singola pratica, non come richieste scollegate.</p></div>
		<div class="trb-portal__service-grid">
			<?php foreach ( $services as $service ) : ?>
				<article><h3><?php echo esc_html( $service['title'] ); ?></h3><p><?php echo esc_html( $service['copy'] ); ?></p></article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

function trb_portal_render_release_section( $profile ) {
	$releases = trb_portal_user_releases();
	$status   = isset( $_GET['trb_release'] ) ? sanitize_key( wp_unslash( $_GET['trb_release'] ) ) : '';
	$types    = trb_portal_release_types();
	$selected = isset( $_GET['trb_release_id'] ) ? absint( $_GET['trb_release_id'] ) : 0;
	?>
	<section id="release" class="trb-portal__section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">PUBBLICAZIONI</p><h2>Le tue release</h2><p>Apri una pratica per ogni pubblicazione. I servizi e i form compariranno soltanto all’interno della relativa release.</p></div>
		<?php if ( 'created' === $status ) : ?>
			<div class="trb-portal__message trb-portal__message--success">Pratica creata. Il primo passaggio è la compilazione dei dati contrattuali di distribuzione.</div>
		<?php elseif ( 'invalid' === $status || 'error' === $status ) : ?>
			<div class="trb-portal__message trb-portal__message--error">Inserisci titolo e formato della release, poi riprova.</div>
		<?php endif; ?>
		<div class="trb-portal__request-grid">
			<form class="trb-portal__request-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="trb_portal_start_release" />
				<?php wp_nonce_field( 'trb_portal_start_release', 'trb_portal_release_nonce' ); ?>
				<label>Titolo provvisorio o definitivo
					<input type="text" name="trb_release_title" maxlength="160" required placeholder="Es. Titolo del singolo" />
				</label>
				<label>Formato
					<select name="trb_release_type" required>
						<option value="">Seleziona</option>
						<?php foreach ( $types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
					</select>
				</label>
				<button class="trb-button" type="submit">Avvia nuova release</button>
			</form>
			<aside class="trb-portal__request-help">
				<h3>Come funziona</h3>
				<div><strong>Una release alla volta</strong><p>Ogni invio è tracciato nella sua pratica, senza mescolare copertine, file o materiali di pubblicazioni diverse.</p></div>
				<div><strong>Servizi corretti</strong><p>La pratica riconosce il tuo contratto e mostrerà solo i passaggi che ti riguardano.</p></div>
			</aside>
		</div>
		<?php if ( ! empty( $releases ) ) : ?>
			<div class="trb-portal__request-history trb-portal__release-history"><h3>Le tue pratiche</h3><div class="trb-portal__release-list">
				<?php foreach ( $releases as $release ) : ?>
					<?php $current_step = get_post_meta( $release->ID, '_trb_release_step', true ); $steps = trb_portal_release_steps( $profile ); $release_status = get_post_meta( $release->ID, '_trb_release_status', true ); ?>
					<article<?php echo $selected === (int) $release->ID ? ' class="is-active"' : ''; ?>><p><?php echo esc_html( isset( $types[ get_post_meta( $release->ID, '_trb_release_type', true ) ] ) ? $types[ get_post_meta( $release->ID, '_trb_release_type', true ) ] : 'Release' ); ?></p><h4><?php echo esc_html( $release->post_title ); ?></h4><strong>Adesso: <?php echo esc_html( isset( $steps[ $current_step ] ) ? $steps[ $current_step ]['title'] : 'In verifica' ); ?></strong><span><?php echo esc_html( isset( trb_portal_release_statuses()[ $release_status ] ) ? trb_portal_release_statuses()[ $release_status ] : trb_portal_release_statuses()['artist_action'] ); ?></span><a class="trb-portal__link" href="<?php echo esc_url( add_query_arg( 'trb_release_id', $release->ID, get_permalink() ) . '#release' ); ?>">Apri pratica <span aria-hidden="true">→</span></a></article>
				<?php endforeach; ?>
			</div></div>
			<?php if ( $selected ) : ?>
				<?php $selected_release = get_post( $selected ); ?>
				<?php if ( $selected_release && 'trb_release' === $selected_release->post_type && ( (int) $selected_release->post_author === get_current_user_id() || current_user_can( 'manage_options' ) ) ) : ?>
					<?php trb_portal_render_release_detail( $selected_release, $profile ); ?>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
	</section>
	<?php
}

function trb_portal_render_release_detail( $release, $profile ) {
	$steps         = trb_portal_release_steps( $profile );
	$current_step  = get_post_meta( $release->ID, '_trb_release_step', true );
	$current_index = array_search( $current_step, array_keys( $steps ), true );
	$current_index = false === $current_index ? count( $steps ) - 1 : $current_index;
	$statuses      = trb_portal_release_statuses();
	$status        = get_post_meta( $release->ID, '_trb_release_status', true );
	?>
	<div class="trb-portal__release-detail">
		<p class="trb-portal__eyebrow">PRATICA APERTA</p>
		<h3><?php echo esc_html( $release->post_title ); ?></h3>
		<p class="trb-portal__release-status"><?php echo esc_html( isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['artist_action'] ); ?></p>
		<ol class="trb-portal__timeline">
			<?php $index = 0; foreach ( $steps as $key => $step ) : ?>
				<?php $state = $index < $current_index ? 'is-complete' : ( $index === $current_index ? 'is-current' : 'is-locked' ); ?>
				<li class="<?php echo esc_attr( $state ); ?>"><span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><div><strong><?php echo esc_html( $step['title'] ); ?></strong><p><?php echo esc_html( $step['copy'] ); ?></p><?php if ( $index === $current_index ) : ?><small>Il form collegato a questo passaggio sarà disponibile qui, senza uscire dalla pratica.</small><?php endif; ?></div></li>
			<?php $index++; endforeach; ?>
		</ol>
	</div>
	<?php
}

function trb_portal_render_demo_section() {
	?>
	<section id="demo" class="trb-portal__section trb-portal__demo">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">PRIMA DELLA RELEASE</p><h2>Vuoi una valutazione del demo?</h2><p>È un percorso facoltativo e resta separato dalla pratica di pubblicazione.</p></div>
		<a class="trb-button trb-button--secondary" href="https://trbrec.com/form-valutazione">Richiedi una valutazione demo</a>
	</section>
	<?php
}
add_shortcode( 'trb_artist_portal', 'trb_portal_dashboard_shortcode' );

function trb_portal_current_search() {
	return isset( $_GET['trb_search'] ) ? sanitize_text_field( wp_unslash( $_GET['trb_search'] ) ) : '';
}

function trb_portal_get_resources( $profile ) {
	$resources = array();
	$search    = trb_portal_current_search();
	foreach ( trb_portal_supported_resource_types() as $post_type ) {
		$args = array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $search ? 18 : 6,
				'meta_query'     => array(
					array(
						'key'     => '_trb_portal_profiles',
						'value'   => '"' . $profile . '"',
						'compare' => 'LIKE',
					),
				),
			);

		if ( $search ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );
		$resources[ $post_type ] = $query->posts;
	}

	return $resources;
}

function trb_portal_render_resource_section( $id, $title, $description, $posts ) {
	if ( empty( $posts ) ) {
		return;
	}
	?>
	<section id="<?php echo esc_attr( $id ); ?>" class="trb-portal__section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">KNOWLEDGE HUB</p><h2><?php echo esc_html( $title ); ?></h2><p><?php echo esc_html( $description ); ?></p></div>
		<div class="trb-portal__cards trb-portal__answers">
			<?php foreach ( $posts as $post ) : ?>
				<details class="trb-portal__card">
					<summary>
						<p class="trb-portal__type"><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></p>
						<h3><?php echo esc_html( get_the_title( $post ) ); ?></h3>
						<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 22 ) ); ?></p>
						<span class="trb-portal__link">Leggi la risposta <span aria-hidden="true">↓</span></span>
					</summary>
					<div class="trb-portal__answer"><?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</details>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

function trb_portal_render_search_results( $resources ) {
	$search  = trb_portal_current_search();
	$results = array();
	foreach ( $resources as $posts ) {
		foreach ( $posts as $post ) {
			$results[ $post->ID ] = $post;
		}
	}
	?>
	<section id="risultati" class="trb-portal__section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">RISPOSTE NELLA STESSA PAGINA</p><h2>Risultati per “<?php echo esc_html( $search ); ?>”</h2><p>Mostriamo solo contenuti aggiornati e disponibili per il tuo profilo contrattuale.</p></div>
		<?php if ( empty( $results ) ) : ?>
			<div class="trb-portal__empty"><p>Non abbiamo trovato una risposta precisa. Prova con parole più semplici, ad esempio “audio”, “copertina”, “tempistiche” o “Spotify”.</p></div>
		<?php else : ?>
			<div class="trb-portal__cards trb-portal__answers trb-portal__answers--search">
				<?php foreach ( $results as $post ) : ?>
					<details class="trb-portal__card" open>
						<summary>
							<p class="trb-portal__type"><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></p>
							<h3><?php echo esc_html( get_the_title( $post ) ); ?></h3>
							<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 22 ) ); ?></p>
							<span class="trb-portal__link">Leggi la risposta <span aria-hidden="true">↓</span></span>
						</summary>
						<div class="trb-portal__answer"><?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					</details>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

function trb_portal_enqueue_assets() {
	if ( ! is_page() ) {
		return;
	}

	$post = get_post();
	if ( $post && has_shortcode( $post->post_content, 'trb_artist_portal' ) ) {
		wp_enqueue_style( 'trb-artist-portal', get_template_directory_uri() . '/assets/css/trb-artist-portal.css', array(), DOCY_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'trb_portal_enqueue_assets', 30 );

/**
 * The portal has its own quiet shell. Enforce it even if the WordPress page
 * template was changed from the page editor in the past.
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
		update_post_meta( $existing->ID, '_wp_page_template', 'template-artist-portal.php' );
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
		update_post_meta( $page_id, '_wp_page_template', 'template-artist-portal.php' );
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

