<?php
/** Guarded owner-requested recovery test of existing contract 27. Never approves, pays, signs, or sends. */
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();echo json_encode(['success'=>false,'verification_unconfirmed'=>true])."\n";exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';
$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';
if(trim((string)file_get_contents($theme.'/.trb-deployed-sha'))!=='f87f532df994867e3a259581cc7d20038fc0cbfc')exit(2);
require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require_once $root.'/app/OnboardingRuntime.php';
$db=\TrbCrm\Database::connection();$ledger=new \TrbCrm\OnboardingLedger($db);$p=$ledger->forContract(27);
if(!$p||$p['email']!=='a.tognassi@gmail.com'||$p['snapshot']['contract_number']!=='TRB-QA-NONVALIDO-DDB600-20261003'||$p['snapshot']['template_key']!=='ddb_ccad_600')throw new RuntimeException();
$id=$p['id'];
$fingerprint=static fn()=>hash('sha256',json_encode([$ledger->details($id),$ledger->files($id)],JSON_THROW_ON_ERROR));
$before=$fingerprint();$details=$ledger->details($id);$files=$ledger->files($id);
$q=$db->prepare('SELECT checked_at,document_fingerprint FROM onboarding_identity_checks WHERE practice_id=?');$q->execute([$id]);$check=$q->fetch(\PDO::FETCH_ASSOC);$checked=$check['checked_at']??null;
$q=$db->prepare('SELECT COUNT(*) FROM onboarding_payments WHERE practice_id=?');$q->execute([$id]);$paymentsBefore=(int)$q->fetchColumn();
$signatureBefore=$ledger->signature($id);$ordersBefore=$ledger->orders($id);$proposalBefore=$ledger->artifact($id,'proposal');$snapshotBefore=$p['snapshot'];$decision=$ledger->identityResult($id);
$q=$db->prepare('SELECT COUNT(*) FROM onboarding_identity_jobs WHERE practice_id=?');$q->execute([$id]);$jobExists=(int)$q->fetchColumn()>0;
$eligible=$p['state']==='identity_review'&&!$p['cancelled_at']&&!$p['signed_at']&&!$p['selected_plan']&&!$p['first_payment_date']&&!$p['owner_approved_at']&&!$p['portal_activated_at']&&$paymentsBefore===0&&$signatureBefore===null&&!$ordersBefore
&&$checked==='2026-10-03T09:24:22+00:00'&&($decision['status']??'')==='review'&&($decision['reason']??'')==='document_unreadable'
&&!$jobExists&&hash_equals((string)($check['document_fingerprint']??''),hash('sha256',json_encode($files,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)))&&!empty($details['privacy_acknowledged_at'])&&isset($files['identity_front'],$files['tax_front']);
$queued=false;$queueMilliseconds=null;
if($eligible){$start=microtime(true);$queuedResult=$ledger->queueIdentity($id);$queueMilliseconds=(int)round((microtime(true)-$start)*1000);$queued=($queuedResult['status']??'')==='queued';}
$verification=$ledger->identityVerification($id);
$workerUsed=false;
if($queued){$workerUsed=true;(new \TrbCrm\OnboardingRuntime($db))->rpc(['action'=>'identity_worker','practice_id'=>$id]);$verification=$ledger->identityVerification($id);}
$after=$ledger->practice($id);$q=$db->prepare('SELECT COUNT(*) FROM onboarding_payments WHERE practice_id=?');$q->execute([$id]);$paymentsAfter=(int)$q->fetchColumn();
$unchanged=hash_equals($before,$fingerprint());$proposalUnchanged=$proposalBefore===$ledger->artifact($id,'proposal')&&$snapshotBefore===$after['snapshot'];$ordersUnchanged=$ordersBefore===$ledger->orders($id);$signatureUnchanged=$signatureBefore===$ledger->signature($id);
$rejectedBoth=($verification['status']??'')==='rejected'&&($verification['reason']??'')==='documents_required'&&($verification['replace_slots']??[])===['identity_front','tax_front'];
$out=['success'=>$unchanged&&$proposalUnchanged&&$ordersUnchanged&&$paymentsBefore===$paymentsAfter&&$signatureUnchanged&&(!$workerUsed||$rejectedBoth),
'contract_id'=>27,'version_current'=>true,'state'=>$after['state'],'details_and_documents_unchanged'=>$unchanged,'proposal_unchanged'=>$proposalUnchanged,'orders_unchanged'=>$ordersUnchanged,
'details_saved'=>!empty($details['privacy_acknowledged_at']),'both_document_fronts_saved'=>isset($files['identity_front'],$files['tax_front']),
'legacy_case_requeued'=>$queued,'queue_ack_ms'=>$queueMilliseconds,'actual_reader_used'=>$workerUsed,'verification'=>$verification,
'payments_before'=>$paymentsBefore,'payments_after'=>$paymentsAfter,'signature_unchanged'=>$signatureUnchanged,'signature_created'=>$ledger->signature($id)!==null,
'skipped_to_preserve_user_progress'=>!$eligible];
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";exit($out['success']?0:1);
