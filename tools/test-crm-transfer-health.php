<?php
require __DIR__.'/crm-transfer-health.php';
function check($ok,$reason){if(!$ok)throw new RuntimeException($reason);}
$r=trb_crm_release_transfer_health(['portal_post_meta'=>['_trb_release_intake_phase'=>'validation_failed','_trb_release_expected_tracks'=>3,'_trb_release_intake_error'=>'Crediti da verificare']]);
check($r['incomplete']&&$r['expected_tracks']===3&&$r['acquired_audio']===0&&count($r['warnings'])===3,'Missing files must warn');
$r=trb_crm_release_transfer_health(['tracks'=>[[],[],[]],'files'=>[['kind'=>'audio','track'=>0],['kind'=>'audio','track'=>0],['kind'=>'cover']]]);
check($r['incomplete']&&$r['acquired_audio']===1,'Duplicates and cover must not count as tracks');
$r=trb_crm_release_transfer_health(['tracks'=>[[]],'files'=>[['kind'=>'audio','track'=>0]],'pcloud_archive'=>['status'=>'error','detail'=>'Timeout'],'portal_post_meta'=>['_trb_release_intake_phase'=>'complete']]);
check(!$r['incomplete']&&!$r['pcloud_confirmed']&&count($r['warnings'])===1,'Remote failure must remain visible after local completion');
$r=trb_crm_release_transfer_health(['tracks'=>[[]],'files'=>[['kind'=>'audio','track'=>0]],'pcloud_archive'=>['status'=>'synced','verified'=>true],'portal_post_meta'=>['_trb_release_intake_phase'=>'complete']]);
check(!$r['incomplete']&&$r['pcloud_confirmed']&&!$r['warnings'],'Completed transfer incorrectly warned');
echo "PASS technical release completeness and remote failure warnings\n";
