<?php
declare(strict_types=1);
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingService.php';
use TrbCrm\OnboardingLedger;use TrbCrm\OnboardingService;use TrbCrm\OnboardingContractCatalog;
function change_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function change_reject(callable $fn,string $message):void{try{$fn();}catch(Throwable $e){return;}throw new RuntimeException($message);}
function change_fixture(Closure $merchant):array{
 $db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$ledger=new OnboardingLedger($db);$ledger->install();
 $snapshot=OnboardingContractCatalog::model('ddb_ccad_600')+['unsigned_document_sha256'=>str_repeat('a',64),'first_name'=>'Mario','last_name'=>'Rossi','email'=>'artist@example.invalid','artist_folder_id'=>'synthetic-private-folder','contract_number'=>'SYNTHETIC-ONLY'];
 $created=$ledger->create(1,$snapshot,gmdate('c',time()+86400));$id=$created['id'];
 $ledger->saveDetails($id,['billing'=>['address_1'=>'Via QA 1'],'tax_code'=>'RSSMRA90A01H501W','privacy_acknowledged_at'=>gmdate('c')]);
 foreach(['identity_front','tax_front'] as $i=>$slot)$ledger->recordFile($id,$slot,['file_id'=>'synthetic-'.$i,'folder_id'=>'synthetic-private-folder','hash'=>'synthetic-'.$i]);
 $ledger->recordIdentity($id,['legible'=>true,'first_name'=>'Mario','last_name'=>'Rossi','birth_date'=>'1990-01-01','expiry_date'=>'2090-01-01','tax_legible'=>true,'tax_first_name'=>'Mario','tax_last_name'=>'Rossi','tax_birth_date'=>'1990-01-01','tax_code'=>'RSSMRA90A01H501W'],'2026-10-03');
 $noExternal=static function(){throw new RuntimeException('Unexpected synthetic signature call');};
 return [$ledger,new OnboardingService($ledger,new stdClass,new stdClass,$merchant,$noExternal),$id,$db,$snapshot];
}
$calls=0;$during=null;
[$ledger,$service,$id,$db,$snapshot]=change_fixture(function($p)use(&$calls,&$during){$calls++;if($during)$during();return ['order_id'=>123,'checkout_url'=>'https://store.trbrec.com/checkout/order-pay/123/','paid'=>false];});
$choose=fn($key)=>$service->choose($ledger->practice($id),$key,str_repeat('a',64),true);
$choose('A');$beforeDetails=$ledger->details($id);
change_reject(fn()=>$service->choose($ledger->practice($id),'B',str_repeat('a',64),false),'Revised formula requires consent');
change_reject(fn()=>$choose('other'),'Only proposed formulas can be selected');
change_check($ledger->details($id)===$beforeDetails,'Rejected edits do not rewrite the original acknowledgement');
foreach(['C'=>54000,'B'=>28500,'A'=>15000] as $key=>$amount){
 $v=$choose($key);change_check($v['selected_plan_key']===$key&&$v['selected_plan']['amounts_cents'][0]===$amount&&$v['can_change_plan'],'Every alternative can replace the current formula before checkout');
 change_check($ledger->details($id)['plan_key']===$key,'Accepted acknowledgement tracks the selected formula');
 change_check($ledger->practice($id)['snapshot']===$snapshot&&$ledger->installments($id)===[],'The proposal and accounting remain unchanged before payment');
}
change_reject(fn()=>$service->checkout($ledger->practice($id),'C'),'Stale tab cannot prepare another formula amount');
change_check($calls===0&&$service->view($ledger->practice($id))['can_change_plan'],'Stale checkout never dispatches or freezes a new payment');
$during=function()use($service,$ledger,$id,$choose){change_check(!$service->view($ledger->practice($id))['can_change_plan'],'Dispatch barrier is durable before the merchant request');change_reject(fn()=>$choose('C'),'An overlapping plan change cannot overtake payment preparation');};
$service->checkout($ledger->practice($id),'A');
change_check($calls===1&&!$service->view($ledger->practice($id))['can_change_plan'],'Opened checkout makes the selected formula immutable');
change_reject(fn()=>$choose('C'),'Existing order cannot be reassigned to another formula');
change_check($ledger->details($id)['plan_key']==='A','Refused replacement preserves the accepted formula');

[$lostLedger,$lostService,$lostId]=change_fixture(static function(){throw new RuntimeException('Synthetic merchant response lost');});
$lostService->choose($lostLedger->practice($lostId),'B',str_repeat('a',64),true);
change_reject(fn()=>$lostService->checkout($lostLedger->practice($lostId),'B'),'Lost merchant response is surfaced');
change_check($lostLedger->orders($lostId)===[]&&!$lostService->view($lostLedger->practice($lostId))['can_change_plan'],'Unknown external order still prevents changing the formula');
change_reject(fn()=>$lostService->choose($lostLedger->practice($lostId),'C',str_repeat('a',64),true),'Uncertain dispatch cannot mutate contractual terms');

[$paidLedger,$paidService,$paidId]=change_fixture(static function(){throw new RuntimeException('No synthetic merchant request expected');});
$paidService->choose($paidLedger->practice($paidId),'B',str_repeat('a',64),true);
$paidLedger->confirmedPayment($paidId,1,['provider'=>'woocommerce','transaction_id'=>'order:123:synthetic-capture','amount_cents'=>28500,'currency'=>'EUR','paid_on'=>'2026-10-03','status'=>'confirmed']);
change_reject(fn()=>$paidService->choose($paidLedger->practice($paidId),'A',str_repeat('a',64),true),'Confirmed payment always fixes the formula');
change_check(count($paidLedger->installments($paidId))===2&&(int)$paidLedger->installments($paidId)[0]['confirmed_cents']===28500,'Paid accounting and original schedule remain intact');
echo "Formula replacement, fresh acknowledgement, exact amounts, stale-tab rejection, overlapping checkout, lost-response barrier and paid-accounting protection verified.\n";
