<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
$_SERVER['HTTP_HOST']='artist.trbrec.com';$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='on';
define('DISABLE_WP_CRON',true);
require dirname(__DIR__,4).'/wp-load.php';
if(rtrim(home_url(),'/')!=='https://artist.trbrec.com')exit(3);
$ids=get_posts(array('post_type'=>'trb_release','post_status'=>'any','numberposts'=>-1,'fields'=>'ids'));
$summary=array('releases'=>0,'lyrics'=>0,'languages'=>0,'sync_ok'=>0,'sync_errors'=>0);
foreach($ids as $id){
    trb_release_sheet_enrich($id);$auto=(array)get_post_meta($id,'_trb_release_sheet_auto',true);$summary['releases']++;
    foreach($auto['tracks']??array() as $track){if(!empty($track['lyrics']))$summary['lyrics']++;if(!empty($track['language']))$summary['languages']++;}
    if(function_exists('trb_crm_complete_sync_send_release')){$response=trb_crm_complete_sync_send_release($id);$summary[is_wp_error($response)?'sync_errors':'sync_ok']++;}
}
echo 'RELEASE_SHEET '.wp_json_encode($summary)."\n";
