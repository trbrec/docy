<?php
/** CRM proposal selection against a real in-memory database; no network or mail. */
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingIntake.php';
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingLedger.php';
use TrbCrm\OnboardingIntake;
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE contacts(id INTEGER PRIMARY KEY,artist_name TEXT,first_name TEXT,last_name TEXT); CREATE TABLE submissions(id INTEGER PRIMARY KEY,contact_id INTEGER); CREATE TABLE contracts(id INTEGER PRIMARY KEY,submission_id INTEGER,contract_number TEXT,template_key TEXT,status TEXT,sent_at TEXT,accepted_at TEXT);');
$db->exec("INSERT INTO contacts VALUES(1,'Collaudo','Nome','Cognome'); INSERT INTO submissions VALUES(1,1);");
$ledger=new TrbCrm\OnboardingLedger($db);$ledger->install();
$insert=$db->prepare('INSERT INTO contracts VALUES(?,1,?,?,?, ?,?)');
foreach([
    [1,'draft',null,null,'ddb_ccad_600'],[2,'prepared',null,null,'ddb_ccad_800'],[3,'generated',null,null,'ddb_csae_600'],
    [4,'generated','2026-10-01',null,'ddb_ccad_600'],[5,'draft',null,'2026-10-01','ddb_ccad_600'],
    [6,'sent',null,null,'ddb_ccad_600'],[7,'accepted',null,null,'ddb_ccad_600'],[8,'void',null,null,'ddb_ccad_600'],
    [9,'generated',null,null,'legacy-accounting'],[10,'generated',null,null,'ddb_ccad_600'],
] as [$id,$status,$sent,$accepted,$key])$insert->execute([$id,'QA-'.$id,$key,$status,$sent,$accepted]);
$snapshot=TrbCrm\OnboardingContractCatalog::model('ddb_ccad_600')+['unsigned_document_sha256'=>str_repeat('a',64),'first_name'=>'Nome','last_name'=>'Cognome','email'=>'qa@example.invalid','artist_folder_id'=>'drive-owner-qa'];
$ledger->create(10,$snapshot,gmdate('c',time()+3600));
$rows=OnboardingIntake::choices($db);
if(array_map('intval',array_column($rows,'id'))!==[3,2,1])throw new RuntimeException('Unsent generated preview missing or historical/duplicate contract selectable');
if($rows[0]['template_key']!=='ddb_csae_600')throw new RuntimeException('Selected proposal model not preserved');
if(OnboardingIntake::eligible(['status'=>'generated','sent_at'=>'2026-10-01']))throw new RuntimeException('Stale sent proposal accepted');
if(OnboardingIntake::eligible(['status'=>'generated','accepted_at'=>'2026-10-01']))throw new RuntimeException('Stale accepted proposal accepted');
echo "CRM draft, prepared and generated proposals selectable; sent, accepted, void, legacy and duplicate practices excluded.\n";
