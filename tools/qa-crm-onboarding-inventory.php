<?php
/** Read-only aggregate compatibility inventory. Never exports identities or practice identifiers. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');set_exception_handler(static function(){fwrite(STDERR,"Onboarding inventory unconfirmed.\n");exit(1);});
$root='/home/customer/www/crm.trbrec.com/public_html';
require $root.'/app/Core.php';\TrbCrm\Env::load($root.'/.env');
require $root.'/app/OnboardingContractCatalog.php';
$db=\TrbCrm\Database::connection();
$exists=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='onboarding_practices'")->fetchColumn();
$states=[];$snapshots=0;$catalogMismatch=0;
if((int)$exists===1){
    foreach($db->query('SELECT state,COUNT(*) AS total FROM onboarding_practices GROUP BY state')->fetchAll(PDO::FETCH_ASSOC) as $row)$states[(string)$row['state']]=(int)$row['total'];
    foreach($db->query("SELECT snapshot FROM onboarding_practices WHERE state NOT IN ('active','cancelled','expired')")->fetchAll(PDO::FETCH_COLUMN) as $raw){
        $snapshot=json_decode($raw,true,32,JSON_THROW_ON_ERROR);$snapshots++;$matched=false;
        foreach(\TrbCrm\OnboardingContractCatalog::all() as $model)if(($snapshot['template_key']??'')===$model['template_key']&&($snapshot['source_sha256']??'')===$model['source_sha256'])$matched=true;
        if(!$matched)$catalogMismatch++;
    }
}
echo json_encode(['read_only'=>true,'practice_states'=>$states,'pending_snapshots'=>$snapshots,'pending_source_hash_mismatches'=>$catalogMismatch,'personal_data_exported'=>false],JSON_THROW_ON_ERROR);
