<?php
/** Synthetic WordPress/WooCommerce boundary tests: no network or real accounts. */
define('ABSPATH',__DIR__);define('HOUR_IN_SECONDS',3600);
class WP_Error{public function __construct(public $code,public $message,public $data=[]){}public function get_error_message(){return $this->message;}}
class WP_User{public $roles=[];public $user_email='artist@example.invalid';public function __construct(public $ID=101){}public function has_cap($cap){return false;}public function add_role($role){$this->roles[]=$role;}}
function is_wp_error($x){return $x instanceof WP_Error;}
function add_action(...$args){if(($args[0]??'')==='user_register'&&is_array($args[1]??null)&&($args[1][1]??'')==='request_admin_approval_email_2')$GLOBALS['approval_notification_suspended']=false;}function add_filter($tag,$callback,...$args){$GLOBALS['adapter_filters'][$tag][]=$callback;}
function has_action($hook,$callback){return $hook==='user_register'&&($callback[1]??'')==='request_admin_approval_email_2'?10:false;}
function remove_action($hook,$callback,$priority){$GLOBALS['approval_notification_suspended']=true;return true;}
function get_query_var($key){return $key==='order-pay'?($GLOBALS['adapter_pay_order']??0):0;}function wc_get_order($id){return $GLOBALS['adapter_orders'][$id]??false;}
function absint($x){return abs((int)$x);}function sanitize_text_field($x){return trim($x);}function sanitize_file_name($x){return basename($x);}function wp_unslash($x){return $x;}
function wp_json_encode($x,...$args){return json_encode($x,...$args);}function wp_salt($x){return str_repeat('a',64);}function home_url(){return 'https://artist.trbrec.com';}
$meta=[];$options=['trb_candidate_onboarding_enabled'=>true];$transients=[];$user=new WP_User();
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}function add_option($key,$value,...$extra){if(isset($GLOBALS['options'][$key]))return false;$GLOBALS['options'][$key]=$value;return true;}
function get_user_meta($id,$key,$single=true){if($key==='_trb_onboarding_stage'&&isset($GLOBALS['registration_stage_cache'][$id]))return $GLOBALS['registration_stage_cache'][$id];return $GLOBALS['meta'][$id][$key]??'';}function update_user_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
function clean_user_cache($id){unset($GLOBALS['registration_stage_cache'][$id]);$GLOBALS['registration_cache_cleans']=($GLOBALS['registration_cache_cleans']??0)+1;}
function get_post($id){return (int)$id===(int)($GLOBALS['adapter_privacy_page']->ID??0)?$GLOBALS['adapter_privacy_page']:null;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['adapter_privacy_meta'][$id][$key]??'';}
function get_permalink($id){return $GLOBALS['adapter_privacy_permalink']??'https://artist.trbrec.com/privacy-policy/';}
function get_userdata($id){return $id===$GLOBALS['user']->ID?$GLOBALS['user']:false;}function get_transient($key){return $GLOBALS['transients'][$key]??false;}
function trb_crm_connector_settings(){return ['secret'=>str_repeat('s',64)];}function trb_portal_dds_store_secret(){return str_repeat('s',64);}function trb_store_dds_bridge_secret(){return str_repeat('s',64);}
function trb_portal_profiles(){return ['ddb'=>['role'=>'artista_b']];}function pw_new_user_approve(){return new class{public function update_user_status($id,$status){if(empty($GLOBALS['approval_fail']))$GLOBALS['approved']=$status;}public function get_user_status($id){return empty($GLOBALS['approval_fail'])&&($GLOBALS['approved']??'')==='approve'?'approved':'pending';}};}
function trb_release_bridge_contract_term_dates($term){return preg_match('~^\d\d/\d\d/\d\d - \d\d/\d\d/\d\d$~',$term)?[]:new WP_Error('term','Invalid');}
function wp_next_scheduled($x){return true;}function wp_date($format,$stamp,$zone){return (new DateTimeImmutable('@'.$stamp))->setTimezone($zone)->format($format);}
function check_adapter($ok,$message){if(!$ok)throw new RuntimeException($message);}
require dirname(__DIR__).'/inc/trb-candidate-onboarding.php';
require dirname(__DIR__).'/integrations/onboarding/store/trb-onboarding-payments.php';
// A login exception must expose only the managed published notice, never other content.
$_SERVER['REQUEST_URI']='/privacy-policy/';
check_adapter(!trb_onboarding_is_privacy_page(),'An unconfigured notice cannot bypass portal access');
$options['wp_page_for_privacy_policy']=9258;
$GLOBALS['adapter_privacy_page']=(object)['ID'=>9258,'post_type'=>'page','post_status'=>'publish'];
$GLOBALS['adapter_privacy_meta'][9258]['_trb_onboarding_privacy_version']='20261002c';
check_adapter(trb_onboarding_is_privacy_page(),'Published managed notice is public before registration');
foreach(['/area-artisti/','/docs/private/','/privacy-policy/extra','/wp-admin/','/adesione/'] as $path){$_SERVER['REQUEST_URI']=$path;check_adapter(!trb_onboarding_is_privacy_page(),'Unrelated paths retain access controls');}
$_SERVER['REQUEST_URI']='/privacy-policy/?from=onboarding';check_adapter(trb_onboarding_is_privacy_page(),'Notice remains accessible with an ordinary query string');
$GLOBALS['adapter_privacy_page']->post_status='draft';check_adapter(!trb_onboarding_is_privacy_page(),'A draft notice is not public');
$GLOBALS['adapter_privacy_page']->post_status='publish';$GLOBALS['adapter_privacy_page']->post_type='docs';check_adapter(!trb_onboarding_is_privacy_page(),'A private resource cannot become the public notice');
$GLOBALS['adapter_privacy_page']->post_type='page';$GLOBALS['adapter_privacy_meta'][9258]['_trb_onboarding_privacy_version']='other';check_adapter(!trb_onboarding_is_privacy_page(),'An unmanaged page retains portal access');
$GLOBALS['adapter_privacy_meta'][9258]['_trb_onboarding_privacy_version']='20261002c';$GLOBALS['adapter_privacy_permalink']='https://artist.trbrec.com/other/';check_adapter(!trb_onboarding_is_privacy_page(),'A different configured route is not exposed');
unset($options['wp_page_for_privacy_policy'],$GLOBALS['adapter_privacy_page'],$GLOBALS['adapter_privacy_meta'],$GLOBALS['adapter_privacy_permalink']);
$siteLoginError=new WP_Error('rest_login','Site login required');$_SERVER['REQUEST_METHOD']='POST';
foreach(array('/wp-json/trb/v1/onboarding/private','/wp-json/trb/v1/onboarding/public') as $route){$_SERVER['REQUEST_URI']=$route;check_adapter(trb_onboarding_protocol_authentication($siteLoginError)===null,'Portal protocol reaches its own permission callback');}
foreach(array('/wp-json/trb/v1/onboarding/order','/wp-json/trb/v1/onboarding/payment-status') as $route){$_SERVER['REQUEST_URI']=$route;check_adapter(trb_onboarding_store_protocol_authentication($siteLoginError)===null,'Store protocol reaches its own permission callback');}
foreach(array('/wp-json/wp/v2/users','/wp-json/trb/v1/onboarding/private-export','/wp-json/trb/v1/onboarding/order-extra') as $route){$_SERVER['REQUEST_URI']=$route;check_adapter(trb_onboarding_protocol_authentication($siteLoginError)===$siteLoginError&&trb_onboarding_store_protocol_authentication($siteLoginError)===$siteLoginError,'Unrelated routes retain the site login gate');}
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/wp-json/trb/v1/onboarding/private';check_adapter(trb_onboarding_protocol_authentication($siteLoginError)===$siteLoginError,'Only POST uses the protocol gate');
$portalStamp=(string)time();$portalNonce=str_repeat('e',32);$portalHeaders=array('x-trb-timestamp'=>$portalStamp,'x-trb-nonce'=>$portalNonce,'x-trb-signature'=>'sha256='.str_repeat('0',64));
check_adapter(is_wp_error(trb_onboarding_private_permission(new AdapterRequest(array(),$portalHeaders))),'Invalid portal HMAC rejected after protocol routing');
$portalHeaders['x-trb-signature']='sha256='.hash_hmac('sha256','onboarding-portal-v1|'.$portalStamp.'|'.$portalNonce.'|{}',str_repeat('s',64));
check_adapter(trb_onboarding_private_permission(new AdapterRequest(array(),$portalHeaders))===true,'Valid portal HMAC accepted');check_adapter(is_wp_error(trb_onboarding_private_permission(new AdapterRequest(array(),$portalHeaders))),'Portal replay rejected');
class AdapterRequest{public function __construct(public $params=[],public $headers=[],public $body='{}'){}public function get_json_params(){return $this->params;}public function get_header($key){return $this->headers[$key]??'';}public function get_body(){return $this->body;}}
$practice=str_repeat('a',32);$payload=['action'=>'activate_account','portal_user_id'=>101,'practice_id'=>$practice,'email'=>$user->user_email,'group_code'=>'DDB','owner_approved'=>true,'signed'=>true,'signed_pcloud_file_id'=>123,'contract_number'=>'TRB-QA','contract_term'=>'01/10/26 - 30/09/27','details'=>['billing'=>['address_1'=>'Via Collaudo 12','city'=>'Roma','postcode'=>'00100','country'=>'IT','phone'=>'+393330000000'],'tax_code'=>'RSSMRA90A01H501W'],'birth_date'=>'1990-01-01','files'=>['identity_front'=>['name'=>'identity.pdf','size'=>1234]]];
check_adapter(is_wp_error(trb_onboarding_private(new AdapterRequest($payload))),'Legacy account must stay untouched');check_adapter(!$meta,'No legacy writes');
$meta[101]=['_trb_onboarding_version'=>'2026.2','_trb_onboarding_practice'=>$practice];
check_adapter(is_wp_error(trb_onboarding_private(new AdapterRequest(array_replace($payload,['signed'=>false])))),'Unsigned account rejected');
$r=trb_onboarding_private(new AdapterRequest($payload));check_adapter($r['activated']&&$approved==='approve','New account activated');
check_adapter($meta[101]['_trb_artist_street']==='Via Collaudo'&&$meta[101]['_trb_artist_street_number']==='12','Administrative address transferred');
check_adapter($meta[101]['_trb_artist_preliminary_contract']==='TRB-QA'&&$meta[101]['_trb_artist_contract_term']==='01/10/26 - 30/09/27','Release contract gates initialized');
check_adapter($meta[101]['_trb_artist_private_files'][0]['path']===''&&$meta[101]['_trb_artist_private_files'][0]['id']==='trb-onboarding-identity_front','Remote document metadata without local bytes');
trb_onboarding_private(new AdapterRequest($payload));check_adapter(count($meta[101]['_trb_artist_private_files'])===1,'Repeated activation does not duplicate files');
$GLOBALS['approval_fail']=true;update_user_meta(101,'_trb_onboarding_stage','account_preparing');check_adapter(is_wp_error(trb_onboarding_private(new AdapterRequest($payload)))&&get_user_meta(101,'_trb_onboarding_stage',true)==='account_preparing','Missing approval confirmation never marks the account active');$GLOBALS['approval_fail']=false;trb_onboarding_private(new AdapterRequest($payload));
$separate=$payload;$separate['details']['billing']['street']='Via 25 Aprile';$separate['details']['billing']['street_number']='11/A';$separate['details']['billing']['address_1']='Via 25 Aprile 11/A';$separate['details']['profile']=['birth_place'=>'Roma','birth_province'=>'RM','document_number'=>'CA12345AB','document_expiry'=>'2030-01-01'];trb_onboarding_private(new AdapterRequest($separate));check_adapter($meta[101]['_trb_artist_street']==='Via 25 Aprile'&&$meta[101]['_trb_artist_street_number']==='11/A'&&$meta[101]['_trb_artist_document_number']==='CA12345AB','Structured address and identity fields reach the original artist profile');
$_COOKIE['__Host-trb_onboarding']=str_repeat('b',64);$transients[trb_onboarding_browser_key()]=['invite'=>str_repeat('c',64),'salt'=>'qa'];check_adapter(trb_onboarding_public(new AdapterRequest(['action'=>'browser_status']))['challenge_pending'],'Email verification resumes after page reload');
$stamp=(string)time();$nonce=str_repeat('d',32);$request=new AdapterRequest([],['x-trb-timestamp'=>$stamp,'x-trb-nonce'=>$nonce,'x-trb-signature'=>'sha256='.hash_hmac('sha256','onboarding-v1|'.$stamp.'|'.$nonce.'|{}',str_repeat('s',64))]);
check_adapter(trb_onboarding_store_permission($request)===true,'Authenticated Store boundary accepted');check_adapter(is_wp_error(trb_onboarding_store_permission($request)),'Store replay rejected');
$order=new class{public $gateway='stripe',$transaction='qa-capture',$date,$amount=49.0,$currency='EUR';public function __construct(){$this->date=new DateTimeImmutable('now');}public function get_meta($key){return 4900;}public function get_payment_method(){return $this->gateway;}public function get_date_paid(){return $this->date;}public function get_transaction_id(){return $this->transaction;}public function get_currency(){return $this->currency;}public function get_total(){return $this->amount;}};
check_adapter(trb_onboarding_store_capture($order)['captured'],'Exact provider capture accepted');$order->gateway='bacs';check_adapter(!trb_onboarding_store_capture($order)['captured'],'Manual bank transfer not capture');$order->gateway='stripe';$order->transaction='';check_adapter(!trb_onboarding_store_capture($order)['captured'],'Transaction proof required');$order->transaction='qa';$order->amount=50;check_adapter(!trb_onboarding_store_capture($order)['captured'],'Gross contract cents enforced');
foreach(array('ppcp-card-button-gateway','ppcp-credit-card-gateway','stripe') as $gateway){$order->gateway=$gateway;$order->amount=49;check_adapter(trb_onboarding_store_capture($order)['captured'],'Direct card captures accepted with their own gateway IDs');}
check_adapter(!trb_onboarding_bank_configured(),'No bank checkout without an IBAN');$options['woocommerce_bacs_accounts']=[['account_name'=>'QA TEST','iban'=>'IT60 X054 2811 1010 0000 0123 456']];check_adapter(trb_onboarding_bank_configured(),'Bank checkout requires an IBAN checksum');$options['woocommerce_bacs_accounts'][0]['iban']='IT00X0542811101000000123456';check_adapter(!trb_onboarding_bank_configured(),'Invalid bank details rejected');
$gatewayFilter=end($GLOBALS['adapter_filters']['woocommerce_available_payment_gateways']);$gateways=['ppcp-gateway'=>(object)[],'stripe'=>(object)[],'ppcp-card-button-gateway'=>(object)[],'bacs'=>(object)[]];
check_adapter($gatewayFilter($gateways)===$gateways,'Ordinary store checkout retains every available gateway');check_adapter(trb_onboarding_checkout_button_text('Original')==='Original','Ordinary store button is untouched');
$GLOBALS['adapter_pay_order']=903;$GLOBALS['adapter_orders'][903]=new class{public function get_meta($key){return $key==='_trb_onboarding_practice_id'?'qa':'';}};
check_adapter(array_keys($gatewayFilter($gateways))===['ppcp-gateway','stripe'],'Onboarding offers one card alternative and hides an unconfigured bank');
$options['woocommerce_bacs_accounts']=[['account_name'=>'QA TEST','iban'=>'IT60 X054 2811 1010 0000 0123 456']];check_adapter(trb_onboarding_bank_configured()&&array_keys($gatewayFilter($gateways))===['ppcp-gateway','stripe'],'A bank configured for other Store purchases never enables transfer for a new contract');
$fallback=$gateways;unset($fallback['stripe']);check_adapter(array_keys($gatewayFilter($fallback))===['ppcp-gateway','ppcp-card-button-gateway'],'PayPal card option remains when direct cards are unavailable');
check_adapter(trb_onboarding_checkout_button_text('Paga per l’ordine')==='EFFETTUA IL VERSAMENTO','Contract checkout uses the requested action');
$GLOBALS['adapter_pay_order']=0;
class WC_DateTime extends DateTimeImmutable{}
class BankTestOrder{
 public $meta=['_trb_onboarding_practice_id'=>'qa','_trb_onboarding_amount_cents'=>4900],$transaction='',$date=null,$status='on-hold',$gateway='bacs',$completions=0;public function __construct(public $id){}
 public function get_id(){return $this->id;}public function get_meta($k){return $this->meta[$k]??'';}public function get_payment_method(){return $this->gateway;}public function has_status($s){return in_array($this->status,(array)$s,true);}public function get_currency(){return 'EUR';}public function get_total(){return 49;}public function get_total_refunded(){return 0;}public function get_date_paid(){return $this->date;}public function get_transaction_id(){return $this->transaction;}public function update_meta_data($k,$v){$this->meta[$k]=$v;}public function set_date_paid($d){$this->date=$d;}public function save(){}public function payment_complete($ref){$this->transaction=$ref;$this->status='processing';$this->completions++;}public function add_order_note($x){}
}
$bank=new BankTestOrder(901);$paidOn=(new DateTimeImmutable('today',new DateTimeZone('Europe/Rome')))->format('Y-m-d');
check_adapter(!trb_onboarding_store_capture($bank)['captured'],'Bank selection cannot confirm payment');$bank->status='processing';check_adapter(!trb_onboarding_store_capture($bank)['captured'],'Manual status toggle cannot confirm payment');$bank->status='on-hold';
check_adapter(is_wp_error(trb_onboarding_confirm_bank_receipt($bank,'QA-TRN-123',$paidOn,0)),'Unattributed bank confirmation rejected');check_adapter(is_wp_error(trb_onboarding_confirm_bank_receipt($bank,'QA-TRN-123','2099-01-01',1)),'Future credit date rejected');
check_adapter(trb_onboarding_confirm_bank_receipt($bank,'QA-TRN-123',$paidOn,1)===true,'Recorded full credit can reconcile the bank payment');check_adapter(trb_onboarding_store_capture($bank)['captured'],'Bank receipt verified against exact order, amount, transaction and date');
check_adapter(trb_onboarding_confirm_bank_receipt($bank,'QA-TRN-123',$paidOn,1)===true&&$bank->completions===1,'Repeated confirmation does not duplicate the accounting');$other=new BankTestOrder(902);check_adapter(is_wp_error(trb_onboarding_confirm_bank_receipt($other,'QA-TRN-123',$paidOn,1)),'One bank credit cannot settle two orders');$bank->transaction='wrong';check_adapter(!trb_onboarding_store_capture($bank)['captured'],'Edited transaction fails the bank receipt comparison');
echo "Onboarding boundary authentication, replay, legacy isolation, profile transfer and capture proof verified.\n";

