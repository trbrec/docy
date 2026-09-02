<?php
/**
 * Bridge between artist.trbrec.com and the existing DDB/TRB Apps Script factories.
 * Contract generation and OTPService remain exclusively inside Apps Script.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const TRB_RELEASE_BRIDGE_OPTION = 'trb_release_bridge_settings';

/** Recover the highest portal-assigned sequence if an annual counter is missing. */
function trb_release_bridge_recover_isrc_seed( $year, $is_trb, $fallback ) {
    global $wpdb;

    $prefix = 'ITV24' . sprintf( '%02d', absint( $year ) );
    $values = $wpdb->get_col( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_trb_release_tracks' AND meta_value LIKE %s",
        '%' . $wpdb->esc_like( $prefix ) . '%'
    ) );
    $highest = absint( $fallback );
    foreach ( $values as $value ) {
        $tracks = maybe_unserialize( $value );
        if ( ! is_array( $tracks ) ) continue;
        foreach ( $tracks as $track ) {
            $code = isset( $track['isrc'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $track['isrc'] ) ) : '';
            if ( ! preg_match( '/^' . preg_quote( $prefix, '/' ) . '([0-9]{5})$/', $code, $match ) ) continue;
            $sequence = absint( $match[1] );
            if ( ( $is_trb && $sequence >= 1 && $sequence <= 9999 ) || ( ! $is_trb && $sequence >= 10000 && $sequence <= 99999 ) ) $highest = max( $highest, $sequence );
        }
    }
    return $highest;
}

/**
 * Reserve a consecutive block of TRB rec ISRCs with one atomic database write.
 * TRB releases use 00001–09999 (2026 starts at 00005); DDS, DDB12, DDB and
 * DDB-TRB use 10000–99999. Each year has independent counters and receives the
 * current two-digit year in the ISRC itself.
 */
function trb_release_bridge_allocate_isrcs( $quantity, $profile = 'trb' ) {
    global $wpdb;

    $quantity = absint( $quantity );
    if ( $quantity < 1 || $quantity > 60 ) return new WP_Error( 'invalid_isrc_quantity', 'Numero di ISRC da assegnare non valido.' );

    $profile = sanitize_key( $profile );
    if ( ! in_array( $profile, array( 'dds', 'ddb12', 'ddb', 'ddb_trb', 'trb' ), true ) ) return new WP_Error( 'invalid_isrc_profile', 'Gruppo contrattuale non valido per l’assegnazione ISRC.' );

    $year = absint( wp_date( 'y' ) );
    $is_trb = 'trb' === $profile;
    $seed = $is_trb ? ( 26 === $year ? 4 : 0 ) : 9999;
    $range_start = $is_trb ? 1 : 10000;
    $range_end = $is_trb ? 9999 : 99999;
    // Preserve the existing TRB counter name so already allocated codes remain reserved.
    $option_name = $is_trb ? 'trb_release_isrc_sequence_' . sprintf( '%02d', $year ) : 'trb_release_isrc_distribution_sequence_' . sprintf( '%02d', $year );
    $counter_exists = null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option_name ) );
    if ( ! $counter_exists ) $seed = trb_release_bridge_recover_isrc_seed( $year, $is_trb, $seed );
    // Create the annual counter at its seed, then increment it in a dedicated
    // atomic UPDATE. Keeping LAST_INSERT_ID() out of the INSERT avoids MySQL
    // returning wp_options.option_id during the very first allocation.
    $created = $wpdb->query( $wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no')",
        $option_name,
        $seed
    ) );
    if ( false === $created ) return new WP_Error( 'isrc_reservation_failed', 'Il sistema non è riuscito a inizializzare il registro ISRC.' );

    $updated = $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->options}
         SET option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + %d)
         WHERE option_name = %s AND CAST(option_value AS UNSIGNED) BETWEEN %d AND %d",
        $quantity,
        $option_name,
        $seed,
        $range_end - $quantity
    ) );
    if ( 1 !== $updated ) return new WP_Error( 'isrc_sequence_exhausted', 'La sequenza ISRC disponibile non è valida o risulta esaurita.' );

    $end = absint( $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ) );
    $start = $end - $quantity + 1;
    if ( $start < $range_start || $end > $range_end ) return new WP_Error( 'isrc_sequence_exhausted', 'La sequenza ISRC disponibile non è valida o risulta esaurita.' );

    wp_cache_delete( $option_name, 'options' );
    $prefix = 'ITV24' . sprintf( '%02d', $year );
    $codes = array();
    for ( $sequence = $start; $sequence <= $end; $sequence++ ) {
        $codes[] = $prefix . sprintf( '%05d', $sequence );
    }
    return $codes;
}

function trb_release_bridge_settings() {
    $defaults = array('ddb_webapp_url'=>'','trb_webapp_url'=>'','shared_secret'=>'','dds_store_secret'=>'');
    $saved = get_option( TRB_RELEASE_BRIDGE_OPTION, array() );
    $saved = is_array( $saved ) ? $saved : array();
    if ( defined( 'TRB_DDB_WEBAPP_URL' ) && empty( $saved['ddb_webapp_url'] ) ) $saved['ddb_webapp_url'] = TRB_DDB_WEBAPP_URL;
    if ( defined( 'TRB_TRB_WEBAPP_URL' ) && empty( $saved['trb_webapp_url'] ) ) $saved['trb_webapp_url'] = TRB_TRB_WEBAPP_URL;
    if ( defined( 'TRB_RELEASE_BRIDGE_SECRET' ) && empty( $saved['shared_secret'] ) ) $saved['shared_secret'] = TRB_RELEASE_BRIDGE_SECRET;
    if ( defined( 'TRB_DDS_STORE_SECRET' ) && empty( $saved['dds_store_secret'] ) ) $saved['dds_store_secret'] = TRB_DDS_STORE_SECRET;
    return wp_parse_args( $saved, $defaults );
}

function trb_release_bridge_admin_menu() {
    add_options_page('Collegamento contratti release','Contratti release','manage_options','trb-release-bridge','trb_release_bridge_settings_page');
}
add_action( 'admin_menu', 'trb_release_bridge_admin_menu' );

function trb_release_bridge_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( isset( $_POST['trb_release_bridge_save'] ) ) {
        check_admin_referer( 'trb_release_bridge_save' );
        update_option( TRB_RELEASE_BRIDGE_OPTION, array(
            'ddb_webapp_url' => esc_url_raw( wp_unslash( $_POST['ddb_webapp_url'] ?? '' ) ),
            'trb_webapp_url' => esc_url_raw( wp_unslash( $_POST['trb_webapp_url'] ?? '' ) ),
            'shared_secret'  => sanitize_text_field( wp_unslash( $_POST['shared_secret'] ?? '' ) ),
            'dds_store_secret' => sanitize_text_field( wp_unslash( $_POST['dds_store_secret'] ?? '' ) ),
        ), false );
        echo '<div class="notice notice-success"><p>Impostazioni salvate.</p></div>';
    }
    $s = trb_release_bridge_settings();
    ?>
    <div class="wrap"><h1>Collegamento contratti release</h1>
    <p>Inserire gli URL delle Web App pubblicate dai due progetti Apps Script. OTPService resta gestito dagli script dei fogli.</p>
    <form method="post"><?php wp_nonce_field( 'trb_release_bridge_save' ); ?>
    <table class="form-table"><tbody>
    <tr><th><label for="ddb_webapp_url">Web App DDB</label></th><td><input class="large-text" id="ddb_webapp_url" name="ddb_webapp_url" value="<?php echo esc_attr( $s['ddb_webapp_url'] ); ?>"></td></tr>
    <tr><th><label for="trb_webapp_url">Web App TRB</label></th><td><input class="large-text" id="trb_webapp_url" name="trb_webapp_url" value="<?php echo esc_attr( $s['trb_webapp_url'] ); ?>"></td></tr>
    <tr><th><label for="shared_secret">Segreto condiviso</label></th><td><input class="regular-text" id="shared_secret" name="shared_secret" value="<?php echo esc_attr( $s['shared_secret'] ); ?>"><p class="description">Usare lo stesso valore nella proprietà PORTAL_SHARED_SECRET di entrambi i progetti Apps Script.</p></td></tr>
    <tr><th><label for="dds_store_secret">Segreto Store DDS</label></th><td><input type="password" class="regular-text" id="dds_store_secret" name="dds_store_secret" value="<?php echo esc_attr( $s['dds_store_secret'] ); ?>" autocomplete="new-password"><p class="description">Chiave HMAC dedicata all’attivazione DDS dallo Store. Non riutilizzare il segreto dei contratti.</p></td></tr>
    </tbody></table><p><button class="button button-primary" name="trb_release_bridge_save" value="1">Salva</button></p></form></div>
    <?php
}

/** Administrative-only artist field used by both contract spreadsheets. */
function trb_release_bridge_render_preliminary_contract_field( $user ) {
    if ( ! current_user_can( 'manage_options' ) || ! $user instanceof WP_User ) return;
    $value = (string) get_user_meta( $user->ID, '_trb_artist_preliminary_contract', true );
    $term  = (string) get_user_meta( $user->ID, '_trb_artist_contract_term', true );
    wp_nonce_field( 'trb_release_bridge_save_preliminary_contract_' . $user->ID, 'trb_release_bridge_preliminary_contract_nonce' );
    ?>
    <h2>Contratto preliminare</h2>
    <table class="form-table" role="presentation"><tbody><tr>
        <th><label for="trb_artist_preliminary_contract">Contratto preliminare</label></th>
        <td><input type="text" class="regular-text" id="trb_artist_preliminary_contract" name="trb_artist_preliminary_contract" value="<?php echo esc_attr( $value ); ?>" maxlength="120"><p class="description">Campo amministrativo riservato e invisibile all’artista. <?php echo esc_html( trb_release_bridge_expected_contract_label( $user ) ); ?> Gli abbinamenti incompatibili vengono bloccati.</p></td>
    </tr><tr>
        <th><label for="trb_artist_contract_term">Data di attuazione/scadenza</label></th>
        <td><input type="text" class="regular-text" id="trb_artist_contract_term" name="trb_artist_contract_term" value="<?php echo esc_attr( $term ); ?>" maxlength="50" placeholder="08/08/26 - 08/08/27"><p class="description">Formato: GG/MM/AA - GG/MM/AA oppure GG/MM/AA - INFINITO. Campo riservato e invisibile all’artista. Dal giorno successivo alla scadenza i profili DDB-TRB passano automaticamente a TRB; i profili DDS, DDB12 e DDB vengono sospesi fino a un rinnovo approvato dalla Direzione.</p></td>
    </tr></tbody></table>
    <?php
}
add_action( 'show_user_profile', 'trb_release_bridge_render_preliminary_contract_field' );
add_action( 'edit_user_profile', 'trb_release_bridge_render_preliminary_contract_field' );

