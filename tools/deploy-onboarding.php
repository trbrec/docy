<?php
/** CLI-only installation using the established revision-bound SSH deployment. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$deployStage='bootstrap';
set_exception_handler(static function($e){global $deployStage;fwrite(STDERR,'Onboarding installation not confirmed at '.$deployStage.".\n");exit(1);});
$revision=$argv[1]??'';$theme=dirname(__DIR__);
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents($theme.'/.trb-deployed-sha'))!==$revision)exit(2);
$crm='/home/customer/www/crm.trbrec.com/public_html';$private=dirname($crm).'/private';
$backup=$private.'/onboarding-'.$revision;
if(!is_dir($backup)&&!mkdir($backup,0700,true))throw new RuntimeException('Backup unavailable');
$lock=fopen($private.'/onboarding-deploy.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Busy');
function onboarding_wp(string $root,string $code): array{
    $script='define("WP_USE_THEMES",false);require '.var_export($root.'/wp-load.php',true).';'.$code;
    exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($script).' 2>/dev/null',$out,$status);
    $result=json_decode(implode("\n",$out),true);
    if($status!==0||!is_array($result))throw new RuntimeException('WordPress operation unconfirmed');return $result;
}
$storeRoot='/home/customer/www/store.trbrec.com/public_html';$portalRoot='/home/customer/www/artist.trbrec.com/public_html';
$deployStage='store-bootstrap';
$store=onboarding_wp($storeRoot,'echo json_encode(["directory"=>get_stylesheet_directory(),"bridge"=>function_exists("trb_store_dds_bridge_secret")&&strlen(trb_store_dds_bridge_secret())>=32,"woocommerce"=>function_exists("wc_create_order")]);');
if(!$store['bridge']||!$store['woocommerce']||!str_starts_with($store['directory'],$storeRoot.'/wp-content/themes/'))throw new RuntimeException('Store readiness missing');
$deployStage='stage-files';
$changes=[];
$stage=static function(string $path,string $next)use(&$changes,$backup){
    $original=is_file($path)?file_get_contents($path):null;$temp=$backup.'/'.hash('sha256',$path).'.new';
    if(file_put_contents($temp,$next)!==strlen($next))throw new RuntimeException('Stage failed');
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1',$out,$status);if($status!==0)throw new RuntimeException('Syntax check failed');
    $changes[]=compact('path','next','original','temp');
};
foreach(glob($theme.'/integrations/onboarding/crm/*.php') as $file)$stage($crm.'/app/'.basename($file),(string)file_get_contents($file));
$deployStage='crm-routing';
$indexPath=$crm.'/index.php';$index=(string)file_get_contents($indexPath);
$anchor='$router->dispatch($method,rtrim($path,\'/\')?:\'/\');';
$hook="require_once __DIR__.'/app/OnboardingRuntime.php';\nif(\\TrbCrm\\OnboardingRuntime::dispatch(\$method,rtrim(\$path,'/')?:'/'))exit;\n";
if(!str_contains($index,"require_once __DIR__.'/app/OnboardingRuntime.php';")){
 if(substr_count($index,$anchor)!==1)throw new RuntimeException('Entry anchor changed');$index=str_replace($anchor,$hook.$anchor,$index);
}
$stage($indexPath,$index);
$deployStage='crm-navigation';
$viewPath=$crm.'/app/View.php';$view=(string)file_get_contents($viewPath);$anchor='<a href="#contracts" data-view="contracts">Contratti</a>';
if(!str_contains($view,'href="/onboarding"')){if(substr_count($view,$anchor)!==1)throw new RuntimeException('Navigation anchor changed');$view=str_replace($anchor,$anchor.'<a href="/onboarding">Nuove adesioni</a>',$view);}
$stage($viewPath,$view);
$deployStage='store-stage';
$stage($store['directory'].'/inc/trb-onboarding-payments.php',(string)file_get_contents($theme.'/integrations/onboarding/store/trb-onboarding-payments.php'));
$functionsPath=$store['directory'].'/functions.php';$functions=(string)file_get_contents($functionsPath);
if(!str_contains($functions,"'/inc/trb-onboarding-payments.php'"))$functions.="\n/** New contract installment checkout. */\nrequire_once get_stylesheet_directory() . '/inc/trb-onboarding-payments.php';\n";
$stage($functionsPath,$functions);
foreach($changes as $c){$current=is_file($c['path'])?file_get_contents($c['path']):null;if($current!==$c['original'])throw new RuntimeException('Concurrent edit');}
$installed=[];$flag=$private.'/onboarding-enabled.json';$oldFlag=is_file($flag)?file_get_contents($flag):null;
$deployStage='activation-snapshot';
$oldStore=onboarding_wp($storeRoot,'echo json_encode(["enabled"=>(bool)get_option("trb_onboarding_payments_enabled",false)]);');
$oldPortal=onboarding_wp($portalRoot,'echo json_encode(["enabled"=>(bool)get_option("trb_candidate_onboarding_enabled",false),"worker"=>(bool)wp_next_scheduled("trb_onboarding_worker")]);');
$activationTouched=false;
try{
 $deployStage='file-install';
 foreach($changes as $c){if($c['original']!==null){file_put_contents($backup.'/'.hash('sha256',$c['path']).'.before',$c['original']);chmod($backup.'/'.hash('sha256',$c['path']).'.before',0600);}chmod($c['temp'],0644);if(!rename($c['temp'],$c['path']))throw new RuntimeException('Install failed');$installed[]=$c;if(function_exists('opcache_invalidate'))opcache_invalidate($c['path'],true);}
 require_once $crm.'/app/Core.php';\TrbCrm\Env::load($crm.'/.env');
 require_once $crm.'/app/OnboardingLedger.php';require_once $crm.'/app/OnboardingPcloud.php';require_once $crm.'/app/OnboardingTransport.php';require_once $crm.'/app/OnboardingContractCatalog.php';
 $deployStage='database';
 $ledger=new \TrbCrm\OnboardingLedger(\TrbCrm\Database::connection());$ledger->install();
 $deployStage='archive';
 $archive=new \TrbCrm\OnboardingPcloud($private.'/pcloud-demo-oauth.json');
 foreach(['/Discografia - TRB rec','/Discografia - DDB'] as $path)$archive->api('listfolder',['path'=>$path,'recursive'=>0]);
 $deployStage='configuration';
 foreach(['ARTIST_PORTAL_SYNC_SECRET','OPENAI_API_KEY','CONTRACT_APPS_SCRIPT_SECRET','APP_KEY'] as $key)if(strlen((string)\TrbCrm\Env::get($key,''))<(in_array($key,['APP_KEY','ARTIST_PORTAL_SYNC_SECRET'],true)?32:16))throw new RuntimeException('Required configuration missing');
 $deployStage='signature-health';
 $health=\TrbCrm\OnboardingTransport::script((string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_URL',''),(string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_SECRET',''),['action'=>'crm_onboarding_health']);
 if(($health['version']??'')!=='2026.2'||($health['otp_configured']??false)!==true)throw new RuntimeException('Signature adapter not ready');
 $deployStage='contract-sources';
 foreach(\TrbCrm\OnboardingContractCatalog::all() as $model){$actual=$health['models'][$model['template_key']]??[];if(($actual['id']??'')!==$model['template_document_id']||($actual['anchors']??false)!==true||!hash_equals($model['source_sha256'],(string)($actual['sha256']??'')))throw new RuntimeException('Contract source mismatch');}
 $deployStage='portal-health';
 $portal=\TrbCrm\OnboardingTransport::signed('https://artist.trbrec.com/wp-json/trb/v1/onboarding/private',(string)\TrbCrm\Env::get('ARTIST_PORTAL_SYNC_SECRET',''),['action'=>'health'],'onboarding-portal-v1');
 if(($portal['version']??'')!=='2026.2'||empty($portal['crm_configured'])||empty($portal['store_configured'])||empty($portal['approval_plugin']))throw new RuntimeException('Portal adapter not ready');
 if(($argv[2]??'')==='--enable'){
    $deployStage='activation';$activationTouched=true;$next=json_encode(['version'=>'2026.2','enabled'=>true,'revision'=>$revision,'checked_at'=>gmdate('c')],JSON_THROW_ON_ERROR);$temp=$flag.'.new';if(file_put_contents($temp,$next)!==strlen($next))throw new RuntimeException('Flag failed');chmod($temp,0600);if(!rename($temp,$flag))throw new RuntimeException('Flag failed');
    $enabledStore=onboarding_wp($storeRoot,'update_option("trb_onboarding_payments_enabled",true,false);echo json_encode(["enabled"=>(bool)get_option("trb_onboarding_payments_enabled")]);');
    $enabledPortal=onboarding_wp($portalRoot,'update_option("trb_candidate_onboarding_enabled",true,false);if(!wp_next_scheduled("trb_onboarding_worker"))wp_schedule_event(time()+30,"trb_onboarding_ten_minutes","trb_onboarding_worker");echo json_encode(["enabled"=>(bool)get_option("trb_candidate_onboarding_enabled"),"worker"=>(bool)wp_next_scheduled("trb_onboarding_worker")]);');
    if(empty($enabledStore['enabled'])||empty($enabledPortal['enabled'])||empty($enabledPortal['worker']))throw new RuntimeException('Activation unconfirmed');
 }
}catch(Throwable $e){
 if($activationTouched){
    if($oldFlag===null)@unlink($flag);else{file_put_contents($flag,$oldFlag,LOCK_EX);chmod($flag,0600);}
    try{onboarding_wp($storeRoot,'update_option("trb_onboarding_payments_enabled",'.var_export($oldStore['enabled'],true).',false);echo json_encode(["restored"=>true]);');}catch(Throwable $ignored){}
    try{onboarding_wp($portalRoot,'update_option("trb_candidate_onboarding_enabled",'.var_export($oldPortal['enabled'],true).',false);'.(!$oldPortal['worker']?'wp_clear_scheduled_hook("trb_onboarding_worker");':'').'echo json_encode(["restored"=>true]);');}catch(Throwable $ignored){}
 }
foreach(array_reverse($installed) as $c){if($c['original']===null)@unlink($c['path']);else file_put_contents($c['path'],$c['original'],LOCK_EX);if(function_exists('opcache_invalidate'))opcache_invalidate($c['path'],true);}throw $e;}
echo "Onboarding modules installed; authenticated adapters and all contract sources verified.\n";
