<?php
declare(strict_types=1);
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingService.php';
use TrbCrm\OnboardingLedger;use TrbCrm\OnboardingService;use TrbCrm\OnboardingContractCatalog;
function identity_check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
function identity_reject(callable $fn,string $name):void{try{$fn();}catch(Throwable $e){return;}throw new RuntimeException($name);}
function good_fields():array{return ['identity_document'=>true,'tax_document'=>true,'legible'=>true,'tax_legible'=>true,'first_name'=>'Mario','last_name'=>'Rossi','birth_date'=>'1990-01-01','expiry_date'=>'2090-01-01','tax_first_name'=>'Mario','tax_last_name'=>'Rossi','tax_birth_date'=>'1990-01-01','tax_code'=>'RSSMRA90A01H501W'];}
function identity_fixture():array{
    $db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$ledger=new OnboardingLedger($db);$ledger->install();
    $snapshot=OnboardingContractCatalog::model('ddb_ccad_600')+['unsigned_document_sha256'=>str_repeat('a',64),'first_name'=>'Mario','last_name'=>'Rossi','artist_name'=>'Synthetic','email'=>'synthetic@example.invalid','artist_folder_id'=>'synthetic-archive','contract_number'=>'SYNTHETIC-DDB-600'];
    $id=$ledger->create(1,$snapshot,gmdate('c',time()+86400))['id'];
    $archive=new class{public int $reads=0;public bool $fail=false;public function identityDocument($file){$this->reads++;if($this->fail)throw new RuntimeException('Synthetic private archive outage');return ['mime'=>'image/png','data'=>'synthetic-only','name'=>$file['name']];}};
    $reader=new class{public int $calls=0;public array $fields=[];public bool $fail=false;public ?Closure $during=null;public function extract($docs){$this->calls++;identity_check(array_column($docs,'slot')===['identity_front','tax_front'],'Only requested fronts extracted');if($this->during)($this->during)();if($this->fail)throw new RuntimeException('Synthetic temporary reader failure');return $this->fields;}};$reader->fields=good_fields();
    $external=static function($p){throw new RuntimeException('Payments, signature and portal must never run during identity');};
    $service=new OnboardingService($ledger,$archive,$reader,$external,$external);
    $details=['privacy_acknowledged'=>true,'billing'=>['address_1'=>'Via synthetic 1','street'=>'Via synthetic','street_number'=>'1','city'=>'Roma','postcode'=>'00100','country'=>'IT','phone'=>'+393330000000'],'profile'=>['birth_date'=>'1990-01-01','document_expiry'=>'2090-01-01'],'tax_code'=>'RSSMRA90A01H501W'];$service->details($ledger->practice($id),$details);
    foreach(['identity_front','tax_front'] as $i=>$slot)$ledger->recordFile($id,$slot,['file_id'=>'synthetic-'.($i+1),'folder_id'=>'synthetic-folder','hash'=>'synthetic-hash-'.$i,'name'=>$slot.'.png']);
    return compact('db','ledger','service','reader','archive','id','details');
}
$f=identity_fixture();extract($f);$queued=$service->identity($ledger->practice($id));
identity_check($queued['identity']['status']==='queued'&&$reader->calls===0&&$archive->reads===0,'Public save acknowledges durable queue without archive or reader calls');
$service->identity($ledger->practice($id));$service->view($ledger->practice($id));identity_check($reader->calls===0,'Repeated save and poll never extract synchronously');
$claim=$ledger->claimIdentity($id);identity_check($claim!==null&&$ledger->claimIdentity($id)===null,'Concurrent workers cannot claim the same revision');
identity_check($ledger->identityVerification($id)['status']==='processing','Processing is visible after durable claim');
identity_check($ledger->identityVerification($id)['retry_after_seconds']>=200,'A duplicate cron waits for lease recovery instead of looping during the active reader');
$ledger->finishIdentity($claim,good_fields(),'2026-10-03');identity_check($ledger->identityVerification($id)['status']==='complete'&&$ledger->practice($id)['state']==='identity_matched','Only verified fields complete identity');
$service->identity($ledger->practice($id));identity_check($reader->calls===0,'Lost completion response cannot duplicate extraction');
$ack=$ledger->details($id)['privacy_acknowledged_at'];$service->details($ledger->practice($id),$details);identity_check($ledger->details($id)['privacy_acknowledged_at']===$ack,'Duplicate save after completion preserves the original acknowledgement and is idempotent');
foreach(['birth_date'=>'1991-01-01','document_expiry'=>'2091-01-01','document_number'=>'CA76543AB'] as $field=>$different){
    $changed=$details;$changed['profile'][$field]=$different;identity_reject(fn()=>$service->details($ledger->practice($id),$changed),'Verified '.$field.' cannot change after matching');
    identity_check($ledger->practice($id)['state']==='identity_matched'&&$ledger->identityResult($id)['status']==='matched','Rejected verified-profile edit retains the matched identity gate');
}
$service->choose($ledger->practice($id),'C',str_repeat('a',64),true);identity_check($ledger->practice($id)['state']==='payment_pending','Unchanged verified profile still allows normal formula selection');

