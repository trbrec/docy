<?php
/** Exercise production guards and worker entry points without external side effects. */
define('ABSPATH',__DIR__); define('ARRAY_A','ARRAY_A'); define('MINUTE_IN_SECONDS',60);
class WP_Error { function __construct(public $code,public $message='',public $data=null){} }
function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower($v);}
function sanitize_text_field($v){return trim(strip_tags($v));}
function wp_json_encode($v){return json_encode($v);}
function get_post($id){return $GLOBALS['posts'][$id]??null;}
function get_post_type($id){return get_post($id)->post_type??'';}
function get_post_status($id){return get_post($id)->post_status??'';}
function get_post_meta($id,$key,$single){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
function wp_upload_dir(){return ['basedir'=>sys_get_temp_dir().'/trb-integrity-'.getmypid()];}
function wp_mkdir_p($p){return is_dir($p)||mkdir($p,0700,true);}
function wp_next_scheduled(...$args){return false;}
function wp_schedule_single_event($at,$hook,$args){$GLOBALS['scheduled'][]=[$hook,$args];}
function trb_resource_tables(){return ['usage'=>'test_usage'];}
function trb_resource_now(){return gmdate('Y-m-d H:i:s');}
function verify($ok,$why){if(!$ok)throw new RuntimeException($why);}
function load_function($path,$name,$next){$s=file_get_contents(__DIR__.'/../inc/'.$path);$a=strpos($s,'function '.$name.'(');$b=strpos($s,$next,$a);verify($a!==false&&$b!==false,'Function boundary missing: '.$name);eval(substr($s,$a,$b-$a));}
require __DIR__.'/../inc/trb-release-integrity.php';
register_shutdown_function(function(){$d=wp_upload_dir()['basedir'].'/trb-release-locks';foreach(glob($d.'/*')?:[] as $p)unlink($p);if(is_dir($d))rmdir($d);if(is_dir(dirname($d)))rmdir(dirname($d));});
$posts=[1=>(object)['ID'=>1,'post_type'=>'trb_release','post_status'=>'publish'],2=>(object)['ID'=>2,'post_type'=>'trb_release','post_status'=>'trash']];
$meta=[1=>['_trb_release_tracks'=>[['title'=>'Track']], '_trb_release_files'=>[['kind'=>'audio','track'=>0,'sha256'=>'new']], '_trb_release_technical_analysis'=>['status'=>'passed','tracks'=>[['status'=>'passed','sha256'=>'new']]]]];
verify(trb_release_technical_is_current(1),'Valid current technical result rejected');
verify(!trb_release_current_audio_hash(1,0,'old')&&trb_release_current_audio_hash(1,0,'new'),'Stale WAV treated as current');
$meta[1]['_trb_release_technical_analysis']['tracks'][0]['sha256']='old';
verify(!trb_release_technical_is_current(1),'Old technical result accepted');
$meta[1]['_trb_release_technical_analysis']['tracks'][0]['sha256']='new';
$meta[1]['_trb_release_tracks'][]=['title'=>'Missing'];
verify(!trb_release_technical_is_current(1),'Missing album track accepted');
array_pop($meta[1]['_trb_release_tracks']);
$meta[1]['_trb_release_files'][]=$meta[1]['_trb_release_files'][0];
verify(!trb_release_technical_is_current(1),'Duplicate audio slot accepted');
array_pop($meta[1]['_trb_release_files']);
$meta[1]['_trb_release_technical_analysis']['status']='failed';
verify(!trb_release_technical_is_current(1),'Failed technical result accepted');
verify(trb_release_is_inactive(2)&&trb_release_is_inactive(999),'Trash/missing release remains active');
echo "PASS current audio hash, technical failure, missing/duplicate tracks, inactive practices\n";
$wpdb=new class {
 public $row;public $updates=[];
 function prepare($s,...$args){return $s;}
 function get_results(...$args){return [];}
 function get_row(...$args){return $this->row;}
 function update($table,$data,$where){$this->updates[]=$data;}
};
load_function('trb-release-analysis.php','trb_analysis_decide_release','function trb_analysis_benchmark_count');
trb_analysis_decide_release(1);
verify($meta[1]['_trb_release_pipeline_status']==='technical_error','Copyright bypassed failed technical gate');
$meta[1]['_trb_release_technical_analysis']['status']='passed';
$meta[1]['_trb_release_technical_analysis']['tracks'][0]['sha256']='old';
trb_analysis_decide_release(1);
verify($meta[1]['_trb_release_pipeline_status']==='archived_pending_analysis','Stale technical result bypassed gate');
$meta[1]['_trb_release_files']=[];trb_analysis_decide_release(1);
verify($meta[1]['_trb_release_pipeline_status']==='upload_incomplete','Empty inventory approved');
foreach(['trb_resource_poll_acr_job','trb_resource_poll_dual_acr_job'] as $name){
 load_function('trb-resource-monitor.php',$name,"add_action( '".$name."'");
 $wpdb->row=(object)['id'=>9,'release_id'=>1,'track_index'=>0,'file_hash'=>'old','status'=>'processing','provider_reference'=>'remote-id'];
 $before=$meta[1];$name(9);
 verify(end($wpdb->updates)['last_error']==='superseded_audio'&&$before===$meta[1],'Obsolete poll changed practice');
 $lock=trb_release_process_lock('release:1');$scheduled=[];$name(9);trb_release_process_unlock($lock);
 verify(count($scheduled)===1&&$scheduled[0][0]===$name,'Busy poll lost instead of rescheduling');
}
load_function('trb-release-spreadsheet-bridge.php','trb_release_bridge_dispatch',"add_action( 'trb_release_bridge_dispatch'");
$scheduled=[];trb_release_bridge_dispatch(2);verify(!$scheduled,'Cancelled contract restarted');
$meta[1]['_trb_release_intake_phase']='complete';$meta[1]['_trb_contract_state']='signed';
trb_release_bridge_dispatch(1);verify($meta[1]['_trb_contract_state']==='signed','Repeated dispatch changed signed contract');
load_function('trb-release-spreadsheet-bridge.php','trb_release_bridge_apply_callback','function trb_release_bridge_public_callback');
verify(trb_release_bridge_apply_callback(['release_id'=>2,'status'=>'completed','dossier_id'=>'x'])->code==='release_cancelled','Callback revived cancelled practice');
verify(trb_release_bridge_apply_callback(['release_id'=>1,'status'=>'unknown','dossier_id'=>'wrong'])->code==='status_invalid','Invalid callback accepted');
verify(empty($meta[1]['_trb_otp_dossier_id']),'Invalid callback claimed a dossier');
echo "PASS real decision, obsolete/busy polling, cancelled dispatch, signed retry and callback integrity\n";
