<?php
define('ABSPATH',__DIR__);
function add_action(...$args){}
function absint($v){return abs((int)$v);}
function sanitize_file_name($v){return basename($v);}
function sanitize_mime_type($v){return (string)$v;}
function trb_portal_release_staging_root($id){global $root;return $root.'/'.$id;}
require __DIR__.'/../inc/trb-release-recovery.php';
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);}
$root=sys_get_temp_dir().'/trb-recovery-'.bin2hex(random_bytes(8));
mkdir($root.'/7/session',0700,true);mkdir($root.'/8/session',0700,true);
file_put_contents($root.'/7/session/f0.part','audio');file_put_contents($root.'/7/session/f0.json',json_encode(['name'=>'qa.wav','type'=>'audio/wav','size'=>5,'complete'=>true]));
file_put_contents($root.'/7/session/f1.part','short');file_put_contents($root.'/7/session/f1.json',json_encode(['name'=>'short.wav','size'=>9,'complete'=>true]));
file_put_contents($root.'/7/session/f2.part','unfinished');file_put_contents($root.'/7/session/f2.json',json_encode(['name'=>'partial.wav','size'=>10,'complete'=>false]));
file_put_contents($root.'/8/session/f0.part','other');file_put_contents($root.'/8/session/f0.json',json_encode(['name'=>'other.wav','size'=>5,'complete'=>true]));
symlink($root.'/8/session/f0.part',$root.'/7/session/f3.part');file_put_contents($root.'/7/session/f3.json',json_encode(['name'=>'escape.wav','size'=>5,'complete'=>true]));
try{
 $found=trb_recovery_file_candidates(7);verify(count($found)===1,'Incomplete, mismatched or cross-owner files admitted');
 $file=array_values($found)[0];verify($file['name']==='qa.wav'&&$file['size']===5,'Valid received file lost');
 verify(count(trb_recovery_file_candidates(8))===1,'Owner file lookup mixed');
 verify(trb_recovery_file_candidates(99)===[],'Missing root not handled');
 echo "PASS recovery ownership, symlink escape, completion and size checks\n";
}finally{
 foreach([7,8] as $id){foreach(glob($root.'/'.$id.'/session/*') as $p)unlink($p);rmdir($root.'/'.$id.'/session');rmdir($root.'/'.$id);}rmdir($root);
}

class WP_Error { public $code; function __construct($code){$this->code=$code;} }
function trb_release_pcloud_local_file($file){return $file['local']??'';}
$local=tempnam(sys_get_temp_dir(),'recovery-private');
$stage=tempnam(sys_get_temp_dir(),'recovery-stage');
file_put_contents($local,'verified-pcm');file_put_contents($stage,'verified-pcm');
$stored=['kind'=>'audio','track'=>0,'local'=>$local,'sha256'=>hash_file('sha256',$local)];
try {
 verify(trb_recovery_reuse_file(12,['tmp_name'=>$stage],'audio',0)===null,'Normal submissions affected');
 $GLOBALS['trb_recovery_resume_context']=['id'=>12,'files'=>[$stored]];
 verify(trb_recovery_reuse_file(13,['tmp_name'=>$stage],'audio',0)===null,'Cross-release reuse allowed');
 verify(trb_recovery_reuse_file(12,['tmp_name'=>$stage],'audio',0)===$stored,'Verified file not reused');
 verify(trb_recovery_reuse_file(12,['tmp_name'=>$stage],'audio',1) instanceof WP_Error,'Wrong track accepted');
 file_put_contents($stage,'changed');
 verify(trb_recovery_reuse_file(12,['tmp_name'=>$stage],'audio',0) instanceof WP_Error,'Changed staging accepted');
 file_put_contents($stage,'verified-pcm');file_put_contents($local,'changed');
 verify(trb_recovery_reuse_file(12,['tmp_name'=>$stage],'audio',0) instanceof WP_Error,'Changed private file accepted');
 echo "PASS recovered file reuse, release isolation and integrity checks\n";
} finally { unlink($local);unlink($stage);unset($GLOBALS['trb_recovery_resume_context']); }

function get_post_meta($id,$key,$single){return $GLOBALS['recovery_meta'][$key]??'';}
$GLOBALS['recovery_meta']=['_trb_release_intake_phase'=>'files_partial','_trb_release_files'=>[['kind'=>'audio']]];
verify(trb_recovery_ready_for_validation(12),'Stored files cannot enter ordinary validation after ISRC failure');
$GLOBALS['recovery_meta']['_trb_release_files']=[];
verify(!trb_recovery_ready_for_validation(12),'Empty files offered for resume');
$GLOBALS['recovery_meta']['_trb_release_files']=[['kind'=>'audio']];
foreach(['awaiting_upload','acquiring_files','complete'] as $phase){$GLOBALS['recovery_meta']['_trb_release_intake_phase']=$phase;verify(!trb_recovery_ready_for_validation(12),'Unsafe phase offered for resume');}
echo "PASS partial stored files expose standard validation without reopening completed or active intake\n";

$GLOBALS['recovery_meta']['_trb_release_files']=[['kind'=>'audio','track'=>0,'sha256'=>'new']];
$GLOBALS['trb_recovery_resume_context']=['id'=>12,'files'=>[['kind'=>'audio','track'=>0,'sha256'=>'old']]];
verify(!trb_recovery_context_matches_files(12),'Recovery overwrote a replacement made during validation');
$GLOBALS['trb_recovery_resume_context']['files'][0]['sha256']='new';
verify(trb_recovery_context_matches_files(12),'Unchanged recovery snapshot rejected');
unset($GLOBALS['trb_recovery_resume_context']);
verify(trb_recovery_context_matches_files(12),'Ordinary submission affected');
echo "PASS recovery snapshot freshness after concurrent replacement\n";
