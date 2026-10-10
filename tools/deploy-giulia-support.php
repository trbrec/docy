<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$stage='bootstrap';
set_exception_handler(static function($e)use(&$stage){fwrite(STDERR,'GIULIA_DEPLOY_FAILED stage='.$stage.' type='.get_class($e).' code='.(string)$e->getCode()."\n");exit(1);});
$bundle=dirname(__DIR__);$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision))exit(2);
$crm='/home/customer/www/crm.trbrec.com/public_html';$private=dirname($crm).'/private';
if(!is_dir($private)&&!mkdir($private,0700,true))throw new RuntimeException();
$lock=fopen($private.'/giulia-deploy.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException();
$backup=$private.'/giulia-backup-'.$revision;if(!is_dir($backup)&&!mkdir($backup,0700,true))throw new RuntimeException();
$stage='configuration';$config=$private.'/giulia-support.json';
if(!is_file($config)) { $value=json_encode(['key'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);if(file_put_contents($config,$value,LOCK_EX)!==strlen($value))throw new RuntimeException();chmod($config,0600); }
$stage='database';
require_once $crm.'/app/Core.php';\TrbCrm\Env::load($crm.'/.env');
require_once $bundle.'/integrations/giulia/GiuliaSupport.php';
\TrbCrm\GiuliaSupport::install(\TrbCrm\Database::connection());
$stage='entry';$indexPath=$crm.'/index.php';$index=(string)file_get_contents($indexPath);
$anchor='$router->dispatch($method,rtrim($path,\'/\')?:\'/\');';
$hook="require_once __DIR__.'/app/GiuliaSupport.php';\nif(\\TrbCrm\\GiuliaSupport::dispatch(\$method,rtrim(\$path,'/')?:'/'))exit;\n";
if(!str_contains($index,"require_once __DIR__.'/app/GiuliaSupport.php';")) {if(substr_count($index,$anchor)!==1)throw new RuntimeException();$index=str_replace($anchor,$hook.$anchor,$index);}
$changes=[$crm.'/app/GiuliaSupport.php'=>(string)file_get_contents($bundle.'/integrations/giulia/GiuliaSupport.php'),$indexPath=>$index];$original=[];
$stage='validate';foreach($changes as $path=>$next){$original[$path]=is_file($path)?file_get_contents($path):null;$staged=$backup.'/'.basename($path).'.next';file_put_contents($staged,$next);exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($staged).' 2>&1',$output,$status);if($status!==0)throw new RuntimeException();if($original[$path]!==null&&!is_file($backup.'/'.basename($path).'.previous'))file_put_contents($backup.'/'.basename($path).'.previous',$original[$path]);}
$stage='activate';try {foreach($changes as $path=>$next){$temp=$path.'.giulia-next';if(file_put_contents($temp,$next)!==strlen($next)||!rename($temp,$path))throw new RuntimeException();}if(!hash_equals(hash('sha256',$changes[$crm.'/app/GiuliaSupport.php']),hash_file('sha256',$crm.'/app/GiuliaSupport.php')))throw new RuntimeException();}
catch(Throwable $e){foreach($original as $path=>$content){if($content===null){if(is_file($path))unlink($path);}else file_put_contents($path,$content);}throw $e;}
try {
$stage='live-read-check';
$db=\TrbCrm\Database::connection();$fixture=$db->query("SELECT s.contract_number,COALESCE(si.first_name,c.first_name) first_name,COALESCE(si.last_name,c.last_name) last_name FROM submissions s JOIN contacts c ON c.id=s.contact_id LEFT JOIN submission_identity_snapshots si ON si.submission_id=s.id WHERE TRIM(COALESCE(si.first_name,c.first_name,''))<>'' AND TRIM(COALESCE(si.last_name,c.last_name,''))<>'' AND TRIM(COALESCE(s.contract_number,''))<>'' AND (SELECT COUNT(*) FROM submissions sx WHERE UPPER(REPLACE(TRIM(sx.contract_number),' ',''))=UPPER(REPLACE(TRIM(s.contract_number),' ','')))=1 ORDER BY (s.source_tab='CRM_TEST_PERMANENT') DESC,(c.email_normalized='andrea.tognassi@trbrec.com') DESC,s.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$fixture){$stage='fixture-missing';throw new RuntimeException();}
$key=json_decode((string)file_get_contents($config),true,16,JSON_THROW_ON_ERROR)['key'];
$context=['caller_id'=>'giulia_deploy_'.$revision,'conversation_id'=>'conv_deploy_'.$revision,'agent_id'=>\TrbCrm\GiuliaSupport::AGENT,'text_only'=>true];
$stage='fixture-match';$db->beginTransaction();try{$result=(new \TrbCrm\GiuliaSupport($db,$key))->handle($context+$fixture);if(($result['verified']??false)!==true){$stage='fixture-not-verified';throw new RuntimeException();}}finally{$db->rollBack();}
$stage='live-http-check';
$http=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nX-TRB-Giulia-Key: ".$key."\r\n",'content'=>json_encode($context), 'timeout'=>15,'ignore_errors'=>true]]);
$response=json_decode((string)file_get_contents('https://crm.trbrec.com/api/giulia/support',false,$http),true);
if(($response['reason']??'')!=='identity_required')throw new RuntimeException();
} catch(Throwable $e) {foreach($original as $path=>$content){if($content===null){if(is_file($path))unlink($path);}else file_put_contents($path,$content);}throw $e;}
file_put_contents($private.'/giulia-deployed-sha',$revision);echo 'GIULIA_DEPLOY_OK revision='.$revision."\n";

