<?php
// Functional tests of the real intake and start handler with an in-memory WP boundary.
define('ABSPATH', __DIR__); define('MINUTE_IN_SECONDS',60);
class WP_Error { public function __construct(public $code, public $message='', public $data=null) {} public function get_error_message(){return $this->message;} public function get_error_code(){return $this->code;} public function get_error_data(){return $this->data;} }
class Reply extends Exception { public function __construct(public $payload){parent::__construct('reply');} }
$posts=[];$options=[];$next=1;$logged=true;$nonce=true;
function is_wp_error($v){return $v instanceof WP_Error;}
function add_action(...$args){}
function add_filter(...$args){}
function get_userdata($id){return (object)["ID"=>$id];}
function absint($v){return abs((int)$v);}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));}
function wp_unslash($v){return $v;}
function get_current_user_id(){return 7;}
function is_user_logged_in(){global $logged;return $logged;}
function current_user_can($v){return true;}
function wp_verify_nonce($a,$b){global $nonce;return $nonce;}
function trb_portal_user_profile(){return $GLOBALS['test_profile']??'trb';}
function trb_portal_artist_profile_is_complete(){return true;}
function trb_portal_is_release_qa_account(){return true;}
function trb_portal_sanitize_release_tracks($tracks){return $tracks;}
function add_option($k,$v,...$rest){global $options;if(isset($options[$k]))return false;$options[$k]=$v;return true;}
function delete_option($k){global $options;unset($options[$k]);}
function get_posts($args){global $posts;$out=[];foreach(array_reverse($posts,true) as $id=>$p){
 if($p['post_author']!==$args['author'] || !in_array($p['post_status'],$args['post_status'],true))continue;
 if(isset($args['meta_key']) && ($p['meta_input'][$args['meta_key']]??null)!==$args['meta_value'])continue;
 $out[]=($args['fields']??'')==='ids'?$id:(object)$p;
 }return ($args['posts_per_page']??-1)>0?array_slice($out,0,$args['posts_per_page']):$out;}
function wp_insert_post($p,...$rest){global $posts,$next;$id=$next++;$p['ID']=$id;$posts[$id]=$p;return $id;}
function wp_update_post($p){global $posts;$posts[$p['ID']]=array_merge($posts[$p['ID']],$p);return $p['ID'];}
function wp_upload_dir(){return ['basedir'=>sys_get_temp_dir().'/trb-intake-tests-'.getmypid()];}
function wp_mkdir_p($p){return is_dir($p)||mkdir($p,0700,true);}
function wp_json_encode($v){return json_encode($v);}
register_shutdown_function(function(){ $d=wp_upload_dir()['basedir'].'/trb-release-locks';foreach(glob($d.'/*')?:[] as $p)unlink($p);if(is_dir($d))rmdir($d);if(is_dir(dirname($d)))rmdir(dirname($d)); });
function get_post_meta($id,$k,$single){global $posts;return $posts[$id]['meta_input'][$k]??'';}
function update_post_meta($id,$k,$v){global $posts;$posts[$id]['meta_input'][$k]=$v;}
function wp_send_json_success($p,...$rest){throw new Reply($p);}
function trb_portal_release_submission_response($s,$m='',$http=422,$id=0){throw new Reply(['status'=>$s,'message'=>$m,'release_id'=>$id]);}
require __DIR__.'/../inc/trb-release-intake.php';
$source=file_get_contents(__DIR__.'/../inc/trb-artist-portal.php');
$start=strpos($source,'function trb_portal_start_release() {');
$end=strpos($source,"add_action( 'admin_post_trb_portal_start_release'",$start);
eval(substr($source,$start,$end-$start));
function check($ok,$why){if(!$ok)throw new Exception($why);}
function submit(){try{trb_portal_start_release();}catch(Reply $r){return $r->payload;}throw new Exception('No response');}
$_POST=['trb_release_submission_token'=>'11111111-1111-1111-1111-111111111111','trb_release_title'=>'QA intake','trb_release_type'=>'single','trb_tracks'=>[['title'=>'Track']], 'trb_release_intake_only'=>'1'];
$logged=false;check(submit()['status']==='session_expired'&&count($posts)===0,'Unauthenticated receipt created');
$logged=true;$nonce=false;check(submit()['status']==='security_expired'&&count($posts)===0,'Invalid nonce created receipt');
$nonce=true;$GLOBALS['test_profile']=false;check(submit()['status']==='profile_required'&&count($posts)===0,'Missing profile reached receipt or upload');$GLOBALS['test_profile']='trb';$a=submit();check($a['status']==='received'&&$a['release_id']===1&&count($posts)===1,'Receipt not persisted');
check(get_post_meta(1,'_trb_release_pipeline_status',true)==='upload_incomplete','Premature pipeline progress');
check(get_post_meta(1,'_trb_contract_state',true)==='waiting_upload','Premature contracts');
$b=submit();check($b['release_id']===1&&count($posts)===1,'Duplicate receipt');
trb_intake_failure('invalid','Invalid credit');check(get_post_meta(1,'_trb_release_intake_phase',true)==='validation_failed','Failure not durable');
check(submit()['release_id']===1&&count($posts)===1,'Retry duplicated failed receipt');
update_post_meta(1,'_trb_release_intake_phase','files_partial');check(submit()['status']==='recovery_required','Partial acquisition reported success');
update_post_meta(1,'_trb_release_intake_phase','complete');unset($_POST['trb_release_intake_only']);check(submit()['status']==='created','Completed retry required missing files');
check(trb_intake_find(8,$_POST['trb_release_submission_token'])===0,'Cross-user lookup');
check(is_wp_error(trb_intake_record(7,'bad',[])),'Invalid token accepted');
echo "PASS intake authentication, persistence, duplicate retries, partial failure and ownership\n";

