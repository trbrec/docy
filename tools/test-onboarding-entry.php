<?php
require_once __DIR__.'/fixtures-onboarding-entry-mail.php';
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingRuntime.php';
$db=new PDO('sqlite::memory:');
$db->exec('CREATE TABLE contacts(id INTEGER PRIMARY KEY,artist_name TEXT,first_name TEXT,last_name TEXT,email TEXT)');
$db->exec('CREATE TABLE submissions(id INTEGER PRIMARY KEY,contact_id INTEGER,source_tab TEXT,status TEXT,received_at TEXT,contract_number TEXT,contract_type TEXT)');
$db->exec('CREATE TABLE contracts(id INTEGER PRIMARY KEY,submission_id INTEGER,contract_number TEXT,template_key TEXT,status TEXT,sent_at TEXT,accepted_at TEXT,metadata TEXT)');
$db->exec('CREATE TABLE onboarding_practices(id TEXT PRIMARY KEY,contract_id INTEGER)');
$db->exec("INSERT INTO contacts VALUES(1,'QA','Mario','Rossi','qa@example.invalid')");
$insert=$db->prepare('INSERT INTO contracts(id,submission_id,contract_number,template_key,status,sent_at,accepted_at) VALUES(?,?,?,\'ddb_ccad_600\',?,?,?)');
foreach([
    [1,'draft',null,null,'fluent_form_7'],
    [2,'generated',null,null,'fluent_form_7'],
    [3,'prepared',null,null,'CRM_TEST_PERMANENT'],
    [4,'generated','2026-10-01',null,'fluent_form_7'],
    [5,'generated',null,'2026-10-01','fluent_form_7'],
    [6,'accepted',null,null,'fluent_form_7'],
    [7,'generated',null,null,'PORTALE_ARTISTI'],
    [8,'draft',null,null,'PORTALE_DEMO'],
    [9,'generated',null,null,'fluent_form_7'],
] as [$id,$status,$sent,$accepted,$source]){
    $db->prepare("INSERT INTO submissions(id,contact_id,source_tab,status,received_at) VALUES(?,1,?,'new','2026-10-01 09:00:00')")->execute([$id,$source]);
    $insert->execute([$id,$id,'QA-'.$id,$status,$sent,$accepted]);
}
$db->exec("INSERT INTO onboarding_practices VALUES('existing-practice',9)");
$type=new ReflectionClass(TrbCrm\OnboardingRuntime::class);$runtime=$type->newInstanceWithoutConstructor();
$type->getProperty('db')->setValue($runtime,$db);
$choices=$type->getMethod('preparationChoices')->invoke($runtime)['contracts'];
if(array_column($choices,'id')!==[3,2,1])throw new RuntimeException('New unsent CRM drafts must include generated PDFs and exclude sent, accepted, existing onboarding and legacy portal contracts');
echo "CRM draft and generated PDF entry verified; sent, accepted, existing onboarding and legacy portal contracts excluded.\n";

$db->exec('CREATE TABLE contract_sequences(year INTEGER PRIMARY KEY,next_number INTEGER)');
$db->exec("UPDATE submissions SET contract_number='TRB-20261000' WHERE id=3");
$db->exec("INSERT INTO submissions(id,contact_id,source_tab,status,received_at) VALUES(10,1,'fluent_form_7','new','2026-10-01 09:00:00'),(11,1,'PORTALE_ARTISTI','new','2026-10-01 09:00:00'),(12,1,'fluent_form_7','accepted','2026-10-01 09:00:00')");
$entry=new TrbCrm\OnboardingEntry($db);
if(array_column($entry->candidates(),'id')!==[10])throw new RuntimeException('Only new candidates without contracts may start');
$id=$entry->draft(10,'ddb_ccad_600',1);
if($db->query('SELECT contract_number FROM contracts WHERE id='.$id)->fetchColumn()!=='TRB-20261001')throw new RuntimeException('Imported number reconciliation failed');
if($entry->draft(10,'ddb_ccad_600',1)!==$id||$db->query('SELECT COUNT(*) FROM contracts WHERE submission_id=10')->fetchColumn()!==1)throw new RuntimeException('Draft retry duplicated the contract');
foreach([11,12] as $blocked){try{$entry->draft($blocked,'ddb_ccad_600',1);throw new LogicException('Legacy or accepted candidate changed');}catch(RuntimeException $expected){}}
try{$entry->draft(10,'trb_ccde',1);throw new LogicException('Existing draft overwritten');}catch(RuntimeException $expected){}
if($entry->candidates())throw new RuntimeException('Prepared candidates must leave the new candidate selector');
echo "Owner-selected candidate entry, legacy isolation, contract number reconciliation and retry idempotency verified.\n";

// The new route uses the existing authenticated Gmail connector, with a fixed
// single recipient and MIME integrity. No real email is sent by this fixture.
$fixtureFlag=dirname($type->getFileName(),3).'/private/onboarding-enabled.json';
$oldFlag=is_file($fixtureFlag)?file_get_contents($fixtureFlag):null;$newDir=!is_dir(dirname($fixtureFlag));
if($newDir)mkdir(dirname($fixtureFlag),0700,true);
file_put_contents($fixtureFlag,json_encode(['enabled'=>true,'version'=>TrbCrm\OnboardingPolicy::VERSION]));
try{
    $type->getMethod('mail')->invoke($runtime,'qa@example.invalid','Codice per la tua adesione TRB rec',"Gentile Mario,\n\nil codice è 123456.");
    $request=TrbCrm\OutboundMail::$last;$raw=base64_decode($request['raw_base64'],true);
    if(!hash_equals(hash('sha256',$raw),$request['mime_sha256'])||!str_starts_with($raw,"To: qa@example.invalid\r\n")||!str_contains($raw,'boundary="')||str_contains($raw,'boundary=\\"'))throw new RuntimeException('Onboarding email MIME binding invalid');
    TrbCrm\OutboundMail::$sentCopy=false;
    try{$type->getMethod('mail')->invoke($runtime,'qa@example.invalid','Accesso','Gentile Mario,');throw new LogicException('Unconfirmed email accepted');}catch(RuntimeException $expected){}
    file_put_contents($fixtureFlag,json_encode(['enabled'=>false,'version'=>TrbCrm\OnboardingPolicy::VERSION]));
    try{$type->getMethod('mail')->invoke($runtime,'qa@example.invalid','Accesso','Gentile Mario,');throw new LogicException('Disabled onboarding sent email');}catch(RuntimeException $expected){}
}finally{
    if($oldFlag===null)unlink($fixtureFlag);else file_put_contents($fixtureFlag,$oldFlag);
    if($newDir)rmdir(dirname($fixtureFlag));
}
echo "Scoped onboarding email transport, MIME binding, sent-copy receipt and disabled feature gate verified.\n";
