<?php
/** Read-only portal inspection; the report stays on the server, outside public files. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
$mode=$argv[2]??'';
$private='/home/customer/www/artist.trbrec.com/private';
$report=$private.'/trb-studio-inspection.json';
if($mode==='source'){
 define('WP_USE_THEMES',false);require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
 $d=['revision'=>$revision,'profiles'=>[],'approval'=>[],'artist_errors'=>[],'releases'=>[],'meta_keys'=>[],'adapter_lines'=>[]];
 foreach(get_users(['number'=>1001]) as $u){
  try{
   $p=function_exists('trb_portal_user_profile')?trb_portal_user_profile($u):'missing';$p=$p?:'none';
   $d['profiles'][$p]=($d['profiles'][$p]??0)+1;
   if($p!=='trb')continue;
   $status=function_exists('trb_release_bridge_access_status')?trb_release_bridge_access_status($u->ID):get_user_meta($u->ID,'pw_user_status',true);
   $status=sanitize_key((string)$status);$d['approval'][$status?:'empty']=($d['approval'][$status?:'empty']??0)+1;
   \TRB\Studio\portal_artist_allowed($u);
  }catch(Throwable $e){$d['artist_errors'][get_class($e).':'.preg_replace('/[^a-zA-Z0-9_(): \\-]/','',$e->getMessage())]=true;}
 }
 foreach(get_posts(['post_type'=>'trb_release','post_status'=>'any','posts_per_page'=>2001]) as $p){
  $profile=trb_portal_user_profile(get_userdata($p->post_author));
  if($profile!=='trb'||trb_portal_release_is_qa($p->ID))continue;
  $row=[];
  foreach(['_trb_crm_workflow_status','_trb_release_pipeline_status','_trb_contract_state','_trb_release_intake_phase'] as $key)$row[$key]=sanitize_key((string)get_post_meta($p->ID,$key,true));
  $fingerprint=wp_json_encode($row);$d['releases'][$fingerprint]=($d['releases'][$fingerprint]??0)+1;
  foreach(array_keys(get_post_meta($p->ID)) as $key)if(preg_match('/upc|workflow|distribution|release_date|original_date/',$key))$d['meta_keys'][$key]=($d['meta_keys'][$key]??0)+1;
 }
 foreach(glob(WPMU_PLUGIN_DIR.'/*.php') as $file){
  if(!preg_match('/crm.*release|release.*crm/',basename($file)))continue;
  $lines=file($file);foreach($lines as $i=>$line)if(preg_match('/_trb_crm_workflow_status|_trb_release_upc|distribution_approved|workflow_status/',$line)){
   // Only expose mapping literals and identifiers, never raw lines or credentials.
   preg_match_all('/[\x27\x22]([a-zA-Z0-9_-]{2,80})[\x27\x22]/',$line,$matches);
   $d['adapter_lines'][]=['file'=>basename($file),'line'=>$i+1,'literals'=>$matches[1]];
  }
 }

 // Inventory only artistic field presence and public archive folder names.
 $d['artist_materials']=[];
 foreach(get_users(['number'=>1001,'orderby'=>'ID']) as $u){
  if(!\TRB\Studio\portal_artist_allowed($u))continue;
  $row=['id'=>(int)$u->ID,'public_name'=>\TRB\Studio\portal_artist_name($u),'email'=>$u->user_email,'missing'=>array_values(array_map(fn($r)=>['key'=>$r['key'],'label'=>$r['label'],'group'=>$r['group']],trb_portal_artist_profile_completion($u->ID)['missing'])),'artistic_keys'=>[],'files'=>['photo'=>0,'biography'=>0],'readable'=>['photo'=>false,'biography'=>false]];
  foreach(get_user_meta($u->ID) as $key=>$values){
   if(preg_match('/bio|photo|avatar|picture|image|spotify|soundcloud|youtube|instagram|website|social|artist.*name/i',$key))$row['artistic_keys'][$key]=count(array_filter((array)$values,fn($v)=>$v!==''&&$v!==null));
  }
  foreach((array)get_user_meta($u->ID,'_trb_artist_private_files',true) as $file)if(is_array($file)&&in_array($file['group']??'',['photo','biography'],true))++$row['files'][$file['group']];
  foreach(['photo','biography'] as $kind)$row['readable'][$kind]=(bool)\TRB\Studio\material('artist',$u->ID,$kind);
  $row['archive_status']=sanitize_key((string)(((array)get_user_meta($u->ID,'_trb_artist_promo_archive',true))['status']??''));
  $d['artist_materials'][]=$row;
 }
 $d['promo_archive']=['code'=>'module_missing','folders'=>[],'materials'=>[]];
 if(function_exists('trb_demo_webdav_request')){
  // Read only /Discografia - TRB rec and designated PROMO directories, never identity or audio folders.
  $list=function($path){
   $settings=trb_demo_settings();
   if(empty($settings['webdav_endpoint'])||empty($settings['pcloud_user'])||empty($settings['pcloud_pass']))return ['code'=>'missing_webdav_settings','entries'=>[]];
   // pCloud collection URLs require the slash; do not redirect credentials to another URL.
   $url=trb_demo_remote_url($settings['webdav_endpoint'],$path).'/';
   $headers=['Depth'=>'1','Content-Type'=>'application/xml','Authorization'=>'Basic '.base64_encode($settings['pcloud_user'].':'.$settings['pcloud_pass'])];
   $r=wp_remote_request($url,['method'=>'PROPFIND','headers'=>$headers,'timeout'=>25,'redirection'=>0,'limit_response_size'=>3*1024*1024,'body'=>'<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname/><d:resourcetype/><d:getcontentlength/></d:prop></d:propfind>']);
   if(is_wp_error($r))return ['code'=>sanitize_key($r->get_error_code()),'entries'=>[]];
   $status=wp_remote_retrieve_response_code($r);$body=wp_remote_retrieve_body($r);
   if($status!==207||strlen($body)>3*1024*1024||stripos($body,'<!DOCTYPE')!==false)return ['code'=>'http_'.$status,'entries'=>[]];
   $doc=new DOMDocument();if(!@$doc->loadXML($body,LIBXML_NONET))return ['code'=>'invalid_xml','entries'=>[]];
   $xp=new DOMXPath($doc);$xp->registerNamespace('d','DAV:');$entries=[];
   foreach($xp->query('//d:response') as $node){
    $href=rawurldecode((string)$xp->evaluate('string(d:href)',$node));$name=basename(rtrim($href,'/'));
    if($name===basename(rtrim($path,'/')))continue;
    $entries[]=['name'=>sanitize_text_field($name),'directory'=>$xp->query('.//d:resourcetype/d:collection',$node)->length>0,'bytes'=>(int)$xp->evaluate('string(.//d:getcontentlength)',$node)];
   }
   return ['code'=>'ok','entries'=>array_slice($entries,0,250)];
  };
  $root=$list('/Discografia - TRB rec');$d['promo_archive']['code']=$root['code'];
  foreach($root['entries'] as $entry)if($entry['directory'])$d['promo_archive']['folders'][]=$entry['name'];
  $d['archive_inventory']=[];$d['archive_texts']=[];$d['archive_pdfs']=[];
  $started=microtime(true);$requests=0;$texts=0;
  $walk=function($path,$depth=0)use(&$walk,$list,&$d,&$requests,&$texts,$started){
   if($requests>=450||microtime(true)-$started>420){$d['archive_scan_limited']=true;return;}
   ++$requests;$listing=$list($path);$d['archive_inventory'][$path]=$listing;
   foreach($listing['entries'] as $e){
    $next=$path.'/'.$e['name'];
    if($e['directory']){
     if($depth<3&&preg_match('/media|promo|foto|photos?|biograf|press|social.?media|kit/i',$e['name']))$walk($next,$depth+1);
     continue;
    }
    if($texts>=30||$e['bytes']>5*1024*1024||!preg_match('/biograf|biography|(^|[\s_-])bio[\s_.-]|press|profilo artista|edmondo|simona/i',$e['name']))continue;
    $ext=strtolower(pathinfo($e['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,['txt','docx','odt','rtf','pdf'],true))continue;
    $r=trb_demo_webdav_request('GET',$next);
    if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)continue;
    $bytes=wp_remote_retrieve_body($r);if(strlen($bytes)>5*1024*1024)continue;++$texts;
    if($ext==='pdf'){
     if(strlen($bytes)<1200000&&str_starts_with($bytes,'%PDF-'))$d['archive_pdfs'][$next]=base64_encode($bytes);
     continue;
    }
    $tmp=wp_tempnam('trb-archive-review');file_put_contents($tmp,$bytes);
    $text=\TRB\Studio\material_text(['path'=>$tmp,'name'=>$e['name']]);unlink($tmp);
    if(is_wp_error($text))continue;
    if(preg_match('/^Profilo artista\.txt$/i',$e['name'])){
     if(preg_match('/BIOGRAFIA\s*\R([\s\S]*?)\RPROFILI MUSICALI UFFICIALI/u',$text,$match))$text=trim($match[1]);else $text='';
    }
    if($text!=='')$d['archive_texts'][$next]=$text;
   }
  };
  if($root['code']==='ok')foreach($d['promo_archive']['folders'] as $folder)$walk('/Discografia - TRB rec/'.$folder);
  // Inspect release directories only for the incomplete artists and documented historical aliases.
  $legacy=['Carmine Granato','Emiliano Di Meo','Alberto Puviani','Diiego','Fabio Anastasi','FaDe - Alessio de Fanzoni','FaDe - Alessio The Fanzoni','Solidoro','Anabasi Road'];
  foreach($legacy as $folder){
   $path='/Discografia - TRB rec/'.$folder;
   foreach(($d['archive_inventory'][$path]['entries']??[]) as $e){
    if($e['directory']&&!preg_match('/media|promo|foto|photos?|biograf|press|social.?media|kit/i',$e['name']))$walk($path.'/'.$e['name'],1);
   }
  }
  $d['archive_requests']=$requests;$d['archive_scan_seconds']=round(microtime(true)-$started);
 }

 if(!is_dir($private)&&!mkdir($private,0700,true))exit(3);
 file_put_contents($report,wp_json_encode($d));chmod($report,0600);exit;
}
if($mode==='destination'){
 define('WP_USE_THEMES',false);require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';
 $d=json_decode((string)file_get_contents($report),true);
 if(!is_array($d)||($d['revision']??'')!==$revision)exit(4);
 update_option('wpvibe_task_trb_artist_recipients',$d['artist_materials'],false);
 update_option('wpvibe_task_trb_archive_review',['texts'=>$d['archive_texts'],'pdfs'=>$d['archive_pdfs']],false);
 unset($d['archive_texts'],$d['archive_pdfs']);
 foreach($d['artist_materials'] as &$r)unset($r['email']);unset($r);
 file_put_contents($report,wp_json_encode($d));chmod($report,0600);
 update_option('trb_studio_inspection',$d,false);exit;
}
exit(5);