update_post_meta(1,'_trb_release_intake_phase','validation_failed');
check(trb_intake_protect_pipeline(null,1,'_trb_release_pipeline_status','approved')===true,'Incomplete intake technically approved');
check(trb_intake_protect_pipeline(null,1,'_trb_release_pipeline_status','upload_failed')===null,'Failure suppressed');
update_post_meta(1,'_trb_release_intake_phase','complete');
check(trb_intake_protect_pipeline(null,1,'_trb_release_pipeline_status','approved')===null,'Completed technical review blocked');
$GLOBALS['trb_crm_sync_update_from_crm']=true;
check(trb_intake_protect_pipeline(null,1,'_trb_release_pipeline_status','approved')===true,'CRM business status changed technical approval');
check(trb_intake_protect_pipeline(null,1,'_trb_crm_workflow_status','ready')===null,'CRM business state blocked');
echo "PASS independent commercial and technical status gates\n";

function get_post($id){global $posts;return isset($posts[$id])?(object)$posts[$id]:null;}
function trb_portal_artist_profile_value(...$args){return 'QA artist';}
function trb_release_pcloud_master_folder(...$args){return '/QA';}
function trb_release_pcloud_mastering_folder(...$args){return '/QA/mastering';}
function trb_demo_ensure_remote_folder($folder){return true;}
function trb_release_pcloud_local_file($file){return '/test.wav';}
function trb_portal_release_audio_filename($id,$index,...$args){return $index.'.wav';}
$publishedFiles=0;$failAt=7;
function trb_release_pcloud_publish_file(...$args){global $publishedFiles,$failAt;$publishedFiles++;return $publishedFiles===$failAt?new WP_Error('network_error'):true;}
function do_action(...$args){}
function trb_portal_delete_release_files($files){}
function delete_post_meta($id,$key){global $posts;unset($posts[$id]['meta_input'][$key]);}
$cloudSource=file_get_contents(__DIR__.'/../inc/trb-release-pcloud-archive.php');
$cloudStart=strpos($cloudSource,'function trb_release_pcloud_sync( $release_id ) {');
$cloudEnd=strpos($cloudSource,'function trb_release_pcloud_run_sync(', $cloudStart);
eval(substr($cloudSource,$cloudStart,$cloudEnd-$cloudStart));
update_post_meta(1,'_trb_release_tracks',array_fill(0,24,['title'=>'QA track']));
update_post_meta(1,'_trb_release_intake_phase','validation_failed');
check(trb_release_pcloud_sync(1)->code==='release_intake_incomplete'&&$publishedFiles===0,'Incomplete intake attempted pCloud');
update_post_meta(1,'_trb_release_intake_phase','complete');
check(trb_release_pcloud_sync(1)->code==='release_audio_incomplete'&&$publishedFiles===0,'Missing WAVs attempted pCloud');
$qaFiles=[];for($i=0;$i<24;$i++)$qaFiles[]=['kind'=>'audio','track'=>$i,'audio_status'=>'mastered'];
update_post_meta(1,'_trb_release_files',$qaFiles);
$result=trb_release_pcloud_sync(1);
$partial=get_post_meta(1,'_trb_release_pcloud_archive',true);
check(is_wp_error($result)&&count($partial['files'])===6&&$partial['verified']===false,'Partial progress lost or marked verified');
$failAt=0;$publishedFiles=0;
$result=trb_release_pcloud_sync(1);
$finished=get_post_meta(1,'_trb_release_pcloud_archive',true);
check(!is_wp_error($result)&&count($finished['files'])===24&&$finished['verified']===true,'Complete batch not recorded');
echo "PASS pCloud empty batch guard and durable progress with failure at file 7 of 24\n";

