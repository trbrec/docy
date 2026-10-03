<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
$root='/home/customer/www/crm.trbrec.com/public_html';require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require_once $root.'/app/OnboardingRuntime.php';
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();echo json_encode(['success'=>false,'inspection_unconfirmed'=>true])."\n";exit(1);});
$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';if(trim(file_get_contents($theme.'/.trb-deployed-sha'))!=='fd431de6538d462bd7cc8916a6f35e540a5b5138')exit(2);
$db=\TrbCrm\Database::connection();$ledger=new \TrbCrm\OnboardingLedger($db);$p=$ledger->forContract(27);
if(!$p||$p['email']!=='a.tognassi@gmail.com'||$p['snapshot']['contract_number']!=='TRB-QA-NONVALIDO-DDB600-20261003'||$p['snapshot']['template_key']!=='ddb_ccad_600')throw new RuntimeException();
$files=$ledger->files($p['id']);$details=$ledger->details($p['id']);$decision=$ledger->identityResult($p['id']);
$q=$db->prepare('SELECT checked_at FROM onboarding_identity_checks WHERE practice_id=?');$q->execute([$p['id']]);$checked=$q->fetchColumn();
$q=$db->prepare('SELECT kind,status,created_at,completed_at FROM onboarding_events WHERE practice_id=? ORDER BY created_at');$q->execute([$p['id']]);$events=$q->fetchAll();
$q=$db->prepare('SELECT COUNT(*) FROM onboarding_payments WHERE practice_id=?');$q->execute([$p['id']]);$payments=(int)$q->fetchColumn();
$out=['success'=>true,'contract_id'=>27,'state'=>$p['state'],'identity_decision'=>array_intersect_key($decision,array_flip(['status','reason'])),'identity_checked_at'=>$checked,'details_saved'=>!empty($details['privacy_acknowledged_at']),'identity_front_saved'=>isset($files['identity_front']),'tax_front_saved'=>isset($files['tax_front']),'payments'=>$payments,'signature_created'=>$ledger->signature($p['id'])!==null,'events'=>$events];
while(ob_get_level())ob_end_clean();echo json_encode($out)."\n";
