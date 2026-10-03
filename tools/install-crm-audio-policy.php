<?php
/** Scoped installer: verified audio keeps its origin while remaining sandboxed without scripts. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
set_exception_handler(static function(){fwrite(STDERR,"Audio policy installation unconfirmed.\n");exit(1);});
$path='/home/customer/www/crm.trbrec.com/public_html/pcloud-material.php';
$original=file_get_contents($path);
$old="header('Content-Security-Policy: sandbox');";
$new="header('Content-Security-Policy: sandbox'.(in_array($ext,['mp3','wav','m4a','flac','ogg'],true)?' allow-same-origin':''));";
if(strpos($original,$new)!==false){echo "Audio origin policy already installed.\n";exit;}
if(hash('sha256',$original)!=='695ead14ccde7b0fcaeec947d6ade949d3761ff77b4d49d9f9f0948c553f6f02'||substr_count($original,$old)!==1)throw new RuntimeException('Concurrent source change');
$next=str_replace($old,$new,$original);
$private='/home/customer/www/crm.trbrec.com/private';
$lock=fopen($private.'/audio-policy.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Busy');
$backup=$private.'/pcloud-audio-policy-20261003.before.php';
if(!is_file($backup)){if(file_put_contents($backup,$original)!==strlen($original))throw new RuntimeException('Backup failed');chmod($backup,0600);}
$temp=tempnam($private,'audio-policy-');
if(file_put_contents($temp,$next)!==strlen($next))throw new RuntimeException('Stage failed');
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1',$output,$status);
if($status!==0)throw new RuntimeException('Syntax failed');
if(hash_file('sha256',$path)!==hash('sha256',$original))throw new RuntimeException('Concurrent source change');
chmod($temp,0644);if(!rename($temp,$path))throw new RuntimeException('Install failed');
if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
echo "Scoped audio origin policy installed; authentication, retention and remote streaming preserved.\n";