$f=identity_fixture();extract($f);$reader->fields=array_replace(good_fields(),['identity_document'=>false,'tax_document'=>false]);
$service->identity($ledger->practice($id));$service->processIdentity($id);$v=$ledger->identityVerification($id);
identity_check($v['status']==='rejected'&&$v['reason']==='documents_required'&&$v['replace_slots']===['identity_front','tax_front'],'Unrelated screenshots with matching printed names and tax code are rejected as non-documents');
identity_check($ledger->practice($id)['state']==='identity_review'&&$ledger->practice($id)['selected_plan']===null,'Rejected uploads stay editable and cannot create a quota');
identity_reject(fn()=>$service->choose($ledger->practice($id),'C',str_repeat('a',64),true),'Wrong images cannot advance to a payment');
$service->identity($ledger->practice($id));$service->processIdentity($id);identity_check($reader->calls===1,'Same rejected revision is cached instead of running chargeable extraction again');
$taxBefore=$ledger->files($id)['tax_front'];$ledger->recordFile($id,'identity_front',['file_id'=>'synthetic-new-id','folder_id'=>'synthetic-folder','hash'=>'synthetic-new-hash','name'=>'identity.png']);
identity_check($ledger->identityVerification($id)===null&&$ledger->files($id)['tax_front']===$taxBefore,'Replacing one front clears stale rejection and preserves the other front');
$reader->fields=array_replace(good_fields(),['tax_document'=>false]);$service->identity($ledger->practice($id));$service->processIdentity($id);$v=$ledger->identityVerification($id);
identity_check($v['status']==='rejected'&&$v['replace_slots']===['tax_front'],'Only the remaining bad fiscal front is requested again');
$ledger->recordFile($id,'tax_front',['file_id'=>'synthetic-new-tax','folder_id'=>'synthetic-folder','hash'=>'synthetic-new-tax-hash','name'=>'tax.png']);$reader->fields=good_fields();$service->identity($ledger->practice($id));$service->processIdentity($id);
identity_check($ledger->practice($id)['state']==='identity_matched'&&$reader->calls===3,'Replacement succeeds on the original invitation without losing administrative data');

