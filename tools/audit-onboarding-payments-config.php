<?php
/** Read-only connection/model/gateway audit. Never creates an order or charges. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');$level=ob_get_level();ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Onboarding configuration audit unconfirmed.\n");exit(1);});
$crm='/home/customer/www/crm.trbrec.com/public_html';
require_once $crm.'/app/Core.php';\TrbCrm\Env::load($crm.'/.env');require_once $crm.'/app/OnboardingTransport.php';require_once $crm.'/app/OnboardingContractCatalog.php';
$health=\TrbCrm\OnboardingTransport::script((string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_URL',''),(string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_SECRET',''),['action'=>'crm_onboarding_health']);
$out=['models'=>[]];
foreach(\TrbCrm\OnboardingContractCatalog::all() as $model){$a=$health['models'][$model['template_key']]??[];if(($a['id']??'')!==$model['template_document_id']||($a['anchors']??false)!==true||!preg_match('/^[a-f0-9]{64}$/D',(string)($a['sha256']??'')))throw new RuntimeException('Model unconfirmed');$out['models'][$model['template_key']]=$a['sha256'];}
$code= <<<'CODE'
$_SERVER['HTTP_HOST']='store.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';define('WP_USE_THEMES',false);define('DISABLE_WP_CRON',true);ob_start();require '/home/customer/www/store.trbrec.com/public_html/wp-load.php';
$gateways=WC()->payment_gateways()->payment_gateways();$result=[];
foreach(['ppcp-gateway','ppcp-credit-card-gateway','ppcp-card-button-gateway','stripe','woocommerce_payments','paypal','bacs'] as $id){$g=$gateways[$id]??null;$result[$id]=['installed'=>(bool)$g,'enabled'=>$g&&$g->enabled==='yes'];}
$bank=false;foreach((array)get_option('woocommerce_bacs_accounts',[]) as $a){$iban=preg_replace('/\s+/','',strtoupper((string)($a['iban']??'')));if(preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/D',$iban)&&trim((string)($a['account_name']??''))!=='')$bank=true;}
$result['bank_details_present']=$bank;$result['currency_eur']=get_woocommerce_currency()==='EUR';$result['onboarding_enabled']=(bool)get_option('trb_onboarding_payments_enabled');ob_end_clean();echo json_encode($result);
CODE;
exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>/dev/null',$lines,$exit);if($exit!==0||!is_array($result=json_decode(implode("\n",$lines),true)))throw new RuntimeException('Store unconfirmed');$out['store']=$result;while(ob_get_level()>$level)ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
