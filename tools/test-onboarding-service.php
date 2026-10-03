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
$reader=new class{public function extract($docs){service_check(array_column($docs,'slot')===['identity_front','tax_front'],'only the two labeled fronts reach the reader');return ['identity_document'=>true,'tax_document'=>true,'legible'=>true,'first_name'=>'Mario Antonio','last_name'=>'Rossi','birth_date'=>'1990-01-01','expiry_date'=>'2090-01-01','tax_legible'=>true,'tax_first_name'=>'Mario Antonio','tax_last_name'=>'Rossi','tax_birth_date'=>'1990-01-01','tax_code'=>'RSSMRA90A01H501W'];}};
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
$presented=$service->view($ledger->practice($id));
service_check($presented['nominal_cents']===60000,'Original DDB quota remains 600 euros');
foreach(['C'=>['Opzione A – Unica soluzione',54000,6000],'B'=>['Opzione B – Due versamenti',28500,3000],'A'=>['Opzione C – Rate mensili senza interessi',15000,0]] as $key=>[$label,$first,$saving]){
 service_check($presented['plans'][$key]['display_label']===$label&&$presented['plans'][$key]['amounts_cents'][0]===$first,'Visible alternative retains its exact first payment');
 service_check($presented['nominal_cents']-$presented['plans'][$key]['total_cents']===$saving,'Exact saving is derived from the original fee');
}
service_check($ledger->practice($id)['snapshot']['plans']===$snapshot['plans'],'Presentation never rewrites issued proposal terms');
$details=['privacy_acknowledged'=>true,'billing'=>['address_1'=>'Via collaudo 11/A','street'=>'Via collaudo','street_number'=>'11/A','city'=>'Roma','postcode'=>'00100','country'=>'IT','phone'=>'+393330000000'],'tax_code'=>'RSSMRA90A01H501W'];
$details['profile']=['birth_date'=>'1990-01-01','birth_place'=>'Roma','birth_province'=>'RM','document_number'=>'CA12345AB','document_expiry'=>'2090-01-01'];
$service->details($ledger->practice($id),$details);
service_check($ledger->details($id)['billing']['street_number']==='11/A'&&$ledger->details($id)['profile']['document_number']==='CA12345AB','structured portal fields survive CRM storage');
foreach(['identity_front','tax_front'] as $i=>$slot)$ledger->recordFile($id,$slot,['file_id'=>10+$i,'folder_id'=>333,'hash'=>'h'.$i,'name'=>$slot.'.pdf']);
$mismatch=$details;$mismatch['profile']['birth_date']='1991-01-01';$service->details($ledger->practice($id),$mismatch);$service->identity($ledger->practice($id));$service->processIdentity($id);service_check($service->view($ledger->practice($id))['state']==='identity_review','declared birth date cannot contradict the documents');$service->details($ledger->practice($id),$details);$service->identity($ledger->practice($id));$service->processIdentity($id);
$service_reject=fn()=>null;
service_reject(fn()=>$service->choose($ledger->practice($id),'C',str_repeat('a',64),false),'proposal acknowledgement necessary');
$service->choose($ledger->practice($id),'C',str_repeat('a',64),true);$service->choose($ledger->practice($id),'C',str_repeat('a',64),true);
service_check($service->view($ledger->practice($id))['can_change_plan'],'Formula remains editable before opening checkout');
$service->choose($ledger->practice($id),'A',str_repeat('a',64),true);service_check($service->view($ledger->practice($id))['selected_plan']['amounts_cents'][0]===15000,'Monthly alternative replaces the single payment before checkout');
$service->choose($ledger->practice($id),'B',str_repeat('a',64),true);service_check($service->view($ledger->practice($id))['selected_plan']['amounts_cents'][0]===28500,'Two-payment alternative uses the exact first amount');
$service->choose($ledger->practice($id),'C',str_repeat('a',64),true);service_check($ledger->details($id)['plan_key']==='C','Accepted replacement updates its acknowledgement atomically');
$service->checkout($ledger->practice($id));service_check($order['amount_cents']===54000,'exact contract price sent to Store');service_check(!$service->view($ledger->practice($id))['can_change_plan'],'Opening checkout locks the formula');service_reject(fn()=>$service->choose($ledger->practice($id),'A',str_repeat('a',64),true),'An opened checkout cannot be reassigned to another formula');
$owner=['id'=>1,'role'=>'admin','email'=>'owner@example.invalid'];$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Rome')))->format('Y-m-d');
service_reject(fn()=>$ledger->approve($id,$owner,$owner['email'],$today),'pending checkout does not permit approval');
$status='confirmed';$badEmail=true;service_reject(fn()=>$service->refreshPayments($id),'foreign receipt cannot activate');service_check($ledger->practice($id)['first_payment_date']===null,'foreign receipt leaves anchor unset');
$badEmail=false;$service->refreshPayments($id);$ledger->approve($id,$owner,$owner['email'],$today);
service_check($service->view($ledger->practice($id))['signature_email']===null,'approved practice does not claim a signature email');service_reject(fn()=>$service->dispatchSignature($ledger->practice($id)),'lost dispatch response is surfaced');service_check($service->view($ledger->practice($id))['signature_email']===null,'reserved dossier never claims an email receipt');$service->dispatchSignature($ledger->practice($id));service_check($dispatches===1,'one chargeable dossier across lost response retry');
$service->refreshSignature($ledger->practice($id));$mail=$service->view($ledger->practice($id))['signature_email'];service_check($mail['sender']==='OTP service <please-do-not-reply@otpservice.io>'&&str_starts_with($mail['subject'],'Il documento 98765 da firmare'),'reconciled receipt exposes exact signature email metadata');
$completed=true;service_reject(fn()=>$service->refreshSignature($ledger->practice($id)),'one signature cannot activate');service_check($ledger->practice($id)['state']==='signature_pending','missing company signature stays pending');service_check(!$ledger->reserveWelcome($id),'one signature cannot send welcome');
$companySigned=true;service_reject(fn()=>$service->refreshSignature($ledger->practice($id)),'partial archive does not activate');service_check($ledger->practice($id)['state']==='signature_pending','partial archive stays pending');$service->refreshSignature($ledger->practice($id));$service->refreshSignature($ledger->practice($id));service_check($archiveCalls===2,'verified archive is reused and missing proof recovered');
service_check(str_starts_with((string)$ledger->practice($id)['signed_pcloud_file_id'],'drive-file-'),'signed Drive ID survives account activation');
service_check($ledger->artifact($id,'signed_pdf')!==null&&$ledger->artifact($id,'signature_audit')!==null,'contract and proof archived before registration');
service_check($service->registrationAuthorization($ledger->practice($id))['qa']===false,'normal contracts cannot claim owner QA privileges');
$qaPractice=$ledger->practice($id);$qaPractice['contract_id']=27;$qaPractice['email']='a.tognassi@gmail.com';$qaPractice['snapshot']['contract_number']='TRB-QA-NONVALIDO-DDB600-20261003';
service_check($service->registrationAuthorization($qaPractice)['qa']===true,'exact signed owner DDB test receives isolated QA access');
foreach(['id','email','number','model'] as $scope){$foreign=$qaPractice;
 if($scope==='id')$foreign['contract_id']=28;
 if($scope==='email')$foreign['email']='other@example.invalid';
 if($scope==='number')$foreign['snapshot']['contract_number']='TRB-OTHER-600';
 if($scope==='model')$foreign['snapshot']['template_key']='ddb_csae_600';
 service_check($service->registrationAuthorization($foreign)['qa']===false,'owner DDB QA scope cannot extend to another '.$scope);
}
$unsignedQa=$qaPractice;$unsignedQa['state']='payment_pending';$unsignedQa['owner_approved_at']=null;$unsignedQa['signed_at']=null;$unsignedQa['signed_pcloud_file_id']=null;
service_reject(fn()=>$service->registrationAuthorization($unsignedQa),'owner DDB test still requires payment approval, signatures and archive');

