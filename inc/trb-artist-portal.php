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
	);
}

function trb_portal_artist_profile_value( $key, $user_id = 0 ) {
	return (string) get_user_meta( $user_id ? $user_id : get_current_user_id(), '_trb_artist_' . $key, true );
}

function trb_portal_artist_profile_is_complete( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$user = get_userdata( $user_id );
	if ( ! $user || '' === trim( (string) $user->first_name ) || '' === trim( (string) $user->last_name ) || '' === trim( (string) $user->user_email ) ) {
		return false;
	}
	$required = array( 'artist_name', 'phone', 'birth_date', 'tax_code', 'street', 'street_number', 'city', 'postal_code', 'province', 'country' );
	foreach ( $required as $field ) {
		if ( '' === trb_portal_artist_profile_value( $field, $user_id ) ) {
			return false;
		}
	}
	if ( '' === trim( (string) get_user_meta( $user_id, '_trb_artist_bio', true ) ) ) {
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
	foreach ( trb_portal_artist_profile_fields() as $key => $label ) {
		if ( ! isset( $_POST[ 'trb_artist_' . $key ] ) ) {
			continue;
		}
		$value = sanitize_text_field( wp_unslash( $_POST[ 'trb_artist_' . $key ] ) );
		if ( 'email' === $key ) {
			$value = sanitize_email( $value );
		}
		update_user_meta( $user_id, '_trb_artist_' . $key, $value );
	}
	if ( isset( $_POST['trb_artist_invoice_requested'] ) || isset( $_POST['trb_artist_company_section'] ) ) {
		update_user_meta( $user_id, '_trb_artist_invoice_requested', isset( $_POST['trb_artist_invoice_requested'] ) ? '1' : '' );
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
	if ( get_option( 'trb_portal_guides_synced_v4' ) ) {
		return;
	}

	foreach ( trb_portal_seed_guides() as $key => $guide ) {
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
		}
	}

	update_option( 'trb_portal_guides_synced_v4', time(), false );
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
 * Bring the six surviving legacy download packages into the new library once.
 * Their old category audiences are deliberately consolidated: TRB Basic is
 * included in TRB and the two e-books remain premium material.
 */
function trb_portal_migrate_known_download_audiences() {
	if ( get_option( 'trb_portal_download_audience_migrated_v2' ) ) {
		return;
	}

	$packages = array(
		11829 => array( 'dds', 'ddb', 'ddb_trb', 'trb' ), // Guida: biografia artistica.
		11201 => array( 'ddb', 'ddb_trb', 'trb' ), // E-book: missaggio.
		11119 => array( 'ddb', 'ddb_trb', 'trb' ), // E-book: brano contemporaneo.
		11118 => array( 'dds', 'ddb', 'ddb_trb', 'trb' ), // Guida: Spotify e streaming.
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
			'post_excerpt' => 'Approfondimento per il progetto TRB: identità, pianificazione editoriale e presenza social.',
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

/**
 * The legacy Video CPT is empty: the previous DDB video page stored embeds in
 * the page builder. Preserve the confirmed YouTube lessons here so they are
 * shown in the new dashboard and can later be replaced or expanded centrally.
 */
function trb_portal_legacy_video_catalogue() {
	return array(
		array(
			'title'    => 'Come Musixmatch ha costruito un prodotto scalabile',
			'youtube'  => '2Ppuzp_8CyQ',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
		),
		array(
			'title'    => 'Come scrivere il testo di una canzone',
			'youtube'  => 'lpj4wDenbvo',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
		),
		array(
			'title'    => 'Scegliere il microfono: dinamico, condensatore e pattern',
			'youtube'  => 'hXuSELCrHKQ',
			'profiles' => array( 'dds', 'ddb', 'ddb_trb', 'trb' ),
		),
	);
}

function trb_portal_render_video_library( $profile ) {
	$videos = array_filter(
		trb_portal_legacy_video_catalogue(),
		function( $video ) use ( $profile ) {
			return in_array( $profile, $video['profiles'], true );
		}
	);
	?>
	<section id="video" class="trb-portal__section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">KNOWLEDGE HUB</p><h2>Video e formazione</h2><p>Le lezioni selezionate per il tuo profilo, direttamente dalla precedente videoteca TRB.</p></div>
		<?php if ( empty( $videos ) ) : ?>
			<div class="trb-portal__empty"><p>La videoteca essenziale per il tuo profilo è in preparazione.</p></div>
		<?php else : ?>
			<div class="trb-portal__video-grid">
				<?php foreach ( $videos as $video ) : ?>
					<article class="trb-portal__video-card"><a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $video['youtube'] ); ?>" target="_blank" rel="noopener"><img src="https://i.ytimg.com/vi/<?php echo esc_attr( $video['youtube'] ); ?>/hqdefault.jpg" alt="" loading="lazy" /><span aria-hidden="true">▶</span></a><h3><?php echo esc_html( $video['title'] ); ?></h3><p><a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $video['youtube'] ); ?>" target="_blank" rel="noopener">Guarda su YouTube <span aria-hidden="true">↗</span></a></p></article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

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
			<a href="#documenti">Procedure</a>
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
		<?php trb_portal_render_resource_section( 'documenti', 'Documenti e procedure', 'Le indicazioni aggiornate per gestire ogni fase della collaborazione.', $resources['docs'] ); ?>
		<?php trb_portal_render_video_library( $profile ); ?>
		<?php trb_portal_render_resource_section( 'download', 'Library e download', 'Manuali, e-book e materiali da conservare.', $resources['wpdmpro'] ); ?>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'trb_artist_portal', 'trb_portal_dashboard_shortcode' );

function trb_portal_render_artist_profile_section() {
	$saved = isset( $_GET['trb_profile'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['trb_profile'] ) );
	$complete = trb_portal_artist_profile_is_complete();
	$company_requested = '1' === trb_portal_artist_profile_value( 'invoice_requested' );
	$user = wp_get_current_user();
	?>
	<section id="profilo" class="trb-portal__section trb-portal__profile-section">
		<div class="trb-portal__section-heading"><p class="trb-portal__eyebrow">PRIMO PASSAGGIO OBBLIGATORIO</p><h2>Aggiorna il profilo artista</h2><p>Prima della prima release servono dati completi e verificabili. Li riuseremo per preparare le pratiche e, in seguito, i contratti.</p></div>
		<?php if ( $saved ) : ?><div class="trb-portal__message trb-portal__message--success">Profilo artista aggiornato.</div><?php endif; ?>
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
						<label>Cellulare abilitato a ricezione SMS <span>*</span><input type="tel" name="trb_artist_phone" autocomplete="tel" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'phone' ) ); ?>" required /></label>
						<label>Data di nascita <span>*</span><input type="date" name="trb_artist_birth_date" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'birth_date' ) ); ?>" required /></label>
						<label>Luogo di nascita <input type="text" name="trb_artist_birth_place" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'birth_place' ) ); ?>" /></label>
						<label>Codice fiscale <span>*</span><input type="text" name="trb_artist_tax_code" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'tax_code' ) ); ?>" required /></label>
						<label>Indirizzo di residenza <span>*</span><input type="text" name="trb_artist_street" autocomplete="street-address" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'street' ) ); ?>" required /></label>
						<label>Numero civico <span>*</span><input type="text" name="trb_artist_street_number" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'street_number' ) ); ?>" required /></label>
						<label>CAP <span>*</span><input type="text" name="trb_artist_postal_code" autocomplete="postal-code" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'postal_code' ) ); ?>" required /></label>
						<label>Città <span>*</span><input type="text" name="trb_artist_city" autocomplete="address-level2" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'city' ) ); ?>" required /></label>
						<label>Provincia <span>*</span><input type="text" name="trb_artist_province" autocomplete="address-level1" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'province' ) ); ?>" required /></label>
						<label>Nazione <span>*</span><input type="text" name="trb_artist_country" autocomplete="country-name" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'country' ) ); ?>" required /></label>
					</div><p id="trb-account-data-note" class="trb-portal__field-help">Nome, cognome ed e-mail sono ripresi dall’anagrafica del tuo account. Per modificarli, usa il pulsante “Apri una segnalazione” in alto.</p>
					<details class="trb-portal__company-details" <?php echo $company_requested ? 'open' : ''; ?>><summary>Hai una partita IVA o devi ricevere una fattura intestata a un’azienda?</summary><div><label class="trb-portal__invoice-toggle"><input type="checkbox" name="trb_artist_invoice_requested" value="1" <?php checked( $company_requested ); ?> /> Inserisci dati aziendali per fattura specifica</label><div class="trb-portal__field-grid"><label>Ragione sociale <input type="text" name="trb_artist_company_name" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'company_name' ) ); ?>" /></label><label>Partita IVA <input type="text" name="trb_artist_company_vat" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'company_vat' ) ); ?>" /></label><label>Codice SDI <input type="text" name="trb_artist_company_sdi" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'company_sdi' ) ); ?>" /></label><label>Indirizzo della sede aziendale <input type="text" name="trb_artist_company_address" autocomplete="street-address" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'company_address' ) ); ?>" /></label></div></div></details>
					<div class="trb-portal__private-documents"><strong>Documenti riservati</strong><p>Carica i quattro documenti richiesti. Restano esclusivamente nella tua pratica e non vengono pubblicati.</p><div class="trb-portal__field-grid"><label>Carta d’identità — fronte <small>PDF, JPG o PNG</small><input type="file" name="trb_artist_id_front" accept="application/pdf,image/jpeg,image/png" /></label><label>Carta d’identità — retro <small>PDF, JPG o PNG</small><input type="file" name="trb_artist_id_back" accept="application/pdf,image/jpeg,image/png" /></label><label>Codice fiscale o tessera sanitaria — fronte <small>PDF, JPG o PNG</small><input type="file" name="trb_artist_tax_front" accept="application/pdf,image/jpeg,image/png" /></label><label>Codice fiscale o tessera sanitaria — retro <small>PDF, JPG o PNG</small><input type="file" name="trb_artist_tax_back" accept="application/pdf,image/jpeg,image/png" /></label></div><?php trb_portal_render_private_files(); ?></div>
					<button class="trb-button" type="submit">Salva i dati contrattuali</button>
				</form>
			</details>
			<details class="trb-portal__profile-module">
				<summary><span><b>Identità artistica</b><small>Nome d’arte, biografia e immagini ufficiali</small></span><em>Apri il modulo</em></summary>
				<form class="trb-portal__request-form trb-portal__profile-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="trb_portal_save_artist_profile" /><?php wp_nonce_field( 'trb_portal_save_artist_profile', 'trb_portal_profile_nonce' ); ?><label>Nome d’arte <span>*</span><input type="text" name="trb_artist_artist_name" value="<?php echo esc_attr( trb_portal_artist_profile_value( 'artist_name' ) ); ?>" required /></label><label>Biografia artistica aggiornata <span>*</span><textarea name="trb_artist_bio" rows="9" required placeholder="Incolla qui la biografia aggiornata: non caricare un file."><?php echo esc_textarea( trb_portal_artist_profile_value( 'bio' ) ); ?></textarea><small>Inserisci testo copiato e incollato, pronto per materiali editoriali e profili artista.</small></label><div class="trb-portal__private-documents"><strong>Foto artista</strong><p>Carica fino a 6 foto ad alta qualità. Puoi selezionare le immagini già caricate per eliminarle e sostituirle.</p><label>Foto artista <small>JPG, PNG o WEBP · massimo 6 foto in totale</small><input type="file" name="trb_artist_photos[]" accept="image/jpeg,image/png,image/webp" multiple /></label><?php trb_portal_render_private_files( 'photo' ); ?></div><button class="trb-button" type="submit">Salva identità artistica</button></form>
			</details>
		</div>
	</section>
	<?php
}

