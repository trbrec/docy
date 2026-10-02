<?php
/** Read-only final audit: no payments, emails, contracts or account changes. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Final onboarding audit unconfirmed.\n");exit(1);});
$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($theme.'/tools/onboarding-readiness.php').' 61e61b968e8d0f11ad65d3f08e985d8e929e2283 2>/dev/null',$readyLines,$readyExit);
$out=['connections'=>$readyExit===0?json_decode(implode("\n",$readyLines),true):null];
require_once '/home/customer/www/crm.trbrec.com/public_html/app/Core.php';
\TrbCrm\Env::load('/home/customer/www/crm.trbrec.com/public_html/.env');
require_once '/home/customer/www/crm.trbrec.com/public_html/app/OnboardingContractCatalog.php';
$db=\TrbCrm\Database::connection();
$models=[];foreach(\TrbCrm\OnboardingContractCatalog::all() as $m)$models[$m['template_key']]=$m;
$out['pending_practices']=0;$out['pending_stale_sources']=0;$out['pending_unknown_models']=0;$out['pending_source_cases']=[];
foreach($db->query('SELECT snapshot FROM onboarding_practices WHERE signed_at IS NULL AND cancelled_at IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $raw){
 $out['pending_practices']++;$s=json_decode($raw,true);$model=$models[$s['template_key']??'']??null;$out['pending_source_cases'][]=['is_qa'=>str_contains(strtoupper((string)($s['contract_number']??'')),'QA-')||str_contains(strtoupper((string)($s['contract_number']??'')),'NONVALIDO'),'group'=>$s['group_code']??'unknown','source_hash_present'=>preg_match('/^[a-f0-9]{64}$/D',(string)($s['source_sha256']??''))===1,'matches_current'=>$model&&hash_equals($model['source_sha256'],(string)($s['source_sha256']??''))];
 if(!$model){$out['pending_unknown_models']++;continue;}
 if(!hash_equals($model['source_sha256'],(string)($s['source_sha256']??'')))$out['pending_stale_sources']++;
}
$out['uncertain_overdue_emails']=(int)$db->query("SELECT COUNT(*) FROM onboarding_events WHERE kind='overdue_reminder' AND status='uncertain'")->fetchColumn();
$out['pending_signature_dispatches']=(int)$db->query("SELECT COUNT(*) FROM onboarding_signatures WHERE state<>'completed'")->fetchColumn();
$store= <<<'STORE'
$_SERVER['HTTP_HOST']='store.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';define('WP_USE_THEMES',false);define('DISABLE_WP_CRON',true);ob_start();require '/home/customer/www/store.trbrec.com/public_html/wp-load.php';
$gateways=WC()->payment_gateways()->payment_gateways();$s=(array)get_option('woocommerce_stripe_settings',[]);
$r=['stripe_enabled'=>isset($gateways['stripe'])&&$gateways['stripe']->enabled==='yes','paypal_enabled'=>isset($gateways['ppcp-gateway'])&&$gateways['ppcp-gateway']->enabled==='yes','stripe_live_mode'=>($s['testmode']??'no')!=='yes','guest_checkout_enabled'=>get_option('woocommerce_enable_guest_checkout')==='yes','bank_transfer_enabled'=>isset($gateways['bacs'])&&$gateways['bacs']->enabled==='yes','bank_transfer_configured'=>function_exists('trb_onboarding_bank_configured')&&trb_onboarding_bank_configured(),'currency_eur'=>get_woocommerce_currency()==='EUR','onboarding_enabled'=>(bool)get_option('trb_onboarding_payments_enabled')];
if(class_exists('WC_Stripe_API')&&$r['stripe_live_mode']){$a=WC_Stripe_API::request([],'account','GET');$r['stripe_account_read']=!is_wp_error($a)&&is_object($a)&&($a->object??'')==='account';if($r['stripe_account_read']){$r['stripe_charges_enabled']=(bool)($a->charges_enabled??false);$r['stripe_payouts_enabled']=(bool)($a->payouts_enabled??false);$r['stripe_payout_schedule']=$a->settings->payouts->schedule->interval??null;}}
while(ob_get_level())ob_end_clean();echo json_encode($r);
STORE;
$artist= <<<'ARTIST'
$_SERVER['HTTP_HOST']='artist.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';define('WP_USE_THEMES',false);define('DISABLE_WP_CRON',true);ob_start();require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
$url=get_privacy_policy_url();$page=get_post((int)get_option('wp_page_for_privacy_policy'));
$r=['privacy_url_present'=>$url!=='','privacy_page_published'=>$page&&$page->post_status==='publish','worker_scheduled'=>(bool)wp_next_scheduled('trb_onboarding_worker'),'onboarding_enabled'=>function_exists('trb_onboarding_enabled')&&trb_onboarding_enabled()];
while(ob_get_level())ob_end_clean();echo json_encode($r);
ARTIST;
foreach(['store'=>$store,'artist'=>$artist] as $key=>$code){$lines=[];exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>/dev/null',$lines,$exit);$out[$key]=$exit===0?json_decode(implode("\n",$lines),true):null;}
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
