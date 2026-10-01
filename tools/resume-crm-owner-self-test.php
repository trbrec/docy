<?php
/** Resume only the already approved owner self-test; preserve its PDF, payload, and batch. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$stage='source-guard';
set_exception_handler(static function()use(&$stage){echo json_encode(['resumed'=>false,'stage'=>$stage]);exit(1);});
$crm='/home/customer/www/crm.trbrec.com/public_html';
$expectedWorkflow='50f33bc1847bee09ecd34fa3f60b11f21f30bb48ed50a560ef03f14356870d71';
if(!hash_equals($expectedWorkflow,hash_file('sha256',$crm.'/app/OnboardingContractWorkflow.php')))throw new RuntimeException('Corrected worker is not installed');
require_once $crm.'/app/Core.php';\TrbCrm\Env::load($crm.'/.env');
require_once $crm.'/app/OnboardingContractWorkflow.php';
$db=\TrbCrm\Database::connection();
$stage='queue-lock';
if((int)$db->query("SELECT GET_LOCK('trb_crm_contract_mail_queue',10)")->fetchColumn()!==1)throw new RuntimeException('Queue busy');
try{
    $column=$db->query("SHOW COLUMNS FROM outbound_batch_items LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if(!str_contains((string)$column['Type'],"'sending'"))throw new RuntimeException('Queue state migration missing');
    $stage='confirmed-owner-test';
    $db->beginTransaction();
    $q=$db->prepare("SELECT bi.*,b.status batch_status,b.confirmed_at,u.id owner_id,u.role owner_role,u.email owner_email,u.is_active owner_active,bp.payload_json,bp.attempt_at FROM outbound_batch_items bi JOIN outbound_batches b ON b.id=bi.batch_id JOIN users u ON u.id=b.created_by JOIN outbound_batch_payloads bp ON bp.batch_item_id=bi.id WHERE b.public_id=? AND b.action_type='contract_send' AND bi.submission_id=? FOR UPDATE");
    $q->execute(['4DF00BA63E205FB204A1EE570C',519]);$item=$q->fetch(PDO::FETCH_ASSOC);
    if(!$item||$item['recipient']!=='spotify4@trbrec.com'||$item['owner_role']!=='admin'||strcasecmp($item['owner_email'],'andrea.tognassi@trbrec.com')!==0||!(int)$item['owner_active']||empty($item['confirmed_at']))throw new RuntimeException('Approval scope changed');
    if(in_array($item['status'],['pending','sending','sent'],true)){$db->rollBack();echo json_encode(['resumed'=>false,'already_resumed_or_sent'=>true]);exit;}
    if($item['status']!==''||$item['batch_status']!=='processing'||!empty($item['sent_at'])||empty($item['attempt_at'])||strtotime($item['attempt_at'].' UTC')>time()-300)throw new RuntimeException('Queue attempt is not eligible for repair');
    $stage='reviewed-contract-query';
    $q=$db->prepare('SELECT c.*,s.status submission_status,co.email operative_email FROM contracts c JOIN submissions s ON s.id=c.submission_id JOIN contacts co ON co.id=s.contact_id WHERE c.submission_id=? ORDER BY c.id DESC LIMIT 1 FOR UPDATE');
    $q->execute([519]);$contract=$q->fetch(PDO::FETCH_ASSOC);
    $meta=json_decode((string)($contract['metadata']??''),true)?:[];
    $payload=json_decode((string)$item['payload_json'],true)?:[];
    $sha='2dd4f558b33bffa96ca930f350f5c12a28c4c1476169cfc90289beba40aea5ee';
    $stage='proposal-state';
    if(!$contract||$contract['operative_email']!=='spotify4@trbrec.com'||$contract['template_key']!=='trb_ccde'||!empty($contract['sent_at'])||$contract['status']!=='generated'||!empty($meta['onboarding']['gmail_message_id']))throw new RuntimeException('Proposal state changed');
    foreach(['document-pdf'=>(string)$contract['document_sha256'],'metadata-pdf'=>(string)($meta['reviewed_sha256']??''),'payload-pdf'=>(string)($payload['reviewed_sha256']??'')] as $stage=>$actual)if(!hash_equals($sha,$actual))throw new RuntimeException('Reviewed PDF changed');
    $stage='approved-payload';
    if(($payload['expected_recipient']??'')!=='spotify4@trbrec.com'||($payload['template_key']??'')!=='trb_ccde'||(int)($payload['submission_id']??0)!==519)throw new RuntimeException('Approved payload changed');
    $stage='approved-email-body';
    if(!hash_equals((string)($payload['expected_email_hash']??''),hash('sha256',(string)$meta['subject']."\n".(string)$meta['body'])))throw new RuntimeException('Approved email changed');
    $stage='preview-binding';
    if(!hash_equals((string)($meta['preview_hash']??''),\TrbCrm\OnboardingContractWorkflow::hash(519,'trb_ccde','spotify4@trbrec.com',(string)$contract['document_url'],(string)$meta['subject'],(string)$meta['body'])))throw new RuntimeException('Preview binding changed');
    $stage='no-previous-delivery';
    $q=$db->prepare('SELECT * FROM onboarding_practices WHERE contract_id=? FOR UPDATE');$q->execute([(int)$contract['id']]);$p=$q->fetch(PDO::FETCH_ASSOC);
    if(!$p||$p['email']!=='spotify4@trbrec.com'||$p['state']!=='invited'||!empty($p['cancelled_at'])||strtotime($p['expires_at'])<=time()||!empty($p['owner_approved_at'])||!empty($p['signed_at'])||!empty($p['first_payment_date']))throw new RuntimeException('Practice changed');
    $q=$db->prepare("SELECT COUNT(*) FROM onboarding_events WHERE practice_id=? AND kind='proposal_email'");$q->execute([$p['id']]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Previous delivery needs reconciliation');
    $q=$db->prepare('SELECT COUNT(*) FROM candidate_mail_receipts WHERE message_id=?');$q->execute(['<trbproposal.'.$p['id'].'@crm.trbrec.com>']);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Mail receipt already exists');
    $q=$db->prepare("SELECT COUNT(*) FROM outbound_batch_items WHERE submission_id=? AND id<>? AND status IN ('pending','sending','sent')");$q->execute([519,(int)$item['id']]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Another delivery exists');
    $stage='resume-approved-item';
    $q=$db->prepare("UPDATE outbound_batch_items SET status='pending' WHERE id=? AND status=''");$q->execute([(int)$item['id']]);if($q->rowCount()!==1)throw new RuntimeException('Item changed');
    $q=$db->prepare('UPDATE outbound_batch_payloads SET attempt_at=NULL WHERE batch_item_id=?');$q->execute([(int)$item['id']]);
    $db->prepare('INSERT INTO audit_log(user_id,entity_type,entity_id,action,before_json,after_json,ip_hash) VALUES(?,?,?,?,?,?,?)')->execute([(int)$item['owner_id'],'outbound_batch_item',(string)$item['id'],'owner_self_test_queue_resumed',json_encode(['status'=>'']),json_encode(['status'=>'pending','reviewed_sha256'=>$sha]),str_repeat('0',64)]);
    $db->commit();echo json_encode(['resumed'=>true,'same_batch'=>true,'same_reviewed_pdf'=>true,'manual_mail_dispatch'=>false]),"\n";
}finally{if($db->inTransaction())$db->rollBack();$db->query("SELECT RELEASE_LOCK('trb_crm_contract_mail_queue')");}
