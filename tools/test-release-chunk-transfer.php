<?php
/** Exercise the production handler with real files and a simulated HTTP boundary. */
namespace ChunkRegression;
const MB_IN_BYTES = 1048576;
class Reply extends \Exception { public function __construct(public $ok, public $data) {} }
function is_user_logged_in(){return true;}
function trb_portal_cleanup_expired_release_staging(){}
function wp_verify_nonce(...$a){return true;}
function sanitize_text_field($s){return $s;}
function sanitize_file_name($s){return $s;}
function sanitize_mime_type($s){return $s;}
function sanitize_key($s){return $s;}
function wp_unslash($s){return $s;}
function absint($s){return abs((int)$s);}
function is_uploaded_file($s){return is_file($s);}
function trb_portal_release_max_file_bytes(){return 250*MB_IN_BYTES;}
function trb_portal_release_max_submission_bytes(){return 4*1024*MB_IN_BYTES;}
function trb_portal_release_staging_declared_bytes(...$a){return 0;}
function trb_portal_release_staging_session_dir(...$a){return $GLOBALS['chunk_dir'];}
function trailingslashit($s){return $s.'/';}
function wp_json_encode($s){return json_encode($s);}
function wp_delete_file($s){if(is_file($s))unlink($s);}
function wp_send_json_success($s,...$a){throw new Reply(true,$s);}
function wp_send_json_error($s,...$a){throw new Reply(false,$s);}
function check($ok,$why){if(!$ok)throw new \RuntimeException($why);}
$source=file_get_contents($argv[1] ?? __DIR__.'/../inc/trb-artist-portal.php');
$start=strpos($source,'function trb_portal_stage_release_chunk() {');
$end=strpos($source,"add_action( 'admin_post_trb_portal_stage_release_chunk'",$start);
eval('namespace ChunkRegression; '.substr($source,$start,$end-$start));
$GLOBALS['chunk_dir']=sys_get_temp_dir().'/trb-chunks-'.bin2hex(random_bytes(6));
mkdir($GLOBALS['chunk_dir']);
function send($index,$total,$size,$bytes,$id='one'){
 clearstatcache();
 $_POST=['trb_release_stage_nonce'=>'valid','session'=>'11111111-1111-1111-1111-111111111111','file_key'=>'f0','file_name'=>'test.txt','file_type'=>'text/plain','file_size'=>$size,'last_modified'=>1,'chunk_index'=>$index,'chunk_total'=>$total,'upload_id'=>$id];
 $tmp=$GLOBALS['chunk_dir'].'/http';file_put_contents($tmp,$bytes);
 $_FILES=['trb_release_chunk'=>['tmp_name'=>$tmp,'error'=>0,'size'=>strlen($bytes)]];
 try{trb_portal_stage_release_chunk();}catch(Reply $r){return $r;}
 throw new \RuntimeException('Missing response');
}
try{
 $a=str_repeat('A',5*MB_IN_BYTES);$b=str_repeat('B',137);$size=strlen($a)+strlen($b);
 check(send(0,2,$size,$a)->ok,'First block rejected');
 check(send(0,2,$size,$a)->ok,'Repeated block rejected');
 check(send(1,2,$size,$b)->ok,'Complete multi-block file rejected');
 check(hash_file('sha256',$GLOBALS['chunk_dir'].'/f0.part')===hash('sha256',$a.$b),'File bytes changed');
 check(send(1,2,$size,$b)->ok,'Completed retry rejected');
 check(send(0,1,3,'XYZ','replacement')->ok,'Replacement failed');
 check(file_get_contents($GLOBALS['chunk_dir'].'/f0.part')==='XYZ','Replacement reused old bytes');
 check(!send(0,1,4,'XYZ','truncated')->ok,'Real truncation accepted');
 $bad=send(2,1,3,'XYZ','invalid');
 check(!$bad->ok&&!str_contains($bad->data['message'],'250 MB'),'Invalid sequence mislabeled as oversized');
 $large=send(0,1,251*MB_IN_BYTES,'XYZ','large');
 check(!$large->ok&&str_contains($large->data['message'],'250 MB'),'Size limit lost');
 echo "PASS multi-block bytes, replay, replacement, truncation and distinct errors\n";
}finally{
 foreach(glob($GLOBALS['chunk_dir'].'/*') as $p)unlink($p);
 rmdir($GLOBALS['chunk_dir']);
}
