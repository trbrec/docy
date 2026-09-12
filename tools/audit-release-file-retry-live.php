<?php
/** Read-only deployment smoke check. No uploads, emails or analysis jobs. */
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$revision=$argv[1]??'';
if (!preg_match('/^[a-f0-9]{40}$/D',$revision) || trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision) exit(2);
$_SERVER['HTTP_HOST']='artist.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';
define('DISABLE_WP_CRON',true);
require dirname(__DIR__,4).'/wp-load.php';
if (rtrim(home_url(),'/')!=='https://artist.trbrec.com') throw new RuntimeException('Unexpected site');
add_filter('pre_wp_mail',static function(){return false;},PHP_INT_MAX);
foreach (array('trb_file_retry_manifest','trb_file_retry_discard','trb_file_retry_retained_upload','trb_portal_validate_release_upload_bytes') as $fn) if (!function_exists($fn)) throw new RuntimeException('Missing retry function: '.$fn);
$initial=get_current_user_id();$results=array();
try {
 foreach (array(12321,12329) as $id) {
  $post=get_post($id);if (!$post || $post->post_type!=='trb_release') throw new RuntimeException('Receipt missing #'.$id);
  wp_set_current_user($post->post_author);
  $phase=(string)get_post_meta($id,'_trb_release_intake_phase',true);
  $manifest=trb_file_retry_manifest($id);
  $results[]=array('id'=>$id,'phase'=>$phase,'active'=>!trb_release_is_inactive($id),'owner_can_resume'=>in_array($phase,trb_file_retry_phases(),true),'retained_fields'=>array_keys($manifest),'has_saved_metadata'=>(bool)get_post_meta($id,'_trb_release_intake_draft',true));
  if (12329===$id) {
   $technical=(array)get_post_meta($id,'_trb_release_technical_analysis',true);
   $archive=(array)get_post_meta($id,'_trb_release_pcloud_archive',true);
   $files=(array)get_post_meta($id,'_trb_release_files',true);
   echo 'GREED_PIPELINE_DIAGNOSTIC '.wp_json_encode(array(
    'id'=>$id,'checked_at'=>gmdate('c'),'post_status'=>$post->post_status,
    'pipeline'=>get_post_meta($id,'_trb_release_pipeline_status',true),
    'label'=>trb_portal_release_current_state_label($id),
    'contract'=>get_post_meta($id,'_trb_contract_state',true),
    'rights_decision'=>array_intersect_key((array)get_post_meta($id,'_trb_release_analysis_decision',true),array_flip(array('state','semaphore','copyright_findings','limitations','decided_at'))),
    'technical'=>array_intersect_key($technical,array_flip(array('status','errors','warnings','retryable','completed_at'))),
    'archive'=>array_intersect_key($archive,array_flip(array('status','verified','time','error'))),
    'files'=>array_map(static function($f){return array_intersect_key((array)$f,array_flip(array('kind','name','audio_status','rejected_reason')));},$files)
   ),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
   $images=array();$token=(string)get_post_meta($id,'_trb_release_submission_token',true);
   foreach (trb_recovery_file_candidates($post->post_author) as $candidate) {
    $image=@getimagesize($candidate['path']);if (!$image) continue;
    $images[]=array('name'=>$candidate['name'],'width'=>$image[0],'height'=>$image[1],'bytes'=>$candidate['size'],'current_receipt'=>strpos($candidate['relative'],$token.'/')===0);
   }
   $confirmation=false;
   foreach ((array)get_post_meta($id,'_trb_release_intake_draft',true) as $pair) if (is_array($pair) && ($pair[0]??'')==='trb_release_cover_300dpi') $confirmation=($pair[1]??'')==='1';
   echo 'GREED_COVER_DIAGNOSTIC '.wp_json_encode(array('id'=>$id,'phase'=>$phase,'saved_confirmation'=>$confirmation,'last_error'=>get_post_meta($id,'_trb_release_intake_error',true),'cover_rejection'=>get_post_meta($id,'_trb_release_last_cover_rejection',true),'received_images'=>$images),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
  }
  wp_set_current_user(0);
  if (trb_file_retry_manifest($id)) throw new RuntimeException('Unauthenticated file manifest exposed');
 }
} finally {wp_set_current_user($initial);}
echo 'FILE_RETRY_LIVE '.wp_json_encode(array('revision'=>$revision,'functions_loaded'=>true,'ownership_guard'=>true,'receipts'=>$results),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";

$crmRoot='/home/customer/www/crm.trbrec.com';
foreach(array($crmRoot.'/public_html',$crmRoot.'/public_html/app',$crmRoot.'/app',$crmRoot.'/private/app') as $dir) {
 if(is_dir($dir))echo 'CRM_LAYOUT '.wp_json_encode(array('dir'=>$dir,'entries'=>array_values(array_diff(scandir($dir),array('.','..')))))."\n";
}

require_once $crmRoot.'/public_html/app/bootstrap.php';
$db=\TrbCrm\Database::connection();
$rows=$db->query("SELECT rc.id,rc.portal_release_id,rc.workflow_status,s.public_id,rc.metadata FROM release_cases rc JOIN submissions s ON s.id=rc.submission_id WHERE rc.portal_release_id IN ('artist:12325','artist:12329')")->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $row){$m=json_decode($row['metadata'],true);unset($row['metadata']);$row['pipeline']=$m['portal_pipeline_status']??null;echo 'CRM_GREED_CASE '.wp_json_encode($row)."\n";}
foreach(array('app/View.php','app/Controller.php','app/SubmissionRepository.php','assets/app-20260831-r27.js') as $rel)echo 'CRM_FILE_VERSION '.wp_json_encode(array('path'=>$rel,'sha256'=>hash_file('sha256',$crmRoot.'/public_html/'.$rel)))."\n";
foreach(get_defined_functions()['user'] as $fn) if(strpos($fn,'trb')===0 && preg_match('/(copyright.*review|review.*copyright)/',$fn)) { $rf=new ReflectionFunction($fn);echo 'RIGHTS_FUNCTION '.wp_json_encode(array('name'=>$fn,'file'=>basename($rf->getFileName()),'parameters'=>array_map(static function($p){return $p->getName();},$rf->getParameters())))."\n"; }
