<?php
/** Owner-authorized renewal of one unused, unsent draft. Never calls a send action. */
if(PHP_SAPI!=='cli')exit;ini_set('display_errors','0');ob_start();$stage='bootstrap';
set_exception_handler(static function()use(&$stage){while(ob_get_level())ob_end_clean();echo json_encode(['success'=>false,'stage'=>$stage])."\n";exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');
require $root.'/app/OnboardingContractWorkflow.php';
$db=\TrbCrm\Database::connection();$ledger=new \TrbCrm\OnboardingLedger($db);$target=null;
foreach($db->query('SELECT p.id,p.contract_id,c.submission_id FROM onboarding_practices p JOIN contracts c ON c.id=p.contract_id')->fetchAll() as $r)if(substr(hash('sha256',$r['id']),0,8)==='72186623')$target=$r;
if(!$target)throw new RuntimeException('Missing scoped draft');
$old=$ledger->practice($target['id']);if(($old['snapshot']['group_code']??'')!=='DDB12')throw new RuntimeException('Wrong model');
$q=$db->prepare('SELECT id FROM users WHERE role=\'admin\' AND is_active=1 AND LOWER(email)=?');$q->execute(['andrea.tognassi@trbrec.com']);$owner=(int)$q->fetchColumn();if(!$owner)throw new RuntimeException('Owner unavailable');
$q=$db->prepare('SELECT s.*,co.first_name,co.last_name,co.artist_name,co.email,ct.contract_number FROM submissions s JOIN contacts co ON co.id=s.contact_id JOIN contracts ct ON ct.id=? WHERE s.id=?');$q->execute([$target['contract_id'],$target['submission_id']]);$s=$q->fetch();
if(!$s||!\TrbCrm\OnboardingContractWorkflow::handles($s,$old['snapshot']['template_key']))throw new RuntimeException('Candidate state guard');
$q=$db->prepare('SELECT * FROM contracts WHERE submission_id=? ORDER BY id DESC LIMIT 1');$q->execute([$target['submission_id']]);$contract=$q->fetch();$contract['metadata']=json_decode($contract['metadata']??'{}',true);$s['contract']=$contract;
$stage='renew-draft';
if(!$old['cancelled_at']){
 if((int)$contract['id']!==(int)$old['contract_id'])throw new RuntimeException('Latest draft changed');
 if(!\TrbCrm\OnboardingContractWorkflow::renewDraft($db,$s,$old['snapshot']['template_key'],$owner))throw new RuntimeException('Renewal not needed');
 $q->execute([$target['submission_id']]);$contract=$q->fetch();$contract['metadata']=json_decode($contract['metadata']??'{}',true);
}else{
 if((int)($contract['metadata']['revision']['previous_contract_id']??0)!==(int)$old['contract_id'])throw new RuntimeException('Different revision');
}
if($contract['sent_at']||!in_array($contract['status'],['draft','generated','prepared'],true))throw new RuntimeException('New draft already sent');
$stage='prepare-current-pdf';$result=(new \TrbCrm\OnboardingRuntime($db))->prepare((int)$contract['id'],0,$old['snapshot']['template_key']);$current=$ledger->practice($result['id']);$artifact=$ledger->artifact($current['id'],'proposal');$model=\TrbCrm\OnboardingContractCatalog::model($current['snapshot']['template_key']);
if(!hash_equals($model['source_sha256'],$current['snapshot']['source_sha256'])||!preg_match('/^[a-f0-9]{64}$/D',$artifact['sha256']??''))throw new RuntimeException('Artifact/source guard');
$stage='save-preview';$s['contract']=$contract;$preview=\TrbCrm\OnboardingContractWorkflow::preview($db,$s,$current['snapshot']['template_key']);
$meta=$contract['metadata'];foreach(['subject','body','followup_subject','followup_body'] as $field)$meta[$field]=$preview[$field];$meta['preview_hash']=$preview['preview_token'];
$meta['onboarding']=['practice_id'=>$current['id'],'archive_provider'=>'google_drive','proposal_sha256'=>$artifact['sha256']];
$q=$db->prepare('UPDATE contracts SET document_url=?,document_sha256=?,document_mime=?,generated_at=UTC_TIMESTAMP(),metadata=? WHERE id=? AND sent_at IS NULL AND status IN (\'draft\',\'generated\',\'prepared\')');$q->execute([$result['invite_url'],$artifact['sha256'],'application/pdf',json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$contract['id']]);
if($q->rowCount()!==1)throw new RuntimeException('Save state changed');
$stage='verify';$q=$db->prepare('SELECT status,sent_at,document_sha256 FROM contracts WHERE id=?');$q->execute([(int)$contract['id']]);$saved=$q->fetch();
$checks=['source_current'=>hash_equals($model['source_sha256'],$current['snapshot']['source_sha256']),'proposal_ready'=>$preview['attachment_ready']===true,'old_practice_preserved'=>$ledger->practice($old['id'])['cancelled_at']!==null,'new_not_sent'=>empty($saved['sent_at']),'pdf_recorded'=>hash_equals($artifact['sha256'],$saved['document_sha256'])];
if(in_array(false,$checks,true))throw new RuntimeException('Final guard');while(ob_get_level())ob_end_clean();echo json_encode(['success'=>true,'checks'=>$checks,'emails_sent'=>0,'payments_created'=>0])."\n";
