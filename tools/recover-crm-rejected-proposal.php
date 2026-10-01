<?php
/** Recover the one approved owner test after a proven pre-dispatch bridge rejection. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$stage='corrected-source';
set_exception_handler(static function()use(&$stage){echo json_encode(['recovered'=>false,'stage'=>$stage]);exit(1);});
$crm='/home/customer/www/crm.trbrec.com/public_html';
if(!hash_equals('3de27c35fa231a16863bb0e16363eeea69d773db7ffae70c307f90d227250ebe',hash_file('sha256',$crm.'/app/OnboardingContractWorkflow.php')))throw new RuntimeException('Corrected mail identifier is not installed');
require_once $crm.'/app/Core.php';\TrbCrm\Env::load($crm.'/.env');
require_once $crm.'/app/OnboardingContractWorkflow.php';
$db=\TrbCrm\Database::connection();
$stage='queue-lock';
if((int)$db->query("SELECT GET_LOCK('trb_crm_contract_mail_queue',10)")->fetchColumn()!==1)throw new RuntimeException('Queue busy');
try{
    $stage='candidate-lock';
    if((int)$db->query("SELECT GET_LOCK('trb_contract_send_519',10)")->fetchColumn()!==1)throw new RuntimeException('Candidate busy');
    $db->beginTransaction();
    $stage='approved-owner-batch';
    $q=$db->prepare("SELECT bi.*,b.status batch_status,b.confirmed_at,u.id owner_id,u.role owner_role,u.email owner_email,u.is_active owner_active,bp.payload_json,bp.attempt_at FROM outbound_batch_items bi JOIN outbound_batches b ON b.id=bi.batch_id JOIN users u ON u.id=b.created_by JOIN outbound_batch_payloads bp ON bp.batch_item_id=bi.id WHERE b.public_id=? AND b.action_type='contract_send' AND bi.submission_id=? FOR UPDATE");
    $q->execute(['26C15375BED9E554E7462BA5F4',519]);$item=$q->fetch(PDO::FETCH_ASSOC);
    if(!$item||$item['recipient']!=='spotify4@trbrec.com'||$item['owner_role']!=='admin'||strcasecmp($item['owner_email'],'andrea.tognassi@trbrec.com')!==0||!(int)$item['owner_active']||$item['confirmed_at']!=='2026-10-01 22:56:00')throw new RuntimeException('Approval scope changed');
    if(in_array($item['status'],['pending','sending','sent'],true)){$db->rollBack();echo json_encode(['recovered'=>false,'already_recovered_or_sent'=>true]);exit;}
    if($item['status']!=='failed'||$item['batch_status']!=='failed'||!empty($item['sent_at'])||$item['error_message']!=='Esito incerto: verificare posta inviata. Gmail: Identificativo email non valido')throw new RuntimeException('Not the verified pre-dispatch failure');
    $q=$db->prepare('SELECT COUNT(*) FROM outbound_batch_items WHERE batch_id=?');$q->execute([(int)$item['batch_id']]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Batch scope changed');
    $stage='same-reviewed-contract';
    $q=$db->prepare('SELECT c.*,co.email operative_email FROM contracts c JOIN submissions s ON s.id=c.submission_id JOIN contacts co ON co.id=s.contact_id WHERE c.submission_id=? ORDER BY c.id DESC LIMIT 1 FOR UPDATE');$q->execute([519]);$contract=$q->fetch(PDO::FETCH_ASSOC);
    $meta=json_decode((string)($contract['metadata']??''),true)?:[];$payload=json_decode((string)$item['payload_json'],true)?:[];
    $sha='2dd4f558b33bffa96ca930f350f5c12a28c4c1476169cfc90289beba40aea5ee';
    if(!$contract||(int)$contract['id']!==1||(int)$item['contract_id']!==1||$contract['operative_email']!=='spotify4@trbrec.com'||$contract['template_key']!=='trb_ccde'||!empty($contract['sent_at'])||$contract['status']!=='generated'||!empty($meta['onboarding']['gmail_message_id']))throw new RuntimeException('Proposal changed');
    foreach([(string)$contract['document_sha256'],(string)($meta['reviewed_sha256']??''),(string)($payload['reviewed_sha256']??'')] as $actual)if(!hash_equals($sha,$actual))throw new RuntimeException('Reviewed PDF changed');
    if(($payload['expected_recipient']??'')!=='spotify4@trbrec.com'||($payload['template_key']??'')!=='trb_ccde'||(int)($payload['submission_id']??0)!==519||!hash_equals((string)($payload['expected_email_hash']??''),hash('sha256',(string)$meta['subject']."\n".(string)$meta['body'])))throw new RuntimeException('Approved email changed');
    if(!preg_match('/^[a-f0-9]{64}$/D',(string)($meta['preview_hash']??'')))throw new RuntimeException('Native preview confirmation missing');
    $stage='unchanged-practice';
    $q=$db->prepare('SELECT * FROM onboarding_practices WHERE contract_id=? FOR UPDATE');$q->execute([1]);$p=$q->fetch(PDO::FETCH_ASSOC);
    if(!$p||$p['email']!=='spotify4@trbrec.com'||$p['state']!=='invited'||!empty($p['cancelled_at'])||strtotime($p['expires_at'])<=time()||!empty($p['owner_approved_at'])||!empty($p['signed_at'])||!empty($p['first_payment_date']))throw new RuntimeException('Practice changed');
    $stage='no-delivery-receipt';
    $oldId='<trbproposal.'.$p['id'].'@crm.trbrec.com>';$newId=\TrbCrm\OnboardingContractWorkflow::proposalMessageId($p['id']);
    $q=$db->prepare('SELECT COUNT(*) FROM candidate_mail_receipts WHERE message_id IN (?,?)');$q->execute([$oldId,$newId]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Mail receipt exists');
    $q=$db->prepare("SELECT COUNT(*) FROM outbound_batch_items WHERE submission_id=? AND id<>? AND status IN ('pending','sending','sent')");$q->execute([519,(int)$item['id']]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Another delivery exists');
    $stage='verified-rejected-event';
    $event='onboarding:'.$p['id'].':proposal-email';
    $q=$db->prepare("SELECT * FROM onboarding_events WHERE practice_id=? AND kind='proposal_email' FOR UPDATE");$q->execute([$p['id']]);$events=$q->fetchAll(PDO::FETCH_ASSOC);
    if(count($events)!==1||$events[0]['event_key']!==$event||$events[0]['status']!=='dispatching'||!empty($events[0]['completed_at']))throw new RuntimeException('Previous dispatch changed');
    $oldPayload=json_decode($events[0]['payload'],true)?:[];
    if(($oldPayload['message_id']??'')!==$oldId||!hash_equals($sha,(string)($oldPayload['document_sha256']??''))||!empty($oldPayload['gmail_message_id']))throw new RuntimeException('Dispatch evidence changed');
    // The published bridge rejects this identifier before MIME decoding,
    // idempotency state creation, or Gmail.Users.Messages.send. Preserve evidence.
    $oldPayload['reconciliation']=['reason'=>'bridge_message_id_rejected_before_dispatch','owner_id'=>(int)$item['owner_id'],'checked_at'=>gmdate('c')];
    $stage='preserve-rejection-and-resume';
    $q=$db->prepare("UPDATE onboarding_events SET event_key=?,kind='proposal_email_rejected',status='rejected',payload=?,completed_at=? WHERE event_key=? AND status='dispatching'");
    $q->execute([$event.':rejected-item-'.(int)$item['id'],json_encode($oldPayload,JSON_THROW_ON_ERROR),gmdate('c'),$event]);if($q->rowCount()!==1)throw new RuntimeException('Event changed');
    $q=$db->prepare("UPDATE outbound_batch_items SET status='pending',error_message=NULL WHERE id=? AND status='failed'");$q->execute([(int)$item['id']]);if($q->rowCount()!==1)throw new RuntimeException('Item changed');
    $db->prepare('UPDATE outbound_batch_payloads SET attempt_at=NULL WHERE batch_item_id=?')->execute([(int)$item['id']]);
    $q=$db->prepare("UPDATE outbound_batches SET status='processing',completed_at=NULL WHERE id=? AND status='failed'");$q->execute([(int)$item['batch_id']]);if($q->rowCount()!==1)throw new RuntimeException('Batch changed');
    $db->prepare('INSERT INTO audit_log(user_id,entity_type,entity_id,action,before_json,after_json,ip_hash) VALUES(?,?,?,?,?,?,?)')->execute([(int)$item['owner_id'],'outbound_batch_item',(string)$item['id'],'owner_proposal_preflight_recovered',json_encode(['status'=>'failed','error'=>$item['error_message']]),json_encode(['status'=>'pending','same_batch'=>true,'same_pdf'=>true,'previous_event_preserved'=>true]),str_repeat('0',64)]);
    $db->commit();echo json_encode(['recovered'=>true,'same_batch'=>true,'same_reviewed_pdf'=>true,'previous_rejection_preserved'=>true,'direct_dispatch'=>false]),"\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $db->query("SELECT RELEASE_LOCK('trb_contract_send_519')");
    $db->query("SELECT RELEASE_LOCK('trb_crm_contract_mail_queue')");
}
