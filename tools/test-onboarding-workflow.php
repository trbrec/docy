<?php
namespace TrbCrm;
require_once __DIR__.'/fixtures-onboarding-entry-mail.php';
final class Security{public static function ipHash():string{return str_repeat('0',64);}}
final class Env{public static function get($key,$default=''){return $key==='APP_KEY'?str_repeat('s',64):$default;}}
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingContractWorkflow.php';
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingWorkflowInstaller.php';
function workflow_check($ok,$message){if(!$ok)throw new \RuntimeException($message);}
$pdf='%PDF-synthetic';$id='<trbproposal.'.str_repeat('a',32).'@crm.trbrec.com>';
$mime=OnboardingContractWorkflow::mime('qa@example.invalid','Proposta contratto','Apri la tua adesione',$pdf,'Contratto-QA.pdf',$id,'2026-10-01T10:00:00Z');
workflow_check(str_contains($mime,'multipart/mixed; boundary="'),'MIME mixed header');
workflow_check(str_contains($mime,'Content-Disposition: attachment; filename="Contratto-QA.pdf"'),'PDF attachment');
workflow_check(str_contains($mime,base64_encode($pdf)),'Attachment bytes');
workflow_check($mime===OnboardingContractWorkflow::mime('qa@example.invalid','Proposta contratto','Apri la tua adesione',$pdf,'Contratto-QA.pdf',$id,'2026-10-01T10:00:00Z'),'Stable MIME retries');
try{OnboardingContractWorkflow::mime("qa@example.invalid\r\nBcc: other@example.invalid",'Proposta','Test',$pdf,'QA.pdf',$id,'2026-10-01');throw new \LogicException('Header injection accepted');}catch(\RuntimeException $expected){}
$expected=hash_hmac('sha256',implode("\n",[1,'ddb_ccad_600','qa@example.invalid',OnboardingContractWorkflow::PREVIEW_URL,'Proposta','Corpo']),str_repeat('s',64));
workflow_check(OnboardingContractWorkflow::hash(1,'ddb_ccad_600',' QA@example.invalid ',OnboardingContractWorkflow::PREVIEW_URL,'Proposta','Corpo')===$expected,'Existing CRM preview hash compatible');
$db=new \PDO('sqlite::memory:');$db->exec('CREATE TABLE contract_templates(template_key TEXT,display_name TEXT,email_subject TEXT,email_body TEXT,followup_subject TEXT,followup_body TEXT,document_source_url TEXT,is_active INTEGER)');
$db->exec("INSERT INTO contract_templates VALUES('ddb_ccad_600','DDB 600','Proposta {numero_contratto}','Ciao {nome_contatto}, Carica qui il contratto firmato: {link_contratto}','Promemoria','{link_contratto}','https://docs.google.com/document/d/fixture',1)");
$s=['id'=>1,'first_name'=>'Mario','last_name'=>'Rossi','artist_name'=>'QA','email'=>'qa@example.invalid','contract_number'=>'TRB-QA'];
$p=OnboardingContractWorkflow::preview($db,$s,'ddb_ccad_600');
workflow_check($p['submission_id']===1,'Candidate identity retained for PDF preparation');
workflow_check($p['onboarding']&&$p['plugin_dispatch_available']&&str_contains($p['body'],'Ciao Mario'),'Normal preview personalization');
workflow_check(!str_contains($p['body'],'Carica qui il contratto firmato'),'Old signed PDF upload instruction removed');
workflow_check($db->query('SELECT COUNT(*) FROM sqlite_master WHERE name LIKE \'onboarding_%\'')->fetchColumn()===0,'Preview sends no email and creates no practice');
$ledger=new OnboardingLedger($db);$ledger->install();$snapshot=OnboardingContractCatalog::model('ddb_ccad_600')+array_intersect_key($s,array_flip(['first_name','last_name','artist_name','email','contract_number']));$sha=str_repeat('a',64);$snapshot['artist_folder_id']='qa-private-drive';$snapshot['unsigned_document_sha256']=$sha;
$ledger->create(2,$snapshot,gmdate('c',time()+86400),str_repeat('b',64),str_repeat('c',32),['file_id'=>'qa-pdf','folder_id'=>'qa-private-drive','sha256'=>$sha,'hash'=>'qa-proof']);
$s['contract']=['id'=>2,'contract_number'=>'TRB-QA','template_key'=>'ddb_ccad_600','status'=>'generated','sent_at'=>null];$base=$p;$base['body']='Testo plurale approvato. '.$p['document_url'];
$ready=OnboardingContractWorkflow::preview($db,$s,'ddb_ccad_600',$base);
workflow_check($ready['attachment_ready']&&$ready['document_sha256']===$sha&&str_contains($ready['pdf_preview_url'],'/submissions/1/contracts/2/document'),'Existing PDF review opens the immutable Drive proposal');
workflow_check(str_contains($ready['body'],'Testo plurale approvato.')&&!str_contains($ready['body'],'PENDING'),'Existing email wording retained with personal invitation');
workflow_check($ready===OnboardingContractWorkflow::preview($db,$s,'ddb_ccad_600',$base),'Repeated preview is stable');
$db->exec("INSERT INTO contract_templates SELECT 'ddb_ccad_800',display_name,email_subject,email_body,followup_subject,followup_body,document_source_url,is_active FROM contract_templates WHERE template_key='ddb_ccad_600'");
$alternate=OnboardingContractWorkflow::preview($db,$s,'ddb_ccad_800',$base);workflow_check(!$alternate['attachment_ready'],'Changed model requires a newly prepared PDF');
$changed=$s;$changed['email']='different@example.invalid';$changedPreview=OnboardingContractWorkflow::preview($db,$changed,'ddb_ccad_600',$base);workflow_check(!$changedPreview['attachment_ready'],'Changed recipient requires a newly prepared PDF');
$practice=$ledger->forContract(2);workflow_check(OnboardingContractWorkflow::revisionAllowed($practice,$s['contract'],false),'Unissued unused draft can be revised');
workflow_check(!OnboardingContractWorkflow::revisionAllowed($practice,$s['contract'],true),'Artist interaction prevents implicit revision');
$issued=$s['contract'];$issued['sent_at']='2026-10-01 10:00:00';workflow_check(!OnboardingContractWorkflow::revisionAllowed($practice,$issued,false),'Sent PDF cannot be replaced');
$paid=$practice;$paid['first_payment_date']='2026-10-01';workflow_check(!OnboardingContractWorkflow::revisionAllowed($paid,$s['contract'],false),'Payment prevents revision');
$chosen=$practice;$chosen['selected_plan']=['kind'=>'single'];workflow_check(!OnboardingContractWorkflow::revisionAllowed($chosen,$s['contract'],false),'Chosen plan prevents revision');
workflow_check((int)$db->query('SELECT COUNT(*) FROM onboarding_practices')->fetchColumn()===1,'Preview does not create or revise a practice');
$db->exec("UPDATE onboarding_practices SET cancelled_at='2026-10-01'");
try{OnboardingContractWorkflow::preview($db,$s,'ddb_ccad_600',$base);throw new \LogicException('Cancelled proposal accepted');}catch(\RuntimeException $expected){}
// Exercise the actual transaction, preserving the old private artifact and rejecting used or customized drafts.
$db->exec('CREATE TABLE contracts(id INTEGER PRIMARY KEY,submission_id INTEGER,contract_number TEXT,template_key TEXT,status TEXT,sent_at TEXT,metadata TEXT); CREATE TABLE audit_log(user_id INTEGER,entity_type TEXT,entity_id TEXT,action TEXT,before_json TEXT,after_json TEXT,ip_hash TEXT)');
$db->exec("INSERT INTO contracts VALUES(2,1,'TRB-QA','ddb_ccad_600','generated',NULL,'{}')");
$db->exec('UPDATE onboarding_practices SET cancelled_at=NULL');
$changed=$s;$changed['email']='revised@example.invalid';
$custom=$changed;$custom['contract']['metadata']=['candidate_review'=>['reason'=>'Custom conditions']];
try{OnboardingContractWorkflow::renewDraft($db,$custom,'ddb_ccad_600',1);throw new \LogicException('Customized contract replaced');}catch(\RuntimeException $expected){}
$db->exec("INSERT INTO onboarding_email_challenges VALUES('".$practice['id']."','fixture',9999999999,0,1)");
try{OnboardingContractWorkflow::renewDraft($db,$changed,'ddb_ccad_600',1);throw new \LogicException('Used contract replaced');}catch(\RuntimeException $expected){}
workflow_check((int)$db->query('SELECT COUNT(*) FROM contracts')->fetchColumn()===1&&!$db->inTransaction(),'Rejected revision rolls back without creating a contract');
$db->exec('DELETE FROM onboarding_email_challenges');
workflow_check(OnboardingContractWorkflow::renewDraft($db,$changed,'ddb_ccad_600',1),'Unissued unused draft is revised');
workflow_check((int)$db->query('SELECT COUNT(*) FROM contracts')->fetchColumn()===2,'Revision creates a new draft');
workflow_check($db->query('SELECT status FROM contracts WHERE id=2')->fetchColumn()==='void','Old contract stays in history');
workflow_check($ledger->practice($practice['id'])['state']==='cancelled'&&$ledger->artifact($practice['id'],'proposal')['sha256']===$sha,'Old PDF is preserved and old invitation cancelled');
workflow_check((int)$db->query('SELECT COUNT(*) FROM audit_log')->fetchColumn()===1&&OutboundMail::$last===[],'Revision is audited and sends no email');
$fixture=<<<'SOURCE'
<?php final class SubmissionRepository {
use CandidateContractReview;
public function previewContract(int $id,string $templateKey): array {return [];}
public function sendContract(int $id,int $userId): array {return [];}
public function sendContractBatch(array $input,int $userId): array {return [];}
public function find(){ $submission['contract_draft_writable']=true; }
}
SOURCE;
$patched=OnboardingWorkflowInstaller::repository($fixture);workflow_check($patched===OnboardingWorkflowInstaller::repository($patched),'Installer idempotent');workflow_check(str_contains($patched,'OnboardingContractWorkflow::send'),'Single send hook');workflow_check(str_contains($patched,'public function sendContractBatch'),'Existing async batch preserved');
$v2=str_replace('// TRB candidate onboarding workflow v3','// TRB candidate onboarding workflow v2',str_replace('OnboardingContractWorkflow::renewDraft($this->db,$s,$key,$userId);$s=$this->candidateDraft($id,$key);','',$patched));workflow_check(OnboardingWorkflowInstaller::repository($v2)===$patched,'Existing v2 installation upgrades to the revision guard');
try{OnboardingWorkflowInstaller::repository('<?php final class SubmissionRepository {
use CandidateContractReview;}');throw new \LogicException('Missing anchors accepted');}catch(\RuntimeException $expected){}
echo "Normal CRM preview/save/send and batch hooks, PDF MIME, personalized instructions, unchanged preview binding and guarded installer verified.\n";
