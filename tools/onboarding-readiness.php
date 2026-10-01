<?php
/** Read-only CLI readiness report; output is a fixed set of boolean flags. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$checks=[];$readinessBuffer=ob_get_level();$readinessReserve=str_repeat('x',65536);
ob_start();
register_shutdown_function(static function(){
    global $checks,$readinessBuffer,$readinessReserve;
    $readinessReserve=null;while(ob_get_level()>$readinessBuffer)ob_end_clean();
    $allowed=['revision','archive_connection','portal_secret','identity_key','signature_secret','invitation_key','signature_adapter','contract_sources','portal_adapter'];
    $clean=[];foreach($allowed as $key)$clean[$key]=($checks[$key]??false)===true;
    echo json_encode($clean);
});
set_exception_handler(static function($error){exit(1);});
$revision=$argv[1]??'';$theme=dirname(__DIR__);
$checks['revision']=preg_match('/^[a-f0-9]{40}$/D',$revision)&&trim((string)@file_get_contents($theme.'/.trb-deployed-sha'))===$revision;
if(!$checks['revision'])exit(2);
require_once '/home/customer/www/crm.trbrec.com/public_html/app/Core.php';
\TrbCrm\Env::load('/home/customer/www/crm.trbrec.com/public_html/.env');
require_once $theme.'/integrations/onboarding/crm/OnboardingPcloud.php';
require_once $theme.'/integrations/onboarding/crm/OnboardingTransport.php';
require_once $theme.'/integrations/onboarding/crm/OnboardingContractCatalog.php';
try{$archive=new \TrbCrm\OnboardingPcloud('/home/customer/www/crm.trbrec.com/private/pcloud-demo-oauth.json');$checks['archive_connection']=true;}catch(Throwable $ignored){}
foreach(['portal_secret'=>'ARTIST_PORTAL_SYNC_SECRET','identity_key'=>'OPENAI_API_KEY','signature_secret'=>'CONTRACT_APPS_SCRIPT_SECRET','invitation_key'=>'APP_KEY'] as $check=>$key)$checks[$check]=strlen((string)\TrbCrm\Env::get($key,''))>=(in_array($check,['portal_secret','invitation_key'],true)?32:16);
if($checks['signature_secret']){
    try{
        $health=\TrbCrm\OnboardingTransport::script((string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_URL',''),(string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_SECRET',''),['action'=>'crm_onboarding_health']);
        $checks['signature_adapter']=($health['version']??'')==='2026.2'&&($health['otp_configured']??false)===true;
        $checks['contract_sources']=true;
        foreach(\TrbCrm\OnboardingContractCatalog::all() as $model){$actual=$health['models'][$model['template_key']]??[];if(($actual['id']??'')!==$model['template_document_id']||($actual['anchors']??false)!==true||!hash_equals($model['source_sha256'],(string)($actual['sha256']??'')))$checks['contract_sources']=false;}
    }catch(Throwable $ignored){}
}
if($checks['portal_secret']){
    try{
        $portal=\TrbCrm\OnboardingTransport::signed('https://artist.trbrec.com/wp-json/trb/v1/onboarding/private',(string)\TrbCrm\Env::get('ARTIST_PORTAL_SYNC_SECRET',''),['action'=>'health'],'onboarding-portal-v1');
        $checks['portal_adapter']=($portal['version']??'')==='2026.2'&&!empty($portal['crm_configured'])&&!empty($portal['store_configured'])&&!empty($portal['approval_plugin']);
    }catch(Throwable $ignored){}
}
