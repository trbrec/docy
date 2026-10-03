<?php
/** Exact owner QA case: correct proven expiry typo, then run ordinary document verification. No finance, dossier, account or sends. */
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();echo json_encode(['success'=>false,'recovery_unconfirmed'=>true])."\n";exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';
if(trim((string)file_get_contents($theme.'/.trb-deployed-sha'))!=='7de4e7c10e9649d59a43ea98a2734c75dc717664')exit(2);
require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require_once $root.'/app/OnboardingRuntime.php';
$db=\TrbCrm\Database::connection();$l=new \TrbCrm\OnboardingLedger($db);$p=$l->forContract(27);
if(!$p||$p['email']!=='a.tognassi@gmail.com'||$p['snapshot']['contract_number']!=='TRB-QA-NONVALIDO-DDB600-20261003'||$p['snapshot']['template_key']!=='ddb_ccad_600')throw new RuntimeException();
$id=$p['id'];$files=$l->files($id);$details=$l->details($id);$proposal=$l->artifact($id,'proposal');$orders=$l->orders($id);$signature=$l->signature($id);$snapshot=$p['snapshot'];
$q=$db->prepare('SELECT checked_at,document_fingerprint FROM onboarding_identity_checks WHERE practice_id=?');$q->execute([$id]);$check=$q->fetch(\PDO::FETCH_ASSOC);$decision=$l->identityResult($id);
$q=$db->prepare('SELECT COUNT(*) FROM onboarding_payments WHERE practice_id=?');$q->execute([$id]);$paymentsBefore=(int)$q->fetchColumn();
$eligible=$p['state']==='identity_review'&&!$p['cancelled_at']&&!$p['signed_at']&&!$p['selected_plan']&&!$p['owner_approved_at']&&!$p['portal_activated_at']&&!$p['first_payment_date']&&!$orders&&$signature===null&&$paymentsBefore===0
&&$check&&$check['checked_at']==='2026-10-03T10:41:31+00:00'&&($decision['reason']??'')==='tax_document_required'&&($details['profile']['document_expiry']??'')==='2026-12-19'
&&hash_equals($check['document_fingerprint'],hash('sha256',json_encode($files,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));
$corrected=false;$expectedDetails=$details;$elapsed=null;$ackMs=null;
if($eligible){
    $input=['privacy_acknowledged'=>true,'billing'=>$details['billing'],'profile'=>$details['profile'],'tax_code'=>$details['tax_code'],'invoice'=>$details['invoice']??[]];
    $input['profile']['document_expiry']='2032-12-19';$expectedDetails['profile']['document_expiry']='2032-12-19';
    $unused=static function(){throw new RuntimeException('Unexpected adapter call');};$service=new \TrbCrm\OnboardingService($l,new stdClass(),new stdClass(),$unused,$unused);
    $service->details($p,$input);$corrected=$l->details($id)===$expectedDetails;
    if(!$corrected)throw new RuntimeException();$start=microtime(true);$service->identity($l->practice($id));$ackMs=(int)round((microtime(true)-$start)*1000);
    $start=microtime(true);(new \TrbCrm\OnboardingRuntime($db))->rpc(['action'=>'identity_worker','practice_id'=>$id]);$elapsed=(int)round((microtime(true)-$start)*1000);
}
$after=$l->practice($id);$verification=$l->identityVerification($id);$q=$db->prepare('SELECT COUNT(*) FROM onboarding_payments WHERE practice_id=?');$q->execute([$id]);$paymentsAfter=(int)$q->fetchColumn();
$unchanged=$l->files($id)===$files&&$l->artifact($id,'proposal')===$proposal&&$after['snapshot']===$snapshot&&$l->orders($id)===$orders&&$l->signature($id)===$signature&&$paymentsBefore===$paymentsAfter;
$detailsExpected=$l->details($id)===$expectedDetails;$matched=($verification['status']??'')==='complete'&&($l->identityResult($id)['status']??'')==='matched';
$out=['success'=>$unchanged&&$detailsExpected&&(!$eligible||$matched),'contract_id'=>27,'state'=>$after['state'],'expiry_typo_corrected'=>$corrected,'document_expiry'=>$l->details($id)['profile']['document_expiry']??null,'other_details_unchanged'=>$detailsExpected,'files_proposal_orders_payments_signature_unchanged'=>$unchanged,'both_original_fronts_preserved'=>isset($files['identity_front'],$files['tax_front']),'queue_ack_ms'=>$ackMs,'worker_elapsed_ms'=>$elapsed,'verification'=>$verification,'payments'=>$paymentsAfter,'signature_created'=>$l->signature($id)!==null,'three_contractual_formulas'=>count($after['snapshot']['plans'])===3,'skipped_to_preserve_user_progress'=>!$eligible];
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";exit($out['success']?0:1);