$f=identity_fixture();extract($f);$service->identity($ledger->practice($id));$claim=$ledger->claimIdentity($id);$oldTax=$ledger->files($id)['tax_front'];
$ledger->recordFile($id,'identity_front',['file_id'=>'synthetic-replacement','folder_id'=>'synthetic-folder','hash'=>'different','name'=>'new.png']);
identity_check(!$ledger->finishIdentity($claim,good_fields(),'2026-10-03')&&$ledger->identityResult($id)===[],'Late successful response cannot approve a replaced document revision');
identity_check($ledger->files($id)['tax_front']===$oldTax&&$ledger->details($id)['tax_code']===$details['tax_code'],'Stale completion preserves the unaffected front and saved details');
$service->identity($ledger->practice($id));$reader->during=function()use($service,$ledger,$id,$details){$new=$details;$new['tax_code']='RSSMRA90A01H501X';$service->details($ledger->practice($id),$new);};$service->processIdentity($id);
identity_check($ledger->identityResult($id)===[]&&$ledger->practice($id)['state']==='invited','Declared identity edit during extraction invalidates a formerly matching response');

$f=identity_fixture();extract($f);$service->identity($ledger->practice($id));$now=time();$first=$ledger->claimIdentity($id,$now);$reclaimed=$ledger->claimIdentity($id,$now+241);
identity_check($reclaimed!==null&&$reclaimed['claim_token']!==$first['claim_token'],'A worker that crashed is reclaimed after its lease');
identity_check(!$ledger->finishIdentity($first,good_fields(),'2026-10-03',$now+242),'Old worker completion is rejected after lease recovery');
identity_check($ledger->finishIdentity($reclaimed,good_fields(),'2026-10-03',$now+242),'Recovered worker can finish the exact unchanged revision');

$f=identity_fixture();extract($f);$service->identity($ledger->practice($id));$claim=$ledger->claimIdentity($id);$changed=$details;$changed['billing']['address_1']='Via synthetic 2';$changed['billing']['street_number']='2';$service->details($ledger->practice($id),$changed);
identity_check($ledger->finishIdentity($claim,good_fields(),'2026-10-03'),'Address-only changes do not discard valid identity extraction');

$f=identity_fixture();extract($f);$service->identity($ledger->practice($id));$claim=$ledger->claimIdentity($id);$ledger->cancel($id,['id'=>1,'role'=>'admin']);
identity_check(!$ledger->finishIdentity($claim,good_fields(),'2026-10-03')&&$ledger->identityResult($id)===[],'Cancellation invalidates an in-flight reader result');

$f=identity_fixture();extract($f);$reader->fields=['legible'=>true];$service->identity($ledger->practice($id));$service->processIdentity($id);$v=$ledger->identityVerification($id);
identity_check($v['status']==='retry_wait'&&$v['retryable']&&$v['replace_slots']===[]&&$ledger->identityResult($id)===[],'Malformed provider result is a temporary failure and never a document rejection or approval');
identity_check($ledger->claimIdentity($id)===null,'Provider retry respects backoff');

$f=identity_fixture();extract($f);$archive->fail=true;$service->identity($ledger->practice($id));$service->processIdentity($id);$v=$ledger->identityVerification($id);
identity_check($v['status']==='retry_wait'&&$reader->calls===0,'Private archive outage remains retryable without clearing files');
identity_check(!str_contains(json_encode($v),'Synthetic private'),'Candidate sees no raw provider exception');

$f=identity_fixture();extract($f);$service->identity($ledger->practice($id));$now=time();
for($attempt=1;$attempt<=3;$attempt++){$claim=$ledger->claimIdentity($id,$now);identity_check($claim!==null,'Due automatic attempt '.$attempt.' is claimable');$ledger->failIdentity($claim,$now);$v=$ledger->identityVerification($id,$now);$now+=(int)$v['retry_after_seconds'];}
identity_check($v['status']==='error'&&$v['retryable']&&$ledger->claimIdentity($id,$now)===null,'Automatic provider failures stop after three attempts with an explicit retryable error');
$ledger->queueIdentity($id,true,$now);$claim=$ledger->claimIdentity($id,$now);identity_check($claim!==null,'Candidate can explicitly retry a saved revision after cooldown');
$ledger->finishIdentity($claim,good_fields(),'2026-10-03',$now);identity_check($ledger->identityVerification($id,$now)['status']==='complete','Recovered provider completes the preserved documents');