function trb_release_bridge_normalize_contract_term( $value ) {
    $value = strtoupper( sanitize_text_field( wp_unslash( (string) $value ) ) );
    $value = preg_replace( '/\s*-\s*/', ' - ', trim( $value ) );
    return preg_replace( '/\s+/', ' ', $value );
}

/** Parse the administrative contract term using the legal Europe/Rome day. */
function trb_release_bridge_contract_term_dates( $value ) {
    $value = trb_release_bridge_normalize_contract_term( $value );
    if ( '' === $value ) return new WP_Error( 'contract_term_missing', 'Data di attuazione/scadenza non assegnata.' );
    if ( ! preg_match( '/^(\d{2})\/(\d{2})\/(\d{2}|\d{4}) - (?:(\d{2})\/(\d{2})\/(\d{2}|\d{4})|INFINITO)$/', $value, $parts ) ) {
        return new WP_Error( 'invalid_contract_term', 'Data di attuazione/scadenza non valida. Usa GG/MM/AA - GG/MM/AA oppure GG/MM/AA - INFINITO.' );
    }

    $start_year = 2 === strlen( $parts[3] ) ? 2000 + absint( $parts[3] ) : absint( $parts[3] );
    if ( ! checkdate( absint( $parts[2] ), absint( $parts[1] ), $start_year ) ) {
        return new WP_Error( 'invalid_contract_start', 'La data di attuazione indicata non esiste.' );
    }

    $timezone = new DateTimeZone( 'Europe/Rome' );
    $start = new DateTimeImmutable( sprintf( '%04d-%02d-%02d 00:00:00', $start_year, absint( $parts[2] ), absint( $parts[1] ) ), $timezone );
    if ( false !== strpos( $value, 'INFINITO' ) ) return array( 'value' => $value, 'start' => $start, 'end' => null );

    $end_year = 2 === strlen( $parts[6] ) ? 2000 + absint( $parts[6] ) : absint( $parts[6] );
    if ( ! checkdate( absint( $parts[5] ), absint( $parts[4] ), $end_year ) ) {
        return new WP_Error( 'invalid_contract_end', 'La data di scadenza indicata non esiste.' );
    }
    $end = new DateTimeImmutable( sprintf( '%04d-%02d-%02d 00:00:00', $end_year, absint( $parts[5] ), absint( $parts[4] ) ), $timezone );
    if ( $end < $start ) return new WP_Error( 'invalid_contract_order', 'La scadenza non può precedere la data di attuazione.' );

    return array( 'value' => $value, 'start' => $start, 'end' => $end );
}

/** Return the portal profile, accounting for a role changed in the same save. */
function trb_release_bridge_contract_profile( $user ) {
    if ( ! $user instanceof WP_User || ! function_exists( 'trb_portal_profiles' ) ) return false;
    $posted_role = current_user_can( 'manage_options' ) && isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
    foreach ( trb_portal_profiles() as $key => $profile ) {
        $roles = array_merge( array( $profile['role'] ?? '' ), (array) ( $profile['aliases'] ?? array() ) );
        $roles = array_values( array_filter( array_map( 'sanitize_key', $roles ) ) );
        if ( ( $posted_role && in_array( $posted_role, $roles, true ) ) || ( ! $posted_role && array_intersect( $roles, (array) $user->roles ) ) ) return $key;
    }
    return false;
}

function trb_release_bridge_expected_contract_label( $user ) {
	return 'Inserisci il numero del contratto preliminare, che deve iniziare con TRB- (esempio: TRB-20260327).';
}

/**
 * Validate only the preliminary contract identifier.
 *
 * The contractual model is determined by the artist profile and must never be
 * inferred from this field: every preliminary identifier starts with TRB-.
 */
function trb_release_bridge_validate_preliminary_contract( $user, $value ) {
    if ( ! $user instanceof WP_User ) return new WP_Error( 'contract_user_invalid', 'Profilo artista non valido.' );
	$value = strtoupper( trim( sanitize_text_field( (string) $value ) ) );
    if ( '' === trim( $value ) ) return true;
	if ( 0 !== strpos( $value, 'TRB-' ) ) {
		return new WP_Error( 'preliminary_contract_invalid', 'Il numero del contratto preliminare deve iniziare con TRB-.' );
	}
    return true;
}

/** Keep the spotify4 production fixture complete enough to test every TRB release gate. */
function trb_release_bridge_seed_spotify4_qa_contract() {
	if ( '20260824.1' === get_option( 'trb_release_spotify4_qa_contract_version' ) ) return;
	$user = get_user_by( 'login', 'spotify4' );
	if ( ! $user ) $user = get_user_by( 'email', 'spotify4@trbrec.com' );
	if ( ! $user instanceof WP_User ) return;
	$preliminary = (string) get_user_meta( $user->ID, '_trb_artist_preliminary_contract', true );
	if ( '' === trim( $preliminary ) || is_wp_error( trb_release_bridge_validate_preliminary_contract( $user, $preliminary ) ) ) {
		update_user_meta( $user->ID, '_trb_artist_preliminary_contract', 'TRB-QA-SPOTIFY4' );
	}
	$term = (string) get_user_meta( $user->ID, '_trb_artist_contract_term', true );
	if ( '' === trim( $term ) || is_wp_error( trb_release_bridge_contract_term_dates( $term ) ) ) {
		update_user_meta( $user->ID, '_trb_artist_contract_term', '01/01/26 - INFINITO' );
	}
	update_user_meta( $user->ID, '_trb_artist_contract_profile', 'trb' );
	update_option( 'trb_release_spotify4_qa_contract_version', '20260824.1', false );
}
add_action( 'init', 'trb_release_bridge_seed_spotify4_qa_contract', 5 );

/** Keep every contractual QA account ready to exercise its own release rule. */
function trb_release_bridge_seed_release_group_qa_contracts() {
	if ( '20260902.1' === get_option( 'trb_release_group_qa_contract_version' ) ) return;
	$fixtures = array(
		'spotify1' => array( 'profile' => 'dds', 'term' => '01/01/26 - 31/12/26' ),
		'spotify6' => array( 'profile' => 'ddb12', 'term' => '01/01/26 - 31/12/26' ),
		'spotify2' => array( 'profile' => 'ddb', 'term' => '01/01/26 - 31/12/26' ),
		'spotify3' => array( 'profile' => 'ddb_trb', 'term' => '01/01/26 - 31/12/27' ),
		'spotify4' => array( 'profile' => 'trb', 'term' => '01/01/26 - INFINITO' ),
		'spotify9' => array( 'profile' => 'trb', 'term' => '01/01/26 - INFINITO' ),
	);
	foreach ( $fixtures as $login => $fixture ) {
		$user = get_user_by( 'login', $login );
		if ( ! $user ) $user = get_user_by( 'email', $login . '@trbrec.com' );
		if ( ! $user instanceof WP_User ) continue;
		$preliminary = (string) get_user_meta( $user->ID, '_trb_artist_preliminary_contract', true );
		if ( '' === trim( $preliminary ) || is_wp_error( trb_release_bridge_validate_preliminary_contract( $user, $preliminary ) ) ) {
			update_user_meta( $user->ID, '_trb_artist_preliminary_contract', 'TRB-QA-' . strtoupper( $login ) );
		}
		$term = (string) get_user_meta( $user->ID, '_trb_artist_contract_term', true );
		if ( '' === trim( $term ) || is_wp_error( trb_release_bridge_contract_term_dates( $term ) ) ) {
			update_user_meta( $user->ID, '_trb_artist_contract_term', $fixture['term'] );
		}
		update_user_meta( $user->ID, '_trb_artist_contract_profile', $fixture['profile'] );
		if ( 'spotify9' === $login && function_exists( 'trb_portal_profiles' ) ) {
			$profiles = trb_portal_profiles();
			foreach ( $profiles as $profile ) {
				foreach ( array_merge( array( $profile['role'] ?? '' ), (array) ( $profile['aliases'] ?? array() ) ) as $role ) {
					$role = sanitize_key( $role );
					if ( $role && 'artista_d' !== $role && in_array( $role, (array) $user->roles, true ) ) $user->remove_role( $role );
				}
			}
			if ( ! in_array( 'artista_d', (array) $user->roles, true ) ) $user->add_role( 'artista_d' );
		}
	}
	update_option( 'trb_release_group_qa_contract_version', '20260902.1', false );
}
add_action( 'init', 'trb_release_bridge_seed_release_group_qa_contracts', 6 );

function trb_release_bridge_validate_contract_term( $errors, $update, $user ) {
    if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['trb_artist_contract_term'] ) ) return;
    $value = trb_release_bridge_normalize_contract_term( $_POST['trb_artist_contract_term'] );
    if ( '' === $value ) return;
    $dates = trb_release_bridge_contract_term_dates( $value );
    if ( is_wp_error( $dates ) ) $errors->add( $dates->get_error_code(), $dates->get_error_message() );
}
add_action( 'user_profile_update_errors', 'trb_release_bridge_validate_contract_term', 10, 3 );

function trb_release_bridge_validate_contract_model( $errors, $update, $user ) {
    if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['trb_artist_preliminary_contract'] ) ) return;
    $valid = trb_release_bridge_validate_preliminary_contract( $user, wp_unslash( $_POST['trb_artist_preliminary_contract'] ) );
    if ( is_wp_error( $valid ) ) $errors->add( $valid->get_error_code(), $valid->get_error_message() );
}
add_action( 'user_profile_update_errors', 'trb_release_bridge_validate_contract_model', 10, 3 );

/** Canonical and legacy role slugs involved in the one-way DDB-TRB to TRB transition. */
function trb_release_bridge_transition_roles() {
    $roles = array(
        'source' => array( 'artista_c', 'artista_ddb-trb' ),
        'target' => 'artista_d',
    );
    if ( function_exists( 'trb_portal_profiles' ) ) {
        $profiles = trb_portal_profiles();
        if ( ! empty( $profiles['ddb_trb']['role'] ) ) {
            $roles['source'] = array_merge( array( $profiles['ddb_trb']['role'] ), (array) ( $profiles['ddb_trb']['aliases'] ?? array() ) );
        }
        if ( ! empty( $profiles['trb']['role'] ) ) $roles['target'] = $profiles['trb']['role'];
    }
    $roles['source'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $roles['source'] ) ) ) );
    $roles['target'] = sanitize_key( $roles['target'] );
    return $roles;
}

/** Roles whose portal access ends on the day after a finite contract term. */
function trb_release_bridge_expiring_access_roles() {
    $roles = array( 'artista_a', 'artista_dds', 'artista_ddb12', 'artista_e', 'artista_b', 'artista_ddb' );
    if ( function_exists( 'trb_portal_profiles' ) ) {
        $profiles = trb_portal_profiles();
        $roles = array();
        foreach ( array( 'dds', 'ddb12', 'ddb' ) as $profile ) {
            if ( empty( $profiles[ $profile ] ) || ! is_array( $profiles[ $profile ] ) ) continue;
            if ( ! empty( $profiles[ $profile ]['role'] ) ) $roles[] = $profiles[ $profile ]['role'];
            $roles = array_merge( $roles, (array) ( $profiles[ $profile ]['aliases'] ?? array() ) );
        }
    }
    return array_values( array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) ) );
}