service_check($ledger->reserveWelcome($id),'verified both signatures and archive permit one welcome');
service_check(!$ledger->reserveWelcome($id),'concurrent or repeat request cannot send another welcome');
$ledger->finishWelcome($id,'synthetic-receipt');service_check($ledger->welcomeStatus($id)['state']==='sent','welcome receipt persisted');
service_check(!$ledger->reserveWelcome($id),'confirmed delivery never repeats');
$db->exec('DELETE FROM onboarding_welcomes');service_check($ledger->reserveWelcome($id),'synthetic lost-reply reservation');$ledger->finishWelcome($id,null);
service_check($ledger->welcomeStatus($id)['state']==='uncertain'&&!$ledger->reserveWelcome($id),'lost send reply requires reconciliation instead of duplicate delivery');
$service->register($ledger->practice($id),1234);$service->register($ledger->practice($id),1234);service_reject(fn()=>$service->register($ledger->practice($id),4321),'another account cannot take the practice');service_check($ledger->portalPractice(1234,$snapshot['email'])['id']===$id,'correct portal identity bound');
service_check($ledger->access($id,$today)['allowed'],'services enabled only after final registration');
$ledger->cancel($id,$owner);service_check(!$ledger->access($id,$today)['allowed'],'cancellation suspends future services');
$freeSnapshot=OnboardingContractCatalog::model('trb_ccde')+['unsigned_document_sha256'=>str_repeat('b',64),'first_name'=>'Mario','last_name'=>'Rossi','email'=>'free@example.invalid','artist_folder_id'=>'drive-owner-free','contract_number'=>'QA-TRB'];
$free=$ledger->create(124,$freeSnapshot,gmdate('c',time()+86400));$freeId=$free['id'];$service->details($ledger->practice($freeId),$details);
service_check(!$ledger->reserveWelcome($freeId),'unsigned candidate cannot receive a welcome');
foreach(['identity_front','tax_front'] as $i=>$slot)$ledger->recordFile($freeId,$slot,['file_id'=>30+$i,'folder_id'=>333,'hash'=>'free-h'.$i,'name'=>$slot.'.pdf']);
$service->identity($ledger->practice($freeId));$service->processIdentity($freeId);$freeResult=$service->view($ledger->practice($freeId));service_check($freeResult['state']==='owner_review'&&$freeResult['selected_plan']['kind']==='free','TRB proceeds without asking for a commercial choice');
service_check(empty($ledger->details($freeId)['proposal_read_at']),'automatic TRB routing is not consent or signature');service_reject(fn()=>$service->checkout($ledger->practice($freeId)),'TRB cannot create payment orders');
service_reject(fn()=>$service->dispatchSignature($ledger->practice($freeId)),'owner approval still precedes the chargeable signature dossier');
$service->view($ledger->practice($freeId));service_check($ledger->practice($freeId)['state']==='owner_review','TRB refresh is idempotent');
echo "Onboarding orchestration, trusted receipts, one dossier, both signatures, archive and account binding verified.\n";

