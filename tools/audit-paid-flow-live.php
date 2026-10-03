<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();
set_exception_handler(static function(){while(ob_get_level())ob_end_clean();file_put_contents('php://stderr',"Plan revision read-only audit unconfirmed.\n");exit(1);});
require '/home/customer/www/crm.trbrec.com/public_html/app/Core.php';
\TrbCrm\Env::load('/home/customer/www/crm.trbrec.com/public_html/.env');
require_once '/home/customer/www/crm.trbrec.com/public_html/app/OnboardingService.php';
$db=\TrbCrm\Database::connection();$ledger=new \TrbCrm\OnboardingLedger($db);$p=$ledger->forContract(27);
if(!$p||$p['email']!=='a.tognassi@gmail.com'||$p['snapshot']['contract_number']!=='TRB-QA-NONVALIDO-DDB600-20261003'||$p['snapshot']['template_key']!=='ddb_ccad_600')throw new RuntimeException('Scope mismatch');
$before=$p;$details=$ledger->details($p['id']);$counts=[];
foreach(['onboarding_orders','onboarding_payments','onboarding_installments','onboarding_signatures','onboarding_events'] as $table){$q=$db->prepare('SELECT COUNT(*) FROM '.$table.' WHERE practice_id=?');$q->execute([$p['id']]);$counts[$table]=(int)$q->fetchColumn();}
$disabled=static function(){throw new RuntimeException('External side effect disabled');};
$service=new \TrbCrm\OnboardingService($ledger,new stdClass,new stdClass,$disabled,$disabled);$v=$service->view($p);
$lockId=bin2hex(random_bytes(16));$lockName='trb_onboarding_payment_'.substr(hash('sha256',$lockId),0,32);
$locked=$ledger->paymentOperation($lockId,function()use($db,$lockName){$q=$db->prepare('SELECT IS_USED_LOCK(?)=CONNECTION_ID()');$q->execute([$lockName]);return ['held'=>(int)$q->fetchColumn()===1];});
$q=$db->prepare('SELECT IS_FREE_LOCK(?)');$q->execute([$lockName]);$released=(int)$q->fetchColumn()===1;
$sources=json_decode('{"/home/customer/www/crm.trbrec.com/public_html/app/OnboardingLedger.php":"c887cbfe54d151b0bf33e04b4cdd0431539d4bf8","/home/customer/www/crm.trbrec.com/public_html/app/OnboardingService.php":"6cd73ec89fbc53210c2ef456eab100a72d847629","/home/customer/www/crm.trbrec.com/public_html/app/OnboardingRuntime.php":"81ba7f15baf7505b18ca8d1143bef0514a0c0e82","/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy/inc/trb-candidate-onboarding-page.php":"4579f69906cc4475ab616e4421e90dc58d7185c6","/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy/assets/js/onboarding/onboarding.js":"795859e5221d2e1ebc302b9f58a56cfd4b03a0ab"}',true);$sourceMatch=true;
foreach($sources as $file=>$sha){$body=file_get_contents($file);$sourceMatch=$sourceMatch&&hash_equals($sha,hash('sha1','blob '.strlen($body)."\0".$body));}
$unchanged=$ledger->practice($p['id'])===$before&&$ledger->details($p['id'])===$details;
foreach($counts as $table=>$old){$q=$db->prepare('SELECT COUNT(*) FROM '.$table.' WHERE practice_id=?');$q->execute([$p['id']]);$unchanged=$unchanged&&$old===(int)$q->fetchColumn();}
$out=['source_files_verified'=>$sourceMatch,'mysql_operation_lock_held'=>$locked['held'],'mysql_operation_lock_released'=>$released,'read_only_confirmed'=>$unchanged,'current_state'=>$v['state'],'current_formula'=>$v['selected_plan']['display_label']??null,'current_first_amount_cents'=>$v['selected_plan']['amounts_cents'][0]??null,'formula_editable'=>$v['can_change_plan'],'selected_key_consistent'=>$v['selected_plan']===null||isset($p['snapshot']['plans'][$v['selected_plan_key']]),'existing_orders'=>$counts['onboarding_orders'],'existing_payments'=>$counts['onboarding_payments']];
$out['success']=$sourceMatch&&$locked['held']&&$released&&$unchanged&&$out['selected_key_consistent'];while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";exit($out['success']?0:1);