/** True only for DDS, DDB12 or DDB after the complete legal expiry day. */
function trb_release_bridge_is_contract_access_expired( $user_id, $now = null ) {
    $user_id = absint( $user_id );
    $user = $user_id ? get_userdata( $user_id ) : false;
    if ( ! $user instanceof WP_User || $user->has_cap( 'manage_options' ) ) return false;
    if ( ! array_intersect( trb_release_bridge_expiring_access_roles(), (array) $user->roles ) ) return false;

    $dates = trb_release_bridge_contract_term_dates( get_user_meta( $user_id, '_trb_artist_contract_term', true ) );
    if ( is_wp_error( $dates ) || ! $dates['end'] instanceof DateTimeImmutable ) return false;

    $timezone = new DateTimeZone( 'Europe/Rome' );
    if ( $now instanceof DateTimeInterface ) {
        $today = new DateTimeImmutable( $now->format( 'Y-m-d' ) . ' 00:00:00', $timezone );
    } elseif ( is_string( $now ) && '' !== trim( $now ) ) {
        $today = ( new DateTimeImmutable( $now, $timezone ) )->setTime( 0, 0, 0 );
    } else {
        $today = new DateTimeImmutable( 'today', $timezone );
    }
    return $today > $dates['end'];
}

/** Read New User Approve's canonical status without requiring the plugin. */
function trb_release_bridge_access_status( $user_id ) {
    $status = '';
    if ( function_exists( 'pw_new_user_approve' ) ) {
        $approval = pw_new_user_approve();
        if ( is_object( $approval ) && is_callable( array( $approval, 'get_user_status' ) ) ) {
            $status = sanitize_key( (string) $approval->get_user_status( $user_id ) );
        }
    }
    if ( '' === $status ) $status = sanitize_key( (string) get_user_meta( $user_id, 'pw_user_status', true ) );
    if ( 'approve' === $status ) return 'approved';
    if ( 'deny' === $status ) return 'denied';
    return $status;
}

/**
 * Deny an expired contractual account without sending the registration-denial
 * email. The dedicated audit marker distinguishes expiry from manual refusal.
 */
function trb_release_bridge_maybe_expire_contract_access( $user_id, $now = null, $source = 'automatic' ) {
    $user_id = absint( $user_id );
    if ( ! $user_id || ! trb_release_bridge_is_contract_access_expired( $user_id, $now ) ) return false;

    $status = trb_release_bridge_access_status( $user_id );
    if ( ! in_array( $status, array( '', 'approved', 'denied' ), true ) ) return false;

    if ( 'denied' !== $status ) update_user_meta( $user_id, 'pw_user_status', 'denied' );
    if ( ! get_user_meta( $user_id, '_trb_artist_contract_access_expired_at', true ) ) {
        $timezone = new DateTimeZone( 'Europe/Rome' );
        update_user_meta( $user_id, '_trb_artist_contract_access_expired_at', ( new DateTimeImmutable( 'now', $timezone ) )->format( DATE_ATOM ) );
        update_user_meta( $user_id, '_trb_artist_contract_access_expired_source', sanitize_key( $source ) );
        update_user_meta( $user_id, '_trb_artist_contract_access_expired_term', trb_release_bridge_normalize_contract_term( get_user_meta( $user_id, '_trb_artist_contract_term', true ) ) );
    }
    clean_user_cache( $user_id );
    return true;
}

/** Apply expiry to every relevant contractual artist; all other groups are excluded. */
function trb_release_bridge_expire_contract_access_users() {
    $user_ids = get_users( array( 'fields' => 'ids', 'role__in' => trb_release_bridge_expiring_access_roles(), 'number' => -1 ) );
    foreach ( $user_ids as $user_id ) trb_release_bridge_maybe_expire_contract_access( $user_id, null, 'hourly' );
}

/** A consistent, branded destination for password and Google authentication. */
function trb_release_bridge_expired_login_url() {
    return add_query_arg( 'trb_login', 'contract_expired', home_url( '/accedi/' ) );
}

/** Override generic approval errors with the contractual expiry reason. */
function trb_release_bridge_block_expired_authentication( $authenticated, $username = '', $password = '' ) {
    $user = $authenticated instanceof WP_User ? $authenticated : false;
    if ( ! $user && '' !== trim( (string) $username ) ) {
        $identifier = trim( (string) $username );
        $user = is_email( $identifier ) ? get_user_by( 'email', $identifier ) : get_user_by( 'login', $identifier );
    }
    if ( $user instanceof WP_User && trb_release_bridge_is_contract_access_expired( $user->ID ) ) {
        trb_release_bridge_maybe_expire_contract_access( $user->ID, null, 'login' );
        return new WP_Error( 'trb_contract_expired', 'Il contratto artistico associato a questo profilo è terminato.' );
    }
    return $authenticated;
}
add_filter( 'authenticate', 'trb_release_bridge_block_expired_authentication', 9999, 3 );

/** Social-login plugins can set a cookie without using the normal authenticate filter. */
function trb_release_bridge_block_expired_social_login( $user_login, $user ) {
    if ( ! $user instanceof WP_User || ! trb_release_bridge_is_contract_access_expired( $user->ID ) ) return;
    trb_release_bridge_maybe_expire_contract_access( $user->ID, null, 'social_login' );
    wp_logout();
    wp_safe_redirect( trb_release_bridge_expired_login_url(), 302 );
    exit;
}
add_action( 'wp_login', 'trb_release_bridge_block_expired_social_login', -1000, 2 );

/** Last-resort guard for existing cookies and authentication-provider callbacks. */
function trb_release_bridge_guard_expired_portal_session() {
    if ( is_admin() || wp_doing_ajax() || ! is_user_logged_in() ) return;
    $user_id = get_current_user_id();
    if ( ! trb_release_bridge_is_contract_access_expired( $user_id ) ) return;
    trb_release_bridge_maybe_expire_contract_access( $user_id, null, 'session' );
    wp_logout();
    wp_safe_redirect( trb_release_bridge_expired_login_url(), 302 );
    exit;
}
add_action( 'template_redirect', 'trb_release_bridge_guard_expired_portal_session', -2000 );

/**
 * Move one expired DDB-TRB artist to TRB. The change is one-way and idempotent;
 * unrelated WordPress roles are preserved.
 */
function trb_release_bridge_maybe_transition_ddb_trb_user( $user_id, $now = null, $source = 'automatic' ) {
    $user_id = absint( $user_id );
    $user = $user_id ? get_userdata( $user_id ) : false;
    if ( ! $user instanceof WP_User ) return false;

    $roles = trb_release_bridge_transition_roles();
    if ( ! array_intersect( $roles['source'], (array) $user->roles ) ) return false;
    if ( ! get_role( $roles['target'] ) ) return false;

    $dates = trb_release_bridge_contract_term_dates( get_user_meta( $user_id, '_trb_artist_contract_term', true ) );
    if ( is_wp_error( $dates ) || ! $dates['end'] instanceof DateTimeImmutable ) return false;

    $timezone = new DateTimeZone( 'Europe/Rome' );
    if ( $now instanceof DateTimeInterface ) {
        $today = new DateTimeImmutable( $now->format( 'Y-m-d' ) . ' 00:00:00', $timezone );
    } elseif ( is_string( $now ) && '' !== trim( $now ) ) {
        $today = new DateTimeImmutable( $now, $timezone );
        $today = $today->setTime( 0, 0, 0 );
    } else {
        $today = new DateTimeImmutable( 'today', $timezone );
    }
    if ( $today <= $dates['end'] ) return false;

    // Re-read immediately before mutation so a concurrent manual group change
    // cannot be overwritten by a stale scheduled check.
    clean_user_cache( $user_id );
    $user = get_userdata( $user_id );
    if ( ! $user instanceof WP_User || ! array_intersect( $roles['source'], (array) $user->roles ) ) return false;

    foreach ( $roles['source'] as $source_role ) {
        if ( in_array( $source_role, (array) $user->roles, true ) ) $user->remove_role( $source_role );
    }
    if ( ! in_array( $roles['target'], (array) $user->roles, true ) ) $user->add_role( $roles['target'] );

    $transitioned_at = ( new DateTimeImmutable( 'now', $timezone ) )->format( DATE_ATOM );
    update_user_meta( $user_id, '_trb_artist_group_transitioned_at', $transitioned_at );
    update_user_meta( $user_id, '_trb_artist_group_transition_source', sanitize_key( $source ) );
    update_user_meta( $user_id, '_trb_artist_group_transition_contract_term', $dates['value'] );
    update_user_meta( $user_id, '_trb_artist_contract_profile', 'trb' );
    do_action( 'trb_artist_group_transitioned', $user_id, 'ddb_trb', 'trb', $dates['value'], $transitioned_at );
    return true;
}

/** Check every current DDB-TRB account; the small role-scoped query is safe to repeat. */
function trb_release_bridge_transition_expired_ddb_trb_users() {
    $roles = trb_release_bridge_transition_roles();
    $user_ids = get_users( array( 'fields' => 'ids', 'role__in' => $roles['source'], 'number' => -1 ) );
    foreach ( $user_ids as $user_id ) trb_release_bridge_maybe_transition_ddb_trb_user( $user_id, null, 'hourly' );
    trb_release_bridge_expire_contract_access_users();
}
add_action( 'trb_release_bridge_transition_expired_ddb_trb', 'trb_release_bridge_transition_expired_ddb_trb_users' );

/** Keep the hourly safety check registered after theme updates or cron cleanup. */
function trb_release_bridge_schedule_group_transitions() {
    if ( ! wp_next_scheduled( 'trb_release_bridge_transition_expired_ddb_trb' ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'trb_release_bridge_transition_expired_ddb_trb' );
    }
}
add_action( 'init', 'trb_release_bridge_schedule_group_transitions', 20 );

/** If an expired artist logs in before cron runs, update the role immediately. */
function trb_release_bridge_transition_current_artist() {
    if ( ! is_user_logged_in() ) return;
    trb_release_bridge_maybe_transition_ddb_trb_user( get_current_user_id(), null, 'access' );
    trb_release_bridge_maybe_expire_contract_access( get_current_user_id(), null, 'access' );
}
add_action( 'init', 'trb_release_bridge_transition_current_artist', 21 );

/**
 * Traffic fallback: run at most once per hour even when WP-Cron is disabled or
 * delayed. Concurrent requests are harmless because every transition is
 * role-scoped and idempotent.
 */
