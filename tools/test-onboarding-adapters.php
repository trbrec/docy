<?php
/** Synthetic WordPress/WooCommerce boundary tests: no network or real accounts. */
define('ABSPATH',__DIR__);define('HOUR_IN_SECONDS',3600);
class WP_Error{public function __construct(public $code,public $message,public $data=[]){}public function get_error_message(){return $this->message;}}
class WP_User{public $roles=[];public $user_email='artist@example.invalid';public function __construct(public $ID=101){}public function has_cap($cap){return false;}public function add_role($role){$this->roles[]=$role;}}
function is_wp_error($x){return $x instanceof WP_Error;}
function add_action(...$args){}function add_filter(...$args){}
function absint($x){return abs((int)$x);}function sanitize_text_field($x){return trim($x);}function sanitize_file_name($x){return basename($x);}function wp_unslash($x){return $x;}
function wp_json_encode($x,...$args){return json_encode($x,...$args);}function wp_salt($x){return str_repeat('a',64);}function home_url(){return 'https://artist.trbrec.com';}
$meta=[];$options=['trb_candidate_onboarding_enabled'=>true];$transients=[];$user=new WP_User();
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}function add_option($key,$value,...$extra){if(isset($GLOBALS['options'][$key]))return false;$GLOBALS['options'][$key]=$value;return true;}
function get_user_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}function update_user_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
function get_userdata($id){return $id===$GLOBALS['user']->ID?$GLOBALS['user']:false;}function get_transient($key){return $GLOBALS['transients'][$key]??false;}
function trb_crm_connector_settings(){return ['secret'=>str_repeat('s',64)];}function trb_portal_dds_store_secret(){return str_repeat('s',64);}function trb_store_dds_bridge_secret(){return str_repeat('s',64);}
function trb_portal_profiles(){return ['ddb'=>['role'=>'artista_b']];}function pw_new_user_approve(){return new class{public function update_user_status($id,$status){$GLOBALS['approved']=$status;}};}
function trb_release_bridge_contract_term_dates($term){return preg_match('~^\d\d/\d\d/\d\d - \d\d/\d\d/\d\d$~',$term)?[]:new WP_Error('term','Invalid');}
function wp_next_scheduled($x){return true;}function wp_date($format,$stamp,$zone){return (new DateTimeImmutable('@'.$stamp))->setTimezone($zone)->format($format);}
function check_adapter($ok,$message){if(!$ok)throw new RuntimeException($message);}
require dirname(__DIR__).'/inc/trb-candidate-onboarding.php';
require dirname(__DIR__).'/integrations/onboarding/store/trb-onboarding-payments.php';
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
$_COOKIE['__Host-trb_onboarding']=str_repeat('b',64);$transients[trb_onboarding_browser_key()]=['invite'=>str_repeat('c',64),'salt'=>'qa'];check_adapter(trb_onboarding_public(new AdapterRequest(['action'=>'browser_status']))['challenge_pending'],'Email verification resumes after page reload');
$stamp=(string)time();$nonce=str_repeat('d',32);$request=new AdapterRequest([],['x-trb-timestamp'=>$stamp,'x-trb-nonce'=>$nonce,'x-trb-signature'=>'sha256='.hash_hmac('sha256','onboarding-v1|'.$stamp.'|'.$nonce.'|{}',str_repeat('s',64))]);
check_adapter(trb_onboarding_store_permission($request)===true,'Authenticated Store boundary accepted');check_adapter(is_wp_error(trb_onboarding_store_permission($request)),'Store replay rejected');
$order=new class{public $gateway='stripe',$transaction='qa-capture',$date,$amount=49.0,$currency='EUR';public function __construct(){$this->date=new DateTimeImmutable('now');}public function get_meta($key){return 4900;}public function get_payment_method(){return $this->gateway;}public function get_date_paid(){return $this->date;}public function get_transaction_id(){return $this->transaction;}public function get_currency(){return $this->currency;}public function get_total(){return $this->amount;}};
check_adapter(trb_onboarding_store_capture($order)['captured'],'Exact provider capture accepted');$order->gateway='bacs';check_adapter(!trb_onboarding_store_capture($order)['captured'],'Manual bank transfer not capture');$order->gateway='stripe';$order->transaction='';check_adapter(!trb_onboarding_store_capture($order)['captured'],'Transaction proof required');$order->transaction='qa';$order->amount=50;check_adapter(!trb_onboarding_store_capture($order)['captured'],'Gross contract cents enforced');
echo "Onboarding boundary authentication, replay, legacy isolation, profile transfer and capture proof verified.\n";
