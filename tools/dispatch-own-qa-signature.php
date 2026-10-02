<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Targeted onboarding diagnostic unconfirmed.\n");exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';
require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require $root.'/app/OnboardingRuntime.php';
$db=\TrbCrm\Database::connection();$ledger=new \TrbCrm\OnboardingLedger($db);$p=$ledger->forContract(26);
if(!$p||($p['snapshot']['contract_number']??'')!=='QA-TRB-NONVALIDO-20261002-2235'||$p['email']!=='a.tognassi@gmail.com')throw new RuntimeException('QA scope mismatch');
$q=$db->prepare('SELECT submission_id FROM contracts WHERE id=?');$q->execute([26]);if((int)$q->fetchColumn()!==719)throw new RuntimeException('QA scope mismatch');

$q=$db->prepare("SELECT id,role,email FROM users WHERE LOWER(email)=?");$q->execute(['andrea.tognassi@trbrec.com']);$owner=$q->fetch();
if(!$owner||$owner['role']!=='admin')throw new RuntimeException('Owner missing');

$runtime=new \TrbCrm\OnboardingRuntime($db);$service=(new ReflectionProperty($runtime,'service'))->getValue($runtime);
$artifact=$ledger->artifact($p['id'],'final_pdf');
if(!$p['owner_approved_at']||!$artifact||($artifact['drive_pdf_id']??'')!=='1Vkpo_URcCNxBj6hcuO7u6HVxyyXECx_g'||!hash_equals('fd2d02b8f9e4c8d82391bad1115677be98247fc8fecf987760542000ee632286',$artifact['sha256']))throw new RuntimeException('Final artifact mismatch');
$q=$db->prepare('SELECT result,document_fingerprint FROM onboarding_identity_checks WHERE practice_id=?');$q->execute([$p['id']]);$check=$q->fetch();$decision=$check?json_decode($check['result'],true):[];
if(($decision['status']??'')!=='matched'||!hash_equals($check['document_fingerprint'],hash('sha256',json_encode($ledger->files($p['id']),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))))throw new RuntimeException('Identity mismatch');
$old=$ledger->signature($p['id']);
if($old){$service->refreshSignature($p);$result=['already_reserved'=>true];}
else $result=$service->dispatchSignature($p);
$receipt=$ledger->signature($p['id']);
$out=['qa_signature_dispatch'=>true,'state'=>$ledger->practice($p['id'])['state'],'signature_state'=>$receipt['state']??null,'dossier_id'=>$receipt['dossier_id']??null,'new_request'=>!$old,'recipient_exact'=>true];
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