function trb_release_bridge_maybe_run_group_transition_fallback() {
    if ( wp_doing_cron() || get_transient( 'trb_release_bridge_group_transition_sweep' ) ) return;
    set_transient( 'trb_release_bridge_group_transition_sweep', 1, HOUR_IN_SECONDS );
    trb_release_bridge_transition_expired_ddb_trb_users();
}
add_action( 'init', 'trb_release_bridge_maybe_run_group_transition_fallback', 22 );

function trb_release_bridge_save_preliminary_contract_field( $user_id ) {
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user_id ) ) return;
    $nonce = sanitize_text_field( wp_unslash( $_POST['trb_release_bridge_preliminary_contract_nonce'] ?? '' ) );
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'trb_release_bridge_save_preliminary_contract_' . $user_id ) ) return;
    $value = sanitize_text_field( wp_unslash( $_POST['trb_artist_preliminary_contract'] ?? '' ) );
	$user = get_userdata( $user_id );
	if ( is_wp_error( trb_release_bridge_validate_preliminary_contract( $user, $value ) ) ) return;
    if ( '' === $value ) delete_user_meta( $user_id, '_trb_artist_preliminary_contract' );
    else update_user_meta( $user_id, '_trb_artist_preliminary_contract', $value );
    $term = trb_release_bridge_normalize_contract_term( $_POST['trb_artist_contract_term'] ?? '' );
    if ( '' === $term ) delete_user_meta( $user_id, '_trb_artist_contract_term' );
    else update_user_meta( $user_id, '_trb_artist_contract_term', $term );
    trb_release_bridge_maybe_transition_ddb_trb_user( $user_id, null, 'admin_save' );
    if ( trb_release_bridge_is_contract_access_expired( $user_id ) ) {
        trb_release_bridge_maybe_expire_contract_access( $user_id, null, 'admin_save' );
    } else {
        // Extending the term removes the expiry reason, but deliberately does
        // not approve the account: renewal and Access Status remain two
        // explicit administrative decisions.
        delete_user_meta( $user_id, '_trb_artist_contract_access_expired_at' );
        delete_user_meta( $user_id, '_trb_artist_contract_access_expired_source' );
        delete_user_meta( $user_id, '_trb_artist_contract_access_expired_term' );
    }
}
add_action( 'personal_options_update', 'trb_release_bridge_save_preliminary_contract_field' );
add_action( 'edit_user_profile_update', 'trb_release_bridge_save_preliminary_contract_field' );

/** True for every contractual artist role shown in the WordPress user list. */
function trb_release_bridge_is_managed_artist( $user ) {
    if ( ! $user instanceof WP_User ) return false;
    $roles = array( 'artista_a', 'artista_dds', 'artista_ddb12', 'artista_e', 'artista_b', 'artista_ddb', 'artista_c', 'artista_ddb-trb', 'artista_d', 'artista_trb' );
    if ( function_exists( 'trb_portal_profiles' ) ) {
        $roles = array();
        foreach ( trb_portal_profiles() as $profile ) {
            if ( ! is_array( $profile ) ) continue;
            if ( ! empty( $profile['role'] ) ) $roles[] = $profile['role'];
            $roles = array_merge( $roles, (array) ( $profile['aliases'] ?? array() ) );
        }
    }
    $roles = array_values( array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) ) );
    return (bool) array_intersect( $roles, (array) $user->roles );
}

/** Compact status used by the administrative contract preview. */
function trb_release_bridge_contract_term_status( $user_id, $term = '' ) {
    $term = '' !== (string) $term ? $term : get_user_meta( $user_id, '_trb_artist_contract_term', true );
    $dates = trb_release_bridge_contract_term_dates( $term );
    if ( is_wp_error( $dates ) ) return array( 'class' => 'missing', 'label' => 'Periodo non impostato' );
    if ( ! $dates['end'] instanceof DateTimeImmutable ) return array( 'class' => 'infinite', 'label' => 'Senza scadenza' );

    $today = new DateTimeImmutable( 'today', new DateTimeZone( 'Europe/Rome' ) );
    if ( $today > $dates['end'] ) {
        $user = get_userdata( $user_id );
        $transition_roles = trb_release_bridge_transition_roles();
        if ( $user instanceof WP_User && in_array( $transition_roles['target'], (array) $user->roles, true ) && get_user_meta( $user_id, '_trb_artist_group_transitioned_at', true ) ) {
            return array( 'class' => 'transitioned', 'label' => 'Passato a TRB' );
        }
        return array( 'class' => 'expired', 'label' => 'Scaduto' );
    }
    return array( 'class' => 'active', 'label' => 'Attivo' );
}

/** Add one compact column instead of widening the user table with two fields. */
function trb_release_bridge_add_user_contract_column( $columns ) {
    if ( ! current_user_can( 'manage_options' ) ) return $columns;
    $columns['trb_artist_contract'] = 'Contratto artista';
    return $columns;
}
add_filter( 'manage_users_columns', 'trb_release_bridge_add_user_contract_column', 50 );

function trb_release_bridge_render_user_contract_column( $output, $column_name, $user_id ) {
    if ( 'trb_artist_contract' !== $column_name || ! current_user_can( 'manage_options' ) ) return $output;
    $user = get_userdata( $user_id );
    if ( ! trb_release_bridge_is_managed_artist( $user ) ) return '<span aria-hidden="true">—</span>';

    $preliminary = (string) get_user_meta( $user_id, '_trb_artist_preliminary_contract', true );
    $term = (string) get_user_meta( $user_id, '_trb_artist_contract_term', true );
    $status = trb_release_bridge_contract_term_status( $user_id, $term );
    ob_start();
    ?>
    <div class="trb-contract-cell" data-trb-contract-cell data-user-id="<?php echo esc_attr( $user_id ); ?>">
        <div class="trb-contract-cell__preview" data-trb-contract-preview>
            <span><b>Preliminare:</b> <em data-trb-preliminary-preview><?php echo esc_html( $preliminary ?: 'Non impostato' ); ?></em></span>
            <span><b>Periodo:</b> <em data-trb-term-preview><?php echo esc_html( $term ?: 'Non impostato' ); ?></em></span>
            <span class="trb-contract-badge trb-contract-badge--<?php echo esc_attr( $status['class'] ); ?>" data-trb-contract-status><?php echo esc_html( $status['label'] ); ?></span>
            <button type="button" class="button-link" data-trb-contract-edit>Modifica rapida</button>
        </div>
        <div class="trb-contract-cell__editor" data-trb-contract-editor hidden>
            <label><span>Contratto preliminare</span><input type="text" maxlength="120" value="<?php echo esc_attr( $preliminary ); ?>" data-trb-preliminary-input></label>
            <small><?php echo esc_html( trb_release_bridge_expected_contract_label( $user ) ); ?></small>
            <label><span>Data di attuazione/scadenza</span><input type="text" maxlength="50" placeholder="08/08/26 - 08/08/27" value="<?php echo esc_attr( $term ); ?>" data-trb-term-input></label>
            <small>GG/MM/AA - GG/MM/AA oppure GG/MM/AA - INFINITO</small>
            <div class="trb-contract-cell__actions"><button type="button" class="button button-primary" data-trb-contract-save>Salva</button><button type="button" class="button" data-trb-contract-cancel>Annulla</button></div>
            <p class="trb-contract-cell__message" data-trb-contract-message aria-live="polite"></p>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
add_filter( 'manage_users_custom_column', 'trb_release_bridge_render_user_contract_column', 10, 3 );

/** Validate and persist the two administrative fields from the Users screen. */
function trb_release_bridge_quick_update_contract() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Non autorizzato.' ), 403 );
    check_ajax_referer( 'trb_release_bridge_quick_contract', 'nonce' );

    $user_id = absint( $_POST['user_id'] ?? 0 );
    $user = $user_id ? get_userdata( $user_id ) : false;
    if ( ! $user instanceof WP_User || ! current_user_can( 'edit_user', $user_id ) || ! trb_release_bridge_is_managed_artist( $user ) ) {
        wp_send_json_error( array( 'message' => 'Profilo artista non valido.' ), 400 );
    }

    $preliminary = sanitize_text_field( wp_unslash( $_POST['preliminary'] ?? '' ) );
    $term = trb_release_bridge_normalize_contract_term( $_POST['term'] ?? '' );
    if ( function_exists( 'mb_substr' ) ) $preliminary = mb_substr( $preliminary, 0, 120 );
    else $preliminary = substr( $preliminary, 0, 120 );
	$valid_contract = trb_release_bridge_validate_preliminary_contract( $user, $preliminary );
	if ( is_wp_error( $valid_contract ) ) wp_send_json_error( array( 'message' => $valid_contract->get_error_message() ), 422 );
    if ( '' !== $term ) {
        $dates = trb_release_bridge_contract_term_dates( $term );
        if ( is_wp_error( $dates ) ) wp_send_json_error( array( 'message' => $dates->get_error_message() ), 422 );
    }

    if ( '' === $preliminary ) delete_user_meta( $user_id, '_trb_artist_preliminary_contract' );
    else update_user_meta( $user_id, '_trb_artist_preliminary_contract', $preliminary );
    if ( '' === $term ) delete_user_meta( $user_id, '_trb_artist_contract_term' );
    else update_user_meta( $user_id, '_trb_artist_contract_term', $term );

    $transitioned = trb_release_bridge_maybe_transition_ddb_trb_user( $user_id, null, 'quick_edit' );
    $access_expired = trb_release_bridge_is_contract_access_expired( $user_id );
    if ( $access_expired ) {
        trb_release_bridge_maybe_expire_contract_access( $user_id, null, 'quick_edit' );
    } else {
        delete_user_meta( $user_id, '_trb_artist_contract_access_expired_at' );
        delete_user_meta( $user_id, '_trb_artist_contract_access_expired_source' );
        delete_user_meta( $user_id, '_trb_artist_contract_access_expired_term' );
    }

    $status = trb_release_bridge_contract_term_status( $user_id, $term );
    wp_send_json_success( array(
        'preliminary' => $preliminary ?: 'Non impostato',
        'term'        => $term ?: 'Non impostato',
        'status'      => $status,
        'reload'      => (bool) ( $transitioned || $access_expired ),
        'message'     => 'Dati contrattuali aggiornati.',
    ) );
}
add_action( 'wp_ajax_trb_release_bridge_quick_update_contract', 'trb_release_bridge_quick_update_contract' );

