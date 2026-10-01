<?php
namespace TrbCrm;
require_once __DIR__.'/fixtures-onboarding-entry-mail.php';
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
workflow_check($p['onboarding']&&$p['plugin_dispatch_available']&&str_contains($p['body'],'Ciao Mario'),'Normal preview personalization');
workflow_check(!str_contains($p['body'],'Carica qui il contratto firmato'),'Old signed PDF upload instruction removed');
workflow_check($db->query('SELECT COUNT(*) FROM sqlite_master WHERE name LIKE \'onboarding_%\'')->fetchColumn()===0,'Preview sends no email and creates no practice');
$fixture=<<<'SOURCE'
<?php final class SubmissionRepository {
public function previewContract(int $id,string $templateKey): array {return [];}
public function sendContract(int $id,int $userId): array {return [];}
public function sendContractBatch(array $input,int $userId): array {return [];}
public function find(){ $submission['contract_draft_writable']=true; }
}
SOURCE;
$patched=OnboardingWorkflowInstaller::repository($fixture);workflow_check($patched===OnboardingWorkflowInstaller::repository($patched),'Installer idempotent');workflow_check(str_contains($patched,'OnboardingContractWorkflow::send'),'Single send hook');workflow_check(str_contains($patched,'OnboardingContractWorkflow::batch'),'Batch send hook');
try{OnboardingWorkflowInstaller::repository('<?php final class SubmissionRepository {}');throw new \LogicException('Missing anchors accepted');}catch(\RuntimeException $expected){}
echo "Normal CRM preview/save/send and batch hooks, PDF MIME, personalized instructions, unchanged preview binding and guarded installer verified.\n";
