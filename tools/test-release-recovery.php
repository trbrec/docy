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
