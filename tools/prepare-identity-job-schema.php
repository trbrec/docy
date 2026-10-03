<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
$root='/home/customer/www/crm.trbrec.com/public_html';require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require_once $root.'/app/OnboardingRuntime.php';
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();echo json_encode(['success'=>false,'migration_unconfirmed'=>true])."\n";exit(1);});
$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';if(trim(file_get_contents($theme.'/.trb-deployed-sha'))!=='fd431de6538d462bd7cc8916a6f35e540a5b5138')exit(2);
$db=\TrbCrm\Database::connection();$ledger=new \TrbCrm\OnboardingLedger($db);$p=$ledger->forContract(27);
if(!$p||$p['email']!=='a.tognassi@gmail.com'||$p['snapshot']['contract_number']!=='TRB-QA-NONVALIDO-DDB600-20261003'||$p['snapshot']['template_key']!=='ddb_ccad_600')throw new RuntimeException();
$before=hash('sha256',json_encode([$ledger->files($p['id']),$ledger->details($p['id']),$ledger->identityResult($p['id'])]));
$db->exec('CREATE TABLE IF NOT EXISTS onboarding_identity_jobs (practice_id VARCHAR(64) PRIMARY KEY, input_fingerprint VARCHAR(64) NOT NULL, state VARCHAR(24) NOT NULL, attempts INT NOT NULL DEFAULT 0, claim_token VARCHAR(64) NULL, lease_until BIGINT NOT NULL DEFAULT 0, next_retry_at BIGINT NOT NULL DEFAULT 0, reason VARCHAR(64) NOT NULL DEFAULT \'\', queued_at VARCHAR(32) NOT NULL, started_at VARCHAR(32) NULL, finished_at VARCHAR(32) NULL)');
$columns=$db->query('SHOW COLUMNS FROM onboarding_identity_jobs')->fetchAll(PDO::FETCH_COLUMN);foreach(['practice_id','input_fingerprint','state','attempts','claim_token','lease_until','next_retry_at','reason','queued_at','started_at','finished_at'] as $name)if(!in_array($name,$columns,true))throw new RuntimeException();
$unchanged=hash_equals($before,hash('sha256',json_encode([$ledger->files($p['id']),$ledger->details($p['id']),$ledger->identityResult($p['id'])])));if(!$unchanged)throw new RuntimeException();
$out=['success'=>true,'identity_job_schema_ready'=>true,'existing_test_documents_and_details_unchanged'=>$unchanged,'no_verification_or_signature_started'=>true];
while(ob_get_level())ob_end_clean();echo json_encode($out)."\n";
