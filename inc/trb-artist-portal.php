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
			'label'      => 'DDS',
			'capability' => 'trb_portal_dds',
		),
		'ddb' => array(
			'role'       => 'artista_b',
			'label'      => 'DDB',
			'capability' => 'trb_portal_ddb',
		),
		'ddb_trb' => array(
			'role'       => 'artista_c',
			'label'      => 'DDB-TRB',
			'capability' => 'trb_portal_ddb_trb',
		),
		'trb' => array(
			'role'       => 'artista_d',
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
		if ( in_array( $profile['role'], (array) $user->roles, true ) ) {
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
		$role = get_role( $profile['role'] );
		if ( $role ) {
			$role->add_cap( 'trb_portal_access' );
			$role->add_cap( $profile['capability'] );
		}
	}
}
add_action( 'init', 'trb_portal_register_capabilities', 20 );

/**
 * Resource metadata: an administrator selects exactly which contracts can see
 * a document, video or download. A resource can belong to more than one group.
 */
function trb_portal_supported_resource_types() {
	return array( 'docs', 'video', 'wpdmpro' );
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
			<a href="#inizia">Da dove iniziare</a>
			<a href="#documenti">Documenti</a>
			<a href="#video">Video</a>
			<a href="#download">Download</a>
			<a href="#richieste">Le tue richieste</a>
		</nav>

		<section id="inizia" class="trb-portal__section trb-portal__start">
			<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">IL TUO PERCORSO</p><h2>Da dove iniziare</h2></div>
			<div class="trb-portal__steps">
				<article><span>01</span><h3>Prepara la release</h3><p>Consulta requisiti, formati e tempistiche prima dell’invio.</p></article>
				<article><span>02</span><h3>Invia il materiale</h3><p>Segui la procedura corretta per evitare ritardi nella lavorazione.</p></article>
				<article><span>03</span><h3>Segui il lancio</h3><p>Trova i materiali e le indicazioni utili per valorizzare la pubblicazione.</p></article>
			</div>
		</section>

		<?php trb_portal_render_resource_section( 'documenti', 'Documenti e procedure', 'Le indicazioni aggiornate per gestire ogni fase della collaborazione.', $resources['docs'] ); ?>
		<?php trb_portal_render_resource_section( 'video', 'Video e formazione', 'Percorsi video selezionati per il tuo profilo.', $resources['video'] ); ?>
		<?php trb_portal_render_resource_section( 'download', 'Library e download', 'Manuali, e-book e materiali da conservare.', $resources['wpdmpro'] ); ?>

		<section id="richieste" class="trb-portal__section">
			<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">SERVIZI DEL TUO PROFILO</p><h2>Richieste disponibili</h2></div>
			<div class="trb-portal__cards">
				<?php foreach ( $requests as $request ) : ?>
					<?php if ( ! trb_portal_user_can_access( $request['profiles'] ) ) { continue; } ?>
					<article class="trb-portal__card trb-portal__card--action"><h3><?php echo esc_html( $request['label'] ); ?></h3><p><?php echo esc_html( $request['copy'] ); ?></p><span class="trb-portal__coming-soon">In arrivo con la nuova area richieste</span></article>
				<?php endforeach; ?>
			</div>
		</section>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'trb_artist_portal', 'trb_portal_dashboard_shortcode' );

function trb_portal_get_resources( $profile ) {
	$resources = array();
	foreach ( trb_portal_supported_resource_types() as $post_type ) {
		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 6,
				'meta_query'     => array(
					array(
						'key'     => '_trb_portal_profiles',
						'value'   => '"' . $profile . '"',
						'compare' => 'LIKE',
					),
				),
			)
		);
		$resources[ $post_type ] = $query->posts;
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
					<article class="trb-portal__card"><p class="trb-portal__type"><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></p><h3><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 22 ) ); ?></p><a class="trb-portal__link" href="<?php echo esc_url( get_permalink( $post ) ); ?>">Apri contenuto <span aria-hidden="true">→</span></a></article>
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
