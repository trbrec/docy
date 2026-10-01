<?php
/** Read-only inspection of the one authorized owner self-test. Never dispatches mail. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$stage = 'bootstrap';
set_exception_handler(static function () use (&$stage) { echo json_encode(['audit' => 'unconfirmed', 'stage' => $stage]); exit(1); });
$crm = '/home/customer/www/crm.trbrec.com/public_html';
require_once $crm . '/app/Core.php';
\TrbCrm\Env::load($crm . '/.env');
$db = \TrbCrm\Database::connection();
$stage = 'batch-read';
$q = $db->prepare('SELECT b.status batch_status, bi.status item_status, bi.error_message, bi.recipient, bi.contract_id FROM outbound_batches b JOIN outbound_batch_items bi ON bi.batch_id=b.id WHERE b.public_id=? AND bi.submission_id=?');
$q->execute(['26C15375BED9E554E7462BA5F4', 519]);
$item = $q->fetch();
if (!$item || strcasecmp((string)$item['recipient'], 'spotify4@trbrec.com') !== 0) { echo json_encode(['audit'=>'scope-unconfirmed']); exit(2); }
$enum = static fn($value) => $value === '' ? 'empty' : (preg_match('/^[a-z_]{1,40}$/D', (string)$value) ? $value : 'other');
$error = (string)($item['error_message'] ?? '');
$error = preg_replace(['~https?://\S+~i', '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '/[A-Za-z0-9_\-]{32,}/'], '[redacted]', $error);
$report = ['audit'=>'confirmed', 'batch_status'=>$enum($item['batch_status']), 'item_status'=>$enum($item['item_status']), 'error'=>mb_substr($error, 0, 600)];
$q=$db->query("SELECT COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='outbound_batch_items' AND COLUMN_NAME='status'");
$report['status_column']=$q->fetch();
$stage = 'queue-details-read';
$q=$db->prepare('SELECT b.* FROM outbound_batches b JOIN outbound_batch_items bi ON bi.batch_id=b.id WHERE b.public_id=? AND bi.submission_id=?');
$q->execute(['26C15375BED9E554E7462BA5F4',519]);$details=$q->fetch();
foreach(['created_at','started_at','completed_at','confirmed_at','next_run_at','scheduled_at','updated_at'] as $field)if(array_key_exists($field,$details)&&($details[$field]===null||preg_match('/^[0-9T:\-+. Z]{1,40}$/D',(string)$details[$field])))$report['batch_'.$field]=$details[$field];
$source=(string)file_get_contents($crm.'/app/SubmissionRepository.php');preg_match_all('/(?:public|private|protected)\s+(?:static\s+)?function\s+([A-Za-z0-9_]+)\s*\(/',$source,$methods);$report['queue_methods']=array_values(array_filter($methods[1],static fn($name)=>preg_match('/batch|outbound|queue|mail/i',$name)));
$stage = 'contract-read';
$q = $db->prepare('SELECT id,status,sent_at,metadata,document_sha256 FROM contracts WHERE submission_id=? ORDER BY id DESC LIMIT 1');
$q->execute([519]); $contract = $q->fetch();
$report['contract_status'] = $enum($contract['status'] ?? 'missing');
$report['contract_sent'] = !empty($contract['sent_at']);
$meta = json_decode((string)($contract['metadata'] ?? '{}'), true) ?: [];
$report['reviewed_pdf_matches'] = !empty($meta['reviewed_sha256']) && hash_equals((string)$contract['document_sha256'], (string)$meta['reviewed_sha256']);
$report['gmail_receipt_recorded'] = !empty($meta['onboarding']['gmail_message_id']);
$stage = 'practice-read';
$q = $db->prepare('SELECT id,state FROM onboarding_practices WHERE contract_id=?');
$q->execute([(int)$contract['id']]); $practice=$q->fetch();
$report['practice_state'] = $enum($practice['state'] ?? 'missing');
if ($practice) {
    $q=$db->prepare('SELECT kind,status FROM onboarding_events WHERE practice_id=? AND kind=?');
    $q->execute([$practice['id'], 'proposal_email']);
    $report['proposal_email_events'] = array_map(static fn($event) => ['kind'=>$enum($event['kind']), 'status'=>$enum($event['status'])], $q->fetchAll());
}
$stage = 'preview-binding-read';
require_once $crm.'/app/OnboardingContractWorkflow.php';
$q=$db->prepare('SELECT payload_json FROM outbound_batch_payloads bp JOIN outbound_batch_items bi ON bi.id=bp.batch_item_id JOIN outbound_batches b ON b.id=bi.batch_id WHERE b.public_id=? AND bi.submission_id=?');
$q->execute(['26C15375BED9E554E7462BA5F4',519]);$payload=json_decode((string)$q->fetchColumn(),true)?:[];
$subject=(string)($meta['subject']??'');$body=(string)($meta['body']??'');
$q=$db->prepare('SELECT document_url,template_key FROM contracts WHERE id=?');$q->execute([(int)$contract['id']]);$binding=$q->fetch();
$personal=(string)$binding['document_url'];$pending=\TrbCrm\OnboardingContractWorkflow::PREVIEW_URL;
$hash=static fn($url,$emailBody)=>\TrbCrm\OnboardingContractWorkflow::hash(519,'trb_ccde','spotify4@trbrec.com',$url,$subject,$emailBody);
$report['metadata_hash_matches_personal']=hash_equals((string)($meta['preview_hash']??''),$hash($personal,$body));
$report['metadata_hash_matches_pending']=hash_equals((string)($meta['preview_hash']??''),$hash($pending,str_replace($personal,$pending,$body)));
$report['payload_keys']=array_keys($payload);
$report['approved_email_matches']=hash_equals((string)($payload['expected_email_hash']??''),hash('sha256',$subject."\n".$body));
$report['payload_token_matches_personal']=hash_equals((string)($payload['preview_token']??''),$hash($personal,$body));
$report['payload_token_matches_metadata']=hash_equals((string)($payload['preview_token']??''),(string)($meta['preview_hash']??''));

$stage = 'delivery-history';
$q=$db->prepare("SELECT COUNT(*) FROM mail_messages mm JOIN mail_threads mt ON mt.id=mm.thread_id WHERE mt.contract_id=? AND mm.direction='outbound' AND mm.delivery_status='sent'");$q->execute([(int)$contract['id']]);$report['recorded_sent_messages']=(int)$q->fetchColumn();
$q=$db->prepare('SELECT sequence_no,status,due_at FROM followups WHERE contract_id=? ORDER BY sequence_no');$q->execute([(int)$contract['id']]);$report['followups']=$q->fetchAll();
$report['sent_at']=$contract['sent_at'];
$q=$db->prepare("SELECT status FROM outbound_batch_items bi JOIN outbound_batches b ON b.id=bi.batch_id WHERE b.public_id=? AND bi.submission_id=?");$q->execute(['4DF00BA63E205FB204A1EE570C',519]);$report['older_batch_item_status']=$enum($q->fetchColumn());
$stage = 'source-read';
$source=(string)file_get_contents($crm.'/app/SubmissionRepository.php');
$report['workflow_v3_marker'] = str_contains($source, '// TRB candidate onboarding workflow v3');
$report['obsolete_batch_wrapper_present'] = str_contains($source, 'OnboardingContractWorkflow::batch(');
$report['workflow_batch_method_present'] = str_contains((string)file_get_contents($crm.'/app/OnboardingContractWorkflow.php'), 'function batch(');
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
