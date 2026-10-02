<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();$stage='bootstrap';
set_exception_handler(static function()use(&$stage){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Owner QA send unconfirmed at ".$stage.". Do not repeat without checking receipt.\n");exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require_once $root.'/app/SubmissionRepository.php';
$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';if(trim(file_get_contents($theme.'/.trb-deployed-sha'))!=='864a35fe23776d2700fdc3a84cb215ed26e470c3')exit(2);
$db=\TrbCrm\Database::connection();$owner=(int)$db->query("SELECT id FROM users WHERE email='andrea.tognassi@trbrec.com' AND role='admin' AND is_active=1")->fetchColumn();if(!$owner)throw new RuntimeException();
$repo=new \TrbCrm\SubmissionRepository($db);$id=719;$s=$repo->find($id);
if(!$s||$s['email']!=='a.tognassi@gmail.com'||$s['source_tab']!=='QA_ONBOARDING'||$s['contract_number']!=='QA-TRB-NONVALIDO-20261002-2235'||(int)$s['contract']['id']!==26)throw new RuntimeException();
$ledger=new \TrbCrm\OnboardingLedger($db);$p=$ledger->forContract(26);$a=$ledger->artifact($p['id'],'proposal');$expected='7c139d34ccb139008c0f0d1f305dbb6e8498dca55f305ce98e5374abaf42c442';if(!hash_equals($expected,$a['sha256']))throw new RuntimeException();
$stage='confirmed-queue';$worker=null;
if($s['contract']['status']!=='sent'){
$q=$db->prepare("SELECT COUNT(*) FROM onboarding_events WHERE practice_id=? AND kind='proposal_email'");$q->execute([$p['id']]);if((int)$q->fetchColumn()>0)throw new RuntimeException();
if((int)$db->query("SELECT COUNT(*) FROM outbound_batch_items WHERE status IN ('pending','sending')")->fetchColumn()>0)throw new RuntimeException();
$items=[['submission_id'=>$id,'template_key'=>'trb_ccde','reviewed_sha256'=>$expected]];$preview=$repo->previewContractBatch($items);
if(($preview['blocked']??1)!==0||count($preview['items'])!==1||$preview['items'][0]['recipient']!=='a.tognassi@gmail.com')throw new RuntimeException();
$repo->sendContractBatch(['confirm'=>true,'items'=>$items,'batch_token'=>$preview['batch_token']],$owner);
$stage='gmail-delivery';$worker=$repo->processContractBatchQueue(1);
}
$stage='receipt-verification';$s=$repo->find($id);$q=$db->prepare("SELECT e.status,r.gmail_message_id,r.gmail_thread_id FROM onboarding_events e JOIN candidate_mail_receipts r ON r.message_id=JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.message_id')) WHERE e.practice_id=? AND e.kind='proposal_email'");$q->execute([$p['id']]);$receipt=$q->fetch();
$confirmed=$s['contract']['status']==='sent'&&($receipt['status']??'')==='completed'&&!empty($receipt['gmail_message_id'])&&!empty($receipt['gmail_thread_id']);if(!$confirmed)throw new RuntimeException();
$stage='qa-followups';$repo->stopCandidateFollowups($id,$owner);
$out=['success'=>true,'sent'=>true,'recipient_matches'=>true,'verified_pdf_sha256'=>$expected,'gmail_receipt_recorded'=>true,'gmail_thread_recorded'=>true,'test_followups_stopped'=>true,'contract_number'=>$s['contract_number'],'sent_at_utc'=>$s['contract']['sent_at'],'worker'=>$worker];while(ob_get_level())ob_end_clean();echo json_encode($out)."\n";
