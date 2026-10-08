<?php
/** Summarize technical completeness without changing commercial workflow state. */
function trb_crm_release_transfer_health(array $metadata): array
{
    $meta=is_array($metadata['portal_post_meta']??null)?$metadata['portal_post_meta']:[];
    $pipeline=(string)($metadata['portal_pipeline_status']??$meta['_trb_release_pipeline_status']??'');
    $phase=(string)($meta['_trb_release_intake_phase']??'');
    $tracks=is_array($metadata['tracks']??null)?$metadata['tracks']:[];
    $files=is_array($metadata['files']??null)?$metadata['files']:[];
    $archive=is_array($metadata['pcloud_archive']??null)?$metadata['pcloud_archive']:[];
    $expected=max(count($tracks),(int)($meta['_trb_release_expected_tracks']??0));
    $audio=[];
    foreach($files as $file)if(is_array($file)&&($file['kind']??'')==='audio'&&isset($file['track']))$audio[(string)$file['track']]=true;
    $incomplete=($phase!==''&&$phase!=='complete')||in_array($pipeline,['upload_incomplete','upload_failed','isrc_assignment_failed'],true)||($expected>0&&count($audio)<$expected);
    $remoteReady=($archive['status']??'')==='synced'&&($archive['verified']??false)===true;
    $warnings=[];
    if($incomplete)$warnings[]='Pratica incompleta: acquisizione o validazione dei file da completare.';
    $reason=trim((string)($meta['_trb_release_intake_error']??''));
    if($reason!==''&&$incomplete)$warnings[]=mb_substr($reason,0,700);
    if(($archive['status']??'')==='error')$warnings[]='Trasferimento pCloud non completato: '.mb_substr((string)($archive['detail']??$archive['code']??'errore da verificare'),0,500);
    elseif(!$remoteReady&&($expected>0||$phase!==''))$warnings[]='Archiviazione pCloud non ancora confermata.';
    if(preg_match('/failed|error|rejected|waiting_configuration|quota/i',$pipeline)&&!$incomplete)$warnings[]='Pipeline da verificare: '.$pipeline;
    return ['incomplete'=>$incomplete,'expected_tracks'=>$expected,'acquired_audio'=>count($audio),'pcloud_confirmed'=>$remoteReady,'warnings'=>array_values(array_unique($warnings))];
}
