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
$r=['paypal_settings_present'=>count($p)>0,'paypal_setting_keys'=>array_keys($p),'paypal_container_function'=>function_exists('wc_paypal_payments_container'),'stripe_payment_method_configuration'=>$s['upe_checkout_experience_accepted_payments']??null,'stripe_webhook_secret_present'=>strlen((string)($s['webhook_secret']??$s['test_webhook_secret']??''))>16];
if(class_exists('WC_Stripe_API')){$hooks=WC_Stripe_API::request([],'webhook_endpoints','GET');$r['stripe_webhook_list_read']=!is_wp_error($hooks)&&is_object($hooks)&&is_array($hooks->data??null);$r['stripe_live_webhook_enabled']=false;$r['stripe_capture_event_configured']=false;if($r['stripe_webhook_list_read'])foreach($hooks->data as $h){$host=strtolower((string)parse_url((string)($h->url??''),PHP_URL_HOST));if($host==='store.trbrec.com'&&($h->status??'')==='enabled'&&($h->livemode??false)===true){$r['stripe_live_webhook_enabled']=true;$events=(array)($h->enabled_events??[]);$r['stripe_capture_event_configured']=$r['stripe_capture_event_configured']||in_array('*',$events,true)||in_array('payment_intent.succeeded',$events,true);}}}
while(ob_get_level())ob_end_clean();echo json_encode($r);
STORE;
$lines=[];exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>/dev/null',$lines,$exit);$out['store']=$exit===0?json_decode(implode("\n",$lines),true):null;
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
