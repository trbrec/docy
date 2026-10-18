<?php
/** Real HTTP intake + WordPress/MySQL readback in the disposable ordinary-artist fixture. */
defined('ABSPATH') || exit;
$qaMetadataUserBefore=array();
foreach (array('_trb_artist_preliminary_contract','_trb_artist_contract_term','_trb_release_last_submission_error') as $key) $qaMetadataUserBefore[$key]=get_user_meta($qaUser,$key,false);
$qaMetadataIds=array();
$qaMetadataChecksBefore=$GLOBALS['qa_checks'];
$qaMetadataFields=array(
    'action'=>'trb_portal_start_release','trb_release_ajax'=>'1','trb_release_intake_only'=>'1',
    'trb_release_submission_token'=>'11111111-2222-4333-8444-555555555555',
    'trb_release_title'=>'Synthetic metadata intake','trb_release_type'=>'single',
    'trb_release_metadata_language'=>'it','trb_release_version'=>'Live',
    'trb_tracks[0][title]'=>'Synthetic metadata intake','trb_tracks[0][audio_style]'=>'Vocal',
    'trb_tracks[0][vocal_language]'=>'it','trb_tracks[0][recording_year]'=>wp_date('Y'),
    'trb_tracks[0][recording_country]'=>'IT','trb_tracks[0][duration_minutes]'=>'3','trb_tracks[0][duration_seconds]'=>'10',
    'trb_tracks[0][primary_genre]'=>'Rock','trb_tracks[0][secondary_genre]'=>'Classic Rock',
    'trb_tracks[0][advisory]'=>'non_explicit','trb_tracks[0][content_nature]'=>'original','trb_tracks[0][rights_basis]'=>'owned',
    'trb_tracks[0][audio_status]'=>'mastered',
    'trb_tracks[0][credits][writers][0][name]'=>'Synthetic Writer',
    'trb_tracks[0][credits][writers][0][roles][0]'=>'Composer','trb_tracks[0][credits][writers][0][roles][1]'=>'Lyricist',
    'trb_tracks[0][credits][performers][0][name]'=>'Synthetic Singer','trb_tracks[0][credits][performers][0][role]'=>'Vocals',
    'trb_tracks[0][credits][engineering][0][name]'=>'Synthetic Producer','trb_tracks[0][credits][engineering][0][role]'=>'Producer',
);
$qaMetadataRead=static function($id) { wp_cache_delete($id,'post_meta'); return get_post_meta($id); };
try {
    update_user_meta($qaUser,'_trb_artist_preliminary_contract','TRB-QA-SYNTHETIC-METADATA');
    update_user_meta($qaUser,'_trb_artist_contract_term','01/01/26 - INFINITO');
    $bootstrap=qa_http()['data'];qa_check($bootstrap['complete']===true,'Metadata fixture artist must satisfy ordinary profile requirements.');
    $qaMetadataFields['trb_portal_release_nonce']=$bootstrap['release_nonce'];
    $countBefore=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='trb_release'");
    qa_check(qa_http($qaMetadataFields,true)['status']===401,'Anonymous release intake accepted.');
    qa_check(qa_http(array_replace($qaMetadataFields,array('trb_portal_release_nonce'=>'invalid')))['status']===403,'Invalid release nonce accepted.');
    foreach (array('trb_release_metadata_language','trb_tracks[0][vocal_language]','trb_tracks[0][recording_year]','trb_tracks[0][recording_country]') as $missing) {
        $bad=$qaMetadataFields;unset($bad[$missing]);$response=qa_http($bad);
        qa_check($response['status']===409&&($response['data']['data']['status']??'')==='invalid_metadata','Required metadata bypassed over HTTP: '.$missing);
    }
    qa_check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='trb_release'")===$countBefore,'Rejected metadata created a receipt in MySQL.');
    $response=qa_http($qaMetadataFields);$id=(int)($response['data']['data']['release_id']??0);if($id)$qaMetadataIds[]=$id;
    qa_check($response['status']===200&&$id>0,'Valid metadata HTTP intake failed: '.$response['body']);
    $stored=$qaMetadataRead($id);$track=maybe_unserialize($stored['_trb_release_tracks'][0])[0];
    qa_check($stored['_trb_release_metadata_language'][0]==='it'&&$stored['_trb_release_version'][0]==='Live','Release metadata lost in MySQL.');
    qa_check($track['audio_style']==='Vocal'&&$track['vocal_language']==='it'&&$track['recording_year']===(int)wp_date('Y')&&$track['recording_country']==='IT','Typed track metadata lost in MySQL.');
    qa_check($track['ai_usage']==='None'&&$track['ai_usage_provenance']==='owner_declared_default_2026-10-18','HTTP intake lost owner default provenance.');
    $tracks=get_post_meta($id,'_trb_release_tracks',true);$tracks[0]['ai_usage']='Some';update_post_meta($id,'_trb_release_tracks',$tracks);
    $retry=array_replace($qaMetadataFields,array('trb_tracks[0][audio_style]'=>'Instrumental','trb_tracks[0][advisory]'=>'no_lyrics','trb_tracks[0][recording_country]'=>'GB'));
    $retryResponse=qa_http($retry);qa_check(($retryResponse['data']['data']['release_id']??0)===$id&&$retryResponse['status']===200,'Retry duplicated or rejected the HTTP receipt.');
    $stored=$qaMetadataRead($id);$track=maybe_unserialize($stored['_trb_release_tracks'][0])[0];
    qa_check($track['audio_style']==='Instrumental'&&$track['vocal_language']===null&&$track['recording_country']==='GB'&&$track['ai_usage']==='Some','Instrumental correction or specific declaration lost in MySQL retry.');
    qa_check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='trb_release'")===$countBefore+1,'Repeated HTTP intake created more than one receipt.');
    update_post_meta($id,'_trb_release_intake_phase','complete');update_post_meta($id,'signature_digest','synthetic-immutable-signature-digest');
    $before=$qaMetadataRead($id);$legacy=$retry;
    foreach (array('trb_release_metadata_language','trb_release_version','trb_tracks[0][audio_style]','trb_tracks[0][vocal_language]','trb_tracks[0][recording_year]','trb_tracks[0][recording_country]') as $key) unset($legacy[$key]);
    qa_check(qa_http($legacy)['status']===200,'Completed historical receipt requires newly introduced metadata.');
    qa_check($qaMetadataRead($id)===$before,'Completed receipt metadata or signature changed over HTTP.');
    echo ($GLOBALS['qa_checks']-$qaMetadataChecksBefore)." release metadata assertions passed through real HTTP and MySQL.\n";
} finally {
    foreach ($qaMetadataIds as $id) wp_delete_post($id,true);
    foreach ($qaMetadataUserBefore as $key=>$values) { delete_user_meta($qaUser,$key);foreach($values as $value)add_user_meta($qaUser,$key,$value); }
}
