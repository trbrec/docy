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

/**
 * The contractual profile and the brand under which it belongs are distinct.
 * DDS, DDB and DDB-TRB remain Digital Distribution Bundle; TRB is TRB rec.
 */
function trb_portal_profile_affiliation( $profile = null ) {
	$profile = $profile ? $profile : trb_portal_user_profile();

	return 'trb' === $profile ? 'TRB rec - Music Publishing' : 'Digital Distribution Bundle';
}

/** Keep brand assets portable when the canonical host changes. */
function trb_portal_logo_url() {
	return content_url( '/uploads/2023/08/Vector-TRB-rec-White.png' );
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

/**
 * Preserve the legacy Video entries after retiring Docy Core.
 * Docy Core registered this post type only as part of its Elementor bundle;
 * the Artist Portal needs the data, not that page-builder dependency.
 */
function trb_portal_register_legacy_video_type() {
	if ( post_type_exists( 'video' ) ) {
		return;
	}

	register_post_type(
		'video',
		array(
			'labels'       => array( 'name' => 'Video', 'singular_name' => 'Video' ),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'show_in_rest' => true,
		)
	);
}
add_action( 'init', 'trb_portal_register_legacy_video_type', 9 );

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

/** Direct links obey the same audience policy as the private hub. */
function trb_portal_protect_tagged_resource() {
	if ( ! is_singular( trb_portal_supported_resource_types() ) || current_user_can( 'manage_options' ) ) {
		return;
	}

	$post_id  = get_queried_object_id();
	$profiles = trb_portal_resource_profiles( $post_id );

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/accedi/' ), 302 );
		exit;
	}

	// Untagged legacy files remain stored, but are never public by accident.
	if ( empty( $profiles ) || ! trb_portal_user_can_access( $profiles ) ) {
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
		'phone'       => 'Cellulare abilitato a ricezione SMS',
		'birth_date'  => 'Data di nascita',
		'birth_place' => 'Luogo di nascita',
		'birth_province' => 'Provincia di nascita',
		'tax_code'    => 'Codice fiscale',
		'street'      => 'Indirizzo di residenza',
		'street_number' => 'Numero civico',
		'city'        => 'Città',
		'postal_code' => 'CAP',
		'province'    => 'Provincia',
		'country'     => 'Nazione',
		'company_name'    => 'Ragione sociale',
		'company_vat'     => 'Partita IVA',
		'company_sdi'     => 'Codice SDI',
		'company_address' => 'Indirizzo della sede aziendale',
		'spotify_url'     => 'Profilo Spotify',
		'apple_music_url' => 'Profilo Apple Music',
		'youtube_url'     => 'Canale YouTube',
		'soundcloud_url'  => 'Profilo SoundCloud',
		'facebook_url'    => 'Facebook',
		'instagram_url'   => 'Instagram',
		'linkedin_url'    => 'LinkedIn',
		'tiktok_url'      => 'TikTok',
		'discord_url'     => 'Discord',
		'twitch_url'      => 'Twitch',
		'x_url'           => 'X',
		'snapchat_url'    => 'Snapchat',
		'threads_url'     => 'Threads',
		'live_fee'        => 'Cachet per esibizioni live o DJ set',
	);
}

function trb_portal_artist_profile_value( $key, $user_id = 0 ) {
	return (string) get_user_meta( $user_id ? $user_id : get_current_user_id(), '_trb_artist_' . $key, true );
}

