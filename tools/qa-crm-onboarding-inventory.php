<?php
/** Read-only aggregate compatibility inventory. Never exports identities or practice identifiers. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');set_exception_handler(static function(){fwrite(STDERR,"Onboarding inventory unconfirmed.\n");exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';
require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');
require $root.'/app/OnboardingContractCatalog.php';
$db=\TrbCrm\Database::connection();
$exists=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='onboarding_practices'")->fetchColumn();
$states=[];$snapshots=0;$catalogMismatch=0;$pending=[];
if((int)$exists===1){
    foreach($db->query('SELECT state,COUNT(*) AS total FROM onboarding_practices GROUP BY state')->fetchAll(PDO::FETCH_ASSOC) as $row)$states[(string)$row['state']]=(int)$row['total'];
    foreach($db->query("SELECT id,snapshot,created_at,expires_at FROM onboarding_practices WHERE state NOT IN ('active','cancelled','expired')")->fetchAll(PDO::FETCH_ASSOC) as $practice){
        $snapshot=json_decode($practice['snapshot'],true,32,JSON_THROW_ON_ERROR);$snapshots++;$matched=false;
        foreach(\TrbCrm\OnboardingContractCatalog::all() as $model)if(($snapshot['template_key']??'')===$model['template_key']&&($snapshot['source_sha256']??'')===$model['source_sha256'])$matched=true;
        if(!$matched)$catalogMismatch++;
        $artifact=$db->prepare("SELECT metadata FROM onboarding_artifacts WHERE practice_id=? AND slot='proposal'");$artifact->execute([$practice['id']]);$proposal=json_decode((string)($artifact->fetchColumn()?:'null'),true,32,JSON_THROW_ON_ERROR);
        $expires=strtotime((string)$practice['expires_at']);
        $pending[]=['template_key'=>(string)($snapshot['template_key']??''),'source_sha256'=>(string)($snapshot['source_sha256']??''),'qa_flag'=>($snapshot['qa']??false)===true,'test_number_prefix'=>str_starts_with((string)($snapshot['contract_number']??''),'QA-'),'owner_test_recipient'=>strcasecmp((string)($snapshot['email']??''),'a.tognassi@gmail.com')===0,'created_day'=>substr((string)$practice['created_at'],0,10),'valid_expiry'=>$expires!==false,'expired_now'=>$expires!==false&&$expires<=time(),'proposal_registered'=>is_array($proposal),'proposal_sha_matches'=>is_array($proposal)&&preg_match('/^[a-f0-9]{64}$/D',(string)($proposal['sha256']??''))===1&&hash_equals((string)($snapshot['unsigned_document_sha256']??''),(string)$proposal['sha256'])];
    }
}
echo json_encode(['read_only'=>true,'practice_states'=>$states,'pending_snapshots'=>$snapshots,'pending_source_hash_mismatches'=>$catalogMismatch,'pending_classifications'=>$pending,'personal_data_exported'=>false],JSON_THROW_ON_ERROR);
