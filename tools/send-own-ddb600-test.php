<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();$stage='bootstrap';

$root='/home/customer/www/crm.trbrec.com/public_html';require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require_once $root.'/app/SubmissionRepository.php';require_once $root.'/app/OnboardingRuntime.php';set_exception_handler(static function()use(&$stage){while(ob_get_level())ob_end_clean();echo json_encode(['success'=>false,'stage'=>$stage,'do_not_repeat_without_receipt_check'=>true])."\n";exit(1);});
$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';if(trim(file_get_contents($theme.'/.trb-deployed-sha'))!=='fd431de6538d462bd7cc8916a6f35e540a5b5138')exit(2);
$db=\TrbCrm\Database::connection();$owner=(int)$db->query("SELECT id FROM users WHERE email='andrea.tognassi@trbrec.com' AND role='admin' AND is_active=1")->fetchColumn();if(!$owner)throw new RuntimeException();
$repo=new \TrbCrm\SubmissionRepository($db);$id=723;$s=$repo->find($id);
if(!$s||$s['email']!=='a.tognassi@gmail.com'||$s['source_tab']!=='QA_ONBOARDING'||$s['contract_number']!=='TRB-QA-NONVALIDO-DDB600-20261003'||(int)$s['contract']['id']!==27)throw new RuntimeException();
$ledger=new \TrbCrm\OnboardingLedger($db);$p=$ledger->forContract(27);$a=$ledger->artifact($p['id'],'proposal');$expected='8ba8ed32c65bd4f745c51f1a2edcc0b8f0655f900fb3d3648e773de513c08c53';if(!hash_equals($expected,$a['sha256']))throw new RuntimeException();
$stage='prior-account-and-new-test-scope';
$prior=$ledger->forContract(26);
if(!$prior||$prior['state']!=='cancelled'||!$ledger->artifact($prior['id'],'signed_pdf')||!$ledger->artifact($prior['id'],'signature_audit')||$p['snapshot']['template_key']!=='ddb_ccad_600'||$p['snapshot']['nominal_cents']!==60000||count($p['snapshot']['plans'])!==3)throw new RuntimeException();
$code='$_SERVER["HTTP_HOST"]="artist.trbrec.com";$_SERVER["REQUEST_URI"]="/";$_SERVER["HTTPS"]="on";define("WP_USE_THEMES",false);define("DISABLE_WP_CRON",true);ob_start();require "/home/customer/www/artist.trbrec.com/public_html/wp-load.php";$absent=!get_user_by("email","a.tognassi@gmail.com");while(ob_get_level())ob_end_clean();echo json_encode(["account_absent"=>$absent]);';
$lines=[];exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>/dev/null',$lines,$exit);
if($exit!==0||(json_decode(implode("\n",$lines),true)['account_absent']??false)!==true)throw new RuntimeException();
$stage='confirmed-queue';$worker=null;
if($s['contract']['status']!=='sent'){
$q=$db->prepare("SELECT COUNT(*) FROM onboarding_events WHERE practice_id=? AND kind='proposal_email'");$q->execute([$p['id']]);if((int)$q->fetchColumn()>0)throw new RuntimeException();
if((int)$db->query("SELECT COUNT(*) FROM outbound_batch_items WHERE status IN ('pending','sending')")->fetchColumn()>0)throw new RuntimeException();
$items=[['submission_id'=>$id,'template_key'=>'ddb_ccad_600','reviewed_sha256'=>$expected]];$preview=$repo->previewContractBatch($items);
if(($preview['blocked']??1)!==0||count($preview['items'])!==1||$preview['items'][0]['recipient']!=='a.tognassi@gmail.com')throw new RuntimeException();
$repo->sendContractBatch(['confirm'=>true,'items'=>$items,'batch_token'=>$preview['batch_token']],$owner);
$stage='gmail-delivery';$worker=$repo->processContractBatchQueue(1);
}
$stage='receipt-verification';$s=$repo->find($id);$q=$db->prepare("SELECT e.status,r.gmail_message_id,r.gmail_thread_id FROM onboarding_events e JOIN candidate_mail_receipts r ON r.message_id=JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.message_id')) WHERE e.practice_id=? AND e.kind='proposal_email'");$q->execute([$p['id']]);$receipt=$q->fetch();
$confirmed=$s['contract']['status']==='sent'&&($receipt['status']??'')==='completed'&&!empty($receipt['gmail_message_id'])&&!empty($receipt['gmail_thread_id']);if(!$confirmed||$ledger->automaticSignatureOwner($p['id'])===null)throw new RuntimeException();
$stage='qa-followups';$repo->stopCandidateFollowups($id,$owner);
$out=['success'=>true,'sent'=>true,'recipient_matches'=>true,'verified_pdf_sha256'=>$expected,'gmail_receipt_recorded'=>true,'gmail_thread_recorded'=>true,'test_followups_stopped'=>true,'contract_number'=>$s['contract_number'],'sent_at_utc'=>$s['contract']['sent_at'],'automatic_signature_authorized'=>true,'gmail_message_id'=>$receipt['gmail_message_id'],'subject'=>$s['contract']['metadata']['subject']??null];while(ob_get_level())ob_end_clean();echo json_encode($out)."\n";