$f=identity_fixture();extract($f);$ledger->recordIdentity($id,array_replace(good_fields(),['tax_legible'=>false]),'2026-10-03');$v=$service->view($ledger->practice($id))['identity_verification'];
identity_check($v['status']==='rejected'&&$v['replace_slots']===['tax_front'],'Legacy completed rejection resumes with clear replacement instructions');

$f=identity_fixture();extract($f);$service->identity($ledger->practice($id));$expired=$id;
$db->prepare('UPDATE onboarding_practices SET expires_at=? WHERE id=?')->execute([gmdate('c',time()-1),$expired]);
$snapshot=$ledger->practice($expired)['snapshot'];$snapshot['email']='new-synthetic@example.invalid';$snapshot['contract_number']='SYNTHETIC-NEW-QUEUE';
$fresh=$ledger->create(2,$snapshot,gmdate('c',time()+86400))['id'];$service->details($ledger->practice($fresh),$details);
foreach(['identity_front','tax_front'] as $i=>$slot)$ledger->recordFile($fresh,$slot,['file_id'=>'new-queue-'.($i+1),'folder_id'=>'synthetic-folder','hash'=>'new-hash-'.$i,'name'=>$slot.'.png']);
$service->identity($ledger->practice($fresh));$db->prepare('UPDATE onboarding_identity_jobs SET queued_at=? WHERE practice_id=?')->execute([gmdate('c',time()-20),$expired]);
$claim=$ledger->claimIdentity();identity_check($claim!==null&&$claim['practice_id']===$fresh,'An older expired queued practice is skipped and cannot starve a fresh job in the same sweep');
$v=$ledger->identityVerification($expired);identity_check($v['status']==='error'&&$v['reason']==='proposal_expired'&&!$v['retryable']&&$v['replace_slots']===[],'Expired proposal is a clear final business status rather than a provider retry');
identity_check($ledger->claimIdentity($expired)===null&&$reader->calls===0&&$archive->reads===0,'Expired queued job never reads the private archive or calls the reader');
identity_check($ledger->practice($expired)['cancelled_at']===null&&$ledger->files($expired)!==[]&&$ledger->details($expired)!==[],'Expiry preserves files, details and the existing cancellation state');
$ledger->finishIdentity($claim,good_fields(),'2026-10-03');identity_check($ledger->practice($fresh)['state']==='identity_matched','The unaffected current proposal completes normally after expired-job cleanup');
$f=identity_fixture();extract($f);$reader->fields=array_replace(good_fields(),['tax_document'=>false]);$service->identity($ledger->practice($id));$service->processIdentity($id);
$db->prepare('UPDATE onboarding_identity_jobs SET input_fingerprint=? WHERE practice_id=?')->execute([str_repeat('f',64),$id]);$v=$ledger->identityVerification($id);
identity_check($v['status']==='error'&&$v['reason']==='verification_updated'&&$v['replace_slots']===[],'An outdated reader rejection asks for verification of the saved documents instead of another upload');
$beforeFiles=$ledger->files($id);$reader->fields=good_fields();$service->identity($ledger->practice($id));$service->processIdentity($id);
identity_check($ledger->practice($id)['state']==='identity_matched'&&$ledger->files($id)===$beforeFiles&&$reader->calls===2,'The updated reader rechecks the original files and completes the unchanged invitation');
$db->prepare('UPDATE onboarding_identity_jobs SET input_fingerprint=? WHERE practice_id=?')->execute([str_repeat('e',64),$id]);
identity_check($ledger->identityVerification($id)['status']==='complete'&&$service->identity($ledger->practice($id))['identity']['status']==='complete'&&$reader->calls===2,'Reader upgrades preserve already verified identities without a duplicate read');
echo "Identity recovery verified: queue-only public requests, duplicate claims, invalid screenshots, one-front replacement, stale results, reader upgrades, crash lease, bounded retries, preserved details and strict payment gate.\n";
