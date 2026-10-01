<?php
/** CLI-only wrapper: emit fixed readiness labels, never child output or errors. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$onboardingStatusRevisionMatch=preg_match('/^[a-f0-9]{40}$/D',$argv[1]??'')&&trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))===($argv[1]??'');
$onboardingStatusSuccess=false;$onboardingStatusBufferLevel=ob_get_level();$onboardingStatusReserve=str_repeat('x',65536);
ob_start();
register_shutdown_function(static function(){
    global $onboardingStatusSuccess,$onboardingStatusBufferLevel,$onboardingStatusReserve,$onboardingStatusRevisionMatch,$deployStage;
    $onboardingStatusReserve=null;$last=error_get_last();
    while(ob_get_level()>$onboardingStatusBufferLevel)ob_end_clean();
    $allowed=['bootstrap','store-bootstrap','store-bootstrap-wp-exit','store-bootstrap-wp-json','crm-entry','crm-navigation','crm-workflow','crm-workflow-repository','crm-workflow-assets','store-stage','activation-snapshot','activation-snapshot-wp-exit','activation-snapshot-wp-json','file-install','database','archive','archive-config','archive-trb','archive-ddb','configuration','signature-health','contract-sources','portal-health','activation','activation-wp-exit','activation-wp-json'];
    $stage=in_array($deployStage??'',$allowed,true)?$deployStage:'unknown';
    if(!$onboardingStatusRevisionMatch)$stage='revision-guard';
    $fatal=$last&&in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true);
    echo json_encode(['success'=>$onboardingStatusSuccess,'stage'=>$stage,'fatal'=>(bool)$fatal,'sapi'=>'cli']);
});
require __DIR__.'/deploy-onboarding.php';
$onboardingStatusSuccess=true;
