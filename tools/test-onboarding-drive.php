<?php
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingDrive.php';
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingLedger.php';
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingContractCatalog.php';
use TrbCrm\OnboardingDrive;use TrbCrm\OnboardingLedger;
$bytes='%PDF-fixture';$sha=hash('sha256',$bytes);$tampered=false;
$archive=new OnboardingDrive(function($p)use($bytes,$sha,&$tampered){return ['data'=>base64_encode($tampered?'wrong':$bytes),'size'=>strlen($bytes),'mime'=>'application/pdf','name'=>'fixture.pdf','sha256'=>$sha,'file_id'=>'drive-file-abc','folder_id'=>'drive-folder-abc','artist_folder_id'=>'drive-owner-abc'];});
$read=$archive->read('drive-file-abc','drive-folder-abc',$sha);if($read['data']!==base64_encode($bytes))throw new RuntimeException('private bytes missing');
$tampered=true;try{$archive->read('drive-file-abc','drive-folder-abc',$sha);throw new LogicException('tampering accepted');}catch(RuntimeException $expected){}$tampered=false;
$file=$archive->verifyArtifact(['folder_id'=>'drive-folder-abc','code'=>'grant-code'],$sha,'drive-owner-abc');
try{$archive->verifyArtifact(['folder_id'=>'drive-folder-abc','code'=>'grant-code'],$sha,'foreign-owner');throw new LogicException('foreign practice accepted');}catch(RuntimeException $expected){}
$db=new PDO('sqlite::memory:');$ledger=new OnboardingLedger($db);$ledger->install();
$snapshot=TrbCrm\OnboardingContractCatalog::model('ddb_ccad_600')+['unsigned_document_sha256'=>$sha,'first_name'=>'Mario','last_name'=>'Rossi','email'=>'qa@example.invalid','artist_folder_id'=>'drive-owner-abc'];
$created=$ledger->create(999,$snapshot,gmdate('c',time()+3600));$ledger->saveArtifact($created['id'],'proposal',$file);
if($ledger->artifact($created['id'],'proposal')['file_id']!=='drive-file-abc')throw new RuntimeException('Drive identifier truncated');
echo "Private Drive bytes, hash tampering, foreign practice and string identifiers verified.\n";
