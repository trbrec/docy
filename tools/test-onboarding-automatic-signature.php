<?php
declare(strict_types=1);
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingService.php';
use TrbCrm\OnboardingLedger;use TrbCrm\OnboardingService;use TrbCrm\OnboardingContractCatalog;use TrbCrm\OnboardingPolicy;
function auto_check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
function auto_reject(callable $call,string $name):void{try{$call();}catch(Throwable $e){return;}throw new RuntimeException($name);}
function auto_json(array $a):string{return json_encode($a,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
$today=(new DateTimeImmutable('today',new DateTimeZone('Europe/Rome')))->format('Y-m-d');$flows=0;$paidFlows=0;
foreach(OnboardingContractCatalog::all() as $model)foreach($model['plans'] as $key=>$plan){
    $db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$ledger=new OnboardingLedger($db);$ledger->install();
    $db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,role TEXT,email TEXT,is_active INTEGER); INSERT INTO users VALUES(1,'admin','andrea.tognassi@trbrec.com',1)");
    $snapshot=$model+['unsigned_document_sha256'=>str_repeat('a',64),'first_name'=>'Mario','last_name'=>'Rossi','artist_name'=>'Synthetic artist','email'=>'artist@example.invalid','artist_folder_id'=>'synthetic-private-drive','contract_number'=>'SYNTHETIC-ONLY'];
    $proposal=['file_id'=>'synthetic-proposal','folder_id'=>'synthetic-private-drive','artist_folder_id'=>'synthetic-private-drive','sha256'=>str_repeat('a',64),'hash'=>'synthetic-proof'];
    $created=$ledger->create(1,$snapshot,gmdate('c',time()+86400),null,null,$proposal);$id=$created['id'];$order=null;$paid=false;$captured=true;$badProof=[];$dossiers=0;$loseReply=false;$complete=false;$company=false;$archiveFailure=false;$archiveCalls=0;
    $archive=new class{public int $sequence=0;public bool $failAudit=false;public function createUpload($folder,$id,$slot){return ['folder_id'=>'synthetic-slot-'.(++$this->sequence),'upload_link_id'=>$this->sequence,'slot'=>$slot,'expires_at'=>gmdate('c',time()+3600)];}public function verifyArtifact($grant,$sha,$folder){if($grant['slot']==='signature_audit'&&$this->failAudit){$this->failAudit=false;throw new RuntimeException('Synthetic archive interruption');}return ['file_id'=>'synthetic-file-'.$grant['upload_link_id'],'folder_id'=>$grant['folder_id'],'artist_folder_id'=>$folder,'sha256'=>$sha,'hash'=>'synthetic-hash'];}public function url($file,$folder,$hash){return 'https://example.invalid/synthetic-document';}};
    $reader=new class{public function extract($docs){auto_check(array_column($docs,'slot')===['identity_front','tax_front'],'Only two document fronts verified');return ['legible'=>true,'first_name'=>'Mario','last_name'=>'Rossi','birth_date'=>'1990-01-01','expiry_date'=>'2090-01-01','tax_legible'=>true,'tax_first_name'=>'Mario','tax_last_name'=>'Rossi','tax_birth_date'=>'1990-01-01','tax_code'=>'RSSMRA90A01H501W'];}};
    $portal=function($p)use(&$order,&$paid,&$captured,&$badProof,$snapshot,$id,$today){
        if($p['action']==='store_order'){$order=$p;return ['order_id'=>101,'checkout_url'=>'https://store.trbrec.com/checkout/order-pay/101/','paid'=>false];}
        if($p['action']==='store_status')return array_replace(['practice_id'=>$id,'number'=>1,'snapshot_sha256'=>$order['snapshot_sha256'],'email'=>$snapshot['email'],'provider'=>'woocommerce','transaction_id'=>'order:101:synthetic-capture','amount_cents'=>$order['amount_cents'],'currency'=>'EUR','paid_on'=>$today,'status'=>$paid?'confirmed':'pending','captured'=>$captured&&$paid],$badProof);
        if($p['action']==='activate_account')return ['activated'=>true,'practice_id'=>$id,'portal_user_id'=>$p['portal_user_id']];
        throw new RuntimeException('Unexpected synthetic portal action');
    };
    $script=function($p)use(&$dossiers,&$loseReply,&$complete,&$company,&$archiveCalls,$plan,$today,$id){
        switch($p['action']){
            case 'crm_onboarding_document':auto_check($p['phase']==='final'&&$p['appendix']['plan']===$plan,'Final PDF retains selected contractual formula');auto_check($p['appendix']['first_payment_date']===($plan['kind']==='free'?null:$today),'Calendar is anchored to confirmed first payment');auto_check($p['appendix']['schedule']===($plan['kind']==='free'?[]:OnboardingPolicy::schedule($plan,$today)),'Every contracted installment reaches final PDF');return ['sha256'=>str_repeat('b',64),'drive_pdf_id'=>'synthetic-final'];
            case 'crm_onboarding_signature':$dossiers++;auto_check($p['owner_approved']===true&&$p['document_sha256']===str_repeat('b',64),'Signature uses approved verified final PDF');if($loseReply){$loseReply=false;throw new RuntimeException('Synthetic provider reply lost after dossier creation');}return ['dossier_id'=>'123456'];
            case 'crm_onboarding_signature_status':return ['dossier_id'=>'123456','status'=>$complete?'completed':'pending','artist_signed'=>$complete,'company_signed'=>$company];
            case 'crm_onboarding_signed_archive':$archiveCalls++;return ['signed_pdf_sha256'=>str_repeat('c',64),'signature_audit_sha256'=>str_repeat('d',64)];
        }throw new RuntimeException('Unexpected synthetic signature action');
    };
    $service=new OnboardingService($ledger,$archive,$reader,$portal,$script);
    auto_check($ledger->automaticSignatureOwner($id)===null,'Prepared but unsent proposal has no dispatch authority');
    $event='onboarding:'.$id.':proposal-email';$authority=['automatic_signature'=>true,'owner_id'=>1,'contract_id'=>1,'gmail_message_id'=>'synthetic-gmail-receipt','message_id'=>'<synthetic@example.invalid>','document_sha256'=>$proposal['sha256'],'snapshot_sha256'=>hash('sha256',auto_json($snapshot))];
    $db->prepare('INSERT INTO onboarding_events(event_key,practice_id,kind,payload,status,created_at) VALUES(?,?,?,?,?,?)')->execute([$event,$id,'proposal_email',auto_json($authority),'dispatching',gmdate('c')]);
    auto_check($ledger->automaticSignatureOwner($id)===null,'Unconfirmed proposal send cannot authorize a chargeable signature');
    $db->prepare("UPDATE onboarding_events SET status='completed' WHERE event_key=?")->execute([$event]);
    foreach(['owner_id'=>2,'contract_id'=>2,'document_sha256'=>str_repeat('f',64),'snapshot_sha256'=>str_repeat('f',64),'gmail_message_id'=>'','automatic_signature'=>false] as $field=>$bad){$db->prepare('UPDATE onboarding_events SET payload=? WHERE event_key=?')->execute([auto_json(array_replace($authority,[$field=>$bad])),$event]);auto_check($ledger->automaticSignatureOwner($id)===null,'Foreign or incomplete delivery authorization rejected: '.$field);}
    $db->prepare('UPDATE onboarding_events SET payload=? WHERE event_key=?')->execute([auto_json($authority),$event]);
    $db->exec('UPDATE users SET is_active=0');auto_check($ledger->automaticSignatureOwner($id)===null,'Disabled owner cannot authorize automatic dispatch');$db->exec("UPDATE users SET is_active=1,role='operator'");auto_check($ledger->automaticSignatureOwner($id)===null,'Operator cannot authorize dispatch');$db->exec("UPDATE users SET role='admin'");
    auto_check($ledger->automaticSignatureOwner($id)!==null,'Confirmed reviewed owner delivery binds exact proposal and snapshot');
    $service->advanceSignature($ledger->practice($id));auto_check($dossiers===0,'Identity review precedes automatic signature');
    $service->details($ledger->practice($id),['privacy_acknowledged'=>true,'billing'=>['address_1'=>'Via synthetic 1','street'=>'Via synthetic','street_number'=>'1','city'=>'Roma','postcode'=>'00100','country'=>'IT','phone'=>'+393330000000'],'tax_code'=>'RSSMRA90A01H501W']);
    foreach(['identity_front','tax_front'] as $i=>$slot)$ledger->recordFile($id,$slot,['file_id'=>10+$i,'folder_id'=>33,'hash'=>'synthetic-'.$i,'name'=>$slot.'.pdf']);
    $service->identity($ledger->practice($id));
    if($plan['kind']!=='free'){
        $service->advanceSignature($ledger->practice($id));auto_check($dossiers===0,'Candidate must choose the formula before dispatch');
        $service->choose($ledger->practice($id),$key,$proposal['sha256'],true);$service->checkout($ledger->practice($id));auto_check($order['amount_cents']===$plan['amounts_cents'][0],'Exact first quota reaches merchant');
        $service->refreshPayments($id);$service->advanceSignature($ledger->practice($id));auto_check($dossiers===0&&$ledger->practice($id)['first_payment_date']===null,'Pending or failed payment does not dispatch');
        $paid=true;$captured=false;auto_reject(fn()=>$service->refreshPayments($id),'Status without provider capture cannot advance');auto_check($ledger->practice($id)['first_payment_date']===null,'Missing capture cannot write accounting');$captured=true;
        foreach(['email'=>'other@example.invalid','snapshot_sha256'=>str_repeat('f',64),'amount_cents'=>$order['amount_cents']-1,'currency'=>'USD','practice_id'=>str_repeat('f',32),'number'=>2] as $field=>$bad){$badProof=[$field=>$bad];auto_reject(fn()=>$service->refreshPayments($id),'Foreign or wrong receipt rejected: '.$field);auto_check($ledger->practice($id)['first_payment_date']===null,'Invalid receipt does not open signature');}
        $badProof=[];$service->refreshPayments($id);$service->refreshPayments($id);auto_check((int)$db->query('SELECT COUNT(*) FROM onboarding_payments')->fetchColumn()===1,'Repeated merchant confirmations are counted once');
        $service->checkout($ledger->practice($id));auto_check($order['number']===1,'No new order for an already paid current quota');$paidFlows++;
    }
    $ledger->paymentHold($id,'synthetic-hold',true);auto_reject(fn()=>$service->advanceSignature($ledger->practice($id)),'Administrative hold prevents dispatch');auto_check($dossiers===0,'No chargeable dossier while held');$ledger->paymentHold($id,'synthetic-hold',false);
    $loseReply=$plan['kind']==='single';
    if($loseReply){auto_reject(fn()=>$service->advanceSignature($ledger->practice($id)),'Lost provider response is surfaced');auto_check($ledger->practice($id)['state']==='signature_pending'&&$service->view($ledger->practice($id))['signature_email']===null,'Uncertain dispatch is durable and not presented as an email');$service->advanceSignature($ledger->practice($id));$service->refreshSignature($ledger->practice($id));}
    else $service->advanceSignature($ledger->practice($id));
    $service->advanceSignature($ledger->practice($id));auto_check($dossiers===1&&$ledger->practice($id)['state']==='signature_pending','One dossier after payment without a second CRM approval or repeated dispatch');
    auto_check(!$ledger->reserveWelcome($id),'No welcome before both signatures and archive');$complete=true;
    auto_reject(fn()=>$service->refreshSignature($ledger->practice($id)),'Artist signature without company signature cannot activate');auto_check(!$ledger->reserveWelcome($id),'Missing counter-signature blocks welcome');$company=true;$archive->failAudit=true;
    auto_reject(fn()=>$service->refreshSignature($ledger->practice($id)),'Incomplete signed archive blocks activation');auto_check($ledger->practice($id)['state']==='signature_pending'&&!$ledger->reserveWelcome($id),'No access or welcome after partial archive');
    $service->refreshSignature($ledger->practice($id));$service->refreshSignature($ledger->practice($id));auto_check($ledger->practice($id)['state']==='activation_ready'&&$archiveCalls===2,'Only missing signature audit recovered, then activation ready');
    auto_check($ledger->reserveWelcome($id)&&!$ledger->reserveWelcome($id),'Exactly one welcome can be reserved');$ledger->finishWelcome($id,'synthetic-welcome');
    $service->register($ledger->practice($id),123);auto_check($ledger->practice($id)['state']==='active'&&$ledger->access($id,$today)['allowed'],'Bound account gets access after complete signed archive');
    auto_reject(fn()=>$service->register($ledger->practice($id),124),'Second account cannot take a signed practice');auto_check($dossiers===1,'Registration never sends a second signature dossier');$flows++;
}
auto_check($flows===26&&$paidFlows===25,'Every plan of all ten contract models exercised');
echo "All 26 contractual flows (25 paid) verified: reviewed owner delivery, exact capture, automatic one-time signature, two signatures, partial archive recovery, welcome and account activation.\n";