/** Styles and behavior are loaded only on Users > All Users. */
function trb_release_bridge_user_contract_column_assets( $hook ) {
    if ( 'users.php' !== $hook || ! current_user_can( 'manage_options' ) ) return;
    $nonce = wp_create_nonce( 'trb_release_bridge_quick_contract' );
    ?>
    <style>
        .column-trb_artist_contract{width:290px}.trb-contract-cell__preview{display:grid;gap:3px}.trb-contract-cell__preview>span:not(.trb-contract-badge){overflow-wrap:anywhere}.trb-contract-cell em{font-style:normal}.trb-contract-badge{display:inline-flex;width:max-content;padding:2px 7px;border-radius:999px;font-size:11px;font-weight:700}.trb-contract-badge--active{background:#dff3e4;color:#176b2c}.trb-contract-badge--infinite,.trb-contract-badge--transitioned{background:#e3eefb;color:#135b98}.trb-contract-badge--expired{background:#fde2e2;color:#a42626}.trb-contract-badge--missing{background:#f0f0f1;color:#50575e}.trb-contract-cell__editor{display:grid;gap:7px;padding:10px;border:1px solid #c3c4c7;border-radius:6px;background:#fff}.trb-contract-cell__editor label{display:grid;gap:3px;font-weight:600}.trb-contract-cell__editor input{width:100%}.trb-contract-cell__editor small{color:#646970}.trb-contract-cell__actions{display:flex;gap:6px}.trb-contract-cell__message{margin:0;font-weight:600}.trb-contract-cell__message.is-error{color:#b32d2e}.trb-contract-cell__message.is-success{color:#008a20}@media(max-width:782px){.column-trb_artist_contract{width:auto}.trb-contract-cell{max-width:420px}}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        var nonce=<?php echo wp_json_encode( $nonce ); ?>;
        document.addEventListener('click',function(event){
            var cell=event.target.closest('[data-trb-contract-cell]'); if(!cell)return;
            var editor=cell.querySelector('[data-trb-contract-editor]'),preview=cell.querySelector('[data-trb-contract-preview]'),message=cell.querySelector('[data-trb-contract-message]');
            if(event.target.matches('[data-trb-contract-edit]')){editor.hidden=false;preview.hidden=true;message.textContent='';return;}
            if(event.target.matches('[data-trb-contract-cancel]')){editor.hidden=true;preview.hidden=false;message.textContent='';return;}
            if(!event.target.matches('[data-trb-contract-save]'))return;
            var save=event.target; save.disabled=true; message.className='trb-contract-cell__message'; message.textContent='Salvataggio…';
            var body=new URLSearchParams({action:'trb_release_bridge_quick_update_contract',nonce:nonce,user_id:cell.dataset.userId,preliminary:cell.querySelector('[data-trb-preliminary-input]').value,term:cell.querySelector('[data-trb-term-input]').value});
            fetch(ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(response){return response.json();}).then(function(result){
                if(!result.success)throw new Error(result.data&&result.data.message?result.data.message:'Salvataggio non riuscito.');
                cell.querySelector('[data-trb-preliminary-preview]').textContent=result.data.preliminary;cell.querySelector('[data-trb-term-preview]').textContent=result.data.term;
                var badge=cell.querySelector('[data-trb-contract-status]');badge.textContent=result.data.status.label;badge.className='trb-contract-badge trb-contract-badge--'+result.data.status.class;
                message.classList.add('is-success');message.textContent=result.data.message;setTimeout(function(){if(result.data.reload){window.location.reload();return;}editor.hidden=true;preview.hidden=false;message.textContent='';},650);
            }).catch(function(error){message.classList.add('is-error');message.textContent=error.message;}).finally(function(){save.disabled=false;});
        });
    });
    </script>
    <?php
}
add_action( 'admin_enqueue_scripts', 'trb_release_bridge_user_contract_column_assets' );

function trb_release_bridge_capture_isrc() {
    if ( ! is_user_logged_in() ) return;
    if ( empty( $_POST['trb_portal_release_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trb_portal_release_nonce'] ) ), 'trb_portal_start_release' ) ) return;
    $state = sanitize_key( wp_unslash( $_POST['trb_release_state'] ?? '' ) );
    $codes = array();
    if ( 'previously_released' === $state ) {
        foreach ( (array) wp_unslash( $_POST['trb_existing_isrc'] ?? array() ) as $index => $value ) {
            $value = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $value ) );
            if ( preg_match( '/^[A-Z]{2}[A-Z0-9]{3}[0-9]{7}$/', $value ) ) $codes[ absint( $index ) ] = $value;
        }
    }
    set_transient( 'trb_release_bridge_isrc_' . get_current_user_id(), $codes, 2 * HOUR_IN_SECONDS );
}
add_action( 'admin_post_trb_portal_start_release', 'trb_release_bridge_capture_isrc', 1 );
add_action( 'wp_ajax_trb_portal_start_release', 'trb_release_bridge_capture_isrc', 1 );

function trb_release_bridge_meta_written( $meta_id, $post_id, $meta_key, $meta_value ) {
    if ( '_trb_release_files' !== $meta_key || 'trb_release' !== get_post_type( $post_id ) ) return;
    if ( ! get_post_meta( $post_id, '_trb_contract_state', true ) ) update_post_meta( $post_id, '_trb_contract_state', 'waiting_analysis' );
}
add_action( 'added_post_meta', 'trb_release_bridge_meta_written', 10, 4 );
add_action( 'updated_post_meta', 'trb_release_bridge_meta_written', 10, 4 );

function trb_release_bridge_queue_dispatch( $release_id ) {
	$release_id = absint( $release_id );
	if ( 'trb_release' !== get_post_type( $release_id ) || 'approved' !== get_post_meta( $release_id, '_trb_release_pipeline_status', true ) ) return;
	if ( function_exists( 'trb_portal_release_is_qa' ) && trb_portal_release_is_qa( $release_id ) ) {
		update_post_meta( $release_id, '_trb_contract_state', 'qa_simulated' );
		update_post_meta( $release_id, '_trb_contract_qa_simulated_at', current_time( 'mysql', true ) );
		delete_post_meta( $release_id, '_trb_contract_error' );
		return;
	}
	$current = get_post_meta( $release_id, '_trb_contract_state', true );
    if ( in_array( $current, array( 'contract_sent', 'signed' ), true ) ) return;
    update_post_meta( $release_id, '_trb_contract_state', 'preparing' );
    if ( ! wp_next_scheduled( 'trb_release_bridge_dispatch', array( $release_id ) ) ) wp_schedule_single_event( time() + 10, 'trb_release_bridge_dispatch', array( $release_id ) );
}
add_action( 'trb_release_analysis_approved', 'trb_release_bridge_queue_dispatch', 10, 1 );

/** Keep contract handoff diagnostics visible on every release admin screen. */
function trb_release_bridge_add_meta_box() {
    add_meta_box( 'trb-release-contract-diagnostics', 'Stato contratto e spreadsheet', 'trb_release_bridge_render_meta_box', 'trb_release', 'normal', 'high' );
}
add_action( 'add_meta_boxes_trb_release', 'trb_release_bridge_add_meta_box' );

function trb_release_bridge_render_meta_box( $post ) {
	$technical = (array) get_post_meta( $post->ID, '_trb_release_technical_analysis', true );
	$decision  = (array) get_post_meta( $post->ID, '_trb_release_analysis_decision', true );
	$technical_summary = isset( $technical['status'] ) ? (string) $technical['status'] : '';
	if ( ! empty( $technical['errors'] ) ) $technical_summary .= ' · errori: ' . implode( ', ', array_map( 'sanitize_text_field', (array) $technical['errors'] ) );
	if ( ! empty( $technical['warnings'] ) ) $technical_summary .= ' · avvisi: ' . implode( ', ', array_map( 'sanitize_text_field', (array) $technical['warnings'] ) );
	$decision_summary = isset( $decision['semaphore'] ) ? (string) $decision['semaphore'] : '';
	if ( ! empty( $decision['state'] ) ) $decision_summary .= ' · ' . (string) $decision['state'];
	$limitations = array();
	foreach ( (array) ( $decision['limitations'] ?? array() ) as $track => $codes ) foreach ( (array) $codes as $code ) $limitations[] = 'brano ' . ( absint( $track ) + 1 ) . ': ' . sanitize_text_field( $code );
    $sheet_error = (string) get_post_meta( $post->ID, '_trb_contract_spreadsheet_error', true );
    $sheet_synced_at = (string) get_post_meta( $post->ID, '_trb_contract_spreadsheet_synced_at', true );
    $sheet_status = $sheet_error ?: ( $sheet_synced_at ? 'Completata il ' . $sheet_synced_at : ( 'signed' === get_post_meta( $post->ID, '_trb_contract_state', true ) ? 'Da sincronizzare' : '—' ) );
    $rows = array(
        'Pipeline release'  => get_post_meta( $post->ID, '_trb_release_pipeline_status', true ),
		'Analisi tecnica'   => $technical_summary,
		'Decisione audio'   => $decision_summary,
		'Limiti analisi'    => implode( ', ', $limitations ),
        'Stato contratto'   => get_post_meta( $post->ID, '_trb_contract_state', true ),
        'Errore contratto'  => get_post_meta( $post->ID, '_trb_contract_error', true ),
        'Numero contratto'  => get_post_meta( $post->ID, '_trb_contract_number', true ),
        'Dossier OTP'       => get_post_meta( $post->ID, '_trb_otp_dossier_id', true ),
        'Contratto inviato' => get_post_meta( $post->ID, '_trb_contract_sent_at', true ),
        'Contratto firmato' => get_post_meta( $post->ID, '_trb_contract_signed_at', true ),
        'Sincronizzazione foglio' => $sheet_status,
    );
    echo '<table class="widefat striped"><tbody>';
    foreach ( $rows as $label => $value ) echo '<tr><th style="width:190px">' . esc_html( $label ) . '</th><td>' . esc_html( '' !== (string) $value ? $value : '—' ) . '</td></tr>';
    echo '</tbody></table>';
	if ( current_user_can( 'manage_options' ) && function_exists( 'trb_resource_tables' ) ) {
		global $wpdb;
		$usage_table = trb_resource_tables()['usage'];
		$usage_rows = $wpdb->get_results( $wpdb->prepare( "SELECT service,track_index,status,attempts,last_error,provider_reference,payload,updated_at FROM $usage_table WHERE release_id=%d ORDER BY id", $post->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $usage_rows ) {
			echo '<details style="margin-top:12px"><summary><strong>Diagnostica analisi audio</strong></summary><table class="widefat striped" style="margin-top:8px"><thead><tr><th>Servizio</th><th>Brano</th><th>Stato</th><th>Tentativi</th><th>Errore</th><th>Riferimento</th><th>Aggiornato</th></tr></thead><tbody>';
			foreach ( $usage_rows as $usage ) echo '<tr><td>' . esc_html( $usage['service'] ) . '</td><td>' . esc_html( absint( $usage['track_index'] ) + 1 ) . '</td><td>' . esc_html( $usage['status'] ) . '</td><td>' . esc_html( absint( $usage['attempts'] ) ) . '</td><td>' . esc_html( $usage['last_error'] ?: '—' ) . '</td><td>' . esc_html( $usage['provider_reference'] ?: '—' ) . '</td><td>' . esc_html( $usage['updated_at'] ) . '</td></tr>';
			echo '</tbody></table><details style="margin-top:8px"><summary>Risultati grezzi del provider</summary><pre style="max-height:420px;overflow:auto;white-space:pre-wrap">' . esc_html( wp_json_encode( $usage_rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre></details></details>';
			$has_acr_error = false;
			foreach ( $usage_rows as $usage ) if ( 'fingerprinting' === $usage['service'] && 'error' === $usage['status'] ) { $has_acr_error = true; break; }
			if ( $has_acr_error ) echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_release_analysis_retry&release_id=' . absint( $post->ID ) ), 'trb_release_analysis_retry_' . absint( $post->ID ) ) ) . '">Rielabora risposta ACR senza nuovo caricamento</a></p>';
		}
	}
    if ( 'approved' === get_post_meta( $post->ID, '_trb_release_pipeline_status', true ) && ! in_array( get_post_meta( $post->ID, '_trb_contract_state', true ), array( 'contract_sent', 'signed' ), true ) ) {
        echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_release_bridge_retry&release_id=' . absint( $post->ID ) ), 'trb_release_bridge_retry_' . absint( $post->ID ) ) ) . '">Riprova invio contratto</a></p>';
    }
    if ( current_user_can( 'manage_options' ) && 'contract_sent' === get_post_meta( $post->ID, '_trb_contract_state', true ) && get_post_meta( $post->ID, '_trb_otp_dossier_id', true ) ) {
        echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_release_bridge_confirm_signed&release_id=' . absint( $post->ID ) ), 'trb_release_bridge_confirm_signed_' . absint( $post->ID ) ) ) . '" onclick="return confirm(\'Usa questo recupero soltanto dopo aver verificato su OTPService che il dossier risulta completato e che il PDF firmato e stato archiviato. Confermi?\');">Firma verificata: sincronizza la pratica</a></p>';
    }
    if ( current_user_can( 'manage_options' ) && 'signed' === get_post_meta( $post->ID, '_trb_contract_state', true ) ) {
        echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_release_bridge_sync_spreadsheet&release_id=' . absint( $post->ID ) ), 'trb_release_bridge_sync_spreadsheet_' . absint( $post->ID ) ) ) . '">Sincronizza riga verde nel foglio</a></p>';
    }
}

function trb_release_analysis_retry_dispatch() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Non autorizzato.' );
	$release_id = isset( $_GET['release_id'] ) ? absint( $_GET['release_id'] ) : 0;
	check_admin_referer( 'trb_release_analysis_retry_' . $release_id );
	if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) || ! function_exists( 'trb_resource_start_release_analysis' ) ) wp_die( 'Pratica non valida.' );
	update_post_meta( $release_id, '_trb_release_pipeline_status', 'analysis_in_progress' );
	trb_resource_start_release_analysis( $release_id );
	wp_safe_redirect( get_edit_post_link( $release_id, 'url' ) );
	exit;
}
add_action( 'admin_post_trb_release_analysis_retry', 'trb_release_analysis_retry_dispatch' );

function trb_release_bridge_retry_dispatch() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Non autorizzato.' );
    $release_id = isset( $_GET['release_id'] ) ? absint( $_GET['release_id'] ) : 0;
    check_admin_referer( 'trb_release_bridge_retry_' . $release_id );
    if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) ) wp_die( 'Pratica non valida.' );
    update_post_meta( $release_id, '_trb_contract_state', 'preparing' );
    delete_post_meta( $release_id, '_trb_contract_error' );
    trb_release_bridge_dispatch( $release_id );
    wp_safe_redirect( get_edit_post_link( $release_id, 'url' ) );
    exit;
}
add_action( 'admin_post_trb_release_bridge_retry', 'trb_release_bridge_retry_dispatch' );

