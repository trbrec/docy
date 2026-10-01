<?php
/** New candidate workflow; existing accounts are outside this module's scope. */
if(!defined('ABSPATH'))exit;

function trb_onboarding_enabled(){return (bool)get_option('trb_candidate_onboarding_enabled',false);}
function trb_onboarding_crm($payload){
    $settings=trb_crm_connector_settings();$body=wp_json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$time=(string)time();$nonce=bin2hex(random_bytes(16));
    if(strlen($settings['secret'])<32)return new WP_Error('onboarding_config','Collegamento adesioni non disponibile.');
    $result=wp_remote_post('https://crm.trbrec.com/webhooks/artist-portal/onboarding',array('timeout'=>55,'redirection'=>0,'headers'=>array('Content-Type'=>'application/json','X-TRB-Timestamp'=>$time,'X-TRB-Nonce'=>$nonce,'X-TRB-Signature'=>'sha256='.hash_hmac('sha256','onboarding-crm-v1|'.$time.'|'.$nonce.'|'.$body,$settings['secret'])),'body'=>$body));
    if(is_wp_error($result))return new WP_Error('onboarding_connection','La connessione non è stata confermata. La pratica resta salvata: riprova tra poco.');
    $data=json_decode(wp_remote_retrieve_body($result),true);$status=wp_remote_retrieve_response_code($result);
    if($status<200||$status>=300||!is_array($data)||isset($data['error']))return new WP_Error('onboarding_remote',is_array($data)&&!empty($data['error'])?(string)$data['error']:'Operazione non confermata dal CRM.');
    return $data;
}
function trb_onboarding_private_permission($request){
    $secret=trb_crm_connector_settings()['secret'];$time=(string)$request->get_header('x-trb-timestamp');$nonce=(string)$request->get_header('x-trb-nonce');
    if(strlen($secret)<32||!ctype_digit($time)||abs(time()-(int)$time)>300||!preg_match('/^[a-f0-9]{32}$/D',$nonce))return new WP_Error('onboarding_auth','Richiesta non autorizzata.',array('status'=>403));
    $expected='sha256='.hash_hmac('sha256','onboarding-portal-v1|'.$time.'|'.$nonce.'|'.$request->get_body(),$secret);
    if(!hash_equals($expected,(string)$request->get_header('x-trb-signature')))return new WP_Error('onboarding_auth','Richiesta non autorizzata.',array('status'=>403));
    if(!add_option('trb_onboarding_rpc_nonce_'.$nonce,time()+600,'','no'))return new WP_Error('onboarding_replay','Richiesta già utilizzata.',array('status'=>409));
    if(!wp_next_scheduled('trb_onboarding_rpc_cleanup'))wp_schedule_single_event(time()+600,'trb_onboarding_rpc_cleanup');
    return true;
}
add_action('trb_onboarding_rpc_cleanup',static function(){global $wpdb;$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED)<%d",$wpdb->esc_like('trb_onboarding_rpc_nonce_').'%',time()));});
function trb_onboarding_store($action,$payload){
    $secret=trb_portal_dds_store_secret();if(strlen($secret)<32)return new WP_Error('onboarding_store_config','Collegamento versamenti non disponibile.');
    $path=$action==='store_order'?'order':'payment-status';unset($payload['action']);$body=wp_json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$time=(string)time();$nonce=bin2hex(random_bytes(16));
    $response=wp_remote_post('https://store.trbrec.com/wp-json/trb/v1/onboarding/'.$path,array('timeout'=>45,'redirection'=>0,'headers'=>array('Content-Type'=>'application/json','X-TRB-Timestamp'=>$time,'X-TRB-Nonce'=>$nonce,'X-TRB-Signature'=>'sha256='.hash_hmac('sha256','onboarding-v1|'.$time.'|'.$nonce.'|'.$body,$secret)),'body'=>$body));
    if(is_wp_error($response))return new WP_Error('onboarding_store','Il circuito non ha confermato l’operazione.');
    $data=json_decode(wp_remote_retrieve_body($response),true);if(wp_remote_retrieve_response_code($response)!==200||!is_array($data)||isset($data['code'],$data['message']))return new WP_Error('onboarding_store',is_array($data)&&isset($data['message'])?$data['message']:'Versamento da verificare.');
    return $data;
}
function trb_onboarding_private($request){
    $p=$request->get_json_params();$action=$p['action']??'';
    if($action==='health')return array('protocol'=>'onboarding-portal-v1','version'=>'2026.2','enabled'=>trb_onboarding_enabled(),'crm_configured'=>strlen(trb_crm_connector_settings()['secret'])>=32,'store_configured'=>strlen(trb_portal_dds_store_secret())>=32,'approval_plugin'=>function_exists('pw_new_user_approve'));
    if(!trb_onboarding_enabled())return new WP_Error('onboarding_disabled','Nuove adesioni non ancora abilitate.',array('status'=>503));
    if(in_array($action,array('store_order','store_status'),true))return trb_onboarding_store($action,$p);
    if($action==='activate_account'){
        $id=absint($p['portal_user_id']??0);$user=get_userdata($id);$practice=(string)($p['practice_id']??'');
        if(!$user||!preg_match('/^[a-f0-9]{32}$/D',$practice)||get_user_meta($id,'_trb_onboarding_version',true)!=='2026.2'||get_user_meta($id,'_trb_onboarding_practice',true)!==$practice||strcasecmp($user->user_email,(string)($p['email']??''))!==0||($p['owner_approved']??false)!==true||($p['signed']??false)!==true||empty($p['signed_pcloud_file_id']))return new WP_Error('onboarding_account','Account non associato alla pratica approvata e firmata.',array('status'=>409));
        $profiles=trb_portal_profiles();$key=strtolower(str_replace('-','_',(string)($p['group_code']??'')));if(!isset($profiles[$key]))return new WP_Error('onboarding_profile','Profilo contrattuale non valido.',array('status'=>422));
        // This account was created by this workflow; never replace an existing artist.
        if($user->has_cap('manage_options'))return new WP_Error('onboarding_account','Account amministrativo non modificabile.',array('status'=>409));
        $number=(string)($p['contract_number']??'');$term=(string)($p['contract_term']??'');
        if(strpos($number,'TRB-')!==0||is_wp_error(trb_release_bridge_contract_term_dates($term)))return new WP_Error('onboarding_contract','Decorrenza o numero contrattuale non validi.',array('status'=>422));
        update_user_meta($id,'_trb_artist_preliminary_contract',$number);update_user_meta($id,'_trb_artist_contract_term',$term);
        $details=(array)($p['details']??array());$billing=(array)($details['billing']??array());
        foreach(array('phone'=>'phone','city'=>'city','postcode'=>'postal_code','state'=>'province','country'=>'country') as $from=>$to)if(!empty($billing[$from]))update_user_meta($id,'_trb_artist_'.$to,sanitize_text_field($billing[$from]));
        if(!empty($billing['address_1'])){
            $address=trim($billing['address_1']);$street=$address;$civic='';
            if(preg_match('/^(.+?)\s+(\d+[A-Za-z0-9\/ -]*)$/u',$address,$parts)){$street=$parts[1];$civic=$parts[2];}
            update_user_meta($id,'_trb_artist_street',sanitize_text_field($street));if($civic!=='')update_user_meta($id,'_trb_artist_street_number',sanitize_text_field($civic));
        }
        foreach(array('tax_code'=>$details['tax_code']??'','birth_date'=>$p['birth_date']??'') as $field=>$value)if($value!=='')update_user_meta($id,'_trb_artist_'.$field,sanitize_text_field($value));
        $labels=array('identity_front'=>array('identity','Carta d’identità — fronte'),'identity_back'=>array('identity','Carta d’identità — retro'),'tax_front'=>array('tax_card','Codice fiscale o tessera sanitaria — fronte'),'tax_back'=>array('tax_card','Codice fiscale o tessera sanitaria — retro'));
        $files=get_user_meta($id,'_trb_artist_private_files',true);$files=is_array($files)?$files:array();
        foreach($labels as $slot=>$label){$file=$p['files'][$slot]??null;if(!$file)continue;$fileId='trb-onboarding-'.$slot;
            if(array_filter($files,static fn($entry)=>($entry['id']??'')===$fileId))continue;
            $files[]=array('id'=>$fileId,'path'=>'','name'=>sanitize_file_name($file['name']),'type'=>str_ends_with(strtolower($file['name']),'.pdf')?'application/pdf':(str_ends_with(strtolower($file['name']),'.png')?'image/png':'image/jpeg'),'group'=>$label[0],'label'=>$label[1],'time'=>time(),'size'=>absint($file['size']??0),'source'=>'onboarding-pcloud');
        }
        update_user_meta($id,'_trb_artist_private_files',$files);
        $user->add_role($profiles[$key]['role']);update_user_meta($id,'_trb_artist_contract_profile',$key);update_user_meta($id,'_trb_onboarding_contract_file_id',absint($p['signed_pcloud_file_id']));update_user_meta($id,'_trb_onboarding_stage','active');
        if(function_exists('pw_new_user_approve'))pw_new_user_approve()->update_user_status($id,'approve');
        return array('portal_user_id'=>$id,'practice_id'=>$practice,'activated'=>true);
    }
    return new WP_Error('onboarding_action','Operazione non disponibile.',array('status'=>422));
}

function trb_onboarding_browser_key(){
    $nonce=(string)($_COOKIE['__Host-trb_onboarding']??'');return preg_match('/^[a-f0-9]{64}$/D',$nonce)?'trb_onboarding_browser_'.hash('sha256',$nonce):'';
}
function trb_onboarding_csrf($nonce){$time=(string)time();return $time.'.'.hash_hmac('sha256','onboarding-browser-v1|'.$time.'|'.$nonce,wp_salt('auth'));}
function trb_onboarding_public_permission($request){
    if(!trb_onboarding_enabled())return new WP_Error('onboarding_disabled','Nuove adesioni non ancora abilitate.',array('status'=>503));
    if(stripos((string)$request->get_header('content-type'),'application/json')!==0)return new WP_Error('onboarding_body','Sono ammesse soltanto richieste JSON.',array('status'=>415));
    if(strlen($request->get_body())>20000)return new WP_Error('onboarding_body','Richiesta troppo grande.',array('status'=>413));
    $origin=(string)$request->get_header('origin');if($origin!==''&&$origin!==rtrim(home_url(),'/'))return new WP_Error('onboarding_origin','Richiesta non autorizzata.',array('status'=>403));
    $nonce=(string)($_COOKIE['__Host-trb_onboarding']??'');$parts=explode('.',(string)$request->get_header('x-trb-onboarding-csrf'));
    if(!trb_onboarding_browser_key()||count($parts)!==2||!ctype_digit($parts[0])||time()-(int)$parts[0]<0||time()-(int)$parts[0]>28800||!hash_equals(hash_hmac('sha256','onboarding-browser-v1|'.$parts[0].'|'.$nonce,wp_salt('auth')),$parts[1]))return new WP_Error('onboarding_csrf','Aggiorna la pagina per continuare.',array('status'=>403));
    return true;
}
function trb_onboarding_public($request){
    $p=$request->get_json_params();$action=$p['action']??'';$key=trb_onboarding_browser_key();$browser=(array)get_transient($key);
    if($action==='browser_status')return array('challenge_pending'=>!empty($browser['invite'])&&!empty($browser['salt']));
    if($action==='challenge'){
        $token=(string)($p['token']??$browser['invite']??'');if(!preg_match('/^[a-f0-9]{64}$/D',$token))return new WP_Error('onboarding_invite','Collegamento personale non valido.',array('status'=>422));
        $response=trb_onboarding_crm(array('action'=>'challenge','token'=>$token));if(is_wp_error($response))return $response;
        $browser=array('invite'=>$token,'salt'=>$response['salt']);set_transient($key,$browser,8*HOUR_IN_SECONDS);return array('code_sent'=>true);
    }
    if($action==='verify_email'){
        $response=trb_onboarding_crm(array('action'=>'verify_email','token'=>$browser['invite']??'','salt'=>$browser['salt']??'','code'=>(string)($p['code']??'')));
        if(is_wp_error($response))return $response;$browser['session']=$response['session'];unset($browser['salt']);set_transient($key,$browser,8*HOUR_IN_SECONDS);return $response['practice'];
    }
    // Account-only operations are derived from the authenticated WP user, never request claims.
    if(in_array($action,array('account','account_checkout','account_document'),true)){
        $user=wp_get_current_user();if(!$user->exists()||get_user_meta($user->ID,'_trb_onboarding_version',true)!=='2026.2')return new WP_Error('onboarding_login','Accedi al tuo account artista.',array('status'=>401));
        return trb_onboarding_crm(array('action'=>$action,'portal_user_id'=>$user->ID,'email'=>$user->user_email,'slot'=>(string)($p['slot']??'')));
    }
    if(empty($browser['session']))return new WP_Error('onboarding_email','Conferma prima l’indirizzo email.',array('status'=>401));
    if($action==='register'){
        $ready=trb_onboarding_crm(array('action'=>'registration_authorization','session'=>$browser['session']));if(is_wp_error($ready))return $ready;
        $password=(string)($p['password']??'');if(mb_strlen($password)<14||mb_strlen($password)>128||str_contains($password,"\0")||$password!==(string)($p['repeat_password']??''))return new WP_Error('onboarding_password','Inserisci due password uguali di almeno 14 caratteri.',array('status'=>422));
        $email=$ready['email'];$existing=get_user_by('email',$email);
        if($existing){
            if(get_user_meta($existing->ID,'_trb_onboarding_practice',true)!==$ready['id']||get_user_meta($existing->ID,'_trb_onboarding_version',true)!=='2026.2'||!wp_check_password($password,$existing->user_pass,$existing->ID))return new WP_Error('onboarding_existing','Questo indirizzo è già associato a un account. Contatta l’assistenza per collegare la pratica senza modificare il profilo esistente.',array('status'=>409));
            $id=$existing->ID;
        }else{
            $GLOBALS['trb_onboarding_register_context']=$ready;
            try{$id=wp_insert_user(array('user_login'=>'artista_'.bin2hex(random_bytes(10)),'user_pass'=>$password,'user_email'=>$email,'first_name'=>$ready['first_name'],'last_name'=>$ready['last_name'],'display_name'=>$ready['artist_name']?:trim($ready['first_name'].' '.$ready['last_name']),'role'=>'subscriber'));}
            finally{unset($GLOBALS['trb_onboarding_register_context']);}
            if(is_wp_error($id))return new WP_Error('onboarding_register','Account non creato. Riprova senza modificare la pratica.',array('status'=>409));
        }
        $result=trb_onboarding_crm(array('action'=>'register','session'=>$browser['session'],'portal_user_id'=>(int)$id));if(is_wp_error($result))return $result;
        delete_transient($key);return array('registered'=>true,'login_url'=>home_url('/accedi/'));
    }
    $allowed=array('view','details','upload','uploaded','identity','choose','checkout','refresh','document');if(!in_array($action,$allowed,true))return new WP_Error('onboarding_action','Operazione non disponibile.',array('status'=>422));
    if($action==='details'&&strtoupper($p['billing']['country']??'')==='IT'){
        $tax=trb_portal_validate_tax_code($p['tax_code']??'');if(!$tax)return new WP_Error('onboarding_tax','Codice fiscale non valido. Controlla i dati prima di proseguire.',array('status'=>422));$p['tax_code']=$tax;
    }
    $p['session']=$browser['session'];unset($p['token'],$p['portal_user_id'],$p['email'],$p['password'],$p['repeat_password']);return trb_onboarding_crm($p);
}
// Mark before third-party registration callbacks, including DDS annual auto-approval.
add_action('user_register',static function($id){
    $p=$GLOBALS['trb_onboarding_register_context']??null;if(!$p)return;
    update_user_meta($id,'_trb_onboarding_version','2026.2');update_user_meta($id,'_trb_onboarding_practice',$p['id']);update_user_meta($id,'_trb_onboarding_stage','account_preparing');update_user_meta($id,'_trb_artist_artist_name',$p['artist_name']);
},-10000);
add_filter('wp_authenticate_user',static function($user){if($user instanceof WP_User&&get_user_meta($user->ID,'_trb_onboarding_version',true)==='2026.2'&&get_user_meta($user->ID,'_trb_onboarding_stage',true)!=='active')return new WP_Error('onboarding_pending','L’attivazione dell’account è in completamento. Riapri il collegamento personale e riprendi la registrazione.');return $user;},10000);
add_action('rest_api_init',static function(){
    register_rest_route('trb/v1','/onboarding/private',array('methods'=>'POST','permission_callback'=>'trb_onboarding_private_permission','callback'=>'trb_onboarding_private'));
    register_rest_route('trb/v1','/onboarding/public',array('methods'=>'POST','permission_callback'=>'trb_onboarding_public_permission','callback'=>'trb_onboarding_public'));
});

function trb_onboarding_access(){
    if(!trb_onboarding_enabled())return array('managed'=>false,'allowed'=>true);
    static $result=null;$user=wp_get_current_user();if(!$user->exists()||get_user_meta($user->ID,'_trb_onboarding_version',true)!=='2026.2'||$user->has_cap('manage_options'))return array('managed'=>false,'allowed'=>true);
    if($result!==null)return $result;
    $response=trb_onboarding_crm(array('action'=>'account','portal_user_id'=>$user->ID,'email'=>$user->user_email));
    return $result=is_wp_error($response)?array('managed'=>true,'allowed'=>false,'reason'=>'connection_unconfirmed'):($response['access']??array('managed'=>true,'allowed'=>false,'reason'=>'verification_pending'));
}
function trb_onboarding_guard_actions(){
    if(!is_user_logged_in())return;$action=sanitize_key($_REQUEST['action']??'');if(strpos($action,'trb_')!==0)return;
    $allowed=array('trb_portal_submit_support','trb_portal_private_file','trb_portal_release_file','trb_portal_release_waveform','trb_analysis_download_report','trb_portal_save_artist_profile');if(in_array($action,$allowed,true))return;
    $gate=trb_onboarding_access();if($gate['allowed'])return;
    if(wp_doing_ajax()||isset($_SERVER['HTTP_X_TRB_UPLOAD']))wp_send_json_error(array('message'=>'I servizi richiedono la verifica dei versamenti. Accedi a Versamenti e documenti per continuare.','billing_url'=>home_url('/adesione/?account=1')),402);
    wp_safe_redirect(home_url('/adesione/?account=1'));exit;
}
add_action('init','trb_onboarding_guard_actions',-2000);
add_filter('rest_pre_dispatch',static function($result,$server,$request){
    if(!trb_onboarding_enabled())return $result;
    if(!is_user_logged_in()||strpos($request->get_route(),'/trb/v1/onboarding/')===0)return $result;
    $gate=trb_onboarding_access();if(!$gate['allowed'])return new WP_Error('onboarding_payment_required','I servizi richiedono la verifica dei versamenti.',array('status'=>402));return $result;
},-2000,3);

function trb_onboarding_is_page(){return rtrim((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),'/')==='/adesione';}
add_action('init',static function(){
    if(!trb_onboarding_is_page())return;
    if(!trb_onboarding_browser_key()){
        $cookie=bin2hex(random_bytes(32));
        setcookie('__Host-trb_onboarding',$cookie,array('expires'=>time()+8*HOUR_IN_SECONDS,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax'));
        $_COOKIE['__Host-trb_onboarding']=$cookie;
    }
    if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
},-3000);
add_action('template_redirect',static function(){
    if(trb_onboarding_is_page()){status_header(200);require __DIR__.'/trb-candidate-onboarding-page.php';exit;}
    if(!trb_onboarding_enabled()||!is_user_logged_in()||is_admin())return;
    $path=rtrim((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),'/');
    if(in_array($path,array('/accedi','/segnalazione','/registrazione'),true))return;
    $gate=trb_onboarding_access();if(!$gate['allowed']&&($path==='/area-artisti'||is_singular(trb_portal_supported_resource_types()))){wp_safe_redirect(home_url('/adesione/?account=1'));exit;}
},-10000);
add_action('trb_onboarding_worker',static function(){if(trb_onboarding_enabled())trb_onboarding_crm(array('action'=>'worker'));});
add_filter('cron_schedules',static function($schedules){$schedules['trb_onboarding_ten_minutes']=array('interval'=>600,'display'=>'Adesioni ogni dieci minuti');return $schedules;});
add_action('init',static function(){if(trb_onboarding_enabled()&&!wp_next_scheduled('trb_onboarding_worker'))wp_schedule_event(time()+30,'trb_onboarding_ten_minutes','trb_onboarding_worker');});

// Reuse documents already verified in the new workflow without hosting their bytes.
add_filter('trb_portal_artist_profile_requirements',static function($requirements,$userId){
    if(get_user_meta($userId,'_trb_onboarding_version',true)!=='2026.2')return $requirements;
    return array_values(array_filter($requirements,static fn($r)=>($r['label']??'')!=='Codice fiscale o tessera sanitaria — retro'));
},10,2);
add_action('admin_post_trb_portal_private_file',static function(){
    $fileId=sanitize_text_field(wp_unslash($_GET['file_id']??''));if(strpos($fileId,'trb-onboarding-')!==0)return;
    if(!is_user_logged_in())auth_redirect();$user=wp_get_current_user();check_admin_referer('trb_portal_private_file_'.$fileId);
    if(get_user_meta($user->ID,'_trb_onboarding_version',true)!=='2026.2')wp_die('Documento non disponibile.','Documento',array('response'=>404));
    $slot=substr($fileId,15);if(!in_array($slot,array('identity_front','identity_back','tax_front','tax_back'),true))wp_die('Documento non disponibile.','Documento',array('response'=>404));
    $response=trb_onboarding_crm(array('action'=>'account_document','portal_user_id'=>$user->ID,'email'=>$user->user_email,'slot'=>$slot));
    if(is_wp_error($response))wp_die(esc_html($response->get_error_message()),'Documento',array('response'=>409));
    $url=$response['url']??'';if(!preg_match('~^https://[a-z0-9.-]+\.pcloud\.com/~D',$url))wp_die('Documento non disponibile.','Documento',array('response'=>409));
    nocache_headers();header('Referrer-Policy: no-referrer');wp_redirect($url);exit;
},-100);
