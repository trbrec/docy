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
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Rome')))->format('Y-m-d');
$runtime=new \TrbCrm\OnboardingRuntime($db);$property=new ReflectionProperty($runtime,'service');$service=$property->getValue($runtime);
if(!$p['owner_approved_at']){$service->refreshPayments($p['id']);$ledger->approve($p['id'],$owner,$owner['email'],$today);}
$artifact=$service->prepareFinal($ledger->practice($p['id']));
$out=['qa_final_prepared'=>true,'state'=>$ledger->practice($p['id'])['state'],'drive_pdf_id'=>$artifact['drive_pdf_id'],'sha256'=>$artifact['sha256'],'signature_reserved'=>(bool)$ledger->signature($p['id'])];
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