/** Administrative fallback for dossiers completed by OTPService without a delivered callback. */
function trb_release_bridge_confirm_signed_dispatch() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Non autorizzato.' );
    $release_id = isset( $_GET['release_id'] ) ? absint( $_GET['release_id'] ) : 0;
    check_admin_referer( 'trb_release_bridge_confirm_signed_' . $release_id );
    if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) ) wp_die( 'Pratica non valida.' );
    $dossier_id = sanitize_text_field( (string) get_post_meta( $release_id, '_trb_otp_dossier_id', true ) );
    if ( ! $dossier_id ) wp_die( 'Dossier OTP mancante.' );
    $result = trb_release_bridge_apply_callback( array(
        'release_id' => $release_id,
        'dossier_id' => $dossier_id,
        'status'     => 'completed',
        'signed_at'  => gmdate( DATE_ATOM ),
    ) );
    if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
    wp_safe_redirect( add_query_arg( 'trb_contract_reconciled', '1', get_edit_post_link( $release_id, 'url' ) ) );
    exit;
}
add_action( 'admin_post_trb_release_bridge_confirm_signed', 'trb_release_bridge_confirm_signed_dispatch' );

/** Retry only the signed-row formatting without touching contract state. */
function trb_release_bridge_sync_spreadsheet_dispatch() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Non autorizzato.' );
    $release_id = isset( $_GET['release_id'] ) ? absint( $_GET['release_id'] ) : 0;
    check_admin_referer( 'trb_release_bridge_sync_spreadsheet_' . $release_id );
    if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) || 'signed' !== get_post_meta( $release_id, '_trb_contract_state', true ) ) wp_die( 'Pratica firmata non valida.' );
    $result = trb_release_bridge_notify_spreadsheet_signed( $release_id );
    $status = is_wp_error( $result ) ? 'error' : '1';
    wp_safe_redirect( add_query_arg( 'trb_contract_sheet_synced', $status, get_edit_post_link( $release_id, 'url' ) ) );
    exit;
}
add_action( 'admin_post_trb_release_bridge_sync_spreadsheet', 'trb_release_bridge_sync_spreadsheet_dispatch' );