// Actual first-password route: no auth cookie until the private CRM activation succeeds.
function wp_remote_post($url,$args){$p=json_decode($args['body'],true);$GLOBALS['registration_rpc'][]=$p;
 if($p['action']==='registration_authorization')return ['status'=>200,'data'=>$GLOBALS['registration_ready']];
 if($p['action']==='register'){if(!empty($GLOBALS['registration_fail']))return new WP_Error('test','Synthetic activation unavailable');update_user_meta((int)$p['portal_user_id'],'_trb_onboarding_stage','active');return ['status'=>200,'data'=>['registered'=>true]];}
 throw new RuntimeException('Unexpected registration RPC');}
function wp_remote_retrieve_body($r){return json_encode($r['data']);}function wp_remote_retrieve_response_code($r){return $r['status'];}
function get_user_by($type,$value){return $GLOBALS['registration_existing']??false;}
function wp_check_password($password,$hash,$id){return $password==='synthetic-password-123';}
function wp_insert_user($data){check_adapter(!empty($GLOBALS['approval_notification_suspended']),'Manual approval notification suspended during verified account creation');$GLOBALS['registration_insert']=$data;$ready=$GLOBALS['trb_onboarding_register_context'];update_user_meta(101,'_trb_onboarding_version','2026.2');update_user_meta(101,'_trb_onboarding_practice',$ready['id']);update_user_meta(101,'_trb_onboarding_stage','account_preparing');$GLOBALS['registration_stage_cache'][101]='account_preparing';return 101;}
function wp_set_current_user($id){$GLOBALS['registration_current']=$id;}function wp_set_auth_cookie($id,$remember,$secure){$GLOBALS['registration_cookie']=[$id,$remember,$secure];}
function delete_transient($key){unset($GLOBALS['transients'][$key]);}
$GLOBALS['registration_ready']=['id'=>$practice,'email'=>'artist@example.invalid','first_name'=>'Mario','last_name'=>'Rossi','artist_name'=>'QA','qa'=>false];
$passwordRequest=new AdapterRequest(['action'=>'register','password'=>'synthetic-password-123','repeat_password'=>'synthetic-password-123']);
$transients[trb_onboarding_browser_key()]=['session'=>str_repeat('f',64)];$GLOBALS['registration_fail']=true;
check_adapter(is_wp_error(trb_onboarding_public($passwordRequest))&&!isset($GLOBALS['registration_cookie']),'Unconfirmed activation never logs in');
check_adapter(empty($GLOBALS['approval_notification_suspended']),'Legacy approval hook restored after a failed activation');
$GLOBALS['registration_existing']=$user;$user->user_pass='synthetic-hash';$GLOBALS['registration_fail']=false;
$registered=trb_onboarding_public($passwordRequest);
check_adapter($registered['registered']&&$GLOBALS['registration_cookie']===[101,false,true],'Activated artist gets a secure session after password setup');
check_adapter($GLOBALS['registration_cache_cleans']>0&&get_user_meta(101,'_trb_onboarding_stage',true)==='active','Remote activation invalidates the stale request cache before authentication');
check_adapter(!isset($GLOBALS['trb_onboarding_register_context'])&&!isset($transients[trb_onboarding_browser_key()]),'Registration context and browser challenge cleared');
foreach($GLOBALS['registration_rpc'] as $rpc)check_adapter(!isset($rpc['password'],$rpc['repeat_password']),'Password never crosses the CRM bridge');
// Legacy mail suppression is recipient-specific and only inside onboarding operations.
$suppress=end($GLOBALS['adapter_filters']['pre_wp_mail']);$GLOBALS['trb_onboarding_approve_email']='artist@example.invalid';
check_adapter($suppress(null,['to'=>'artist@example.invalid'])===true,'Legacy approval mail replaced by branded welcome');
check_adapter($suppress(null,['to'=>'other@example.invalid'])===null,'Other artists keep their system emails');unset($GLOBALS['trb_onboarding_approve_email']);
check_adapter($suppress(null,['to'=>'artist@example.invalid'])===null,'Mail suppression scope is cleared');
echo "First password setup, secure login, retry binding and scoped welcome mail verified.\n";
$GLOBALS['registration_existing']=false;unset($GLOBALS['registration_cookie']);$transients[trb_onboarding_browser_key()]=['session'=>str_repeat('f',64)];
check_adapter(is_wp_error(trb_onboarding_public(new AdapterRequest(['action'=>'register','password'=>'safePW123','repeat_password'=>'safePW123'])))&&!isset($GLOBALS['registration_cookie']),'Nine characters cannot create or authenticate an account');
$short=trb_onboarding_public(new AdapterRequest(['action'=>'register','password'=>'safePW123!','repeat_password'=>'safePW123!']));
check_adapter($short['registered']&&$GLOBALS['registration_insert']['user_pass']==='safePW123!','Ten characters are accepted by the actual registration route');
check_adapter(empty($GLOBALS['approval_notification_suspended']),'Legacy approval callback remains installed outside the new workflow');