function trb_portal_render_private_files( $group = '' ) {
	$files = trb_portal_private_profile_files();
	if ( $group ) {
		$files = array_filter( $files, function( $file ) use ( $group ) { return isset( $file['group'] ) && $group === $file['group']; } );
	}
	if ( empty( $files ) ) return;
	?><fieldset class="trb-portal__uploaded-files"><legend>File già ricevuti</legend><p>Seleziona un file solo se vuoi eliminarlo e poi caricarne una versione aggiornata.</p><?php foreach ( $files as $file ) : ?><label><input type="checkbox" name="trb_artist_remove_files[]" value="<?php echo esc_attr( $file['id'] ); ?>" /> <?php echo esc_html( ! empty( $file['label'] ) ? $file['label'] . ': ' : '' ); ?><?php echo esc_html( $file['name'] ); ?></label><?php endforeach; ?></fieldset><?php
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
				'posts_per_page' => ( $search && 'trb_guide' === $post_type ) ? 100 : 7,
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
	foreach ( trb_portal_supported_resource_types() as $post_type ) {
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
					<?php if ( in_array( $post->post_type, array( 'trb_guide', 'docs' ), true ) ) : ?>
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
	if ( is_front_page() || is_page( array( 'registrati', 'accedi', 'recupera-password', 'segnalazione' ) ) || is_singular( 'wpdmpro' ) || ( is_page() && $post && has_shortcode( $post->post_content, 'trb_artist_portal' ) ) ) {
		$style_path    = get_template_directory() . '/assets/css/trb-artist-portal.css';
		$style_version = file_exists( $style_path ) ? (string) filemtime( $style_path ) : DOCY_VERSION;
		wp_enqueue_style( 'trb-artist-portal', get_template_directory_uri() . '/assets/css/trb-artist-portal.css', array(), $style_version );

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
	if ( false === $start ) {
		return $html;
	}
	$end = strpos( $html, '<style type="text/css">', $start );
	if ( false === $end ) {
		return $html;
	}
	return substr( $html, 0, $start ) . substr( $html, $end );
}

function trb_portal_start_private_output_filter() {
	if ( is_front_page() || trb_portal_is_private_screen() ) {
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
}
add_action( 'template_redirect', 'trb_portal_send_security_headers', 998 );

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

/** Render a dedicated artist-only support page without a legacy builder route. */
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
	include $support_template;
	exit;
}
add_action( 'template_redirect', 'trb_portal_render_public_support_early', -999 );

/** Create the support endpoint once, independently from Elementor. */
function trb_portal_maybe_create_support_page() {
	if ( get_option( 'trb_portal_support_page_created' ) || ! current_user_can( 'manage_options' ) ) return;
	if ( ! get_page_by_path( 'segnalazione' ) ) {
		$page_id = wp_insert_post( array( 'post_title' => 'Apri una segnalazione', 'post_name' => 'segnalazione', 'post_status' => 'publish', 'post_type' => 'page' ) );
		if ( is_wp_error( $page_id ) ) return;
	}
	update_option( 'trb_portal_support_page_created', 1, false );
}
add_action( 'admin_init', 'trb_portal_maybe_create_support_page' );

/** Store every request and notify the TRB mailbox. */
function trb_portal_submit_support_request() {
	check_admin_referer( 'trb_portal_submit_support', 'trb_support_nonce' );
	$user = wp_get_current_user();
	$logged_in = is_user_logged_in();
	$name = $logged_in ? trim( $user->first_name . ' ' . $user->last_name ) : ( isset( $_POST['trb_support_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_support_name'] ) ) : '' );
	$email = $logged_in ? $user->user_email : ( isset( $_POST['trb_support_email'] ) ? sanitize_email( wp_unslash( $_POST['trb_support_email'] ) ) : '' );
	$artist_name = isset( $_POST['trb_support_artist_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_support_artist_name'] ) ) : '';
	$type = isset( $_POST['trb_support_type'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_support_type'] ) ) : 'supporto';
	$subject = isset( $_POST['trb_support_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['trb_support_subject'] ) ) : '';
	$message = isset( $_POST['trb_support_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['trb_support_message'] ) ) : '';
	$website = isset( $_POST['trb_support_website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['trb_support_website'] ) ) ) : '';
	$started = isset( $_POST['trb_support_started'] ) ? absint( $_POST['trb_support_started'] ) : 0;
	$labels = array( 'supporto' => 'Supporto via e-mail', 'call' => 'Richiesta call di 30 minuti', 'dati' => 'Modifica dati anagrafici o contatti', 'problema' => 'Problema tecnico del portale' );
	$type = isset( $labels[ $type ] ) ? $type : 'supporto';
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

/** Catch the removed account endpoint before a legacy plugin renders its 404. */
function trb_portal_early_account_route_redirect() {
	if ( is_admin() || 'GET' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) return;
	$path = untrailingslashit( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', PHP_URL_PATH ) );
	if ( '/my-account' === $path ) {
		wp_safe_redirect( home_url( '/accedi/' ), 302 );
		exit;
	}
}
add_action( 'wp_loaded', 'trb_portal_early_account_route_redirect', 1 );

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
		'/homepage-ddb'                  => '/area-artisti/',
		'/homepage-ddb-trb'              => '/area-artisti/',
		'/home-page-dds'                 => '/area-artisti/',
		'/homepage-trb'                  => '/area-artisti/',
		'/home-page-trb-basic'           => '/area-artisti/',
		'/video-corsi-ddb'               => '/area-artisti/#video',
		'/video-corsi-ddb-trb'           => '/area-artisti/#video',
		'/video-corsi-dds'               => '/area-artisti/#video',
		'/video-corsi-trb'               => '/area-artisti/#video',
		'/video-corsi-trb-basic'         => '/area-artisti/#video',
		'/strumenti-e-utilita-ddb'       => '/area-artisti/#download',
		'/strumenti-e-utilita-ddb-trb'   => '/area-artisti/#download',
		'/strumenti-e-utilita-dds'       => '/area-artisti/#download',
		'/strumenti-e-utilita-trb'       => '/area-artisti/#download',
		'/strumenti-e-utilita-trb-basic' => '/area-artisti/#download',
		'/multi-documentations'          => '/area-artisti/#documenti',
		'/contact'                       => '/segnalazione/',
		'/my-account'                    => '/accedi/',
		'/login'                         => '/accedi/',
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
			'fields'       => array( 'ID', 'user_registered' ),
			'meta_key'     => '_trb_portal_registration_source',
			'meta_value'   => $cutoff,
			'meta_compare' => '<=',
			'meta_type'    => 'NUMERIC',
			'number'       => 100,
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
