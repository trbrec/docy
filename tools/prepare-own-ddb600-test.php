<?php
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();$stage='bootstrap';
set_exception_handler(static function()use(&$stage){while(ob_get_level())ob_end_clean();file_put_contents('php://stderr',"New QA contract preparation unconfirmed at ".$stage.".\n");exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');require_once $root.'/app/SubmissionRepository.php';require_once $root.'/app/OnboardingLedger.php';
$theme='/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy';if(trim(file_get_contents($theme.'/.trb-deployed-sha'))!=='09c6b725aca95c0642aebc3534455c61b0bbd3c6')exit(2);
$db=\TrbCrm\Database::connection();$owner=$db->query("SELECT id FROM users WHERE email='andrea.tognassi@trbrec.com' AND role='admin' AND is_active=1")->fetchColumn();if(!$owner)throw new RuntimeException();
$number='TRB-QA-NONVALIDO-DDB600-20261003';$q=$db->prepare('SELECT id FROM submissions WHERE contract_number=?');$q->execute([$number]);$id=(int)$q->fetchColumn();
$stage='prior-account-retired';$retired=new \TrbCrm\OnboardingLedger($db);$prior=$retired->forContract(26);if(!$prior||$prior['state']!=='cancelled'||!$retired->artifact($prior['id'],'signed_pdf')||!$retired->artifact($prior['id'],'signature_audit'))throw new RuntimeException();
$stage='new-test-candidate';
if(!$id){$q=$db->prepare('SELECT s.*,c.email,c.first_name,c.last_name FROM submissions s JOIN contacts c ON c.id=s.contact_id WHERE s.id=719');$q->execute();$old=$q->fetch();if(!$old||$old['email']!=='a.tognassi@gmail.com'||$old['first_name']!=='Andrea'||$old['last_name']!=='Tognassi')throw new RuntimeException();
$columns=$db->query('SHOW COLUMNS FROM submissions')->fetchAll(PDO::FETCH_COLUMN);$copy=array_intersect_key($old,array_flip($columns));unset($copy['id'],$copy['created_at'],$copy['updated_at']);
$copy['public_id']=strtoupper(substr(bin2hex(random_bytes(16)),0,26));$copy['source_tab']='QA_ONBOARDING';$copy['source_row']=202610031049;$copy['row_fingerprint']=hash('sha256',$number);$copy['received_at']=gmdate('Y-m-d H:i:s');$copy['status']='interested';$copy['assigned_user_id']=(int)$owner;$copy['contract_number']=$number;$copy['contract_type']='ddb_ccad_600';$copy['contract_sent_at']=null;$copy['accepted_at']=null;$copy['primary_submission_id']=null;$copy['is_legacy_processed']=0;$copy['legacy_style']=null;
$raw=json_decode($copy['raw_payload']??'{}',true)?:[];$raw['qa_onboarding']=true;$raw['qa_purpose']='Collaudo attivazione DDB da 600 euro con versamento e firma';$copy['raw_payload']=json_encode($raw,JSON_THROW_ON_ERROR);
$db->prepare('INSERT INTO submissions('.implode(',',array_keys($copy)).') VALUES('.implode(',',array_fill(0,count($copy),'?')).')')->execute(array_values($copy));$id=(int)$db->lastInsertId();
}
$stage='prepare-current-pdf';$repo=new \TrbCrm\SubmissionRepository($db);$result=$repo->prepareCandidateContractReview($id,['template_key'=>'ddb_ccad_600'],(int)$owner);$p=$result['preview'];
if(($p['attachment_ready']??false)!==true||($p['recipient']??'')!=='a.tognassi@gmail.com')throw new RuntimeException();
$s=$repo->find($id);$ledger=new \TrbCrm\OnboardingLedger($db);$practice=$ledger->forContract((int)$s['contract']['id']);$artifact=$ledger->artifact($practice['id'],'proposal');
$out=['success'=>true,'submission_id'=>$id,'contract_id'=>(int)$s['contract']['id'],'contract_number'=>$number,'pdf_file_id'=>$artifact['file_id'],'pdf_sha256'=>$artifact['sha256'],'current_source'=>hash_equals(\TrbCrm\OnboardingContractCatalog::model('ddb_ccad_600')['source_sha256'],$practice['snapshot']['source_sha256']),'three_formulas'=>count($practice['snapshot']['plans'])===3,'nominal_cents'=>$practice['snapshot']['nominal_cents'],'formulas'=>$practice['snapshot']['plans'],'sent'=>false];
while(ob_get_level())ob_end_clean();echo json_encode($out)."\n";
