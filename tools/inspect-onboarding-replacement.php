<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();echo json_encode(['success'=>false,'inspection_unconfirmed'=>true])."\n";exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';
if(trim((string)file_get_contents($theme.'/.trb-deployed-sha'))!=='f87f532df994867e3a259581cc7d20038fc0cbfc')exit(2);
require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require_once $root.'/app/OnboardingRuntime.php';
$db=\TrbCrm\Database::connection();$l=new \TrbCrm\OnboardingLedger($db);$p=$l->forContract(27);
if(!$p||$p['email']!=='a.tognassi@gmail.com'||$p['snapshot']['contract_number']!=='TRB-QA-NONVALIDO-DDB600-20261003'||$p['snapshot']['template_key']!=='ddb_ccad_600')throw new RuntimeException();
$id=$p['id'];$files=$l->files($id);$slots=[];
foreach(['identity_front','tax_front'] as $slot){$f=$files[$slot]??null;$g=$l->upload($id,$slot);$slots[$slot]=['saved'=>(bool)$f,'size'=>$f['size']??null,'mime'=>$f['mime']??null,'active_grant_matches_saved_folder'=>$f&&$g&&(string)$f['folder_id']===(string)$g['folder_id'],'grant_expires_at'=>$g['expires_at']??null];}
$q=$db->prepare('SELECT state,attempts,lease_until,next_retry_at,reason,queued_at,started_at,finished_at FROM onboarding_identity_jobs WHERE practice_id=?');$q->execute([$id]);$job=$q->fetch(\PDO::FETCH_ASSOC)?:null;
$q=$db->prepare('SELECT checked_at FROM onboarding_identity_checks WHERE practice_id=?');$q->execute([$id]);$checked=$q->fetchColumn();
$decision=$l->identityResult($id);
$q=$db->prepare('SELECT COUNT(*) FROM onboarding_payments WHERE practice_id=?');$q->execute([$id]);$payments=(int)$q->fetchColumn();
$out=['success'=>true,'contract_id'=>27,'state'=>$p['state'],'job'=>$job,'identity_checked_at'=>$checked,'identity_decision'=>array_intersect_key($decision,array_flip(['status','reason'])),'verification'=>$l->identityVerification($id),'slots'=>$slots,'payments'=>$payments,'signature_created'=>$l->signature($id)!==null];
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_UNICODE)."\n";
