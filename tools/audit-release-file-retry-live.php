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
