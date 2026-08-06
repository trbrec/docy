<?php
/**
 * Bridge between artist.trbrec.com and the existing DDB/TRB Apps Script factories.
 * Contract generation and OTPService remain exclusively inside Apps Script.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const TRB_RELEASE_BRIDGE_OPTION = 'trb_release_bridge_settings';

/**
 * Reserve a consecutive block of TRB ISRCs with one atomic database write.
 * The 2026 register already contains ITV242600001–ITV242600004, therefore the
 * first portal allocation starts at ITV242600005. Each following year starts
 * from 00001 and receives the current two-digit year in the ISRC itself.
 */
function trb_release_bridge_allocate_isrcs( $quantity ) {
    global $wpdb;

    $quantity = absint( $quantity );
    if ( $quantity < 1 || $quantity > 60 ) return new WP_Error( 'invalid_isrc_quantity', 'Numero di ISRC da assegnare non valido.' );

    $year = absint( wp_date( 'y' ) );
    $seed = 26 === $year ? 4 : 0;
    $option_name = 'trb_release_isrc_sequence_' . sprintf( '%02d', $year );
    $initial_end = $seed + $quantity;

    $sql = $wpdb->prepare(
        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
         VALUES (%s, LAST_INSERT_ID(%d), 'no')
         ON DUPLICATE KEY UPDATE option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + %d)",
        $option_name,
        $initial_end,
        $quantity
    );
    if ( false === $wpdb->query( $sql ) ) return new WP_Error( 'isrc_reservation_failed', 'Il sistema non è riuscito a riservare gli ISRC.' );

    $end = absint( $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ) );
    $start = $end - $quantity + 1;
    if ( $start < 1 || $end > 99999 ) return new WP_Error( 'isrc_sequence_exhausted', 'La sequenza ISRC disponibile non è valida o risulta esaurita.' );

    wp_cache_delete( $option_name, 'options' );
    $prefix = 'ITV24' . sprintf( '%02d', $year );
    $codes = array();
    for ( $sequence = $start; $sequence <= $end; $sequence++ ) {
        $codes[] = $prefix . sprintf( '%05d', $sequence );
    }
    return $codes;
}

