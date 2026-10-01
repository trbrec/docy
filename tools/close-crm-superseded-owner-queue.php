<?php
/** Close only the empty historical attempt superseded by the verified owner delivery. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');$stage='bootstrap';
set_exception_handler(static function()use(&$stage){echo json_encode(['closed'=>false,'stage'=>$stage]);exit(1);});
$crm='/home/customer/www/crm.trbrec.com/public_html';
require_once $crm.'/app/Core.php';\TrbCrm\Env::load($crm.'/.env');$db=\TrbCrm\Database::connection();
$stage='queue-lock';if((int)$db->query("SELECT GET_LOCK('trb_crm_contract_mail_queue',10)")->fetchColumn()!==1)throw new RuntimeException('Queue busy');
try{
    $db->beginTransaction();$stage='confirmed-single-delivery';
    $q=$db->prepare("SELECT b.id,b.status batch_status,b.created_by,bi.status item_status,bi.recipient,bi.contract_id,c.status contract_status,c.sent_at,c.document_sha256,c.metadata FROM outbound_batches b JOIN outbound_batch_items bi ON bi.batch_id=b.id JOIN contracts c ON c.id=bi.contract_id WHERE b.public_id=? AND bi.submission_id=519 FOR UPDATE");
    $q->execute(['26C15375BED9E554E7462BA5F4']);$sent=$q->fetch(PDO::FETCH_ASSOC);$meta=json_decode((string)($sent['metadata']??''),true)?:[];
    if(!$sent||$sent['batch_status']!=='completed'||$sent['item_status']!=='sent'||$sent['contract_status']!=='sent'||empty($sent['sent_at'])||$sent['recipient']!=='spotify4@trbrec.com'||(int)$sent['contract_id']!==1||$sent['document_sha256']!=='2dd4f558b33bffa96ca930f350f5c12a28c4c1476169cfc90289beba40aea5ee'||($meta['onboarding']['gmail_message_id']??'')!=='1a0f9bd712d93cf1')throw new RuntimeException('Delivery not verified');
    $q=$db->prepare('SELECT COUNT(*) FROM candidate_mail_receipts WHERE gmail_message_id=? AND mailbox=?');$q->execute(['1a0f9bd712d93cf1','andrea.tognassi@trbrec.com']);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Receipt not unique');
    $stage='superseded-empty-attempt';
    $q=$db->prepare("SELECT bi.*,b.status batch_status,b.created_by,b.confirmed_at,u.email owner_email,u.role owner_role,u.is_active FROM outbound_batches b JOIN outbound_batch_items bi ON bi.batch_id=b.id JOIN users u ON u.id=b.created_by WHERE b.public_id=? AND bi.submission_id=519 FOR UPDATE");$q->execute(['4DF00BA63E205FB204A1EE570C']);$old=$q->fetch(PDO::FETCH_ASSOC);
    if(!$old||$old['recipient']!=='spotify4@trbrec.com'||$old['owner_email']!=='andrea.tognassi@trbrec.com'||$old['owner_role']!=='admin'||!(int)$old['is_active']||(int)$old['created_by']!==(int)$sent['created_by']||empty($old['confirmed_at'])||!empty($old['sent_at']))throw new RuntimeException('Historical scope changed');
    if($old['status']==='skipped'&&$old['batch_status']==='completed'){$db->rollBack();echo json_encode(['closed'=>true,'already_closed'=>true]);exit;}
    if($old['status']!==''||$old['batch_status']!=='processing')throw new RuntimeException('Historical attempt changed');
    $q=$db->prepare('SELECT COUNT(*) FROM outbound_batch_items WHERE batch_id=?');$q->execute([(int)$old['batch_id']]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Historical batch scope changed');
    $q=$db->prepare("UPDATE outbound_batch_items SET status='skipped',error_message=? WHERE id=? AND status=''");$q->execute(['Tentativo precedente senza invio, sostituito dal lotto confermato 26C15375BED9E554E7462BA5F4 completato.',(int)$old['id']]);if($q->rowCount()!==1)throw new RuntimeException('Historical item changed');
    $q=$db->prepare("UPDATE outbound_batches SET status='completed',completed_at=UTC_TIMESTAMP() WHERE id=? AND status='processing'");$q->execute([(int)$old['batch_id']]);if($q->rowCount()!==1)throw new RuntimeException('Historical batch changed');
    $db->prepare('INSERT INTO audit_log(user_id,entity_type,entity_id,action,before_json,after_json,ip_hash) VALUES(?,?,?,?,?,?,?)')->execute([(int)$old['created_by'],'outbound_batch_item',(string)$old['id'],'owner_superseded_queue_closed',json_encode(['status'=>'','batch_status'=>'processing']),json_encode(['status'=>'skipped','verified_batch'=>'26C15375BED9E554E7462BA5F4','mail_dispatched'=>false]),str_repeat('0',64)]);
    $db->commit();echo json_encode(['closed'=>true,'historical_attempt_preserved'=>true,'mail_dispatched'=>false]),"\n";
}finally{if($db->inTransaction())$db->rollBack();$db->query("SELECT RELEASE_LOCK('trb_crm_contract_mail_queue')");}
