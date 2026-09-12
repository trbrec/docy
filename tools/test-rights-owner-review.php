<?php
function add_filter(...$args){}
require __DIR__.'/../inc/trb-rights-owner-review.php';
function check_review($ok,$message){if(!$ok)throw new RuntimeException($message);}
$snapshot=['tracks'=>[
 ['index'=>0,'findings'=>[]],
 ['index'=>1,'findings'=>['Brano 2: corrispondenza riferimento A']],
 ['index'=>2,'findings'=>['Brano 3: corrispondenza riferimento B']]
]];
$r=trb_rights_selected_findings($snapshot,['1']);
check_review($r['indexes']===[1]&&$r['findings']===['Brano 2: corrispondenza riferimento A'],'Unselected track leaked into artist email');
$r=trb_rights_selected_findings($snapshot,[2,1,2]);
check_review($r['indexes']===[1,2]&&count($r['findings'])===2,'Duplicate selections not deduplicated');
foreach([[],[-1],['1.0'],['1<script>'],[0],[3]] as $invalid){
 $failed=false;try{trb_rights_selected_findings($snapshot,$invalid);}catch(RuntimeException $e){$failed=true;}
 check_review($failed,'Invalid or evidence-free selection admitted');
}
echo "PASS per-track isolation, duplicate selection and invalid index rejection\n";

// Exercise the actual download entry point before any report data is read.
function current_user_can($cap){return $GLOBALS['report_admin']??false;}
function is_user_logged_in(){return $GLOBALS['report_logged_in']??true;}
function auth_redirect(){throw new RuntimeException('login_required');}
function wp_die($message,$title='',$args=[]){throw new RuntimeException('http:'.($args['response']??0));}
function absint($v){return abs((int)$v);}
function check_admin_referer($v){throw new RuntimeException('admin_nonce_gate');}
$source=file_get_contents(__DIR__.'/../inc/trb-release-analysis.php');
$start=strpos($source,'function trb_analysis_download_report()');
$end=strpos($source,"add_action( 'admin_post_trb_analysis_download_report'",$start);
eval(substr($source,$start,$end-$start));
foreach([false,true] as $admin){
 $GLOBALS['report_admin']=$admin;
 try{trb_analysis_download_report();throw new RuntimeException('unexpected_download');}
 catch(RuntimeException $e){check_review($e->getMessage()===($admin?'admin_nonce_gate':'http:403'),'Report access authorization failed');}
}
echo "PASS artist report denied; administrator proceeds to nonce validation\n";

$GLOBALS['report_logged_in']=false;
try{trb_analysis_download_report();throw new RuntimeException('anonymous_download');}
catch(RuntimeException $e){check_review($e->getMessage()==='login_required','Anonymous report access allowed');}
$GLOBALS['report_logged_in']=true;
$start=strpos($source,'function trb_analysis_report_url(');
$end=strpos($source,'function trb_analysis_download_report()',$start);
eval(substr($source,$start,$end-$start));
$GLOBALS['report_admin']=false;
check_review(trb_analysis_report_url(123)==='','Artist received internal report URL');
$portal=file_get_contents(__DIR__.'/../inc/trb-artist-portal.php');
check_review(strpos($portal,"if ( current_user_can( 'manage_options' ) && ! empty( \$analysis_report['name'] )")!==false,'Artist report panel must require administrator access');

// A scheduled analysis must never queue an artist copyright email without review.
function get_post_meta(...$args){return $GLOBALS['review_fixture']??[];}
function trb_resource_queue_recipient_email(...$args){throw new RuntimeException('unexpected_artist_email');}
function sanitize_key($v){throw new RuntimeException('authorized_review_reached');}
$start=strpos($source,'function trb_analysis_queue_artist_copyright_email(');
$end=strpos($source,'/** Queue one consolidated',$start);
eval(substr($source,$start,$end-$start));
foreach([
 [[], ''],
 [['token'=>'approved','selected_tracks'=>[0]], ''],
 [['token'=>'approved','selected_tracks'=>[0]], 'wrong'],
 [['token'=>'approved','selected_tracks'=>[]], 'approved']
] as [$review,$token]){
 $GLOBALS['review_fixture']=$review;
 trb_analysis_queue_artist_copyright_email(123,['semaphore'=>'yellow'],$token);
}
$GLOBALS['review_fixture']=['token'=>'approved','selected_tracks'=>[0]];
try{trb_analysis_queue_artist_copyright_email(123,['semaphore'=>'yellow'],'approved');throw new RuntimeException('valid_review_blocked');}
catch(RuntimeException $e){check_review($e->getMessage()==='authorized_review_reached','Valid manual review did not pass authorization');}
echo "PASS anonymous/artist report protection, private UI, and mandatory reviewed email selection\n";
