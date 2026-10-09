<?php
/** CLI-only, read-only pCloud inventory. Credentials stay in the existing WordPress transport. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$revision=$argv[1]??'';$mode=$argv[2]??'scan';$theme=dirname(__DIR__);
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents($theme.'/.trb-deployed-sha'))!==$revision)exit(2);
$trbLegacyPrivate='/home/customer/www/new1.trbrec.com/private/trb-site-studio';
if(!is_dir($trbLegacyPrivate)||is_link($trbLegacyPrivate))exit(3);
$trbLegacyInventoryFile=$trbLegacyPrivate.'/legacy-artistic-inventory.json';
define('WP_USE_THEMES',false);
if($mode==='import'){
 require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';
 $d=json_decode((string)@file_get_contents($trbLegacyInventoryFile),true);if(!is_array($d)||($d['root']??'')!=='/Upload files - TRB rec'){update_option('trb_studio_legacy_material_audit_status',['code'=>is_file($trbLegacyInventoryFile)?'inventory_invalid':'inventory_missing','at'=>gmdate('c')],false);exit(4);}
 unset($d['queue'],$d['seen']);update_option('trb_studio_legacy_material_audit',$d,false);exit;
}
require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
if(!function_exists('trb_demo_webdav_request'))exit(5);
$root='/Upload files - TRB rec';
$artists=[28=>['Carmine Granato'],41=>['Edmondo Romano','Simona Fasano'],46=>['Emiliano Di Meo'],70=>['diiego'],90=>['Leonardo M Facinelli','Leonardo Facinelli'],94=>['Fabio Guglielmo Anastasi','Fabio Anastasi'],103=>['Gli Irati'],128=>['Alessio de Franzoni','Alessio de Fanzoni','FaDe'],181=>['Valerio Di Paolo']];
$key=static function($s){$s=remove_accents(mb_strtolower($s,'UTF-8'));return trim(preg_replace('/[^a-z0-9]+/',' ',$s));};
$propfind=static function($path){
 $settings=trb_demo_settings();$endpoint=$settings['webdav_endpoint']??'';
 if(strtolower((string)wp_parse_url($endpoint,PHP_URL_SCHEME))!=='https')return new WP_Error('endpoint_https_required');
 // Collection URLs require a final slash. Keep the configured origin and
 // authentication and never follow a redirect to another server.
 return wp_remote_request(trb_demo_remote_url($endpoint,$path).'/',[
  'method'=>'PROPFIND','headers'=>['Authorization'=>'Basic '.base64_encode(($settings['pcloud_user']??'').':'.($settings['pcloud_pass']??'')),'Depth'=>'1','Content-Type'=>'application/xml'],
  'body'=>'<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/><d:getcontentlength/><d:getcontenttype/></d:prop></d:propfind>','timeout'=>45,'redirection'=>0,
 ]);
};
$d=is_file($trbLegacyInventoryFile)?json_decode((string)file_get_contents($trbLegacyInventoryFile),true):null;
if(!is_array($d)||($d['root']??'')!==$root)$d=['root'=>$root,'queue'=>[$root],'seen'=>[],'matches'=>[],'errors'=>[],'folders'=>0,'files'=>0,'complete'=>false];
foreach($d['errors'] as $i=>$error)if(($error['code']??'')==='http_301'){if(!in_array($error['path'],$d['queue'],true))$d['queue'][]=$error['path'];unset($d['errors'][$i]);}
$d['errors']=array_values($d['errors']);
$lock=fopen($trbLegacyPrivate.'/legacy-artistic-inventory.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(6);
$save=static function()use(&$d,$trbLegacyInventoryFile){$d['at']=gmdate('c');$tmp=$trbLegacyInventoryFile.'.tmp';$bytes=wp_json_encode($d);if(file_put_contents($tmp,$bytes)!==strlen($bytes))exit(7);chmod($tmp,0600);if(!rename($tmp,$trbLegacyInventoryFile))exit(8);};
$started=time();$processed=0;
while($d['queue']&&$processed<180&&time()-$started<100){
 $path=array_shift($d['queue']);if(isset($d['seen'][$path]))continue;
 $response=$propfind($path);
 ++$processed;
 if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==207){$d['errors'][]=['path'=>$path,'code'=>is_wp_error($response)?$response->get_error_code():'http_'.wp_remote_retrieve_response_code($response)];continue;}
 $body=wp_remote_retrieve_body($response);if(strlen($body)>8*1024*1024||stripos($body,'<!DOCTYPE')!==false||stripos($body,'<!ENTITY')!==false){$d['errors'][]=['path'=>$path,'code'=>'xml_rejected'];continue;}
 $xml=new DOMDocument();$previous=libxml_use_internal_errors(true);$valid=$xml->loadXML($body,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);
 if(!$valid){$d['errors'][]=['path'=>$path,'code'=>'xml_invalid'];continue;}
 $d['seen'][$path]=true;++$d['folders'];$xp=new DOMXPath($xml);$xp->registerNamespace('d','DAV:');
 foreach($xp->query('//d:response') as $entry){
  $href=$xp->evaluate('string(d:href)',$entry);$remote=rawurldecode((string)wp_parse_url($href,PHP_URL_PATH));$remote='/'.trim($remote,'/');
  if($remote===$path||!str_starts_with($remote,$path.'/')||!str_starts_with($remote,$root.'/')||str_contains($remote,'/../')||str_contains($remote,'/./'))continue;
  if(preg_match('/contratt|anagrafic|document[oi].*ident|codice.fiscale|privacy|fattur|passaporto|carta.ident|dati.personali/i',$remote))continue;
  $folder=$xp->query('d:propstat[d:status[contains(.,"200")]]/d:prop/d:resourcetype/d:collection',$entry)->length>0;
  if($folder){if(!isset($d['seen'][$remote])&&!in_array($remote,$d['queue'],true))$d['queue'][]=$remote;continue;}
  ++$d['files'];$extension=strtolower(pathinfo($remote,PATHINFO_EXTENSION));if(!in_array($extension,['jpg','jpeg','png','webp','pdf','doc','docx','txt','rtf'],true))continue;
  $normalized=' '.$key($remote).' ';
  foreach($artists as $id=>$names)foreach($names as $name){
   if(!str_contains($normalized,' '.$key($name).' '))continue;
   $d['matches'][$id][$remote]=['path'=>$remote,'extension'=>$extension,'size'=>(int)$xp->evaluate('string(d:propstat[d:status[contains(.,"200")]]/d:prop/d:getcontentlength)',$entry),'matched_name'=>$name];break;
  }
 }
 if($processed%10===0)$save();
}
$d['complete']=!$d['queue']&&!$d['errors'];$d['pending_folders']=count($d['queue']);$save();
