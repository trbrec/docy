<?php
/** CLI-only production audit and narrowly guarded repair of empty GREED duplicates. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$revision=$argv[1]??'';
if (!preg_match('/^[a-f0-9]{40}$/D',$revision) || trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision) exit(2);
$_SERVER['HTTP_HOST']='artist.trbrec.com'; $_SERVER['REQUEST_URI']='/'; $_SERVER['HTTPS']='on';
define('DISABLE_WP_CRON',true);
require dirname(__DIR__,4).'/wp-load.php';
if (rtrim(home_url(),'/')!=='https://artist.trbrec.com') throw new RuntimeException('Unexpected site');
add_filter('pre_wp_mail',static function(){return false;},PHP_INT_MAX);
function trb_live_audit_emit($label,$data){echo $label.' '.wp_json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";}
function trb_live_audit_identity($post){return trb_intake_project_identity(array('trb_release_title'=>$post->post_title,'trb_release_type'=>get_post_meta($post->ID,'_trb_release_type',true),'trb_tracks'=>get_post_meta($post->ID,'_trb_release_tracks',true)));}

if (in_array('--repair-empty-greed',$argv,true)) {
    $ids=array(12325,12327,12329); $keep=12329; $locks=array();
    try {
        foreach ($ids as $id) {
            $locks[$id]=trb_release_process_lock('release:'.$id);
            if (!$locks[$id]) throw new RuntimeException('Repair deferred: active worker #'.$id);
        }
        $reference=get_post($keep);
        if (!$reference || trb_release_is_inactive($keep) || trb_intake_identity_text($reference->post_title)!=='non ho più te' || trb_intake_identity_text(trb_portal_artist_profile_value('artist_name',$reference->post_author))!=='greed') throw new RuntimeException('Repair identity guard');
        $identity=trb_live_audit_identity($reference);
        if (!$identity) throw new RuntimeException('Incomplete project identity');
        // Validate all candidates before changing any of them. Staging is never deleted.
        foreach (array(12325,12327) as $id) {
            $post=get_post($id);
            if (!$post || (int)$post->post_author!==(int)$reference->post_author || trb_live_audit_identity($post)!==$identity || get_post_meta($id,'_trb_release_date',true)!==get_post_meta($keep,'_trb_release_date',true)) throw new RuntimeException('Duplicate identity mismatch #'.$id);
            if (get_post_meta($id,'_trb_release_files',true) || get_post_meta($id,'_trb_release_acquired_files',true) || get_post_meta($id,'_trb_release_pcloud_archive',true) || get_post_meta($id,'_trb_otp_dossier_id',true)) throw new RuntimeException('Materials or external dossier present #'.$id);
            if (!in_array(get_post_meta($id,'_trb_contract_state',true),array('','waiting_upload'),true) || !in_array(get_post_meta($id,'_trb_release_intake_phase',true),array('awaiting_upload','validation_failed'),true)) throw new RuntimeException('Practice progressed #'.$id);
            foreach ((array)get_post_meta($id,'_trb_release_tracks',true) as $track) if (!empty($track['isrc'])) throw new RuntimeException('ISRC assigned #'.$id);
        }
        foreach (array(12325,12327) as $id) {
            if ('trash'===get_post_status($id)) {trb_live_audit_emit('DUPLICATE_ALREADY_TRASHED',array('id'=>$id));continue;}
            update_post_meta($id,'_trb_owner_pretrash_pipeline_status',get_post_meta($id,'_trb_release_pipeline_status',true));
            update_post_meta($id,'_trb_owner_cancelled_at',current_time('mysql',true));
            update_post_meta($id,'_trb_owner_cancelled_by',0);
            update_post_meta($id,'_trb_release_duplicate_of',$keep);
            update_post_meta($id,'_trb_release_integrity_repair',array('revision'=>$revision,'at'=>gmdate('c'),'reason'=>'Repeated empty intake of the same project','keep'=>$keep));
            update_post_meta($id,'_trb_release_pipeline_status','cancelled');
            trb_owner_dashboard_stop_release_jobs($id);
            if (!wp_trash_post($id) || 'trash'!==get_post_status($id)) throw new RuntimeException('Recoverable trash failed #'.$id);
            trb_live_audit_emit('DUPLICATE_TRASHED',array('id'=>$id,'retained'=>$keep,'recoverable'=>true));
        }
        $staging=trb_recovery_file_candidates($reference->post_author);
        trb_live_audit_emit('GREED_RETAINED',array('id'=>$keep,'phase'=>get_post_meta($keep,'_trb_release_intake_phase',true),'complete_staging_files'=>count($staging),'staging_untouched'=>true));
    } finally {foreach($locks as $lock) trb_release_process_unlock($lock);}
}

$posts=get_posts(array('post_type'=>'trb_release','post_status'=>array('publish','private','pending','draft'),'posts_per_page'=>-1,'orderby'=>'ID','order'=>'ASC'));
$counts=array('active'=>0,'qa'=>0,'incomplete'=>0,'completed'=>0,'cancelled'=>0); $issues=array(); $identities=array();
foreach($posts as $post) {
    $id=(int)$post->ID;
    if (trb_portal_release_is_qa($id)) {$counts['qa']++;continue;}
    if (trb_release_is_inactive($id)) {$counts['cancelled']++;continue;}
    $counts['active']++;
    $identity=trb_live_audit_identity($post);
    if ($identity) $identities[$post->post_author.':'.$identity][]=$id;
    $phase=get_post_meta($id,'_trb_release_intake_phase',true);
    $pipeline=get_post_meta($id,'_trb_release_pipeline_status',true);
    $contract=get_post_meta($id,'_trb_contract_state',true);
    if ($phase && 'complete'!==$phase) {
        $counts['incomplete']++;
        $issues[]=array('id'=>$id,'issue'=>'incomplete_intake','phase'=>$phase,'pipeline'=>$pipeline);
        if (in_array($contract,array('contract_sent','signed'),true)) $issues[]=array('id'=>$id,'issue'=>'contract_on_incomplete_intake');
        continue;
    }
    $counts['completed']++;
    $files=(array)get_post_meta($id,'_trb_release_files',true);
    $tracks=(array)get_post_meta($id,'_trb_release_tracks',true);
    $inventory=trb_analysis_audio_inventory_errors($tracks,$files);
    if ($inventory) $issues[]=array('id'=>$id,'issue'=>'audio_inventory','codes'=>$inventory);
    foreach($files as $file) {
        if(!is_array($file)||empty($file['path']))continue;
        $path=trb_release_pcloud_local_file($file);
        if (!$path || !is_file($path) || (!empty($file['size']) && filesize($path)!==(int)$file['size'])) $issues[]=array('id'=>$id,'issue'=>'local_material_missing_or_size_mismatch','kind'=>$file['kind']??'','track'=>$file['track']??null);
    }
    if ('approved'===$pipeline && !trb_release_technical_is_current($id)) $issues[]=array('id'=>$id,'issue'=>'approval_without_current_technical_result');
    if ('approved'===$pipeline && !in_array($contract,array('contract_sent','signed'),true) && !wp_next_scheduled('trb_release_bridge_dispatch',array($id))) $issues[]=array('id'=>$id,'issue'=>'approved_contract_not_dispatched','contract'=>$contract);
}
foreach($identities as $ids) if(count($ids)>1)$issues[]=array('issue'=>'duplicate_active_project','ids'=>$ids);
trb_live_audit_emit('RELEASE_AUDIT',array('revision'=>$revision,'counts'=>$counts,'issues'=>$issues));