function trb_release_bridge_reconciled_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! empty( $_GET['trb_contract_reconciled'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Firma sincronizzata: la pratica risulta ora contrattualmente completata.</p></div>';
    if ( ! empty( $_GET['trb_contract_sheet_synced'] ) ) {
        $ok = '1' === sanitize_key( wp_unslash( $_GET['trb_contract_sheet_synced'] ) );
        echo '<div class="notice ' . ( $ok ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>' . esc_html( $ok ? 'Riga del contratto sincronizzata in verde.' : 'Sincronizzazione del foglio non riuscita: consulta la diagnostica della pratica.' ) . '</p></div>';
    }
}
add_action( 'admin_notices', 'trb_release_bridge_reconciled_notice' );

function trb_release_bridge_profile_value( $user_id, $key, $fallback = '' ) {
    if ( function_exists( 'trb_portal_artist_profile_value' ) ) {
        $value = trb_portal_artist_profile_value( $key, $user_id );
        if ( '' !== (string) $value ) return $value;
    }
    $value = get_user_meta( $user_id, '_trb_artist_' . $key, true );
    return '' !== (string) $value ? $value : $fallback;
}

function trb_release_bridge_payload( $release_id ) {
    $post = get_post( $release_id );
    if ( ! $post || 'trb_release' !== $post->post_type ) return new WP_Error( 'release_missing' );
    $user = get_userdata( $post->post_author );
    if ( ! $user ) return new WP_Error( 'artist_missing' );
    $profile = trb_portal_user_profile( $user );
    $preliminary_contract = sanitize_text_field( (string) get_user_meta( $post->post_author, '_trb_artist_preliminary_contract', true ) );
    $contract_term = trb_release_bridge_normalize_contract_term( get_user_meta( $post->post_author, '_trb_artist_contract_term', true ) );
    $tracks  = (array) get_post_meta( $release_id, '_trb_release_tracks', true );
    $state   = (string) get_post_meta( $release_id, '_trb_release_state', true );
    if ( ! in_array( $profile, array( 'dds', 'ddb12', 'ddb', 'ddb_trb', 'trb' ), true ) ) return new WP_Error( 'profile_invalid', 'Profilo contrattuale non riconosciuto.' );
    if ( '' === $preliminary_contract ) return new WP_Error( 'preliminary_contract_missing', 'Contratto preliminare non assegnato nell’anagrafica amministrativa dell’artista.' );
    $contract_valid = trb_release_bridge_validate_preliminary_contract( $user, $preliminary_contract );
    if ( is_wp_error( $contract_valid ) ) return $contract_valid;
    if ( '' === $contract_term ) return new WP_Error( 'contract_term_missing', 'Data di attuazione/scadenza non assegnata nell’anagrafica amministrativa dell’artista.' );
    if ( 'unreleased' === $state ) {
        $missing_indexes = array();
        foreach ( $tracks as $index => $track ) {
            $code = isset( $track['isrc'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $track['isrc'] ) ) : '';
            if ( ! $code ) $missing_indexes[] = $index;
        }
        if ( $missing_indexes ) {
            $lock_key = '_trb_release_isrc_assignment_lock';
            $lock_time = absint( get_post_meta( $release_id, $lock_key, true ) );
            if ( $lock_time && time() - $lock_time > 10 * MINUTE_IN_SECONDS ) delete_post_meta( $release_id, $lock_key );
            if ( ! add_post_meta( $release_id, $lock_key, time(), true ) ) return new WP_Error( 'isrc_assignment_in_progress', 'L’assegnazione ISRC della pratica è già in corso.' );

            // Re-read after acquiring the lock: another dispatch may have
            // completed the assignment while this request was waiting.
            $tracks = (array) get_post_meta( $release_id, '_trb_release_tracks', true );
            $missing_indexes = array();
            foreach ( $tracks as $index => $track ) {
                $code = isset( $track['isrc'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $track['isrc'] ) ) : '';
                if ( ! $code ) $missing_indexes[] = $index;
            }
        }
        if ( $missing_indexes ) {
            $reserved = trb_release_bridge_allocate_isrcs( count( $missing_indexes ), $profile );
            if ( is_wp_error( $reserved ) || count( $reserved ) !== count( $missing_indexes ) ) {
                delete_post_meta( $release_id, $lock_key );
                return new WP_Error( 'isrc_assignment_failed', 'Il sistema non è riuscito ad assegnare gli ISRC mancanti.' );
            }
            foreach ( $missing_indexes as $position => $track_index ) $tracks[ $track_index ]['isrc'] = $reserved[ $position ];
            if ( false === update_post_meta( $release_id, '_trb_release_tracks', $tracks ) ) {
                delete_post_meta( $release_id, $lock_key );
                return new WP_Error( 'isrc_assignment_failed', 'Il sistema non è riuscito a salvare gli ISRC assegnati.' );
            }
            update_post_meta( $release_id, '_trb_release_isrc_allocation', array( 'pool' => 'trb' === $profile ? 'trb' : 'distribution', 'year' => wp_date( 'y' ), 'codes' => $reserved, 'assigned_at' => time(), 'source' => 'contract_backfill' ) );
        }
        if ( isset( $lock_key ) ) delete_post_meta( $release_id, $lock_key );
    }
    if ( in_array( $state, array( 'previously_released', 'unreleased' ), true ) ) {
        $codes = (array) get_transient( 'trb_release_bridge_isrc_' . $post->post_author );
        delete_transient( 'trb_release_bridge_isrc_' . $post->post_author );
        $seen = array();
        foreach ( $tracks as $index => &$track ) {
            $code = isset( $track['isrc'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $track['isrc'] ) ) : '';
            if ( 'previously_released' === $state && ! $code ) $code = $codes[ $index ] ?? ''; // Compatibility with practices created before persistent ISRC storage.
            if ( ! $code || isset( $seen[ $code ] ) ) return new WP_Error( 'invalid_isrc', 'ISRC mancante o duplicato.' );
            $track['isrc'] = $code;
            $seen[ $code ] = true;
        }
        unset( $track );
        update_post_meta( $release_id, '_trb_release_tracks', $tracks );
    }
    $files = array();
    foreach ( (array) get_post_meta( $release_id, '_trb_release_files', true ) as $file ) {
        if ( ! is_array( $file ) ) continue;
        $files[] = array('kind'=>sanitize_key($file['kind']??''),'name'=>sanitize_file_name($file['name']??''),'track'=>isset($file['track'])?absint($file['track']):null);
    }
    $artist = array(
        'user_id'=>(int)$post->post_author,'name'=>$user->first_name,'surname'=>$user->last_name,'email'=>$user->user_email,
        'phone'=>trb_release_bridge_profile_value($post->post_author,'phone'),'artist_name'=>trb_release_bridge_profile_value($post->post_author,'artist_name'),
        'tax_code'=>trb_release_bridge_profile_value($post->post_author,'tax_code'),'birth_date'=>trb_release_bridge_profile_value($post->post_author,'birth_date'),
        'birth_place'=>trb_release_bridge_profile_value($post->post_author,'birth_place'),'birth_province'=>trb_release_bridge_profile_value($post->post_author,'birth_province'),
        'document_type'=>trb_release_bridge_profile_value($post->post_author,'document_type','Carta d’identità'),
        'document_number'=>trb_release_bridge_profile_value($post->post_author,'document_number'),'address'=>trb_release_bridge_profile_value($post->post_author,'street'),
        'street_number'=>trb_release_bridge_profile_value($post->post_author,'street_number'),'postcode'=>trb_release_bridge_profile_value($post->post_author,'postal_code'),
        'municipality'=>trb_release_bridge_profile_value($post->post_author,'city'),'province'=>trb_release_bridge_profile_value($post->post_author,'province'),
        'country'=>trb_release_bridge_profile_value($post->post_author,'country','Italia'),
    );
    foreach ( array( 'name','surname','email','phone','artist_name','tax_code','birth_date','birth_place','birth_province','document_type','document_number','address','street_number','postcode','municipality','province','country' ) as $required ) {
        if ( '' === trim( (string) $artist[ $required ] ) ) return new WP_Error( 'artist_data_missing', 'Dato contrattuale mancante: ' . $required . '.' );
    }
    if ( empty( $tracks ) ) return new WP_Error( 'tracks_missing', 'Nessun brano disponibile per il contratto.' );
    return array('action'=>'portal_release','release_id'=>(int)$release_id,'profile'=>$profile,'preliminary_contract'=>$preliminary_contract,'contract_term'=>$contract_term,'title'=>$post->post_title,
        'release_type'=>(string)get_post_meta($release_id,'_trb_release_type',true),'release_state'=>$state,
        'release_date'=>(string)get_post_meta($release_id,'_trb_release_date',true),
        'original_date'=>(string)get_post_meta($release_id,'_trb_release_original_date',true),'confirmed_at'=>get_post_time(DATE_ATOM,true,$post),
        'confirmation_accepted'=>true,'artist'=>$artist,'tracks'=>$tracks,'files'=>$files,'portal_callback_url'=>trb_release_bridge_callback_url());
}

/**
 * Build the same positional row consumed by the existing TRB/DDB contract
 * factories. Those webapps receive a batch of spreadsheet rows, not a batch
 * of rich portal objects. Keep the rich payload at the top level for the
 * callback and future integrations, while rows remains a real 2-D array.
 */
function trb_release_bridge_spreadsheet_row( $payload ) {
    $artist = isset( $payload['artist'] ) && is_array( $payload['artist'] ) ? $payload['artist'] : array();
    $tracks = isset( $payload['tracks'] ) && is_array( $payload['tracks'] ) ? $payload['tracks'] : array();
    $format_date = static function ( $value ) {
        $value = (string) $value;
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) return $value;
        return $parts[3] . '/' . $parts[2] . '/' . $parts[1];
    };
    $credit_names = static function ( $entries ) {
        if ( ! is_array( $entries ) ) return '';
        return implode( "\n", array_filter( array_map( static function ( $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['name'] ) ) return '';
            $suffix = ! empty( $entry['role'] ) ? ' — ' . $entry['role'] : '';
            return $entry['name'] . $suffix;
        }, $entries ) ) );
    };

    $row = array(
        wp_date( 'd/m/Y H:i', strtotime( (string) ( $payload['confirmed_at'] ?? 'now' ) ) ),
        '', '', '', '',
        (string) ( $artist['name'] ?? '' ),
        (string) ( $artist['surname'] ?? '' ),
        $format_date( $artist['birth_date'] ?? '' ),
        (string) ( $artist['birth_place'] ?? '' ),
        (string) ( $artist['tax_code'] ?? '' ),
        (string) ( $artist['document_type'] ?? '' ),
        (string) ( $artist['document_number'] ?? '' ),
        (string) ( $artist['address'] ?? '' ),
        (string) ( $artist['street_number'] ?? '' ),
        (string) ( $artist['postcode'] ?? '' ),
        (string) ( $artist['municipality'] ?? '' ),
        (string) ( $artist['province'] ?? '' ),
        (string) ( $artist['country'] ?? '' ),
        (string) ( $artist['phone'] ?? '' ),
        (string) ( $artist['email'] ?? '' ),
        (string) ( $artist['artist_name'] ?? '' ),
        (string) ( $payload['title'] ?? '' ),
        $format_date( $payload['release_date'] ?? '' ),
        $format_date( $payload['original_date'] ?? '' ),
        'previously_released' === ( $payload['release_state'] ?? '' ) ? 'Già pubblicata' : 'Inedita',
        count( $tracks ),
        isset( $tracks[0]['primary_genre'] ) ? (string) $tracks[0]['primary_genre'] : '',
    );

    foreach ( $tracks as $track ) {
        $track = is_array( $track ) ? $track : array();
        $credits = isset( $track['credits'] ) && is_array( $track['credits'] ) ? $track['credits'] : array();
        $row[] = (string) ( $track['title'] ?? '' );
        $row[] = (string) ( $track['version'] ?? '' );
        $row[] = (string) ( $track['featuring'] ?? '' );
        $row[] = (string) ( $credits['authors'] ?? '' );
        $row[] = (string) ( $credits['composers'] ?? '' );
        $row[] = (string) ( $track['duration'] ?? '' );
        $row[] = (string) ( $track['advisory'] ?? '' );
        $row[] = (string) ( $track['primary_genre'] ?? '' );
        $row[] = (string) ( $track['secondary_genre'] ?? '' );
        $row[] = (string) ( $track['audio_status'] ?? '' );
        $row[] = (string) ( $track['isrc'] ?? '' );
        $row[] = $credit_names( $credits['credits'] ?? array() );
        $row[] = (string) ( $track['content_nature'] ?? '' );
        $row[] = (string) ( $track['rights_basis'] ?? '' );
        $row[] = (string) ( $track['rights_reference'] ?? '' );
        $row[] = (string) ( $track['rights_document'] ?? '' );
    }
    return $row;
}

/**
 * The contract webapps process a JSON batch (`JSON.parse(...).map(...)`).
 * Authentication is read from the Apps Script query parameters. Sending the
 * rich release object as the JSON root made the factory call `.map()` on an
 * object, which is the exact `rows.map is not a function` failure returned by
 * both deployments.
 */
function trb_release_bridge_post_webapp( $url, $payload, $secret ) {
    $request_url = add_query_arg( 'secret', (string) $secret, $url );
    $response = wp_remote_post( $request_url, array(
        'timeout'     => 300,
        'redirection' => 0,
        'headers'     => array( 'Content-Type' => 'application/json' ),
        'body'        => wp_json_encode( array( $payload ) ),
    ) );
    if ( is_wp_error( $response ) ) return $response;
    $code = wp_remote_retrieve_response_code( $response );
    if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
        $location = wp_remote_retrieve_header( $response, 'location' );
        $host = $location ? strtolower( (string) wp_parse_url( $location, PHP_URL_HOST ) ) : '';
        if ( ! $location || ( 'script.googleusercontent.com' !== $host && ! str_ends_with( $host, '.googleusercontent.com' ) ) ) {
            return new WP_Error( 'invalid_contract_redirect', 'Redirect Apps Script non valido.' );
        }
        $response = wp_remote_get( $location, array( 'timeout' => 300, 'redirection' => 2 ) );
    }
    return $response;
}