function trb_release_bridge_settings() {
    $defaults = array('ddb_webapp_url'=>'','trb_webapp_url'=>'','shared_secret'=>'');
    $saved = get_option( TRB_RELEASE_BRIDGE_OPTION, array() );
    $saved = is_array( $saved ) ? $saved : array();
    if ( defined( 'TRB_DDB_WEBAPP_URL' ) && empty( $saved['ddb_webapp_url'] ) ) $saved['ddb_webapp_url'] = TRB_DDB_WEBAPP_URL;
    if ( defined( 'TRB_TRB_WEBAPP_URL' ) && empty( $saved['trb_webapp_url'] ) ) $saved['trb_webapp_url'] = TRB_TRB_WEBAPP_URL;
    if ( defined( 'TRB_RELEASE_BRIDGE_SECRET' ) && empty( $saved['shared_secret'] ) ) $saved['shared_secret'] = TRB_RELEASE_BRIDGE_SECRET;
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
    </tbody></table><p><button class="button button-primary" name="trb_release_bridge_save" value="1">Salva</button></p></form></div>
    <?php
}

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
    $rows = array(
        'Pipeline release'  => get_post_meta( $post->ID, '_trb_release_pipeline_status', true ),
        'Stato contratto'   => get_post_meta( $post->ID, '_trb_contract_state', true ),
        'Errore contratto'  => get_post_meta( $post->ID, '_trb_contract_error', true ),
        'Numero contratto'  => get_post_meta( $post->ID, '_trb_contract_number', true ),
        'Dossier OTP'       => get_post_meta( $post->ID, '_trb_otp_dossier_id', true ),
        'Contratto inviato' => get_post_meta( $post->ID, '_trb_contract_sent_at', true ),
    );
    echo '<table class="widefat striped"><tbody>';
    foreach ( $rows as $label => $value ) echo '<tr><th style="width:190px">' . esc_html( $label ) . '</th><td>' . esc_html( '' !== (string) $value ? $value : '—' ) . '</td></tr>';
    echo '</tbody></table>';
    if ( 'approved' === get_post_meta( $post->ID, '_trb_release_pipeline_status', true ) && ! in_array( get_post_meta( $post->ID, '_trb_contract_state', true ), array( 'contract_sent', 'signed' ), true ) ) {
        echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=trb_release_bridge_retry&release_id=' . absint( $post->ID ) ), 'trb_release_bridge_retry_' . absint( $post->ID ) ) ) . '">Riprova invio contratto</a></p>';
    }
}

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
    $tracks  = (array) get_post_meta( $release_id, '_trb_release_tracks', true );
    $state   = (string) get_post_meta( $release_id, '_trb_release_state', true );
    if ( 'previously_released' === $state || ( 'trb' === $profile && 'unreleased' === $state ) ) {
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
    foreach ( array( 'name','surname','email','phone','artist_name','tax_code','birth_date','birth_place','address','street_number','postcode','municipality','province','country' ) as $required ) {
        if ( '' === trim( (string) $artist[ $required ] ) ) return new WP_Error( 'artist_data_missing', 'Dato contrattuale mancante: ' . $required . '.' );
    }
    if ( ! in_array( $profile, array( 'dds', 'ddb12', 'ddb', 'ddb_trb', 'trb' ), true ) ) return new WP_Error( 'profile_invalid', 'Profilo contrattuale non riconosciuto.' );
    if ( empty( $tracks ) ) return new WP_Error( 'tracks_missing', 'Nessun brano disponibile per il contratto.' );
    return array('action'=>'portal_release','release_id'=>(int)$release_id,'profile'=>$profile,'title'=>$post->post_title,
        'release_type'=>(string)get_post_meta($release_id,'_trb_release_type',true),'release_state'=>$state,
        'release_date'=>(string)get_post_meta($release_id,'_trb_release_date',true),
        'original_date'=>(string)get_post_meta($release_id,'_trb_release_original_date',true),'confirmed_at'=>get_post_time(DATE_ATOM,true,$post),
        'confirmation_accepted'=>true,'artist'=>$artist,'tracks'=>$tracks,'files'=>$files,'portal_callback_url'=>rest_url('trb/v1/release-contract-callback'));
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

/** Apps Script returns a signed googleusercontent location after the POST. */
function trb_release_bridge_post_webapp( $url, $payload ) {
    $response = wp_remote_post( $url, array(
        'timeout'     => 120,
        'redirection' => 0,
        'headers'     => array( 'Content-Type' => 'application/json' ),
        'body'        => wp_json_encode( $payload ),
    ) );
    if ( is_wp_error( $response ) ) return $response;
    $code = wp_remote_retrieve_response_code( $response );
    if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
        $location = wp_remote_retrieve_header( $response, 'location' );
        $host = $location ? strtolower( (string) wp_parse_url( $location, PHP_URL_HOST ) ) : '';
        if ( ! $location || ( 'script.googleusercontent.com' !== $host && ! str_ends_with( $host, '.googleusercontent.com' ) ) ) {
            return new WP_Error( 'invalid_contract_redirect', 'Redirect Apps Script non valido.' );
        }
        $response = wp_remote_get( $location, array( 'timeout' => 120, 'redirection' => 2 ) );
    }
    return $response;
}

