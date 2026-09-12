<?php
/** Per-file retry. A rejected slot survives; its rejected bytes do not. */
if (!defined('ABSPATH')) exit;

function trb_file_retry_phases() {
    return array('awaiting_upload','validation_failed','files_partial','recovery_review');
}

function trb_file_retry_field($file) {
    $fields=array('cover'=>'trb_release_cover','cover_reference'=>'trb_release_cover_reference','presentation'=>'trb_release_presentation','audio'=>'trb_track_audio','lyrics'=>'trb_track_lyrics','rights_document'=>'trb_track_rights_document');
    $kind=$file['kind']??'';
    if (!isset($fields[$kind])) return '';
    return $fields[$kind].(in_array($kind,array('audio','lyrics','rights_document'),true)?'['.absint($file['track']).']':'');
}

/** Resolve only inside this receipt's private directory, never a client path. */
function trb_file_retry_path($id,$file) {
    $base=trailingslashit(wp_upload_dir()['basedir']);
    $root=realpath($base.'trb-release-private/'.absint($id));
    $path=!empty($file['path'])?realpath($base.ltrim($file['path'],'/')):false;
    return $root && $path && is_file($path) && strpos($path,$root.DIRECTORY_SEPARATOR)===0 ? $path : false;
}

function trb_file_retry_verified($id,$file) {
    $path=trb_file_retry_path($id,$file);
    return empty($file['rejected']) && $path && !empty($file['sha256']) && hash_equals((string)$file['sha256'],(string)hash_file('sha256',$path));
}

/** Public form data contains names and hashes, never filesystem paths. */
function trb_file_retry_manifest($id) {
    $post=get_post($id);
    if (!$post || (int)$post->post_author!==get_current_user_id() || trb_release_is_inactive($id) || !in_array(get_post_meta($id,'_trb_release_intake_phase',true),trb_file_retry_phases(),true)) return array();
    $out=array();
    $token=(string)get_post_meta($id,'_trb_release_submission_token',true);
    $directory=trb_portal_release_staging_session_dir($token,false);
    if ($directory) foreach (glob(trailingslashit($directory).'f*.json')?:array() as $meta_path) {
        if (filesize($meta_path)>16384) continue;
        $meta=json_decode((string)file_get_contents($meta_path),true);
        $part=substr($meta_path,0,-5).'.part';
        $field=$meta['field_name']??'';
        if (!preg_match('/^trb_(?:release_(?:cover|cover_reference|presentation)|track_(?:audio|lyrics|rights_document)\[[0-9]{1,2}\])$/',$field) || empty($meta['complete']) || !trb_portal_release_is_staged_path($part) || (int)filesize($part)!==(int)($meta['size']??0)) continue;
        $out[$field]=array('session'=>$token,'key'=>basename($part,'.part'),'name'=>$meta['name'],'upload_id'=>$meta['upload_id']??'');
    }
    foreach ((array)get_post_meta($id,'_trb_release_files',true) as $file) {
        if (!is_array($file) || !trb_file_retry_verified($id,$file)) continue;
        $field=trb_file_retry_field($file);
        if ($field) $out[$field]=array('retained'=>$file['sha256'],'name'=>$file['original_name']??$file['name']);
    }
    return $out;
}

/** A hash is only a selector: ownership, receipt, phase, slot and bytes are rechecked. */
function trb_file_retry_retained_upload($field,$hash) {
    $id=(int)($GLOBALS['trb_verified_intake_id']??0);
    $post=$id?get_post($id):null;
    $token=(string)wp_unslash($_POST['trb_release_submission_token']??'');
    if (!$post || (int)$post->post_author!==get_current_user_id() || trb_release_is_inactive($id) || !hash_equals((string)get_post_meta($id,'_trb_release_submission_token',true),$token) || !in_array(get_post_meta($id,'_trb_release_intake_phase',true),array_merge(trb_file_retry_phases(),array('acquiring_files')),true)) return array();
    foreach ((array)get_post_meta($id,'_trb_release_files',true) as $file) {
        if (!is_array($file) || trb_file_retry_field($file)!==$field || !hash_equals((string)($file['sha256']??''),(string)$hash) || !trb_file_retry_verified($id,$file)) continue;
        $path=trb_file_retry_path($id,$file);
        return array('name'=>$file['original_name']??$file['name'],'type'=>$file['type']??'','size'=>filesize($path),'tmp_name'=>$path,'error'=>UPLOAD_ERR_OK,'_trb_retained'=>$id,'_trb_field'=>$field,'_trb_hash'=>$hash);
    }
    return array();
}

