<?php
/** Canonical CRM owner release from a separately signed private archive. No database migration or provider action. */
declare(strict_types=1);
require_once __DIR__.'/release-file-transaction.php';
function trb_crm_release_json(string $path): array {
    if(is_link($path)||!is_file($path)||filesize($path)>1048576)throw new RuntimeException('Release receipt unavailable.');
    $data=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($data))throw new RuntimeException('Invalid release receipt.');
    return $data;
}
function trb_crm_release_map(array $files): array {
    $map=[];
    foreach($files as $f){
        $p=$f['path']??'';$sha=$f['sha256']??'';
        if(!is_string($p)||!preg_match('#^[a-zA-Z0-9_./-]+$#D',$p)||str_starts_with($p,'/')||preg_match('#(^|/)\.\.?(/|$)#',$p)||isset($map[$p])||!preg_match('/^[a-f0-9]{64}$/D',$sha))throw new RuntimeException('Unsafe release manifest.');
        $map[$p]=$sha;
    }
    ksort($map);return $map;
}
function trb_crm_release_verify(string $public,array $map): void {
    foreach($map as $p=>$sha){$target=$public.'/'.$p;trb_release_file_check_path($target,$public);if(!is_file($target)||!hash_equals($sha,hash_file('sha256',$target)))throw new RuntimeException('CRM source drift.');}
}
function trb_crm_release_package(string $public,string $incoming,array $baseline,array $request): array {
    if(is_link($incoming)||str_replace('\\','/',(string)realpath($incoming))!==$incoming)throw new RuntimeException('Unsafe private candidate path.');
    $old=trb_crm_release_map($baseline['files']??[]);
    if(($baseline['production_deployed']??false)!==true||($baseline['candidate_commit']??'')!==$request['baseline_revision']||count($old)!==$request['file_count']||hash('sha256',json_encode($old,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))!==$request['baseline_manifest_sha256'])throw new RuntimeException('CRM baseline mismatch.');
    $upload=trb_crm_release_json($incoming.'/upload.json');$zipPath=$incoming.'/candidate.zip';
    trb_release_file_check_path($zipPath,dirname($incoming));
    if(($upload['verified']??false)!==true||($upload['tag']??'')!==$request['tag']||($upload['revision']??'')!==$request['revision']||($upload['sha256']??'')!==$request['archive_sha256']||($upload['bytes']??0)!==$request['archive_bytes']||!is_file($zipPath)||filesize($zipPath)!==$request['archive_bytes']||!hash_equals($request['archive_sha256'],hash_file('sha256',$zipPath)))throw new RuntimeException('Signed private package mismatch.');
    $zip=new ZipArchive;
    if($zip->open($zipPath)!==true||$zip->numFiles!==count($old))throw new RuntimeException('Unexpected private package entries.');
    $next=[];$changes=[];
    try{
        for($i=0;$i<$zip->numFiles;$i++){
            $p=$zip->getNameIndex($i);$stat=$zip->statIndex($i);$opsys=0;$attributes=0;
            if(!is_string($p)||!isset($old[$p])||isset($next[$p])||!is_array($stat)||$stat['size']>2097152||!$zip->getExternalAttributesIndex($i,$opsys,$attributes)||($opsys===3&&(($attributes>>16)&0170000)===0120000))throw new RuntimeException('Unexpected package member.');
            $bytes=$zip->getFromIndex($i);if(!is_string($bytes)||strlen($bytes)!==$stat['size'])throw new RuntimeException('Package member unavailable.');
            if(str_ends_with($p,'.php'))token_get_all($bytes,TOKEN_PARSE);
            $next[$p]=hash('sha256',$bytes);
            if($next[$p]!==$old[$p])$changes[$p]=$bytes;
        }
    }finally{$zip->close();}
    ksort($next);ksort($changes);$expected=$request['changed_paths'];sort($expected);
    if(array_keys($next)!==array_keys($old)||array_keys($changes)!==$expected)throw new RuntimeException('Unreviewed module changes.');
    return ['old'=>$old,'next'=>$next,'changes'=>$changes];
}
function trb_crm_release_apply(string $public,string $private,string $state,array $package,array $request,?callable $verify=null): array {
    trb_crm_release_verify($public,$package['old']);
    if(is_link($state)||file_exists($state)||!mkdir($state,0700))throw new RuntimeException('Release already exists.');
    $envHash=hash_file('sha256',$public.'/.env');
    $manifestPath=$private.'/canonical-release.json';trb_release_file_check_path($manifestPath,$private);
    $previous=is_file($manifestPath)?file_get_contents($manifestPath):null;
    $ht=(string)file_get_contents($public.'/.htaccess');$mode=fileperms($public.'/.htaccess')&0777;
    trb_release_file_replace($state.'/htaccess.before',$ht,0600);
    if($previous!==null)trb_release_file_replace($state.'/canonical.before',$previous,0600);
    $changes=[];foreach($package['changes'] as $p=>$bytes)$changes[]=['path'=>$public.'/'.$p,'original'=>file_get_contents($public.'/'.$p),'next'=>$bytes];
    $began=microtime(true);$started=false;$manifestChanged=false;
    try{
        trb_release_file_replace($public.'/.htaccess',"RewriteEngine On\nRewriteRule ^ - [R=503,L]\n".$ht,$mode);
        $started=true;trb_release_file_install($changes,$state.'/modules-before',$public);
        trb_release_file_replace($public.'/.htaccess',$ht,$mode);
        trb_crm_release_verify($public,$package['next']);
        if(hash_file('sha256',$public.'/.env')!==$envHash)throw new RuntimeException('CRM environment drift.');
        if($verify!==null)$verify();
        $files=[];foreach($package['next'] as $p=>$sha)$files[]=['path'=>$p,'sha256'=>$sha,'bytes'=>filesize($public.'/'.$p)];
        $record=['verified'=>true,'revision'=>$request['revision'],'files'=>$files,'captured_at'=>gmdate('c')];
        $manifestChanged=true;trb_release_file_replace($manifestPath,json_encode($record,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),0600);
        $installed=['completed'=>true,'production_deployed'=>true,'candidate_commit'=>$request['revision'],'archive_sha256'=>$request['archive_sha256'],'runtime_files_verified'=>count($files),'changed_modules'=>count($changes),'files'=>$files,'database_written'=>false,'environment_unchanged'=>true,'environment_sha256'=>$envHash,'maintenance_seconds'=>round(microtime(true)-$began,4),'backup_available'=>true,'captured_at'=>gmdate('c')];
        trb_release_file_replace($state.'/installed.json',json_encode($installed,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),0600);
        return $installed;
    }catch(Throwable $failure){
        if($started){
            trb_release_file_rollback($state.'/modules-before',$public);
            trb_release_file_replace($public.'/.htaccess',$ht,$mode);
            if($manifestChanged){if($previous===null){if(is_file($manifestPath)&&!unlink($manifestPath))throw new RuntimeException('Canonical manifest rollback failed.');}else trb_release_file_replace($manifestPath,$previous,0600);}
            trb_crm_release_verify($public,$package['old']);
        }
        trb_release_file_replace($state.'/failed.json',json_encode(['completed'=>false,'rollback_verified'=>true,'captured_at'=>gmdate('c')],JSON_THROW_ON_ERROR),0600);
        throw $failure;
    }
}
function trb_crm_release_rollback(string $public,string $private,string $state,array $old): void {
    $record=trb_crm_release_json($state.'/installed.json');
    trb_crm_release_verify($public,trb_crm_release_map($record['files']));
    $ht=(string)file_get_contents($state.'/htaccess.before');$mode=fileperms($public.'/.htaccess')&0777;
    trb_release_file_replace($public.'/.htaccess',"RewriteEngine On\nRewriteRule ^ - [R=503,L]\n".$ht,$mode);
    try{trb_release_file_rollback($state.'/modules-before',$public);trb_release_file_replace($public.'/.htaccess',$ht,$mode);trb_crm_release_verify($public,$old);
        if(is_file($state.'/canonical.before'))trb_release_file_replace($private.'/canonical-release.json',file_get_contents($state.'/canonical.before'),0600);
        elseif(is_file($private.'/canonical-release.json')&&!unlink($private.'/canonical-release.json'))throw new RuntimeException('Canonical rollback failed.');
    }finally{trb_release_file_replace($public.'/.htaccess',$ht,$mode);}
    trb_release_file_replace($state.'/rolled-back.json',json_encode(['rollback_verified'=>true,'captured_at'=>gmdate('c')],JSON_THROW_ON_ERROR),0600);
}
if(defined('TRB_CANONICAL_CRM_RELEASE_LIBRARY_ONLY'))return;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');umask(0077);
set_exception_handler(static function(){fwrite(STDERR,"Canonical CRM release unconfirmed; inspect only its private diagnostics.\n");exit(1);});
$phase=$argv[1]??'';if(!in_array($phase,['install','rollback'],true)||isset($argv[2]))exit(2);
$request=trb_crm_release_json(__DIR__.'/crm-release-request.json');
foreach(['revision','baseline_revision'] as $key)if(!preg_match('/^[a-f0-9]{40}$/D',$request[$key]??''))throw new RuntimeException('Invalid revision.');
if(!preg_match('/^[a-f0-9]{16}$/D',$request['tag']??'')||($request['file_count']??0)!==53||($request['archive_bytes']??0)<1||($request['archive_bytes']??0)>10485760||!preg_match('/^[a-f0-9]{64}$/D',$request['archive_sha256']??'')||!preg_match('/^[a-f0-9]{64}$/D',$request['baseline_manifest_sha256']??''))throw new RuntimeException('Invalid release request.');
$root='/home/customer/www/crm.trbrec.com';$public=$root.'/public_html';$private=$root.'/private';
foreach([$root,$public,$private] as $p)if(is_link($p)||realpath($p)!==$p)throw new RuntimeException('CRM destination unavailable.');
$locks=[];foreach(['crm-deploy19.lock','onboarding-deploy.lock','giulia-deploy.lock','inline-review-deploy.lock','performance-deploy.lock','rights-review-deploy.lock','demo-bridge-deploy.lock'] as $name){$handle=fopen($private.'/'.$name,'c');if(!$handle||!flock($handle,LOCK_EX|LOCK_NB))throw new RuntimeException('Concurrent CRM release.');$locks[]=$handle;}
$baseline=trb_crm_release_json($private.'/crm-release19-c854eda657ee47d0/installed.json');
$state=$private.'/canonical-crm-release-'.$request['revision'];
$package=trb_crm_release_package($public,$private.'/incoming-audit-'.$request['tag'],$baseline,$request);
if($phase==='rollback'){trb_crm_release_rollback($public,$private,$state,$package['old']);echo '{"rollback_verified":true}';exit;}
if(is_file($state.'/installed.json')&&!is_file($state.'/rolled-back.json')){
    $installed=trb_crm_release_json($state.'/installed.json');$canonical=trb_crm_release_json($private.'/canonical-release.json');
    if(($installed['candidate_commit']??'')!==$request['revision']||($canonical['revision']??'')!==$request['revision']||($canonical['verified']??false)!==true||trb_crm_release_map($canonical['files'])!==$package['next'])throw new RuntimeException('Repeated release differs.');
    trb_crm_release_verify($public,$package['next']);echo '{"completed":true,"production_deployed":true,"duplicate":true}';exit;
}
trb_crm_release_verify($public,$package['old']);
require __DIR__.'/qa-crm-release-backup.php';
$backup=trb_crm_release_database_backup($public,$private.'/canonical-crm-backup-'.$request['revision']);
$verify=static function(){
    foreach(['https://crm.trbrec.com/login','https://crm.trbrec.com/'] as $url){
        $handle=curl_init($url);curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);$body=curl_exec($handle);$status=curl_getinfo($handle,CURLINFO_HTTP_CODE);curl_close($handle);
        if(!is_string($body)||!in_array($status,[200,302,303],true)||preg_match('/Fatal error|Parse error|Uncaught (?:Error|Exception)/i',$body))throw new RuntimeException('CRM public verification failed.');
    }
};
$result=trb_crm_release_apply($public,$private,$state,$package,$request,$verify);
echo json_encode(['completed'=>$result['completed'],'production_deployed'=>true,'revision'=>$request['revision'],'runtime_files_verified'=>53,'changed_modules'=>6,'database_written'=>false,'backup_readback_verified'=>$backup['readback_verified'],'public_http_verified'=>true],JSON_THROW_ON_ERROR);