function trb_release_bridge_dispatch( $release_id ) {
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
    $response = trb_release_bridge_post_webapp( $url, $payload );
    if ( is_wp_error( $response ) ) { update_post_meta($release_id,'_trb_contract_state','dispatch_error'); update_post_meta($release_id,'_trb_contract_error',$response->get_error_message()); wp_schedule_single_event(time()+15*MINUTE_IN_SECONDS,'trb_release_bridge_dispatch',array($release_id)); return; }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( wp_remote_retrieve_response_code( $response ) >= 300 || empty( $body['success'] ) ) { update_post_meta($release_id,'_trb_contract_state','dispatch_error'); update_post_meta($release_id,'_trb_contract_error',sanitize_text_field($body['error']??wp_remote_retrieve_body($response))); return; }
    update_post_meta($release_id,'_trb_contract_state','contract_sent'); update_post_meta($release_id,'_trb_contract_sent_at',current_time('mysql',true));
    if ( ! empty($body['contract_number']) ) update_post_meta($release_id,'_trb_contract_number',sanitize_text_field($body['contract_number']));
    if ( ! empty($body['dossier_id']) ) update_post_meta($release_id,'_trb_otp_dossier_id',sanitize_text_field($body['dossier_id']));
}
add_action( 'trb_release_bridge_dispatch', 'trb_release_bridge_dispatch', 10, 1 );

function trb_release_bridge_rest_routes() {
    register_rest_route('trb/v1','/release-contract-status',array('methods'=>'GET','permission_callback'=>function(){return is_user_logged_in();},'callback'=>function(){
        $releases=get_posts(array('post_type'=>'trb_release','post_status'=>array('publish','private','pending','draft'),'author'=>get_current_user_id(),'numberposts'=>100)); $out=array();
        foreach($releases as $release)$out[]=array('release_id'=>$release->ID,'state'=>get_post_meta($release->ID,'_trb_contract_state',true)?:'waiting_analysis','pipeline_state'=>get_post_meta($release->ID,'_trb_release_pipeline_status',true),'state_label'=>function_exists('trb_portal_release_current_state_label')?trb_portal_release_current_state_label($release->ID):'Controllo del brano in corso','release_date'=>get_post_meta($release->ID,'_trb_release_date',true));
        return rest_ensure_response($out);
    }));
    register_rest_route('trb/v1','/release-contract-callback',array('methods'=>'POST','permission_callback'=>'__return_true','callback'=>function(WP_REST_Request $request){
        $s=trb_release_bridge_settings(); $secret=(string)($request->get_header('x-trb-portal-secret')?:$request->get_param('secret'));
        if(!$s['shared_secret']||!hash_equals($s['shared_secret'],$secret))return new WP_Error('forbidden','Secret non valido.',array('status'=>403));
        $release_id=absint($request->get_param('release_id')); if('trb_release'!==get_post_type($release_id))return new WP_Error('not_found','Release non trovata.',array('status'=>404));
        $status=sanitize_key($request->get_param('status')); if('completed'===$status){$signed_at=sanitize_text_field($request->get_param('signed_at')?:gmdate(DATE_ATOM));$release_date=(string)get_post_meta($release_id,'_trb_release_date',true);update_post_meta($release_id,'_trb_contract_state','signed');update_post_meta($release_id,'_trb_contract_signed_at',$signed_at);return rest_ensure_response(array('success'=>true,'release_date'=>$release_date));} update_post_meta($release_id,'_trb_contract_state',$status?:'contract_sent'); return rest_ensure_response(array('success'=>true));
    }));
}
add_action('rest_api_init','trb_release_bridge_rest_routes');

function trb_release_bridge_enqueue() {
    $post=get_post(); if(!is_page()||!$post||!has_shortcode($post->post_content,'trb_artist_portal'))return;
    $path=get_template_directory().'/assets/js/trb-release-contract-status.js'; wp_enqueue_script('trb-release-contract-status',get_template_directory_uri().'/assets/js/trb-release-contract-status.js',array(),file_exists($path)?filemtime($path):null,true);
    wp_localize_script('trb-release-contract-status','trbReleaseContract',array('statusUrl'=>rest_url('trb/v1/release-contract-status'),'nonce'=>wp_create_nonce('wp_rest'),'created'=>isset($_GET['trb_release'])&&'created'===sanitize_key(wp_unslash($_GET['trb_release']))));
}
add_action('wp_enqueue_scripts','trb_release_bridge_enqueue',45);
