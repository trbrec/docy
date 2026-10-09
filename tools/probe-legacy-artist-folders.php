<?php
/** Read-only discovery in artistic upload indexes and reviewed discography roots. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}ini_set('display_errors','0');
$revision=$argv[1]??'';$mode=$argv[2]??'probe';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
$trbProbePrivate='/home/customer/www/new1.trbrec.com/private/trb-site-studio';
$trbProbeFile=$trbProbePrivate.'/legacy-artistic-folder-probe.json';
define('WP_USE_THEMES',false);
if($mode==='import'){require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';$d=json_decode((string)@file_get_contents($trbProbeFile),true);if(!is_array($d)||($d['revision']??'')!==$revision)exit(3);unset($d['queue'],$d['seen']);update_option('trb_studio_legacy_folder_probe',$d,false);exit;}
require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
$names=[28=>['Carmine Granato'],41=>['Edmondo Romano','Simona Fasano'],46=>['Emiliano Di Meo'],70=>['diiego'],90=>['Leonardo M Facinelli','Leonardo Facinelli','Leonardo Maria Facinelli'],94=>['Fabio Guglielmo Anastasi','Fabio Anastasi'],103=>['Gli Irati'],128=>['Alessio de Franzoni','Alessio de Fanzoni','FaDe'],181=>['Valerio Di Paolo','Urbania']];
$normalize=static fn($s)=>trim(preg_replace('/[^a-z0-9]+/',' ',remove_accents(mb_strtolower($s,'UTF-8'))));
$matches=static function($path)use($names,$normalize){$k=$normalize($path);$out=[];foreach($names as $id=>$aliases)foreach($aliases as $alias){$parts=explode(' ',$normalize($alias));$forms=[$parts];if(count($parts)===2)$forms[]=array_reverse($parts);foreach($forms as $form)if(preg_match('/(?<![a-z0-9])'.implode('\\s*',array_map(static fn($p)=>preg_quote($p,'/'),$form)).'(?![a-z0-9])/',$k)){$out[$id]=$alias;break 2;}}return $out;};
$excluded=static fn($path)=>(bool)preg_match('/contract|contratt|identity|passport|identit|anagrafic|privacy|fattur|invoice|passaporto|liberatori|ctr.firmat/i',$path);
$read=static function($path){
 $s=trb_demo_settings();$endpoint=$s['webdav_endpoint']??'';if(strtolower((string)wp_parse_url($endpoint,PHP_URL_SCHEME))!=='https')return ['error'=>'https_required'];
 $r=wp_remote_request(trb_demo_remote_url($endpoint,$path).'/', ['method'=>'PROPFIND','headers'=>['Authorization'=>'Basic '.base64_encode(($s['pcloud_user']??'').':'.($s['pcloud_pass']??'')),'Depth'=>'1','Content-Type'=>'application/xml'],'body'=>'<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/><d:getcontentlength/></d:prop></d:propfind>','timeout'=>30,'redirection'=>0]);
 if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==207)return ['error'=>is_wp_error($r)?$r->get_error_code():'http_'.wp_remote_retrieve_response_code($r)];
 $body=wp_remote_retrieve_body($r);if(strlen($body)>8*1024*1024||stripos($body,'<!DOCTYPE')!==false||stripos($body,'<!ENTITY')!==false)return ['error'=>'xml_rejected'];
 $xml=new DOMDocument();$old=libxml_use_internal_errors(true);$ok=$xml->loadXML($body,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($old);if(!$ok)return ['error'=>'xml_invalid'];
 $xp=new DOMXPath($xml);$xp->registerNamespace('d','DAV:');$items=[];
 foreach($xp->query('//d:response') as $entry){$href=$xp->evaluate('string(d:href)',$entry);$p='/'.trim(rawurldecode((string)wp_parse_url($href,PHP_URL_PATH)),'/');if($p===$path||!str_starts_with($p,$path.'/')||str_contains($p,'/../')||str_contains($p,'/./'))continue;$items[]=['path'=>$p,'folder'=>$xp->query('d:propstat[d:status[contains(.,"200")]]/d:prop/d:resourcetype/d:collection',$entry)->length>0,'size'=>(int)$xp->evaluate('string(d:propstat[d:status[contains(.,"200")]]/d:prop/d:getcontentlength)',$entry)];}
 return ['items'=>$items];
};
$roots=['/Discografia - TRB rec','/Discografia - DDB','/Upload files - TRB rec/Media/Biographies','/Upload files - TRB rec/Media/Photos'];
$d=['revision'=>$revision,'roots'=>$roots,'matches'=>[],'queue'=>[],'seen'=>[],'errors'=>[],'folders'=>0,'files'=>0];
foreach($roots as $root){$r=$read($root);if(isset($r['error'])){$d['errors'][]=['path'=>$root,'code'=>$r['error']];continue;}foreach($r['items'] as $item){if(!$item['folder']||$excluded($item['path']))continue;$ids=$matches($item['path']);if($ids)$d['queue'][]=['path'=>$item['path'],'artists'=>$ids];}}
$started=time();
while($d['queue']&&$d['folders']<600&&time()-$started<180){$item=array_shift($d['queue']);$path=$item['path'];if(isset($d['seen'][$path])||$excluded($path))continue;$d['seen'][$path]=true;$r=$read($path);++$d['folders'];if(isset($r['error'])){$d['errors'][]=['path'=>$path,'code'=>$r['error']];continue;}
 foreach($r['items'] as $file){if($excluded($file['path']))continue;if($file['folder']){$d['queue'][]=['path'=>$file['path'],'artists'=>$item['artists']];continue;}++$d['files'];$ext=strtolower(pathinfo($file['path'],PATHINFO_EXTENSION));if(!in_array($ext,['jpg','jpeg','png','webp','pdf','doc','docx','txt','rtf'],true))continue;foreach($item['artists'] as $id=>$alias)$d['matches'][$id][$file['path']]=['path'=>$file['path'],'size'=>$file['size'],'extension'=>$ext,'matched_name'=>$alias];}
}
$d['complete']=!$d['queue']&&!$d['errors'];$d['pending_folders']=count($d['queue']);$d['at']=gmdate('c');$json=wp_json_encode($d);$tmp=$trbProbeFile.'.tmp';if(file_put_contents($tmp,$json)!==strlen($json))exit(4);chmod($tmp,0600);if(!rename($tmp,$trbProbeFile))exit(5);
