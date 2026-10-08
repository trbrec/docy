<?php
/** Owner-authorized removal of the obsolete internal cap; resume normal checks. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$revision=$argv[1]??'';
if (!preg_match('/^[a-f0-9]{40}$/D',$revision) || trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision) exit(2);
$_SERVER['HTTP_HOST']='artist.trbrec.com'; $_SERVER['REQUEST_URI']='/'; $_SERVER['HTTPS']='on';
define('DISABLE_WP_CRON',true);
require dirname(__DIR__,4).'/wp-load.php';
if (rtrim(home_url(),'/')!=='https://artist.trbrec.com') throw new RuntimeException('Unexpected site');
global $wpdb; $tables=trb_resource_tables();
$before=get_posts(array('post_type'=>'trb_release','post_status'=>array('publish','private','pending'),'posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'_trb_release_pipeline_status','meta_value'=>'ACR_BUDGET_LIMIT_REACHED'));
echo 'ACR_CAP_REMOVAL '.wp_json_encode(array('blocked_before'=>$before,'guard_allows'=>true===trb_resource_acr_budget_guard(1000000,12329)))."\n";
// Cancel only unsent messages from the removed internal cap, preserve audit history.
$pattern=$wpdb->esc_like('acr-budget-block-').'%';
$wpdb->query($wpdb->prepare("UPDATE {$tables['notifications']} SET status='cancelled',last_error='internal_cap_removed',updated_at=%s WHERE status IN ('pending','retry') AND event_key LIKE %s",trb_resource_now(),$pattern));
$event_pattern=$wpdb->esc_like('acrcloud:budget-').'%';
$wpdb->query($wpdb->prepare("UPDATE {$tables['events']} SET status='resolved' WHERE status='open' AND event_key LIKE %s",$event_pattern));
trb_resource_recover_release_pipeline(true);
// Poll existing GREED provider jobs only; never create repeated paid submissions here.
for($attempt=0;$attempt<8;$attempt++) {
 $jobs=$wpdb->get_results($wpdb->prepare("SELECT id,service,status,last_error FROM {$tables['usage']} WHERE release_id=%d AND provider='acrcloud' AND status IN ('submitted','processing')",12329));
 if (!$jobs) break;
 foreach($jobs as $job) {
  if (in_array($job->service,array('fingerprinting_exact','cover_song_scan'),true)) trb_resource_poll_dual_acr_job((int)$job->id);
  elseif (in_array($job->service,array('fingerprinting','fingerprinting_reuse'),true)) trb_resource_poll_acr_job((int)$job->id);
 }
 $state=get_post_meta(12329,'_trb_release_pipeline_status',true);
 echo 'GREED_ACR_PROGRESS '.wp_json_encode(array('attempt'=>$attempt+1,'pipeline'=>$state))."\n";
 if ($state!=='analysis_in_progress') break;
 if ($attempt<7) sleep(15);
}
foreach(array_unique(array_merge($before,array(12329))) as $id) {
 $rows=$wpdb->get_results($wpdb->prepare("SELECT service,status,last_error FROM {$tables['usage']} WHERE release_id=%d AND provider='acrcloud'",$id),ARRAY_A);
 echo 'ACR_RECOVERY_RESULT '.wp_json_encode(array('id'=>$id,'pipeline'=>get_post_meta($id,'_trb_release_pipeline_status',true),'contract'=>get_post_meta($id,'_trb_contract_state',true),'jobs'=>$rows),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}