/** Resolve an Italian postcode locally, without transmitting profile data. */
function trb_portal_lookup_postcode( $postcode ) {
	$postcode = preg_replace( '/\D+/', '', (string) $postcode );
	if ( 5 !== strlen( $postcode ) ) return new WP_Error( 'invalid_postcode', 'Inserisci un CAP italiano di 5 cifre.' );
	static $archive = null;
	if ( null === $archive ) {
		$file = get_template_directory() . '/assets/data/italian-postcodes.json';
		$data = file_exists( $file ) ? json_decode( file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$archive = isset( $data['postcodes'] ) && is_array( $data['postcodes'] ) ? $data['postcodes'] : array();
	}
	$places = isset( $archive[ $postcode ] ) ? $archive[ $postcode ] : array();
	if ( empty( $places ) ) return new WP_Error( 'postcode_not_found', 'CAP non trovato nell’archivio nazionale.' );
	return $places;
}

function trb_portal_territorial_archive() {
	static $archive = null;
	if ( null === $archive ) {
		$postcodes_file = get_template_directory() . '/assets/data/italian-postcodes.json';
		$municipalities_file = get_template_directory() . '/assets/data/italian-municipalities.json';
		$postcodes = file_exists( $postcodes_file ) ? json_decode( file_get_contents( $postcodes_file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$municipalities = file_exists( $municipalities_file ) ? json_decode( file_get_contents( $municipalities_file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$archive = is_array( $postcodes ) ? $postcodes : array();
		$archive['municipalities'] = array();
		foreach ( isset( $municipalities['municipalities'] ) ? $municipalities['municipalities'] : array() as $item ) {
			$parts = explode( '|', $item, 2 );
			if ( 2 === count( $parts ) ) $archive['municipalities'][] = array( 'city' => $parts[0], 'province' => $parts[1] );
		}
	}
	return $archive;
}

function trb_portal_find_municipalities( $search ) {
	$search = strtolower( remove_accents( trim( (string) $search ) ) );
	if ( strlen( $search ) < 2 ) return array();
	$archive = trb_portal_territorial_archive();
	$results = array();
	foreach ( isset( $archive['municipalities'] ) ? $archive['municipalities'] : array() as $municipality ) {
		$name = strtolower( remove_accents( $municipality['city'] ) );
		if ( 0 === strpos( $name, $search ) ) $results[] = $municipality;
		if ( count( $results ) >= 20 ) break;
	}
	return $results;
}

function trb_portal_find_municipality_exact( $city, $province = '' ) {
	$needle = strtolower( remove_accents( trim( (string) $city ) ) );
	$archive = trb_portal_territorial_archive();
	foreach ( isset( $archive['municipalities'] ) ? $archive['municipalities'] : array() as $municipality ) {
		if ( $needle === strtolower( remove_accents( $municipality['city'] ) ) && ( ! $province || strtoupper( $province ) === strtoupper( $municipality['province'] ) ) ) return $municipality;
	}
	return false;
}

function trb_portal_validate_mobile( $value ) {
	$value = preg_replace( '/[\s\.\-\(\)]+/', '', (string) $value );
	if ( 0 === strpos( $value, '0039' ) ) $value = '+39' . substr( $value, 4 );
	return preg_match( '/^(?:\+39)?3\d{9}$/', $value ) ? $value : false;
}

function trb_portal_validate_tax_code( $value ) {
	$value = strtoupper( preg_replace( '/\s+/', '', (string) $value ) );
	if ( ! preg_match( '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/', $value ) ) return false;
	$odd = array( '0'=>1,'1'=>0,'2'=>5,'3'=>7,'4'=>9,'5'=>13,'6'=>15,'7'=>17,'8'=>19,'9'=>21,'A'=>1,'B'=>0,'C'=>5,'D'=>7,'E'=>9,'F'=>13,'G'=>15,'H'=>17,'I'=>19,'J'=>21,'K'=>2,'L'=>4,'M'=>18,'N'=>20,'O'=>11,'P'=>3,'Q'=>6,'R'=>8,'S'=>12,'T'=>14,'U'=>16,'V'=>10,'W'=>22,'X'=>25,'Y'=>24,'Z'=>23 );
	$even = array();
	foreach ( range( '0', '9' ) as $char ) $even[ $char ] = (int) $char;
	foreach ( range( 'A', 'Z' ) as $index => $char ) $even[ $char ] = $index;
	$sum = 0;
	for ( $i = 0; $i < 15; $i++ ) $sum += 0 === $i % 2 ? $odd[ $value[ $i ] ] : $even[ $value[ $i ] ];
	return chr( 65 + ( $sum % 26 ) ) === $value[15] ? $value : false;
}

function trb_portal_rest_postcode( WP_REST_Request $request ) {
	$result = trb_portal_lookup_postcode( $request['postcode'] );
	return is_wp_error( $result ) ? new WP_REST_Response( array( 'message' => $result->get_error_message() ), 404 ) : rest_ensure_response( array( 'places' => $result, 'country' => 'Italia' ) );
}

function trb_portal_rest_municipalities( WP_REST_Request $request ) {
	return rest_ensure_response( array( 'places' => trb_portal_find_municipalities( $request->get_param( 'search' ) ) ) );
}

function trb_portal_register_rest_routes() {
	register_rest_route( 'trb/v1', '/postcode/(?P<postcode>\d{5})', array(
		'methods' => WP_REST_Server::READABLE,
		'callback' => 'trb_portal_rest_postcode',
		'permission_callback' => function() { return is_user_logged_in() && ( trb_portal_user_profile() || current_user_can( 'manage_options' ) ); },
	) );
	register_rest_route( 'trb/v1', '/municipalities', array(
		'methods' => WP_REST_Server::READABLE,
		'callback' => 'trb_portal_rest_municipalities',
		'permission_callback' => function() { return is_user_logged_in() && ( trb_portal_user_profile() || current_user_can( 'manage_options' ) ); },
		'args' => array( 'search' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
	) );
}
add_action( 'rest_api_init', 'trb_portal_register_rest_routes' );

function trb_portal_artist_profile_is_complete( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$user = get_userdata( $user_id );
	if ( ! $user || '' === trim( (string) $user->first_name ) || '' === trim( (string) $user->last_name ) || '' === trim( (string) $user->user_email ) ) {
		return false;
	}
	$required = array( 'artist_name', 'phone', 'birth_date', 'birth_place', 'birth_province', 'tax_code', 'street', 'street_number', 'city', 'postal_code', 'province', 'country' );
	foreach ( $required as $field ) {
		if ( '' === trb_portal_artist_profile_value( $field, $user_id ) ) {
			return false;
		}
	}
	if ( '' === trim( (string) get_user_meta( $user_id, '_trb_artist_bio', true ) ) ) {
		return false;
	}
	$platform_requirements = array(
		array( 'spotify_url', 'spotify_new' ),
		array( 'apple_music_url', 'apple_music_new' ),
		array( 'youtube_url', 'youtube_none' ),
		array( 'soundcloud_url', 'soundcloud_none' ),
	);
	foreach ( $platform_requirements as $requirement ) {
		if ( '' === trb_portal_artist_profile_value( $requirement[0], $user_id ) && '1' !== trb_portal_artist_profile_value( $requirement[1], $user_id ) ) {
			return false;
		}
	}
	if ( '' === trb_portal_artist_profile_value( 'live_fee', $user_id ) ) {
		return false;
	}

	$required_documents = array(
		'Carta d’identità — fronte',
		'Carta d’identità — retro',
		'Codice fiscale o tessera sanitaria — fronte',
		'Codice fiscale o tessera sanitaria — retro',
	);
	$received_documents = array();
	$has_photo          = false;
	foreach ( trb_portal_private_profile_files( $user_id ) as $file ) {
		if ( isset( $file['group'] ) && 'photo' === $file['group'] ) {
			$has_photo = true;
		}
		if ( ! empty( $file['label'] ) ) {
			$received_documents[] = $file['label'];
		}
	}

	return $has_photo && empty( array_diff( $required_documents, $received_documents ) );
}

function trb_portal_handle_artist_profile() {
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	check_admin_referer( 'trb_portal_save_artist_profile', 'trb_portal_profile_nonce' );
	$user_id = get_current_user_id();
	$profile = trb_portal_user_profile();
	$company_fields = array( 'company_name', 'company_vat', 'company_sdi', 'company_address' );
	$url_fields = array( 'spotify_url', 'apple_music_url', 'youtube_url', 'soundcloud_url', 'facebook_url', 'instagram_url', 'linkedin_url', 'tiktok_url', 'discord_url', 'twitch_url', 'x_url', 'snapchat_url', 'threads_url' );
	if ( isset( $_POST['trb_artist_company_section'] ) ) {
		$postcode = isset( $_POST['trb_artist_postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_artist_postal_code'] ) ) : '';
		$city = isset( $_POST['trb_artist_city'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_artist_city'] ) ) : '';
		$places = trb_portal_lookup_postcode( $postcode );
		$matched = false;
		if ( ! is_wp_error( $places ) ) foreach ( $places as $place ) {
			if ( strtolower( remove_accents( $city ) ) === strtolower( remove_accents( $place['city'] ) ) ) { $matched = $place; break; }
		}
		if ( ! $matched ) {
			wp_safe_redirect( add_query_arg( 'trb_profile', 'invalid_address', get_permalink( get_option( 'trb_portal_dashboard_created' ) ) ) . '#profilo' );
			exit;
		}
		$_POST['trb_artist_city'] = $matched['city'];
		$_POST['trb_artist_province'] = $matched['province'];
		$_POST['trb_artist_country'] = 'Italia';
	}
	if ( isset( $_POST['trb_artist_company_section'] ) ) {
		$birth_place = isset( $_POST['trb_artist_birth_place'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_artist_birth_place'] ) ) : '';
		$birth_province = isset( $_POST['trb_artist_birth_province'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_artist_birth_province'] ) ) : '';
		$birth_match = trb_portal_find_municipality_exact( $birth_place, $birth_province );
		$phone = isset( $_POST['trb_artist_phone'] ) ? trb_portal_validate_mobile( wp_unslash( $_POST['trb_artist_phone'] ) ) : false;
		$tax_code = isset( $_POST['trb_artist_tax_code'] ) ? trb_portal_validate_tax_code( wp_unslash( $_POST['trb_artist_tax_code'] ) ) : false;
		$error = ! $birth_match ? 'invalid_birthplace' : ( ! $phone ? 'invalid_phone' : ( ! $tax_code ? 'invalid_tax_code' : '' ) );
		if ( $error ) {
			wp_safe_redirect( add_query_arg( 'trb_profile', $error, get_permalink( get_option( 'trb_portal_dashboard_created' ) ) ) . '#profilo' );
			exit;
		}
		$_POST['trb_artist_birth_place'] = $birth_match['city'];
		$_POST['trb_artist_birth_province'] = $birth_match['province'];
		$_POST['trb_artist_phone'] = $phone;
		$_POST['trb_artist_tax_code'] = $tax_code;
	}
	foreach ( trb_portal_artist_profile_fields() as $key => $label ) {
		if ( ! isset( $_POST[ 'trb_artist_' . $key ] ) ) {
			continue;
		}
		if ( 'artist_name' === $key && '' !== trb_portal_artist_profile_value( 'artist_name', $user_id ) ) {
			continue;
		}
		if ( 'trb' === $profile && in_array( $key, $company_fields, true ) ) continue;
		$value = in_array( $key, $url_fields, true ) ? esc_url_raw( wp_unslash( $_POST[ 'trb_artist_' . $key ] ) ) : sanitize_text_field( wp_unslash( $_POST[ 'trb_artist_' . $key ] ) );
		update_user_meta( $user_id, '_trb_artist_' . $key, $value );
	}
	if ( 'trb' === $profile ) {
		delete_user_meta( $user_id, '_trb_artist_invoice_requested' );
		foreach ( $company_fields as $company_field ) delete_user_meta( $user_id, '_trb_artist_' . $company_field );
	} elseif ( isset( $_POST['trb_artist_invoice_requested'] ) || isset( $_POST['trb_artist_company_section'] ) ) {
		update_user_meta( $user_id, '_trb_artist_invoice_requested', isset( $_POST['trb_artist_invoice_requested'] ) ? '1' : '' );
	}
	if ( isset( $_POST['trb_artist_identity_section'] ) ) {
		foreach ( array( 'spotify_new', 'apple_music_new', 'youtube_none', 'soundcloud_none' ) as $choice ) {
			update_user_meta( $user_id, '_trb_artist_' . $choice, isset( $_POST[ 'trb_artist_' . $choice ] ) ? '1' : '' );
		}
	}
	if ( isset( $_POST['trb_artist_bio'] ) ) {
		update_user_meta( $user_id, '_trb_artist_bio', wp_kses_post( wp_unslash( $_POST['trb_artist_bio'] ) ) );
	}
	trb_portal_remove_private_profile_files( $user_id );
	trb_portal_handle_private_profile_uploads( $user_id );
	wp_safe_redirect( add_query_arg( 'trb_profile', 'saved', get_permalink( get_option( 'trb_portal_dashboard_created' ) ) ) . '#profilo' );
	exit;
}
add_action( 'admin_post_trb_portal_save_artist_profile', 'trb_portal_handle_artist_profile' );

/**
 * Store identity files outside the normal Media Library and deny direct web
 * access. The stored metadata contains no public URL, only a private path.
 */
function trb_portal_private_upload_dir( $dirs ) {
	$dirs['subdir'] = '/trb-artist-private';
	$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
	$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

	return $dirs;
}

function trb_portal_private_profile_files( $user_id = 0 ) {
	$files = get_user_meta( $user_id ? $user_id : get_current_user_id(), '_trb_artist_private_files', true );
	$files = is_array( $files ) ? $files : array();
	$changed = false;
	foreach ( $files as $index => $file ) {
		if ( empty( $file['id'] ) ) {
			$files[ $index ]['id'] = sha1( ( isset( $file['path'] ) ? $file['path'] : '' ) . '|' . $index );
			$changed = true;
		}
	}
	if ( $changed ) {
		update_user_meta( $user_id ? $user_id : get_current_user_id(), '_trb_artist_private_files', $files );
	}
	return $files;
}

function trb_portal_remove_private_profile_files( $user_id ) {
	$remove_ids = isset( $_POST['trb_artist_remove_files'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['trb_artist_remove_files'] ) ) : array();
	if ( empty( $remove_ids ) ) {
		return;
	}
	$upload_dir = wp_upload_dir();
	$private_dir = realpath( trailingslashit( $upload_dir['basedir'] ) . 'trb-artist-private' );
	$remaining = array();
	foreach ( trb_portal_private_profile_files( $user_id ) as $file ) {
		if ( ! in_array( $file['id'], $remove_ids, true ) ) {
			$remaining[] = $file;
			continue;
		}
		$target = ! empty( $file['path'] ) ? realpath( trailingslashit( $upload_dir['basedir'] ) . ltrim( $file['path'], '/' ) ) : false;
		if ( $private_dir && $target && 0 === strpos( $target, $private_dir . DIRECTORY_SEPARATOR ) && is_file( $target ) ) {
			wp_delete_file( $target );
		}
	}
	update_user_meta( $user_id, '_trb_artist_private_files', $remaining );
}

function trb_portal_private_upload_items( $input_name ) {
	if ( empty( $_FILES[ $input_name ]['name'] ) ) {
		return array();
	}
	if ( ! is_array( $_FILES[ $input_name ]['name'] ) ) {
		return array( array( 'name' => $_FILES[ $input_name ]['name'], 'type' => $_FILES[ $input_name ]['type'], 'tmp_name' => $_FILES[ $input_name ]['tmp_name'], 'error' => $_FILES[ $input_name ]['error'], 'size' => $_FILES[ $input_name ]['size'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	$items = array();
	foreach ( $_FILES[ $input_name ]['name'] as $index => $name ) {
		$items[] = array( 'name' => $name, 'type' => $_FILES[ $input_name ]['type'][ $index ], 'tmp_name' => $_FILES[ $input_name ]['tmp_name'][ $index ], 'error' => $_FILES[ $input_name ]['error'][ $index ], 'size' => $_FILES[ $input_name ]['size'][ $index ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	return $items;
}

function trb_portal_handle_private_profile_uploads( $user_id ) {
	if ( empty( $_FILES ) ) return;

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$existing       = trb_portal_private_profile_files( $user_id );
	$photos_count   = count( array_filter( $existing, function( $file ) { return isset( $file['group'] ) && 'photo' === $file['group']; } ) );
	$uploads        = array(
		'trb_artist_photos'       => array( 'group' => 'photo', 'label' => 'Foto artista', 'mimes' => array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ),
		'trb_artist_id_front'     => array( 'group' => 'identity', 'label' => 'Carta d’identità — fronte', 'mimes' => array( 'pdf' => 'application/pdf', 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png' ) ),
		'trb_artist_id_back'      => array( 'group' => 'identity', 'label' => 'Carta d’identità — retro', 'mimes' => array( 'pdf' => 'application/pdf', 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png' ) ),
		'trb_artist_tax_front'    => array( 'group' => 'tax_card', 'label' => 'Codice fiscale o tessera sanitaria — fronte', 'mimes' => array( 'pdf' => 'application/pdf', 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png' ) ),
		'trb_artist_tax_back'     => array( 'group' => 'tax_card', 'label' => 'Codice fiscale o tessera sanitaria — retro', 'mimes' => array( 'pdf' => 'application/pdf', 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png' ) ),
	);

	foreach ( $uploads as $input_name => $settings ) {
		foreach ( trb_portal_private_upload_items( $input_name ) as $upload ) {
			if ( empty( $upload['name'] ) || empty( $upload['tmp_name'] ) ) {
				continue;
			}
			if ( 'photo' === $settings['group'] && $photos_count >= 6 ) {
				break;
			}

			$file = array(
				'name'     => sanitize_file_name( $upload['name'] ),
				'type'     => isset( $upload['type'] ) ? sanitize_mime_type( $upload['type'] ) : '',
				'tmp_name' => $upload['tmp_name'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'error'    => isset( $upload['error'] ) ? absint( $upload['error'] ) : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $upload['size'] ) ? absint( $upload['size'] ) : 0,
			);
			if ( UPLOAD_ERR_OK !== $file['error'] ) {
				continue;
			}

			add_filter( 'upload_dir', 'trb_portal_private_upload_dir', 99 );
			$handled = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $settings['mimes'] ) );
			remove_filter( 'upload_dir', 'trb_portal_private_upload_dir', 99 );
			if ( ! empty( $handled['error'] ) || empty( $handled['file'] ) ) {
				continue;
			}

			$upload_dir = wp_upload_dir();
			$private_dir = trailingslashit( $upload_dir['basedir'] ) . 'trb-artist-private';
			if ( wp_mkdir_p( $private_dir ) ) {
				$rules_file = trailingslashit( $private_dir ) . '.htaccess';
				if ( ! file_exists( $rules_file ) ) {
					file_put_contents( $rules_file, "Require all denied\nDeny from all\nOptions -Indexes\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				}
			}

			$existing[] = array(
				'id'    => wp_generate_uuid4(),
				'group' => $settings['group'],
				'label' => $settings['label'],
				'name'  => basename( $handled['file'] ),
				'path'  => str_replace( trailingslashit( $upload_dir['basedir'] ), '', $handled['file'] ),
				'type'  => $handled['type'],
				'time'  => time(),
			);
			if ( 'photo' === $settings['group'] ) {
				$photos_count++;
			}
		}
	}

	update_user_meta( $user_id, '_trb_artist_private_files', $existing );
}

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
	if ( ( ! $is_catalogue && '' === $title ) || ! isset( $types[ $type ] ) || empty( $tracks ) || count( $tracks ) < $types[ $type ]['min'] || ! in_array( $release_state, array( 'unreleased', 'previously_released' ), true ) || ( 'previously_released' === $release_state && '' === $original_date ) || ( $is_catalogue && 'previously_released' !== $release_state ) || count( $tracks ) > $types[ $type ]['max'] ) {
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
		$credits = isset( $track['credits'] ) && is_array( $track['credits'] ) ? $track['credits'] : array();
		$duration = isset( $track['duration'] ) ? sanitize_text_field( $track['duration'] ) : '';
		$primary  = isset( $track['primary_genre'] ) && in_array( $track['primary_genre'], $genres, true ) ? $track['primary_genre'] : '';
		$authors  = isset( $credits['authors'] ) ? sanitize_textarea_field( $credits['authors'] ) : '';
		$composers = isset( $credits['composers'] ) ? sanitize_textarea_field( $credits['composers'] ) : '';
		$performers = isset( $credits['performers'] ) ? sanitize_textarea_field( $credits['performers'] ) : '';
		$producers = isset( $credits['producers'] ) ? sanitize_textarea_field( $credits['producers'] ) : '';
		if ( '' === $title || ! preg_match( '/^[0-9]{1,2}:[0-5][0-9]$/', $duration ) || '' === $primary || '' === $authors || '' === $composers || '' === $performers || '' === $producers ) {
			continue;
		}
		$secondary = isset( $track['secondary_genre'] ) && in_array( $track['secondary_genre'], $genres, true ) ? $track['secondary_genre'] : '';
		if ( $secondary === $primary ) {
			$secondary = '';
		}
		$clean[] = array(
			'title' => $title,
			'featuring' => isset( $track['featuring'] ) ? sanitize_text_field( $track['featuring'] ) : '',
			'duration' => $duration,
			'advisory' => isset( $track['advisory'] ) && in_array( $track['advisory'], array( 'none', 'clean', 'explicit' ), true ) ? $track['advisory'] : 'none',
			'primary_genre' => $primary,
			'secondary_genre' => $secondary,
			'credits' => array(
				'authors' => $authors,
				'composers' => $composers,
				'performers' => $performers,
				'producers' => $producers,
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
	$profiles = array(
		'dds' => array(
			'label' => 'DDS',
			'audio' => '<p>Per la distribuzione devi consegnare il <strong>master definitivo</strong> in WAV o AIFF stereo a <strong>48.000 Hz / 24 bit</strong>. Il file deve essere già approvato e pronto per la pubblicazione.</p><ul><li>Non inviare MP3, M4A, audio WhatsApp o file estratti da piattaforme streaming.</li><li>Non normalizzare o convertire nuovamente il master dopo l’approvazione.</li><li>Esporta dall’inizio corretto e controlla che intro, dissolvenze e code non siano tagliate.</li><li>Per pubblicazioni con più brani usa lo stesso standard tecnico per ogni traccia.</li></ul>',
			'cover' => '<p>Devi caricare la <strong>copertina definitiva già realizzata</strong>, collegandola alla pratica della release.</p><ul><li>Formato quadrato RGB, 3.000 × 3.000 px, 300 DPI.</li><li>Niente immagini sfocate, bordi involontari, URL, loghi di store o contenuti non autorizzati.</li><li>Nome d’arte e titolo devono coincidere esattamente con i metadati inseriti.</li></ul><p>Controlla attentamente il file prima dell’invio: una copertina non conforme blocca la programmazione.</p>',
			'platforms' => '<p>Il tuo percorso comprende la distribuzione sulle piattaforme digitali previste. Inserisci link corretti ai profili artista già esistenti per evitare la creazione di pagine duplicate.</p><p>La consegna dei materiali non costituisce candidatura editoriale alle playlist ufficiali. Puoi comunque curare autonomamente Spotify for Artists e gli strumenti messi a disposizione dalle piattaforme.</p>',
			'promo' => '<p>Prepara una biografia aggiornata, fotografie ad alta qualità e link social corretti. Questi elementi permettono di identificare il progetto e accompagnare correttamente la pubblicazione.</p><p>Le eventuali opportunità nel network interno dipendono dall’idoneità della release e non costituiscono un risultato garantito.</p>',
		),
		'ddb' => array(
			'label' => 'DDB',
			'audio' => '<p>Quando richiedi la lavorazione inclusa, consegna il <strong>pre-master WAV o AIFF stereo a 48.000 Hz / 24 bit</strong>, privo di limiter aggressivi e con margine dinamico sufficiente.</p><ul><li>Non inviare MP3, conversioni o audio provenienti da applicazioni di messaggistica.</li><li>Evita clipping e normalizzazione automatica.</li><li>Per stem e tracce multiple usa identico punto di partenza e durata.</li><li>Dopo l’approvazione utilizza esclusivamente il master definitivo ricevuto.</li></ul>',
			'cover' => '<p>La copertina grafica non è compresa nel tuo percorso: devi caricare l’asset definitivo nella pratica della release.</p><ul><li>RGB, 3.000 × 3.000 px, 300 DPI.</li><li>Titolo e nome d’arte identici ai metadati.</li><li>Nessun URL, logo di piattaforma, bordo involontario o immagine non autorizzata.</li></ul><p>Se acquisti separatamente una realizzazione grafica, le indicazioni verranno gestite nella relativa richiesta.</p>',
			'platforms' => '<p>Il tuo percorso comprende l’ottimizzazione del profilo e la strategia di pitching editoriale su <strong>Spotify e Apple Music</strong>.</p><ul><li>Fornisci i link esatti ai profili artista e segnala eventuali omonimie.</li><li>Descrivi storia, contesto, pubblico ed elementi distintivi del brano.</li><li>Consegna tutto con sufficiente anticipo rispetto alla data programmata.</li></ul><p>Il pitching è una candidatura editoriale: non garantisce playlist, copertura o risultati specifici.</p>',
			'promo' => '<p>Collega alla release biografia, fotografie, storia del brano, testi, link e materiali richiesti per le attività editoriali e promozionali comprese.</p><p>Campagne, opportunità di booking e ulteriori attività dipendono dalle condizioni previste e dalla valutazione del progetto o della singola pubblicazione.</p>',
		),
		'ddb_trb' => array(
			'label' => 'DDB-TRB',
			'audio' => '<p>Consegna il <strong>pre-master WAV o AIFF stereo a 48.000 Hz / 24 bit</strong> per la lavorazione prevista dal tuo percorso.</p><ul><li>Evita limiter aggressivi, clipping e normalizzazione automatica.</li><li>Per stem e tracce multiple usa identico punto di partenza e durata.</li><li>Non inviare MP3, conversioni o file provenienti da applicazioni di messaggistica.</li><li>Dopo l’approvazione non modificare né riconvertire il master definitivo.</li></ul>',
			'cover' => '<p>La realizzazione della copertina è compresa: nella pratica della release devi compilare il <strong>brief grafico</strong>, non caricare una richiesta scollegata.</p><ul><li>Spiega concept, atmosfera e messaggio del progetto.</li><li>Allega riferimenti visivi pertinenti e indica gli elementi da evitare.</li><li>Verifica titolo, nome d’arte e testi prima dell’avvio.</li></ul><p>Le proposte vengono preparate sulla base delle informazioni definitive fornite.</p>',
			'platforms' => '<p>Il tuo percorso comprende ottimizzazione del profilo e strategia di pitching editoriale su <strong>Spotify e Apple Music</strong>.</p><ul><li>Fornisci link esatti e segnala eventuali profili duplicati.</li><li>Descrivi storia, contesto, pubblico e punti distintivi della release.</li><li>Consegna i materiali prima della finestra utile al pitching.</li></ul><p>La candidatura non costituisce garanzia di playlist o risultati specifici.</p>',
			'promo' => '<p>Prepara biografia, fotografie, storia del brano, testi e materiali editoriali completi. Le attività avanzate di comunicazione, radio e ufficio stampa vengono organizzate secondo idoneità e condizioni della singola release.</p><p>Il percorso accompagna lo sviluppo fino all’inserimento previsto nel roster; ogni pubblicazione deve comunque rispettare la procedura e le verifiche indicate.</p>',
		),
		'trb' => array(
			'label' => 'TRB',
			'audio' => '<p>Per la lavorazione prevista dal tuo percorso consegna il <strong>pre-master WAV o AIFF stereo a 48.000 Hz / 24 bit</strong>.</p><ul><li>Evita limiter aggressivi, clipping e normalizzazione automatica.</li><li>Per stem e tracce multiple usa identico punto di partenza e durata.</li><li>Non inviare MP3, conversioni o file provenienti da applicazioni di messaggistica.</li><li>Utilizza esclusivamente il master definitivo approvato per la distribuzione.</li></ul>',
			'cover' => '<p>La realizzazione della copertina è compresa nel tuo percorso. Compila il <strong>brief grafico dentro la pratica della release</strong>.</p><ul><li>Descrivi concept, atmosfera, riferimenti e messaggio artistico.</li><li>Indica chiaramente gli elementi obbligatori e quelli da evitare.</li><li>Conferma titolo, nome d’arte e testi prima dell’avvio.</li></ul><p>La grafica deve rappresentare coerentemente l’identità del progetto nel roster.</p>',
			'platforms' => '<p>Il tuo percorso comprende ottimizzazione del profilo e strategia di pitching editoriale su <strong>Spotify e Apple Music</strong>.</p><ul><li>Fornisci link esatti ai profili e segnala omonimie o duplicazioni.</li><li>Descrivi in modo concreto storia, contesto e posizionamento della release.</li><li>Completa i materiali prima della finestra utile alla candidatura.</li></ul><p>Il pitching non garantisce inserimenti editoriali o risultati specifici.</p>',
			'promo' => '<p>Come artista del roster, collega alla pratica biografia, fotografie, storia del brano, testi, crediti e materiali completi. Promozione avanzata, comunicazione, radio e ufficio stampa vengono pianificati in relazione alla singola release.</p><p>Le opportunità restano soggette a valutazione artistica, editoriale e strategica: non inviare richieste promozionali scollegate dalla pubblicazione.</p>',
		),
	);

	$guides = array();
	foreach ( $profiles as $profile => $copy ) {
		$prefix = $profile . '-';
		$guides[ $prefix . 'profilo-artista' ] = array(
			'title' => 'Profilo artista: dati, documenti e identità', 'profiles' => array( $profile ),
			'excerpt' => 'Il primo passaggio obbligatorio prima di aprire una pratica.',
			'content' => '<p>Completa il profilo prima della prima pubblicazione. Nome, cognome ed e-mail provengono dall’account; gli altri dati devono essere inseriti e verificati.</p><ul><li>Dati anagrafici, residenza, codice fiscale e cellulare abilitato alla ricezione SMS.</li><li>Carta d’identità fronte e retro.</li><li>Codice fiscale o tessera sanitaria fronte e retro.</li><li>Biografia artistica aggiornata e fino a sei fotografie ad alta qualità.</li><li>Dati aziendali solo quando pertinenti alla fatturazione.</li></ul><p>Per modificare nome, cognome o e-mail dell’account devi aprire una segnalazione.</p>',
		);
		$guides[ $prefix . 'tipologie-release' ] = array(
			'title' => 'Quale tipologia di release devo scegliere?', 'profiles' => array( $profile ),
			'excerpt' => 'Singolo, EP, album, compilation, collection e catalogo.',
			'content' => '<p>Scegli la tipologia in base al numero effettivo di brani della pratica:</p><ul><li><strong>Singolo:</strong> 1 brano.</li><li><strong>EP:</strong> da 4 a 8 brani.</li><li><strong>Album:</strong> da 9 a 15 brani.</li><li><strong>Doppio album:</strong> da 16 a 30 brani.</li><li><strong>Compilation:</strong> da 16 a 24 brani.</li><li><strong>Collection:</strong> da 20 a 40 brani.</li><li><strong>Catalogo o repertorio musicale edito:</strong> fino a 60 brani.</li></ul><p>Non dividere artificialmente un progetto e non scegliere una categoria incompatibile con il numero delle tracce.</p>',
		);
		$guides[ $prefix . 'inedita-o-edita' ] = array(
			'title' => 'Release inedita o già pubblicata: come indicarla', 'profiles' => array( $profile ),
			'excerpt' => 'La differenza da dichiarare prima di inserire i brani.',
			'content' => '<p>Se la release non è mai comparsa ufficialmente sulle piattaforme, seleziona <strong>inedita</strong>. Se è stata distribuita in precedenza, seleziona <strong>già pubblicata</strong> e indica la data di pubblicazione originale.</p><p>Una presenza precedente su store o servizi streaming non deve essere nascosta: serve per valutare correttamente metadati, codici e continuità del catalogo. Non considerare “inedita” una registrazione già pubblicata soltanto perché verrà caricata da un nuovo distributore.</p>',
		);
		$guides[ $prefix . 'firma-otp' ] = array(
			'title' => 'Dati contrattuali, firma e codice OTP', 'profiles' => array( $profile ),
			'excerpt' => 'Come vengono usati i dati inseriti nel profilo e nella pratica.',
			'content' => '<p>I dati anagrafici del profilo e i metadati della release vengono utilizzati per predisporre la documentazione contrattuale. Controllali prima dell’invio: informazioni incomplete o incoerenti bloccano la pratica.</p><p>Il cellulare indicato deve poter ricevere SMS perché la firma digitale può richiedere un codice OTP personale. Non condividere il codice e non utilizzare il numero di una persona estranea al firmatario.</p>',
		);
		$guides[ $prefix . 'nuova-release' ] = array(
			'title' => 'Come avviare e completare una nuova release', 'profiles' => array( $profile ),
			'excerpt' => 'La sequenza corretta prevista dal tuo percorso ' . $copy['label'] . '.',
			'content' => '<p>Ogni pubblicazione deve avere una pratica distinta. Non mescolare dati, audio o materiali appartenenti a release diverse.</p><ol><li><strong>Aggiorna il profilo artista.</strong> I dati devono essere completi e verificabili.</li><li><strong>Apri la pratica.</strong> Scegli la tipologia e inserisci metadati e brani.</li><li><strong>Completa audio e copertina.</strong> Segui esclusivamente le istruzioni mostrate nel tuo percorso.</li><li><strong>Inserisci crediti e materiali editoriali.</strong> Ogni informazione deve essere definitiva.</li><li><strong>Attendi la verifica.</strong> La data viene programmata solo quando la pratica è completa.</li></ol><p>La valutazione demo rimane facoltativa, sempre disponibile e separata dalla pubblicazione.</p>',
		);
		$guides[ $prefix . 'audio' ] = array( 'title' => 'File audio: formato e consegna', 'profiles' => array( $profile ), 'excerpt' => 'Lo standard audio e il file richiesto per il tuo percorso.', 'content' => $copy['audio'] );
		$guides[ $prefix . 'copertina' ] = array( 'title' => 'Copertina: preparazione e consegna', 'profiles' => array( $profile ), 'excerpt' => 'Come gestire correttamente la copertina della tua release.', 'content' => $copy['cover'] );
		$guides[ $prefix . 'metadati' ] = array(
			'title' => 'Metadati, crediti e diritti: controlli obbligatori', 'profiles' => array( $profile ),
			'excerpt' => 'Titoli, featuring, autori, compositori e titolarità senza errori.',
			'content' => '<p>Prima dell’invio verifica che titolo, nome d’arte, versione, featuring e crediti siano corretti e definitivi.</p><ul><li>Indica tutti gli autori, compositori, interpreti, produttori e musicisti coinvolti.</li><li>Scrivi il featuring esattamente come deve comparire sulle piattaforme.</li><li>Per sample, beat, basi e contenuti di terzi devi possedere autorizzazioni e diritti necessari.</li><li>Segnala se la release è già stata pubblicata e inserisci la data originale.</li><li>Non cambiare metadati dopo l’avvio senza aprire una segnalazione.</li></ul>',
		);
		$guides[ $prefix . 'tempistiche' ] = array(
			'title' => 'Tempistiche e programmazione della pubblicazione', 'profiles' => array( $profile ),
			'excerpt' => 'Quando può essere confermata la data della release.',
			'content' => '<p>Servono normalmente <strong>tre settimane dalla consegna completa</strong> del master approvato e di tutti i materiali richiesti. Una pratica incompleta non consente di confermare la data.</p><ul><li>Le eventuali lavorazioni audio richiedono normalmente 2–3 giorni tecnici.</li><li>Ad agosto, Ferragosto e nel periodo di fine anno considera almeno quattro settimane.</li><li>Correzioni tardive a audio, copertina, metadati, testi o featuring possono spostare la programmazione.</li></ul>',
		);
		$guides[ $prefix . 'piattaforme' ] = array( 'title' => 'Piattaforme, profili artista e pitching', 'profiles' => array( $profile ), 'excerpt' => 'Cosa è previsto per distribuzione e profili digitali.', 'content' => $copy['platforms'] );
		$guides[ $prefix . 'promozione' ] = array( 'title' => 'Materiali promozionali e supporto alla release', 'profiles' => array( $profile ), 'excerpt' => 'Come preparare i materiali previsti dal tuo percorso.', 'content' => $copy['promo'] );
		$guides[ $prefix . 'correzioni' ] = array(
			'title' => 'Correzioni, sostituzioni e variazioni dopo l’invio', 'profiles' => array( $profile ),
			'excerpt' => 'Cosa fare quando un dato o un file deve essere modificato.',
			'content' => '<p>Non aprire una seconda pratica per correggere quella esistente. Apri una segnalazione indicando titolo della release, dato errato e correzione richiesta.</p><ul><li>Prima della consegna alle piattaforme, la modifica viene valutata nella pratica corrente.</li><li>Dopo la consegna, una sostituzione può richiedere nuovi tempi tecnici o lo spostamento della data.</li><li>Dopo la pubblicazione, modifiche sostanziali e rimozioni seguono procedure specifiche e non sono immediate.</li></ul><p>Non inviare autonomamente versioni alternative senza aver ricevuto indicazioni.</p>',
		);
	}
	return $guides;
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

/** Search vocabulary is intentional: artists do not always use the title terms. */
function trb_portal_index_canonical_guides() {
	if ( get_option( 'trb_portal_guides_indexed_v2' ) ) return;
	$terms = array(
		'nuova-release' => 'nuova release pubblicazione contratto distribuzione avviare iniziare singolo ep album pratica',
		'formati-audio' => 'formato audio file master premaster pre master wav aiff 48 khz 48000 24 bit lossless spotify tidal mastering',
		'tempistiche-release' => 'tempi tempistiche quando pubblicare data release tre settimane agosto ferragosto fine anno mastering',
		'metadati-e-diritti' => 'metadati autori compositori featuring diritti titolarita sample beat isrc',
		'copertine' => 'copertina cover grafica 3000 3000 rgb dpi brief immagine',
		'spotify-apple' => 'spotify apple music profilo artista pitching editoriale playlist',
		'knowledge-hub-avanzata' => 'ebook e-book guida avanzata marketing brand promozione social',
	);
	foreach ( $terms as $key => $value ) {
		$guides = get_posts( array( 'post_type' => 'trb_guide', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_trb_guide_key', 'meta_value' => $key ) );
		if ( ! empty( $guides ) ) update_post_meta( $guides[0]->ID, '_trb_portal_search_terms', $value );
	}
	update_option( 'trb_portal_guides_indexed_v2', time(), false );
}
add_action( 'init', 'trb_portal_index_canonical_guides', 37 );

/**
 * Keep the concise, in-page answers editable from one source. This is an
 * update pass, not a second set of FAQ pages: existing guides are found by
 * their key and refreshed in place.
 */
function trb_portal_sync_canonical_guides() {
	if ( get_option( 'trb_portal_guides_synced_v7' ) ) {
		return;
	}

	$canonical = trb_portal_seed_guides();
	foreach ( $canonical as $key => $guide ) {
		$existing = get_posts( array(
			'post_type'   => 'trb_guide',
			'post_status' => 'publish',
			'numberposts' => 1,
			'meta_key'    => '_trb_guide_key',
			'meta_value'  => $key,
		) );
		$post_data = array(
			'post_title'   => $guide['title'],
			'post_excerpt' => $guide['excerpt'],
			'post_content' => $guide['content'],
		);
		if ( ! empty( $existing ) ) {
			$post_data['ID'] = $existing[0]->ID;
			$guide_id = wp_update_post( $post_data, true );
		} else {
			$post_data['post_type'] = 'trb_guide';
			$post_data['post_status'] = 'publish';
			$guide_id = wp_insert_post( $post_data, true );
		}
		if ( ! is_wp_error( $guide_id ) ) {
			update_post_meta( $guide_id, '_trb_guide_key', $key );
			update_post_meta( $guide_id, '_trb_portal_profiles', $guide['profiles'] );
			$topic = preg_replace( '/^(dds|ddb|ddb_trb|trb)-/', '', $key );
			$terms = array(
				'nuova-release' => 'nuova release pubblicazione contratto distribuzione iniziare singolo ep album compilation collection catalogo pratica',
				'profilo-artista' => 'profilo artista dati anagrafici documenti carta identita codice fiscale tessera sanitaria foto biografia cellulare',
				'tipologie-release' => 'tipologia singolo ep album doppio album compilation collection catalogo repertorio numero brani',
				'inedita-o-edita' => 'inedita edita gia pubblicata data originale redistribuzione catalogo precedente uscita',
				'firma-otp' => 'contratto firma digitale otp sms cellulare dati contrattuali documentazione',
				'audio' => 'formato audio file master premaster pre master wav aiff 48 khz 48000 24 bit lossless mastering stem',
				'copertina' => 'copertina cover grafica 3000 rgb dpi brief immagine artwork',
				'metadati' => 'metadati autori compositori interpreti musicisti produttori featuring diritti sample beat titolarita',
				'tempistiche' => 'tempi tempistiche data uscita pubblicare settimane agosto ferragosto fine anno programmazione',
				'piattaforme' => 'spotify apple music profilo artista pitching editoriale playlist distribuzione store',
				'promozione' => 'promozione biografia foto comunicazione radio ufficio stampa materiali supporto',
				'correzioni' => 'correzione modifica sostituzione variazione errore rimozione takedown data file metadati',
			);
			update_post_meta( $guide_id, '_trb_portal_search_terms', isset( $terms[ $topic ] ) ? $terms[ $topic ] : '' );
		}
	}

	$legacy = get_posts( array( 'post_type' => 'trb_guide', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_trb_guide_key' ) );
	foreach ( $legacy as $post ) {
		$key = (string) get_post_meta( $post->ID, '_trb_guide_key', true );
		if ( ! isset( $canonical[ $key ] ) ) {
			wp_trash_post( $post->ID );
		}
	}

	update_option( 'trb_portal_guides_synced_v7', time(), false );
}
add_action( 'init', 'trb_portal_sync_canonical_guides', 38 );

/**
 * EazyDocs Pro holds the detailed reference documents. They are intentionally
 * different from the short answers above and are protected by the exact same
 * contractual audience metadata as every other portal resource.
 */
function trb_portal_eazydocs_manuals() {
	return array(
		'audio-consegna' => array(
			'title' => 'Manuale tecnico: preparazione e consegna dei file audio',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'Specifiche aggiornate per consegnare master o pre-master senza errori di formato.',
			'content' => '<h2>Formato richiesto</h2><p>Per ogni brano consegna un file stereo <strong>WAV o AIFF a 48.000 Hz / 24 bit</strong>. Questo è il riferimento per le attuali piattaforme ad alta qualità, incluse le modalità lossless.</p><h2>Prima dell’invio</h2><ul><li>Esporta dall’inizio esatto del brano, senza silenzi accidentali o finali tagliati.</li><li>Non inviare MP3, file estratti da streaming, conversioni, audio ricevuti da WhatsApp o screen recording.</li><li>Non cambiare frequenza di campionamento o profondità bit dopo l’approvazione del master.</li><li>Per EP, album e compilation usa lo stesso standard tecnico per tutte le tracce.</li></ul><h2>Quando è previsto il mastering</h2><p>Consegna il pre-master nello stesso formato, evitando limiter aggressivi sul master bus. Se devi inviare stem, usa la stessa durata e lo stesso punto di partenza per ogni file.</p>',
		),
		'biografia-artista' => array(
			'title' => 'Manuale: biografia artistica e materiali stampa',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'Come preparare una biografia chiara, aggiornata e utile per le piattaforme e la comunicazione.',
			'content' => '<h2>Una biografia utile non è un curriculum</h2><p>Scrivi in terza persona, con un linguaggio concreto. Spiega identità, percorso, suono, riferimenti e direzione del progetto senza formule generiche.</p><h2>Struttura consigliata</h2><ol><li>Nome d’arte e collocazione artistica.</li><li>Origine o momento chiave del progetto.</li><li>Suono, influenze e temi ricorrenti.</li><li>Pubblicazioni, collaborazioni o risultati realmente verificabili.</li><li>Focus attuale e prossima release.</li></ol><h2>Foto</h2><p>Carica fino a sei foto nitide ad alta qualità. Devono rappresentare l’identità attuale del progetto e non contenere loghi, scritte aggiunte o filtri che ne riducano l’utilizzo editoriale.</p>',
		),
		'profilo-artista' => array(
			'title' => 'Profilo artista: dati, documenti e aggiornamenti',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'Dati necessari alla gestione contrattuale, alla compilazione delle pratiche e all’identità artistica.',
			'content' => '<h2>Perché il profilo è obbligatorio</h2><p>I dati anagrafici e la documentazione permettono di preparare correttamente le pratiche contrattuali. La biografia e le foto sono invece il nucleo della presentazione artistica.</p><h2>Documenti richiesti</h2><ul><li>Carta d’identità fronte e retro.</li><li>Codice fiscale o tessera sanitaria fronte e retro.</li><li>Solo se pertinenti alla fatturazione: ragione sociale, partita IVA, Codice SDI e sede aziendale.</li></ul><p>I documenti restano privati e non vengono usati come materiali pubblici. Aggiorna il profilo quando cambiano dati, foto o biografia.</p>',
		),
		'promozione-release' => array(
			'title' => 'Preparare il materiale promozionale di una release',
			'profiles' => array( 'ddb', 'ddb_trb', 'trb' ),
			'excerpt' => 'Informazioni e materiali da preparare per pitching, comunicazione e pianificazione della pubblicazione.',
			'content' => '<h2>Il materiale serve prima della pubblicazione</h2><p>Ogni elemento promozionale va collegato alla stessa pratica di release: non inviare dati di brani diversi nella medesima richiesta.</p><h2>Prepara</h2><ul><li>Storia del brano, contesto, significato e elementi distintivi.</li><li>Link corretti a Spotify for Artists, Apple Music for Artists e social dell’artista.</li><li>Biografia aggiornata, foto utilizzabili e crediti verificati.</li><li>Eventuali testi, visual, riferimenti e indicazioni editoriali.</li></ul><p>Il pitching editoriale è una candidatura: la qualità e la puntualità del materiale aiutano il lavoro, ma non costituiscono garanzia di playlist o risultati.</p>',
		),
	);
}

function trb_portal_sync_eazydocs_manuals() {
	if ( ! post_type_exists( 'docs' ) || get_option( 'trb_portal_eazydocs_manuals_v2' ) ) {
		return;
	}
	foreach ( trb_portal_eazydocs_manuals() as $key => $manual ) {
		$existing = get_posts( array( 'post_type' => 'docs', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_trb_portal_doc_key', 'meta_value' => $key ) );
		$data = array( 'post_title' => $manual['title'], 'post_excerpt' => $manual['excerpt'], 'post_content' => $manual['content'] );
		if ( ! empty( $existing ) ) {
			$data['ID'] = $existing[0]->ID;
			$doc_id = wp_update_post( $data, true );
		} else {
			$data['post_type'] = 'docs';
			$data['post_status'] = 'publish';
			$doc_id = wp_insert_post( $data, true );
		}
		if ( ! is_wp_error( $doc_id ) ) {
			update_post_meta( $doc_id, '_trb_portal_doc_key', $key );
			update_post_meta( $doc_id, '_trb_portal_profiles', $manual['profiles'] );
		}
	}
	update_option( 'trb_portal_eazydocs_manuals_v2', time(), false );
}
add_action( 'init', 'trb_portal_sync_eazydocs_manuals', 39 );

/**
 * Bring the surviving legacy download packages into the one Library once.
 * The two Social Network Tips are deliberately separate documents: one is
 * for DDB/DDB-TRB and the other is a TRB-only advanced guide.
 */
function trb_portal_migrate_known_download_audiences() {
	if ( get_option( 'trb_portal_download_audience_migrated_v2' ) ) {
		return;
	}

	$packages = array(
		11829 => array( 'dds', 'ddb', 'ddb_trb', 'trb' ), // Biografia artistica.
		11201 => array( 'ddb', 'ddb_trb', 'trb' ), // E-book: missaggio.
		11119 => array( 'ddb', 'ddb_trb', 'trb' ), // E-book: brano contemporaneo.
		11118 => array( 'dds', 'ddb', 'ddb_trb', 'trb' ), // Spotify e streaming.
		11117 => array( 'ddb', 'ddb_trb' ), // Social Network Tips: DDB / DDB-TRB.
		11116 => array( 'trb' ), // Social Network Tips: TRB.
	);

	foreach ( $packages as $package_id => $profiles ) {
		if ( 'wpdmpro' === get_post_type( $package_id ) ) {
			update_post_meta( $package_id, '_trb_portal_profiles', $profiles );
		}
	}

	$revisions = array(
		11117 => array(
			'post_title'   => '[GUIDA] SOCIAL NETWORK TIPS · DDB / DDB-TRB',
			'post_excerpt' => 'Linee guida per creare e gestire i profili social, i contenuti e i TAG dei post.',
		),
		11116 => array(
			'post_title'   => '[GUIDA] SOCIAL NETWORK TIPS · TRB',
			'post_excerpt' => 'Approfondimento dedicato al progetto TRB per identità, pianificazione editoriale e presenza social.',
		),
	);
	foreach ( $revisions as $package_id => $revision ) {
		if ( 'wpdmpro' === get_post_type( $package_id ) ) {
			wp_update_post( array_merge( array( 'ID' => $package_id ), $revision ) );
		}
	}

	update_option( 'trb_portal_download_audience_migrated_v2', time(), false );
}
add_action( 'init', 'trb_portal_migrate_known_download_audiences', 36 );

function trb_portal_video_seed_data() {
	return array(
		array( '2Ppuzp_8CyQ', 'Come Musixmatch ha costruito un prodotto scalabile', 'Music business', 'Comprendi come una piattaforma musicale sviluppa prodotto, community e servizi per artisti.', 'Intermedio', 21 ),
		array( 'LZX3Ma2Oass', 'Come nasce una canzone: dall’idea al brano', 'Scrittura e composizione', 'Un percorso completo da idea, testo e musica fino alla struttura definitiva della canzone.', 'Base', 1 ),
		array( 'lpj4wDenbvo', 'Come scrivere il testo di una canzone', 'Scrittura e composizione', 'Principi e scelte utili per costruire un testo coerente, personale e cantabile.', 'Base', 2 ),
		array( 'rovW0nBWOpQ', 'Strofa, ritornello e bridge: costruire la struttura', 'Scrittura e composizione', 'Organizza le sezioni del brano per renderlo dinamico, chiaro e riconoscibile.', 'Base', 3 ),
		array( 'LmBoaRMht_s', 'Come creare una melodia da zero', 'Scrittura e composizione', 'Un metodo accessibile per costruire melodie e accordi senza teoria musicale avanzata.', 'Base', 4 ),
		array( 'IPoEIBBrnPQ', 'Trasformare gli accordi in una melodia efficace', 'Scrittura e composizione', 'Individua le note adatte sopra una progressione e crea una linea coerente e cantabile.', 'Base/intermedio', 5 ),
		array( 'zRFp15k9Ryw', 'I principi fondamentali dell’arrangiamento', 'Scrittura e composizione', 'Distribuisci strumenti, parti e dinamiche valorizzando la canzone senza sovraccaricarla.', 'Base/intermedio', 6 ),
		array( 'fZdG6yGGtVU', 'I fondamenti della tecnica vocale', 'Canto e interpretazione', 'Postura, respiro, intonazione, timbro, dizione e controllo in una panoramica completa.', 'Base', 7 ),
		array( 'A-3K-vGbPO0', 'Riscaldamento vocale prima di cantare o registrare', 'Canto e interpretazione', 'Una routine guidata per preparare la voce e ridurre lo sforzo prima della sessione.', 'Base', 8 ),
		array( 'JQ8aGLMtUjs', 'Migliorare l’intonazione con scale e intervalli', 'Canto e interpretazione', 'Esercizi per allenare orecchio, precisione delle note e controllo dell’intonazione.', 'Base', 9 ),
		array( 'QOJur8lRSdU', 'Respirazione e appoggio nel canto', 'Canto e interpretazione', 'Comprendi il sostegno del fiato e controlla intensità, durata e stabilità della voce.', 'Base', 10 ),
		array( 'DqLh_Dc6cLY', 'Interpretazione vocale: comunicare il significato del testo', 'Canto e interpretazione', 'Usa intenzione, fraseggio, dinamica ed emozione per personalizzare l’esecuzione.', 'Base/intermedio', 11 ),
		array( 'PRplUNPRTSU', 'Come scegliere la tonalità adatta alla propria voce', 'Canto e interpretazione', 'Riconosci il range utile e scegli una tonalità naturale, controllabile e credibile.', 'Base', 12 ),
		array( 'hXuSELCrHKQ', 'Scegliere il microfono: dinamico, condensatore e pattern', 'Registrazione', 'Comprendi tipologie e caratteristiche dei microfoni per scegliere la soluzione adatta.', 'Base', 13 ),
		array( 'Fcpllu0SAFg', 'Registrare la voce in home studio', 'Registrazione', 'Livelli, distanza e accorgimenti essenziali per ottenere tracce vocali utilizzabili.', 'Base', 14 ),
		array( '8Dhb9p68Ido', 'Microfono, asta, filtro antipop e accessori essenziali', 'Registrazione', 'Conosci il microfono e gli accessori fondamentali per preparare una ripresa ordinata e affidabile.', 'Base', 15 ),
		array( 'WCl0IYLW4Mg', 'L’attrezzatura necessaria per registrare da casa', 'Registrazione', 'Computer, interfaccia audio, cuffie, monitor, microfono e software: una panoramica per iniziare senza acquisti casuali.', 'Base', 16 ),
		array( 'DPf5Bkvj54A', 'Preparare una stanza per registrare correttamente', 'Registrazione', 'Comprendi come ambiente, riflessioni e trattamento acustico influenzano la qualità della ripresa.', 'Base', 17 ),
		array( 'XNQY89OXje8', 'Come microfonare una chitarra acustica', 'Registrazione', 'Scelta e posizionamento dei microfoni per ottenere una ripresa naturale e utilizzabile.', 'Base/intermedio', 18 ),
		array( 'sDa7rVQGH1Q', 'Quattro posizioni microfoniche per la chitarra acustica', 'Registrazione', 'Confronta quattro collocazioni con un solo microfono e riconosci come cambia il timbro registrato.', 'Base/intermedio', 19 ),
		array( 'Y5qiNz7QQNI', 'Registrare voce e chitarra con un solo microfono', 'Registrazione', 'Imposta una ripresa semplice per provini e preproduzioni bilanciando voce e strumento.', 'Base', 20 ),
		array( 'Tx4cIS3wHLQ', '16, 24 e 32 bit: scegliere il formato di registrazione', 'Registrazione', 'Comprendi la profondità di bit e perché le consegne TRB devono rispettare il formato richiesto.', 'Base', 21 ),
		array( 'yxyYwgylNSM', 'Gain staging: gestire correttamente i livelli', 'Mixaggio e mastering', 'Gestisci i livelli lungo la catena audio evitando clipping e processori alimentati in modo scorretto.', 'Base', 15 ),
		array( 'YrpxrgQFVGY', 'Come iniziare correttamente un mix', 'Mixaggio e mastering', 'Prepara la sessione, stabilisci le priorità e costruisci un primo bilanciamento ordinato.', 'Base', 16 ),
		array( 'baJE7zsNW-I', 'Cinque errori di mixaggio da evitare', 'Mixaggio e mastering', 'Riconosci gli sbagli che rendono il mix confuso, sbilanciato o poco efficace.', 'Base', 17 ),
		array( '21vchwZhX3w', 'Volumi e clipping: preparare correttamente il mix', 'Mixaggio e mastering', 'Gestisci picchi e livelli lasciando al mastering un segnale tecnicamente adeguato.', 'Base', 18 ),
		array( 'fsCKKraAUw0', 'Che cos’è il mastering e come si prepara un brano', 'Mixaggio e mastering', 'Una panoramica delle finalità del mastering e delle verifiche prima della pubblicazione.', 'Base/intermedio', 19 ),
		array( 'Y5b8ul2mmU8', 'Loudness e LUFS spiegati in modo semplice', 'Mixaggio e mastering', 'Comprendi volume percepito, picco e dinamica e perché non indicano la stessa cosa.', 'Intermedio', 20 ),
		array( 'uyNy0Lw5Fd0', 'Come preparare un DJ set', 'Live e DJ set', 'Organizza selezione musicale, materiali e sviluppo del set prima dell’esibizione.', 'Base', 22 ),
		array( '1tSbsRI0UYU', 'Le regole fondamentali di un buon DJ set', 'Live e DJ set', 'Costruisci una scaletta coerente e gestisci con criterio ritmo, transizioni e pubblico.', 'Base', 23 ),
		array( 'gN9sOSbwwGc', 'Cinque errori da evitare durante un DJ set', 'Live e DJ set', 'Previeni gli errori più frequenti nella preparazione e nella gestione dell’esibizione.', 'Base', 24 ),
		array( 'uzGN_8tlxqQ', 'Come affrontare l’ansia da esibizione', 'Live e DJ set', 'Strategie concrete per gestire tensione, concentrazione e presenza davanti al pubblico.', 'Base', 25 ),
		array( 'nnlWxEvqMXw', 'Suonare davanti al pubblico con maggiore sicurezza', 'Live e DJ set', 'Prepara l’esibizione e sviluppa sicurezza senza perdere naturalezza e attenzione musicale.', 'Base', 26 ),
		array( '6z9NmF2HhR8', 'Come comunicare e muoversi sul palco', 'Live e DJ set', 'Introduzione alla presenza scenica, alla comunicazione e all’uso consapevole dello spazio.', 'Base/intermedio', 27 ),
		array( 'UY1k-zoO_bg', 'In-ear monitor: ascoltarsi correttamente dal vivo', 'Live e DJ set', 'Comprendi funzione, vantaggi e impostazione generale del monitoraggio personale.', 'Base', 28 ),
		array( 'jdi6XviNxsc', 'Come richiedere e gestire Spotify for Artists', 'Social e profili artista', 'Ottieni il controllo del profilo artista e gestisci correttamente le funzioni principali.', 'Base', 29 ),
		array( '6YNWOKxbusM', 'Come ottenere Apple Music for Artists', 'Social e profili artista', 'Richiedi direttamente l’accesso al profilo e distinguilo da un normale account Apple Music.', 'Base', 30 ),
		array( 'KjwAoZPrlL4', 'Come ottenere il Canale ufficiale artista YouTube', 'Social e profili artista', 'Comprendi requisiti e differenze tra canale personale, tematico e Official Artist Channel.', 'Base', 31 ),
		array( '0axdTRB1BIE', 'Che cos’è il canale tematico YouTube', 'Social e profili artista', 'Riconosci il canale generato automaticamente da YouTube ed evita di confonderlo con quello ufficiale.', 'Base', 32 ),
		array( 'PSYz_hyMRPg', 'Come caricare un Canvas su Spotify', 'Social e profili artista', 'Associa correttamente un contenuto verticale al brano tramite Spotify for Artists.', 'Base', 33 ),
		array( 'qeV1Wo_MNfI', 'Come rendere disponibile la propria musica su Instagram', 'Social e profili artista', 'Comprendi la presenza dei brani nella libreria musicale di storie e reel.', 'Base', 34 ),
		array( '_TCbMA1dMcU', 'Come recuperare o correggere un profilo Spotify', 'Social e profili artista', 'Intervieni quando una pubblicazione è associata a un omonimo o manca l’accesso al profilo corretto.', 'Base', 35 ),
		array( 'uR-UgAIQQRw', 'Come sviluppare un’identità artistica riconoscibile', 'Identità e branding', 'Definisci direzione, obiettivi e caratteristiche distintive del progetto artistico.', 'Base', 36 ),
		array( 'zifxjQiuFIg', 'Come esprimere concretamente la propria identità artistica', 'Identità e branding', 'Trasforma l’identità in scelte coerenti di repertorio, immagine e comunicazione.', 'Base', 37 ),
		array( 'tuEwiKbh29c', 'Come può evolvere l’identità di un artista', 'Identità e branding', 'Comprendi come far crescere il progetto mantenendo riconoscibilità e coerenza.', 'Base/intermedio', 38 ),
		array( '0uB7_YiA_UA', 'Come scrivere la biografia per il press kit', 'Identità e branding', 'Imposta una biografia utile a stampa, addetti ai lavori e presentazioni professionali.', 'Base', 39 ),
		array( '_tZY7b0_ZgU', 'Rendere coerenti musica, immagini e comunicazione', 'Identità e branding', 'Allinea cover, video, linguaggio e percezione complessiva del progetto musicale.', 'Base', 40 ),
		array( 'yQyV31WeS-c', 'Come realizzare un portfolio artistico efficace', 'Identità e branding', 'Organizza materiali e informazioni per una presentazione chiara e credibile.', 'Base', 41 ),
		array( '6zoIEd9HgLk', 'Come viene costruito e posizionato un progetto musicale', 'Identità e branding', 'Introduzione a management, posizionamento e costruzione del brand musicale.', 'Intermedio', 42 ),
		array( 'PBMYvZbDTu4', 'Creare contenuti senza trasformarsi in influencer', 'Contenuti video', 'Costruisci una presenza editoriale sostenibile senza snaturare l’attività artistica.', 'Base', 43 ),
		array( '7sqUlCAXHaU', 'Come creare reel e TikTok per un progetto musicale', 'Contenuti video', 'Imposta contenuti verticali adatti a solisti, gruppi e altre formazioni musicali.', 'Base', 44 ),
		array( 'I0S70j3FXvg', 'Come realizzare un reel efficace', 'Contenuti video', 'Dall’idea al montaggio: principi essenziali per un video verticale chiaro e coinvolgente.', 'Base', 45 ),
		array( 'BojhsaoKJ2I', 'Montare un video verticale con CapCut', 'Contenuti video', 'Usa gli strumenti essenziali per tagliare, montare e completare reel e TikTok.', 'Base', 46 ),
		array( 'w18pD6nqXUI', 'Creare un visual video con Canva', 'Contenuti video', 'Realizza teaser, annunci e visualizer semplici combinando immagini, musica e testi.', 'Base', 47 ),
		array( 'zYHZJBMvW6Y', 'Quando serve davvero un videoclip musicale', 'Contenuti video', 'Valuta quando investire in un videoclip e quando privilegiare contenuti verticali più frequenti.', 'Base/intermedio', 48 ),
		array( 'At2haa6sQ2A', 'Creare un video musicale con l’intelligenza artificiale', 'Contenuti video', 'Introduzione alla generazione di immagini e sequenze per un contenuto musicale.', 'Base', 49 ),
		array( 'fHiiX7dZq6E', 'Master, copyright e distribuzione musicale', 'Music business', 'Comprendi proprietà dei master, tutela dei diritti e funzione della distribuzione.', 'Base', 50 ),
		array( 'LNTJvB9ihoQ', 'Come pianificare l’uscita di un nuovo singolo', 'Music business', 'Prepara pubblicazione, materiali e comunicazione con una sequenza coerente.', 'Base', 51 ),
		array( '5v0oO4mr7Co', 'Come promuovere la propria musica', 'Music business', 'Una panoramica di music marketing e sviluppo consapevole del progetto.', 'Base/intermedio', 52 ),
		array( 'B7u-FBQUqkI', 'Come presentare la propria musica ai curatori', 'Music business', 'Prepara un invio chiaro e pertinente per playlist e curatori editoriali.', 'Base', 53 ),
		array( 'bucflmOyU2A', 'Come pubblicare una cover senza violare il copyright', 'Music business', 'Comprendi autorizzazioni e principali cautele per distribuire una reinterpretazione.', 'Base/intermedio', 54 ),
		array( '91_SFWpiihw', 'Tre esercizi per allenare la scrittura di un testo', 'Scrittura e composizione', 'Esercizi guidati per superare il blocco iniziale e sviluppare immagini, lessico e continuità narrativa.', 'Base', 62 ),
		array( 'YxWdz4z0q8w', 'Costruire la dinamica di un arrangiamento', 'Scrittura e composizione', 'Organizza densità, intensità e contrasti affinché le sezioni del brano accompagnino l’ascolto.', 'Base/intermedio', 63 ),
		array( 'pGDF-5U4UHk', 'Scrittura musicale: metodo, intenzione e riconoscibilità', 'Scrittura e composizione', 'Una masterclass sulla costruzione delle idee e sulle scelte che rendono personale una canzone.', 'Intermedio', 64 ),
		array( '-ObFU8gsdkA', 'Creare un hook riconoscibile', 'Scrittura e composizione', 'Comprendi la funzione del gancio melodico o testuale e usalo senza rendere artificiale il brano.', 'Base/intermedio', 65 ),
		array( '6Ay9Y_g5X64', 'Proteggere e mantenere sana la voce', 'Canto e interpretazione', 'Dieci indicazioni di igiene vocale per affrontare prove, registrazioni ed esibizioni con maggiore consapevolezza.', 'Base', 66 ),
		array( 'vG7QmEX2fN0', 'Dizione e chiarezza nella voce cantata', 'Canto e interpretazione', 'Articola parole e consonanti preservando naturalezza, intenzione e qualità del suono.', 'Base/intermedio', 67 ),
		array( 'LK5j3Cohp3w', 'Creare cori e armonizzazioni vocali', 'Canto e interpretazione', 'Esercizi pratici per riconoscere gli intervalli e costruire seconde voci coerenti con la melodia.', 'Base/intermedio', 68 ),
		array( 'AwkCX7rIgzA', 'Prepararsi a una sessione di registrazione vocale', 'Canto e interpretazione', 'Organizza testo, tonalità, riscaldamento e ascolto in cuffia prima di iniziare le riprese.', 'Base', 69 ),
		array( 'Gc11_a_UOdE', 'Controllare fase e compatibilità mono', 'Mixaggio e mastering', 'Verifica correlazione e cancellazioni affinché il mix rimanga solido anche fuori dall’ascolto stereo ideale.', 'Intermedio', 70 ),
		array( '8BPu8dFygOw', 'Comprendere e usare correttamente la compressione', 'Mixaggio e mastering', 'Impara funzione e parametri del compressore evitando di ridurre la dinamica senza una finalità precisa.', 'Base/intermedio', 71 ),
		array( 'dWANq78ntnM', 'Equalizzazione: principi e metodo di ascolto', 'Mixaggio e mastering', 'Riconosci frequenze, filtri e interventi utili senza affidarti a correzioni casuali.', 'Base', 72 ),
		array( 'AA66AIBGhuw', 'Usare una traccia di riferimento nel mix', 'Mixaggio e mastering', 'Confronta equilibrio, dinamica e immagine sonora mantenendo l’identità della produzione.', 'Intermedio', 73 ),
		array( '7S9uyrQcGgQ', 'Soundcheck, palco e monitor: istruzioni essenziali', 'Live e DJ set', 'Comunica con il fonico e prepara livelli, monitoraggio e disposizione sul palco senza rallentare il soundcheck.', 'Base', 74 ),
		array( 'POaJ5Thpk34', 'Costruire una scaletta efficace per un concerto', 'Live e DJ set', 'Ordina repertorio, pause e cambi di intensità per sostenere attenzione e coerenza dello spettacolo.', 'Base', 75 ),
		array( 'c2p0m8TNAmQ', 'Spotify for Artists: funzioni e gestione del profilo', 'Social e profili artista', 'Panoramica degli strumenti disponibili per controllare informazioni, pubblico e presentazione del profilo ufficiale.', 'Base', 76 ),
		array( 'Djd-TSGihuU', 'Branding musicale: rendere riconoscibile il progetto', 'Identità e branding', 'Collega identità, immagine, tono e continuità visiva senza imitare modelli estranei al progetto.', 'Base', 77 ),
		array( 'Xg5Yb3yLJpQ', 'Illuminare correttamente reel e video verticali', 'Contenuti video', 'Imposta una luce semplice e leggibile per migliorare i contenuti realizzati con smartphone e attrezzatura accessibile.', 'Base', 78 ),
		array( 'pLgHqI3ED6I', 'Diritto d’autore e società di collecting', 'Music business', 'Matteo Fedeli di SIAE spiega funzione del diritto d’autore, tutela delle opere e gestione collettiva dei compensi.', 'Base', 79 ),
		array( 'DWM2DVM12pg', 'Il ruolo dell’A&R nello sviluppo di un progetto', 'Music business', 'Comprendi valutazione, repertorio e rapporto tra artista e struttura attraverso l’esperienza di Sony Music Italy.', 'Base', 80 ),
		array( 'Y62DH2aENHQ', 'Il ruolo del manager musicale', 'Music business', 'Paola Zukar descrive responsabilità, relazioni professionali e collaborazioni che accompagnano la crescita artistica.', 'Base', 81 ),
		array( 'O1dt42cW6U8', 'Codice ISRC: identità e tracciamento di una registrazione', 'Music business', 'Comprendi perché ogni registrazione necessita di un identificativo corretto e perché non deve essere duplicato o inventato.', 'Base', 82 ),
		array( 'sCuuqAjJz-8', 'Autore, artista e produttore di fonogrammi', 'Music business', 'Distingui ruoli e diritti delle principali figure coinvolte nella creazione e pubblicazione di una registrazione.', 'Base', 83 ),
		array( 'ldzX7Oret68', 'Autori, compositori, arrangiatori e interpreti', 'Music business', 'Riconosci correttamente i contributi creativi ed esecutivi da dichiarare nei crediti e nei metadati.', 'Base', 84 ),
		array( 'U5qC6u2MYtw', 'Streaming artificiale: rischi e conseguenze', 'Music business', 'Spotify for Artists spiega come riconoscere pratiche non autentiche e perché compromettono dati, royalty e distribuzione.', 'Base/intermedio · sottotitoli', 85 ),
	);
}

function trb_portal_seed_video_lessons() {
	if ( get_option( 'trb_portal_video_lessons_seeded_v4' ) ) return;
	$interface_tutorials = array( 'jdi6XviNxsc', '6YNWOKxbusM', 'KjwAoZPrlL4', '0axdTRB1BIE', 'PSYz_hyMRPg', 'qeV1Wo_MNfI', '_TCbMA1dMcU', '7sqUlCAXHaU', 'I0S70j3FXvg', 'BojhsaoKJ2I', 'w18pD6nqXUI', 'c2p0m8TNAmQ' );
	$new_registration_lessons = array( '8Dhb9p68Ido', 'WCl0IYLW4Mg', 'DPf5Bkvj54A', 'XNQY89OXje8', 'sDa7rVQGH1Q', 'Y5qiNz7QQNI', 'Tx4cIS3wHLQ' );
	$editorial_review = array(
		'fHiiX7dZq6E' => array( 'distribuzione_autonoma', 'informazioni_legali_non_verificate' ),
		'5v0oO4mr7Co' => array( 'distribuzione_autonoma', 'promesse_streaming' ),
		'B7u-FBQUqkI' => array( 'pitching_diretto' ),
	);
	foreach ( trb_portal_video_seed_data() as $lesson ) {
		$existing = get_posts( array( 'post_type' => 'video', 'post_status' => 'any', 'meta_key' => '_trb_video_youtube', 'meta_value' => $lesson[0], 'fields' => 'ids', 'posts_per_page' => 1 ) );
		$post_id = $existing ? $existing[0] : wp_insert_post( array( 'post_type' => 'video', 'post_status' => 'publish', 'post_title' => $lesson[1], 'post_excerpt' => $lesson[3] ) );
		if ( ! $post_id || is_wp_error( $post_id ) ) continue;
		update_post_meta( $post_id, '_trb_video_youtube', $lesson[0] );
		update_post_meta( $post_id, '_trb_video_category', $lesson[2] );
		update_post_meta( $post_id, '_trb_video_level', $lesson[4] );
		$order = $lesson[5];
		if ( $order >= 15 && $order <= 54 && ! in_array( $lesson[0], $new_registration_lessons, true ) ) $order += 7;
		update_post_meta( $post_id, '_trb_video_order', $order );
		update_post_meta( $post_id, '_trb_video_language', 'Italiano' );
		update_post_meta( $post_id, '_trb_video_why', $lesson[3] );
		update_post_meta( $post_id, '_trb_video_objectives', "Comprendere i principi presentati nella lezione.\nRiconoscere gli errori più frequenti.\nApplicare il metodo al proprio progetto musicale." );
		update_post_meta( $post_id, '_trb_video_exercise', 'Applica un principio della lezione a un brano o a una sessione reale e annota il risultato prima e dopo la modifica.' );
		$status = isset( $editorial_review[ $lesson[0] ] ) ? 'review' : ( 'bucflmOyU2A' === $lesson[0] ? 'rejected' : 'approved' );
		update_post_meta( $post_id, '_trb_video_editorial_status', $status );
		update_post_meta( $post_id, '_trb_video_content_risks', isset( $editorial_review[ $lesson[0] ] ) ? $editorial_review[ $lesson[0] ] : ( 'bucflmOyU2A' === $lesson[0] ? array( 'cover_remix', 'informazioni_legali_non_verificate' ) : array() ) );
		if ( 'approved' === $status && ! get_post_meta( $post_id, '_trb_video_editorial_reviewed_at', true ) ) update_post_meta( $post_id, '_trb_video_editorial_reviewed_at', gmdate( 'Y-m-d' ) );
		if ( 'bucflmOyU2A' === $lesson[0] ) wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
		if ( in_array( $lesson[0], $interface_tutorials, true ) ) {
			update_post_meta( $post_id, '_trb_video_interface_tutorial', '1' );
			if ( ! get_post_meta( $post_id, '_trb_video_last_content_review', true ) ) update_post_meta( $post_id, '_trb_video_last_content_review', gmdate( 'Y-m-d' ) );
		}
		if ( ! $existing ) update_post_meta( $post_id, '_trb_video_available', '0' );
		update_post_meta( $post_id, '_trb_portal_profiles', trb_portal_allowed_profiles() );
	}
	update_option( 'trb_portal_video_lessons_seeded_v4', time(), false );
	if ( ! wp_next_scheduled( 'trb_portal_initial_video_check' ) ) wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'trb_portal_initial_video_check' );
}
add_action( 'init', 'trb_portal_seed_video_lessons', 38 );

function trb_portal_video_lessons( $profile ) {
	$posts = get_posts( array( 'post_type' => 'video', 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => '_trb_video_order', 'orderby' => 'meta_value_num', 'order' => 'ASC' ) );
	return array_values( array_filter( $posts, function( $post ) use ( $profile ) { return trb_portal_resource_is_visible( $post->ID ) && 'approved' === get_post_meta( $post->ID, '_trb_video_editorial_status', true ) && '0' !== get_post_meta( $post->ID, '_trb_video_available', true ); } ) );
}

function trb_portal_video_progress() {
	$progress = get_user_meta( get_current_user_id(), '_trb_video_progress', true );
	return is_array( $progress ) ? $progress : array();
}

function trb_portal_render_video_library( $profile ) {
	$videos = trb_portal_video_lessons( $profile );
	$progress = trb_portal_video_progress();
	$completed = count( array_filter( $progress, function( $item ) { return ! empty( $item['completed_at'] ); } ) );
	?>
	<section id="video" class="trb-portal__section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">KNOWLEDGE HUB</p><h2>Video e formazione</h2><p>Un percorso consigliato, ma non obbligatorio, che accompagna il progetto dall’idea alla preparazione finale.</p></div>
		<?php if ( empty( $videos ) ) : ?>
			<div class="trb-portal__empty"><p>La videoteca essenziale per il tuo profilo è in preparazione.</p></div>
		<?php else : ?>
			<div class="trb-video__toolbar"><div class="trb-video__progress"><strong><?php echo esc_html( $completed ); ?> lezioni completate su <?php echo esc_html( count( $videos ) ); ?></strong><span><i style="width:<?php echo esc_attr( count( $videos ) ? round( $completed / count( $videos ) * 100 ) : 0 ); ?>%"></i></span></div><div class="trb-video__search-row"><input type="search" placeholder="Cerca una lezione" aria-label="Cerca una lezione" data-video-search /><select data-video-state aria-label="Filtra per stato"><option value="">Tutti gli stati</option><option value="Da iniziare">Da iniziare</option><option value="In corso">In corso</option><option value="Completato">Completati</option></select></div><div class="trb-video__filters" role="group" aria-label="Filtra le lezioni"><button type="button" data-video-category="" class="is-active">Tutte</button><?php foreach ( array( 'Scrittura e composizione', 'Canto e interpretazione', 'Registrazione', 'Mixaggio e mastering', 'Live e DJ set', 'Social e profili artista', 'Identità e branding', 'Contenuti video', 'Music business' ) as $category ) : ?><button type="button" data-video-category="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></button><?php endforeach; ?></div></div>
			<div class="trb-portal__video-grid" data-video-grid>
				<?php foreach ( $videos as $video ) : $youtube = get_post_meta( $video->ID, '_trb_video_youtube', true ); $category = get_post_meta( $video->ID, '_trb_video_category', true ); $item_progress = isset( $progress[ $video->ID ] ) ? $progress[ $video->ID ] : array(); $state = ! empty( $item_progress['completed_at'] ) ? 'Completato' : ( ! empty( $item_progress['started_at'] ) ? 'In corso' : 'Da iniziare' ); ?>
					<article class="trb-portal__video-card" data-video-card data-category="<?php echo esc_attr( $category ); ?>" data-state="<?php echo esc_attr( $state ); ?>" data-search="<?php echo esc_attr( strtolower( $video->post_title . ' ' . $video->post_excerpt . ' ' . $category ) ); ?>"><button type="button" class="trb-video__open" data-video-open="<?php echo esc_attr( $video->ID ); ?>"><span class="trb-video__thumb"><img src="https://i.ytimg.com/vi/<?php echo esc_attr( $youtube ); ?>/hqdefault.jpg" alt="" loading="lazy" /><i aria-hidden="true">▶</i></span><small><?php echo esc_html( $category ); ?></small><h3><?php echo esc_html( $video->post_title ); ?></h3><p><?php echo esc_html( $video->post_excerpt ); ?></p><span class="trb-video__meta"><?php echo esc_html( get_post_meta( $video->ID, '_trb_video_level', true ) ); ?> · Italiano · <?php echo esc_html( $state ); ?></span><b><?php echo 'Completato' === $state ? 'Rivedi' : ( 'In corso' === $state ? 'Continua' : 'Inizia la lezione' ); ?></b></button></article>
					<template id="trb-video-<?php echo esc_attr( $video->ID ); ?>"><div class="trb-video__lesson" data-lesson-id="<?php echo esc_attr( $video->ID ); ?>" data-youtube="<?php echo esc_attr( $youtube ); ?>" data-last-position="<?php echo esc_attr( isset( $item_progress['last_position_seconds'] ) ? $item_progress['last_position_seconds'] : 0 ); ?>"><p class="trb-portal__eyebrow"><?php echo esc_html( $category ); ?></p><h2><?php echo esc_html( $video->post_title ); ?></h2><p class="trb-video__lesson-meta"><?php echo esc_html( get_post_meta( $video->ID, '_trb_video_level', true ) ); ?> · Italiano · Lezione <?php echo esc_html( get_post_meta( $video->ID, '_trb_video_order', true ) ); ?> di <?php echo esc_html( count( $videos ) ); ?></p><div class="trb-video__player" data-video-player><button type="button" data-video-play><img src="https://i.ytimg.com/vi/<?php echo esc_attr( $youtube ); ?>/hqdefault.jpg" alt="Avvia <?php echo esc_attr( $video->post_title ); ?>" /><span>Avvia la lezione</span></button></div><p class="trb-video__author">Contenuto realizzato dal canale originale indicato su YouTube<?php $author = get_post_meta( $video->ID, '_trb_video_author', true ); echo $author ? ': ' . esc_html( $author ) : ''; ?>.</p><h3>Perché guardare questa lezione</h3><p><?php echo esc_html( get_post_meta( $video->ID, '_trb_video_why', true ) ); ?></p><h3>Cosa imparerai</h3><ul><?php foreach ( preg_split( '/\r\n|\r|\n/', get_post_meta( $video->ID, '_trb_video_objectives', true ) ) as $objective ) : if ( trim( $objective ) ) : ?><li><?php echo esc_html( $objective ); ?></li><?php endif; endforeach; ?></ul><h3>Mettilo in pratica</h3><p><?php echo esc_html( get_post_meta( $video->ID, '_trb_video_exercise', true ) ); ?></p><div class="trb-video__lesson-actions"><button type="button" class="trb-button" data-video-complete>Ho completato questa lezione</button><a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $youtube ); ?>" target="_blank" rel="noopener">Apri su YouTube ↗</a></div><p class="trb-video__completion" data-video-completion hidden></p></div></template>
				<?php endforeach; ?>
			</div>
			<dialog class="trb-video__dialog" data-video-dialog><button type="button" class="trb-video__close" data-video-close aria-label="Chiudi la lezione">×</button><div data-video-dialog-content></div></dialog>
		<?php endif; ?>
	</section>
	<?php
}

function trb_portal_video_lesson_metabox() {
	add_meta_box( 'trb-video-lesson', 'Dati della lezione', 'trb_portal_render_video_lesson_metabox', 'video', 'normal', 'high' );
}
add_action( 'add_meta_boxes_video', 'trb_portal_video_lesson_metabox' );

function trb_portal_render_video_lesson_metabox( $post ) {
	wp_nonce_field( 'trb_video_lesson_meta', 'trb_video_lesson_nonce' );
	$fields = array( 'youtube' => 'ID video YouTube', 'category' => 'Categoria', 'level' => 'Livello', 'duration' => 'Durata', 'language' => 'Lingua', 'order' => 'Ordine', 'author' => 'Canale / autore originale', 'why' => 'Perché guardare questa lezione', 'objectives' => 'Obiettivi didattici (uno per riga)', 'exercise' => 'Esercizio finale' );
	foreach ( $fields as $key => $label ) : $value = get_post_meta( $post->ID, '_trb_video_' . $key, true ); ?>
		<p><label><strong><?php echo esc_html( $label ); ?></strong><br /><?php if ( in_array( $key, array( 'why', 'objectives', 'exercise' ), true ) ) : ?><textarea name="trb_video_<?php echo esc_attr( $key ); ?>" rows="4" style="width:100%"><?php echo esc_textarea( $value ); ?></textarea><?php else : ?><input type="text" name="trb_video_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" style="width:100%" /><?php endif; ?></label></p>
	<?php endforeach; ?>
	<p><label><strong>Ultima revisione del contenuto</strong><br /><input type="date" name="trb_video_last_content_review" value="<?php echo esc_attr( get_post_meta( $post->ID, '_trb_video_last_content_review', true ) ); ?>" style="width:100%" /></label><small style="display:block;margin-top:5px">Obbligatoria almeno ogni sei mesi per tutorial su Spotify, Apple Music, YouTube, social e software.</small></p>
	<?php
	$status = get_post_meta( $post->ID, '_trb_video_editorial_status', true );
	$risks = (array) get_post_meta( $post->ID, '_trb_video_content_risks', true );
	$risk_options = array( 'critica_etichette' => 'Critica alle etichette', 'distribuzione_autonoma' => 'Distribuzione autonoma', 'pitching_diretto' => 'Pitching diretto', 'cover_remix' => 'Cover / remix', 'trasferimento_catalogo' => 'Trasferimento catalogo', 'promesse_streaming' => 'Promesse di streaming', 'acquisto_follower_playlist' => 'Acquisto follower / playlist', 'informazioni_legali_non_verificate' => 'Informazioni legali non verificate' );
	?>
	<hr /><p><label><strong>Compatibilità con TRB</strong><br /><select name="trb_video_editorial_status" style="width:100%"><option value="review" <?php selected( $status, 'review' ); ?>>Da revisionare</option><option value="approved" <?php selected( $status, 'approved' ); ?>>Approvato</option><option value="rejected" <?php selected( $status, 'rejected' ); ?>>Respinto</option></select></label></p>
	<fieldset><legend><strong>Rischi del contenuto</strong></legend><?php foreach ( $risk_options as $risk_key => $risk_label ) : ?><label style="display:block;margin:6px 0"><input type="checkbox" name="trb_video_content_risks[]" value="<?php echo esc_attr( $risk_key ); ?>" <?php checked( in_array( $risk_key, $risks, true ) ); ?> /> <?php echo esc_html( $risk_label ); ?></label><?php endforeach; ?></fieldset>
	<p><label><strong>Revisionato da</strong><br /><input type="text" name="trb_video_editorial_reviewed_by" value="<?php echo esc_attr( get_post_meta( $post->ID, '_trb_video_editorial_reviewed_by', true ) ); ?>" style="width:100%" /></label></p>
	<p><label><strong>Data revisione editoriale</strong><br /><input type="date" name="trb_video_editorial_reviewed_at" value="<?php echo esc_attr( get_post_meta( $post->ID, '_trb_video_editorial_reviewed_at', true ) ); ?>" style="width:100%" /></label></p>
	<p><label><strong>Note interne</strong><br /><textarea name="trb_video_editorial_notes" rows="4" style="width:100%"><?php echo esc_textarea( get_post_meta( $post->ID, '_trb_video_editorial_notes', true ) ); ?></textarea></label></p>
	<?php
}

function trb_portal_save_video_lesson_meta( $post_id ) {
	if ( ! isset( $_POST['trb_video_lesson_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trb_video_lesson_nonce'] ) ), 'trb_video_lesson_meta' ) || ! current_user_can( 'edit_post', $post_id ) ) return;
	foreach ( array( 'youtube', 'category', 'level', 'duration', 'language', 'order', 'author', 'why', 'objectives', 'exercise' ) as $key ) {
		if ( isset( $_POST[ 'trb_video_' . $key ] ) ) update_post_meta( $post_id, '_trb_video_' . $key, 'objectives' === $key ? sanitize_textarea_field( wp_unslash( $_POST[ 'trb_video_' . $key ] ) ) : sanitize_text_field( wp_unslash( $_POST[ 'trb_video_' . $key ] ) ) );
	}
	if ( isset( $_POST['trb_video_last_content_review'] ) ) update_post_meta( $post_id, '_trb_video_last_content_review', sanitize_text_field( wp_unslash( $_POST['trb_video_last_content_review'] ) ) );
	if ( isset( $_POST['trb_video_editorial_status'] ) ) update_post_meta( $post_id, '_trb_video_editorial_status', in_array( sanitize_key( wp_unslash( $_POST['trb_video_editorial_status'] ) ), array( 'approved', 'review', 'rejected' ), true ) ? sanitize_key( wp_unslash( $_POST['trb_video_editorial_status'] ) ) : 'review' );
	$risk_options = array( 'critica_etichette', 'distribuzione_autonoma', 'pitching_diretto', 'cover_remix', 'trasferimento_catalogo', 'promesse_streaming', 'acquisto_follower_playlist', 'informazioni_legali_non_verificate' );
	$risks = isset( $_POST['trb_video_content_risks'] ) ? array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['trb_video_content_risks'] ) ), $risk_options ) : array();
	update_post_meta( $post_id, '_trb_video_content_risks', array_values( $risks ) );
	if ( isset( $_POST['trb_video_editorial_reviewed_by'] ) ) update_post_meta( $post_id, '_trb_video_editorial_reviewed_by', sanitize_text_field( wp_unslash( $_POST['trb_video_editorial_reviewed_by'] ) ) );
	if ( isset( $_POST['trb_video_editorial_reviewed_at'] ) ) update_post_meta( $post_id, '_trb_video_editorial_reviewed_at', sanitize_text_field( wp_unslash( $_POST['trb_video_editorial_reviewed_at'] ) ) );
	if ( isset( $_POST['trb_video_editorial_notes'] ) ) update_post_meta( $post_id, '_trb_video_editorial_notes', sanitize_textarea_field( wp_unslash( $_POST['trb_video_editorial_notes'] ) ) );
}
add_action( 'save_post_video', 'trb_portal_save_video_lesson_meta' );

function trb_portal_rest_video_progress( WP_REST_Request $request ) {
	$lesson_id = absint( $request->get_param( 'lesson_id' ) );
	if ( 'video' !== get_post_type( $lesson_id ) || ! trb_portal_resource_is_visible( $lesson_id ) ) return new WP_Error( 'invalid_lesson', 'Lezione non disponibile.', array( 'status' => 403 ) );
	$progress = trb_portal_video_progress();
	$item = isset( $progress[ $lesson_id ] ) ? $progress[ $lesson_id ] : array();
	if ( empty( $item['started_at'] ) ) $item['started_at'] = time();
	$item['last_position_seconds'] = max( 0, (float) $request->get_param( 'position' ) );
	$item['watched_percentage'] = min( 100, max( 0, (float) $request->get_param( 'percentage' ) ) );
	$duration = max( 0, (float) $request->get_param( 'duration' ) );
	if ( $duration ) update_post_meta( $lesson_id, '_trb_video_duration_seconds', round( $duration ) );
	$manual = (bool) $request->get_param( 'manual' );
	if ( $manual || $item['watched_percentage'] >= 80 ) {
		$item['completed_at'] = time();
		$item['completion_method'] = $manual ? 'manual' : 'watched_80_percent';
	}
	$progress[ $lesson_id ] = $item;
	update_user_meta( get_current_user_id(), '_trb_video_progress', $progress );
	return rest_ensure_response( array( 'success' => true, 'progress' => $item ) );
}

function trb_portal_register_video_progress_route() {
	register_rest_route( 'trb/v1', '/video-progress', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => 'trb_portal_rest_video_progress', 'permission_callback' => function() { return is_user_logged_in() && ( trb_portal_user_profile() || current_user_can( 'manage_options' ) ); } ) );
}
add_action( 'rest_api_init', 'trb_portal_register_video_progress_route' );

function trb_portal_schedule_video_checks() {
	if ( ! wp_next_scheduled( 'trb_portal_weekly_video_check' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'trb_portal_weekly_video_check' );
}
function trb_portal_weekly_schedule( $schedules ) {
	$schedules['weekly'] = array( 'interval' => WEEK_IN_SECONDS, 'display' => 'Una volta alla settimana' );
	return $schedules;
}
add_filter( 'cron_schedules', 'trb_portal_weekly_schedule' );
add_action( 'init', 'trb_portal_schedule_video_checks', 40 );

function trb_portal_check_video_availability() {
	foreach ( get_posts( array( 'post_type' => 'video', 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $video ) {
		$id = get_post_meta( $video->ID, '_trb_video_youtube', true );
		$response = wp_remote_get( 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( 'https://www.youtube.com/watch?v=' . $id ), array( 'timeout' => 12 ) );
		$available = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
		update_post_meta( $video->ID, '_trb_video_available', $available ? '1' : '0' );
		update_post_meta( $video->ID, '_trb_video_last_check', time() );
		if ( $available ) { $data = json_decode( wp_remote_retrieve_body( $response ), true ); if ( ! empty( $data['author_name'] ) ) update_post_meta( $video->ID, '_trb_video_author', sanitize_text_field( $data['author_name'] ) ); }
	}
}
add_action( 'trb_portal_weekly_video_check', 'trb_portal_check_video_availability' );
add_action( 'trb_portal_initial_video_check', 'trb_portal_check_video_availability' );

function trb_portal_request_catalogue() {
	return array(
		'cover' => array(
			'label'    => 'Richiedi la copertina ufficiale',
			'profiles' => array( 'ddb_trb', 'trb' ),
			'copy'     => 'Invia il brief creativo, le reference e le indicazioni utili per la copertina della release.',
		),
		'profile' => array(
			'label'    => 'Aggiorna i profili artista',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
			'copy'     => 'Segnala biografia, immagini, link e materiali da aggiornare su Spotify e Apple Music.',
		),
		'demo' => array(
			'label'    => 'Invia un demo per valutazione',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
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
		return '<section class="trb-portal-login"><h2>Area Artisti TRB rec</h2><p>Accedi per consultare i materiali riservati al tuo profilo.</p><p><a class="trb-button" href="' . esc_url( add_query_arg( 'redirect_to', get_permalink(), home_url( '/accedi/' ) ) ) . '">Accedi all’area riservata</a></p></section>';
	}

	$profile = trb_portal_user_profile();
	if ( ! $profile && ! current_user_can( 'manage_options' ) ) {
		return '<section class="trb-portal-notice"><h2>Profilo in attivazione</h2><p>Il tuo accesso è in fase di configurazione. Riceverai conferma via e-mail appena il profilo sarà attivo.</p></section>';
	}

	$user      = wp_get_current_user();
	$profile   = $profile ? $profile : 'trb';
	$resources = trb_portal_get_resources( $profile );
	$requests  = trb_portal_request_catalogue();
	$affiliation = trb_portal_profile_affiliation( $profile );
	$first_name  = trim( (string) get_user_meta( $user->ID, 'first_name', true ) );
	if ( '' === $first_name ) {
		$first_name = trim( (string) trb_portal_artist_profile_value( 'legal_name', $user->ID ) );
	}
	if ( '' === $first_name ) {
		$first_name = 'Artista';
	}

	ob_start();
	?>
	<div class="trb-portal" data-profile="<?php echo esc_attr( $profile ); ?>">
		<header class="trb-portal__hero">
			<div>
				<p class="trb-portal__eyebrow">PORTALE ARTISTI &middot; AREA RISERVATA</p>
				<h1>Ciao <?php echo esc_html( $first_name ); ?>.</h1>
				<p>Knowledge Hub: Linee guida, procedure, formazione e supporto per il percorso artistico.</p>
			</div>
			<div class="trb-portal__profile"><span>Sei un artista:</span><strong><?php echo esc_html( $affiliation ); ?></strong></div>
		</header>

		<nav class="trb-portal__nav" aria-label="Sezioni Area Artisti">
			<a href="#profilo">Profilo artista</a>
			<a href="#release">Le tue release</a>
			<a href="#demo">Valuta un demo</a>
			<a href="#risposte">Risposte rapide</a>
			<a href="#download">Guide ed e-book</a>
			<a href="#video">Video</a>
		</nav>

		<section class="trb-portal__search-panel" aria-labelledby="trb-portal-search-title">
			<div><p class="trb-portal__eyebrow">KNOWLEDGE HUB</p><h2 id="trb-portal-search-title">Trova subito la risposta che ti serve</h2><p>Cerca fra guide aggiornate, procedure e materiali disponibili per il tuo profilo. Le risposte si aprono qui, senza uscire dalla pagina.</p></div>
			<form class="trb-portal__search" method="get" action="<?php echo esc_url( get_permalink() ); ?>">
				<label class="screen-reader-text" for="trb-portal-search">Cerca nella Knowledge Hub</label>
				<input id="trb-portal-search" type="search" name="trb_search" value="<?php echo esc_attr( trb_portal_current_search() ); ?>" placeholder="Es. formato audio, copertina, tempi di pubblicazione" />
				<button type="submit">Cerca</button>
			</form>
			<p class="trb-portal__search-suggestions">Prova: <a href="?trb_search=formato+audio#risposte">formato audio</a> &middot; <a href="?trb_search=copertina#risposte">copertina</a> &middot; <a href="?trb_search=tempistiche#risposte">tempistiche</a></p>
			<?php if ( trb_portal_current_search() ) : ?><?php trb_portal_render_search_results( trb_portal_get_search_results( $profile, trb_portal_current_search() ), trb_portal_current_search() ); ?><?php endif; ?>
		</section>

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

		<?php if ( ! trb_portal_current_search() ) : ?><?php trb_portal_render_resource_section( 'risposte', 'Risposte rapide', 'Le guide essenziali per preparare una release senza passaggi inutili.', $resources['trb_guide'] ); ?><?php endif; ?>
		<?php trb_portal_render_resource_section( 'download', 'Guide ed e-book', 'Manuali e approfondimenti scaricabili selezionati per il tuo percorso.', $resources['wpdmpro'] ); ?>
		<?php trb_portal_render_video_library( $profile ); ?>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'trb_artist_portal', 'trb_portal_dashboard_shortcode' );

function trb_portal_render_artist_profile_section() {
	$saved = isset( $_GET['trb_profile'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['trb_profile'] ) );
	$invalid_address = isset( $_GET['trb_profile'] ) && 'invalid_address' === sanitize_key( wp_unslash( $_GET['trb_profile'] ) );
	$profile_error = isset( $_GET['trb_profile'] ) ? sanitize_key( wp_unslash( $_GET['trb_profile'] ) ) : '';
	$complete = trb_portal_artist_profile_is_complete();
	$company_requested = '1' === trb_portal_artist_profile_value( 'invoice_requested' );
	$user = wp_get_current_user();
	$profile = trb_portal_user_profile();
	$artist_name = trb_portal_artist_profile_value( 'artist_name' );
	?>
	<section id="profilo" class="trb-portal__section trb-portal__profile-section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">PRIMO PASSAGGIO OBBLIGATORIO</p><h2>Aggiorna il profilo artista</h2><p>Prima della prima release servono dati completi e verificabili. Li riuseremo per preparare le pratiche e, in seguito, i contratti.</p></div>
		<?php if ( $saved ) : ?><div class="trb-portal__message trb-portal__message--success">Profilo artista aggiornato.</div><?php endif; ?>
		<?php if ( $invalid_address ) : ?><div class="trb-portal__message trb-portal__message--error">Indirizzo non salvato: il Comune non corrisponde al CAP indicato. Inserisci nuovamente il CAP e seleziona il Comune proposto.</div><?php endif; ?>
		<?php if ( 'invalid_birthplace' === $profile_error ) : ?><div class="trb-portal__message trb-portal__message--error">Dati non salvati: seleziona Comune e Provincia di nascita fra i risultati dell’archivio italiano.</div><?php endif; ?>
		<?php if ( 'invalid_phone' === $profile_error ) : ?><div class="trb-portal__message trb-portal__message--error">Dati non salvati: inserisci un numero di cellulare italiano valido, con 10 cifre e iniziale 3; il prefisso +39 è facoltativo.</div><?php endif; ?>
		<?php if ( 'invalid_tax_code' === $profile_error ) : ?><div class="trb-portal__message trb-portal__message--error">Dati non salvati: il codice fiscale non supera il controllo formale e della lettera finale. Verifica attentamente i 16 caratteri.</div><?php endif; ?>
		<?php if ( ! $complete ) : ?><div class="trb-portal__message trb-portal__message--error">Completa attentamente entrambi i moduli qui sotto prima di avviare la tua prima release. Per correggere nome, cognome o e-mail dell’account, apri una segnalazione.</div><?php endif; ?>
		<div class="trb-portal__profile-accordions">
			<details class="trb-portal__profile-module" open>
				<summary><span><b>Dati anagrafici e documenti</b><small>Dati necessari ai fini contrattuali</small></span><em>Apri il modulo</em></summary>
				<form class="trb-portal__request-form trb-portal__profile-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="trb_portal_save_artist_profile" /><input type="hidden" name="trb_artist_company_section" value="1" />
					<?php wp_nonce_field( 'trb_portal_save_artist_profile', 'trb_portal_profile_nonce' ); ?>
					<div class="trb-portal__field-grid">
						<label>Nome anagrafico <span>*</span><input type="text" value="<?php echo esc_attr( $user->first_name ); ?>" autocomplete="given-name" readonly aria-describedby="trb-account-data-note" /></label>
						<label>Cognome anagrafico <span>*</span><input type="text" value="<?php echo esc_attr( $user->last_name ); ?>" autocomplete="family-name" readonly aria-describedby="trb-account-data-note" /></label>
						<label>E-mail di riferimento <span>*</span><input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" autocomplete="email" readonly aria-describedby="trb-account-data-note" /></label>
						<label>Cellulare abilitato a ricezione SMS <span>*</span><input type="tel" name="trb_artist_phone" autocomplete="tel" inputmode="tel" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'phone' ) ); ?>" placeholder="Es. +39 333 1234567" required /><small>Numero italiano utilizzabile anche per la ricezione degli OTP contrattuali.</small></label>
						<label>Data di nascita <span>*</span><input type="date" name="trb_artist_birth_date" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'birth_date' ) ); ?>" required /></label>
						<label>Comune di nascita <span>*</span><input type="text" name="trb_artist_birth_place" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'birth_place' ) ); ?>" autocomplete="off" list="trb-birthplace-options" data-trb-birthplace required /><datalist id="trb-birthplace-options"></datalist><small data-trb-birthplace-status>Digita almeno due lettere e seleziona il Comune dall’archivio italiano.</small></label>
						<label>Provincia di nascita <span>*</span><input type="text" name="trb_artist_birth_province" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'birth_province' ) ); ?>" data-trb-birth-province readonly required /></label>
						<label>Codice fiscale <span>*</span><input type="text" name="trb_artist_tax_code" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'tax_code' ) ); ?>" minlength="16" maxlength="16" autocapitalize="characters" spellcheck="false" data-trb-tax-code required /><small>Il sistema controlla struttura e carattere finale prima del salvataggio.</small></label>
						<label>Indirizzo di residenza <span>*</span><input type="text" name="trb_artist_street" autocomplete="street-address" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'street' ) ); ?>" required /></label>
						<label>Numero civico <span>*</span><input type="text" name="trb_artist_street_number" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'street_number' ) ); ?>" required /></label>
						<label>CAP <span>*</span><input type="text" name="trb_artist_postal_code" autocomplete="postal-code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'postal_code' ) ); ?>" data-trb-postcode required /><small data-trb-postcode-status>Inserisci il CAP: Comune e provincia saranno ricavati dall’archivio nazionale.</small></label>
						<label>Città <span>*</span><select name="trb_artist_city" autocomplete="address-level2" data-trb-city required><option value="<?php echo esc_attr( trb_portal_artist_profile_value( 'city' ) ); ?>"><?php echo esc_html( trb_portal_artist_profile_value( 'city' ) ? trb_portal_artist_profile_value( 'city' ) : 'Inserisci prima il CAP' ); ?></option></select></label>
						<label>Provincia <span>*</span><input type="text" name="trb_artist_province" autocomplete="address-level1" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'province' ) ); ?>" data-trb-province readonly required /></label>
						<label>Nazione <span>*</span><input type="text" name="trb_artist_country" autocomplete="country-name" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'country' ) ? trb_portal_artist_profile_value( 'country' ) : 'Italia' ); ?>" data-trb-country readonly required /></label>
					</div><p id="trb-account-data-note" class="trb-portal__field-help">Nome, cognome ed e-mail sono ripresi dall’anagrafica del tuo account. Per modificarli, usa il pulsante “Apri una segnalazione” in alto.</p>
					<?php if ( 'trb' !== $profile ) : ?><details class="trb-portal__company-details" <?php echo $company_requested ? 'open' : ''; ?>><summary>Hai una partita IVA o devi ricevere una fattura intestata a un’azienda?</summary><div><label class="trb-portal__invoice-toggle"><input type="checkbox" name="trb_artist_invoice_requested" value="1" <?php checked( $company_requested ); ?> /> Inserisci dati aziendali per fattura specifica</label><div class="trb-portal__field-grid"><label>Ragione sociale <input type="text" name="trb_artist_company_name" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'company_name' ) ); ?>" /></label><label>Partita IVA <input type="text" name="trb_artist_company_vat" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'company_vat' ) ); ?>" /></label><label>Codice SDI <input type="text" name="trb_artist_company_sdi" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'company_sdi' ) ); ?>" /></label><label>Indirizzo della sede aziendale <input type="text" name="trb_artist_company_address" autocomplete="street-address" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'company_address' ) ); ?>" /></label></div></div></details><?php endif; ?>
					<div class="trb-portal__private-documents"><strong>Documenti riservati</strong><p>Carica i quattro documenti richiesti. Restano esclusivamente nella tua pratica e non vengono pubblicati.</p><div class="trb-portal__field-grid"><label>Carta d’identità — fronte <small>PDF, JPG o PNG</small><input type="file" name="trb_artist_id_front" accept="application/pdf,image/jpeg,image/png" /></label><label>Carta d’identità — retro <small>PDF, JPG o PNG</small><input type="file" name="trb_artist_id_back" accept="application/pdf,image/jpeg,image/png" /></label><label>Codice fiscale o tessera sanitaria — fronte <small>PDF, JPG o PNG</small><input type="file" name="trb_artist_tax_front" accept="application/pdf,image/jpeg,image/png" /></label><label>Codice fiscale o tessera sanitaria — retro <small>PDF, JPG o PNG</small><input type="file" name="trb_artist_tax_back" accept="application/pdf,image/jpeg,image/png" /></label></div><?php trb_portal_render_private_files( 'documents' ); ?></div>
					<button class="trb-button" type="submit">Salva i dati contrattuali</button>
				</form>
			</details>
			<details class="trb-portal__profile-module">
				<summary><span><b>Identità artistica</b><small>Nome d’arte, biografia e immagini ufficiali</small></span><em>Apri il modulo</em></summary>
				<form class="trb-portal__request-form trb-portal__profile-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="trb_portal_save_artist_profile" /><input type="hidden" name="trb_artist_identity_section" value="1" /><?php wp_nonce_field( 'trb_portal_save_artist_profile', 'trb_portal_profile_nonce' ); ?>
					<label>Nome d’arte <span>*</span><input type="text" name="trb_artist_artist_name" value="<?php echo esc_attr( $artist_name ); ?>" <?php echo $artist_name ? 'readonly' : ''; ?> required /><small>Deve corrispondere esattamente al nome indicato nell’accordo contrattuale. Dopo il primo salvataggio potrà essere modificato soltanto previa autorizzazione della Direzione, tramite una segnalazione.</small></label>
					<label>Biografia artistica aggiornata <span>*</span><textarea name="trb_artist_bio" rows="9" required placeholder="Incolla qui la biografia aggiornata: non caricare un file."><?php echo esc_textarea( trb_portal_artist_profile_value( 'bio' ) ); ?></textarea><small>Testo pronto per materiali editoriali, comunicazione e profili ufficiali; descrivi il progetto in modo adatto a solisti, duo, gruppi o formazioni.</small></label>
					<fieldset class="trb-portal__platforms"><legend>Profili musicali ufficiali</legend><div class="trb-portal__field-grid">
						<?php trb_portal_render_platform_field( 'spotify', 'Profilo Spotify', 'Copia il link al profilo artista Spotify, se esistente.', 'spotify_new', 'Richiedo un nuovo profilo artista Spotify' ); ?>
						<?php trb_portal_render_platform_field( 'apple_music', 'Profilo Apple Music', 'Copia il link al profilo artista Apple Music, se esistente.', 'apple_music_new', 'Richiedo un nuovo profilo artista Apple Music' ); ?>
						<?php trb_portal_render_platform_field( 'youtube', 'Canale YouTube', 'Copia il link al canale YouTube ufficiale, se esistente.', 'youtube_none', 'Non ho un canale YouTube' ); ?>
						<?php trb_portal_render_platform_field( 'soundcloud', 'Profilo SoundCloud', 'Copia il link al profilo SoundCloud ufficiale, se esistente.', 'soundcloud_none', 'Non ho un canale SoundCloud' ); ?>
					</div></fieldset>
					<fieldset class="trb-portal__platforms"><legend>Social e contatti pubblici <small>facoltativi</small></legend><div class="trb-portal__social-grid"><?php foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok', 'discord' => 'Discord', 'twitch' => 'Twitch', 'x' => 'X (ex Twitter)', 'snapchat' => 'Snapchat', 'threads' => 'Threads' ) as $social_key => $social_label ) : ?><label><?php echo esc_html( $social_label ); ?><input type="url" name="trb_artist_<?php echo esc_attr( $social_key ); ?>_url" value="<?php echo esc_attr( trb_portal_artist_profile_value( $social_key . '_url' ) ); ?>" placeholder="https://" /></label><?php endforeach; ?></div></fieldset>
					<label>Cachet per esibizioni live o DJ set <span>*</span><input type="text" name="trb_artist_live_fee" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'live_fee' ) ); ?>" required placeholder="Es. € 800 + viaggio e alloggio" /><small>Indica il compenso normalmente richiesto per una singola esibizione dal vivo o DJ set e specifica cosa comprende: durata, organico, esigenze tecniche, viaggio, vitto, alloggio e imposte. Il dato serve per valutare correttamente eventuali proposte di booking e non costituisce un prezzo pubblico o definitivo.</small></label>
					<div class="trb-portal__private-documents"><strong>Foto artista</strong><p>Carica immagini ufficiali ad alta qualità. Puoi conservare fino a 6 fotografie, eliminarne una o più e sostituirle in qualsiasi momento.</p><label>Aggiungi fotografie <small>JPG, PNG o WEBP · massimo 6 immagini complessive</small><input type="file" name="trb_artist_photos[]" accept="image/jpeg,image/png,image/webp" multiple /></label><?php trb_portal_render_private_files( 'photo' ); ?></div>
					<button class="trb-button" type="submit">Salva identità artistica</button>
				</form>
			</details>
		</div>
	</section>
	<?php
}

function trb_portal_render_platform_field( $key, $label, $placeholder, $choice_key, $choice_label ) {
	$value = trb_portal_artist_profile_value( $key . '_url' );
	$choice = '1' === trb_portal_artist_profile_value( $choice_key );
	?><div class="trb-portal__platform-field" data-trb-platform><label><?php echo esc_html( $label ); ?> <span>*</span><input type="url" name="trb_artist_<?php echo esc_attr( $key ); ?>_url" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" <?php echo $choice ? 'disabled' : 'required'; ?> data-trb-platform-url /></label><label class="trb-portal__choice"><input type="checkbox" name="trb_artist_<?php echo esc_attr( $choice_key ); ?>" value="1" <?php checked( $choice ); ?> data-trb-platform-choice /> <?php echo esc_html( $choice_label ); ?></label></div><?php
}

function trb_portal_private_photo_url( $file_id ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=trb_portal_private_photo&file_id=' . rawurlencode( $file_id ) ), 'trb_portal_private_photo_' . $file_id );
}

function trb_portal_serve_private_photo() {
	if ( ! is_user_logged_in() ) auth_redirect();
	$file_id = isset( $_GET['file_id'] ) ? sanitize_text_field( wp_unslash( $_GET['file_id'] ) ) : '';
	check_admin_referer( 'trb_portal_private_photo_' . $file_id );
	foreach ( trb_portal_private_profile_files() as $file ) {
		if ( empty( $file['id'] ) || $file_id !== $file['id'] || 'photo' !== $file['group'] ) continue;
		$uploads = wp_upload_dir();
		$private_dir = realpath( trailingslashit( $uploads['basedir'] ) . 'trb-artist-private' );
		$target = realpath( trailingslashit( $uploads['basedir'] ) . ltrim( $file['path'], '/' ) );
		if ( ! $private_dir || ! $target || 0 !== strpos( $target, $private_dir . DIRECTORY_SEPARATOR ) || ! is_file( $target ) ) break;
		nocache_headers();
		header( 'Content-Type: ' . sanitize_mime_type( $file['type'] ) );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $file['name'] ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
	wp_die( 'Immagine non disponibile.', 'File non disponibile', array( 'response' => 404 ) );
}
add_action( 'admin_post_trb_portal_private_photo', 'trb_portal_serve_private_photo' );

function trb_portal_render_private_files( $group = '' ) {
	$files = trb_portal_private_profile_files();
	if ( 'documents' === $group ) {
		$files = array_filter( $files, function( $file ) { return isset( $file['group'] ) && in_array( $file['group'], array( 'identity', 'tax_card' ), true ); } );
	} elseif ( $group ) {
		$files = array_filter( $files, function( $file ) use ( $group ) { return isset( $file['group'] ) && $group === $file['group']; } );
	}
	if ( empty( $files ) ) return;
	?><fieldset class="trb-portal__uploaded-files <?php echo 'photo' === $group ? 'trb-portal__uploaded-photos' : ''; ?>"><legend><?php echo 'photo' === $group ? 'Fotografie attualmente salvate' : 'File già ricevuti'; ?></legend><p>Seleziona “Elimina” solo per i file che vuoi rimuovere al prossimo salvataggio.</p><div class="<?php echo 'photo' === $group ? 'trb-portal__photo-grid' : 'trb-portal__file-list'; ?>"><?php foreach ( $files as $file ) : ?><?php if ( 'photo' === $group ) : ?><article class="trb-portal__photo-card"><img src="<?php echo esc_url( trb_portal_private_photo_url( $file['id'] ) ); ?>" alt="Anteprima foto artista" loading="lazy" /><label><input type="checkbox" name="trb_artist_remove_files[]" value="<?php echo esc_attr( $file['id'] ); ?>" /> Elimina</label></article><?php else : ?><label><input type="checkbox" name="trb_artist_remove_files[]" value="<?php echo esc_attr( $file['id'] ); ?>" /> <?php echo esc_html( ! empty( $file['label'] ) ? $file['label'] . ': ' : '' ); ?><?php echo esc_html( $file['name'] ); ?></label><?php endif; ?><?php endforeach; ?></div></fieldset><?php
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
			<div class="trb-portal__request-history"><h3>Le tue pratiche</h3><ul><?php foreach ( $releases as $release ) : $release_type = get_post_meta( $release->ID, '_trb_release_type', true ); ?><li><strong><?php echo esc_html( $release->post_title ); ?></strong><span><?php echo esc_html( isset( $types[ $release_type ] ) ? $types[ $release_type ]['label'] : 'Release' ); ?> &middot; Dati contrattuali da completare</span></li><?php endforeach; ?></ul></div>
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

/**
 * Safety net for legacy role home pages.
 *
 * Some existing accounts authenticate through Profile Builder/LoginWP rules
 * that issue their redirect before WordPress' normal login_redirect filter can
 * take precedence. Do not leave those artists in the old FAQ homes: whenever
 * one of the retired contract-specific home pages is reached, send the signed
 * in contractual artist to the single new dashboard instead. Documentation,
 * demo forms and the rest of the site are deliberately not affected.
 */
function trb_portal_redirect_legacy_artist_home() {
	if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) || ! trb_portal_user_profile() ) {
		return;
	}

	$legacy_homes = array(
		'homepage-dds',
		'homepage-ddb',
		'homepage-ddb-trb',
		'homepage-trb',
		'homepage-trb-basic',
		'home-page-dds',
		'home-page-ddb',
		'home-page-ddb-trb',
		'home-page-trb',
		'home-page-trb-basic',
	);

	// A few older login flows fall back to the site's front page rather than a
	// named role page. For contractual artists the front page is legacy too.
	if ( is_front_page() || is_page( $legacy_homes ) ) {
		wp_safe_redirect( home_url( '/area-artisti/' ) );
		exit;
	}
}
add_action( 'template_redirect', 'trb_portal_redirect_legacy_artist_home', 0 );

function trb_portal_get_resources( $profile ) {
	$resources = array();
	$search    = trb_portal_current_search();
	foreach ( trb_portal_supported_resource_types() as $post_type ) {
		$args = array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => ( $search && 'trb_guide' === $post_type ) ? 100 : ( 'trb_guide' === $post_type ? 20 : 7 ),
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
		// Search belongs to the Knowledge Hub answers. It must never make the
		// artist's Library, videos or downloads appear to have disappeared.
		if ( $search && 'trb_guide' === $post_type ) {
			$ranked = array();
			foreach ( $posts as $post ) {
				$score = trb_portal_search_score( $post, $search );
				if ( $score ) $ranked[] = array( 'post' => $post, 'score' => $score );
			}
			usort( $ranked, function( $left, $right ) { return $right['score'] <=> $left['score']; } );
			$posts = wp_list_pluck( $ranked, 'post' );
		}
		$resources[ $post_type ] = $posts;
	}

	return $resources;
}

/** Search the entire authorised Knowledge Hub, not only the short FAQ cards. */
function trb_portal_get_search_results( $profile, $search ) {
	$results = array();
	foreach ( array( 'trb_guide', 'wpdmpro' ) as $post_type ) {
		$query = new WP_Query( array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'meta_query'     => array( array( 'key' => '_trb_portal_profiles', 'value' => '"' . $profile . '"', 'compare' => 'LIKE' ) ),
		) );
		foreach ( $query->posts as $post ) {
			$score = trb_portal_search_score( $post, $search );
			if ( $score ) {
				$results[] = array( 'post' => $post, 'score' => $score );
			}
		}
	}
	usort( $results, function( $left, $right ) { return $right['score'] <=> $left['score']; } );
	return array_slice( wp_list_pluck( $results, 'post' ), 0, 12 );
}

function trb_portal_search_score( $post, $search ) {
	$search = strtolower( remove_accents( trim( $search ) ) );
	$tokens = array_filter( preg_split( '/[^[:alnum:]]+/u', $search ) );
	if ( empty( $tokens ) ) return 0;
	$title = strtolower( remove_accents( $post->post_title ) );
	$excerpt = strtolower( remove_accents( $post->post_excerpt ) );
	$body = strtolower( remove_accents( wp_strip_all_tags( $post->post_content ) ) );
	$terms = strtolower( remove_accents( (string) get_post_meta( $post->ID, '_trb_portal_search_terms', true ) ) );
	$score = false !== strpos( $title . ' ' . $excerpt . ' ' . $body . ' ' . $terms, $search ) ? 12 : 0;
	foreach ( $tokens as $token ) {
		if ( false !== strpos( $title, $token ) ) $score += 8;
		if ( false !== strpos( $terms, $token ) ) $score += 6;
		if ( false !== strpos( $excerpt, $token ) ) $score += 3;
		if ( false !== strpos( $body, $token ) ) $score += 1;
	}
	return $score;
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
						<article class="trb-portal__card"><p class="trb-portal__type"><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></p><h3><a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 22 ) ); ?></p><a class="trb-portal__link" href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener">Apri contenuto <span aria-hidden="true">↗</span></a></article>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

/** Results belong beside the search field, not at the end of the dashboard. */
function trb_portal_render_search_results( $posts, $query ) {
	?>
	<div id="risposte" class="trb-portal__search-results" aria-live="polite">
		<p class="trb-portal__search-result-label">Risultati per “<?php echo esc_html( $query ); ?>”</p>
		<?php if ( empty( $posts ) ) : ?>
			<p class="trb-portal__search-empty">Non ho ancora una guida abbastanza precisa per questa ricerca. Prova una parola chiave più diretta oppure consulta le procedure qui sotto.</p>
		<?php else : ?>
			<div class="trb-portal__search-result-list">
			<?php foreach ( $posts as $post ) : ?>
				<?php if ( 'trb_guide' === $post->post_type ) : ?>
					<details><summary><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong><span><?php echo esc_html( $post->post_excerpt ); ?></span></summary><div><?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></details>
				<?php else : ?>
					<a class="trb-portal__search-resource" href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong><span><?php echo esc_html( $post->post_excerpt ); ?></span><em>Apri contenuto ↗</em></a>
				<?php endif; ?>
			<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

function trb_portal_current_search() {
	return isset( $_GET['trb_search'] ) ? sanitize_text_field( wp_unslash( $_GET['trb_search'] ) ) : '';
}

function trb_portal_enqueue_assets() {
	$post = get_post();
	wp_enqueue_style( 'trb-inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap', array(), null );
	wp_add_inline_style( 'trb-inter-font', 'body,button,input,select,textarea{font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif!important}' );
	if ( is_front_page() || is_page( array( 'registrati', 'accedi', 'recupera-password', 'segnalazione' ) ) || is_singular( 'wpdmpro' ) || ( is_page() && $post && has_shortcode( $post->post_content, 'trb_artist_portal' ) ) ) {
		$style_path    = get_template_directory() . '/assets/css/trb-artist-portal.css';
		$style_version = file_exists( $style_path ) ? (string) filemtime( $style_path ) : DOCY_VERSION;
		wp_enqueue_style( 'trb-artist-portal', get_template_directory_uri() . '/assets/css/trb-artist-portal.css', array(), $style_version );
		$script_path = get_template_directory() . '/assets/js/trb-artist-profile.js';
		wp_enqueue_script( 'trb-artist-profile', get_template_directory_uri() . '/assets/js/trb-artist-profile.js', array(), file_exists( $script_path ) ? (string) filemtime( $script_path ) : DOCY_VERSION, true );
		wp_localize_script( 'trb-artist-profile', 'trbArtistProfile', array( 'postcodeEndpoint' => esc_url_raw( rest_url( 'trb/v1/postcode/' ) ), 'municipalityEndpoint' => esc_url_raw( rest_url( 'trb/v1/municipalities' ) ), 'restNonce' => wp_create_nonce( 'wp_rest' ) ) );
		$academy_path = get_template_directory() . '/assets/js/trb-video-academy.js';
		wp_enqueue_script( 'trb-video-academy', get_template_directory_uri() . '/assets/js/trb-video-academy.js', array(), file_exists( $academy_path ) ? (string) filemtime( $academy_path ) : DOCY_VERSION, true );
		wp_localize_script( 'trb-video-academy', 'trbVideoAcademy', array( 'restRoot' => esc_url_raw( rest_url( 'trb/v1/' ) ), 'restNonce' => wp_create_nonce( 'wp_rest' ) ) );

		// The retired forum is not part of the Artist Portal. Some legacy plugins
		// enqueue their assets globally, so prevent them from affecting or slowing
		// down the custom public and authenticated screens.
		wp_dequeue_style( 'bbp-default' );
		wp_dequeue_style( 'sbv-render-css' );
		wp_dequeue_script( 'sbv-mask' );
		wp_dequeue_script( 'sbv-render' );
	}
}
add_action( 'wp_enqueue_scripts', 'trb_portal_enqueue_assets', 30 );

/** Remove theme and plugin bundles that the bespoke portal screens do not use. */
function trb_portal_dequeue_unused_custom_screen_assets() {
	$post = get_post();
	$is_portal_screen = is_front_page() || is_page( array( 'registrati', 'accedi', 'recupera-password', 'segnalazione' ) ) || is_singular( 'wpdmpro' ) || ( is_page() && $post && has_shortcode( $post->post_content, 'trb_artist_portal' ) );
	if ( ! $is_portal_screen ) {
		return;
	}

	$styles = array( 'wpdm-gutenberg-blocks-frontend', 'wpdm-fonticon', 'wpdm-front', 'wpdm-front-dark', 'wpdm-modal', 'eazydocs-subscription', 'bootstrap', 'elegant-icon', 'font-awesome-6', 'animate', 'docy-essential', 'docy-main', 'docy-root', 'docy-responsive', 'eazydocs-assistant' );
	$scripts = array( 'wpdm-modal', 'wpdm-frontend-js', 'wpdm-frontjs', 'eazydocs-assistant', 'eazydocs-subscription', 'bootstrap', 'wow', 'imagesloaded', 'masonry', 'docy-main', 'docy-ajax-search-form' );

	foreach ( $styles as $handle ) {
		wp_dequeue_style( $handle );
	}
	foreach ( $scripts as $handle ) {
		wp_dequeue_script( $handle );
	}
	if ( ! is_page( 'registrati' ) ) {
		wp_dequeue_style( 'wppb_stylesheet' );
		wp_dequeue_script( 'wppb_front_end_script' );
		wp_dequeue_script( 'jquery-form' );
		wp_dequeue_script( 'wp-hooks' );
		wp_dequeue_script( 'wp-i18n' );
	}
}
add_action( 'wp_enqueue_scripts', 'trb_portal_dequeue_unused_custom_screen_assets', PHP_INT_MAX );

/**
 * Retire the legacy plugins that have been replaced by the Artist Portal.
 *
 * The cleanup runs once, only during an authenticated administrator request,
 * and resolves installed plugins by directory so minor main-file name changes
 * cannot cause the wrong package to be removed.
 */
function trb_portal_retire_legacy_plugins() {
	if ( get_option( 'trb_portal_legacy_plugins_retired_v1' ) || ! current_user_can( 'delete_plugins' ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$target_directories = array(
		'bbp-core',
		'smart-bbpress-nverify',
		'duplicate-page',
		'string-locator',
		'classic-widgets',
	);
	$plugin_files = array();

	foreach ( array_keys( get_plugins() ) as $plugin_file ) {
		if ( in_array( dirname( $plugin_file ), $target_directories, true ) ) {
			$plugin_files[] = $plugin_file;
		}
	}

	if ( $plugin_files ) {
		deactivate_plugins( $plugin_files, true );
		$errors = delete_plugins( $plugin_files );
		if ( is_wp_error( $errors ) ) {
			update_option( 'trb_portal_legacy_plugins_cleanup_error', $errors->get_error_message(), false );
			return;
		}
	}

	update_option( 'trb_portal_legacy_plugins_retired_v1', wp_date( 'c' ), false );
}
add_action( 'admin_init', 'trb_portal_retire_legacy_plugins', 40 );

/**
 * EazyDocs Pro remains the document engine, but its global assistant does not
 * understand the portal's contractual audiences and can expose private titles
 * inside otherwise public HTML. The portal already provides an audience-aware
 * in-page search, so remove that one global widget from every custom shell.
 */
function trb_portal_filter_eazydocs_assistant( $html ) {
	$start = strpos( $html, '<div class="eazydocs-assistant-wrapper' );
	if ( false !== $start ) {
		$end = strpos( $html, '<style type="text/css">', $start );
		if ( false !== $end ) {
			$style_end = strpos( $html, '</style>', $end );
			$resume    = false !== $style_end ? $style_end + strlen( '</style>' ) : $end;
			$html      = substr( $html, 0, $start ) . substr( $html, $resume );
		}
	}

	// Profile Builder injects this stylesheet globally even though only the
	// dedicated registration form needs it.
	if ( ! is_page( 'registrati' ) ) {
		$html = preg_replace( "#<link[^>]+id=['\"]wppb_stylesheet-css['\"][^>]*>\s*#i", '', $html );
	}
	$html = preg_replace( "#<style[^>]+id=['\"]global-styles-inline-css['\"][^>]*>.*?</style>\s*#is", '', $html );
	$html = preg_replace( '#<script>\s*const abmsg = .*?</script>\s*<div id="fb-root"></div>\s*#is', '', $html );
	return $html;
}

function trb_portal_start_private_output_filter() {
	if ( is_front_page() || trb_portal_is_private_screen() || is_page( array( 'registrati', 'accedi', 'recupera-password', 'segnalazione' ) ) ) {
		ob_start( 'trb_portal_filter_eazydocs_assistant' );
	}
}
add_action( 'template_redirect', 'trb_portal_start_private_output_filter', 999 );

/** Baseline browser protections for every custom portal screen. */
function trb_portal_send_security_headers() {
	if ( ! is_front_page() && ! trb_portal_is_private_screen() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), geolocation=(), microphone=()' );
	header( 'X-Permitted-Cross-Domain-Policies: none' );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
	}
}
add_action( 'template_redirect', 'trb_portal_send_security_headers', 998 );

/** Remove the emoji runtime from the bespoke screens; native emoji remain usable. */
function trb_portal_remove_emoji_assets() {
	if ( ! is_front_page() && ! trb_portal_is_private_screen() ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'template_redirect', 'trb_portal_remove_emoji_assets', -1000 );

// This private portal does not expose a remote publishing API. Keeping XML-RPC
// disabled removes an unnecessary authentication attack surface.
add_filter( 'xmlrpc_enabled', '__return_false' );

/** Keep the public artist entry pages independent from the legacy Docy shell. */
function trb_portal_public_body_class( $classes ) {
	if ( is_front_page() || is_page( array( 'registrati', 'accedi', 'recupera-password', 'segnalazione' ) ) ) {
		$classes[] = 'trb-artist-public-shell';
	}

	return $classes;
}
add_filter( 'body_class', 'trb_portal_public_body_class' );

/** The historical FAQ front page is now the public, approval-only portal landing. */
function trb_portal_force_public_landing_template( $template ) {
	if ( is_front_page() && ! is_admin() ) {
		$landing_template = locate_template( 'template-artist-landing.php' );
		return $landing_template ? $landing_template : $template;
	}

	return $template;
}
add_filter( 'template_include', 'trb_portal_force_public_landing_template', 90 );

function trb_portal_force_registration_template( $template ) {
	if ( is_page( 'registrati' ) ) {
		$registration_template = locate_template( 'template-artist-registration.php' );
		return $registration_template ? $registration_template : $template;
	}
	return $template;
}
add_filter( 'template_include', 'trb_portal_force_registration_template', 91 );

/** Use a dedicated, branded authentication page instead of the WordPress screen. */
function trb_portal_force_login_template( $template ) {
	if ( is_page( 'accedi' ) ) {
		$login_template = locate_template( 'template-artist-login.php' );
		return $login_template ? $login_template : $template;
	}
	return $template;
}
add_filter( 'template_include', 'trb_portal_force_login_template', 92 );

function trb_portal_force_password_template( $template ) {
	if ( is_page( 'recupera-password' ) ) {
		$password_template = locate_template( 'template-artist-password.php' );
		return $password_template ? $password_template : $template;
	}
	return $template;
}
add_filter( 'template_include', 'trb_portal_force_password_template', 93 );

/** Render a dedicated artist-only support page without relying on a legacy page builder route. */
function trb_portal_force_support_template( $template ) {
	if ( is_page( 'segnalazione' ) ) {
		$support_template = locate_template( 'template-artist-support.php' );
		return $support_template ? $support_template : $template;
	}
	return $template;
}
add_filter( 'template_include', 'trb_portal_force_support_template', 94 );

/**
 * Render the support screen before Profile Builder applies its legacy
 * authenticated-page redirect. Only this endpoint is made public.
 */
function trb_portal_render_public_support_early() {
	if ( is_admin() || ! is_page( 'segnalazione' ) ) {
		return;
	}

	$support_template = locate_template( 'template-artist-support.php' );
	if ( ! $support_template ) {
		return;
	}

	status_header( 200 );
	nocache_headers();
	trb_portal_send_security_headers();
	ob_start();
	include $support_template;
	$html = ob_get_clean();
	echo trb_portal_filter_eazydocs_assistant( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'template_redirect', 'trb_portal_render_public_support_early', -999 );

/** Create the support endpoint once, so it remains available independently from Elementor. */
function trb_portal_maybe_create_support_page() {
	if ( get_option( 'trb_portal_support_page_created' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$page = get_page_by_path( 'segnalazione' );
	if ( ! $page ) {
		$page_id = wp_insert_post( array( 'post_title' => 'Apri una segnalazione', 'post_name' => 'segnalazione', 'post_status' => 'publish', 'post_type' => 'page' ) );
		if ( is_wp_error( $page_id ) ) return;
	}
	update_option( 'trb_portal_support_page_created', 1, false );
}
add_action( 'admin_init', 'trb_portal_maybe_create_support_page' );

/** Store every support request in WordPress and notify the TRB mailbox. */
function trb_portal_submit_support_request() {
	check_admin_referer( 'trb_portal_submit_support', 'trb_support_nonce' );
	$user    = wp_get_current_user();
	$logged_in = is_user_logged_in();
	$name    = $logged_in ? trim( $user->first_name . ' ' . $user->last_name ) : ( isset( $_POST['trb_support_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_support_name'] ) ) : '' );
	$email   = $logged_in ? $user->user_email : ( isset( $_POST['trb_support_email'] ) ? sanitize_email( wp_unslash( $_POST['trb_support_email'] ) ) : '' );
	$artist_name = isset( $_POST['trb_support_artist_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_support_artist_name'] ) ) : '';
	$type    = isset( $_POST['trb_support_type'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_support_type'] ) ) : 'supporto';
	$subject = isset( $_POST['trb_support_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_support_subject'] ) ) : '';
	$message = isset( $_POST['trb_support_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['trb_support_message'] ) ) : '';
	$website = isset( $_POST['trb_support_website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['trb_support_website'] ) ) ) : '';
	$started = isset( $_POST['trb_support_started'] ) ? absint( $_POST['trb_support_started'] ) : 0;
	$labels  = array( 'supporto' => 'Supporto via e-mail', 'dati' => 'Modifica dati anagrafici o contatti', 'problema' => 'Problema tecnico del portale', 'altro' => 'Altra richiesta' );
	$type    = isset( $labels[ $type ] ) ? $type : 'supporto';
	if ( '' !== $website || ! $started || time() - $started < 3 || '' === $name || '' === $artist_name || ! is_email( $email ) || '' === $subject || '' === $message ) {
		wp_safe_redirect( add_query_arg( 'trb_support', 'invalid', home_url( '/segnalazione/' ) ) );
		exit;
	}
	$profile = $logged_in ? trb_portal_user_profile( $user ) : 'Utente non autenticato';
	$body = "Tipo: {$labels[ $type ]}\nNome e cognome: {$name}\nNome d’arte: {$artist_name}\nE-mail: {$email}\nProfilo: {$profile}\n\n{$message}";
	wp_insert_post( array( 'post_type' => 'trb_request', 'post_status' => 'private', 'post_title' => '[Supporto] ' . $subject, 'post_content' => $body, 'post_author' => $logged_in ? $user->ID : 0 ) );
	wp_mail( 'info@trbrec.com', '[Portale Artisti] ' . $labels[ $type ] . ' — ' . $subject, $body, array( 'Reply-To: ' . $email ) );
	wp_safe_redirect( add_query_arg( 'trb_support', 'sent', home_url( '/segnalazione/' ) ) );
	exit;
}
add_action( 'admin_post_trb_portal_submit_support', 'trb_portal_submit_support_request' );
add_action( 'admin_post_nopriv_trb_portal_submit_support', 'trb_portal_submit_support_request' );

/**
 * Retire the former Profile Builder entry page. It exposes a public
 * registration form that conflicts with the approval-only Artist Portal.
 */
function trb_portal_redirect_legacy_account_page() {
	if ( 'GET' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) && is_page( 'my-account' ) ) {
		$destination = is_user_logged_in() && ( trb_portal_user_profile() || current_user_can( 'manage_options' ) ) ? '/area-artisti/#profilo' : '/accedi/';
		wp_safe_redirect( home_url( $destination ), 302 );
		exit;
	}
}
add_action( 'template_redirect', 'trb_portal_redirect_legacy_account_page', 2 );

/**
 * Keep every retired Elementor route useful after its page has been removed.
 * The new dashboard is the single destination for contractual material, while
 * the public entry page explains the approval-only access model.
 */
function trb_portal_redirect_retired_elementor_routes() {
	if ( 'GET' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) || is_admin() ) {
		return;
	}

	$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', PHP_URL_PATH );
	$path = untrailingslashit( (string) $path );
	$routes = array(
		'/homepage-ddb'                 => '/area-artisti/',
		'/home-page-ddb'                => '/area-artisti/',
		'/homepage-ddb-trb'             => '/area-artisti/',
		'/home-page-ddb-trb'            => '/area-artisti/',
		'/home-page-dds'                => '/area-artisti/',
		'/homepage-dds'                 => '/area-artisti/',
		'/homepage-trb'                 => '/area-artisti/',
		'/home-page-trb'                => '/area-artisti/',
		'/home-page-trb-basic'          => '/area-artisti/',
		'/video-corsi-ddb'              => '/area-artisti/#video',
		'/video-corsi-ddb-trb'          => '/area-artisti/#video',
		'/video-corsi-dds'              => '/area-artisti/#video',
		'/video-corsi-trb'              => '/area-artisti/#video',
		'/video-corsi-trb-basic'        => '/area-artisti/#video',
		'/strumenti-e-utilita-ddb'      => '/area-artisti/#download',
		'/strumenti-e-utilita-ddb-trb'  => '/area-artisti/#download',
		'/strumenti-e-utilita-dds'      => '/area-artisti/#download',
		'/strumenti-e-utilita-trb'      => '/area-artisti/#download',
		'/strumenti-e-utilita-trb-basic' => '/area-artisti/#download',
		'/multi-documentations'         => '/area-artisti/#documenti',
		'/domande-e-risposte'           => '/area-artisti/#risposte-rapide',
		'/video-corsi'                  => '/area-artisti/#video',
		'/strumenti-e-utilita'          => '/area-artisti/#download',
		'/forums'                       => '/area-artisti/',
		'/my-account'                   => '/accedi/',
		'/mio-account'                  => '/accedi/',
		'/password-dimenticata'         => '/recupera-password/',
		'/area-artisti-2'               => '/area-artisti/',
		'/contact'                      => '/segnalazione/',
		'/login'                        => '/accedi/',
	);

	if ( isset( $routes[ $path ] ) ) {
		wp_safe_redirect( home_url( $routes[ $path ] ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'trb_portal_redirect_retired_elementor_routes', 1 );

/**
 * Unpublish the surviving page-builder shells after the new routes have been
 * verified and backed up. Their URLs remain covered by the redirects above.
 */
function trb_portal_retire_legacy_pages() {
	if ( get_option( 'trb_portal_legacy_pages_retired_v1' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$slugs = array(
		'mio-account',
		'password-dimenticata',
		'video-corsi-dds',
		'video-corsi-ddb',
		'video-corsi-ddb-trb',
		'video-corsi-trb',
		'video-corsi-trb-basic',
		'strumenti-e-utilita-dds',
		'strumenti-e-utilita-ddb',
		'area-artisti-2',
	);
	foreach ( $slugs as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
			wp_trash_post( $page->ID );
		}
	}

	update_option( 'trb_portal_legacy_pages_retired_v1', time(), false );
}
add_action( 'admin_init', 'trb_portal_retire_legacy_pages' );

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

/** Replace the legacy Download Manager banner with a focused resource page. */
function trb_portal_force_download_template( $template ) {
	if ( is_singular( 'wpdmpro' ) ) {
		$resource_template = locate_template( 'template-artist-download.php' );
		return $resource_template ? $resource_template : $template;
	}
	return $template;
}
add_filter( 'template_include', 'trb_portal_force_download_template', 100 );

function trb_portal_body_class( $classes ) {
	if ( is_page() ) {
		$page = get_queried_object();
		if ( $page instanceof WP_Post && has_shortcode( $page->post_content, 'trb_artist_portal' ) ) {
			$classes[] = 'trb-artist-portal-shell';
		}
	}
	if ( is_singular( 'wpdmpro' ) ) $classes[] = 'trb-artist-download-shell';

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

/** Public request form. New User Approve keeps all new accounts pending. */
function trb_portal_maybe_create_registration_page() {
	if ( get_page_by_path( 'registrati' ) ) {
		return;
	}

	wp_insert_post(
		array(
			'post_title'   => 'Registrati al Portale Artisti',
			'post_name'    => 'registrati',
			'post_content' => '[wppb-register]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
}
add_action( 'init', 'trb_portal_maybe_create_registration_page', 31 );

/** Server-verified anti-bot check for the public registration form. */
function trb_portal_registration_captcha_markup() {
	$left   = wp_rand( 2, 8 );
	$right  = wp_rand( 3, 9 );
	$token  = wp_generate_uuid4();
	$key    = 'trb_registration_captcha_' . md5( $token );
	set_transient( $key, (string) ( $left + $right ), 15 * MINUTE_IN_SECONDS );

	return '<fieldset class="trb-registration__captcha"><legend>Verifica di sicurezza</legend><p>Per confermare che non sei un sistema automatico, risolvi questa semplice operazione.</p><label for="trb_registration_answer">Quanto fa ' . esc_html( $left ) . ' + ' . esc_html( $right ) . '? <span>*</span></label><input id="trb_registration_answer" name="trb_registration_answer" type="number" inputmode="numeric" required /><input name="trb_registration_token" type="hidden" value="' . esc_attr( $token ) . '" /><label class="trb-registration__honeypot" aria-hidden="true">Sito web<input name="trb_registration_website" type="text" tabindex="-1" autocomplete="off" /></label></fieldset>';
}

function trb_portal_validate_registration_captcha() {
	if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) || 'register' !== ( isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '' ) ) {
		return;
	}

	$token    = isset( $_POST['trb_registration_token'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_registration_token'] ) ) : '';
	$answer   = isset( $_POST['trb_registration_answer'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_registration_answer'] ) ) : '';
	$honeypot = isset( $_POST['trb_registration_website'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_registration_website'] ) ) : '';
	$expected = $token ? get_transient( 'trb_registration_captcha_' . md5( $token ) ) : false;
	delete_transient( 'trb_registration_captcha_' . md5( $token ) );

	if ( ! $expected || ! hash_equals( (string) $expected, (string) $answer ) || '' !== $honeypot ) {
		wp_safe_redirect( add_query_arg( 'trb_registration_error', 'security', home_url( '/registrati/' ) ) );
		exit;
	}
}
add_action( 'init', 'trb_portal_validate_registration_captcha', 1 );

/** Mark only accounts created by the new portal, leaving legacy artists untouched. */
function trb_portal_mark_new_registration( $user_id ) {
	$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
	$request_path = untrailingslashit( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ) );
	if ( 'register' !== $action || '/registrati' !== $request_path ) {
		return;
	}
	update_user_meta( $user_id, '_trb_portal_registration_source', time() );
}
add_action( 'user_register', 'trb_portal_mark_new_registration', 999 );

/** Remove portal registrations that the Direction has left pending for 30 days. */
function trb_portal_schedule_pending_account_cleanup() {
	if ( ! wp_next_scheduled( 'trb_portal_cleanup_pending_accounts' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'trb_portal_cleanup_pending_accounts' );
	}
}
add_action( 'init', 'trb_portal_schedule_pending_account_cleanup', 40 );

function trb_portal_cleanup_pending_accounts() {
	if ( ! function_exists( 'pw_new_user_approve' ) ) {
		return;
	}

	$cutoff = time() - ( 30 * DAY_IN_SECONDS );
	$users  = get_users(
		array(
			'fields'     => array( 'ID', 'user_registered' ),
			'meta_key'   => '_trb_portal_registration_source',
			'meta_value' => $cutoff,
			'meta_compare' => '<=',
			'meta_type'  => 'NUMERIC',
			'number'     => 100,
		)
	);
	foreach ( $users as $user ) {
		if ( user_can( $user->ID, 'manage_options' ) || 'pending' !== pw_new_user_approve()->get_user_status( $user->ID ) ) {
			continue;
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user->ID );
	}
}
add_action( 'trb_portal_cleanup_pending_accounts', 'trb_portal_cleanup_pending_accounts' );

/** Each registration challenge is single-use, so the page must never be cached. */
function trb_portal_no_cache_registration() {
	if ( is_page( 'registrati' ) ) {
		nocache_headers();
	}
}
add_action( 'template_redirect', 'trb_portal_no_cache_registration', 0 );

/** Create the public entry page for artists that already have credentials. */
function trb_portal_maybe_create_login_page() {
	if ( get_page_by_path( 'accedi' ) ) {
		return;
	}

	wp_insert_post(
		array(
			'post_title'   => 'Accedi al Portale Artisti',
			'post_name'    => 'accedi',
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
}
add_action( 'init', 'trb_portal_maybe_create_login_page', 32 );

function trb_portal_maybe_create_password_page() {
	if ( get_page_by_path( 'recupera-password' ) ) {
		return;
	}

	wp_insert_post(
		array(
			'post_title'   => 'Recupera la password',
			'post_name'    => 'recupera-password',
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
}
add_action( 'init', 'trb_portal_maybe_create_password_page', 33 );

/** Preserve the branded recovery journey when someone opens the legacy URL. */
function trb_portal_redirect_default_password_request() {
	if ( 'GET' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) {
		return;
	}

	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( 'lostpassword' === $action ) {
		wp_safe_redirect( home_url( '/recupera-password/' ), 302 );
		exit;
	}
}
add_action( 'login_init', 'trb_portal_redirect_default_password_request' );

function trb_portal_is_private_screen() {
	$post = get_post();

	return is_page( array( 'registrati', 'accedi', 'recupera-password', 'segnalazione' ) )
		|| is_singular( trb_portal_supported_resource_types() )
		|| ( is_page() && $post && has_shortcode( $post->post_content, 'trb_artist_portal' ) );
}

/** Keep authentication, support and contractual resources out of search engines. */
function trb_portal_noindex_private_area( $robots ) {
	if ( trb_portal_is_private_screen() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
	}

	return $robots;
}
add_filter( 'wp_robots', 'trb_portal_noindex_private_area', 99 );

/** Concise browser titles for the custom entry and account screens. */
function trb_portal_document_title( $title ) {
	if ( is_front_page() ) {
		return 'Portale Artisti | TRB rec';
	}
	$titles = array(
		'registrati'        => 'Registrati | Portale Artisti TRB rec',
		'accedi'            => 'Accedi | Portale Artisti TRB rec',
		'recupera-password' => 'Recupera password | Portale Artisti TRB rec',
		'segnalazione'      => 'Apri una segnalazione | Portale Artisti TRB rec',
		'area-artisti'      => 'Area Artisti | TRB rec',
	);
	foreach ( $titles as $slug => $screen_title ) {
		if ( is_page( $slug ) ) {
			return $screen_title;
		}
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'trb_portal_document_title', 99 );
