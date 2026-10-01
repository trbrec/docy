<?php
/** Revision-bound, read-only model integrity inspection after the activation callout edit. No customer records or document contents. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
set_exception_handler(static function(){fwrite(STDERR,"Contract source hashes unconfirmed.\n");exit(1);});
$theme=dirname(__DIR__);$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents($theme.'/.trb-deployed-sha'))!==$revision)exit(2);
$crm='/home/customer/www/crm.trbrec.com/public_html';
require_once $crm.'/app/Core.php';\TrbCrm\Env::load($crm.'/.env');require_once $crm.'/app/OnboardingTransport.php';require_once $crm.'/app/OnboardingContractCatalog.php';
$health=\TrbCrm\OnboardingTransport::script((string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_URL',''),(string)\TrbCrm\Env::get('CONTRACT_APPS_SCRIPT_SECRET',''),['action'=>'crm_onboarding_health']);
$out=[];
foreach(\TrbCrm\OnboardingContractCatalog::all() as $model){$actual=$health['models'][$model['template_key']]??[];if(($actual['id']??'')!==$model['template_document_id']||($actual['anchors']??false)!==true||!preg_match('/^[a-f0-9]{64}$/D',(string)($actual['sha256']??'')))throw new RuntimeException('Model integrity unconfirmed');$out[$model['template_key']]=$actual['sha256'];}
$path=dirname($crm).'/private/onboarding-source-hashes-'.$revision.'.json';
if(file_put_contents($path,json_encode($out,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Integrity report unavailable');chmod($path,0600);
