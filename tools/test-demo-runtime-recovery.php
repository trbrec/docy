<?php
define('MINUTE_IN_SECONDS',60);define('HOUR_IN_SECONDS',3600);
function absint($v){return abs((int)$v);}
function check_demo($ok,$message){if(!$ok)throw new RuntimeException($message);}
$portal=file_get_contents(dirname(__DIR__).'/inc/trb-artist-portal.php');
$a=strpos($portal,'function trb_portal_demo_delivery_window()');$b=strpos($portal,'function trb_portal_store_demo_file',$a);eval(substr($portal,$a,$b-$a));
$source=file_get_contents(dirname(__DIR__).'/inc/trb-demo-automation.php');
$a=strpos($source,'function trb_demo_defer_review_if_needed(');$b=strpos($source,'function trb_demo_send_review(',$a);eval(substr($source,$a,$b-$a));
$meta=[];$events=[];
function get_post_meta($id,$key,$single=true){global $meta;return $meta[$key]??'';}
function update_post_meta($id,$key,$value){global $meta;$meta[$key]=$value;}
function delete_post_meta($id,$key){global $meta;unset($meta[$key]);}
function wp_next_scheduled(...$args){return false;}
function wp_schedule_single_event($at,$hook,$args){global $events;$events[]=[$at,$hook,$args];return true;}
function trb_demo_is_test_payload($payload){return !empty($payload['test']);}
function demo_ts($date){return (new DateTimeImmutable($date,trb_portal_demo_delivery_timezone()))->getTimestamp();}
foreach([
 ['2026-09-06 12:00','2026-09-07 08:30',true],
 ['2026-09-07 07:00','2026-09-07 08:30',true],
 ['2026-09-07 18:30','2026-09-08 08:30',true],
 ['2026-09-07 10:00',null,false],
] as [$now,$expected,$deferred]){
 $events=[];$meta=[];
 check_demo(trb_demo_defer_review_if_needed(7,[],demo_ts($now))===$deferred,'Execution-time delivery guard failed');
 check_demo($deferred?($events[0][0]===demo_ts($expected)):count($events)===0,'Wrong rescheduled delivery');
}
$meta=['_trb_demo_earliest_delivery'=>demo_ts('2026-09-07 11:30')];$events=[];
check_demo(trb_demo_defer_review_if_needed(7,[],demo_ts('2026-09-07 09:30'))&&$events[0][0]===demo_ts('2026-09-07 11:30'),'Minimum working delay bypassed');
class WP_Error {function __construct(public $message){}function get_error_message(){return $this->message;}}
function is_wp_error($v){return $v instanceof WP_Error;}
$remoteFails=true;$paidCalls=0;
function trb_demo_upload_to_pcloud($payload){global $remoteFails;return $remoteFails?new WP_Error('Temporary archive failure'):['folder'=>'/Demo/test'];}
function trb_demo_openai_review($payload){global $paidCalls;$paidCalls++;return ['review'=>'Stored successful evaluation','usage'=>['estimated_cost_usd'=>.01]];}
function trb_demo_sheet_row(...$args){return true;}
function wp_mail(...$args){throw new RuntimeException('Test must not send real notifications');}
$a=strpos($source,'function trb_demo_process_request(');$b=strpos($source,"\nadd_action(",$a);eval(substr($source,$a,$b-$a));
$meta=['_trb_demo_payload'=>['status'=>'queued','title'=>'Test']];$events=[];
trb_demo_process_request(7);
check_demo($meta['_trb_demo_payload']['status']==='retry'&&$meta['_trb_demo_review']==='Stored successful evaluation','Paid review lost on archive failure');
$remoteFails=false;trb_demo_process_request(7);
check_demo($paidCalls===1&&$meta['_trb_demo_payload']['status']==='ready','Retry repeated paid analysis');
check_demo(!isset($meta['_trb_demo_last_error']),'Recovered request retained stale error');
echo "Demo actual-delivery guards and paid-result recovery passed.\n";
