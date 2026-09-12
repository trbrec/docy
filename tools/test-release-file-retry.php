<?php
// Real private/staging bytes; no network, email, artist records or paid analysis.
define('ABSPATH',__DIR__); define('MB_IN_BYTES',1048576);
class WP_Error {function __construct(public $code,public $message=''){}function get_error_code(){return $this->code;}}
function is_wp_error($e){return $e instanceof WP_Error;}
function absint($v){return abs((int)$v);} function trailingslashit($v){return rtrim($v,'/').'/';}
function sanitize_file_name($v){return $v;}
function trb_portal_release_max_file_bytes(){return 250*MB_IN_BYTES;}
function trb_master_upload_check(...$args){return true;}
function wp_unslash($v){return $v;} function wp_delete_file($p){if(is_file($p))unlink($p);}
$root=sys_get_temp_dir().'/trb-file-retry-'.getmypid();mkdir($root,0700,true);
function wp_upload_dir(){return ['basedir'=>$GLOBALS['root']];}
$meta=[];$owner=7;$busy=false;$locked=false;
function get_current_user_id(){return $GLOBALS['owner'];}
function get_post($id){return (object)['ID'=>$id,'post_author'=>7];}
function trb_release_is_inactive($id){return $id===99;}
function trb_release_process_lock($s){return $GLOBALS['busy']?false:fopen($GLOBALS['root'].'/lock','c');}
function trb_release_process_unlock($h){fclose($h);}
function trb_portal_release_files_are_locked($id){return $GLOBALS['locked'];}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$v){$GLOBALS['meta'][$id][$key]=$v;}
function trb_portal_release_staging_session_dir($token,$create){return $GLOBALS['root'].'/staging/'.$token;}
function trb_portal_release_is_staged_path($p){$real=realpath($p);return $real&&is_file($real)&&str_starts_with($real,$GLOBALS['root'].'/staging/');}
require __DIR__.'/../inc/trb-release-file-retry.php';
function check($v,$why){if(!$v)throw new RuntimeException($why);}
function fixture($id,$name,$kind,$track=null){$dir=wp_upload_dir()['basedir'].'/trb-release-private/'.$id;if(!is_dir($dir))mkdir($dir,0700,true);$path=$dir.'/'.$name;file_put_contents($path,$name.' bytes');return ['path'=>'trb-release-private/'.$id.'/'.$name,'name'=>$name,'original_name'=>$name,'sha256'=>hash_file('sha256',$path),'kind'=>$kind,'track'=>$track,'audio_status'=>'mastered','size'=>filesize($path)];}
try {
 $bad=fixture(1,'bad.wav','audio',0);$good=fixture(1,'good.png','cover');$other=fixture(2,'other.wav','audio',0);
 $meta[1]['_trb_release_files']=[$good,$bad];$meta[1]['_trb_release_acquired_files']=['audio:0'=>$bad];
 $meta[1]['_trb_release_tracks']=[['title'=>'Same release']];
 $meta[1]['_trb_release_intake_phase']='files_partial';$meta[1]['_trb_release_submission_token']='11111111-1111-1111-1111-111111111111';
 $_POST['trb_release_submission_token']=$meta[1]['_trb_release_submission_token'];$GLOBALS['trb_verified_intake_id']=1;
 $before=$meta[1]['_trb_release_tracks'];
 check(!trb_file_retry_discard(1,'trb_track_audio[0]',$bad['sha256'],'PCM_MEASUREMENT_UNAVAILABLE'),'Transient measurement deleted file');
 check(is_file(trb_file_retry_path(1,$bad)),'Valid bytes removed on service error');
 $busy=true;check(!trb_file_retry_discard(1,'trb_track_audio[0]',$bad['sha256'],'MASTER_PEAK_REJECTED'),'Busy release mutated');$busy=false;
 $locked=true;check(!trb_file_retry_discard(1,'trb_track_audio[0]',$bad['sha256'],'MASTER_PEAK_REJECTED'),'Approved materials deleted');$locked=false;
 check(!trb_file_retry_discard(1,'trb_track_audio[0]','obsolete','MASTER_PEAK_REJECTED'),'Obsolete result deleted replacement');
 check(!trb_file_retry_path(1,$other),'Cross-release path accepted');
 check(!trb_file_retry_path(1,['path'=>'../outside.wav']),'Traversal accepted');
 check(trb_file_retry_discard(1,'trb_track_audio[0]',$bad['sha256'],'MASTER_PEAK_REJECTED'),'Rejected bytes not discarded');
 check(!is_file($root.'/'.$bad['path'])&&is_file($root.'/'.$good['path']),'Deletion affected wrong slot');
 check(isset($meta[1]['_trb_release_files'][1]['rejected'])&&!isset($meta[1]['_trb_release_files'][1]['path']),'Missing replacement slot or dangling path');
 check($before===$meta[1]['_trb_release_tracks']&&empty($meta[1]['_trb_release_acquired_files']),'Metadata changed or checkpoint revived rejected file');
 $manifest=trb_file_retry_manifest(1);check(isset($manifest['trb_release_cover'])&&!isset($manifest['trb_track_audio[0]']),'Bad file offered for reuse');
 $upload=trb_file_retry_retained_upload('trb_release_cover',$good['sha256']);check($upload&&$upload['tmp_name']===$root.'/'.$good['path'],'Valid acquired file cannot be resumed');
 check(trb_file_retry_reuse(1,$upload,'cover',null)===$good,'Retry copied/changed accepted file');
 $owner=8;check(!trb_file_retry_retained_upload('trb_release_cover',$good['sha256']),'Cross-owner reuse');$owner=7;
 $_POST['trb_release_submission_token']='wrong';check(!trb_file_retry_retained_upload('trb_release_cover',$good['sha256']),'Cross-receipt reuse');$_POST['trb_release_submission_token']=$meta[1]['_trb_release_submission_token'];
 check(!trb_file_retry_retained_upload('trb_track_audio[0]',$good['sha256']),'Cross-slot reuse');
 file_put_contents($root.'/'.$good['path'],'changed');check(!trb_file_retry_retained_upload('trb_release_cover',$good['sha256']),'Changed private bytes reused');
 $dir=trb_portal_release_staging_session_dir($_POST['trb_release_submission_token'],false);mkdir($dir,0700,true);
 file_put_contents($dir.'/f1002.part','presentation');file_put_contents($dir.'/f1002.json',json_encode(['field_name'=>'trb_release_presentation','name'=>'presentation.txt','size'=>12,'complete'=>true]));
 $manifest=trb_file_retry_manifest(1);check(($manifest['trb_release_presentation']['key']??'')==='f1002','Complete staged field not recovered after reload');
 $staged=['_trb_hash'=>hash_file('sha256',$dir.'/f1002.part'),'_trb_staged'=>true,'tmp_name'=>$dir.'/f1002.part','_trb_field'=>'trb_release_presentation'];
 trb_file_retry_reject_upload($staged,new WP_Error('VIRUS_SCAN_FAILED'));check(is_file($dir.'/f1002.part'),'Antivirus outage deleted file');
 $obsolete=$staged;$obsolete['_trb_hash']='obsolete';trb_file_retry_reject_upload($obsolete,new WP_Error('invalid_presentation'));check(is_file($dir.'/f1002.part'),'Obsolete staging result deleted replacement');
 trb_file_retry_reject_upload($staged,new WP_Error('invalid_presentation'));check(!is_file($dir.'/f1002.part')&&!is_file($dir.'/f1002.json'),'Invalid staged bytes or manifest retained');
 check(in_array('trb_release_presentation',$GLOBALS['trb_discarded_upload_fields']??[],true),'Client not told which field to replace');
 // Exercise the real validator and acquisition function on a retained WAV.
 $source=file_get_contents(__DIR__.'/../inc/trb-artist-portal.php');
 foreach (array('trb_portal_wav_spec','trb_portal_validate_release_upload','trb_portal_validate_release_upload_bytes','trb_portal_store_release_upload') as $fn) {
  $start=strpos($source,'function '.$fn.'(');$end=strpos($source,"\n}\n",$start)+3;eval(substr($source,$start,$end-$start));
 }
 $audio=fixture(1,'accepted.wav','audio',0);$pcm=str_repeat(pack('v',1000),44100*2);
 $wav='RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,2,44100,176400,4,16).'data'.pack('V',strlen($pcm)).$pcm;
 file_put_contents($root.'/'.$audio['path'],$wav);$audio['sha256']=hash_file('sha256',$root.'/'.$audio['path']);$audio['size']=strlen($wav);$meta[1]['_trb_release_files'][1]=$audio;
 $upload=trb_file_retry_retained_upload('trb_track_audio[0]',$audio['sha256']);
 check(trb_portal_validate_release_upload($upload,'audio')===true,'Actual validator rejects retained WAV');
 $stored=trb_portal_store_release_upload(1,$upload,'audio',0,['audio_status'=>'mastered']);
 check($stored===$audio && file_get_contents($root.'/'.$audio['path'])===$wav,'Actual acquisition changed or moved the retained WAV');
 file_put_contents($dir.'/f1100.part','not a WAV');file_put_contents($dir.'/f1100.json','{}');
 $replacement=['_trb_hash'=>hash_file('sha256',$dir.'/f1100.part'),'name'=>'replacement.wav','error'=>UPLOAD_ERR_OK,'size'=>9,'tmp_name'=>$dir.'/f1100.part','_trb_staged'=>true,'_trb_field'=>'trb_release_replacement'];
 check(is_wp_error(trb_portal_validate_release_upload($replacement,'audio')),'Malformed replacement accepted');
 check(!is_file($dir.'/f1100.part') && is_file($root.'/'.$audio['path']),'Rejected replacement destroyed valid predecessor');
 echo "PASS actual WAV validator/acquisition reuse and rejected replacement preserves predecessor\n";
 echo "PASS per-file deletion, stable replacement slot, partial resume, staged reload, ownership, hash/race gates and transient failure preservation\n";
} finally {
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $p){$p->isDir()?rmdir($p->getPathname()):unlink($p->getPathname());}rmdir($root);
}
