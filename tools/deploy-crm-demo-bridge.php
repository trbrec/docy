<?php
/** Install only the reviewed Demo field patch; never load the production CRM or its database. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
ini_set('display_errors','0');
set_exception_handler(static function(){echo "CRM_DEMO_BRIDGE_FAILED\n";exit(1);});
require __DIR__.'/crm-demo-source-patch.php';
$root='/home/customer/www/crm.trbrec.com';
$target=$root.'/public_html/app/SubmissionRepository.php';
$backup=$root.'/private/demo-bridge-'.$revision;
if(!is_dir($backup)&&!mkdir($backup,0700,true))throw new RuntimeException('Backup unavailable');
$lock=fopen($root.'/private/demo-bridge-deploy.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Deployment busy');
if(!is_file($target)||is_link($target))throw new RuntimeException('Source unavailable');
$original=file_get_contents($target);
if(!is_string($original)||$original==='')throw new RuntimeException('Source unavailable');
$next=trb_crm_demo_source_patch($original);
if($next===$original){echo "CRM_DEMO_BRIDGE_ALREADY_INSTALLED\n";exit;}
$stage=$backup.'/SubmissionRepository.php.new';
if(file_put_contents($stage,$next)!==strlen($next))throw new RuntimeException('Stage failed');
chmod($stage,0600);
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($stage).' 2>&1',$out,$code);
if($code!==0)throw new RuntimeException('Syntax failed');
if(!hash_equals(hash('sha256',$original),(string)hash_file('sha256',$target)))throw new RuntimeException('Concurrent edit');
$before=$backup.'/SubmissionRepository.php.before';
if(file_put_contents($before,$original)!==strlen($original))throw new RuntimeException('Backup failed');
chmod($before,0600);chmod($stage,0644);
if(!rename($stage,$target))throw new RuntimeException('Install failed');
if(function_exists('opcache_invalidate'))opcache_invalidate($target,true);
echo "CRM_DEMO_BRIDGE_INSTALLED\n";