/** Infrastructure errors and editable metadata are not proof of corrupt bytes. */
function trb_file_retry_is_rejection($code) {
    return in_array($code,array('invalid_cover','invalid_cover_reference','invalid_presentation','invalid_lyrics','invalid_rights_document','invalid_audio','MASTER_PEAK_REJECTED','MALWARE_DETECTED','WAV_NOT_PCM','WAV_DECODE_FAILED','AUDIO_NOT_STEREO','SAMPLE_RATE_INVALID','BIT_DEPTH_INVALID','INVALID_AUDIO_SAMPLES','AUDIO_LEVEL_EFFECTIVELY_SILENT','MASTER_TRUE_PEAK_AT_ZERO','MASTER_SAMPLE_PEAK_AT_ZERO'),true);
}

/** Caller holding the release lock may pass $locked. Obsolete results cannot erase a replacement. */
function trb_file_retry_discard($id,$field,$hash,$reason,$locked=false) {
    if (!trb_file_retry_is_rejection($reason) || !$hash || trb_release_is_inactive($id)) return false;
    $lock=$locked?null:trb_release_process_lock('release:'.absint($id));
    if (!$locked && !$lock) return false;
    try {
        // Approval/contract states still protect definitive materials.
        if (function_exists('trb_portal_release_files_are_locked') && trb_portal_release_files_are_locked($id)) return false;
        $files=(array)get_post_meta($id,'_trb_release_files',true);
        foreach ($files as $index=>$file) {
            if (!is_array($file) || trb_file_retry_field($file)!==$field || !hash_equals((string)($file['sha256']??''),(string)$hash)) continue;
            $path=trb_file_retry_path($id,$file);
            if (!$path || !hash_equals($hash,(string)hash_file('sha256',$path))) return false;
            wp_delete_file($path);
            clearstatcache(true,$path);
            if (is_file($path)) return false;
            // Keep the slot and rejection record so the ordinary replacement form remains available.
            $slot=array_intersect_key($file,array_flip(array('kind','track','name','original_name','audio_status')));
            $slot['rejected']=array('sha256'=>$hash,'reason'=>$reason,'at'=>time());
            $files[$index]=$slot;
            update_post_meta($id,'_trb_release_files',$files);
            $checkpoint=(array)get_post_meta($id,'_trb_release_acquired_files',true);
            foreach ($checkpoint as $key=>$item) if (is_array($item) && ($item['sha256']??'')===$hash && trb_file_retry_field($item)===$field) unset($checkpoint[$key]);
            update_post_meta($id,'_trb_release_acquired_files',$checkpoint);
            $documents=(array)get_post_meta($id,'_trb_release_rights_documents',true);
            foreach ($documents as $key=>$document) if (is_array($document) && ($document['path']??'')===($file['path']??'')) unset($documents[$key]);
            update_post_meta($id,'_trb_release_rights_documents',array_values($documents));
            return true;
        }
        return false;
    } finally { if ($lock) trb_release_process_unlock($lock); }
}

function trb_file_retry_reject_upload($file,$error) {
    if (!is_wp_error($error) || !trb_file_retry_is_rejection($error->get_error_code())) return;
    $removed=false;
    if (!empty($file['_trb_retained'])) {
        $removed=trb_file_retry_discard($file['_trb_retained'],$file['_trb_field'],$file['_trb_hash'],$error->get_error_code());
    } elseif (!empty($file['_trb_staged']) && !empty($file['tmp_name']) && trb_portal_release_is_staged_path($file['tmp_name'])) {
        $path=$file['tmp_name'];
        $lock=fopen($path.'.lock','c');
        if ($lock && flock($lock,LOCK_EX|LOCK_NB)) {
            // A new upload can replace this staging slot while the previous file is being validated.
            if (empty($file['_trb_hash']) || !is_file($path) || !hash_equals($file['_trb_hash'],(string)hash_file('sha256',$path))) {flock($lock,LOCK_UN);fclose($lock);return;}
            wp_delete_file($path); wp_delete_file(preg_replace('/\.part$/','.json',$path));
            clearstatcache(true,$path); $removed=!is_file($path);
            flock($lock,LOCK_UN);
        }
        if ($lock) fclose($lock);
    }
    if ($removed && !empty($file['_trb_field'])) $GLOBALS['trb_discarded_upload_fields'][]=$file['_trb_field'];
}

/** Repeated submissions reuse identical acquired bytes, without overwriting any valid predecessor. */
function trb_file_retry_reuse($id,$upload,$kind,$index) {
    foreach ((array)get_post_meta($id,'_trb_release_files',true) as $stored) {
        if (!is_array($stored) || ($stored['kind']??'')!==$kind || ($stored['track']??null)!==$index || !trb_file_retry_verified($id,$stored)) continue;
        if (hash_equals($stored['sha256'],(string)hash_file('sha256',$upload['tmp_name']))) return $stored;
    }
    return !empty($upload['_trb_retained'])?new WP_Error('recovery_integrity_failed'):null;
}
