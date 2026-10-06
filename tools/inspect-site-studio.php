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
 if(!is_dir($private)&&!mkdir($private,0700,true))exit(3);
 file_put_contents($report,wp_json_encode($d));chmod($report,0600);exit;
}
if($mode==='destination'){
 define('WP_USE_THEMES',false);require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';
 $d=json_decode((string)file_get_contents($report),true);
 if(!is_array($d)||($d['revision']??'')!==$revision)exit(4);
 update_option('trb_studio_inspection',$d,false);exit;
}
exit(5);
