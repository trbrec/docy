<?php
// Synthetic fixtures only: no production records, API requests, contracts or email.
define('ABSPATH',__DIR__);function add_action(...$args){};function add_filter(...$args){};
class WP_Error {public function __construct(public $code,public $message='',public $data=[]){} }
function is_wp_error($v){return $v instanceof WP_Error;}
function sanitize_textarea_field($v){return trim(strip_tags($v));}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function trb_resource_artist_email_signature(){return '<p>TRB rec</p>';}
require dirname(__DIR__).'/inc/trb-crm-inline-review.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$tracks=[];foreach(range(0,2) as $i)$tracks[]=['index'=>$i,'title'=>'Fixture '.$i,'analysis_available'=>true,'matches'=>[['id'=>'match-'.$i.'-a','artists'=>['QA artist'],'title'=>'Reference '.$i.' A'],['id'=>'match-'.$i.'-b','artists'=>['QA artist'],'title'=>'Reference '.$i.' B']]];
$snapshot=['tracks'=>$tracks];$input=[['track'=>0,'action'=>'approve','listened'=>true],['track'=>1,'action'=>'reject','listened'=>true,'note'=>'Motivazione per il secondo brano.','selected_matches'=>['match-1-b']],['track'=>2,'action'=>'reject','listened'=>true,'note'=>'Motivazione per il terzo brano.','selected_matches'=>['match-2-a']]];
$result=trb_crm_inline_validate_batch($snapshot,$input);check(!is_wp_error($result)&&count($result)===3,'Valid partial match selection rejected');
$email=trb_crm_inline_email_body('QA',array_values(array_filter($result,fn($v)=>$v['action']==='reject')));
check(str_contains($email,'Gentile QA,')&&str_contains($email,'Reference 1 B')&&str_contains($email,'Reference 2 A'),'Selected reasons missing');
check(!str_contains($email,'Fixture 0')&&!str_contains($email,'Reference 1 A')&&!str_contains($email,'Reference 2 B'),'Unselected finding leaked into email');
foreach(['foreign','duplicate','unheard','short','conflict','missing_analysis'] as $case){$bad=$input;$snap=$snapshot;
 if($case==='foreign')$bad[1]['selected_matches']=['match-2-a'];
 if($case==='duplicate')$bad[]=$bad[0];
 if($case==='unheard')$bad[0]['listened']=false;
 if($case==='short')$bad[1]['note']='no';
 if($case==='conflict')$bad[0]['selected_matches']=['match-0-a'];
 if($case==='missing_analysis')$snap['tracks'][0]['analysis_available']=false;
 check(is_wp_error(trb_crm_inline_validate_batch($snap,$bad)),'Invalid batch accepted: '.$case);
}
$files=[['sha256'=>'new-file']];$reviews=[['sha256'=>'old-file','analysis_revision'=>'r1','action'=>'approve']];
check(!trb_crm_inline_current_reviews($files,$reviews,'r1'),'Old audio approval reused');
$reviews[0]['sha256']='new-file';check(!trb_crm_inline_current_reviews($files,$reviews,'r2'),'Old analysis approval reused');
check(count(trb_crm_inline_current_reviews($files,$reviews,'r1'))===1,'Current approval lost');
echo "Inline review: selective notifications, validation and stale evidence checks passed.\n";
function wp_json_encode($v){return json_encode($v);}
function get_post_type($id){return 'trb_release';}
function trb_release_is_inactive($id){return false;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['fixtureMeta'][$key]??[];}
function update_post_meta($id,$key,$value){$GLOBALS['fixtureMeta'][$key]=$value;return true;}
function trb_resource_tables(){return ['usage'=>'fixture_usage'];}
function trb_analysis_normalize_acr_result($v){return $v;}
function trb_release_pcloud_local_file($f){return '/qa/audio.wav';}
function trb_release_technical_is_current($id){return $GLOBALS['fixtureTechnical'];}
function trb_portal_release_has_final_cover($id){return true;}
function do_action($hook,$id){$GLOBALS['fixtureDispatches']++;}
define('ARRAY_A','ARRAY_A');
$GLOBALS['wpdb']=new class {function prepare($sql,...$args){return $sql;}function get_results(...$args){return [['track_index'=>0,'file_hash'=>'current','payload'=>'{"matches":[]}']];}};
$GLOBALS['fixtureMeta']=['_trb_release_files'=>[['kind'=>'audio','track'=>0,'sha256'=>'current','path'=>'qa.wav']],'_trb_release_tracks'=>[['title'=>'QA']], '_trb_release_pipeline_status'=>'technical_error','_trb_release_technical_analysis'=>['status'=>'failed','errors'=>['QA clipping']],'_trb_release_pcloud_archive'=>['verified'=>true],'_trb_contract_state'=>'waiting_analysis'];
$GLOBALS['fixtureTechnical']=false;$GLOBALS['fixtureDispatches']=0;
$snap=trb_crm_inline_snapshot(1);check($snap['can_decide']&&!$snap['can_finalize'],'Technical block incorrectly prevents rights review or permits contract');
$GLOBALS['fixtureMeta']['_trb_crm_inline_reviews']=[['action'=>'approve','sha256'=>'current','analysis_revision'=>$snap['tracks'][0]['analysis_revision']]];
check(!trb_crm_inline_apply_review(1,true)&&$GLOBALS['fixtureDispatches']===0&&$GLOBALS['fixtureMeta']['_trb_release_pipeline_status']==='technical_error','Copyright approval erased technical blocker');
$GLOBALS['fixtureTechnical']=true;$GLOBALS['fixtureMeta']['_trb_release_technical_analysis']=['status'=>'passed'];
check(trb_crm_inline_apply_review(1,true)&&$GLOBALS['fixtureDispatches']===1&&$GLOBALS['fixtureMeta']['_trb_release_pipeline_status']==='approved','Successful review did not resume contract workflow');
$GLOBALS['fixtureMeta']['_trb_contract_state']='contract_sent';trb_crm_inline_apply_review(1,true);check($GLOBALS['fixtureDispatches']===1,'Contract sent again');
echo "Technical gates and contract dispatch checks passed.\n";
function trb_crm_connector_settings(){return ['secret'=>str_repeat('fixture',8)];}
$request=new class {public $headers=[];public function get_header($k){return $this->headers[$k]??'';}public function get_body(){return '{"fixture":true}';}};
check(is_wp_error(trb_crm_inline_permission($request)),'Unsigned request accepted');
$request->headers=['x-trb-timestamp'=>(string)time(),'x-trb-signature'=>'sha256='.hash_hmac('sha256',time().'.'.$request->get_body(),trb_crm_connector_settings()['secret'])];
check(trb_crm_inline_permission($request)===true,'Signed fixture rejected');
$request->headers['x-trb-signature'].='0';check(is_wp_error(trb_crm_inline_permission($request)),'Tampered signature accepted');
echo "Signed service request validation passed.\n";
