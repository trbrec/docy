<?php
// Functional tests of the real intake and start handler with an in-memory WP boundary.
define('ABSPATH', __DIR__); define('MINUTE_IN_SECONDS',60);
class WP_Error { public function __construct(public $code, public $message='') {} public function get_error_message(){return $this->message;} }
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
function trb_portal_user_profile(){return 'trb';}
function trb_portal_artist_profile_is_complete(){return true;}
function trb_portal_is_release_qa_account(){return true;}
function trb_portal_sanitize_release_tracks($tracks){return $tracks;}
function add_option($k,$v,...$rest){global $options;if(isset($options[$k]))return false;$options[$k]=$v;return true;}
function delete_option($k){global $options;unset($options[$k]);}
function get_posts($args){global $posts;$out=[];foreach($posts as $id=>$p)if($p['post_author']===$args['author']&&($p['meta_input'][$args['meta_key']]??null)===$args['meta_value'])$out[]=$id;return array_slice($out,0,1);}
function wp_insert_post($p,...$rest){global $posts,$next;$id=$next++;$posts[$id]=$p;return $id;}
function wp_update_post($p){return $p['ID'];}
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
$nonce=true;$a=submit();check($a['status']==='received'&&$a['release_id']===1&&count($posts)===1,'Receipt not persisted');
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