update_post_meta(1,'_trb_release_intake_phase','acquiring_files');
update_post_meta(1,'_trb_release_pipeline_status','upload_incomplete');
update_post_meta(1,'_trb_release_acquisition_started_at',time());
check(trb_intake_recover_stalled(1)===false,'Active acquisition interrupted');
update_post_meta(1,'_trb_release_acquisition_started_at',time()-1900);
check(trb_intake_recover_stalled(1)===true && get_post_meta(1,'_trb_release_intake_phase',true)==='files_partial','Stale acquisition not recoverable');
update_post_meta(1,'_trb_release_intake_phase','acquiring_files');
update_post_meta(1,'_trb_release_acquisition_started_at',0);
update_post_meta(1,'_trb_release_pipeline_status','isrc_assignment_failed');
check(trb_intake_recover_stalled(1)===true,'Historical ISRC failure remains stuck');
update_post_meta(1,'_trb_release_intake_phase','complete');
check(trb_intake_recover_stalled(1)===false,'Completed intake modified');
trb_intake_refresh_draft(1,['trb_release_date'=>'2027-01-01']);
check(get_post_meta(1,'_trb_release_date',true)!=='2027-01-01','Completed data overwritten');
update_post_meta(1,'_trb_release_intake_phase','validation_failed');
trb_intake_refresh_draft(1,['trb_release_date'=>'2027-01-01']);
check(get_post_meta(1,'_trb_release_date',true)==='2027-01-01','Retry kept stale date');
echo "PASS stale acquisition, historical ISRC recovery, latest retry metadata and completed-intake protection\n";

// Same project/new browser token must never create another receipt.
$project=['trb_release_title'=>'Different project','trb_release_type'=>'single','trb_tracks'=>[['title'=>'Song','version'=>'']]];
$token='22222222-2222-2222-2222-222222222222';
$id=trb_intake_record(7,$token,$project);check(is_int($id),'Project rejected');
$changed=$project;$changed['trb_release_date']='2027-05-01';$changed['trb_tracks'][0]['duration_seconds']=55;
$retry=trb_intake_record(7,'33333333-3333-3333-3333-333333333333',$changed);
check(is_wp_error($retry)&&$retry->get_error_code()==='existing_release'&&$retry->get_error_data()===$id,'Changed token/date/duration duplicated project');
check(is_int(trb_intake_record(8,'44444444-4444-4444-4444-444444444444',$project)),'Different artist wrongly blocked');
$version=$project;$version['trb_tracks'][0]['version']='Acoustic';
check(is_int(trb_intake_record(7,'55555555-5555-5555-5555-555555555555',$version)),'Distinct version wrongly blocked');
check(trb_intake_record(7,$token,$version)->get_error_code()==='existing_release','Editing an existing token duplicated another project');
update_post_meta($id,'_trb_release_intake_phase','complete');
check(trb_intake_record(7,$token,$project)===$id,'Completed retry rejected');
check(is_wp_error(trb_intake_record(7,$token,$version)),'New project silently accepted with completed token');
$lock=trb_release_process_lock('intake-user:7');
check(trb_intake_record(7,$token,$project)->get_error_code()==='intake_busy','Concurrent receipt not serialized');
trb_release_process_unlock($lock);check(trb_intake_record(7,$token,$project)===$id,'Released process left a stuck lock');
$posts[$id]['post_status']='trash';
check(is_int(trb_intake_record(7,'66666666-6666-6666-6666-666666666666',$project)),'Trashed practice blocks new submission');
check(is_wp_error(trb_intake_record(7,'77777777-7777-7777-7777-777777777777',[])),'Empty receipt accepted');
echo "PASS cross-token duplicate prevention, artist isolation, editions, completed token, process lock and trash
";
$posts[$id]['post_status']='private';
update_post_meta($id,'_trb_release_intake_phase','acquiring_files');
update_post_meta($id,'_trb_release_acquisition_started_at',time()-1900);
$file=['kind'=>'audio','track'=>0,'path'=>'private.wav','sha256'=>'hash'];
trb_intake_checkpoint_file($id,$file);
$lock=trb_release_process_lock('release:'.$id);
check(!trb_intake_recover_stalled($id),'Recovery interrupted an active worker');
trb_release_process_unlock($lock);
check(trb_intake_recover_stalled($id),'Interrupted worker did not recover');
check(get_post_meta($id,'_trb_release_files',true)===[$file],'Durable acquired file lost on recovery');
echo "PASS durable acquisition checkpoint and recovery concurrency
";
