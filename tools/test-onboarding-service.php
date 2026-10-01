<?php
declare(strict_types=1);
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingService.php';
use TrbCrm\OnboardingLedger;use TrbCrm\OnboardingService;use TrbCrm\OnboardingContractCatalog;
function service_check(bool $condition,string $name):void{if(!$condition)throw new RuntimeException($name);}
function service_reject(callable $call,string $name):void{try{$call();}catch(Throwable $e){return;}throw new RuntimeException($name);}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$ledger=new OnboardingLedger($db);$ledger->install();
$snapshot=OnboardingContractCatalog::model('ddb_ccad_600')+['unsigned_document_sha256'=>str_repeat('a',64),'first_name'=>'Mario','last_name'=>'Rossi','artist_name'=>'Artista di collaudo','email'=>'test@example.invalid','artist_folder_id'=>'drive-owner-333','contract_number'=>'TEST-1234'];
$created=$ledger->create(123,$snapshot,gmdate('c',time()+86400));$id=$created['id'];
$archive=new class{public int $sequence=0;public bool $auditFailure=true;public function artistFolder($group,$folder){return [];}public function url($file,$folder,$hash){return 'https://files.pcloud.com/example';}public function createUpload($folder,$id,$slot){return ['folder_id'=>'drive-folder-'.(++$this->sequence),'upload_link_id'=>$this->sequence,'expires_at'=>gmdate('c',time()+7200),'slot'=>$slot];}public function verifyArtifact($grant,$sha,$folder){if($grant['slot']==='signature_audit'&&$this->auditFailure){$this->auditFailure=false;throw new RuntimeException('synthetic archive response lost');}return ['file_id'=>'drive-file-'.$grant['upload_link_id'],'folder_id'=>$grant['folder_id'],'hash'=>'remote-hash','sha256'=>$sha,'artist_folder_id'=>$folder,'name'=>$grant['slot'].'.pdf','size'=>200];}};
$reader=new class{public function extract($docs){return ['legible'=>true,'first_name'=>'Mario Antonio','last_name'=>'Rossi','birth_date'=>'1990-01-01'];}};
$order=null;$status='pending';$badEmail=false;$activationCalls=0;
$portal=function($payload)use(&$order,&$status,&$badEmail,&$activationCalls,$snapshot,$id){
 if($payload['action']==='activate_account'){$activationCalls++;return ['activated'=>true,'practice_id'=>$id,'portal_user_id'=>$payload['portal_user_id']];}
 if($payload['action']==='store_order'){$order=$payload;return ['order_id'=>101,'checkout_url'=>'https://store.trbrec.com/checkout/order-pay/101/','paid'=>false];}
 if($payload['action']==='store_status')return ['practice_id'=>$id,'number'=>1,'snapshot_sha256'=>$order['snapshot_sha256'],'email'=>$badEmail?'other@example.invalid':$snapshot['email'],'provider'=>'woocommerce','transaction_id'=>'order:101:provider-proof','amount_cents'=>$order['amount_cents'],'currency'=>'EUR','paid_on'=>(new DateTimeImmutable('now',new DateTimeZone('Europe/Rome')))->format('Y-m-d'),'status'=>$status,'captured'=>$status==='confirmed'];
 throw new RuntimeException('unexpected portal action');
};
$dispatches=0;$dispatchResponseLost=true;$completed=false;$companySigned=false;$archiveCalls=0;$failAuditOnce=true;
$script=function($payload)use(&$dispatches,&$dispatchResponseLost,&$completed,&$companySigned,&$archiveCalls){
 switch($payload['action']){
  case 'crm_onboarding_document':service_check($payload['phase']==='final','only final artifact requested');return ['sha256'=>str_repeat('b',64),'drive_pdf_id'=>'drive-pdf-qa'];
  case 'crm_onboarding_signature':$dispatches++;service_check($payload['owner_approved']===true&&$payload['document_sha256']===str_repeat('b',64),'signature uses verified artifact');if($dispatchResponseLost){$dispatchResponseLost=false;throw new RuntimeException('synthetic lost CRM response after provider receipt');}return ['dossier_id'=>'98765'];
  case 'crm_onboarding_signature_status':return ['dossier_id'=>'98765','status'=>$completed?'completed':'pending','artist_signed'=>$completed,'company_signed'=>$companySigned];
  case 'crm_onboarding_signed_archive':$archiveCalls++;if($archiveCalls===2)service_check(!isset($payload['uploads']['signed_pdf'])&&isset($payload['uploads']['signature_audit']),'partial archive retries only missing proof');return ['signed_pdf_sha256'=>str_repeat('c',64),'signature_audit_sha256'=>str_repeat('d',64)];
 }
 throw new RuntimeException('unexpected script action');
};
$service=new OnboardingService($ledger,$archive,$reader,$portal,$script);
$details=['privacy_acknowledged'=>true,'billing'=>['address_1'=>'Via collaudo 1','city'=>'Roma','postcode'=>'00100','country'=>'IT','phone'=>'+393330000000'],'tax_code'=>'RSSMRA90A01H501W'];
$service->details($ledger->practice($id),$details);
foreach(['identity_front','identity_back','tax_front'] as $i=>$slot)$ledger->recordFile($id,$slot,['file_id'=>10+$i,'folder_id'=>333,'hash'=>'h'.$i,'name'=>$slot.'.pdf']);
$service->identity($ledger->practice($id));
$service_reject=fn()=>null;
service_reject(fn()=>$service->choose($ledger->practice($id),'C',str_repeat('a',64),false),'proposal acknowledgement necessary');
$service->choose($ledger->practice($id),'C',str_repeat('a',64),true);$service->choose($ledger->practice($id),'C',str_repeat('a',64),true);
service_reject(fn()=>$service->choose($ledger->practice($id),'A',str_repeat('a',64),true),'confirmed formula cannot be replaced');
$service->checkout($ledger->practice($id));service_check($order['amount_cents']===54000,'exact contract price sent to Store');
$owner=['id'=>1,'role'=>'admin','email'=>'owner@example.invalid'];$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Rome')))->format('Y-m-d');
service_reject(fn()=>$ledger->approve($id,$owner,$owner['email'],$today),'pending checkout does not permit approval');
$status='confirmed';$badEmail=true;service_reject(fn()=>$service->refreshPayments($id),'foreign receipt cannot activate');service_check($ledger->practice($id)['first_payment_date']===null,'foreign receipt leaves anchor unset');
$badEmail=false;$service->refreshPayments($id);$ledger->approve($id,$owner,$owner['email'],$today);
service_reject(fn()=>$service->dispatchSignature($ledger->practice($id)),'lost dispatch response is surfaced');$service->dispatchSignature($ledger->practice($id));service_check($dispatches===1,'one chargeable dossier across lost response retry');
$completed=true;service_reject(fn()=>$service->refreshSignature($ledger->practice($id)),'one signature cannot activate');service_check($ledger->practice($id)['state']==='signature_pending','missing company signature stays pending');
$companySigned=true;service_reject(fn()=>$service->refreshSignature($ledger->practice($id)),'partial archive does not activate');service_check($ledger->practice($id)['state']==='signature_pending','partial archive stays pending');$service->refreshSignature($ledger->practice($id));$service->refreshSignature($ledger->practice($id));service_check($archiveCalls===2,'verified archive is reused and missing proof recovered');
service_check(str_starts_with((string)$ledger->practice($id)['signed_pcloud_file_id'],'drive-file-'),'signed Drive ID survives account activation');
service_check($ledger->artifact($id,'signed_pdf')!==null&&$ledger->artifact($id,'signature_audit')!==null,'contract and proof archived before registration');
$service->register($ledger->practice($id),1234);$service->register($ledger->practice($id),1234);service_reject(fn()=>$service->register($ledger->practice($id),4321),'another account cannot take the practice');service_check($ledger->portalPractice(1234,$snapshot['email'])['id']===$id,'correct portal identity bound');
service_check($ledger->access($id,$today)['allowed'],'services enabled only after final registration');
$ledger->cancel($id,$owner);service_check(!$ledger->access($id,$today)['allowed'],'cancellation suspends future services');
echo "Onboarding orchestration, trusted receipts, one dossier, both signatures, archive and account binding verified.\n";