/** Mark the matching contract row as signed in the correct DDB/TRB spreadsheet. */
function trb_release_bridge_notify_spreadsheet_signed( $release_id ) {
    $release = get_post( $release_id );
    if ( ! $release || 'trb_release' !== $release->post_type ) return new WP_Error( 'release_missing', 'Release non trovata.' );
    $user = get_userdata( $release->post_author );
    $profile = $user && function_exists( 'trb_portal_user_profile' ) ? sanitize_key( trb_portal_user_profile( $user ) ) : '';
    if ( ! in_array( $profile, array( 'dds', 'ddb12', 'ddb', 'ddb_trb', 'trb' ), true ) ) {
        $error = new WP_Error( 'profile_invalid', 'Profilo contrattuale non riconosciuto per la sincronizzazione del foglio.' );
        update_post_meta( $release_id, '_trb_contract_spreadsheet_error', $error->get_error_message() );
        return $error;
    }

    $settings = trb_release_bridge_settings();
    $url = 'trb' === $profile ? $settings['trb_webapp_url'] : $settings['ddb_webapp_url'];
    if ( ! $url || ! $settings['shared_secret'] ) {
        $error = new WP_Error( 'spreadsheet_configuration_required', 'Collegamento al foglio non configurato.' );
        update_post_meta( $release_id, '_trb_contract_spreadsheet_error', $error->get_error_message() );
        return $error;
    }

    $payload = array(
        'action'          => 'portal_contract_signed',
        'release_id'      => absint( $release_id ),
        'profile'         => $profile,
        'contract_number' => sanitize_text_field( (string) get_post_meta( $release_id, '_trb_contract_number', true ) ),
        'dossier_id'      => sanitize_text_field( (string) get_post_meta( $release_id, '_trb_otp_dossier_id', true ) ),
        'secret'          => $settings['shared_secret'],
    );
    $response = trb_release_bridge_post_webapp( $url, $payload, $settings['shared_secret'] );
    if ( is_wp_error( $response ) ) {
        update_post_meta( $release_id, '_trb_contract_spreadsheet_error', $response->get_error_message() );
        return $response;
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( wp_remote_retrieve_response_code( $response ) >= 300 || empty( $body['success'] ) ) {
        $message = sanitize_text_field( (string) ( $body['error'] ?? wp_remote_retrieve_body( $response ) ) );
        $error = new WP_Error( 'spreadsheet_sync_failed', $message ?: 'Il foglio non ha confermato la sincronizzazione.' );
        update_post_meta( $release_id, '_trb_contract_spreadsheet_error', $error->get_error_message() );
        return $error;
    }
    delete_post_meta( $release_id, '_trb_contract_spreadsheet_error' );
    update_post_meta( $release_id, '_trb_contract_spreadsheet_synced_at', current_time( 'mysql', true ) );
    return true;
}

function trb_release_bridge_dispatch( $release_id ) {
	if ( function_exists( 'trb_portal_release_is_qa' ) && trb_portal_release_is_qa( $release_id ) ) {
		update_post_meta( $release_id, '_trb_contract_state', 'qa_simulated' );
		update_post_meta( $release_id, '_trb_contract_qa_simulated_at', current_time( 'mysql', true ) );
		delete_post_meta( $release_id, '_trb_contract_error' );
		return;
	}
    $current = get_post_meta( $release_id, '_trb_contract_state', true );
    if ( in_array( $current, array( 'contract_sent', 'signed' ), true ) ) return;
    if ( 'approved' !== get_post_meta( $release_id, '_trb_release_pipeline_status', true ) ) { update_post_meta($release_id,'_trb_contract_state','waiting_analysis'); return; }
    $payload = trb_release_bridge_payload( $release_id );
    if ( is_wp_error( $payload ) ) { update_post_meta($release_id,'_trb_contract_state','data_error'); update_post_meta($release_id,'_trb_contract_error',$payload->get_error_message()); return; }
    $s = trb_release_bridge_settings();
    $url = 'trb' === $payload['profile'] ? $s['trb_webapp_url'] : $s['ddb_webapp_url'];
    if ( ! $url || ! $s['shared_secret'] ) { update_post_meta($release_id,'_trb_contract_state','configuration_required'); return; }
    $payload['rows'] = array( trb_release_bridge_spreadsheet_row( $payload ) );
    $payload['secret'] = $s['shared_secret'];
    $response = trb_release_bridge_post_webapp( $url, $payload, $s['shared_secret'] );
    if ( is_wp_error( $response ) ) { update_post_meta($release_id,'_trb_contract_state','dispatch_error'); update_post_meta($release_id,'_trb_contract_error',$response->get_error_message()); wp_schedule_single_event(time()+15*MINUTE_IN_SECONDS,'trb_release_bridge_dispatch',array($release_id)); return; }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( wp_remote_retrieve_response_code( $response ) >= 300 || empty( $body['success'] ) ) { update_post_meta($release_id,'_trb_contract_state','dispatch_error'); update_post_meta($release_id,'_trb_contract_error',sanitize_text_field($body['error']??wp_remote_retrieve_body($response))); return; }
    update_post_meta($release_id,'_trb_contract_state','contract_sent'); update_post_meta($release_id,'_trb_contract_sent_at',current_time('mysql',true));
    if ( ! empty($body['contract_number']) ) update_post_meta($release_id,'_trb_contract_number',sanitize_text_field($body['contract_number']));
    if ( ! empty($body['dossier_id']) ) update_post_meta($release_id,'_trb_otp_dossier_id',sanitize_text_field($body['dossier_id']));
}
add_action( 'trb_release_bridge_dispatch', 'trb_release_bridge_dispatch', 10, 1 );

/**
 * Use admin-post for provider callbacks because this installation protects the
 * whole REST API with a login requirement before route permissions are checked.
 */
function trb_release_bridge_callback_url() {
    return admin_url( 'admin-post.php?action=trb_release_contract_callback' );
}

/** Apply a verified contract callback once; repeated completed callbacks are harmless. */
function trb_release_bridge_apply_callback( $payload ) {
    $release_id = absint( $payload['release_id'] ?? 0 );
    if ( ! $release_id || 'trb_release' !== get_post_type( $release_id ) ) return new WP_Error( 'not_found', 'Release non trovata.', array( 'status' => 404 ) );

    $dossier_id = sanitize_text_field( (string) ( $payload['dossier_id'] ?? '' ) );
    if ( ! $dossier_id ) return new WP_Error( 'dossier_missing', 'Dossier OTP mancante.', array( 'status' => 400 ) );
    $stored_dossier = sanitize_text_field( (string) get_post_meta( $release_id, '_trb_otp_dossier_id', true ) );
    if ( $stored_dossier && ! hash_equals( $stored_dossier, $dossier_id ) ) return new WP_Error( 'dossier_mismatch', 'Il dossier OTP non corrisponde alla pratica.', array( 'status' => 409 ) );
    if ( ! $stored_dossier ) update_post_meta( $release_id, '_trb_otp_dossier_id', $dossier_id );

    $status = sanitize_key( (string) ( $payload['status'] ?? '' ) );
    if ( ! in_array( $status, array( 'completed', 'contract_sent' ), true ) ) return new WP_Error( 'status_invalid', 'Stato firma non valido.', array( 'status' => 400 ) );
    if ( 'completed' !== $status ) {
        if ( 'signed' !== get_post_meta( $release_id, '_trb_contract_state', true ) ) update_post_meta( $release_id, '_trb_contract_state', 'contract_sent' );
        return array( 'success' => true, 'state' => get_post_meta( $release_id, '_trb_contract_state', true ) );
    }

    $already_signed = 'signed' === get_post_meta( $release_id, '_trb_contract_state', true );
    if ( ! $already_signed ) {
        $signed_at = sanitize_text_field( (string) ( $payload['signed_at'] ?? '' ) );
        update_post_meta( $release_id, '_trb_contract_state', 'signed' );
        update_post_meta( $release_id, '_trb_contract_signed_at', $signed_at ?: gmdate( DATE_ATOM ) );
        delete_post_meta( $release_id, '_trb_contract_error' );
    }
    $spreadsheet_result = trb_release_bridge_notify_spreadsheet_signed( $release_id );
    return array(
        'success'      => true,
        'state'        => 'signed',
        'idempotent'   => $already_signed,
        'spreadsheet_synced' => ! is_wp_error( $spreadsheet_result ),
        'release_date' => (string) get_post_meta( $release_id, '_trb_release_date', true ),
    );
}

/** Public provider endpoint authenticated with the same shared bridge secret. */
function trb_release_bridge_public_callback() {
    if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
        status_header( 405 );
        wp_send_json_error( array( 'message' => 'Metodo non consentito.' ), 405 );
    }
    $raw = file_get_contents( 'php://input' );
    $payload = json_decode( (string) $raw, true );
    if ( ! is_array( $payload ) ) $payload = wp_unslash( $_POST );
    $settings = trb_release_bridge_settings();
    $header_secret = isset( $_SERVER['HTTP_X_TRB_PORTAL_SECRET'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TRB_PORTAL_SECRET'] ) ) : '';
    $secret = $header_secret ?: sanitize_text_field( (string) ( $payload['secret'] ?? '' ) );
    if ( ! $settings['shared_secret'] || ! $secret || ! hash_equals( (string) $settings['shared_secret'], $secret ) ) wp_send_json_error( array( 'message' => 'Secret non valido.' ), 403 );
    $result = trb_release_bridge_apply_callback( $payload );
    if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data()['status'] ?? 400 ) );
    wp_send_json_success( $result );
}
add_action( 'admin_post_nopriv_trb_release_contract_callback', 'trb_release_bridge_public_callback' );
add_action( 'admin_post_trb_release_contract_callback', 'trb_release_bridge_public_callback' );

function trb_release_bridge_rest_routes() {
    register_rest_route('trb/v1','/release-contract-status',array('methods'=>'GET','permission_callback'=>function(){return is_user_logged_in();},'callback'=>function(){
        $releases=get_posts(array('post_type'=>'trb_release','post_status'=>array('publish','private','pending','draft'),'author'=>get_current_user_id(),'numberposts'=>100)); $out=array();
        foreach($releases as $release){
            $summary=function_exists('trb_portal_release_status_summary')?trb_portal_release_status_summary($release->ID):array();
            $out[]=array_merge(array('release_id'=>$release->ID,'state'=>get_post_meta($release->ID,'_trb_contract_state',true)?:'waiting_analysis','pipeline_state'=>get_post_meta($release->ID,'_trb_release_pipeline_status',true),'state_label'=>function_exists('trb_portal_release_current_state_label')?trb_portal_release_current_state_label($release->ID):'Controllo del brano in corso','release_date'=>get_post_meta($release->ID,'_trb_release_date',true)),$summary);
        }
        return rest_ensure_response($out);
    }));
    register_rest_route('trb/v1','/release-contract-callback',array('methods'=>'POST','permission_callback'=>'__return_true','callback'=>function(WP_REST_Request $request){
        $s=trb_release_bridge_settings(); $secret=(string)($request->get_header('x-trb-portal-secret')?:$request->get_param('secret'));
        if(!$s['shared_secret']||!hash_equals($s['shared_secret'],$secret))return new WP_Error('forbidden','Secret non valido.',array('status'=>403));
        $result=trb_release_bridge_apply_callback($request->get_params());
        return is_wp_error($result)?$result:rest_ensure_response($result);
    }));
}
add_action('rest_api_init','trb_release_bridge_rest_routes');

function trb_release_bridge_enqueue() {
    $post=get_post(); if(!is_page()||!$post||!has_shortcode($post->post_content,'trb_artist_portal'))return;
    $path=get_template_directory().'/assets/js/trb-release-contract-status.js'; wp_enqueue_script('trb-release-contract-status',get_template_directory_uri().'/assets/js/trb-release-contract-status.js',array(),file_exists($path)?filemtime($path):null,true);
    wp_localize_script('trb-release-contract-status','trbReleaseContract',array('statusUrl'=>rest_url('trb/v1/release-contract-status'),'nonce'=>wp_create_nonce('wp_rest'),'created'=>isset($_GET['trb_release'])&&'created'===sanitize_key(wp_unslash($_GET['trb_release']))));
}
add_action('wp_enqueue_scripts','trb_release_bridge_enqueue',45);
