<?php
/**
 * Refresh artist deletion and the explicitly corrected duo name after deployment. CLI-only, scoped publication transfer over the existing verified server deployment. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$revision=$argv[1]??'';$mode=$argv[2]??'transfer';
$theme=dirname(__DIR__);$marker=$theme.'/.trb-studio-transfer-phase';$phase='initial';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents($theme.'/.trb-deployed-sha'))!==$revision)exit(2);
register_shutdown_function(static function()use(&$phase,$marker){file_put_contents($marker,$phase);});
set_exception_handler(static function($e){exit(1);});
$root='/home/customer/www/new1.trbrec.com/private/trb-site-studio';
if(!is_dir($root)&&!mkdir($root,0700,true))exit(3);
if(is_link($root))exit(4);
$report=$root.'/transfer-report.json';
if($mode==='transfer'){
 $lock=fopen($root.'/transfer.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(5);
 $phase='export';
 exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($revision).' export >/dev/null 2>&1',$output,$status);
 if($status!==0){
  exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($revision).' report >/dev/null 2>&1');
  exit(6);
 }
 $phase='import';
 passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($revision).' import >/dev/null 2>&1',$status);
 if($status!==0)exit(7);
 $phase='complete';exit;
}
define('WP_USE_THEMES',false);
if($mode==='export'){
 require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
 if(!\TRB\Studio\source_site()||\TRB\Studio\VERSION!=='0.1.8')exit(8);
 // Observed commercial state: processed is the owner-completed CRM release.
 // A signed contract and non-inactive release are independently required by the adapter.
 update_option('trb_studio_distribution_states',['processed'],false);
 $d=['at'=>gmdate('c'),'ok'=>false,'phase'=>'snapshot','code'=>'','mapping'=>[]];
 $mu=WPMU_PLUGIN_DIR.'/trb-z-crm-release-sync-r26.php';
 if(is_file($mu)){
  foreach(file($mu) as $i=>$line){
   if(preg_match('/_trb_crm_workflow_status|_trb_release_upc|workflow_status.*processed|workflow_status.*ready/',$line)&&!preg_match('/secret|token|password|cookie|authorization/i',$line)){
    $d['mapping'][]=['line'=>$i+1,'code'=>trim($line)];
   }
  }
 }
 $saveReport=static function()use(&$d,$report){file_put_contents($report,wp_json_encode($d));chmod($report,0600);};
 try{
  $result=\TRB\Studio\portal_snapshot();
  if(is_wp_error($result)){$d['code']=$result->get_error_code();$saveReport();exit(9);}
  $snapshot=$result->get_data();
  if(!\TRB\Studio\validate_snapshot($snapshot))throw new RuntimeException('snapshot_invalid');
  $generation='generation-'.bin2hex(random_bytes(16));$dir=$root.'/'.$generation;
  if(!mkdir($dir,0700))throw new RuntimeException('bundle_directory');
  $total=0;$d['phase']='assets';
  foreach(['artists'=>'photo','releases'=>'cover'] as $list=>$field)foreach($snapshot[$list] as $item){
   $ref=$item[$field]??null;if(!$ref)continue;
   $req=new WP_REST_Request('GET');$req['kind']=$ref['kind'];$req['id']=$ref['id'];
   $asset=\TRB\Studio\portal_asset($req);if(is_wp_error($asset))throw new RuntimeException($asset->get_error_code());
   $data=$asset->get_data();if(!hash_equals($ref['hash'],$data['hash']??''))throw new RuntimeException('asset_changed');
   $json=wp_json_encode($data);$total+=strlen($json);if($total>512*1024*1024)throw new RuntimeException('bundle_size');
   $path=$dir.'/'.$ref['kind'].'-'.$ref['id'].'-'.$ref['hash'].'.json';
   if(file_put_contents($path,$json)!==strlen($json))throw new RuntimeException('asset_write');chmod($path,0600);
  }
  $json=wp_json_encode($snapshot);if(strlen($json)>20*1024*1024)throw new RuntimeException('snapshot_size');
  if(file_put_contents($dir.'/snapshot.json',$json)!==strlen($json))throw new RuntimeException('snapshot_write');chmod($dir.'/snapshot.json',0600);
  $pointer=wp_json_encode(['generation'=>$generation,'sha256'=>hash('sha256',$json)]);
  $tmp=$root.'/current-'.bin2hex(random_bytes(8)).'.json';
  file_put_contents($tmp,$pointer);chmod($tmp,0600);
  if(!rename($tmp,$root.'/current.json'))throw new RuntimeException('pointer_write');
  $d['ok']=true;$d['phase']='complete';$d['artists']=count($snapshot['artists']);$d['releases']=count($snapshot['releases']);$d['code']='';$saveReport();
  // Retain the prior generation, remove only our older immutable bundle files.
  $dirs=glob($root.'/generation-*',GLOB_ONLYDIR);usort($dirs,static fn($a,$b)=>filemtime($b)<=>filemtime($a));
  foreach(array_slice($dirs,2) as $old){
   if(is_link($old)||!preg_match('/^generation-[a-f0-9]{32}$/D',basename($old)))continue;
   foreach(glob($old.'/*.json') as $file)if(is_file($file)&&!is_link($file))unlink($file);
   @rmdir($old);
  }
 }catch(Throwable $e){
  $d['code']=preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'export_error';$saveReport();exit(10);
 }
 $phase='complete';exit;
}
if(in_array($mode,['import','report'],true)){
 require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';
 if(!\TRB\Studio\destination_site()||\TRB\Studio\VERSION!=='0.1.8')exit(11);
 $d=json_decode((string)file_get_contents($report),true);
 update_option('trb_studio_transfer_report',is_array($d)?$d:['ok'=>false,'code'=>'report_invalid'],false);
 if($mode==='report')exit;
 update_option('trb_studio_transport','private-bundle',false);
 $result=\TRB\Studio\sync_directory();if(is_wp_error($result))exit(12);
 update_option('trb_studio_enabled',true,false);
 if(!wp_next_scheduled('trb_studio_sync'))wp_schedule_event(time()+900,'trb_studio_15min','trb_studio_sync');
 $phase='complete';exit;
}
exit(13);

