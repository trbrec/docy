<?php
/** CLI-only, guarded installation of read-path performance fixes. No production data is printed or exported. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
set_exception_handler(static function($error){fwrite(STDERR,"CRM performance installation failed.\n");exit(1);});
$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
require __DIR__.'/crm-performance-patch.php';
$root='/home/customer/www/crm.trbrec.com/public_html';
$private=dirname($root).'/private';
$backup=$private.'/performance-'.$revision;
if(!is_dir($backup)&&!mkdir($backup,0700,true))throw new RuntimeException('Backup unavailable');
$lock=fopen($private.'/performance-deploy.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Busy');
$files=[];
foreach(['Controller.php'=>'controller','SubmissionRepository.php'=>'repository'] as $file=>$component){
 $path=$root.'/app/'.$file;$original=file_get_contents($path);
 if(!is_string($original)||$original==='')throw new RuntimeException('Missing source');
 $next=trb_crm_performance_patch($original,$component);
 $temp=$backup.'/'.$file.'.new';
 if(file_put_contents($temp,$next)!==strlen($next))throw new RuntimeException('Stage failed');
 exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1',$output,$status);
 if($status!==0)throw new RuntimeException('Syntax validation failed');
 $files[]=compact('path','original','temp','file');
}
foreach($files as $entry)if(!hash_equals(hash('sha256',$entry['original']),hash_file('sha256',$entry['path'])))throw new RuntimeException('Concurrent edit');
foreach($files as $entry){
 if(file_put_contents($backup.'/'.$entry['file'].'.before',$entry['original'])!==strlen($entry['original']))throw new RuntimeException('Backup failed');
 chmod($backup.'/'.$entry['file'].'.before',0600);
 chmod($entry['temp'],0644);
 if(!rename($entry['temp'],$entry['path']))throw new RuntimeException('Installation failed');
 if(function_exists('opcache_invalidate'))opcache_invalidate($entry['path'],true);
}
echo "CRM read-path performance patch installed and syntax verified.\n";
