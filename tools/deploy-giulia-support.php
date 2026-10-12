<?php
declare(strict_types=1);
/** Read-only verification. The separate CRM task owns installation and migrations. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$stage='bootstrap';
set_exception_handler(static function($e)use(&$stage){fwrite(STDERR,'GIULIA_VERIFY_FAILED stage='.$stage."\n");exit(1);});
$bundle=dirname(__DIR__);$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents($bundle.'/.trb-deployed-sha'))!==$revision)exit(2);
$crm='/home/customer/www/crm.trbrec.com/public_html';
$stage='crm-source-guard';
require_once __DIR__.'/crm-module-source-guard.php';
trb_crm_module_source_guard($crm,dirname($crm).'/private/canonical-release.json',['app/GiuliaSupport.php']);
require_once $crm.'/app/GiuliaSupport.php';
$stage='existing-configuration';
$config=dirname($crm).'/private/giulia-support.json';
if(!is_file($config)||is_link($config))throw new RuntimeException();
$key=json_decode((string)file_get_contents($config),true,16,JSON_THROW_ON_ERROR)['key']??'';
if(!is_string($key)||strlen($key)<32)throw new RuntimeException();
$stage='live-http-check';
// A request with no artist identity must never return an artist record.
$context=['caller_id'=>'giulia_verify_'.$revision,'conversation_id'=>'conv_verify_'.$revision,'agent_id'=>\TrbCrm\GiuliaSupport::AGENT,'text_only'=>true];
$http=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nX-TRB-Giulia-Key: ".$key."\r\n",'content'=>json_encode($context,JSON_THROW_ON_ERROR),'timeout'=>15,'ignore_errors'=>true]]);
$response=json_decode((string)file_get_contents('https://crm.trbrec.com/api/giulia/support',false,$http),true);
if(($response['reason']??'')!=='identity_required')throw new RuntimeException();
echo 'GIULIA_VERIFY_OK revision='.$revision."\n";
