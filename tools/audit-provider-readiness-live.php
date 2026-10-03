<?php
/** Read-only provider and worker checks. No payment, mail or cron is executed. */
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Provider readiness audit unconfirmed.\n");exit(1);});
require '/home/customer/www/crm.trbrec.com/public_html/app/Core.php';\TrbCrm\Env::load('/home/customer/www/crm.trbrec.com/public_html/.env');$db=\TrbCrm\Database::connection();
$last=$db->query("SELECT MAX(checked_at) FROM onboarding_worker_checks")->fetchColumn();
$out=['worker_last_check_age_seconds'=>$last?max(0,time()-strtotime($last)):null];
$code= <<<'STORE'
$_SERVER['HTTP_HOST']='store.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';define('WP_USE_THEMES',false);define('DISABLE_WP_CRON',true);ob_start();require '/home/customer/www/store.trbrec.com/public_html/wp-load.php';
$s=(array)get_option('woocommerce_stripe_settings',[]);$p=(array)get_option('woocommerce-ppcp-settings',[]);
$r=['paypal_settings_present'=>count($p)>0,'paypal_setting_keys'=>array_keys($p),'paypal_live'=>!in_array($p['sandbox_on']??false,[true,1,'1','yes'],true),'paypal_intent'=>strtoupper((string)($p['intent']??'')),'stripe_payment_method_configuration'=>$s['upe_checkout_experience_accepted_payments']??null,'stripe_webhook_secret_present'=>strlen((string)($s['webhook_secret']??$s['test_webhook_secret']??''))>16];
if(class_exists('WC_Stripe_API')){$hooks=WC_Stripe_API::request([],'webhook_endpoints','GET');$r['stripe_webhook_list_read']=!is_wp_error($hooks)&&is_object($hooks)&&is_array($hooks->data??null);$r['stripe_live_webhook_enabled']=false;$r['stripe_capture_event_configured']=false;if($r['stripe_webhook_list_read'])foreach($hooks->data as $h){$host=strtolower((string)parse_url((string)($h->url??''),PHP_URL_HOST));if($host==='store.trbrec.com'&&($h->status??'')==='enabled'&&($h->livemode??false)===true){$r['stripe_live_webhook_enabled']=true;$events=(array)($h->enabled_events??[]);$r['stripe_capture_event_configured']=$r['stripe_capture_event_configured']||in_array('*',$events,true)||in_array('payment_intent.succeeded',$events,true);}}}
if($r['paypal_live']){
$client=(string)($p['client_id_production']??'');if($client==='')$client=(string)($p['client_id']??'');$secret=(string)($p['client_secret_production']??'');if($secret==='')$secret=(string)($p['client_secret']??'');
$r['paypal_credentials_present']=$client!==''&&$secret!=='';$r['paypal_api_authenticated']=false;$r['paypal_live_webhook_present']=false;$r['paypal_capture_event_configured']=false;
if($r['paypal_credentials_present']){$token=wp_remote_post('https://api-m.paypal.com/v1/oauth2/token',['timeout'=>20,'redirection'=>0,'headers'=>['Authorization'=>'Basic '.base64_encode($client.':'.$secret),'Content-Type'=>'application/x-www-form-urlencoded'],'body'=>'grant_type=client_credentials']);$json=is_wp_error($token)?null:json_decode(wp_remote_retrieve_body($token),true);$r['paypal_api_authenticated']=is_array($json)&&!empty($json['access_token']);if($r['paypal_api_authenticated']){$hooks=wp_remote_get('https://api-m.paypal.com/v1/notifications/webhooks',['timeout'=>20,'redirection'=>0,'headers'=>['Authorization'=>'Bearer '.$json['access_token']]]);$list=is_wp_error($hooks)?null:json_decode(wp_remote_retrieve_body($hooks),true);$r['paypal_webhook_list_read']=is_array($list)&&isset($list['webhooks']);foreach((array)($list['webhooks']??[]) as $h){if(strtolower((string)parse_url((string)($h['url']??''),PHP_URL_HOST))!=='store.trbrec.com')continue;$r['paypal_live_webhook_present']=true;foreach((array)($h['event_types']??[]) as $e)if(in_array($e['name']??'',['*','PAYMENT.CAPTURE.COMPLETED'],true))$r['paypal_capture_event_configured']=true;}}}
}
unset($r['paypal_setting_keys']);while(ob_get_level())ob_end_clean();echo json_encode($r);
STORE;
$lines=[];exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>/dev/null',$lines,$exit);$out['store']=$exit===0?json_decode(implode("\n",$lines),true):null;
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
