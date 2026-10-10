<?php
/** CLI-only installation using the established revision-bound SSH deployment. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$deployStage='bootstrap';
set_exception_handler(static function($e){global $deployStage,$theme,$revision;if(isset($theme,$revision)&&preg_match('/^[a-f0-9]{40}$/D',$revision))file_put_contents($theme.'/.trb-onboarding-stage-'.$revision,$deployStage);fwrite(STDERR,'Onboarding installation not confirmed at '.$deployStage.".\n");exit(1);});
$revision=$argv[1]??'';$theme=dirname(__DIR__);
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||isset($argv[2]))exit(2);
function onboarding_stage(string $value): void{global $deployStage,$theme,$revision;$deployStage=$value;file_put_contents($theme.'/.trb-onboarding-stage-'.$revision,$value);}
onboarding_stage('revision');
if(trim((string)@file_get_contents($theme.'/.trb-deployed-sha'))!==$revision)exit(2);
onboarding_stage('bootstrap');
register_shutdown_function(static function(){global $deployStage,$theme,$revision;file_put_contents($theme.'/.trb-onboarding-stage-'.$revision,$deployStage);});
$crm='/home/customer/www/crm.trbrec.com/public_html';$private='/home/customer/www/artist.trbrec.com/private';
$backup=$private.'/onboarding-'.$revision;
if(!is_dir($backup)&&!mkdir($backup,0700,true))throw new RuntimeException('Backup unavailable');
$lock=fopen($private.'/onboarding-deploy.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Busy');
onboarding_stage('crm-source-guard');
require_once __DIR__.'/crm-module-source-guard.php';
$crmSources=[];
foreach(glob($theme.'/integrations/onboarding/crm/*.php') as $file)$crmSources[$crm.'/app/'.basename($file)]=$file;
$crmSources[$crm.'/assets/onboarding.css']=$theme.'/integrations/onboarding/crm/onboarding.css';
trb_crm_module_source_guard($crmSources);
function onboarding_wp(string $root,string $code): array{
    $script='define("WP_USE_THEMES",false);define("DISABLE_WP_CRON",true);require '.var_export($root.'/wp-load.php',true).';'.$code;
    exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($script).' 2>/dev/null',$out,$status);
    $result=json_decode(implode("\n",$out),true);
    if($status!==0||!is_array($result)){global $deployStage;$deployStage.=$status!==0?'-wp-exit':'-wp-json';throw new RuntimeException('WordPress operation unconfirmed');}return $result;
}
$storeRoot='/home/customer/www/store.trbrec.com/public_html';$portalRoot='/home/customer/www/artist.trbrec.com/public_html';
onboarding_stage('store-bootstrap');
$store=onboarding_wp($storeRoot,'echo json_encode(["directory"=>get_stylesheet_directory(),"bridge"=>function_exists("trb_store_dds_bridge_secret")&&strlen(trb_store_dds_bridge_secret())>=32,"woocommerce"=>function_exists("wc_create_order")]);');
if(!$store['bridge']||!$store['woocommerce']||!str_starts_with($store['directory'],$storeRoot.'/wp-content/themes/'))throw new RuntimeException('Store readiness missing');
$changes=[];
$stage=static function(string $path,string $next)use(&$changes,$backup){
    $original=is_file($path)?file_get_contents($path):null;
    if($original===$next)return;
    require_once __DIR__.'/integration-syntax-preflight.php';
    trb_integration_syntax_preflight([$path=>$next]);
    $changes[]=compact('path','next','original');
};
// These sources are checked above and maintained by the separate CRM release.
// Never stage bundled CRM modules over an installed canonical runtime.
onboarding_stage('crm-entry');
$crmIndex=(string)file_get_contents($crm.'/index.php');
if(!str_contains($crmIndex,"require_once __DIR__.'/app/OnboardingRuntime.php';"))throw new RuntimeException('Canonical CRM entry not installed');
onboarding_stage('store-stage');
$stage($store['directory'].'/inc/trb-onboarding-payments.php',(string)file_get_contents($theme.'/integrations/onboarding/store/trb-onboarding-payments.php'));
$functionsPath=$store['directory'].'/functions.php';$functions=(string)file_get_contents($functionsPath);
if(!str_contains($functions,"'/inc/trb-onboarding-payments.php'"))$functions.="\n/** New contract installment checkout. */\nrequire_once get_stylesheet_directory() . '/inc/trb-onboarding-payments.php';\n";
$stage($functionsPath,$functions);
foreach($changes as $c){$current=is_file($c['path'])?file_get_contents($c['path']):null;if($current!==$c['original'])throw new RuntimeException('Concurrent edit');}
 require_once $crm.'/app/Core.php';\TrbCrm\Env::load($crm.'/.env');
 require_once $crm.'/app/OnboardingLedger.php';require_once $crm.'/app/OnboardingDrive.php';require_once $crm.'/app/OnboardingTransport.php';require_once $crm.'/app/OnboardingContractCatalog.php';
 onboarding_stage('archive-config');
 $drive=\TrbCrm\OnboardingTransport::script((string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_URL',''),(string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_SECRET',''),['action'=>'crm_onboarding_drive_health']);
 if(($drive['provider']??'')!=='google_drive'||($drive['private']??false)!==true)throw new RuntimeException('Private Drive archive not ready');
 onboarding_stage('configuration');
 foreach(['ARTIST_PORTAL_SYNC_SECRET','OPENAI_API_KEY','CONTRACT_APPS_SCRIPT_SECRET','APP_KEY'] as $key)if(strlen((string)\TrbCrm\Env::get($key,''))<(in_array($key,['APP_KEY','ARTIST_PORTAL_SYNC_SECRET'],true)?32:16))throw new RuntimeException('Required configuration missing');
 onboarding_stage('mail-health');
 $mail=\TrbCrm\OnboardingTransport::script((string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_URL',''),(string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_SECRET',''),['action'=>'crm_candidate_mail_health']);
 if(($mail['transport']??'')!=='gmail'||($mail['sent_copy']??false)!==true||strtolower((string)($mail['mailbox']??''))!=='andrea.tognassi@trbrec.com')throw new RuntimeException('Adhesion email adapter not ready');
 onboarding_stage('signature-health');
 $health=\TrbCrm\OnboardingTransport::script((string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_URL',''),(string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_SECRET',''),['action'=>'crm_onboarding_health']);
 if(($health['version']??'')!=='2026.2'||($health['otp_configured']??false)!==true)throw new RuntimeException('Signature adapter not ready');
 onboarding_stage('contract-sources');
 foreach(\TrbCrm\OnboardingContractCatalog::all() as $model){$actual=$health['models'][$model['template_key']]??[];if(($actual['id']??'')!==$model['template_document_id']||($actual['anchors']??false)!==true||!hash_equals($model['source_sha256'],(string)($actual['sha256']??'')))throw new RuntimeException('Contract source mismatch');}
 onboarding_stage('portal-health');
 $portal=\TrbCrm\OnboardingTransport::signed('https://artist.trbrec.com/wp-json/trb/v1/onboarding/private',(string)\TrbCrm\Env::get('ARTIST_PORTAL_SYNC_SECRET',''),['action'=>'health'],'onboarding-portal-v1');
 if(($portal['version']??'')!=='2026.2'||empty($portal['crm_configured'])||empty($portal['store_configured'])||empty($portal['approval_plugin']))throw new RuntimeException('Portal adapter not ready');
 onboarding_stage('file-install');
 require_once __DIR__.'/release-file-transaction.php';
 trb_release_file_install($changes,$backup.'/store-files',$storeRoot.'/wp-content/themes');
echo "Onboarding modules installed; authenticated adapters and all contract sources verified.\n";
