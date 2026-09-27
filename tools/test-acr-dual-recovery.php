<?php
/** Regression: a slow Cover Song job must never masquerade as an engine mismatch. */
define('ABSPATH',__DIR__.'/'); define('MINUTE_IN_SECONDS',60);
function add_action(...$a) {} function add_filter(...$a) {}
function absint($v) {return abs((int)$v);} function sanitize_key($v){return $v;}
function sanitize_text_field($v){return $v;} function wp_json_encode($v){return json_encode($v);}
function current_time(...$a){return '2026-09-27 20:00:00';}
function get_post_status($id){return 'publish';}
function get_option($k,$d=[]){return $GLOBALS['options'][$k]??$d;} function wp_parse_args($a,$b){return array_merge($b,$a);}
function get_post_meta($id,$key,$single=true){return $GLOBALS['input_meta'][$key]??'';}
function trb_release_process_lock($key){return true;} function trb_release_process_unlock($lock){}
function trb_release_current_audio_hash(...$a){return true;}
function wp_remote_get($url,$args){$GLOBALS['requested_url']=$url;return $GLOBALS['response'];}
function is_wp_error($v){return false;}
function wp_remote_retrieve_response_code($v){return $v['code'];}
function wp_remote_retrieve_body($v){return json_encode(['data'=>$v['item']]);}
function wp_next_scheduled(...$a){return false;}
function wp_schedule_single_event($when,$hook,$args){$GLOBALS['scheduled'][]=[$when,$hook,$args];}
function update_post_meta($id,$key,$v){$GLOBALS['meta'][$key]=$v;}
class RecoveryDB {
 public $prefix='wp_', $insert_id=1, $row, $writes=[], $events=[];
 function prepare($sql,...$args){return $sql;}
 function get_row($sql){return str_contains($sql,'trb_usage_ledger')?$this->row:null;}
 function update($table,$data,$where){$this->writes[]=[$table,$data]; return 1;}
 function insert($table,$data){$this->events[]=$data; return 1;}
}
require dirname(__DIR__).'/inc/trb-resource-monitor.php';
function check($v,$message){if(!$v)throw new RuntimeException($message);}
foreach([1,2] as $engine){
 foreach([1,-1] as $state)check(''===trb_resource_dual_acr_result_error(['engine'=>$engine,'state'=>$state],$engine),'Completed result accepted');
 check('ACR_PROVIDER_PROCESSING'===trb_resource_dual_acr_result_error(['engine'=>$engine,'state'=>0],$engine),'Pending is not mismatch');
 check('ACR_PROVIDER_STATE_-2'===trb_resource_dual_acr_result_error(['engine'=>$engine,'state'=>-2],$engine),'Provider failure distinct');
}
check('ACR_DUAL_ENGINE_MISMATCH_1_EXPECTED_2'===trb_resource_dual_acr_result_error(['engine'=>1,'state'=>1],2),'Actual mismatch fails');
check('ACR_HTTP_503'===trb_resource_dual_acr_result_error(['engine'=>2,'state'=>1],2,503),'HTTP failure cannot complete');
check('ACR_RESPONSE_INVALID'===trb_resource_dual_acr_result_error([],2),'Missing response fails');
$legacy=['id'=>'known-job','cid'=>35033,'engine'=>2,'state'=>0,'duration'=>0,'results'=>null];
$wpdb=new RecoveryDB();
$wpdb->row=(object)['id'=>4,'release_id'=>12339,'track_index'=>0,'file_hash'=>str_repeat('a',64),'service'=>'cover_song_scan','status'=>'error','attempts'=>30,'provider_reference'=>'known-job','payload'=>json_encode($legacy)];
check([35033,2]===trb_resource_dual_acr_context($wpdb->row,$legacy),'Legacy context recovered');
check([0,2]===trb_resource_dual_acr_context($wpdb->row,array_merge($legacy,['id'=>'another-job'])),'Cannot recover context from a different file');
$response=['code'=>200,'item'=>$legacy]; $scheduled=[];
trb_resource_poll_dual_acr_job(4);
$write=$wpdb->writes[0][1]; $saved=json_decode($write['payload'],true);
check('processing'===$write['status'] && 31===$write['attempts'],'At attempt 30 pending job remains processing');
check('ACR_PROVIDER_PROCESSING'===$write['last_error'],'Accurate pending diagnostic');
check(35033===$saved['trb_container_id'] && 2===$saved['trb_expected_engine'],'Context preserved');
check(count($scheduled)===1 && $scheduled[0][0]>=time()+899,'Backoff polling scheduled');
check('analysis_in_progress'===$GLOBALS['meta']['_trb_release_pipeline_status'],'No release approval while pending');
check(str_ends_with($GLOBALS['requested_url'],'/35033/files/known-job'),'Existing job reused');
check('warning'===$wpdb->events[0]['severity'],'Slow job has accurate warning');
$wpdb->writes=[]; $wpdb->row->attempts=126; $scheduled=[];
trb_resource_poll_dual_acr_job(4);
check('ACR_PROVIDER_TIMEOUT'===$wpdb->writes[0][1]['last_error'],'Bounded polling ends in timeout');
check(!$scheduled,'Terminal timeout does not spin forever');
$wpdb->writes=[]; $wpdb->row->status='completed';
trb_resource_poll_dual_acr_job(4);
check(!$wpdb->writes,'Duplicate poll does not repeat completion');
$options['trb_resource_monitor_settings']=['acr_fingerprint_container_id'=>35034,'acr_container_id'=>35033];
$input_meta['_trb_release_files']=[['kind'=>'audio','track'=>0,'sha256'=>str_repeat('a',64)]];
$wpdb->row->status='error'; $wpdb->row->attempts=30; $scheduled=[];
check(true===trb_resource_start_dual_acr_analysis(12339),'Manual retry reuses jobs without local audio');
check(count($scheduled)===2,'Both independent jobs are scheduled for polling');
foreach($wpdb->writes as $write) check('processing'===$write[1]['status'] && 0===$write[1]['attempts'],'Retry restarts polling, not uploads');
echo "ACR pending, failure, legacy recovery, bounded retry and completion guards passed.\n";
